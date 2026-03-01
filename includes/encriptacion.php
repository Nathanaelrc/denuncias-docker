<?php
/**
 * Portal de Denuncias EPCO - Servicio de Encriptación
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
        if (!$envKey || strlen($envKey) < 16) {
            throw new RuntimeException('ENCRYPTION_KEY no configurada o muy corta (mín. 16 chars)');
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
