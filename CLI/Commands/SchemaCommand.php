<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/SchemaCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/Commands/SchemaCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Primordyx\Database\SchemaBase;
use RuntimeException;
use ReflectionClass;
use Exception;

/**
 * CLI command for schema-based generation
 *
 * Provides commands to generate traits and migrations from schema definitions.
 * This implements the DRY principle by allowing developers to define database
 * structure once in a schema and generate all related files from that single
 * source of truth.
 *
 * ## Available Operations:
 * - Generate property traits for models
 * - Generate SQL migrations
 * - List all available schemas
 * - Process all schemas at once
 *
 * @package PrimordyxCLI
 * @since 1.0.0
 * @see SchemaBase For schema definition system
 *
 * @phpstan-type SchemaClass class-string<SchemaBase>
 */
class SchemaCommand extends AbstractCommand
{
    /**
     * Default schema directory path relative to project root
     * @var string
     */
    private const DEFAULT_SCHEMA_PATH = 'app/Schemas';

    /**
     * Get command name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'schema';
    }

    /**
     * Get command description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Generate traits and migrations from schema definitions';
    }

    /**
     * Get detailed help text
     *
     * @return string
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx schema trait <SchemaName>      Generate trait for a specific schema
  primordyx schema trait --all              Generate traits for all schemas
  primordyx schema migration <SchemaName>   Generate migration for a specific schema
  primordyx schema migration --all          Generate migrations for all schemas
  primordyx schema generate <SchemaName>    Generate both trait and migration
  primordyx schema generate --all           Generate everything for all schemas
  primordyx schema list                     List all available schemas
  primordyx schema status <SchemaName>      Check status of schema vs database

Examples:
  primordyx schema trait UserSchema
  primordyx schema migration ProductSchema
  primordyx schema generate UserSchema
  primordyx schema generate --all
  primordyx schema list

Options:
  --path <directory>    Specify custom schema directory (default: app/Schemas)
  --force              Overwrite existing files without confirmation
  --dry-run            Show what would be generated without creating files
  --verbose            Show detailed output during generation

Notes:
  - Schemas must extend Primordyx\\Database\\SchemaBase
  - Schema class names must end with 'Schema'
  - Traits are generated at app/Models/Traits/{ModelName}Properties.php
  - Migrations are generated at migrations/{timestamp}_{action}_{table}.sql
  - Use 'schema generate --all' to regenerate everything after schema changes";
    }

    /**
     * Execute the command
     *
     * @param array<string> $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        // Check for help
        if (in_array('--help', $args) || in_array('-h', $args) || empty($args)) {
            $this->out($this->getDetailedHelp());
            return;
        }

        // Parse options
        $options = $this->parseOptions($args);
        $action = array_shift($args);

        // Route to appropriate action
        switch ($action) {
            case 'trait':
                $this->handleTraitGeneration($args, $options);
                break;

            case 'migration':
                $this->handleMigrationGeneration($args, $options);
                break;

            case 'generate':
                $this->handleFullGeneration($args, $options);
                break;

            case 'list':
                $this->handleListSchemas($options);
                break;

            case 'status':
                $this->handleSchemaStatus($args, $options);
                break;

            default:
                $this->error("Unknown schema action: {$action}. Use 'primordyx schema --help' for usage.");
        }
    }

    /**
     * Parse command options from arguments
     *
     * @param array<string> $args Arguments array (modified by reference)
     * @return array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool}
     */
    private function parseOptions(array &$args): array
    {
        $options = [
            'path' => self::DEFAULT_SCHEMA_PATH,
            'force' => false,
            'dry-run' => false,
            'verbose' => false,
            'all' => false,
        ];

        $newArgs = [];
        $skipNext = false;

        for ($i = 0; $i < count($args); $i++) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $arg = $args[$i];

            if ($arg === '--path' && isset($args[$i + 1])) {
                $options['path'] = $args[$i + 1];
                $skipNext = true;
            } elseif ($arg === '--force') {
                $options['force'] = true;
            } elseif ($arg === '--dry-run') {
                $options['dry-run'] = true;
            } elseif ($arg === '--verbose') {
                $options['verbose'] = true;
            } elseif ($arg === '--all') {
                $options['all'] = true;
            } else {
                $newArgs[] = $arg;
            }
        }

        $args = $newArgs;
        return $options;
    }

    /**
     * Handle trait generation command
     *
     * @param array<string> $args Command arguments
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function handleTraitGeneration(array $args, array $options): void
    {
        if ($options['all']) {
            $this->generateAllTraits($options);
        } elseif (!empty($args[0])) {
            $this->generateSingleTrait($args[0], $options);
        } else {
            $this->error("Please specify a schema name or use --all flag");
        }
    }

    /**
     * Handle migration generation command
     *
     * @param array<string> $args Command arguments
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function handleMigrationGeneration(array $args, array $options): void
    {
        if ($options['all']) {
            $this->generateAllMigrations($options);
        } elseif (!empty($args[0])) {
            $this->generateSingleMigration($args[0], $options);
        } else {
            $this->error("Please specify a schema name or use --all flag");
        }
    }

    /**
     * Handle full generation (trait + migration)
     *
     * @param array<string> $args Command arguments
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function handleFullGeneration(array $args, array $options): void
    {
        if ($options['all']) {
            $this->out("Generating all traits and migrations...\n");
            $this->generateAllTraits($options);
            $this->out("");  // Empty line for separation
            $this->generateAllMigrations($options);
        } elseif (!empty($args[0])) {
            $schemaName = $args[0];
            $this->out("Generating trait and migration for {$schemaName}...\n");
            $this->generateSingleTrait($schemaName, $options);
            $this->generateSingleMigration($schemaName, $options);
        } else {
            $this->error("Please specify a schema name or use --all flag");
        }
    }

    /**
     * Generate trait for a single schema
     *
     * @param string $schemaName Schema class name
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function generateSingleTrait(string $schemaName, array $options): void
    {
        /** @var SchemaClass $schemaClass */
        $schemaClass = $this->resolveSchemaClass($schemaName, $options['path']);

        if ($options['dry-run']) {
            $this->out("[DRY RUN] Would generate trait for: {$schemaClass}");
            return;
        }

        try {
            if ($options['verbose']) {
                $this->out("Generating trait for {$schemaClass}...");
            }

            // Use call_user_func to avoid IDE warnings about string method calls
            /** @var bool $success */
            $success = call_user_func([$schemaClass, 'generateTrait']);

            if ($success) {
                $modelName = $this->getModelNameFromSchema($schemaName);
                $this->out("✓ Generated trait: app/Models/Traits/{$modelName}Properties.php");
            } else {
                $this->out("✗ Failed to generate trait for {$schemaClass}");
            }
        } catch (RuntimeException $e) {
            $this->out("✗ Error generating trait for {$schemaClass}: " . $e->getMessage());
        }
    }

    /**
     * Generate migration for a single schema
     *
     * @param string $schemaName Schema class name
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function generateSingleMigration(string $schemaName, array $options): void
    {
        /** @var SchemaClass $schemaClass */
        $schemaClass = $this->resolveSchemaClass($schemaName, $options['path']);

        if ($options['dry-run']) {
            $this->out("[DRY RUN] Would generate migration for: {$schemaClass}");
            return;
        }

        try {
            if ($options['verbose']) {
                $this->out("Generating migration for {$schemaClass}...");
            }

            // Use call_user_func to avoid IDE warnings about string method calls
            /** @var string $filepath */
            $filepath = call_user_func([$schemaClass, 'writeMigration']);

            $this->out("✓ Generated migration: {$filepath}");
        } catch (RuntimeException $e) {
            $this->out("✗ Error generating migration for {$schemaClass}: " . $e->getMessage());
        }
    }

    /**
     * Generate traits for all schemas
     *
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function generateAllTraits(array $options): void
    {
        $schemas = $this->discoverSchemas($options['path']);

        if (empty($schemas)) {
            $this->out("No schemas found in {$options['path']}");
            return;
        }

        $this->out("Generating traits for " . count($schemas) . " schema(s)...\n");

        $success = 0;
        $failed = 0;

        foreach ($schemas as $schemaClass) {
            $schemaName = $this->getShortClassName($schemaClass);

            if ($options['dry-run']) {
                $this->out("[DRY RUN] Would generate trait for: {$schemaName}");
                continue;
            }

            try {
                if ($options['verbose']) {
                    $this->out("Processing {$schemaName}...");
                }

                // Use call_user_func to avoid IDE warnings
                /** @var SchemaClass $schemaClass */
                /** @var bool $result */
                $result = call_user_func([$schemaClass, 'generateTrait']);

                if ($result) {
                    $modelName = $this->getModelNameFromSchema($schemaName);
                    $this->out("✓ {$schemaName} → app/Models/Traits/{$modelName}Properties.php");
                    $success++;
                } else {
                    $this->out("✗ {$schemaName} - Generation failed");
                    $failed++;
                }
            } catch (RuntimeException $e) {
                $this->out("✗ {$schemaName} - Error: " . $e->getMessage());
                $failed++;
            }
        }

        if (!$options['dry-run']) {
            $this->out("\nSummary: {$success} succeeded, {$failed} failed");
        }
    }

    /**
     * Generate migrations for all schemas
     *
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function generateAllMigrations(array $options): void
    {
        $schemas = $this->discoverSchemas($options['path']);

        if (empty($schemas)) {
            $this->out("No schemas found in {$options['path']}");
            return;
        }

        $this->out("Generating migrations for " . count($schemas) . " schema(s)...\n");

        $success = 0;
        $failed = 0;

        foreach ($schemas as $schemaClass) {
            $schemaName = $this->getShortClassName($schemaClass);

            if ($options['dry-run']) {
                $this->out("[DRY RUN] Would generate migration for: {$schemaName}");
                continue;
            }

            try {
                if ($options['verbose']) {
                    $this->out("Processing {$schemaName}...");
                }

                // Use call_user_func to avoid IDE warnings
                /** @var SchemaClass $schemaClass */
                /** @var string $filepath */
                $filepath = call_user_func([$schemaClass, 'writeMigration']);

                $filename = basename($filepath);
                $this->out("✓ {$schemaName} → migrations/{$filename}");
                $success++;
            } catch (RuntimeException $e) {
                $this->out("✗ {$schemaName} - Error: " . $e->getMessage());
                $failed++;
            }
        }

        if (!$options['dry-run']) {
            $this->out("\nSummary: {$success} succeeded, {$failed} failed");
        }
    }

    /**
     * Handle listing all schemas
     *
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function handleListSchemas(array $options): void
    {
        $schemas = $this->discoverSchemas($options['path']);

        if (empty($schemas)) {
            $this->out("No schemas found in {$options['path']}");
            return;
        }

        $this->out("Available schemas in {$options['path']}:\n");

        foreach ($schemas as $schemaClass) {
            $schemaName = $this->getShortClassName($schemaClass);
            $modelName = $this->getModelNameFromSchema($schemaName);

            // Try to get table name
            try {
                /** @var SchemaClass $schemaClass */
                if (!class_exists($schemaClass)) {
                    $this->out("  • {$schemaName} (class not found)");
                    continue;
                }

                /** @var SchemaBase $instance */
                $instance = new $schemaClass();
                $reflection = new ReflectionClass($instance);
                $tableProperty = $reflection->getProperty('table');
                $tableProperty->setAccessible(true);
                $tableName = $tableProperty->getValue($instance);

                $this->out("  • {$schemaName}");
                $this->out("    Model: {$modelName}");
                $this->out("    Table: {$tableName}");
                $this->out("");
            } catch (Exception $e) {
                $this->out("  • {$schemaName} (unable to read details)");
            }
        }

        $this->out("Total: " . count($schemas) . " schema(s)");
        $this->out("\nUse 'primordyx schema generate --all' to regenerate all files");
    }

    /**
     * Handle schema status check
     *
     * @param array<string> $args Command arguments
     * @param array{path: string, force: bool, dry-run: bool, verbose: bool, all: bool} $options Command options
     * @return void
     */
    private function handleSchemaStatus(array $args, array $options): void
    {
        if (empty($args[0])) {
            $this->error("Please specify a schema name");
        }

        $schemaName = $args[0];
        $schemaClass = $this->resolveSchemaClass($schemaName, $options['path']);

        $this->out("Schema Status: {$schemaName}\n");

        // Check trait existence
        $modelName = $this->getModelNameFromSchema($schemaName);
        $traitPath = getcwd() . "/app/Models/Traits/{$modelName}Properties.php";

        if (file_exists($traitPath)) {
            $traitModTime = date('Y-m-d H:i:s', filemtime($traitPath));
            $this->out("✓ Trait exists: app/Models/Traits/{$modelName}Properties.php");
            $this->out("  Last modified: {$traitModTime}");
        } else {
            $this->out("✗ Trait missing: app/Models/Traits/{$modelName}Properties.php");
        }

        // Check for migrations
        $migrationDir = getcwd() . '/migrations';
        if (is_dir($migrationDir)) {
            /** @var SchemaClass $schemaClass */
            if (class_exists($schemaClass)) {
                /** @var SchemaBase $instance */
                $instance = new $schemaClass();
                $reflection = new ReflectionClass($instance);
                $tableProperty = $reflection->getProperty('table');
                $tableProperty->setAccessible(true);
                /** @var string $tableName */
                $tableName = $tableProperty->getValue($instance);

                $migrations = glob("{$migrationDir}/*_{$tableName}*.sql");
                if (!empty($migrations)) {
                    $this->out("\n✓ Migrations found:");
                    foreach ($migrations as $migration) {
                        $this->out("  - " . basename($migration));
                    }
                } else {
                    $this->out("\n✗ No migrations found for table: {$tableName}");
                }
            }
        }

        $this->out("\nRun 'primordyx schema generate {$schemaName}' to regenerate files");
    }

    /**
     * Discover all schema classes in a directory
     *
     * @param string $path Directory path to search
     * @return array<SchemaClass> Array of fully qualified schema class names
     * @phpstan-return array<SchemaClass>
     */
    private function discoverSchemas(string $path): array
    {
        $fullPath = getcwd() . '/' . $path;

        if (!is_dir($fullPath)) {
            return [];
        }

        // Use SchemaBase's discovery method
        /** @var array<SchemaClass> $schemas */
        $schemas = SchemaBase::discoverSchemas($path);
        return $schemas;
    }

    /**
     * Resolve schema class name with namespace
     *
     * @param string $schemaName Schema class name (with or without namespace)
     * @param string $path Schema directory path
     * @return string Fully qualified class name
     * @throws RuntimeException If schema class not found
     * @phpstan-return SchemaClass
     */
    private function resolveSchemaClass(string $schemaName, string $path): string
    {
        // If already fully qualified, return as is
        if (strpos($schemaName, '\\') !== false) {
            if (class_exists($schemaName) && is_subclass_of($schemaName, SchemaBase::class)) {
                /** @var SchemaClass $schemaName */
                return $schemaName;
            }
            throw new RuntimeException("Schema class not found: {$schemaName}");
        }

        // Try to find the schema
        $schemas = $this->discoverSchemas($path);

        foreach ($schemas as $schemaClass) {
            if ($this->getShortClassName($schemaClass) === $schemaName) {
                return $schemaClass;
            }
        }

        // Try with default namespace
        $defaultClass = 'App\\Schemas\\' . $schemaName;
        if (class_exists($defaultClass) && is_subclass_of($defaultClass, SchemaBase::class)) {
            /** @var SchemaClass $defaultClass */
            return $defaultClass;
        }

        throw new RuntimeException("Schema class not found: {$schemaName}");
    }

    /**
     * Get short class name from fully qualified name
     *
     * @param string $fullyQualifiedName Full class name with namespace
     * @return string Short class name
     */
    private function getShortClassName(string $fullyQualifiedName): string
    {
        $parts = explode('\\', $fullyQualifiedName);
        return array_pop($parts) ?? '';
    }

    /**
     * Get model name from schema name
     *
     * @param string $schemaName Schema class name
     * @return string Model class name
     */
    private function getModelNameFromSchema(string $schemaName): string
    {
        // Remove "Schema" suffix if present
        if (str_ends_with($schemaName, 'Schema')) {
            return substr($schemaName, 0, -6);
        }
        return $schemaName;
    }
}