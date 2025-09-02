<?php
/**
 * File: /vendor/vernsix/primordyx/src/Crypto.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Crypto.php
 *
 */

declare(strict_types=1);

namespace Primordyx\Security;

use Random\RandomException;
use RuntimeException;

/**
 * Class Crypto
 *
 * Provides encryption and decryption utilities using AES-256-GCM with automatic
 * key management, random IVs, and authenticated encryption for security.
 *
 * @since       1.0.0
 *
 */
class Crypto
{
    /**
     * Optional override for encryption key.
     *
     * @var string|null
     */
    protected static ?string $key = null;

    /**
     * Cipher method to use for encryption.
     */
    private const CIPHER_METHOD = 'AES-256-GCM';

    /**
     * IV length for AES-256-GCM (12 bytes for optimal performance).
     */
    private const IV_LENGTH = 12;

    /**
     * Authentication tag length for GCM (16 bytes).
     */
    private const TAG_LENGTH = 16;

    /**
     * Sets or gets the encryption key.
     *
     * @param string|null $key If provided, sets the key and returns the old key.
     * @return string|null     Returns the current/old key.
     */
    public static function key(?string $key = null): string|null
    {
        $old = self::$key;
        if ($key !== null) {
            self::$key = $key;
        }
        return $old;
    }

    /**
     * Derives a proper AES-256 encryption key from any input string.
     *
     * Takes a string of any length and converts it to exactly 32 bytes (256 bits)
     * required by AES-256-GCM. This allows developers to provide passwords,
     * passphrases, or keys of any length without worrying about exact byte requirements.
     *
     * @param string $masterKey Input string of any length (password, passphrase, etc.)
     * @return string           Exactly 32 bytes suitable for AES-256 encryption
     */
    protected static function deriveKey(string $masterKey): string
    {
        // Use SHA-256 to derive a 256-bit key for AES-256
        return hash('sha256', $masterKey, true);
    }

    /**
     * Encrypts any PHP value and returns a printable (hex-encoded) string.
     *
     * @param mixed $mixedToEncrypt The value to encrypt.
     * @param string|null $key Optional override key.
     * @return string                     Hex-encoded encrypted JSON string.
     * @throws RuntimeException|RandomException      If encryption fails.
     */
    public static function encrypt(mixed $mixedToEncrypt, ?string $key = null): string
    {
        $wrapped = ['k' => $mixedToEncrypt];
        $json = json_encode($wrapped);

        if ($json === false) {
            throw new RuntimeException('Failed to JSON encode data for encryption');
        }

        $masterKey = $key ?? self::key();
        if ($masterKey === null) {
            throw new RuntimeException('No encryption key configured. Call Crypto::key($key) first.');
        }

        $encryptionKey = self::deriveKey($masterKey);

        // Generate random IV for each encryption (12 bytes for GCM)
        $iv = random_bytes(self::IV_LENGTH);

        // Encrypt with GCM (provides both confidentiality and authenticity)
        $tag = '';
        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER_METHOD,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Format: IV + ciphertext + authentication tag
        $package = $iv . $ciphertext . $tag;

        return bin2hex($package);
    }

    /**
     * Decrypts a hex-encoded string and returns the original PHP value.
     *
     * @param string $encryptedString The hex-encoded encrypted JSON string.
     * @param string|null $key Optional override key.
     * @return mixed                       The original value or null on failure.
     */
    public static function decrypt(string $encryptedString, ?string $key = null): mixed
    {
        if (empty($encryptedString)) {
            return null;
        }

        // Validate hex format
        if (!ctype_xdigit($encryptedString) || strlen($encryptedString) % 2 !== 0) {
            return null;
        }

        $package = hex2bin($encryptedString);
        if ($package === false) {
            return null;
        }

        // Check minimum length (IV + at least 1 byte ciphertext + tag)
        $minLength = self::IV_LENGTH + 1 + self::TAG_LENGTH;
        if (strlen($package) < $minLength) {
            return null;
        }

        $masterKey = $key ?? self::key();
        if ($masterKey === null) {
            return null;
        }

        $encryptionKey = self::deriveKey($masterKey);

        // Extract components: IV + ciphertext + tag
        $iv = substr($package, 0, self::IV_LENGTH);
        $tagStart = strlen($package) - self::TAG_LENGTH;
        $ciphertext = substr($package, self::IV_LENGTH, $tagStart - self::IV_LENGTH);
        $tag = substr($package, $tagStart);

        // Decrypt with GCM (automatically verifies authentication tag)
        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER_METHOD,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return null;
        }

        $data = json_decode($decrypted, true);
        if (!is_array($data) || !array_key_exists('k', $data)) {
            return null;
        }

        return $data['k'];

    }

    /**
     * Generates a cryptographically secure random key suitable for AES-256.
     *
     * @return string Base64-encoded 256-bit key
     * @throws RandomException
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32)); // 256 bits
    }

    /**
     * Securely compares two strings to prevent timing attacks.
     *
     * Regular string comparison (===, strcmp) stops checking as soon as it finds
     * the first difference, making comparison time dependent on WHERE the strings
     * differ. Attackers can measure these tiny timing differences to gradually
     * guess secrets character by character.
     *
     * This method always takes the same amount of time regardless of where or
     * if the strings differ, preventing timing-based side-channel attacks.
     *
     * CRITICAL: Use this for any comparison involving secrets, tokens, passwords,
     * API keys, or other sensitive data where the comparison result must remain
     * secure from timing analysis.
     *
     * @param string $first The first string to compare
     * @param string $second The second string to compare
     * @return bool          True if strings are identical, false otherwise
     */
    public static function secureCompare(string $first, string $second): bool
    {
        return hash_equals($first, $second);
    }
}