<?php
/**
 * Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - Servicio de Encriptación
 *
 * Usa libsodium (AES-256-GCM equivalente) para encriptar datos sensibles.
 * La clave se obtiene de la variable de entorno ENCRYPTION_KEY.
 */

class EncryptionService
{
    private string $key;

    public function __construct()
    {
        $envKey = getenv('ENCRYPTION_KEY');
        if (!$envKey || strlen($envKey) < 16) {
            throw new RuntimeException('ENCRYPTION_KEY no configurada o muy corta (mín. 16 chars)');
        }
        $this->key = sodium_crypto_generichash($envKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(?string $plaintext): ?array
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return ['ciphertext' => $ciphertext, 'nonce' => $nonce];
    }

    public function decrypt(?string $ciphertext, ?string $nonce): ?string
    {
        if ($ciphertext === null || $nonce === null) {
            return null;
        }
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if ($plaintext === false) {
            error_log("[Encriptación] Error al desencriptar: clave incorrecta o datos corruptos.");
            return '[Error de desencriptación]';
        }
        return $plaintext;
    }

    public function encryptForDb(?string $plaintext): array
    {
        if ($plaintext === null || $plaintext === '') {
            return ['encrypted' => null, 'nonce' => null];
        }
        $result = $this->encrypt($plaintext);
        return ['encrypted' => $result['ciphertext'], 'nonce' => $result['nonce']];
    }

    /**
     * Genera hash determinístico para búsqueda/comparación de texto encriptado.
     * HMAC-SHA256 con clave derivada. No permite recuperar el valor original.
     */
    public function computeSearchHash(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $plaintext)), 'UTF-8');
        $hmacKey = sodium_crypto_generichash($this->key . ':searchhash', '', 32);
        return hash_hmac('sha256', $normalized, $hmacKey);
    }

    /**
     * Normaliza y tokeniza un campo de texto para detección de conflictos.
     * Devuelve: texto completo normalizado + cada palabra significativa (≥3 chars, sin stop-words).
     */
    private function tokenizeField(string $text): array
    {
        static $stopWords = ['de','del','la','el','los','las','y','e','o','a','en','con','por','para','que','al','un','una','su','sus','se'];
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)), 'UTF-8');
        $normalized = strtr($normalized, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $tokens  = [$normalized];
        $words   = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && !in_array($word, $stopWords, true)) {
                $tokens[] = $word;
            }
        }
        return array_values(array_unique($tokens));
    }

    /**
     * Genera los HMACs de todos los tokens del acusado para almacenar con la denuncia.
     * @param  array<string|null> $fields  Campos de texto plano del acusado
     * @return array<string>               Array de HMACs únicos
     */
    public function computeAccusedConflictTokens(array $fields): array
    {
        $hmacs = [];
        foreach ($fields as $value) {
            if ($value === null || $value === '') continue;
            foreach ($this->tokenizeField($value) as $token) {
                $hmacs[] = $this->computeSearchHash($token);
            }
        }
        return array_values(array_unique(array_filter($hmacs)));
    }

    /**
     * Genera los HMACs de identidad de un investigador para comparar contra los tokens del acusado.
     * Incluye: nombre completo, nombre, apellido(s), combinaciones nombre+apellido,
     *          cargo completo, palabras del cargo, departamento completo, palabras del departamento.
     * @return array<string> Array de HMACs únicos del investigador
     */
    public function computeInvestigatorTokens(string $name, ?string $position, ?string $department): array
    {
        $hmacs = [];

        foreach ($this->tokenizeField($name) as $t) {
            $hmacs[] = $this->computeSearchHash($t);
        }

        $norm  = strtr(mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)), 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $words = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY);
        $n     = count($words);
        if ($n >= 2) {
            $primerNombre    = $words[0];
            $primerApellido  = $n >= 3 ? $words[$n - 2] : $words[1];
            $segundoApellido = $n >= 3 ? $words[$n - 1] : null;
            $hmacs[] = $this->computeSearchHash("$primerNombre $primerApellido");
            if ($segundoApellido) {
                $hmacs[] = $this->computeSearchHash("$primerNombre $primerApellido $segundoApellido");
                $hmacs[] = $this->computeSearchHash("$primerApellido $segundoApellido");
            }
        }

        if (!empty($position)) {
            foreach ($this->tokenizeField($position) as $t) {
                $hmacs[] = $this->computeSearchHash($t);
            }
        }

        if (!empty($department)) {
            foreach ($this->tokenizeField($department) as $t) {
                $hmacs[] = $this->computeSearchHash($t);
            }
        }

        return array_values(array_unique(array_filter($hmacs)));
    }
}

/** Singleton de servicio de encriptación */
function getEncryptionService(): EncryptionService
{
    static $instance = null;
    if ($instance === null) {
        $instance = new EncryptionService();
    }
    return $instance;
}
