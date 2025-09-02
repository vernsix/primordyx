<?php
/**
 * File: /vendor/vernsix/primordyx/src/Model.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Model.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use AllowDynamicProperties;
use DateTimeInterface;
use JsonSerializable;
use PDO;
use Primordyx\Data\Cargo;
use Primordyx\Data\Validator;
use RuntimeException;
use Throwable;


/**
 * Abstract ORM base class with query building, soft deletes, and automatic type casting
 *
 * Comprehensive database model foundation for the Primordyx framework providing full ORM
 * functionality with fluent query interfaces, automatic database introspection, soft delete
 * support, validation integration, and JSON serialization. Features automatic timestamp
 * management, type casting system, and seamless pagination support.
 *
 * ## Core ORM Features
 * - **Database Introspection**: Automatic column discovery and caching via Cargo
 * - **Soft Delete Support**: Optional soft deletes with restore functionality
 * - **Automatic Timestamps**: created_at/updated_at management
 * - **Type Casting System**: Configurable attribute casting (bool, int, float, datetime)
 * - **Fluent Query Builder**: Chainable query methods with QueryBuilder integration
 * - **Validation Integration**: Validator system with error handling
 * - **Change Tracking**: Dirty field detection and original state preservation
 * - **JSON Serialization**: Built-in JsonSerializable implementation
 * - **Pagination Support**: Integrated pagination with Paginator class
 * - **Bulk Operations**: Bulk update capabilities with query constraints
 *
 * ## Database Schema Requirements
 * Models expect standard conventions but allow override:
 * - Primary key: `id` (configurable via $primaryKey)
 * - Table name: Class name + 's' (configurable via $table)
 * - Timestamps: `created_at`, `updated_at` (if $timestamps = true)
 * - Soft deletes: `deleted_at`, `restored_at` (if $softDelete = true)
 *
 * ## Query Builder Integration
 * Seamless integration with QueryBuilder for complex queries:
 * - Fluent interface with method chaining
 * - Query state preservation between operations
 * - Automatic soft delete filtering
 * - Support for raw queries and complex conditions
 *
 * ## Type Casting System
 * Configurable attribute casting via $casts property:
 * - `bool`: Converts to 1/0 for database storage
 * - `int`: Numeric validation with fallback to 0
 * - `float`: Float conversion with fallback to 0.0
 * - `datetime`: Converts DateTime objects and strings to Y-m-d H:i:s format
 *
 * @since 1.0.0
 *
 * @example Basic Model Usage
 * ```php
 * class User extends Model
 * {
 *     protected string $table = 'users';
 *     protected array $casts = [
 *         'active' => 'bool',
 *         'age' => 'int',
 *         'created_at' => 'datetime'
 *     ];
 *
 *     public function rules(): array
 *     {
 *         return [
 *             'email' => 'required|email',
 *             'username' => 'required|min:3'
 *         ];
 *     }
 * }
 * ```
 *
 * @example CRUD Operations
 * ```php
 * // Create new record
 * $user = new User(['name' => 'John', 'email' => 'john@example.com']);
 * $user->save();
 *
 * // Find and update
 * $user = (new User())->find(1);
 * $user->name = 'Jane';
 * $user->save();
 *
 * // Soft delete
 * $user->delete(); // Sets deleted_at timestamp
 * $user->restore(); // Clears deleted_at
 * ```
 *
 * @example Query Builder Integration
 * ```php
 * $users = (new User())
 *     ->where('active', 1)
 *     ->where('age', '>=', 18)
 *     ->orderBy('created_at', 'DESC')
 *     ->limit(10)
 *     ->getAsModels();
 * ```
 *
 * @example Pagination
 * ```php
 * $paginator = (new User())
 *     ->where('active', 1)
 *     ->paginateAsModels(20, 1); // 20 per page, page 1
 *
 * foreach ($paginator->getData() as $user) {
 *     echo $user->name;
 * }
 * ```
 *
 * @see QueryBuilder For query building functionality
 * @see Paginator For pagination results
 * @see ConnectionManager For database connection management
 * @see Validator For validation system integration
 */
#[AllowDynamicProperties]
abstract class Model implements JsonSerializable
{
    /**
     * Database table name for the model
     *
     * Specifies which database table this model represents. Defaults to empty string,
     * which triggers automatic table name generation based on class name (class + 's').
     * Override in child classes to specify explicit table names.
     *
     * @var string Database table name or empty for auto-generation
     * @since 1.0.0
     */
    protected string $table = '';

    /**
     * Primary key column name for database operations
     *
     * Defines the primary key field used for find, update, and delete operations.
     * Must be a unique identifier column in the database table. Used for exists()
     * checking and WHERE clauses in update/delete operations.
     *
     * @var string Primary key column name
     * @since 1.0.0
     */
    protected string $primaryKey = 'id';

    /**
     * Enable soft delete functionality for this model
     *
     * When true, delete() operations set deleted_at timestamp instead of removing
     * records. Automatic filtering excludes soft-deleted records from queries unless
     * specifically included via withTrashed() or onlyTrashed() methods.
     *
     * @var bool Whether to use soft deletes (true) or hard deletes (false)
     * @since 1.0.0
     */
    protected bool $softDelete = true;

    /**
     * Enable automatic timestamp management
     *
     * When true, automatically manages created_at and updated_at fields during
     * save operations. Sets created_at on insert and updated_at on all saves.
     * Requires corresponding datetime columns in database table.
     *
     * @var bool Whether to automatically manage timestamps
     * @since 1.0.0
     */
    protected bool $timestamps = true;

    /**
     * Current model attribute data with type casting applied
     *
     * Stores all model attributes as key-value pairs after type casting via castAttribute().
     * Initialized with all table columns set to null during construction. Modified via
     * magic methods __get/__set or direct array access.
     *
     * @var array<string, mixed> Model attributes with applied type casting
     * @since 1.0.0
     */
    protected array $data = [];

    /**
     * Original attribute values for change detection
     *
     * Preserves the initial state of model data after loading from database or
     * after successful save operations. Used by isDirty() and getDirty() methods
     * to detect field changes and optimize update queries.
     *
     * @var array<string, mixed> Original model state for change tracking
     * @since 1.0.0
     */
    protected array $original = [];

    /**
     * Validation and database error collection
     *
     * Stores validation errors from isValid() and database operation errors.
     * Cleared on each validation run. Used by hasErrors() and getErrors() methods
     * to provide error feedback after validation or save operations.
     *
     * @var array<string, array<string>> Error messages grouped by field/category
     * @since 1.0.0
     */
    protected array $errors = [];

    /**
     * Flag to include soft-deleted records in queries
     *
     * When true, queries will include records with deleted_at timestamps.
     * Modified by withTrashed() method to temporarily include soft-deleted
     * records in query results. Resets to false for new queries.
     *
     * @var bool Whether current query includes soft-deleted records
     * @since 1.0.0
     */
    protected bool $includeSoftDeleted = false;

    /**
     * PDO database connection handle for all database operations
     *
     * Database connection used for all SQL operations. Defaults to ConnectionManager
     * global connection but can be overridden via constructor parameter for
     * multi-database scenarios or testing with specific connections.
     *
     * @var PDO|null Database connection or null to use default
     * @since 1.0.0
     */
    protected ?PDO $db = null;

    /**
     * Attribute type casting configuration array
     *
     * Defines how specific attributes should be cast when setting values.
     * Supported types: 'bool' (to 1/0), 'int', 'float', 'datetime'.
     * Override in child classes to specify casting rules for model attributes.
     *
     * @var array<string, string> Attribute name => cast type mapping
     * @since 1.0.0
     */
    protected array $casts = [];

    /**
     * Current QueryBuilder instance for fluent query chaining
     *
     * Preserves query state between fluent method calls. Set by query methods
     * like where(), orderBy(), limit() to maintain query context. Reset after
     * query execution or via resetQueryBuilder() method.
     *
     * @var QueryBuilder|null Current query builder or null for fresh queries
     * @since 1.0.0
     */
    protected ?QueryBuilder $queryBuilder = null;

    /**
     * Initialize model with database introspection and attribute setup
     *
     * Creates new model instance with automatic database column discovery, caching,
     * and attribute initialization. Performs database introspection to determine
     * available columns and initializes all columns to null before applying
     * provided attributes.
     *
     * ## Initialization Process
     * 1. Sets database connection (parameter or ConnectionManager default)
     * 2. Overrides table/primaryKey if provided
     * 3. Checks Cargo cache for table columns
     * 4. Performs DESCRIBE query if columns not cached
     * 5. Caches column list for future use
     * 6. Initializes all columns to null in $data array
     * 7. Applies provided attributes via fill()
     * 8. Captures original state for change tracking
     *
     * ## Column Caching
     * Uses Cargo caching with key format: 'model.columns.{table_name}'
     * Reduces database queries by caching DESCRIBE results
     * Automatically handles cache invalidation via reloadColumns()
     *
     * @param array<string, mixed> $attrs Initial attribute values to fill
     * @param PDO|null $db Database connection override (null uses ConnectionManager)
     * @param string|null $table Table name override (null uses model default)
     * @param string|null $primaryKey Primary key override (null uses model default)
     *
     * @throws RuntimeException If database introspection fails or table doesn't exist
     * @since 1.0.0
     *
     * @example Basic Construction
     * ```php
     * $user = new User(['name' => 'John', 'email' => 'john@example.com']);
     * ```
     *
     * @example With Database Override
     * ```php
     * $readOnlyDb = ConnectionManager::getHandle('readonly');
     * $user = new User([], $readOnlyDb);
     * ```
     *
     * @example With Table Override
     * ```php
     * $user = new User(['name' => 'John'], null, 'custom_users_table');
     * ```
     *
     * @see ConnectionManager::getHandle() For database connection management
     * @see Cargo For column caching system
     * @see QueryTracker For query performance monitoring
     */
    public function __construct(array $attrs = [], ?PDO $db = null, ?string $table = null, ?string $primaryKey = null)
    {
        $this->db = $db ?? ConnectionManager::getHandle();
        if ($table) $this->table = $table;
        if ($primaryKey) $this->primaryKey = $primaryKey;

        // Use cargo to cache column names per table
        $cacheKey = 'model.columns.' . $this->table();
        $columns = Cargo::on($cacheKey)->get($cacheKey);

        if (!$columns) {
            try {
                $sql = "DESCRIBE `{$this->table()}`";
                QueryTracker::start();
                $stmt = $this->db->query($sql);
                QueryTracker::stop($sql, []);

                $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                Cargo::on($cacheKey)->set($cacheKey, $columns);
            } catch (Throwable $e) {
                // Fallback: only essential fields
                throw new RuntimeException("Failed to introspect table `{$this->table()}`: " . $e->getMessage(), 500, $e);
            }
        }

        // Initialize all columns to null
        foreach ($columns as $col) {
            $this->data[$col] = null;
        }

        // Apply passed-in attributes and capture original state
        $this->fill($attrs);
        $this->original = $this->data;
    }

    /**
     * Apply configured type casting to attribute values
     *
     * Converts attribute values based on the $casts configuration array.
     * Provides consistent type handling across all attribute operations
     * with fallback values for invalid input data.
     *
     * ## Supported Cast Types
     * - **bool**: Uses filter_var(FILTER_VALIDATE_BOOLEAN), returns 1/0 for database
     * - **int**: Validates numeric input, returns 0 for non-numeric values
     * - **float**: Validates numeric input, returns 0.0 for non-numeric values
     * - **datetime**: Converts DateTime objects and strings to 'Y-m-d H:i:s' format
     *
     * ## Type Safety
     * Invalid values are converted to safe defaults rather than causing errors:
     * - Non-boolean values become 0 (false) for bool type
     * - Non-numeric values become 0 for int type
     * - Non-numeric values become 0.0 for float type
     * - Invalid datetime strings use strtotime() for parsing
     *
     * @param string $field Attribute name to check for casting configuration
     * @param mixed $value Raw value to apply casting to
     * @return mixed Casted value or original value if no casting defined
     * @since 1.0.0
     *
     * @example Casting Configuration
     * ```php
     * protected array $casts = [
     *     'active' => 'bool',      // '1' becomes 1, 'false' becomes 0
     *     'age' => 'int',          // '25' becomes 25, 'abc' becomes 0
     *     'price' => 'float',      // '19.99' becomes 19.99
     *     'birthday' => 'datetime' // '2023-01-01' becomes '2023-01-01 00:00:00'
     * ];
     * ```
     */
    protected function castAttribute(string $field, mixed $value): mixed
    {
        $type = $this->casts[$field] ?? null;

        if ($type === 'bool') {
            // invalid values are false!
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if ($type === 'int') {
            return is_numeric($value) ? (int)$value : 0;
        }

        if ($type === 'float') {
            return is_numeric($value) ? (float)$value : 0.0;
        }

        if ($type === 'datetime' && $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($type === 'datetime' && is_string($value)) {
            return date('Y-m-d H:i:s', strtotime($value));
        }

        return $value;
    }

    /**
     * Magic method to retrieve model attributes
     *
     * Provides object-like access to model data array. Returns null for
     * non-existent attributes rather than throwing errors for graceful
     * handling of undefined properties.
     *
     * @param string $key Attribute name to retrieve
     * @return mixed Attribute value or null if not set
     * @since 1.0.0
     *
     * @example Attribute Access
     * ```php
     * $user = new User(['name' => 'John', 'email' => 'john@example.com']);
     * echo $user->name;  // 'John'
     * echo $user->missing; // null
     * ```
     */
    public function __get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Magic method to set model attributes with type casting
     *
     * Applies configured type casting via castAttribute() before storing
     * values in the data array. Provides object-like attribute assignment
     * with automatic type conversion.
     *
     * @param string $key Attribute name to set
     * @param mixed $value Raw value to cast and store
     * @return void
     * @since 1.0.0
     *
     * @example Attribute Setting
     * ```php
     * $user = new User();
     * $user->active = 'yes';  // Casted to 1 if 'active' => 'bool' in $casts
     * $user->age = '25';      // Casted to 25 if 'age' => 'int' in $casts
     * ```
     *
     * @see castAttribute() For type casting implementation
     */
    public function __set(string $key, mixed $value): void
    {
        // $this->data[$key] = $value;
        $this->data[$key] = $this->castAttribute($key, $value);
    }

    /**
     * Magic method to check if model attribute is set
     *
     * Returns true if the attribute exists and is not null in the data array.
     * Enables use of isset() and empty() with model attributes.
     *
     * @param string $key Attribute name to check
     * @return bool True if attribute is set and not null
     * @since 1.0.0
     *
     * @example Existence Checking
     * ```php
     * if (isset($user->email)) {
     *     sendEmail($user->email);
     * }
     * ```
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Force reload of table column information from database
     *
     * Performs fresh DESCRIBE query on the model's table and updates the
     * Cargo cache with current column information. Useful after schema
     * changes or when cache becomes stale.
     *
     * ## Cache Management
     * - Clears existing cached columns for this table
     * - Executes DESCRIBE query with QueryTracker monitoring
     * - Updates Cargo cache with fresh column list
     * - Provides foundation for accurate introspection
     *
     * @return void
     * @throws RuntimeException If DESCRIBE query fails or table doesn't exist
     * @since 1.0.0
     *
     * @example Manual Cache Refresh
     * ```php
     * // After schema migration
     * $user = new User();
     * $user->reloadColumns(); // Fresh column discovery
     * ```
     *
     * @see Cargo For caching system
     * @see QueryTracker For query performance monitoring
     */
    public function reloadColumns(): void
    {
        $table = $this->table();
        $cacheKey = 'model.columns.' . $table;

        try {
            $sql = "DESCRIBE `$table`";                    // ← EXTRACT SQL TO VARIABLE

            QueryTracker::start();
            $stmt = $this->db->query($sql);
            QueryTracker::stop($sql, []);

            $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
            Cargo::on($cacheKey)->set($cacheKey, $columns);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to reload columns for `$table`: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Get the table name for this model with automatic generation fallback
     *
     * Returns the configured table name or generates one from the class name.
     * Generation rule: lowercase class name + 's' suffix.
     * Provides consistent table naming across the application.
     *
     * @return string Database table name for this model
     * @since 1.0.0
     *
     * @example Table Name Resolution
     * ```php
     * class User extends Model {} // table: 'users'
     * class BlogPost extends Model {} // table: 'blogposts'
     *
     * class User extends Model {
     *     protected string $table = 'custom_users'; // table: 'custom_users'
     * }
     * ```
     */
    protected function table(): string
    {
        return $this->table ?: strtolower(static::class) . 's';
    }

    /**
     * Include soft-deleted records in subsequent queries
     *
     * Modifies the model state to include records with deleted_at timestamps
     * in query results. Only affects the current model instance and resets
     * after query execution.
     *
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Including Deleted Records
     * ```php
     * // Find all users including soft-deleted
     * $allUsers = (new User())->withTrashed()->findAll();
     *
     * // Chain with other query methods
     * $trashedActive = (new User())
     *     ->withTrashed()
     *     ->where('active', 1)
     *     ->getAsModels();
     * ```
     *
     * @see onlyTrashed() To get only soft-deleted records
     */
    public function withTrashed(): static
    {
        $this->includeSoftDeleted = true;
        return $this;
    }

    /**
     * Get only soft-deleted records from the database
     *
     * Returns array of records that have been soft-deleted (deleted_at IS NOT NULL).
     * Provides direct access to deleted records without affecting model query state.
     *
     * @return array<array<string, mixed>> Array of deleted record data
     * @since 1.0.0
     *
     * @example Retrieving Deleted Records
     * ```php
     * $deletedUsers = (new User())->onlyTrashed();
     * foreach ($deletedUsers as $userData) {
     *     echo "Deleted user: " . $userData['name'];
     * }
     * ```
     *
     * @see withTrashed() To include deleted records in normal queries
     * @see restore() To restore soft-deleted records
     */
    public function onlyTrashed(): array
    {
        return $this->query()->whereRaw('deleted_at IS NOT NULL')->get($this->db);
    }

    /**
     * Execute current query and return results as model instances
     *
     * Runs the built query (or basic select if no query builder) and converts
     * each result row into a new model instance. Provides object-oriented
     * access to result data with all model functionality.
     *
     * @return array<static> Array of model instances from query results
     * @since 1.0.0
     *
     * @example Getting Model Objects
     * ```php
     * $activeUsers = (new User())
     *     ->where('active', 1)
     *     ->orderBy('name')
     *     ->getAsModels();
     *
     * foreach ($activeUsers as $user) {
     *     echo $user->name; // Object property access
     *     $user->last_login = date('Y-m-d H:i:s');
     *     $user->save(); // Model methods available
     * }
     * ```
     */
    public function getAsModels(): array
    {
        $rows = ($this->queryBuilder ?? $this->query())->get($this->db);
        return array_map(fn($row) => (new static([], $this->db))->fill($row), $rows);
    }

    /**
     * Execute query with pagination and return raw data results
     *
     * Performs paginated query execution returning raw associative arrays.
     * Provides pagination metadata through Paginator object including total
     * count, page information, and navigation helpers.
     *
     * @param int $perPage Number of records per page
     * @param int $page Current page number (1-based)
     * @return Paginator Pagination results with raw data arrays
     * @since 1.0.0
     *
     * @example Basic Pagination
     * ```php
     * $paginator = (new User())
     *     ->where('active', 1)
     *     ->paginate(20, 2); // 20 per page, page 2
     *
     * $users = $paginator->getData(); // Raw arrays
     * $total = $paginator->getTotal();
     * $hasMore = $paginator->hasMorePages();
     * ```
     *
     * @see paginateAsModels() For model object results
     * @see Paginator For pagination functionality
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        [$rows, $total, $pages] = ($this->queryBuilder ?? $this->query())->paginate($perPage, $page, $this->db);
        return new Paginator($rows, $total, $perPage, $page, $pages);
    }

    /**
     * Execute query with pagination and return model instance results
     *
     * Performs paginated query execution and converts each result row into
     * a model instance. Combines pagination functionality with object-oriented
     * result access for comprehensive data handling.
     *
     * @param int $perPage Number of records per page
     * @param int $page Current page number (1-based)
     * @return Paginator Pagination results with model instances
     * @since 1.0.0
     *
     * @example Model Instance Pagination
     * ```php
     * $paginator = (new User())
     *     ->where('active', 1)
     *     ->paginateAsModels(15, 1);
     *
     * foreach ($paginator->getData() as $user) {
     *     echo $user->name; // Model object access
     *     $user->updateLastSeen(); // Model methods available
     * }
     * ```
     *
     * @see paginate() For raw data results
     * @see findPageAsModels() Alias method
     */
    public function paginateAsModels(int $perPage = 15, int $page = 1): Paginator
    {
        [$rows, $total, $pages] = ($this->queryBuilder ?? $this->query())->paginate($perPage, $page, $this->db);
        $models = array_map(fn($row) => (new static([], $this->db))->fill($row), $rows);
        return new Paginator($models, $total, $perPage, $page, $pages);
    }

    /**
     * Alias for paginateAsModels() with consistent naming
     *
     * Provides alternative method name for pagination with model instances.
     * Identical functionality to paginateAsModels() with more explicit naming.
     *
     * @param int $perPage Number of records per page
     * @param int $page Current page number (1-based)
     * @return Paginator Pagination results with model instances
     * @since 1.0.0
     *
     * @see paginateAsModels() Primary implementation
     */
    public function findPageAsModels(int $perPage = 15, int $page = 1): Paginator
    {
        return $this->paginateAsModels($perPage, $page);
    }

    /**
     * Alias for paginate() with consistent naming
     *
     * Provides alternative method name for pagination with raw data arrays.
     * Identical functionality to paginate() with more explicit naming.
     *
     * @param int $perPage Number of records per page
     * @param int $page Current page number (1-based)
     * @return Paginator Pagination results with raw data
     * @since 1.0.0
     *
     * @see paginate() Primary implementation
     */
    public function findPage(int $perPage = 15, int $page = 1): Paginator
    {
        return $this->paginate($perPage, $page);
    }

    /**
     * Generate URL for next page in pagination sequence
     *
     * Creates properly formatted URL for the next page with pagination parameters
     * and optional additional query parameters. Returns null if already on last page.
     *
     * @param int $perPage Items per page for URL parameters
     * @param int $currentPage Current page number for calculation
     * @param int $totalPages Total available pages for boundary checking
     * @param string $baseUrl Base URL without query parameters
     * @param array<string, mixed> $extraParams Additional query parameters to include
     * @return string|null Next page URL or null if on last page
     * @since 1.0.0
     *
     * @example Navigation URL Generation
     * ```php
     * $nextUrl = $user->nextPageUrl(20, 2, 5, '/users', ['status' => 'active']);
     * // Result: '/users?page=3&limit=20&status=active'
     * ```
     */
    public function nextPageUrl(int $perPage, int $currentPage, int $totalPages, string $baseUrl, array $extraParams = []): ?string
    {
        if ($currentPage >= $totalPages) return null;
        $nextPage = $currentPage + 1;
        $params = array_merge($extraParams, ['page' => $nextPage, 'limit' => $perPage]);
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Generate URL for previous page in pagination sequence
     *
     * Creates properly formatted URL for the previous page with pagination parameters
     * and optional additional query parameters. Returns null if already on first page.
     *
     * @param int $perPage Items per page for URL parameters
     * @param int $currentPage Current page number for calculation
     * @param string $baseUrl Base URL without query parameters
     * @param array<string, mixed> $extraParams Additional query parameters to include
     * @return string|null Previous page URL or null if on first page
     * @since 1.0.0
     *
     * @example Navigation URL Generation
     * ```php
     * $prevUrl = $user->prevPageUrl(20, 3, '/users', ['status' => 'active']);
     * // Result: '/users?page=2&limit=20&status=active'
     * ```
     */
    public function prevPageUrl(int $perPage, int $currentPage, string $baseUrl, array $extraParams = []): ?string
    {
        if ($currentPage <= 1) return null;
        $prevPage = $currentPage - 1;
        $params = array_merge($extraParams, ['page' => $prevPage, 'limit' => $perPage]);
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Create new QueryBuilder instance with automatic soft delete filtering
     *
     * Initializes fresh QueryBuilder for the model's table with automatic
     * soft delete exclusion if enabled. Provides foundation for all query
     * operations with consistent filtering behavior.
     *
     * ## Automatic Filtering
     * - Applies 'deleted_at IS NULL' condition if soft deletes enabled
     * - Respects $includeSoftDeleted flag from withTrashed() method
     * - Provides clean base for additional query conditions
     *
     * @return QueryBuilder Fresh query builder instance for this model
     * @since 1.0.0
     *
     * @example Manual Query Building
     * ```php
     * $qb = (new User())->query()
     *     ->select(['name', 'email'])
     *     ->where('active', 1)
     *     ->orderBy('created_at', 'DESC');
     *
     * $results = $qb->get($db);
     * ```
     *
     * @see QueryBuilder For query building functionality
     */
    public function query(): QueryBuilder
    {
        $qb = new QueryBuilder($this->table());
        if ($this->softDelete && !$this->includeSoftDeleted) {
            $qb->whereRaw('deleted_at IS NULL');
        }
        return $qb;
    }

    /**
     * Find single record by primary key and populate current model instance
     *
     * Loads record data into the current model instance and updates original
     * state for change tracking. Returns the model instance for method chaining
     * or null if record not found.
     *
     * ## State Management
     * - Populates $data array with record fields
     * - Updates $original array for change tracking
     * - Enables immediate save() operations after modifications
     * - Respects soft delete settings
     *
     * @param mixed $id Primary key value to search for
     * @return static|null Current model instance if found, null if not found
     * @since 1.0.0
     *
     * @example Single Record Loading
     * ```php
     * $user = (new User())->find(123);
     * if ($user) {
     *     $user->name = 'Updated Name';
     *     $user->save(); // Updates existing record
     * }
     * ```
     *
     * @example Method Chaining
     * ```php
     * $user = (new User())->find(123)?->updateLastLogin();
     * ```
     */
    public function find(mixed $id): ?static
    {
        $row = $this->query()->where($this->primaryKey, $id)->first($this->db);
        if (!$row) return null;
        $this->fill($row);
        $this->original = $this->data;
        return $this;
    }

    /**
     * Retrieve all records from table as array of model instances
     *
     * Loads all non-soft-deleted records from the model's table and returns
     * them as an array of new model instances. Each instance is fully populated
     * and ready for individual operations.
     *
     * @return array<static> Array of model instances for all records
     * @since 1.0.0
     *
     * @example Loading All Records
     * ```php
     * $allUsers = (new User())->findAll();
     * foreach ($allUsers as $user) {
     *     echo $user->name . "\n";
     *     $user->updateLastSeen();
     * }
     * ```
     *
     * @example With Soft Delete Context
     * ```php
     * $allUsers = (new User())->withTrashed()->findAll(); // Includes deleted
     * $activeOnly = (new User())->findAll(); // Excludes deleted (default)
     * ```
     */
    public function findAll(): array
    {
        $rows = $this->query()->get($this->db);
        return array_map(fn($row) => (new static([], $this->db))->fill($row), $rows);
    }

    /**
     * Add WHERE condition to query builder with flexible parameter support
     *
     * Supports both standard where conditions and raw SQL strings. Initializes
     * query builder if needed and preserves query state for method chaining.
     * Handles multiple parameter formats for maximum flexibility.
     *
     * ## Parameter Formats
     * - Single string: Raw SQL condition
     * - Multiple parameters: Standard where($column, $operator, $value) or where($column, $value)
     *
     * @param mixed ...$args Variable arguments for WHERE condition
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Standard WHERE Conditions
     * ```php
     * $users = (new User())
     *     ->where('active', 1)
     *     ->where('age', '>=', 18)
     *     ->where('status', '!=', 'banned')
     *     ->getAsModels();
     * ```
     *
     * @example Raw SQL Conditions
     * ```php
     * $users = (new User())
     *     ->where('DATE(created_at) = CURDATE()')
     *     ->where('email LIKE "%@company.com"')
     *     ->getAsModels();
     * ```
     *
     * @see QueryBuilder::where() For underlying implementation
     * @see andWhere() For explicit AND conditions
     * @see orWhere() For OR conditions
     */
    public function where(...$args): static
    {
        /*
        $qb = $this->query();

        if (count($args) === 1 && is_string($args[0])) {
            $this->queryBuilder = $qb->whereRaw($args[0]);
        } else {
            $this->queryBuilder = $qb->where(...$args);
        }
        */

        $qb = $this->queryBuilder ?? $this->query();

        $this->queryBuilder = (count($args) === 1 && is_string($args[0]))
            ? $qb->whereRaw($args[0])
            : $qb->where(...$args);

        return $this;
    }

    /**
     * Alias for where() method providing explicit AND condition semantics
     *
     * Functionally identical to where() but provides clearer intent when
     * building complex queries with multiple conditions. All WHERE conditions
     * are combined with AND by default.
     *
     * @param mixed ...$args Variable arguments for WHERE condition
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Explicit AND Conditions
     * ```php
     * $users = (new User())
     *     ->where('active', 1)
     *     ->andWhere('verified', 1)
     *     ->andWhere('age', '>=', 18)
     *     ->getAsModels();
     * ```
     *
     * @see where() Primary implementation
     */
    public function andWhere(...$args): static
    {
        return $this->where(...$args);
    }

    /**
     * Clear current query builder state for fresh query building
     *
     * Resets the query builder to null, allowing subsequent query methods
     * to start with a clean slate. Useful when reusing model instances
     * for multiple different queries.
     *
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Query State Reset
     * ```php
     * $userModel = new User();
     *
     * // First query
     * $activeUsers = $userModel->where('active', 1)->getAsModels();
     *
     * // Reset and build different query
     * $inactiveUsers = $userModel
     *     ->resetQueryBuilder()
     *     ->where('active', 0)
     *     ->getAsModels();
     * ```
     */
    public function resetQueryBuilder(): static
    {
        $this->queryBuilder = null;
        return $this;
    }

    /**
     * Add OR WHERE condition to query builder
     *
     * Adds OR condition to existing WHERE clause. Requires existing query
     * builder state with previous conditions to combine with OR logic.
     * Maintains query state for continued method chaining.
     *
     * @param mixed ...$args Variable arguments for OR WHERE condition
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example OR Conditions
     * ```php
     * $users = (new User())
     *     ->where('role', 'admin')
     *     ->orWhere('role', 'manager')
     *     ->orWhere('special_access', 1)
     *     ->getAsModels();
     * ```
     *
     * @example Complex Logic
     * ```php
     * // (active = 1 AND role = 'user') OR (role = 'admin')
     * $users = (new User())
     *     ->where('active', 1)
     *     ->where('role', 'user')
     *     ->orWhere('role', 'admin')
     *     ->getAsModels();
     * ```
     *
     * @see QueryBuilder::orWhere() For underlying implementation
     */
    public function orWhere(...$args): static
    {
        // $this->queryBuilder = $this->query()->orWhere(...$args);
        $qb = $this->queryBuilder ?? $this->query();
        $this->queryBuilder = $qb->orWhere(...$args);
        return $this;
    }

    /**
     * Add ORDER BY clause to query with column and direction
     *
     * Sorts query results by specified column and direction. Preserves query
     * state and allows multiple orderBy calls for complex sorting requirements.
     *
     * @param string $column Database column name to sort by
     * @param string $direction Sort direction: 'ASC' or 'DESC' (default: 'ASC')
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Single Column Sorting
     * ```php
     * $users = (new User())
     *     ->orderBy('name', 'ASC')
     *     ->getAsModels();
     * ```
     *
     * @example Multiple Column Sorting
     * ```php
     * $users = (new User())
     *     ->orderBy('priority', 'DESC')
     *     ->orderBy('created_at', 'ASC')
     *     ->getAsModels();
     * ```
     *
     * @see orderByRaw() For raw SQL ordering
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->queryBuilder = ($this->queryBuilder ?? $this->query())->orderBy($column, $direction);
        return $this;
    }

    /**
     * Add raw SQL ORDER BY clause to query
     *
     * Allows complex ordering with raw SQL expressions, functions, and
     * calculations. Provides maximum flexibility for advanced sorting
     * requirements beyond simple column ordering.
     *
     * @param string $clause Raw SQL ORDER BY clause (without ORDER BY keyword)
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Function-Based Ordering
     * ```php
     * $users = (new User())
     *     ->orderByRaw('RAND()')  // Random order
     *     ->limit(10)
     *     ->getAsModels();
     * ```
     *
     * @example Complex Expressions
     * ```php
     * $users = (new User())
     *     ->orderByRaw('CASE WHEN priority IS NULL THEN 1 ELSE 0 END, priority DESC')
     *     ->getAsModels();
     * ```
     *
     * @see orderBy() For standard column ordering
     */
    public function orderByRaw(string $clause): static
    {
        $this->queryBuilder = ($this->queryBuilder ?? $this->query());
        $this->queryBuilder->orderByRaw($clause);
        return $this;
    }

    /**
     * Add LIMIT clause to restrict number of returned records
     *
     * Limits query results to specified number of records. Commonly used
     * with orderBy() for top-N queries or with offset() for pagination.
     *
     * @param int $limit Maximum number of records to return
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Top Records
     * ```php
     * $topUsers = (new User())
     *     ->orderBy('points', 'DESC')
     *     ->limit(10)
     *     ->getAsModels();
     * ```
     *
     * @example With Pagination
     * ```php
     * $page2Users = (new User())
     *     ->orderBy('id')
     *     ->limit(20)
     *     ->offset(20)  // Skip first 20 records
     *     ->getAsModels();
     * ```
     *
     * @see offset() For pagination support
     * @see paginate() For full pagination functionality
     */
    public function limit(int $limit): static
    {
        $this->queryBuilder = ($this->queryBuilder ?? $this->query())->limit($limit);
        return $this;
    }

    /**
     * Add OFFSET clause to skip records at beginning of result set
     *
     * Skips specified number of records before returning results. Primarily
     * used with limit() for manual pagination implementation. Results depend
     * on consistent ordering for predictable pagination.
     *
     * @param int $offset Number of records to skip
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Manual Pagination
     * ```php
     * // Page 3 with 15 records per page
     * $users = (new User())
     *     ->orderBy('id')
     *     ->limit(15)
     *     ->offset(30)  // Skip first 30 records (2 pages × 15)
     *     ->getAsModels();
     * ```
     *
     * @see limit() For result count restriction
     * @see paginate() For automated pagination
     */
    public function offset(int $offset): static
    {
        $this->queryBuilder = ($this->queryBuilder ?? $this->query())->offset($offset);
        return $this;
    }

    /**
     * Execute query and return first matching record as model instance
     *
     * Returns the first record matching current query conditions as a populated
     * model instance. Returns null if no records match the query conditions.
     * Automatically applies LIMIT 1 for performance optimization.
     *
     * @return static|null Model instance for first matching record or null if none found
     * @since 1.0.0
     *
     * @example First Matching Record
     * ```php
     * $admin = (new User())
     *     ->where('role', 'admin')
     *     ->where('active', 1)
     *     ->first();
     *
     * if ($admin) {
     *     echo "Admin found: " . $admin->name;
     * }
     * ```
     *
     * @example With Ordering
     * ```php
     * $newest = (new User())
     *     ->orderBy('created_at', 'DESC')
     *     ->first(); // Most recently created user
     * ```
     */
    public function first(): ?static
    {
        $row = ($this->queryBuilder ?? $this->query())->first($this->db);
        if (!$row) return null;
        return (new static([], $this->db))->fill($row);
    }

    /**
     * Populate model attributes from associative array with type casting
     *
     * Fills model data array with provided attributes, applying configured
     * type casting to each value. Enables mass assignment of attributes
     * with proper type conversion and validation.
     *
     * @param array<string, mixed> $attrs Associative array of attribute values
     * @return static Current model instance for method chaining
     * @since 1.0.0
     *
     * @example Mass Assignment
     * ```php
     * $user = (new User())->fill([
     *     'name' => 'John Doe',
     *     'email' => 'john@example.com',
     *     'active' => '1',  // Casted to boolean if configured
     *     'age' => '25'     // Casted to integer if configured
     * ]);
     * ```
     *
     * @example From Form Data
     * ```php
     * $user = (new User())->fill($_POST);
     * $user->save();
     * ```
     *
     * @see castAttribute() For type casting implementation
     */
    public function fill(array $attrs): static
    {
        foreach ($attrs as $k => $v) {
            // $this->data[$k] = $v;
            $this->data[$k] = $this->castAttribute($k, $v);
        }
        return $this;
    }

    /**
     * Persist model changes to database with validation and timestamp management
     *
     * Validates model data, manages timestamps, and performs INSERT or UPDATE
     * based on model state. Returns true on success, false on validation
     * failure or database error.
     *
     * ## Save Process
     * 1. Runs validation via isValid() method
     * 2. Updates timestamp fields if enabled
     * 3. Determines INSERT vs UPDATE based on exists()
     * 4. Executes appropriate database operation
     * 5. Updates model state on success
     *
     * ## Timestamp Management
     * - Sets updated_at on all save operations
     * - Sets created_at only on new record creation
     * - Uses current datetime in Y-m-d H:i:s format
     *
     * @return bool True if save successful, false if validation failed or database error
     * @since 1.0.0
     *
     * @example New Record Creation
     * ```php
     * $user = new User(['name' => 'John', 'email' => 'john@example.com']);
     * if ($user->save()) {
     *     echo "User created with ID: " . $user->id;
     * } else {
     *     print_r($user->getErrors());
     * }
     * ```
     *
     * @example Updating Existing Record
     * ```php
     * $user = (new User())->find(123);
     * $user->name = 'Updated Name';
     * $user->save(); // Updates existing record
     * ```
     *
     * @see isValid() For validation process
     * @see insertRow() For new record creation
     * @see updateRow() For existing record updates
     */
    public function save(): bool
    {
        if (!$this->isValid()) return false;

        $now = date('Y-m-d H:i:s');
        if ($this->timestamps) {
            $this->data['updated_at'] = $now;
            if (!$this->exists()) $this->data['created_at'] = $now;
        }

        return $this->exists() ? $this->updateRow() : $this->insertRow();
    }

    /**
     * Insert new record into database with column filtering and tracking
     *
     * Performs INSERT operation for new records with automatic column validation,
     * query tracking, and primary key assignment. Filters data to only include
     * columns that exist in the database table.
     *
     * ## Insert Process
     * 1. Gets cached table columns from Cargo
     * 2. Filters model data to only valid columns
     * 3. Builds parameterized INSERT SQL
     * 4. Executes with QueryTracker monitoring
     * 5. Sets primary key from lastInsertId()
     * 6. Updates original state for change tracking
     * 7. Records database errors if operation fails
     *
     * @return bool True if insert successful, false on database error
     * @throws RuntimeException If column cache is unavailable
     * @since 1.0.0
     *
     * @see QueryTracker For query performance monitoring
     * @see Cargo For column caching
     */
    protected function insertRow(): bool
    {
        // $fields = array_keys($this->data);
        // $placeholders = implode(',', array_fill(0, count($fields), '?'));
        // $sql = "INSERT INTO {$this->table()} (" . implode(',', $fields) . ") VALUES ($placeholders)";
        // $ok = $this->db->prepare($sql)->execute(array_values($this->data));

        $cacheKey = 'model.columns.' . $this->table();
        $columns = Cargo::on($cacheKey)->get($cacheKey);

        $filtered = array_filter(
            $this->data,
            fn($key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_KEY
        );

        $placeholders = implode(',', array_fill(0, count($filtered), '?'));
        $sql = "INSERT INTO {$this->table()} (" . implode(',', array_keys($filtered)) . ") VALUES ($placeholders)";

        QueryTracker::start();
        $ok = $this->db->prepare($sql)->execute(array_values($filtered));
        QueryTracker::stop($sql, array_values($filtered));

        if ($ok) {
            $this->data[$this->primaryKey] = (int)$this->db->lastInsertId();
            $this->original = $this->data;
        } else {
            $this->errors['db'][] = 'Insert failed';
        }

        return $ok;
    }

    /**
     * Update existing database record with only changed fields
     *
     * Performs UPDATE operation using only dirty (changed) fields for
     * performance optimization. Uses change tracking to minimize database
     * operations and avoid unnecessary updates.
     *
     * ## Update Process
     * 1. Gets dirty fields via getDirty()
     * 2. Returns true immediately if no changes
     * 3. Builds UPDATE SQL with only changed fields
     * 4. Executes with WHERE primary key condition
     * 5. Executes with QueryTracker monitoring
     * 6. Updates original state on success
     * 7. Records database errors if operation fails
     *
     * @return bool True if update successful or no changes needed, false on database error
     * @since 1.0.0
     *
     * @see getDirty() For change detection
     * @see QueryTracker For query performance monitoring
     */
    protected function updateRow(): bool
    {
        $dirty = $this->getDirty();
        if (!$dirty) return true;

        $set = implode(',', array_map(fn($f) => "$f = ?", array_keys($dirty)));
        $sql = "UPDATE {$this->table()} SET $set WHERE " . $this->primaryKey . " = ?";
        $vals = array_values($dirty);
        $vals[] = $this->data[$this->primaryKey];

        QueryTracker::start();
        $ok = $this->db->prepare($sql)->execute($vals);
        QueryTracker::stop($sql, $vals);

        if ($ok) $this->original = $this->data;
        else $this->errors['db'][] = 'Update failed';

        return $ok;
    }

    /**
     * Remove record using soft delete or hard delete based on model configuration
     *
     * Removes record from active dataset using either soft delete (sets deleted_at)
     * or hard delete (removes from database) based on $softDelete property setting.
     * Returns false if model doesn't have primary key value.
     *
     * ## Soft Delete Behavior
     * - Sets deleted_at timestamp to current datetime
     * - Preserves record in database for recovery
     * - Automatically excluded from future queries
     * - Enables restore() functionality
     *
     * ## Hard Delete Behavior
     * - Permanently removes record from database
     * - Cannot be recovered after deletion
     * - Used when $softDelete = false
     *
     * @return bool True if deletion successful, false if no primary key or database error
     * @since 1.0.0
     *
     * @example Soft Delete (Default)
     * ```php
     * $user = (new User())->find(123);
     * $user->delete(); // Sets deleted_at timestamp
     *
     * // User still exists but excluded from queries
     * $found = (new User())->find(123); // Returns null
     * $withDeleted = (new User())->withTrashed()->find(123); // Returns user
     * ```
     *
     * @example Hard Delete
     * ```php
     * class TemporaryModel extends Model {
     *     protected bool $softDelete = false;
     * }
     *
     * $temp = (new TemporaryModel())->find(123);
     * $temp->delete(); // Permanently removes from database
     * ```
     *
     * @see forceDelete() For permanent deletion regardless of soft delete setting
     * @see restore() For recovering soft-deleted records
     */
    public function delete(): bool
    {
        if (!isset($this->data[$this->primaryKey])) return false;

        if ($this->softDelete) {
            $this->data['deleted_at'] = date('Y-m-d H:i:s');
            return $this->save();
        }

        return $this->forceDelete();
    }

    /**
     * Restore soft-deleted record by clearing deleted_at timestamp
     *
     * Recovers previously soft-deleted record by setting deleted_at to null
     * and recording restoration timestamp. Only works with soft delete enabled
     * models that have been previously deleted.
     *
     * ## Restoration Process
     * - Sets deleted_at field to null
     * - Sets restored_at timestamp to current datetime
     * - Record becomes visible in normal queries again
     * - Triggers save() operation to persist changes
     *
     * @return bool True if restoration successful, false if not soft delete model or no primary key
     * @since 1.0.0
     *
     * @example Record Restoration
     * ```php
     * // Find and restore deleted user
     * $user = (new User())->withTrashed()->find(123);
     * if ($user && $user->restore()) {
     *     echo "User restored successfully";
     * }
     *
     * // User now appears in normal queries
     * $restored = (new User())->find(123); // Returns user object
     * ```
     *
     * @example Bulk Restoration
     * ```php
     * $deletedUsers = (new User())->onlyTrashed();
     * foreach ($deletedUsers as $userData) {
     *     $user = (new User())->fill($userData);
     *     $user->restore();
     * }
     * ```
     *
     * @see delete() For soft delete process
     * @see withTrashed() For querying deleted records
     */
    public function restore(): bool
    {
        if (!$this->softDelete || !isset($this->data[$this->primaryKey])) return false;

        $this->data['deleted_at'] = null;
        $this->data['restored_at'] = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Permanently delete record from database regardless of soft delete setting
     *
     * Performs hard DELETE operation that permanently removes record from database.
     * Bypasses soft delete configuration and cannot be recovered. Use with caution
     * as this operation is irreversible.
     *
     * @return bool True if permanent deletion successful, false if no primary key or database error
     * @since 1.0.0
     *
     * @example Permanent Deletion
     * ```php
     * $user = (new User())->find(123);
     * $user->forceDelete(); // Permanently removes from database
     *
     * // Record no longer exists
     * $gone = (new User())->withTrashed()->find(123); // Returns null
     * ```
     *
     * @example Cleanup Deleted Records
     * ```php
     * // Permanently remove old soft-deleted records
     * $oldDeleted = (new User())->onlyTrashed();
     * foreach ($oldDeleted as $userData) {
     *     if (strtotime($userData['deleted_at']) < strtotime('-30 days')) {
     *         $user = (new User())->fill($userData);
     *         $user->forceDelete(); // Permanent cleanup
     *     }
     * }
     * ```
     *
     * @see delete() For soft delete option
     */
    public function forceDelete(): bool
    {
        $sql = "DELETE FROM {$this->table()} WHERE " . $this->primaryKey . " = ?";
        $ok = $this->db->prepare($sql)->execute([$this->data[$this->primaryKey]]);
        if (!$ok) $this->errors['db'][] = 'Delete failed';
        return $ok;
    }

    /**
     * Check if model represents existing database record
     *
     * Determines if current model instance represents an existing database
     * record by checking for non-empty primary key value. Used internally
     * by save() to decide between INSERT and UPDATE operations.
     *
     * @return bool True if primary key has value (existing record), false if empty (new record)
     * @since 1.0.0
     */
    public function exists(): bool
    {
        return !empty($this->data[$this->primaryKey]);
    }

    /**
     * Check if model has unsaved changes compared to original database state
     *
     * Compares current model data with original values to detect modifications.
     * Can check entire model or specific field for changes. Returns true if
     * any differences are found between current and original state.
     *
     * @param string|null $field Specific field to check, or null for entire model
     * @return bool True if model/field has unsaved changes, false if unchanged
     * @since 1.0.0
     *
     * @example Check Entire Model
     * ```php
     * $user = (new User())->find(123);
     * $user->name = 'New Name';
     *
     * if ($user->isDirty()) {
     *     echo "User has unsaved changes";
     *     $user->save();
     * }
     * ```
     *
     * @example Check Specific Field
     * ```php
     * $user = (new User())->find(123);
     * $user->name = 'New Name';
     * $user->email = 'new@email.com';
     *
     * if ($user->isDirty('name')) {
     *     echo "Name was changed";
     * }
     * ```
     *
     * @see getDirty() For retrieving changed fields and values
     */
    public function isDirty(?string $field = null): bool
    {
        return $field
            ? ($this->data[$field] ?? null) !== ($this->original[$field] ?? null)
            : $this->data !== $this->original;
    }

    /**
     * Get associative array of fields that have changed from original values
     *
     * Returns key-value pairs of only the fields that differ between current
     * model data and original database state. Useful for partial updates
     * and change logging.
     *
     * @return array<string, mixed> Changed fields with their current values
     * @since 1.0.0
     *
     * @example Track Changes
     * ```php
     * $user = (new User())->find(123);
     * $user->name = 'New Name';
     * $user->email = 'new@email.com';
     *
     * $changes = $user->getDirty();
     * // ['name' => 'New Name', 'email' => 'new@email.com']
     *
     * foreach ($changes as $field => $newValue) {
     *     echo "$field changed to: $newValue\n";
     * }
     * ```
     *
     * @example Conditional Updates
     * ```php
     * $user = (new User())->find(123);
     * $user->fill($_POST);
     *
     * $changes = $user->getDirty();
     * if (!empty($changes)) {
     *     auditLog('User updated', $changes);
     *     $user->save();
     * }
     * ```
     *
     * @see isDirty() For checking if changes exist
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->data as $k => $v) {
            if (($this->original[$k] ?? null) !== $v) $dirty[$k] = $v;
        }
        return $dirty;
    }

    /**
     * Define validation rules for model attributes
     *
     * Override this method in child classes to specify validation rules
     * for model attributes. Rules are used by isValid() method through
     * the Validator system for data validation before save operations.
     *
     * @return array<string, string> Attribute validation rules
     * @since 1.0.0
     *
     * @example Validation Rules Definition
     * ```php
     * class User extends Model
     * {
     *     public function rules(): array
     *     {
     *         return [
     *             'email' => 'required|email|unique:users,email',
     *             'name' => 'required|min:2|max:100',
     *             'age' => 'integer|min:0|max:150',
     *             'password' => 'required|min:8'
     *         ];
     *     }
     * }
     * ```
     *
     * @see isValid() For validation execution
     * @see Validator For validation rule syntax
     */
    public function rules(): array
    {
        return [];
    }


    /**
     * Validate model data against defined rules and populate error array
     *
     * Runs model data through Validator system using rules from rules() method.
     * Clears previous errors and populates $errors array with any validation
     * failures. Returns true only if all validations pass.
     *
     * ## Validation Process
     * 1. Clears existing error array
     * 2. Gets validation rules from rules() method
     * 3. Runs Validator::validate() on current data
     * 4. Populates $errors array with failures
     * 5. Returns true if no errors found
     *
     * @return bool True if all validations pass, false if any validation fails
     * @since 1.0.0
     *
     * @example Pre-Save Validation
     * ```php
     * $user = new User(['email' => 'invalid-email']);
     *
     * if ($user->isValid()) {
     *     $user->save();
     * } else {
     *     foreach ($user->getErrors() as $field => $messages) {
     *         echo "$field: " . implode(', ', $messages) . "\n";
     *     }
     * }
     * ```
     *
     * @see rules() For validation rule definition
     * @see getErrors() For accessing validation errors
     * @see Validator::validate() For validation implementation
     */
    public function isValid(): bool
    {
        $this->errors = Validator::validate($this->data, $this->rules());
        return empty($this->errors);
    }

    /**
     * Check if model has any validation or database errors
     *
     * Returns true if the errors array contains any error messages from
     * validation failures or database operation errors. Useful for conditional
     * logic based on model error state.
     *
     * @return bool True if errors exist, false if error-free
     * @since 1.0.0
     *
     * @example Error State Checking
     * ```php
     * $user = new User(['email' => 'invalid']);
     * $user->save();
     *
     * if ($user->hasErrors()) {
     *     displayErrorMessages($user->getErrors());
     * } else {
     *     redirectToSuccess();
     * }
     * ```
     *
     * @see getErrors() For retrieving error details
     * @see isValid() For validation process
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Retrieve all validation and database errors as associative array
     *
     * Returns complete error array with field names as keys and error message
     * arrays as values. Includes both validation errors from isValid() and
     * database operation errors from save/delete operations.
     *
     * @return array<string, array<string>> Error messages grouped by field/category
     * @since 1.0.0
     *
     * @example Error Display
     * ```php
     * $user = new User();
     * $user->save();
     *
     * foreach ($user->getErrors() as $field => $messages) {
     *     echo "Field '$field': " . implode(', ', $messages) . "\n";
     * }
     * ```
     *
     * @example Form Validation
     * ```php
     * $user = new User($_POST);
     * if (!$user->save()) {
     *     $errors = $user->getErrors();
     *     renderForm($_POST, $errors);
     * }
     * ```
     *
     * @see hasErrors() For checking error existence
     * @see isValid() For validation process
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convert model data to associative array
     *
     * Returns the complete model data array with all attribute values.
     * Useful for serialization, API responses, and data transformation
     * operations where array format is required.
     *
     * @return array<string, mixed> All model attributes as associative array
     * @since 1.0.0
     *
     * @example Data Export
     * ```php
     * $user = (new User())->find(123);
     * $userData = $user->toArray();
     *
     * file_put_contents('user.json', json_encode($userData));
     * ```
     *
     * @example API Response
     * ```php
     * $users = (new User())->getAsModels();
     * $response = array_map(fn($user) => $user->toArray(), $users);
     * echo json_encode($response);
     * ```
     *
     * @see asArray() Alias method
     * @see jsonSerialize() For JSON serialization
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Alias for toArray() method providing consistent naming
     *
     * Identical functionality to toArray() with alternative method name
     * for consistency across different coding styles and preferences.
     *
     * @return array<string, mixed> All model attributes as associative array
     * @since 1.0.0
     *
     * @see toArray() Primary implementation
     */
    public function asArray(): array
    {
        return $this->toArray();
    }

    /**
     * JsonSerializable interface implementation for automatic JSON conversion
     *
     * Enables automatic JSON serialization when model is passed to json_encode().
     * Returns model data array for consistent JSON representation of model
     * objects in API responses and data serialization.
     *
     * @return array<string, mixed> Model data for JSON serialization
     * @since 1.0.0
     *
     * @example Automatic JSON Conversion
     * ```php
     * $user = (new User())->find(123);
     * echo json_encode($user); // Automatically calls jsonSerialize()
     * ```
     *
     * @example API Response
     * ```php
     * header('Content-Type: application/json');
     * echo json_encode([
     *     'user' => $user,           // Automatically serialized
     *     'status' => 'success'
     * ]);
     * ```
     *
     * @see JsonSerializable Interface specification
     * @see toArray() For manual array conversion
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Perform bulk UPDATE operation on records matching current query conditions
     *
     * Updates multiple records with provided data based on current QueryBuilder
     * conditions. Applies automatic timestamp management and type casting.
     * Resets query builder after execution and returns number of affected rows.
     *
     * ## Update Process
     * 1. Validates data array is not empty
     * 2. Gets WHERE conditions from current QueryBuilder
     * 3. Adds updated_at timestamp if timestamps enabled
     * 4. Applies type casting to all values
     * 5. Builds and executes UPDATE SQL
     * 6. Returns count of affected rows
     * 7. Resets QueryBuilder state
     *
     * ## Performance Benefits
     * - Single SQL operation vs multiple save() calls
     * - Reduced database round trips
     * - Automatic WHERE clause building from query conditions
     * - Consistent type casting and timestamp management
     *
     * @param array<string, mixed> $data Field values to update across matched records
     * @return int Number of database rows affected by update
     * @since 1.0.0
     *
     * @example Bulk Status Update
     * ```php
     * $affected = (new User())
     *     ->where('active', 0)
     *     ->where('last_login', '<', '2023-01-01')
     *     ->bulkUpdate(['status' => 'inactive', 'archived' => 1]);
     *
     * echo "Updated $affected inactive users";
     * ```
     *
     * @example Conditional Bulk Updates
     * ```php
     * // Promote active users to premium
     * $count = (new User())
     *     ->where('active', 1)
     *     ->where('purchases', '>', 5)
     *     ->bulkUpdate(['tier' => 'premium', 'discount' => 10]);
     * ```
     *
     * @see QueryBuilder::getWhereClause() For WHERE condition extraction
     * @see castAttribute() For value type casting
     */
    public function bulkUpdate(array $data): int
    {
        if (empty($data)) return 0;

        $qb = $this->queryBuilder ?? $this->query();

        $now = date('Y-m-d H:i:s');
        if ($this->timestamps) {
            $data['updated_at'] = $now;
        }

        // Build SET clause
        $setClauses = [];
        $params = [];
        foreach ($data as $field => $value) {
            $setClauses[] = "`$field` = ?";
            $params[] = $this->castAttribute($field, $value);
        }

        // Get WHERE clause from QueryBuilder
        $whereClause = $qb->getWhereClause();
        $sql = "UPDATE `{$this->table()}` SET " . implode(', ', $setClauses);

        if (!empty($whereClause['sql'])) {
            $sql .= " WHERE " . $whereClause['sql'];
            $params = array_merge($params, $whereClause['params']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        // Reset query builder after use
        $this->queryBuilder = null;

        return $stmt->rowCount();
    }

}
