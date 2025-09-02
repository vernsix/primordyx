<?php
/**
 * File: /vendor/vernsix/primordyx/src/DataSaver.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/DataSaver.php
 *
 */

declare(strict_types=1);

namespace Primordyx\Data;

use InvalidArgumentException;
use Primordyx\Events\EventManager;
use Random\RandomException;

/**
 * Static facade and factory for DataSaver file operations with fluent interface support
 *
 * DataSaver serves as a static entry point that combines global configuration management
 * with fluent interface capabilities. It operates using a **Static Factory Pattern** where
 * the static methods either modify global defaults or create DataSaverConfig instances
 * to handle the actual file operations.
 *
 * ## Two-Class Architecture
 * The DataSaver system uses a dual-class design:
 * - **DataSaver** (this class): Static facade providing global defaults and factory methods
 * - **DataSaverConfig**: Worker class that performs actual file operations with per-instance configuration
 *
 * ## Static Factory Pattern
 * When you call fluent methods on DataSaver, it creates DataSaverConfig instances behind the scenes:
 * ```php
 * // What you write:
 * DataSaver::type('csv')->suffix('.csv')->save($data);
 *
 * // What actually happens:
 * $config = new DataSaverConfig();
 * $config->type('csv')->suffix('.csv')->save($data);
 * ```
 *
 * ## Method Types
 * DataSaver provides three categories of methods:
 *
 * ### 1. Global Configuration Methods
 * Set static properties that serve as defaults for all operations:
 * - `setFolder()`, `setPrefix()`, `setSuffix()`, `setFilename()`, `setType()`, `setRandom()`
 * - `clearDefaults()`, `defaults()`
 *
 * ### 2. Static Factory Methods (Fluent Interface)
 * Create DataSaverConfig instances and configure them:
 * - `prefix()`, `suffix()`, `folder()`, `random()`, `type()`, `mode()`, `filename()`
 * - Each returns a DataSaverConfig instance for method chaining
 *
 * ### 3. Direct Execution Method
 * Convenience method that creates DataSaverConfig and saves immediately:
 * - `save()` → Creates new DataSaverConfig()->save()
 *
 * ## Configuration Resolution Hierarchy
 * Settings are resolved in this priority order:
 * 1. **DataSaverConfig instance settings**: Values set via fluent methods (highest priority)
 * 2. **DataSaver global defaults**: Values set via setFolder(), setPrefix(), etc.
 * 3. **Built-in defaults**: Hardcoded fallbacks (folder=__DIR__, prefix='data_', suffix='.json')
 *
 * ## Automatic Filename Generation
 * When no explicit filename is provided, generates unique names using:
 * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
 *
 * Each component is resolved using the configuration hierarchy above.
 *
 * ## Usage Patterns
 *
 * ### Pattern 1: Global Defaults + Simple Operations
 * ```php
 * // Configure once
 * DataSaver::setFolder('/app/logs');
 * DataSaver::setPrefix('log_');
 * DataSaver::setType('json');
 *
 * // Use throughout application
 * DataSaver::save(['event' => 'user_login']);    // Uses global defaults
 * DataSaver::save(['event' => 'user_logout']);   // Uses global defaults
 * ```
 *
 * ### Pattern 2: Per-Operation Customization
 * ```php
 * // Each operation gets custom configuration without affecting globals
 * $csvFile = DataSaver::type('csv')->suffix('.csv')->save($tableData);
 * $logFile = DataSaver::type('txt')->mode('append')->filename('/var/log/app.log')->save($logData);
 * $jsonFile = DataSaver::folder('/exports')->prefix('export_')->save($exportData);
 * ```
 *
 * ### Pattern 3: Mixed Global + Per-Operation
 * ```php
 * // Set common defaults
 * DataSaver::setFolder('/data');
 * DataSaver::setPrefix('app_');
 *
 * // Override specific settings per operation
 * $csvFile = DataSaver::type('csv')->suffix('.csv')->save($csvData);        // Uses /data folder, app_ prefix
 * $debugFile = DataSaver::folder('/tmp')->prefix('debug_')->save($debug);   // Overrides folder & prefix
 * $regularFile = DataSaver::save($regularData);                             // Uses all global defaults
 * ```
 *
 * ## Factory Method Execution Flow
 * Understanding what happens during a fluent chain:
 *
 * ```php
 * $file = DataSaver::type('csv')      // 1. Creates new DataSaverConfig(), calls ->type('csv')
 *     ->folder('/exports')            // 2. Calls ->folder('/exports') on same instance
 *     ->prefix('report_')             // 3. Calls ->prefix('report_') on same instance
 *     ->save($data);                  // 4. Calls ->save($data) on same instance
 * ```
 *
 * Each fluent method call operates on the same DataSaverConfig instance created by the first factory method.
 *
 * ## Global State vs Instance State
 * - **Global state** (DataSaver static properties): Shared across all operations, modified by set* methods
 * - **Instance state** (DataSaverConfig properties): Isolated per operation, created by factory methods
 * - **No interference**: Global changes don't affect active DataSaverConfig instances
 * - **Fallback behavior**: DataSaverConfig instances inherit from globals only when their own values are null
 *
 * ## Error Handling Strategy
 * - Failed operations return false rather than throwing exceptions
 * - EventManager integration provides detailed operation logging and debugging
 * - Directory creation is handled automatically with appropriate permissions
 * - Type validation occurs during DataSaverConfig method calls, not during DataSaver factory calls
 *
 * @since 1.0.0
 *
 * @example Basic Usage with Defaults
 * ```php
 * // Configure global defaults once
 * DataSaver::setFolder('/var/log');
 * DataSaver::setPrefix('app_');
 * DataSaver::setSuffix('.json');
 * DataSaver::setType('json');
 *
 * // Simple save operation using all defaults
 * $file = DataSaver::save(['status' => 'complete', 'timestamp' => time()]);
 * // Saves to: /var/log/app_20250830_143022_123456_a1b2c3.json
 * ```
 *
 * @example Fluent Interface for Custom Operations
 * ```php
 * // Chain configuration for specific operations
 * $csvFile = DataSaver::type('csv')
 *     ->suffix('.csv')
 *     ->folder('/exports')
 *     ->prefix('report_')
 *     ->mode('overwrite')
 *     ->save([
 *         ['ID', 'Name', 'Email'],
 *         [1, 'John Doe', 'john@example.com'],
 *         [2, 'Jane Smith', 'jane@example.com']
 *     ]);
 * ```
 *
 * @example Explicit Filename with Append Mode
 * ```php
 * // Write to specific file with append behavior
 * $success = DataSaver::filename('/var/log/application.log')
 *     ->type('txt')
 *     ->mode('append')
 *     ->save(['User logged in', 'Session started']);
 *
 * if ($success === false) {
 *     error_log('Failed to write to application log');
 * }
 * ```
 *
 * @example Custom Format Registration
 * ```php
 * // Register custom XML format handler
 * DataSaverTypeRegistry::register('xml', function (string $filename, array $data, bool $append): bool {
 *     $xml = '<?xml version="1.0"?><data>';
 *     foreach ($data as $key => $value) {
 *         $xml .= "<item key=\"$key\">$value</item>";
 *     }
 *     $xml .= '</data>';
 *
 *     $flags = $append ? FILE_APPEND : 0;
 *     return file_put_contents($filename, $xml, $flags) !== false;
 * });
 *
 * // Use custom format
 * DataSaver::type('xml')->suffix('.xml')->save(['foo' => 'bar']);
 * ```
 *
 * @example Configuration Management
 * ```php
 * // View current configuration
 * $config = DataSaver::defaults();
 * print_r($config);
 *
 * // Reset all settings to defaults
 * DataSaver::clearDefaults();
 *
 * // Configure for different environments
 * if ($environment === 'production') {
 *     DataSaver::setFolder('/var/log/production');
 *     DataSaver::setPrefix('prod_');
 * } else {
 *     DataSaver::setFolder('./logs/debug');
 *     DataSaver::setPrefix('debug_');
 * }
 * ```
 *
 * @see DataSaverConfig For fluent configuration instance methods
 * @see DataSaverTypeRegistry For format handler registration and management
 * @see EventManager For error logging and debugging events
 */
class DataSaver
{
    /**
     * Default directory path where generated files will be saved
     *
     * Stores the global default folder path used for automatic filename generation
     * across all DataSaver operations. This path serves as the base directory for
     * file output unless overridden by individual method calls or explicit filename usage.
     *
     * ## Path Handling
     * - Initialized to current script directory (__DIR__)
     * - Normalized by removing trailing slashes via setFolder()
     * - Used in automatic filename generation pattern
     * - Bypassed when explicit filenames are provided
     * - Directory is created automatically during save operations if it doesn't exist
     *
     * ## Global State Impact
     * This static property maintains global state across all DataSaver operations
     * until modified by setFolder() or reset by clearDefaults().
     *
     * @var string The default folder path for file operations, without trailing slash
     * @see DataSaver::setFolder() To modify this value
     * @see DataSaver::clearDefaults() To reset to __DIR__
     * @since 1.0.0
     */
    protected static string $folder = __DIR__;

    /**
     * Default filename prefix used in automatic filename generation
     *
     * Stores the global default prefix that gets prepended to automatically generated
     * filenames. This prefix is combined with timestamp, microseconds, and random ID
     * components to create unique, identifiable filenames for data output operations.
     *
     * ## Filename Generation Role
     * Part of the automatic filename pattern:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Usage Scenarios
     * - Categorizing files by operation type (e.g., 'log_', 'data_', 'export_')
     * - Adding application identifiers to output files
     * - Providing consistent naming conventions across operations
     * - Can be empty string to disable prefixing
     *
     * ## Global State Impact
     * This static property maintains global state and affects all subsequent
     * DataSaver operations until modified or reset.
     *
     * @var string The default filename prefix, defaults to 'data_'
     * @see DataSaver::setPrefix() To modify this value
     * @see DataSaver::clearDefaults() To reset to 'data_'
     * @since 1.0.0
     */
    protected static string $prefix = 'data_';

    /**
     * Default file extension suffix used in automatic filename generation
     *
     * Stores the global default file extension that gets appended to automatically
     * generated filenames. This suffix typically includes the leading dot and file
     * extension appropriate for the output format type being used.
     *
     * ## File Extension Role
     * - Provides proper file type identification for operating systems
     * - Should align with the output format type for consistency
     * - Enables correct application association and file handling
     * - Part of the automatic filename generation pattern
     *
     * ## Format Alignment
     * Common suffix/type pairings:
     * - '.json' for JSON output format
     * - '.csv' for CSV tabular data
     * - '.txt' or '.log' for plain text output
     * - Custom extensions for specialized formats
     *
     * ## Global State Impact
     * This static property affects all DataSaver operations until modified
     * by setSuffix() or reset by clearDefaults().
     *
     * @var string The default file extension suffix including leading dot, defaults to '.json'
     * @see DataSaver::setSuffix() To modify this value
     * @see DataSaver::clearDefaults() To reset to '.json'
     * @since 1.0.0
     */
    protected static string $suffix = '.json';

    /**
     * Storage property for explicit filename that bypasses automatic generation
     *
     * This property stores the explicit filename value when set via setFilename() method.
     * When not null, this property completely disables the automatic filename generation
     * system. The filename stored here is used exactly as provided, ignoring all other
     * naming components (folder, prefix, suffix, timestamp, random ID).
     *
     * ## Naming Relationships
     * - **Property name**: `$filenameOverride` (this property)
     * - **Setter parameter**: `setFilename(string $filename)` - stores `$filename` in this property
     * - **Defaults array key**: `'filename'` - returns the value of this `$filenameOverride` property
     * - **Config parameter**: `filename(string $fullPath)` - stores `$fullPath` in similar property
     *
     * ## Override Behavior
     * When this property is not null:
     * - Automatic filename generation is completely bypassed
     * - Folder, prefix, suffix settings are ignored
     * - Timestamp and random ID generation is skipped
     * - The exact path stored in this property is used for file operations
     *
     * ## Path Flexibility
     * - Can be absolute or relative file paths
     * - Directory components are created automatically if needed during save
     * - No validation of file extensions or naming conventions
     * - Supports any valid filesystem path format
     *
     * ## Global State Impact
     * When set to a non-null value, affects all subsequent DataSaver operations
     * until cleared or reset. Set to null to return to automatic filename generation.
     *
     * @var string|null The explicit filename to use, or null for automatic generation.
     *                  Set via setFilename() parameter, returned via defaults()['filename'] key.
     * @see DataSaver::setFilename() Method that sets this property (parameter $filename -> $filenameOverride)
     * @see DataSaver::defaults() Returns this property value under 'filename' key
     * @see DataSaver::clearDefaults() To reset this property to null (auto-generation)
     * @since 1.0.0
     */
    protected static ?string $filenameOverride = null;

    /**
     * Default output format type for data serialization
     *
     * Stores the global default format type identifier that determines how array
     * data is serialized and written to files. The type must correspond to a
     * registered handler in the DataSaverTypeRegistry. When null, defaults to
     * 'json' during save operations.
     *
     * ## Format Type System
     * - Must be registered in DataSaverTypeRegistry before use
     * - Case-insensitive (automatically converted to lowercase)
     * - Determines data serialization and file writing behavior
     * - Each type has its own append mode behavior
     *
     * ## Built-in Types
     * - **null**: Defaults to 'json' format during operations
     * - **'json'**: Pretty-printed JSON with merge support for append
     * - **'csv'**: Comma-separated values for tabular data
     * - **'txt'**: Plain text with one array element per line
     *
     * ## Type Validation
     * Type registration is validated during save operations, not when set.
     * Invalid types cause save operations to fail and return false.
     *
     * ## Global State Impact
     * This setting affects all DataSaver operations until modified by
     * setType() or reset by clearDefaults().
     *
     * @var string|null The default output format type, null defaults to 'json'
     * @see DataSaver::setType() To modify this value
     * @see DataSaverTypeRegistry::isRegistered() To check type availability
     * @see DataSaver::clearDefaults() To reset to null
     * @since 1.0.0
     */
    protected static ?string $type = null;

    /**
     * Fixed random identifier for filename generation or null for automatic generation
     *
     * Controls the random component used in automatic filename generation. When set
     * to a specific string value, that identifier is used consistently across all
     * generated filenames. When null (default), a new 6-character hexadecimal ID
     * is generated for each save operation to ensure filename uniqueness.
     *
     * ## Random ID Purpose
     * - Provides uniqueness in generated filenames
     * - Enables filename traceability and identification
     * - Part of the filename pattern: `{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     * - Prevents filename collisions in high-frequency operations
     *
     * ## Generation Modes
     * - **null (default)**: Automatic generation of 6-digit hex IDs per operation
     * - **string value**: Fixed identifier used for all operations until changed
     * - **empty string**: Valid fixed value resulting in no random component
     *
     * ## Use Cases for Fixed Values
     * - Testing and debugging with predictable filenames
     * - Session-based file identification
     * - Batch operation tagging
     * - Application instance identification
     *
     * ## Global State Impact
     * This setting affects all DataSaver operations until modified by
     * setRandom() or reset by clearDefaults().
     *
     * @var string|null Fixed random ID string, or null for automatic 6-char hex generation
     * @see DataSaver::setRandom() To set fixed random ID or enable auto-generation
     * @see DataSaver::clearDefaults() To reset to null (auto-generation)
     * @since 1.0.0
     */
    protected static ?string $random = null;

    /**
     * Set the default folder path where generated files will be saved
     *
     * Configures the global default directory for all DataSaver operations. This path
     * will be used for automatic filename generation unless overridden by individual
     * calls or explicit filename usage. The path is normalized by removing trailing
     * slashes for consistent behavior across different input formats.
     *
     * ## Path Requirements
     * - Accepts both relative and absolute paths
     * - Directory will be created automatically during save operations if it doesn't exist
     * - Trailing slashes are automatically removed for consistency
     *
     * ## Global Defaults Impact
     * This setting affects all subsequent DataSaver operations until changed or reset
     * via clearDefaults(). Individual method calls can override this setting using
     * the fluent interface folder() method.
     *
     * @param string $folder The directory path where files should be saved.
     *                      Can be relative or absolute, trailing slashes are automatically removed.
     * @return void
     *
     * @example Basic Folder Setup
     * ```php
     * // Set a relative path from current directory
     * DataSaver::setFolder('./logs');
     *
     * // Set an absolute path
     * DataSaver::setFolder('/var/www/app/data');
     *
     * // Trailing slashes are automatically handled
     * DataSaver::setFolder('/tmp/output/');  // Becomes '/tmp/output'
     * ```
     *
     * @see DataSaver::clearDefaults() To reset to default folder
     * @see DataSaverConfig::folder() For per-call folder override
     * @since 1.0.0
     */
    public static function setFolder(string $folder): void
    {
        self::$folder = rtrim($folder, '/');
    }

    /**
     * Set the default filename prefix for automatic filename generation
     *
     * Configures the global prefix that will be prepended to automatically generated
     * filenames. This setting is used in conjunction with timestamp and random ID
     * components to create unique filenames. The prefix is ignored when using explicit
     * filename overrides via setFilename() or filename() methods.
     *
     * ## Filename Generation Pattern
     * Generated filenames follow the pattern: `{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     * where the prefix is the value set by this method.
     *
     * ## Global Defaults Impact
     * This setting affects all subsequent DataSaver operations until changed or reset.
     * Individual calls can override this using the fluent interface prefix() method.
     *
     * @param string $prefix The prefix string to prepend to generated filenames.
     *                      Can be empty string to disable prefixing.
     * @return void
     *
     * @example Prefix Configuration
     * ```php
     * // Standard data file prefix
     * DataSaver::setPrefix('data_');
     *
     * // Log file prefix with application name
     * DataSaver::setPrefix('myapp_log_');
     *
     * // No prefix (empty string)
     * DataSaver::setPrefix('');
     * ```
     *
     * @see DataSaver::clearDefaults() To reset to default prefix ('data_')
     * @see DataSaverConfig::prefix() For per-call prefix override
     * @since 1.0.0
     */
    public static function setPrefix(string $prefix): void
    {
        self::$prefix = $prefix;
    }

    /**
     * Set the default file extension suffix for automatic filename generation
     *
     * Configures the global file extension that will be appended to automatically
     * generated filenames. This typically includes the dot (.) and file extension.
     * The suffix is ignored when using explicit filename overrides via setFilename()
     * or filename() methods.
     *
     * ## File Extension Handling
     * - Include the leading dot in the suffix (e.g., '.json', not 'json')
     * - Can be set to empty string to create files without extensions
     * - Should match the output format type for consistency
     *
     * ## Global Defaults Impact
     * This setting affects all subsequent DataSaver operations until changed or reset.
     * Individual calls can override this using the fluent interface suffix() method.
     *
     * @param string $suffix The file extension suffix including the leading dot.
     *                      Common values: '.json', '.csv', '.txt', '.log'
     * @return void
     *
     * @example Suffix Configuration
     * ```php
     * // JSON files (default)
     * DataSaver::setSuffix('.json');
     *
     * // CSV data files
     * DataSaver::setSuffix('.csv');
     *
     * // Plain text logs
     * DataSaver::setSuffix('.log');
     *
     * // No extension
     * DataSaver::setSuffix('');
     * ```
     *
     * @see DataSaver::clearDefaults() To reset to default suffix ('.json')
     * @see DataSaverConfig::suffix() For per-call suffix override
     * @since 1.0.0
     */
    public static function setSuffix(string $suffix): void
    {
        self::$suffix = $suffix;
    }

    /**
     * Set an explicit filename to bypass automatic filename generation
     *
     * When an explicit filename is provided, it completely bypasses the automatic
     * filename generation system and is stored in the $filenameOverride property.
     * All prefix, suffix, timestamp, random ID, and folder settings are ignored
     * in favor of using this exact filename path. This is useful for writing to
     * specific log files or predetermined output locations.
     *
     * ## Parameter vs Property Relationship
     * - Method parameter: `$filename` (the input filename to use)
     * - Storage property: `$filenameOverride` (where the value is actually stored)
     * - Defaults array key: `'filename'` (returns the `$filenameOverride` value)
     *
     * ## Bypass Behavior
     * When `$filenameOverride` is set (not null):
     * - Automatic prefix/suffix addition is disabled
     * - Timestamp generation is skipped
     * - Random ID generation is skipped
     * - Folder path prefixing is disabled
     *
     * ## Path Handling
     * - Can be relative or absolute path
     * - Directory components will be created if they don't exist during save
     * - No automatic extension detection or validation
     *
     * ## Global Defaults Impact
     * The provided filename is stored in `$filenameOverride` and affects all
     * subsequent DataSaver operations until cleared or reset.
     *
     * @param string $filename The complete file path to use for saving.
     *                        Stored internally in the $filenameOverride property.
     * @return void
     *
     * @example Explicit Filename Usage
     * ```php
     * // Set specific log file (parameter $filename -> stored in $filenameOverride)
     * DataSaver::setFilename('/var/log/application.log');
     *
     * // Use relative path
     * DataSaver::setFilename('output/results.json');
     *
     * // View the stored value via defaults (key 'filename' -> $filenameOverride value)
     * $config = DataSaver::defaults();
     * echo $config['filename']; // Shows: 'output/results.json'
     * ```
     *
     * @see DataSaver::$filenameOverride The property where the filename is actually stored
     * @see DataSaver::clearDefaults() To clear the filenameOverride back to null
     * @see DataSaverConfig::filename() For per-call filename override
     * @since 1.0.0
     */
    public static function setFilename(string $filename): void
    {
        self::$filenameOverride = $filename;
    }

    /**
     * Set the default output format type for data serialization
     *
     * Configures the global format handler that will be used to serialize and write
     * array data to files. The type must be registered in the DataSaverTypeRegistry.
     * Built-in types include 'json', 'csv', and 'txt'. Custom types can be registered
     * for specialized output formats.
     *
     * ## Built-in Types
     * - **json**: Pretty-printed JSON with support for append mode merging
     * - **csv**: Comma-separated values, expects array of arrays for rows
     * - **txt**: Plain text with one array element per line
     *
     * ## Type Validation
     * The type is converted to lowercase and must exist in the registry when save
     * operations are performed. Invalid types will cause save operations to fail.
     *
     * ## Global Defaults Impact
     * This setting affects all subsequent DataSaver operations until changed or reset.
     * Individual calls can override this using the fluent interface type() method.
     *
     * @param string $type The output format type (case-insensitive).
     *                    Must be registered in DataSaverTypeRegistry.
     * @return void
     *
     * @example Type Configuration
     * ```php
     * // Use JSON format (default)
     * DataSaver::setType('json');
     *
     * // Use CSV format for tabular data
     * DataSaver::setType('csv');
     *
     * // Use plain text format
     * DataSaver::setType('txt');
     *
     * // Use custom registered type
     * DataSaver::setType('xml');  // Assuming 'xml' handler is registered
     * ```
     *
     * @see DataSaverTypeRegistry::register() To add custom types
     * @see DataSaverTypeRegistry::isRegistered() To check type availability
     * @see DataSaver::clearDefaults() To reset to default type ('json')
     * @see DataSaverConfig::type() For per-call type override
     * @since 1.0.0
     */
    public static function setType(string $type): void
    {
        self::$type = strtolower($type);
    }

    /**
     * Set a fixed random identifier for filename generation or enable automatic generation
     *
     * Controls the random component used in automatic filename generation. When set to
     * a specific string, that value will be used consistently for all generated filenames.
     * When set to null (default), a new 6-character hexadecimal ID is generated for each
     * file operation, ensuring unique filenames.
     *
     * ## Random ID Usage
     * The random component is part of the filename pattern:
     * `{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Fixed vs Automatic Behavior
     * - **Fixed ID**: Same random component for all files (useful for testing/debugging)
     * - **Automatic ID**: New random component per save operation (default behavior)
     * - **Null value**: Triggers automatic 6-digit hex generation
     *
     * ## Global Defaults Impact
     * This setting affects all subsequent DataSaver operations until changed or reset.
     * Individual calls can override this using the fluent interface random() method.
     *
     * @param string|null $random The fixed random identifier to use, or null for automatic generation.
     *                           When null, generates 6-character hex IDs automatically.
     * @return void
     *
     * @example Random ID Configuration
     * ```php
     * // Use automatic random generation (default)
     * DataSaver::setRandom(null);
     *
     * // Use fixed random component for testing
     * DataSaver::setRandom('test01');
     *
     * // Use session-based identifier
     * DataSaver::setRandom(session_id());
     *
     * // Use custom hex identifier
     * DataSaver::setRandom('abc123');
     * ```
     *
     * @see DataSaver::clearDefaults() To reset to automatic generation
     * @see DataSaverConfig::random() For per-call random override
     * @since 1.0.0
     */
    public static function setRandom(?string $random): void
    {
        self::$random = $random;
    }

    /**
     * Reset all global default settings to their initial built-in values
     *
     * Restores all static configuration properties to their original defaults, effectively
     * clearing any previous setFolder(), setPrefix(), setSuffix(), setFilename(), setType(),
     * or setRandom() calls. This is useful for testing, debugging, or resetting the
     * DataSaver state between different application phases.
     *
     * ## Reset Values
     * After calling clearDefaults(), the following defaults are restored:
     * - **folder**: `__DIR__` (current script directory)
     * - **prefix**: `'data_'`
     * - **suffix**: `'.json'`
     * - **filename**: `null` (automatic generation enabled)
     * - **type**: `null` (defaults to 'json' during save)
     * - **random**: `null` (automatic generation enabled)
     *
     * ## Use Cases
     * - Resetting state between test cases
     * - Clearing configuration after specific operations
     * - Returning to known defaults after complex configurations
     * - Debugging configuration issues
     *
     * @return void
     *
     * @example Reset Configuration
     * ```php
     * // Configure custom settings
     * DataSaver::setFolder('/var/logs');
     * DataSaver::setPrefix('app_');
     * DataSaver::setType('csv');
     *
     * // ... perform operations ...
     *
     * // Reset to defaults
     * DataSaver::clearDefaults();
     *
     * // Now back to: folder=__DIR__, prefix='data_', suffix='.json', etc.
     * ```
     *
     * @see DataSaver::defaults() To view current default values
     * @since 1.0.0
     */
    public static function clearDefaults(): void
    {
        self::$folder = __DIR__;
        self::$prefix = 'data_';
        self::$suffix = '.json';
        self::$filenameOverride = null;
        self::$type = null;
        self::$random = null;
    }

    /**
     * Retrieve all current global default configuration values
     *
     * Returns an associative array containing all current global default settings
     * used by the DataSaver class. This is useful for debugging configuration,
     * backing up settings before changes, or displaying current configuration state.
     *
     * ## Returned Array Structure
     * The returned array contains the following keys with their corresponding property sources:
     * - **folder**: Current default folder path (from `$folder` property)
     * - **prefix**: Current default filename prefix (from `$prefix` property)
     * - **suffix**: Current default file extension (from `$suffix` property)
     * - **filename**: Explicit filename override (from `$filenameOverride` property, null if using auto-generation)
     * - **type**: Current default output format type (from `$type` property, defaults to 'json' if null)
     * - **random**: Fixed random ID (from `$random` property, null if using auto-generation)
     *
     * ## Key vs Property Name Mapping
     * Note that the array key `'filename'` returns the value from the `$filenameOverride` property.
     * This naming convention provides a cleaner API interface while the internal property
     * name reflects its role as an override mechanism.
     *
     * ## Configuration Inspection
     * This method provides read-only access to internal configuration state without
     * modifying any values. It's particularly useful for logging, debugging, or
     * creating configuration backups.
     *
     * @return array<string, mixed> Associative array of all current default values.
     *                             Keys: 'folder', 'prefix', 'suffix', 'filename', 'type', 'random'
     *                             Note: 'filename' key contains $filenameOverride property value
     *
     * @example Configuration Inspection
     * ```php
     * // View current configuration
     * $config = DataSaver::defaults();
     * print_r($config);
     *
     * // Check specific setting - 'filename' key shows $filenameOverride value
     * if ($config['filename'] !== null) {
     *     echo "Using explicit filename: " . $config['filename'];
     * } else {
     *     echo "Using automatic filename generation";
     * }
     *
     * // Log configuration for debugging
     * error_log("DataSaver config: " . json_encode($config));
     * ```
     *
     * @see DataSaver::$filenameOverride The property returned under the 'filename' key
     * @see DataSaver::clearDefaults() To reset all values
     * @since 1.0.0
     */
    public static function defaults(): array
    {
        return [
            'folder' => self::$folder,
            'prefix' => self::$prefix,
            'suffix' => self::$suffix,
            'filename' => self::$filenameOverride,
            'type' => self::$type ?? 'json',
            'random' => self::$random,
        ];
    }

    // ---- Fluent Config Helpers ----

    /**
     * Create a new DataSaverConfig instance with custom prefix setting
     *
     * Returns a new DataSaverConfig object configured with the specified prefix
     * for fluent method chaining. This allows per-operation customization without
     * affecting global defaults. Part of the fluent interface for building
     * customized save operations.
     *
     * ## Fluent Interface Pattern
     * This method starts a fluent chain that can include multiple configuration
     * methods before ending with save(). Each method returns a DataSaverConfig
     * instance that maintains its own configuration state.
     *
     * @param string $prefix The filename prefix to use for this operation.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @example Fluent Prefix Usage
     * ```php
     * // Chain with other configuration methods
     * DataSaver::prefix('log_')
     *     ->suffix('.log')
     *     ->type('txt')
     *     ->save(['Operation complete']);
     *
     * // Use only prefix override
     * DataSaver::prefix('debug_')->save(['Debug info']);
     * ```
     *
     * @see DataSaverConfig::prefix() For the instance method
     * @see DataSaver::setPrefix() To change global default
     * @since 1.0.0
     */
    public static function prefix(string $prefix): DataSaverConfig
    {
        return (new DataSaverConfig())->prefix($prefix);
    }

    /**
     * Create a new DataSaverConfig instance with custom suffix setting
     *
     * Returns a new DataSaverConfig object configured with the specified file
     * extension suffix for fluent method chaining. This allows per-operation
     * customization without affecting global defaults. Part of the fluent
     * interface for building customized save operations.
     *
     * ## File Extension Handling
     * The suffix should include the leading dot and appropriate file extension
     * for the intended output format. This ensures proper file recognition by
     * operating systems and applications.
     *
     * @param string $suffix The file extension suffix including leading dot.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @example Fluent Suffix Usage
     * ```php
     * // Chain with format type for consistency
     * DataSaver::suffix('.csv')
     *     ->type('csv')
     *     ->save($csvData);
     *
     * // Custom log extension
     * DataSaver::suffix('.custom')->save(['Custom format data']);
     * ```
     *
     * @see DataSaverConfig::suffix() For the instance method
     * @see DataSaver::setSuffix() To change global default
     * @since 1.0.0
     */
    public static function suffix(string $suffix): DataSaverConfig
    {
        return (new DataSaverConfig())->suffix($suffix);
    }

    /**
     * Create a new DataSaverConfig instance with custom folder setting
     *
     * Returns a new DataSaverConfig object configured with the specified folder
     * path for fluent method chaining. This allows per-operation customization
     * without affecting global defaults. The folder will be created automatically
     * during save operations if it doesn't exist.
     *
     * ## Directory Handling
     * - Accepts both relative and absolute paths
     * - Trailing slashes are automatically normalized
     * - Directory creation is handled during save operations
     * - Permissions default to 0755 for created directories
     *
     * @param string $folder The directory path where the file should be saved.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @example Fluent Folder Usage
     * ```php
     * // Save to specific directory
     * DataSaver::folder('/tmp/exports')
     *     ->prefix('export_')
     *     ->save($exportData);
     *
     * // Use relative path
     * DataSaver::folder('./temp')->save(['Temporary data']);
     * ```
     *
     * @see DataSaverConfig::folder() For the instance method
     * @see DataSaver::setFolder() To change global default
     * @since 1.0.0
     */
    public static function folder(string $folder): DataSaverConfig
    {
        return (new DataSaverConfig())->folder($folder);
    }

    /**
     * Create a new DataSaverConfig instance with custom random ID setting
     *
     * Returns a new DataSaverConfig object configured with the specified random
     * identifier for fluent method chaining. This allows per-operation customization
     * of the random component used in automatic filename generation without
     * affecting global defaults.
     *
     * ## Random ID Purpose
     * The random identifier becomes part of the generated filename pattern:
     * `{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     * This helps ensure filename uniqueness and can provide operation traceability.
     *
     * @param string $random The custom random identifier to use in filename generation.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @example Fluent Random Usage
     * ```php
     * // Use session ID for traceability
     * DataSaver::random(session_id())
     *     ->prefix('session_')
     *     ->save(['Session data']);
     *
     * // Use custom identifier
     * DataSaver::random('batch01')->save(['Batch processing data']);
     * ```
     *
     * @see DataSaverConfig::random() For the instance method
     * @see DataSaver::setRandom() To change global default
     * @since 1.0.0
     */
    public static function random(string $random): DataSaverConfig
    {
        return (new DataSaverConfig())->random($random);
    }

    /**
     * Create a new DataSaverConfig instance with custom output format type
     *
     * Returns a new DataSaverConfig object configured with the specified output
     * format type for fluent method chaining. The type must be registered in
     * the DataSaverTypeRegistry. This allows per-operation format selection
     * without affecting global defaults.
     *
     * ## Format Type Validation
     * The specified type is validated against the DataSaverTypeRegistry during
     * the save operation. If the type is not registered, the save operation
     * will fail and return false.
     *
     * ## Built-in Types
     * - **json**: Pretty-printed JSON with merge support for append operations
     * - **csv**: Comma-separated values for tabular data
     * - **txt**: Plain text with one array element per line
     *
     * @param string $type The output format type (case-insensitive).
     *                    Must be registered in DataSaverTypeRegistry.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @throws InvalidArgumentException If the type is not registered during save operation.
     *
     * @example Fluent Type Usage
     * ```php
     * // Save as CSV with appropriate extension
     * DataSaver::type('csv')
     *     ->suffix('.csv')
     *     ->save($tabularData);
     *
     * // Save as plain text
     * DataSaver::type('txt')->save(['Log entry 1', 'Log entry 2']);
     * ```
     *
     * @see DataSaverTypeRegistry::isRegistered() To check type availability
     * @see DataSaverConfig::type() For the instance method
     * @see DataSaver::setType() To change global default
     * @since 1.0.0
     */
    public static function type(string $type): DataSaverConfig
    {
        return (new DataSaverConfig())->type($type);
    }

    /**
     * Create a new DataSaverConfig instance with custom write mode setting
     *
     * Returns a new DataSaverConfig object configured with the specified write
     * mode for fluent method chaining. The mode determines how the save operation
     * handles existing files. This allows per-operation mode selection without
     * affecting global defaults.
     *
     * ## Write Modes
     * - **overwrite**: Replace existing file completely (default behavior)
     * - **append**: Add data to existing file, or create if not present
     * - **skip_if_exists**: Do nothing if file already exists, return false
     *
     * ## Mode-Specific Behavior
     * Each output format type handles append mode differently:
     * - JSON: Merges new data with existing data structure
     * - CSV: Adds new rows to existing file
     * - TXT: Adds new lines to existing file
     *
     * @param string $mode The write mode. Must be 'overwrite', 'append', or 'skip_if_exists'.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @throws InvalidArgumentException If mode is not one of the valid options during save.
     *
     * @example Fluent Mode Usage
     * ```php
     * // Append to log file
     * DataSaver::mode('append')
     *     ->type('txt')
     *     ->filename('app.log')
     *     ->save(['New log entry']);
     *
     * // Skip if file exists (one-time data export)
     * DataSaver::mode('skip_if_exists')->save($exportData);
     * ```
     *
     * @see DataSaverConfig::mode() For the instance method
     * @since 1.0.0
     */
    public static function mode(string $mode): DataSaverConfig
    {
        return (new DataSaverConfig())->mode($mode);
    }

    /**
     * Create a new DataSaverConfig instance with explicit filename override
     *
     * Returns a new DataSaverConfig object configured to use the specified
     * filename instead of automatic generation for fluent method chaining.
     * The provided filename parameter is stored in the instance's $filenameOverride
     * property. When this property is set, all automatic naming components
     * (prefix, suffix, timestamp, random ID, folder) are bypassed.
     *
     * ## Parameter to Property Mapping
     * - Method parameter: `$filename` (the input filename path)
     * - Storage property: `$filenameOverride` (where the value is stored in DataSaverConfig)
     * - Similar to: `DataSaver::setFilename($filename)` which stores in `DataSaver::$filenameOverride`
     *
     * ## Filename Override Behavior
     * Using an explicit filename disables:
     * - Automatic prefix and suffix addition
     * - Timestamp-based naming
     * - Random ID generation
     * - Folder path prefixing from configuration
     *
     * ## Path Handling
     * - Can be relative or absolute file path
     * - Directory components are created automatically if needed
     * - No validation of file extensions or format compatibility
     *
     * @param string $filename The complete file path to use for the save operation.
     *                        Stored in the instance $filenameOverride property.
     * @return DataSaverConfig Configured instance for method chaining.
     *
     * @example Fluent Filename Usage
     * ```php
     * // Save to specific file with append mode (parameter $filename -> $filenameOverride)
     * DataSaver::filename('/var/log/application.log')
     *     ->mode('append')
     *     ->type('txt')
     *     ->save(['Application started']);
     *
     * // Use relative path
     * DataSaver::filename('output/results.json')->save($results);
     * ```
     *
     * @see DataSaverConfig::$filenameOverride The property where the filename is stored
     * @see DataSaverConfig::filename() For the instance method implementation
     * @see DataSaver::setFilename() For global filename override
     * @since 1.0.0
     */
    public static function filename(string $filename): DataSaverConfig
    {
        return (new DataSaverConfig())->filename($filename);
    }

    /**
     * Save array data using current global defaults with automatic filename generation
     *
     * Convenience method that creates a new DataSaverConfig instance and immediately
     * performs a save operation using the current global default settings. This is
     * the simplest way to save data when the global defaults are sufficient for
     * the operation.
     *
     * ## Default Behavior
     * Uses all current global defaults set via setFolder(), setPrefix(), setSuffix(),
     * setFilename(), setType(), and setRandom() methods. If no defaults have been
     * configured, uses built-in defaults (JSON format, 'data_' prefix, current directory).
     *
     * ## Automatic Filename Generation
     * Unless setFilename() has been used to specify an explicit filename, this method
     * generates unique filenames using the pattern:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Error Handling
     * Returns false if the save operation fails due to:
     * - Unregistered output type
     * - Directory creation failure
     * - File writing permission issues
     * - Format handler errors
     *
     * @param array $mydata The array data to save. Can be empty array for placeholder files.
     * @return string|false Full path to saved file on success, false on failure.
     *
     * @throws RandomException If random_bytes() fails during automatic ID generation.
     *
     * @example Quick Save Operations
     * ```php
     * // Save with all defaults
     * $filepath = DataSaver::save(['status' => 'complete']);
     *
     * // Save empty placeholder file
     * $filepath = DataSaver::save();
     *
     * // Handle save failure
     * $filepath = DataSaver::save($data);
     * if ($filepath === false) {
     *     error_log('Failed to save data');
     * }
     * ```
     *
     * @see DataSaverConfig::save() For the full implementation
     * @see DataSaver::defaults() To view current configuration
     * @since 1.0.0
     */
    public static function save(array $mydata = []): string|false
    {
        return (new DataSaverConfig())->save($mydata);
    }
}
