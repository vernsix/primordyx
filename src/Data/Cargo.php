<?php
/**
 * File: /vendor/vernsix/primordyx/src/Cargo.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Cargo.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use Exception;

/**
 * Class Cargo
 *
 * cargo – a super‑light, static key/value container with optional TTL, versioning,
 * and safety rails.
 *
 * Think of it as an in‑memory "miscellaneous drawer" you can open anywhere
 * in your codebase. Each named container (default is "default") is:
 *
 * • **Singleton‑scoped** – `\Primordyx\Cargo::getInstance('foo')` always returns the
 * same object for a given name.
 *
 * • **Globally shared** – the data lives in a static array, so every request
 * to that container sees the same state.
 *
 * Core features
 * -------------
 * **CRUD helpers** – `set()`, `get()`, `has()`, `forget()`, `flush()`.
 * **TTL support** – store temp data that auto‑expires (`$ttl` in seconds).
 * **Lazy values** – `lazy($key, fn() => heavy())` only runs the callback once.
 * **Snapshots / restore** – `snapshot()` versions the whole container; `restore()` rolls back.
 * **Diff & merge** – static helpers compare or merge two containers.
 * **Protection & lg**
 *    – *Protect* individual keys from mutation (`protect()`).
 *    – *Lock* an entire container from writes (`lock()`).
 * **Logging** – every mutating call is logged (`getLog()`, `pruneLog()`, global `setLogLimit()`), capped at 1000 by default.
 * **Persistence** – save/load to JSON file or PHP session.
 * **Helpers** – bulk import/export, JSON serialization (`toJSON()`), key list, null‑to‑empty‑string fallback (`nullsAreEmptyStrings()`).
 *
 * Intended use‑cases
 * ------------------
 * * Stashing request‑wide or application‑wide context that doesn't merit a dedicated class (e.g. feature flags, breadcrumb trails, debug data).
 * * Quick‑and‑dirty caching of computed values.
 * * Coordinating data between legacy procedural code and newer class‑based modules without wiring a DI container.
 *
 * **NOT** a replacement for a real cache or queue – it lives only as long as the PHP process/request.
 *
 * @example
 * ```php
 * $cargo = \Primordyx\Cargo::getInstance();          // default container
 * $cargo->set('user_id', 42);
 *
 * $config = \Primordyx\Cargo::getInstance('whatever'); // separate container
 * $config->remember('db', fn() => new PDO(...));
 * ```
 *
 * @since       1.0.0
 */
class Cargo {
    // All shared data per named container
    private static array $instances = [];
    private static array $dirtyFlags = [];
    // All cargo object instances (for method access)
    private static array $objects = [];

    // Static lock registry
    private static array $locks = [];
    private static array $protected = [];

    private static array $versions = [];
    private static array $logs = [];
    private static int $logLimit = 1000;
    private bool $returnEmptyStringForNull = false;

    protected static bool $globalEmptyStringFallback = false;


    // Instance-specific container name
    private string $name;

    /**
     * Initializes a new Cargo container instance with the specified name.
     *
     * This constructor is private to enforce the singleton pattern for named containers.
     * Use `getInstance()` to obtain container instances.
     *
     * @param string $name The unique name identifier for this container.
     */
    private function __construct(string $name) {
        $this->name = $name;

        // Ensure the container exists
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = [];
        }
    }

    /**
     * Gets the name of this container instance.
     *
     * @return string The container's unique name identifier.
     *
     * @example
     * $cargo = Cargo::getInstance('session');
     * echo $cargo->getName(); // 'session'
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Gets or creates a singleton Cargo container instance by name.
     *
     * This is the primary entry point for accessing named containers. Each unique
     * name returns the same instance throughout the application lifecycle.
     *
     * @param string $name The container name. Defaults to 'default'.
     *
     * @return self The singleton container instance for the given name.
     *
     * @example
     * ```php
     * $default = Cargo::getInstance();           // Gets 'default' container
     * $session = Cargo::getInstance('session');  // Gets 'session' container
     * $cache = Cargo::getInstance('cache');      // Gets 'cache' container
     * ```
     *
     * @note Each named container maintains its own separate data store and configuration.
     */
    public static function getInstance(string $name = 'default'): self {
        if (!isset(self::$objects[$name])) {
            $instance = new self($name);
            $instance->returnEmptyStringForNull = self::$globalEmptyStringFallback;
            self::$objects[$name] = $instance;
            if (!isset(self::$dirtyFlags[$name])) self::$dirtyFlags[$name] = false;
            // self::$objects[$name] = new self($name);
        }

        // Ensure the named instance array exists
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = [];
            self::$dirtyFlags[$name] = false;
        }

        return self::$objects[$name];
    }

    /**
     * Creates a temporary Cargo instance that doesn't persist in the singleton registry.
     *
     * Unlike `getInstance()`, this method creates a new container instance each time
     * it's called, without storing it in the global registry. Useful for temporary
     * containers or testing scenarios.
     *
     * @param string $name The container name for this temporary instance.
     *
     * @return self A new, non-persistent container instance.
     *
     * @example
     * ```php
     * $temp = Cargo::on('temporary');    // Creates new instance each call
     * $temp2 = Cargo::on('temporary');   // Different instance than $temp
     * ```
     *
     * @note These instances are not globally shared and won't appear in `allInstances()`.
     */
    public static function on(string $name): self {
        return new self($name);
    }

    /**
     * Returns all container data arrays indexed by container name.
     *
     * Provides access to the raw data structures for all registered containers.
     * Useful for debugging, serialization, or advanced container manipulation.
     *
     * @return array<string, array> All container data keyed by container name.
     *
     * @example
     * ```php
     * $all = Cargo::allInstances();
     * // ['default' => [...], 'session' => [...], 'cache' => [...]]
     * ```
     *
     * @note This returns the actual data arrays, not the Cargo object instances.
     */
    public static function allInstances(): array {
        return self::$instances;
    }

    /**
     * Checks if this container has been modified since creation or last clean state.
     *
     * The dirty flag tracks whether any mutating operations have occurred,
     * helping with persistence decisions and change detection.
     *
     * @return bool True if the container has been modified, false otherwise.
     *
     * @example
     * ```php
     * $cargo = Cargo::getInstance();
     * echo $cargo->isDirty(); // false
     * $cargo->set('key', 'value');
     * echo $cargo->isDirty(); // true
     * ```
     */
    public function isDirty(): bool {
        return self::$dirtyFlags[$this->name] ?? false;
    }

    /**
     * Manually sets the dirty flag for this container.
     *
     * Allows manual control over the dirty state, useful for custom persistence
     * logic or when loading data from external sources.
     *
     * @param bool $isDirty The dirty state to set. Defaults to true.
     *
     * @example
     * ```php
     * $cargo->setDirty(false);  // Mark as clean after saving
     * $cargo->setDirty(true);   // Force dirty state
     * ```
     */
    public function setDirty(bool $isDirty = true): void {
        self::$dirtyFlags[$this->name] = $isDirty;
    }

    /**
     * Appends a value to an array stored under the specified key.
     *
     * If the key doesn't exist, creates a new array. If the existing value
     * is not an array, replaces it with a new array containing the new value.
     *
     * @param string $arrayKey The key containing the array to append to.
     * @param mixed $value The value to append to the array.
     *
     * @example
     * ```php
     * $cargo->addToArray('users', 'john');
     * $cargo->addToArray('users', 'jane');
     * $users = $cargo->get('users'); // ['john', 'jane']
     * ```
     *
     * @note If the key contains a non-array value, it will be replaced with a new array.
     */
    public function addToArray(string $arrayKey, mixed $value): void {
        $a = $this->get($arrayKey, []);
        if (!is_array($a)) {
            $a = [];
        }
        $a[] = $value;
        $this->set($arrayKey, $a);
    }

    /**
     * Stores a value in the container with optional TTL expiration.
     *
     * Values can be stored with an optional time-to-live (TTL) in seconds.
     * TTL values are automatically checked and expired during retrieval.
     *
     * @param string $key The storage key.
     * @param mixed $value The value to store.
     * @param int $ttl Time-to-live in seconds. 0 means no expiration.
     *
     * @example
     * ```php
     * $cargo->set('user_id', 42);           // Permanent storage
     * $cargo->set('temp_token', 'abc123', 300); // Expires in 5 minutes
     * ```
     *
     * @note Operations on locked containers or protected keys are silently ignored and logged.
     */
    public function set(string $key, mixed $value, int $ttl = 0): void {
        if ($this->isLocked()) {
            $this->log('blocked:set (locked)', $key, $value);
            return;
        }

        if ($this->isProtected($key)) {
            $this->log('blocked:set (protected)', $key, $value);
            return;
        }

        self::$dirtyFlags[$this->name] = true;

        if ($ttl > 0) {
            $expiresAt = time() + $ttl;
            self::$instances[$this->name][$key] = [
                '__cargo_value'   => $value,
                '__cargo_expires' => $expiresAt
            ];
            $this->log('set (ttl)', $key, ['value' => $value, 'expires' => $expiresAt]);
        } else {
            self::$instances[$this->name][$key] = $value;
            $this->log('set', $key, $value);
        }
    }

    /**
     * Retrieves a value from the container with optional default fallback.
     *
     * Automatically handles TTL expiration by removing expired entries.
     * Supports null-to-empty-string conversion if configured.
     *
     * @param string $key The storage key to retrieve.
     * @param mixed $default The value to return if the key doesn't exist or has expired.
     *
     * @return mixed The stored value, or the default if not found/expired.
     *
     * @example
     * ```php
     * $userId = $cargo->get('user_id', 0);        // Returns 0 if not set
     * $config = $cargo->get('app_config', []);    // Returns empty array if not set
     * ```
     *
     * @note Expired TTL entries are automatically removed and logged during retrieval.
     */
    public function get(string $key, mixed $default = null): mixed {
        if (!isset(self::$instances[$this->name][$key])) {
            return $this->resolveDefault($default);
        }

        $entry = self::$instances[$this->name][$key];

        if (is_array($entry) && array_key_exists('__cargo_expires', $entry)) {
            if ($entry['__cargo_expires'] !== null && time() > $entry['__cargo_expires']) {
                $this->forget($key);
                $this->log('expired', $key);
                return $this->resolveDefault($default);
            }
            return $entry['__cargo_value'];
        }

        return $entry;
    }

    /**
     * Checks if a key exists in the container and hasn't expired.
     *
     * This method handles TTL expiration by automatically removing expired
     * entries and returning false for them.
     *
     * @param string $key The key to check for existence.
     *
     * @return bool True if the key exists and hasn't expired, false otherwise.
     *
     * @example
     * ```php
     * if ($cargo->has('user_session')) {
     *     $session = $cargo->get('user_session');
     * }
     * ```
     *
     * @note Expired TTL entries are automatically removed during this check.
     */
    public function has(string $key): bool {
        if (!isset(self::$instances[$this->name][$key])) {
            return false;
        }

        $entry = self::$instances[$this->name][$key];

        if (is_array($entry) && array_key_exists('__cargo_expires', $entry)) {
            if ($entry['__cargo_expires'] !== null && time() > $entry['__cargo_expires']) {
                $this->forget($key);
                $this->log('expired:has', $key);
                return false;
            }
        }

        return true;
    }

    /**
     * Removes all expired TTL entries from the container.
     *
     * Manually triggers cleanup of expired entries without waiting for
     * individual key access. Useful for proactive memory management.
     *
     * @example
     * ```php
     * $cargo->purgeExpired(); // Clean up all expired entries
     * ```
     *
     * @note Each purged entry is logged and the container is marked as dirty.
     */
    public function purgeExpired(): void {
        foreach (self::$instances[$this->name] as $key => $value) {
            if (is_array($value) && array_key_exists('__cargo_expires', $value)) {
                if ($value['__cargo_expires'] !== null && time() > $value['__cargo_expires']) {
                    $this->forget($key);
                    $this->log('purged:expired', $key);
                    self::$dirtyFlags[$this->name] = true;
                }
            }
        }
    }

    /**
     * Gets the expiration timestamp for a TTL-enabled key.
     *
     * Returns the Unix timestamp when the key will expire, or null
     * if the key has no TTL or doesn't exist.
     *
     * @param string $key The key to check expiration for.
     *
     * @return int|null The expiration timestamp, or null if no TTL set.
     *
     * @example
     * ```php
     * $expires = $cargo->getExpiresAt('temp_token');
     * if ($expires && $expires < time() + 60) {
     *     // Token expires in less than 1 minute
     * }
     * ```
     */
    public function getExpiresAt(string $key): ?int {
        $entry = self::$instances[$this->name][$key] ?? null;

        if (is_array($entry) && array_key_exists('__cargo_expires', $entry)) {
            return $entry['__cargo_expires'];
        }

        return null;
    }

    /**
     * Removes a key from the container.
     *
     * Respects container locks and key protection. Blocked operations
     * are logged but don't throw exceptions.
     *
     * @param string $key The key to remove.
     *
     * @example
     * ```php
     * $cargo->forget('temp_data');  // Remove the key completely
     * ```
     *
     * @note Operations on locked containers or protected keys are silently ignored and logged.
     */
    public function forget(string $key): void {
        if ($this->isLocked()) {
            $this->log('blocked:forget (locked)', $key);
            return;
        }

        if ($this->isProtected($key)) {
            $this->log('blocked:forget (protected)', $key);
            return;
        }

        unset(self::$instances[$this->name][$key]);
        $this->log('forget', $key);

        self::$dirtyFlags[$this->name] = true;
    }

    /**
     * Removes all data from the container.
     *
     * Clears the entire container while respecting lock status.
     * Locked containers cannot be flushed.
     *
     * @example
     * ```php
     * $cargo->flush(); // Remove all keys and values
     * ```
     *
     * @note Operations on locked containers are silently ignored and logged.
     */
    public function flush(): void {
        if ($this->isLocked()) {
            $this->log('blocked:flush (locked)', '', self::$instances[$this->name] ?? []);
            return;
        }

        $this->log('flush', '', self::$instances[$this->name] ?? []);
        self::$instances[$this->name] = [];
        self::$dirtyFlags[$this->name] = true;
    }

    /**
     * Returns a copy of all data in the container.
     *
     * Provides access to the raw container data including TTL metadata.
     * The returned array is a copy, so modifications won't affect the container.
     *
     * @return array A copy of all container data.
     *
     * @example
     * ```php
     * $data = $cargo->dump();
     * foreach ($data as $key => $value) {
     *     echo "$key => " . print_r($value, true);
     * }
     * ```
     */
    public function dump(): array {
        return self::$instances[$this->name];
    }

    /**
     * Alias for dump() - returns all container data.
     *
     * @return array A copy of all container data.
     *
     * @see dump()
     */
    public function all(): array {
        return $this->dump();
    }

    /**
     * Protects a key from modification or deletion.
     *
     * Protected keys cannot be changed via set(), forget(), or other
     * mutating operations. Useful for read-only configuration values.
     *
     * @param string $key The key to protect from modification.
     *
     * @example
     * ```php
     * $cargo->set('config', ['db_host' => 'localhost']);
     * $cargo->protect('config');
     * $cargo->set('config', 'new_value'); // Silently ignored and logged
     * ```
     */
    public function protect(string $key): void {
        self::$protected[$this->name][$key] = true;
    }

    /**
     * Checks if a key is protected from modification.
     *
     * @param string $key The key to check protection status for.
     *
     * @return bool True if the key is protected, false otherwise.
     *
     * @example
     * ```php
     * if (!$cargo->isProtected('user_data')) {
     *     $cargo->set('user_data', $newData);
     * }
     * ```
     */
    public function isProtected(string $key): bool {
        return self::$protected[$this->name][$key] ?? false;
    }

    /**
     * Retrieves and removes a key from the container in one operation.
     *
     * Atomically gets a value and removes it from the container,
     * useful for one-time tokens or queue-like operations.
     *
     * @param string $key The key to retrieve and remove.
     * @param mixed $default The value to return if the key doesn't exist.
     *
     * @return mixed The retrieved value, or the default if not found.
     *
     * @example
     * ```php
     * $token = $cargo->pull('csrf_token', null);  // Get and remove token
     * $message = $cargo->pull('flash_message', ''); // Get and remove message
     * ```
     *
     * @note Both the retrieval and removal are logged separately.
     */
    public function pull(string $key, mixed $default = null): mixed {
        $value = $this->get($key, $default);
        $this->forget($key);    // already logs internally
        $this->log('pull', $key, $value);   // optional but helpful for debugging
        return $value;
    }

    /**
     * Returns a value if it exists, otherwise sets and returns the provided default.
     *
     * Implements a "get or set" pattern, useful for lazy initialization
     * or ensuring a key always has a value.
     *
     * @param string $key The key to check and potentially set.
     * @param mixed $default The value to set and return if the key doesn't exist.
     *
     * @return mixed The existing value or the newly set default.
     *
     * @example
     * ```php
     * $config = $cargo->remember('app_config', ['debug' => false]);
     * $counter = $cargo->remember('page_views', 0);
     * ```
     *
     * @note If the key exists, the default value is not stored or returned.
     */
    public function remember(string $key, mixed $default): mixed {
        if (!$this->has($key)) {
            $this->set($key, $default);
        }
        return $this->get($key);
    }

    /**
     * Serializes the container data to a JSON string.
     *
     * Converts all container data to JSON format for persistence,
     * debugging, or API responses.
     *
     * @param int $flags JSON encoding flags (e.g., JSON_PRETTY_PRINT).
     *
     * @return string The container data as a JSON string.
     * @throws Exception If JSON encoding fails.
     *
     * @example
     * ```php
     * $json = $cargo->toJSON(JSON_PRETTY_PRINT);
     * file_put_contents('cargo_backup.json', $json);
     * ```
     *
     * @note TTL metadata is included in the JSON output.
     */
    public function toJSON(int $flags = 0): string {
        $json = json_encode(self::$instances[$this->name], $flags);
        if ($json === false) {
            throw new Exception('Failed to encode container data to JSON: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Replaces all container data with the provided array.
     *
     * Completely overwrites the container contents and resets the dirty flag.
     * Respects container locks.
     *
     * @param array $data The data to load into the container.
     *
     * @example
     * ```php
     * $cargo->loadFromArray(['key1' => 'value1', 'key2' => 'value2']);
     * ```
     *
     * @note This operation resets the dirty flag to false after loading.
     */
    public function loadFromArray(array $data): void {
        if ($this->isLocked()) return;
        self::$instances[$this->name] = $data;
        self::$dirtyFlags[$this->name] = false;
    }

    /**
     * Merges an array into the container with optional overwrite control.
     *
     * Adds or updates keys from the provided array while optionally
     * preserving existing values. Respects locks and key protection.
     *
     * @param array $data The data to merge into the container.
     * @param bool $overwrite Whether to overwrite existing keys. Defaults to false.
     *
     * @example
     * ```php
     * $cargo->mergeArray(['new_key' => 'value'], true);  // Overwrite existing
     * $cargo->mergeArray(['safe_key' => 'value'], false); // Keep existing
     * ```
     *
     * @note Protected keys and locked containers prevent merging with appropriate logging.
     */
    public function mergeArray(array $data, bool $overwrite = false): void {
        if ($this->isLocked()) {
            $this->log('blocked:mergeArray (locked)', '', $data);
            return;
        }

        foreach ($data as $key => $value) {
            if (!$overwrite && array_key_exists($key, self::$instances[$this->name])) {
                continue; // skip if not overwriting
            }

            if ($this->isProtected($key)) {
                $this->log('blocked:mergeArray (protected)', $key, $value);
                continue;
            }

            self::$instances[$this->name][$key] = $value;
            $this->log('mergeArray', $key, $value);
            self::$dirtyFlags[$this->name] = true;
        }
    }

    /**
     * Saves the container data to a JSON file.
     *
     * Serializes the container to JSON and writes it to the specified file path.
     * Useful for persistence between requests or application restarts.
     *
     * @param string $filepath The file path to save the JSON data to.
     *
     * @return bool True if the file was written successfully, false otherwise.
     * @throws Exception If JSON encoding fails.
     *
     * @example
     * ```php
     * if ($cargo->saveToFile('/tmp/cargo_backup.json')) {
     *     echo "Container saved successfully";
     * }
     * ```
     *
     * @note The file is created with JSON_PRETTY_PRINT for readability.
     */
    public function saveToFile(string $filepath): bool {
        try {
            $json = $this->toJSON(JSON_PRETTY_PRINT);
            $result = file_put_contents($filepath, $json);
            return $result !== false;
        } catch (Exception $e) {
            $this->log('saveToFile:error', $filepath, $e->getMessage());
            return false;
        }
    }

    /**
     * Loads container data from a JSON file.
     *
     * Reads and parses a JSON file, then loads the data into the container.
     * The operation is logged and overwrites existing container data.
     *
     * @param string $filepath The file path to load JSON data from.
     *
     * @return bool True if the file was loaded successfully, false otherwise.
     *
     * @example
     * ```php
     * if ($cargo->loadFromFile('/tmp/cargo_backup.json')) {
     *     echo "Container loaded successfully";
     * }
     * ```
     *
     * @note Returns false if the file doesn't exist or contains invalid JSON.
     */
    public function loadFromFile(string $filepath): bool {
        try {
            if (!file_exists($filepath)) {
                return false;
            }

            $json = file_get_contents($filepath);
            if ($json === false) {
                $this->log('loadFromFile:error', $filepath, 'Failed to read file');
                return false;
            }

            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('loadFromFile:error', $filepath, 'Invalid JSON: ' . json_last_error_msg());
                return false;
            }

            if (!is_array($data)) {
                $this->log('loadFromFile:error', $filepath, 'JSON does not contain an array');
                return false;
            }

            $this->log('loadFromFile', $filepath, $data);
            $this->loadFromArray($data);
            return true;
        } catch (Exception $e) {
            $this->log('loadFromFile:error', $filepath, $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a named container exists in the registry.
     *
     * @param string $name The container name to check.
     *
     * @return bool True if the container exists, false otherwise.
     *
     * @example
     * ```php
     * if (Cargo::exists('session')) {
     *     $session = Cargo::getInstance('session');
     * }
     * ```
     */
    public static function exists(string $name): bool {
        return isset(self::$instances[$name]);
    }

    /**
     * Copies all data from one container to another.
     *
     * Creates an exact duplicate of the source container's data in the
     * destination container, overwriting any existing data.
     *
     * @param string $from The source container name.
     * @param string $to The destination container name.
     *
     * @example
     * ```php
     * Cargo::copy('production_config', 'backup_config');
     * ```
     *
     * @note If the source container doesn't exist, no operation is performed.
     */
    public static function copy(string $from, string $to): void {
        if (!isset(self::$instances[$from])) return;

        self::$instances[$to] = self::$instances[$from];
        self::$dirtyFlags[$to] = true;
    }

    /**
     * Merges data from one container into another with optional overwrite control.
     *
     * Combines data from the source container into the destination container,
     * with control over whether existing keys should be overwritten.
     *
     * @param string $from The source container name.
     * @param string $to The destination container name.
     * @param bool $overwrite Whether to overwrite existing keys in the destination.
     *
     * @example
     * ```php
     * Cargo::merge('default_config', 'user_config', false); // Keep user settings
     * Cargo::merge('new_data', 'existing_data', true);      // Overwrite existing
     * ```
     *
     * @note Creates the destination container if it doesn't exist.
     */
    public static function merge(string $from, string $to, bool $overwrite = false): void {
        if (!isset(self::$instances[$from])) return;
        if (!isset(self::$instances[$to])) {
            self::$instances[$to] = [];
            self::$dirtyFlags[$to] = true;
        }

        foreach (self::$instances[$from] as $key => $value) {
            if ($overwrite || !array_key_exists($key, self::$instances[$to])) {
                self::$instances[$to][$key] = $value;
                self::$dirtyFlags[$to] = true;

            }
        }
    }

    /**
     * Completely removes a container and all associated metadata.
     *
     * Deletes the container data, object instance, locks, protections,
     * and dirty flags. The operation is logged before removal.
     *
     * @param string $name The container name to remove.
     *
     * @example
     * ```php
     * Cargo::removeContainer('temporary_data');
     * ```
     *
     * @note Logs are preserved even after container removal.
     */
    public static function removeContainer(string $name): void {
        if (isset(self::$instances[$name])) {
            self::logStatic($name, 'removeContainer', '', self::$instances[$name]);
        }

        unset(self::$instances[$name], self::$objects[$name], self::$locks[$name], self::$protected[$name], self::$dirtyFlags[$name]);  // Keep logs
    }

    /**
     * Clears all containers and their associated metadata.
     *
     * Removes all container data, object instances, locks, protections,
     * dirty flags, and version snapshots. A complete reset operation.
     *
     * @example
     * ```php
     * Cargo::forgetAll(); // Clean slate for testing or reset
     * ```
     *
     * @note This is a destructive operation that cannot be undone.
     */
    public static function forgetAll(): void {
        self::$instances = [];
        self::$objects = [];
        self::$locks = [];
        self::$dirtyFlags = [];
        self::$protected = [];
        self::$versions = [];
    }

    /**
     * Returns an array of all keys in the container.
     *
     * @return array An array of string keys currently in the container.
     *
     * @example
     * ```php
     * $keys = $cargo->keys(); // ['user_id', 'session_data', 'preferences']
     * foreach ($keys as $key) {
     *     echo "Key: $key, Value: " . $cargo->get($key) . "\n";
     * }
     * ```
     */
    public function keys(): array {
        return array_keys(self::$instances[$this->name]);
    }

    /**
     * Locks the container against all write operations.
     *
     * Prevents any modifications to the container including set(), forget(),
     * flush(), and merge operations. Useful for read-only modes.
     *
     * @example
     * ```php
     * $cargo->lock();
     * $cargo->set('key', 'value'); // Silently ignored and logged
     * ```
     *
     * @note Once locked, a container cannot be unlocked within the same request.
     */
    public function lock(): void {
        self::$locks[$this->name] = true;
    }

    /**
     * Checks if the container is locked against write operations.
     *
     * @return bool True if the container is locked, false otherwise.
     *
     * @example
     * ```php
     * if (!$cargo->isLocked()) {
     *     $cargo->set('safe_to_write', true);
     * }
     * ```
     */
    public function isLocked(): bool {
        return self::$locks[$this->name] ?? false;
    }

    /**
     * Creates a versioned snapshot of the current container state.
     *
     * Captures the entire container data at a point in time for later restoration.
     * If no version name is provided, uses a timestamp.
     *
     * @param string|null $versionName Optional name for the snapshot. Auto-generated if null.
     *
     * @return string The name of the created snapshot version.
     *
     * @example
     * ```php
     * $version = $cargo->snapshot('before_changes');
     * // Make changes...
     * $cargo->restore('before_changes'); // Rollback
     * ```
     *
     * @note Snapshots persist until the container is removed or the application restarts.
     */
    public function snapshot(?string $versionName = null): string {
        if ($versionName === null) {
            $versionName = date('Y-m-d_H-i-s');
        }
        self::$versions[$this->name][$versionName] = $this->dump();
        return $versionName;
    }

    /**
     * Restores the container to a previously saved snapshot version.
     *
     * Replaces all current container data with the data from the specified
     * snapshot version. Respects container locks.
     *
     * @param string $versionName The name of the snapshot version to restore.
     *
     * @return bool True if the restore was successful, false if version not found or container locked.
     *
     * @example
     * ```php
     * $cargo->restore('initial_state');
     * $cargo->restore('before_user_changes');
     * ```
     *
     * @note Locked containers cannot be restored.
     */
    public function restore(string $versionName): bool {
        if (!isset(self::$versions[$this->name][$versionName])) return false;
        if ($this->isLocked()) return false;
        $this->loadFromArray(self::$versions[$this->name][$versionName]);
        return true;
    }

    /**
     * Returns a list of all available snapshot version names for this container.
     *
     * @return array An array of version names that can be used with restore().
     *
     * @example
     * ```php
     * $versions = $cargo->listVersions();
     * foreach ($versions as $version) {
     *     echo "Available version: $version\n";
     * }
     * ```
     */
    public function listVersions(): array {
        return array_keys(self::$versions[$this->name] ?? []);
    }

    /**
     * Compares two containers and returns the differences.
     *
     * Analyzes two named containers and returns arrays of added, removed,
     * and changed keys between them.
     *
     * @param string $a The first container name.
     * @param string $b The second container name.
     *
     * @return array An associative array with 'added', 'removed', and 'changed' keys.
     *
     * @example
     * ```php
     * $diff = Cargo::diff('original', 'modified');
     * // ['added' => [...], 'removed' => [...], 'changed' => [...]]
     * ```
     *
     * @note Non-existent containers are treated as empty arrays.
     */
    public static function diff(string $a, string $b): array {
        $aData = self::$instances[$a] ?? [];
        $bData = self::$instances[$b] ?? [];
        return [
            'added'   => array_diff_key($bData, $aData),
            'removed' => array_diff_key($aData, $bData),
            'changed' => array_diff_assoc($bData, $aData),
        ];
    }

    /**
     * Logs an operation for this container instance.
     *
     * Records container operations with timestamp, action, key, and value
     * for debugging and auditing purposes.
     *
     * @param string $action The action being performed (e.g., 'set', 'get', 'forget').
     * @param string $key The key involved in the operation.
     * @param mixed $value The value involved in the operation.
     *
     * @example
     * ```php
     * $cargo->log('custom_action', 'user_id', 42);
     * ```
     *
     * @note Logs are automatically pruned when they exceed the global limit.
     */
    public function log(string $action, string $key = '', mixed $value = null): void {
        self::logStatic($this->name, $action, $key, $value);
    }

    /**
     * Static method for logging operations on named containers.
     *
     * Internal method used by the logging system to record operations
     * with automatic pruning when the log limit is exceeded.
     *
     * @param string $name The container name.
     * @param string $action The action being performed.
     * @param string $key The key involved in the operation.
     * @param mixed $value The value involved in the operation.
     *
     * @note This method handles automatic log pruning and adds pruning entries.
     */
    private static function logStatic(string $name, string $action, string $key = '', mixed $value = null): void {
        self::$logs[$name][] = [
            'time'   => date('c'),
            'action' => $action,
            'key'    => $key,
            'value'  => $value,
        ];

        // Auto-prune if over limit
        if (count(self::$logs[$name]) > self::$logLimit) {
            self::$logs[$name] = array_slice(self::$logs[$name], -self::$logLimit);
            self::$logs[$name][] = [
                'time'   => date('c'),
                'action' => 'log:autopruned',
                'key'    => '',
                'value'  => ['kept' => self::$logLimit],
            ];
        }
    }

    /**
     * Sets the global limit for log entries per container.
     *
     * @param int $limit The maximum number of log entries to keep. Minimum is 0.
     *
     * @example
     * ```php
     * Cargo::setLogLimit(500);  // Keep last 500 log entries
     * Cargo::setLogLimit(0);    // Disable logging
     * ```
     */
    public static function setLogLimit(int $limit): void {
        self::$logLimit = max(0, $limit);
    }

    /**
     * Gets the current global log limit.
     *
     * @return int The maximum number of log entries kept per container.
     *
     * @example
     * ```php
     * $currentLimit = Cargo::getLogLimit();
     * ```
     */
    public static function getLogLimit(): int {
        return self::$logLimit;
    }

    /**
     * Retrieves the operation log for this container with optional filtering.
     *
     * Returns an array of logged operations with timestamps, actions, keys, and values.
     * Can be filtered by action prefix for targeted debugging.
     *
     * @param string $filterPrefix Optional prefix to filter log entries by action.
     *
     * @return array An array of log entries matching the filter criteria.
     *
     * @example
     * ```php
     * $allLogs = $cargo->getLog();                    // All operations
     * $setLogs = $cargo->getLog('set');               // Only 'set' operations
     * $blockedLogs = $cargo->getLog('blocked:');      // Only blocked operations
     * ```
     */
    public function getLog(string $filterPrefix = ''): array {
        $log = self::$logs[$this->name] ?? [];
        if ($filterPrefix === '') return $log;
        return array_filter($log, fn($entry) => str_starts_with($entry['action'], $filterPrefix));
    }

    /**
     * Manually prunes the operation log to keep only recent entries.
     *
     * Removes older log entries while keeping the specified number of recent ones.
     * The pruning operation itself is logged.
     *
     * @param int $keep The number of recent log entries to preserve.
     *
     * @example
     * ```php
     * $cargo->pruneLog(50);  // Keep only the last 50 log entries
     * ```
     *
     * @note The pruning operation is logged with statistics about removed entries.
     */
    public function pruneLog(int $keep = 100): void {
        if (!isset(self::$logs[$this->name])) return;

        $log = self::$logs[$this->name];
        $total = count($log);

        if ($total > $keep) {
            self::$logs[$this->name] = array_slice($log, -$keep);
            $this->log('log:pruned', '', ['kept' => $keep, 'removed' => $total - $keep]);
        }
    }

    /**
     * Lazily evaluates and caches the result of a callback.
     *
     * If the key exists, returns the stored value. If not, executes the callback,
     * stores the result, and returns it. Useful for expensive computations.
     *
     * @param string $key The cache key for the computed value.
     * @param callable $callback The function to execute if the key doesn't exist.
     *
     * @return mixed The cached or newly computed value.
     *
     * @example
     * ```php
     * $config = $cargo->lazy('database_config', function() {
     *     return loadExpensiveDatabaseConfig();
     * });
     * ```
     *
     * @note The callback is only executed once per key, subsequent calls return the cached value.
     */
    public function lazy(string $key, callable $callback): mixed {
        if (!$this->has($key)) {
            $this->set($key, $callback());
            self::$dirtyFlags[$this->name] = true;
        }
        return $this->get($key);
    }

    /**
     * Saves all container instances to the PHP session.
     *
     * Stores the entire Cargo state in the session for persistence across requests.
     * Automatically starts the session if not already active.
     *
     * @example
     * ```php
     * Cargo::saveToSession();  // Persist all containers to session
     * ```
     *
     * @note Only primitive values and arrays should be stored to avoid serialization issues.
     */
    public static function saveToSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['cargo_instances'] = self::$instances;
    }

    /**
     * Loads container instances from the PHP session.
     *
     * Restores previously saved Cargo state from the session and resets
     * dirty flags. Automatically starts the session if not already active.
     *
     * @example
     * ```php
     * Cargo::loadFromSession();  // Restore containers from session
     * ```
     *
     * @note All loaded containers have their dirty flags reset to false.
     */
    public static function loadFromSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['cargo_instances']) && is_array($_SESSION['cargo_instances'])) {
            self::$instances = $_SESSION['cargo_instances'];
            foreach (self::$instances as $name => $data) {
                self::$dirtyFlags[$name] = false;
            }
        }
    }

    /**
     * Removes all Cargo data from the PHP session.
     *
     * Cleans up session storage by removing the Cargo instances data.
     * Automatically starts the session if not already active.
     *
     * @example
     * ```php
     * Cargo::clearFromSession();  // Remove all Cargo data from session
     * ```
     */
    public static function clearFromSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['cargo_instances']);
    }

    /**
     * Configures null-to-empty-string conversion for this container or globally.
     *
     * When enabled, null default values are converted to empty strings when
     * retrieving non-existent keys. Can be set per-instance or globally.
     *
     * @param bool $toggle Whether to enable null-to-empty-string conversion.
     * @param bool $makeGlobal Whether to apply this setting globally to new containers.
     *
     * @return static Returns the container instance for method chaining.
     *
     * @example
     * ```php
     * $cargo->nullsAreEmptyStrings(true);           // For this container only
     * $cargo->nullsAreEmptyStrings(true, true);     // For all new containers
     * $value = $cargo->get('missing_key');          // Returns '' instead of null
     * ```
     */
    public function nullsAreEmptyStrings(bool $toggle = true, bool $makeGlobal = false): static {
        if ($makeGlobal) {
            self::$globalEmptyStringFallback = $toggle;
        }
        $this->returnEmptyStringForNull = $toggle;
        return $this;
    }

    /**
     * Resolves default values with null-to-empty-string conversion if enabled.
     *
     * Internal method that handles the conversion of null defaults to empty
     * strings when the feature is enabled for this container instance.
     *
     * @param mixed $default The default value to potentially convert.
     *
     * @return mixed The default value, or empty string if null and conversion is enabled.
     */
    private function resolveDefault(mixed $default): mixed {
        if ($this->returnEmptyStringForNull && $default === null) {
            return '';
        }
        return $default;
    }

    /**
     * Checks if this container instance is configured to return empty strings for null defaults.
     *
     * @return bool True if null-to-empty-string conversion is enabled for this instance.
     *
     * @example
     * ```php
     * if ($cargo->shouldReturnEmptyStringForNull()) {
     *     echo "This container converts nulls to empty strings";
     * }
     * ```
     */
    public function shouldReturnEmptyStringForNull(): bool {
        return $this->returnEmptyStringForNull;
    }

    /**
     * Checks if the global null-to-empty-string fallback is enabled.
     *
     * @return bool True if new containers will default to null-to-empty-string conversion.
     *
     * @example
     * ```php
     * if (Cargo::shouldReturnEmptyStringsGlobally()) {
     *     echo "New containers will convert nulls to empty strings";
     * }
     * ```
     */
    public static function shouldReturnEmptyStringsGlobally(): bool {
        return self::$globalEmptyStringFallback;
    }


    /**
     * Saves all container instances to the Safe session system.
     *
     * Stores the entire Cargo state in the Safe session for secure persistence
     * across requests. Automatically ensures Safe is started before saving.
     *
     * @throws Exception If Safe session cannot be started or data cannot be saved.
     * @example
     * ```php
     * Cargo::saveToSafe();  // Persist all containers to Safe session
     * ```
     *
     * @note Objects will lose class information due to JSON serialization. Use only
     *       JSON-compatible data (primitives, arrays, JsonSerializable objects).
     */
    public static function saveToSafe(): void {
        Safe::set('cargo_instances', self::$instances);
    }

    /**
     * Loads container instances from the Safe session system.
     *
     * Restores previously saved Cargo state from the Safe session and resets
     * dirty flags. Automatically ensures Safe is started before loading.
     *
     * @example
     * ```php
     * Cargo::loadFromSafe();  // Restore containers from Safe session
     * ```
     *
     * @note All loaded containers have their dirty flags reset to false.
     * @throws Exception If Safe session cannot be started.
     */
    public static function loadFromSafe(): void {
        $safeInstances = Safe::get('cargo_instances');

        if (!empty($safeInstances) && is_array($safeInstances)) {
            self::$instances = $safeInstances;
            foreach (self::$instances as $name => $data) {
                self::$dirtyFlags[$name] = false;
            }
        }
    }

    /**
     * Removes all Cargo data from the Safe session system.
     *
     * Cleans up Safe session storage by removing the Cargo instances data.
     * Automatically ensures Safe is started before removing.
     *
     * @example
     * ```php
     * Cargo::removeFromSafe();  // Remove all Cargo data from Safe session
     * ```
     *
     * @throws Exception If Safe session cannot be started.
     */
    public static function removeFromSafe(): void {
        Safe::forget('cargo_instances');
    }

}
