<?php
/**
 * File: /vendor/vernsix/primordyx/src/HttpClientConfig.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Http/HttpClientConfig.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Http;


use Primordyx\PrimordyxInfo;
use Primordyx\Utils\RandomStuff;

/**
 * HTTP Client Configuration Management for RESTful API Requests
 *
 * Comprehensive configuration class for HTTP client operations providing secure authentication,
 * custom headers, timeout management, proxy support, and debugging capabilities. Designed to
 * work seamlessly with HttpClient for consistent, secure, and monitorable API communication.
 *
 * ## Key Features
 * - **Flexible Authentication**: Basic auth, Bearer tokens, and custom authorization headers
 * - **Header Management**: Raw string headers supporting complex HTTP header scenarios
 * - **Timeout Control**: Separate connection and response timeout configuration
 * - **Security Defaults**: Secure configuration options with sensible fallbacks
 * - **Debug Support**: Verbose logging and named instances for performance monitoring
 * - **Proxy Support**: HTTP/HTTPS proxy configuration for enterprise environments
 * - **Serialization**: Export configuration as arrays or JSON for debugging/logging
 *
 * ## Authentication Methods
 * - **Basic Auth**: Username/password authentication for traditional APIs
 * - **Bearer Tokens**: OAuth2 and API key authentication via Authorization header
 * - **Custom Headers**: Full control over authentication header format and content
 *
 * ## Header Storage Design
 * Headers are stored as raw strings (not key-value pairs) to support:
 * - Multiple headers with same key name
 * - Complex header formats beyond simple key:value pairs
 * - Exact header formatting control for strict API requirements
 * - Case-insensitive header matching for HTTP standard compliance
 *
 * ## Usage Patterns
 * ```php
 * // Basic API configuration
 * $config = new HttpClientConfig('user_api');
 * $config->timeout(30);
 * $config->bearerToken('abc123');
 *
 * // Advanced enterprise configuration
 * $config = new HttpClientConfig('secure_api');
 * $config->proxy('http://proxy.corp.com:8080');
 * $config->setBasicAuth('api_user', 'secret');
 * $config->addHeader('X-API-Version', '2.1');
 * $config->verboseLogging(true);
 *
 * // Use with HttpClient
 * $result = HttpClient::get('https://api.example.com/users', $config);
 * ```
 *
 * ## Performance Integration
 * - Named instances integrate with Timer class for performance monitoring
 * - Automatic timer creation using configuration name for request tracking
 * - Verbose logging captures detailed cURL communication for debugging
 * - Serializable configuration enables logging and audit trail creation
 *
 * @package Primordyx
 * @since 1.0.0
 */
class HttpClientConfig
{

    /**
     * Configuration instance identifier for debugging and performance monitoring
     *
     * Used for Timer integration, logging, and distinguishing between concurrent HTTP requests.
     * Automatically generated using RandomStuff::words() if not provided in constructor.
     * Never transmitted in HTTP requests - purely internal identifier.
     *
     * @var string
     * @since 1.0.0
     */
    private string $name;

    /**
     * Array of HTTP headers stored as raw strings.
     *
     * Each item should be a full header string, e.g., "Authorization: Bearer abc123".
     * Note: This is NOT a key-value associative array, because some HTTP headers may appear multiple times
     * or have structures not representable as a simple key => value pair.
     *
     * Examples:
     *   - "Authorization: Bearer xyz"
     *   - "Accept: application/json"
     *   - "X-Custom-Flag"
     *
     * Internal methods normalize header keys for case-insensitive matching when adding/removing headers.
     *
     * @var string[]
     * @since 1.0.0
     */
    private array $headers = [];

    /**
     * Request timeout in seconds for HTTP response completion
     *
     * @var int|null
     * @since 1.0.0
     */
    private ?int $timeout = 30;

    /**
     * Connection establishment timeout in seconds
     *
     * @var int|null
     * @since 1.0.0
     */
    private ?int $connectTimeout = null;

    /**
     * Username for HTTP Basic Authentication
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $authUser = null;

    /**
     * Password for HTTP Basic Authentication
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $authPass = null;

    /**
     * HTTP/HTTPS proxy server URL
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $proxy = null;

    /**
     * Enable detailed cURL verbose logging for debugging
     *
     * @var bool
     * @since 1.0.0
     */
    private bool $verboseLogging = false;

    /**
     * OAuth2 Bearer token for Authorization header
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $bearerToken = null;

    /**
     * Custom User-Agent string for HTTP requests
     *
     * @var string|null
     * @since 1.0.0
     */
    private ?string $userAgent = null;

    /**
     * Initialize HTTP client configuration with optional identifier name
     *
     * Creates new configuration instance with secure defaults and optional naming for
     * debugging and performance monitoring. Name can be integrated with Timer class for request
     * tracking and verbose logging identification.
     *
     * ## Name Generation
     * - Uses provided name if specified
     * - Generates random human-readable name via RandomStuff::words() if empty
     * - Name used for Timer integration and request identification
     * - Never transmitted over HTTP - purely internal identifier
     *
     * ## Default Configuration
     * - 30-second request timeout
     * - No authentication configured
     * - Empty headers array
     * - Verbose logging disabled
     * - Default User-Agent auto-generated when first accessed
     *
     * @param string $name Optional identifier for debugging and performance monitoring
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Named configuration for monitoring
     * $config = new HttpClientConfig('payment_api');
     *
     * // Auto-generated name for ad-hoc requests
     * $config = new HttpClientConfig();
     * echo $config->name(); // e.g., "brave-elephant-42"
     * ```
     */
    public function __construct(string $name = '')
    {
        if (!empty($name)) {
            $this->name = $name;
        } else {
            $this->name = RandomStuff::words();
        }
    }

    /**
     * Get the configuration instance identifier
     *
     * Returns the name used for debugging, performance monitoring, and Timer integration.
     * Name is either provided during construction or auto-generated using random words.
     *
     * @return string Configuration instance identifier
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config = new HttpClientConfig('api_client');
     * echo $config->name(); // "api_client"
     * ```
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * Export complete configuration as associative array
     *
     * Returns all configuration settings in array format suitable for debugging,
     * logging, serialization, or configuration transfer between instances.
     * Includes all authentication, timeout, proxy, and header settings.
     *
     * @return array<string, mixed> Complete configuration array with all settings
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config = new HttpClientConfig('test');
     * $config->timeout(60);
     * $config->bearerToken('abc123');
     *
     * $array = $config->asArray();
     * // Returns: ['name' => 'test', 'timeout' => 60, 'bearer_token' => 'abc123', ...]
     * ```
     */
    public function asArray(): array
    {
        return [
            'name' => $this->name,
            'headers' => $this->headers,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'auth_user' => $this->authUser,
            'auth_pass' => $this->authPass,
            'proxy' => $this->proxy,
            'verbose' => $this->verboseLogging,
            'bearer_token' => $this->bearerToken,
            'user_agent' => $this->userAgent,
        ];
    }

    /**
     * Export configuration as JSON string for logging or serialization
     *
     * Converts complete configuration to JSON format using standard json_encode.
     * Useful for configuration logging, debugging output, or storing configuration
     * state for later restoration.
     *
     * @return string JSON-encoded configuration string
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config = new HttpClientConfig('api');
     * $config->addHeader('Accept', 'application/json');
     *
     * $json = $config->asJson();
     * error_log("API Config: $json");
     * ```
     */
    public function asJson(): string
    {
        return json_encode(self::asArray());
    }

    /**
     * Reset all configuration options to default values
     *
     * Clears all headers, authentication, timeouts, and other settings while preserving
     * the configuration instance name. Useful for reusing configuration objects or
     * clearing sensitive data after use.
     *
     * ## Reset Behavior
     * - Clears all headers array
     * - Resets timeout to 30 seconds default
     * - Clears authentication credentials
     * - Removes proxy configuration
     * - Disables verbose logging
     * - Preserves instance name (does not change)
     *
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config = new HttpClientConfig('reusable');
     * $config->bearerToken('abc123');
     * $config->timeout(60);
     *
     * $config->reset(); // Clear sensitive data
     * // Name is preserved, but auth and timeout are reset
     * ```
     */
    public function reset(): void
    {
        $this->headers = [];
        $this->timeout = 30;
        $this->connectTimeout = null;
        $this->authUser = null;
        $this->authPass = null;
        $this->proxy = null;
        $this->verboseLogging = false;
        $this->bearerToken = null;
        $this->userAgent = null;
    }


    // headers ---------------------------------------------------------------------------------

    /**
     * Replace entire header collection with new raw header strings
     *
     * Completely replaces current headers with provided array. Each header must be
     * formatted as complete "Key: Value" string. Does not validate header format
     * or content - provides maximum flexibility for complex HTTP scenarios.
     *
     * ## Header Format Requirements
     * - Each array element should be complete header string
     * - Format: "HeaderName: HeaderValue"
     * - Supports non-standard headers and complex authorization schemes
     * - No automatic validation or normalization applied
     *
     * @param array<string> $headers Array of complete header strings in "Key: Value" format
     * @return array<string> Previous headers that were replaced
     * @since 1.0.0
     *
     * @example
     * ```php
     * $newHeaders = [
     *     'Content-Type: application/json',
     *     'Authorization: Bearer xyz123',
     *     'X-Custom-Header: custom-value'
     * ];
     *
     * $oldHeaders = $config->setAllHeaders($newHeaders);
     * ```
     */
    public function setAllHeaders(array $headers = []): array
    {
        $old = $this->headers;
        $this->headers = $headers;
        return $old;
    }

    /**
     * Retrieve all configured headers as raw string array
     *
     * Returns complete header collection as array of formatted header strings.
     * Headers are not parsed into key-value pairs to preserve exact formatting
     * and support complex HTTP header scenarios.
     *
     * @return array<string> Array of complete header strings in "Key: Value" format
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config->addHeader('Accept', 'application/json');
     * $config->addHeader('Authorization', 'Bearer abc123');
     *
     * $headers = $config->getAllHeaders();
     * // Returns: ['Accept: application/json', 'Authorization: Bearer abc123']
     * ```
     */
    public function getAllHeaders(): array
    {
        return $this->headers;
    }


    /**
     * Convenience alias for getAllHeaders()
     *
     * Returns all configured headers as raw string array. Provided for improved
     * code readability and developer convenience when accessing header collection.
     *
     * @return array<string> Array of complete header strings in "Key: Value" format
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Both calls are equivalent
     * $headers1 = $config->getAllHeaders();
     * $headers2 = $config->headers();
     * ```
     */
    public function headers(): array
    {
        return $this->getAllHeaders();
    }

    /**
     * Remove first matching header and return its value
     *
     * Internal method for case-insensitive header removal with value capture.
     * Used by header replacement operations to prevent duplicate headers while
     * preserving previous values for return.
     *
     * @param string $key Header name to remove (without colon)
     * @return string|null Value of removed header or null if not found
     * @since 1.0.0
     */
    private function removeAndCapture(string $key): ?string
    {
        $priorValue = null;
        $normalizedKey = strtolower($key . ':');

        $this->headers = array_filter($this->headers, function ($header) use (&$priorValue, $normalizedKey) {
            if (stripos($header, $normalizedKey) === 0) {
                $priorValue = trim(substr($header, strlen($normalizedKey)));
                return false;
            }
            return true;
        });

        return $priorValue;
    }

    /**
     * Remove header by name with case-insensitive matching
     *
     * Removes first header matching the specified key name using case-insensitive
     * comparison. Returns the value of removed header for confirmation or restoration.
     *
     * @param string $key Header name to remove (e.g., 'Authorization', 'Content-Type')
     * @return string|null Value of removed header or null if header not found
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config->addHeader('Authorization', 'Bearer abc123');
     *
     * $removed = $config->removeHeader('authorization'); // Case-insensitive
     * // Returns: "Bearer abc123"
     * ```
     */
    public function removeHeader(string $key): ?string
    {
        return $this->removeAndCapture($key);
    }

    /**
     * Add or replace HTTP header with case-insensitive key matching
     *
     * Adds new header or replaces existing header with same key name. Uses case-insensitive
     * matching to prevent duplicate headers while maintaining exact case formatting in
     * stored header string. Returns previous value if header was replaced.
     *
     * ## Replacement Behavior
     * - Removes any existing header with matching key (case-insensitive)
     * - Adds new header with exact case formatting as provided
     * - Returns previous header value if one was replaced
     * - Supports complex authorization schemes and custom header formats
     *
     * @param string $key Header name (e.g., 'Authorization', 'Content-Type')
     * @param string $value Header value (e.g., 'Bearer abc123', 'application/json')
     * @return string|null Previous value if header was replaced, null if new header
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Add new header
     * $config->addHeader('Accept', 'application/json');
     *
     * // Replace existing header
     * $old = $config->addHeader('Accept', 'application/xml');
     * // Returns: "application/json"
     *
     * // Custom authorization
     * $config->addHeader('Authorization', 'Custom-Scheme token=xyz');
     * ```
     */
    public function addHeader(string $key, string $value): ?string
    {
        $key = trim($key);
        $value = trim($value);

        $priorValue = $this->removeAndCapture($key);
        $this->headers[] = $key . ': ' . $value;

        return $priorValue;
    }

    /**
     * Retrieve single header value by name with case-insensitive lookup
     *
     * Searches configured headers for matching key name and returns the value portion.
     * Uses case-insensitive matching for HTTP standard compliance while preserving
     * exact header formatting.
     *
     * @param string $key Header name to search for (e.g., 'Authorization', 'content-type')
     * @return string|null Header value if found, null if header not configured
     * @since 1.0.0
     *
     * @example
     * ```php
     * $config->addHeader('Content-Type', 'application/json');
     *
     * $value = $config->getHeader('content-type'); // Case-insensitive
     * // Returns: "application/json"
     *
     * $missing = $config->getHeader('Authorization');
     * // Returns: null
     * ```
     */
    public function getHeader(string $key): ?string
    {
        $normalizedKey = strtolower($key . ':');
        foreach ($this->headers as $header) {
            if (stripos($header, $normalizedKey) === 0) {
                return trim(substr($header, strlen($normalizedKey)));
            }
        }
        return null;
    }


    // timeouts --------------------------------------------------------------------------------

    /**
     * Get or set HTTP response timeout in seconds
     *
     * Configures maximum time to wait for complete HTTP response after connection
     * is established. Does not include connection establishment time. Used by cURL
     * CURLOPT_TIMEOUT for response timeout enforcement.
     *
     * @param int|null $seconds Response timeout in seconds (null to get current value)
     * @return int|null Previous timeout value before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set 60-second timeout for slow APIs
     * $oldTimeout = $config->timeout(60);
     *
     * // Get current timeout
     * $current = $config->timeout(); // 60
     * ```
     */
    public function timeout(?int $seconds = null): ?int
    {
        $old = $this->timeout;
        if ($seconds !== null) {
            $this->timeout = $seconds;
        }
        return $old;
    }

    /**
     * Get or set TCP connection establishment timeout in seconds
     *
     * Configures maximum time to wait for initial TCP connection to remote server.
     * Separate from response timeout to handle slow connection scenarios differently
     * from slow response scenarios. Maps to cURL CURLOPT_CONNECTTIMEOUT.
     *
     * @param int|null $seconds Connection timeout in seconds (null to get current value)
     * @return int|null Previous connection timeout value before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set 10-second connection timeout for faster failure detection
     * $config->connectTimeout(10);
     *
     * // Different timeouts for connection vs response
     * $config->connectTimeout(10);  // Quick connection required
     * $config->timeout(300);        // Allow slow responses
     * ```
     */
    public function connectTimeout(?int $seconds = null): ?int
    {
        $old = $this->connectTimeout;
        if ($seconds !== null) {
            $this->connectTimeout = $seconds;
        }
        return $old;
    }


    // auth ------------------------------------------------------------------------------------
    /**
     * Get or set username for HTTP Basic Authentication
     *
     * Configures username portion of HTTP Basic Authentication. Used with authPass()
     * to create Authorization header for APIs requiring traditional username/password
     * authentication. Credentials are base64-encoded by HttpClient during request.
     *
     * @param string|null $user Username to set (null to get current value)
     * @return string|null Previous username value before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Configure API credentials
     * $config->authUser('api_user');
     * $config->authPass('secret123');
     *
     * // Retrieve current username
     * $username = $config->authUser(); // "api_user"
     * ```
     */
    public function authUser(?string $user = null): ?string
    {
        $old = $this->authUser;
        if ($user !== null) {
            $this->authUser = $user;
        }
        return $old;
    }

    /**
     * Get or set password for HTTP Basic Authentication
     *
     * Configures password portion of HTTP Basic Authentication. Used with authUser()
     * to create complete credentials for APIs requiring username/password authentication.
     * Password is stored in memory and base64-encoded during HTTP request transmission.
     *
     * @param string|null $pass Password to set (null to get current value)
     * @return string|null Previous password value before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set secure credentials
     * $config->authUser('api_user');
     * $config->authPass('complex_password_123');
     *
     * // Clear credentials for security
     * $config->authPass(null);
     * ```
     */
    public function authPass(?string $pass = null): ?string
    {
        $old = $this->authPass;
        if ($pass !== null) {
            $this->authPass = $pass;
        }
        return $old;
    }

    /**
     * Configure complete HTTP Basic Authentication credentials
     *
     * Convenience method for setting both username and password in single call.
     * Equivalent to calling authUser() and authPass() separately but more readable
     * for initialization scenarios.
     *
     * @param string $user Username for basic authentication
     * @param string $pass Password for basic authentication
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Set complete credentials in one call
     * $config->setBasicAuth('api_user', 'secret_password');
     *
     * // Equivalent to:
     * // $config->authUser('api_user');
     * // $config->authPass('secret_password');
     * ```
     */
    public function setBasicAuth(string $user = '', string $pass = ''): void
    {
        self::authUser($user);
        self::authPass($pass);
    }


    // proxy -----------------------------------------------------------------------------------
    /**
     * Get or set HTTP/HTTPS proxy server configuration
     *
     * Configures proxy server for routing HTTP requests through corporate firewalls
     * or privacy networks. Supports both HTTP and HTTPS proxy protocols with
     * optional authentication via proxy URL format.
     *
     * @param string|null $proxy Proxy URL to set (null to get current value)
     * @return string|null Previous proxy configuration before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Basic proxy configuration
     * $config->proxy('http://proxy.corp.com:8080');
     *
     * // Proxy with authentication
     * $config->proxy('http://user:pass@proxy.corp.com:8080');
     *
     * // HTTPS proxy
     * $config->proxy('https://secure-proxy.example.com:443');
     * ```
     */
    public function proxy(?string $proxy = null): ?string
    {
        $old = $this->proxy;
        if ($proxy !== null) {
            $this->proxy = $proxy;
        }
        return $old;
    }


    // verbose logging -------------------------------------------------------------------------
    /**
     * Get or set verbose cURL logging for debugging HTTP communication
     *
     * Enables detailed cURL verbose output capture for troubleshooting HTTP requests.
     * Verbose logs include connection details, SSL handshake info, header transmission,
     * and protocol-level communication. Logs accessible via HttpResult object.
     *
     * @param bool|null $value Enable verbose logging (null to get current value)
     * @return bool Previous verbose logging state before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Enable debugging for problematic API
     * $config->verboseLogging(true);
     * $result = HttpClient::get('https://api.example.com', $config);
     * var_dump($result->verboseLog()); // Detailed cURL communication
     *
     * // Disable for production
     * $config->verboseLogging(false);
     * ```
     */
    public function verboseLogging(?bool $value = null): bool
    {
        $old = $this->verboseLogging;
        if ($value !== null) {
            $this->verboseLogging = $value;
        }
        return $old;
    }


    // bearer token ----------------------------------------------------------------------------
    /**
     * Get or set OAuth2 Bearer token with automatic Authorization header management
     *
     * Configures Bearer token authentication by setting both internal token storage
     * and Authorization header automatically. Handles OAuth2, API keys, and other
     * bearer token authentication schemes. Automatically formats Authorization header.
     *
     * ## Header Management
     * - Automatically adds "Authorization: Bearer {token}" header
     * - Removes any existing Authorization header before adding new one
     * - Token stored separately for debugging and configuration export
     * - Header formatting follows OAuth2 RFC standards
     *
     * @param string|null $token Bearer token to set (null to get current value)
     * @return string Previous bearer token value before any changes
     * @since 1.0.0
     *
     * @example
     * ```php
     * // OAuth2 API authentication
     * $config->bearerToken('eyJhbGciOiJIUzI1NiIs...');
     * // Automatically adds: Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
     *
     * // API key authentication
     * $config->bearerToken('sk_live_abc123xyz789');
     *
     * // Clear token and authorization
     * $config->bearerToken(null);
     * ```
     */
    public function bearerToken(?string $token = null): string
    {
        $old = $this->bearerToken;
        if ($token !== null) {
            $this->bearerToken = $token;
            self::addHeader('Authorization: ', 'Bearer ' . $this->bearerToken);
        }
        return $old;
    }


    // userAgent -------------------------------------------------------------------------------
    /**
     * Get or set User-Agent header with automatic default generation
     *
     * Configures User-Agent header for HTTP requests with intelligent default handling.
     * If no User-Agent is explicitly set, automatically generates default using
     * PrimordyxInfo::version() for framework identification and compatibility tracking.
     *
     * ## Default Behavior
     * - Auto-generates default User-Agent if none configured: "PrimordyxHttpClient/{version}"
     * - Automatically adds User-Agent header when value is set or accessed
     * - Returns current or default User-Agent string (never null)
     * - Default helps API providers identify Primordyx-based applications
     *
     * @param string|null $ua User-Agent string to set (null to get current/default value)
     * @return string Current User-Agent string (previous value if setting, current/default if getting)
     * @since 1.0.0
     *
     * @example
     * ```php
     * // Custom User-Agent for specific application
     * $config->userAgent('MyApp/2.1.4 (+https://myapp.com)');
     *
     * // Get current User-Agent (auto-generates default if none set)
     * $ua = $config->userAgent(); // "PrimordyxHttpClient/1.0.0"
     *
     * // API-specific User-Agent
     * $config->userAgent('MyBot/1.0 (Webhook-Handler)');
     * ```
     */
    public function userAgent(?string $ua = null): string
    {
        // example.. User-Agent: PrimordyxHttpClient/1.0 (AppName/2.4.1; +https://circle6maildrop.com)

        $old = $this->userAgent;

        if ($ua !== null) {
            $this->userAgent = $ua;
            self::addHeader('User-Agent', $this->userAgent);
        }

        if ( $this->userAgent == null ) {
            $old = $this->userAgent = 'PrimordyxHttpClient/' . PrimordyxInfo::version();
            self::addHeader('User-Agent', $this->userAgent);
        }

        return $old ?? $this->userAgent;
    }

}
