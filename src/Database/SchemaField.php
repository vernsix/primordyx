<?php
/**
 * File: /vendor/vernsix/primordyx/src/Database/SchemaField.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.1.0
 * @version     1.1.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Database/SchemaField.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Database;

/**
 * Fluent interface for defining database schema fields
 *
 * Provides a chainable API for defining field types, modifiers, constraints,
 * and validation rules. This class is used internally by SchemaBase to build
 * field definitions that can be converted to SQL column definitions, PHP property
 * declarations, validation rules, and type casts.
 *
 * Note: Field names are not stored in this class. They are managed externally
 * via array keys in schema definitions and passed as parameters when needed.
 *
 * @example
 * ```php
 * // Basic field definition (name managed externally)
 * $field = SchemaField::string(255)
 *     ->unique()
 *     ->required()
 *     ->email();
 *
 * // Generate SQL column (name passed as parameter)
 * echo $field->toSqlColumn('email');
 * // Output: `email` VARCHAR(255) NOT NULL
 *
 * // Generate PHP property (name passed as parameter)
 * echo $field->toPropertyDeclaration('email');
 * // Output: public string $email;
 * ```
 *
 * @package Primordyx\Database
 * @see SchemaBase For usage within schema definitions
 */
class SchemaField
{
    /**
     * Field type (int, string, text, datetime, etc.)
     * @var string
     */
    private string $type;

    /**
     * Maximum length for string fields
     * @var int|null
     */
    private ?int $length = null;

    /**
     * Precision for decimal fields (total digits)
     * @var int|null
     */
    private ?int $precision = null;

    /**
     * Scale for decimal fields (digits after decimal point)
     * @var int|null
     */
    private ?int $scale = null;

    /**
     * Field modifiers (nullable, unique, index, etc.)
     * @var array<string, mixed>
     */
    private array $modifiers = [];

    /**
     * Validation rules to apply to this field
     * @var array<string>
     */
    private array $validationRules = [];

    /**
     * Allowed values for enum fields
     * @var array<string>
     */
    private array $enumValues = [];

    /**
     * Default value for the field
     * @var mixed
     */
    private mixed $defaultValue = null;

    /**
     * Whether a default value has been set
     * @var bool
     */
    private bool $hasDefault = false;

    /**
     * MySQL type mapping
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'int' => 'INT',
        'bigInt' => 'BIGINT',
        'tinyInt' => 'TINYINT',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'datetime' => 'DATETIME',
        'date' => 'DATE',
        'time' => 'TIME',
        'timestamp' => 'TIMESTAMP',  // Added timestamp support
        'boolean' => 'BOOLEAN',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'json' => 'JSON',
        'enum' => 'ENUM'
    ];

    /**
     * PHP type mapping for property declarations
     * @var array<string, string>
     */
    private const PHP_TYPE_MAP = [
        'int' => 'int',
        'bigInt' => 'int',
        'tinyInt' => 'int',
        'string' => 'string',
        'text' => 'string',
        'datetime' => '\\DateTime',
        'date' => '\\DateTime',
        'time' => 'string',
        'timestamp' => 'string',
        'boolean' => 'bool',
        'decimal' => 'float',
        'float' => 'float',
        'json' => 'array',
        'enum' => 'string'
    ];

    /**
     * Constructor
     *
     * @param string $type  Field type (int, string, text, etc.)
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    // ===== Internal Setters (used by SchemaBase) =====

    /**
     * Set field length (internal use by SchemaBase)
     *
     * Used internally for string/varchar fields to set maximum length.
     *
     * @param int $length  Maximum field length
     * @return self        Returns this instance for method chaining
     * @internal
     */
    public function setLength(int $length): self
    {
        $this->length = $length;
        return $this;
    }

    /**
     * Set decimal precision and scale (internal use by SchemaBase)
     *
     * Used internally for decimal fields to set precision and scale.
     *
     * @param int $precision  Total number of digits
     * @param int $scale      Digits after decimal point
     * @return self           Returns this instance for method chaining
     * @internal
     */
    public function setPrecisionScale(int $precision, int $scale): self
    {
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    /**
     * Set enum values (internal use by SchemaBase)
     *
     * Used internally for enum fields to set allowed values.
     *
     * @param array<string> $values  Array of allowed enum values
     * @return self                  Returns this instance for method chaining
     * @internal
     */
    public function setEnumValues(array $values): self
    {
        $this->enumValues = $values;
        return $this;
    }

    // ===== Static Factory Methods =====

    /**
     * Create an integer field
     *
     * Creates a standard 32-bit signed integer field (-2147483648 to 2147483647).
     * Use bigInt() for larger values.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::int()->unsigned()->index();
     * SchemaField::int()->unsigned()->default(0);
     * ```
     */
    public static function int(): self
    {
        return new self('int');
    }

    /**
     * Create a big integer field
     *
     * Creates a 64-bit signed integer field for storing large numbers.
     * Commonly used for auto-incrementing primary keys in large tables.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::bigInt()->primaryKey()->autoIncrement();
     * SchemaField::bigInt()->unsigned()->default(0);
     * ```
     */
    public static function bigInt(): self
    {
        return new self('bigInt');
    }

    /**
     * Create a tiny integer field
     *
     * Creates an 8-bit signed integer field for storing small numbers.
     * Range: -128 to 127 (signed) or 0 to 255 (unsigned).
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::tinyInt()->unsigned()->default(0);
     * SchemaField::tinyInt()->nullable();
     * ```
     */
    public static function tinyInt(): self
    {
        return new self('tinyInt');
    }




    /**
     * Create a string/varchar field
     *
     * Creates a variable-length string field with a maximum length.
     * Maps to VARCHAR in MySQL.
     *
     * @param int    $length  Maximum string length (default: 255)
     * @return self           Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::string(255)->unique()->required();
     * SchemaField::string(100)->unique()->index();
     * ```
     */
    public static function string(int $length = 255): self
    {
        $field = new self('string');
        $field->length = $length;
        return $field;
    }

    /**
     * Create a text field
     *
     * Creates a TEXT field for storing long strings (up to 65,535 characters).
     * Use for content that exceeds varchar limitations.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::text()->nullable();
     * SchemaField::text()->required();
     * ```
     */
    public static function text(): self
    {
        return new self('text');
    }

    /**
     * Create a datetime field
     *
     * Creates a DATETIME field for storing date and time values.
     * Format: 'YYYY-MM-DD HH:MM:SS'
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::datetime()->default('CURRENT_TIMESTAMP');
     * SchemaField::datetime()->nullable()->onUpdate();
     * ```
     */
    public static function datetime(): self
    {
        return new self('datetime');
    }

    /**
     * Create a date field
     *
     * Creates a DATE field for storing date values.
     * Format: 'YYYY-MM-DD'
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::date()->nullable();
     * SchemaField::date()->required();
     * ```
     */
    public static function date(): self
    {
        return new self('date');
    }

    /**
     * Create a time field
     *
     * Creates a TIME field for storing time values.
     * Format: 'HH:MM:SS'
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::time()->default('09:00:00');
     * SchemaField::time()->nullable();
     * ```
     */
    public static function time(): self
    {
        return new self('time');
    }

    /**
     * Create a timestamp field
     *
     * Creates a TIMESTAMP field for storing date and time values.
     * Has automatic timezone conversion and limited range (1970-2038).
     * Can auto-update with ON UPDATE CURRENT_TIMESTAMP.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::timestamp()->default('CURRENT_TIMESTAMP');
     * SchemaField::timestamp()->default('CURRENT_TIMESTAMP')->onUpdate();
     * SchemaField::timestamp()->nullable();
     * ```
     */
    public static function timestamp(): self
    {
        return new self('timestamp');
    }

    /**
     * Create a boolean field
     *
     * Creates a BOOLEAN field (TINYINT(1) in MySQL).
     * Maps to PHP bool type.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::boolean()->default(true);
     * SchemaField::boolean()->default(false);
     * ```
     */
    public static function boolean(): self
    {
        return new self('boolean');
    }

    /**
     * Create a decimal field
     *
     * Creates a DECIMAL field for exact numeric values.
     * Use for monetary values or when precision is critical.
     *
     * @param int $precision  Total number of digits
     * @param int $scale      Digits after decimal point
     * @return self           Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::decimal(10, 2)->required();
     * SchemaField::decimal(5, 4)->default(0.0000);
     * ```
     */
    public static function decimal(int $precision = 10, int $scale = 2): self
    {
        $field = new self('decimal');
        $field->precision = $precision;
        $field->scale = $scale;
        return $field;
    }

    /**
     * Create a float field
     *
     * Creates a FLOAT field for approximate numeric values.
     * Less precise than DECIMAL but uses less storage.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::float()->default(0.0);
     * SchemaField::float()->nullable();
     * ```
     */
    public static function float(): self
    {
        return new self('float');
    }

    /**
     * Create a JSON field
     *
     * Creates a JSON field for storing structured data.
     * Maps to PHP array type.
     *
     * @return self         Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::json()->nullable();
     * SchemaField::json()->default('{}');
     * ```
     */
    public static function json(): self
    {
        return new self('json');
    }

    /**
     * Create an enum field
     *
     * Creates an ENUM field with specified allowed values.
     *
     * @param array<string> $values  Array of allowed values
     * @return self                  Returns new SchemaField instance for method chaining
     *
     * @example
     * ```php
     * SchemaField::enum(['active', 'inactive', 'pending'])->default('pending');
     * SchemaField::enum(['small', 'medium', 'large'])->required();
     * ```
     */
    public static function enum(array $values): self
    {
        $field = new self('enum');
        $field->enumValues = $values;
        return $field;
    }

    // ===== Field Modifiers (Chainable) =====

    /**
     * Mark field as primary key
     *
     * Sets this field as the table's primary key.
     * Usually combined with autoIncrement() for ID fields.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->int()->primaryKey()->autoIncrement()
     * ```
     */
    public function primaryKey(): self
    {
        $this->modifiers['primaryKey'] = true;
        return $this;
    }

    /**
     * Mark field as auto-incrementing
     *
     * Sets AUTO_INCREMENT on the field.
     * Typically used with integer primary keys.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->bigInt()->primaryKey()->autoIncrement()
     * ```
     */
    public function autoIncrement(): self
    {
        $this->modifiers['autoIncrement'] = true;
        return $this;
    }

    /**
     * Mark field as unsigned
     *
     * For numeric fields, prevents negative values.
     * Doubles the positive range.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->int()->unsigned()  // 0 to 4294967295
     * ```
     */
    public function unsigned(): self
    {
        $this->modifiers['unsigned'] = true;
        return $this;
    }

    /**
     * Mark field as nullable
     *
     * Allows NULL values in this field.
     * By default, fields are NOT NULL.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(100)->nullable()
     * ```
     */
    public function nullable(): self
    {
        $this->modifiers['nullable'] = true;
        return $this;
    }

    /**
     * Mark field as required
     *
     * Adds 'required' validation rule.
     * Field must have a value when saving.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(255)->required()
     * ```
     */
    public function required(): self
    {
        $this->validationRules[] = 'required';
        return $this;
    }

    /**
     * Add unique constraint
     *
     * Creates a unique index on this field.
     * No two rows can have the same value.
     *
     * @param string|null $indexName  Optional custom index name
     * @return self                   Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(255)->unique()
     * $this->string(100)->unique('unique_username')
     * ```
     */
    public function unique(?string $indexName = null): self
    {
        $this->modifiers['unique'] = $indexName ?: true;
        $this->validationRules[] = 'unique';
        return $this;
    }

    /**
     * Add index to field
     *
     * Creates a regular index for faster queries.
     *
     * @param string|null $indexName  Optional custom index name
     * @return self                   Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(100)->index()
     * $this->int()->index('idx_user_id')
     * ```
     */
    public function index(?string $indexName = null): self
    {
        $this->modifiers['index'] = $indexName ?: true;
        return $this;
    }

    /**
     * Set default value
     *
     * Sets a default value for the field.
     * Used when no value is provided on insert.
     *
     * @param mixed $value  Default value (can be scalar, null, or SQL function)
     * @return self         Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->boolean()->default(false)
     * $this->datetime()->default('CURRENT_TIMESTAMP')
     * $this->string(50)->default('pending')
     * ```
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;
        return $this;
    }

    /**
     * Add comment to field
     *
     * Adds a MySQL COMMENT to the column definition.
     * Useful for documenting database structure.
     *
     * @param string $comment  Comment text
     * @return self            Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->decimal(10, 2)->comment('Price in USD')
     * ```
     */
    public function comment(string $comment): self
    {
        $this->modifiers['comment'] = $comment;
        return $this;
    }

    /**
     * Add ON UPDATE CURRENT_TIMESTAMP
     *
     * For datetime/timestamp fields, automatically updates to current
     * timestamp when the row is modified.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->datetime()->default('CURRENT_TIMESTAMP')->onUpdate()
     * $this->timestamp()->default('CURRENT_TIMESTAMP')->onUpdate()
     * ```
     */
    public function onUpdate(): self
    {
        $this->modifiers['onUpdate'] = true;
        return $this;
    }

    // ===== Validation Rule Methods =====

    /**
     * Add email validation
     *
     * Field value must be a valid email address.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(255)->email()->required()
     * ```
     */
    public function email(): self
    {
        $this->validationRules[] = 'email';
        return $this;
    }

    /**
     * Add URL validation
     *
     * Field value must be a valid URL.
     *
     * @return self  Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(500)->url()
     * ```
     */
    public function url(): self
    {
        $this->validationRules[] = 'url';
        return $this;
    }

    /**
     * Add minimum length/value validation
     *
     * For strings: minimum character length
     * For numbers: minimum value
     *
     * @param int $value  Minimum length or value
     * @return self       Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(100)->min(3)  // At least 3 characters
     * $this->int()->min(18)        // At least 18
     * ```
     */
    public function min(int $value): self
    {
        $this->validationRules[] = "min:{$value}";
        return $this;
    }

    /**
     * Add maximum length/value validation
     *
     * For strings: maximum character length
     * For numbers: maximum value
     *
     * @param int $value  Maximum length or value
     * @return self       Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(100)->max(100)  // At most 100 characters
     * $this->int()->max(999)        // At most 999
     * ```
     */
    public function max(int $value): self
    {
        $this->validationRules[] = "max:{$value}";
        return $this;
    }

    /**
     * Add regex pattern validation
     *
     * Field value must match the given regular expression.
     *
     * @param string $pattern  Regular expression pattern
     * @return self            Returns this instance for method chaining
     *
     * @example
     * ```php
     * $this->string(20)->regex('/^[A-Z]{3}-\\d{3}$/')  // Format: ABC-123
     * ```
     */
    public function regex(string $pattern): self
    {
        $this->validationRules[] = "regex:{$pattern}";
        return $this;
    }

    // ===== SQL Generation Methods =====

    /**
     * Generate MySQL column definition SQL
     *
     * Converts this field definition into a MySQL column definition
     * suitable for CREATE TABLE or ALTER TABLE statements.
     *
     * @param string $name  The column name
     * @return string       MySQL column definition SQL
     *
     * @example
     * ```php
     * $field = SchemaField::string(255)->unique()->required();
     * echo $field->toSqlColumn('email');
     * // Output: `email` VARCHAR(255) NOT NULL
     * ```
     */
    public function toSqlColumn(string $name): string
    {
        $sql = "`{$name}` ";

        // Add type
        $sqlType = self::TYPE_MAP[$this->type] ?? 'VARCHAR';
        $sql .= $sqlType;

        // Add length/precision
        if ($this->length !== null) {
            $sql .= "({$this->length})";
        } elseif ($this->precision !== null && $this->scale !== null) {
            $sql .= "({$this->precision},{$this->scale})";
        } elseif ($this->type === 'enum' && !empty($this->enumValues)) {
            $values = array_map(fn($v) => "'{$v}'", $this->enumValues);
            $sql .= '(' . implode(',', $values) . ')';
        }

        // Add UNSIGNED
        if (!empty($this->modifiers['unsigned'])) {
            $sql .= ' UNSIGNED';
        }

        // Add NULL/NOT NULL
        if (!empty($this->modifiers['nullable'])) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        // Add DEFAULT
        if ($this->hasDefault) {
            if ($this->defaultValue === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($this->defaultValue)) {
                $sql .= ' DEFAULT ' . ($this->defaultValue ? '1' : '0');
            } elseif (is_numeric($this->defaultValue)) {
                $sql .= ' DEFAULT ' . $this->defaultValue;
            } elseif (in_array($this->defaultValue, ['CURRENT_TIMESTAMP', 'NULL'])) {
                $sql .= ' DEFAULT ' . $this->defaultValue;
            } else {
                $sql .= " DEFAULT '{$this->defaultValue}'";
            }
        }

        // Add AUTO_INCREMENT
        if (!empty($this->modifiers['autoIncrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Add ON UPDATE
        if (!empty($this->modifiers['onUpdate'])) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        // Add COMMENT
        if (!empty($this->modifiers['comment'])) {
            $sql .= " COMMENT '{$this->modifiers['comment']}'";
        }

        return $sql;
    }

    /**
     * Generate PHP property declaration
     *
     * Generates a typed property declaration for use in PHP trait files.
     *
     * @param string $name  The property name
     * @return string       PHP property declaration
     *
     * @example
     * ```php
     * $field = SchemaField::string(255)->nullable();
     * echo $field->toPropertyDeclaration('email');
     * // Output:     public ?string $email;
     * ```
     */
    public function toPropertyDeclaration(string $name): string
    {
        $phpType = self::PHP_TYPE_MAP[$this->type] ?? 'mixed';

        // Handle nullable types
        $nullable = !empty($this->modifiers['nullable']) ? '?' : '';

        // Special case for primary keys - they're never nullable in PHP
        if (!empty($this->modifiers['primaryKey'])) {
            $nullable = '';
        }

        return "    public {$nullable}{$phpType} \${$name};";
    }

    /**
     * Get validation rules for this field
     *
     * Returns an array of validation rules that have been applied to this field.
     *
     * @return array<string> Array of validation rule strings
     *
     * @example
     * ```php
     * $field = SchemaField::string(255)->required()->email()->min(5);
     * $rules = $field->getValidationRules();
     * // Returns: ['required', 'email', 'min:5']
     * ```
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get cast type for Model class
     *
     * Returns the cast type to be used with Primordyx's Model class
     * for automatic type casting of database values.
     *
     * @return string|null  Cast type or null if no casting needed
     *
     * @example
     * ```php
     * SchemaField::boolean()->getCastType();  // Returns: 'boolean'
     * SchemaField::datetime()->getCastType();  // Returns: 'datetime'
     * SchemaField::json()->getCastType();  // Returns: 'array'
     * ```
     */
    public function getCastType(): ?string
    {
        return match($this->type) {
            'boolean' => 'boolean',
            'int', 'bigInt' => 'integer',
            'float', 'decimal' => 'float',
            'datetime', 'date', 'timestamp' => 'datetime',
            'json' => 'array',
            default => null
        };
    }

    /**
     * Get the field type
     *
     * @return string  The field type (int, string, text, etc.)
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the field length
     *
     * @return int|null  The maximum length for string fields, null for others
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * Get the decimal precision
     *
     * @return int|null  The precision for decimal fields, null for others
     */
    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    /**
     * Get the decimal scale
     *
     * @return int|null  The scale for decimal fields, null for others
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * Get enum values
     *
     * @return array<string>  The allowed values for enum fields
     */
    public function getEnumValues(): array
    {
        return $this->enumValues;
    }

    /**
     * Get the default value
     *
     * @return mixed  The default value if set
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Check if field has a default value
     *
     * @return bool  True if a default value has been set
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Get all field modifiers
     *
     * @return array<string, mixed>  Array of modifier name => value pairs
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    /**
     * Check if field is marked as primary key
     *
     * @return bool  True if field is a primary key
     */
    public function isPrimaryKey(): bool
    {
        return !empty($this->modifiers['primaryKey']);
    }

    /**
     * Check if field has a unique constraint
     *
     * @return bool  True if field has unique constraint
     */
    public function isUnique(): bool
    {
        return !empty($this->modifiers['unique']);
    }

    /**
     * Check if field has an index
     *
     * @return bool  True if field has an index
     */
    public function hasIndex(): bool
    {
        return !empty($this->modifiers['index']);
    }
}