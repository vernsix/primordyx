<?php
/**
 * File: /vendor/vernsix/primordyx/src/SqlProcessor.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/SqlProcessor.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use PDO;
use Primordyx\Events\EventManager;
use RuntimeException;
use Throwable;


/**
 * Class SqlProcessor
 *
 * Advanced SQL processor for the Primordyx framework. This class provides robust functionality
 * for parsing and executing SQL from files or strings with comprehensive error handling,
 * dry-run capabilities, and intelligent SQL statement parsing.
 *
 * Features:
 * - Smart SQL parsing that respects quoted strings and comments
 * - Dry-run mode for testing without actual execution
 * - Comprehensive logging with timestamps
 * - Execute SQL from files, wildcards, or strings
 * - Event-driven error handling via EventManager
 *
 * The SQL parser handles:
 * - Single-line comments (-- comment)
 * - Multi-line comments (/* comment *\/)
 * - Quoted strings with proper escaping
 * - Multiple SQL statements separated by semicolons
 *
 * Usage Examples:
 * ```php
 * // Simple usage with default connection
 * $processor = new SqlProcessor();
 * $processor->run('/app/database/migrations/001_create_users.sql');
 *
 * // Run multiple files with wildcard
 * $processor = new SqlProcessor();
 * $processor->runAll('/app/migrations/*.sql');
 *
 * // Execute SQL string directly
 * $sql = "CREATE TABLE users (id INT PRIMARY KEY); CREATE INDEX idx_users ON users(id);";
 * $processor->runSqlString($sql);
 *
 * // Custom PDO connection with dry run
 * $pdo = new PDO($dsn, $user, $pass);
 * $processor = new SqlProcessor($pdo);
 * $processor->dryRun(true)
 *           ->logTo('/tmp/test.log')
 *           ->run('/app/migrations/test.sql');
 *
 * // Error handling
 * $result = $processor->run('/app/migrations/001_create_tables.sql');
 * if (!$result) {
 *     // Error was fired via EventManager
 *     echo "Migration failed - check events\n";
 * }
 * ```
 *
 * @since       1.0.0
 *
 * @see         EventManager For error event handling
 * @see         ConnectionManager For database connection handling
 */
class SqlProcessor
{
    protected PDO $db;
    protected bool $dryRun = false;
    protected ?string $logFile = null;
    public string $logtext = '';

    /**
     * SqlProcessor constructor.
     *
     * Initializes the SqlProcessor with optional PDO connection.
     *
     * @param PDO|null $db Optional PDO database connection instance.
     *                     If null, uses ConnectionManager::getHandle() default connection.
     *
     * @throws RuntimeException If ConnectionManager fails to provide a valid PDO connection
     *
     * @since 2.0.0
     *
     * @example
     * ```php
     * // Use default connection
     * $processor = new SqlProcessor();
     *
     * // Custom PDO connection
     * $pdo = new PDO($dsn, $user, $pass);
     * $processor = new SqlProcessor($pdo);
     * ```
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? ConnectionManager::getHandle();
    }

    /**
     * Enables or disables dry run mode.
     *
     * When dry run mode is enabled, SQL statements are parsed, validated, and logged
     * but not actually executed against the database. This is invaluable for testing
     * migration scripts, debugging SQL syntax, or previewing changes before applying
     * them to production databases.
     *
     * In dry run mode:
     * - SQL files are still parsed and validated
     * - All statements are logged with [DRY RUN] prefix
     * - No actual database modifications occur
     * - Syntax errors are still detected and reported
     * - Execution flow remains identical to live runs
     *
     * @param bool $toggle Set to true to enable dry run mode, false to disable.
     *                    Default behavior is live execution (false).
     *
     * @return static Returns self for method chaining
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * $processor = new SqlProcessor();
     *
     * // Test migration before applying
     * $processor->dryRun(true)
     *           ->run('/app/migrations/001_create_tables.sql');
     *
     * // Apply for real
     * $processor->dryRun(false)
     *           ->run('/app/migrations/001_create_tables.sql');
     * ```
     */
    public function dryRun(bool $toggle = true): static
    {
        $this->dryRun = $toggle;
        return $this;
    }

    /**
     * Configures logging output to a specified file.
     *
     * When a log file is configured, all SQL processing operations are logged with
     * timestamps, including SQL statements executed, errors encountered, and execution
     * status. This provides an audit trail and debugging information for SQL operations.
     *
     * The log file is created if it doesn't exist and
     * all entries are appended to preserve execution history.
     *
     * Log Format:
     * - [YYYY-MM-DD HH:MM:SS] Message content
     * - Includes file paths, execution status, and SQL content
     * - Error logs include file names, line numbers, and details
     *
     * @param string $filePath Full absolute path to the log file.
     *                         Directory must be writable by the web server.
     *                         File will be created if it doesn't exist.
     *                         All log entries are appended (not overwritten).
     *
     * @return static Returns self for method chaining
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * $processor = new SqlProcessor();
     *
     * // Basic logging
     * $processor->logTo('/var/log/sql_processor.log');
     *
     * // Method chaining with timestamped log files
     * $logFile = '/tmp/sql_' . date('Y-m-d_H-i-s') . '.log';
     * $processor->logTo($logFile)
     *           ->dryRun(true)
     *           ->runAll('/app/migrations/*.sql');
     * ```
     */
    public function logTo(string $filePath): static
    {
        $this->logFile = $filePath;
        return $this;
    }

    /**
     * Loads and executes SQL statements from a specific file.
     *
     * This method reads the specified SQL file, parses it into executable statements
     * using intelligent parsing that respects SQL syntax rules, and executes each
     * statement against the database (unless in dry run mode).
     *
     * Processing Pipeline:
     * 1. Validates file existence and readability
     * 2. Reads entire file content into memory
     * 3. Parses SQL using smartSplit() method
     * 4. Executes each statement individually
     * 5. Logs all operations with detailed status
     * 6. Fires events on errors via EventManager
     *
     * @param string $filepath Absolute path to the SQL file to execute.
     *                        Must be readable and contain valid SQL statements.
     *
     * @return bool True if execution was successful, false if errors occurred
     *
     * @since 2.0.0
     *
     * @example
     * ```php
     * $processor = new SqlProcessor();
     *
     * // Run a single SQL file
     * $success = $processor->run('/app/migrations/001_create_users.sql');
     *
     * // Run with dry run and logging
     * $success = $processor->dryRun(true)
     *                      ->logTo('/tmp/test.log')
     *                      ->run('/app/migrations/002_add_indexes.sql');
     *
     * if (!$success) {
     *     // Handle error - event was fired
     * }
     * ```
     */
    public function run(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            $this->log("SQL file not found: $filepath");
            EventManager::fire('sql.processor.error', [
                'error' => 'File not found',
                'file' => $filepath,
                'processor' => $this
            ]);
            return false;
        }

        if (!is_readable($filepath)) {
            $this->log("SQL file not readable: $filepath");
            EventManager::fire('sql.processor.error', [
                'error' => 'File not readable',
                'file' => $filepath,
                'processor' => $this
            ]);
            return false;
        }

        $sql = file_get_contents($filepath);
        $this->log("Loading SQL from file: $filepath");
        $this->log(($this->dryRun ? "[DRY RUN] " : '[REAL RUN]'));

        $statements = $this->smartSplit($sql);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;

            try {
                if ($this->dryRun) {
                    $this->log("[DRY RUN] Would execute:\n$stmt\n");
                } else {
                    $this->db->exec($stmt);
                    $this->log("Executed:\n$stmt\n");
                }
            } catch (Throwable $e) {
                $this->log("Failed to execute SQL from: $filepath");
                $this->log("Error: " . $e->getMessage());
                $this->log("Statement: $stmt");

                EventManager::fire('sql.processor.error', [
                    'error' => $e->getMessage(),
                    'file' => $filepath,
                    'statement' => $stmt,
                    'exception' => $e,
                    'processor' => $this
                ]);

                return false;
            }
        }

        $this->log("SQL executed successfully: $filepath");
        EventManager::fire('sql.processor.success', [
            'file' => $filepath,
            'statements_count' => count($statements),
            'processor' => $this
        ]);

        return true;
    }

    /**
     * Loads and executes all SQL files matching a wildcard pattern.
     *
     * Performs batch execution of SQL files matching the provided pattern. Files are
     * processed in alphabetical/lexicographic order, which allows for sequential numbering
     * schemes (001_file.sql, 002_file.sql, etc.) to control execution order.
     *
     * @param string $pattern Wildcard pattern for SQL files (e.g., '/app/migrations/*.sql')
     *                       Must be an absolute path with wildcard.
     *
     * @return bool True if all files executed successfully, false if any errors occurred
     *
     * @since 2.0.0
     *
     * @example
     * ```php
     * $processor = new SqlProcessor();
     *
     * // Run all SQL files in a directory
     * $success = $processor->runAll('/app/migrations/*.sql');
     *
     * // Run specific pattern with logging
     * $success = $processor->logTo('/tmp/batch.log')
     *                      ->runAll('/app/migrations/2025_*.sql');
     *
     * // Dry run test
     * $success = $processor->dryRun(true)
     *                      ->runAll('/app/migrations/rollback_*.sql');
     * ```
     */
    public function runAll(string $pattern): bool
    {
        $this->log("Starting batch SQL execution with pattern: $pattern");

        $files = glob($pattern);
        if (empty($files)) {
            $this->log("No files found matching pattern: $pattern");
            EventManager::fire('sql.processor.error', [
                'error' => 'No files found',
                'pattern' => $pattern,
                'processor' => $this
            ]);
            return false;
        }

        sort($files); // Ensure consistent order

        $this->log("Found " . count($files) . " SQL files to process");

        $allSuccess = true;
        $processedCount = 0;

        foreach ($files as $file) {
            $this->log("Processing: " . basename($file));

            if ($this->run($file)) {
                $this->log("Completed: " . basename($file));
                $processedCount++;
            } else {
                $this->log("Failed: " . basename($file));
                $allSuccess = false;
                break; // Stop on first failure
            }
        }

        if ($allSuccess) {
            $this->log("Batch SQL execution completed successfully");
            EventManager::fire('sql.processor.batch.success', [
                'pattern' => $pattern,
                'files_count' => count($files),
                'processor' => $this
            ]);
        } else {
            EventManager::fire('sql.processor.batch.error', [
                'pattern' => $pattern,
                'processed' => $processedCount,
                'total' => count($files),
                'processor' => $this
            ]);
        }

        return $allSuccess;
    }

    /**
     * Executes SQL statements from a string.
     *
     * This method takes a string containing SQL statements, parses it using the same
     * intelligent parsing as file-based execution, and executes each statement against
     * the database (unless in dry run mode).
     *
     * @param string $sql SQL statements to execute. Multiple statements should be
     *                   separated by semicolons.
     *
     * @return bool True if execution was successful, false if errors occurred
     *
     * @since 2.0.0
     *
     * @example
     * ```php
     * $processor = new SqlProcessor();
     *
     * // Execute single statement
     * $sql = "CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(100))";
     * $success = $processor->runSqlString($sql);
     *
     * // Execute multiple statements
     * $sql = "
     *     CREATE TABLE posts (id INT PRIMARY KEY, title VARCHAR(200));
     *     CREATE INDEX idx_posts_title ON posts(title);
     *     INSERT INTO posts (id, title) VALUES (1, 'First Post');
     * ";
     * $success = $processor->runSqlString($sql);
     *
     * // Dry run test
     * $success = $processor->dryRun(true)
     *                      ->logTo('/tmp/sql_test.log')
     *                      ->runSqlString($sql);
     * ```
     */
    public function runSqlString(string $sql): bool
    {
        $this->log("Executing SQL string");
        $this->log(($this->dryRun ? "[DRY RUN] " : '[REAL RUN]'));

        $statements = $this->smartSplit($sql);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;

            try {
                if ($this->dryRun) {
                    $this->log("[DRY RUN] Would execute:\n$stmt\n");
                } else {
                    $this->db->exec($stmt);
                    $this->log("Executed:\n$stmt\n");
                }
            } catch (Throwable $e) {
                $this->log("Failed to execute SQL statement");
                $this->log("Error: " . $e->getMessage());
                $this->log("Statement: $stmt");

                EventManager::fire('sql.processor.error', [
                    'error' => $e->getMessage(),
                    'source' => 'string',
                    'statement' => $stmt,
                    'exception' => $e,
                    'processor' => $this
                ]);

                return false;
            }
        }

        $this->log("SQL string executed successfully");
        EventManager::fire('sql.processor.success', [
            'source' => 'string',
            'statements_count' => count($statements),
            'processor' => $this
        ]);

        return true;
    }

    /**
     * Parses SQL content into individual executable statements.
     *
     * This method intelligently splits SQL content on semicolons while respecting
     * SQL syntax rules for quoted strings and comments. This prevents improper
     * splitting of statements that contain semicolons within string literals or
     * comment blocks.
     *
     * Parsing Rules:
     * - Splits on semicolons not inside quoted strings
     * - Ignores content within single-line comments (-- comment)
     * - Ignores content within multi-line comments (\/* comment *\/)
     * - Respects both single and double quoted strings
     * - Handles escaped quotes within strings
     * - Handles escaped backslashes
     * - Removes empty statements after splitting
     *
     * @param string $sql Raw SQL content to parse
     *
     * @return array List of individual SQL statements ready for execution
     *
     * @since 1.0.0
     * @version 2.1.0 Fixed escaped quote handling
     */
    protected function smartSplit(string $sql): array
    {
        $statements = [];
        $current = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inComment = false;
        $inBlockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

            // Handle block comments
            if (!$inSingleQuote && !$inDoubleQuote && !$inComment) {
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++; // Skip next char
                    continue;
                }
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++; // Skip next char
                }
                continue;
            }

            // Handle single-line comments
            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === '-' && $next === '-') {
                    $inComment = true;
                    continue;
                }
            }

            if ($inComment) {
                if ($char === "\n" || $char === "\r") {
                    $inComment = false;
                }
                continue;
            }

            // Handle quotes with proper escape checking
            if ($char === "'" && !$inDoubleQuote) {
                // Check if this quote is escaped by counting preceding backslashes
                if (!$this->isQuoteEscaped($sql, $i)) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote) {
                // Check if this quote is escaped by counting preceding backslashes
                if (!$this->isQuoteEscaped($sql, $i)) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            }

            // Handle statement separator
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add final statement if exists
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * Checks if a quote at the given position is escaped by backslashes.
     *
     * This method counts the number of consecutive backslashes immediately before
     * the quote. If there's an odd number of backslashes, the quote is escaped.
     * If there's an even number (including zero), the quote is not escaped.
     *
     * Examples:
     * - 'don\'t' -> quote at position of \' is escaped (1 backslash = odd)
     * - 'path\\' -> quote would not be escaped (2 backslashes = even)
     * - 'say \\\' -> quote is escaped (3 backslashes = odd)
     *
     * @param string $sql The full SQL string
     * @param int $quotePos Position of the quote character to check
     * @return bool True if the quote is escaped, false otherwise
     *
     * @since 2.1.0
     */
    private function isQuoteEscaped(string $sql, int $quotePos): bool
    {
        $backslashCount = 0;
        $pos = $quotePos - 1;

        // Count consecutive backslashes before the quote
        while ($pos >= 0 && $sql[$pos] === '\\') {
            $backslashCount++;
            $pos--;
        }

        // Quote is escaped if there's an odd number of backslashes before it
        return ($backslashCount % 2) === 1;
    }
    /**
     * Internal logging method.
     *
     * Handles both file-based logging and internal log text accumulation.
     * All log entries are timestamped for accurate audit trails.
     *
     * @param string $message Message to log
     */
    protected function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";

        // Always accumulate in memory
        $this->logtext .= $logEntry;

        // Write to file if configured
        if ($this->logFile) {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
}