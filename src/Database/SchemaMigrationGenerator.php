<?php
/**
 * File: /vendor/vernsix/primordyx/src/Database/SchemaMigrationGenerator.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/SchemaMigrationGenerator.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

/**
 * MySQL SQL generation for schema-based migrations
 *
 * Generates CREATE TABLE and ALTER TABLE statements for MySQL based on schema
 * definitions. This class is responsible for converting SchemaField definitions
 * into proper MySQL DDL (Data Definition Language) statements.
 *
 * ## Key Features:
 * - Generate CREATE TABLE with columns, keys, and indexes
 * - Generate ALTER TABLE for adding, modifying, or dropping columns
 * - Support for all MySQL column types and modifiers
 * - Generate rollback SQL for migrations
 * - Proper handling of indexes and constraints
 *
 * ## Generated SQL:
 * - Uses backticks for identifiers
 * - InnoDB engine with utf8mb4 charset
 * - Supports composite operations in single ALTER TABLE
 *
 * @example
 * ```php
 * $generator = new SchemaMigrationGenerator();
 *
 * // Generate CREATE TABLE
 * $sql = $generator->generateCreateTable('users', $fields);
 *
 * // Generate ALTER TABLE
 * $changes = [
 *     'add' => ['email' => $emailField],
 *     'modify' => ['name' => $nameField],
 *     'drop' => ['old_column']
 * ];
 * $sql = $generator->generateAlterTable('users', $changes);
 * ```
 *
 * @package Primordyx\Database
 * @since 1.0.0
 * @see SchemaBase For usage in schema system
 * @see SchemaField For field definitions
 */
class SchemaMigrationGenerator
{
    /**
     * Connection name for ConnectionManager
     *
     * Specifies which database connection to use.
     * Currently not actively used but reserved for future multi-connection support.
     *
     * @var string
     */
    private string $connectionName = 'default';

    /**
     * Constructor
     *
     * @param string $connectionName  Connection name from ConnectionManager (default: 'default')
     *
     * @example
     * ```php
     * // Use default connection
     * $generator = new SchemaMigrationGenerator();
     *
     * // Use specific connection
     * $generator = new SchemaMigrationGenerator('reporting');
     * ```
     */
    public function __construct(string $connectionName = 'default')
    {
        $this->connectionName = $connectionName;
    }

    /**
     * Generate CREATE TABLE statement for MySQL
     *
     * Creates a complete CREATE TABLE statement including:
     * - Column definitions with types and modifiers
     * - Primary key constraints
     * - Unique key constraints
     * - Regular indexes
     * - InnoDB engine specification
     * - utf8mb4 character set and collation
     *
     * @param string $table                     Table name to create
     * @param array<string, SchemaField> $fields  Field definitions keyed by column name
     * @return string                           Complete CREATE TABLE SQL statement
     *
     * @example
     * ```php
     * $fields = [
     *     'id' => SchemaField::int('id')->primaryKey()->autoIncrement(),
     *     'email' => SchemaField::string('email', 255)->unique()->required(),
     *     'created_at' => SchemaField::datetime('created_at')->default('CURRENT_TIMESTAMP')
     * ];
     *
     * $sql = $generator->generateCreateTable('users', $fields);
     * // Output:
     * // CREATE TABLE `users` (
     * //     `id` INT NOT NULL AUTO_INCREMENT,
     * //     `email` VARCHAR(255) NOT NULL,
     * //     `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
     * //     PRIMARY KEY (`id`),
     * //     UNIQUE KEY `unique_email` (`email`)
     * // ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
     * ```
     */
    public function generateCreateTable(string $table, array $fields): string
    {
        $sql = "CREATE TABLE `{$table}` (\n";
        $columns = [];
        $primaryKeys = [];
        $uniqueKeys = [];
        $indexes = [];

        // Generate column definitions
        foreach ($fields as $name => $field) {
            $columns[] = "    " . $field->toSqlColumn($name);

            // Track keys and indexes
            if ($field->isPrimaryKey()) {
                $primaryKeys[] = "`{$name}`";
            }

            $modifiers = $field->getModifiers();
            if ($field->isUnique() && !$field->isPrimaryKey()) {
                $indexName = is_string($modifiers['unique']) ? $modifiers['unique'] : "unique_{$name}";
                $uniqueKeys[$indexName][] = "`{$name}`";
            }

            if ($field->hasIndex() && !$field->isPrimaryKey() && !$field->isUnique()) {
                $indexName = is_string($modifiers['index']) ? $modifiers['index'] : "idx_{$name}";
                $indexes[$indexName][] = "`{$name}`";
            }
        }

        // Add columns to SQL
        $sql .= implode(",\n", $columns);

        // Add primary key
        if (!empty($primaryKeys)) {
            $sql .= ",\n    PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }

        // Add unique keys
        foreach ($uniqueKeys as $indexName => $cols) {
            $sql .= ",\n    UNIQUE KEY `{$indexName}` (" . implode(', ', $cols) . ")";
        }

        // Add regular indexes
        foreach ($indexes as $indexName => $cols) {
            $sql .= ",\n    KEY `{$indexName}` (" . implode(', ', $cols) . ")";
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";

        return $sql;
    }

    /**
     * Generate ALTER TABLE statement for MySQL
     *
     * Creates ALTER TABLE statements for modifying existing tables.
     * Supports multiple operations in a single statement for efficiency.
     *
     * ## Supported Operations:
     * - ADD COLUMN: Add new columns with full definitions
     * - MODIFY COLUMN: Change existing column definitions
     * - DROP COLUMN: Remove columns from table
     * - ADD INDEX: Create new indexes on added columns
     *
     * @param string $table   Table name to alter
     * @param array $changes  Changes array with 'add', 'modify', 'drop' keys
     * @return string         ALTER TABLE SQL statement(s) or empty string if no changes
     *
     * @example
     * ```php
     * $changes = [
     *     'add' => [
     *         'email' => SchemaField::string('email', 255)->unique(),
     *         'age' => SchemaField::int('age')->nullable()
     *     ],
     *     'modify' => [
     *         'name' => SchemaField::string('name', 200)->required()
     *     ],
     *     'drop' => ['old_column', 'unused_field']
     * ];
     *
     * $sql = $generator->generateAlterTable('users', $changes);
     * // Output:
     * // ALTER TABLE `users`
     * //     ADD COLUMN `email` VARCHAR(255) NOT NULL,
     * //     ADD UNIQUE KEY `unique_email` (`email`),
     * //     ADD COLUMN `age` INT NULL,
     * //     MODIFY COLUMN `name` VARCHAR(200) NOT NULL,
     * //     DROP COLUMN `old_column`,
     * //     DROP COLUMN `unused_field`;
     * ```
     *
     * @param array{
     *     add?: array<string, SchemaField>,
     *     modify?: array<string, SchemaField>,
     *     drop?: array<string>
     * } $changes
     */
    public function generateAlterTable(string $table, array $changes): string
    {
        $alterations = [];

        // Add new columns
        if (!empty($changes['add'])) {
            foreach ($changes['add'] as $name => $field) {
                $alterations[] = "ADD COLUMN " . $field->toSqlColumn($name);

                // Add indexes for new columns
                if ($field->isUnique()) {
                    $modifiers = $field->getModifiers();
                    $indexName = is_string($modifiers['unique']) ? $modifiers['unique'] : "unique_{$name}";
                    $alterations[] = "ADD UNIQUE KEY `{$indexName}` (`{$name}`)";
                } elseif ($field->hasIndex()) {
                    $modifiers = $field->getModifiers();
                    $indexName = is_string($modifiers['index']) ? $modifiers['index'] : "idx_{$name}";
                    $alterations[] = "ADD KEY `{$indexName}` (`{$name}`)";
                }
            }
        }

        // Modify existing columns
        if (!empty($changes['modify'])) {
            foreach ($changes['modify'] as $name => $field) {
                $alterations[] = "MODIFY COLUMN " . $field->toSqlColumn($name);
            }
        }

        // Drop columns
        if (!empty($changes['drop'])) {
            foreach ($changes['drop'] as $columnName) {
                $alterations[] = "DROP COLUMN `{$columnName}`";
            }
        }

        if (empty($alterations)) {
            return '';
        }

        // MySQL supports multiple alterations in one statement
        $sql = "ALTER TABLE `{$table}`\n    ";
        $sql .= implode(",\n    ", $alterations);
        $sql .= ";\n";

        return $sql;
    }

    /**
     * Generate migration rollback SQL
     *
     * Creates the inverse operation for a migration. This can be used
     * to undo a migration if needed.
     *
     * ## Rollback Logic:
     * - CREATE TABLE → DROP TABLE
     * - ADD COLUMN → DROP COLUMN
     * - DROP COLUMN → ADD COLUMN (requires original definition)
     * - MODIFY COLUMN → Restore original (not implemented)
     *
     * @param string $table    Table name
     * @param array  $changes  Original changes that were applied
     * @param string $type     Migration type ('CREATE' or 'ALTER')
     * @return string          Rollback SQL statement
     *
     * @example
     * ```php
     * // Rollback for CREATE TABLE
     * $rollback = $generator->generateRollback('users', [], 'CREATE');
     * // Output: DROP TABLE IF EXISTS `users`;
     *
     * // Rollback for ALTER TABLE
     * $changes = [
     *     'add' => ['email' => $emailField],
     *     'drop' => ['old_field' => $oldField]
     * ];
     * $rollback = $generator->generateRollback('users', $changes, 'ALTER');
     * // Will drop 'email' and re-add 'old_field'
     * ```
     *
     * @note MODIFY operations cannot be fully rolled back without storing
     *       the original column definitions
     */
    public function generateRollback(string $table, array $changes, string $type): string
    {
        if ($type === 'CREATE') {
            // Rollback for CREATE TABLE is DROP TABLE
            return "DROP TABLE IF EXISTS `{$table}`;\n";
        }

        if ($type === 'ALTER') {
            // Rollback for ALTER TABLE is the inverse operations
            $inverseChanges = [
                'add' => $changes['drop'] ?? [],  // Drop what was added
                'drop' => $changes['add'] ?? [],  // Add what was dropped
                'modify' => []  // Would need original column definitions
            ];

            return $this->generateAlterTable($table, $inverseChanges);
        }

        return '';
    }
}