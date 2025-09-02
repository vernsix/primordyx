<?php
/**
 * File: /vendor/vernsix/primordyx/src/QueryBuilder.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/QueryBuilder.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use PDO;

/**
 * Fluent SQL query builder with prepared statements and comprehensive SELECT support
 *
 * Lightweight, chainable SQL query builder specifically designed for SELECT operations with
 * full prepared statement security, flexible WHERE conditions, ordering, pagination, and
 * seamless integration with the Primordyx framework's database architecture.
 *
 * ## Core Features
 * - **Fluent Interface**: Method chaining for readable query construction
 * - **Prepared Statements**: Automatic parameter binding for SQL injection protection
 * - **Flexible WHERE Clauses**: AND/OR conditions with raw SQL support
 * - **Multiple Order Options**: Column-based and raw SQL ordering
 * - **Built-in Pagination**: Complete pagination with count queries
 * - **Column Selection**: Configurable column selection with defaults
 * - **Query Compilation**: Optimized SQL generation with parameter separation
 * - **Framework Integration**: ConnectionManager and QueryTracker compatibility
 *
 * ## Query Building Philosophy
 * The QueryBuilder emphasizes security through prepared statements while maintaining
 * flexibility for complex queries. All user input is automatically parameterized, and
 * raw SQL options are available for advanced use cases that require direct SQL control.
 *
 * ## Parameter Binding Security
 * All values passed through standard methods (where, orWhere) are automatically bound
 * as prepared statement parameters, preventing SQL injection attacks. Raw methods
 * (whereRaw, orWhereRaw, orderByRaw) allow direct SQL for advanced scenarios but
 * require careful handling of user input.
 *
 * ## Method Chaining Architecture
 * The builder maintains internal state through properties that accumulate query components.
 * Each fluent method returns $this to enable chaining, and the final execute methods
 * (get, first, count) compile and run the complete query.
 *
 * @since 1.0.0
 *
 * @example Basic Query Building
 * ```php
 * $users = (new QueryBuilder('users'))
 *     ->select(['name', 'email', 'created_at'])
 *     ->where('active', 1)
 *     ->where('age', '>=', 18)
 *     ->orderBy('name', 'ASC')
 *     ->limit(50)
 *     ->get();
 * ```
 *
 * @example Complex Conditions with OR Logic
 * ```php
 * $results = (new QueryBuilder('orders'))
 *     ->where('status', 'completed')
 *     ->where('total', '>', 100.00)
 *     ->orWhere('priority', 'urgent')
 *     ->orderBy('created_at', 'DESC')
 *     ->get();
 * ```
 *
 * @example Raw SQL for Advanced Queries
 * ```php
 * $reports = (new QueryBuilder('sales'))
 *     ->select(['DATE(created_at) as date', 'SUM(amount) as total'])
 *     ->whereRaw('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
 *     ->orderByRaw('DATE(created_at) DESC')
 *     ->get();
 * ```
 *
 * @example Pagination with Counting
 * ```php
 * $builder = new QueryBuilder('products');
 * $builder->where('category', 'electronics')->where('in_stock', 1);
 *
 * [$products, $total, $pages] = $builder->paginate(20, 1, $db);
 * echo "Page 1 of $pages ($total total products)";
 * ```
 *
 * @see Model For ORM integration examples
 * @see ConnectionManager For database connection management
 * @see QueryTracker For query performance monitoring
 */
class QueryBuilder
{
    /**
     * Database table name for all query operations
     *
     * The target table for SELECT operations, set during construction and used
     * in all SQL compilation. Forms the FROM clause of generated queries and
     * cannot be changed after QueryBuilder instantiation.
     *
     * @var string Target database table name
     * @since 1.0.0
     */
    protected string $table;

    /**
     * Collection of WHERE condition specifications with boolean operators
     *
     * Stores all WHERE clauses as array of [boolean_operator, condition] tuples.
     * Boolean operators include 'AND', 'OR' for query logic. Conditions contain
     * parameterized SQL with placeholders that correspond to $bindings array.
     *
     * Structure: [['AND', 'column = ?'], ['OR', 'other_column > ?'], ...]
     *
     * @var array<array{string, string}> WHERE conditions with boolean operators
     * @since 1.0.0
     */
    protected array $wheres = [];

    /**
     * Collection of ORDER BY clauses for result sorting
     *
     * Stores ORDER BY specifications as array of strings containing column names
     * and sort directions. Supports both standard column ordering and raw SQL
     * expressions for complex sorting requirements.
     *
     * Examples: ['name ASC', 'created_at DESC', 'RAND()', 'CASE priority...']
     *
     * @var array<string> ORDER BY clauses for query sorting
     * @since 1.0.0
     */
    protected array $orders = [];

    /**
     * Maximum number of rows to return from query
     *
     * Controls LIMIT clause in generated SQL. Null means no limit applied.
     * Used for pagination, top-N queries, and performance optimization by
     * restricting result set size at database level.
     *
     * @var int|null Maximum result count or null for unlimited
     * @since 1.0.0
     */
    protected ?int $limit = null;

    /**
     * Number of rows to skip before returning results
     *
     * Controls OFFSET clause for pagination support. Null means no offset.
     * Combined with LIMIT for efficient pagination by skipping specified
     * number of rows before collecting results.
     *
     * @var int|null Number of rows to skip or null for no offset
     * @since 1.0.0
     */
    protected ?int $offset = null;

    /**
     * Parameter values for prepared statement binding
     *
     * Ordered array of values that correspond to placeholders (?) in compiled SQL.
     * Maintains same order as placeholder appearance in WHERE conditions to ensure
     * correct parameter binding during prepared statement execution.
     *
     * @var array Values for prepared statement parameter binding
     * @since 1.0.0
     */
    protected array $bindings = [];

    /**
     * Column specifications for SELECT clause
     *
     * Array of column names or expressions to include in SELECT statement.
     * Defaults to ['*'] for all columns. Supports column aliases, functions,
     * and calculated expressions for flexible result formatting.
     *
     * Examples: ['*'], ['name', 'email'], ['COUNT(*) as total'], ['DATE(created_at) as date']
     *
     * @var array<string> Column names and expressions for SELECT clause
     * @since 1.0.0
     */
    protected array $columns = ['*'];

    /**
     * Initialize QueryBuilder for specified database table
     *
     * Creates new query builder instance targeting the specified table. Sets up
     * default state with all columns selected and no conditions, limits, or ordering.
     * Table name is used in FROM clause and cannot be changed after construction.
     *
     * @param string $table Database table name for query operations
     * @since 1.0.0
     *
     * @example Table Targeting
     * ```php
     * $users = new QueryBuilder('users');
     * $orders = new QueryBuilder('customer_orders');
     * $logs = new QueryBuilder('system_logs');
     * ```
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Specify columns to include in SELECT clause with fluent chaining
     *
     * Replaces default '*' selection with specified column list. Supports column names,
     * aliases, functions, and calculated expressions. Column specifications are used
     * directly in SQL generation, so raw SQL expressions are supported.
     *
     * ## Column Specification Options
     * - Simple columns: ['name', 'email', 'created_at']
     * - Aliased columns: ['name', 'email as user_email']
     * - Functions: ['COUNT(*) as total', 'MAX(created_at) as latest']
     * - Expressions: ['(price * quantity) as subtotal']
     *
     * @param array<string> $columns List of column names, aliases, and expressions
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Basic Column Selection
     * ```php
     * $users = (new QueryBuilder('users'))
     *     ->select(['id', 'name', 'email'])
     *     ->get();
     * ```
     *
     * @example Functions and Aliases
     * ```php
     * $stats = (new QueryBuilder('orders'))
     *     ->select(['COUNT(*) as order_count', 'SUM(total) as revenue'])
     *     ->where('status', 'completed')
     *     ->get();
     * ```
     */
    public function select(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add AND WHERE condition with automatic parameter binding
     *
     * Adds parameterized WHERE clause using AND boolean logic. Supports both
     * two-parameter format (column, value) with default '=' operator and
     * three-parameter format (column, operator, value) for flexible comparisons.
     *
     * ## Parameter Formats
     * - Two parameters: where('status', 'active') → status = ?
     * - Three parameters: where('age', '>=', 18) → age >= ?
     *
     * ## Supported Operators
     * =, !=, <>, <, <=, >, >=, LIKE, NOT LIKE, IN, NOT IN, IS NULL, IS NOT NULL
     *
     * ## Parameter Binding Security
     * All values are automatically bound as prepared statement parameters,
     * preventing SQL injection attacks regardless of input content.
     *
     * @param string $column Database column name for condition
     * @param mixed $operator Comparison operator or value if third parameter omitted
     * @param mixed|null $value Comparison value when operator specified separately
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Basic Equality Conditions
     * ```php
     * $activeUsers = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->where('verified', true)
     *     ->get();
     * ```
     *
     * @example Comparison Operators
     * ```php
     * $adults = (new QueryBuilder('users'))
     *     ->where('age', '>=', 18)
     *     ->where('created_at', '>', '2023-01-01')
     *     ->where('score', '!=', 0)
     *     ->get();
     * ```
     *
     * @example String Matching
     * ```php
     * $emailUsers = (new QueryBuilder('users'))
     *     ->where('email', 'LIKE', '%@company.com')
     *     ->where('name', 'NOT LIKE', 'test%')
     *     ->get();
     * ```
     *
     * @see orWhere() For OR logic conditions
     * @see whereRaw() For raw SQL conditions
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['AND', "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add OR WHERE condition with automatic parameter binding
     *
     * Adds parameterized WHERE clause using OR boolean logic. Identical parameter
     * handling to where() method but combines conditions with OR instead of AND.
     * Useful for alternative matching criteria and flexible search conditions.
     *
     * ## Logical Combination
     * OR conditions provide alternative matching paths:
     * - (condition1 AND condition2) OR condition3
     * - Multiple orWhere() calls create expanded OR logic
     *
     * ## Parameter Security
     * Same automatic parameter binding as where() method ensures SQL injection
     * protection regardless of condition complexity.
     *
     * @param string $column Database column name for condition
     * @param mixed $operator Comparison operator or value if third parameter omitted
     * @param mixed|null $value Comparison value when operator specified separately
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Alternative Matching Criteria
     * ```php
     * $importantUsers = (new QueryBuilder('users'))
     *     ->where('role', 'admin')
     *     ->orWhere('role', 'manager')
     *     ->orWhere('special_access', 1)
     *     ->get();
     * ```
     *
     * @example Search Flexibility
     * ```php
     * $searchResults = (new QueryBuilder('products'))
     *     ->where('name', 'LIKE', '%search%')
     *     ->orWhere('description', 'LIKE', '%search%')
     *     ->orWhere('sku', 'search')
     *     ->get();
     * ```
     *
     * @see where() For AND logic conditions
     * @see orWhereRaw() For raw SQL OR conditions
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = ['OR', "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add raw SQL AND WHERE condition without parameter binding
     *
     * Adds raw SQL condition directly to WHERE clause using AND boolean logic.
     * Provides maximum flexibility for complex conditions, functions, and
     * subqueries that require direct SQL control.
     *
     * ## Security Considerations
     * Raw SQL conditions bypass automatic parameter binding. Ensure proper
     * input sanitization when incorporating user data to prevent SQL injection.
     * Use standard where() method for user-provided values.
     *
     * ## Use Cases
     * - Database functions: 'DATE(created_at) = CURDATE()'
     * - Subqueries: 'user_id IN (SELECT id FROM premium_users)'
     * - Complex expressions: '(price * quantity) > 1000'
     * - JSON operations: 'JSON_EXTRACT(metadata, "$.priority") = "high"'
     *
     * @param string $clause Raw SQL condition clause (without WHERE keyword)
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Database Functions
     * ```php
     * $recentOrders = (new QueryBuilder('orders'))
     *     ->whereRaw('DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)')
     *     ->whereRaw('TIME(created_at) BETWEEN "09:00:00" AND "17:00:00"')
     *     ->get();
     * ```
     *
     * @example Complex Expressions
     * ```php
     * $highValueOrders = (new QueryBuilder('orders'))
     *     ->whereRaw('(quantity * unit_price) > 500')
     *     ->whereRaw('discount_percent < 10')
     *     ->get();
     * ```
     *
     * @example Subquery Conditions
     * ```php
     * $activeCustomers = (new QueryBuilder('users'))
     *     ->whereRaw('id IN (SELECT DISTINCT customer_id FROM orders WHERE created_at > "2023-01-01")')
     *     ->get();
     * ```
     *
     * @see where() For parameterized conditions
     * @see orWhereRaw() For raw SQL OR conditions
     */
    public function whereRaw(string $clause): self
    {
        $this->wheres[] = ['AND', $clause];
        return $this;
    }

    /**
     * Add raw SQL OR WHERE condition without parameter binding
     *
     * Adds raw SQL condition directly to WHERE clause using OR boolean logic.
     * Combines raw SQL flexibility with OR conditional logic for complex
     * alternative matching scenarios.
     *
     * ## Security and Usage
     * Same security considerations as whereRaw() - raw SQL bypasses parameter
     * binding protection. Use with caution for user input and prefer standard
     * orWhere() for simple conditions.
     *
     * @param string $clause Raw SQL condition clause (without WHERE keyword)
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Alternative Function Conditions
     * ```php
     * $specialUsers = (new QueryBuilder('users'))
     *     ->where('role', 'premium')
     *     ->orWhereRaw('DATEDIFF(NOW(), last_login) < 30')
     *     ->orWhereRaw('total_purchases > (SELECT AVG(total_purchases) FROM users)')
     *     ->get();
     * ```
     *
     * @example Complex OR Logic
     * ```php
     * $eligibleOrders = (new QueryBuilder('orders'))
     *     ->where('status', 'pending')
     *     ->orWhereRaw('(priority = "urgent" AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))')
     *     ->get();
     * ```
     *
     * @see whereRaw() For raw SQL AND conditions
     * @see orWhere() For parameterized OR conditions
     */
    public function orWhereRaw(string $clause): self
    {
        $this->wheres[] = ['OR', $clause];
        return $this;
    }

    /**
     * Add ORDER BY clause for result sorting with direction validation
     *
     * Adds column-based sorting to query results with automatic direction validation.
     * Multiple orderBy() calls create multi-level sorting with priority based on
     * call order. Direction parameter is validated and defaults to ASC.
     *
     * ## Sorting Priority
     * First orderBy() call has highest priority, subsequent calls provide
     * secondary sorting for records with identical values in previous columns.
     *
     * ## Direction Handling
     * - Valid directions: 'ASC', 'DESC' (case insensitive)
     * - Invalid directions default to 'ASC' for query safety
     * - Direction is normalized to uppercase in generated SQL
     *
     * @param string $column Database column name for sorting
     * @param string $direction Sort direction: 'ASC' (default) or 'DESC'
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Single Column Sorting
     * ```php
     * $users = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->orderBy('name', 'ASC')
     *     ->get();
     * ```
     *
     * @example Multi-Level Sorting
     * ```php
     * $orders = (new QueryBuilder('orders'))
     *     ->orderBy('priority', 'DESC')    // Primary sort
     *     ->orderBy('created_at', 'ASC')   // Secondary sort
     *     ->orderBy('customer_name', 'ASC') // Tertiary sort
     *     ->get();
     * ```
     *
     * @example Direction Validation
     * ```php
     * $products = (new QueryBuilder('products'))
     *     ->orderBy('price', 'desc')     // Normalized to DESC
     *     ->orderBy('name', 'invalid')   // Defaults to ASC
     *     ->get();
     * ```
     *
     * @see orderByRaw() For raw SQL ordering expressions
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "$column $direction";
        return $this;
    }

    /**
     * Add raw SQL ORDER BY expression for complex sorting requirements
     *
     * Adds raw SQL ordering expression directly to ORDER BY clause. Enables
     * complex sorting using database functions, calculations, conditional logic,
     * and other SQL features not available through standard column sorting.
     *
     * ## Advanced Sorting Capabilities
     * - Database functions: RAND(), FIELD(), LENGTH()
     * - Conditional sorting: CASE statements for custom priority
     * - Calculated sorting: Mathematical expressions
     * - Multi-column expressions: Complex ordering logic
     *
     * ## Raw SQL Considerations
     * Raw expressions are inserted directly into SQL without parameter binding.
     * Ensure proper input validation when incorporating dynamic content.
     *
     * @param string $clause Raw SQL ORDER BY expression (without ORDER BY keyword)
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Random Ordering
     * ```php
     * $randomProducts = (new QueryBuilder('products'))
     *     ->where('featured', 1)
     *     ->orderByRaw('RAND()')
     *     ->limit(10)
     *     ->get();
     * ```
     *
     * @example Custom Priority Sorting
     * ```php
     * $tasks = (new QueryBuilder('tasks'))
     *     ->orderByRaw('CASE priority WHEN "urgent" THEN 1 WHEN "high" THEN 2 ELSE 3 END')
     *     ->orderBy('created_at', 'DESC')
     *     ->get();
     * ```
     *
     * @example Field-Specific Ordering
     * ```php
     * $users = (new QueryBuilder('users'))
     *     ->orderByRaw('FIELD(status, "active", "inactive", "banned")')
     *     ->orderBy('name', 'ASC')
     *     ->get();
     * ```
     *
     * @see orderBy() For standard column-based sorting
     */
    public function orderByRaw(string $clause): self
    {
        $this->orders[] = $clause;
        return $this;
    }

    /**
     * Set maximum number of rows to return with fluent chaining
     *
     * Applies LIMIT clause to restrict result set size at database level.
     * Provides performance optimization by reducing data transfer and memory usage.
     * Commonly used with orderBy() for top-N queries and with offset() for pagination.
     *
     * ## Performance Benefits
     * - Reduces database processing time for large tables
     * - Minimizes network data transfer
     * - Decreases application memory usage
     * - Enables efficient pagination when combined with offset()
     *
     * @param int $limit Maximum number of rows to return
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Top-N Query
     * ```php
     * $topCustomers = (new QueryBuilder('customers'))
     *     ->select(['name', 'total_purchases'])
     *     ->orderBy('total_purchases', 'DESC')
     *     ->limit(10)
     *     ->get();
     * ```
     *
     * @example Sample Data Retrieval
     * ```php
     * $sampleOrders = (new QueryBuilder('orders'))
     *     ->where('status', 'completed')
     *     ->orderByRaw('RAND()')
     *     ->limit(100)
     *     ->get();
     * ```
     *
     * @see offset() For pagination support
     * @see paginate() For complete pagination functionality
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set number of rows to skip before returning results
     *
     * Applies OFFSET clause for pagination support by skipping specified number
     * of rows before collecting results. Most effective when combined with limit()
     * and consistent orderBy() for reliable pagination sequences.
     *
     * ## Pagination Mathematics
     * - Page 1: offset(0) or no offset
     * - Page 2: offset(limit_size)
     * - Page N: offset((N-1) * limit_size)
     *
     * ## Ordering Requirements
     * Consistent orderBy() is essential for reliable pagination results.
     * Without ordering, offset results may be unpredictable across page requests.
     *
     * @param int $offset Number of rows to skip
     * @return static Current QueryBuilder instance for method chaining
     * @since 1.0.0
     *
     * @example Manual Pagination
     * ```php
     * // Page 3 with 20 items per page
     * $page3Users = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->orderBy('created_at', 'DESC')
     *     ->limit(20)
     *     ->offset(40)  // Skip first 40 records
     *     ->get();
     * ```
     *
     * @example Dynamic Pagination
     * ```php
     * $page = 2;
     * $perPage = 15;
     * $offset = ($page - 1) * $perPage;
     *
     * $results = (new QueryBuilder('products'))
     *     ->where('category', 'electronics')
     *     ->orderBy('name', 'ASC')
     *     ->limit($perPage)
     *     ->offset($offset)
     *     ->get();
     * ```
     *
     * @see limit() For result count restriction
     * @see paginate() For automated pagination with counting
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Compile all query components into final SQL statement with parameter bindings
     *
     * Assembles SELECT query from accumulated components including columns, table,
     * WHERE conditions, ORDER BY clauses, LIMIT, and OFFSET. Generates complete
     * SQL string with parameter placeholders and separate parameter array.
     *
     * ## Compilation Process
     * 1. Builds SELECT clause from columns array
     * 2. Adds FROM clause with table name
     * 3. Compiles WHERE conditions with boolean operator handling
     * 4. Adds ORDER BY clauses if specified
     * 5. Appends LIMIT and OFFSET if set
     * 6. Returns SQL string and parameter array tuple
     *
     * ## SQL Structure
     * Generated SQL follows standard format:
     * SELECT columns FROM table WHERE conditions ORDER BY ordering LIMIT limit OFFSET offset
     *
     * @return array{string, array} SQL string and parameter bindings tuple
     * @since 1.0.0
     */
    protected function compile(): array
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;

        if ($this->wheres) {
            $parts = [];
            foreach ($this->wheres as [$bool, $cond]) {
                $parts[] = (empty($parts) ? '' : $bool . ' ') . $cond;
            }
            $sql .= ' WHERE ' . implode(' ', $parts);
        }

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) $sql .= ' LIMIT ' . $this->limit;
        if ($this->offset !== null) $sql .= ' OFFSET ' . $this->offset;

        return [$sql, $this->bindings];
    }

    /**
     * Execute compiled query and return all matching results as associative arrays
     *
     * Compiles current query state into SQL with parameter bindings, executes via
     * prepared statement, and returns all matching rows as associative arrays.
     * Integrates with ConnectionManager and QueryTracker for database management
     * and performance monitoring.
     *
     * ## Execution Process
     * 1. Compiles query components into SQL string and parameter array
     * 2. Obtains database connection via ConnectionManager if not provided
     * 3. Prepares SQL statement for secure execution
     * 4. Starts QueryTracker monitoring for performance analysis
     * 5. Executes with bound parameters to prevent SQL injection
     * 6. Stops QueryTracker and records execution statistics
     * 7. Returns all results as associative arrays
     *
     * ## Result Format
     * Each row returned as associative array with column names as keys.
     * Empty result set returns empty array, never null.
     *
     * @param PDO|null $db Optional database connection override
     * @return array<array<string, mixed>> All matching rows as associative arrays
     * @since 1.0.0
     *
     * @example Basic Result Retrieval
     * ```php
     * $users = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->orderBy('name', 'ASC')
     *     ->get();
     *
     * foreach ($users as $user) {
     *     echo $user['name'] . ' - ' . $user['email'] . "\n";
     * }
     * ```
     *
     * @example Custom Database Connection
     * ```php
     * $readOnlyDb = ConnectionManager::getHandle('readonly');
     * $reports = (new QueryBuilder('reports'))
     *     ->select(['date', 'revenue', 'orders'])
     *     ->where('date', '>=', '2023-01-01')
     *     ->get($readOnlyDb);
     * ```
     *
     * @see first() For single result retrieval
     * @see count() For result counting
     * @see ConnectionManager::getHandle() For database connections
     * @see QueryTracker For performance monitoring
     */
    public function get(?PDO $db = null): array
    {
        [$sql, $bind] = $this->compile();
        $db ??= ConnectionManager::getHandle();
        $stmt = $db->prepare($sql);
        QueryTracker::start();
        $stmt->execute($bind);
        QueryTracker::stop($sql, $bind);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute query and return first matching result as associative array
     *
     * Automatically applies LIMIT 1 for performance optimization, executes query,
     * and returns first matching row as associative array. Returns null if no
     * results match the query conditions.
     *
     * ## Performance Optimization
     * Automatically adds LIMIT 1 to query for optimal database performance,
     * reducing unnecessary data processing and transfer when only one result needed.
     *
     * ## Result Handling
     * - Single match: Returns associative array with column data
     * - No matches: Returns null for easy null checking
     * - Multiple potential matches: Returns first based on ORDER BY or natural order
     *
     * @param PDO|null $db Optional database connection override
     * @return array<string, mixed>|null First matching row or null if no results
     * @since 1.0.0
     *
     * @example Single Record Retrieval
     * ```php
     * $user = (new QueryBuilder('users'))
     *     ->where('email', 'john@example.com')
     *     ->first();
     *
     * if ($user) {
     *     echo "Found user: " . $user['name'];
     * } else {
     *     echo "User not found";
     * }
     * ```
     *
     * @example Latest Record
     * ```php
     * $latestOrder = (new QueryBuilder('orders'))
     *     ->where('customer_id', 123)
     *     ->orderBy('created_at', 'DESC')
     *     ->first();
     * ```
     *
     * @example Existence Checking
     * ```php
     * $exists = (new QueryBuilder('products'))
     *     ->where('sku', 'ABC123')
     *     ->first();
     *
     * return $exists !== null;
     * ```
     *
     * @see get() For all results retrieval
     * @see count() For existence counting
     */
    public function first(?PDO $db = null): ?array
    {
        $this->limit(1);
        $rows = $this->get($db);
        return $rows[0] ?? null;
    }

    /**
     * Execute COUNT query and return total number of matching rows
     *
     * Temporarily modifies column selection to COUNT(*), executes query, and returns
     * integer count of matching rows. Preserves original column selection after
     * count execution for continued query building.
     *
     * ## Count Query Optimization
     * - Replaces SELECT columns with COUNT(*) for optimal performance
     * - Removes LIMIT and OFFSET clauses as they don't affect counting
     * - Preserves WHERE conditions for accurate filtered counting
     * - Restores original column selection after execution
     *
     * ## Use Cases
     * - Existence checking: count() > 0
     * - Pagination: Calculate total pages from total count
     * - Statistics: Get filtered record counts
     * - Validation: Check constraint compliance
     *
     * @param PDO|null $db Optional database connection override
     * @return int Total number of matching rows
     * @since 1.0.0
     *
     * @example Filtered Record Count
     * ```php
     * $activeUserCount = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->where('verified', 1)
     *     ->count();
     *
     * echo "Active verified users: $activeUserCount";
     * ```
     *
     * @example Existence Verification
     * ```php
     * $builder = new QueryBuilder('orders');
     * $hasOrders = $builder->where('customer_id', 123)->count() > 0;
     *
     * if ($hasOrders) {
     *     // Customer has order history
     *     $orders = $builder->get(); // Original columns restored
     * }
     * ```
     *
     * @example Pagination Preparation
     * ```php
     * $builder = (new QueryBuilder('products'))->where('category', 'electronics');
     * $totalProducts = $builder->count();
     * $totalPages = ceil($totalProducts / 20);
     *
     * $pageProducts = $builder->limit(20)->offset(0)->get();
     * ```
     *
     * @see get() For data retrieval
     * @see paginate() For integrated counting with pagination
     */
    public function count(?PDO $db = null): int
    {
        $origCols = $this->columns;
        $this->columns = ['COUNT(*) AS cnt'];
        $row = $this->first($db);
        $this->columns = $origCols; // reset
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Execute paginated query with automatic counting and page calculation
     *
     * Performs complete pagination by executing separate count and data queries.
     * Returns tuple with result data, total count, and calculated page count for
     * comprehensive pagination information in single method call.
     *
     * ## Pagination Process
     * 1. Clones current query for separate count operation
     * 2. Modifies count query to use COUNT(*) without LIMIT/OFFSET
     * 3. Executes count query to get total matching records
     * 4. Calculates page offset from page number and items per page
     * 5. Applies LIMIT and OFFSET to original query for data retrieval
     * 6. Executes data query to get page results
     * 7. Calculates total pages from count and page size
     * 8. Returns comprehensive pagination information
     *
     * ## Return Value Structure
     * Array with three elements: [results_array, total_count, total_pages]
     * - results_array: Associative arrays for current page
     * - total_count: Integer count of all matching records
     * - total_pages: Integer count of total pages available
     *
     * @param int $perPage Number of results per page
     * @param int $page Current page number (1-based indexing)
     * @param PDO $db Database connection for query execution
     * @return array{array<array<string, mixed>>, int, int} Results, total count, and page count
     * @since 1.0.0
     *
     * @example Complete Pagination
     * ```php
     * $builder = (new QueryBuilder('products'))
     *     ->where('category', 'electronics')
     *     ->where('in_stock', 1)
     *     ->orderBy('name', 'ASC');
     *
     * [$products, $total, $pages] = $builder->paginate(20, 1, $db);
     *
     * echo "Showing page 1 of $pages ($total total products)\n";
     * foreach ($products as $product) {
     *     echo "- " . $product['name'] . "\n";
     * }
     * ```
     *
     * @example Pagination Navigation
     * ```php
     * $currentPage = 2;
     * [$users, $totalUsers, $totalPages] = (new QueryBuilder('users'))
     *     ->where('active', 1)
     *     ->orderBy('created_at', 'DESC')
     *     ->paginate(15, $currentPage, $db);
     *
     * $hasNext = $currentPage < $totalPages;
     * $hasPrev = $currentPage > 1;
     *
     * echo "Page $currentPage of $totalPages\n";
     * echo "Previous: " . ($hasPrev ? "Available" : "None") . "\n";
     * echo "Next: " . ($hasNext ? "Available" : "None") . "\n";
     * ```
     *
     * @see get() For all results without pagination
     * @see count() For count-only queries
     * @see limit() For simple result limiting
     */
    public function paginate(int $perPage, int $page, PDO $db): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        // Clone for counting
        $countQuery = clone $this;
        $countQuery->columns = ['COUNT(*) AS total'];
        $countQuery->limit = null;
        $countQuery->offset = null;
        $totalRow = $countQuery->first($db);
        $total = (int)($totalRow['total'] ?? 0);

        // Apply limits to original
        $this->limit($perPage)->offset($offset);
        $rows = $this->get($db);

        $pages = (int)ceil($total / $perPage);

        return [$rows, $total, $pages];
    }

    /**
     * Extract compiled WHERE clause and parameters for external use
     *
     * Compiles current WHERE conditions into SQL clause string and separate parameter
     * array for use in external query construction. Enables QueryBuilder WHERE logic
     * to be integrated into custom SQL queries and bulk operations.
     *
     * ## Return Structure
     * Returns associative array with two keys:
     * - 'sql': Compiled WHERE clause string (without WHERE keyword)
     * - 'params': Array of parameter values in binding order
     *
     * ## Empty Condition Handling
     * When no WHERE conditions exist, returns empty strings and arrays:
     * - 'sql': Empty string ''
     * - 'params': Empty array []
     *
     * ## Integration Use Cases
     * - Model bulk operations requiring WHERE clause extraction
     * - Custom UPDATE/DELETE queries using QueryBuilder WHERE logic
     * - Complex query construction with mixed QueryBuilder and raw SQL
     * - Query analysis and debugging
     *
     * @return array{sql: string, params: array} WHERE clause and parameters
     * @since 1.0.0
     *
     * @example Bulk Update Integration
     * ```php
     * $builder = (new QueryBuilder('users'))
     *     ->where('active', 0)
     *     ->where('last_login', '<', '2022-01-01');
     *
     * $whereClause = $builder->getWhereClause();
     *
     * if (!empty($whereClause['sql'])) {
     *     $sql = "UPDATE users SET archived = 1 WHERE " . $whereClause['sql'];
     *     $stmt = $db->prepare($sql);
     *     $stmt->execute($whereClause['params']);
     * }
     * ```
     *
     * @example Query Analysis
     * ```php
     * $builder = (new QueryBuilder('orders'))
     *     ->where('status', 'pending')
     *     ->orWhere('priority', 'urgent');
     *
     * $where = $builder->getWhereClause();
     * echo "WHERE clause: " . $where['sql'] . "\n";
     * echo "Parameters: " . json_encode($where['params']) . "\n";
     * // Output: status = ? OR priority = ?
     * // Parameters: ["pending", "urgent"]
     * ```
     *
     * @see Model::bulkUpdate() For bulk operation integration
     */
    public function getWhereClause(): array
    {
        if (empty($this->wheres)) {
            return ['sql' => '', 'params' => []];
        }

        $parts = [];
        foreach ($this->wheres as [$bool, $cond]) {
            $parts[] = (empty($parts) ? '' : $bool . ' ') . $cond;
        }

        return [
            'sql' => implode(' ', $parts),
            'params' => $this->bindings
        ];
    }




}
