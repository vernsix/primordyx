<?php
/**
 * File: /vendor/vernsix/primordyx/src/Ini.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Ini.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Config;

use RuntimeException;

/**
 * Class Ini
 *
 * A lightweight INI file handler with read/write support, default sectioning,
 * and an optional safe-save capability that preserves comment lines when possible.
 *
 * @since       1.0.0
 *
 */
class Ini
{
    protected string $filename;
    protected string $defaultSection;
    protected bool $readOnly = false;
    protected array|null $contents = [];

    /**
     * Ini constructor.
     *
     * Initializes the INI handler using the given filename and section.
     *
     * @param string $filename        The INI file path.
     * @param string $defaultSection  The default section name to use.
     * @param bool $readOnly          Whether to open in read-only mode.
     */
    public function __construct(string $filename, string $defaultSection, bool $readOnly = false)
    {
        $this->filename = $filename;
        $this->defaultSection = $defaultSection;
        $this->readOnly = $readOnly;
        $this->loadFromFile();
    }

    /**
     * Loads content from an INI file or creates a stub file if it doesn't exist.  This DOES NOT change
     * the filename that future calls to save functions will do.  This gives you the ability to copy from
     * an existing file
     *
     * @param string $filename Optional filename to override the current one.
     * @return void
     */
    public function loadFromFile(string $filename = ''): void
    {
        if (empty($filename)) {
            $fileToLoad = $this->filename;
        } else {
            $fileToLoad = $filename;
        }
        if (empty($fileToLoad)) {
            throw new RuntimeException("INI-1: No INI filename specified.");
        }
        if (!file_exists($fileToLoad)) {
            $stub = '[' . $this->defaultSection . ']' . PHP_EOL;
            if (file_put_contents($this->filename, $stub, LOCK_EX) === false) {
                throw new RuntimeException("INI-2: Failed to create INI file: " . $fileToLoad);
            }
        }

        $raw = file_get_contents($fileToLoad);
        if ($raw === false) {
            throw new RuntimeException("INI-11: Failed to read file: $fileToLoad");
        }

        $parsed = @parse_ini_string($raw, true);
        if ($parsed === false) {
            $err = error_get_last();
            $msg = $err['message'] ?? 'Unknown parse_ini_string() error';
            throw new RuntimeException("INI-12: Failed to parse INI contents: $msg");
        }

        $this->contents = $parsed;

    }

    /**
     * Returns the raw contents array.
     *
     * @return array
     */
    public function dump(): array
    {
        return $this->contents;
    }

    /**
     * Alias of dump().
     *
     * @return array
     */
    public function all(): array
    {
        return $this->dump();
    }

    /**
     * Gets a value from the INI content.
     *
     * Returns the raw value from parse_ini_file(), which may be a string, int, float, or bool,
     * depending on whether the original value was quoted or unquoted.
     * If the key is not found, returns the string 'undefined' for consistency with older behavior.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param string $default optional default value
     * @return mixed The raw value or 'undefined' if the key is missing
     */
    public function get(string $key, string $section = '', string $default = 'undefined'): mixed
    {
        $section = $section ?: $this->defaultSection;
        return $this->contents[$section][$key] ?? $default;
    }



    /**
     * Get a config value as a boolean.
     *
     * Supports native booleans or common truthy/falsy strings like "true", "false", "on", "off", etc.
     * Quoted values in the INI will be strings and need parsing. Unquoted values may be real booleans.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param bool $default Fallback value if key is missing or unrecognized
     * @return bool
     */
    public function getBool(string $key, string $section = '', bool $default = false): bool
    {
        $value = $this->get($key, $section);

        if (is_bool($value)) return $value;

        return match (strtolower((string)$value)) {
            '1', 'true', 'yes', 'on'  => true,
            '0', 'false', 'no', 'off' => false,
            default                   => $default,
        };
    }

    /**
     * Get a config value as an integer.
     *
     * parse_ini_file() might return strings if the value was quoted,
     * so this explicitly casts the result.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param int $default Fallback if value is missing or invalid
     * @return int
     */
    public function getInt(string $key, string $section = '', int $default = 0): int
    {
        $value = $this->get($key, $section);

        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Get a config value as a float.
     *
     * Like getInt(), this ensures type safety since parse_ini_file()
     * returns strings for quoted numbers.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param float $default Fallback if value is missing or invalid
     * @return float
     */
    public function getFloat(string $key, string $section = '', float $default = 0.0): float
    {
        $value = $this->get($key, $section);

        return is_numeric($value) ? (float)$value : $default;
    }

    /**
     * Get a config value as an array (comma-separated).
     *
     * Useful for values like: "one,two,three"
     * Will trim whitespace and ignore empty segments.
     * Returns an empty array if the key is missing or empty.
     *
     * Note: parse_ini_file() may return raw strings even for lists,
     * so this method does the right thing regardless of quoting.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param string $separator Delimiter to split on (default: comma)
     * @return array<int, string>
     */
    public function getArray(string $key, string $section = '', string $separator = ','): array
    {
        $value = $this->get($key, $section);

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode($separator, $value)),
                fn($item) => $item !== ''
            )
        );
    }

    /**
     * Get a config value as a JSON-decoded array.
     *
     * Some weirdos (probably you) put actual JSON in an INI file.
     * This decodes it into an array and fails gracefully if it's not valid JSON.
     *
     * Example INI:
     * ```php
     * options = '["foo", "bar", "baz"]'
     * ```
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param array $default Fallback if not set or invalid
     * @return array Can be replaced with 'array'
     */
    public function getJsonArray(string $key, string $section = '', array $default = []): array
    {
        $value = $this->get($key, $section);

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Get a config value that must match one of the allowed options.
     *
     * Helps validate "enum-style" settings where only specific values are valid.
     *
     * @param string $key
     * @param string $section Optional section to look in
     * @param array $allowed List of allowed values
     * @param string $default Fallback if not present or invalid
     * @return string
     */
    public function getEnum(string $key, string $section = '', array $allowed = [], string $default = ''): string
    {
        $value = $this->get($key, $section);

        return in_array($value, $allowed, true) ? $value : $default;
    }




    /**
     * Sets a value in the INI content.
     *
     * @param string $key
     * @param string $value
     * @param string $section
     * @return string
     */
    public function set(string $key, string $value, string $section = ''): string
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-3:" . $this->filename . " is in read-only mode.");
        }
        $section = $section ?: $this->defaultSection;
        $originalValue = $this->get($key, $section);
        $this->contents[$section][$key] = $value;
        return $originalValue;
    }

    /**
     * Deletes a key from the contents.
     *
     * @param string $key
     * @param string $section
     * @return string The original value before deletion.
     */
    public function delete(string $key, string $section = ''): string
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-4:" . $this->filename . " is in read-only mode.");
        }
        $section = $section ?: $this->defaultSection;
        $originalValue = $this->get($key, $section);
        if (isset($this->contents[$section][$key])) {
            unset($this->contents[$section][$key]);
        }
        return $originalValue;
    }

    /**
     * Gets all key-value pairs from a section.
     *
     * @param string $section
     * @return array
     */
    public function getSection(string $section = ''): array
    {
        $section = $section ?: $this->defaultSection;
        return $this->contents[$section] ?? [];
    }

    /**
     * Deletes an entire section from the contents.
     *
     * @param string $section
     * @return array The original contents of the deleted section.
     */
    public function deleteSection(string $section): array
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-5:" . $this->filename . " is in read-only mode.");
        }
        $section = $section ?: $this->defaultSection;
        if (isset($this->contents[$section])) {
            $oldSection = $this->contents[$section];
            unset($this->contents[$section]);
        } else {
            $oldSection = [];
        }
        return $oldSection;
    }

    /**
     * Checks if a key exists in the given section.
     *
     * @param string $key
     * @param string $section
     * @return bool
     */
    public function has(string $key, string $section = ''): bool
    {
        $section = $section ?: $this->defaultSection;
        return isset($this->contents[$section][$key]);
    }

    /**
     * Returns the boolean interpretation of a key's value.
     *
     * @param string $key
     * @param string $section
     * @return bool
     */
    public function bool(string $key, string $section = ''): bool
    {
        return filter_var($this->get($key, $section), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Converts the entire contents array to a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->contents, JSON_PRETTY_PRINT);
    }

    /**
     * Returns the currently loaded filename.
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Returns the current default section.
     *
     * @return string
     */
    public function getDefaultSection(): string
    {
        return $this->defaultSection ?: '';
    }

    /**
     * Sets the default section to use when none is provided.
     *
     * @param string $section
     * @return string The old default section.
     */
    public function setDefaultSection(string $section): string
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-6:" . $this->filename . " is in read-only mode.");
        }
        $oldDefaultSection = $this->defaultSection ?: '';
        $this->defaultSection = $section;
        return $oldDefaultSection;
    }

    /**
     * Clears all contents.
     *
     * @return void
     */
    public function clear(): void
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-7:" . $this->filename . " is in read-only mode.");
        }
        $this->contents = [];
    }

    /**
     * Resets readOnly status and clears all contents.
     *
     * @return void
     */
    public function reset(): void
    {
        if ($this->readOnly) {
            throw new RuntimeException("INI-8:" . $this->filename . " is in read-only mode.");
        }
        $this->readOnly = false;
        $this->contents = []; // not really a true reset as it doesn't provoke a load
    }

    /**
     * Resets and reloads the INI file from the disk.
     *
     * @return void
     */
    public function reload(): void
    {
        $this->reset();
        $this->loadFromFile();
    }

    /**
     * Locks the INI handler to prevent write operations.
     *
     * @return void
     */
    public function lock(): void
    {
        $this->readOnly = true;
    }

    /**
     * Unlocks the INI handler to allow your write operations.
     *
     * @return void
     */public function unlock(): void
    {
        $this->readOnly = false;
    }

    /**
     * Saves the current INI contents to the file.
     * Note: This will overwrite the file and remove all comments.
     *
     * @param string $filename Optional filename override.
     * @return bool|int Number of bytes written, or false on failure.
     */
    public function saveToFile(string $filename = ''): bool|int  // kills comment lines!
    {
        $this->filename = $filename ?: $this->filename;

        if (empty($this->filename)) {
            throw new RuntimeException("INI-9: No INI filename specified to save to.");
        }

        // Write an empty file if contents are null or empty
        if (empty($this->contents)) {
            return file_put_contents($filename, '', LOCK_EX);
        }

        $content = '';

        foreach ($this->contents as $section => $values) {
            if (!is_array($values)) continue;

            if (!empty($content)) {
                $content .= PHP_EOL;
            }

            $content .= "[$section]" . PHP_EOL;

            foreach ($values as $key => $val) {
                $escapedVal = is_string($val) ? '"' . $val . '"' : $val;
                $content .= "$key = $escapedVal" . PHP_EOL;
            }
        }

        return file_put_contents($this->filename, $content, LOCK_EX);
    }


    /**
     * Safely writes contents to the file, preserving existing structure and comments
     * where possible by modifying in-place.
     *
     * @param string $filename Optional filename override.
     * @return void
     */
    public function safeSave(string $filename = ''): void
    {
        $this->filename = $filename ?: $this->filename;
        if (!file_exists($this->filename)) {
            throw new RuntimeException("INI-9: Cannot safe-save non-existent file.");
        }

        $lines = file($this->filename, FILE_IGNORE_NEW_LINES);
        $output = [];
        $currentSection = '';
        $sectionsSeen = [];
        $handledKeys = [];

        // Flatten contents
        $flat = [];
        foreach ($this->contents as $section => $values) {
            foreach ($values as $key => $val) {
                $flatKey = "$section.$key";
                $flat[$flatKey] = is_string($val) ? '"' . $val . '"' : $val;
            }
        }

        // First pass... rewrite keys in-place, and collect section ranges
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // New section
            if (preg_match('/^\[(.+)]$/', $trimmed, $m)) {
                // Inject pending new keys from the previous section before moving on
                if ($currentSection !== '' && isset($this->contents[$currentSection])) {
                    foreach ($this->contents[$currentSection] as $key => $val) {
                        $flatKey = "$currentSection.$key";
                        if (!isset($handledKeys[$flatKey]) && !in_array("[$currentSection]", $output)) {
                            $output[] = "$key = $flat[$flatKey]";
                            $handledKeys[$flatKey] = true;
                        }
                    }
                }

                $currentSection = $m[1];
                $sectionsSeen[$currentSection] = true;
                $output[] = $line;
                continue;
            }

            // Key/value
            if (preg_match('/^([^=;#][^=]*)\s*=\s*(.*)$/', $line, $m)) {
                $key = trim($m[1]);
                $flatKey = "$currentSection.$key";

                if (array_key_exists($flatKey, $flat)) {
                    $output[] = "$key = " . $flat[$flatKey];
                    $handledKeys[$flatKey] = true;
                } else {
                    $output[] = $line;
                }

                continue;
            }

            // Everything else (comments, blanks, etc.)
            $output[] = $line;
        }

        // Inject remaining new keys into their existing sections
        if ($currentSection !== '' && isset($this->contents[$currentSection])) {
            foreach ($this->contents[$currentSection] as $key => $val) {
                $flatKey = "$currentSection.$key";
                if (!isset($handledKeys[$flatKey])) {
                    $output[] = "$key = $flat[$flatKey]";
                    $handledKeys[$flatKey] = true;
                }
            }
        }

        // Add completely new sections
        foreach ($this->contents as $section => $values) {
            if (isset($sectionsSeen[$section])) continue;

            $output[] = '';
            $output[] = "[$section]";
            foreach ($values as $key => $val) {
                $flatKey = "$section.$key";
                if (!isset($handledKeys[$flatKey])) {
                    $output[] = "$key = " . (is_string($val) ? '"' . $val . '"' : $val);
                    $handledKeys[$flatKey] = true;
                }
            }
        }

        file_put_contents($this->filename, implode(PHP_EOL, $output) . PHP_EOL, LOCK_EX);
    }

}
