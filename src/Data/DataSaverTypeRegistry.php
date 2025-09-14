<?php
/**
 * File: /vendor/vernsix/primordyx/src/DataSaverTypeRegistry.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Data/DataSaverTypeRegistry.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use InvalidArgumentException;

/**
 * Central registry and handler management system for DataSaver output format types
 *
 * DataSaverTypeRegistry serves as the pluggable format system backbone for the DataSaver
 * framework, providing registration, validation, and retrieval of format handlers. It
 * ensures built-in types (json, csv, txt) are always available while supporting unlimited
 * custom format registration for specialized data serialization needs.
 *
 * ## Registry Architecture
 * The registry operates as a static service class that:
 * - **Manages Format Handlers**: Stores callable functions that serialize array data to files
 * - **Ensures Built-in Availability**: Automatically registers json, csv, txt handlers on first use
 * - **Supports Custom Types**: Allows registration of unlimited custom format handlers
 * - **Provides Validation**: Checks type availability before DataSaver operations execute
 * - **Enables Extensibility**: Plugin-like system for adding new output formats
 *
 * ## Handler Function Interface
 * All registered handlers must follow this signature:
 * ```php
 * function(string $filename, array $data, bool $append): bool
 * ```
 *
 * Where:
 * - **$filename**: Target file path for output
 * - **$data**: Array data to serialize and write
 * - **$append**: Whether to append to existing file or overwrite
 * - **Return**: Boolean success/failure status
 *
 * ## Built-in Format Types
 * The registry automatically provides these handlers on first use:
 *
 * ### JSON Handler
 * - **Format**: Pretty-printed JSON with proper escaping
 * - **Append Mode**: Recursively merges with existing JSON data structure
 * - **Data Structure**: Any valid PHP array that json_encode() can handle
 * - **File Extension**: Typically .json
 * - **Use Cases**: API responses, configuration files, structured data export
 *
 * ### CSV Handler
 * - **Format**: Standard comma-separated values with fputcsv()
 * - **Append Mode**: Adds new rows to existing file without header duplication
 * - **Data Structure**: Array of arrays (rows), or array of scalars (single row)
 * - **File Extension**: Typically .csv
 * - **Use Cases**: Spreadsheet export, tabular data, database dumps
 *
 * ### TXT Handler
 * - **Format**: Plain text with one array element per line
 * - **Append Mode**: Adds new lines to existing file content
 * - **Data Structure**: Array elements joined with commas for nested arrays
 * - **File Extension**: Typically .txt or .log
 * - **Use Cases**: Log files, simple lists, human-readable output
 *
 * ## Registration System
 * Custom handlers are registered using the static register() method:
 * ```php
 * DataSaverTypeRegistry::register('xml', function(string $filename, array $data, bool $append): bool {
 *     $xml = '<?xml version="1.0"?><root>';
 *     foreach ($data as $key => $value) {
 *         $xml .= "<item key=\"$key\">$value</item>";
 *     }
 *     $xml .= '</root>';
 *
 *     $flags = $append ? FILE_APPEND : 0;
 *     return file_put_contents($filename, $xml, $flags) !== false;
 * });
 * ```
 *
 * ## Integration with DataSaver System
 * The registry integrates tightly with DataSaver operations:
 * 1. **Type Validation**: DataSaver calls isRegistered() before operations
 * 2. **Handler Retrieval**: DataSaver calls get() to retrieve format handlers
 * 3. **Format Execution**: DataSaver passes filename, data, and append mode to handlers
 * 4. **Error Handling**: Invalid types cause operations to fail gracefully
 *
 * ## Lazy Initialization Strategy
 * Built-in handlers use lazy initialization via the init() method:
 * - Called automatically on first registry access
 * - Prevents handler creation overhead until actually needed
 * - Allows built-in handler override if registered before first use
 * - Ensures consistent availability across different application entry points
 *
 * ## Handler Development Guidelines
 * When creating custom handlers, consider:
 * - **File Safety**: Handle file operation failures gracefully
 * - **Append Logic**: Implement meaningful append behavior for your format
 * - **Data Validation**: Validate input data structure matches format requirements
 * - **Error Handling**: Return false on any failure, true only on complete success
 * - **Performance**: Optimize for typical data sizes in your use cases
 * - **Consistency**: Follow established patterns from built-in handlers
 *
 * ## Thread Safety and State
 * The registry maintains global state through static properties but is designed for
 * safe concurrent access patterns. Handler registration is typically done during
 * application bootstrap, with retrieval occurring during request processing.
 *
 * ## Extension Points
 * The registry supports several extension patterns:
 * - **Format-Specific Options**: Handlers can access external configuration
 * - **Handler Inheritance**: New handlers can wrap existing ones for enhanced functionality
 * - **Dynamic Registration**: Handlers can be registered conditionally based on runtime state
 * - **Plugin Systems**: Third-party packages can register handlers via service providers
 *
 * @since 1.0.0
 *
 * @example Basic Custom Handler Registration
 * ```php
 * // Register a simple pipe-delimited format
 * DataSaverTypeRegistry::register('pipe', function(string $filename, array $data, bool $append): bool {
 *     $lines = [];
 *     foreach ($data as $row) {
 *         if (is_array($row)) {
 *             $lines[] = implode('|', $row);
 *         } else {
 *             $lines[] = (string)$row;
 *         }
 *     }
 *
 *     $content = implode("\n", $lines) . "\n";
 *     $flags = $append ? FILE_APPEND : 0;
 *     return file_put_contents($filename, $content, $flags) !== false;
 * });
 *
 * // Use the custom format
 * DataSaver::type('pipe')->suffix('.pipe')->save([
 *     ['Name', 'Age', 'City'],
 *     ['John', '30', 'NYC'],
 *     ['Jane', '25', 'LA']
 * ]);
 * ```
 *
 * @example Advanced Handler with Validation
 * ```php
 * // Register XML handler with data validation
 * DataSaverTypeRegistry::register('xml', function(string $filename, array $data, bool $append): bool {
 *     // Validate data structure
 *     if (empty($data) || !is_array($data)) {
 *         return false;
 *     }
 *
 *     $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<data>\n";
 *
 *     foreach ($data as $key => $value) {
 *         $key = is_numeric($key) ? "item_{$key}" : $key;
 *         $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key); // Clean key for XML
 *
 *         if (is_array($value)) {
 *             $xml .= "  <{$key}>" . htmlspecialchars(json_encode($value)) . "</{$key}>\n";
 *         } else {
 *             $xml .= "  <{$key}>" . htmlspecialchars((string)$value) . "</{$key}>\n";
 *         }
 *     }
 *
 *     $xml .= "</data>\n";
 *
 *     // Handle append mode by reading existing content
 *     if ($append && file_exists($filename)) {
 *         $existing = file_get_contents($filename);
 *         if ($existing) {
 *             // Simple append - real implementation might parse XML properly
 *             $xml = $existing . $xml;
 *         }
 *     }
 *
 *     return file_put_contents($filename, $xml) !== false;
 * });
 * ```
 *
 * @example Dynamic Handler Registration
 * ```php
 * // Conditionally register handlers based on available extensions
 * if (extension_loaded('yaml')) {
 *     DataSaverTypeRegistry::register('yaml', function(string $filename, array $data, bool $append): bool {
 *         if ($append && file_exists($filename)) {
 *             $existing = yaml_parse_file($filename) ?: [];
 *             $data = array_merge($existing, $data);
 *         }
 *
 *         $yaml = yaml_emit($data);
 *         return file_put_contents($filename, $yaml) !== false;
 *     });
 * }
 *
 * // Check availability before use
 * if (DataSaverTypeRegistry::isRegistered('yaml')) {
 *     DataSaver::type('yaml')->suffix('.yml')->save($complexData);
 * } else {
 *     // Fallback to JSON
 *     DataSaver::type('json')->suffix('.json')->save($complexData);
 * }
 * ```
 *
 * @see DataSaver For the primary interface that uses registered handlers
 * @see DataSaverConfig For the configuration system that validates types
 */
class DataSaverTypeRegistry
{
    /**
     * Storage array for all registered format type handlers
     *
     * This static property serves as the central repository for all format handlers in the
     * DataSaver system. It stores callable functions indexed by their type identifiers,
     * providing the core registry functionality for format type management.
     *
     * ## Array Structure
     * The array structure is:
     * ```php
     * [
     *     'json' => callable(string $filename, array $data, bool $append): bool,
     *     'csv'  => callable(string $filename, array $data, bool $append): bool,
     *     'txt'  => callable(string $filename, array $data, bool $append): bool,
     *     // ... additional registered handlers
     * ]
     * ```
     *
     * ## Key Management
     * - **Normalization**: All type keys are stored in lowercase for consistent lookups
     * - **Uniqueness**: Each type identifier can have only one registered handler
     * - **Override Support**: Later registrations override existing handlers with same key
     * - **Case Insensitive**: Lookups work regardless of registration or query case
     *
     * ## Handler Function Interface
     * All stored callables must conform to the standardized handler signature:
     * - **Parameter 1**: `string $filename` - Target file path for output
     * - **Parameter 2**: `array $data` - Array data to serialize and write
     * - **Parameter 3**: `bool $append` - Whether to append to existing file or overwrite
     * - **Return**: `bool` - Success (true) or failure (false) status
     *
     * ## Built-in Handler Initialization
     * The array is initially empty and populated via lazy initialization:
     * 1. First registry access triggers init() method
     * 2. Built-in handlers (json, csv, txt) are registered if not already present
     * 3. Custom handlers can be registered at any time via register() method
     * 4. Handlers remain available for the lifetime of the PHP process
     *
     * ## Thread Safety Considerations
     * While PHP is single-threaded per request, the static nature of this property means:
     * - Handler registrations persist across multiple DataSaver operations
     * - Registration order can matter if handlers depend on each other
     * - Built-in handlers are consistently available regardless of registration order
     * - Custom handlers should be registered during application bootstrap for predictability
     *
     * ## Memory and Performance
     * - **Lazy Loading**: Built-in handlers only consume memory when first accessed
     * - **Persistent Storage**: Handlers remain in memory once loaded (no repeated initialization)
     * - **Direct Access**: Handler retrieval is O(1) via array key lookup
     * - **Minimal Overhead**: Only stores function references, not data or complex objects
     *
     * @var array<string, callable> Registry of format handlers indexed by lowercase type identifiers.
     *                              Each callable must match signature: (string, array, bool): bool
     *
     * @see DataSaverTypeRegistry::init() Method that populates built-in handlers
     * @see DataSaverTypeRegistry::register() Method that adds custom handlers to this array
     * @see DataSaverTypeRegistry::get() Method that retrieves handlers from this array
     * @since 1.0.0
     */
    protected static array $types = [];

    /**
     * Initialize built-in format handlers with lazy loading strategy
     *
     * This method ensures the three core format types (json, csv, txt) are always available
     * by registering their handlers on first access to the registry. It uses a lazy loading
     * approach to avoid handler creation overhead until the registry is actually used.
     *
     * ## Lazy Initialization Strategy
     * The method only registers handlers that don't already exist, allowing:
     * - **Override Support**: Custom handlers registered before init() will not be overwritten
     * - **Performance Optimization**: Handler creation only occurs when registry is first accessed
     * - **Consistent Availability**: Built-in types are guaranteed available after any registry operation
     * - **Flexible Registration Order**: Custom and built-in handlers can be registered in any sequence
     *
     * ## Built-in Handler Implementations
     *
     * ### JSON Handler Registration
     * Creates a handler that:
     * - Outputs pretty-printed JSON with unescaped slashes for readability
     * - Supports append mode by merging with existing JSON file content
     * - Uses recursive merge to preserve nested data structures during append
     * - Handles non-JSON existing files gracefully by treating them as empty arrays
     * - Returns boolean success/failure status for error handling
     *
     * ### CSV Handler Registration
     * Creates a handler that:
     * - Uses PHP's built-in fputcsv() for standards-compliant CSV output
     * - Supports append mode by opening files in append mode ('a')
     * - Handles both array-of-arrays (multiple rows) and scalar arrays (single row)
     * - Automatically wraps scalar values in arrays for consistent CSV row structure
     * - Properly manages file handle opening and closing with error checking
     *
     * ### TXT Handler Registration
     * Creates a handler that:
     * - Converts array elements to lines with one element per line
     * - Joins nested arrays with commas for readable plain text representation
     * - Supports append mode using FILE_APPEND flag
     * - Adds trailing newline for proper text file formatting
     * - Handles mixed data types by converting everything to strings
     *
     * ## Handler Function Signature
     * All registered handlers conform to the standard interface:
     * ```php
     * function(string $filename, array $data, bool $append): bool
     * ```
     *
     * ## Append Mode Implementations
     * Each built-in handler implements append mode differently:
     * - **JSON**: Reads existing file, decodes JSON, recursively merges with new data
     * - **CSV**: Opens file in append mode, adds new rows without headers
     * - **TXT**: Uses FILE_APPEND flag to add new lines to existing content
     *
     * ## Error Handling Strategy
     * Built-in handlers implement consistent error handling:
     * - Return false for any file operation failures
     * - Handle missing or corrupted existing files gracefully
     * - Validate data structures appropriate for each format
     * - Use appropriate PHP flags and functions for reliable file operations
     *
     * ## Call Frequency and Performance
     * This method is called automatically by all public registry methods, but uses
     * conditional registration to avoid redundant handler creation. Multiple calls
     * to init() have minimal performance impact due to the existence checks.
     *
     * @return void
     *
     * @example Handler Creation Process
     * ```php
     * // This method creates handlers similar to:
     *
     * // JSON handler with merge logic
     * $jsonHandler = function(string $filename, array $data, bool $append): bool {
     *     if ($append && file_exists($filename)) {
     *         $existing = json_decode(file_get_contents($filename), true) ?: [];
     *         $data = DataSaverTypeRegistry::array_merge_recursive_distinct($existing, $data);
     *     }
     *     $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
     *     return file_put_contents($filename, $json) !== false;
     * };
     *
     * // CSV handler with row processing
     * $csvHandler = function(string $filename, array $data, bool $append): bool {
     *     $mode = $append ? 'a' : 'w';
     *     $fp = fopen($filename, $mode);
     *     if (!$fp) return false;
     *
     *     foreach ($data as $row) {
     *         fputcsv($fp, is_array($row) ? $row : [$row]);
     *     }
     *     return fclose($fp);
     * };
     * ```
     *
     * @see DataSaverTypeRegistry::$types Where the handlers are stored
     * @see DataSaverTypeRegistry::array_merge_recursive_distinct() Helper used by JSON handler
     * @since 1.0.0
     */
    protected static function init(): void
    {
        // JSON
        if (!isset(self::$types['json'])) {
            self::$types['json'] = function (string $filename, array $data, bool $append = false): bool {
                if ($append && file_exists($filename)) {
                    $existing = json_decode(file_get_contents($filename), true);
                    if (!is_array($existing)) $existing = [];
                    $data = self::array_merge_recursive_distinct($existing, $data);
                }
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return file_put_contents($filename, $json) !== false;
            };
        }

        // CSV
        if (!isset(self::$types['csv'])) {
            self::$types['csv'] = function (string $filename, array $data, bool $append = false): bool {
                $mode = $append ? 'a' : 'w';
                $fp = fopen($filename, $mode);
                if (!$fp) return false;
                foreach ($data as $row) {
                    fputcsv($fp, is_array($row) ? $row : [$row]);
                }
                return fclose($fp);
            };
        }

        // TXT
        if (!isset(self::$types['txt'])) {
            self::$types['txt'] = function (string $filename, array $data, bool $append = false): bool {
                $lines = array_map(fn($x) => is_array($x) ? implode(', ', $x) : $x, $data);
                $flags = $append ? FILE_APPEND : 0;
                return file_put_contents($filename, implode("\n", $lines) . "\n", $flags) !== false;
            };
        }
    }

    /**
     * Recursively merge arrays with override behavior, not duplication like array_merge_recursive
     *
     * This helper method provides clean array merging for JSON append operations by recursively
     * combining nested array structures while avoiding the key duplication issues of PHP's
     * built-in array_merge_recursive() function. It ensures that later values override earlier
     * ones at all nesting levels.
     *
     * ## Merge Behavior Comparison
     * Unlike PHP's array_merge_recursive() which creates arrays of duplicated values:
     * ```php
     * // PHP's array_merge_recursive() creates:
     * ['key' => ['value1', 'value2']]  // Duplication
     *
     * // This method creates:
     * ['key' => 'value2']  // Clean override
     * ```
     *
     * ## Recursive Processing Logic
     * The method processes arrays recursively:
     * 1. **Scalar Override**: Non-array values in $array2 replace corresponding values in $array1
     * 2. **Array Merge**: When both values are arrays, recursively merge their contents
     * 3. **Type Conversion**: Array values override scalar values, scalar values override array values
     * 4. **Deep Nesting**: Process continues to arbitrary nesting depths
     *
     * ## JSON Append Use Case
     * This method specifically supports the JSON handler's append mode:
     * ```php
     * // Existing file: {"user": {"name": "John", "age": 30}}
     * // New data:      {"user": {"age": 31, "city": "NYC"}}
     * // Result:        {"user": {"name": "John", "age": 31, "city": "NYC"}}
     * ```
     *
     * The merge preserves existing data while allowing new data to update or extend the structure.
     *
     * ## Algorithm Implementation
     * The method iterates through $array2 and for each key:
     * - If both values are arrays: Recursively merge them
     * - If either value is not an array: Override with $array2 value
     * - Preserves all keys from $array1 not present in $array2
     *
     * @param array $array1 The base array (typically existing file data).
     *                     Values in this array are preserved unless overridden.
     * @param array $array2 The override array (typically new data to merge).
     *                     Values in this array take precedence over $array1.
     * @return array The merged result with $array2 values taking precedence over $array1.
     *              Maintains the structure of both input arrays with recursive merging.
     *
     * @example Basic Override Behavior
     * ```php
     * $existing = ['name' => 'John', 'age' => 30, 'settings' => ['theme' => 'dark']];
     * $new = ['age' => 31, 'settings' => ['theme' => 'light', 'lang' => 'en']];
     *
     * $result = DataSaverTypeRegistry::array_merge_recursive_distinct($existing, $new);
     * // Result: [
     * //     'name' => 'John',           // Preserved from $existing
     * //     'age' => 31,                // Overridden by $new
     * //     'settings' => [             // Recursively merged
     * //         'theme' => 'light',     // Overridden by $new
     * //         'lang' => 'en'          // Added from $new
     * //     ]
     * // ]
     * ```
     *
     * @example Deep Nesting Support
     * ```php
     * $base = ['config' => ['db' => ['host' => 'localhost', 'port' => 3306]]];
     * $updates = ['config' => ['db' => ['port' => 5432], 'cache' => ['driver' => 'redis']]];
     *
     * $merged = DataSaverTypeRegistry::array_merge_recursive_distinct($base, $updates);
     * // Result: [
     * //     'config' => [
     * //         'db' => [
     * //             'host' => 'localhost',  // Preserved
     * //             'port' => 5432          // Updated
     * //         ],
     * //         'cache' => [
     * //             'driver' => 'redis'     // Added
     * //         ]
     * //     ]
     * // ]
     * ```
     *
     * @see DataSaverTypeRegistry::init() Where this method is used by the JSON handler
     * @since 1.0.0
     */
    private static function array_merge_recursive_distinct(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::array_merge_recursive_distinct($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    /**
     * Register or override a format type handler in the registry
     *
     * This method adds a new format handler to the registry or replaces an existing handler
     * for the specified type. It provides the primary mechanism for extending the DataSaver
     * system with custom output formats beyond the built-in json, csv, and txt types.
     *
     * ## Registration Process
     * When called, this method:
     * 1. **Ensures Initialization**: Calls init() to ensure built-in handlers are available
     * 2. **Normalizes Type Key**: Converts the type to lowercase for consistent storage and lookup
     * 3. **Stores Handler**: Places the callable in the $types registry array
     * 4. **Enables Immediate Use**: Makes the handler available for DataSaver operations immediately
     *
     * ## Handler Function Requirements
     * The provided callable must conform to the standardized signature:
     * ```php
     * function(string $filename, array $data, bool $append): bool
     * ```
     *
     * ### Parameter Details
     * - **$filename**: Complete file path where output should be written
     * - **$data**: Array data structure to serialize according to your format
     * - **$append**: Boolean indicating whether to append to existing file (true) or overwrite (false)
     * - **Return**: Boolean indicating success (true) or failure (false)
     *
     * ## Override Behavior
     * This method supports handler replacement:
     * - **New Types**: Creates new registry entries for previously unused type identifiers
     * - **Existing Types**: Replaces existing handlers, including built-in types
     * - **Built-in Override**: Can replace json, csv, txt handlers if registered after init()
     * - **No Warning**: Silent replacement - previous handlers are discarded
     *
     * ## Type Key Management
     * Type identifiers are processed for consistency:
     * - **Case Normalization**: 'JSON', 'Json', 'json' all become 'json'
     * - **Storage Key**: Stored using lowercase version of provided type
     * - **Lookup Compatibility**: Enables case-insensitive lookups in get() and isRegistered()
     * - **Original Case Preserved**: Return from list() uses the normalized lowercase form
     *
     * ## Integration with DataSaver System
     * Registered handlers integrate seamlessly:
     * 1. **Type Validation**: DataSaver calls isRegistered() to validate types before operations
     * 2. **Handler Retrieval**: DataSaver calls get() to retrieve handlers for execution
     * 3. **Format Execution**: DataSaver passes resolved filename, data array, and append mode
     * 4. **Result Processing**: DataSaver interprets handler return value for success/failure handling
     *
     * ## Custom Handler Development Guidelines
     * When implementing custom handlers:
     * - **File Safety**: Use proper file operation error handling
     * - **Append Logic**: Implement meaningful append behavior for your format
     * - **Data Validation**: Validate that input data structure matches format requirements
     * - **Error Handling**: Return false for any failure condition, true only for complete success
     * - **Performance**: Consider typical data sizes and optimize accordingly
     * - **Standards Compliance**: Follow established standards for your output format when possible
     *
     * @param string $type The format type identifier (case-insensitive).
     *                    Will be normalized to lowercase for storage and lookup.
     *                    Examples: 'xml', 'yaml', 'pipe', 'custom_format'
     * @param callable(string, array, bool): bool $callback The format handler function.
     *                                                     Must accept filename, data array, and append boolean.
     *                                                     Must return boolean success/failure status.
     * @return void
     *
     * @example Basic Custom Format Registration
     * ```php
     * // Register a simple pipe-delimited format
     * DataSaverTypeRegistry::register('pipe', function(string $filename, array $data, bool $append): bool {
     *     $lines = [];
     *     foreach ($data as $row) {
     *         if (is_array($row)) {
     *             $lines[] = implode('|', array_map('strval', $row));
     *         } else {
     *             $lines[] = (string)$row;
     *         }
     *     }
     *
     *     $content = implode("\n", $lines) . "\n";
     *     $flags = $append ? FILE_APPEND : 0;
     *     return file_put_contents($filename, $content, $flags) !== false;
     * });
     *
     * // Use immediately after registration
     * DataSaver::type('pipe')->save([['A', 'B'], ['1', '2']]); // Outputs: A|B\n1|2\n
     * ```
     *
     * @example Advanced Handler with Validation and Error Handling
     * ```php
     * // Register XML format with comprehensive error handling
     * DataSaverTypeRegistry::register('xml', function(string $filename, array $data, bool $append): bool {
     *     // Validate input data
     *     if (empty($data) || !is_array($data)) {
     *         error_log("XML handler: Invalid data structure");
     *         return false;
     *     }
     *
     *     try {
     *         $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<root>\n";
     *
     *         foreach ($data as $key => $value) {
     *             $key = is_numeric($key) ? "item_{$key}" : $key;
     *             $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
     *
     *             if (is_array($value)) {
     *                 $value = json_encode($value);
     *             }
     *
     *             $xml .= "  <{$key}>" . htmlspecialchars((string)$value, ENT_XML1) . "</{$key}>\n";
     *         }
     *
     *         $xml .= "</root>\n";
     *
     *         // Handle append mode
     *         if ($append && file_exists($filename)) {
     *             // Simple append - real implementation might parse existing XML
     *             $xml = file_get_contents($filename) . "\n" . $xml;
     *         }
     *
     *         $result = file_put_contents($filename, $xml);
     *         return $result !== false;
     *
     *     } catch (Exception $e) {
     *         error_log("XML handler failed: " . $e->getMessage());
     *         return false;
     *     }
     * });
     * ```
     *
     * @example Built-in Handler Override
     * ```php
     * // Override the built-in JSON handler with custom formatting
     * DataSaverTypeRegistry::register('json', function(string $filename, array $data, bool $append): bool {
     *     // Custom JSON formatting with different options
     *     $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
     *
     *     if ($json === false) {
     *         error_log("Custom JSON encoding failed: " . json_last_error_msg());
     *         return false;
     *     }
     *
     *     $flags = $append ? FILE_APPEND : 0;
     *     return file_put_contents($filename, $json . "\n", $flags) !== false;
     * });
     * ```
     *
     * @see DataSaverTypeRegistry::init() Method that ensures built-in handlers are available
     * @see DataSaverTypeRegistry::get() Method that retrieves registered handlers
     * @see DataSaverTypeRegistry::isRegistered() Method that validates type availability
     * @since 1.0.0
     */
    public static function register(string $type, callable $callback): void
    {
        self::init();
        self::$types[strtolower($type)] = $callback;
    }

    /**
     * Check whether a format type handler is registered and available
     *
     * This method provides type availability validation for the DataSaver system by checking
     * if a format handler exists for the specified type. It's used extensively by DataSaver
     * and DataSaverConfig to validate types before attempting save operations, enabling
     * graceful error handling for unregistered format types.
     *
     * ## Validation Process
     * When called, this method:
     * 1. **Ensures Initialization**: Calls init() to ensure built-in handlers are registered
     * 2. **Normalizes Type Key**: Converts the query type to lowercase for consistent lookup
     * 3. **Checks Registry**: Uses isset() to verify handler exists in $types array
     * 4. **Returns Availability**: Boolean indicating whether handler is available for use
     *
     * ## Built-in Type Guarantee
     * This method guarantees availability of built-in types:
     * - **Always Available**: json, csv, txt types return true after any registry initialization
     * - **Lazy Registration**: Built-in handlers are registered automatically if not present
     * - **Consistent Response**: Same result regardless of when called during application lifecycle
     * - **Override Aware**: Returns true for built-in types even if they've been overridden
     *
     * ## Case Insensitive Behavior
     * Type checking is case-insensitive due to normalization:
     * ```php
     * DataSaverTypeRegistry::isRegistered('JSON');  // true
     * DataSaverTypeRegistry::isRegistered('Json');  // true
     * DataSaverTypeRegistry::isRegistered('json');  // true
     * DataSaverTypeRegistry::isRegistered('CSV');   // true
     * DataSaverTypeRegistry::isRegistered('xml');   // false (unless registered)
     * ```
     *
     * ## Integration with DataSaver Operations
     * This method is called by DataSaver components to validate types:
     * - **DataSaverConfig::type()**: Throws InvalidArgumentException if type not registered
     * - **DataSaverConfig::save()**: Validates resolved type before handler retrieval
     * - **Error Prevention**: Prevents attempts to use non-existent format handlers
     * - **User Experience**: Enables meaningful error messages for typos or missing handlers
     *
     * ## Performance Characteristics
     * The method is optimized for frequent calls:
     * - **O(1) Lookup**: Direct array key existence check via isset()
     * - **Minimal Overhead**: Simple boolean return after normalization
     * - **Init Once**: Built-in handler registration occurs only on first registry access
     * - **No File I/O**: Pure in-memory operation for fast validation
     *
     * ## Custom Type Availability
     * For custom registered types:
     * - **Immediate Availability**: Returns true immediately after register() is called
     * - **Persistent Availability**: Remains true for lifetime of PHP process
     * - **Override Detection**: Returns true even if type has been overridden multiple times
     * - **Registration Order**: Available regardless of when registered relative to built-in types
     *
     * @param string $type The format type identifier to check (case-insensitive).
     *                    Common values: 'json', 'csv', 'txt', or any custom registered type.
     * @return bool True if a handler is registered for this type, false otherwise.
     *             Built-in types (json, csv, txt) always return true.
     *
     * @example Basic Type Validation
     * ```php
     * // Check built-in types (always available)
     * var_dump(DataSaverTypeRegistry::isRegistered('json')); // true
     * var_dump(DataSaverTypeRegistry::isRegistered('CSV'));  // true (case insensitive)
     * var_dump(DataSaverTypeRegistry::isRegistered('txt'));  // true
     *
     * // Check custom type availability
     * var_dump(DataSaverTypeRegistry::isRegistered('xml'));  // false (not registered)
     *
     * // Register custom type
     * DataSaverTypeRegistry::register('xml', $xmlHandler);
     * var_dump(DataSaverTypeRegistry::isRegistered('xml'));  // true (now available)
     * ```
     *
     * @example Conditional Format Usage
     * ```php
     * // Use preferred format with fallback
     * $preferredFormat = 'yaml';
     * $fallbackFormat = 'json';
     *
     * if (DataSaverTypeRegistry::isRegistered($preferredFormat)) {
     *     $file = DataSaver::type($preferredFormat)->suffix('.yml')->save($data);
     *     echo "Saved using preferred format: {$preferredFormat}";
     * } else {
     *     $file = DataSaver::type($fallbackFormat)->suffix('.json')->save($data);
     *     echo "Saved using fallback format: {$fallbackFormat}";
     * }
     * ```
     *
     * @example Pre-operation Validation
     * ```php
     * function saveWithValidation(array $data, string $format): string|false {
     *     // Validate format before attempting save
     *     if (!DataSaverTypeRegistry::isRegistered($format)) {
     *         throw new InvalidArgumentException("Unsupported format: {$format}");
     *     }
     *
     *     // Proceed with save knowing format is available
     *     return DataSaver::type($format)->save($data);
     * }
     *
     * try {
     *     $file = saveWithValidation($userData, 'json');     // Success
     *     $file = saveWithValidation($userData, 'unknown');  // Throws exception
     * } catch (InvalidArgumentException $e) {
     *     echo "Error: " . $e->getMessage();
     * }
     * ```
     *
     * @see DataSaverTypeRegistry::init() Method that ensures built-in type availability
     * @see DataSaverTypeRegistry::register() Method for adding custom types
     * @see DataSaverTypeRegistry::get() Method that retrieves registered handlers
     * @see DataSaverConfig::type() Method that uses this for type validation
     * @since 1.0.0
     */
    public static function isRegistered(string $type): bool
    {
        self::init();
        return isset(self::$types[strtolower($type)]);
    }

    /**
     * Retrieve the format handler for a registered type
     *
     * This method returns the callable format handler for the specified type, enabling
     * DataSaver operations to execute the appropriate serialization logic. It serves as
     * the primary mechanism for accessing registered format handlers and includes built-in
     * validation to ensure only valid types are retrieved.
     *
     * ## Retrieval Process
     * When called, this method:
     * 1. **Ensures Initialization**: Calls init() to guarantee built-in handlers are available
     * 2. **Normalizes Type Key**: Converts the type to lowercase for consistent lookup
     * 3. **Validates Existence**: Checks if handler exists, throwing exception if not found
     * 4. **Returns Handler**: Provides the callable function ready for immediate execution
     *
     * ## Handler Function Interface
     * All returned handlers conform to the standardized signature:
     * ```php
     * callable(string $filename, array $data, bool $append): bool
     * ```
     *
     * This consistency enables DataSaver to call any registered handler with the same
     * parameter set, regardless of whether it's a built-in or custom format.
     *
     * ## Error Handling Strategy
     * The method uses exception-based error handling:
     * - **Invalid Types**: Throws InvalidArgumentException with descriptive message
     * - **Immediate Failure**: Exception prevents execution with non-existent handlers
     * - **Clear Messaging**: Exception message includes the requested type for debugging
     * - **Safe Operations**: Ensures DataSaver never attempts to call undefined handlers
     *
     * ## Built-in Handler Guarantee
     * Built-in format handlers are guaranteed to be available:
     * - **Automatic Registration**: json, csv, txt handlers are registered via init() if needed
     * - **Consistent Availability**: Same handler returned regardless of application state
     * - **Override Awareness**: Returns current handler even if built-in has been overridden
     * - **Reliable Fallback**: Applications can always depend on built-in format availability
     *
     * ## Case Insensitive Lookup
     * Type queries are case-insensitive due to key normalization:
     * ```php
     * $handler1 = DataSaverTypeRegistry::get('JSON');  // Same handler
     * $handler2 = DataSaverTypeRegistry::get('json');  // Same handler
     * $handler3 = DataSaverTypeRegistry::get('Json');  // Same handler
     * ```
     *
     * ## Integration with DataSaver System
     * This method is called by DataSaver components to retrieve handlers:
     * - **DataSaverConfig::save()**: Retrieves handler for resolved format type
     * - **Handler Execution**: Passes filename, data array, and append boolean to retrieved handler
     * - **Result Processing**: Uses handler return value to determine save operation success
     * - **Error Propagation**: Handler exceptions or false returns indicate operation failure
     *
     * ## Performance Considerations
     * The method is optimized for frequent access:
     * - **O(1) Retrieval**: Direct array access after key normalization
     * - **No Cloning**: Returns reference to stored callable for minimal overhead
     * - **Minimal Validation**: Simple existence check with fast exception path
     * - **Init Caching**: Built-in handler creation occurs only once per process
     *
     * @param string $type The format type identifier for which to retrieve the handler (case-insensitive).
     *                    Must correspond to a registered type (built-in or custom).
     * @return callable The format handler function conforming to signature:
     *                 (string $filename, array $data, bool $append): bool
     *
     * @throws InvalidArgumentException If no handler is registered for the specified type.
     *                                 Exception message includes the requested type name for debugging.
     *
     * @example Basic Handler Retrieval and Execution
     * ```php
     * // Retrieve and use JSON handler directly
     * $jsonHandler = DataSaverTypeRegistry::get('json');
     * $success = $jsonHandler('/tmp/data.json', ['key' => 'value'], false);
     *
     * if ($success) {
     *     echo "JSON file saved successfully";
     * } else {
     *     echo "JSON save operation failed";
     * }
     * ```
     *
     * @example Handler Validation and Error Handling
     * ```php
     * function executeHandler(string $type, string $filename, array $data, bool $append = false): bool {
     *     try {
     *         $handler = DataSaverTypeRegistry::get($type);
     *         return $handler($filename, $data, $append);
     *     } catch (InvalidArgumentException $e) {
     *         error_log("Format handler error: " . $e->getMessage());
     *         return false;
     *     }
     * }
     *
     * // Use with error handling
     * $result = executeHandler('json', '/tmp/test.json', $data);     // Success
     * $result = executeHandler('unknown', '/tmp/test.txt', $data);   // Logged error, returns false
     * ```
     *
     * @example Advanced Handler Inspection
     * ```php
     * // Get handler and inspect its behavior
     * $csvHandler = DataSaverTypeRegistry::get('csv');
     *
     * // Test with different data structures
     * $tabularData = [['Name', 'Age'], ['John', 30], ['Jane', 25]];
     * $scalarData = ['Simple', 'List', 'Data'];
     *
     * $success1 = $csvHandler('/tmp/table.csv', $tabularData, false);   // Proper CSV table
     * $success2 = $csvHandler('/tmp/list.csv', $scalarData, false);     // Single-column CSV
     *
     * echo "Tabular data: " . ($success1 ? 'success' : 'failed') . "\n";
     * echo "Scalar data: " . ($success2 ? 'success' : 'failed') . "\n";
     * ```
     *
     * @example Custom Handler Retrieval
     * ```php
     * // Register custom handler first
     * DataSaverTypeRegistry::register('pipe', function(string $filename, array $data, bool $append): bool {
     *     $lines = array_map(fn($row) => is_array($row) ? implode('|', $row) : $row, $data);
     *     $content = implode("\n", $lines) . "\n";
     *     $flags = $append ? FILE_APPEND : 0;
     *     return file_put_contents($filename, $content, $flags) !== false;
     * });
     *
     * // Retrieve and use custom handler
     * $pipeHandler = DataSaverTypeRegistry::get('pipe');
     * $result = $pipeHandler('/tmp/data.pipe', [['A', 'B'], ['1', '2']], false);
     * // File contains: A|B\n1|2\n
     * ```
     *
     * @see DataSaverTypeRegistry::init() Method that ensures built-in handlers are available
     * @see DataSaverTypeRegistry::isRegistered() Method for validating type availability before retrieval
     * @see DataSaverTypeRegistry::register() Method for registering custom handlers
     * @see DataSaverConfig::save() Method that uses retrieved handlers for file operations
     * @since 1.0.0
     */
    public static function get(string $type): callable
    {
        self::init();
        $key = strtolower($type);
        if (!isset(self::$types[$key])) {
            throw new InvalidArgumentException("No handler registered for type: {$type}");
        }
        return self::$types[$key];
    }

    /**
     * Return array of all registered format type identifiers
     *
     * This method provides a comprehensive list of all format types currently available
     * in the registry, including both built-in types (json, csv, txt) and any custom
     * types that have been registered. It's useful for debugging, administration
     * interfaces, and dynamic format selection scenarios.
     *
     * ## List Generation Process
     * When called, this method:
     * 1. **Ensures Initialization**: Calls init() to guarantee built-in handlers are registered
     * 2. **Extracts Keys**: Uses array_keys() to get all type identifiers from $types registry
     * 3. **Returns Normalized Keys**: Provides lowercase type identifiers as stored internally
     * 4. **Includes All Types**: Both built-in and custom registered types are included
     *
     * ## Built-in Type Inclusion
     * The returned array always includes built-in types:
     * - **Guaranteed Presence**: json, csv, txt are always included after init()
     * - **Consistent Ordering**: Built-in types appear in registration order (json, csv, txt)
     * - **Override Awareness**: Built-in types listed even if overridden by custom handlers
     * - **Standard Foundation**: Provides baseline format capabilities for all applications
     *
     * ## Custom Type Integration
     * Custom registered types are seamlessly integrated:
     * - **Registration Order**: Custom types appear in the order they were registered
     * - **Mixed Listing**: Built-in and custom types are listed together
     * - **Dynamic Updates**: List reflects current registry state including recent registrations
     * - **No Distinction**: No indication of which types are built-in vs custom
     *
     * ## Key Format and Normalization
     * All returned keys use the normalized storage format:
     * - **Lowercase Format**: All type identifiers are returned in lowercase
     * - **Storage Consistency**: Keys match internal $types array keys exactly
     * - **Case Normalization**: Original registration case is not preserved in the list
     * - **Consistent Lookup**: Returned keys work directly with get() and isRegistered()
     *
     * ## Use Cases and Applications
     * This method supports various application scenarios:
     * - **Dynamic UI**: Populate format selection dropdowns in admin interfaces
     * - **Capability Detection**: Determine available formats for feature activation
     * - **Documentation**: Generate lists of supported formats for help systems
     * - **Validation**: Check comprehensive format availability for configuration validation
     * - **Debugging**: Inspect registry state during development and troubleshooting
     *
     * ## Performance Characteristics
     * The method provides efficient registry inspection:
     * - **O(n) Complexity**: Linear in number of registered types (typically small)
     * - **Minimal Overhead**: Simple array_keys() operation after init()
     * - **No Side Effects**: Read-only operation that doesn't modify registry state
     * - **Cacheable**: Results can be cached if registry is static after bootstrap
     *
     * @return string[] Array of all registered type identifiers in lowercase format.
     *                 Always includes built-in types: ['json', 'csv', 'txt', ...custom types...]
     *                 Order reflects registration sequence with built-ins first.
     *
     * @example Basic Registry Inspection
     * ```php
     * // Get all available format types
     * $availableTypes = DataSaverTypeRegistry::list();
     * print_r($availableTypes);
     * // Output: ['json', 'csv', 'txt'] (plus any custom types)
     *
     * echo "Available formats: " . implode(', ', $availableTypes);
     * // Output: Available formats: json, csv, txt
     * ```
     *
     * @example Dynamic Format Selection UI
     * ```php
     * function buildFormatSelector(): string {
     *     $availableFormats = DataSaverTypeRegistry::list();
     *     $options = '';
     *
     *     foreach ($availableFormats as $format) {
     *         $label = strtoupper($format);
     *         $selected = ($format === 'json') ? ' selected' : '';
     *         $options .= "<option value=\"{$format}\"{$selected}>{$label}</option>\n";
     *     }
     *
     *     return "<select name=\"format\">\n{$options}</select>";
     * }
     *
     * // Generates HTML select with all available formats
     * echo buildFormatSelector();
     * ```
     *
     * @example Registry State Validation
     * ```php
     * function validateFormatCapabilities(array $requiredFormats): array {
     *     $availableFormats = DataSaverTypeRegistry::list();
     *     $missing = [];
     *
     *     foreach ($requiredFormats as $required) {
     *         if (!in_array(strtolower($required), $availableFormats)) {
     *             $missing[] = $required;
     *         }
     *     }
     *
     *     return $missing;
     * }
     *
     * // Check if application requirements are met
     * $required = ['json', 'csv', 'xml', 'yaml'];
     * $missing = validateFormatCapabilities($required);
     *
     * if (!empty($missing)) {
     *     echo "Missing format handlers: " . implode(', ', $missing);
     *     // Register missing handlers or show error
     * }
     * ```
     *
     * @example Custom Type Registration Verification
     * ```php
     * // Before registration
     * $beforeList = DataSaverTypeRegistry::list();
     * echo "Before: " . implode(', ', $beforeList) . "\n";
     * // Output: Before: json, csv, txt
     *
     * // Register custom types
     * DataSaverTypeRegistry::register('xml', $xmlHandler);
     * DataSaverTypeRegistry::register('yaml', $yamlHandler);
     *
     * // After registration
     * $afterList = DataSaverTypeRegistry::list();
     * echo "After: " . implode(', ', $afterList) . "\n";
     * // Output: After: json, csv, txt, xml, yaml
     *
     * // Verify new types are available
     * $newTypes = array_diff($afterList, $beforeList);
     * echo "New types registered: " . implode(', ', $newTypes) . "\n";
     * // Output: New types registered: xml, yaml
     * ```
     *
     * @example Administration Interface Support
     * ```php
     * function generateFormatReport(): array {
     *     $formats = DataSaverTypeRegistry::list();
     *     $report = [];
     *
     *     foreach ($formats as $format) {
     *         $isBuiltIn = in_array($format, ['json', 'csv', 'txt']);
     *         $handler = DataSaverTypeRegistry::get($format);
     *
     *         $report[] = [
     *             'type' => $format,
     *             'built_in' => $isBuiltIn,
     *             'available' => true,
     *             'handler_type' => get_debug_type($handler)
     *         ];
     *     }
     *
     *     return $report;
     * }
     *
     * // Generate comprehensive format capability report
     * $report = generateFormatReport();
     * foreach ($report as $info) {
     *     $status = $info['built_in'] ? 'Built-in' : 'Custom';
     *     echo "{$info['type']}: {$status} ({$info['handler_type']})\n";
     * }
     * ```
     *
     * @see DataSaverTypeRegistry::init() Method that ensures built-in types are included
     * @see DataSaverTypeRegistry::register() Method for adding custom types to the list
     * @see DataSaverTypeRegistry::isRegistered() Method for checking individual type availability
     * @since 1.0.0
     */
    public static function list(): array
    {
        self::init();
        return array_keys(self::$types);
    }
}
