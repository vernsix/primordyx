<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Generators/MigrationGenerator.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/Generators/MigrationGenerator.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use InvalidArgumentException;
use Primordyx\Utils\Utils;
use RuntimeException;

class MigrationGenerator
{
    private string $templatesPath;
    private ?string $migrationsPath = null;
    private ?string $migrationName = null;
    private ?string $migrationType = null;
    private array $templateVariables = [];
    private bool $dryRun = false;
    private string $targetDatabase = '';

    /**
     * MigrationGenerator constructor.
     *
     * @param string $templatesPath Path to migration templates directory
     * @throws RuntimeException If templates path does not exist
     */
    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/');

        if (!is_dir($this->templatesPath)) {
            throw new RuntimeException("Templates path does not exist: {$this->templatesPath}");
        }
    }

    /**
     * Set the migrations directory path with method chaining.
     *
     * @param string $path Absolute path to migrations directory.
     * @return static Returns self for method chaining
     * @throws InvalidArgumentException If path is empty
     * @throws RuntimeException If path does not exist or is not readable
     */
    public function migrationsPath(string $path): static
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
        return $this;
    }

    /**
     * Set the migration name with method chaining.
     *
     * @param string $name Migration name
     * @return static Returns self for method chaining
     */
    public function migrationName(string $name): static
    {
        $this->migrationName = $name;
        return $this;
    }

    /**
     * Set the migration type with method chaining.
     *
     * @param string $type Migration type
     * @return static Returns self for method chaining
     */
    public function migrationType(string $type): static
    {
        $this->migrationType = $type;
        return $this;
    }

    /**
     * Set template variables for placeholder replacement with method chaining.
     *
     * @param array $variables Template variables
     * @return static Returns self for method chaining
     */
    public function templateVariables(array $variables): static
    {
        $this->templateVariables = $variables;
        return $this;
    }

    /**
     * Enable or disable dry run mode with method chaining.
     *
     * @param bool $enable Set to true to enable dry run mode, false to disable
     * @return static Returns self for method chaining
     */
    public function dryRun(bool $enable = true): static
    {
        $this->dryRun = $enable;
        return $this;
    }

    /**
     * Set the target database name with method chaining.
     *
     * @param string $databaseName Target database name
     * @return static Returns self for method chaining
     */
    public function targetDatabase(string $databaseName): static
    {
        $this->targetDatabase = $databaseName;
        return $this;
    }

    /**
     * Get list of available migration types based on template files.
     *
     * @return array List of available migration types
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        $upFiles = glob($this->templatesPath . '/*_up.sql');

        if ($upFiles === false) {
            return [];
        }

        foreach ($upFiles as $file) {
            $basename = basename($file, '_up.sql');
            $downFile = $this->templatesPath . '/' . $basename . '_down.sql';

            if (file_exists($downFile)) {
                $types[] = $basename;
            }
        }

        sort($types);
        return $types;
    }

    /**
     * Generate migration files.
     *
     * @return array Generated file paths ['up' => path, 'down' => path]
     * @throws RuntimeException If required properties are not set
     * @throws RuntimeException If migrations directory cannot be created
     * @throws RuntimeException If template files don't exist
     * @throws RuntimeException If migration files already exist
     */
    public function generate(): array
    {
        $this->validateGeneration();

        // Get next migration number
        $pattern = $this->migrationsPath . '/###_*.sql';
        $nextNumber = Utils::getNextFileSequenceNumber($pattern);
        $migrationPrefix = sprintf('%03d', $nextNumber);

        // Build file paths
        $migrationBase = $migrationPrefix . '_' . $this->migrationName;
        $upFile = $this->migrationsPath . '/' . $migrationBase . '_up.sql';
        $downFile = $this->migrationsPath . '/' . $migrationBase . '_down.sql';

        // Check if files already exist
        if (file_exists($upFile) || file_exists($downFile)) {
            throw new RuntimeException("Migration files already exist: {$migrationBase}");
        }

        $result = ['up' => $upFile, 'down' => $downFile];

        if ($this->dryRun) {
            return $result;
        }

        // Create migrations directory if it doesn't exist
        if (!is_dir($this->migrationsPath)) {
            if (!mkdir($this->migrationsPath, 0755, true)) {
                throw new RuntimeException("Failed to create migrations directory: {$this->migrationsPath}");
            }
        }

        // Load and process templates
        $upContent = $this->loadAndProcessTemplate($this->migrationType . '_up.sql');
        $downContent = $this->loadAndProcessTemplate($this->migrationType . '_down.sql');

        // Write files
        if (file_put_contents($upFile, $upContent) === false) {
            throw new RuntimeException("Failed to write up migration file: {$upFile}");
        }

        if (file_put_contents($downFile, $downContent) === false) {
            // Clean up up file if down file fails
            unlink($upFile);
            throw new RuntimeException("Failed to write down migration file: {$downFile}");
        }

        return $result;
    }

    /**
     * Validate that all required properties are set for generation.
     *
     * @throws RuntimeException If validation fails
     */
    private function validateGeneration(): void
    {
        if (empty($this->migrationsPath)) {
            throw new RuntimeException("Migrations path is required");
        }

        if (empty($this->migrationName)) {
            throw new RuntimeException("Migration name is required");
        }

        if (empty($this->migrationType)) {
            throw new RuntimeException("Migration type is required");
        }

        // Sanitize migration name
        if (!preg_match('/^[a-z0-9_]+$/', $this->migrationName)) {
            throw new RuntimeException("Migration name must contain only lowercase letters, numbers, and underscores");
        }

        // Validate migration type exists
        $availableTypes = $this->getAvailableTypes();
        if (!in_array($this->migrationType, $availableTypes)) {
            throw new RuntimeException(
                "Unknown migration type: {$this->migrationType}\n" .
                "Available types: " . implode(', ', $availableTypes)
            );
        }
    }

    /**
     * Load template file and process placeholders.
     *
     * @param string $templateFile Template filename
     * @return string Processed template content
     * @throws RuntimeException If template file doesn't exist
     */
    private function loadAndProcessTemplate(string $templateFile): string
    {
        $templatePath = $this->templatesPath . '/' . $templateFile;

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template file not found: {$templatePath}");
        }

        $content = file_get_contents($templatePath);

        if ($content === false) {
            throw new RuntimeException("Failed to read template file: {$templatePath}");
        }

        // Add default variables
        $variables = array_merge([
            'MIGRATION_NAME' => $this->migrationName,
            'MIGRATION_TYPE' => $this->migrationType,
            'TABLE_NAME' => $this->extractTableName($this->migrationName),
            'TIMESTAMP' => date('Y-m-d H:i:s'),
            // Default values for common template variables
            'COLUMN_DEFINITIONS' => '-- Add your column definitions here',
            'ENGINE' => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATION' => 'utf8mb4_unicode_ci',
            'COLUMN_OPTIONS' => '',
            'AFTER' => '',
            'INDEX_TYPE' => '',
            'ON_DELETE' => 'RESTRICT',
            'ON_UPDATE' => 'RESTRICT',
            'HOST' => '%',
            'PRIVILEGES' => 'SELECT, INSERT, UPDATE, DELETE',
            'DATABASE' => $this->targetDatabase ?: '*',
            'DATABASE_NAME' => $this->extractTableName($this->migrationName),
            'TRIGGER_TIME' => 'AFTER',
            'TRIGGER_EVENT' => 'UPDATE',
            'TRIGGER_BODY' => '-- Add trigger logic here',
            'PROCEDURE_BODY' => '-- Add procedure logic here',
            'FUNCTION_BODY' => '-- Add function logic here',
            'RETURN_TYPE' => 'VARCHAR(255)',
            'FUNCTION_CHARACTERISTICS' => 'DETERMINISTIC',
            'PARTITION_TYPE' => 'RANGE',
            'ARCHIVE_CONDITION' => 'created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            'GENERATION_TYPE' => 'VIRTUAL',
            'NEW_CHARSET' => 'utf8mb4',
            'NEW_COLLATION' => 'utf8mb4_unicode_ci'
        ], $this->templateVariables);

        // Replace placeholders
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Extract likely table name from migration name.
     *
     * @param string $migrationName Migration name
     * @return string Extracted table name
     */
    private function extractTableName(string $migrationName): string
    {
        // Common patterns: create_users_table, add_email_to_users, drop_posts_table
        if (preg_match('/create_(.+?)_table$/', $migrationName, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(?:add|drop)_\w+_(?:to|from)_(.+)$/', $migrationName, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(?:alter|modify|update)_(.+?)(?:_table)?$/', $migrationName, $matches)) {
            return $matches[1];
        }

        // Fallback: just remove common suffixes
        return preg_replace('/_table$/', '', $migrationName);
    }
}