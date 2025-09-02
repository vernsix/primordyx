<?php
/**
 * File: /vendor/vernsix/primordyx/src/ConnectionManager.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/ConnectionManager.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Class ConnectionManager
 *
 * Centralizes database handles for the Primordyx framework using connection management.
 * Connections must be configured manually via createHandle() in your bootstrap.php.
 * Cached for reuse within the same request lifecycle.
 *
 * Manual Configuration Example:
 * ```php
 * ConnectionManager::createHandle('default',
 *     'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
 *     'app_user',
 *     'secure_password_123',
 *     [PDO::ATTR_TIMEOUT => 30]
 * );
 * ```
 *
 * Usage Examples:
 * ```php
 *   $db = ConnectionManager::getHandle();           // default connection
 *   $db = ConnectionManager::getHandle('reporting'); // named connection
 *   ConnectionManager::forget('reporting');         // close specific connection
 *   ConnectionManager::reconnect('default');       // force reconnection
 * ```
 *
 * @since 1.0.0
 *
 */
class ConnectionManager
{
    /** @var array<string, PDO> */
    protected static array $handles = [];

    /** @var array<string, array> Stored connection configurations */
    protected static array $configs = [];

    /**
     * Register a database configuration for lazy loading.
     * Connection will be created on first use via getHandle()
     *
     * @param string $handleName Connection handle name
     * @param string $dsn PDO Data Source Name
     * @param string $username Database username
     * @param string $password Database password
     * @param array $options PDO options array
     * @return void
     */
    public static function registerConfig(
        string $handleName,
        string $dsn,
        string $username,
        string $password,
        array  $options = []
    ): void
    {
        // Just store it - no INI knowledge!
        self::$configs[$handleName] = [
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options
        ];
    }


    /**
     * Create and configure a database connection handle.
     *
     * This method allows you to configure database connections programmatically.
     * Useful for CLI scripts, testing, or dynamic configuration scenarios.
     *
     * @param string $handleName Connection handle name
     * @param string $dsn PDO Data Source Name
     * @param string $username Database username
     * @param string $password Database password
     * @param array $options PDO options array
     * @return PDO The configured PDO connection
     * @throws PDOException If database connection fails
     *
     * @example
     * ```php
     * // Create default connection
     * ConnectionManager::createHandle('default',
     *     'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
     *     'app_user',
     *     'secure_password',
     *     [PDO::ATTR_TIMEOUT => 30]
     * );
     * ```
     *
     * @example
     * ```php
     * // Create admin connection for elevated operations
     * ConnectionManager::createHandle('admin',
     *     'mysql:host=localhost;dbname=mysql;charset=utf8mb4',
     *     'root',
     *     'root_password'
     * );
     * ```
     */
    public static function createHandle(string $handleName, string $dsn, string $username, string $password, array $options = []): PDO
    {
        // Store configuration for potential reconnection
        self::registerConfig($handleName, $dsn, $username, $password, $options);

        // Create using stored config
        return self::createAndStoreHandle($handleName);
    }

    /**
     * Create PDO connection, set attributes, and store in handles array.
     *
     * @param string $handleName Connection handle name
     * @return PDO $handle
     * @throws PDOException If database connection fails
     */
    private static function createAndStoreHandle(string $handleName): PDO
    {
        if (!isset(self::$configs[$handleName])) {
            throw new RuntimeException("No configuration found for handle: {$handleName}");
        }

        $config = self::$configs[$handleName];

        // Merge hardcoded essentials with config options
        $options = $config['options'];
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION; // Always want this

        // Create the PDO connection
        self::$handles[$handleName] = new PDO(
            $config['dsn'],
            $config['username'],
            $config['password'],
            $options
        );

        return self::$handles[$handleName];
    }


    /**
     * Get a PDO handle for database operations.
     *
     * This method implements connection caching - if a connection for the specified
     * handle name already exists, it returns the cached instance. If not found in handles,
     * it attempts to create one from stored configuration.
     *
     * @param string $handleName Connection handle name
     * @return PDO Configured PDO database handle
     * @throws PDOException If database connection fails
     * @throws RuntimeException If connection not found and no configuration available
     *
     * @example
     * ```php
     * // Get default database connection
     * $db = ConnectionManager::getHandle();
     * ```
     *
     * @example
     * ```php
     * // Get named connection for reporting database
     * $reportDb = ConnectionManager::getHandle('reporting');
     * ```
     */
    public static function getHandle(string $handleName = 'default'): PDO
    {
        // Return existing connection if available
        if (isset(self::$handles[$handleName])) {
            return self::$handles[$handleName];
        }

        // Create the PDO connection and store it
        return self::createAndStoreHandle($handleName);
    }

    /**
     * Check if a connection exists in the handles.
     *
     * @param string $handleName Connection handle name
     * @return bool True if connection exists
     */
    public static function hasConnection(string $handleName = 'default'): bool
    {
        return isset(self::$handles[$handleName]) || isset(self::$configs[$handleName]);
    }

    /**
     * Get all available connection names.
     *
     * @return array<string> Array of connection handle names
     */
    public static function getConnectionNames(): array
    {
        return array_unique(array_merge(
            array_keys(self::$handles),
            array_keys(self::$configs)
        ));
    }

    /**
     * Close and remove a connection from the handles.
     *
     * This method properly closes a database connection and removes it from the
     * internal handles pool. Useful for long-running processes or when you need to
     * force a fresh connection (e.g., after configuration changes).
     *
     * @param string $handleName The name of the connection handle to close
     * @return void
     *
     * @example
     * ```php
     * // Close the default connection
     * ConnectionManager::forget();
     * ```
     *
     * @example
     * ```php
     * // Close a specific named connection
     * ConnectionManager::forget('reporting');
     * ```
     */
    public static function forget(string $handleName = 'default'): void
    {
        if (isset(self::$handles[$handleName])) {
            self::$handles[$handleName] = null;
            unset(self::$handles[$handleName]);
        }

        // Optionally remove stored configuration as well
        // Uncomment the next lines if you want forget() to completely remove the connection
        // if (isset(self::$configs[$handleName])) {
        //     unset(self::$configs[$handleName]);
        // }
    }

    /**
     * Force reconnection by destroying and recreating a connection.
     *
     * This method removes the existing connection from the handles pool and immediately
     * recreates it using the stored configuration. Useful when you suspect a
     * connection has gone stale or want to refresh connection settings.
     *
     * @param string $handleName Connection handle name
     * @return PDO The new PDO connection
     * @throws RuntimeException If no stored configuration exists for the handle
     *
     * @example
     * ```php
     * // Force reconnect to default database
     * $db = ConnectionManager::reconnect();
     * ```
     *
     * @example
     * ```php
     * // Force reconnect to admin database
     * $adminDb = ConnectionManager::reconnect('admin');
     * ```
     */
    public static function reconnect(string $handleName = 'default'): PDO
    {
        if (!isset(self::$configs[$handleName])) {
            throw new RuntimeException("Cannot reconnect handle '{$handleName}': no stored configuration found. Use createHandle() first.");
        }

        // Remove existing connection
        self::forget($handleName);

        // Recreate from stored config
        return self::getHandle($handleName);
    }

    /**
     * Close all connections and clear the handles.
     *
     * @return void
     */
    public static function forgetAll(): void
    {
        foreach (array_keys(self::$handles) as $handleName) {
            self::forget($handleName);
        }
    }

}