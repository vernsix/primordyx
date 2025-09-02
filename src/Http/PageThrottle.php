<?php
/**
 * File: /vendor/vernsix/primordyx/src/PageThrottle.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/PageThrottle.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;

/**
 * Class PageThrottle
 *
 * PageThrottle provides simple per-session throttling logic for route access,
 * rate-limiting based on count and time period. It supports per-key limits,
 * IP whitelisting, and session-based permanent exemptions.
 *
 *  PageThrottle usage examples:
 *
 *  1. Basic throttling:
 *     Limit access to a page to 20 hits per hour:
 *
 *     if (PageThrottle::isThrottled('cat_of_the_moment', 20, 3600)) {
 *         return View::output('cat-throttle.php');
 *     }
 *     PageThrottle::increment('cat_of_the_moment', 3600);
 *     return View::output('cat-approved.php');
 *
 *  2. Whitelist a specific IP (no throttling ever for it):
 *
 *     PageThrottle::whitelist('192.168.1.10');
 *
 *  3. Bypass throttling for a specific key for this session (e.g. after login):
 *
 *     PageThrottle::neverThrottle('upload_documents');
 *
 *  4. Bypass all throttling for this session:
 *
 *     if ($user->isAdmin()) {
 *         PageThrottle::neverThrottleAll();
 *     }
 *
 *  5. Inspect the current throttle info for a key:
 *
 *     $info = PageThrottle::info('cat_of_the_moment');
 *     if ($info) {
 *         echo 'Hit count: ' . $info['count'] . ', first hit at ' . date('r', $info['first_hit']);
 *     }
 *
 *  6. Reset a specific key's throttle count:
 *
 *     PageThrottle::reset('upload_documents');
 *
 *  7. Use an alternate session key for isolating subsystems (e.g. API vs frontend):
 *
 *     $oldKey = PageThrottle::SessionKey('api');
 *     if (PageThrottle::isThrottled('v1.bulk_submit', 100, 60)) {
 *         return jsonResponse(['error' => 'Rate limited. Try again.'], 429);
 *     }
 *     PageThrottle::increment('v1.bulk_submit', 60);
 *     PageThrottle::SessionKey($oldKey); // restore original
 *
 *   8. Show time remaining until throttle reset:
 *
 *      if (PageThrottle::isThrottled('cat_of_the_moment', 20, 3600)) {
 *          $seconds = PageThrottle::timeRemaining('cat_of_the_moment', 20, 3600);
 *          echo 'You’ve reached your limit. Try again in ' . ceil($seconds / 60) . ' minute(s).';
 *          return View::output('cat-throttle.php');
 *      }
 *      PageThrottle::increment('cat_of_the_moment', 3600);
 *      return View::output('cat-approved.php');
 *
 * @since       1.0.0
 *
 */
class PageThrottle
{

    /**
     * Session namespace key for storing all throttle-related data
     *
     * Defines the top-level session key under which all throttle data, exemptions,
     * and configuration is stored. Enables isolation of throttling contexts within
     * the same session for multi-subsystem applications (e.g., separate API and
     * frontend throttling).
     *
     * ## Session Structure
     * Creates session structure: $_SESSION[$sessionKey]['throttle'][...]
     * All throttle operations reference this key for data storage and retrieval.
     *
     * ## Context Isolation
     * Different session keys enable independent throttling contexts:
     * - 'frontend' - Web interface throttling
     * - 'api' - API endpoint throttling
     * - 'admin' - Administrative interface throttling
     *
     * @var string Session namespace for throttle data storage
     * @since 1.0.0
     */
    protected static string $sessionKey = 'primordyx';

    /**
     * Global IP address whitelist for complete throttling exemption
     *
     * Array of IP addresses that are permanently exempt from all throttling checks.
     * Provides highest-priority exemption that bypasses all rate limiting logic
     * regardless of session state or throttle key configuration.
     *
     * ## Exemption Priority
     * IP whitelist has highest exemption priority and overrides:
     * - All session-based exemptions
     * - Per-key throttle limits
     * - Global session exemptions
     *
     * ## Initialization Requirements
     * Must be populated during application bootstrap before any throttle checks.
     * IP addresses should include trusted servers, admin networks, and monitoring
     * systems that require unrestricted access.
     *
     * ## Security Considerations
     * Whitelist should only include trusted IP addresses as it provides complete
     * bypass of rate limiting protections. Review regularly and limit to essential IPs.
     *
     * @var array<string> IP addresses with complete throttling exemption
     * @since 1.0.0
     */
    protected static array $whitelist = [];

    /**
     * Get or set the session namespace key for throttle data storage
     *
     * Provides read/write access to the session key used for throttle data isolation.
     * Enables switching between different throttling contexts within the same session,
     * useful for applications with multiple subsystems requiring separate rate limits.
     *
     * ## Context Switching
     * Returns the previous session key when setting a new value, enabling context
     * switching with restoration:
     * - Store old key when switching contexts
     * - Restore original key after context-specific operations
     *
     * ## Use Cases
     * - **Multi-tenant applications**: Separate limits per tenant
     * - **API vs Web separation**: Different limits for API and web interfaces
     * - **Testing isolation**: Separate throttle data for testing scenarios
     * - **Subsystem isolation**: Independent throttling for different application areas
     *
     * @param string|null $newKey Optional new session key to set
     * @return string Previous session key before any change (for restoration)
     * @since 1.0.0
     *
     * @example Context Switching
     * ```php
     * // Switch to API throttling context
     * $originalKey = PageThrottle::SessionKey('api');
     *
     * // Perform API-specific throttle operations
     * if (PageThrottle::isThrottled('endpoint', 1000, 3600)) {
     *     // Handle API rate limiting
     * }
     *
     * // Restore original context
     * PageThrottle::SessionKey($originalKey);
     * ```
     *
     * @example Multi-Context Application
     * ```php
     * // Frontend operations (default context)
     * PageThrottle::increment('search', 3600);
     *
     * // Switch to admin context with higher limits
     * $saved = PageThrottle::SessionKey('admin');
     * if (!PageThrottle::isThrottled('bulk_import', 10, 3600)) {
     *     processBulkImport();
     *     PageThrottle::increment('bulk_import', 3600);
     * }
     * PageThrottle::SessionKey($saved);
     * ```
     *
     * @see reset() For clearing throttle data in specific contexts
     */
    public static function SessionKey(?string $newKey = null): string
    {
        $old = self::$sessionKey;
        if ($newKey !== null) {
            self::$sessionKey = $newKey;
        }
        return $old;
    }

    /**
     * Add IP addresses to global throttling exemption whitelist
     *
     * Permanently exempts specified IP addresses from all throttling checks across
     * all session contexts and throttle keys. Provides highest-priority exemption
     * that cannot be overridden by any other throttling configuration.
     *
     * ## Parameter Handling
     * Accepts both single IP strings and arrays of IPs for flexible configuration.
     * All provided IPs are added to the existing whitelist (cumulative operation).
     *
     * ## Exemption Scope
     * Whitelisted IPs are exempt from:
     * - All throttle key restrictions
     * - Time window limitations
     * - Hit count limits
     * - Session-based throttling
     *
     * ## Bootstrap Integration
     * Should be called during application initialization to establish trusted IP
     * exemptions before any throttle checks are performed.
     *
     * @param string|array<string> $ips Single IP address or array of IP addresses
     * @return void
     * @since 1.0.0
     *
     * @example Single IP Whitelist
     * ```php
     * // Exempt admin server from throttling
     * PageThrottle::whitelist('192.168.1.100');
     * ```
     *
     * @example Multiple IP Whitelist
     * ```php
     * // Exempt multiple trusted servers
     * PageThrottle::whitelist([
     *     '192.168.1.10',  // Admin server
     *     '10.0.0.5',      // Monitoring server
     *     '172.16.0.20'    // Load balancer
     * ]);
     * ```
     *
     * @example Dynamic Whitelist Configuration
     * ```php
     * // Load trusted IPs from configuration
     * $trustedIPs = config('throttle.whitelist', []);
     * if (!empty($trustedIPs)) {
     *     PageThrottle::whitelist($trustedIPs);
     * }
     * ```
     *
     * @see isWhitelisted() For whitelist checking logic
     */
    public static function whitelist(string|array $ips): void
    {
        foreach ((array)$ips as $ip) {
            self::$whitelist[] = $ip;
        }
    }

    /**
     * Check if current request IP address is globally whitelisted from throttling
     *
     * Verifies if the requesting IP address exists in the global whitelist array.
     * Whitelisted IPs receive complete exemption from all throttling checks with
     * highest priority in the exemption hierarchy.
     *
     * ## IP Detection
     * Uses $_SERVER['REMOTE_ADDR'] for IP address detection with empty string
     * fallback for missing values. Considers direct IP connections without
     * proxy or load balancer X-Forwarded-For handling.
     *
     * ## Exemption Priority
     * IP whitelist provides highest-priority exemption that overrides all other
     * throttling logic including session exemptions and per-key limits.
     *
     * @return bool True if current IP is whitelisted, false otherwise
     * @since 1.0.0
     */
    protected static function isWhitelisted(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($ip, self::$whitelist, true);
    }

    /**
     * Check if specified key has session-based throttling exemption
     *
     * Determines if the current session has established a per-key exemption
     * for the specified throttle key. Key-specific exemptions allow unlimited
     * access for specific operations while maintaining throttling for other keys.
     *
     * ## Session Storage Check
     * Examines session storage for per-key exemption flags:
     * $_SESSION[sessionKey]['neverThrottle'][key] = true
     *
     * ## Exemption Scope
     * Per-key exemptions are session-local and key-specific, providing targeted
     * throttling relief without affecting other operations or users.
     *
     * @param string $key Throttle key to check for session exemption
     * @return bool True if key is exempt in current session, false otherwise
     * @since 1.0.0
     */
    protected static function isNeverThrottled(string $key): bool
    {
        return !empty($_SESSION[self::$sessionKey]['neverThrottle'][$key]);
    }

    /**
     * Check if current session has global throttling exemption for all keys
     *
     * Determines if the current session has been granted blanket exemption from
     * all throttling checks. Global exemptions provide unrestricted access across
     * all throttle keys and operations.
     *
     * ## Session Storage Check
     * Examines session storage for global exemption flag:
     * $_SESSION[sessionKey]['neverThrottleAll'] = true
     *
     * ## Exemption Hierarchy Priority
     * Global session exemption has second-highest priority in exemption hierarchy,
     * below IP whitelist but above per-key session exemptions.
     *
     * @return bool True if session is globally exempt, false otherwise
     * @since 1.0.0
     */
    protected static function isGloballyExempt(): bool
    {
        return !empty($_SESSION[self::$sessionKey]['neverThrottleAll']);
    }

    /**
     * Exempt specific throttle key from rate limiting for current session
     *
     * Creates session-based exemption for specified throttle key, allowing unlimited
     * access within current session while maintaining throttling for other keys.
     * Useful for post-authentication scenarios or premium user privileges.
     *
     * ## Session Persistence
     * Exemption persists for the entire session duration across multiple requests.
     * Does not affect other sessions or users - exemption is strictly local.
     *
     * ## Exemption Scope
     * - **Key-Specific**: Only affects the specified throttle key
     * - **Session-Local**: Does not impact other user sessions
     * - **Request Persistent**: Survives across multiple page requests
     * - **Context Aware**: Respects current session key context
     *
     * ## Common Use Cases
     * - **Post-Login Exemptions**: Remove throttling after successful authentication
     * - **Premium Features**: Exempt premium users from upload/API throttling
     * - **Administrative Access**: Allow unlimited admin operations
     * - **One-Time Exemptions**: Temporary exemption for specific operations
     *
     * @param string $key Throttle key to exempt from rate limiting
     * @return void
     * @since 1.0.0
     *
     * @example Post-Authentication Exemption
     * ```php
     * // After successful login, remove API throttling
     * if (authenticateUser($credentials)) {
     *     PageThrottle::neverThrottle('api_calls');
     *     PageThrottle::neverThrottle('file_uploads');
     *     redirectToDashboard();
     * }
     * ```
     *
     * @example Premium User Benefits
     * ```php
     * if ($user->subscription === 'premium') {
     *     // Exempt premium users from search throttling
     *     PageThrottle::neverThrottle('search_api');
     *     PageThrottle::neverThrottle('export_data');
     * }
     * ```
     *
     * @example Conditional Exemption
     * ```php
     * // Exempt verified users from upload throttling
     * if ($user->isVerified() && $user->trustScore > 0.8) {
     *     PageThrottle::neverThrottle('document_upload');
     * }
     * ```
     *
     * @see neverThrottleAll() For global session exemption
     * @see isNeverThrottled() For exemption status checking
     */
    public static function neverThrottle(string $key): void
    {
        $_SESSION[self::$sessionKey]['neverThrottle'][$key] = true;
    }

    /**
     * Globally exempt current session from all throttling checks
     *
     * Creates session-wide exemption that bypasses all throttle key restrictions
     * for the current session. Provides blanket exemption for scenarios requiring
     * unrestricted access across all application features.
     *
     * ## Global Exemption Scope
     * - **All Keys**: Exempts every throttle key check
     * - **Session Duration**: Persists for entire session lifetime
     * - **Context Independent**: Works across all session key contexts
     * - **Override Protection**: Cannot be bypassed by individual throttle checks
     *
     * ## Priority in Exemption Hierarchy
     * Global session exemption has second-highest priority, below only IP whitelist:
     * 1. IP Whitelist (highest)
     * 2. Global Session Exemption
     * 3. Per-Key Session Exemption
     * 4. Standard Throttling
     *
     * ## Administrative Use Cases
     * - **Administrator Access**: Exempt admin users from all rate limits
     * - **System Maintenance**: Bypass throttling during maintenance operations
     * - **Testing Scenarios**: Remove throttling constraints for testing
     * - **Emergency Access**: Unrestricted access during incident response
     *
     * @return void
     * @since 1.0.0
     *
     * @example Administrator Exemption
     * ```php
     * // Grant unrestricted access to administrators
     * if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
     *     PageThrottle::neverThrottleAll();
     * }
     * ```
     *
     * @example Maintenance Mode Exemption
     * ```php
     * // Exempt maintenance operations from throttling
     * if (isMaintenanceMode() && $user->hasPermission('maintenance')) {
     *     PageThrottle::neverThrottleAll();
     * }
     * ```
     *
     * @example Emergency Access
     * ```php
     * // Emergency bypass for critical system operations
     * if ($emergencyMode && validateEmergencyAccess($token)) {
     *     PageThrottle::neverThrottleAll();
     *     logEmergencyAccess($user, $timestamp);
     * }
     * ```
     *
     * @see neverThrottle() For key-specific exemptions
     * @see isGloballyExempt() For exemption status checking
     */
    public static function neverThrottleAll(): void
    {
        $_SESSION[self::$sessionKey]['neverThrottleAll'] = true;
    }

    /**
     * Clear throttle counter and time window for specific key
     *
     * Removes all throttle tracking data for specified key, effectively resetting
     * the rate limit counter and time window. Useful for manual resets, testing
     * scenarios, or administrative interventions.
     *
     * ## Reset Effects
     * - **Counter Reset**: Clears hit count to zero
     * - **Time Window Reset**: Removes time window tracking
     * - **Immediate Access**: Next check will treat key as unused
     * - **Session Specific**: Only affects current session's throttle data
     *
     * ## Data Removal
     * Completely removes throttle entry from session storage rather than setting
     * values to zero, ensuring clean state for subsequent operations.
     *
     * ## Administrative Applications
     * - **Manual Intervention**: Admin tools for user assistance
     * - **Testing Reset**: Clean slate for testing scenarios
     * - **Error Recovery**: Reset after system errors or false positives
     * - **Bulk Operations**: Reset before planned high-volume operations
     *
     * @param string $key Throttle key to reset and clear tracking data
     * @return void
     * @since 1.0.0
     *
     * @example Administrative Reset
     * ```php
     * // Admin tool for resetting user throttle limits
     * if ($user->hasRole('admin') && $_POST['reset_user_throttle']) {
     *     PageThrottle::reset('api_calls');
     *     PageThrottle::reset('file_uploads');
     *     addFlashMessage('User throttle limits reset successfully');
     * }
     * ```
     *
     * @example Testing Environment Reset
     * ```php
     * // Reset throttles before integration tests
     * if (isTestingEnvironment()) {
     *     PageThrottle::reset('test_api');
     *     PageThrottle::reset('test_uploads');
     *     runIntegrationTests();
     * }
     * ```
     *
     * @example Error Recovery Reset
     * ```php
     * // Reset throttles after resolving system issues
     * if ($systemErrorResolved) {
     *     $affectedKeys = ['payment_processing', 'email_sending', 'sms_api'];
     *     foreach ($affectedKeys as $key) {
     *         PageThrottle::reset($key);
     *     }
     *     logSystemRecovery('Throttle limits reset after error resolution');
     * }
     * ```
     *
     * @see info() For checking current throttle status
     * @see increment() For normal usage tracking
     */
    public static function reset(string $key): void
    {
        unset($_SESSION[self::$sessionKey]['throttle'][$key]);
    }

    /**
     * Retrieve current throttle tracking information for debugging and monitoring
     *
     * Returns detailed throttle state for specified key including hit count and
     * time window information. Enables monitoring, debugging, and administrative
     * oversight of throttling behavior without affecting throttle state.
     *
     * ## Return Structure
     * Returns associative array with throttle details:
     * - **count**: Current hit count within time window
     * - **first_hit**: Unix timestamp of time window start
     * - **null**: Returned if key has no throttle tracking data
     *
     * ## Monitoring Applications
     * - **Usage Analytics**: Track API usage patterns over time
     * - **Debugging Tools**: Investigate throttling behavior issues
     * - **Admin Interfaces**: Display current throttle status to administrators
     * - **Performance Monitoring**: Analyze throttle effectiveness and patterns
     *
     * ## Non-Destructive Operation
     * This method is read-only and does not modify throttle state, counters,
     * or time windows. Safe for monitoring and debugging without side effects.
     *
     * @param string $key Throttle key to inspect for current status
     * @return array<string, mixed>|null Throttle info with 'count' and 'first_hit', or null if unused
     * @since 1.0.0
     *
     * @example Administrative Monitoring
     * ```php
     * // Display throttle status in admin dashboard
     * $apiInfo = PageThrottle::info('api_calls');
     * if ($apiInfo) {
     *     echo "API Calls: {$apiInfo['count']} hits since " .
     *          date('Y-m-d H:i:s', $apiInfo['first_hit']);
     *
     *     $windowAge = time() - $apiInfo['first_hit'];
     *     echo "Window age: " . gmdate('H:i:s', $windowAge);
     * } else {
     *     echo "No API usage recorded";
     * }
     * ```
     *
     * @example Usage Analytics
     * ```php
     * // Collect throttle statistics for analysis
     * $keys = ['search', 'upload', 'api_calls', 'downloads'];
     * $stats = [];
     *
     * foreach ($keys as $key) {
     *     $info = PageThrottle::info($key);
     *     $stats[$key] = $info ? [
     *         'hits' => $info['count'],
     *         'window_start' => $info['first_hit'],
     *         'window_age' => time() - $info['first_hit']
     *     ] : null;
     * }
     *
     * logUsageAnalytics($stats);
     * ```
     *
     * @example Debug Output
     * ```php
     * // Debug throttle state for troubleshooting
     * if ($debugMode) {
     *     $throttleKeys = ['login_attempts', 'password_reset', 'api_access'];
     *     foreach ($throttleKeys as $key) {
     *         $info = PageThrottle::info($key);
     *         echo "Key: $key - " . ($info ?
     *             "Count: {$info['count']}, Started: " . date('H:i:s', $info['first_hit']) :
     *             "No activity");
     *     }
     * }
     * ```
     *
     * @see isThrottled() For throttle limit checking
     * @see timeRemaining() For time-based throttle information
     */
    public static function info(string $key): ?array
    {
        return $_SESSION[self::$sessionKey]['throttle'][$key] ?? null;
    }

    /**
     * Check if throttle key has exceeded rate limit without modifying counters
     *
     * Performs read-only throttle limit verification considering exemptions, time
     * windows, and hit counts. Does not increment counters or modify state - use
     * increment() separately after successful access to track usage.
     *
     * ## Exemption Check Priority
     * Evaluates exemptions in priority order before throttle logic:
     * 1. **IP Whitelist**: Global IP exemption (highest priority)
     * 2. **Global Session Exemption**: Session-wide bypass
     * 3. **Per-Key Session Exemption**: Key-specific session bypass
     * 4. **Throttle Logic**: Standard rate limiting evaluation
     *
     * ## Time Window Behavior
     * - **No Previous Usage**: Returns false (not throttled)
     * - **Expired Window**: Returns false (window reset, not throttled)
     * - **Within Window**: Compares count against limit
     * - **At/Over Limit**: Returns true (throttled)
     *
     * ## Recommended Usage Pattern
     * Always check isThrottled() before granting access, then call increment()
     * only after successful operation to maintain accurate tracking.
     *
     * @param string $key Unique throttle identifier (route, action, feature)
     * @param int $limit Maximum allowed hits within time window
     * @param int $seconds Time window duration in seconds
     * @return bool True if throttled (access denied), false if access allowed
     * @since 1.0.0
     *
     * @example Basic Access Control
     * ```php
     * // Check before allowing search operation
     * if (PageThrottle::isThrottled('search', 50, 3600)) {
     *     return response('Too many searches. Try again later.', 429);
     * }
     *
     * $results = performSearch($query);
     * PageThrottle::increment('search', 3600); // Track successful usage
     * return response($results);
     * ```
     *
     * @example API Rate Limiting
     * ```php
     * // Protect API endpoint with rate limiting
     * $rateLimited = PageThrottle::isThrottled('api_endpoint', 1000, 3600);
     * if ($rateLimited) {
     *     $resetTime = PageThrottle::timeRemaining('api_endpoint', 1000, 3600);
     *     return apiError([
     *         'error' => 'Rate limit exceeded',
     *         'retry_after' => $resetTime,
     *         'limit' => 1000,
     *         'window' => 3600
     *     ], 429);
     * }
     *
     * $result = processApiRequest($request);
     * PageThrottle::increment('api_endpoint', 3600);
     * return apiResponse($result);
     * ```
     *
     * @example Conditional Throttling
     * ```php
     * // Different limits based on user type
     * $limit = $user->isPremium() ? 200 : 50;
     * $key = 'file_downloads';
     *
     * if (PageThrottle::isThrottled($key, $limit, 3600)) {
     *     $upgradeMessage = $user->isPremium() ?
     *         'Premium limit reached' :
     *         'Upgrade to premium for higher limits';
     *     return response($upgradeMessage, 429);
     * }
     *
     * $file = serveDownload($fileId);
     * PageThrottle::increment($key, 3600);
     * return $file;
     * ```
     *
     * @see increment() For tracking successful usage
     * @see timeRemaining() For reset time information
     * @see info() For detailed throttle status
     */
    public static function isThrottled(string $key, int $limit, int $seconds): bool
    {
        if (self::isWhitelisted() || self::isGloballyExempt() || self::isNeverThrottled($key)) {
            return false;
        }

        if (!isset($_SESSION[self::$sessionKey]['throttle'][$key])) {
            return false; // No hits yet
        }

        $entry = $_SESSION[self::$sessionKey]['throttle'][$key];
        $elapsed = time() - $entry['first_hit'];

        if ($elapsed > $seconds) {
            return false; // New window
        }

        return $entry['count'] >= $limit;
    }

    /**
     * Track successful access by incrementing throttle counter for specified key
     *
     * Records usage hit for throttle key, managing time window and counter state.
     * Handles window initialization, expiration reset, and counter increment logic.
     * Should be called after successful operation to maintain accurate usage tracking.
     *
     * ## Time Window Management
     * - **New Key**: Initializes counter to 1 and sets current time as window start
     * - **Expired Window**: Resets counter to 1 and updates window start time
     * - **Active Window**: Increments counter within existing time window
     * - **Window Calculation**: Uses first_hit timestamp + seconds for window duration
     *
     * ## State Management
     * Updates session storage with current counter and time information:
     * ```php
     * $_SESSION[sessionKey]['throttle'][key] = [
     *     'count' => integer,      // Current hit count
     *     'first_hit' => timestamp // Window start time
     * ];
     * ```
     *
     * ## Usage Pattern
     * Call increment() only after successful operations to ensure accurate tracking.
     * Calling before success can lead to false positives and incorrect throttling.
     *
     * @param string $key Throttle identifier for usage tracking
     * @param int $seconds Time window duration for counter reset logic
     * @return void
     * @since 1.0.0
     *
     * @example Successful Operation Tracking
     * ```php
     * // Track usage after successful file upload
     * if (PageThrottle::isThrottled('upload', 10, 3600)) {
     *     return error('Upload limit exceeded');
     * }
     *
     * $uploadResult = processFileUpload($_FILES['document']);
     * if ($uploadResult->success) {
     *     PageThrottle::increment('upload', 3600); // Track successful upload
     *     return success('File uploaded successfully');
     * }
     * // Don't increment on failure - prevents false throttling
     * ```
     *
     * @example API Request Tracking
     * ```php
     * // Track API usage after successful request processing
     * if (!PageThrottle::isThrottled('api_v1', 500, 3600)) {
     *     try {
     *         $response = processApiRequest($requestData);
     *         PageThrottle::increment('api_v1', 3600); // Count successful request
     *         return $response;
     *     } catch (ApiException $e) {
     *         // Don't increment on API errors
     *         return errorResponse($e->getMessage());
     *     }
     * }
     * ```
     *
     * @example Multi-Level Tracking
     * ```php
     * // Track both global and specific operation usage
     * if (!PageThrottle::isThrottled('global_actions', 100, 3600) &&
     *     !PageThrottle::isThrottled('email_send', 20, 3600)) {
     *
     *     $emailSent = sendEmail($recipient, $subject, $body);
     *     if ($emailSent) {
     *         PageThrottle::increment('global_actions', 3600);
     *         PageThrottle::increment('email_send', 3600);
     *     }
     * }
     * ```
     *
     * @see isThrottled() For pre-operation limit checking
     * @see timeRemaining() For window reset timing
     */
    public static function increment(string $key, int $seconds): void
    {
        if (!isset($_SESSION[self::$sessionKey]['throttle'][$key])) {
            $_SESSION[self::$sessionKey]['throttle'][$key] = [
                'count' => 1,
                'first_hit' => time()
            ];
            return;
        }

        $entry = &$_SESSION[self::$sessionKey]['throttle'][$key];
        $elapsed = time() - $entry['first_hit'];

        if ($elapsed > $seconds) {
            $entry['count'] = 1;
            $entry['first_hit'] = time();
        } else {
            $entry['count']++;
        }
    }

    /**
     * Calculate seconds remaining until throttle window reset allows access
     *
     * Determines time until throttle limit resets based on time window duration
     * and current throttle state. Returns zero if key is not currently throttled
     * or if time window has already expired.
     *
     * ## Calculation Logic
     * - **No Throttle Data**: Returns 0 (no restrictions)
     * - **Under Limit**: Returns 0 (access currently allowed)
     * - **Window Expired**: Returns 0 (reset already occurred)
     * - **Over Limit**: Returns remaining seconds until window expires
     *
     * ## Reset Time Calculation
     * Uses window start time (first_hit) plus window duration to calculate
     * when throttle limit will reset and access will be allowed again.
     *
     * ## User Experience Applications
     * - **Error Messages**: "Try again in 5 minutes"
     * - **UI Feedback**: Countdown timers for throttled features
     * - **Retry Logic**: Automatic retry scheduling
     * - **Rate Limit Headers**: HTTP Retry-After header values
     *
     * @param string $key Throttle key to check for reset timing
     * @param int $limit Maximum hits allowed (for limit comparison)
     * @param int $seconds Time window duration for reset calculation
     * @return int Seconds until access allowed again, or 0 if currently allowed
     * @since 1.0.0
     *
     * @example User-Friendly Error Messages
     * ```php
     * if (PageThrottle::isThrottled('search', 20, 3600)) {
     *     $waitTime = PageThrottle::timeRemaining('search', 20, 3600);
     *     $minutes = ceil($waitTime / 60);
     *
     *     return response("Search limit reached. Try again in $minutes minute(s).", 429);
     * }
     * ```
     *
     * @example API Rate Limit Headers
     * ```php
     * $key = 'api_requests';
     * if (PageThrottle::isThrottled($key, 100, 3600)) {
     *     $retryAfter = PageThrottle::timeRemaining($key, 100, 3600);
     *
     *     header("X-RateLimit-Limit: 100");
     *     header("X-RateLimit-Reset: " . (time() + $retryAfter));
     *     header("Retry-After: $retryAfter");
     *
     *     http_response_code(429);
     *     return json_encode(['error' => 'Rate limit exceeded']);
     * }
     * ```
     *
     * @example Automatic Retry Logic
     * ```php
     * function makeApiCall($endpoint, $data) {
     *     if (PageThrottle::isThrottled('external_api', 50, 3600)) {
     *         $waitTime = PageThrottle::timeRemaining('external_api', 50, 3600);
     *
     *         // Schedule retry after throttle window expires
     *         scheduleDelayedJob('api_retry', $data, $waitTime);
     *         return ['status' => 'queued', 'retry_in' => $waitTime];
     *     }
     *
     *     $result = callExternalApi($endpoint, $data);
     *     PageThrottle::increment('external_api', 3600);
     *     return $result;
     * }
     * ```
     *
     * @example Dynamic UI Updates
     * ```php
     * // JavaScript countdown integration
     * if (PageThrottle::isThrottled('feature_usage', 10, 300)) {
     *     $resetIn = PageThrottle::timeRemaining('feature_usage', 10, 300);
     *     echo "<script>startCountdown($resetIn);</script>";
     *     echo "<p>Feature available again in <span id='countdown'>$resetIn</span> seconds</p>";
     * }
     * ```
     *
     * @see isThrottled() For throttle status checking
     * @see info() For detailed throttle state information
     */
    public static function timeRemaining(string $key, int $limit, int $seconds): int
    {
        if (!isset($_SESSION[self::$sessionKey]['throttle'][$key])) {
            return 0; // No record = no throttle
        }

        $entry = $_SESSION[self::$sessionKey]['throttle'][$key];
        $elapsed = time() - $entry['first_hit'];

        // Not yet at limit or time has passed
        if ($elapsed > $seconds || $entry['count'] < $limit) {
            return 0;
        }

        return max(0, $seconds - $elapsed);
    }

}
