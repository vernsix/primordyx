<?php
/**
 * File: /vendor/vernsix/primordyx/src/AppAutoLoader.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/AppAutoLoader.php
 *
 */

declare(strict_types=1);
namespace Primordyx;

/**
 * Application Layer Class Autoloading for Primordyx Applications
 *
 * Provides PSR-4 compliant autoloading specifically for **application classes** that developers
 * create when building applications with the Primordyx framework. This autoloader handles your
 * Controllers, Models, Middleware, Services, and other custom application classes - NOT framework
 * or vendor classes (those are handled by Composer's autoloader).
 *
 * ## Purpose: Application Code Organization
 * Enables clean separation between:
 * - **Framework Code**: Primordyx classes (loaded by Composer)
 * - **Vendor Libraries**: Third-party packages (loaded by Composer)
 * - **Your Application**: Whatever classes YOU create (loaded by AppAutoLoader)
 *
 * ## Flexible Directory Structure
 * The recursive scanner maps ANY directory structure you choose to namespaces:
 * - `app/Controllers/` → `Controllers\*` classes
 * - `app/MyApp/Controllers/` → `MyApp\Controllers\*` classes
 * - `app/Stuff/Whatever/` → `Stuff\Whatever\*` classes
 * - `app/SingleFile.php` → `SingleFile` class
 *
 * ## Key Features
 * - **PSR-4 Compliant** - Follows PSR-4 autoloading standards for namespace-to-directory mapping
 * - **O(1) Class Loading** - Pre-builds classmap for instant class resolution
 * - **Flexible Setup** - Register one or multiple autoloaders as needed
 * - **Recursive Directory Scanning** - Automatically maps nested directory structures to namespaces
 * - **Zero Static State** - No shared state, clean implementation
 * - **Flexible Organization** - Maps whatever directory structure you create
 * - **Graceful Failure** - Logs failures but allows other autoloaders to attempt loading
 *
 * ## PSR-4 Namespace Mapping
 * The autoloader follows PSR-4 standards where:
 * - Namespace separators (`\`) map to directory separators (`/`)
 * - Class names map to file names with `.php` extension
 * - Directory structure mirrors namespace hierarchy
 *
 * ## Architecture
 * When you call `enable()`, the autoloader:
 * 1. Scans the specified directory tree recursively
 * 2. Builds a complete classmap mapping namespaces to file paths
 * 3. Registers an autoloader callback with the classmap
 * 4. Provides O(1) class loading performance via hash lookups
 *
 * ## Typical Usage Pattern
 * Most applications call `enable()` once during bootstrap, but multiple autoloaders
 * can be registered if your application structure requires it.
 *
 * ## Performance Characteristics
 * - **Setup Time**: O(n) where n is the number of PHP files (one-time cost)
 * - **Class Loading**: O(1) hash lookup (no filesystem operations)
 * - **Memory Usage**: Minimal - only stores classmap array
 * - **Multi-Autoloader**: Each autoloader operates independently with its own classmap
 *
 * ## Integration with Composer Autoloader
 * This autoloader works **alongside** (not instead of) Composer's autoloader:
 * - **Composer Autoloader**: Loads Primordyx framework classes and vendor libraries
 * - **AppAutoLoader**: Loads YOUR application classes (Controllers, Models, etc.)
 * - **Together**: Provides complete class loading for your Primordyx application
 *
 * ## Security Considerations
 * - Only scans specified directory trees (no filesystem traversal attacks)
 * - Validates file paths before inclusion
 * - Graceful error handling prevents application crashes
 * - Directory validation prevents autoloader registration on non-existent paths
 *
 * ## Integration
 * - Works alongside Composer autoloader without conflicts
 * - Compatible with any existing SPL autoloader stack
 * - Can be used with EventManager for autoloader event handling
 * - Supports multiple PHP file extensions (configurable)
 *
 * @see spl_autoload_register() For PHP autoloader registration
 * @see https://www.php-fig.org/psr/psr-4/ PSR-4 Autoloading Standard
 * @package Primordyx
 * @since 1.0.0
 */
class AppAutoLoader
{
    /**
     * Enable PSR-4 compliant autoloading for your application classes
     *
     * Creates an autoloader specifically for the classes YOU create when building your
     * Primordyx application (Controllers, Models, Middleware, Services, etc.). This
     * autoloader works alongside Composer's autoloader - Composer handles framework
     * and vendor classes, while AppAutoLoader handles your custom application classes.
     *
     * ## Application Class Organization
     * The autoloader recursively maps whatever directory structure you create:
     * - Organize by type: `Controllers/`, `Models/`, `Services/`
     * - Organize by feature: `User/`, `Product/`, `Admin/`
     * - Organize by vendor: `MyCompany/`, `ThirdParty/`
     * - Mix approaches: `MyApp/Controllers/`, `Legacy/Utils/`
     * - Single files: `Helper.php` directly in app directory
     *
     * ## Process Overview
     * 1. **Path Validation**: Ensures the namespace directory exists
     * 2. **Classmap Building**: Recursively scans directory tree and maps classes to files
     * 3. **Autoloader Registration**: Registers SPL autoloader with captured classmap
     * 4. **Error Logging**: Logs but doesn't throw on missing directories
     *
     * ## Directory Structure Requirements
     * - Files must end with `.php` extension
     * - Directory names must match namespace segments
     * - File names must match class names
     * - One class per file (PSR-4 standard)
     *
     * ## Performance Notes
     * - Setup cost: O(n) where n = number of PHP files (one-time)
     * - Class loading: O(1) hash lookup (no filesystem I/O)
     * - Memory usage: Minimal classmap storage only
     * - Multiple autoloaders: Each operates independently
     *
     * @param string $appRoot The absolute path to the application root directory.
     *                        Should be a full filesystem path like '/var/www/myapp'
     * @param string $namespaceDirectory Directory name relative to appRoot containing
     *                                  namespaced classes. Defaults to 'app' - the standard
     *                                  Primordyx convention for application classes.
     *
     * @return void
     *
     * @example Default App Directory
     * ```php
     * // Scans /var/www/myproject/app/ recursively (standard default)
     * AppAutoLoader::enable('/var/www/myproject');
     *
     * // Enables autoloading for:
     * // app/Controllers/HomeController.php -> Controllers\HomeController
     * // app/Models/User.php -> Models\User
     * // app/Services/EmailService.php -> Services\EmailService
     * // app/Utils/StringHelper.php -> Utils\StringHelper
     * ```
     *
     * @example Custom Application Directory
     * ```php
     * // Use 'src' directory for your application classes
     * AppAutoLoader::enable('/var/www/myapi', 'src');
     *
     * // Your API application structure:
     * // src/Controllers/ApiController.php -> Controllers\ApiController
     * // src/Models/User.php -> Models\User
     * // src/Services/AuthService.php -> Services\AuthService
     * // src/Middleware/RateLimitMiddleware.php -> Middleware\RateLimitMiddleware
     *
     * // Your application classes are autoloaded:
     * $api = new Controllers\ApiController();
     * $auth = new Services\AuthService();
     * ```
     *
     * @example Application Bootstrap
     *  ```php
     *  // Typical application bootstrap - one autoloader call (most common)
     *  require_once 'vendor/autoload.php';        // Loads framework + vendor
     *  AppAutoLoader::enable('/var/www/myapp');   // Loads YOUR application classes
     *
     *  // That's it! Now all your application classes are available:
     *  $home = new Controllers\HomeController();
     *  $user = new Models\User();
     *  $email = new Services\EmailService();
     *  ```
     *
     * @since 1.0.0
     */
    public static function enable(string $appRoot, string $namespaceDirectory = 'app'): void
    {
        // Clean up the app root path and build namespace root
        $cleanAppRoot = rtrim($appRoot, '/');
        $namespaceRoot = $cleanAppRoot . '/' . trim($namespaceDirectory, '/');

        // Verify the namespace directory exists
        if (!is_dir($namespaceRoot)) {
            error_log("Primordyx AppAutoLoader: Namespace directory does not exist: $namespaceRoot");
            return;
        }

        // Build classmap for THIS specific autoloader instance
        $classMap = self::buildClassMap($namespaceRoot);

        // Register the autoloader with classmap captured in closure
        spl_autoload_register(function ($className) use ($classMap) {
            self::loadAppClass($className, $classMap);
        });
    }

    /**
     * Build classmap by scanning the namespace root directory recursively
     *
     * @param string $namespaceRoot The root directory containing namespaced classes
     * @return array<string, string> The classmap for this autoloader instance
     */
    private static function buildClassMap(string $namespaceRoot): array
    {

        // File extensions to scan for
        $fileExtensions = [
            '.php',
            // '.class.php',
            // '.controller.php',
            // '.middleware.php',
            // '.model.php'
        ];

        // Recursively scan from the namespace root
        return self::scanDirectory($namespaceRoot, '', $fileExtensions);
    }

    /**
     * Recursively scan directory and build PSR-4 class mappings
     *
     * @param string $directory The directory to scan recursively
     * @param string $namespacePrefix The current namespace prefix (builds as recursion deepens)
     * @param array<string> $fileExtensions File extensions to look for
     * @return array<string, string> Classmap entries found in this directory and subdirectories
     */
    private static function scanDirectory(string $directory, string $namespacePrefix, array $fileExtensions): array
    {
        $classMap = [];

        // Get all items in the directory
        $items = glob($directory . '/*');
        if ($items === false) {
            return $classMap;
        }

        foreach ($items as $item) {
            if (is_dir($item)) {
                // Recursive scan of subdirectories
                $dirName = basename($item);
                $subNamespace = $namespacePrefix === '' ? $dirName : $namespacePrefix . '\\' . $dirName;
                $classMap = array_merge($classMap, self::scanDirectory($item, $subNamespace, $fileExtensions));
            } elseif (is_file($item)) {
                // Check if it's a PHP class file
                $filename = basename($item);

                foreach ($fileExtensions as $extension) {
                    if (str_ends_with($filename, $extension)) {
                        // Extract class name by removing extension
                        $className = substr($filename, 0, -strlen($extension));

                        // Build full namespaced class name
                        $fullClassName = $namespacePrefix === '' ? $className : $namespacePrefix . '\\' . $className;

                        // Store mapping: full class name -> file path
                        $classMap[$fullClassName] = $item;
                        break;
                    }
                }
            }
        }

        return $classMap;
    }

    /**
     * Autoloader callback function for loading application classes
     *
     * @param string $className The fully qualified class name to load
     * @param array<string, string> $classMap The classmap for this autoloader instance
     * @return void
     */
    private static function loadAppClass(string $className, array $classMap): void
    {
        // Check if we have this class in our map
        if (isset($classMap[$className])) {
            $filePath = $classMap[$className];
            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }

        // If we get here, the class wasn't found in app directories
        // Log the attempt for debugging, but don't throw - let other autoloaders try
        $caller = self::getCaller();
        $msg = "Primordyx AppAutoLoader: Could not find '$className' (called from $caller)";
        error_log($msg);
    }

    /**
     * Get diagnostic information about the calling context for debugging purposes
     *
     * Walks through the debug backtrace to find the first caller that isn't part of the
     * autoloading system itself. This is primarily used for error logging to identify
     * where a failed class load attempt originated from, excluding autoloader internals.
     *
     * The function filters out:
     * - Files containing 'autoload' in their path
     * - The AppAutoLoader.php file itself
     *
     * This ensures the reported caller is the actual application code that triggered
     * the autoload attempt, not internal autoloader mechanics.
     *
     * @since 1.0.0
     * @static
     *
     * @return string The file path and line number of the calling code in format "file:line",
     *                or "unknown source" if no suitable caller can be identified
     *
     * @example
     * // When called from /var/www/app/Controllers/HomeController.php at line 25:
     * // Returns: "/var/www/app/Controllers/HomeController.php:25"
     *
     * @uses debug_backtrace() Uses DEBUG_BACKTRACE_IGNORE_ARGS for performance
     * @used-by AppAutoLoader::loadAppClass() For error logging when class loading fails
     */
    public static function getCaller(): string
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($bt as $entry) {
            $file = $entry['file'] ?? '';
            if (!str_contains($file, 'autoload') && !str_contains($file, 'AppAutoLoader.php')) {
                return $file . ':' . ($entry['line'] ?? '??');
            }
        }
        return 'unknown source';
    }
}

