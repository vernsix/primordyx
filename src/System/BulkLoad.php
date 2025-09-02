<?php
/**
 * File: /vendor/vernsix/primordyx/src/BulkLoad.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/BulkLoad.php
 *
 */

declare(strict_types=1);
namespace Primordyx\System;

use DirectoryIterator;
use Primordyx\Events\EventManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Bulk loads PHP scripts from directories with optional recursion and file filtering.
 *
 * This class provides utilities to load multiple PHP files from directories,
 * with support for recursive scanning and dry-run preview. It's designed for
 * cases where you need to include many related files at once, such as loading
 * configuration files, helper functions, or plugin directories.
 *
 * Key Features:
 * - Loads all PHP files from a specified directory using require_once
 * - Skips files that have a corresponding .disabled sibling file (allows runtime toggling)
 * - Requires absolute paths for explicit, unambiguous behavior
 * - Recursive directory scanning to include subdirectories
 * - Dry-run capability to preview files without actually loading them
 * - Automatic sorting of files for consistent, predictable load order
 * - Event-driven error reporting through EventManager for better debugging
 *
 * File Filtering Logic:
 * The .disabled mechanism allows you to temporarily disable files without moving or renaming them.
 * If "myfile.php.disabled" exists alongside "myfile.php", the PHP file will be skipped.
 * This is useful for debugging, feature toggles, or environment-specific includes.

 * @example
 * ```php
 * // Load all configuration files from an absolute directory
 * $loaded = BulkLoad::loadAll('/var/www/app/config/database');
 * ```
 *
 * @example
 * ```php
 * // Load all helper functions recursively from subdirectories
 * $loaded = BulkLoad::loadAll('/var/www/app/helpers', true);
 * ```
 *
 * @example
 * ```php
 * // Preview what would be loaded without actually including files
 * $files = BulkLoad::listAllDryRun('/var/www/app/includes/email');
 * foreach ($files as $file) {
 *     echo "Would load: $file\n";
 * }
 * ```
 *
 * @example
 * ```php
 * // Load from system library directory
 * $loaded = BulkLoad::loadAll('/usr/share/php/mylib');
 * ```
 */
class BulkLoad
{

    /**
     * Load all PHP scripts from a directory using require_once, with optional recursion.
     *
     * This method scans the specified directory for .php files and includes each one using require_once.
     * Files are processed in alphabetical order for predictable behavior. The .disabled filtering
     * mechanism allows runtime control over which files get loaded - useful for feature toggles,
     * debugging, or environment-specific configurations.
     *
     * Error Handling:
     * If the target directory doesn't exist, an error event is fired via EventManager and an empty
     * array is returned. This allows the application to continue running while still tracking issues.
     *
     * Path Requirements:
     * - Only absolute paths are supported (must start with '/')
     * - Directory must exist on the filesystem
     *
     * @param string $path Absolute path to directory containing PHP files to load.
     *                     Must start with '/' and directory must exist.
     * @param bool $recursive If true, recursively scan subdirectories. Default false for performance.
     * @return array List of absolute file paths that were successfully loaded via require_once.
     *               Empty array if directory doesn't exist or contains no loadable PHP files.
     */
    public static function loadAll(string $path, bool $recursive = false): array
    {
        $targetPath = self::resolvePath($path);

        if ($targetPath === null) {
            return [];
        }

        $scripts = self::getScriptFiles($targetPath, $recursive);

        foreach ($scripts as $script) {
            require_once $script;
        }

        return $scripts;
    }

    /**
     * Preview PHP scripts that would be loaded from a directory without actually including them.
     *
     * This is the "dry run" version of loadAll() - it performs the same directory scanning,
     * filtering, and sorting logic but returns the list of files instead of loading them.
     * Useful for debugging, validation, or generating reports about what will be loaded.
     *
     * The filtering and path resolution logic is identical to loadAll():
     * - Only .php files are considered
     * - Files with .disabled siblings are skipped
     * - Results are sorted alphabetically for consistent ordering
     * - Same error handling via EventManager if directory doesn't exist
     *
     * @param string $path Absolute path to directory to scan. Must start with '/' and directory must exist.
     * @param bool $recursive If true, scan subdirectories recursively. Default false.
     * @return array List of absolute file paths that would be loaded by loadAll().
     *               Empty array if directory doesn't exist or contains no loadable PHP files.
     */
    public static function loadAllDryRun(string $path, bool $recursive = false): array
    {
        $targetPath = self::resolvePath($path);

        if ($targetPath === null) {
            return [];
        }

        return self::getScriptFiles($targetPath, $recursive);
    }

    /**
     * Validate that input path is absolute and that the directory exists.
     *
     * This internal method enforces the absolute path requirement and validates that the
     * target directory actually exists. By requiring absolute paths, the method eliminates
     * any ambiguity about base directories or application constants.
     *
     * Path Validation:
     * 1. Path must start with "/" (absolute path requirement)
     * 2. Directory must exist on the filesystem
     *
     * Error Handling:
     * For both relative paths and missing directories, this method fires an error
     * event via EventManager with structured context data. This allows applications to
     * handle path issues gracefully while maintaining visibility into problems.
     *
     * Event Structure:
     * The 'bulk.load.error' event includes:
     * - message: Human-readable error description
     * - path: Original path parameter passed in
     * - error_type: Categorized error type ('relative_path_not_supported' or 'directory_not_found')
     *
     * @param string $path Input path to validate - must be absolute
     * @return string|null Absolute path to existing directory, or null if path is relative or doesn't exist
     */
    private static function resolvePath(string $path): ?string
    {
        // Require absolute paths for explicit, unambiguous behavior
        if (!str_starts_with($path, '/')) {
            EventManager::fire('bulk.load.error', [
                'message' => 'Only absolute paths are supported',
                'path' => $path,
                'error_type' => 'relative_path_not_supported'
            ]);
            return null;
        }

        $targetPath = $path;

        // Verify the path exists
        if (!is_dir($targetPath)) {
            EventManager::fire('bulk.load.error', [
                'message' => 'Directory does not exist',
                'path' => $path,
                'error_type' => 'directory_not_found'
            ]);
            return null;
        }

        return $targetPath;
    }

    /**
     * Scan directory for PHP files, applying filters and returning sorted list.
     *
     * This is the core file discovery method that handles the actual directory iteration,
     * file filtering, and result preparation. It uses PHP's SPL directory iterators for
     * efficient scanning and applies multiple filters to ensure only appropriate files
     * are included.
     *
     * Iteration Strategy:
     * - Non-recursive: Uses DirectoryIterator for single-level scanning (faster)
     * - Recursive: Uses RecursiveDirectoryIterator + RecursiveIteratorIterator for deep scanning
     *
     * File Filtering Criteria:
     * 1. Must be a regular file (not directory, symlink, etc.)
     * 2. Must have .php extension (case-sensitive)
     * 3. Must NOT have a corresponding .disabled sibling file
     *    - Example: if "config.php.disabled" exists, "config.php" is skipped
     *    - This allows runtime enabling/disabling without file moves or renames
     *
     * Result Processing:
     * Files are collected as absolute paths (via getRealPath()) and then sorted using
     * case-insensitive string comparison. This ensures consistent load order across
     * different filesystems and operating systems.
     *
     * @param string $targetPath Absolute path to directory that has already been validated to exist
     * @param bool $recursive Whether to scan subdirectories recursively
     * @return array Sorted list of absolute file paths for .php files that passed all filters
     */
    private static function getScriptFiles(string $targetPath, bool $recursive): array
    {
        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetPath))
            : new DirectoryIterator($targetPath);

        $scripts = [];

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            if (file_exists($filePath . '.disabled')) {
                continue;
            }

            $scripts[] = $filePath;
        }

        sort($scripts, SORT_STRING | SORT_FLAG_CASE);

        return $scripts;
    }

}