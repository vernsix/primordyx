<?php
/**
 * File: /vendor/vernsix/primordyx/src/Utils.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use InvalidArgumentException;
use Primordyx\Http\HttpStatus;
use RuntimeException;

/**
 * Multi-purpose utility class for HTTP responses, data formatting, and file operations
 *
 * Comprehensive collection of static utility methods for common web application tasks
 * including JSON API responses, CSV data export, configuration parsing, file sequence
 * management, and directory operations. Designed to reduce code duplication and provide
 * consistent interfaces for frequent programming tasks.
 *
 * ## Response Utilities
 * - **JSON API Responses**: Standardized JSON output with HTTP status codes and headers
 * - **Content-Type Management**: Automatic header setting for proper MIME types
 * - **HTTP Status Integration**: Works with HttpStatus class for proper status codes
 * - **Script Termination**: Controlled exit after response delivery
 *
 * ## Data Format Utilities
 * - **CSV Generation**: Convert arrays to CSV format with customizable delimiters
 * - **Configuration Parsing**: Parse pipe-delimited INI-style configuration strings
 * - **Type Conversion**: Automatic type casting for numeric values
 * - **Header Management**: Automatic CSV header generation from array keys
 *
 * ## File System Utilities
 * - **Sequence Management**: Generate next available numbers in file naming patterns
 * - **Directory Creation**: Ensure directory structure exists for file operations
 * - **Path Validation**: Safe directory and file path handling
 * - **Permission Management**: Configurable directory permissions
 *
 * ## Design Philosophy
 * - **Static Interface**: All methods static for convenience and consistency
 * - **Error Handling**: Graceful error handling with exceptions for critical failures
 * - **No Side Effects**: Pure functions where possible, clear side effects where necessary
 * - **Framework Integration**: Works seamlessly with other Primordyx components
 *
 * ## Common Usage Patterns
 * Utils serves as the "Swiss Army knife" for common operations that don't warrant
 * separate classes but occur frequently across applications. Methods are designed
 * to be called directly without instantiation and handle edge cases gracefully.
 *
 * @since 1.0.0
 *
 * @example API Response Patterns
 * ```php
 * // Standard success response
 * Utils::sendJson(['status' => 'success', 'data' => $results]);
 *
 * // Error response with custom status code
 * Utils::sendJson(['error' => 'Not found'], 404);
 *
 * // Response with custom headers
 * Utils::sendJson($data, 200, ['X-Custom-Header' => 'value']);
 * ```
 *
 * @example Data Export and Configuration
 * ```php
 * // Export array data to CSV
 * $csvString = Utils::toCsv($userRecords);
 * file_put_contents('users.csv', $csvString);
 *
 * // Parse configuration string
 * $config = Utils::parseToAssocArray('timeout=30|retries=3|debug=1');
 * // Result: ['timeout' => 30, 'retries' => 3, 'debug' => 1]
 * ```
 *
 * @example File Management
 * ```php
 * // Get next file number in sequence
 * $nextNum = Utils::getNextFileSequenceNumber('/uploads/file_###.jpg');
 * // If file_001.jpg and file_003.jpg exist, returns 4
 *
 * // Ensure directory exists before file write
 * Utils::ensureDirectoryForFile('/var/log/app/debug.log');
 * file_put_contents('/var/log/app/debug.log', $logData);
 * ```
 *
 * @see HttpStatus For HTTP status code management
 */
class Utils
{
    /**
     * Convenient alias for sendJsonResponse() with identical functionality
     *
     * Provides shorter method name for frequent JSON response operations while
     * maintaining exact same parameters, behavior, and script termination as
     * sendJsonResponse(). Choose based on coding style preference or team conventions.
     *
     * ## Method Equivalence
     * This method is functionally identical to sendJsonResponse() and simply
     * delegates all parameters without modification. Both methods set identical
     * headers, handle data encoding the same way, and terminate script execution.
     *
     * @param mixed $data Data to encode as JSON (arrays, objects, primitives)
     * @param int $code HTTP status code to send (default: 200 OK)
     * @param array<string, string> $headers Optional additional HTTP headers to send
     * @return never Method terminates script execution and never returns
     * @since 1.0.0
     *
     * @example Convenient JSON Responses
     * ```php
     * // Quick success response
     * Utils::sendJson(['success' => true]);
     *
     * // Error response with custom status
     * Utils::sendJson(['error' => 'Invalid input'], 400);
     *
     * // Complex response with headers
     * Utils::sendJson($apiData, 201, ['X-API-Version' => '1.0']);
     * ```
     *
     * @see sendJsonResponse() For complete method documentation
     */
    public static function sendJson(mixed $data, int $code = 200, array $headers = []): never
    {
        self::sendJsonResponse($data, $code, $headers);
    }

    /**
     * Send JSON response with HTTP status code and terminate script execution
     *
     * Comprehensive JSON response handler that sets appropriate HTTP headers, encodes
     * data as JSON with pretty-printing, and terminates script execution. Essential for
     * API endpoints and AJAX handlers requiring standardized JSON responses.
     *
     * ## Response Process
     * 1. **Header Safety**: Check if headers already sent to avoid errors
     * 2. **Status Code**: Set HTTP status using HttpStatus class integration
     * 3. **Content-Type**: Set to application/json for proper MIME handling
     * 4. **Custom Headers**: Add any additional headers provided
     * 5. **JSON Encoding**: Encode data with JSON_PRETTY_PRINT for readability
     * 6. **Script Termination**: Exit immediately after response sent
     *
     * ## Header Management
     * - Automatically sets Content-Type: application/json
     * - Uses HttpStatus::sendHeader() for proper status code handling
     * - Supports custom headers via associative array parameter
     * - Gracefully handles cases where headers already sent
     *
     * ## JSON Encoding
     * - Uses JSON_PRETTY_PRINT for human-readable output
     * - Handles PHP data types automatically (arrays, objects, primitives)
     * - Does not perform JSON encoding error handling (assumes valid data)
     *
     * ## Script Termination
     * Method never returns - always terminates script with exit after response.
     * This prevents additional output that could corrupt JSON response format.
     *
     * @param mixed $data Data to encode as JSON (any JSON-serializable type)
     * @param int $code HTTP status code (default: 200, common: 201, 400, 404, 500)
     * @param array<string, string> $headers Additional headers as key-value pairs
     * @return never Method always terminates script execution
     * @since 1.0.0
     *
     * @example API Success Responses
     * ```php
     * // Simple success with data
     * Utils::sendJsonResponse(['users' => $userList, 'total' => $count]);
     *
     * // Created response (HTTP 201)
     * Utils::sendJsonResponse(['id' => $newUserId], 201);
     *
     * // Success with metadata headers
     * Utils::sendJsonResponse($results, 200, [
     *     'X-Total-Count' => $totalCount,
     *     'X-Page-Number' => $pageNumber
     * ]);
     * ```
     *
     * @example API Error Responses
     * ```php
     * // Bad Request (HTTP 400)
     * Utils::sendJsonResponse([
     *     'error' => 'validation_failed',
     *     'message' => 'Email address is required',
     *     'fields' => ['email']
     * ], 400);
     *
     * // Not Found (HTTP 404)
     * Utils::sendJsonResponse(['error' => 'User not found'], 404);
     *
     * // Internal Server Error (HTTP 500)
     * Utils::sendJsonResponse(['error' => 'Database connection failed'], 500);
     * ```
     *
     * @example AJAX Handler Integration
     * ```php
     * // In controller or route handler
     * public function handleAjaxRequest() {
     *     try {
     *         $result = $this->processRequest($_POST);
     *         Utils::sendJsonResponse(['success' => true, 'data' => $result]);
     *     } catch (ValidationException $e) {
     *         Utils::sendJsonResponse(['error' => $e->getMessage()], 400);
     *     } catch (Exception $e) {
     *         Utils::sendJsonResponse(['error' => 'Internal error'], 500);
     *     }
     * }
     * ```
     *
     * @see HttpStatus::sendHeader() For HTTP status code handling
     * @see sendJson() For convenient alias method
     */
    public static function sendJsonResponse(mixed $data, int $code = 200, array $headers = []): never {
        if (!headers_sent()) {
            HttpStatus::sendHeader($code);
            header('Content-Type: application/json');
            foreach ($headers as $key => $value) {
                header("$key: $value");
            }
        }
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Convert two-dimensional array to CSV-formatted string with automatic headers
     *
     * Transforms array of associative arrays into properly formatted CSV string with
     * configurable delimiters and enclosures. Automatically generates header row from
     * first record's keys and handles CSV escaping through PHP's built-in fputcsv().
     *
     * ## CSV Format Features
     * - **Automatic Headers**: Uses first row keys as column headers
     * - **Proper Escaping**: Handles commas, quotes, newlines in data via fputcsv()
     * - **Configurable Delimiters**: Custom field separators (comma, tab, pipe, etc.)
     * - **Configurable Enclosures**: Custom quote characters for field wrapping
     * - **Empty Array Handling**: Returns empty string for empty input arrays
     *
     * ## Data Structure Requirements
     * - Input must be array of associative arrays (rows)
     * - All rows should have consistent key structure for proper columns
     * - Keys become column headers, values become cell data
     * - Mixed data types automatically converted to strings
     *
     * ## Memory Efficiency
     * Uses php://temp stream for memory-efficient processing of large datasets
     * without loading entire CSV string into memory during generation.
     *
     * @param array<array<string, mixed>> $rows Array of associative arrays representing CSV rows
     * @param string $delimiter Field separator character (default: comma)
     * @param string $enclosure Quote character for fields (default: double-quote)
     * @return string Complete CSV-formatted string including headers
     * @throws RuntimeException If unable to create temporary stream for CSV generation
     * @since 1.0.0
     *
     * @example User Data Export
     * ```php
     * $users = [
     *     ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
     *     ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
     *     ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com']
     * ];
     *
     * $csv = Utils::toCsv($users);
     * // Returns:
     * // id,name,email
     * // 1,"John Doe","john@example.com"
     * // 2,"Jane Smith","jane@example.com"
     * // 3,"Bob Wilson","bob@example.com"
     *
     * // Save to file
     * file_put_contents('users.csv', $csv);
     * ```
     *
     * @example Custom Delimiters
     * ```php
     * $data = [
     *     ['product' => 'Widget A', 'price' => 19.99, 'stock' => 150],
     *     ['product' => 'Widget B', 'price' => 29.99, 'stock' => 75]
     * ];
     *
     * // Tab-separated values
     * $tsv = Utils::toCsv($data, "\t");
     *
     * // Pipe-separated values
     * $psv = Utils::toCsv($data, '|');
     *
     * // Custom enclosure
     * $custom = Utils::toCsv($data, ',', "'");
     * ```
     *
     * @example Database Export
     * ```php
     * // Export database results
     * $orders = Database::query('SELECT * FROM orders WHERE status = ?', ['completed']);
     * $csvData = Utils::toCsv($orders);
     *
     * // Send as download
     * header('Content-Type: text/csv');
     * header('Content-Disposition: attachment; filename="orders.csv"');
     * echo $csvData;
     * ```
     *
     * @example Complex Data Handling
     * ```php
     * $reports = [
     *     [
     *         'date' => '2025-01-15',
     *         'description' => 'Sales report with "quotes" and, commas',
     *         'revenue' => 15420.50,
     *         'notes' => "Multi-line\nnotes here"
     *     ],
     *     [
     *         'date' => '2025-01-16',
     *         'description' => 'Another report',
     *         'revenue' => 18750.00,
     *         'notes' => 'Simple notes'
     *     ]
     * ];
     *
     * $csv = Utils::toCsv($reports);
     * // Properly escapes quotes, commas, and newlines
     * ```
     */
    public static function toCsv(array $rows, string $delimiter = ',', string $enclosure = '"'): string
    {
        if (empty($rows)) return '';

        $out = fopen('php://temp', 'r+');

        // Get column headers from the first row
        fputcsv($out, array_keys(reset($rows)), $delimiter, $enclosure);

        foreach ($rows as $row) {
            fputcsv($out, $row, $delimiter, $enclosure);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Parse pipe-delimited INI-style string into associative array with type conversion
     *
     * Converts configuration strings in "key=value|key=value" format into PHP
     * associative arrays with automatic type conversion for numeric values. Useful
     * for parsing configuration data, database connection options, or serialized settings.
     *
     * ## Parsing Format
     * - **Delimiter**: Pipe character (|) separates key-value pairs by default
     * - **Assignment**: Equals sign (=) separates keys from values
     * - **Key Conversion**: Keys cast to integers for numeric keys
     * - **Value Conversion**: Numeric values automatically cast to integers
     * - **Trimming**: Automatic whitespace trimming on keys and values
     *
     * ## Type Conversion Rules
     * - Numeric values converted to integers using is_numeric() and (int) casting
     * - Non-numeric values remain as strings
     * - Empty values remain as empty strings
     *
     * ## Error Handling
     * - Empty/null input returns empty array
     * - Malformed pairs (missing =) are silently skipped
     * - Invalid separators produce empty arrays
     * - Method never throws exceptions for parsing errors
     *
     * @param string|null $raw Configuration string to parse (null returns empty array)
     * @param string $separator Character used to separate key-value pairs (default: '|')
     * @return array<int, mixed> Associative array with integer keys and mixed values
     * @since 1.0.0
     *
     * @example Database Configuration Parsing
     * ```php
     * $dbConfig = "1002=SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci|3=2|1000=30";
     * $options = Utils::parseToAssocArray($dbConfig);
     * // Result: [
     * //   1002 => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
     * //   3 => 2,
     * //   1000 => 30
     * // ]
     *
     * // Use in PDO connection
     * $pdo = new PDO($dsn, $username, $password, $options);
     * ```
     *
     * @example Application Settings
     * ```php
     * $settings = "timeout=30|retries=3|debug=1|environment=production";
     * $config = Utils::parseToAssocArray($settings);
     * // Result: [
     * //   'timeout' => 30,      // Note: key would be integer if numeric
     * //   'retries' => 3,
     * //   'debug' => 1,
     * //   'environment' => 'production'
     * // ]
     * ```
     *
     * @example Custom Separator
     * ```php
     * $data = "width=800&height=600&fullscreen=1";
     * $dimensions = Utils::parseToAssocArray($data, '&');
     * // Result: ['width' => 800, 'height' => 600, 'fullscreen' => 1]
     *
     * // Comma-separated configuration
     * $csvConfig = "port=3306,user=admin,password=secret";
     * $dbSettings = Utils::parseToAssocArray($csvConfig, ',');
     * ```
     *
     * @example Error Handling Cases
     * ```php
     * // Empty input
     * $empty = Utils::parseToAssocArray('');        // Returns: []
     * $null = Utils::parseToAssocArray(null);       // Returns: []
     *
     * // Malformed pairs (missing equals)
     * $malformed = Utils::parseToAssocArray('key1|key2=value|key3');
     * // Result: ['key2' => 'value'] (key1 and key3 skipped)
     *
     * // Mixed valid/invalid pairs
     * $mixed = "valid=123|invalid|another=456";
     * $result = Utils::parseToAssocArray($mixed);   // ['valid' => 123, 'another' => 456]
     * ```
     *
     * @example Configuration File Processing
     * ```php
     * // Read from config file
     * $configLine = trim(file_get_contents('app.config'));
     * $appConfig = Utils::parseToAssocArray($configLine);
     *
     * // Apply configuration
     * ini_set('max_execution_time', $appConfig['timeout'] ?? 30);
     * error_reporting($appConfig['error_level'] ?? E_ALL);
     * ```
     */
    public static function parseToAssocArray(?string $raw, string $separator = '|'): array
    {
        if (empty($raw)) return [];

        $options = [];

        foreach (explode($separator, $raw) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $key = (int) trim($parts[0]);
                $val = trim($parts[1]);

                // Cast to int if it's numeric
                if (is_numeric($val)) {
                    $val = (int) $val;
                }

                $options[$key] = $val;
            }
        }

        return $options;
    }

    /**
     * Calculate next available sequence number for patterned file naming
     *
     * Analyzes existing files matching a numeric pattern and returns the next available
     * sequence number. Useful for auto-incrementing file names, backup sequences, or
     * any systematic file numbering scheme requiring gap detection and collision avoidance.
     *
     * ## Pattern Format
     * - **Placeholder**: Use '#' characters to represent numeric digits
     * - **Digit Count**: Number of '#' characters determines padding (###  = 3 digits)
     * - **Pattern Example**: `/var/logs/app_###.log` matches `app_001.log`, `app_002.log`
     * - **Directory Support**: Full paths supported with automatic directory extraction
     *
     * ## Sequence Detection Logic
     * 1. **Pattern Analysis**: Count '#' characters to determine numeric field width
     * 2. **Glob Search**: Create wildcard pattern and scan directory for matches
     * 3. **Number Extraction**: Parse numeric sequences from matching filenames
     * 4. **Maximum Finding**: Identify highest existing sequence number
     * 5. **Next Calculation**: Return max + 1 as next available number
     *
     * ## Gap Handling
     * Returns next number after highest found, not first gap. If files exist as
     * `file_001.txt, file_003.txt, file_005.txt`, returns `6` not `2` or `4`.
     *
     * ## Zero Padding Awareness
     * Respects padding implied by pattern - `###` expects 3-digit numbers, so
     * generated filenames should use sprintf('%03d', $number) for consistency.
     *
     * @param string $pattern File path pattern with '#' placeholders for digits
     * @return int Next available sequence number (always ≥ 1)
     * @throws InvalidArgumentException If pattern contains no '#' characters
     * @since 1.0.0
     *
     * @example Log File Rotation
     * ```php
     * // Pattern: /var/log/app_###.log
     * // Existing: app_001.log, app_002.log, app_005.log
     * $nextNum = Utils::getNextFileSequenceNumber('/var/log/app_###.log');
     * // Returns: 6
     *
     * $newLogFile = sprintf('/var/log/app_%03d.log', $nextNum);
     * // Creates: /var/log/app_006.log
     * ```
     *
     * @example Backup File Management
     * ```php
     * $backupPattern = '/backups/database_backup_####.sql';
     * // Existing: database_backup_0001.sql, database_backup_0003.sql
     * $nextBackup = Utils::getNextFileSequenceNumber($backupPattern);
     * // Returns: 4
     *
     * $backupFile = sprintf('/backups/database_backup_%04d.sql', $nextBackup);
     * // Creates: /backups/database_backup_0004.sql
     * ```
     *
     * @example Upload File Sequences
     * ```php
     * function saveUpload($uploadedFile) {
     *     $pattern = '/uploads/user_files/document_###.pdf';
     *     $sequence = Utils::getNextFileSequenceNumber($pattern);
     *     $filename = sprintf('/uploads/user_files/document_%03d.pdf', $sequence);
     *
     *     move_uploaded_file($uploadedFile['tmp_name'], $filename);
     *     return $filename;
     * }
     * ```
     *
     * @example Different Padding Patterns
     * ```php
     * // Single digit: #
     * $next1 = Utils::getNextFileSequenceNumber('/temp/file_#.tmp');     // Returns: 1,2,3...
     *
     * // Two digits: ##
     * $next2 = Utils::getNextFileSequenceNumber('/temp/file_##.tmp');    // Use %02d format
     *
     * // Five digits: #####
     * $next5 = Utils::getNextFileSequenceNumber('/temp/file_#####.tmp'); // Use %05d format
     * ```
     *
     * @example Error Cases
     * ```php
     * try {
     *     // Invalid pattern - no # characters
     *     $num = Utils::getNextFileSequenceNumber('/logs/static_filename.log');
     * } catch (InvalidArgumentException $e) {
     *     echo "Error: " . $e->getMessage(); // "Pattern must contain one or more '#' characters."
     * }
     * ```
     */
    public static function getNextFileSequenceNumber(string $pattern): int
    {
        // Extract directory and pattern parts
        $dir = dirname($pattern);
        $base = basename($pattern);

        // Count number of # symbols
        if (!preg_match('/#+/', $base, $matches)) {
            throw new InvalidArgumentException("Pattern must contain one or more '#' characters.");
        }

        $hashes = $matches[0];
        $numDigits = strlen($hashes);

        // Create a glob pattern by replacing # with wildcard
        $globPattern = str_replace($hashes, str_repeat('?', $numDigits), $base);
        $files = glob($dir . DIRECTORY_SEPARATOR . $globPattern);

        $max = 0;
        foreach ($files as $file) {
            if (preg_match('/(\d{' . $numDigits . '})/', basename($file), $match)) {
                $num = intval($match[1]);
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return $max + 1;
    }

    /**
     * Create directory structure for file path to prevent write failures
     *
     * Recursively creates parent directories for specified file path if they don't
     * already exist. Essential for dynamic file creation where directory structure
     * may not be pre-established, preventing common "No such file or directory" errors.
     *
     * ## Directory Creation Process
     * 1. **Path Analysis**: Extract directory path using dirname()
     * 2. **Existence Check**: Return early if directory already exists
     * 3. **Recursive Creation**: Create parent directories with mkdir(..., true)
     * 4. **Permission Setting**: Apply specified permissions to created directories
     * 5. **Result Return**: Boolean success/failure status
     *
     * ## Permission Handling
     * - **Default**: 0755 (owner: rwx, group: rx, other: rx)
     * - **Configurable**: Custom permissions via parameter
     * - **Recursive**: Applied to all created directories in path
     * - **Existing**: Does not modify permissions of existing directories
     *
     * ## Use Cases
     * - Log file initialization in dynamic paths
     * - Cache file storage with date-based directory structure
     * - Upload handling with user-specific directories
     * - Report generation in organized folder hierarchies
     * - Configuration file creation in multi-level paths
     *
     * @param string $filePath Complete file path (including filename) for directory creation
     * @param int $permissions Octal permissions for created directories (default: 0755)
     * @return bool True if directory exists or was created successfully, false on failure
     * @since 1.0.0
     *
     * @example Log File Directory Preparation
     * ```php
     * $logFile = '/var/log/app/2025/01/15/debug.log';
     *
     * if (Utils::ensureDirectoryForFile($logFile)) {
     *     file_put_contents($logFile, "Application started\n", FILE_APPEND);
     * } else {
     *     error_log("Failed to create log directory");
     * }
     * ```
     *
     * @example User Upload Organization
     * ```php
     * function saveUserUpload($userId, $filename, $uploadData) {
     *     $uploadPath = "/uploads/users/$userId/" . date('Y/m/') . $filename;
     *
     *     if (Utils::ensureDirectoryForFile($uploadPath)) {
     *         return file_put_contents($uploadPath, $uploadData) !== false;
     *     }
     *
     *     throw new RuntimeException("Unable to create upload directory");
     * }
     *
     * // Creates: /uploads/users/123/2025/01/ (if needed)
     * $success = saveUserUpload(123, 'document.pdf', $pdfData);
     * ```
     *
     * @example Cache File Management
     * ```php
     * class CacheManager {
     *     public static function store($key, $data) {
     *         $cachePath = '/tmp/cache/' . substr($key, 0, 2) . '/' . $key . '.cache';
     *
     *         if (Utils::ensureDirectoryForFile($cachePath)) {
     *             return file_put_contents($cachePath, serialize($data)) !== false;
     *         }
     *
     *         return false;
     *     }
     * }
     *
     * // Creates nested cache directories as needed
     * CacheManager::store('abc123def456', $cacheData);
     * // Creates: /tmp/cache/ab/ (if needed)
     * ```
     *
     * @example Custom Permissions
     * ```php
     * // More restrictive permissions for sensitive files
     * $sensitiveFile = '/secure/keys/private/app.key';
     * Utils::ensureDirectoryForFile($sensitiveFile, 0750); // rwxr-x---
     *
     * // More open permissions for public files
     * $publicFile = '/var/www/public/images/thumbnails/thumb.jpg';
     * Utils::ensureDirectoryForFile($publicFile, 0755); // rwxr-xr-x
     * ```
     *
     * @example Error Handling
     * ```php
     * function safeFileWrite($path, $data) {
     *     if (!Utils::ensureDirectoryForFile($path)) {
     *         throw new RuntimeException("Cannot create directory for: $path");
     *     }
     *
     *     if (file_put_contents($path, $data) === false) {
     *         throw new RuntimeException("Cannot write to file: $path");
     *     }
     *
     *     return true;
     * }
     * ```
     */
    public static function ensureDirectoryForFile(string $filePath, int $permissions = 0755): bool
    {
        $directory = dirname($filePath);
        if (is_dir($directory)) {
            return true;
        }
        return mkdir($directory, $permissions, true);
    }


}
