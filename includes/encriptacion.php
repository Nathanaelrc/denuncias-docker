<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Servicio de Encriptación
 * 
 * Usa libsodium (AES-256-GCM equivalente) para encriptar datos sensibles.
 * Solo los usuarios admin e investigador pueden desencriptar a través de la app.
 * 
 * La clave se obtiene de la variable de entorno ENCRYPTION_KEY.
 * Si alguien accede directamente a la BD, verá datos binarios ilegibles.
 */

class EncryptionService
{
    private string $key;

    public function __construct()
    {
        $envKey = getenv('ENCRYPTION_KEY');
        if (!$envKey || strlen($envKey) < 32) {
            throw new RuntimeException('ENCRYPTION_KEY no configurada o muy corta (mín. 32 chars)');
        }
        // Derivar clave de 32 bytes con hash
        $this->key = sodium_crypto_generichash($envKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /**
     * Encriptar texto plano
     * @return array ['ciphertext' => string, 'nonce' => string]
     */
    public function encrypt(?string $plaintext): ?array
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 bytes
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce
        ];
    }

    /**
     * Desencriptar datos
     */
    public function decrypt(?string $ciphertext, ?string $nonce): ?string
    {
        if ($ciphertext === null || $nonce === null) {
            return null;
        }

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            error_log("[Encriptación] Error: No se pudo desencriptar. Clave incorrecta o datos corruptos.");
            return '[Error de desencriptación]';
        }

        return $plaintext;
    }

    /**
     * Encriptar y preparar para insertar en BD (devuelve parámetros para bind)
     */
    public function encryptForDb(?string $plaintext): array
    {
        if ($plaintext === null || $plaintext === '') {
            return ['encrypted' => null, 'nonce' => null];
        }

        $result = $this->encrypt($plaintext);
        return [
            'encrypted' => $result['ciphertext'],
            'nonce' => $result['nonce']
        ];
    }

    /**
     * Generar hash determinístico para búsqueda/comparación de texto encriptado.
     * Usa HMAC-SHA256 con una clave derivada del ENCRYPTION_KEY.
     * No permite desencriptar el valor original.
     */
    public function computeSearchHash(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        // Normalizar: minúsculas + trim + colapsar espacios múltiples
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $plaintext)), 'UTF-8');
        // Derivar clave HMAC desde la clave de encriptación con contexto diferente
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
     * Se generan tokens de: nombre, cargo y departamento del acusado.
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
     * Genera HMACs de identidad basados solo en el nombre de una persona.
     * Incluye nombre completo normalizado, cada palabra relevante y combinaciones nombre+apellido.
     * @return array<string>
     */
    public function computePersonNameTokens(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $hmacs = [];

        foreach ($this->tokenizeField($name) as $token) {
            $hmacs[] = $this->computeSearchHash($token);
        }

        $norm  = strtr(mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)), 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $words = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($words);

        if ($count >= 2) {
            $firstName      = $words[0];
            $firstLastname  = $count >= 3 ? $words[$count - 2] : $words[1];
            $secondLastname = $count >= 3 ? $words[$count - 1] : null;

            $hmacs[] = $this->computeSearchHash("$firstName $firstLastname");

            if ($secondLastname) {
                $hmacs[] = $this->computeSearchHash("$firstName $firstLastname $secondLastname");
                $hmacs[] = $this->computeSearchHash("$firstLastname $secondLastname");
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
        $hmacs = $this->computePersonNameTokens($name);

        // --- Cargo completo y cada palabra ---
        if (!empty($position)) {
            foreach ($this->tokenizeField($position) as $t) {
                $hmacs[] = $this->computeSearchHash($t);
            }
        }

        // --- Departamento completo y cada palabra ---
        if (!empty($department)) {
            foreach ($this->tokenizeField($department) as $t) {
                $hmacs[] = $this->computeSearchHash($t);
            }
        }

        return array_values(array_unique(array_filter($hmacs)));
    }

    /**
     * Limpiar memoria sensible
     */
    public function __destruct()
    {
        try {
            sodium_memzero($this->key);
        } catch (Exception $e) {
            // Ignorar si ya fue limpiado
        }
    }
}

/**
 * Instancia global del servicio de encriptación
 */
function getEncryptionService(): EncryptionService
{
    static $instance = null;
    if ($instance === null) {
        $instance = new EncryptionService();
    }
    return $instance;
}
