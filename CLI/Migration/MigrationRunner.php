<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Migration/MigrationRunner.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/Migration/MigrationRunner.php
 *
 * Class MigrationRunner
 *
 * Provides a complete solution for tracking, executing, and rolling back database migrations
 * using SQL files. It maintains migration state in a JSON file and provides batch
 * execution capabilities with detailed logging and error handling.
 *
 * Note: This class is intentionally in the global namespace to match the CLI structure.
 * It uses fully qualified names for Primordyx framework classes.
 *
 * Core Features:
 * - Sequential migration execution with automatic numbering
 * - Batch tracking for coordinated rollbacks
 * - Dry-run mode for testing migrations before execution
 * - Comprehensive status reporting and logging
 * - Integration with SqlProcessor for SQL execution
 * - Uses absolute migration paths from database configuration
 * - Transaction safety and error recovery
 * - JSON-based migration tracking (no database table required)
 * - Enhanced error recovery with user interaction
 *
 * Migration File Structure:
 * - Uses paired files: {number}_{name}_up.sql and {number}_{name}_down.sql
 * - Supports sequential numbering (001, 002, 003, etc.)
 * - Tracks execution state in .primordyx/migrations.json
 * - Maintains execution order and batch grouping
 *
 */
declare(strict_types=1);

namespace PrimordyxCLI;

use InvalidArgumentException;
use PDO;
use Primordyx\Config\Config;
use Primordyx\Database\ConnectionManager;
use Primordyx\Database\SqlProcessor;
use RuntimeException;
use Throwable;

class MigrationRunner
{
    protected PDO $db;
    protected string $configSectionName = '';
    protected SqlProcessor $sqlProcessor;
    protected string $databaseName;
    protected ?string $migrationsPath = null;
    protected bool $dryRun = false;
    protected bool $usingSystemConnection = false;
    protected ?array $tempPendingMigrations = null;

    /**
     * MigrationRunner constructor.
     *
     * @param string $migrationsPath  Absolute path to migrations directory.
     * @param string $configSectionName Config section name (e.g., 'default' for 'database_default').  // CHANGE THIS LINE
     *
     * @throws RuntimeException If connection cannot be established
     * @throws RuntimeException If migrations tracking file cannot be created
     * @throws InvalidArgumentException If databaseName is empty or invalid
     * @throws RuntimeException If migrationsPath does not exist or is not readable
     */
    public function __construct(string $migrationsPath, string $configSectionName)
    {
        if (trim($configSectionName) === '') {
            throw new InvalidArgumentException('Config section name cannot be empty');
        }

        $this->configSectionName = $configSectionName;

        // Read the ACTUAL database name from config
        $section = 'database_' . $configSectionName;
        $this->databaseName = Config::get('database', $section);

        if ($this->databaseName === 'undefined') {
            throw new RuntimeException("Database name not found in section [{$section}]");
        }

        // Try target database first, fall back to system connection
        $this->establishConnection();

        // Initialize SqlProcessor with PDO only (it doesn't take a path parameter)
        $this->sqlProcessor = new SqlProcessor($this->db);

        // Set our internal path
        $this->setMigrationsPath($migrationsPath);

        $this->ensureMigrationsFile();
    }

    /**
     * Ensure target database exists when using system connection
     */
    protected function ensureTargetDatabase(): void
    {
        if (!$this->usingSystemConnection) {
            return; // Already connected to target database
        }

        try {
            // Create the database if it doesn't exist
            $createDbSql = "CREATE DATABASE IF NOT EXISTS `{$this->databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $this->db->exec($createDbSql);

            // Switch to the target database
            $this->db->exec("USE `{$this->databaseName}`");

            if (!$this->dryRun) {
                echo "✅ Created and switched to database: {$this->databaseName}\n";
            }

        } catch (Throwable $e) {
            throw new RuntimeException("Failed to create/select target database '{$this->databaseName}': " . $e->getMessage());
        }
    }


    /**
     * Establish database connection, trying target database first, then system
     *
     * @throws RuntimeException If neither connection type works
     */
    protected function establishConnection(): void
    {
        try {
            // Try to connect to target database first
            $this->db = $this->createTargetDatabaseConnection();
            $this->usingSystemConnection = false;

            if (!$this->dryRun) {
                echo "✓ Connected to target database\n";
            }
        } catch (Throwable $e) {
            try {
                // Fall back to system connection
                $this->db = $this->createSystemDatabaseConnection();
                $this->usingSystemConnection = true;

                if (!$this->dryRun) {
                    echo "⚠ Target database not found, using system connection for bootstrap\n";
                }
            } catch (Throwable $systemError) {
                throw new RuntimeException(
                    "Failed to connect to database '{$this->databaseName}' or system: " .
                    $e->getMessage() . " | System error: " . $systemError->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Check if we should switch from system to target database
     *
     * @return bool True if we switched
     */
    protected function checkForDatabaseSwitch(): bool
    {
        if (!$this->usingSystemConnection) {
            return false; // Already using target database
        }

        if ($this->targetDatabaseExists()) {
            try {
                // Switch to target database
                $this->db = $this->createTargetDatabaseConnection();
                $this->sqlProcessor = new SqlProcessor($this->db);
                $this->usingSystemConnection = false;

                if (!$this->dryRun) {
                    echo "✓ Switched to target database\n";
                }
                return true;
            } catch (Throwable $e) {
                // Stay on system connection if switch fails
                if (!$this->dryRun) {
                    echo "⚠ Could not switch to target database yet: " . $e->getMessage() . "\n";
                }
            }
        }

        return false;
    }

    /**
     * Create PDO connection to target database using Config
     *
     * @return PDO
     * @throws RuntimeException If configuration missing or connection fails
     */
    private function createTargetDatabaseConnection(): PDO
    {
        $section = 'database_' . $this->configSectionName;

        $driver = Config::get('driver', $section);
        $host = Config::get('host', $section);
        $port = Config::get('port', $section);
        $username = Config::get('username', $section);
        $password = Config::get('password', $section);
        $charset = Config::get('charset', $section);
        $timeout = Config::get('timeout', $section, '30');
        $persistent = Config::getBool('persistent', $section, false);

        if ($driver === 'undefined' || $host === 'undefined' || $username === 'undefined' || $password === 'undefined') {
            throw new RuntimeException("Database section [{$section}] not configured properly");
        }

        $dsn = "{$driver}:host={$host};port={$port};dbname={$this->databaseName};charset={$charset}";

        return ConnectionManager::createHandle('migration_target', $dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => (int)$timeout,
            PDO::ATTR_PERSISTENT => $persistent
        ]);
    }

    /**
     * Create PDO system connection (no database) using Config
     *
     * @return PDO
     * @throws RuntimeException If configuration missing or connection fails
     */
    private function createSystemDatabaseConnection(): PDO
    {
        $section = 'database_default';

        $driver = Config::get('driver', $section);
        $host = Config::get('host', $section);
        $port = Config::get('port', $section);
        $username = Config::get('username', $section);
        $password = Config::get('password', $section);
        $charset = Config::get('charset', $section);
        $timeout = Config::get('timeout', $section, '30');
        $persistent = Config::getBool('persistent', $section, false);

        if ($driver === 'undefined' || $host === 'undefined' || $username === 'undefined' || $password === 'undefined') {
            throw new RuntimeException("Database section [{$section}] not configured properly");
        }

        // System DSN - NO database name
        $dsn = "{$driver}:host={$host};port={$port};charset={$charset}";

        return ConnectionManager::createHandle('migration_system', $dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => (int)$timeout,
            PDO::ATTR_PERSISTENT => $persistent
        ]);
    }

    /**
     * Check if target database exists using system connection
     *
     * @return bool
     */
    private function targetDatabaseExists(): bool
    {
        try {
            $systemDb = $this->createSystemDatabaseConnection();
            $stmt = $systemDb->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$this->databaseName]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Set the migrations directory path with method chaining.
     *
     * @param string $path Absolute path to migrations directory.
     * @return static Returns self for method chaining
     */
    public function setMigrationsPath(string $path): static
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Migrations path cannot be empty');
        }

        $normalizedPath = rtrim($path, '/');

        if (!is_dir($normalizedPath)) {
            throw new RuntimeException("Migrations path does not exist: {$normalizedPath}");
        }

        if (!is_readable($normalizedPath)) {
            throw new RuntimeException("Migrations path is not readable: {$normalizedPath}");
        }

        $this->migrationsPath = $normalizedPath;

        // SqlProcessor doesn't store path internally - it expects full paths in run()

        return $this;
    }

    /**
     * Get the current migrations path.
     *
     * @return string|null The configured migrations path, or null if not configured
     */
    public function getMigrationsPath(): ?string
    {
        return $this->migrationsPath;
    }

    /**
     * Enables or disables dry run mode for testing migrations.
     *
     * @param bool $enable Set to true to enable dry run mode, false to disable
     * @return static Returns self for method chaining
     */
    public function dryRun(bool $enable = true): static
    {
        $this->dryRun = $enable;
        $this->sqlProcessor->dryRun($enable);
        return $this;
    }

    /**
     * Configure logging output to a specified file.
     *
     * @param string $logFile Absolute path to log file
     * @return static Returns self for method chaining
     * @throws RuntimeException If log file cannot be created or written
     */
    public function logTo(string $logFile): static
    {
        $this->sqlProcessor->logTo($logFile);
        return $this;
    }

    /**
     * Ensure migrations tracking file exists
     *
     * @throws RuntimeException If file creation fails
     */
    protected function ensureMigrationsFile(): void
    {
        $file = $this->getMigrationsFilePath();
        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create migrations directory: {$dir}");
            }
        }

        if (!file_exists($file)) {
            $defaultData = [
                'executed' => [],
                'next_batch' => 1
            ];
            if (file_put_contents($file, json_encode($defaultData, JSON_PRETTY_PRINT)) === false) {
                throw new RuntimeException("Failed to create migrations file: {$file}");
            }
        }
    }

    /**
     * Get path to migrations tracking file
     *
     * @return string Absolute path to migrations.json
     */
    protected function getMigrationsFilePath(): string
    {
        return APP_ROOT . '/.primordyx/migrations.json';
    }

    /**
     * Read migrations data from JSON file
     *
     * @return array Migration data
     * @throws RuntimeException If file cannot be read or parsed
     */
    protected function readMigrationsFile(): array
    {
        $file = $this->getMigrationsFilePath();
        if (!file_exists($file)) {
            return ['executed' => [], 'next_batch' => 1];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Failed to read migrations file: {$file}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in migrations file: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Write migrations data to JSON file
     *
     * @param array $data Migration data to write
     * @throws RuntimeException If file cannot be written
     */
    protected function writeMigrationsFile(array $data): void
    {
        $file = $this->getMigrationsFilePath();
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to encode migrations data: " . json_last_error_msg());
        }

        if (file_put_contents($file, $json) === false) {
            throw new RuntimeException("Failed to write migrations file: {$file}");
        }
    }

    /**
     * Check if a migration has been executed
     *
     * @param string $migration Migration name to check
     * @return bool True if migration has been executed
     */
    public function isMigrationExecuted(string $migration): bool
    {
        $executed = $this->getExecutedMigrations();
        return isset($executed[$migration]);
    }

    /**
     * Determine if an error might be recoverable through user intervention
     *
     * @param string $message Error message to analyze
     * @return bool True if error might be recoverable
     */
    private function isRecoverableError(string $message): bool
    {
        $recoverablePatterns = [
            '/table.*already exists/i',             // CREATE TABLE when exists
            '/database.*already exists/i',          // CREATE DATABASE when exists
            '/column.*already exists/i',            // ADD column that exists
            '/key.*already exists/i',               // ADD key that exists
            '/index.*already exists/i',             // CREATE INDEX when exists
            '/user.*already exists/i',              // CREATE USER when exists
            '/duplicate entry/i',                   // INSERT duplicate key
            '/table.*doesn\'t exist/i',             // DROP table that doesn't exist
            '/database.*doesn\'t exist/i',          // DROP database that doesn't exist
            '/unknown table/i',                     // Reference non-existent table
            '/unknown column.*in.*list/i',          // SELECT unknown column
            '/column.*cannot be null/i',            // INSERT NULL into NOT NULL
            '/data too long for column/i',          // INSERT data too long
            '/out of range/i',                      // Numeric value out of range
            '/check that.*column.*exists/i',        // DROP column that doesn't exist
            '/check that.*key.*exists/i',           // DROP key that doesn't exist
            '/duplicate column name/i',             // ADD column that exists
            '/SQLSTATE\[42S01\]/i',                // Table/view already exists
            '/SQLSTATE\[42000\].*1007/i',          // Database exists (MySQL code 1007)
            '/SQLSTATE\[42000\].*1050/i',          // Table exists (MySQL code 1050)
            '/SQLSTATE\[42000\].*1061/i',          // Duplicate key name (MySQL code 1061)
        ];

        foreach ($recoverablePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display the SQL content that failed
     *
     * @param string $migrationName The migration that failed
     */
    protected function viewFailedSql(string $migrationName): void
    {
        $sqlFile = $migrationName . '_up.sql';
        $filePath = $this->migrationsPath . '/' . $sqlFile;

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "SQL Content for: {$migrationName}\n";
        echo "File: {$filePath}\n";
        echo str_repeat("=", 60) . "\n";

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            echo $content . "\n";
        } else {
            echo "SQL file not found: {$filePath}\n";
        }

        echo str_repeat("=", 60) . "\n";
        echo "Press Enter to continue...";
        $stdin = fopen('php://stdin', 'r');
        fgets($stdin);
        fclose($stdin);
        echo "\n";
    }

    /**
     * Run all pending migrations with enhanced error recovery.
     *
     * @return int Number of migrations executed
     * @throws RuntimeException If migration execution fails
     * @throws Throwable If any error occurs during execution
     */
    public function runPending(): int
    {
        $this->ensureTargetDatabase();

        $pending = $this->tempPendingMigrations ?? $this->getPendingMigrations();
        if (empty($pending)) {
            echo "No pending migrations to run.\n";
            return 0;
        }

        $batch = $this->getNextBatchNumber();
        $executed = 0;
        $skipped = 0;

        echo "Running " . count($pending) . " pending migrations in batch {$batch}:\n";

        foreach ($pending as $migration) {
            // Check for database switch before each migration
            $this->checkForDatabaseSwitch();

            try {
                echo "  → {$migration}";

                if ($this->dryRun) {
                    echo " [DRY RUN]";
                }

                $this->executeMigration($migration, 'up');
                $this->recordMigration($migration, $batch);
                $executed++;

                if ($this->dryRun) {
                    echo " ✓ (would execute)\n";
                } else {
                    echo " ✓\n";
                }

            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
                echo " ✗\n";
                echo "    Error: {$errorMessage}\n";

                if ($this->isRecoverableError($errorMessage)) {
                    echo "\n--- RECOVERABLE ERROR DETECTED ---\n";
                    echo "This might be a harmless error (like trying to create something that already exists).\n\n";
                    echo "Options:\n";
                    echo "  (s) Skip this migration and continue\n";
                    echo "  (v) View the SQL file content\n";
                    echo "  (r) Retry this migration\n";
                    echo "  (a) Abort migration process\n";
                    echo "\nChoice [s/v/r/a]: ";

                    $stdin = fopen('php://stdin', 'r');
                    $choice = strtolower(trim(fgets($stdin)));
                    fclose($stdin);

                    switch ($choice) {
                        case 's':
                            echo "Skipping migration: {$migration}\n";
                            $this->recordMigration($migration, $batch);
                            $skipped++;
                            continue 2;

                        case 'v':
                            $this->viewFailedSql($migration);
                            // After viewing, ask again
                            echo "Try again? [y/N]: ";
                            $stdin = fopen('php://stdin', 'r');
                            $retry = strtolower(trim(fgets($stdin)));
                            fclose($stdin);

                            if ($retry === 'y' || $retry === 'yes') {
                                // Retry the migration
                                $executed--; // Don't double count
                                continue 2;
                            } else {
                                throw $e;
                            }

                        case 'r':
                            echo "Retrying migration: {$migration}\n";
                            $executed--; // Don't double count on retry
                            continue 2;

                        case 'a':
                        default:
                            echo "Migration process aborted by user.\n";
                            throw $e;
                    }
                } else {
                    echo "\nMigration failed with non-recoverable error.\n";
                    throw $e;
                }
            }
        }

        $total = $executed + $skipped;

        if ($total > 0) {
            echo "\nSummary:\n";
            echo "  Executed: {$executed}\n";
            echo "  Skipped: {$skipped}\n";
            echo "  Total processed: {$total} of " . count($pending) . "\n";

            if ($total === count($pending)) {
                echo "✓ All migrations completed successfully!\n";
            } else {
                echo "⚠ Migration process incomplete. " . (count($pending) - $total) . " migrations remain pending.\n";
            }
        }

        return $executed;
    }

    /**
     * Continue migrations starting from a specific migration
     *
     * @param string $startMigration Migration name to start from
     * @return int Number of migrations executed
     * @throws Throwable
     */
    public function continueFromMigration(string $startMigration): int
    {
        $allMigrations = $this->getAllMigrationFiles();
        $startIndex = array_search($startMigration, $allMigrations);

        if ($startIndex === false) {
            throw new RuntimeException("Migration not found: {$startMigration}");
        }

        // Get migrations from start point onward that haven't been executed
        $remainingMigrations = array_slice($allMigrations, $startIndex);
        $executed = $this->getExecutedMigrations();

        // Filter out already executed ones
        $pendingFromPoint = array_filter($remainingMigrations, function($migration) use ($executed) {
            return !isset($executed[$migration]);
        });

        if (empty($pendingFromPoint)) {
            echo "No pending migrations from {$startMigration} onward.\n";
            return 0;
        }

        echo "Continuing from migration: {$startMigration}\n";
        echo "Migrations to process: " . count($pendingFromPoint) . "\n\n";

        // Temporarily override pending migrations for this run
        $this->tempPendingMigrations = array_values($pendingFromPoint);

        return $this->runPending();
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @return int Number of migrations rolled back
     * @throws RuntimeException If MigrationRunner has not been configured
     * @throws Throwable If rollback fails
     */
    public function rollbackLast(): int
    {
        $lastBatch = $this->getLastBatch();
        if (!$lastBatch) {
            echo "No migrations to rollback.\n";
            return 0;
        }

        return $this->rollbackBatch($lastBatch['batch']);
    }

    /**
     * Rollback a specific batch of migrations.
     *
     * @param int $batchNumber Batch number to rollback
     * @return int Number of migrations rolled back
     * @throws RuntimeException If rollback fails
     * @throws Throwable If any error occurs during rollback
     */
    public function rollbackBatch(int $batchNumber): int
    {
        $migrationsToRollback = $this->getMigrationsInBatch($batchNumber);
        if (empty($migrationsToRollback)) {
            echo "No migrations in batch {$batchNumber} to rollback.\n";
            return 0;
        }

        $totalRolledBack = 0;

        echo "Rolling back batch {$batchNumber} (" . count($migrationsToRollback) . " migrations):\n";

        // Rollback in reverse order
        foreach (array_reverse($migrationsToRollback) as $migrationInfo) {
            $migration = $migrationInfo['migration'];

            try {
                echo "  ← {$migration}";

                if ($this->dryRun) {
                    echo " [DRY RUN]";
                }

                $this->executeMigration($migration, 'down');
                $this->removeMigrationRecord($migration);
                $totalRolledBack++;

                if ($this->dryRun) {
                    echo " ✓ (would rollback)\n";
                } else {
                    echo " ✓\n";
                }

            } catch (RuntimeException $e) {
                echo " ✗\n";
                echo "    Error rolling back {$migration}: {$e->getMessage()}\n";
                throw $e;
            }
        }

        if ($this->dryRun) {
            echo "\n{$totalRolledBack} migrations would be rolled back.\n";
        } else {
            echo "\nRolled back {$totalRolledBack} migrations.\n";
        }

        return $totalRolledBack;
    }

    /**
     * Reset all migrations by rolling back everything
     *
     * @return int Number of migrations reset
     * @throws Throwable
     */
    public function reset(): int
    {
        $executed = $this->getExecutedMigrations();
        if (empty($executed)) {
            echo "No migrations to reset.\n";
            return 0;
        }

        // Get all unique batch numbers in descending order
        $batches = array_column($executed, 'batch');
        $batches = array_unique($batches);
        rsort($batches);

        $totalReset = 0;

        echo "Resetting all migrations (" . count($executed) . " total):\n";

        foreach ($batches as $batch) {
            $count = $this->rollbackBatch($batch);
            $totalReset += $count;
        }

        if ($this->dryRun) {
            echo "\n{$totalReset} migrations would be reset.\n";
        } else {
            echo "Total migrations reset: {$totalReset}\n";
        }

        return $totalReset;
    }

    /**
     * Execute a migration file (up or down).
     *
     * @param string $migrationName Migration identifier without suffix
     * @param string $direction Either 'up' or 'down'
     * @throws RuntimeException If the migration file does not exist
     * @throws RuntimeException If SqlProcessor fails to execute the SQL file
     */
    protected function executeMigration(string $migrationName, string $direction): void
    {
        $sqlFile = $migrationName . '_' . $direction . '.sql';
        $filePath = $this->migrationsPath . '/' . $sqlFile;

        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration {$direction} file not found: {$filePath}");
        }

        // Capture the actual SQL error details
        try {
            if (!$this->sqlProcessor->run($filePath)) {
                // Get the actual error details from the log
                $logText = $this->sqlProcessor->logtext ?? '';

                // Parse out useful error information
                $errorDetails = $this->extractErrorDetails($logText, $filePath);

                $action = $direction === 'up' ? 'execute migration' : 'execute rollback';
                throw new RuntimeException(
                    "Failed to {$action}: {$sqlFile}\n" .
                    "SQL Error: {$errorDetails['error']}\n" .
                    "Failed Statement: {$errorDetails['statement']}\n" .
                    "File: {$filePath}"
                );
            }
        } catch (RuntimeException $e) {
            // Re-throw our detailed error
            throw $e;
        } catch (Throwable $e) {
            // Catch any other unexpected errors
            $action = $direction === 'up' ? 'execute migration' : 'execute rollback';
            throw new RuntimeException(
                "Unexpected error while trying to {$action}: {$sqlFile}\n" .
                "Error: {$e->getMessage()}\n" .
                "File: {$filePath}",
                0,
                $e
            );
        }
    }

    /**
     * Extract useful error details from SqlProcessor log text
     *
     * @param string $logText The log text from SqlProcessor
     * @param string $filePath The SQL file path for context
     * @return array Array with 'error' and 'statement' keys
     */
    private function extractErrorDetails(string $logText, string $filePath): array
    {
        $error = 'Unknown SQL error';
        $statement = 'Unknown statement';

        // Parse log text to extract error and statement
        $lines = explode("\n", $logText);

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Look for error line
            if (str_contains($line, 'Error: ')) {
                $error = trim(substr($line, strpos($line, 'Error: ') + 7));
            }

            // Look for statement line
            if (str_contains($line, 'Statement: ')) {
                $statement = trim(substr($line, strpos($line, 'Statement: ') + 11));

                // If statement is multiline, capture subsequent lines until we hit a blank line
                $j = $i + 1;
                while ($j < count($lines) && trim($lines[$j]) !== '') {
                    $statement .= "\n" . $lines[$j];
                    $j++;
                }
            }
        }

        // If we didn't get good details from log, try to read the file directly
        if ($error === 'Unknown SQL error' && file_exists($filePath)) {
            $statement = "Full SQL file content:\n" . file_get_contents($filePath);
        }

        return [
            'error' => $error,
            'statement' => $this->truncateStatement($statement, 300)
        ];
    }

    /**
     * Truncate SQL statement for readable error messages
     *
     * @param string $statement The SQL statement
     * @param int $maxLength Maximum length before truncation
     * @return string Truncated statement
     */
    private function truncateStatement(string $statement, int $maxLength = 300): string
    {
        if (strlen($statement) <= $maxLength) {
            return $statement;
        }

        return substr($statement, 0, $maxLength) . "\n... (truncated)";
    }

    /**
     * Get all pending migrations.
     *
     * @return array Array of migration names that haven't been executed
     */
    public function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations();

        return array_filter($allMigrations, function($migration) use ($executed) {
            return !isset($executed[$migration]);
        });
    }

    /**
     * Get all migration files from the migrations directory.
     *
     * @return array Array of migration names (without _up.sql suffix)
     * @throws RuntimeException If migrations path is not configured
     */
    public function getAllMigrationFiles(): array
    {
        if (!$this->migrationsPath) {
            throw new RuntimeException('Migrations path not configured');
        }

        $files = glob($this->migrationsPath . '/*_up.sql');
        $migrations = [];

        foreach ($files as $file) {
            $basename = basename($file, '_up.sql');
            $migrations[] = $basename;
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Get executed migrations.
     *
     * @return array Associative array of executed migrations with their metadata
     */
    public function getExecutedMigrations(): array
    {
        $data = $this->readMigrationsFile();
        return $data['executed'] ?? [];
    }

    /**
     * Get migrations in a specific batch.
     *
     * @param int $batchNumber The batch number to filter by
     * @return array Array of migration info for the specified batch
     */
    public function getMigrationsInBatch(int $batchNumber): array
    {
        $executed = $this->getExecutedMigrations();
        $batchMigrations = [];

        foreach ($executed as $migration => $info) {
            if ($info['batch'] === $batchNumber) {
                $batchMigrations[] = [
                    'migration' => $migration,
                    'batch' => $info['batch'],
                    'executed_at' => $info['executed_at']
                ];
            }
        }

        return $batchMigrations;
    }

    /**
     * Get the last batch information.
     *
     * @return array|null Array with 'batch' and 'count' keys, or null if no migrations
     */
    public function getLastBatch(): ?array
    {
        $executed = $this->getExecutedMigrations();
        if (empty($executed)) {
            return null;
        }

        $batches = [];
        foreach ($executed as $migration => $info) {
            $batch = $info['batch'];
            $batches[$batch] = ($batches[$batch] ?? 0) + 1;
        }

        if (empty($batches)) {
            return null;
        }

        $lastBatch = max(array_keys($batches));
        return [
            'batch' => $lastBatch,
            'count' => $batches[$lastBatch]
        ];
    }

    /**
     * Get the next batch number for new migrations.
     *
     * @return int Next batch number to use
     */
    protected function getNextBatchNumber(): int
    {
        $data = $this->readMigrationsFile();
        return $data['next_batch'] ?? 1;
    }

    /**
     * Record a migration as executed.
     *
     * @param string $migration Migration name
     * @param int $batch Batch number
     * @throws RuntimeException If recording fails
     */
    protected function recordMigration(string $migration, int $batch): void
    {
        $data = $this->readMigrationsFile();

        $data['executed'][$migration] = [
            'batch' => $batch,
            'executed_at' => date('Y-m-d H:i:s')
        ];

        $data['next_batch'] = $batch + 1;

        $this->writeMigrationsFile($data);
    }

    /**
     * Remove a migration record.
     *
     * @param string $migration Migration name to remove
     * @throws RuntimeException If removal fails
     */
    protected function removeMigrationRecord(string $migration): void
    {
        $data = $this->readMigrationsFile();

        if (isset($data['executed'][$migration])) {
            unset($data['executed'][$migration]);
            $this->writeMigrationsFile($data);
        }
    }

    /**
     * Display migration status.
     */
    public function displayStatus(): void
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrationFiles();
        $pending = $this->getPendingMigrations();

        echo "Migration Status:\n";
        echo "================\n";
        echo "Migrations path: {$this->getMigrationsPath()}\n";
        echo "Migrations file: {$this->getMigrationsFilePath()}\n";
        echo "Database config: {$this->databaseName}\n";
        echo "Connection type: " . ($this->usingSystemConnection ? "System (bootstrap mode)" : "Target database") . "\n";
        echo "Total migrations: " . count($all) . "\n";
        echo "Executed: " . count($executed) . "\n";
        echo "Pending: " . count($pending) . "\n\n";

        if (!empty($executed)) {
            echo "Executed Migrations:\n";
            echo "-------------------\n";
            foreach ($executed as $migration => $info) {
                echo "✓ {$migration} (batch {$info['batch']}) - {$info['executed_at']}\n";
            }
            echo "\n";
        }

        if (!empty($pending)) {
            echo "Pending Migrations:\n";
            echo "------------------\n";
            foreach ($pending as $migration) {
                echo "• {$migration}\n";
            }
            echo "\n";
        }
    }

    /**
     * Get migration status data.
     *
     * @return array Status information
     */
    public function getStatus(): array
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrationFiles();
        $pending = $this->getPendingMigrations();

        return [
            'migrations_path' => $this->getMigrationsPath(),
            'migrations_file' => $this->getMigrationsFilePath(),
            'total_migrations' => count($all),
            'executed_count' => count($executed),
            'pending_count' => count($pending),
            'executed_migrations' => $executed,
            'pending_migrations' => $pending,
            'all_migrations' => $all
        ];
    }

    /**
     * Mark a migration as executed without running it
     *
     * @param string $migration Migration name to mark
     * @throws RuntimeException If marking fails
     */
    public function markAsExecuted(string $migration): void
    {
        $batch = $this->getNextBatchNumber();
        $this->recordMigration($migration, $batch);
    }
}