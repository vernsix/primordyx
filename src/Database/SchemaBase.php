<?php
/**
 * File: /vendor/vernsix/primordyx/src/Database/SchemaBase.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.1.0
 * @version     1.1.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/SchemaBase.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use RuntimeException;

/**
 * Abstract base class for database schema definitions with configurable namespaces
 *
 * Provides a self-contained schema system where each schema class can generate its own
 * trait files and migrations. Implements the DRY (Don't Repeat Yourself) principle by
 * defining database structure once and generating all artifacts from that single source
 * of truth.
 *
 * ## Key Features:
 * - Generate MySQL CREATE TABLE and ALTER TABLE statements
 * - Create typed property traits for IDE autocomplete support
 * - Extract validation rules from field definitions
 * - Provide type casting configuration for models
 * - Configurable namespace patterns for flexible project organization
 * - No CLI dependency - all methods can be called directly from PHP
 *
 * ## Naming Convention:
 * - Schema class: `UserModelSchema` (must end with "Schema")
 * - Generated model: `UserModel` (removes "Schema" suffix)
 * - Generated trait: `UserModelProperties`
 *
 * ## Namespace Configuration:
 * You can customize the namespace pattern by setting properties in your schema:
 * ```php
 * class UserModelSchema extends SchemaBase
 * {
 *     protected string $table = 'users';
 *     protected ?string $modelNamespace = 'App\\Portal\\Models';
 *     protected ?string $traitNamespace = 'App\\Portal\\Models\\Traits';
 * }
 * ```
 *
 * @example
 * ```php
 * class UserModelSchema extends SchemaBase
 * {
 *     protected string $table = 'users';
 *
 *     // Optional: Override namespaces
 *     protected ?string $modelNamespace = 'App\\Portal\\Models';
 *     protected ?string $traitNamespace = 'App\\Portal\\Models\\Traits';
 *
 *     protected function define(): array
 *     {
 *         return [
 *             'id' => $this->int()->primaryKey()->autoIncrement(),
 *             'email' => $this->string(255)->unique()->required()->email(),
 *             'active' => $this->boolean()->default(true),
 *         ];
 *     }
 * }
 *
 * // Generate trait
 * UserModelSchema::generateTrait();
 *
 * // Generate migration
 * $migration = UserModelSchema::generateMigration();
 * ```
 *
 * @package Primordyx\Database
 * @see SchemaField For field definition methods
 * @see DatabaseInspector For database introspection
 * @see SchemaMigrationGenerator For SQL generation
 */
abstract class SchemaBase
{
    /**
     * Database table name
     *
     * Must be defined in concrete schema classes.
     * This is the actual MySQL table name.
     *
     * @var string
     */
    protected string $table = '';

    /**
     * Cached field definitions
     *
     * Stores SchemaField instances after first call to getFields().
     * Prevents multiple calls to define() method.
     *
     * @var array<string, SchemaField>
     */
    private array $fields = [];

    /**
     * Override model namespace (optional)
     *
     * If set, this namespace will be used for the generated model.
     * If null, it will be derived from the schema namespace.
     *
     * @var string|null
     *
     * @example
     * ```php
     * protected ?string $modelNamespace = 'App\\Portal\\Models';
     * ```
     */
    protected ?string $modelNamespace = null;

    /**
     * Override trait namespace (optional)
     *
     * If set, this namespace will be used for the generated trait.
     * If null, it will be derived from the model namespace.
     *
     * @var string|null
     *
     * @example
     * ```php
     * protected ?string $traitNamespace = 'App\\Portal\\Models\\Traits';
     * ```
     */
    protected ?string $traitNamespace = null;

    /**
     * Define the schema fields
     *
     * Subclasses must implement this method to define their fields using
     * the field builder methods (int, string, text, etc.). The array keys
     * become the database column names.
     *
     * @return array<string, SchemaField> Field definitions keyed by column name
     *
     * @example
     * ```php
     * protected function define(): array
     * {
     *     return [
     *         'id' => $this->int()->primaryKey()->autoIncrement(),
     *         'name' => $this->string(100)->required(),
     *         'created_at' => $this->datetime()->default('CURRENT_TIMESTAMP'),
     *     ];
     * }
     * ```
     */
    abstract protected function define(): array;

    // ===== Field Builder Methods =====

    /**
     * Create an integer field
     *
     * Creates a 32-bit signed integer field.
     * Range: -2147483648 to 2147483647
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'age' => $this->int()->unsigned()->nullable()
     * 'quantity' => $this->int()->default(0)
     * ```
     */
    protected function int(): SchemaField
    {
        return new SchemaField('int');
    }

    /**
     * Create a big integer field
     *
     * Creates a 64-bit signed integer field.
     * Use for large numbers or auto-incrementing primary keys.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'id' => $this->bigInt()->primaryKey()->autoIncrement()
     * ```
     */
    protected function bigInt(): SchemaField
    {
        return new SchemaField('bigInt');
    }

    /**
     * Create a string/varchar field
     *
     * Creates a variable-length string field.
     * Maps to VARCHAR in MySQL.
     *
     * @param int $length  Maximum string length (default: 255)
     * @return SchemaField Field instance for chaining
     *
     * @example
     * ```php
     * 'email' => $this->string(255)->unique()->required()
     * 'slug' => $this->string(100)->unique()
     * ```
     */
    protected function string(int $length = 255): SchemaField
    {
        return (new SchemaField('string'))->setLength($length);
    }

    /**
     * Alias for string()
     *
     * @param int $length
     * @return SchemaField
     */
    protected function varchar(int $length = 255): SchemaField
    {
        return $this->string($length);
    }

    /**
     * Create a text field
     *
     * Creates a TEXT field for long strings.
     * Maximum length: 65,535 characters.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'description' => $this->text()->nullable()
     * 'content' => $this->text()->required()
     * ```
     */
    protected function text(): SchemaField
    {
        return new SchemaField('text');
    }

    /**
     * Create a datetime field
     *
     * Creates a DATETIME field.
     * Format: 'YYYY-MM-DD HH:MM:SS'
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'created_at' => $this->datetime()->default('CURRENT_TIMESTAMP')
     * 'updated_at' => $this->datetime()->nullable()->onUpdate()
     * ```
     */
    protected function datetime(): SchemaField
    {
        return new SchemaField('datetime');
    }

    /**
     * Create a date field
     *
     * Creates a DATE field.
     * Format: 'YYYY-MM-DD'
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'birth_date' => $this->date()->nullable()
     * ```
     */
    protected function date(): SchemaField
    {
        return new SchemaField('date');
    }

    /**
     * Create a time field
     *
     * Creates a TIME field.
     * Format: 'HH:MM:SS'
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'opening_time' => $this->time()->default('09:00:00')
     * ```
     */
    protected function time(): SchemaField
    {
        return new SchemaField('time');
    }

    /**
     * Create a timestamp field
     *
     * Creates a TIMESTAMP field.
     * Format: 'YYYY-MM-DD HH:MM:SS'
     * Range: 1970-2038 (limited by Unix timestamp)
     * Automatic timezone conversion to UTC for storage.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'created_at' => $this->timestamp()->default('CURRENT_TIMESTAMP')
     * 'updated_at' => $this->timestamp()->default('CURRENT_TIMESTAMP')->onUpdate()
     * ```
     */
    protected function timestamp(): SchemaField
    {
        return new SchemaField('timestamp');
    }

    /**
     * Create a boolean field
     *
     * Creates a BOOLEAN field (TINYINT(1) in MySQL).
     * Maps to PHP bool type.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'active' => $this->boolean()->default(true)
     * 'verified' => $this->boolean()->default(false)
     * ```
     */
    protected function boolean(): SchemaField
    {
        return new SchemaField('boolean');
    }

    /**
     * Alias for boolean()
     *
     * @return SchemaField
     */
    protected function bool(): SchemaField
    {
        return $this->boolean();
    }

    /**
     * Create a decimal field
     *
     * Creates a DECIMAL field for exact numeric values.
     * Use for monetary values or when precision is critical.
     *
     * @param int $precision  Total number of digits
     * @param int $scale      Digits after decimal point
     * @return SchemaField    Field instance for chaining
     *
     * @example
     * ```php
     * 'price' => $this->decimal(10, 2)->required()
     * 'tax_rate' => $this->decimal(5, 4)->default(0.0000)
     * ```
     */
    protected function decimal(int $precision = 10, int $scale = 2): SchemaField
    {
        return (new SchemaField('decimal'))->setPrecisionScale($precision, $scale);
    }

    /**
     * Create a float field
     *
     * Creates a FLOAT field for approximate numeric values.
     * Use when storage efficiency is more important than precision.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'latitude' => $this->float()->required()
     * ```
     */
    protected function float(): SchemaField
    {
        return new SchemaField('float');
    }

    /**
     * Create a JSON field
     *
     * Creates a JSON field for storing JSON data.
     * Maps to array type in PHP.
     *
     * @return SchemaField  Field instance for chaining
     *
     * @example
     * ```php
     * 'metadata' => $this->json()->nullable()
     * 'settings' => $this->json()->default('{}')
     * ```
     */
    protected function json(): SchemaField
    {
        return new SchemaField('json');
    }

    /**
     * Create an enum field
     *
     * Creates an ENUM field with specified allowed values.
     *
     * @param array<string> $values  Allowed values
     * @return SchemaField           Field instance for chaining
     *
     * @example
     * ```php
     * 'status' => $this->enum(['active', 'inactive', 'pending'])->default('pending')
     * 'role' => $this->enum(['admin', 'user', 'guest'])->required()
     * ```
     */
    protected function enum(array $values): SchemaField
    {
        return (new SchemaField('enum'))->setEnumValues($values);
    }

    // ===== Static Generation Methods =====

    /**
     * Generate property trait file for the model
     *
     * Creates a trait file with typed properties based on the schema definition.
     * The trait provides IDE autocomplete support for model properties.
     * Model name is derived from schema name (e.g., UserModelSchema -> UserModel).
     *
     * ## File Location:
     * - Schema: `App\Schemas\UserModelSchema`
     * - Trait: `app/Models/Traits/UserModelProperties.php`
     *
     * @return bool  True if file was created successfully
     *
     * @throws RuntimeException If schema name doesn't end with 'Schema'
     * @throws RuntimeException If unable to create directory or write file
     *
     * @example
     * ```php
     * // Generate trait at app/Models/Traits/UserModelProperties.php
     * UserModelSchema::generateTrait();
     *
     * // Use in model
     * class UserModel extends Model {
     *     use UserModelProperties;
     * }
     * ```
     */
    public static function generateTrait(): bool
    {
        $instance = new static();
        $instance->validateConfiguration();

        $fields = $instance->getFields();

        // Derive model name and namespace from schema class name
        $schemaClass = static::class;
        $schemaParts = explode('\\', $schemaClass);
        $schemaName = array_pop($schemaParts);

        // Remove "Schema" suffix to get model name
        if (!str_ends_with($schemaName, 'Schema')) {
            throw new RuntimeException("Schema class name must end with 'Schema': {$schemaName}");
        }
        $modelName = substr($schemaName, 0, -6); // Remove "Schema" suffix

        // Determine namespaces - use overrides if set, otherwise derive from schema
        if ($instance->modelNamespace !== null) {
            // Use explicitly defined model namespace
            $modelNamespace = $instance->modelNamespace;
        } else {
            // Derive model namespace from schema (default behavior)
            $schemaNamespace = implode('\\', $schemaParts);
            $modelNamespace = str_replace('\\Schemas', '\\Models', $schemaNamespace);
        }

        // Determine trait namespace
        if ($instance->traitNamespace !== null) {
            // Use explicitly defined trait namespace
            $traitNamespace = $instance->traitNamespace;
        } else {
            // Derive from model namespace (default behavior)
            $traitNamespace = $modelNamespace . '\\Traits';
        }

        // Trait name
        $traitName = $modelName . 'Properties';

        // Build trait content
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * AUTO-GENERATED - DO NOT EDIT\n";
        $content .= " * Generated from: " . static::class . "::generateTrait()\n";
        $content .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n\n";
        $content .= "namespace {$traitNamespace};\n\n";
        $content .= "trait {$traitName}\n{\n";

        // Add property declarations
        foreach ($fields as $name => $field) {
            $content .= $field->toPropertyDeclaration($name) . "\n";
        }

        $content .= "}\n";

        // Determine file path (from application root)
        $traitPath = str_replace('\\', '/', $traitNamespace);
        $traitPath = str_replace('App/', 'app/', $traitPath);
        $filePath = $traitPath . '/' . $traitName . '.php';

        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }

        // Write file
        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException("Failed to write trait file: {$filePath}");
        }

        return true;
    }

    /**
     * Generate migration SQL and metadata
     *
     * Compares schema definition with current database structure and generates
     * appropriate CREATE TABLE or ALTER TABLE statements.
     *
     * @return array{type: string, sql: ?string} Migration type and SQL
     *         - type: 'CREATE' for new table, 'ALTER' for changes, 'NONE' if no changes
     *         - sql: MySQL SQL statement or null if no changes needed
     *
     * @example
     * ```php
     * $migration = UserModelSchema::generateMigration();
     * if ($migration['type'] !== 'NONE') {
     *     echo $migration['sql'];
     * }
     * ```
     */
    public static function generateMigration(): array
    {
        $instance = new static();
        $instance->validateConfiguration();

        $inspector = new DatabaseInspector();
        $generator = new SchemaMigrationGenerator();

        // Check if table exists
        if (!$inspector->tableExists($instance->table)) {
            // Generate CREATE TABLE
            $sql = $generator->generateCreateTable($instance->table, $instance->getFields());
            return ['type' => 'CREATE', 'sql' => $sql];
        }

        // Compare with existing table
        $currentColumns = $inspector->getTableColumns($instance->table);
        $changes = $instance->compareWithDatabase($currentColumns);

        if (!empty($changes)) {
            // Generate ALTER TABLE
            $sql = $generator->generateAlterTable($instance->table, $changes);
            return ['type' => 'ALTER', 'sql' => $sql];
        }

        return ['type' => 'NONE', 'sql' => null];
    }

    /**
     * Write migration to file
     *
     * Generates migration SQL and writes it to the specified directory
     * with a timestamped filename.
     *
     * @param string $dir  Directory to write migration file (default: 'migrations')
     * @return string|null File path if migration was created, null if no changes
     *
     * @throws RuntimeException If unable to create directory or write file
     *
     * @example
     * ```php
     * // Write to default migrations directory
     * $file = UserModelSchema::writeMigration();
     *
     * // Write to custom directory
     * $file = UserModelSchema::writeMigration('database/migrations');
     * ```
     */
    public static function writeMigration(string $dir = 'migrations'): ?string
    {
        $migration = static::generateMigration();

        if ($migration['type'] === 'NONE' || $migration['sql'] === null) {
            return null;
        }

        $instance = new static();
        $timestamp = date('Y_m_d_His');

        // Determine action for filename
        $action = $migration['type'] === 'CREATE' ? 'create' : 'alter';
        $filename = "{$timestamp}_{$action}_{$instance->table}_table.sql";
        $filepath = rtrim($dir, '/') . '/' . $filename;

        // Ensure directory exists
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }

        // Add migration metadata as SQL comments
        $content = "-- Migration generated from: " . static::class . "\n";
        $content .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= "-- Type: " . $migration['type'] . "\n\n";
        $content .= $migration['sql'];

        // Write file
        if (file_put_contents($filepath, $content) === false) {
            throw new RuntimeException("Failed to write migration file: {$filepath}");
        }

        return $filepath;
    }

    /**
     * Get validation rules derived from schema
     *
     * Returns an array of validation rules suitable for use with
     * Primordyx's Validator class. Rules are extracted from field
     * definitions and modifiers.
     *
     * @return array<string, string> Field names mapped to pipe-separated validation rules
     *
     * @example
     * ```php
     * $rules = UserModelSchema::getValidationRules();
     * // Returns: [
     * //   'email' => 'required|email|unique',
     * //   'username' => 'required|min:3|max:100|unique'
     * // ]
     * ```
     */
    public static function getValidationRules(): array
    {
        $instance = new static();
        $fields = $instance->getFields();
        $rules = [];

        foreach ($fields as $name => $field) {
            $fieldRules = $field->getValidationRules();
            if (!empty($fieldRules)) {
                $rules[$name] = implode('|', $fieldRules);
            }
        }

        return $rules;
    }

    /**
     * Get type casts derived from schema
     *
     * Returns an array of type casts suitable for use with
     * Primordyx's Model class. Determines how values should
     * be cast when retrieved from the database.
     *
     * @return array<string, string> Field names mapped to cast types
     *
     * @example
     * ```php
     * $casts = UserModelSchema::getCasts();
     * // Returns: [
     * //   'active' => 'boolean',
     * //   'created_at' => 'datetime',
     * //   'metadata' => 'array'
     * // ]
     * ```
     */
    public static function getCasts(): array
    {
        $instance = new static();
        $fields = $instance->getFields();
        $casts = [];

        foreach ($fields as $name => $field) {
            $castType = $field->getCastType();
            if ($castType !== null) {
                $casts[$name] = $castType;
            }
        }

        return $casts;
    }

    /**
     * Discover all schema classes in a directory
     *
     * Utility method to find all schema classes for batch operations.
     * Searches for PHP files ending with 'Schema' that extend SchemaBase.
     *
     * @param string $path  Directory to search (default: 'app/Schemas')
     * @return array<string> Array of fully qualified schema class names
     *
     * @example
     * ```php
     * $schemas = SchemaBase::discoverSchemas('app/Schemas');
     * foreach ($schemas as $schemaClass) {
     *     $schemaClass::generateTrait();
     *     $schemaClass::writeMigration();
     * }
     * ```
     */
    public static function discoverSchemas(string $path = 'app/Schemas'): array
    {
        $schemas = [];

        if (!is_dir($path)) {
            return $schemas;
        }

        foreach (glob("{$path}/*Schema.php") as $file) {
            $content = file_get_contents($file);

            // Extract namespace
            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
                $namespace = $nsMatch[1];

                // Extract class name
                if (preg_match('/class\s+(\w+)\s+extends\s+SchemaBase/', $content, $classMatch)) {
                    $className = $classMatch[1];
                    $schemas[] = $namespace . '\\' . $className;
                }
            }
        }

        return $schemas;
    }

    // ===== Protected Helper Methods =====

    /**
     * Get field definitions
     *
     * Calls define() and caches the results, setting field names
     * from array keys.
     *
     * @return array<string, SchemaField> Field definitions keyed by name
     */
    protected function getFields(): array
    {
        if (empty($this->fields)) {
            $definitions = $this->define();

            // Store field definitions with names as keys
            foreach ($definitions as $name => $field) {
                if ($field instanceof SchemaField) {
                    $this->fields[$name] = $field;
                }
            }
        }

        return $this->fields;
    }

    /**
     * Validate schema configuration
     *
     * Ensures required properties are set.
     *
     * @throws RuntimeException If table property is empty
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->table)) {
            throw new RuntimeException('Schema must define a $table property');
        }
    }

    /**
     * Compare schema with database columns
     *
     * Analyzes differences between schema definition and actual database structure.
     *
     * @param array $currentColumns  Current database column information from DatabaseInspector
     * @return array                 Changes detected (add, modify, drop)
     */
    protected function compareWithDatabase(array $currentColumns): array
    {
        $fields = $this->getFields();
        $changes = [
            'add' => [],
            'modify' => [],
            'drop' => []
        ];

        $inspector = new DatabaseInspector();

        // Check for new or modified fields
        foreach ($fields as $name => $field) {
            if (!isset($currentColumns[$name])) {
                $changes['add'][$name] = $field;
            } else {
                // Check if field has changed
                $column = $currentColumns[$name];
                $differences = $inspector->compareFieldWithColumn($field, $column);

                // If there are differences, mark field for modification
                if (!empty($differences)) {
                    $changes['modify'][$name] = $field;
                } else {
                    // Additional checks for things not covered by compareFieldWithColumn

                    // Check default value changes
                    $schemaDefault = $field->hasDefaultValue() ? $field->getDefaultValue() : null;
                    $dbDefault = $column['default'];

                    // Normalize defaults for comparison
                    if ($schemaDefault !== null && $dbDefault !== null) {
                        // Convert boolean defaults to match MySQL representation
                        if (is_bool($schemaDefault)) {
                            $schemaDefault = $schemaDefault ? '1' : '0';
                        }
                        // Convert numeric strings to match
                        if (is_numeric($schemaDefault) && is_numeric($dbDefault)) {
                            $schemaDefault = (string) $schemaDefault;
                            $dbDefault = (string) $dbDefault;
                        }
                    }

                    // Check if defaults are different
                    if ($schemaDefault !== $dbDefault) {
                        // Special case: CURRENT_TIMESTAMP
                        if ($schemaDefault === 'CURRENT_TIMESTAMP' &&
                            (stripos($dbDefault, 'CURRENT_TIMESTAMP') !== false ||
                                stripos($dbDefault, 'current_timestamp()') !== false)) {
                            // These are equivalent, no change needed
                        } else {
                            $changes['modify'][$name] = $field;
                        }
                    }

                    // Check modifiers for AUTO_INCREMENT and ON UPDATE
                    $modifiers = $field->getModifiers();

                    // Check AUTO_INCREMENT
                    if (!empty($modifiers['autoIncrement']) && stripos($column['extra'], 'auto_increment') === false) {
                        $changes['modify'][$name] = $field;
                    }

                    // Check ON UPDATE CURRENT_TIMESTAMP
                    if (!empty($modifiers['onUpdate']) && stripos($column['extra'], 'on update') === false) {
                        $changes['modify'][$name] = $field;
                    }
                }
            }
        }

        // Check for removed fields
        foreach ($currentColumns as $columnName => $columnInfo) {
            if (!isset($fields[$columnName])) {
                $changes['drop'][] = $columnName;
            }
        }

        // Filter out empty change types
        $changes = array_filter($changes, fn($v) => !empty($v));

        return $changes;
    }
}