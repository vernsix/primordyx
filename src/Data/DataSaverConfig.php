<?php
/**
 * File: /vendor/vernsix/primordyx/src/DataSaverConfig.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Data/DataSaverConfig.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use InvalidArgumentException;
use Primordyx\Events\EventManager;
use Random;

/**
 * Worker class for DataSaver operations with fluent configuration interface
 *
 * DataSaverConfig is the **worker class** that performs actual file save operations in the
 * DataSaver system. While users primarily interact with the static DataSaver facade,
 * DataSaverConfig instances are created behind the scenes to handle the real work.
 * This class is typically not instantiated directly by developers.
 *
 * ## Static Factory Pattern Role
 * DataSaverConfig works as part of a Static Factory Pattern with DataSaver:
 * - **DataSaver**: Static facade that creates DataSaverConfig instances
 * - **DataSaverConfig**: Worker class that actually performs file operations
 *
 * ## How Instances Are Created
 * DataSaverConfig instances are created automatically by DataSaver static methods:
 * ```php
 * // User code:
 * DataSaver::type('csv')->suffix('.csv')->save($data);
 *
 * // What DataSaver actually does:
 * $config = new DataSaverConfig();          // Created by DataSaver::type()
 * $config->type('csv')->suffix('.csv')->save($data);  // Chain continues on same instance
 * ```
 *
 * ## Instance Lifecycle
 * 1. **Creation**: DataSaver static factory method creates new DataSaverConfig instance
 * 2. **Configuration**: Fluent methods configure the instance properties
 * 3. **Execution**: save() method performs the file operation
 * 4. **Disposal**: Instance is discarded after save() returns
 *
 * ## Configuration Inheritance
 * Each DataSaverConfig instance inherits from DataSaver global defaults using fallback logic:
 * ```php
 * // Resolution hierarchy for each property:
 * $finalValue = $this->instanceProperty ?? DataSaver::$globalProperty ?? $builtInDefault;
 * ```
 *
 * ## Instance Independence
 * Each DataSaverConfig instance maintains completely isolated state:
 * - No sharing of configuration between instances
 * - No modification of DataSaver global defaults
 * - Safe for concurrent operations
 * - Predictable behavior regardless of other operations
 *
 * ## Fluent Interface Design
 * Every configuration method returns `$this` to enable method chaining:
 * ```php
 * $instance->folder('/exports')     // Returns $this
 *   ->prefix('report_')             // Returns $this
 *   ->suffix('.csv')                // Returns $this
 *   ->type('csv')                   // Returns $this
 *   ->mode('overwrite')             // Returns $this
 *   ->save($data);                  // Performs operation, returns string|false
 * ```
 *
 * ## Property State Management
 * Instance properties use nullable types with fallback resolution:
 * - **null**: Inherit from DataSaver global default
 * - **non-null**: Use instance-specific value (overrides global)
 * - **Exception**: `$mode` has concrete default ('overwrite') and doesn't inherit
 *
 * ## Filename Generation Process
 * When save() is called, the instance resolves filename using this logic:
 * 1. **Explicit filename**: If `$filenameOverride` is set, use it exactly (bypass all generation)
 * 2. **Automatic generation**: Build filename from resolved components:
 *    - folder: `$this->folder ?? DataSaver::$folder ?? __DIR__`
 *    - prefix: `$this->prefix ?? DataSaver::$prefix ?? 'data_'`
 *    - timestamp: Current YmdHis
 *    - microseconds: 6-digit microsecond component
 *    - random: `$this->random ?? DataSaver::$random ?? auto-generated-hex`
 *    - suffix: `$this->suffix ?? DataSaver::$suffix ?? '.json'`
 *
 * ## Format Handler Integration
 * The instance delegates actual file writing to DataSaverTypeRegistry handlers:
 * 1. Resolve format type using configuration hierarchy
 * 2. Validate type is registered in DataSaverTypeRegistry
 * 3. Retrieve appropriate handler function
 * 4. Call handler with filename, data array, and append mode boolean
 * 5. Return handler's success/failure result
 *
 * ## Write Mode Behavior
 * The instance handles different write modes through file system operations:
 * - **overwrite**: Normal file_put_contents (default PHP behavior)
 * - **append**: Passes append=true to format handler for merge logic
 * - **skip_if_exists**: Checks file_exists() before any processing
 *
 * ## Error Handling and Logging
 * The instance integrates with EventManager for comprehensive operation logging:
 * - Configuration resolution events
 * - Filename generation process events
 * - Directory creation attempts and results
 * - Format handler execution and results
 * - Final operation success/failure status
 *
 * ## Directory Management
 * The instance automatically handles directory creation during save():
 * - Checks if target directory exists
 * - Creates missing directories with 0755 permissions
 * - Logs creation attempts via EventManager
 * - Fails save operation if directory creation fails
 *
 * ## Direct Instantiation (Not Recommended)
 * While possible to create DataSaverConfig instances directly, this is not the intended usage:
 * ```php
 * // Not recommended - bypasses DataSaver facade
 * $config = new DataSaverConfig();
 * $config->type('json')->save($data);
 *
 * // Recommended - use DataSaver static interface
 * DataSaver::type('json')->save($data);
 * ```
 *
 * Direct instantiation loses the benefit of DataSaver's global default inheritance
 * and doesn't follow the intended API design patterns.
 *
 * @since 1.0.0
 *
 * @example Basic Fluent Usage
 * ```php
 * // Create instance with custom configuration and save
 * $file = DataSaver::type('csv')
 *     ->suffix('.csv')
 *     ->folder('/exports')
 *     ->prefix('report_')
 *     ->mode('overwrite')
 *     ->save([
 *         ['Name', 'Email', 'Status'],
 *         ['John Doe', 'john@example.com', 'Active'],
 *         ['Jane Smith', 'jane@example.com', 'Pending']
 *     ]);
 *
 * echo "Saved to: $file";
 * ```
 *
 * @example Explicit Filename with Append Mode
 * ```php
 * // Use specific filename, bypass automatic generation
 * $success = DataSaver::filename('/var/log/application.log')
 *     ->type('txt')
 *     ->mode('append')
 *     ->save(['User login: ' . $username, 'Session ID: ' . session_id()]);
 *
 * if ($success === false) {
 *     error_log('Failed to write to application log');
 * }
 * ```
 *
 * @example Configuration Isolation
 * ```php
 * // Each instance maintains independent configuration
 * $csvConfig = DataSaver::type('csv')->suffix('.csv');
 * $jsonConfig = DataSaver::type('json')->suffix('.json');
 *
 * // Neither affects the other or global DataSaver defaults
 * $csvFile = $csvConfig->save($tableData);
 * $jsonFile = $jsonConfig->save($apiResponse);
 * ```
 *
 * @example Mixed Configuration Sources
 * ```php
 * // Global defaults
 * DataSaver::setFolder('/app/data');
 * DataSaver::setPrefix('app_');
 *
 * // Instance override folder, inherit prefix from global
 * $file = DataSaver::folder('/tmp/debug')  // Override: /tmp/debug
 *     ->suffix('.debug')                   // Override: .debug
 *     ->save($debugData);                  // Inherits: app_ prefix from global
 * ```
 *
 * @see DataSaver For global configuration and static factory methods
 * @see DataSaverTypeRegistry For format handler registration and management
 * @see EventManager For operation event logging and debugging
 */
class DataSaverConfig
{

    /**
     * Instance-specific folder path override for this save operation
     *
     * When set, this property overrides the global DataSaver folder default for this
     * specific instance. When null, the save operation falls back to the global
     * folder setting from DataSaver::setFolder() or the built-in default (__DIR__).
     * This enables per-operation directory customization without affecting other
     * operations or global state.
     *
     * ## Fallback Hierarchy
     * The save operation resolves the folder in this order:
     * 1. This instance property (if not null) - highest priority
     * 2. DataSaver global default from setFolder()
     * 3. Built-in default: __DIR__ (current script directory)
     *
     * ## Path Processing
     * Values are normalized by removing trailing slashes via rtrim() in the folder()
     * method. Directory creation is handled automatically during save operations with
     * 0755 permissions if the target directory doesn't exist.
     *
     * ## Independence from Global State
     * Setting this property doesn't affect DataSaver global defaults or other
     * DataSaverConfig instances. Each instance maintains its own independent
     * configuration state.
     *
     * @var string|null Custom folder path for this operation, or null to use global default.
     *                  Normalized to remove trailing slashes when set via folder() method.
     *
     * @see DataSaverConfig::folder() Method that sets this property
     * @see DataSaver::setFolder() Global folder configuration
     * @since 1.0.0
     */
    protected ?string $folder = null;

    /**
     * Instance-specific filename prefix override for this save operation
     *
     * When set, this property overrides the global DataSaver prefix default for this
     * specific instance. When null, the save operation falls back to the global
     * prefix setting from DataSaver::setPrefix() or the built-in default ('data_').
     * The prefix becomes part of the automatic filename generation pattern.
     *
     * ## Fallback Hierarchy
     * The save operation resolves the prefix in this order:
     * 1. This instance property (if not null) - highest priority
     * 2. DataSaver global default from setPrefix()
     * 3. Built-in default: 'data_'
     *
     * ## Filename Pattern Role
     * When automatic filename generation is used, the prefix forms part of:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Common Use Cases
     * - Operation-specific prefixes: 'export_', 'log_', 'backup_'
     * - User or session identification: 'user123_', 'session_'
     * - Empty string to disable prefixing for this operation
     *
     * ## Independence from Global State
     * Setting this property affects only this instance and doesn't modify
     * DataSaver global defaults or other DataSaverConfig instances.
     *
     * @var string|null Custom filename prefix for this operation, or null to use global default.
     *                  Can be empty string to disable prefixing for this operation.
     *
     * @see DataSaverConfig::prefix() Method that sets this property
     * @see DataSaver::setPrefix() Global prefix configuration
     * @since 1.0.0
     */
    protected ?string $prefix = null;

    /**
     * Instance-specific file extension suffix override for this save operation
     *
     * When set, this property overrides the global DataSaver suffix default for this
     * specific instance. When null, the save operation falls back to the global
     * suffix setting from DataSaver::setSuffix() or the built-in default ('.json').
     * The suffix should include the leading dot and appropriate file extension.
     *
     * ## Fallback Hierarchy
     * The save operation resolves the suffix in this order:
     * 1. This instance property (if not null) - highest priority
     * 2. DataSaver global default from setSuffix()
     * 3. Built-in default: '.json'
     *
     * ## File Extension Guidelines
     * - Include leading dot: '.csv', '.txt', '.log', '.xml'
     * - Match output format type for consistency
     * - Can be empty string to create files without extensions
     * - Should align with registered DataSaverTypeRegistry handlers
     *
     * ## Format Consistency
     * Common suffix/type pairings for consistency:
     * - '.json' with type('json') for JSON data
     * - '.csv' with type('csv') for tabular data
     * - '.txt' or '.log' with type('txt') for plain text
     *
     * ## Independence from Global State
     * Setting this property affects only this instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @var string|null Custom file extension suffix including leading dot, or null for global default.
     *                  Should match the output format type for proper file recognition.
     *
     * @see DataSaverConfig::suffix() Method that sets this property
     * @see DataSaver::setSuffix() Global suffix configuration
     * @since 1.0.0
     */
    protected ?string $suffix = null;

    /**
     * Instance-specific random identifier override for filename generation
     *
     * When set, this property provides a fixed random identifier for automatic filename
     * generation instead of using the global DataSaver random default. When null, falls
     * back to the global random setting from DataSaver::setRandom(), and ultimately to
     * automatic generation of 6-character hexadecimal IDs if all sources are null.
     *
     * ## Fallback Hierarchy
     * The save operation resolves the random ID in this order:
     * 1. This instance property (if not null) - highest priority
     * 2. DataSaver global default from setRandom()
     * 3. Automatic generation: 6-character hex from random_bytes()
     *
     * ## Filename Pattern Role
     * The random identifier becomes part of the automatic filename:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Generation vs Fixed Behavior
     * - **Fixed ID**: Consistent random component for predictable filenames
     * - **Automatic ID**: New random component per save for guaranteed uniqueness
     * - **Empty string**: Valid fixed value resulting in no random component
     *
     * ## Use Cases for Fixed Values
     * - Testing scenarios requiring predictable filenames
     * - Session or transaction-based file identification
     * - Batch processing with consistent operation identifiers
     * - Debug scenarios where filename predictability is valuable
     *
     * ## Independence from Global State
     * Setting this property affects only this instance and doesn't modify
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @var string|null Fixed random identifier for this operation, or null for fallback resolution.
     *                  When all sources are null, auto-generates 6-character hex IDs.
     *
     * @see DataSaverConfig::random() Method that sets this property
     * @see DataSaver::setRandom() Global random ID configuration
     * @since 1.0.0
     */
    protected ?string $random = null;

    /**
     * Instance-specific output format type override for data serialization
     *
     * When set, this property overrides the global DataSaver type default for this
     * specific instance. When null, falls back to the global type setting from
     * DataSaver::setType() or the built-in default ('json'). The type determines
     * which registered handler from DataSaverTypeRegistry processes the array data.
     *
     * ## Fallback Hierarchy
     * The save operation resolves the type in this order:
     * 1. This instance property (if not null) - highest priority
     * 2. DataSaver global default from setType()
     * 3. Built-in default: 'json'
     *
     * ## Format Handler Integration
     * The type is used to look up registered handlers in DataSaverTypeRegistry.
     * Each handler receives the filename, data array, and append mode boolean,
     * returning success/failure status. Invalid types cause save operations to fail.
     *
     * ## Built-in Format Types
     * - **json**: Pretty-printed JSON with merge support in append mode
     * - **csv**: Comma-separated values for tabular data structures
     * - **txt**: Plain text with one array element per line
     *
     * ## Type Validation
     * The type is validated against DataSaverTypeRegistry during save operations.
     * Unregistered types trigger EventManager events and cause save operations
     * to return false rather than throwing exceptions.
     *
     * ## Independence from Global State
     * Setting this property affects only this instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @var string|null Output format type identifier, or null to use global default.
     *                  Must correspond to registered DataSaverTypeRegistry handler.
     *
     * @see DataSaverConfig::type() Method that sets this property with validation
     * @see DataSaver::setType() Global type configuration
     * @see DataSaverTypeRegistry::isRegistered() Type validation method
     * @since 1.0.0
     */
    protected ?string $type = null;

    /**
     * Write mode determining how save operations handle existing files
     *
     * Controls the behavior when the target file already exists. Unlike other properties,
     * this has a concrete default value ('overwrite') rather than falling back to global
     * defaults. The mode affects both filename-based and explicit file operations.
     *
     * ## Write Mode Options
     * - **overwrite** (default): Replace existing file contents completely
     * - **append**: Add data to existing file using format-specific merge logic
     * - **skip_if_exists**: Return false immediately if target file exists
     *
     * ## Format-Specific Append Behavior
     * Each format type handles append mode differently:
     * - **JSON**: Merges new data with existing data structure recursively
     * - **CSV**: Appends new rows to existing file without headers
     * - **TXT**: Adds new lines to existing file content
     *
     * ## Default vs Global Behavior
     * Unlike other configuration properties, $mode has its own default value and
     * doesn't fall back to DataSaver global settings. This ensures consistent
     * write behavior when no explicit mode is specified.
     *
     * ## File Existence Handling
     * - **overwrite**: Truncates existing files, creates if not present
     * - **append**: Preserves existing content, creates if not present
     * - **skip_if_exists**: No operation if file exists, normal operation if not
     *
     * ## Instance Independence
     * Each DataSaverConfig instance maintains its own mode setting independently
     * of other instances and global DataSaver configuration.
     *
     * @var string Write mode for file operations. Default: 'overwrite'.
     *             Valid values: 'overwrite', 'append', 'skip_if_exists'
     *
     * @see DataSaverConfig::mode() Method that sets and validates this property
     * @since 1.0.0
     */
    protected string $mode = 'overwrite';

    /**
     * Instance-specific explicit filename that bypasses automatic generation
     *
     * When set to a non-null value, this property completely disables automatic filename
     * generation for this instance and uses the exact path provided. All other naming
     * components (folder, prefix, suffix, timestamp, random) are ignored when this
     * property is set. This enables precise control over output file location and name.
     *
     * ## Parameter vs Property Relationship
     * - Method parameter: `filename(string $fullPath)` - the input filename
     * - Storage property: `$filenameOverride` - where the value is stored (this property)
     * - Similar to: `DataSaver::setFilename($filename)` -> `DataSaver::$filenameOverride`
     *
     * ## Bypass Behavior
     * When this property is not null:
     * - All automatic filename generation is disabled
     * - Instance folder, prefix, suffix settings are ignored
     * - Timestamp and random ID generation is skipped
     * - The exact path in this property is used for file operations
     *
     * ## Path Flexibility
     * - Can be absolute or relative file paths
     * - Directory components are created automatically if needed during save
     * - No validation of file extensions or naming conventions
     * - Supports any valid filesystem path format
     *
     * ## Global Default Relationship
     * This property is independent of DataSaver::$filenameOverride. Setting this
     * instance property doesn't affect the global DataSaver configuration or other
     * DataSaverConfig instances.
     *
     * ## Instance Independence
     * Each DataSaverConfig instance maintains its own filename override independently,
     * enabling different explicit filenames for concurrent operations.
     *
     * @var string|null Explicit filename for this operation, or null for automatic generation.
     *                  Set via filename() method parameter, bypasses all naming components when set.
     *
     * @see DataSaverConfig::filename() Method that sets this property (parameter $fullPath -> $filenameOverride)
     * @see DataSaver::$filenameOverride Global filename override property (independent)
     * @since 1.0.0
     */
    protected ?string $filenameOverride = null;

    /**
     * Set custom folder path for this operation and enable method chaining
     *
     * Configures the folder path where this specific save operation will write files.
     * The path is normalized by removing trailing slashes and stored in the instance
     * $folder property. This setting takes precedence over DataSaver global folder
     * defaults for this operation only.
     *
     * ## Path Normalization
     * Trailing slashes are automatically removed using rtrim() to ensure consistent
     * path handling across different input formats. Both relative and absolute paths
     * are supported.
     *
     * ## Directory Creation
     * If the specified folder doesn't exist during save operations, it will be created
     * automatically with 0755 permissions. Directory creation failures are logged via
     * EventManager and cause save operations to return false.
     *
     * ## Configuration Hierarchy
     * When save() executes, folder resolution follows this priority:
     * 1. This instance $folder property (if set via this method) - highest priority
     * 2. DataSaver global default from setFolder()
     * 3. Built-in default: __DIR__ (current script directory)
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $folder The directory path where the file should be saved.
     *                      Can be relative or absolute, trailing slashes automatically removed.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @example Folder Configuration
     * ```php
     * // Absolute path
     * $result = DataSaver::folder('/var/log/exports')
     *     ->prefix('export_')
     *     ->save($exportData);
     *
     * // Relative path with chaining
     * $debugFile = DataSaver::folder('./debug')
     *     ->suffix('.debug')
     *     ->type('txt')
     *     ->save(['Debug info here']);
     *
     * // Trailing slashes are normalized
     * DataSaver::folder('/tmp/output/')->save($data); // Becomes '/tmp/output'
     * ```
     *
     * @see DataSaverConfig::$folder The property where the folder is stored
     * @see DataSaver::setFolder() Global folder configuration
     * @since 1.0.0
     */
    public function folder(string $folder): static
    {
        $this->folder = rtrim($folder, '/');
        return $this;
    }

    /**
     * Set custom filename prefix for this operation and enable method chaining
     *
     * Configures the prefix string that will be prepended to automatically generated
     * filenames for this specific save operation. The prefix is stored in the instance
     * $prefix property and takes precedence over DataSaver global prefix defaults.
     * This setting is ignored when explicit filenames are used via filename() method.
     *
     * ## Filename Pattern Integration
     * When automatic filename generation is used, the prefix becomes part of:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Prefix Flexibility
     * - Can be any string including empty string to disable prefixing
     * - Common patterns: 'log_', 'export_', 'backup_', 'user123_'
     * - No automatic normalization or validation is performed
     *
     * ## Configuration Hierarchy
     * When save() executes, prefix resolution follows this priority:
     * 1. This instance $prefix property (if set via this method) - highest priority
     * 2. DataSaver global default from setPrefix()
     * 3. Built-in default: 'data_'
     *
     * ## Explicit Filename Override
     * When filename() is used to specify an exact file path, the prefix setting
     * is ignored since automatic filename generation is bypassed entirely.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $prefix The prefix string to prepend to generated filenames.
     *                      Can be empty string to disable prefixing for this operation.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @example Prefix Configuration
     * ```php
     * // Custom prefix for export operations
     * $exportFile = DataSaver::prefix('export_')
     *     ->suffix('.csv')
     *     ->type('csv')
     *     ->save($csvData);
     *
     * // User-specific prefix
     * $userFile = DataSaver::prefix('user_' . $userId . '_')
     *     ->save($userData);
     *
     * // Disable prefix for this operation
     * $cleanFile = DataSaver::prefix('')->save($data);
     * ```
     *
     * @see DataSaverConfig::$prefix The property where the prefix is stored
     * @see DataSaver::setPrefix() Global prefix configuration
     * @since 1.0.0
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Set custom file extension suffix for this operation and enable method chaining
     *
     * Configures the file extension suffix that will be appended to automatically
     * generated filenames for this specific save operation. The suffix should include
     * the leading dot and is stored in the instance $suffix property. This setting
     * takes precedence over DataSaver global suffix defaults.
     *
     * ## File Extension Guidelines
     * - Include the leading dot: '.json', '.csv', '.txt', '.log'
     * - Should align with the output format type for consistency
     * - Can be empty string to create files without extensions
     * - No validation against format type is performed
     *
     * ## Filename Pattern Integration
     * When automatic filename generation is used, the suffix becomes the final part of:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Configuration Hierarchy
     * When save() executes, suffix resolution follows this priority:
     * 1. This instance $suffix property (if set via this method) - highest priority
     * 2. DataSaver global default from setSuffix()
     * 3. Built-in default: '.json'
     *
     * ## Format Type Alignment
     * While not enforced, it's recommended to align suffix with format type:
     * - '.json' with type('json') for JSON data files
     * - '.csv' with type('csv') for tabular data files
     * - '.txt' or '.log' with type('txt') for plain text files
     *
     * ## Explicit Filename Override
     * When filename() is used to specify an exact file path, the suffix setting
     * is ignored since automatic filename generation is bypassed entirely.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $suffix The file extension suffix including the leading dot.
     *                      Can be empty string to create extensionless files.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @example Suffix Configuration
     * ```php
     * // CSV export with proper extension
     * $csvFile = DataSaver::suffix('.csv')
     *     ->type('csv')
     *     ->save($tabularData);
     *
     * // Custom log extension
     * $logFile = DataSaver::suffix('.log')
     *     ->type('txt')
     *     ->mode('append')
     *     ->save(['Log entry']);
     *
     * // No extension
     * $rawFile = DataSaver::suffix('')->save($binaryData);
     * ```
     *
     * @see DataSaverConfig::$suffix The property where the suffix is stored
     * @see DataSaver::setSuffix() Global suffix configuration
     * @since 1.0.0
     */
    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;
        return $this;
    }

    /**
     * Set custom random identifier for filename generation and enable method chaining
     *
     * Configures a fixed random identifier that will be used in automatically generated
     * filenames for this specific save operation. The identifier is stored in the instance
     * $random property and takes precedence over DataSaver global random defaults.
     * This enables predictable filename generation for testing or consistent operation tracking.
     *
     * ## Fixed vs Automatic Random IDs
     * - **Fixed ID**: Uses the provided string consistently for this operation
     * - **Automatic ID**: When all sources are null, generates 6-character hex IDs
     * - **Empty string**: Valid fixed value resulting in no random component
     *
     * ## Filename Pattern Integration
     * When automatic filename generation is used, the random identifier becomes part of:
     * `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Configuration Hierarchy
     * When save() executes, random ID resolution follows this priority:
     * 1. This instance $random property (if set via this method) - highest priority
     * 2. DataSaver global default from setRandom()
     * 3. Auto-generation: 6-character hex from random_bytes()
     *
     * ## Use Cases for Fixed IDs
     * - Testing scenarios requiring predictable filenames
     * - Session-based file identification using session_id()
     * - Batch processing with operation-specific identifiers
     * - Debug scenarios where filename traceability is important
     *
     * ## Explicit Filename Override
     * When filename() is used to specify an exact file path, the random setting
     * is ignored since automatic filename generation is bypassed entirely.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $random The fixed random identifier to use in filename generation.
     *                      Can be any string including empty string for no random component.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @example Random ID Configuration
     * ```php
     * // Session-based identification
     * $sessionFile = DataSaver::random(session_id())
     *     ->prefix('session_')
     *     ->save($sessionData);
     *
     * // Batch processing identifier
     * $batchFile = DataSaver::random('batch_' . $batchId)
     *     ->save($batchResults);
     *
     * // Testing with predictable ID
     * $testFile = DataSaver::random('test123')->save($testData);
     *
     * // No random component
     * $cleanFile = DataSaver::random('')->save($data);
     * ```
     *
     * @see DataSaverConfig::$random The property where the random ID is stored
     * @see DataSaver::setRandom() Global random ID configuration
     * @since 1.0.0
     */
    public function random(string $random): static
    {
        $this->random = $random;
        return $this;
    }

    /**
     * Set output format type for data serialization and enable method chaining
     *
     * Configures the data format type that determines how array data is serialized
     * and written to files for this specific save operation. The type must be registered
     * in DataSaverTypeRegistry and is stored in the instance $type property. This
     * setting takes precedence over DataSaver global type defaults.
     *
     * ## Format Handler Lookup
     * The type is used to retrieve the appropriate handler from DataSaverTypeRegistry
     * during save operations. Each handler receives the filename, data array, and
     * append mode boolean, returning success/failure status.
     *
     * ## Built-in Format Types
     * - **json**: Pretty-printed JSON with recursive merge support in append mode
     * - **csv**: Comma-separated values expecting array of arrays for row data
     * - **txt**: Plain text with one array element per line, joined with commas for nested arrays
     *
     * ## Type Validation
     * The type is validated using DataSaverTypeRegistry::isRegistered() when this method
     * is called. Invalid types throw InvalidArgumentException immediately rather than
     * failing during save operations. This provides early error detection.
     *
     * ## Configuration Hierarchy
     * When save() executes, type resolution follows this priority:
     * 1. This instance $type property (if set via this method) - highest priority
     * 2. DataSaver global default from setType()
     * 3. Built-in default: 'json'
     *
     * ## Case Normalization
     * Type values are automatically converted to lowercase for consistent lookup
     * in the DataSaverTypeRegistry regardless of input case.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $type The output format type identifier (case-insensitive).
     *                    Must be registered in DataSaverTypeRegistry.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @throws InvalidArgumentException If the type is not registered in DataSaverTypeRegistry.
     *
     * @example Type Configuration
     * ```php
     * // CSV format with proper extension
     * $csvFile = DataSaver::type('csv')
     *     ->suffix('.csv')
     *     ->save([
     *         ['Name', 'Age', 'City'],
     *         ['John', 30, 'New York'],
     *         ['Jane', 25, 'Boston']
     *     ]);
     *
     * // Plain text logging
     * $logFile = DataSaver::type('txt')
     *     ->suffix('.log')
     *     ->mode('append')
     *     ->save(['Application started', 'User logged in']);
     *
     * // Case insensitive
     * $jsonFile = DataSaver::type('JSON')->save($data); // Becomes 'json'
     * ```
     *
     * @see DataSaverConfig::$type The property where the type is stored
     * @see DataSaver::setType() Global type configuration
     * @see DataSaverTypeRegistry::isRegistered() Type validation method
     * @see DataSaverTypeRegistry::register() Custom type registration
     * @since 1.0.0
     */
    public function type(string $type): static
    {
        if (!DataSaverTypeRegistry::isRegistered($type)) {
            throw new InvalidArgumentException("Unsupported type: $type");
        }
        $this->type = strtolower($type);
        return $this;
    }

    /**
     * Set write mode for file operations and enable method chaining
     *
     * Configures how this save operation handles existing files. The mode determines
     * whether to overwrite, append to, or skip existing files entirely. The mode is
     * stored in the instance $mode property and validated immediately when set.
     *
     * ## Write Mode Options
     * - **overwrite**: Replace existing file contents completely (default behavior)
     * - **append**: Add data to existing file using format-specific merge logic
     * - **skip_if_exists**: Return false immediately if target file already exists
     *
     * ## Format-Specific Append Behavior
     * Each registered format type handles append mode differently:
     * - **JSON**: Recursively merges new data with existing JSON structure
     * - **CSV**: Appends new rows to existing file without duplicating headers
     * - **TXT**: Adds new lines to existing file content with newline separation
     *
     * ## File Creation Behavior
     * All modes create new files if the target doesn't exist:
     * - **overwrite**: Creates file if not present (same as normal write)
     * - **append**: Creates file if not present (same as normal write)
     * - **skip_if_exists**: Creates file if not present, skips if exists
     *
     * ## Mode Validation
     * The mode value is validated immediately when set. Invalid modes throw
     * InvalidArgumentException with descriptive error messages rather than
     * failing during save operations.
     *
     * ## Case Normalization
     * Mode values are automatically converted to lowercase for consistent
     * validation and processing regardless of input case.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $mode The write mode (case-insensitive).
     *                    Valid values: 'overwrite', 'append', 'skip_if_exists'
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @throws InvalidArgumentException If mode is not one of the valid options.
     *
     * @example Mode Configuration
     * ```php
     * // Overwrite existing file completely
     * $newFile = DataSaver::mode('overwrite')
     *     ->save($completeDataSet);
     *
     * // Append to log file
     * $logResult = DataSaver::mode('append')
     *     ->filename('/var/log/app.log')
     *     ->type('txt')
     *     ->save(['New log entry']);
     *
     * // Skip if file already exists (one-time export)
     * $exportResult = DataSaver::mode('skip_if_exists')
     *     ->prefix('export_')
     *     ->save($exportData);
     *
     * if ($exportResult === false) {
     *     echo "Export skipped - file already exists";
     * }
     *
     * // Case insensitive
     * DataSaver::mode('APPEND')->save($data); // Becomes 'append'
     * ```
     *
     * @see DataSaverConfig::$mode The property where the mode is stored
     * @since 1.0.0
     */
    public function mode(string $mode): static
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['overwrite', 'append', 'skip_if_exists'], true)) {
            throw new InvalidArgumentException("Invalid mode: $mode (expected overwrite, append, or skip_if_exists)");
        }
        $this->mode = $mode;
        return $this;
    }

    /**
     * Set explicit filename to bypass automatic generation and enable method chaining
     *
     * Configures an exact file path to use for this save operation instead of automatic
     * filename generation. The provided path is stored in the instance $filenameOverride
     * property. When set, all automatic naming components (folder, prefix, suffix,
     * timestamp, random ID) are ignored for this operation.
     *
     * ## Parameter to Property Relationship
     * - Method parameter: `$fullPath` (the input filename to use)
     * - Storage property: `$filenameOverride` (where the value is stored)
     * - Behavior: Bypasses all automatic filename generation when set
     *
     * ## Filename Override Behavior
     * When this method is called, the save operation:
     * - Uses the exact path provided, no modifications
     * - Ignores instance folder, prefix, suffix settings
     * - Skips timestamp and random ID generation
     * - Bypasses all automatic naming logic
     *
     * ## Path Flexibility
     * - Accepts both relative and absolute file paths
     * - No validation of file extensions or naming conventions
     * - Directory components created automatically if needed during save
     * - Supports any valid filesystem path format
     *
     * ## Directory Creation
     * If parent directories in the specified path don't exist, they will be created
     * automatically during save operations with 0755 permissions. Creation failures
     * are logged via EventManager and cause save operations to return false.
     *
     * ## Instance Independence
     * This method affects only this DataSaverConfig instance without modifying
     * global DataSaver defaults or other DataSaverConfig instances.
     *
     * @param string $fullPath The complete file path to use for the save operation.
     *                        Can be relative or absolute, with or without extension.
     *                        Stored in the $filenameOverride property.
     * @return static This DataSaverConfig instance for method chaining.
     *
     * @example Explicit Filename Usage
     * ```php
     * // Specific log file with append mode
     * $result = DataSaver::filename('/var/log/application.log')
     *     ->type('txt')
     *     ->mode('append')
     *     ->save(['Application started at ' . date('Y-m-d H:i:s')]);
     *
     * // Relative path output
     * $outputFile = DataSaver::filename('results/output.json')
     *     ->type('json')
     *     ->save($processResults);
     *
     * // Custom extension with specific location
     * $customFile = DataSaver::filename('/tmp/debug.custom')
     *     ->type('txt')
     *     ->save($debugData);
     *
     * // Override ignores other naming settings
     * $exactFile = DataSaver::filename('/exact/path.dat')
     *     ->prefix('ignored')    // These settings are ignored
     *     ->suffix('.ignored')   // when filename() is used
     *     ->folder('/ignored')
     *     ->save($data);
     * ```
     *
     * @see DataSaverConfig::$filenameOverride The property where the filename is stored
     * @see DataSaver::setFilename() Global filename override (independent)
     * @since 1.0.0
     */
    public function filename(string $fullPath): static
    {
        $this->filenameOverride = $fullPath;
        return $this;
    }

    /**
     * Execute the save operation using configured settings and format handler
     *
     * Performs the actual file save operation using the current instance configuration
     * combined with DataSaver global defaults where needed. Returns the full path to
     * the saved file on success, or false on failure. This method integrates filename
     * generation, directory creation, format handling, and comprehensive error logging.
     *
     * ## Configuration Resolution Process
     * The save operation resolves settings using this hierarchy for each property:
     * 1. Instance configuration (set via fluent methods) - highest priority
     * 2. DataSaver global defaults (set via DataSaver static methods)
     * 3. Built-in defaults (hardcoded fallbacks)
     *
     * ## Filename Generation Logic
     * Two filename determination paths:
     * - **Explicit**: Uses $filenameOverride if set, ignoring all other naming components
     * - **Automatic**: Generates using pattern `{folder}/{prefix}{timestamp}_{microseconds}_{random}{suffix}`
     *
     * ## Automatic Generation Components
     * When generating filenames automatically:
     * - **timestamp**: Current datetime in YmdHis format (e.g., 20250830_143022)
     * - **microseconds**: 6-digit microsecond component for sub-second uniqueness
     * - **random**: Instance/global setting or 6-character hex from random_bytes()
     *
     * ## Directory Management
     * Target directories are created automatically if they don't exist, using 0755 permissions.
     * Directory creation is attempted before file operations and failures are logged via
     * EventManager with detailed error information.
     *
     * ## Write Mode Handling
     * - **overwrite**: Replaces existing file contents (default PHP file_put_contents behavior)
     * - **append**: Uses format-specific append logic via registered handlers
     * - **skip_if_exists**: Checks file existence before any processing, returns false if exists
     *
     * ## Format Handler Integration
     * The resolved type is used to retrieve a handler from DataSaverTypeRegistry. Each handler
     * receives the final filename, array data, and append mode boolean. Handler return values
     * determine save operation success/failure status.
     *
     * ## Error Handling Strategy
     * Failed operations return false rather than throwing exceptions. Detailed error information
     * is logged via EventManager events for debugging and monitoring. Common failure scenarios
     * include unregistered types, directory creation failures, and handler execution errors.
     *
     * ## Event Integration
     * The save process fires EventManager events at key points for monitoring and debugging:
     * - Configuration resolution and validation
     * - Filename generation (automatic vs explicit)
     * - Directory creation attempts
     * - Format handler execution
     * - Final operation results
     *
     * @param array $mydata The array data to save. Can be empty array for placeholder files.
     *                     Data structure should match the requirements of the selected format type.
     * @return string|false Full path to the saved file on success, false on any failure.
     *                     False indicates operation failure - check EventManager events for details.
     *
     * @throws Random\RandomException If random_bytes() fails during automatic ID generation.
     *                               This is a rare system-level error indicating insufficient entropy.
     *
     * @example Successful Save Operations
     * ```php
     * // Basic save with automatic filename
     * $file = DataSaver::type('json')->save(['status' => 'complete']);
     * if ($file !== false) {
     *     echo "Saved to: $file";
     * }
     *
     * // Complex configuration with error handling
     * $csvFile = DataSaver::type('csv')
     *     ->folder('/exports')
     *     ->prefix('report_')
     *     ->suffix('.csv')
     *     ->mode('overwrite')
     *     ->save([
     *         ['ID', 'Name', 'Status'],
     *         [1, 'John Doe', 'Active'],
     *         [2, 'Jane Smith', 'Pending']
     *     ]);
     *
     * if ($csvFile === false) {
     *     error_log('CSV export failed - check EventManager logs');
     * }
     *
     * // Explicit filename with append mode
     * $logResult = DataSaver::filename('/var/log/app.log')
     *     ->type('txt')
     *     ->mode('append')
     *     ->save(['User login: ' . $username]);
     * ```
     *
     * @example Error Scenarios
     * ```php
     * // Unregistered type (returns false)
     * $result = DataSaver::type('nonexistent')->save($data);
     * // $result === false, EventManager event fired
     *
     * // Skip existing file
     * $skipResult = DataSaver::mode('skip_if_exists')
     *     ->filename('/existing/file.json')
     *     ->save($data);
     * // $skipResult === false if file exists, EventManager event fired
     * ```
     *
     * @see DataSaverTypeRegistry::get() Format handler retrieval and validation
     * @see EventManager::fire() Error logging and operation monitoring
     * @see DataSaver::defaults() Global configuration fallback values
     * @since 1.0.0
     */
    public function save(array $mydata = []): string|false
    {
        $defaults = DataSaver::defaults();

        $type = strtolower($this->type ?? $defaults['type']);
        if (!DataSaverTypeRegistry::isRegistered($type)) {
            EventManager::fire('dataSaverConfig.save.type_unknown', $type);
            return false;
        }

        // If full filename is given explicitly or via static config, use that
        $filename = $this->filenameOverride ?? $defaults['filename'];
        if (!$filename) {

            EventManager::fire('dataSaverConfig.filename.building_our_own', [] );

            $folder = $this->folder ?? $defaults['folder'];
            $prefix = $this->prefix ?? $defaults['prefix'];
            $suffix = $this->suffix ?? $defaults['suffix'];
            $random = $this->random ?? $defaults['random'] ?? substr(bin2hex(random_bytes(3)), 0, 6);

            $now = microtime(true);
            $datetime = date('Ymd_His', (int) $now);
            $micros = sprintf('%06d', ($now - floor($now)) * 1_000_000);

            if (!is_dir($folder)) {
                if (!mkdir($folder, 0755, true) && !is_dir($folder)) {
                    EventManager::fire('dataSaverConfig.mkdir.failed', ['folder' => $folder, 'error' => error_get_last()]);
                    return false;
                } else {
                    EventManager::fire('dataSaverConfig.mkdir.success', ['folder' => $folder, 'error' => 'none']);
                }
            } else {
                EventManager::fire('dataSaverConfig.is_dir', ['folder' => $folder, 'error' => 'none']);
            }

            $filename = sprintf(
                '%s/%s%s_%s_%s%s',
                $folder,
                $prefix,
                $datetime,
                $micros,
                $random,
                $suffix
            );

            EventManager::fire('dataSaverConfig.filename.built_our_own', ['filename' => $filename ] );
        } else {
            EventManager::fire('dataSaverConfig.filename.using_supplied', ['filename' => $filename ] );
        }

        // Skip if requested and file exists
        if ($this->mode === 'skip_if_exists' && file_exists($filename)) {
            EventManager::fire('dataSaverConfig.filename.skipping', ['filename' => $filename ] );
            return false;
        }

        $append = $this->mode === 'append';
        EventManager::fire('dataSaverConfig.mode', ['mode' => $this->mode ] );

        $callback = DataSaverTypeRegistry::get($type);
        $callbackResults = $callback($filename, $mydata, $append);

        EventManager::fire('dataSaverConfig.CallbackResults', ['callbackResults' => $callbackResults ] );

        $res = $callbackResults ? $filename : false;

        EventManager::fire('dataSaverConfig.returning', ['returning' => $res ] );

        return $res;

    }
}
