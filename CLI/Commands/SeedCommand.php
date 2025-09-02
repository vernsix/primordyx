<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/SeedCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/Commands/SeedCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Exception;
use PDO;
use Primordyx\Config\Config;
use Primordyx\Database\ConnectionManager;
use Primordyx\Database\SqlProcessor;
use RuntimeException;
use Throwable;

class SeedCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'seed';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Seed the database with sample data';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Database Seeding

Usage:
  primordyx seed [seeder] [options]

Arguments:
  seeder                  Specific seeder to run (optional)

Options:
  --database=name         Target database name
  --dry-run               Show what would be executed without running
  --help, -h              Show help

Examples:
  primordyx seed                    Run all seeders
  primordyx seed cat_facts          Run specific seeder
  primordyx seed --dry-run          Preview what would be seeded
  primordyx seed cat_facts --database=testing

Seeder Types:
  - SQL files in ./seeds/ directory
  - PHP seeder classes in ./seeds/ directory

Notes:
  - Seeders should be idempotent (safe to run multiple times)
  - Use INSERT IGNORE or ON DUPLICATE KEY UPDATE for safety
  - Seeder files use format: SeederName.sql or SeederName.php";
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args): void
    {
        // Check for --help
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->out($this->getDetailedHelp());
            return;
        }

        // Parse options and clean args
        $result = $this->parseOptions($args);
        $options = $result['options'];
        $cleanArgs = $result['args'];

        $databaseName = $options['database'] ?? $this->getDefaultDatabase();
        $dryRun = isset($options['dry-run']);
        $seederName = $cleanArgs[0] ?? null;

        // Get seeds directory
        $seedsPath = getcwd() . '/seeds';
        if (!is_dir($seedsPath)) {
            $this->out("Seeds directory not found: {$seedsPath}");
            $this->out("Create it with: mkdir seeds");
            return;
        }

        try {
            if ($seederName) {
                $this->runSpecificSeeder($seederName, $seedsPath, $databaseName, $dryRun);
            } else {
                $this->runAllSeeders($seedsPath, $databaseName, $dryRun);
            }
        } catch (Exception $e) {
            $this->error("Seeding error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Run a specific seeder
     */
    private function runSpecificSeeder(string $seederName, string $seedsPath, string $databaseName, bool $dryRun): void
    {
        $sqlFile = $seedsPath . '/' . $seederName . '.sql';
        $phpFile = $seedsPath . '/' . $seederName . '.php';

        if (file_exists($sqlFile)) {
            $this->runSqlSeeder($sqlFile, $databaseName, $dryRun);
        } elseif (file_exists($phpFile)) {
            $this->runPhpSeeder($phpFile, $databaseName, $dryRun);
        } else {
            $this->error("Seeder not found: {$seederName}\nLooked for:\n  - {$sqlFile}\n  - {$phpFile}");
        }
    }

    /**
     * Run all seeders in directory
     */
    private function runAllSeeders(string $seedsPath, string $databaseName, bool $dryRun): void
    {
        $seeders = [];

        // Find SQL seeders
        foreach (glob($seedsPath . '/*.sql') as $file) {
            $seeders[] = ['type' => 'sql', 'file' => $file, 'name' => basename($file, '.sql')];
        }

        // Find PHP seeders
        foreach (glob($seedsPath . '/*.php') as $file) {
            $seeders[] = ['type' => 'php', 'file' => $file, 'name' => basename($file, '.php')];
        }

        if (empty($seeders)) {
            $this->out("No seeders found in: {$seedsPath}");
            return;
        }

        // Sort by name for consistent order
        usort($seeders, fn($a, $b) => $a['name'] <=> $b['name']);

        $this->out("Running " . count($seeders) . " seeders:");

        foreach ($seeders as $seeder) {
            $this->out("  - {$seeder['name']} ({$seeder['type']})");

            if ($seeder['type'] === 'sql') {
                $this->runSqlSeeder($seeder['file'], $databaseName, $dryRun);
            } else {
                $this->runPhpSeeder($seeder['file'], $databaseName, $dryRun);
            }
        }

        $this->out("✓ All seeders completed successfully!");
    }

    /**
     * Run SQL seeder file
     */
    private function runSqlSeeder(string $filePath, string $databaseName, bool $dryRun): void
    {
        try {
            // Create PDO connection to target database
            $pdo = $this->createDatabaseConnection($databaseName);
            $processor = new SqlProcessor($pdo);

            if ($dryRun) {

                $processor->dryRun(true);
                $this->out("    [DRY RUN] SQL from: " . basename($filePath));

                $this->out("    " . str_repeat("-", 50));
                if (!$processor->run($filePath)) {
                    $this->out("    ✗ SQL seeder would fail");
                }
                $this->out($processor->logtext);
                $this->out("    " . str_repeat("-", 50));

            } else {

                if ($processor->run($filePath)) {
                    $this->out("    ✓ SQL seeder completed");
                } else {
                    $this->out("    ✗ SQL seeder failed");
                    $this->out("    Log: " . $processor->logtext);
                    throw new RuntimeException("SQL seeder failed");
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to run SQL seeder {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Run PHP seeder class
     */
    private function runPhpSeeder(string $filePath, string $databaseName, bool $dryRun): void
    {
        try {
            // Include the PHP seeder file
            $seederClass = include $filePath;

            // Create PDO connection to target database
            $pdo = $this->createDatabaseConnection($databaseName);

            // Handle dry run mode (common to both seeder types)
            if ($dryRun) {
                $this->out("    [DRY RUN] Would execute PHP seeder: " . basename($filePath));
                return;
            }

            if (is_callable($seederClass)) {
                // Function-based seeder
                $seederClass($pdo);
                $this->out("    ✓ PHP seeder completed");
            } elseif (is_object($seederClass) && method_exists($seederClass, 'run')) {
                // Class-based seeder
                $seederClass->run($pdo);
                $this->out("    ✓ PHP seeder completed");
            } else {
                throw new RuntimeException("Invalid seeder format - must return callable or object with run() method");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to run PHP seeder {$filePath}: " . $e->getMessage());
        }
    }

    /**
     * Create PDO connection to target database using Config
     *
     * @param string $databaseName Target database name
     * @return PDO
     * @throws RuntimeException If configuration missing or connection fails
     */
    private function createDatabaseConnection(string $databaseName): PDO
    {
        $section = 'database_' . $databaseName;

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

        $dsn = "{$driver}:host={$host};port={$port};dbname={$databaseName};charset={$charset}";

        return ConnectionManager::createHandle('seeder_' . $databaseName, $dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => (int)$timeout,
            PDO::ATTR_PERSISTENT => $persistent
        ]);
    }

    /**
     * Parse command line options
     */
    private function parseOptions(array $args): array
    {
        $options = [];
        $cleanArgs = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $optionKey = $parts[0];
                $value = $parts[1] ?? true;
                $options[$optionKey] = $value;
            } else {
                $cleanArgs[] = $arg;
            }
        }

        return ['options' => $options, 'args' => $cleanArgs];
    }

    /**
     * Get the default database name from configuration
     */
    private function getDefaultDatabase(): string
    {
        try {
            // Try to get default database from config
            $defaultDatabase = Config::get('default_database', 'app');

            if ($defaultDatabase !== 'undefined' && !empty($defaultDatabase)) {
                return $defaultDatabase;
            }

            // Fall back to 'default' if no default_database configured
            return 'default';

        } catch (Throwable $e) {
            throw new RuntimeException("Failed to get default database configuration: " . $e->getMessage());
        }
    }
}