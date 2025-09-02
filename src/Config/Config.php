<?php
/**
 * File: /vendor/vernsix/primordyx/src/Config.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Config.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Config;

use RuntimeException;

/**
 * Config - Application Configuration Management
 *
 * A static facade for managing application configuration via INI files.
 * Provides single-load performance by reading the INI file once and caching
 * in memory for the application lifecycle.
 *
 * Usage:
 * ```php
 *   Config::initialize('/path/to/config.ini');         // Uses 'default' section by default
 *   Config::initialize('/path/to/config.ini', 'prod'); // Uses 'prod' section by default
 *   $dbHost = Config::get('host', 'database');         // [database] section
 *   $apiKey = Config::get('api_key');                  // Uses default section
 *   $enabled = Config::getBool('enabled', 'features'); // Type-safe boolean
 *   $port = Config::getInt('port', 'database', 3306);  // Type-safe integer
 * ```
 *
 */
class Config
{
    /**
     * The Ini configuration object
     */
    private static ?Ini $config = null;

    /**
     * Initialize the application configuration.
     *
     * @param string $filename Path to the INI configuration file
     * @param string $defaultSection Default section to use when none specified (defaults to 'default')
     * @return void
     * @throws RuntimeException if the INI file cannot be loaded
     */
    public static function initialize(string $filename, string $defaultSection = 'default'): void
    {
        self::$config = new Ini($filename, $defaultSection);
    }

    /**
     * Ensure configuration is initialized, throw exception if not.
     *
     * @return void
     * @throws RuntimeException if configuration is not initialized
     */
    private static function ensureInitialized(): void
    {
        if (self::$config === null) {
            throw new RuntimeException("Configuration is not initialized. Call Config::initialize(\$filename, \$defaultSection) first.");
        }
    }

    /**
     * Get a configuration value.
     *
     * When no section is specified, uses the default section that was set during initialize().
     *
     * Examples:
     * ```php
     *   Config::initialize('/path/config.ini');           // Uses 'default' as default section
     *   Config::get('api_key')              // Looks in default section (usually [default])
     *   Config::get('host', 'database')     // Looks in [database] section
     *   Config::get('debug', 'app', false)  // Looks in [app] section with fallback
     * ```
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     * @throws RuntimeException if configuration is not initialized
     */
    public static function get(string $key, string $section = '', mixed $default = null): mixed
    {
        self::ensureInitialized();
        return self::$config->get($key, $section, $default);
    }

    /**
     * Get a configuration value as a boolean.
     *
     * Supports native booleans or common truthy/falsy strings like "true", "false", "on", "off", etc.
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param bool $default Default value if key not found
     * @return bool The configuration value as boolean
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getBool(string $key, string $section = '', bool $default = false): bool
    {
        self::ensureInitialized();
        return self::$config->getBool($key, $section, $default);
    }

    /**
     * Get a configuration value as an integer.
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param int $default Default value if key not found
     * @return int The configuration value as integer
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getInt(string $key, string $section = '', int $default = 0): int
    {
        self::ensureInitialized();
        return self::$config->getInt($key, $section, $default);
    }

    /**
     * Get a configuration value as a float.
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param float $default Default value if key not found
     * @return float The configuration value as float
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getFloat(string $key, string $section = '', float $default = 0.0): float
    {
        self::ensureInitialized();
        return self::$config->getFloat($key, $section, $default);
    }

    /**
     * Get a configuration value as an array (comma-separated).
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param string $separator Delimiter to split on (default: comma)
     * @return array<int, string> The configuration value as array
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getArray(string $key, string $section = '', string $separator = ','): array
    {
        self::ensureInitialized();
        return self::$config->getArray($key, $section, $separator);
    }

    /**
     * Get a configuration value as a JSON-decoded array.
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param array $default Default value if key not found or invalid JSON
     * @return array The configuration value as array from JSON
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getJsonArray(string $key, string $section = '', array $default = []): array
    {
        self::ensureInitialized();
        return self::$config->getJsonArray($key, $section, $default);
    }

    /**
     * Get a configuration value that must match one of the allowed options.
     *
     * @param string $key The configuration key to retrieve
     * @param string $section Section name (uses default section if empty)
     * @param array $allowed List of allowed values
     * @param string $default Default value if key not found or invalid
     * @return string The configuration value if valid, default otherwise
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getEnum(string $key, string $section = '', array $allowed = [], string $default = ''): string
    {
        self::ensureInitialized();
        return self::$config->getEnum($key, $section, $allowed, $default);
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The configuration key to check
     * @param string $section Section name (uses default section if empty)
     * @return bool True if key exists, false otherwise
     * @throws RuntimeException if configuration is not initialized
     */
    public static function has(string $key, string $section = ''): bool
    {
        self::ensureInitialized();
        return self::$config->has($key, $section);
    }

    /**
     * Get all key-value pairs from a section.
     *
     * @param string $section Section name (uses default section if empty)
     * @return array All configuration values from the section
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getSection(string $section = ''): array
    {
        self::ensureInitialized();
        return self::$config->getSection($section);
    }

    /**
     * Get all configuration sections and their values.
     *
     * @return array All configuration data
     * @throws RuntimeException if configuration is not initialized
     */
    public static function dump(): array
    {
        self::ensureInitialized();
        return self::$config->dump();
    }

    /**
     * Get all configuration data (alias for dump).
     *
     * @return array All configuration data
     * @throws RuntimeException if configuration is not initialized
     */
    public static function all(): array
    {
        return self::dump();
    }

    /**
     * Check if the configuration has been initialized.
     *
     * @return bool True if initialized, false otherwise
     */
    public static function isInitialized(): bool
    {
        return self::$config !== null;
    }

    /**
     * Get the underlying Ini configuration object.
     *
     * Useful for advanced operations that aren't covered by the static facade.
     *
     * @return Ini The configuration object
     * @throws RuntimeException if configuration is not initialized
     */
    public static function getIni(): Ini
    {
        self::ensureInitialized();
        return self::$config;
    }

    /**
     * Reset the configuration (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$config = null;
    }

    /**
     * Re-initialize with new settings.
     *
     * @param string $filename Path to the INI configuration file
     * @param string $defaultSection Default section to use when none specified (defaults to 'default')
     * @return void
     */
    public static function reload(string $filename, string $defaultSection = 'default'): void
    {
        self::initialize($filename, $defaultSection);
    }
}