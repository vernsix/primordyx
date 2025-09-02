<?php
/**
 * File: /vendor/vernsix/primordyx/src/SafePersistenceInterface.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/SafePersistenceInterface.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

/**
 * Interface SafePersistenceInterface
 *
 * Defines the contract for Safe session persistence implementations.
 * This allows the Safe class to work with different storage backends
 * (database, files, Redis, etc.) without tight coupling.
 *
 * @since       1.0.0
 *
 */
interface SafePersistenceInterface
{
    /**
     * Create a new safe record.
     *
     * @param string $safeId    The unique safe identifier
     * @param array  $contents  The initial contents (usually empty)
     * @param string $expiresAt The expiration timestamp
     * @return bool             True if created successfully
     */
    public function create(string $safeId, array $contents, string $expiresAt): bool;

    /**
     * Find and return a safe record by ID.
     *
     * @param string $safeId The safe identifier to find
     * @return array|null    Safe data array or null if not found/expired
     */
    public function find(string $safeId): ?array;

    /**
     * Update an existing safe's contents and expiration.
     *
     * @param string $safeId    The safe identifier
     * @param array  $contents  The updated contents
     * @param string $expiresAt The new expiration timestamp
     * @return bool             True if updated successfully
     */
    public function update(string $safeId, array $contents, string $expiresAt): bool;

    /**
     * Delete a safe (soft delete preferred).
     *
     * @param string $safeId The safe identifier to delete
     * @return bool          True if deleted successfully
     */
    public function delete(string $safeId): bool;

    /**
     * Touch a safe to extend its expiration without changing contents.
     *
     * @param string $safeId    The safe identifier
     * @param string $expiresAt The new expiration timestamp
     * @return bool             True if touched successfully
     */
    public function touch(string $safeId, string $expiresAt): bool;

    /**
     * Clean up expired safes.
     *
     * @return int Number of safes cleaned up
     */
    public function cleanup(): int;

}
