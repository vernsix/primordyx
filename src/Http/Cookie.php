<?php
/**
 * File: /vendor/vernsix/primordyx/src/Cookie.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Cookie.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;

use Primordyx\Security\Crypto;
use Random\RandomException;
use RuntimeException;

/**
 * Class Cookie
 *
 * Handles encrypted, persistent client-side cookies using internal key-value storage.
 *
 * This class stores multiple key-value pairs in a single encrypted cookie to:
 * - Reduce cookie bloat (avoid hitting browser limits of ~50 cookies per domain)
 * - Enhance security through AES-256-GCM encryption via Primordyx\Crypto
 * - Provide a clean API for cookie manipulation without constant encrypt/decrypt calls
 * - Ensure data privacy by making cookie contents unreadable to client-side inspection
 *
 * The class maintains an internal array that gets encrypted/decrypted automatically
 * when reading from or writing to the actual browser cookie.
 *
 * SECURITY NOTES:
 * - All cookie data is encrypted using AES-256-GCM via Primordyx\Crypto
 * - Encryption key must be configured via Crypto::key() before use
 * - httpOnly=true by default prevents JavaScript access (XSS protection)
 * - secure=true by default requires HTTPS transmission
 * - Cookie contents are completely opaque to client-side inspection
 * - Consider cookie size limits: browsers typically allow ~4KB per cookie
 *
 * @example Basic Usage:
 * ```php
 *  // Configure once
 *   Cookie::configure('my_app_session', 3600 * 24 * 7); // 7 days
 *
 *   // Add data
 *   Cookie::add('user_id', 123);
 *   Cookie::add('preferences', ['theme' => 'dark', 'lang' => 'en']);
 *
 *   // Send to browser
 *   Cookie::set();
 *
 *   // Later, retrieve data
 *   $userId = Cookie::get('user_id');
 *   $prefs = Cookie::get('preferences', []);
 *```
 *
 * @example Advanced Usage:
 * ```php
 *  // One-time cookie with custom settings
 *   Cookie::add('temp_token', $resetToken);
 *   Cookie::set('password_reset', 900, '/', '.example.com'); // 15 minutes
 *
 *   // Shopping cart across subdomain
 *   Cookie::configure('shopping_cart', 3600 * 24 * 30, '/', '.store.com');
 *   Cookie::add('items', $cartItems);
 *   Cookie::set();
 * ```
 *
 * @example Development vs Production:
 * ```php
 *   if (Config::get('environment') === 'production') {
 *       Cookie::configure('app_prod', 3600 * 24 * 30, '/', '.mysite.com', true, true);
 *   } else {
 *       Cookie::configure('app_dev', 3600, '/', null, false, true);
 *   }
 * ```
 *
 * @since       1.0.0
 *
 */
class Cookie
{
    /**
     * Internal storage for cookie key-value pairs.
     * Gets encrypted/decrypted when reading/writing to browser.
     *
     * @var array
     */
    protected static array $contents;

    /**
     * Default cookie name if none specified.
     *
     * @var string|null
     */
    protected static ?string $defaultName = null;

    /**
     * Default expiration time in seconds from now.
     *
     * @var int
     */
    protected static int $defaultExpiration = 2592000; // 30 days

    /**
     * Default cookie path.
     *
     * @var string
     */
    protected static string $defaultPath = '/';

    /**
     * Default cookie domain.
     *
     * @var string|null
     */
    protected static ?string $defaultDomain = null;

    /**
     * Default secure flag (HTTPS only).
     *
     * @var bool
     */
    protected static bool $defaultSecure = true;

    /**
     * Default HttpOnly flag (JavaScript access prevention).
     *
     * @var bool
     */
    protected static bool $defaultHttpOnly = true;

    /**
     * Configure default cookie settings for all subsequent operations.
     *
     * This is typically called once during application bootstrap to establish
     * cookie defaults. Individual set() calls can still override these settings.
     *
     * @example Application Bootstrap:
     *   // In your bootstrap.php or config setup:
     *   Cookie::configure(
     *       name: 'myapp_session',
     *       expiration: 3600 * 24 * 14,  // 2 weeks
     *       path: '/',
     *       domain: '.example.com',       // Share across subdomains
     *       secure: true,                 // HTTPS only
     *       httpOnly: true               // Prevent JavaScript access
     *   );
     *
     * @example Environment-Specific Configuration:
     * ```php
     *   if (Config::get('environment') === 'production') {
     *       Cookie::configure('app_prod', 3600 * 24 * 30, '/', '.mysite.com', true, true);
     *   } else {
     *       Cookie::configure('app_dev', 3600, '/', null, false, true);
     *   }
     * ```
     *
     * @param string      $name       Default cookie name
     * @param int         $expiration Default expiration in seconds from now (default: 30 days)
     * @param string      $path       Default cookie path (default: '/')
     * @param string|null $domain     Default cookie domain (default: null)
     * @param bool        $secure     Default secure flag - HTTPS only (default: true)
     * @param bool        $httpOnly   Default HttpOnly flag - prevent JavaScript access (default: true)
     * @return void
     */
    public static function configure(
        string $name,
        int $expiration = 2592000,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = true
    ): void {
        self::$defaultName = $name;
        self::$defaultExpiration = $expiration;
        self::$defaultPath = $path;
        self::$defaultDomain = $domain;
        self::$defaultSecure = $secure;
        self::$defaultHttpOnly = $httpOnly;
    }

    /**
     * Sets or gets the default cookie name.
     *
     * When called with no arguments, returns the current default name.
     * When called with a name, sets it as the new default and returns the old name.
     *
     * @example Getting current name:
     * ```php
     *   $currentName = Cookie::name(); // Returns current default
     * ```
     *
     * @example Setting new name:
     * ```php
     *   $oldName = Cookie::name('new_cookie_name'); // Sets and returns old name
     * ```
     *
     * @param string|null $name If provided, sets the name and returns the old name.
     * @return string|null      Returns the current/old name.
     */
    public static function name(?string $name = null): ?string
    {
        $old = self::$defaultName;
        if ($name !== null) {
            self::$defaultName = $name;
        }
        return $old;
    }

    /**
     * Sets or gets the default expiration time.
     *
     * When called with no arguments, returns the current default expiration.
     * When called with seconds, sets it as the new default and returns the old value.
     *
     * @example Common expiration times:
     * ```php
     *   Cookie::expiration(3600);           // 1 hour
     *   Cookie::expiration(3600 * 24);      // 1 day
     *   Cookie::expiration(3600 * 24 * 7);  // 1 week
     *   Cookie::expiration(3600 * 24 * 30); // 30 days
     * ```
     *
     * @param int|null $seconds If provided, sets expiration and returns the old value.
     * @return int              Returns the current/old expiration in seconds.
     */
    public static function expiration(?int $seconds = null): int
    {
        $old = self::$defaultExpiration;
        if ($seconds !== null) {
            self::$defaultExpiration = $seconds;
        }
        return $old;
    }

    /**
     * Sets or gets the default cookie path.
     *
     * The path determines which URLs the cookie will be sent to.
     * Use '/' for site-wide cookies, '/admin' for admin-only cookies, etc.
     *
     * @example Path restrictions:
     * ```php
     *   Cookie::path('/');        // Available site-wide
     *   Cookie::path('/admin');   // Only available under /admin/
     *   Cookie::path('/api/v1');  // Only available under /api/v1/
     * ```
     *
     * @param string|null $path If provided, sets the path and returns the old path.
     * @return string           Returns the current/old path.
     */
    public static function path(?string $path = null): string
    {
        $old = self::$defaultPath;
        if ($path !== null) {
            self::$defaultPath = $path;
        }
        return $old;
    }

    /**
     * Sets or gets the default cookie domain.
     *
     * Use null for current domain only, or specify domain for subdomain sharing.
     * Leading dot (e.g., '.example.com') allows all subdomains.
     *
     * @example Domain configurations:
     * ```php
     *   Cookie::domain(null);           // Current domain only
     *   Cookie::domain('example.com');  // example.com only
     *   Cookie::domain('.example.com'); // example.com and all subdomains
     * ```
     *
     * @param string|null $domain If provided, sets the domain and returns the old domain.
     * @return string|null        Returns the current/old domain.
     */
    public static function domain(?string $domain = null): ?string
    {
        $old = self::$defaultDomain;
        if ($domain !== null) {
            self::$defaultDomain = $domain;
        }
        return $old;
    }

    /**
     * Sets or gets the default secure flag.
     *
     * When true, cookie will only be transmitted over HTTPS connections.
     * Should be true in production for security.
     *
     * @example Security configurations:
     * ```php
     *   Cookie::secure(true);  // HTTPS only (recommended for production)
     *   Cookie::secure(false); // HTTP and HTTPS (development only)
     * ```
     *
     * @param bool|null $secure If provided, sets the secure flag and returns the old value.
     * @return bool             Returns the current/old secure flag.
     */
    public static function secure(?bool $secure = null): bool
    {
        $old = self::$defaultSecure;
        if ($secure !== null) {
            self::$defaultSecure = $secure;
        }
        return $old;
    }

    /**
     * Sets or gets the default HttpOnly flag.
     *
     * When true, prevents JavaScript access to the cookie (XSS protection).
     * Should almost always be true unless you specifically need JS access.
     *
     * @example HttpOnly configurations:
     * ```php
     *   Cookie::httpOnly(true);  // Prevent JavaScript access (recommended)
     *   Cookie::httpOnly(false); // Allow JavaScript access (rarely needed)
     * ```
     *
     * @param bool|null $httpOnly If provided, sets the HttpOnly flag and returns the old value.
     * @return bool               Returns the current/old HttpOnly flag.
     */
    public static function httpOnly(?bool $httpOnly = null): bool
    {
        $old = self::$defaultHttpOnly;
        if ($httpOnly !== null) {
            self::$defaultHttpOnly = $httpOnly;
        }
        return $old;
    }

    /**
     * Initializes the internal cookie store by decrypting the existing cookie, if present.
     *
     * This method is called automatically by other methods, but can be called manually
     * for explicit initialization. It will only initialize once per request.
     * If a cookie exists, it will be decrypted and loaded into the internal store.
     *
     * @param string|null $cookieName Optional cookie name override
     * @return void
     * @throws RuntimeException If no cookie name configured
     * @throws RuntimeException If decryption fails (malformed cookie data)
     * @see Crypto::decrypt() For decryption implementation
     */
    public static function init(?string $cookieName = null): void
    {
        if (isset(self::$contents)) return;

        self::$contents = [];
        $cookieName = $cookieName ?? self::name();

        if ($cookieName === null) {
            throw new RuntimeException('No cookie name configured. Call Cookie::name($name) or Cookie::configure() first.');
        }

        if (!empty($_COOKIE[$cookieName])) {
            $decrypted = Crypto::decrypt($_COOKIE[$cookieName]);

            if (is_array($decrypted)) {
                self::$contents = $decrypted;
            }
        }
    }

    /**
     * Writes the encrypted cookie to the user's browser.
     *
     * Encrypts the internal key-value store and sends it as a single cookie.
     * If the internal store is empty, the cookie will be expired (removed).
     * Headers must not have been sent yet for this to work.
     *
     * @param string|null $cookieName  Optional cookie name override
     * @param int|null    $expiration  Optional expiration override (seconds from now)
     * @param string|null $path        Optional path override
     * @param string|null $domain      Optional domain override
     * @param bool|null   $secure      Optional secure flag override
     * @param bool|null   $httpOnly    Optional HttpOnly flag override
     * @return void
     * @throws RuntimeException If no cookie name configured
     * @throws RuntimeException If encryption fails
     * @throws RuntimeException If headers already sent
     * @throws RandomException If encryption fails (cryptographically secure random bytes unavailable)
     *
     * @example Basic usage:
     * ```php
     *   Cookie::add('user_id', 123);
     *   Cookie::set(); // Uses configured defaults
     * ```
     *
     * @example Custom expiration:
     * ```php
     *   Cookie::set('remember_me', 3600 * 24 * 30); // 30 days
     * ```
     *
     * @example Full customization:
     * ```php
     *   Cookie::set(
     *       cookieName: 'temp_session',
     *       expiration: 900,              // 15 minutes
     *       path: '/admin',
     *       domain: '.secure.example.com',
     *       secure: true,
     *       httpOnly: true
     *   );
     * ```
     *
     * @see Crypto::encrypt() For encryption implementation
     */
    public static function set(
        ?string $cookieName = null,
        ?int $expiration = null,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null
    ): void {
        self::init($cookieName);

        $cookieName = $cookieName ?? self::name();
        if ($cookieName === null) {
            throw new RuntimeException('No cookie name configured. Call Cookie::name($name) or Cookie::configure() first.');
        }

        $expires = $expiration !== null ? (time() + $expiration) : (time() + self::expiration());
        $path = $path ?? self::path();
        $domain = $domain ?? self::domain();
        $secure = $secure ?? self::secure();
        $httpOnly = $httpOnly ?? self::httpOnly();

        if (empty(self::$contents)) {
            // if it's empty... expire it (aka remove from user's machine on next load)
            if (!empty($_COOKIE[$cookieName])) {
                setcookie($cookieName, '', 1, $path, $domain ?? '', $secure, $httpOnly);
            }
        } else {
            $encrypted = Crypto::encrypt(self::$contents);
            setcookie($cookieName, $encrypted, $expires, $path, $domain ?? '', $secure, $httpOnly);
        }
    }

    /**
     * Alias for set(). Sends the cookie to the user.
     *
     * Provides a more descriptive method name for when you want to be explicit
     * about sending the cookie to the user's browser.
     *
     * @param string|null $cookieName  Optional cookie name override
     * @param int|null    $expiration  Optional expiration override (seconds from now)
     * @param string|null $path        Optional path override
     * @param string|null $domain      Optional domain override
     * @param bool|null   $secure      Optional secure flag override
     * @param bool|null   $httpOnly    Optional HttpOnly flag override
     * @return void
     * @throws RuntimeException If no cookie name configured
     * @throws RuntimeException If encryption fails
     * @throws RuntimeException If headers already sent
     * @throws RandomException If encryption fails (cryptographically secure random bytes unavailable)
     * @see set() For full documentation
     */
    public static function sendToUser(
        ?string $cookieName = null,
        ?int $expiration = null,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null
    ): void {
        self::set($cookieName, $expiration, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Adds or updates a key-value pair in the internal cookie store.
     *
     * The data is stored in memory until set() is called to encrypt and send
     * the cookie to the browser. Any PHP data type can be stored.
     *
     * @example Simple values:
     * ```php
     *   Cookie::add('user_id', 123);
     *   Cookie::add('username', 'john_doe');
     *   Cookie::add('is_admin', true);
     * ```
     *
     * @example Complex data:
     * ```php
     *   Cookie::add('user_preferences', [
     *       'theme' => 'dark',
     *       'language' => 'en',
     *       'notifications' => true
     *   ]);
     *   Cookie::add('shopping_cart', [
     *       ['id' => 1, 'qty' => 2, 'price' => 19.99],
     *       ['id' => 5, 'qty' => 1, 'price' => 34.50]
     *   ]);
     * ```
     *
     * @param string $key   The key to store the value under
     * @param mixed  $value The value to store (any PHP data type)
     * @return void
     */
    public static function add(string $key, mixed $value): void
    {
        self::init();
        self::$contents[$key] = $value;
    }

    /**
     * Removes a key from the internal cookie store.
     *
     * The key will be removed from memory immediately. Call set() to persist
     * the change to the browser cookie.
     *
     * @example Removing keys:
     * ```php
     *   Cookie::drop('temp_token');
     *   Cookie::drop('user_id');
     *   Cookie::set(); // Persist the removal
     * ```
     *
     * @param string $key The key to remove
     * @return void
     */
    public static function drop(string $key): void
    {
        self::init();
        unset(self::$contents[$key]);
    }

    /**
     * Alias for drop(). Removes a key from the cookie store.
     *
     * Provides a more semantic method name for removing keys.
     *
     * @param string $key The key to remove
     * @return void
     * @see drop() For full documentation
     */
    public static function forget(string $key): void
    {
        self::drop($key);
    }

    /**
     * Checks if a key exists in the cookie store.
     *
     * Returns true if the key exists, regardless of its value (even if null).
     * Use this to check existence without accidentally setting defaults.
     *
     * @example Checking existence:
     * ```php
     *   if (Cookie::keyExists('user_id')) {
     *       $userId = Cookie::get('user_id');
     *   }
     *
     *   // Check before conditionally adding
     *   if (!Cookie::keyExists('visit_count')) {
     *       Cookie::add('visit_count', 1);
     *   } else {
     *       Cookie::add('visit_count', Cookie::get('visit_count') + 1);
     *   }
     * ```
     *
     * @param string $key The key to check
     * @return bool       True if key exists, false otherwise
     */
    public static function keyExists(string $key): bool
    {
        self::init();
        return isset(self::$contents[$key]);
    }

    /**
     * Alias for keyExists().
     *
     * Provides a shorter, more common method name for checking key existence.
     *
     * @param string $key The key to check
     * @return bool       True if key exists, false otherwise
     * @see keyExists() For full documentation
     */
    public static function has(string $key): bool
    {
        return self::keyExists($key);
    }

    /**
     * Gets the value for a given key, automatically setting it to default if not found.
     *
     * IMPORTANT: This method will ADD the default value to the cookie store if the key
     * doesn't exist. Use has() + manual retrieval if you don't want this behavior.
     *
     * @example Basic retrieval:
     * ```php
     *   $userId = Cookie::get('user_id'); // Returns null if not set
     *   $theme = Cookie::get('theme', 'light'); // Returns 'light' if not set
     * ```
     *
     * @example With complex defaults:
     * ```php
     *   $settings = Cookie::get('user_settings', [
     *       'notifications' => true,
     *       'theme' => 'dark',
     *       'language' => 'en'
     *   ]);
     * ```
     *
     * @example Avoiding auto-setting behavior:
     * ```php
     *   if (Cookie::has('optional_data')) {
     *       $data = Cookie::get('optional_data');
     *   }
     *   // OR use keyValue() which doesn't auto-set
     * ```
     *
     * @param string $key     The key to retrieve
     * @param mixed  $default Value to set and return if key doesn't exist
     * @return mixed          The stored value or the default
     * @see has() To check existence without setting defaults
     * @see keyValue() Alias method that doesn't auto-set defaults
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();
        if (!isset(self::$contents[$key])) {
            self::add($key, $default);
        }
        return self::$contents[$key];
    }

    /**
     * Returns the value for a given key without auto-setting defaults.
     *
     * Unlike get(), this method will NOT add the default value to the store
     * if the key doesn't exist. It simply returns the default.
     *
     * @example Safe retrieval:
     * ```php
     *   $userId = Cookie::keyValue('user_id'); // Returns null, doesn't set anything
     *   $theme = Cookie::keyValue('theme', 'light'); // Returns 'light', doesn't set anything
     * ```
     *
     * @param string $key     The key to retrieve
     * @param mixed  $default Value to return if key doesn't exist
     * @return mixed          The stored value or the default
     * @see get() For auto-setting behavior
     */
    public static function keyValue(string $key, mixed $default = null): mixed
    {
        self::init();
        return self::$contents[$key] ?? $default;
    }

    /**
     * Clears all stored key-value pairs from the internal store.
     *
     * This empties the internal array but doesn't affect the browser cookie
     * until set() is called. When set() is called with an empty store,
     * the browser cookie will be expired/removed.
     *
     * @example Complete reset:
     * ```php
     *   Cookie::clear();
     *   Cookie::set(); // This will remove the cookie from browser
     * ```
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$contents = [];
    }

    /**
     * Outputs the cookie contents as headers for debugging purposes.
     *
     * Sends X-Primordyx-Cookie-* headers containing the cookie data.
     * Useful for debugging cookie contents without exposing them in HTML.
     *
     * @example Debug usage:
     * ```php
     *   Cookie::add('user_id', 123);
     *   Cookie::add('theme', 'dark');
     *   Cookie::debug('debug'); // Sends headers like:
     *   // X-Primordyx-Cookie-debuguser_id: 123
     *   // X-Primordyx-Cookie-debugtheme: "dark"
     * ```
     *
     * @param string $suffix Optional suffix to include in header keys
     * @return void
     */
    public static function debug(string $suffix = ''): void
    {
        foreach (self::$contents as $k => $v) {
            header('X-Primordyx-Cookie-' . $suffix . $k . ': ' . json_encode($v));
        }
    }

    /**
     * Returns all key-value pairs currently stored in the internal array.
     *
     * Useful for debugging, logging, or bulk operations on cookie data.
     *
     * @example Getting all data:
     * ```php
     *   $allData = Cookie::dump();
     *   foreach ($allData as $key => $value) {
     *       echo "Cookie key: $key, value: " . json_encode($value) . "\n";
     *   }
     * ```
     *
     * @return array All stored key-value pairs
     */
    public static function dump(): array
    {
        self::init();
        return self::$contents;
    }

    /**
     * Alias for dump().
     *
     * Provides a more semantic method name for getting all stored data.
     *
     * @return array All stored key-value pairs
     * @see dump() For full documentation
     */
    public static function all(): array
    {
        return self::dump();
    }
}