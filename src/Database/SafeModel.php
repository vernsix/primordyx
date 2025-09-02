<?php
/**
 * File: /vendor/vernsix/primordyx/src/SafeModel.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/SafeModel.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use DateTime;
use Exception;

/**
 * Database Model for Safe Session Storage and Management
 *
 * Provides database persistence layer for the Safe session management system with
 * comprehensive CRUD operations, automatic expiration handling, and JSON content
 * serialization. Designed to work seamlessly with the Safe class for secure,
 * database-backed session storage.
 *
 * ## Key Features
 * - **JSON Content Storage**: Automatic serialization/deserialization of session data
 * - **Expiration Management**: Built-in expiration checking and extension methods
 * - **Soft Delete Support**: Safe removal with restoration capabilities
 * - **Timestamp Tracking**: Automatic created/updated timestamp management
 * - **Type Safety**: Proper casting for datetime fields and content validation
 * - **Safe ID Lookup**: Efficient queries by cryptographic session identifiers
 *
 * ## Database Schema
 * The model expects a 'safes' table with these columns:
 * - `id` (Primary Key): Auto-incrementing database ID
 * - `safe_id` (Unique): Cryptographically secure session identifier (64 chars)
 * - `contents` (JSON/Text): Serialized session data as JSON string
 * - `expires_at` (DateTime): Session expiration timestamp
 * - `created_at` (DateTime): Record creation timestamp
 * - `updated_at` (DateTime): Last modification timestamp
 * - `deleted_at` (DateTime): Soft delete timestamp (nullable)
 * - `restored_at` (DateTime): Restoration timestamp (nullable)
 *
 * ## Usage Patterns
 * ```php
 * // Find session by safe ID
 * $safe = SafeModel::findBySafeId($sessionId);
 *
 * // Check expiration and extend if needed
 * if ($safe->isExpired()) {
 *     $safe->extend(3600); // Extend 1 hour
 * }
 *
 * // Work with session contents
 * $data = $safe->getContents();
 * $data['new_key'] = 'value';
 * $safe->setContents($data);
 * $safe->save();
 * ```
 *
 * ## Integration
 * - Used internally by SafePersistenceInterface implementations
 * - Integrates with Safe class for session lifecycle management
 * - Supports DatabaseSafePersistence for production session storage
 * - Compatible with existing Model framework patterns and conventions
 *
 * @package Primordyx
 * @since 1.0.0
 */
class SafeModel extends Model
{
    /**
     * The database table associated with the model.
     *
     * @var string
     * @since 1.0.0
     */
    protected string $table = 'safes';

    /**
     * The primary key for the model.
     *
     * @var string
     * @since 1.0.0
     */
    protected string $primaryKey = 'id';

    /**
     * Indicates if the model should use soft deletes.
     *
     * @var bool
     * @since 1.0.0
     */
    protected bool $softDelete = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     * @since 1.0.0
     */
    protected bool $timestamps = true;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     * @since 1.0.0
     */
    protected array $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    /**
     * Find safe session by cryptographic session identifier
     *
     * Queries the database for a safe record using the cryptographically secure
     * session ID rather than the auto-incrementing database primary key. This is
     * the primary lookup method for active session retrieval.
     *
     * ## Query Behavior
     * - Searches by 'safe_id' column (not 'id' primary key)
     * - Returns first matching active (non-soft-deleted) record
     * - Automatically applies model's soft delete filtering
     * - Uses framework's standard query builder methods
     *
     * @param string $safeId The cryptographic session identifier (64-character hex string)
     * @return static|null SafeModel instance if found, null if session doesn't exist or expired
     * @since 1.0.0
     *
     * @example
     * ```php
     * $sessionId = 'a1b2c3d4...'; // 64-char session ID
     * $safe = SafeModel::findBySafeId($sessionId);
     *
     * if ($safe && !$safe->isExpired()) {
     *     $sessionData = $safe->getContents();
     * }
     * ```
     */
    public static function findBySafeId(string $safeId): ?static
    {
        return (new static())->where('safe_id', $safeId)->first();
    }

    /**
     * Check if the safe session has exceeded its expiration time
     *
     * Compares the session's expiration timestamp against the current time to
     * determine if the session should be considered expired. Sessions without
     * expiration timestamps are treated as expired for security.
     *
     * ## Expiration Logic
     * - Returns true if expires_at is null or empty (safety default)
     * - Returns true if expires_at timestamp is less than or equal to current time
     * - Returns false only if session has valid future expiration time
     * - Uses PHP's DateTime for accurate timestamp comparison
     *
     * @return bool True if session is expired and should not be used, false if still valid
     * @throws Exception If DateTime operations fail or expires_at format is invalid
     * @since 1.0.0
     *
     * @example
     * ```php
     * $safe = SafeModel::findBySafeId($sessionId);
     *
     * if ($safe->isExpired()) {
     *     // Session expired - redirect to login
     *     $safe->delete();
     * } else {
     *     // Session valid - continue processing
     *     $safe->extend(); // Optional: extend expiration
     * }
     * ```
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return true;
        }

        $expires = new DateTime($this->expires_at);
        return $expires->getTimestamp() <= time();
    }

    /**
     * Extend the safe session expiration by specified duration
     *
     * Updates the expires_at timestamp to extend the session lifetime by the given
     * number of seconds from the current time. Automatically saves the updated
     * expiration to the database.
     *
     * ## Extension Behavior
     * - Calculates new expiration as current time + seconds parameter
     * - Overwrites existing expiration (does not add to existing time)
     * - Automatically saves the updated record to database
     * - Uses Y-m-d H:i:s format for database compatibility
     *
     * @param int $seconds Number of seconds to extend from current time (default: 3600 = 1 hour)
     * @return bool True if extension and database save succeeded, false on failure
     * @throws Exception If database save operations fail
     * @since 1.0.0
     *
     * @example
     * ```php
     * $safe = SafeModel::findBySafeId($sessionId);
     *
     * // Extend session by 2 hours
     * if ($safe->extend(7200)) {
     *     echo "Session extended successfully";
     * }
     *
     * // Use default 1-hour extension
     * $safe->extend();
     * ```
     */
    public function extend(int $seconds = 3600): bool
    {
        $this->expires_at = date('Y-m-d H:i:s', time() + $seconds);
        return $this->save();
    }

    /**
     * Retrieve and deserialize safe session contents as associative array
     *
     * Decodes the JSON-serialized session data stored in the contents field and
     * returns it as a PHP associative array. Handles empty or malformed JSON
     * gracefully by returning empty array.
     *
     * ## Deserialization Process
     * - Returns empty array if contents field is null or empty
     * - Uses json_decode with associative array flag for PHP compatibility
     * - Returns empty array if JSON is malformed or invalid
     * - Preserves nested arrays and objects from original session data
     *
     * ## Data Types
     * - All data types serializable by json_encode are supported
     * - Complex objects are converted to associative arrays
     * - Maintains type information for strings, numbers, booleans, nulls
     *
     * @return array<string, mixed> Deserialized session data or empty array if no valid content
     * @since 1.0.0
     *
     * @example
     * ```php
     * $safe = SafeModel::findBySafeId($sessionId);
     * $sessionData = $safe->getContents();
     *
     * // Access session data
     * $userId = $sessionData['user_id'] ?? null;
     * $preferences = $sessionData['preferences'] ?? [];
     *
     * // Safely check for keys
     * if (isset($sessionData['cart_items'])) {
     *     processShoppingCart($sessionData['cart_items']);
     * }
     * ```
     */
    public function getContents(): array
    {
        if (empty($this->contents)) {
            return [];
        }

        $decoded = json_decode($this->contents, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Serialize and store session data array as JSON in contents field
     *
     * Converts a PHP associative array into JSON format and stores it in the
     * database contents field. Does not automatically save to database - call
     * save() method separately to persist changes.
     *
     * ## Serialization Process
     * - Uses json_encode for consistent cross-platform serialization
     * - Handles nested arrays, objects, and mixed data types
     * - Stores result directly in contents property
     * - Does not validate array structure or content
     *
     * ## Data Requirements
     * - Input must be serializable by json_encode
     * - Avoid circular references in nested structures
     * - Resource types and closures cannot be serialized
     * - UTF-8 encoding required for string values
     *
     * @param array<string, mixed> $contents Associative array of session data to serialize
     * @return void
     * @since 1.0.0
     *
     * @example
     * ```php
     * $safe = SafeModel::findBySafeId($sessionId);
     *
     * // Update session data
     * $sessionData = $safe->getContents();
     * $sessionData['user_id'] = 123;
     * $sessionData['last_activity'] = time();
     *
     * // Store updated data (remember to save!)
     * $safe->setContents($sessionData);
     * $safe->save(); // Don't forget this step!
     * ```
     */
    public function setContents(array $contents): void
    {
        $this->contents = json_encode($contents);
    }
}