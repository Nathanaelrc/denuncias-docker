<?php
/**
 * Portal Denuncias Ciudadanas EPCO - Servicio de Encriptación
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
