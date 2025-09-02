<?php
/**
 * File: /vendor/vernsix/primordyx/src/Safe.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Safe.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use Exception;
use Primordyx\Database\DatabaseSafePersistence;
use Primordyx\Database\SafePersistenceInterface;
use Random\RandomException;
use RuntimeException;

/**
 * Secure Database-Backed Session Management System
 *
 * Provides a secure, database-backed replacement for PHP's built-in $_SESSION system with
 * automatic expiration, cryptographic security, and comprehensive session lifecycle management.
 * Designed for applications requiring enhanced security, audit trails, and distributed session storage.
 *
 * ## Key Features
 * - **Database Storage**: Sessions stored in database instead of files for scalability
 * - **Cryptographic Security**: Secure ID generation using cryptographically secure random bytes
 * - **Automatic Expiration**: Configurable session lifetime with automatic cleanup
 * - **Flash Messaging**: Built-in flash message support for user notifications
 * - **Cookie Security**: Secure cookie handling with configurable security attributes
 * - **Development Mode**: Automatic HTTP/HTTPS detection for development environments
 *
 * ## Security Architecture
 * - Uses 64-character cryptographically secure session IDs
 * - Secure cookie defaults (HttpOnly, Secure, SameSite=Strict)
 * - Session regeneration support to prevent session fixation attacks
 * - Automatic cleanup of expired sessions to prevent database bloat
 * - No session data stored in cookies - only secure session ID
 *
 * ## Usage Patterns
 * ```php
 * // Initialize session system
 * Safe::start();
 *
 * // Store and retrieve data
 * Safe::set('user_id', 123);
 * $userId = Safe::get('user_id');
 *
 * // Flash messages
 * Safe::flash('success', 'Data saved successfully');
 * $message = Safe::getFlash('success');
 *
 * // Security operations
 * Safe::regenerate();  // Prevent session fixation
 * Safe::destroy();     // Complete logout
 * ```
 *
 * @package Primordyx
 * @since 1.0.0
 */
class Safe
{
    /**
     * Default session lifetime in seconds (2 hours)
     *
     * @since 1.0.0
     */
    protected const DEFAULT_LIFETIME = 7200;

    /**
     * Cookie name for storing the safe session ID
     *
     * @since 1.0.0
     */
    protected const COOKIE_NAME = 'primordyx_safe';

    /**
     * Current cryptographically secure session ID
     *
     * @var string|null
     * @since 1.0.0
     */
    protected static ?string $safeId = null;

    /**
     * In-memory session data array
     *
     * @var array
     * @since 1.0.0
     */
    protected static array $data = [];

    /**
     * Database persistence implementation for session storage
     *
     * @var SafePersistenceInterface|null
     * @since 1.0.0
     */
    protected static ?SafePersistenceInterface $persistence = null;

    /**
     * Session lifetime in seconds
     *
     * @var int
     * @since 1.0.0
     */
    protected static int $lifetime = self::DEFAULT_LIFETIME;

    /**
     * Whether the session system has been initialized
     *
     * @var bool
     * @since 1.0.0
     */
    protected static bool $started = false;

    /**
     * Whether session data has been modified since last save
     *
     * @var bool
     * @since 1.0.0
     */
    protected static bool $dirty = false;

    /**
     * Security-focused cookie configuration settings
     *
     * @var array
     * @since 1.0.0
     */
    protected static array $cookieSettings = [
        'httponly' => true,
        'secure' => true,       // Always secure by default
        'samesite' => 'Strict', // Strict for maximum security
        'path' => '/',
        'domain' => ''
    ];

    /**
     * Initialize the secure session system with database persistence
     *
     * Starts or resumes a secure session using database storage instead of PHP's default
     * file-based sessions. Handles session creation, existing session restoration, cookie
     * security configuration, and automatic HTTP/HTTPS environment detection.
     *
     * ## Security Features
     * - Cryptographically secure 64-character session IDs
     * - Secure cookie defaults (HttpOnly, Secure, SameSite=Strict)
     * - Automatic HTTP detection for development environments
     * - Session expiration and automatic cleanup support
     *
     * ## Session Lifecycle
     * 1. Attempts to restore existing session from cookie
     * 2. Validates session against database persistence layer
     * 3. Creates new session if none exists or session expired
     * 4. Sets secure cookie with session ID
     * 5. Extends session expiration on successful load
     *
     * @param SafePersistenceInterface|null $persistence Custom persistence implementation (defaults to DatabaseSafePersistence)
     * @param int|null $lifetime Session lifetime in seconds (defaults to DEFAULT_LIFETIME)
     * @param array $cookieSettings Cookie security configuration overrides
     * @return bool True if session started successfully, false on failure
     * @throws Exception If session operations fail
     * @since 1.0.0
     *
     * @example Basic Usage
     * ```php
     * // Start with defaults
     * Safe::start();
     *
     * // Custom lifetime (1 hour)
     * Safe::start(null, 3600);
     *
     * // Custom cookie settings
     * Safe::start(null, null, ['domain' => '.example.com']);
     * ```
     */
    public static function start(
        ?SafePersistenceInterface $persistence = null,
        ?int $lifetime = null,
        array $cookieSettings = []
    ): bool {
        if (self::$started) {
            return true;
        }

        // Set persistence layer
        self::$persistence = $persistence ?? new DatabaseSafePersistence();

        // Set lifetime
        if ($lifetime !== null) {
            self::$lifetime = $lifetime;
        }

        // Merge cookie settings
        self::$cookieSettings = array_merge(self::$cookieSettings, $cookieSettings);

        // Auto-detect HTTP for development (override secure setting)
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            // Only allow insecure cookies if explicitly overridden in settings
            if (!isset($cookieSettings['secure'])) {
                error_log('WARNING: Safe sessions running on HTTP - cookies set to insecure for development');
                self::$cookieSettings['secure'] = false;
            }
        }

        // Try to get existing safe ID from cookie
        self::$safeId = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (self::$safeId) {
            // Try to load existing session
            $safeData = self::$persistence->find(self::$safeId);
            if ($safeData) {
                self::$data = $safeData['contents'];
                self::$started = true;
                self::touch(); // Extend expiration
                return true;
            } else {
                // Session expired or not found, clear cookie
                self::clearCookie();
                self::$safeId = null;
            }
        }

        // Create new session
        return self::createNew();
    }

    /**
     * Create new session with cryptographically secure ID
     *
     * Generates secure session ID, initializes empty data storage, and creates
     * database record with calculated expiration timestamp. Sets secure cookie
     * and initializes internal session state.
     *
     * @return bool True if session created successfully, false on database errors
     * @throws RandomException If secure random number generation fails
     * @since 1.0.0
     */
    protected static function createNew(): bool
    {
        self::$safeId = self::generateSafeId();
        self::$data = [];

        $expiresAt = date('Y-m-d H:i:s', time() + self::$lifetime);

        if (self::$persistence->create(self::$safeId, self::$data, $expiresAt)) {
            self::setCookie();
            self::$started = true;
            self::$dirty = false;
            return true;
        }

        return false;
    }

    /**
     * Generate cryptographically secure 64-character session ID
     *
     * Uses random_bytes() with 32 bytes converted to hexadecimal for maximum
     * entropy and security. Provides 256 bits of randomness to prevent session
     * ID prediction or brute force attacks.
     *
     * @return string Cryptographically secure 64-character hexadecimal session ID
     * @throws RandomException If secure random number generation fails
     * @since 1.0.0
     */
    protected static function generateSafeId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Store a value in the secure session
     *
     * Stores any serializable data in the session with automatic dirty flag management.
     * Data is kept in memory until save() is called or automatic save occurs on shutdown.
     *
     * @param string $key The storage key identifier
     * @param mixed $value The value to store (must be serializable)
     * @return void
     * @throws Exception If session cannot be started or storage fails
     * @since 1.0.0
     *
     * @example
     * ```php
     * Safe::set('user_id', 123);
     * Safe::set('user_data', ['name' => 'John', 'email' => 'john@example.com']);
     * Safe::set('login_time', time());
     * ```
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();
        self::$data[$key] = $value;
        self::$dirty = true;
    }

    /**
     * Retrieve a value from the secure session
     *
     * Returns stored session data with optional default value fallback.
     * Automatically ensures session is started before attempting retrieval.
     *
     * @param string $key The storage key to retrieve
     * @param mixed $default Default value returned if key doesn't exist
     * @return mixed The stored value or default value
     * @throws Exception If session cannot be started
     * @since 1.0.0
     *
     * @example
     * ```php
     * $userId = Safe::get('user_id');
     * $userName = Safe::get('user_name', 'Guest');
     * $preferences = Safe::get('settings', []);
     * ```
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return self::$data[$key] ?? $default;
    }

    /**
     * Check if a key exists in the secure session
     *
     * Tests for key existence using array_key_exists to distinguish between
     * non-existent keys and keys with null values.
     *
     * @param string $key The key to check for existence
     * @return bool True if key exists (even with null value), false otherwise
     * @throws Exception If session cannot be started
     * @since 1.0.0
     *
     * @example
     * ```php
     * if (Safe::has('user_id')) {
     *     $user = Safe::get('user_id');
     * }
     *
     * // Distinguish between missing and null
     * Safe::set('flag', null);
     * Safe::has('flag');        // true (exists but null)
     * Safe::has('missing');     // false (doesn't exist)
     * ```
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();
        return array_key_exists($key, self::$data);
    }

    /**
     * Remove a key from the secure session
     *
     * Deletes the specified key and its value from session storage.
     * Sets dirty flag if key existed to ensure persistence on save.
     *
     * @param string $key The key to remove from session
     * @return void
     * @throws Exception If session cannot be started
     * @since 1.0.0
     *
     * @example
     * ```php
     * Safe::forget('user_id');
     * Safe::forget('temp_data');
     * ```
     */
    public static function forget(string $key): void
    {
        self::ensureStarted();
        if (isset(self::$data[$key])) {
            unset(self::$data[$key]);
            self::$dirty = true;
        }
    }

    /**
     * Retrieve all session data as associative array
     *
     * Returns complete copy of current session data including flash messages
     * and any temporary data stored in the session.
     *
     * @return array Complete session data array
     * @throws Exception If session cannot be started
     * @since 1.0.0
     *
     * @example
     * ```php
     * $allData = Safe::all();
     * foreach ($allData as $key => $value) {
     *     echo "$key: $value\n";
     * }
     * ```
     */
    public static function all(): array
    {
        self::ensureStarted();
        return self::$data;
    }

    /**
     * Remove all data from the secure session
     *
     * Clears all session data while maintaining the session ID and cookie.
     * Useful for partial logout scenarios or session reset operations.
     *
     * @return void
     * @throws Exception If session cannot be started
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Clear all session data but keep session active
     * Safe::clear();
     *
     * // Session ID remains the same, but all data is gone
     * $isEmpty = empty(Safe::all()); // true
     * ```
     */
    public static function clear(): void
    {
        self::ensureStarted();
        self::$data = [];
        self::$dirty = true;
    }

    /**
     * Store flash data that persists for exactly one request
     *
     * Flash data is automatically removed after being retrieved once, making it
     * perfect for user notifications and temporary messages that should display
     * exactly once after redirects.
     *
     * @param string $key The flash key identifier
     * @param mixed $value The flash value to store
     * @return void
     * @throws Exception If session storage fails
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set flash messages
     * Safe::flash('success', 'Data saved successfully!');
     * Safe::flash('error', 'Invalid input provided');
     * Safe::flash('user_data', ['id' => 123, 'name' => 'John']);
     * ```
     */
    public static function flash(string $key, mixed $value): void
    {
        self::set("_flash.$key", $value);
    }

    /**
     * Retrieve and consume flash data (auto-removes after retrieval)
     *
     * Returns flash data and immediately removes it from session, ensuring
     * flash messages display exactly once. Subsequent calls return default value.
     *
     * @param string $key The flash key to retrieve
     * @param mixed $default Default value if flash key doesn't exist
     * @return mixed The flash value or default value
     * @throws Exception If session operations fail
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Display flash message if it exists
     * $message = Safe::getFlash('success');
     * if ($message) {
     *     echo "<div class='alert-success'>$message</div>";
     * }
     *
     * // Second call returns null (already consumed)
     * $gone = Safe::getFlash('success'); // null
     * ```
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = self::get("_flash.$key", $default);
        self::forget("_flash.$key");
        return $value;
    }

    /**
     * Keep flash data for another request cycle
     *
     * Prevents flash data from being consumed, allowing it to persist for one
     * additional request. Useful when validation fails and you need to redisplay
     * the same flash messages.
     *
     * @param string|array $keys Single key or array of keys to preserve
     * @return void
     * @throws Exception If session operations fail
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Keep single flash message
     * Safe::reflash('error');
     *
     * // Keep multiple flash messages
     * Safe::reflash(['error', 'warning', 'info']);
     * ```
     */
    public static function reflash(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            $value = self::get("_flash.$key");
            if ($value !== null) {
                self::flash($key, $value);
            }
        }
    }

    /**
     * Extend session expiration without modifying data
     *
     * Updates session expiration timestamp in database persistence layer to
     * prevent session timeout during long user sessions or background operations.
     *
     * @return bool True if expiration updated successfully, false on failure
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Extend session during long operations
     * if (Safe::touch()) {
     *     performLongRunningTask();
     * }
     * ```
     */
    public static function touch(): bool
    {
        if (!self::$started || !self::$safeId) {
            return false;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + self::$lifetime);
        return self::$persistence->touch(self::$safeId, $expiresAt);
    }

    /**
     * Persist current session data to database storage
     *
     * Saves modified session data to database if changes exist. No-op if session
     * is unchanged. Automatically called on script shutdown via autoSave().
     *
     * @return bool True if saved successfully or no changes exist, false on failure
     * @since 1.0.0
     *
     * @example
     * ```php
     * Safe::set('user_id', 123);
     * if (Safe::save()) {
     *     echo "Session saved successfully";
     * }
     * ```
     */
    public static function save(): bool
    {
        if (!self::$started || !self::$safeId || !self::$dirty) {
            return true;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + self::$lifetime);
        $saved = self::$persistence->update(self::$safeId, self::$data, $expiresAt);

        if ($saved) {
            self::$dirty = false;
        }

        return $saved;
    }

    /**
     * Regenerate session ID for security (prevents session fixation attacks)
     *
     * Creates new cryptographically secure session ID while preserving all session
     * data. Essential security practice after privilege changes or authentication.
     * Deletes old session from database and updates cookie with new ID.
     *
     * @return bool True if regeneration successful, false on failure
     * @throws RandomException If secure random number generation fails
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Regenerate after login to prevent session fixation
     * if (authenticateUser($username, $password)) {
     *     Safe::regenerate();
     *     Safe::set('user_id', $user->id);
     * }
     * ```
     */
    public static function regenerate(): bool
    {
        if (!self::$started) {
            return false;
        }

        $oldSafeId = self::$safeId;
        $newSafeId = self::generateSafeId();

        // Create new session with current data
        $expiresAt = date('Y-m-d H:i:s', time() + self::$lifetime);
        if (self::$persistence->create($newSafeId, self::$data, $expiresAt)) {
            // Delete old session
            if ($oldSafeId) {
                self::$persistence->delete($oldSafeId);
            }

            self::$safeId = $newSafeId;
            self::setCookie();
            self::$dirty = false;
            return true;
        }

        return false;
    }

    /**
     * Completely destroy the session and remove all traces
     *
     * Removes session from database, clears cookie, and resets all internal state.
     * Used for complete logout scenarios where no session data should remain.
     *
     * @return bool True if destroyed successfully, false on database errors
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Complete logout process
     * Safe::destroy();
     * header('Location: /login');
     * exit;
     * ```
     */
    public static function destroy(): bool
    {
        if (!self::$started) {
            return true;
        }

        $success = true;

        if (self::$safeId) {
            $success = self::$persistence->delete(self::$safeId);
        }

        self::clearCookie();
        self::$safeId = null;
        self::$data = [];
        self::$started = false;
        self::$dirty = false;

        return $success;
    }

    /**
     * Get the current session ID for debugging or logging purposes
     *
     * Returns the cryptographically secure session identifier. Useful for
     * debugging, logging, or advanced session management operations.
     *
     * @return string|null The current session ID or null if not started
     * @since 1.0.0
     *
     * @example
     * ```php
     * $sessionId = Safe::getId();
     * error_log("Current session: $sessionId");
     * ```
     */
    public static function getId(): ?string
    {
        return self::$safeId;
    }

    /**
     * Check if session system has been initialized
     *
     * Returns session state without triggering initialization. Useful for
     * conditional session operations or debugging session lifecycle issues.
     *
     * @return bool True if session is active, false otherwise
     * @since 1.0.0
     *
     * @example
     * ```php
     * if (!Safe::isStarted()) {
     *     Safe::start();
     * }
     * ```
     */
    public static function isStarted(): bool
    {
        return self::$started;
    }

    /**
     * Set secure session cookie with configured security attributes
     *
     * Creates HTTP cookie containing session ID with security-focused defaults.
     * Applies configured cookie settings for path, domain, security flags, and
     * SameSite protection. No-op if session ID is not available.
     *
     * @return void
     * @since 1.0.0
     */
    protected static function setCookie(): void
    {
        if (!self::$safeId) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            self::$safeId,
            [
                'expires' => time() + self::$lifetime,
                'path' => self::$cookieSettings['path'],
                'domain' => self::$cookieSettings['domain'],
                'secure' => self::$cookieSettings['secure'],
                'httponly' => self::$cookieSettings['httponly'],
                'samesite' => self::$cookieSettings['samesite']
            ]
        );
    }

    /**
     * Clear session cookie by setting expired timestamp
     *
     * Removes session cookie from client browser by setting expiration to past
     * timestamp. Uses same cookie attributes as setCookie() to ensure proper
     * cookie targeting and removal.
     *
     * @return void
     * @since 1.0.0
     */
    protected static function clearCookie(): void
    {
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => self::$cookieSettings['path'],
                'domain' => self::$cookieSettings['domain'],
                'secure' => self::$cookieSettings['secure'],
                'httponly' => self::$cookieSettings['httponly'],
                'samesite' => self::$cookieSettings['samesite']
            ]
        );
    }

    /**
     * Ensure session is started or throw exception
     *
     * Internal helper that guarantees session is initialized before data operations.
     * Attempts to start session automatically and throws exception if startup fails.
     * Prevents operations on uninitialized session state.
     *
     * @return void
     * @throws RuntimeException If session cannot be started
     * @throws Exception If session startup operations fail
     * @since 1.0.0
     */
    protected static function ensureStarted(): void
    {
        if (!self::$started) {
            if (!self::start()) {
                throw new RuntimeException('Unable to start Safe session');
            }
        }
    }

    /**
     * Remove expired sessions from database storage
     *
     * Performs maintenance by deleting expired session records from the database.
     * Should be called periodically via cron job or application maintenance routines
     * to prevent database bloat from abandoned sessions.
     *
     * @return int Number of expired sessions removed
     * @since 1.0.0
     *
     * @example
     * ```php
     * // In maintenance script
     * $cleaned = Safe::cleanup();
     * echo "Removed $cleaned expired sessions";
     * ```
     */
    public static function cleanup(): int
    {
        if (!self::$persistence) {
            self::$persistence = new DatabaseSafePersistence();
        }

        return self::$persistence->cleanup();
    }

    /**
     * Configure session lifetime in seconds
     *
     * Sets the duration that sessions remain valid before automatic expiration.
     * Affects new sessions and session extension operations. Must be called
     * before start() to affect initial session creation.
     *
     * @param int $seconds Session lifetime in seconds
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set 4-hour session lifetime
     * Safe::setLifetime(14400);
     * Safe::start();
     * ```
     */
    public static function setLifetime(int $seconds): void
    {
        self::$lifetime = $seconds;
    }

    /**
     * Get current session lifetime configuration
     *
     * Returns the configured session lifetime in seconds. Useful for displaying
     * session timeout warnings or calculating expiration timestamps.
     *
     * @return int Session lifetime in seconds
     * @since 1.0.0
     *
     * @example
     * ```php
     * $lifetime = Safe::getLifetime();
     * echo "Sessions expire after " . ($lifetime / 3600) . " hours";
     * ```
     */
    public static function getLifetime(): int
    {
        return self::$lifetime;
    }

    /**
     * Configure cookie security settings
     *
     * Merges provided settings with secure defaults for session cookie attributes.
     * Must be called before start() to affect cookie creation. Supports all
     * standard cookie attributes for fine-tuned security control.
     *
     * @param array $settings Cookie configuration array (path, domain, secure, httponly, samesite)
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Configure for subdomain sharing
     * Safe::configureCookies([
     *     'domain' => '.example.com',
     *     'path' => '/',
     *     'secure' => true
     * ]);
     * Safe::start();
     * ```
     */
    public static function configureCookies(array $settings): void
    {
        self::$cookieSettings = array_merge(self::$cookieSettings, $settings);
    }

    /**
     * Automatic save handler called on script shutdown
     *
     * Ensures session data is persisted even if save() is not explicitly called.
     * Registered automatically as shutdown function to prevent data loss from
     * unexpected script termination or developer oversight.
     *
     * @return void
     * @since 1.0.0
     */
    public static function autoSave(): void
    {
        if (self::$started && self::$dirty) {
            self::save();
        }
    }
}

// Register auto-save on shutdown
register_shutdown_function([Safe::class, 'autoSave']);