<?php
/**
 * File: /vendor/vernsix/primordyx/src/DatabaseSafePersistence.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/DatabaseSafePersistence.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use DateTime;
use Exception;

/**
 * Class DatabaseSafePersistence
 *
 * Database implementation of SafePersistenceInterface using Primordyx models.
 * Provides session storage in a database table with soft delete support.
 *
 * @since       1.0.0
 *
 */
class DatabaseSafePersistence implements SafePersistenceInterface
{
    /**
     * Create a new safe record in the database.
     *
     * @param string $safeId    The unique safe identifier
     * @param array  $contents  The initial contents (usually empty)
     * @param string $expiresAt The expiration timestamp
     * @return bool             True if created successfully
     */
    public function create(string $safeId, array $contents, string $expiresAt): bool
    {
        try {
            $safe = new SafeModel();
            $safe->safe_id = $safeId;
            $safe->contents = json_encode($contents);
            $safe->expires_at = $expiresAt;
            return $safe->save();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Find a safe record by ID, checking expiration and soft deletes.
     *
     * @param string $safeId The safe identifier to find
     * @return array|null    Safe data array or null if not found/expired
     * @throws Exception
     */
    public function find(string $safeId): ?array
    {
        /** @var SafeModel|null $safe */
        $safe = (new SafeModel())->where('safe_id', $safeId)->first();

        if (!$safe) {
            return null;
        }

        // Check if expired
        $expires = new DateTime($safe->expires_at);
        if ($expires->getTimestamp() <= time()) {
            // Mark as deleted and return null
            $this->delete($safeId);
            return null;
        }

        // Return the safe data
        return [
            'safe_id' => $safe->safe_id,
            'contents' => json_decode($safe->contents, true) ?: [],
            'expires_at' => $safe->expires_at,
            'created_at' => $safe->created_at,
            'updated_at' => $safe->updated_at
        ];
    }

    /**
     * Update a safe's contents and expiration.
     *
     * @param string $safeId    The safe identifier
     * @param array  $contents  The updated contents
     * @param string $expiresAt The new expiration timestamp
     * @return bool             True if updated successfully
     */
    public function update(string $safeId, array $contents, string $expiresAt): bool
    {
        /** @var SafeModel|null $safe */
        $safe = (new SafeModel())->where('safe_id', $safeId)->first();

        if (!$safe) {
            return false;
        }

        $safe->contents = json_encode($contents);
        $safe->expires_at = $expiresAt;

        return $safe->save();
    }

    /**
     * Soft delete a safe.
     *
     * @param string $safeId The safe identifier to delete
     * @return bool          True if deleted successfully
     */
    public function delete(string $safeId): bool
    {
        /** @var SafeModel|null $safe */
        $safe = (new SafeModel())->where('safe_id', $safeId)->first();

        if (!$safe) {
            return false;
        }

        return $safe->delete();
    }

    /**
     * Touch a safe to extend expiration.
     *
     * @param string $safeId    The safe identifier
     * @param string $expiresAt The new expiration timestamp
     * @return bool             True if touched successfully
     */
    public function touch(string $safeId, string $expiresAt): bool
    {
        /** @var SafeModel|null $safe */
        $safe = (new SafeModel())->where('safe_id', $safeId)->first();

        if (!$safe) {
            return false;
        }

        $safe->expires_at = $expiresAt;

        return $safe->save();
    }

    /**
     * Clean up expired safes.
     *
     * @return int Number of safes cleaned up
     */
    public function cleanup(): int
    {
        $now = date('Y-m-d H:i:s');

        return (new SafeModel())
            ->where('deleted_at IS NULL')
            ->andWhere('expires_at', '<', $now)
            ->bulkUpdate(['deleted_at' => $now]);
    }
}