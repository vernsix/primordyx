<?php
/**
 * File: /vendor/vernsix/primordyx/src/Bundle.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Bundle.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use RuntimeException;

/**
 * Class Bundle
 *
 *  A flexible, static container for storing arbitrary named values at runtime.
 *  Each "thing" is stored under a unique key, and can be anything: objects, arrays,
 *  config fragments, closures, strings, or even other bundles.
 *
 *  This class exists as a lightweight alternative to Cargo — intentionally simple,
 *  with no magic getters, expiration, encryption, or autoload dependencies.
 *
 *  Why Bundle?
 *  Because sometimes you just need to carry around a few named things without the overhead.
 *  Think of it as your stash bag for runtime state, tagged with whatever keys make sense.
 *
 *  Examples of use:
 *  - Centralized config fragments
 *  - Shared service references
 *  - Arbitrary runtime flags
 *  - Debug/testing hooks
 *
 */
class Bundle
{
    /**
     * This holds random stuff so you’ll figure it out — they’re just… things.
     *
     * @var array<string, mixed> Internal store of tagged values
     */
    protected static array $things = [];

    /**
     * Add any value to the bundle.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public static function add(string $key, mixed $value): mixed
    {
        $old = static::$things[$key] ?? null;
        self::$things[$key] = $value;
        return $old;
    }

    /**
     * Remove a value from the bundle.
     *
     * @param string $key
     * @return mixed
     */
    public static function drop(string $key): mixed
    {
        $old = static::$things[$key] ?? null;
        unset(self::$things[$key]);
        return $old;
    }

    /**
     * Retrieve a value from the bundle by key.
     *
     * @param string $key
     * @return mixed|null
     */
    public static function get(string $key): mixed
    {
        return self::$things[$key] ?? null;
    }

    /**
     * Check if a tag exists in the bundle.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$things);
    }

    /**
     * Check if a key exists and is not null.
     *
     * @param string $key
     * @return bool
     */
    public static function exists(string $key): bool
    {
        return isset(self::$things[$key]);
    }

    /**
     * List all keys currently in the bundle.
     *
     * @return string[]
     */
    public static function allKeys(): array
    {
        return array_keys(self::$things);
    }

    /**
     * Get all stored values as a key/value array.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$things;
    }

    // alias for all()
    public static function things(): array
    {
        return self::all();
    }

    /**
     * Apply a callback to each item in the bundle.
     *
     * @param callable $callback Receives ($key, $value)
     * @return void
     */
    public static function each(callable $callback): void
    {
        foreach (self::$things as $key => $value) {
            $callback($key, $value);
        }
    }

    /**
     * Filter the bundle in-place using a callback.
     *
     * @param callable $callback Receives ($key, $value) and returns bool
     * @return void
     */
    public static function filter(callable $callback): void
    {
        self::$things = array_filter(
            self::$things,
            fn($value, $key) => $callback($key, $value),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Drop items from the bundle that match a condition.
     *
     * @param callable $callback Receives ($key, $value) and returns bool
     * @return void
     */
    public static function dropBy(callable $callback): void
    {
        foreach (self::$things as $key => $value) {
            if ($callback($key, $value)) {
                unset(self::$things[$key]);
            }
        }
    }

    /**
     * Drop items whose tag contains a given substring.
     *
     * @param string $match
     * @return void
     */
    public static function dropIfKeyContains(string $match): void
    {
        self::dropBy(fn($key, $value) => str_contains($key, $match));
    }

    /**
     * Drop items whose tag starts with the given substring.
     *
     * @param string $prefix
     * @return void
     */
    public static function dropIfKeyStartsWith(string $prefix): void
    {
        self::dropBy(fn($key, $value) => str_starts_with($key, $prefix));
    }

    /**
     * Drop items whose tag ends with the given substring.
     *
     * @param string $suffix
     * @return void
     */
    public static function dropIfKeyEndsWith(string $suffix): void
    {
        self::dropBy(fn($key, $value) => str_ends_with($key, $suffix));
    }

    /**
     * Keep only items whose key contains the given substring.
     *
     * @param string $match
     * @return void
     */
    public static function keepIfKeyContains(string $match): void
    {
        self::filter(fn($key, $value) => str_contains($key, $match));
    }

    /**
     * Keep only items whose tag starts with the given substring.
     *
     * @param string $prefix
     * @return void
     */
    public static function keepIfKeyStartsWith(string $prefix): void
    {
        self::filter(fn($key, $value) => str_starts_with($key, $prefix));
    }

    /**
     * Keep only items whose key ends with the given substring.
     *
     * @param string $suffix
     * @return void
     */
    public static function keepIfKeyEndsWith(string $suffix): void
    {
        self::filter(fn($key, $value) => str_ends_with($key, $suffix));
    }

    /**
     * Clear the entire bundle.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$things = [];
    }

    /**
     * Get a stored value and assert it is an instance of the given class.
     *
     * @param string $className Fully qualified class name to assert
     * @param string $key Key of the thing in the bundle
     * @return mixed
     * @throws RuntimeException if the value is missing or of the wrong type
     */
    public static function as(string $className, string $key): mixed
    {
        if (!self::has($key)) {
            throw new RuntimeException("Bundle key '$key' is not set.");
        }

        $thing = self::$things[$key];

        if (!$thing instanceof $className) {
            $actual = is_object($thing) ? get_class($thing) : gettype($thing);
            throw new RuntimeException("Bundle key '$key' is not a $className (got $actual).");
        }

        return $thing;
    }

    /**
     * Return a summary of what's in the bundle: key => type/class.
     *
     * Useful for debugging, logging, or just reminding yourself
     * what kind of chaos you've stored in here.
     *
     * @return array<string, string> An associative array of key => type/class name
     */
    public static function summary(): array
    {
        return array_map(
            fn($thing) => is_object($thing) ? get_class($thing) : gettype($thing),
            self::$things
        );
    }

    /**
     * Bulk import multiple values into the bundle.
     *
     * Existing keys will be overwritten with the new values.
     *
     * @param array<string, mixed> $things Keyed values to store
     * @return void
     */
    public static function import(array $things): void
    {
        foreach ($things as $key => $value) {
            self::add($key, $value);
        }
    }

    /**
     * Add a value only if it hasn't already been added.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws RuntimeException if the key already exists
     */
    public static function addOnce(string $key, mixed $value): void
    {
        if (self::has($key)) {
            throw new RuntimeException("Bundle key '$key' is already set.");
        }

        self::add($key, $value);
    }

    /**
     * Get the type (or class) of the stored value at a key.
     *
     * @param string $key
     * @return string|null
     */
    public static function typeOf(string $key): ?string
    {
        if (!self::has($key)) return null;
        $thing = self::get($key);
        return is_object($thing) ? get_class($thing) : gettype($thing);
    }

    /**
     * Get a value or throw if it’s not there.
     *
     * @param string $key
     * @return mixed
     * @throws RuntimeException
     */
    public static function require(string $key): mixed
    {
        if (!self::has($key)) {
            throw new RuntimeException("Required bundle key '$key' is not set.");
        }
        return self::$things[$key];
    }

    /**
     * Get the number of items currently stored.
     *
     * @return int
     */
    public static function count(): int
    {
        return count(self::$things);
    }

    /**
     * Rename a stored key without changing the value.
     *
     * @param string $from
     * @param string $to
     * @return void
     */
    public static function rename(string $from, string $to): void
    {
        if (!self::has($from)) return;
        self::$things[$to] = self::$things[$from];
        unset(self::$things[$from]);
    }

    /**
     * Find all keys where the value matches the given type or class.
     *
     * @param string $type
     * @return string[]
     */
    public static function findKeysByType(string $type): array
    {
        return array_keys(
            array_filter(self::$things, function ($value) use ($type) {
                return is_object($value)
                    ? $value instanceof $type
                    : gettype($value) === $type;
            })
        );
    }

    /**
     * Drop any keys where the value is null.
     *
     * @return void
     */
    public static function removeNulls(): void
    {
        self::$things = array_filter(self::$things, fn($v) => $v !== null);
    }

    /**
     * Return an HTML-rendered diagnostic table of the bundle contents,
     * with expandable sections for each value.
     *
     * @return string
     */
    public static function debugSummary(): string
    {
        if (empty(self::$things)) {
            return '<div class="bundle-summary"><em>[Bundle is empty]</em></div>';
        }

        $style = <<<'HTML'
<style>
.bundle-summary {
    font-family: monospace;
    border-collapse: collapse;
    width: 100%;
    margin: 1em 0;
    border: 1px solid #ccc;
}
.bundle-summary th {
    background: #333;
    color: #fff;
    padding: 6px 10px;
    text-align: left;
}
.bundle-summary td {
    padding: 6px 10px;
    border: 1px solid #ccc;
    vertical-align: top;
}
.bundle-summary .type-object { color: #2a9d8f; font-weight: bold; }
.bundle-summary .type-array { color: #e76f51; font-weight: bold; }
.bundle-summary .type-string { color: #264653; }
.bundle-summary .type-boolean { color: #d62828; font-weight: bold; }
.bundle-summary .type-integer,
.bundle-summary .type-double { color: #457b9d; }
.bundle-summary .type-null { color: #aaa; font-style: italic; }
.bundle-summary details summary {
    cursor: pointer;
    font-weight: bold;
    margin-bottom: 4px;
}
.bundle-summary pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>
HTML;

        $rows = '';
        foreach (self::$things as $key => $value) {
            $type = gettype($value);
            $class = $type === 'object' ? get_class($value) : $type;
            $typeClass = 'type-' . strtolower($type);

            if (is_object($value)) {
                $typeClass = 'type-object';
                $class = get_class($value);
            } elseif (is_array($value)) {
                $typeClass = 'type-array';
                $class = 'array[' . count($value) . ']';
            }

            $encoded = htmlentities(print_r($value, true));

            $details = <<<HTML
<details>
  <summary>Click to view</summary>
  <pre>$encoded</pre>
</details>
HTML;

            $rows .= "<tr>
            <td><code>$key</code></td>
            <td class=\"$typeClass\">$class</td>
            <td>$details</td>
        </tr>";
        }

        return <<<HTML
$style
<table class="bundle-summary">
    <thead>
        <tr>
            <th>Key</th>
            <th>Type / Class</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        $rows
    </tbody>
</table>
HTML;
    }

}
