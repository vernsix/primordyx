<?php
/**
 * File: /vendor/vernsix/primordyx/src/SessionSecurity.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Security/SessionSecurity.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Security;

use Primordyx\Events\EventManager;

/**
 * Class SessionSecurity
 *
 * Secure session configuration for the Primordyx framework.
 * Provides comprehensive session security hardening with HTTPS detection,
 * browser fingerprinting, session regeneration, and hijacking protection.
 *
 * This class should be called early in the request lifecycle (typically in bootstrap)
 * to establish secure session defaults before any other session operations.
 *
 * SECURITY FEATURES:
 * - Forces cookie-only sessions (blocks URL-based session IDs)
 * - Enables strict mode to reject invalid session IDs
 * - Configures HTTPS-only cookies when available
 * - Prevents JavaScript access to session cookies (XSS protection)
 * - Implements SameSite=Strict for CSRF protection
 * - Custom session storage outside web root
 * - Browser fingerprinting for session validation
 * - Automatic session regeneration every 30 minutes
 * - Session hijacking detection and recovery
 * - Configurable session lifetime and storage paths
 *
 * Usage:
 *   // Basic usage with required storage path
 *   SessionSecurity::configure([
 *       'storage_path' => '/app/storage/sessions'
 *   ]);
 *
 *   // Full custom configuration
 *   SessionSecurity::configure([
 *       'storage_path' => '/custom/sessions',      // REQUIRED
 *       'lifetime' => 7200,                       // 2 hours
 *       'cookie_name' => '__Host-MyApp',          // Custom cookie name
 *       'gc_probability' => 2,                    // 2% cleanup chance
 *       'regenerate_interval' => 1800             // 30 minutes
 *   ]);
 *
 * @since       2.0.0
 */
class SessionSecurity
{
    /** @var bool Whether configuration has been applied */
    protected static bool $configured = false;

    /** @var int Default session lifetime in seconds (1 hour) */
    protected static int $lifetime = 3600;

    /** @var string|null Storage path - must be explicitly set */
    protected static ?string $storagePath = null;

    /** @var string Default secure cookie name */
    protected static string $cookieName = '__Host-AppAuth';

    /** @var int Garbage collection probability (1%) */
    protected static int $gcProbability = 1;

    /** @var int Garbage collection divisor */
    protected static int $gcDivisor = 100;

    /** @var int Session regeneration interval in seconds (30 minutes) */
    protected static int $regenerateInterval = 1800;

    /** @var bool Whether HTTPS is detected */
    protected static ?bool $isHttps = null;

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    /**
     * Configure and initialize secure session settings.
     *
     * This method applies comprehensive session security configuration and starts
     * the session with browser fingerprinting and hijacking protection. Should be
     * called once early in the application lifecycle.
     *
     * IMPORTANT: storage_path is REQUIRED and must be explicitly provided.
     * The path should be outside your web root for security.
     *
     * Configuration Options:
     * - storage_path: REQUIRED - Absolute path for session storage (e.g., '/app/storage/sessions')
     * - lifetime: Session lifetime in seconds (default: 3600 = 1 hour)
     * - cookie_name: Session cookie name (default: __Host-AppAuth)
     * - gc_probability: Cleanup probability 1-100 (default: 1)
     * - gc_divisor: Cleanup divisor (default: 100, so 1/100 = 1%)
     * - regenerate_interval: Regeneration interval in seconds (default: 1800 = 30 min)
     *
     * @param array $options Configuration options - storage_path is required
     * @return bool True if configuration was successful
     */
    public static function configure(array $options = []): bool
    {
        if (self::$configured) {
            EventManager::fire('session.security.already_configured', [
                'message' => 'SessionSecurity already configured, skipping duplicate configuration'
            ]);
            return true;
        }

        // Require explicit storage path
        if (empty($options['storage_path'])) {
            EventManager::fire('session.security.storage_path_required', [
                'message' => 'storage_path is required and must be explicitly provided',
                'example' => "SessionSecurity::configure(['storage_path' => '/app/storage/sessions'])"
            ]);
            return false;
        }

        // Merge options with defaults
        self::$lifetime = $options['lifetime'] ?? self::$lifetime;
        self::$storagePath = $options['storage_path'];
        self::$cookieName = $options['cookie_name'] ?? self::$cookieName;
        self::$gcProbability = $options['gc_probability'] ?? self::$gcProbability;
        self::$gcDivisor = $options['gc_divisor'] ?? self::$gcDivisor;
        self::$regenerateInterval = $options['regenerate_interval'] ?? self::$regenerateInterval;

        // Detect HTTPS once
        self::$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // Apply PHP session configuration
        if (!self::configurePhpSession()) {
            return false;
        }

        // Create and configure storage directory
        if (!self::configureStorageDirectory()) {
            return false;
        }

        // Configure session cookie parameters
        self::configureSessionCookie();

        // Start session with security validation
        if (!self::startSecureSession()) {
            return false;
        }

        self::$configured = true;

        EventManager::fire('session.security.configured', [
            'lifetime' => self::$lifetime,
            'storage_path' => self::$storagePath,
            'cookie_name' => self::$cookieName,
            'https_detected' => self::$isHttps,
            'regenerate_interval' => self::$regenerateInterval
        ]);

        return true;
    }

    /**
     * Get or set session lifetime in seconds.
     *
     * @param int|null $newLifetime New lifetime to set, or null to get current
     * @return int Previous lifetime value
     */
    public static function lifetime(?int $newLifetime = null): int
    {
        $old = self::$lifetime;
        if ($newLifetime !== null) {
            self::$lifetime = $newLifetime;
        }
        return $old;
    }

    /**
     * Get or set session storage path.
     *
     * @param string|null $newPath New storage path to set, or null to get current
     * @return string|null Previous storage path value
     */
    public static function storagePath(?string $newPath = null): ?string
    {
        $old = self::$storagePath;
        if ($newPath !== null) {
            self::$storagePath = $newPath;
        }
        return $old;
    }

    /**
     * Get or set session cookie name.
     *
     * @param string|null $newName New cookie name to set, or null to get current
     * @return string Previous cookie name value
     */
    public static function cookieName(?string $newName = null): string
    {
        $old = self::$cookieName;
        if ($newName !== null) {
            self::$cookieName = $newName;
        }
        return $old;
    }

    /**
     * Get or set garbage collection probability (1-100).
     *
     * @param int|null $newProbability New probability to set, or null to get current
     * @return int Previous probability value
     */
    public static function gcProbability(?int $newProbability = null): int
    {
        $old = self::$gcProbability;
        if ($newProbability !== null && $newProbability >= 1 && $newProbability <= 100) {
            self::$gcProbability = $newProbability;
        }
        return $old;
    }

    /**
     * Get current configuration status.
     *
     * @return bool True if configure() has been called successfully
     */
    public static function isConfigured(): bool
    {
        return self::$configured;
    }

    /**
     * Generate browser fingerprint for session validation.
     *
     * Creates a hash based on stable browser characteristics to detect
     * session hijacking attempts. Only uses headers that are consistent
     * across requests but unique enough to identify browser differences.
     *
     * @return string SHA-256 hash of browser characteristics
     */
    public static function generateBrowserFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Auto-detect appropriate session storage path.
     *
     * Attempts to find a suitable directory outside the web root for storing
     * session files. Falls back to system temp directory if no suitable path found.
     *
     * @return string Absolute path to session storage directory
     */
    protected static function autoDetectStoragePath(): string
    {
        // Try to go one level up from document root if available
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if ($docRoot && is_dir($docRoot)) {
            $parentDir = dirname($docRoot);
            $sessionPath = $parentDir . '/storage/sessions';
            if (self::isPathSuitable($sessionPath)) {
                return $sessionPath;
            }
        }

        // Try current working directory approach
        $cwd = getcwd();
        if ($cwd) {
            $sessionPath = dirname($cwd) . '/storage/sessions';
            if (self::isPathSuitable($sessionPath)) {
                return $sessionPath;
            }
        }

        // Fallback to system temp directory
        $tempDir = sys_get_temp_dir() . '/primordyx_sessions';

        EventManager::fire('session.security.storage_fallback', [
            'message' => 'Using system temp directory for session storage',
            'path' => $tempDir,
            'reason' => 'Could not determine suitable path outside web root'
        ]);

        return $tempDir;
    }

    /**
     * Check if a path is suitable for session storage.
     *
     * @param string $path Path to check
     * @return bool True if path can be used or created
     */
    protected static function isPathSuitable(string $path): bool
    {
        // Check if path already exists and is writable
        if (is_dir($path) && is_writable($path)) {
            return true;
        }

        // Check if we can create the path
        $parentDir = dirname($path);
        return is_dir($parentDir) && is_writable($parentDir);
    }

    /**
     * Configure PHP session settings for security.
     *
     * @return bool True if configuration was successful
     */
    protected static function configurePhpSession(): bool
    {
        // Forces strict typing - variables must match their declared types
        if (!ini_set('session.use_only_cookies', '1')) {
            EventManager::fire('session.security.ini_set_failed', [
                'setting' => 'session.use_only_cookies',
                'value' => '1'
            ]);
            return false;
        }

        // Disables transparent session IDs - stops PHP from automatically adding session IDs to URLs/forms
        ini_set('session.use_trans_sid', '0');

        // Rejects invalid session IDs - if someone tries to use a fake/expired session ID, PHP creates a new one
        ini_set('session.use_strict_mode', '1');

        // HTTPS-only cookies - session cookies only sent over encrypted connections (if HTTPS is available)
        ini_set('session.cookie_secure', self::$isHttps ? '1' : '0');

        // Blocks JavaScript access - prevents document.cookie from reading session cookies (XSS protection)
        ini_set('session.cookie_httponly', '1');

        // Prevents cross-site requests - cookies won't be sent from other domains (CSRF protection)
        ini_set('session.cookie_samesite', 'Strict');

        // Session lifetime - sessions expire after configured inactivity period
        ini_set('session.gc_maxlifetime', (string)self::$lifetime);

        // Garbage collection configuration - chance of running cleanup to remove old sessions
        ini_set('session.gc_probability', (string)self::$gcProbability);
        ini_set('session.gc_divisor', (string)self::$gcDivisor);

        return true;
    }

    /**
     * Configure and create session storage directory.
     *
     * @return bool True if directory configuration was successful
     */
    protected static function configureStorageDirectory(): bool
    {
        if (!is_dir(self::$storagePath)) {
            if (!mkdir(self::$storagePath, 0700, true)) {
                EventManager::fire('session.security.storage_create_failed', [
                    'path' => self::$storagePath,
                    'error' => error_get_last()
                ]);
                return false;
            }

            EventManager::fire('session.security.storage_created', [
                'path' => self::$storagePath,
                'permissions' => '0700'
            ]);
        }

        // Set custom session storage path outside web root
        if (!ini_set('session.save_path', self::$storagePath)) {
            EventManager::fire('session.security.storage_set_failed', [
                'path' => self::$storagePath
            ]);
            return false;
        }

        return true;
    }

    /**
     * Configure session cookie parameters.
     */
    protected static function configureSessionCookie(): void
    {
        // Super-secure cookie name - __Host- prefix requires HTTPS, secure flag, and no domain attribute
        session_name(self::$cookieName);

        // Cookie configuration - sets all the security parameters for the session cookie
        session_set_cookie_params([
            'lifetime' => 0,  // Session cookie - expires when browser closes
            'path' => '/',
            'domain' => '',   // Required to be empty for __Host- prefix
            'secure' => self::$isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Start session with security validation and fingerprinting.
     *
     * @return bool True if session started successfully
     */
    protected static function startSecureSession(): bool
    {
        // Start the session
        if (!session_start()) {
            EventManager::fire('session.security.start_failed', [
                'message' => 'Failed to start PHP session'
            ]);
            return false;
        }

        // Track session creation time and browser fingerprint
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
            $_SESSION['fingerprint'] = self::generateBrowserFingerprint();

            EventManager::fire('session.security.session_created', [
                'session_id' => session_id(),
                'created_at' => $_SESSION['created'],
                'fingerprint_hash' => substr($_SESSION['fingerprint'], 0, 16) . '...'
            ]);
        } else {
            // Refresh session ID every configured interval and validate browser consistency
            if (time() - $_SESSION['created'] > self::$regenerateInterval) {
                $oldSessionId = session_id();
                session_regenerate_id(true);
                $_SESSION['created'] = time();

                EventManager::fire('session.security.session_regenerated', [
                    'old_session_id' => $oldSessionId,
                    'new_session_id' => session_id(),
                    'interval' => self::$regenerateInterval
                ]);
            }

            // Validate browser fingerprint on each request
            $currentFingerprint = self::generateBrowserFingerprint();
            if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $currentFingerprint) {
                // Session hijacking detected - destroy and start fresh
                $oldSessionId = session_id();

                EventManager::fire('session.security.hijack_detected', [
                    'old_session_id' => $oldSessionId,
                    'expected_fingerprint' => substr($_SESSION['fingerprint'], 0, 16) . '...',
                    'actual_fingerprint' => substr($currentFingerprint, 0, 16) . '...',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);

                session_destroy();
                session_start();
                $_SESSION['created'] = time();
                $_SESSION['fingerprint'] = $currentFingerprint;

                EventManager::fire('session.security.session_renewed', [
                    'new_session_id' => session_id(),
                    'reason' => 'hijack_protection'
                ]);
            }
        }

        return true;
    }
}
