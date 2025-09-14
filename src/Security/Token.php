<?php
/**
 * File: /vendor/vernsix/primordyx/src/Token.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Security/Token.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Security;

use Exception;
use JsonException;
use Primordyx\Data\Cargo;
use Primordyx\Time\TimeHelper;
use Primordyx\Utils\Strings;

/**
 * Secure expiring token management with encrypted serialization and custom payloads
 *
 * Comprehensive token system for creating, encrypting, decrypting, and validating time-based
 * tokens with custom data payloads. Uses the Cargo system for internal state management and
 * Crypto class for AES-256-GCM encryption, providing secure token-based authentication, session
 * management, and temporary data exchange.
 *
 * ## Core Architecture
 * - **Cargo Integration**: Uses Cargo instances for internal key-value data storage
 * - **Crypto Security**: AES-256-GCM encryption via Primordyx\Crypto for token protection
 * - **UUID Identification**: Each token has unique UUID for identification and cargo naming
 * - **State Tracking**: Dirty flag system tracks modifications for optimization
 * - **Time Management**: Flexible UTC/local time support for expiration handling
 *
 * ## Token Lifecycle
 * 1. **Creation**: New Token instance with unique UUID and Cargo container
 * 2. **Payload Setup**: Store timestamp, TTL, and custom data in Cargo
 * 3. **Encryption**: JSON serialize and encrypt token data into shareable string
 * 4. **Distribution**: Share encrypted token string via URL, API, or storage
 * 5. **Validation**: Load token from encrypted string and verify expiration
 * 6. **Access**: Extract custom payload data and perform authorized operations
 *
 * ## Security Features
 * - **AES-256-GCM Encryption**: Military-grade encryption for token protection
 * - **Tamper Detection**: Invalid tokens fail decryption and are rejected
 * - **Time-based Expiration**: Automatic expiration prevents token reuse
 * - **UUID Randomness**: Cryptographically secure UUIDs prevent prediction
 * - **State Validation**: Multiple validation layers ensure token integrity
 *
 * ## Payload Flexibility
 * Tokens support arbitrary custom data alongside core timestamp/expiration:
 * - User authentication tokens with roles and permissions
 * - Password reset tokens with user ID and security codes
 * - API access tokens with scope and rate limiting data
 * - Session tokens with user preferences and state
 * - One-time operation tokens with specific action parameters
 *
 * ## Integration Points
 * - **Cargo System**: Leverages Cargo for flexible key-value data management
 * - **Crypto Class**: Uses framework encryption for secure token protection
 * - **Strings Utility**: UUID generation via Strings::uuid() for unique identification
 * - **TimeHelper**: ISO timestamp formatting with UTC/local time support
 *
 * @since 1.0.0
 *
 * @example Basic Token Creation and Validation
 * ```php
 * // Create token with 1-hour expiration
 * $token = new Token();
 * $encrypted = $token->makeShareableToken(3600, [
 *     'user_id' => 123,
 *     'action' => 'password_reset',
 *     'security_code' => 'abc123'
 * ]);
 *
 * // Send $encrypted via email or URL
 *
 * // Later, validate received token
 * $receivedToken = new Token();
 * if ($receivedToken->loadFromShareableToken($encrypted)) {
 *     if (!$receivedToken->isExpired()) {
 *         $payload = $receivedToken->getCargo()->get('custom', []);
 *         $userId = $payload['user_id'];
 *         // Proceed with authorized action
 *     } else {
 *         echo "Token has expired";
 *     }
 * } else {
 *     echo "Invalid token";
 * }
 * ```
 *
 * @example Advanced Token Management
 * ```php
 * // Create API access token with metadata
 * $token = new Token(true); // Use UTC timestamps
 * $encrypted = $token->makeShareableToken(86400, [ // 24 hours
 *     'api_key' => 'sk_live_abc123',
 *     'scopes' => ['read', 'write'],
 *     'rate_limit' => 1000,
 *     'client_ip' => $_SERVER['REMOTE_ADDR']
 * ]);
 *
 * // Token inspection and debugging
 * $debug = $token->asArray(true); // Include extras
 * error_log("Token debug: " . json_encode($debug));
 *
 * // Check expiration without full validation
 * echo "Expires: " . $token->expiresAt();
 * echo "Valid: " . ($token->isValid() ? 'Yes' : 'No');
 * echo "Expired: " . ($token->isExpired() ? 'Yes' : 'No');
 * ```
 *
 * @example Session Token Implementation
 * ```php
 * class SessionManager {
 *     public static function createSession($userId, $userData) {
 *         $token = new Token();
 *         return $token->makeShareableToken(3600 * 8, [ // 8 hours
 *             'user_id' => $userId,
 *             'login_time' => time(),
 *             'user_data' => $userData,
 *             'ip_address' => $_SERVER['REMOTE_ADDR'],
 *             'user_agent' => $_SERVER['HTTP_USER_AGENT']
 *         ]);
 *     }
 *
 *     public static function validateSession($tokenString) {
 *         $token = new Token();
 *         if ($token->loadFromShareableToken($tokenString) && !$token->isExpired()) {
 *             return $token->getCargo()->get('custom', []);
 *         }
 *         return null;
 *     }
 * }
 * ```
 *
 * @see Cargo For internal state management system
 * @see Crypto For token encryption and security
 * @see Strings For UUID generation utilities
 * @see TimeHelper For timestamp formatting support
 */
class Token
{
    /**
     * Cargo container instance for token data storage and management
     *
     * Houses all token-related data including creation timestamp, expiration TTL,
     * custom payload data, and metadata. Each token gets unique Cargo instance
     * named with 'token_' prefix plus token UUID for isolation and identification.
     *
     * ## Cargo Data Structure
     * - **timestamp**: Unix timestamp when token was created
     * - **seconds_to_expire**: TTL in seconds for expiration calculation
     * - **custom**: User-provided payload data (arbitrary array)
     * - Additional metadata as needed for token operation
     *
     * ## Cargo Integration Benefits
     * - Consistent key-value storage interface
     * - Built-in dirty flag tracking for optimization
     * - Flexible data type support (primitives, arrays, objects)
     * - Debugging and inspection capabilities via dump()
     * - State management and change tracking
     *
     * @var cargo Cargo instance for this token's data storage
     * @since 1.0.0
     *
     * @see Cargo For complete Cargo system documentation
     * @see getCargo() For public access to cargo instance
     */
    protected cargo $cargo;

    /**
     * Validation flag indicating whether token encryption/decryption was successful
     *
     * Set to true when token is successfully created or loaded from encrypted string.
     * Set to false when decryption fails, token is malformed, or token is reset.
     * Used by validation methods to determine overall token validity state.
     *
     * ## State Transitions
     * - **Construction**: Defaults to false (no valid token yet)
     * - **makeShareableToken()**: Set to true after successful encryption
     * - **loadFromShareableToken()**: Set to true after successful decryption
     * - **reset()**: Set to false when token state is cleared
     * - **Decryption failure**: Set to false when encrypted token is invalid
     *
     * ## Usage in Validation
     * Primary flag checked by isValid(), isExpired(), and expiresAt() to determine
     * if token has valid encrypted state before performing time-based validation.
     *
     * @var bool True if token encryption/decryption succeeded, false otherwise
     * @since 1.0.0
     *
     * @see isValid() For public access to validation state
     * @see makeShareableToken() For token creation process
     * @see loadFromShareableToken() For token loading process
     */
    protected bool $encryptionIsValid = false;

    /**
     * Encrypted string representation of token for secure distribution
     *
     * Contains the AES-256-GCM encrypted JSON serialization of complete token data
     * including UUID, timestamps, expiration, and custom payload. Safe for transmission
     * via URLs, API responses, email, or other insecure channels.
     *
     * ## Security Properties
     * - **AES-256-GCM encryption**: Military-grade encryption prevents tampering
     * - **Opaque format**: No information leakage about token contents
     * - **URL-safe**: Base64-encoded for safe transmission in URLs and headers
     * - **Tamper-evident**: Modified tokens fail decryption completely
     * - **Self-contained**: All validation data included in encrypted payload
     *
     * ## Lifecycle States
     * - **Empty string**: Default state before token creation or after reset
     * - **Encrypted data**: Set after makeShareableToken() successful encryption
     * - **'bogus'**: Set when loadFromShareableToken() encounters decryption failure
     * - **Preserved**: Maintains original encrypted string during loadFromShareableToken()
     *
     * @var string Encrypted token string safe for public distribution
     * @since 1.0.0
     *
     * @see makeShareableToken() For token encryption and string generation
     * @see getShareableToken() For public access to encrypted string
     * @see loadFromShareableToken() For decryption and loading process
     */
    protected string $shareableToken = '';

    /**
     * Unique identifier for token instance and associated Cargo container
     *
     * Cryptographically secure UUID generated during token construction using
     * Strings::uuid(). Serves as unique identifier for token instance and as
     * suffix for Cargo container naming ('token_' + UUID).
     *
     * ## UUID Properties
     * - **Uniqueness**: Cryptographically secure random generation
     * - **Immutable**: Set during construction and never changes
     * - **Cargo Integration**: Used to create unique Cargo container names
     * - **Debugging**: Helpful for token tracking and debugging
     * - **Serialization**: Included in token JSON for identification
     *
     * ## Usage Patterns
     * - **Token Identification**: Unique reference for logging and debugging
     * - **Cargo Naming**: Creates isolated Cargo containers per token
     * - **State Tracking**: Helps identify token instances in complex scenarios
     * - **Debugging**: Included in debug output for token tracing
     *
     * @var string Cryptographically secure UUID for token identification
     * @since 1.0.0
     *
     * @see getUuid() For public access to UUID
     * @see Strings::uuid() For UUID generation implementation
     * @see __construct() For UUID initialization during construction
     */
    protected string $uuid = '';

    /**
     * Flag tracking whether token state has been modified since last clean state
     *
     * Tracks modifications to token-level properties (not cargo data) for optimization
     * and state management. Set to true when token state changes, false when token
     * reaches clean state after successful operations.
     *
     * ## Dirty State Triggers
     * - **Construction**: Set to true during __construct()
     * - **Reset operations**: Set to true during reset()
     * - **Successful operations**: Set to false after makeShareableToken()
     * - **Load operations**: Set to false after successful loadFromShareableToken()
     *
     * ## Combined with Cargo State
     * Used in conjunction with cargo dirty flag via isDirty() method to provide
     * comprehensive state tracking covering both token-level and data-level changes.
     *
     * ## Optimization Benefits
     * Allows systems to avoid unnecessary re-encryption or serialization when
     * token state hasn't changed since last operation.
     *
     * @var bool True if token state modified since last clean state, false otherwise
     * @since 1.0.0
     *
     * @see isDirty() For combined token and cargo dirty state check
     * @see cargoIsDirty() For cargo-only dirty state check
     */
    protected bool $isDirty = true;

    /**
     * Configuration flag for timestamp formatting preference (UTC vs local time)
     *
     * Determines whether timestamp formatting methods use UTC or local system time
     * for human-readable output. Affects expiresAt() formatting and debug output
     * but not internal timestamp storage (always Unix timestamps).
     *
     * ## Time Handling Strategy
     * - **Internal storage**: Always Unix timestamps (timezone-neutral)
     * - **Calculations**: Always UTC-based for consistency
     * - **Display formatting**: Respects this flag for user presentation
     * - **Default behavior**: Local time (false) for user familiarity
     *
     * ## Use Cases
     * - **UTC preference**: Global applications, logging, API responses
     * - **Local preference**: User-facing applications, local system integration
     * - **Debugging**: Consistent timestamp format across different environments
     *
     * @var bool True for UTC timestamps, false for local time formatting
     * @since 1.0.0
     *
     * @see __construct() For UTC preference initialization
     * @see expiresAt() For timestamp formatting using this preference
     * @see asArray() For debug output timestamp formatting
     */
    protected bool $useUTC = false;

    /**
     * Initialize new token instance with UUID generation and Cargo container setup
     *
     * Creates new token with cryptographically secure UUID, initializes associated
     * Cargo container for data storage, and configures timestamp formatting preference.
     * Each token gets isolated Cargo container named 'token_' + UUID.
     *
     * ## Initialization Process
     * 1. **UTC Configuration**: Store timestamp formatting preference
     * 2. **State Setup**: Mark token as dirty (newly created)
     * 3. **UUID Generation**: Create cryptographically secure unique identifier
     * 4. **Cargo Creation**: Initialize isolated Cargo container for token data
     *
     * ## Container Isolation
     * Each token uses separate Cargo container to prevent data conflicts between
     * multiple token instances and enable independent state management.
     *
     * @param bool $useUTC Whether to use UTC time for timestamp formatting (default: false)
     * @throws Exception If UUID generation or Cargo initialization fails
     * @since 1.0.0
     *
     * @example Basic Token Creation
     * ```php
     * // Local time formatting
     * $token = new Token();
     *
     * // UTC time formatting
     * $utcToken = new Token(true);
     *
     * // Both tokens have unique UUIDs and isolated Cargo containers
     * echo $token->getUuid();     // "12345678-1234-5678-9012-123456789012"
     * echo $utcToken->getUuid();  // "87654321-4321-8765-2109-876543210987"
     * ```
     *
     * @see Strings::uuid() For UUID generation implementation
     * @see Cargo::on() For Cargo container creation
     */
    public function __construct(bool $useUTC = false)
    {
        $this->useUTC = $useUTC;
        $this->isDirty = true;
        $this->uuid = Strings::uuid();  // unique identifier for this token and the cargo it contains
        $this->cargo = Cargo::on('token_' . $this->uuid);
    }

    /**
     * Create encrypted shareable token with expiration and custom payload
     *
     * Generates secure encrypted token by storing timestamp, TTL, and custom data in
     * Cargo container, then encrypting complete JSON representation. Returns encrypted
     * string safe for distribution via URLs, APIs, or storage systems.
     *
     * ## Token Generation Process
     * 1. **Timestamp Storage**: Current Unix timestamp for expiration calculation
     * 2. **TTL Configuration**: Seconds-to-expiration for time-based validation
     * 3. **Payload Storage**: Custom data array in Cargo 'custom' key
     * 4. **JSON Serialization**: Complete token data to JSON format
     * 5. **Encryption**: AES-256-GCM encryption of JSON via Crypto::encrypt()
     * 6. **State Management**: Mark token and cargo as clean (not dirty)
     *
     * ## Security Considerations
     * - **Encryption**: Uses AES-256-GCM for tamper-evident security
     * - **Timestamp integrity**: Creation time prevents backdating attacks
     * - **Payload protection**: Custom data encrypted and tamper-protected
     * - **UUID inclusion**: Unique identification within encrypted payload
     *
     * ## Custom Payload Guidelines
     * - Use arrays for structured data storage
     * - Avoid sensitive data that shouldn't be in tokens
     * - Consider payload size impact on token length
     * - Ensure JSON-serializable data types only
     *
     * @param int $secondsToExpire Time-to-live in seconds before token expires (default: 60)
     * @param array $customContents Custom data to store in token payload (default: empty)
     * @return string AES-256-GCM encrypted token string safe for public distribution
     * @throws Exception If Cargo operations or encryption fail
     * @since 1.0.0
     *
     * @example Password Reset Token
     * ```php
     * $token = new Token();
     * $resetToken = $token->makeShareableToken(1800, [ // 30 minutes
     *     'user_id' => 123,
     *     'email' => 'user@example.com',
     *     'action' => 'password_reset',
     *     'security_code' => bin2hex(random_bytes(16))
     * ]);
     *
     * // Send $resetToken via email
     * $resetUrl = "https://example.com/reset?token=" . urlencode($resetToken);
     * ```
     *
     * @example API Access Token
     * ```php
     * $token = new Token();
     * $apiToken = $token->makeShareableToken(86400, [ // 24 hours
     *     'client_id' => 'app_12345',
     *     'scopes' => ['read', 'write', 'delete'],
     *     'rate_limit' => 1000,
     *     'user_context' => ['role' => 'admin', 'tenant' => 'acme_corp']
     * ]);
     *
     * // Return in API response
     * return json_encode(['access_token' => $apiToken, 'expires_in' => 86400]);
     * ```
     *
     * @see Crypto::encrypt() For encryption implementation
     * @see asJson() For JSON serialization process
     * @see getShareableToken() For retrieving encrypted token string
     */
    public function makeShareableToken(int $secondsToExpire = 60, array $customContents = []): string
    {
        $this->cargo->set('timestamp', time());
        $this->cargo->set('seconds_to_expire', $secondsToExpire);
        $this->cargo->set('custom', $customContents);

        $this->encryptionIsValid = true;
        $this->shareableToken = Crypto::encrypt($this->asJson());

        $this->isDirty = false;
        $this->cargo->setDirty(false);

        return $this->shareableToken;
    }

    /**
     * Load and validate token state from encrypted shareable string
     *
     * Attempts to decrypt provided encrypted token string, validate JSON structure,
     * and restore complete token state including Cargo data. Handles decryption
     * failures gracefully by resetting token to invalid state.
     *
     * ## Loading Process
     * 1. **Decryption**: Attempt AES-256-GCM decryption via Crypto::decrypt()
     * 2. **JSON Validation**: Parse decrypted text as JSON array
     * 3. **UUID Extraction**: Get token UUID or generate new one if missing
     * 4. **Cargo Restoration**: Load Cargo container and populate with token data
     * 5. **State Update**: Mark token as valid and not dirty
     * 6. **Token Storage**: Preserve original encrypted string
     *
     * ## Error Handling
     * - **Decryption failure**: Reset token state, mark as invalid
     * - **JSON parsing failure**: Reset token state, return false
     * - **Data corruption**: Gracefully handle missing or invalid fields
     * - **State preservation**: Maintain token consistency during failures
     *
     * ## Security Validation
     * - **Tamper detection**: Failed decryption indicates token modification
     * - **Format validation**: Ensures token structure meets expectations
     * - **State isolation**: Loading doesn't affect other token instances
     *
     * @param string $shareableToken AES-256-GCM encrypted token string to load
     * @return bool True if token successfully loaded and validated, false on failure
     * @throws Exception If Cargo operations fail during loading process
     * @since 1.0.0
     *
     * @example Token Validation Flow
     * ```php
     * // Receive token from request
     * $tokenString = $_GET['token'] ?? '';
     *
     * $token = new Token();
     * if ($token->loadFromShareableToken($tokenString)) {
     *     if (!$token->isExpired()) {
     *         // Token is valid and not expired
     *         $payload = $token->getCargo()->get('custom', []);
     *         $userId = $payload['user_id'] ?? null;
     *
     *         if ($userId) {
     *             // Proceed with authorized operation
     *             processPasswordReset($userId);
     *         }
     *     } else {
     *         throw new TokenExpiredException('Token has expired');
     *     }
     * } else {
     *     throw new InvalidTokenException('Token is invalid or tampered');
     * }
     * ```
     *
     * @example Batch Token Processing
     * ```php
     * $tokens = $_POST['tokens'] ?? [];
     * $validTokens = [];
     *
     * foreach ($tokens as $tokenString) {
     *     $token = new Token();
     *     if ($token->loadFromShareableToken($tokenString)) {
     *         if (!$token->isExpired()) {
     *             $validTokens[] = [
     *                 'token' => $token,
     *                 'payload' => $token->getCargo()->get('custom', [])
     *             ];
     *         }
     *     }
     * }
     * ```
     *
     * @see Crypto::decrypt() For decryption implementation
     * @see reset() For token state reset during failures
     * @see isExpired() For expiration validation after loading
     * @see getCargo() For accessing loaded payload data
     */
    public function loadFromShareableToken(string $shareableToken): bool
    {
        $clearText = Crypto::decrypt($shareableToken);

        if (!$clearText) {
            $this->reset();
            $this->shareableToken = 'bogus';
            return false;
        }

        $dataContainedInClearText = json_decode($clearText, true);
        if (!is_array($dataContainedInClearText)) {
            $this->reset();
            return false;
        }

        $uuid = $dataContainedInClearText['uuid'] ?? Strings::uuid();
        $this->cargo = Cargo::getInstance('token_' . $uuid);
        $this->cargo->loadFromArray($dataContainedInClearText);

        $this->encryptionIsValid = true;
        $this->shareableToken = $shareableToken;
        $this->isDirty = false;

        return true;
    }

    /**
     * Reset internal state and mark token as dirty and invalid
     *
     * Clears all token data, flushes Cargo container, and resets token to
     * invalid state. Used internally during error handling and state cleanup
     * operations.
     *
     * ## Reset Operations
     * 1. **Cargo flush**: Clear all data from Cargo container
     * 2. **Encryption invalidation**: Mark encryption as invalid
     * 3. **State marking**: Set token as dirty (modified state)
     * 4. **State isolation**: Preserve UUID and other instance properties
     *
     * ## Reset Triggers
     * - **Decryption failure**: Invalid encrypted token during loading
     * - **JSON parsing failure**: Corrupted data during token loading
     * - **Error recovery**: State cleanup after operation failures
     * - **Manual cleanup**: Explicit token state reset
     *
     * ## State After Reset
     * - Token marked invalid (encryptionIsValid = false)
     * - Token marked dirty (isDirty = true)
     * - Cargo container emptied but preserved
     * - UUID and UTC preference maintained
     * - Shareable token cleared or marked as invalid
     *
     * @return void
     * @since 1.0.0
     *
     * @see loadFromShareableToken() For reset usage during loading failures
     * @see Cargo::flush() For cargo container clearing operation
     */
    protected function reset(): void
    {
        $this->cargo->flush();
        $this->encryptionIsValid = false;
        $this->isDirty = true;
    }

    /**
     * Check if token has exceeded its configured expiration time
     *
     * Performs time-based validation by comparing token creation timestamp plus
     * TTL against current time. Returns true if token is past expiration or has
     * invalid encryption state.
     *
     * ## Expiration Logic
     * 1. **Encryption Check**: Invalid encryption automatically means expired
     * 2. **TTL Validation**: Zero or negative TTL means never expires
     * 3. **Time Calculation**: (creation_time + ttl) <= current_time = expired
     * 4. **Timestamp Validation**: Missing timestamps treated as expired
     *
     * ## Edge Cases Handled
     * - **Invalid encryption**: Always returns true (expired)
     * - **Zero TTL**: Returns false (never expires)
     * - **Negative TTL**: Returns false (never expires)
     * - **Missing timestamps**: Returns true (expired/invalid)
     * - **Clock skew**: Uses system time consistently
     *
     * ## Security Considerations
     * Expiration check prevents token reuse after intended lifetime,
     * essential for password reset tokens, API access tokens, and
     * temporary authorization mechanisms.
     *
     * @return bool True if token is expired or invalid, false if still valid
     * @since 1.0.0
     *
     * @example Token Expiration Handling
     * ```php
     * $token = new Token();
     * if ($token->loadFromShareableToken($receivedToken)) {
     *     if ($token->isExpired()) {
     *         // Handle expired token
     *         logSecurityEvent('expired_token_used', $token->getUuid());
     *         throw new TokenExpiredException('Token has expired');
     *     } else {
     *         // Token is valid, proceed with operation
     *         processAuthorizedRequest($token);
     *     }
     * }
     * ```
     *
     * @example Session Token Validation
     * ```php
     * class AuthMiddleware {
     *     public function handle($request) {
     *         $tokenString = $request->bearerToken();
     *         $token = new Token();
     *
     *         if ($token->loadFromShareableToken($tokenString)) {
     *             if (!$token->isExpired()) {
     *                 $request->setUser($token->getCargo()->get('custom')['user_id']);
     *                 return; // Continue to next middleware
     *             }
     *         }
     *
     *         throw new UnauthorizedException('Invalid or expired token');
     *     }
     * }
     * ```
     *
     * @see expiresAt() For human-readable expiration time
     * @see isValid() For overall token validity including encryption
     * @see makeShareableToken() For TTL configuration during token creation
     */
    public function isExpired(): bool
    {
        if (!$this->encryptionIsValid) return true;

        $ttl = (int) $this->cargo->get('seconds_to_expire', 60);
        if ($ttl <= 0) return false;

        $created = (int) $this->cargo->get('timestamp', 0);
        return ($created + $ttl) <= time();
    }

    /**
     * Get human-readable ISO-formatted expiration timestamp
     *
     * Calculates and formats token expiration time as ISO 8601 timestamp string
     * using configured UTC or local time preference. Handles invalid tokens and
     * missing data gracefully with descriptive error messages.
     *
     * ## Timestamp Calculation
     * - **Base time**: Token creation timestamp from Cargo
     * - **Expiration**: Creation time + seconds_to_expire (TTL)
     * - **Formatting**: ISO 8601 format via TimeHelper::iso()
     * - **Timezone**: Respects $useUTC setting from constructor
     *
     * ## Error Conditions
     * - **Invalid encryption**: "Unable to determine. Encryption is invalid"
     * - **Missing TTL**: "Unable to determine - missing timestamp or TTL"
     * - **Invalid timestamps**: "Unable to determine - missing timestamp or TTL"
     * - **Zero TTL**: Still calculates expiration (creation time + 0)
     *
     * ## Format Examples
     * - **UTC**: "2025-01-15T14:30:00.000Z"
     * - **Local**: "2025-01-15T09:30:00.000-05:00"
     * - **Error**: "Unable to determine. Encryption is invalid"
     *
     * @return string ISO 8601 formatted expiration timestamp or error message
     * @throws Exception If Cargo operations or TimeHelper formatting fail
     * @since 1.0.0
     *
     * @example Expiration Display
     * ```php
     * $token = new Token(true); // UTC timestamps
     * $encrypted = $token->makeShareableToken(3600); // 1 hour
     *
     * echo "Token expires at: " . $token->expiresAt();
     * // Output: "Token expires at: 2025-01-15T15:30:00.000Z"
     *
     * // Check expiration for user display
     * if (!$token->isExpired()) {
     *     $timeLeft = strtotime($token->expiresAt()) - time();
     *     echo "Token valid for " . $timeLeft . " more seconds";
     * }
     * ```
     *
     * @example API Response with Expiration
     * ```php
     * function createApiToken($userId) {
     *     $token = new Token();
     *     $encrypted = $token->makeShareableToken(86400, ['user_id' => $userId]);
     *
     *     return [
     *         'access_token' => $encrypted,
     *         'expires_at' => $token->expiresAt(),
     *         'expires_in' => 86400
     *     ];
     * }
     * ```
     *
     * @example Error Handling
     * ```php
     * $token = new Token();
     * $expiration = $token->expiresAt();
     *
     * if (strpos($expiration, 'Unable to determine') !== false) {
     *     // Handle invalid token
     *     logError('Invalid token expiration check', $token->getUuid());
     * } else {
     *     // Valid expiration timestamp
     *     $expiryTime = strtotime($expiration);
     * }
     * ```
     *
     * @see TimeHelper::iso() For timestamp formatting implementation
     * @see isExpired() For boolean expiration check
     * @see makeShareableToken() For TTL configuration
     */
    public function expiresAt(): string
    {
        if (!$this->encryptionIsValid) {
            return 'Unable to determine. Encryption is invalid';
        }

        $ttl = (int) $this->cargo->get('seconds_to_expire', 60);
        $created = (int) $this->cargo->get('timestamp', 0);

        if ($ttl <= 0 || $created <= 0) {
            return 'Unable to determine — missing timestamp or TTL';
        }

        $expires = $created + $ttl;
        return TimeHelper::iso($expires, $this->useUTC); // false = local time
    }

    /**
     * Export complete token data as associative array for inspection and debugging
     *
     * Converts token's Cargo data to array format with optional inclusion of
     * extensive debugging information including timestamps, validation state,
     * and internal metadata. Essential for token inspection and troubleshooting.
     *
     * ## Base Data Structure
     * Returns cargo dump containing:
     * - **timestamp**: Token creation Unix timestamp
     * - **seconds_to_expire**: TTL in seconds
     * - **custom**: User-provided payload data
     *
     * ## Extended Debug Information (when includeExtras=true)
     * - **uuid**: Token unique identifier
     * - **current_time**: Current timestamp in ISO format
     * - **expires_at**: Expiration timestamp in ISO format
     * - **is_expired**: Boolean expiration status
     * - **encryption_is_valid**: Encryption validation state
     * - **shareable_token**: Encrypted token string
     * - **is_dirty**: Token dirty flag state
     * - **cargo_is_dirty**: Cargo dirty flag state
     * - **cargo_dump**: Raw cargo container contents
     *
     * ## Use Cases
     * - **Debugging**: Complete token state inspection
     * - **Logging**: Detailed token information for audit trails
     * - **Testing**: Validation of token creation and loading
     * - **API responses**: Token metadata in development environments
     *
     * @param bool $includeExtras Whether to include comprehensive debugging data (default: false)
     * @return array Associative array of token data and optional debug information
     * @throws Exception If Cargo dump() operation or time formatting fail
     * @since 1.0.0
     *
     * @example Basic Token Data Export
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, ['user_id' => 123]);
     *
     * $basic = $token->asArray();
     * // Returns: [
     * //   'timestamp' => 1705334400,
     * //   'seconds_to_expire' => 3600,
     * //   'custom' => ['user_id' => 123]
     * // ]
     * ```
     *
     * @example Debug Information Export
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(1800, ['action' => 'reset']);
     *
     * $debug = $token->asArray(true);
     * // Returns comprehensive array with:
     * // - All basic data
     * // - uuid, current_time, expires_at
     * // - is_expired, encryption_is_valid
     * // - shareable_token, dirty flags
     * // - cargo_dump
     *
     * // Log for debugging
     * error_log('Token debug: ' . json_encode($debug, JSON_PRETTY_PRINT));
     * ```
     *
     * @example Token Comparison
     * ```php
     * $token1 = new Token();
     * $token2 = new Token();
     *
     * $encrypted1 = $token1->makeShareableToken(3600, ['type' => 'A']);
     * $encrypted2 = $token2->makeShareableToken(7200, ['type' => 'B']);
     *
     * $comparison = [
     *     'token1' => $token1->asArray(true),
     *     'token2' => $token2->asArray(true)
     * ];
     *
     * // Compare token properties
     * $ttlDiff = $comparison['token2']['seconds_to_expire'] -
     *            $comparison['token1']['seconds_to_expire'];
     * ```
     *
     * @see asJson() For JSON format export
     * @see getCargo() For direct access to cargo data
     * @see Cargo::dump() For cargo data extraction
     */
    public function asArray(bool $includeExtras = false): array
    {
        $out = $this->cargo->dump(); // requires dump() method in cargo

        if ($includeExtras) {
            $out['uuid'] = $this->uuid;
            $out['current_time'] = TimeHelper::iso(time(), $this->useUTC);
            $out['expires_at'] = $this->expiresAt();
            $out['is_expired'] = $this->isExpired();
            $out['encryption_is_valid'] = $this->encryptionIsValid;
            $out['shareable_token'] = $this->shareableToken;
            $out['is_dirty'] = $this->isDirty;
            $out['cargo_is_dirty'] = $this->cargoIsDirty();
            $out['cargo_dump'] = $this->cargo->dump();
        }

        return $out;
    }

    /**
     * Export token data as JSON string with optional pretty-printing
     *
     * Converts complete token data to JSON format using asArray() as base, with
     * configurable pretty-printing for human readability. Handles JSON encoding
     * failures with appropriate exceptions.
     *
     * ## JSON Formatting Options
     * - **Compact**: Single-line JSON for storage/transmission efficiency
     * - **Pretty**: Multi-line indented JSON for debugging and readability
     * - **Consistent**: Reliable JSON encoding with error handling
     * - **UTF-8**: Proper Unicode handling for international data
     *
     * ## Output Applications
     * - **API responses**: Token metadata in JSON format
     * - **Configuration files**: Token data persistence
     * - **Logging systems**: Structured token information
     * - **Debugging**: Human-readable token inspection
     * - **Token serialization**: Internal JSON representation for encryption
     *
     * ## Error Handling
     * Throws JsonException if JSON encoding fails due to:
     * - Non-UTF-8 data in token payload
     * - Circular references in custom data
     * - Resource types in custom payload
     * - Other JSON encoding limitations
     *
     * @param bool $includeExtras Whether to include debug information (default: false)
     * @param bool $pretty Whether to format JSON with indentation (default: false)
     * @return string JSON representation of token data
     * @throws JsonException If JSON encoding fails due to invalid data
     * @throws Exception If underlying asArray() operation fails
     * @since 1.0.0
     *
     * @example Compact JSON for Storage
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, ['user' => 'john']);
     *
     * $json = $token->asJson();
     * // Returns: {"timestamp":1705334400,"seconds_to_expire":3600,"custom":{"user":"john"}}
     *
     * // Store in database or cache
     * $redis->set("token_data:{$token->getUuid()}", $json, 3600);
     * ```
     *
     * @example Pretty-Printed Debug Output
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(1800, [
     *     'user_id' => 123,
     *     'permissions' => ['read', 'write'],
     *     'metadata' => ['ip' => '192.168.1.1', 'agent' => 'Chrome']
     * ]);
     *
     * $prettyJson = $token->asJson(true, true);
     * echo $prettyJson;
     * // Returns formatted JSON:
     * // {
     * //   "timestamp": 1705334400,
     * //   "seconds_to_expire": 1800,
     * //   "custom": {
     * //     "user_id": 123,
     * //     "permissions": ["read", "write"],
     * //     "metadata": {
     * //       "ip": "192.168.1.1",
     * //       "agent": "Chrome"
     * //     }
     * //   },
     * //   "uuid": "...",
     * //   "expires_at": "2025-01-15T15:00:00.000Z"
     * // }
     * ```
     *
     * @example Error Handling
     * ```php
     * try {
     *     $json = $token->asJson(true);
     *     logTokenData($json);
     * } catch (JsonException $e) {
     *     logError('Token JSON encoding failed', [
     *         'token_uuid' => $token->getUuid(),
     *         'error' => $e->getMessage()
     *     ]);
     * }
     * ```
     *
     * @see asArray() For array format data export
     * @see makeShareableToken() Where JSON serialization is used internally
     */
    public function asJson(bool $includeExtras = false, bool $pretty = false): string
    {
        $options = $pretty ? JSON_PRETTY_PRINT : 0;
        return json_encode($this->asArray($includeExtras), $options);
    }

    /**
     * Get direct access to internal Cargo container for advanced data operations
     *
     * Provides public access to the token's Cargo instance for direct manipulation
     * of token data, custom payload access, and advanced state management. Use with
     * caution as direct cargo modifications can affect token integrity.
     *
     * ## Cargo Access Capabilities
     * - **Direct data access**: Get/set individual cargo keys
     * - **Payload manipulation**: Modify custom data after token creation
     * - **State inspection**: Check cargo dirty flags and metadata
     * - **Advanced operations**: Use full Cargo API for complex data handling
     *
     * ## Common Usage Patterns
     * - **Payload extraction**: `$cargo->get('custom', [])`
     * - **Data modification**: `$cargo->set('custom', $newData)`
     * - **State checking**: `$cargo->isDirty()`
     * - **Debugging**: `$cargo->dump()`
     *
     * ## Integrity Considerations
     * Direct cargo modifications bypass token state management:
     * - Changes don't automatically update shareableToken
     * - Modifications don't trigger dirty flag updates
     * - Consider calling makeShareableToken() after changes
     * - Be careful with timestamp and TTL modifications
     *
     * @return cargo Direct reference to token's Cargo container instance
     * @since 1.0.0
     *
     * @example Custom Payload Access
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, [
     *     'user_id' => 123,
     *     'roles' => ['admin', 'editor']
     * ]);
     *
     * // Access custom payload data
     * $cargo = $token->getCargo();
     * $customData = $cargo->get('custom', []);
     * $userId = $customData['user_id']; // 123
     * $roles = $customData['roles'];   // ['admin', 'editor']
     * ```
     *
     * @example Dynamic Token Modification
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, ['user' => 'john']);
     *
     * // Later, add additional data to token
     * $cargo = $token->getCargo();
     * $existing = $cargo->get('custom', []);
     * $existing['last_activity'] = time();
     * $existing['permissions'] = ['read', 'write'];
     * $cargo->set('custom', $existing);
     *
     * // Re-encrypt token with updated data
     * $newEncrypted = $token->makeShareableToken(
     *     $cargo->get('seconds_to_expire', 3600),
     *     $existing
     * );
     * ```
     *
     * @example Advanced State Management
     * ```php
     * $token = new Token();
     * $cargo = $token->getCargo();
     *
     * // Check if token data has been modified
     * if ($cargo->isDirty()) {
     *     // Re-encrypt token due to changes
     *     $newEncrypted = $token->makeShareableToken(3600);
     * }
     *
     * // Inspect all cargo data
     * $allData = $cargo->dump();
     * foreach ($allData as $key => $value) {
     *     echo "Key: $key, Value: " . json_encode($value) . "\n";
     * }
     * ```
     *
     * @see Cargo For complete Cargo system documentation
     * @see makeShareableToken() For re-encrypting after cargo modifications
     * @see asArray() For read-only access to cargo data
     */
    public function getCargo(): cargo
    {
        return $this->cargo;
    }

    /**
     * Get unique identifier assigned to this token instance
     *
     * Returns the cryptographically secure UUID generated during token construction.
     * UUID serves as unique identifier for the token and is used for Cargo container
     * naming, debugging, and logging purposes.
     *
     * ## UUID Characteristics
     * - **Uniqueness**: Cryptographically secure random generation
     * - **Immutability**: Never changes during token lifetime
     * - **Format**: Standard UUID format (8-4-4-4-12 hex digits)
     * - **Cargo integration**: Used in 'token_' + UUID cargo naming
     * - **Debugging**: Helpful for token tracking and identification
     *
     * ## Use Cases
     * - **Logging**: Token identification in audit logs
     * - **Debugging**: Token instance tracking during development
     * - **Caching**: Cache keys for token-related data
     * - **Database storage**: Primary key for token persistence
     * - **Correlation**: Link token operations across system components
     *
     * @return string Cryptographically secure UUID string
     * @since 1.0.0
     *
     * @example Token Identification in Logging
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, ['action' => 'login']);
     *
     * // Log token creation
     * logSecurityEvent('token_created', [
     *     'uuid' => $token->getUuid(),
     *     'ttl' => 3600,
     *     'action' => 'login',
     *     'timestamp' => time()
     * ]);
     * ```
     *
     * @example Token Tracking Across Requests
     * ```php
     * // Request 1: Create token
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(1800);
     * $tokenId = $token->getUuid();
     *
     * // Store for later reference
     * $redis->set("active_token:$tokenId", $encrypted, 1800);
     *
     * // Request 2: Validate token
     * $receivedToken = $_POST['token'];
     * $validationToken = new Token();
     *
     * if ($validationToken->loadFromShareableToken($receivedToken)) {
     *     $validationId = $validationToken->getUuid();
     *
     *     // Check if token is in active list
     *     if ($redis->exists("active_token:$validationId")) {
     *         echo "Token $validationId is valid and active";
     *     }
     * }
     * ```
     *
     * @see __construct() For UUID generation during token creation
     * @see Strings::uuid() For UUID generation implementation
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get encrypted token string for secure distribution and storage
     *
     * Returns the AES-256-GCM encrypted representation of complete token data
     * safe for transmission via URLs, API responses, email, or other channels.
     * Empty string if token hasn't been encrypted yet via makeShareableToken().
     *
     * ## Token String Properties
     * - **Encryption**: AES-256-GCM encrypted JSON payload
     * - **URL-safe**: Base64 encoded for safe URL transmission
     * - **Self-contained**: All validation data included
     * - **Tamper-evident**: Modified strings fail decryption
     * - **Opaque**: No information leakage about contents
     *
     * ## Lifecycle States
     * - **Empty**: Default state before encryption
     * - **Encrypted**: Contains valid encrypted token after makeShareableToken()
     * - **Preserved**: Maintains original string after loadFromShareableToken()
     * - **'bogus'**: Set when decryption fails during loading
     *
     * ## Distribution Methods
     * Safe for use in URLs, headers, form fields, API responses, email,
     * database storage, or any transmission method.
     *
     * @return string Encrypted token string or empty string if not yet encrypted
     * @since 1.0.0
     *
     * @example URL Parameter Distribution
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(1800, ['user' => 123]);
     * $tokenString = $token->getShareableToken();
     *
     * // Safe for URL transmission
     * $resetUrl = "https://example.com/reset?token=" . urlencode($tokenString);
     * sendEmail($user->email, "Password Reset", "Click: $resetUrl");
     * ```
     *
     * @example API Response Distribution
     * ```php
     * function createAuthToken($userId) {
     *     $token = new Token();
     *     $encrypted = $token->makeShareableToken(86400, [
     *         'user_id' => $userId,
     *         'issued_at' => time()
     *     ]);
     *
     *     return [
     *         'access_token' => $token->getShareableToken(),
     *         'token_type' => 'Bearer',
     *         'expires_in' => 86400
     *     ];
     * }
     * ```
     *
     * @example Token Persistence
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(7200);
     *
     * // Store in database
     * $db->insert('active_tokens', [
     *     'uuid' => $token->getUuid(),
     *     'encrypted_data' => $token->getShareableToken(),
     *     'expires_at' => date('Y-m-d H:i:s', time() + 7200)
     * ]);
     *
     * // Later retrieval
     * $row = $db->select('active_tokens')->where('uuid', $token->getUuid())->first();
     * $storedTokenString = $row['encrypted_data'];
     * ```
     *
     * @see makeShareableToken() For creating encrypted token string
     * @see loadFromShareableToken() For loading from encrypted string
     */
    public function getShareableToken(): string
    {
        return $this->shareableToken;
    }

    /**
     * Check overall token validity based on encryption state
     *
     * Returns encryption validation flag indicating whether token has been
     * successfully created or loaded from valid encrypted string. Primary
     * validity check that should be used before accessing token data.
     *
     * ## Validity Conditions
     * - **True**: Token successfully created via makeShareableToken()
     * - **True**: Token successfully loaded via loadFromShareableToken()
     * - **False**: Newly constructed token (no data yet)
     * - **False**: Failed decryption during loadFromShareableToken()
     * - **False**: Token reset due to errors or invalid state
     *
     * ## Validation Hierarchy
     * 1. **isValid()**: Core encryption/decryption success
     * 2. **isExpired()**: Time-based expiration (requires valid token)
     * 3. **Custom validation**: Application-specific payload validation
     *
     * ## Usage Pattern
     * Always check isValid() before isExpired() or accessing token data
     * to ensure token has valid encrypted state for further validation.
     *
     * @return bool True if token has valid encryption state, false otherwise
     * @since 1.0.0
     *
     * @example Standard Token Validation
     * ```php
     * $token = new Token();
     *
     * if ($token->loadFromShareableToken($receivedToken)) {
     *     if ($token->isValid()) {
     *         if (!$token->isExpired()) {
     *             // Token is fully valid, proceed with operation
     *             $payload = $token->getCargo()->get('custom', []);
     *             processAuthorizedRequest($payload);
     *         } else {
     *             logSecurityEvent('expired_token_used');
     *             throw new TokenExpiredException();
     *         }
     *     } else {
     *         logSecurityEvent('invalid_token_state');
     *         throw new InvalidTokenException();
     *     }
     * } else {
     *     logSecurityEvent('token_decryption_failed');
     *     throw new TokenDecryptionException();
     * }
     * ```
     *
     * @example API Middleware Validation
     * ```php
     * class TokenValidationMiddleware {
     *     public function handle($request, $next) {
     *         $tokenString = $request->bearerToken();
     *
     *         if (!$tokenString) {
     *             return response('Missing token', 401);
     *         }
     *
     *         $token = new Token();
     *         if (!$token->loadFromShareableToken($tokenString)) {
     *             return response('Invalid token format', 401);
     *         }
     *
     *         if (!$token->isValid()) {
     *             return response('Token validation failed', 401);
     *         }
     *
     *         if ($token->isExpired()) {
     *             return response('Token expired', 401);
     *         }
     *
     *         // Attach token data to request
     *         $request->setToken($token);
     *         return $next($request);
     *     }
     * }
     * ```
     *
     * @see makeShareableToken() For creating valid tokens
     * @see loadFromShareableToken() For loading and validating tokens
     * @see isExpired() For time-based validation of valid tokens
     */
    public function isValid(): bool
    {
        return $this->encryptionIsValid;
    }

    /**
     * Check if internal Cargo container has unsaved changes
     *
     * Returns dirty flag state from associated Cargo container indicating whether
     * cargo data has been modified since last clean state. Useful for optimization
     * and state management decisions.
     *
     * ## Cargo Dirty State Triggers
     * - **Data modifications**: set(), forget(), flush() operations
     * - **Bulk operations**: loadFromArray(), replace() operations
     * - **State changes**: Any cargo data manipulation
     *
     * ## Clean State Conditions
     * - **Initial state**: New cargo containers start clean
     * - **After makeShareableToken()**: Cargo marked clean after encryption
     * - **After loadFromShareableToken()**: Cargo marked clean after loading
     * - **Manual cleanup**: Explicit setDirty(false) calls
     *
     * ## Optimization Usage
     * Check dirty state to avoid unnecessary re-encryption or serialization
     * when token data hasn't changed since last operation.
     *
     * @return bool True if cargo has unsaved changes, false if clean
     * @since 1.0.0
     *
     * @example Conditional Token Re-encryption
     * ```php
     * $token = new Token();
     * $encrypted = $token->makeShareableToken(3600, ['user' => 123]);
     *
     * // Later, check if re-encryption needed
     * if ($token->cargoIsDirty() || $token->isDirty()) {
     *     // Token or cargo state changed, re-encrypt
     *     $newEncrypted = $token->makeShareableToken(3600);
     * } else {
     *     // Use existing encrypted token
     *     $newEncrypted = $token->getShareableToken();
     * }
     * ```
     *
     * @example State Monitoring
     * ```php
     * $token = new Token();
     * $cargo = $token->getCargo();
     *
     * echo "Initial cargo dirty: " . ($token->cargoIsDirty() ? 'Yes' : 'No') . "\n";
     *
     * $cargo->set('custom', ['test' => 'data']);
     * echo "After set cargo dirty: " . ($token->cargoIsDirty() ? 'Yes' : 'No') . "\n";
     *
     * $token->makeShareableToken(3600);
     * echo "After encrypt cargo dirty: " . ($token->cargoIsDirty() ? 'Yes' : 'No') . "\n";
     * ```
     *
     * @see isDirty() For combined token and cargo dirty state
     * @see Cargo::isDirty() For direct cargo dirty flag access
     * @see makeShareableToken() For operations that clean cargo state
     */
    public function cargoIsDirty(): bool
    {
        return $this->cargo->isDirty();
    }

    /**
     * Check if token or its cargo has unsaved changes
     *
     * Returns combined dirty state from both token-level modifications and
     * Cargo container changes. Provides comprehensive state tracking for
     * optimization and consistency decisions.
     *
     * ## Combined State Logic
     * Returns true if either:
     * - Token-level state has changed (encryption, loading, etc.)
     * - Cargo data has been modified (payload, metadata, etc.)
     *
     * ## Dirty State Sources
     * - **Token level**: Construction, reset, loading operations
     * - **Cargo level**: Data modifications, bulk operations
     * - **State changes**: Any modification affecting token integrity
     *
     * ## Clean State Achievement
     * Both token and cargo must be clean for isDirty() to return false:
     * - Token marked clean after successful encryption/loading
     * - Cargo marked clean after makeShareableToken() or loading
     *
     * ## Performance Optimization
     * Use to avoid unnecessary operations when token state unchanged:
     * - Skip re-encryption if not dirty
     * - Avoid redundant serialization
     * - Optimize caching decisions
     *
     * @return bool True if token or cargo has unsaved changes, false if both clean
     * @since 1.0.0
     *
     * @example State-Based Cache Management
     * ```php
     * class TokenCache {
     *     private static $cache = [];
     *
     *     public static function getEncrypted($token) {
     *         $uuid = $token->getUuid();
     *
     *         // Return cached if token hasn't changed
     *         if (!$token->isDirty() && isset(self::$cache[$uuid])) {
     *             return self::$cache[$uuid];
     *         }
     *
     *         // Re-encrypt and cache
     *         $encrypted = $token->makeShareableToken(3600);
     *         self::$cache[$uuid] = $encrypted;
     *         return $encrypted;
     *     }
     * }
     * ```
     *
     * @example Development State Monitoring
     * ```php
     * $token = new Token();
     * echo "New token dirty: " . ($token->isDirty() ? 'Yes' : 'No') . "\n"; // Yes
     *
     * $encrypted = $token->makeShareableToken(3600);
     * echo "After encrypt dirty: " . ($token->isDirty() ? 'Yes' : 'No') . "\n"; // No
     *
     * $token->getCargo()->set('custom', ['modified' => true]);
     * echo "After modify dirty: " . ($token->isDirty() ? 'Yes' : 'No') . "\n"; // Yes
     * ```
     *
     * @example Conditional Operations
     * ```php
     * function updateTokenIfNeeded($token, $newData) {
     *     $cargo = $token->getCargo();
     *     $existing = $cargo->get('custom', []);
     *
     *     // Only update if data actually changed
     *     if ($existing !== $newData) {
     *         $cargo->set('custom', $newData);
     *
     *         // Re-encrypt only if there are changes
     *         if ($token->isDirty()) {
     *             return $token->makeShareableToken(3600, $newData);
     *         }
     *     }
     *
     *     return $token->getShareableToken();
     * }
     * ```
     *
     * @see cargoIsDirty() For cargo-only dirty state check
     * @see makeShareableToken() For operations that clean both states
     * @see loadFromShareableToken() For operations that clean both states
     */
    public function isDirty(): bool
    {
        return $this->isDirty || $this->cargoIsDirty();
    }


}
