<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/MigrateCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/MigrateCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use Throwable;
use PDOException;

class MigrateCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'migrate';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Run database migrations';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Database Migration Management

Usage:
  primordyx migrate subcommand [options]

Subcommands:
  up [--continue-from=name]    Run all pending migrations
  down [--batch=number]        Rollback the last batch of migrations
  rollback [--batch=number]    Alias for 'down'
  status                       Show migration status
  reset                        Rollback ALL migrations (with confirmation)
  fake [migration]             Mark migrations as executed without running them
  create name --type=type      Create new migration files
  templates [--verbose]        Show all available migration templates

Options:
  --database=name         Target database name (required for create command)
  --dry-run               Preview actions without making changes
  --batch=number          Rollback specific batch number (down/rollback only)
  --continue-from=name    Continue migrations starting from specific migration (up only)
  --help, -h              Show help for specific subcommand

Migration Creation:
  --type=type             Required: Migration type (see available types)
  --output-folder=path, --migrations-folder=path    Override migrations path from database config
  --var=value             Additional template variables

Examples:
  primordyx migrate status
  primordyx migrate templates                 # Show available templates
  primordyx migrate templates --verbose       # Show detailed template info
  primordyx migrate up
  primordyx migrate up --continue-from=003_add_users_table
  primordyx migrate down
  primordyx migrate rollback --batch=3
  primordyx migrate reset
  primordyx migrate fake                    # Mark ALL pending as executed
  primordyx migrate fake 001_create_users   # Mark specific migration
  primordyx migrate create create_users_table --type=create_table --database=myapp
  primordyx migrate create add_email_to_users --type=add_column --column=email --database=myapp
  primordyx migrate create add_user --type=add_user --username=app_user --database=myapp

Recovery Options:
  When migrations fail, you'll be prompted with recovery options:
  
  For recoverable errors (like 'already exists'):
  - Continue: Skip the failed migration and continue with remaining ones
  - Skip and mark as completed: Mark the migration as executed and continue
  - Abort: Stop the migration process
  - View SQL: Display the SQL that failed for inspection
  
  For non-recoverable errors:
  - Abort: Stop the migration process (recommended)
  - View SQL: Display the SQL that failed for inspection  
  - Continue: Skip without marking as completed (migration remains pending)
  - Force and mark completed: Mark as executed despite failure (dangerous)

Notes:
  - Migrations are tracked in JSON file (.primordyx/migrations.json)
  - Files use format: {number}_{name}_up.sql and {number}_{name}_down.sql
  - Migration path is configured in database configuration
  - Use 'primordyx migrate create --help' for detailed creation options
  - For recoverable errors (like 'already exists'), you can choose to continue safely";
    }

    /**
     * Execute the migrate command
     *
     * @param array $args Command line arguments
     * @return void
     * @throws Exception If migration execution fails
     * @throws RuntimeException If configuration is invalid
     * @throws InvalidArgumentException If arguments are invalid
     * @throws Throwable If unexpected error occurs
     */
    public function execute(array $args): void
    {
        // Check for --help
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->out($this->getDetailedHelp());
            return;
        }

        // Extract subcommand
        $subcommand = array_shift($args) ?? 'help';

        try {
            match ($subcommand) {
                'up' => $this->runUp($args),
                'down', 'rollback' => $this->runDown($args),
                'status' => $this->runStatus($args),
                'reset' => $this->runReset($args),
                'fake' => $this->runFake($args),
                'create' => $this->runCreate($args),
                'templates' => $this->runTemplates($args),
                default => $this->error("Unknown subcommand: $subcommand\nRun 'primordyx migrate --help' for usage.")
            };
        } catch (PDOException $e) {
            $this->error("Database error: " . $e->getMessage());
        } catch (RuntimeException $e) {
            $this->error("Configuration error: " . $e->getMessage());
        } catch (InvalidArgumentException $e) {
            $this->error("Invalid argument: " . $e->getMessage());
        } catch (Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Run pending migrations
     *
     * @param array $args
     * @return void
     * @throws Exception If migration execution fails
     * @throws Throwable If unexpected error occurs
     */
    private function runUp(array $args): void
    {
        $options = $this->parseOptions($args);
        $databaseName = $options['database'] ?? 'default';
        $dryRun = isset($options['dry-run']);
        $continueFrom = $options['continue-from'] ?? null;
        $migrationsPath = $this->getMigrationsPath($options);

        $runner = new MigrationRunner($migrationsPath, $databaseName);

        if ($dryRun) {
            $runner->dryRun(true);
            $this->out("🔍 DRY RUN MODE - No migrations will be executed\n");
        }

        if ($continueFrom) {
            $this->out("Continuing migrations from: {$continueFrom}\n");
            $count = $runner->continueFromMigration($continueFrom);
        } else {
            $count = $runner->runPending();
        }

        if ($count === 0 && !$continueFrom) {
            $this->out("No pending migrations.");
        }
    }

    /**
     * Rollback migrations
     *
     * @param array $args
     * @return void
     * @throws Exception If rollback fails
     * @throws Throwable If unexpected error occurs
     */
    private function runDown(array $args): void
    {
        $options = $this->parseOptions($args);
        $databaseName = $options['database'] ?? 'default';
        $dryRun = isset($options['dry-run']);
        $migrationsPath = $this->getMigrationsPath($options);

        $runner = new MigrationRunner($migrationsPath, $databaseName);

        if ($dryRun) {
            $runner->dryRun(true);
            $this->out("🔍 DRY RUN MODE - No migrations will be rolled back\n");
        }

        if (isset($options['batch'])) {
            $batch = (int)$options['batch'];
            $runner->rollbackBatch($batch);
        } else {
            $runner->rollbackLast();
        }
    }

    /**
     * Show migration status
     *
     * @param array $args
     * @return void
     * @throws Exception If migration execution fails
     * @throws RuntimeException If configuration is invalid
     * @throws InvalidArgumentException If arguments are invalid
     * @throws Throwable If unexpected error occurs
     */
    private function runStatus(array $args): void
    {
        $options = $this->parseOptions($args);
        $databaseName = $options['database'] ?? 'default';

        try {
            if (!class_exists('MigrationRunner')) {
                $this->error("MigrationRunner class not found. Please ensure CLI/MigrationRunner.php exists.");
            }

            $migrationsPath = $this->getMigrationsPath($options);
            $runner = new MigrationRunner($migrationsPath, $databaseName);
            $runner->displayStatus();

        } catch (RuntimeException $e) {
            $this->error("Configuration error: " . $e->getMessage());
        } catch (InvalidArgumentException $e) {
            $this->error("Invalid argument: " . $e->getMessage());
        } catch (Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Show available migration templates
     *
     * @param array $args
     * @return void
     * @throws Exception If template discovery fails
     * @throws RuntimeException If MigrationGenerator cannot be instantiated
     * @throws Throwable If unexpected error occurs
     */
    private function runTemplates(array $args): void
    {
        // Check for --help
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->out("Show Available Migration Templates

Usage:
  primordyx migrate templates [options]

Options:
  --verbose, -v    Show detailed template information and file paths

Examples:
  primordyx migrate templates           Show available template types
  primordyx migrate templates -v       Show detailed template information

Notes:
  - Templates are stored in CLI/Templates/Migrations directory
  - Each type requires both _up.sql and _down.sql files
  - Use these types with 'primordyx migrate create --type=TYPE'");
            return;
        }

        $verbose = in_array('--verbose', $args) || in_array('-v', $args);

        try {
            if (!class_exists('MigrationGenerator')) {
                $this->error("MigrationGenerator class not found. Please ensure CLI/Generators/MigrationGenerator.php exists.");
            }

            $templatesPath = dirname(__DIR__) . '/Templates/Migrations';

            if (!is_dir($templatesPath)) {
                $this->error("Templates directory not found: {$templatesPath}");
            }

            $generator = new MigrationGenerator($templatesPath);
            $availableTypes = $generator->getAvailableTypes();

            if (empty($availableTypes)) {
                $this->out("No migration templates found in: {$templatesPath}");
                $this->out("\nTemplates should be named like: type_name_up.sql and type_name_down.sql");
                return;
            }

            $this->out("Available Migration Templates:");
            $this->out("=============================");
            $this->out("Templates path: {$templatesPath}");
            $this->out("Found " . count($availableTypes) . " template type(s):\n");

            foreach ($availableTypes as $type) {
                $this->out("• {$type}");

                if ($verbose) {
                    $upFile = $templatesPath . '/' . $type . '_up.sql';
                    $downFile = $templatesPath . '/' . $type . '_down.sql';

                    $this->out("  Up file:   " . basename($upFile) . (file_exists($upFile) ? " ✓" : " ✗"));
                    $this->out("  Down file: " . basename($downFile) . (file_exists($downFile) ? " ✓" : " ✗"));

                    // Show template content preview if files exist
                    if (file_exists($upFile)) {
                        $content = file_get_contents($upFile);
                        $preview = substr($content, 0, 200);
                        if (strlen($content) > 200) {
                            $preview .= "...";
                        }
                        $this->out("  Preview:   " . trim(str_replace(["\n", "\r"], " ", $preview)));
                    }
                    $this->out("");
                }
            }

            if (!$verbose) {
                $this->out("\nUsage Examples:");
                foreach (array_slice($availableTypes, 0, 3) as $type) {
                    $this->out("  primordyx migrate create my_migration --type={$type} --database=myapp");
                }
                if (count($availableTypes) > 3) {
                    $this->out("  ... and " . (count($availableTypes) - 3) . " more types");
                }
                $this->out("\nUse --verbose for detailed template information");
            }

        } catch (RuntimeException $e) {
            $this->error("Configuration error: " . $e->getMessage());
        } catch (Exception $e) {
            $this->error("Template discovery error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Reset all migrations
     *
     * @param array $args
     * @return void
     * @throws Exception If reset fails
     * @throws Throwable If unexpected error occurs
     */
    private function runReset(array $args): void
    {
        $options = $this->parseOptions($args);
        $databaseName = $options['database'] ?? 'default';
        $dryRun = isset($options['dry-run']);
        $migrationsPath = $this->getMigrationsPath($options);

        $runner = new MigrationRunner($migrationsPath, $databaseName);

        if ($dryRun) {
            $runner->dryRun(true);
            $this->out("🔍 DRY RUN MODE - No migrations will be reset\n");
        }

        $this->out("⚠️ This will rollback ALL migrations!");
        echo "Are you sure? [y/N]: ";

        $stdin = fopen('php://stdin', 'r');
        $confirm = strtolower(trim(fgets($stdin)));
        fclose($stdin);

        if ($confirm === 'y' || $confirm === 'yes') {
            $runner->reset();
        } else {
            $this->out("Reset cancelled.");
        }
    }

    /**
     * Create new migration files
     *
     * @param array $args
     * @return void
     * @throws Exception If migration generation fails
     * @throws RuntimeException If configuration is invalid
     * @throws InvalidArgumentException If arguments are invalid
     * @throws Throwable If unexpected error occurs
     */
    private function runCreate(array $args): void
    {
        // Check for help
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->showCreateHelp();
            return;
        }

        // Parse arguments and options
        $migrationName = null;
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                $value = $parts[1] ?? true;
                $options[$key] = $value;
            } elseif (!$migrationName) {
                $migrationName = $arg;
            }
        }

        // Validate migration name
        if (empty($migrationName)) {
            $this->error("Migration name is required.\nUsage: primordyx migrate create <n> --type=<type> --database=<n>");
        }

        // Sanitize migration name
        $migrationName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $migrationName));
        if (empty($migrationName) || !preg_match('/^[a-z0-9_]+$/', $migrationName)) {
            $this->error("Invalid migration name. Use only letters, numbers, and underscores.");
        }

        // Get migration type
        $migrationType = $options['type'] ?? null;
        if (empty($migrationType)) {
            $this->error("Migration type is required. Use --type=<type>\nRun 'primordyx migrate create --help' to see available types.");
        }

        // Get target database name
        $targetDatabase = $options['database'] ?? 'default';
        if (empty($targetDatabase)) {
            $this->error("Target database name is required. Use --database=name or configure a default database.");
        }

        try {
            if (!class_exists('MigrationGenerator')) {
                $this->error("MigrationGenerator class not found. Please ensure CLI/Generators/MigrationGenerator.php exists.");
            }

            // Get migrations path from database config or command line override
            $migrationsPath = $this->getMigrationsPath($options);

            // Get templates path
            $templatesPath = dirname(__DIR__) . '/Templates/Migrations';

            $generator = new MigrationGenerator($templatesPath);

            // Configure the generator with fluent interface
            $generator->migrationsPath($migrationsPath)
                ->migrationName($migrationName)
                ->migrationType($migrationType)
                ->targetDatabase($targetDatabase);

            // Set additional template variables
            $templateVars = [];
            foreach ($options as $key => $value) {
                if (!in_array($key, ['type', 'database', 'output-folder', 'migrations-folder', 'dry-run'])) {
                    $templateVars[strtoupper($key)] = $value;
                }
            }
            if (!empty($templateVars)) {
                $generator->templateVariables($templateVars);
            }

            // Set dry run mode if requested
            if (isset($options['dry-run'])) {
                $generator->dryRun(true);
                $this->out("🔍 DRY RUN MODE - Files will not be created\n");
            }

            // Generate the migration
            $result = $generator->generate();

            if ($result) {
                if (isset($options['dry-run'])) {
                    $this->out("Migration files would be created successfully:");
                    $this->out("  - " . basename($result['up']));
                    $this->out("  - " . basename($result['down']));
                } else {
                    $this->out("Migration files created successfully:");
                    $this->out("  - " . basename($result['up']));
                    $this->out("  - " . basename($result['down']));
                    $this->out("Path: " . dirname($result['up']));
                }
            }

        } catch (RuntimeException $e) {
            $this->error("Configuration error: " . $e->getMessage());
        } catch (InvalidArgumentException $e) {
            $this->error("Invalid argument: " . $e->getMessage());
        } catch (Exception $e) {
            $this->error("Migration generation error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Show detailed help for create command
     *
     * @return void
     */
    private function showCreateHelp(): void
    {
        // Try to get available migration types
        try {
            if (class_exists('MigrationGenerator')) {
                $templatesPath = dirname(__DIR__) . '/Templates/migrations';
                $generator = new MigrationGenerator($templatesPath);
                $availableTypes = $generator->getAvailableTypes();
            } else {
                $availableTypes = ['Error: MigrationGenerator class not found'];
            }
        } catch (Exception $e) {
            $availableTypes = ['Error loading templates: ' . $e->getMessage()];
        } catch (Throwable $t) {
            $availableTypes = ['Unexpected error: ' . $t->getMessage()];
        }

        $typesText = empty($availableTypes) ? 'None available' : implode(', ', $availableTypes);

        $help = <<<HELP
Create Migration Files

Usage:
  primordyx migrate create name --type=type --database=name [options]

Arguments:
  name                    Migration name (e.g., create_users_table)

Required Options:
  --type=type            Migration type to use
  --database=name        Target database name

Optional:
  --output-folder=path, --migrations-folder=path    Override migrations path from database config
  --dry-run              Show what would be created without creating files
  --var=value            Additional template variables (uppercase in templates)

Available Types:
  {$typesText}

Examples:
  primordyx migrate create create_users_table --type=create_table --database=myapp
  primordyx migrate create add_email_to_users --type=add_column --column=email --database=myapp
  primordyx migrate create add_user --type=add_user --username=app_user --database=myapp
  primordyx migrate create --dry-run create_posts --type=create_table --database=myapp

Template Variables:
  Templates can use placeholders like {{TABLE_NAME}}, {{MIGRATION_NAME}}, {{DATABASE}}, etc.
  Additional variables can be passed as --var=value options.

See Also:
  primordyx migrate templates           Show all available template types
  primordyx migrate templates -v       Show detailed template information

HELP;

        $this->out($help);
    }

    /**
     * Parse command line options
     *
     * @param array $args
     * @return array
     */
    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                $value = $parts[1] ?? true;
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Mark migrations as executed without running them
     *
     * @param array $args
     * @return void
     * @throws Exception If marking fails
     */
    private function runFake(array $args): void
    {
        $options = $this->parseOptions($args);
        $databaseName = $options['database'] ?? 'default';
        $migrationsPath = $this->getMigrationsPath($options);

        // Extract migration name if provided
        $migrationName = null;
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                $migrationName = $arg;
                break;
            }
        }

        try {
            if (!class_exists('MigrationRunner')) {
                $this->error("MigrationRunner class not found. Please ensure CLI/MigrationRunner.php exists.");
            }

            $runner = new MigrationRunner($migrationsPath, $databaseName);

            // Get pending migrations (common to both branches)
            $pending = $runner->getPendingMigrations();

            if ($migrationName) {
                // Mark specific migration as executed
                if (!in_array($migrationName, $pending)) {
                    $this->error("Migration '{$migrationName}' is not pending or does not exist.");
                }

                $runner->markAsExecuted($migrationName);
                $this->out("Marked migration '{$migrationName}' as executed (faked).");
            } else {
                // Mark all pending migrations as executed
                if (empty($pending)) {
                    $this->out("No pending migrations to mark as executed.");
                    return;
                }

                $this->out("This will mark " . count($pending) . " pending migrations as executed without running them:");
                foreach ($pending as $migration) {
                    $this->out("  - {$migration}");
                }

                echo "\nAre you sure? [y/N]: ";
                $stdin = fopen('php://stdin', 'r');
                $confirm = strtolower(trim(fgets($stdin)));
                fclose($stdin);

                if ($confirm === 'y' || $confirm === 'yes') {
                    foreach ($pending as $migration) {
                        $runner->markAsExecuted($migration);
                    }
                    $this->out("Marked " . count($pending) . " migrations as executed (faked).");
                } else {
                    $this->out("Fake execution cancelled.");
                }
            }

        } catch (RuntimeException $e) {
            $this->error("Configuration error: " . $e->getMessage());
        } catch (InvalidArgumentException $e) {
            $this->error("Invalid argument: " . $e->getMessage());
        } catch (Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
        } catch (Throwable $t) {
            $this->error("Unexpected error: " . $t->getMessage());
        }
    }

    /**
     * Get migrations path from database config or command line override
     *
     * @param array $options Parsed command line options
     * @return string Absolute path to migrations directory
     * @throws RuntimeException If path cannot be determined or does not exist
     */
    private function getMigrationsPath(array $options): string
    {
        // Check for --migrations-folder first (preferred)
        if (isset($options['migrations-folder'])) {
            $path = $options['migrations-folder'];
            if (!$this->isAbsolute($path)) {
                $path = getcwd() . '/' . ltrim($path, '/');
            }
            if (!is_dir($path)) {
                throw new RuntimeException("Migrations folder does not exist: {$path}");
            }
            return rtrim($path, '/');
        }

        // Check for --output-folder as fallback
        if (isset($options['output-folder'])) {
            $path = $options['output-folder'];
            if (!$this->isAbsolute($path)) {
                $path = getcwd() . '/' . ltrim($path, '/');
            }
            if (!is_dir($path)) {
                throw new RuntimeException("Output folder does not exist: {$path}");
            }
            return rtrim($path, '/');
        }

        // Default to APP_ROOT . /migrations if neither arg is set
        $defaultPath = APP_ROOT . '/migrations';
        if (!is_dir($defaultPath)) {
            throw new RuntimeException("Default migrations directory does not exist: {$defaultPath}");
        }
        return rtrim($defaultPath, '/');
    }

    /**
     * Check if a path is absolute
     *
     * @param string $path Path to check
     * @return bool True if path is absolute
     */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':');
    }

}