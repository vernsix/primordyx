<?php
/**
 * File: /vendor/vernsix/primordyx/src/AuthManager.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Security/AuthManager.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Security;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Primordyx\Data\Safe;
use Primordyx\Database\Model;
use RuntimeException;

/**
 * Comprehensive Authentication and Authorization System for Primordyx Applications
 *
 * Provides secure session management, role-based access control, and protection against
 * common attack vectors like brute force login attempts. Built specifically for the
 * Primordyx framework with seamless Safe class integration and flexible model support.
 *
 * ## Core Features
 * - **Secure Session Management** - Uses Safe class for tamper-resistant session storage
 * - **Failed Login Protection** - Automatic account lockout after configurable failed attempts
 * - **Role-Based Authorization** - Flexible permission system using authorization words
 * - **Session Timeout** - Automatic logout after configurable inactivity periods
 * - **Flash Messaging** - Built-in support for user notifications across redirects
 * - **Configurable URLs** - Customizable redirect destinations for all authentication scenarios
 * - **Model Agnostic** - Works with any Model implementation following Primordyx conventions
 * - **Attack Prevention** - Protection against brute force, session fixation, and timing attacks
 *
 * ## Security Architecture
 * - Password verification using PHP's secure `password_verify()` function
 * - Failed attempt tracking with temporary account lockouts
 * - Session data stored via Safe class (tamper-resistant)
 * - Automatic cleanup of expired sessions and stale return URLs
 * - Authorization words normalized to lowercase for consistent checking
 *
 * ## Quick Start Integration
 * ```php
 * // Bootstrap configuration
 * AuthManager::config([
 *     'login_url' => '/auth/login.php',
 *     'max_login_attempts' => 5,
 *     'lockout_time' => 900,
 *     'timeout_seconds' => 3600,
 *     'after_logout_url' => '/'
 * ]);
 *
 * // Set required models
 * AuthManager::userModel(new User());
 * AuthManager::userAuthWordsModel(new UserAuthWords());
 * ```
 *
 * ## Common Usage Patterns
 * ```php
 * // Page protection
 * AuthManager::setReturnUrl($_SERVER['REQUEST_URI']);
 * AuthManager::requireAuth('admin');
 *
 * // Login processing
 * AuthManager::login($_POST['username'], $_POST['password']); // Never returns
 *
 * // Authorization checks
 * if (AuthManager::isAuthorizedAny(['admin', 'manager'])) {
 *     showLeadershipDashboard();
 * }
 * ```
 *
 * @see Safe For secure session management
 * @see Model For database model requirements
 * @package Primordyx
 * @since 1.0.0
 */
class AuthManager
{
    // Configuration properties with sensible defaults
    protected static string $loginUrl = '/login.php';
    protected static int $maxLoginAttempts = 5;
    protected static int $lockoutTime = 900; // 15 minutes in seconds
    protected static string $lockedOutUrl = '/locked.php';
    protected static string $afterLogoutUrl = '/';
    protected static string $timeoutUrl = '/timeout.php';
    protected static int $timeoutSeconds = 3600; // 1 hour
    protected static ?Model $userModel = null;
    protected static ?Model $userAuthWordsModel = null;

    /**
     * Configure multiple AuthManager settings at once for streamlined setup
     *
     * Provides bulk configuration of all AuthManager settings through a single method call.
     * This is the recommended approach for application bootstrap as it ensures all related
     * settings are configured together and provides better maintainability.
     *
     * ## Available Configuration Keys
     * - `login_url` - Where to redirect users for authentication
     * - `max_login_attempts` - Failed attempts before account lockout
     * - `lockout_time` - Lockout duration in seconds
     * - `locked_out_url` - Where to show lockout notification
     * - `after_logout_url` - Destination after successful logout
     * - `timeout_url` - Where to redirect on session timeout
     * - `timeout_seconds` - Session inactivity timeout duration
     *
     * ## Configuration Strategy
     * Settings are applied individually using the respective setter methods, ensuring
     * consistency with individual configuration calls and maintaining backward compatibility.
     *
     * @param array<string, mixed> $config Configuration array with the following supported keys:
     *   - 'login_url' (string): URL to redirect to for login (default: '/login.php')
     *   - 'max_login_attempts' (int): Maximum failed attempts before lockout (default: 5)
     *   - 'lockout_time' (int): Lockout duration in seconds (default: 900 = 15 minutes)
     *   - 'locked_out_url' (string): URL to show when user is locked out (default: '/locked.php')
     *   - 'after_logout_url' (string): URL to redirect after logout (default: '/')
     *   - 'timeout_url' (string): URL to redirect on session timeout (default: '/timeout.php')
     *   - 'timeout_seconds' (int): Session timeout in seconds (default: 3600 = 1 hour)
     *
     * @return void
     * @throws RuntimeException If invalid configuration values are provided
     * @since 1.0.0
     *
     * @example Bootstrap Configuration
     * ```php
     * AuthManager::config([
     *     'login_url' => '/auth/login.php',           // Authentication entry point
     *     'max_login_attempts' => 3,                  // Lock after 3 failures
     *     'lockout_time' => 1800,                     // 30 minute lockout
     *     'locked_out_url' => '/auth/locked.php',     // Lockout notification page
     *     'after_logout_url' => '/goodbye.php',       // Post-logout destination
     *     'timeout_url' => '/auth/timeout.php',       // Session timeout page
     *     'timeout_seconds' => 7200                   // 2 hour session timeout
     * ]);
     * ```
     *
     * @example Production Security Configuration
     * ```php
     * AuthManager::config([
     *     'max_login_attempts' => 5,
     *     'lockout_time' => 900,          // 15 minutes
     *     'timeout_seconds' => 3600       // 1 hour timeout
     * ]);
     * // Other settings remain at defaults
     * ```
     */
    public static function config(array $config): void
    {
        self::loginUrl($config['login_url'] ?? null);
        self::maxLoginAttempts(isset($config['max_login_attempts']) ? (int)$config['max_login_attempts'] : null);
        self::lockoutTime(isset($config['lockout_time']) ? (int)$config['lockout_time'] : null);
        self::lockedOutUrl($config['locked_out_url'] ?? null);
        self::afterLogoutUrl($config['after_logout_url'] ?? null);
        self::timeoutUrl($config['timeout_url'] ?? null);
        self::timeoutSeconds(isset($config['timeout_seconds']) ? (int)$config['timeout_seconds'] : null);
    }

    /**
     * Get or set the login URL where users are redirected for authentication
     *
     * This URL is used whenever the system needs to redirect users for authentication,
     * such as when using `forceLogin()` or when authentication is required but not present.
     *
     * @param string|null $url If provided, sets the login URL and returns the previous value
     * @return string The current login URL (if getting) or previous login URL (if setting)
     * @since 1.0.0
     *
     * @example Get Current Login URL
     * ```php
     * $current = AuthManager::loginUrl();              // Returns current URL
     * echo "Login page: $current";                     // Shows '/login.php' (default)
     * ```
     *
     * @example Set New Login URL
     * ```php
     * $old = AuthManager::loginUrl('/new-login.php');  // Set new URL, get old one back
     * echo "Changed from: $old";                       // Shows previous URL
     * ```
     */
    public static function loginUrl(?string $url = null): string
    {
        $old = self::$loginUrl;
        if ($url !== null) {
            self::$loginUrl = $url;
        }
        return $old;
    }

    /**
     * Get or set the maximum login attempts before triggering account lockout
     *
     * When a user exceeds this number of failed login attempts, their account will be
     * temporarily locked for the duration specified by `lockoutTime()`.
     *
     * @param int|null $attempts If provided, sets max attempts and returns the previous value
     * @return int The current max attempts (if getting) or previous max attempts (if setting)
     * @since 1.0.0
     *
     * @example Get Current Max Attempts
     * ```php
     * $current = AuthManager::maxLoginAttempts();     // Returns current limit
     * echo "Max attempts: $current";                  // Shows '5' (default)
     * ```
     *
     * @example Set Stricter Limit
     * ```php
     * $old = AuthManager::maxLoginAttempts(3);        // Allow only 3 attempts
     * echo "Attempts limit changed from $old to 3";
     * ```
     */
    public static function maxLoginAttempts(?int $attempts = null): int
    {
        $old = self::$maxLoginAttempts;
        if ($attempts !== null) {
            self::$maxLoginAttempts = $attempts;
        }
        return $old;
    }

    /**
     * Get or set the lockout duration in seconds after exceeding max login attempts
     *
     * When a user account is locked due to too many failed attempts, they will be
     * prevented from attempting to login again for this duration.
     *
     * @param int|null $seconds If provided, sets lockout time and returns the previous value
     * @return int The current lockout time in seconds (if getting) or previous lockout time (if setting)
     * @since 1.0.0
     *
     * @example Get Current Lockout Duration
     * ```php
     * $current = AuthManager::lockoutTime();          // Returns current duration
     * $minutes = $current / 60;                       // Convert to minutes
     * echo "Lockout duration: $minutes minutes";      // Shows '15 minutes' (default)
     * ```
     *
     * @example Set 30 Minute Lockout
     * ```php
     * $old = AuthManager::lockoutTime(1800);          // 30 minutes in seconds
     * echo "Lockout changed from " . ($old/60) . " to 30 minutes";
     * ```
     */
    public static function lockoutTime(?int $seconds = null): int
    {
        $old = self::$lockoutTime;
        if ($seconds !== null) {
            self::$lockoutTime = $seconds;
        }
        return $old;
    }

    /**
     * Get or set the URL where locked-out users are redirected for notification
     *
     * When a user attempts to login but their account is currently locked due to
     * too many failed attempts, they will be redirected to this URL with an
     * appropriate flash message explaining the lockout.
     *
     * @param string|null $url If provided, sets the locked out URL and returns the previous value
     * @return string The current locked out URL (if getting) or previous URL (if setting)
     * @since 1.0.0
     *
     * @example Default Usage
     * ```php
     * $current = AuthManager::lockedOutUrl();         // Get current URL
     * echo "Lockout page: $current";                  // Shows '/locked.php' (default)
     * ```
     *
     * @example Custom Lockout Page
     * ```php
     * $old = AuthManager::lockedOutUrl('/auth/account-locked.php');
     * echo "Lockout page changed from: $old";
     * ```
     */
    public static function lockedOutUrl(?string $url = null): string
    {
        $old = self::$lockedOutUrl;
        if ($url !== null) {
            self::$lockedOutUrl = $url;
        }
        return $old;
    }

    /**
     * Get or set the URL where users are redirected after successful logout
     *
     * When `logout()` is called, users will be redirected to this URL after
     * their session data has been cleaned up.
     *
     * @param string|null $url If provided, sets the after logout URL and returns the previous value
     * @return string The current after logout URL (if getting) or previous URL (if setting)
     * @since 1.0.0
     *
     * @example Get Current Destination
     * ```php
     * $current = AuthManager::afterLogoutUrl();       // Get current URL
     * echo "Post-logout: $current";                   // Shows '/' (default)
     * ```
     *
     * @example Custom Goodbye Page
     * ```php
     * $old = AuthManager::afterLogoutUrl('/goodbye.php');
     * echo "Logout destination changed from: $old";
     * ```
     */
    public static function afterLogoutUrl(?string $url = null): string
    {
        $old = self::$afterLogoutUrl;
        if ($url !== null) {
            self::$afterLogoutUrl = $url;
        }
        return $old;
    }

    /**
     * Get or set the URL where users are redirected when their session times out
     *
     * When a user's session expires due to inactivity (exceeds `timeoutSeconds()`),
     * they will be redirected to this URL with appropriate notification.
     *
     * @param string|null $url If provided, sets the timeout URL and returns the previous value
     * @return string The current timeout URL (if getting) or previous URL (if setting)
     * @since 1.0.0
     *
     * @example Get Current Timeout Destination
     * ```php
     * $current = AuthManager::timeoutUrl();           // Get current URL
     * echo "Timeout page: $current";                  // Shows '/timeout.php' (default)
     * ```
     *
     * @example Custom Timeout Page
     * ```php
     * $old = AuthManager::timeoutUrl('/auth/session-expired.php');
     * echo "Timeout destination changed from: $old";
     * ```
     */
    public static function timeoutUrl(?string $url = null): string
    {
        $old = self::$timeoutUrl;
        if ($url !== null) {
            self::$timeoutUrl = $url;
        }
        return $old;
    }

    /**
     * Get or set the session timeout duration in seconds for inactivity-based logout
     *
     * When a user's session is inactive for longer than this duration, they will
     * be automatically logged out and redirected to the timeout URL on their next request.
     *
     * @param int|null $seconds If provided, sets the timeout duration and returns the previous value
     * @return int The current timeout in seconds (if getting) or previous timeout (if setting)
     * @since 1.0.0
     *
     * @example Get Current Timeout
     * ```php
     * $current = AuthManager::timeoutSeconds();       // Get current timeout
     * $hours = $current / 3600;                       // Convert to hours
     * echo "Session timeout: $hours hours";           // Shows '1 hours' (default)
     * ```
     *
     * @example Set 2 Hour Timeout
     * ```php
     * $old = AuthManager::timeoutSeconds(7200);       // 2 hours, get old timeout
     * echo "Timeout changed from " . ($old/3600) . " to 2 hours";
     * ```
     */
    public static function timeoutSeconds(?int $seconds = null): int
    {
        $old = self::$timeoutSeconds;
        if ($seconds !== null) {
            self::$timeoutSeconds = $seconds;
        }
        return $old;
    }

    /**
     * Get or set the user model instance for database authentication operations
     *
     * The user model must support the Primordyx Model interface and should have fields
     * for username, password_hash, failed_attempts, last_failed, and locked_until.
     *
     * @param Model|null $model If provided, sets the user model and returns the previous value
     * @return Model|null The current user model (if getting) or previous model (if setting)
     * @throws RuntimeException If model doesn't support required authentication fields
     * @since 1.0.0
     *
     * @example Get Current Model
     * ```php
     * $current = AuthManager::userModel();            // Get current model
     * if ($current) {
     *     echo "Using model: " . get_class($current);
     * }
     * ```
     *
     * @example Set User Model
     * ```php
     * $old = AuthManager::userModel(new User());      // Set new model, get old one back
     * echo "Model changed from: " . ($old ? get_class($old) : 'none');
     * ```
     */
    public static function userModel(?Model $model = null): ?Model
    {
        $old = self::$userModel;
        if ($model !== null) {
            self::$userModel = $model;
        }
        return $old;
    }

    /**
     * Get or set the user authorization words model for role-based access control
     *
     * The auth words model manages the many-to-many relationship between users and
     * their authorization words (roles/permissions). Must support user_id and auth_word fields.
     *
     * @param Model|null $model If provided, sets the auth words model and returns the previous value
     * @return Model|null The current auth words model (if getting) or previous model (if setting)
     * @throws RuntimeException If model doesn't support required authorization fields
     * @since 1.0.0
     *
     * @example Get Current Model
     * ```php
     * $current = AuthManager::userAuthWordsModel();   // Get current model
     * if ($current) {
     *     echo "Auth model: " . get_class($current);
     * }
     * ```
     *
     * @example Set Authorization Model
     * ```php
     * $old = AuthManager::userAuthWordsModel(new UserAuthWords());
     * echo "Auth model changed from: " . ($old ? get_class($old) : 'none');
     * ```
     */
    public static function userAuthWordsModel(?Model $model = null): ?Model
    {
        $old = self::$userAuthWordsModel;
        if ($model !== null) {
            self::$userAuthWordsModel = $model;
        }
        return $old;
    }

    /**
     * Attempt to authenticate user with username and password - always redirects, never returns
     *
     * Handles complete login workflow including password verification, failed attempt tracking,
     * account lockout enforcement, and session creation. This method performs all authentication
     * logic and always redirects the user to either a success or failure destination.
     *
     * ## Successful Login Process
     * - Resets failed attempt counters in database
     * - Creates secure session using Safe class
     * - Redirects to return URL (if set) or configured success destination
     *
     * ## Failed Login Process
     * - Increments failed attempt counter in database
     * - Applies account lockout if max attempts exceeded
     * - Redirects to login page with appropriate error flash message
     *
     * ## Security Features
     * - Uses `password_verify()` for secure password comparison
     * - Protects against timing attacks through consistent processing
     * - Enforces account lockouts to prevent brute force attacks
     * - Cleans up stale session data before creating new session
     *
     * @param string $username The username to authenticate against
     * @param string $password The plaintext password to verify (never stored)
     * @return never This method never returns - always redirects and calls exit
     * @throws RuntimeException If user or auth words models aren't configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Login Form Processing
     * ```php
     * if ($_POST['login']) {
     *     AuthManager::login($_POST['username'], $_POST['password']);
     *     // Code below this line never executes - user is redirected
     * }
     * ```
     *
     * @example With Return URL
     * ```php
     * AuthManager::setReturnUrl('/admin/dashboard');
     * AuthManager::login($username, $password);
     * // User goes to dashboard on success, login page on failure
     * ```
     */
    public static function login(string $username, string $password): never
    {
        self::ensureModelsConfigured();

        // Find user by username
        $user = (clone self::$userModel)
            ->where('username', '=', $username)
            ->first();

        if (!$user) {
            self::setFlash('error', 'Invalid username or password.');
            header('Location: ' . self::$loginUrl);
            exit;
        }

        // Check if user is currently locked out
        if (!empty($user->locked_until) && strtotime($user->locked_until) > time()) {
            self::setFlash('error', 'Account is temporarily locked due to too many failed attempts.');
            header('Location: ' . self::$loginUrl);
            exit;
        }

        // Verify password
        if (!password_verify($password, $user->password_hash)) {
            self::handleFailedLogin($user);
            header('Location: ' . self::$loginUrl);
            exit;
        }

        // Successful login - reset failure tracking and redirect
        self::handleSuccessfulLogin($user);

        // Get return URL or use default
        $returnUrl = self::getReturnUrl() ?? self::$afterLogoutUrl;
        header('Location: ' . $returnUrl);
        exit;
    }

    /**
     * Log out current user, clean up all session data, and redirect - never returns
     *
     * Performs complete logout workflow including session cleanup, return URL clearing,
     * and user notification. This method ensures all authentication-related data is
     * properly removed from the session to prevent security issues.
     *
     * ## Logout Process
     * - Removes all authentication data from Safe session storage
     * - Clears saved return URLs to prevent stale redirects
     * - Sets success flash message for user feedback
     * - Redirects to configured after-logout URL and exits
     *
     * ## Security Considerations
     * - Completely clears session authentication state
     * - Prevents session fixation by removing all auth data
     * - Cleans up potentially stale return URL redirects
     * - Provides clear feedback that logout was successful
     *
     * @return never This method never returns - always redirects and calls exit
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Simple Logout
     * ```php
     * AuthManager::logout();
     * // User is redirected to after-logout page - code below never runs
     * ```
     *
     * @example In Logout Handler
     * ```php
     * if ($_POST['logout'] || $_GET['logout']) {
     *     AuthManager::logout();
     *     // Automatic redirect to configured destination
     * }
     * ```
     */
    public static function logout(): never
    {
        Safe::forget('auth_user_id');
        Safe::forget('auth_last_activity');
        Safe::forget('auth_login_time');
        Safe::forget('return_url_after_login'); // Clear stale return URL

        self::setFlash('success', 'You have been logged out successfully.');

        header('Location: ' . self::$afterLogoutUrl);
        exit;
    }

    /**
     * Check if user is currently authenticated with a valid, non-expired session
     *
     * Performs comprehensive session validation including existence check, timeout
     * verification, and activity timestamp updates. This method is the authoritative
     * way to determine if a user has valid authentication credentials.
     *
     * ## Validation Process
     * 1. Verifies user ID exists in Safe session storage
     * 2. Checks if session has exceeded inactivity timeout duration
     * 3. Updates last activity timestamp for valid sessions
     * 4. Redirects to timeout URL if session has expired
     *
     * ## Session Timeout Handling
     * - Compares current time against stored last activity timestamp
     * - Automatically redirects to timeout URL with flash message if expired
     * - Updates activity timestamp to current time for valid sessions
     * - Uses configured `timeoutSeconds` for inactivity threshold
     *
     * @return bool True if user has valid authentication, false if not logged in
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Basic Authentication Check
     * ```php
     * if (AuthManager::isLoggedIn()) {
     *     // User is authenticated and session is valid
     *     $user = AuthManager::user();
     *     echo "Welcome, " . $user->username;
     * } else {
     *     // Not authenticated or session expired
     *     AuthManager::forceLogin('Please log in to continue');
     * }
     * ```
     *
     * @example Page Guard Pattern
     * ```php
     * if (!AuthManager::isLoggedIn()) {
     *     AuthManager::setReturnUrl($_SERVER['REQUEST_URI']);
     *     AuthManager::forceLogin('Please log in to access this page');
     * }
     * // Page content here - only reached by authenticated users
     * ```
     */
    public static function isLoggedIn(): bool
    {
        // First check: Do we have a user ID in the session?
        if (!Safe::has('auth_user_id')) {
            return false;
        }

        // Second check: Has the session timed out?
        $lastActivity = Safe::get('auth_last_activity');
        if ($lastActivity && (time() - $lastActivity > self::$timeoutSeconds)) {
            // Session has timed out - clean up
            Safe::forget('auth_user_id');
            Safe::forget('auth_last_activity');
            Safe::forget('auth_login_time');
            Safe::forget('return_url_after_login');

            self::setFlash('warning', 'Your session has expired due to inactivity.');
            return false;
        }

        // All checks passed - update the activity timestamp
        Safe::set('auth_last_activity', time());
        return true;
    }

    /**
     * Check if current authenticated user has a specific authorization word (role/permission)
     *
     * Verifies both user authentication and specific authorization. Authorization words
     * are case-insensitive and automatically normalized to lowercase for consistent
     * matching across the application.
     *
     * ## Authorization Logic
     * - First ensures user is logged in with valid session
     * - Retrieves all user authorization words from database
     * - Performs case-insensitive comparison with requested auth word
     * - Returns false immediately if user is not authenticated
     *
     * @param string $authWord The authorization word (role/permission) to check for
     * @return bool True if user is authenticated AND has the specified authorization
     * @throws RuntimeException If auth words model is not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Admin Panel Access
     * ```php
     * if (AuthManager::isAuthorized('admin')) {
     *     // User has admin privileges
     *     showAdminPanel();
     * } else {
     *     echo "Admin access required";
     * }
     * ```
     *
     * @example Manager Tools Access
     * ```php
     * if (AuthManager::isAuthorized('manager')) {
     *     // User has manager privileges
     *     showManagerTools();
     * }
     * ```
     */
    public static function isAuthorized(string $authWord): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $userAuthWords = self::getUserAuthWords();
        return in_array(strtolower(trim($authWord)), $userAuthWords, true);
    }

    /**
     * Check if user has at least one of the specified authorization words (OR logic)
     *
     * Evaluates multiple authorization words with OR logic - returns true if the user
     * has ANY of the provided authorization words. Useful for "admin OR manager" type
     * access control where multiple roles can access the same resource.
     *
     * ## OR Logic Implementation
     * - Iterates through provided authorization words
     * - Returns true immediately when first match is found
     * - All authorization words are normalized to lowercase
     * - Short-circuits on first successful match for performance
     *
     * @param array<string> $authWords Array of authorization words to check for
     * @return bool True if user is authenticated AND has at least one specified authorization
     * @throws RuntimeException If auth words model is not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Leadership Dashboard Access
     * ```php
     * if (AuthManager::isAuthorizedAny(['admin', 'manager', 'supervisor'])) {
     *     // User has at least one leadership role
     *     showLeadershipDashboard();
     * }
     * ```
     *
     * @example Multiple Role Options
     * ```php
     * if (AuthManager::isAuthorizedAny(['admin', 'manager'])) {
     *     // User has admin OR manager privileges
     *     showManagementTools();
     * }
     * ```
     */
    public static function isAuthorizedAny(array $authWords): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $userAuthWords = self::getUserAuthWords();

        foreach ($authWords as $authWord) {
            if (in_array(strtolower(trim($authWord)), $userAuthWords, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified authorization words (AND logic)
     *
     * Evaluates multiple authorization words with AND logic - returns true only if the user
     * has ALL of the provided authorization words. Useful for "admin AND finance" type
     * access control where multiple specific permissions are required.
     *
     * ## AND Logic Implementation
     * - Iterates through all provided authorization words
     * - Returns false immediately when first missing authorization is found
     * - All authorization words are normalized to lowercase
     * - Short-circuits on first failed match for performance
     *
     * @param array<string> $authWords Array of authorization words that must all be present
     * @return bool True if user is authenticated AND has all specified authorizations
     * @throws RuntimeException If auth words model is not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Financial Admin Access
     * ```php
     * if (AuthManager::isAuthorizedAll(['admin', 'finance'])) {
     *     // User has both admin AND finance privileges
     *     showFinancialAdminTools();
     * }
     * ```
     *
     * @example Complex Permission Requirements
     * ```php
     * if (AuthManager::isAuthorizedAll(['manager', 'hr', 'payroll'])) {
     *     // User has all three specific privileges
     *     showPayrollManagement();
     * }
     * ```
     */
    public static function isAuthorizedAll(array $authWords): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $userAuthWords = self::getUserAuthWords();

        foreach ($authWords as $authWord) {
            if (!in_array(strtolower(trim($authWord)), $userAuthWords, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the current authenticated user's model instance from the database
     *
     * Retrieves the complete user record from the database for the currently authenticated
     * user. This method first verifies authentication, then fetches the full user model
     * with all available fields and relationships.
     *
     * ## Database Query Process
     * - Verifies user authentication with `isLoggedIn()` check
     * - Extracts user ID from Safe session storage
     * - Queries user model using the stored user ID
     * - Returns null if not authenticated or user record not found
     *
     * ## Use Cases
     * - Displaying user profile information
     * - Accessing user preferences and settings
     * - Checking user-specific database fields
     * - Audit logging with user context
     *
     * @return Model|null The complete user model instance, or null if not authenticated
     * @throws RuntimeException If user model is not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Display User Information
     * ```php
     * $user = AuthManager::user();
     * if ($user) {
     *     echo "Welcome, " . htmlspecialchars($user->username);
     *     echo "Email: " . htmlspecialchars($user->email);
     *     echo "Member since: " . $user->created_at;
     * }
     * ```
     *
     * @example Safe Chaining (PHP 8+)
     * ```php
     * $username = AuthManager::user()?->username ?? 'Guest';
     * $isVerified = AuthManager::user()?->email_verified ?? false;
     * ```
     */
    public static function user(): ?Model
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        self::ensureModelsConfigured();

        $userId = Safe::get('auth_user_id');
        return (clone self::$userModel)->find($userId);
    }

    /**
     * Get array of authorization words (roles/permissions) for the current user
     *
     * Retrieves all authorization words associated with the currently authenticated user
     * from the authorization words model. Authorization words are normalized to lowercase
     * and returned as a simple array for easy checking and iteration.
     *
     * ## Database Query Process
     * - Verifies user authentication status first
     * - Queries auth words model filtering by current user ID
     * - Extracts and normalizes authorization word values
     * - Returns empty array if not authenticated or no authorizations found
     *
     * ## Authorization Word Format
     * - All returned values are lowercase for consistency
     * - Duplicates are automatically removed by database constraints
     * - Array values are strings representing role/permission names
     *
     * @return array<string> Array of lowercase authorization words, or empty array if not authenticated
     * @throws RuntimeException If auth words model is not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Display User Roles
     * ```php
     * $permissions = AuthManager::getUserAuthWords();
     * if (!empty($permissions)) {
     *     echo "Your roles: " . implode(', ', $permissions);
     *     // Output: "Your roles: admin, user, manager"
     * }
     * ```
     *
     * @example Check Specific Permission
     * ```php
     * $permissions = AuthManager::getUserAuthWords();
     * if (in_array('admin', $permissions)) {
     *     echo "User has admin role";
     * }
     * ```
     */
    public static function getUserAuthWords(): array
    {
        if (!self::isLoggedIn()) {
            return [];
        }

        self::ensureModelsConfigured();

        $userId = Safe::get('auth_user_id');

        $authRecords = (clone self::$userAuthWordsModel)
            ->where('user_id', '=', $userId)
            ->getAsModels();

        $authWords = [];
        foreach ($authRecords as $record) {
            $authWords[] = strtolower(trim($record->auth_word));
        }

        return array_unique($authWords);
    }

    /**
     * Set flash message for display after redirect (consumed on next request)
     *
     * Flash messages provide user feedback across HTTP redirects by storing the message
     * in the session temporarily. Messages are automatically consumed (removed) when
     * retrieved with `getFlash()`, ensuring they display exactly once.
     *
     * ## Flash Message Lifecycle
     * - Message is stored in Safe session using flash storage
     * - Survives exactly one HTTP redirect cycle
     * - Automatically removed when retrieved with `getFlash()`
     * - Perfect for post-redirect-get pattern feedback
     *
     * ## Common Message Types
     * - `success` - Green/positive feedback (login success, save confirmation)
     * - `error` - Red/negative feedback (login failure, validation errors)
     * - `warning` - Yellow/caution feedback (session timeout, deprecated features)
     * - `info` - Blue/informational feedback (tips, announcements)
     *
     * @param string $type Message type for CSS styling (success, error, warning, info)
     * @param string $message The actual message content to display to the user
     * @return void
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Success Messages
     * ```php
     * AuthManager::setFlash('success', 'Profile updated successfully!');
     * AuthManager::setFlash('success', 'Password changed successfully!');
     * ```
     *
     * @example Error Messages
     * ```php
     * AuthManager::setFlash('error', 'Invalid username or password.');
     * AuthManager::setFlash('error', 'Account is temporarily locked.');
     * ```
     *
     * @example Warning and Info Messages
     * ```php
     * AuthManager::setFlash('warning', 'Session will expire in 5 minutes.');
     * AuthManager::setFlash('info', 'New features are now available.');
     * ```
     */
    public static function setFlash(string $type, string $message): void
    {
        Safe::flash('message_type', $type);
        Safe::flash('message_content', $message);
    }

    /**
     * Retrieve and consume flash message from session (removes after retrieval)
     *
     * Gets the current flash message and automatically removes it from the session,
     * ensuring it displays exactly once. Returns structured data with both message
     * type and content for easy rendering in templates.
     *
     * ## Auto-Consumption Behavior
     * - Message is removed from session when this method is called
     * - Subsequent calls return null until new flash message is set
     * - Prevents accidental duplicate message display
     * - Implements standard flash message pattern
     *
     * ## Return Format
     * Returns associative array with keys:
     * - `type` - Message type for CSS classes (success, error, warning, info)
     * - `message` - The actual message content to display
     *
     * @return array{type: string, message: string}|null Flash message data or null if no message exists
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Display Flash Message
     * ```php
     * $flash = AuthManager::getFlash();
     * if ($flash) {
     *     echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . '">';
     *     echo htmlspecialchars($flash['message']);
     *     echo '</div>';
     * }
     * ```
     *
     * @example Template Integration
     * ```php
     * if ($flash = AuthManager::getFlash()) {
     *     displayAlert($flash['type'], $flash['message']);
     * }
     * ```
     */
    public static function getFlash(): ?array
    {
        $type = Safe::getFlash('message_type');
        $message = Safe::getFlash('message_content');

        if ($type && $message) {
            return [
                'type' => $type,
                'message' => $message
            ];
        }

        return null;
    }

    /**
     * Enforce authentication and optional authorization requirements - redirects if not met
     *
     * Provides declarative access control by checking authentication and authorization
     * requirements. If requirements are not met, automatically redirects with appropriate
     * flash messages. This method never returns when access is denied.
     *
     * ## Access Control Logic
     * - Always requires valid authentication (logged-in user)
     * - Optionally requires specific authorization word(s)
     * - Supports both single auth word and multiple auth words (ANY logic)
     * - Sets appropriate flash messages before redirect
     * - Does NOT automatically set return URLs (controller responsibility)
     *
     * ## Authorization Modes
     * - No auth word: Requires login only
     * - Single string: Requires specific authorization word
     * - Array of strings: Requires ANY of the authorization words (OR logic)
     *
     * @param string|array<string>|null $requiredAuthWord Single auth word, array of auth words, or null for login-only
     * @return void Returns normally if access granted, redirects and exits if denied
     * @throws RuntimeException If models are not configured
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Require Login Only
     * ```php
     * AuthManager::requireAuth();
     * // Code below only runs for authenticated users
     * ```
     *
     * @example Require Specific Role
     * ```php
     * AuthManager::setReturnUrl($_SERVER['REQUEST_URI']);
     * AuthManager::requireAuth('admin');
     * // Only admin users can access this code
     * ```
     *
     * @example Require Multiple Role Options
     * ```php
     * AuthManager::setReturnUrl($_SERVER['REQUEST_URI']);
     * AuthManager::requireAuth(['admin', 'manager']);
     * // Admin OR manager users can access this code
     * ```
     */
    public static function requireAuth(array|string $requiredAuthWord = null): void
    {
        // First check: Is user logged in?
        if (!self::isLoggedIn()) {
            self::setFlash('warning', 'Please log in to access this page.');
            header('Location: ' . self::$loginUrl);
            exit;
        }

        if ($requiredAuthWord !== null) {
            $hasPermission = false;

            if (is_string($requiredAuthWord)) {
                $hasPermission = self::isAuthorized($requiredAuthWord);
            } elseif (is_array($requiredAuthWord)) {
                $hasPermission = self::isAuthorizedAny($requiredAuthWord);
            }

            if (!$hasPermission) {
                self::setFlash('error', 'You do not have permission to access this page.');
                header('Location: ' . self::$afterLogoutUrl);
                exit;
            }
        }

        // If we reach here, user meets all requirements
    }

    /**
     * Set return URL for post-login redirect to user's intended destination
     *
     * Stores a URL in the session that will be used as the redirect destination after
     * successful login. This allows users to return to their intended page after being
     * forced to authenticate, improving user experience by maintaining navigation context.
     *
     * ## URL Management Strategy
     * - Overwrites any existing return URL in session
     * - URL persists until login success or session timeout
     * - Automatically cleared after successful login to prevent reuse
     * - Should be called before redirecting user to login page
     *
     * ## Security Considerations
     * - No validation performed on URL format (trusts caller)
     * - Caller responsible for sanitizing URLs if needed
     * - URLs can be relative or absolute paths
     * - Return URLs are cleared on logout to prevent stale redirects
     *
     * @param string $url The destination URL where user should be sent after login
     * @return void
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Standard Page Protection
     * ```php
     * AuthManager::setReturnUrl($_SERVER['REQUEST_URI']);
     * AuthManager::requireAuth('admin');
     * // User returns to exact same page after login
     * ```
     *
     * @example Secure Redirect Strategy
     * ```php
     * if ($sensitiveAction) {
     *     AuthManager::setReturnUrl('/admin/dashboard');  // Safe destination
     * } else {
     *     AuthManager::setReturnUrl($_SERVER['REQUEST_URI']); // Original page
     * }
     * AuthManager::forceLogin();
     * ```
     */
    public static function setReturnUrl(string $url): void
    {
        Safe::set('return_url_after_login', $url);
    }

    /**
     * Force user to login page with optional message - never returns
     *
     * Immediately redirects user to the configured login URL with an optional flash message.
     * This method is typically used when authentication is required but not present, or
     * when specific access requirements are not met.
     *
     * ## Redirect Process
     * - Sets flash message (if provided) for user feedback
     * - Redirects to configured login URL
     * - Calls exit to stop script execution
     * - Does NOT automatically set return URL (caller responsibility)
     *
     * ## Usage Patterns
     * - Call `setReturnUrl()` first if you want user returned to current page
     * - Provide descriptive message for better user experience
     * - Use for both authentication and authorization failures
     *
     * @param string|null $message Optional flash message to display on login page
     * @return void This method never returns - always redirects and calls exit
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Simple Force Login
     * ```php
     * AuthManager::forceLogin();
     * // User redirected to login with default message
     * ```
     *
     * @example With Custom Message
     * ```php
     * AuthManager::forceLogin('Administrator access required.');
     * // User sees specific message explaining access requirement
     * ```
     *
     * @example With Return URL
     * ```php
     * AuthManager::setReturnUrl('/admin/reports');
     * AuthManager::forceLogin('Please log in to view reports.');
     * // User returned to reports page after successful login
     * ```
     */
    #[NoReturn] public static function forceLogin(?string $message = null): void
    {
        $message = $message ?? 'Please log in to access this page.';
        self::setFlash('warning', $message);
        header('Location: ' . self::$loginUrl);
        exit;
    }

    /**
     * Get and consume the stored return URL for post-login redirect
     *
     * Retrieves the stored return URL from session and automatically removes it to prevent
     * reuse. This method is called internally by `login()` to determine where to redirect
     * users after successful authentication.
     *
     * ## URL Lifecycle
     * - URL is set via `setReturnUrl()` before authentication
     * - Persists through login process in Safe session storage
     * - Retrieved and removed by this method after successful login
     * - Returns null if no return URL was previously set
     *
     * ## Security Notes
     * - URL is automatically cleared to prevent session fixation attacks
     * - Old return URLs are cleared on logout to prevent stale redirects
     * - No validation performed on URL - caller must ensure safety
     *
     * @return string|null The stored return URL, or null if none exists
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Internal Usage by login()
     * ```php
     * // Called internally by login() method:
     * $returnUrl = self::getReturnUrl() ?? self::$afterLogoutUrl;
     * header('Location: ' . $returnUrl);
     * ```
     *
     * @example Manual Retrieval (Rare)
     * ```php
     * $pendingUrl = AuthManager::getReturnUrl();
     * if ($pendingUrl) {
     *     echo "You were trying to access: $pendingUrl";
     * }
     * ```
     */
    public static function getReturnUrl(): ?string
    {
        $url = Safe::get('return_url_after_login');
        Safe::forget('return_url_after_login'); // Clear it after getting
        return $url;
    }

    /**
     * Handle failed login attempt by updating attempt counters and applying lockouts
     *
     * Increments the failed attempt counter, records the failure timestamp, and applies
     * account lockout if the maximum attempts threshold is exceeded. Sets appropriate
     * flash messages indicating remaining attempts or lockout status.
     *
     * ## Failed Login Process
     * 1. Increments failed_attempts counter in user record
     * 2. Updates last_failed timestamp to current time
     * 3. Checks if max attempts exceeded
     * 4. If exceeded, sets locked_until timestamp for lockout period
     * 5. Sets appropriate flash message for user feedback
     * 6. Saves updated user record to database
     *
     * ## Flash Message Logic
     * - Under limit: Shows remaining attempts count
     * - At limit: Indicates account is now locked
     * - Provides clear feedback about security status
     *
     * @param Model $user The user model instance to update
     * @return void
     * @throws Exception If database operations fail
     * @since 1.0.0
     *
     * @example Called by login() Method
     * ```php
     * // Automatically called when password verification fails:
     * if (!password_verify($password, $user->password_hash)) {
     *     self::handleFailedLogin($user);
     *     header('Location: ' . self::$loginUrl);
     *     exit;
     * }
     * ```
     *
     * @used-by AuthManager::login() Called automatically on password failure
     */
    protected static function handleFailedLogin(Model $user): void
    {
        $user->failed_attempts = ($user->failed_attempts ?? 0) + 1;
        $user->last_failed = date('Y-m-d H:i:s');

        if ($user->failed_attempts >= self::$maxLoginAttempts) {
            $user->locked_until = date('Y-m-d H:i:s', time() + self::$lockoutTime);
            self::setFlash('error', 'Too many failed attempts. Account locked.');
        } else {
            $remaining = self::$maxLoginAttempts - $user->failed_attempts;
            self::setFlash('error', "Invalid username or password. $remaining attempts remaining.");
        }

        $user->save();
    }

    /**
     * Handle successful login by resetting failure counters and creating session
     *
     * Resets all failed login tracking fields, creates the authenticated session
     * in Safe storage, and sets a welcome flash message. Called automatically
     * after successful password verification.
     *
     * ## Successful Login Process
     * 1. Resets failed_attempts counter to 0
     * 2. Clears last_failed timestamp
     * 3. Clears locked_until timestamp
     * 4. Saves cleaned user record to database
     * 5. Creates authenticated session with user ID
     * 6. Sets last_activity timestamp for timeout tracking
     * 7. Sets login_time timestamp for session duration tracking
     * 8. Sets welcome flash message with username
     *
     * ## Session Data Created
     * - `auth_user_id` - User's database ID for identity
     * - `auth_last_activity` - Timestamp for timeout checking
     * - `auth_login_time` - Timestamp for session duration
     *
     * @param Model $user The user model instance to update and create session for
     * @return void
     * @throws Exception If Safe session operations fail
     * @since 1.0.0
     *
     * @example Called by login() Method
     * ```php
     * // Automatically called after successful password verification:
     * if (password_verify($password, $user->password_hash)) {
     *     self::handleSuccessfulLogin($user);
     *     // Redirect to return URL or default
     * }
     * ```
     *
     * @used-by AuthManager::login() Called automatically on password success
     */
    protected static function handleSuccessfulLogin(Model $user): void
    {
        // Reset failure tracking
        $user->failed_attempts = 0;
        $user->last_failed = null;
        $user->locked_until = null;
        $user->save();

        // Set session data
        Safe::set('auth_user_id', $user->id);
        Safe::set('auth_last_activity', time());
        Safe::set('auth_login_time', time());

        self::setFlash('success', 'Welcome back, ' . $user->username . '!');
    }

    /**
     * Ensure required models are configured before performing database operations
     *
     * Validates that both the user model and user authorization words model have been
     * properly set via the configuration methods. Throws descriptive runtime exceptions
     * if either model is missing.
     *
     * ## Validation Process
     * 1. Checks if user model is set (for authentication)
     * 2. Checks if auth words model is set (for authorization)
     * 3. Throws RuntimeException with clear instructions if missing
     *
     * ## Configuration Requirements
     * Models must be configured before any authentication operations:
     * - User model: Handles user records and authentication
     * - Auth words model: Handles user permissions/roles
     *
     * @return void
     * @throws RuntimeException If user model or auth words model is not configured
     * @since 1.0.0
     *
     * @example Bootstrap Configuration
     * ```php
     * // Configure models in bootstrap to avoid exceptions:
     * AuthManager::userModel(new User());
     * AuthManager::userAuthWordsModel(new UserAuthWords());
     * ```
     *
     * @example Exception Messages
     * ```php
     * // If user model not set:
     * // "User model must be set using AuthManager::userModel() before use."
     *
     * // If auth words model not set:
     * // "UserAuthWords model must be set using AuthManager::userAuthWordsModel() before use."
     * ```
     *
     * @used-by Various AuthManager methods that require database access
     */
    protected static function ensureModelsConfigured(): void
    {
        if (self::$userModel === null) {
            throw new RuntimeException('User model must be set using AuthManager::userModel() before use.');
        }

        if (self::$userAuthWordsModel === null) {
            throw new RuntimeException('UserAuthWords model must be set using AuthManager::userAuthWordsModel() before use.');
        }
    }
}