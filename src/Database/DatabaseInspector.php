<?php
/**
 * File: /vendor/vernsix/primordyx/src/Database/DatabaseInspector.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.1.0
 * @version     1.1.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/DatabaseInspector.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

use PDO;
use RuntimeException;

/**
 * MySQL database introspection utility for schema comparison
 *
 * Provides methods to inspect MySQL database structure, check table existence,
 * retrieve column information, and compare schema definitions with actual
 * database structure. Used internally by SchemaBase to determine if migrations
 * are needed.
 *
 * ## Key Features:
 * - Check if tables exist in the database
 * - Retrieve detailed column information
 * - Parse MySQL column type definitions
 * - Get table indexes and constraints
 * - Compare schema fields with database columns
 *
 * ## Database Connection:
 * Uses Primordyx's ConnectionManager to access the database.
 * Defaults to the 'default' connection.
 *
 * @example
 * ```php
 * $inspector = new DatabaseInspector();
 *
 * // Check if table exists
 * if ($inspector->tableExists('users')) {
 *     // Get column details
 *     $columns = $inspector->getTableColumns('users');
 *
 *     // Get indexes
 *     $indexes = $inspector->getTableIndexes('users');
 * }
 * ```
 *
 * @package Primordyx\Database
 * @see SchemaBase For usage in schema system
 * @see ConnectionManager For database connections
 */
class DatabaseInspector
{
    /**
     * Database connection instance
     *
     * Cached PDO connection from ConnectionManager.
     *
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * Connection name in ConnectionManager
     *
     * Specifies which connection to use from ConnectionManager.
     *
     * @var string
     */
    private string $connectionName = 'default';

    /**
     * Constructor
     *
     * @param string $connectionName  Name of the connection in ConnectionManager (default: 'default')
     *
     * @example
     * ```php
     * // Use default connection
     * $inspector = new DatabaseInspector();
     *
     * // Use specific connection
     * $inspector = new DatabaseInspector('reporting');
     * ```
     */
    public function __construct(string $connectionName = 'default')
    {
        $this->connectionName = $connectionName;
    }

    /**
     * Get database connection from ConnectionManager
     *
     * Retrieves and caches the PDO connection. Throws exception if
     * the connection is not available in ConnectionManager.
     *
     * @return PDO  Active database connection
     *
     * @throws RuntimeException If connection is not available in ConnectionManager
     */
    protected function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = ConnectionManager::getHandle($this->connectionName);

            if ($this->connection === null) {
                throw new RuntimeException("Database connection '{$this->connectionName}' not available");
            }
        }

        return $this->connection;
    }

    /**
     * Check if a table exists in MySQL database
     *
     * Uses MySQL's SHOW TABLES command to check table existence.
     * Table name comparison is case-sensitive depending on MySQL configuration.
     *
     * @param string $table  Table name to check
     * @return bool          True if table exists, false otherwise
     *
     * @example
     * ```php
     * if ($inspector->tableExists('users')) {
     *     echo "Users table exists";
     * }
     * ```
     */
    public function tableExists(string $table): bool
    {
        $connection = $this->getConnection();
        $sql = "SHOW TABLES LIKE :table";
        $stmt = $connection->prepare($sql);
        $stmt->execute(['table' => $table]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get detailed column information for a MySQL table
     *
     * Retrieves comprehensive information about each column including
     * type, nullability, default values, keys, and extra attributes.
     * Uses MySQL's SHOW FULL COLUMNS command.
     *
     * @param string $table  Table name to inspect
     * @return array         Column information indexed by column name
     *
     * @example
     * ```php
     * $columns = $inspector->getTableColumns('users');
     * foreach ($columns as $name => $info) {
     *     echo $name . ': ' . $info['type'] . "\n";
     *     echo '  Nullable: ' . ($info['nullable'] ? 'YES' : 'NO') . "\n";
     *     echo '  Default: ' . $info['default'] . "\n";
     * }
     * ```
     *
     * @return array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     key: string,
     *     default: mixed,
     *     extra: string,
     *     comment: string,
     *     collation: ?string,
     *     raw_type: array
     * }
     */
    public function getTableColumns(string $table): array
    {
        $connection = $this->getConnection();
        $sql = "SHOW FULL COLUMNS FROM `{$table}`";
        $stmt = $connection->query($sql);
        $columns = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
                'nullable' => $row['Null'] === 'YES',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra'],
                'comment' => $row['Comment'] ?? '',
                'collation' => $row['Collation'] ?? null,
                'raw_type' => $this->parseColumnType($row['Type'])
            ];
        }

        return $columns;
    }

    /**
     * Parse MySQL column type string into components
     *
     * Breaks down a MySQL type definition into its constituent parts:
     * base type, length/precision, scale, unsigned modifier, and enum values.
     *
     * @param string $type  Column type definition (e.g., "VARCHAR(255)", "DECIMAL(10,2)")
     * @return array        Parsed type components
     *
     * @example
     * ```php
     * $parsed = $this->parseColumnType('VARCHAR(255)');
     * // Returns: ['type' => 'varchar', 'length' => 255, ...]
     *
     * $parsed = $this->parseColumnType('DECIMAL(10,2) UNSIGNED');
     * // Returns: ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'unsigned' => true, ...]
     * ```
     *
     * @return array{
     *     type: string,
     *     length: ?int,
     *     precision: ?int,
     *     scale: ?int,
     *     unsigned: bool,
     *     values: array
     * }
     */
    protected function parseColumnType(string $type): array
    {
        $result = [
            'type' => '',
            'length' => null,
            'precision' => null,
            'scale' => null,
            'unsigned' => false,
            'values' => []
        ];

        // Check for unsigned
        if (stripos($type, 'unsigned') !== false) {
            $result['unsigned'] = true;
            $type = str_ireplace(' unsigned', '', $type);
        }

        // Parse ENUM values
        if (preg_match('/^enum\((.+)\)$/i', $type, $matches)) {
            $result['type'] = 'enum';
            // Parse enum values
            $values = $matches[1];
            preg_match_all("/'([^']+)'/", $values, $valueMatches);
            $result['values'] = $valueMatches[1];
            return $result;
        }

        // Parse type with parameters
        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/', $type, $matches)) {
            $result['type'] = strtolower($matches[1]);

            if (isset($matches[2])) {
                $params = $matches[2];

                // Check for precision and scale (DECIMAL, NUMERIC)
                if (strpos($params, ',') !== false) {
                    list($precision, $scale) = explode(',', $params);
                    $result['precision'] = (int) trim($precision);
                    $result['scale'] = (int) trim($scale);
                } else {
                    // Single parameter (length)
                    $result['length'] = (int) $params;
                }
            }
        }

        return $result;
    }

    /**
     * Get all indexes for a MySQL table
     *
     * Retrieves information about all indexes including primary keys,
     * unique constraints, and regular indexes. Uses MySQL's SHOW INDEX command.
     *
     * @param string $table  Table name to inspect
     * @return array         Index information keyed by index name
     *
     * @example
     * ```php
     * $indexes = $inspector->getTableIndexes('users');
     * foreach ($indexes as $name => $info) {
     *     echo $name . ': ';
     *     echo ($info['unique'] ? 'UNIQUE ' : '');
     *     echo '(' . implode(', ', $info['columns']) . ')' . "\n";
     * }
     * ```
     *
     * @return array{
     *     name: string,
     *     unique: bool,
     *     primary: bool,
     *     columns: array<string>
     * }
     */
    public function getTableIndexes(string $table): array
    {
        $connection = $this->getConnection();
        $indexes = [];

        $sql = "SHOW INDEX FROM `{$table}`";
        $stmt = $connection->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indexName = $row['Key_name'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'unique' => !$row['Non_unique'],
                    'primary' => $indexName === 'PRIMARY',
                    'columns' => []
                ];
            }
            $indexes[$indexName]['columns'][] = $row['Column_name'];
        }

        return $indexes;
    }

    /**
     * Compare a schema field with a database column
     *
     * Analyzes differences between a SchemaField definition and actual
     * database column properties. Used to determine if ALTER TABLE is needed.
     *
     * @param SchemaField $field   Schema field definition
     * @param array       $column  Database column information from getTableColumns()
     * @return array               Differences found between field and column
     *
     * @example
     * ```php
     * $field = SchemaField::string('email', 255)->required()->unique();
     * $column = $inspector->getTableColumns('users')['email'];
     * $differences = $inspector->compareFieldWithColumn($field, $column);
     *
     * if (!empty($differences)) {
     *     echo "Column needs updating";
     * }
     * ```
     *
     * @return array{
     *     type?: array{expected: string, actual: string},
     *     nullable?: array{expected: bool, actual: bool},
     *     length?: array{expected: int, actual: int},
     *     precision?: array{expected: int, actual: int},
     *     scale?: array{expected: int, actual: int},
     *     unsigned?: array{expected: bool, actual: bool}
     * }
     */
    public function compareFieldWithColumn(SchemaField $field, array $column): array
    {
        $differences = [];

        // Parse database column type
        $dbType = $column['raw_type'] ?? $this->parseColumnType($column['type']);

        // Map field type to expected MySQL type
        $expectedType = $this->mapFieldTypeToMySQL($field->getType());

        // Compare base type
        if (strtolower($dbType['type']) !== strtolower($expectedType)) {
            $differences['type'] = [
                'expected' => $expectedType,
                'actual' => $dbType['type']
            ];
        }

        // Compare nullable
        $modifiers = $field->getModifiers();
        $expectedNullable = !empty($modifiers['nullable']);

        if ($column['nullable'] !== $expectedNullable) {
            $differences['nullable'] = [
                'expected' => $expectedNullable,
                'actual' => $column['nullable']
            ];
        }

        // Compare length for string fields
        if ($field->getType() === 'string' && $field->getLength() !== null) {
            $expectedLength = $field->getLength();
            $actualLength = $dbType['length'] ?? null;

            if ($actualLength !== null && $expectedLength !== $actualLength) {
                $differences['length'] = [
                    'expected' => $expectedLength,
                    'actual' => $actualLength
                ];
            }
        }

        // Compare unsigned for numeric fields
        if (in_array($field->getType(), ['int', 'bigInt'])) {
            $expectedUnsigned = !empty($modifiers['unsigned']);
            $actualUnsigned = $dbType['unsigned'] ?? false;

            if ($expectedUnsigned !== $actualUnsigned) {
                $differences['unsigned'] = [
                    'expected' => $expectedUnsigned,
                    'actual' => $actualUnsigned
                ];
            }
        }

        // Compare precision and scale for decimal fields
        if ($field->getType() === 'decimal') {
            $expectedPrecision = $field->getPrecision();
            $expectedScale = $field->getScale();
            $actualPrecision = $dbType['precision'] ?? null;
            $actualScale = $dbType['scale'] ?? null;

            if ($expectedPrecision !== null && $actualPrecision !== null &&
                $expectedPrecision !== $actualPrecision) {
                $differences['precision'] = [
                    'expected' => $expectedPrecision,
                    'actual' => $actualPrecision
                ];
            }

            if ($expectedScale !== null && $actualScale !== null &&
                $expectedScale !== $actualScale) {
                $differences['scale'] = [
                    'expected' => $expectedScale,
                    'actual' => $actualScale
                ];
            }
        }

        // Compare enum values
        if ($field->getType() === 'enum') {
            $expectedValues = $field->getEnumValues();
            $actualValues = $dbType['values'] ?? [];

            // Sort for comparison
            sort($expectedValues);
            sort($actualValues);

            if ($expectedValues !== $actualValues) {
                $differences['enum_values'] = [
                    'expected' => $expectedValues,
                    'actual' => $actualValues
                ];
            }
        }

        // Note: Default values and indexes are compared separately in compareWithDatabase

        return $differences;
    }

    /**
     * Map schema field type to MySQL type
     *
     * Converts SchemaField types to their MySQL equivalents.
     *
     * @param string $fieldType  Schema field type (int, string, text, etc.)
     * @return string            Corresponding MySQL type
     *
     * @example
     * ```php
     * $this->mapFieldTypeToMySQL('boolean');  // Returns: 'tinyint'
     * $this->mapFieldTypeToMySQL('string');   // Returns: 'varchar'
     * ```
     */
    protected function mapFieldTypeToMySQL(string $fieldType): string
    {
        $mapping = [
            'int' => 'int',
            'bigInt' => 'bigint',
            'string' => 'varchar',
            'text' => 'text',
            'datetime' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'boolean' => 'tinyint',
            'decimal' => 'decimal',
            'float' => 'float',
            'json' => 'json',
            'enum' => 'enum'
        ];

        return $mapping[$fieldType] ?? 'varchar';
    }

    /**
     * Get CREATE TABLE SQL statement from MySQL
     *
     * Retrieves the original CREATE TABLE statement for an existing table.
     * Useful for debugging and comparing with generated SQL.
     *
     * @param string $table  Table name
     * @return string|null   CREATE TABLE statement or null if table doesn't exist
     *
     * @example
     * ```php
     * $sql = $inspector->getCreateTableSQL('users');
     * echo $sql;  // Shows full CREATE TABLE statement
     * ```
     */
    public function getCreateTableSQL(string $table): ?string
    {
        $connection = $this->getConnection();
        $stmt = $connection->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row[1] ?? null;
    }
}
