<?php
/**
 * File: /vendor/vernsix/primordyx/src/QueryTracker.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/QueryTracker.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;


/**
 * Class QueryTracker
 *
 * A configurable query tracking system that maintains an internal stack of executed SQL queries
 * with timing information, bindings, and caller details for debugging purposes.
 *
 * When enabled, tracks EVERYTHING: timing, bindings, and caller information.
 * When disabled, zero performance overhead.
 *
 * Usage:
 *   QueryTracker::enable();
 *   QueryTracker::start();
 *   // ... execute your query ...
 *   QueryTracker::stop($sql, $bindings);
 *   $queries = QueryTracker::getQueries();
 *   QueryTracker::disable();
 *
 * @since       1.0.0
 *
 */
class QueryTracker
{
    /**
     * Master switch for query tracking functionality
     *
     * Controls whether query tracking is active across all QueryTracker operations.
     * When false, all tracking methods return immediately with zero performance overhead.
     * Essential for zero-overhead design in production environments.
     *
     * ## Performance Impact
     * - **Disabled (false)**: Zero overhead - all methods exit immediately
     * - **Enabled (true)**: Full tracking with timing and metadata capture
     * - **Runtime Toggle**: Can be changed during execution for conditional tracking
     *
     * @var bool Query tracking enabled state
     * @since 1.0.0
     */
    private static bool $enabled = false;

    /**
     * FIFO queue storing tracked query metadata and performance information
     *
     * Array storing complete query tracking information including execution time,
     * parameter bindings, timestamps, and caller details. Managed as FIFO queue
     * with automatic oldest-query removal when maximum limit is reached.
     *
     * ## Query Entry Structure
     * Each entry contains:
     * - `sql`: Trimmed SQL query string
     * - `timestamp`: High-precision timestamp (Y-m-d H:i:s.u format)
     * - `sequence`: Sequential query number for execution order
     * - `execution_time`: Microsecond-precision timing (6 decimal places)
     * - `bindings`: Parameter array used with prepared statements
     * - `binding_count`: Number of parameters for quick reference
     * - `caller`: Application code location that initiated query
     *
     * ## Memory Management
     * - **FIFO Queue**: Oldest queries removed when max_queries limit reached
     * - **Efficient Storage**: Only essential metadata stored per query
     * - **Configurable Limits**: Prevents memory exhaustion in long-running processes
     *
     * @var array<array<string, mixed>> FIFO queue of query tracking information
     * @since 1.0.0
     */
    private static array $queries = [];

    /**
     * High-precision start timestamp for current query timing
     *
     * Stores microtime(true) timestamp when start() is called, used to calculate
     * execution duration when stop() is invoked. Reset to null after each query
     * to prevent timing contamination between queries.
     *
     * ## Timing Precision
     * - **Microsecond Accuracy**: Uses microtime(true) for sub-millisecond precision
     * - **Automatic Reset**: Cleared after each stop() call to prevent timing errors
     * - **Null Safety**: Handles cases where stop() is called without start()
     *
     * ## State Management
     * - **Active Timing**: Non-null float during query execution
     * - **Idle State**: Null when no query is being timed
     * - **Error Recovery**: Graceful handling of unbalanced start/stop calls
     *
     * @var float|null Microsecond-precision start time or null when idle
     * @since 1.0.0
     */
    private static ?float $currentStartTime = null;

    /**
     * Runtime configuration controlling tracking behavior and limits
     *
     * Configuration array managing memory limits, call stack analysis depth,
     * and other behavioral parameters. Provides runtime control over tracking
     * features and resource consumption.
     *
     * ## Configuration Keys
     * - **max_queries**: Maximum queries stored before FIFO removal (default: 1000)
     * - **caller_depth**: Call stack analysis depth for caller detection (default: 10)
     *
     * ## Memory Protection
     * max_queries prevents memory exhaustion by maintaining reasonable query limits.
     * Automatic validation ensures minimum value of 1 to prevent configuration errors.
     *
     * ## Call Stack Control
     * caller_depth balances caller detection accuracy with performance overhead.
     * Higher values provide better application code detection but increase processing time.
     *
     * @var array<string, mixed> Runtime configuration parameters
     * @since 1.0.0
     */
    private static array $config = [
        'max_queries' => 1000,         // Maximum queries to store (prevents memory issues)
        'caller_depth' => 10,          // How deep to look in the call stack for caller info
    ];

    /**
     * Activate query tracking with optional configuration overrides
     *
     * Enables query tracking system and merges provided configuration with defaults.
     * Validates configuration parameters to ensure safe operation and prevents
     * memory-related issues through configuration validation.
     *
     * ## Configuration Parameters
     * - **max_queries**: Maximum tracked queries (prevents memory exhaustion)
     * - **caller_depth**: Call stack analysis depth (balances accuracy vs performance)
     *
     * ## Configuration Validation
     * - **max_queries**: Enforces minimum value of 1000 for safety
     * - **Invalid Values**: Automatically corrected to prevent system issues
     * - **Runtime Override**: Existing configuration merged with new parameters
     *
     * ## Performance Considerations
     * Enabling tracking introduces performance overhead proportional to query volume.
     * Use selectively in development or conditional production monitoring scenarios.
     *
     * @param array<string, mixed> $config Optional configuration overrides
     * @return void
     * @since 1.0.0
     *
     * @example Basic Activation
     * ```php
     * // Enable with default configuration
     * QueryTracker::enable();
     *
     * // Process queries...
     * runDatabaseOperations();
     *
     * // Analyze results
     * $summary = QueryTracker::getSummary();
     * ```
     *
     * @example Custom Configuration
     * ```php
     * // Enable with custom limits for long-running processes
     * QueryTracker::enable([
     *     'max_queries' => 5000,    // Store more queries
     *     'caller_depth' => 15      // Deeper stack analysis
     * ]);
     * ```
     *
     * @example Conditional Production Monitoring
     * ```php
     * // Enable tracking based on environment or debug flags
     * if (app()->isDebug() || $_GET['profile_queries']) {
     *     QueryTracker::enable(['max_queries' => 2000]);
     * }
     * ```
     *
     * @see disable() For deactivating tracking
     * @see getConfig() For current configuration inspection
     */
    public static function enable(array $config = []): void
    {
        self::$enabled = true;
        self::$config = array_merge(self::$config, $config);

        // Ensure max_queries is reasonable
        if (self::$config['max_queries'] < 1) {
            self::$config['max_queries'] = 1000;
        }
    }

    /**
     * Deactivate query tracking and reset timing state
     *
     * Disables query tracking system for zero-overhead operation and resets
     * current timing state to prevent timing contamination. Does not clear
     * stored query data - use clear() separately if needed.
     *
     * ## Performance Restoration
     * After disable(), all QueryTracker methods return immediately with no
     * processing overhead, restoring full application performance.
     *
     * ## State Reset
     * - **Tracking Disabled**: All tracking methods become no-ops
     * - **Timer Reset**: Current timing state cleared to prevent errors
     * - **Data Preservation**: Previously tracked queries remain available
     *
     * ## Usage Patterns
     * Typically called after analysis completion or when switching from
     * development to production performance requirements.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Analysis Session Cleanup
     * ```php
     * QueryTracker::enable();
     *
     * // Run analysis...
     * performQueryAnalysis();
     *
     * // Generate reports
     * $report = generatePerformanceReport(QueryTracker::toArray());
     *
     * // Restore performance
     * QueryTracker::disable();
     * ```
     *
     * @example Conditional Tracking Toggle
     * ```php
     * // Toggle tracking based on runtime conditions
     * if ($needsDebugging) {
     *     QueryTracker::enable();
     * } else {
     *     QueryTracker::disable(); // Ensure zero overhead
     * }
     * ```
     *
     * @see enable() For activating tracking
     * @see clear() For clearing tracked query data
     */
    public static function disable(): void
    {
        self::$enabled = false;
        self::$currentStartTime = null;
    }

    /**
     * Check current query tracking activation state
     *
     * Returns current tracking status for conditional logic and system status
     * checking. Useful for conditional tracking operations and debugging
     * interface state management.
     *
     * ## Use Cases
     * - **Conditional Operations**: Execute tracking code only when enabled
     * - **Status Reporting**: Include tracking state in system status reports
     * - **Debug Interfaces**: Show current tracking state in development tools
     * - **Performance Monitoring**: Log tracking overhead in production metrics
     *
     * ## Performance Impact
     * Lightweight boolean check with minimal overhead suitable for frequent calls.
     *
     * @return bool True if tracking is active, false if disabled
     * @since 1.0.0
     *
     * @example Conditional Query Analysis
     * ```php
     * function analyzeQueryPerformance(): ?array {
     *     if (!QueryTracker::isEnabled()) {
     *         return null; // No data available
     *     }
     *
     *     return [
     *         'total_queries' => QueryTracker::getQueriesCount(),
     *         'total_time' => QueryTracker::getTotalTime(),
     *         'slow_queries' => count(QueryTracker::getSlowQueries())
     *     ];
     * }
     * ```
     *
     * @example Debug Panel Status
     * ```php
     * class DebugPanel {
     *     public function getQueryTrackingStatus(): array {
     *         return [
     *             'enabled' => QueryTracker::isEnabled(),
     *             'queries_tracked' => QueryTracker::isEnabled() ?
     *                                   QueryTracker::getQueriesCount() : 0,
     *             'memory_usage' => QueryTracker::isEnabled() ?
     *                              memory_get_usage() : 'N/A'
     *         ];
     *     }
     * }
     * ```
     *
     * @see enable() For activation
     * @see disable() For deactivation
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Begin high-precision timing for next query execution
     *
     * Records current microtime for query execution timing. Zero overhead when
     * tracking is disabled. Must be called before database operation for
     * accurate timing measurement.
     *
     * ## Timing Precision
     * Uses microtime(true) for microsecond-level precision enabling detection
     * of sub-millisecond query performance variations critical for optimization.
     *
     * ## Zero-Overhead Design
     * Immediate return when tracking disabled ensures no performance impact
     * in production environments where tracking is unnecessary.
     *
     * ## Usage Pattern
     * Always call start() immediately before database operation and stop()
     * immediately after for accurate timing measurement.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Basic Query Timing
     * ```php
     * QueryTracker::start();
     * $result = $pdo->query("SELECT COUNT(*) FROM users");
     * QueryTracker::stop("SELECT COUNT(*) FROM users", []);
     * ```
     *
     * @example Framework Integration
     * ```php
     * class Database {
     *     public function execute(string $sql, array $params = []): bool {
     *         QueryTracker::start();
     *         $stmt = $this->pdo->prepare($sql);
     *         $success = $stmt->execute($params);
     *         QueryTracker::stop($sql, $params);
     *         return $success;
     *     }
     * }
     * ```
     *
     * @example Nested Query Handling
     * ```php
     * // Each query gets independent timing
     * QueryTracker::start();
     * $users = getUserList();
     * QueryTracker::stop($userQuery, $userParams);
     *
     * foreach ($users as $user) {
     *     QueryTracker::start();
     *     $orders = getOrdersForUser($user['id']);
     *     QueryTracker::stop($orderQuery, [$user['id']]);
     * }
     * ```
     *
     * @see stop() For completing timing measurement
     * @see reset() For canceling current timing
     */
    public static function start(): void
    {
        if (self::$enabled) {
            self::$currentStartTime = microtime(true);
        }
    }

    /**
     * Cancel current query timing without recording measurement
     *
     * Resets timing state without creating query record. Useful for error
     * recovery scenarios where query execution is interrupted or when timing
     * needs to be canceled due to exceptional conditions.
     *
     * ## Use Cases
     * - **Error Recovery**: Cancel timing when query execution fails
     * - **Conditional Logic**: Cancel timing based on runtime decisions
     * - **Testing Scenarios**: Reset timing state for controlled test conditions
     * - **Exception Handling**: Clean timing state in exception handlers
     *
     * ## State Cleanup
     * Safely resets timing state without affecting previously recorded queries
     * or overall tracking configuration.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Error Recovery
     * ```php
     * QueryTracker::start();
     * try {
     *     $stmt = $pdo->prepare($sql);
     *     $stmt->execute($params);
     *     QueryTracker::stop($sql, $params);
     * } catch (PDOException $e) {
     *     QueryTracker::reset(); // Cancel timing for failed query
     *     throw $e;
     * }
     * ```
     *
     * @example Conditional Query Execution
     * ```php
     * QueryTracker::start();
     * if (!shouldExecuteQuery($conditions)) {
     *     QueryTracker::reset(); // Cancel timing
     *     return null;
     * }
     *
     * $result = executeQuery($sql, $params);
     * QueryTracker::stop($sql, $params);
     * ```
     *
     * @see start() For initiating timing
     * @see stop() For completing timing with recording
     */
    public static function reset(): void
    {
        self::$currentStartTime = null;
    }

    /**
     * Complete query timing and record comprehensive execution metadata
     *
     * Calculates execution time from start() call and creates complete query record
     * with timing, parameters, and caller information. Implements memory management
     * through FIFO queue with configurable limits.
     *
     * ## Zero-Overhead Protection
     * Immediate return when tracking disabled ensures no performance impact.
     * Critical for production environments where tracking is conditionally disabled.
     *
     * ## Comprehensive Metadata Capture
     * Records complete query information:
     * - **Execution Time**: Microsecond precision from start() to stop()
     * - **SQL Query**: Trimmed query string for analysis
     * - **Parameter Bindings**: Complete parameter array for debugging
     * - **Timestamp**: High-precision execution timestamp
     * - **Caller Information**: Application code location that initiated query
     * - **Sequence Number**: Execution order for chronological analysis
     *
     * ## Memory Management
     * - **FIFO Queue**: Automatic oldest query removal at max_queries limit
     * - **Memory Protection**: Prevents memory exhaustion in long-running processes
     * - **Efficient Storage**: Only essential metadata stored per query
     *
     * @param string $sql SQL query that was executed (will be trimmed)
     * @param array $bindings Parameter array used with prepared statement
     * @return void
     * @since 1.0.0
     *
     * @example Complete Query Tracking
     * ```php
     * QueryTracker::start();
     * $stmt = $pdo->prepare("SELECT * FROM users WHERE status = ? AND created > ?");
     * $stmt->execute(['active', '2024-01-01']);
     * QueryTracker::stop("SELECT * FROM users WHERE status = ? AND created > ?", ['active', '2024-01-01']);
     * ```
     *
     * @example Framework Integration with Error Handling
     * ```php
     * class QueryExecutor {
     *     public function execute(string $sql, array $params = []): array {
     *         QueryTracker::start();
     *         try {
     *             $stmt = $this->pdo->prepare($sql);
     *             $stmt->execute($params);
     *             $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
     *             QueryTracker::stop($sql, $params); // Success - record timing
     *             return $result;
     *         } catch (Exception $e) {
     *             QueryTracker::stop($sql, $params); // Record failed query too
     *             throw $e;
     *         }
     *     }
     * }
     * ```
     *
     * @example Batch Query Processing
     * ```php
     * $queries = [
     *     ['sql' => 'INSERT INTO logs (message) VALUES (?)', 'params' => ['User login']],
     *     ['sql' => 'UPDATE users SET last_login = NOW() WHERE id = ?', 'params' => [123]]
     * ];
     *
     * foreach ($queries as $query) {
     *     QueryTracker::start();
     *     $pdo->prepare($query['sql'])->execute($query['params']);
     *     QueryTracker::stop($query['sql'], $query['params']);
     * }
     * ```
     *
     * @see start() For initiating timing measurement
     * @see getQueries() For retrieving recorded query data
     */
    public static function stop(string $sql, array $bindings = []): void
    {
        // Immediate return if disabled - zero overhead!
        if (!self::$enabled) {
            return;
        }

        // Calculate execution time from stored start time
        $executionTime = 0.0;
        if (self::$currentStartTime !== null) {
            $executionTime = microtime(true) - self::$currentStartTime;
            self::$currentStartTime = null; // Reset for next query
        }

        // Enforce query limit to prevent memory issues
        if (count(self::$queries) >= self::$config['max_queries']) {
            // Remove oldest query (FIFO)
            array_shift(self::$queries);
        }

        $query = [
            'sql' => trim($sql),
            'timestamp' => date('Y-m-d H:i:s.u'),
            'sequence' => count(self::$queries) + 1,
            'execution_time' => round($executionTime, 6),
            'bindings' => $bindings,
            'binding_count' => count($bindings),
            'caller' => self::getCaller(),
        ];

        self::$queries[] = $query;
    }

    /**
     * Retrieve complete array of all tracked query records
     *
     * Returns comprehensive query tracking data including execution times,
     * parameter bindings, caller information, and metadata for each recorded
     * query. Essential for detailed performance analysis and debugging.
     *
     * ## Return Data Structure
     * Each query record contains:
     * - `sql`: Executed SQL query string
     * - `timestamp`: Execution timestamp (Y-m-d H:i:s.u format)
     * - `sequence`: Sequential execution number
     * - `execution_time`: Microsecond-precision timing (6 decimal places)
     * - `bindings`: Complete parameter binding array
     * - `binding_count`: Parameter count for quick reference
     * - `caller`: Application code location that initiated query
     *
     * ## Analysis Applications
     * - **Performance Profiling**: Identify slow queries and bottlenecks
     * - **Query Optimization**: Analyze SQL patterns and parameter usage
     * - **Debug Tracing**: Trace query execution flow and timing
     * - **Pattern Detection**: Find N+1 queries and optimization opportunities
     *
     * @return array<array<string, mixed>> Complete query tracking records
     * @since 1.0.0
     *
     * @example Performance Analysis
     * ```php
     * $queries = QueryTracker::getQueries();
     *
     * foreach ($queries as $query) {
     *     if ($query['execution_time'] > 0.1) {
     *         echo "Slow Query ({$query['execution_time']}s): {$query['sql']}\n";
     *         echo "Called from: {$query['caller']}\n";
     *         echo "Parameters: " . json_encode($query['bindings']) . "\n\n";
     *     }
     * }
     * ```
     *
     * @example Query Timeline Analysis
     * ```php
     * $queries = QueryTracker::getQueries();
     * $timeline = [];
     *
     * foreach ($queries as $query) {
     *     $timeline[] = [
     *         'sequence' => $query['sequence'],
     *         'time' => $query['execution_time'],
     *         'sql_preview' => substr($query['sql'], 0, 50) . '...',
     *         'location' => $query['caller']
     *     ];
     * }
     *
     * // Sort by execution time to find bottlenecks
     * usort($timeline, fn($a, $b) => $b['time'] <=> $a['time']);
     * ```
     *
     * @example Export for External Analysis
     * ```php
     * $queryData = QueryTracker::getQueries();
     * $csvData = [];
     *
     * foreach ($queryData as $query) {
     *     $csvData[] = [
     *         $query['sequence'],
     *         $query['execution_time'],
     *         $query['binding_count'],
     *         str_replace(["\n", "\r"], ' ', $query['sql']),
     *         $query['caller']
     *     ];
     * }
     *
     * // Export to CSV for spreadsheet analysis
     * writeCsvFile('query_analysis.csv', $csvData);
     * ```
     *
     * @see getQueriesCount() For count-only information
     * @see getSummary() For aggregated statistics
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * Get total number of currently tracked queries
     *
     * Returns count of queries in tracking queue. Lightweight alternative to
     * retrieving full query array when only count information is needed.
     * Useful for monitoring tracking volume and memory usage estimation.
     *
     * ## Performance Advantage
     * Simple count operation without data serialization overhead when only
     * quantity information is required for monitoring or display purposes.
     *
     * ## Monitoring Applications
     * - **Memory Usage**: Estimate tracking memory consumption
     * - **Volume Analysis**: Monitor query volume over time
     * - **Interface Display**: Show query count in debug panels
     * - **Threshold Alerts**: Trigger alerts for excessive query volumes
     *
     * @return int Number of tracked queries in current queue
     * @since 1.0.0
     *
     * @example Simple Volume Monitoring
     * ```php
     * $queryCount = QueryTracker::getQueriesCount();
     *
     * if ($queryCount > 500) {
     *     logger()->warning("High query volume detected: $queryCount queries");
     * }
     *
     * echo "Database queries executed: $queryCount\n";
     * ```
     *
     * @example Memory Usage Estimation
     * ```php
     * $count = QueryTracker::getQueriesCount();
     * $estimatedMemory = $count * 1024; // Rough estimate: 1KB per query
     *
     * if ($estimatedMemory > 10 * 1024 * 1024) { // 10MB threshold
     *     echo "Query tracking using approximately " .
     *          round($estimatedMemory / 1024 / 1024, 2) . "MB memory\n";
     * }
     * ```
     *
     * @example Debug Interface Display
     * ```php
     * class DebugToolbar {
     *     public function getQuerySummary(): string {
     *         if (!QueryTracker::isEnabled()) {
     *             return "Query tracking: Disabled";
     *         }
     *
     *         $count = QueryTracker::getQueriesCount();
     *         $totalTime = QueryTracker::getTotalTime();
     *
     *         return "Queries: $count | Time: {$totalTime}s";
     *     }
     * }
     * ```
     *
     * @see getQueries() For complete query data
     * @see getSummary() For comprehensive statistics
     */
    public static function getQueriesCount(): int
    {
        return count(self::$queries);
    }

    /**
     * Calculate total execution time across all tracked queries
     *
     * Aggregates execution times from all tracked queries to provide cumulative
     * database time measurement. Essential for overall performance assessment
     * and database time percentage calculation in request profiling.
     *
     * ## Precision and Accuracy
     * - **Microsecond Precision**: 6 decimal place accuracy for sub-millisecond timing
     * - **Cumulative Calculation**: Sums individual query execution times
     * - **Rounding**: Final result rounded to 6 decimal places for consistency
     *
     * ## Performance Analysis Applications
     * - **Request Profiling**: Calculate database time as percentage of total request time
     * - **Optimization Targets**: Identify high database time for optimization focus
     * - **Performance Trending**: Track database time trends over application lifecycle
     * - **Threshold Monitoring**: Alert on excessive cumulative database time
     *
     * @return float Total execution time in seconds with microsecond precision
     * @since 1.0.0
     *
     * @example Request Performance Profiling
     * ```php
     * $requestStart = microtime(true);
     *
     * // Process request with database operations...
     * handleUserRequest();
     *
     * $requestEnd = microtime(true);
     * $totalRequestTime = $requestEnd - $requestStart;
     * $databaseTime = QueryTracker::getTotalTime();
     *
     * $dbPercentage = ($databaseTime / $totalRequestTime) * 100;
     *
     * echo "Request completed in " . round($totalRequestTime, 3) . "s\n";
     * echo "Database time: " . $databaseTime . "s ({$dbPercentage:.1f}%)\n";
     * ```
     *
     * @example Performance Threshold Monitoring
     * ```php
     * function checkDatabasePerformance(): void {
     *     $totalTime = QueryTracker::getTotalTime();
     *     $queryCount = QueryTracker::getQueriesCount();
     *
     *     if ($totalTime > 2.0) {
     *         logger()->warning("High database time detected", [
     *             'total_time' => $totalTime,
     *             'query_count' => $queryCount,
     *             'avg_time' => $queryCount > 0 ? $totalTime / $queryCount : 0
     *         ]);
     *     }
     * }
     * ```
     *
     * @example Performance Comparison
     * ```php
     * // Before optimization
     * QueryTracker::clear();
     * QueryTracker::start();
     * runLegacyCode();
     * $legacyTime = QueryTracker::getTotalTime();
     *
     * // After optimization
     * QueryTracker::clear();
     * QueryTracker::start();
     * runOptimizedCode();
     * $optimizedTime = QueryTracker::getTotalTime();
     *
     * $improvement = (($legacyTime - $optimizedTime) / $legacyTime) * 100;
     * echo "Performance improvement: {$improvement:.1f}%\n";
     * ```
     *
     * @see getSummary() For comprehensive timing statistics
     * @see getSlowQueries() For individual slow query analysis
     */
    public static function getTotalTime(): float
    {
        $total = 0.0;
        foreach (self::$queries as $query) {
            $total += $query['execution_time'];
        }

        return round($total, 6);
    }

    /**
     * Filter queries exceeding specified execution time threshold
     *
     * Returns queries with execution time greater than or equal to threshold,
     * enabling focused analysis of performance bottlenecks and optimization
     * opportunities. Essential tool for identifying slow query patterns.
     *
     * ## Threshold Configuration
     * - **Default Threshold**: 0.1 seconds (100 milliseconds)
     * - **Configurable**: Any float value for different analysis needs
     * - **Inclusive Filter**: Returns queries with time >= threshold
     *
     * ## Optimization Workflow
     * Slow queries typically indicate optimization opportunities through:
     * - Index analysis and creation
     * - Query restructuring and optimization
     * - Data model refinements
     * - Caching strategies
     *
     * @param float $threshold Minimum execution time in seconds (default: 0.1)
     * @return array<array<string, mixed>> Queries exceeding threshold
     * @since 1.0.0
     *
     * @example Basic Slow Query Detection
     * ```php
     * // Find queries slower than 100ms (default)
     * $slowQueries = QueryTracker::getSlowQueries();
     *
     * echo "Found " . count($slowQueries) . " slow queries:\n";
     * foreach ($slowQueries as $query) {
     *     echo "- {$query['execution_time']}s: " . substr($query['sql'], 0, 60) . "...\n";
     * }
     * ```
     *
     * @example Custom Threshold Analysis
     * ```php
     * // Different thresholds for different analysis levels
     * $criticalQueries = QueryTracker::getSlowQueries(1.0);   // > 1 second
     * $warningQueries = QueryTracker::getSlowQueries(0.5);    // > 500ms
     * $watchQueries = QueryTracker::getSlowQueries(0.1);      // > 100ms
     *
     * echo "Critical queries (>1s): " . count($criticalQueries) . "\n";
     * echo "Warning queries (>500ms): " . count($warningQueries) . "\n";
     * echo "Watch queries (>100ms): " . count($watchQueries) . "\n";
     * ```
     *
     * @example Optimization Priority Report
     * ```php
     * $slowQueries = QueryTracker::getSlowQueries(0.2);
     *
     * // Sort by execution time (slowest first)
     * usort($slowQueries, fn($a, $b) => $b['execution_time'] <=> $a['execution_time']);
     *
     * echo "Top 5 Optimization Targets:\n";
     * foreach (array_slice($slowQueries, 0, 5) as $i => $query) {
     *     echo ($i + 1) . ". {$query['execution_time']}s - {$query['sql']}\n";
     *     echo "   Called from: {$query['caller']}\n";
     *     echo "   Parameters: " . json_encode($query['bindings']) . "\n\n";
     * }
     * ```
     *
     * @example Production Performance Monitoring
     * ```php
     * // Monitor slow queries in production
     * $criticalSlowQueries = QueryTracker::getSlowQueries(2.0);
     *
     * if (!empty($criticalSlowQueries)) {
     *     foreach ($criticalSlowQueries as $query) {
     *         logger()->error('Critical slow query detected', [
     *             'execution_time' => $query['execution_time'],
     *             'sql' => $query['sql'],
     *             'caller' => $query['caller'],
     *             'timestamp' => $query['timestamp']
     *         ]);
     *     }
     * }
     * ```
     *
     * @see getSlowestQuery() For single slowest query identification
     * @see getTotalTime() For cumulative timing analysis
     */
    public static function getSlowQueries(float $threshold = 0.1): array
    {
        return array_filter(self::$queries, function($query) use ($threshold) {
            return $query['execution_time'] >= $threshold;
        });
    }

    /**
     * Identify the fastest query execution from tracked queries
     *
     * Returns single query record with minimum execution time for performance
     * baseline analysis and best-case timing identification. Useful for
     * understanding optimal query performance characteristics.
     *
     * ## Performance Analysis Applications
     * - **Baseline Establishment**: Understand best-case query performance
     * - **Optimization Validation**: Verify optimization effectiveness
     * - **Performance Variance**: Compare fastest vs slowest for consistency analysis
     * - **Caching Impact**: Identify performance improvements from caching
     *
     * ## Null Handling
     * Returns null when no queries are tracked, enabling safe usage without
     * pre-checking query count.
     *
     * @return array<string, mixed>|null Fastest query record or null if no queries tracked
     * @since 1.0.0
     *
     * @example Performance Baseline Analysis
     * ```php
     * $fastest = QueryTracker::getFastestQuery();
     * $slowest = QueryTracker::getSlowestQuery();
     *
     * if ($fastest && $slowest) {
     *     $variance = $slowest['execution_time'] / $fastest['execution_time'];
     *
     *     echo "Query Performance Range:\n";
     *     echo "Fastest: {$fastest['execution_time']}s - {$fastest['sql']}\n";
     *     echo "Slowest: {$slowest['execution_time']}s - {$slowest['sql']}\n";
     *     echo "Performance variance: {$variance:.2f}x\n";
     * }
     * ```
     *
     * @example Optimization Impact Assessment
     * ```php
     * // Before optimization
     * QueryTracker::clear();
     * runTestQueries();
     * $beforeFastest = QueryTracker::getFastestQuery();
     *
     * // After optimization
     * QueryTracker::clear();
     * runOptimizedQueries();
     * $afterFastest = QueryTracker::getFastestQuery();
     *
     * if ($beforeFastest && $afterFastest) {
     *     $improvement = ($beforeFastest['execution_time'] - $afterFastest['execution_time'])
     *                   / $beforeFastest['execution_time'] * 100;
     *     echo "Fastest query improvement: {$improvement:.1f}%\n";
     * }
     * ```
     *
     * @example Caching Effectiveness
     * ```php
     * $fastest = QueryTracker::getFastestQuery();
     *
     * if ($fastest && $fastest['execution_time'] < 0.001) {
     *     echo "Ultra-fast query detected (likely cached): {$fastest['sql']}\n";
     *     echo "Time: {$fastest['execution_time']}s\n";
     * }
     * ```
     *
     * @see getSlowestQuery() For performance bottleneck identification
     * @see getSummary() For comprehensive timing statistics
     */
    public static function getFastestQuery(): ?array
    {
        if (empty(self::$queries)) {
            return null;
        }

        return array_reduce(self::$queries, function($fastest, $current) {
            return !$fastest || $current['execution_time'] < $fastest['execution_time'] ? $current : $fastest;
        });
    }

    /**
     * Identify the slowest query execution from tracked queries
     *
     * Returns single query record with maximum execution time for bottleneck
     * identification and optimization prioritization. Primary tool for finding
     * the most critical performance improvement opportunities.
     *
     * ## Optimization Priority
     * The slowest query typically represents the highest-impact optimization
     * opportunity, making it the primary target for performance improvement efforts.
     *
     * ## Bottleneck Analysis
     * Slowest queries often indicate:
     * - Missing or ineffective database indexes
     * - Complex query logic requiring restructuring
     * - Large dataset operations needing optimization
     * - N+1 query patterns requiring eager loading
     *
     * @return array<string, mixed>|null Slowest query record or null if no queries tracked
     * @since 1.0.0
     *
     * @example Primary Optimization Target
     * ```php
     * $slowest = QueryTracker::getSlowestQuery();
     *
     * if ($slowest) {
     *     echo "PRIMARY OPTIMIZATION TARGET:\n";
     *     echo "Execution Time: {$slowest['execution_time']}s\n";
     *     echo "SQL: {$slowest['sql']}\n";
     *     echo "Called from: {$slowest['caller']}\n";
     *     echo "Parameters: " . json_encode($slowest['bindings']) . "\n";
     *
     *     if ($slowest['execution_time'] > 1.0) {
     *         echo "⚠️  CRITICAL: Query exceeds 1 second threshold\n";
     *     }
     * } else {
     *     echo "No queries tracked for analysis\n";
     * }
     * ```
     *
     * @example Performance Alert System
     * ```php
     * $slowest = QueryTracker::getSlowestQuery();
     *
     * if ($slowest && $slowest['execution_time'] > 2.0) {
     *     // Alert for critically slow queries
     *     alertSystem()->critical('Extremely slow query detected', [
     *         'execution_time' => $slowest['execution_time'],
     *         'sql' => $slowest['sql'],
     *         'location' => $slowest['caller'],
     *         'timestamp' => $slowest['timestamp']
     *     ]);
     * }
     * ```
     *
     * @example Optimization Progress Tracking
     * ```php
     * class OptimizationTracker {
     *     private array $benchmarks = [];
     *
     *     public function recordBenchmark(string $label): void {
     *         $slowest = QueryTracker::getSlowestQuery();
     *         $this->benchmarks[$label] = $slowest ? $slowest['execution_time'] : 0;
     *     }
     *
     *     public function showProgress(): void {
     *         echo "Optimization Progress:\n";
     *         foreach ($this->benchmarks as $label => $time) {
     *             echo "- $label: {$time}s\n";
     *         }
     *     }
     * }
     * ```
     *
     * @see getFastestQuery() For performance baseline comparison
     * @see getSlowQueries() For comprehensive slow query analysis
     */
    public static function getSlowestQuery(): ?array
    {
        if (empty(self::$queries)) {
            return null;
        }

        return array_reduce(self::$queries, function($slowest, $current) {
            return !$slowest || $current['execution_time'] > $slowest['execution_time'] ? $current : $slowest;
        });
    }

    /**
     * Analyze and group queries by SQL pattern for duplication detection
     *
     * Groups identical SQL queries and calculates aggregated statistics including
     * execution count, total time, and average time per pattern. Essential for
     * identifying N+1 query problems and optimization opportunities through
     * query pattern analysis.
     *
     * ## Pattern Analysis Benefits
     * - **N+1 Detection**: Identify repeated queries in loops
     * - **Caching Opportunities**: Find frequently executed queries for caching
     * - **Batch Optimization**: Group similar queries for batch processing
     * - **Performance Patterns**: Understand query frequency vs performance relationships
     *
     * ## Statistics Calculation
     * For each unique SQL pattern:
     * - **count**: Number of times the query was executed
     * - **total_time**: Cumulative execution time for all instances
     * - **avg_time**: Average execution time per query instance
     *
     * ## Return Structure
     * Associative array with SQL query as key and statistics as value:
     * ```php
     * [
     *     'SELECT * FROM users WHERE id = ?' => [
     *         'count' => 15,
     *         'total_time' => 0.075,
     *         'avg_time' => 0.005
     *     ]
     * ]
     * ```
     *
     * @return array<string, array<string, mixed>> SQL patterns with execution statistics
     * @since 1.0.0
     *
     * @example N+1 Query Detection
     * ```php
     * $patterns = QueryTracker::getQueryPatterns();
     *
     * echo "Potential N+1 Query Problems:\n";
     * foreach ($patterns as $sql => $stats) {
     *     if ($stats['count'] > 10) {
     *         echo "🚨 REPEATED {$stats['count']} times: " . substr($sql, 0, 60) . "...\n";
     *         echo "   Total time: {$stats['total_time']}s | Avg: {$stats['avg_time']}s\n\n";
     *     }
     * }
     * ```
     *
     * @example Caching Priority Analysis
     * ```php
     * $patterns = QueryTracker::getQueryPatterns();
     *
     * // Sort by frequency for caching priority
     * arsort($patterns, function($a, $b) {
     *     return $b['count'] <=> $a['count'];
     * });
     *
     * echo "Top Caching Candidates (by frequency):\n";
     * $rank = 1;
     * foreach (array_slice($patterns, 0, 5, true) as $sql => $stats) {
     *     echo "$rank. Executed {$stats['count']} times | Avg: {$stats['avg_time']}s\n";
     *     echo "    SQL: " . substr($sql, 0, 80) . "...\n\n";
     *     $rank++;
     * }
     * ```
     *
     * @example Performance vs Frequency Analysis
     * ```php
     * $patterns = QueryTracker::getQueryPatterns();
     * $hotspots = [];
     *
     * foreach ($patterns as $sql => $stats) {
     *     $impact = $stats['count'] * $stats['avg_time']; // Frequency × Time
     *     if ($impact > 0.1) { // Significant impact threshold
     *         $hotspots[] = [
     *             'sql' => $sql,
     *             'impact' => $impact,
     *             'count' => $stats['count'],
     *             'avg_time' => $stats['avg_time']
     *         ];
     *     }
     * }
     *
     * // Sort by impact (frequency × average time)
     * usort($hotspots, fn($a, $b) => $b['impact'] <=> $a['impact']);
     *
     * echo "Performance Impact Ranking:\n";
     * foreach ($hotspots as $i => $hotspot) {
     *     echo ($i + 1) . ". Impact: {$hotspot['impact']}s " .
     *          "({$hotspot['count']}× @ {$hotspot['avg_time']}s avg)\n";
     *     echo "   " . substr($hotspot['sql'], 0, 70) . "...\n\n";
     * }
     * ```
     *
     * @see getSummary() For overall execution statistics
     * @see getSlowQueries() For individual slow query analysis
     */
    public static function getQueryPatterns(): array
    {
        $patterns = [];

        foreach (self::$queries as $query) {
            $sql = $query['sql'];
            if (isset($patterns[$sql])) {
                $patterns[$sql]['count']++;
                $patterns[$sql]['total_time'] += $query['execution_time'];
            } else {
                $patterns[$sql] = [
                    'count' => 1,
                    'total_time' => $query['execution_time'],
                    'avg_time' => $query['execution_time'],
                ];
            }
        }

        // Calculate average times
        foreach ($patterns as &$data) {
            $data['avg_time'] = $data['count'] > 0 ? round($data['total_time'] / $data['count'], 6) : 0;
        }

        return $patterns;
    }

    /**
     * Generate comprehensive performance summary with aggregated statistics
     *
     * Creates complete performance overview including query counts, timing statistics,
     * configuration details, and performance extremes. Essential for high-level
     * performance assessment and reporting.
     *
     * ## Summary Components
     * - **total_queries**: Number of tracked queries
     * - **tracking_enabled**: Current tracking state
     * - **total_time**: Cumulative execution time across all queries
     * - **avg_time**: Average execution time per query
     * - **fastest_time**: Minimum query execution time
     * - **slowest_time**: Maximum query execution time
     * - **config**: Current tracking configuration
     *
     * ## Statistical Accuracy
     * - **Conditional Calculations**: Statistics only computed when queries exist
     * - **Precision Consistency**: 6 decimal place rounding for timing values
     * - **Safe Handling**: Graceful handling of empty query sets
     *
     * @return array<string, mixed> Comprehensive performance and configuration summary
     * @since 1.0.0
     *
     * @example Performance Dashboard
     * ```php
     * $summary = QueryTracker::getSummary();
     *
     * echo "=== QUERY PERFORMANCE SUMMARY ===\n";
     * echo "Tracking Enabled: " . ($summary['tracking_enabled'] ? 'YES' : 'NO') . "\n";
     * echo "Total Queries: {$summary['total_queries']}\n";
     * echo "Total Time: {$summary['total_time']}s\n";
     * echo "Average Time: {$summary['avg_time']}s\n";
     *
     * if ($summary['fastest_time'] !== null) {
     *     echo "Fastest Query: {$summary['fastest_time']}s\n";
     *     echo "Slowest Query: {$summary['slowest_time']}s\n";
     *
     *     $variance = $summary['slowest_time'] / $summary['fastest_time'];
     *     echo "Performance Variance: {$variance:.2f}x\n";
     * }
     * ```
     *
     * @example Automated Performance Reports
     * ```php
     * function generatePerformanceReport(): array {
     *     $summary = QueryTracker::getSummary();
     *
     *     return [
     *         'performance_grade' => $summary['avg_time'] < 0.1 ? 'A' :
     *                               ($summary['avg_time'] < 0.5 ? 'B' : 'C'),
     *         'total_database_time' => $summary['total_time'],
     *         'query_efficiency' => $summary['total_queries'] > 0 ?
     *                              $summary['total_time'] / $summary['total_queries'] : 0,
     *         'recommendations' => [
     *             'cache_frequent_queries' => $summary['total_queries'] > 100,
     *             'optimize_slow_queries' => $summary['slowest_time'] > 1.0,
     *             'reduce_query_count' => $summary['total_queries'] > 50
     *         ]
     *     ];
     * }
     * ```
     *
     * @example Performance Threshold Monitoring
     * ```php
     * $summary = QueryTracker::getSummary();
     *
     * // Define performance thresholds
     * $thresholds = [
     *     'max_avg_time' => 0.1,      // 100ms average
     *     'max_total_time' => 2.0,    // 2 seconds total
     *     'max_queries' => 50,        // 50 queries max
     *     'max_slowest' => 1.0        // 1 second slowest
     * ];
     *
     * $alerts = [];
     * if ($summary['avg_time'] > $thresholds['max_avg_time']) {
     *     $alerts[] = "Average query time exceeds threshold";
     * }
     * if ($summary['total_time'] > $thresholds['max_total_time']) {
     *     $alerts[] = "Total database time too high";
     * }
     * if ($summary['slowest_time'] > $thresholds['max_slowest']) {
     *     $alerts[] = "Slowest query exceeds acceptable time";
     * }
     *
     * if (!empty($alerts)) {
     *     logger()->warning('Performance thresholds exceeded', [
     *         'alerts' => $alerts,
     *         'summary' => $summary
     *     ]);
     * }
     * ```
     *
     * @see toArray() For complete data export including patterns
     * @see getTotalTime() For detailed timing analysis
     */
    public static function getSummary(): array
    {
        $summary = [
            'total_queries' => self::getQueriesCount(),
            'tracking_enabled' => self::$enabled,
            'total_time' => self::getTotalTime(),
            'avg_time' => 0,
            'fastest_time' => null,
            'slowest_time' => null,
            'config' => self::$config,
        ];

        if ($summary['total_queries'] > 0) {
            $summary['avg_time'] = round($summary['total_time'] / $summary['total_queries'], 6);

            $fastest = self::getFastestQuery();
            $slowest = self::getSlowestQuery();

            $summary['fastest_time'] = $fastest ? $fastest['execution_time'] : null;
            $summary['slowest_time'] = $slowest ? $slowest['execution_time'] : null;
        }

        return $summary;
    }

    /**
     * Remove all tracked queries and reset timing state
     *
     * Clears complete query tracking history and resets current timing state
     * for fresh analysis sessions. Essential for isolating performance analysis
     * between different code sections or test scenarios.
     *
     * ## Complete State Reset
     * - **Query History**: Removes all tracked query records
     * - **Timer State**: Resets current timing to prevent contamination
     * - **Memory Recovery**: Frees memory used by query tracking data
     * - **Configuration Preservation**: Maintains current configuration settings
     *
     * ## Use Cases
     * - **Test Isolation**: Clear state between test scenarios
     * - **Analysis Segmentation**: Separate tracking for different code paths
     * - **Memory Management**: Periodic clearing in long-running processes
     * - **Benchmark Preparation**: Clean slate for performance measurements
     *
     * @return void
     * @since 1.0.0
     *
     * @example Test Scenario Isolation
     * ```php
     * // Test scenario 1
     * QueryTracker::clear();
     * runTestScenario1();
     * $scenario1Results = QueryTracker::getSummary();
     *
     * // Test scenario 2
     * QueryTracker::clear();
     * runTestScenario2();
     * $scenario2Results = QueryTracker::getSummary();
     *
     * // Compare results
     * comparePerformance($scenario1Results, $scenario2Results);
     * ```
     *
     * @example Segmented Analysis
     * ```php
     * // Analyze different request phases
     * $phases = ['initialization', 'main_processing', 'cleanup'];
     * $results = [];
     *
     * foreach ($phases as $phase) {
     *     QueryTracker::clear(); // Fresh start for each phase
     *     executePhase($phase);
     *     $results[$phase] = QueryTracker::getSummary();
     * }
     *
     * // Analyze per-phase performance
     * analyzePhasePerformance($results);
     * ```
     *
     * @example Memory Management in Long-Running Processes
     * ```php
     * $processedBatches = 0;
     *
     * while ($batch = getNextBatch()) {
     *     processBatch($batch);
     *     $processedBatches++;
     *
     *     // Clear tracking data every 100 batches to prevent memory buildup
     *     if ($processedBatches % 100 === 0) {
     *         $summary = QueryTracker::getSummary();
     *         logPerformanceMetrics($summary);
     *         QueryTracker::clear(); // Free memory
     *     }
     * }
     * ```
     *
     * @see getSummary() For capturing data before clearing
     * @see reset() For timing-only reset without clearing history
     */
    public static function clear(): void
    {
        self::$queries = [];
        self::$currentStartTime = null;
    }

    /**
     * Retrieve current tracking configuration parameters
     *
     * Returns complete configuration array for inspection, debugging, and
     * configuration validation. Useful for confirming configuration state
     * and troubleshooting tracking behavior.
     *
     * ## Configuration Parameters
     * - **max_queries**: Maximum tracked queries before FIFO removal
     * - **caller_depth**: Call stack analysis depth for caller detection
     *
     * ## Use Cases
     * - **Configuration Validation**: Confirm settings are applied correctly
     * - **Debug Information**: Include configuration in debug output
     * - **Status Reporting**: Show current configuration in monitoring interfaces
     * - **Dynamic Adjustment**: Base configuration changes on current settings
     *
     * @return array<string, mixed> Complete configuration parameter array
     * @since 1.0.0
     *
     * @example Configuration Validation
     * ```php
     * $config = QueryTracker::getConfig();
     *
     * echo "Current QueryTracker Configuration:\n";
     * echo "Max Queries: {$config['max_queries']}\n";
     * echo "Caller Depth: {$config['caller_depth']}\n";
     *
     * // Validate configuration
     * if ($config['max_queries'] < 100) {
     *     echo "Warning: max_queries setting may be too low\n";
     * }
     * ```
     *
     * @example Debug Information Export
     * ```php
     * $debugInfo = [
     *     'tracking_enabled' => QueryTracker::isEnabled(),
     *     'configuration' => QueryTracker::getConfig(),
     *     'queries_tracked' => QueryTracker::getQueriesCount(),
     *     'memory_usage' => memory_get_usage(true),
     *     'timestamp' => date('Y-m-d H:i:s')
     * ];
     *
     * file_put_contents('debug_info.json', json_encode($debugInfo, JSON_PRETTY_PRINT));
     * ```
     *
     * @example Dynamic Configuration Adjustment
     * ```php
     * $config = QueryTracker::getConfig();
     *
     * // Increase limits for intensive analysis
     * if (needsDeepAnalysis()) {
     *     $newConfig = $config;
     *     $newConfig['max_queries'] = $config['max_queries'] * 2;
     *     $newConfig['caller_depth'] = 15;
     *
     *     QueryTracker::disable();
     *     QueryTracker::enable($newConfig);
     * }
     * ```
     *
     * @see enable() For configuration modification
     * @see getSummary() For configuration included in performance summary
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * Generate comprehensive formatted debug output for development analysis
     *
     * Creates detailed, human-readable output of all tracked queries with complete
     * metadata, execution statistics, and summary information. Essential development
     * tool for query analysis and debugging sessions.
     *
     * ## Output Format
     * - **Header**: Query count and tracking status
     * - **Query Details**: Individual query information with timing, SQL, parameters, and caller
     * - **Summary Statistics**: Aggregated performance metrics
     * - **Formatted Display**: Console-friendly formatting with separators
     *
     * ## Comprehensive Query Information
     * For each query displays:
     * - Sequence number and timestamp
     * - Execution time with microsecond precision
     * - Complete SQL query
     * - Parameter bindings (if present)
     * - Application caller location (if detected)
     *
     * ## Flexible Output Options
     * - **Console Output**: Direct echo for interactive debugging
     * - **Return Value**: String capture for logging or file output
     * - **Dual Mode**: Always returns string regardless of echo parameter
     *
     * @param bool $return Whether to return output string instead of echoing (default: false)
     * @return string Complete formatted debug output (always returned)
     * @since 1.0.0
     *
     * @example Interactive Development Debugging
     * ```php
     * // Enable tracking and run problematic code
     * QueryTracker::enable();
     * runSuspiciousCode();
     *
     * // Output directly to console for immediate analysis
     * QueryTracker::dump();
     * ```
     *
     * @example Debug Log File Creation
     * ```php
     * QueryTracker::enable();
     * processUserRequest();
     *
     * // Capture output for logging
     * $debugOutput = QueryTracker::dump(true);
     * file_put_contents('query_debug_' . date('Y-m-d_H-i-s') . '.log', $debugOutput);
     *
     * echo "Debug output saved to log file\n";
     * ```
     *
     * @example Conditional Debug Output
     * ```php
     * // Only dump queries if there are performance issues
     * $summary = QueryTracker::getSummary();
     *
     * if ($summary['total_time'] > 1.0 || $summary['total_queries'] > 50) {
     *     echo "Performance issues detected - dumping query analysis:\n";
     *     QueryTracker::dump();
     * }
     * ```
     *
     * @example Email Debug Reports
     * ```php
     * if (detectPerformanceProblem()) {
     *     $debugReport = QueryTracker::dump(true);
     *
     *     mail(
     *         'dev@company.com',
     *         'Performance Issue Detected',
     *         "Performance problem detected in application.\n\nQuery Analysis:\n" . $debugReport
     *     );
     * }
     * ```
     *
     * @example Remote Debug Session
     * ```php
     * // For remote debugging via HTTP
     * if ($_GET['debug_queries'] && isAuthorized()) {
     *     header('Content-Type: text/plain');
     *     QueryTracker::dump();
     *     exit;
     * }
     * ```
     *
     * @see toArray() For structured data export
     * @see getSummary() For summary-only information
     */
    public static function dump(bool $return = false): string
    {
        $output = "\n" . str_repeat('=', 80) . "\n";
        $output .= "PRIMORDYX QUERY TRACKER - " . count(self::$queries) . " queries tracked\n";
        $output .= str_repeat('=', 80) . "\n";

        if (empty(self::$queries)) {
            $output .= "No queries tracked.\n";
        } else {
            foreach (self::$queries as $query) {
                $output .= sprintf(
                    "[%d] %s (%.6f seconds)\n",
                    $query['sequence'],
                    $query['timestamp'],
                    $query['execution_time']
                );

                $output .= "    SQL: " . $query['sql'] . "\n";

                if (!empty($query['bindings'])) {
                    $output .= "    Bindings: " . json_encode($query['bindings']) . "\n";
                }

                if ($query['caller']) {
                    $output .= "    Called from: " . $query['caller'] . "\n";
                }

                $output .= "\n";
            }

            // Summary
            $summary = self::getSummary();
            $output .= str_repeat('-', 80) . "\n";
            $output .= sprintf("Total Queries: %d\n", $summary['total_queries']);
            $output .= sprintf("Total Time: %.6f seconds\n", $summary['total_time']);
            $output .= sprintf("Average Time: %.6f seconds\n", $summary['avg_time']);
            if ($summary['fastest_time'] !== null) {
                $output .= sprintf("Fastest Query: %.6f seconds\n", $summary['fastest_time']);
                $output .= sprintf("Slowest Query: %.6f seconds\n", $summary['slowest_time']);
            }
        }

        $output .= str_repeat('=', 80) . "\n";

        if (!$return) {
            echo $output;
        }

        return $output;
    }

    /**
     * Export complete tracking data as structured array for external processing
     *
     * Creates comprehensive data export containing performance summary, complete
     * query history, and pattern analysis in structured format. Ideal for JSON
     * serialization, external analysis tools, and data persistence.
     *
     * ## Export Structure
     * Returns associative array with three main sections:
     * - **summary**: Complete performance statistics and configuration
     * - **queries**: Full query history with all metadata
     * - **patterns**: Query pattern analysis with execution statistics
     *
     * ## Processing Applications
     * - **JSON Export**: Direct json_encode() compatibility
     * - **External Tools**: Import into analysis and visualization tools
     * - **Data Persistence**: Store tracking data for historical analysis
     * - **API Responses**: Include performance data in API responses
     * - **Report Generation**: Feed data into reporting systems
     *
     * ## Data Completeness
     * Provides complete dataset including all tracked queries, performance metrics,
     * pattern analysis, and configuration information for comprehensive analysis.
     *
     * @return array<string, mixed> Complete tracking data with summary, queries, and patterns
     * @since 1.0.0
     *
     * @example JSON Export for External Analysis
     * ```php
     * QueryTracker::enable();
     * runApplicationCode();
     *
     * $trackingData = QueryTracker::toArray();
     * $jsonData = json_encode($trackingData, JSON_PRETTY_PRINT);
     *
     * file_put_contents('performance_analysis.json', $jsonData);
     * echo "Performance data exported to JSON file\n";
     * ```
     *
     * @example API Performance Endpoint
     * ```php
     * class PerformanceAPI {
     *     public function getQueryAnalysis(): array {
     *         if (!QueryTracker::isEnabled()) {
     *             return ['error' => 'Query tracking not enabled'];
     *         }
     *
     *         $data = QueryTracker::toArray();
     *         $data['export_timestamp'] = date('Y-m-d H:i:s');
     *         $data['request_id'] = uniqid();
     *
     *         return $data;
     *     }
     * }
     * ```
     *
     * @example Performance Data Persistence
     * ```php
     * // Store performance data for trend analysis
     * class PerformanceLogger {
     *     public function logPerformanceSession(string $sessionId): void {
     *         $data = QueryTracker::toArray();
     *         $data['session_id'] = $sessionId;
     *         $data['timestamp'] = time();
     *
     *         // Store in database for historical analysis
     *         $this->db->insert('performance_logs', [
     *             'session_id' => $sessionId,
     *             'data' => json_encode($data),
     *             'created_at' => date('Y-m-d H:i:s')
     *         ]);
     *     }
     * }
     * ```
     *
     * @example Data Visualization Feed
     * ```php
     * // Prepare data for charting libraries
     * $trackingData = QueryTracker::toArray();
     *
     * $chartData = [
     *     'timeline' => [],
     *     'performance' => [],
     *     'patterns' => []
     * ];
     *
     * foreach ($trackingData['queries'] as $query) {
     *     $chartData['timeline'][] = [
     *         'timestamp' => $query['timestamp'],
     *         'execution_time' => $query['execution_time'],
     *         'sequence' => $query['sequence']
     *     ];
     * }
     *
     * foreach ($trackingData['patterns'] as $sql => $stats) {
     *     $chartData['patterns'][] = [
     *         'query' => substr($sql, 0, 50),
     *         'count' => $stats['count'],
     *         'avg_time' => $stats['avg_time']
     *     ];
     * }
     *
     * echo json_encode($chartData);
     * ```
     *
     * @see getSummary() For summary-only data
     * @see getQueryPatterns() For pattern analysis only
     * @see dump() For human-readable debug output
     */
    public static function toArray(): array
    {
        return [
            'summary' => self::getSummary(),
            'queries' => self::$queries,
            'patterns' => self::getQueryPatterns(),
        ];
    }

    /**
     * Intelligent call stack analysis to identify application code initiating queries
     *
     * Analyzes debug backtrace to find the application code location that initiated
     * the database query, filtering out framework files to provide meaningful caller
     * information for debugging and optimization purposes.
     *
     * ## Framework-Aware Filtering
     * Skips known framework files to identify actual application code:
     * - QueryTracker.php, Model.php, QueryBuilder.php
     * - Validator.php, ConnectionManager.php, Router.php
     * - Configurable via $frameworkFiles array
     *
     * ## Call Stack Analysis
     * - **Configurable Depth**: Respects caller_depth configuration setting
     * - **Memory Efficient**: Uses DEBUG_BACKTRACE_IGNORE_ARGS for performance
     * - **Path Normalization**: Converts absolute paths to relative for readability
     *
     * @return string|null Caller location in "file:line" format or null if not found
     * @since 1.0.0
     */
    private static function getCaller(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::$config['caller_depth']);

        // Framework FILES to skip (not classes)
        $frameworkFiles = [
            'QueryTracker.php',
            'Model.php',
            'QueryBuilder.php',
            'Validator.php',
            'ConnectionManager.php',
            'Router.php',
        ];

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;

            // Skip framework FILES only
            $isFrameworkFile = false;
            foreach ($frameworkFiles as $skip) {
                if (str_contains($file, $skip)) {
                    $isFrameworkFile = true;
                    break;
                }
            }

            if ($isFrameworkFile) {
                continue;
            }

            if ($file && $line) {
                $relativePath = str_replace(getcwd() . '/', '', $file);
                return $relativePath . ':' . $line;
            }
        }

        return null;
    }

}
