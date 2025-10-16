<?php
/**
 * File: /vendor/vernsix/primordyx/src/RandomStuff.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils/RandomStuff.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use DateTimeInterface;
use InvalidArgumentException;
use Random;
use RuntimeException;

/**
 * Random data generation utility with secure fallback mechanisms
 *
 * Provides comprehensive random data generation capabilities for testing, development,
 * and simulation purposes. Uses secure random number generation (random_int) with
 * automatic fallback to mt_rand when cryptographically secure randomness fails.
 *
 * ## Core Features
 * - **Words & Identifiers**: Human-readable identifiers using curated word lists
 * - **Strings & Data**: Customizable random strings, passwords, hex values
 * - **Numbers & Ranges**: Integers, floats with precise range control
 * - **Collections**: Array shuffling, sampling, weighted selection
 * - **Personal Data**: Names, emails, addresses for testing scenarios
 * - **Specialized Formats**: Credit cards, API keys, IP addresses, coordinates
 * - **Gaming & Fun**: Dice rolling, playing cards, Lorem Ipsum text
 *
 * ## Security Model
 * All methods attempt secure random generation first, with graceful fallback:
 * 1. Primary: Uses random_int() for cryptographically secure randomness
 * 2. Fallback: Uses mt_rand() if Random\RandomException occurs AND strict mode is disabled
 * 3. Strict Mode: When enabled globally or per-call, throws exception instead of using fallback
 *
 * ## Strict Mode
 * - Global setting via strictMode() affects all method calls
 * - Per-call override available via optional parameter on each method
 * - Default: false (backward compatible - allows mt_rand fallback)
 * - When true: Ensures cryptographic security or throws exception
 *
 * ## Word List Caching
 * Implements lazy-loaded caching for word lists to optimize performance when
 * generating multiple word-based identifiers. Cache persists for script duration.
 *
 * ## Usage Patterns
 * Static factory pattern - all methods are static and can be called directly
 * without instantiation. Designed for utility usage across application layers.
 *
 * @since 1.0.0
 *
 * @example Basic Random Data Generation
 * ```php
 * // Human-readable identifiers
 * $id = RandomStuff::words(3, '-'); // "brave-eagle-mount"
 * $session = RandomStuff::myThreeWords(); // Cached for request duration
 *
 * // Secure strings and passwords (force strict mode for security)
 * $token = RandomStuff::urlSafe(32, true);
 * $password = RandomStuff::password(16, true, true, true, true, true);
 *
 * // Test data generation (allow fallback)
 * $email = RandomStuff::email('example.com', false);
 * $name = RandomStuff::fullName('female', false);
 * $card = RandomStuff::creditCard('visa', false);
 * ```
 *
 * @example Strict Mode Usage
 * ```php
 * // Set global strict mode
 * RandomStuff::strictMode(true);
 * $secure = RandomStuff::hex(32); // Will throw if can't be secure
 *
 * // Override per-call
 * $critical = RandomStuff::hex(32, true);  // Force strict
 * $testData = RandomStuff::int(1, 100, false); // Allow fallback
 * ```
 *
 * @see Lists For word lists and data arrays used by this class
 */
class RandomStuff
{
    /**
     * Global strict mode setting for cryptographic security enforcement
     *
     * When true, all methods throw exceptions if secure random generation fails.
     * When false (default), methods fall back to mt_rand for non-critical uses.
     * Can be overridden per-method call via optional parameter.
     *
     * @var bool Default false for backward compatibility
     * @since 1.0.0
     */
    private static bool $strictMode = false;

    /**
     * Cached word list for performance optimization during multiple word generation calls
     *
     * Stores the result of Lists::bigFiveCharWords() after first access to avoid
     * repeated expensive list generation. Cache persists for the entire script
     * execution duration and is shared across all word generation methods.
     *
     * @var array<string>|null Array of five-character words, or null if not yet loaded
     * @since 1.0.0
     *
     * @see Lists::bigFiveCharWords() Source of cached word data
     * @see resetCache() Method to clear cached data for testing
     */
    private static ?array $cachedWordList = null;

    /**
     * Set or get global strict mode for cryptographic security
     *
     * Controls whether methods throw exceptions (strict) or fall back to mt_rand
     * (non-strict) when secure random generation fails. Individual method calls
     * can override this global setting.
     *
     * @param bool|null $strict True to enforce security, false to allow fallback, null to just get current
     * @return bool Current strict mode setting
     * @since 1.0.0
     *
     * @example Global Strict Mode Configuration
     * ```php
     * // Enable strict mode globally
     * RandomStuff::strictMode(true);
     *
     * // All subsequent calls must be secure
     * $token = RandomStuff::hex(32); // Throws if not secure
     *
     * // Check current mode
     * $isStrict = RandomStuff::strictMode(); // Returns true
     *
     * // Disable for non-critical operations
     * RandomStuff::strictMode(false);
     * ```
     */
    public static function strictMode(?bool $strict = null): bool
    {
        $current = self::$strictMode;
        if ($strict !== null) {
            self::$strictMode = $strict;
        }
        return $current;
    }

    /**
     * Generate a stable, human-friendly identifier for the current request
     *
     * Creates and caches a unique identifier composed of three random English words,
     * each exactly five characters long. The result remains constant for the duration
     * of the script execution and is useful for log tagging, request correlation,
     * or simplified debugging without relying on UUIDs.
     *
     * ## Caching Behavior
     * - Generated once per script execution
     * - Subsequent calls return the same cached value
     * - Uses static variable for persistence
     * - Independent of the main word list cache
     *
     * ## Use Cases
     * - Request tracking in logs
     * - Session identification in debugging
     * - Human-readable correlation IDs
     * - Simplified request monitoring
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string A unique, three-word identifier using 5-character words
     * @since 1.0.0
     *
     * @example Request Identification
     * ```php
     * $requestId = RandomStuff::myThreeWords();
     * echo $requestId; // "apple-bread-grape"
     * echo RandomStuff::myThreeWords(); // Still "apple-bread-grape"
     * ```
     *
     * @see words() For customizable word generation
     * @see cachedWordList() For word source details
     */
    public static function myThreeWords(?bool $strict = null): string
    {
        static $myThreeWords = '';
        if ($myThreeWords === '') {
            $myThreeWords = self::words(3, '-', [], $strict);
        }
        return $myThreeWords;
    }

    /**
     * Generate a string of random words joined by a specified separator
     *
     * Creates human-readable identifiers by randomly selecting words from a word list
     * and joining them with a separator. Uses secure randomness with graceful fallback
     * to mt_rand if the secure random generator fails and strict mode is disabled.
     *
     * ## Word Source Hierarchy
     * 1. Custom word list if provided via $wordList parameter
     * 2. Cached internal word list (5-character English words)
     * 3. Falls back to mt_rand selection if random_int fails and not strict
     *
     * ## Error Handling
     * If Random\RandomException occurs during secure random generation:
     * - Strict mode enabled: Throws RuntimeException
     * - Strict mode disabled: Switches to mt_rand for remaining selections
     *
     * @param int $howManyWords Number of words to include (default is 3)
     * @param string $separator Separator used to join the words (default is '-')
     * @param array<string> $wordList Optional custom word list to choose from
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Concatenated string of randomly selected words
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Word Generation Patterns
     * ```php
     * $default = RandomStuff::words(); // "apple-bread-grape"
     * $dotted = RandomStuff::words(4, '.'); // "eagle.flame.ocean.storm"
     * $custom = RandomStuff::words(2, '_', ['red', 'blue', 'green']); // "blue_red"
     * $secure = RandomStuff::words(3, '-', [], true); // Force strict mode
     * ```
     *
     * @see myThreeWords() For cached three-word identifiers
     * @see cachedWordList() For default word source
     */
    public static function words(int $howManyWords = 3, string $separator = '-', array $wordList = [], ?bool $strict = null): string
    {
        $useStrict = $strict ?? self::$strictMode;

        if (empty($wordList)) {
            $wordList = self::cachedWordList();
        }

        $maxIndex = count($wordList) - 1;
        $parts = [];

        try {
            for ($i = 0; $i < $howManyWords; $i++) {
                $parts[] = $wordList[random_int(0, $maxIndex)];
            }
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Fallback: continue with mt_rand for remaining words
            for (; $i < $howManyWords; $i++) {
                $parts[] = $wordList[mt_rand(0, $maxIndex)];
            }
        }

        return implode($separator, $parts);
    }

    /**
     * Return a random element from the provided array
     *
     * Selects a random element from an array using secure random generation with
     * fallback to array_rand if strict mode is disabled.
     *
     *  ## Array Handling
     *  - Preserves original array keys and values
     *  - Works with both indexed and associative arrays
     *  - Returns actual array values, not keys
     *  - Safe for arrays containing mixed data types
     *
     * @param array $array Array to choose from
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return mixed A random element, or null if the array is empty
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Array Element Selection
     * ```php
     * $colors = ['red', 'blue', 'green'];
     * $color = RandomStuff::getElement($colors); // "blue"
     *
     * $empty = RandomStuff::getElement([]); // null
     * ```
     *
     * @see sample() For selecting multiple random elements
     * @see weighted() For weighted random selection
     */
    public static function getElement(array $array, ?bool $strict = null): mixed
    {
        if (empty($array)) {
            return null;
        }

        $useStrict = $strict ?? self::$strictMode;
        $keys = array_keys($array);

        try {
            $key = $keys[random_int(0, count($keys) - 1)];
            return $array[$key];
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }
            return $array[array_rand($array)];
        }
    }

    /**
     * Generate a URL-safe random string suitable for web applications
     *
     * Creates random strings using only URL-safe characters: letters, numbers,
     * hyphens, and underscores. Ideal for generating tokens, identifiers, or
     * parameters that will be transmitted via URLs without encoding issues.
     *
     * ## Character Set
     * Uses: a-z, A-Z, 0-9, hyphen (-), underscore (_)
     * - No special encoding required in URLs
     * - Safe for use in query parameters
     * - Compatible with most web standards
     *
     * @param int $length Length of the string (default: 16)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string URL-safe random string
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example URL-Safe Token Generation
     * ```php
     * $token = RandomStuff::urlSafe(32); // "a7Bc_9xY-4zW8mN3"
     * $short = RandomStuff::urlSafe(8);  // "K2n_9Qw7"
     * ```
     *
     * @see string() For custom character set strings
     * @see apiKey() For alphanumeric-only strings
     */
    public static function urlSafe(int $length = 16, ?bool $strict = null): string
    {
        return self::string($length, '', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', $strict);
    }

    /**
     * Generate a random string of specified length using a custom character set
     *
     * Core string generation method that creates random strings from any specified
     * character set. Uses secure random generation with automatic fallback to
     * mt_rand if cryptographically secure randomness fails and strict mode is disabled.
     *
     * @param int $length Length of the string to generate
     * @param string $prefix A string to prepend to the output. The remaining characters will be randomly generated.
     * @param string $charset Character set to use (default: alphanumeric)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random string built from specified character set
     * @throws InvalidArgumentException If length is less than 1
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Custom String Generation
     * ```php
     * $alpha = RandomStuff::string(8, 'abcdef'); // "beadface"
     * $numeric = RandomStuff::string(6, '0123456789'); // "847392"
     * $symbols = RandomStuff::string(4, '!@#$%^&*'); // "!@^%"
     * ```
     *
     * @see urlSafe() For URL-safe character strings
     * @see hex() For hexadecimal strings
     * @see password() For password generation with mixed character types
     */
    public static function string(int $length = 8, string $prefix = '', string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', ?bool $strict = null): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('String length must be at least 1');
        }

        $useStrict = $strict ?? self::$strictMode;
        $result = $prefix;
        $maxIndex = strlen($charset) - 1;

        try {
            for ($i = 0; $i < $length; $i++) {
                $result .= $charset[random_int(0, $maxIndex)];
            }
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Continue with mt_rand for remaining characters
            for (; $i < $length; $i++) {
                $result .= $charset[mt_rand(0, $maxIndex)];
            }
        }

        return $result;
    }

    /**
     * Generate a random hexadecimal string of specified length
     *
     * Creates random hexadecimal strings using lowercase letters (a-f) and digits (0-9).
     * Commonly used for generating hash-like identifiers, tokens, or color values.
     *
     * ## Output Format
     * - Characters: 0-9, a-f (lowercase only)
     * - No prefix (no "0x" or "#")
     * - Fixed length as specified
     * - Suitable for hash simulation or color generation
     *
     * @param int $length Length of the hex string (default: 32)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random hexadecimal string using 0-9a-f
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function hex(int $length = 32, ?bool $strict = null): string
    {
        return self::string($length, '', '0123456789abcdef', $strict);
    }

    /**
     * Generate a random integer within a specified range (inclusive)
     *
     * Produces random integers within defined bounds using secure random generation
     * with automatic fallback. Both minimum and maximum values are inclusive in
     * the possible results.
     *
     * ## Range Behavior
     * - Both min and max values are included in possible results
     * - Works with negative numbers and zero
     * - Handles ranges of any size (min can equal max)
     * - Default range: 0 to 100 inclusive
     *
     * ## Security Fallback
     * Uses random_int() primarily, falls back to mt_rand() if needed.
     * Both functions handle the inclusive range correctly.
     *
     * @param int $min Minimum value (inclusive, default: 0)
     * @param int $max Maximum value (inclusive, default: 100)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return int Random integer within specified range
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Integer Range Generation
     * ```php
     * $default = RandomStuff::int(); // 0-100
     * $dice = RandomStuff::int(1, 6); // 1-6
     * $negative = RandomStuff::int(-10, 10); // -10 to 10
     * ```
     *
     * @see float() For decimal number generation
     * @see dice() For dice rolling simulation
     */
    public static function int(int $min = 0, int $max = 100, ?bool $strict = null): int
    {
        $useStrict = $strict ?? self::$strictMode;

        try {
            return random_int($min, $max);
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }
            return mt_rand($min, $max);
        }
    }

    /**
     * Generate a random float within a specified range with precision control
     *
     * Creates random floating-point numbers within defined bounds with customizable
     * decimal precision. Uses integer-based random generation internally for
     * consistency across different random sources.
     *
     * ## Precision Implementation
     * - Uses integer multiplication internally for consistent results
     * - Rounds to specified decimal places using round()
     * - Default precision: 2 decimal places
     * - Maximum precision limited by PHP float precision
     *
     * ## Range Handling
     * - Both bounds are achievable (inclusive behavior)
     * - Supports negative numbers and zero
     * - Min and max can be equal for fixed-value testing
     *
     * @param float $min Minimum value (inclusive, default: 0.0)
     * @param float $max Maximum value (inclusive, default: 1.0)
     * @param int $precision Number of decimal places (default: 2)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return float Random float within specified range and precision
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Float Generation with Precision
     * ```php
     * $percentage = RandomStuff::float(0, 100, 2); // 73.45
     * $precise = RandomStuff::float(1, 2, 6); // 1.847392
     * $coordinates = RandomStuff::float(-180, 180, 4); // -127.8394
     * ```
     *
     * @see int() For integer generation
     * @see coordinates() For geographic coordinate pairs
     */
    public static function float(float $min = 0.0, float $max = 1.0, int $precision = 2, ?bool $strict = null): float
    {
        $useStrict = $strict ?? self::$strictMode;

        try {
            $randomInt = random_int(0, getrandmax());
            $randomFloat = $randomInt / getrandmax();
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }
            $randomFloat = mt_rand() / mt_getrandmax();
        }

        $result = $min + ($randomFloat * ($max - $min));
        return round($result, $precision);
    }

    /**
     * Generate a random boolean value with optional probability weighting
     *
     * Returns true or false randomly, with optional probability control for
     * biasing results toward true values. Uses integer-based random generation
     * for consistent behavior across random sources.
     *
     * @param float $trueProbability Probability of true result (0.0-1.0, default: 0.5)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return bool Random boolean value based on specified probability
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Date Range Generation
     * ```php
     * $recent = RandomStuff::date("2023-01-01", "now");
     * $historical = RandomStuff::date("1990-01-01", "2000-12-31");
     * $future = RandomStuff::date("now", "+1 year");
     * ```
     *
     * @see time() For time-only generation
     * @see timezone() For random timezone generation
     */
    public static function bool(float $trueProbability = 0.5, ?bool $strict = null): bool
    {
        return self::float(0, 1, 4, $strict) <= $trueProbability;
    }

    /**
     * Shuffle an array using Fisher-Yates algorithm with secure randomness
     *
     * Implements the Fisher-Yates shuffle algorithm using secure random number
     * generation to provide unbiased shuffling of array elements. Preserves
     * original array keys and their associations with values.
     *
     * ## Algorithm Implementation
     * - Fisher-Yates shuffle for unbiased randomization
     * - Preserves key-value associations during shuffle
     * - Works with both indexed and associative arrays
     * - Uses secure random_int() with mt_rand() fallback
     *
     * ## Key Preservation
     * Unlike PHP's built-in shuffle(), this method maintains the relationship
     * between keys and values while randomizing their order in the array.
     *
     * @param array $array Array to shuffle (any key/value types)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return array Shuffled array with preserved key-value relationships
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Array Shuffling with Key Preservation
     * ```php
     * $data = ['a' => 1, 'b' => 2, 'c' => 3];
     * $shuffled = RandomStuff::shuffle($data);
     * // Might return: ['c' => 3, 'a' => 1, 'b' => 2]
     * ```
     *
     * @see sample() For selecting random elements without replacement
     * @see getElement() For single random element selection
     */
    public static function shuffle(array $array, ?bool $strict = null): array
    {
        $keys = array_keys($array);

        // Fisher-Yates shuffle with secure random
        for ($i = count($keys) - 1; $i > 0; $i--) {
            $j = self::int(0, $i, $strict);
            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $array[$key];
        }

        return $result;
    }

    /**
     * Select random elements from an array without replacement
     *
     * Returns a random subset of array elements using shuffling-based sampling.
     * Provides options for key preservation and handles edge cases gracefully
     * by returning the entire array when sample size exceeds array size.
     *
     * ## Sampling Behavior
     * - Without replacement: Each element appears at most once
     * - Random order in result unless preserveKeys is true
     * - Returns entire array if count >= array size
     * - Empty array returns empty array regardless of count
     *
     * ## Key Handling Options
     * - preserveKeys=false: Returns indexed array [0,1,2...]
     * - preserveKeys=true: Maintains original array keys
     * - Default behavior removes original keys for cleaner usage
     *
     * @param array $array Array to sample from
     * @param int $count Number of elements to select (default: 1)
     * @param bool $preserveKeys Whether to preserve original array keys (default: false)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return array Random sample from input array
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Random Sampling Patterns
     * ```php
     * $colors = ['red', 'blue', 'green', 'yellow'];
     * $three = RandomStuff::sample($colors, 3); // ['blue', 'red', 'yellow']
     *
     * $assoc = ['a' => 1, 'b' => 2, 'c' => 3];
     * $preserved = RandomStuff::sample($assoc, 2, true); // ['c' => 3, 'a' => 1]
     * ```
     *
     * @see shuffle() For complete array randomization
     * @see weighted() For probability-based selection
     */
    public static function sample(array $array, int $count = 1, bool $preserveKeys = false, ?bool $strict = null): array
    {
        if ($count >= count($array)) {
            return $preserveKeys ? $array : array_values($array);
        }

        $shuffled = self::shuffle($array, $strict);
        $sample = array_slice($shuffled, 0, $count, $preserveKeys);

        return $preserveKeys ? $sample : array_values($sample);
    }

    /**
     * Weighted random selection from an associative array
     *
     * Selects a random key from the input array based on associated numeric weights.
     * Higher weight values increase selection probability proportionally. Handles
     * edge cases gracefully and provides predictable fallback behavior.
     *
     * ## Weight Distribution
     * - Weights are relative, not absolute percentages
     * - Higher weights = higher probability of selection
     * - Example: [A=>70, B=>20, C=>10] gives A 70% chance, B 20%, C 10%
     * - Zero weights are valid and result in zero selection probability
     *
     * ## Selection Algorithm
     * 1. Calculate total weight sum
     * 2. Generate random float from 0 to total weight
     * 3. Walk through weights until random value is reached
     * 4. Return associated key for selected weight range
     *
     * ## Edge Case Handling
     * - Empty array: Returns null
     * - All zero weights: Returns last key as fallback
     * - Negative weights: Treated as zero (not recommended)
     *
     * @param array<string, numeric> $weights Associative array of value => weight pairs
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string|int|null Selected key from weights array, or null if empty
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Weighted Selection Scenarios
     * ```php
     * $rarities = ['common' => 70, 'rare' => 20, 'epic' => 10];
     * $result = RandomStuff::weighted($rarities); // "common" (70% chance)
     *
     * $responses = ['yes' => 0.8, 'no' => 0.2];
     * $answer = RandomStuff::weighted($responses); // Weighted by decimals
     * ```
     *
     * @see sample() For unweighted random selection
     * @see getElement() For uniform random element selection
     */
    public static function weighted(array $weights, ?bool $strict = null): string|int|null
    {
        $totalWeight = array_sum($weights);
        $random = self::float(0, $totalWeight, 6, $strict);

        $currentWeight = 0;
        foreach ($weights as $value => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $value;
            }
        }

        // Fallback to last element
        return array_key_last($weights);
    }

    /**
     * Generate a random password with customizable character set requirements
     *
     * Creates secure passwords by combining different character types based on
     * specified criteria. Ensures at least one character from each enabled type
     * appears in the final password for security compliance.
     *
     * ## Character Type Control
     * - Lowercase letters: a-z (includeLowercase)
     * - Uppercase letters: A-Z (includeUppercase)
     * - Numbers: 0-9 (includeNumbers)
     * - Symbols: !@#$%^&*()_+-=[]{}|;:,.<>? (includeSymbols)
     *
     * ## Security Guarantees
     * - At least one character from each enabled type
     * - Remaining positions filled randomly from combined character set
     * - Uses secure random generation with fallback
     * - No predictable patterns in character placement
     *
     * ## Parameter Validation
     * Throws InvalidArgumentException if all character types are disabled,
     * preventing generation of empty passwords.
     *
     * @param int $length Password length (default: 12)
     * @param bool $includeSymbols Include special characters (default: true)
     * @param bool $includeNumbers Include numbers (default: true)
     * @param bool $includeUppercase Include uppercase letters (default: true)
     * @param bool $includeLowercase Include lowercase letters (default: true)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random password meeting specified criteria
     * @throws InvalidArgumentException If all character types are disabled
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Password Generation Patterns
     * ```php
     * $secure = RandomStuff::password(16); // All character types
     * $alphanumeric = RandomStuff::password(12, false); // No symbols
     * $numeric = RandomStuff::password(8, false, true, false, false); // Numbers only
     * ```
     *
     * @see string() For basic string generation
     * @see urlSafe() For URL-safe token generation
     */
    public static function password(
        int $length = 12,
        bool $includeSymbols = true,
        bool $includeNumbers = true,
        bool $includeUppercase = true,
        bool $includeLowercase = true,
        ?bool $strict = null
    ): string {
        $charset = '';

        if ($includeLowercase) $charset .= 'abcdefghijklmnopqrstuvwxyz';
        if ($includeUppercase) $charset .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($includeNumbers) $charset .= '0123456789';
        if ($includeSymbols) $charset .= '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (empty($charset)) {
            throw new InvalidArgumentException('At least one character type must be included');
        }

        return self::string($length, '', $charset, $strict);
    }

    /**
     * Generate a random API key using alphanumeric characters
     *
     * Creates random strings suitable for API key simulation, token generation,
     * or identifier creation in development environments. Uses only alphanumeric
     * characters for broad compatibility across systems and protocols.
     *
     * ## Character Set
     * - Letters: a-z, A-Z (case-sensitive)
     * - Numbers: 0-9
     * - No special characters or symbols
     * - Safe for URL transmission and database storage
     *
     * ## Use Cases
     * - API key simulation for testing
     * - Development environment tokens
     * - Database record identifiers
     * - Configuration placeholders
     *
     * @param int $length Length of the API key (default: 32)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random alphanumeric API key
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Dice Rolling Scenarios
     * ```php
     * $d6 = RandomStuff::dice(); // [4] (single 6-sided die)
     * $two_d6 = RandomStuff::dice(6, 2); // [3, 5] (two dice)
     * $d20 = RandomStuff::dice(20); // [17] (20-sided die)
     * $total = array_sum(RandomStuff::dice(6, 3)); // Sum of three dice
     * ```
     *
     * @see int() For single random integer generation
     */
    public static function apiKey(int $length = 32, ?bool $strict = null): string
    {
        return self::string($length, '', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $strict);
    }

    /**
     * Generate a random email address for testing purposes
     *
     * @param string $domain Optional domain name (default: random from list)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random email address
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Playing Card Generation
     * ```php
     * $card = RandomStuff::playingCard(); // "A♠", "10♥", "K♦", "7♣"
     *
     * // Deal a hand of cards
     * $hand = [];
     * for ($i = 0; $i < 5; $i++) {
     *     $hand[] = RandomStuff::playingCard();
     * }
     * ```
     *
     * @see sample() For dealing multiple unique cards
     */
    public static function email(string $domain = '', ?bool $strict = null): string
    {
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'example.com', 'test.com'];
        $username = self::words(2, '.', [], $strict);

        if (empty($domain)) {
            $domain = self::getElement($domains, $strict);
        }

        return strtolower($username . '@' . $domain);
    }

    /**
     * Generate a random first name with optional gender specification
     *
     * Returns random first names from curated lists based on gender preference.
     * Supports male, female, unisex, and combined name pools for diverse
     * testing scenarios and demographic simulation.
     *
     * ## Name Categories
     * - Male: Traditional masculine names
     * - Female: Traditional feminine names
     * - Unisex: Names commonly used for any gender
     * - Any: Combined pool of all three categories
     *
     * ## Gender Selection Logic
     * - 'male': Returns only from male name list
     * - 'female': Returns only from female name list
     * - 'unisex': Returns only from unisex name list
     * - 'any' or other: Returns from combined male+female+unisex lists
     *
     * @param string $gender Gender preference: 'male', 'female', 'unisex', or 'any' (default: 'any')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random first name matching gender criteria
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example First Name Generation by Gender
     * ```php
     * $anyName = RandomStuff::firstName(); // From all lists
     * $maleName = RandomStuff::firstName('male'); // "James", "Michael"
     * $femaleName = RandomStuff::firstName('female'); // "Sarah", "Emma"
     * $unisexName = RandomStuff::firstName('unisex'); // "Jordan", "Taylor"
     *
     * // Force secure generation for sensitive data
     * $secureName = RandomStuff::firstName('any', true);
     * ```
     *
     * @see Lists::maleFirstNames() For male name source
     * @see Lists::femaleFirstNames() For female name source
     * @see Lists::unisexFirstNames() For unisex name source
     * @see lastName() For surname generation
     * @see fullName() For complete name generation
     */
    public static function firstName(string $gender = 'any', ?bool $strict = null): string
    {
        $male = Lists::maleFirstNames();
        $female = Lists::femaleFirstNames();
        $unisex = Lists::unisexFirstNames();

        return match($gender) {
            'male' => self::getElement($male, $strict),
            'female' => self::getElement($female, $strict),
            'unisex' => self::getElement($unisex, $strict),
            default => self::getElement(array_merge($male, $female, $unisex), $strict)
        };
    }

    /**
     * Generate a random surname from curated list
     *
     * Returns random last names from a comprehensive surname database suitable
     * for testing user profiles, contact lists, or demographic simulations.
     * Names are culturally diverse and commonly used in English-speaking regions.
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random surname/last name
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Surname Generation
     * ```php
     * $surname = RandomStuff::lastName(); // "Smith", "Johnson", "Williams"
     *
     * // Force secure generation for critical operations
     * $secureSurname = RandomStuff::lastName(true);
     * ```
     *
     * @see Lists::lastNames() For surname data source
     * @see firstName() For given name generation
     * @see fullName() For complete name pairs
     */
    public static function lastName(?bool $strict = null): string
    {
        $names = Lists::lastNames();
        return self::getElement($names, $strict);
    }

    /**
     * Generate a random complete name with optional gender specification
     *
     * Combines random first and last name generation to create realistic full names
     * for testing user accounts, contact databases, or form validation scenarios.
     * Leverages existing first name gender logic while using universal surname list.
     *
     * ## Name Construction
     * - First name: Selected based on gender parameter
     * - Last name: Random selection from surname database
     * - Format: "FirstName LastName" with single space separator
     * - No middle names or suffixes included
     *
     * @param string $gender Gender preference for first name: 'male', 'female', 'unisex', or 'any' (default: 'any')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random complete name in "First Last" format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Complete Name Generation
     * ```php
     * $anyName = RandomStuff::fullName(); // "Emma Johnson"
     * $maleName = RandomStuff::fullName('male'); // "Michael Smith"
     * $femaleName = RandomStuff::fullName('female'); // "Sarah Williams"
     *
     * // Force secure generation for user registration
     * $secureUser = RandomStuff::fullName('any', true);
     * ```
     *
     * @see firstName() For first name component generation
     * @see lastName() For surname component generation
     */
    public static function fullName(string $gender = 'any', ?bool $strict = null): string
    {
        return self::firstName($gender, $strict) . ' ' . self::lastName($strict);
    }

    /**
     * Generate a random US phone number for testing
     *
     * Creates realistic US phone numbers in standard format (XXX) XXX-XXXX.
     * Numbers follow North American Numbering Plan rules with valid area codes
     * and exchange prefixes. For testing purposes only - not real phone numbers.
     *
     * ## Number Format
     * - Area code: 200-999 (excludes reserved ranges)
     * - Exchange: 200-999 (excludes special prefixes)
     * - Subscriber: 0000-9999
     * - Format: "(XXX) XXX-XXXX" with proper formatting
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random US phone number in (XXX) XXX-XXXX format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Phone Number Generation
     * ```php
     * $phone = RandomStuff::phoneNumber(); // "(555) 867-5309"
     *
     * // Force secure for sensitive test data
     * $securePhone = RandomStuff::phoneNumber(true);
     * ```
     */
    public static function phoneNumber(?bool $strict = null): string
    {
        $areaCode = self::int(200, 999, $strict);
        $exchange = self::int(200, 999, $strict);
        $number = self::int(0, 9999, $strict);
        return sprintf('(%03d) %03d-%04d', $areaCode, $exchange, $number);
    }

    /**
     * Generate a random US state name or abbreviation
     *
     * Returns US state names or their two-letter postal abbreviations.
     * Supports both full state names and standard USPS abbreviations
     * based on parameter selection.
     *
     * ## Output Formats
     * - Full names: "California", "Texas", "New York"
     * - Abbreviations: "CA", "TX", "NY"
     * - Default: Returns full state names
     * - Abbreviation flag: Set true for postal codes
     *
     * ## State Coverage
     * Includes all 50 US states from Lists::states() data source.
     * Uses official state names and USPS-approved abbreviations.
     *
     * @param bool $abbreviated Return postal abbreviation instead of full name (default: false)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random US state name or abbreviation
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example US State Generation
     * ```php
     * $fullName = RandomStuff::state(); // "California"
     * $abbrev = RandomStuff::state(true); // "CA"
     *
     * // Force secure generation
     * $secureState = RandomStuff::state(false, true);
     * ```
     *
     * @see Lists::states() For state data source
     * @see zipCode() For ZIP code generation
     * @see city() For city name generation
     */
    public static function state(bool $abbreviated = false, ?bool $strict = null): string
    {
        $states = Lists::states();
        if ($abbreviated) {
            return self::getElement(array_keys($states), $strict);
        }
        return self::getElement(array_values($states), $strict);
    }

    /**
     * Generate a random ZIP code
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random 5-digit ZIP code
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function zipCode(?bool $strict = null): string
    {
        return str_pad((string)self::int(1, 99999, $strict), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate random geographic coordinates
     *
     * Creates latitude/longitude coordinate pairs for testing mapping applications,
     * location services, and geographic data processing. Supports custom boundary
     * specification for regional testing or uses global bounds as default.
     * Returns coordinates with appropriate decimal precision.
     *
     * ## Coordinate Ranges
     * - Latitude: -90 to 90 degrees (South to North)
     * - Longitude: -180 to 180 degrees (West to East)
     * - Default: Global coverage (entire Earth)
     * - Custom: Specify regional boundaries via bounds array
     *
     * ## Boundary Specification
     * Optional bounds array accepts keys:
     * - 'min_lat': Minimum latitude
     * - 'max_lat': Maximum latitude
     * - 'min_lng': Minimum longitude
     * - 'max_lng': Maximum longitude
     *
     * ## Output Format
     * Returns associative array with 'latitude' and 'longitude' keys,
     * each containing floating-point values with 6 decimal places precision.
     *
     * @param array<string, float> $bounds Optional boundary constraints
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return array{latitude: float, longitude: float} Random coordinate pair
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Geographic Coordinate Generation
     * ```php
     * $global = RandomStuff::coordinates();
     * // ['latitude' => 23.456789, 'longitude' => -87.654321]
     *
     * $usa = RandomStuff::coordinates([
     *     'min_lat' => 24.0, 'max_lat' => 49.0,
     *     'min_lng' => -125.0, 'max_lng' => -66.0
     * ]);
     *
     * // Force secure generation for location services
     * $secureCoords = RandomStuff::coordinates([], true);
     * ```
     *
     * @see float() For precision decimal generation
     */
    public static function coordinates(array $bounds = [], ?bool $strict = null): array
    {
        $minLat = $bounds['min_lat'] ?? -90;
        $maxLat = $bounds['max_lat'] ?? 90;
        $minLng = $bounds['min_lng'] ?? -180;
        $maxLng = $bounds['max_lng'] ?? 180;

        return [
            'latitude' => self::float($minLat, $maxLat, 6, $strict),
            'longitude' => self::float($minLng, $maxLng, 6, $strict)
        ];
    }

    /**
     * Generate a random timezone
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random timezone identifier
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function timezone(?bool $strict = null): string
    {
        $timezones = Lists::phpTimeZones();
        return self::getElement($timezones, $strict);
    }

    /**
     * Generate a random credit card number for testing purposes
     *
     * Creates realistic-looking credit card numbers that follow proper formatting
     * patterns for major card types. Numbers are for testing only and are not
     * valid for actual transactions or financial processing.
     *
     * ## Card Type Patterns
     * - Visa: Starts with 4, 16 digits
     * - Mastercard: Starts with 5, 16 digits
     * - Amex: Starts with 3, 16 digits (simplified)
     * - Discover: Starts with 6, 16 digits
     *
     * ## Security Warning
     * These numbers are NOT valid credit cards and cannot be used for
     * real transactions. They are purely for testing form validation
     * and payment processing workflows.
     *
     * @param string $type Card type: 'visa', 'mastercard', 'amex', 'discover' (default: 'visa')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random credit card number (NOT VALID FOR REAL USE)
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example Credit Card Number Generation
     * ```php
     * $visa = RandomStuff::creditCard('visa'); // "4532015112830366"
     * $mastercard = RandomStuff::creditCard('mastercard'); // "5425233430109903"
     *
     * // Force secure generation for sensitive testing
     * $secureCard = RandomStuff::creditCard('visa', true);
     * ```
     *
     * @see creditCardExpiration() For expiration date generation
     * @see creditCardCVV() For CVV code generation
     */
    public static function creditCard(string $type = 'visa', ?bool $strict = null): string
    {
        $patterns = [
            'visa' => '4###############',
            'mastercard' => '5###############',
            'amex' => '3###-######-#####'
        ];

        $pattern = $patterns[$type] ?? $patterns['visa'];
        return preg_replace_callback('/#/', fn() => (string)self::int(0, 9, $strict), $pattern);
    }

    /**
     * Generate a random MAC address in standard colon-separated format
     *
     * Creates random Media Access Control (MAC) addresses for network testing,
     * device simulation, or configuration scenarios. Uses standard IEEE 802
     * formatting with lowercase hexadecimal notation.
     *
     * ## MAC Address Format
     * - Pattern: xx:xx:xx:xx:xx:xx
     * - Each segment: Two lowercase hexadecimal digits
     * - Separators: Colons between each byte pair
     * - Example: "a1:b2:c3:d4:e5:f6"
     *
     * ## Address Space
     * - Full 48-bit address space (6 bytes)
     * - Each byte: 0x00 to 0xFF (0-255 decimal)
     * - No vendor-specific prefix enforcement
     * - Purely random generation for testing purposes
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random MAC address in colon-separated hex format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example MAC Address Generation
     * ```php
     * $mac = RandomStuff::macAddress(); // "a1:b2:c3:d4:e5:f6"
     *
     * // Force secure generation for network testing
     * $secureMac = RandomStuff::macAddress(true);
     * ```
     *
     * @see hex() For hexadecimal string generation
     * @see ip() For IP address generation
     */
    public static function macAddress(?bool $strict = null): string
    {
        $bytes = [];
        for ($i = 0; $i < 6; $i++) {
            $bytes[] = sprintf('%02x', self::int(0, 255, $strict));
        }
        return implode(':', $bytes);
    }

    /**
     * Generate a random IP address in IPv4 or IPv6 format
     *
     * Creates random IP addresses for testing network applications, configuration
     * validation, or simulation scenarios. Supports both IPv4 and IPv6 formats
     * with appropriate range restrictions for realistic addresses.
     *
     * ## IPv4 Format
     * - Pattern: XXX.XXX.XXX.XXX
     * - Each octet: 0-255
     * - Example: "192.168.1.1"
     *
     * ## IPv6 Format
     * - Pattern: XXXX:XXXX:XXXX:XXXX:XXXX:XXXX:XXXX:XXXX
     * - Each segment: 0000-FFFF (hexadecimal)
     * - Example: "2001:0db8:85a3:0000:0000:8a2e:0370:7334"
     *
     * @param string $type IP version type: 'ipv4' or 'ipv6' (default: 'ipv4')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random IP address in requested format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     *
     * @example IP Address Generation
     * ```php
     * $ipv4 = RandomStuff::ip(); // "192.168.1.42"
     * $ipv6 = RandomStuff::ip(true); // "2001:db8:85a3::8a2e:370:7334"
     *
     * // Force secure generation for network config
     * $secureIp = RandomStuff::ip(false, true);
     * ```
     *
     * @see macAddress() For MAC address generation
     */
    public static function ip(string $type = 'ipv4', ?bool $strict = null): string
    {

        switch ($type) {
            case 'ipv4':
                return implode('.', [
                    self::int(1, 254, $strict),
                    self::int(0, 255, $strict),
                    self::int(0, 255, $strict),
                    self::int(1, 254, $strict)
                ]);

            case 'ipv6':
                $parts = [];
                for ($i = 0; $i < 8; $i++) {
                    $parts[] = self::hex(4,$strict);
                }
                return implode(':', $parts);

            default:
                throw new InvalidArgumentException("Unsupported IP type: $type");
        }

    }

    /**
     * Generate a random date within a range
     *
     *  Creates random DateTimeImmutable objects between two boundary dates.
     *  Accepts various input formats and provides consistent date range generation
     *  for testing temporal data scenarios.
     *
     *  ## Input Format Flexibility
     *  - DateTimeInterface objects (DateTime, DateTimeImmutable)
     *  - String dates in any format parseable by DateTime constructor
     *  - Relative formats: "now", "+1 week", "2023-01-01", etc.
     *
     *  ## Output Consistency
     *  - Always returns DateTimeImmutable for immutability
     *  - Preserves timezone information from input dates
     *  - Random time component included (not just date)
     *
     * @param mixed $start Start date (strtotime compatible or DateTime)
     * @param mixed $end End date (strtotime compatible or DateTime)
     * @param string $format Date format (default: 'Y-m-d')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random date formatted according to specified pattern
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function date(
        string|DateTimeInterface $start = '-1 year',
        string|DateTimeInterface $end = 'now',
        string $format = 'Y-m-d',
        ?bool $strict = null): string
    {
        $startTimestamp = is_string($start) ? strtotime($start) : $start->getTimestamp();
        $endTimestamp = is_string($end) ? strtotime($end) : $end->getTimestamp();

        $randomTimestamp = self::int($startTimestamp, $endTimestamp, $strict);
        return date($format, $randomTimestamp);
    }

    /**
     * Generate a random time
     *
     * @param string $format Time format (default: 'H:i:s')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random time formatted according to specified pattern
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function time(string $format = 'H:i:s', ?bool $strict = null): string
    {
        $timestamp = self::int(0, 86399, $strict); // seconds in a day
        return gmdate($format, $timestamp);
    }


    /**
     * Roll dice
     *
     * @param int $sides Number of sides on each die
     * @param int $count Number of dice to roll
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return array Array of dice results
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function dice(int $sides = 6, int $count = 1, ?bool $strict = null): array
    {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = self::int(1, $sides, $strict);
        }
        return $results;
    }

    /**
     * Generate a random playing card
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Playing card (e.g., "A♠", "10♥", "K♦")
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function playingCard(?bool $strict = null): string
    {
        $suits = ['♠', '♥', '♦', '♣'];
        $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

        return self::getElement($values, $strict) . self::getElement($suits, $strict);
    }

    /**
     * Generate Lorem Ipsum text
     *
     * @param int $words Number of words to generate
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Lorem Ipsum text
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.0
     */
    public static function lorem(int $words = 10, ?bool $strict = null): string
    {
        $loremWords = Lists::loremIpsum();

        $result = [];
        for ($i = 0; $i < $words; $i++) {
            $result[] = self::getElement($loremWords, $strict);
        }

        return ucfirst(implode(' ', $result)) . '.';
    }

    /**
     * Retrieve cached word list for word generation methods
     *
     * Lazy-loads and caches the word list from Lists::bigFiveCharWords() on first
     * access. Subsequent calls return the cached array for performance optimization.
     * Cache persists for the entire script execution duration.
     *
     * @return array<string> Array of five-character English words
     * @since 1.0.0
     *
     * @example Word List Access
     * ```php
     * $words = RandomStuff::cachedWordList();
     * $randomWord = $words[array_rand($words)];
     * ```
     *
     * @see Lists::bigFiveCharWords() For word list source
     * @see words() For word generation using this cache
     * @see resetCache() For clearing cached data
     */
    protected static function cachedWordList(): array
    {
        if (self::$cachedWordList === null) {
            self::$cachedWordList = Lists::bigFiveCharWords();
        }
        return self::$cachedWordList;
    }

    /**
     * Reset internal caches for testing purposes
     *
     * Clears the cached word list to force regeneration on next access.
     * Primarily intended for unit testing to ensure clean state between tests.
     *
     * @return void
     * @since 1.0.0
     *
     * @example Cache Reset for Testing
     * ```php
     * // In test setup or teardown
     * RandomStuff::resetCache();
     *
     * // Verify cache is cleared
     * $this->assertNull(RandomStuff::$cachedWordList);
     * ```
     *
     * @see cachedWordList() For cache access
     */
    public static function resetCache(): void
    {
        self::$cachedWordList = null;
    }





    /**
     * Generate raw random bytes
     *
     * Returns raw binary random bytes. Useful for cryptographic operations
     * that need binary data rather than hex or base64 encoded strings.
     *
     * @param int $length Number of random bytes to generate
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Raw binary random bytes
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example Raw Bytes Generation
     * ```php
     * $iv = RandomStuff::raw(16, true);  // 16 bytes for AES IV
     * $salt = RandomStuff::raw(32, true); // 32 bytes for salt
     * ```
     */
    public static function raw(int $length, ?bool $strict = null): string
    {
        $useStrict = $strict ?? self::$strictMode;

        try {
            return random_bytes($length);
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Fallback to less secure method
            $result = '';
            for ($i = 0; $i < $length; $i++) {
                $result .= chr(mt_rand(0, 255));
            }
            return $result;
        }
    }

    /**
     * Generate a standard session ID
     *
     * Creates a 64-character hexadecimal string suitable for session identifiers,
     * matching the format used by Safe::generateSafeId().
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 64-character hexadecimal session ID
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function sessionId(?bool $strict = null): string
    {
        return self::hex(32, $strict);
    }

    /**
     * Generate a CSRF token
     *
     * Creates a 64-character hexadecimal string suitable for CSRF tokens,
     * matching the format used by CsrfManager.
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 64-character hexadecimal CSRF token
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function csrfToken(?bool $strict = null): string
    {
        return self::hex(32, $strict);
    }

    /**
     * Generate a UUID v4 (random UUID)
     *
     * Creates a RFC 4122 compliant UUID version 4 using random bytes.
     * Imported from Strings class for centralized random generation.
     *
     * @param bool $uppercase If true (default), returns UUID in uppercase
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 36-character UUID v4 string
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function uuid(bool $uppercase = true, ?bool $strict = null): string
    {
        $useStrict = $strict ?? self::$strictMode;

        try {
            $data = random_bytes(16);
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Fallback to less secure method
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Set version to 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Set variant to 10

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $uppercase ? strtoupper($uuid) : strtolower($uuid);
    }

    /**
     * Generate a UUID v7 (time-ordered + random)
     *
     * Creates a UUID version 7 with timestamp and random components.
     * Imported from Strings class for centralized random generation.
     *
     * @param bool $uppercase If true (default), returns UUID in uppercase
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 36-character UUID v7 string
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function uuidv7(bool $uppercase = true, ?bool $strict = null): string
    {
        $useStrict = $strict ?? self::$strictMode;

        $timestamp = (int) (microtime(true) * 1000); // Unix time in milliseconds
        $timeHex = str_pad(dechex($timestamp), 12, '0', STR_PAD_LEFT);

        try {
            $randBytes = random_bytes(10);
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Fallback
            $randBytes = '';
            for ($i = 0; $i < 10; $i++) {
                $randBytes .= chr(mt_rand(0, 255));
            }
        }

        $randHex = bin2hex($randBytes);

        // Insert version (7) into bits 48-51
        $timeHex[12] = '7';

        // Insert variant (10xx) into bits 64-65 (first nibble of 9th byte)
        $randHex[0] = dechex((hexdec($randHex[0]) & 0x3) | 0x8);

        $uuid = vsprintf('%s-%s-%s-%s-%s', [
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            substr($randHex, 0, 4),
            substr($randHex, 4, 4),
            substr($randHex, 8, 12),
        ]);

        return $uppercase ? strtoupper($uuid) : strtolower($uuid);
    }

    /**
     * Generate a ULID (Universally Unique Lexicographically Sortable Identifier)
     *
     * Creates a 26-character Base32-encoded string with timestamp and random components.
     * Imported from Strings class for centralized random generation.
     *
     * @param bool $uppercase If true (default), returns ULID in uppercase
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 26-character ULID string
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function ulid(bool $uppercase = true, ?bool $strict = null): string
    {
        $useStrict = $strict ?? self::$strictMode;

        // Get timestamp in milliseconds (48 bits)
        $time = (int)(microtime(true) * 1000);
        $timeBytes = pack('J', $time); // 8 bytes (we only need the last 6)

        try {
            $random = random_bytes(10);
        } catch (Random\RandomException $e) {
            if ($useStrict) {
                throw new RuntimeException(
                    'Secure random generation failed and strict mode is enabled', 0, $e
                );
            }

            // Fallback
            $random = '';
            for ($i = 0; $i < 10; $i++) {
                $random .= chr(mt_rand(0, 255));
            }
        }

        // Combine: 6 bytes from timestamp + 10 bytes random = 16 bytes (128 bits)
        $binary = substr($timeBytes, 2) . $random;

        // Crockford Base32 encoding (no 0, O, I, L)
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        $ulid = '';
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        for ($i = 0; $i < 26; $i++) {
            $chunk = substr($bits, $i * 5, 5);
            $index = bindec($chunk);
            $ulid .= $alphabet[$index];
        }

        return $uppercase ? $ulid : strtolower($ulid);
    }

    /**
     * Generate a random username for testing
     *
     * Creates usernames using various common patterns found on social platforms
     * and web applications. Combines words, numbers, and separators for realistic
     * username generation suitable for testing registration systems.
     *
     * ## Username Patterns
     * - Two words with underscore: "brave_eagle"
     * - Two words with dot: "ocean.storm"
     * - Single word with number: "falcon782"
     * - First name with number: "sarah42"
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random username suitable for testing
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example Username Generation
     * ```php
     * $user1 = RandomStuff::username(); // "red_mountain"
     * $user2 = RandomStuff::username(); // "john85"
     *
     * // Force secure for actual user generation
     * $secureUsername = RandomStuff::username(true);
     * ```
     *
     * @see words() For word-based username components
     * @see firstName() For name-based usernames
     */
    public static function username(?bool $strict = null): string
    {
        $formats = [
            fn() => self::words(2, '_', [], $strict),
            fn() => self::words(2, '.', [], $strict),
            fn() => self::words(1, '', [], $strict) . self::int(100, 999, $strict),
            fn() => strtolower(self::firstName('any', $strict)) . self::int(10, 99, $strict)
        ];

        $format = self::getElement($formats, $strict);
        return $format();
    }

    /**
     * Generate a random social security number for testing
     *
     * Creates SSN-formatted strings for testing validation and form handling.
     * Follows SSN formatting rules but generates random, non-valid numbers.
     * FOR TESTING ONLY - these are not real SSNs and should never be used
     * for actual identification purposes.
     *
     * ## SSN Rules Applied
     * - Area: 001-899 (excludes 000, 900-999)
     * - Group: 01-99 (excludes 00)
     * - Serial: 0001-9999 (excludes 0000)
     * - Avoids 666 in area number
     * - Format: XXX-XX-XXXX with hyphens
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random SSN format ###-##-#### (NOT VALID FOR REAL USE)
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example SSN Generation for Testing
     * ```php
     * $ssn = RandomStuff::ssn(); // "123-45-6789"
     *
     * // Force secure for sensitive test scenarios
     * $secureSsn = RandomStuff::ssn(true);
     * ```
     */
    public static function ssn(?bool $strict = null): string
    {
        $area = self::int(1, 899, $strict);
        if ($area == 666) $area = 665; // Skip 666
        $group = self::int(1, 99, $strict);
        $serial = self::int(1, 9999, $strict);
        return sprintf('%03d-%02d-%04d', $area, $group, $serial);
    }

    /**
     * Generate a random street address
     *
     * Creates realistic street addresses using common US street naming patterns.
     * Combines house numbers with typical street names and suffixes for
     * testing address validation, form handling, and mapping applications.
     *
     * ## Address Components
     * - House number: 1-9999
     * - Street names: Common US street names
     * - Suffixes: St, Ave, Dr, Rd, Ln, Blvd
     * - Format: "#### Street Name"
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random street address
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example Street Address Generation
     * ```php
     * $address = RandomStuff::streetAddress(); // "1234 Main St"
     * $address2 = RandomStuff::streetAddress(); // "5678 Oak Ave"
     *
     * // Force secure for sensitive data
     * $secureAddress = RandomStuff::streetAddress(true);
     * ```
     *
     * @see city() For city name generation
     * @see state() For state generation
     * @see zipCode() For ZIP code generation
     */
    public static function streetAddress(?bool $strict = null): string
    {
        $number = self::int(1, 9999, $strict);
        $streets = ['Main St', 'Oak Ave', 'Maple Dr', 'Park Rd', 'Cedar Ln', 'Pine St', 'Elm Ave', 'First St', 'Second Ave', 'Washington Blvd'];
        $street = self::getElement($streets, $strict);
        return "$number $street";
    }

    /**
     * Generate a random city name
     *
     * Returns random city names from a list of major US cities. Useful for
     * testing location-based features, address forms, and geographic data
     * handling in applications.
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random city name
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example City Name Generation
     * ```php
     * $city = RandomStuff::city(); // "Los Angeles"
     *
     * // Force secure generation
     * $secureCity = RandomStuff::city(true);
     * ```
     *
     * @see state() For state generation
     * @see zipCode() For ZIP code generation
     * @see streetAddress() For street address generation
     */
    public static function city(?bool $strict = null): string
    {
        $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
        return self::getElement($cities, $strict);
    }

    /**
     * Generate a random country name
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random country name
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function country(?bool $strict = null): string
    {
        $countries = ['United States', 'Canada', 'United Kingdom', 'Australia', 'Germany', 'France', 'Japan', 'Italy', 'Spain', 'Mexico'];
        return self::getElement($countries, $strict);
    }

    /**
     * Generate a random credit card expiration date
     *
     * Creates realistic expiration dates for testing payment forms and
     * validation systems. Generates dates from current year up to 5 years
     * in the future, formatted as MM/YY.
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Expiration in MM/YY format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example Expiration Date Generation
     * ```php
     * $expiry = RandomStuff::creditCardExpiration(); // "08/26"
     *
     * // Force secure generation
     * $secureExpiry = RandomStuff::creditCardExpiration(true);
     * ```
     *
     * @see creditCard() For card number generation
     * @see creditCardCVV() For CVV code generation
     */
    public static function creditCardExpiration(?bool $strict = null): string
    {
        $month = self::int(1, 12, $strict);
        $year = self::int((int)date('y'), (int)date('y') + 5, $strict);
        return sprintf('%02d/%02d', $month, $year);
    }

    /**
     * Generate a random CVV security code
     *
     * Creates card verification value (CVV) codes for testing payment forms.
     * Supports both 3-digit (Visa/MC/Discover) and 4-digit (Amex) formats.
     *
     * @param int $length CVV length: 3 for most cards, 4 for Amex (default: 3)
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random CVV with appropriate zero-padding
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example CVV Generation
     * ```php
     * $cvv = RandomStuff::creditCardCVV(); // "123"
     * $amexCvv = RandomStuff::creditCardCVV(4); // "1234"
     *
     * // Force secure generation
     * $secureCvv = RandomStuff::creditCardCVV(3, true);
     * ```
     *
     * @see creditCard() For card number generation
     * @see creditCardExpiration() For expiration date generation
     */
    public static function creditCardCVV(int $length = 3, ?bool $strict = null): string
    {
        $max = pow(10, $length) - 1;
        return str_pad((string)self::int(0, $max, $strict), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random user agent string
     *
     * Returns realistic user agent strings from common browsers for testing
     * web applications, analytics systems, and browser detection logic.
     * Includes modern versions of major browsers on different platforms.
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random user agent string
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     *
     * @example User Agent Generation
     * ```php
     * $ua = RandomStuff::userAgent();
     * // "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36..."
     *
     * // Force secure generation
     * $secureUa = RandomStuff::userAgent(true);
     * ```
     */
    public static function userAgent(?bool $strict = null): string
    {
        $browsers = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15'
        ];
        return self::getElement($browsers, $strict);
    }

    /**
     * Generate a random datetime within a range
     *
     * @param mixed $start Start date (strtotime compatible or DateTime)
     * @param mixed $end End date (strtotime compatible or DateTime)
     * @param string $format DateTime format (default: 'Y-m-d H:i:s')
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random datetime formatted according to specified pattern
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function dateTime(mixed $start = '-1 year', mixed $end = 'now', string $format = 'Y-m-d H:i:s', ?bool $strict = null): string
    {
        return self::date($start, $end, $format, $strict);
    }

    /**
     * Flip a coin
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string 'heads' or 'tails'
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function coinFlip(?bool $strict = null): string
    {
        return self::bool(0.5, $strict) ? 'heads' : 'tails';
    }

    /**
     * Generate a random color
     *
     * @param string $format Format: 'hex', 'rgb', 'hsl'
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random color in specified format
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function color(string $format = 'hex', ?bool $strict = null): string
    {
        return match($format) {
            'hex' => '#' . self::hex(3, $strict),
            'rgb' => sprintf('rgb(%d, %d, %d)', self::int(0, 255, $strict), self::int(0, 255, $strict), self::int(0, 255, $strict)),
            'hsl' => sprintf('hsl(%d, %d%%, %d%%)', self::int(0, 360, $strict), self::int(0, 100, $strict), self::int(0, 100, $strict)),
            default => '#' . self::hex(3, $strict)
        };
    }

    /**
     * Generate Lorem Ipsum sentences
     *
     * @param int $count Number of sentences
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Lorem Ipsum sentences
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function loremSentences(int $count = 3, ?bool $strict = null): string
    {
        $sentences = [];
        for ($i = 0; $i < $count; $i++) {
            $wordCount = self::int(5, 15, $strict);
            $sentences[] = self::lorem($wordCount, $strict);
        }
        return implode(' ', $sentences);
    }

    /**
     * Generate Lorem Ipsum paragraphs
     *
     * @param int $count Number of paragraphs
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Lorem Ipsum paragraphs
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function loremParagraphs(int $count = 2, ?bool $strict = null): string
    {
        $paragraphs = [];
        for ($i = 0; $i < $count; $i++) {
            $sentenceCount = self::int(3, 7, $strict);
            $paragraphs[] = self::loremSentences($sentenceCount, $strict);
        }
        return implode("\n\n", $paragraphs);
    }

    /**
     * Generate a random company name
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random company name
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function companyName(?bool $strict = null): string
    {
        $prefixes = ['Global', 'United', 'National', 'International', 'American', 'Pacific', 'Atlantic', 'Northern', 'Southern', 'Eastern'];
        $suffixes = ['Corp', 'Inc', 'LLC', 'Group', 'Holdings', 'Partners', 'Solutions', 'Services', 'Technologies', 'Industries'];
        $words = self::cachedWordList();

        $name = self::getElement($prefixes, $strict) . ' ' . ucfirst(self::getElement($words, $strict)) . ' ' . self::getElement($suffixes, $strict);
        return $name;
    }

    /**
     * Generate a random product name
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random product name
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function productName(?bool $strict = null): string
    {
        $adjectives = ['Super', 'Ultra', 'Mega', 'Pro', 'Elite', 'Premium', 'Deluxe', 'Express', 'Instant', 'Quick'];
        $types = ['Widget', 'Gadget', 'Device', 'Tool', 'System', 'Solution', 'Product', 'Item', 'Component', 'Module'];
        $versions = ['', ' 2.0', ' Pro', ' Plus', ' X', ' Max', ' Mini', ' Lite', ' SE', ' GT'];

        return self::getElement($adjectives, $strict) . ' ' . self::getElement($types, $strict) . self::getElement($versions, $strict);
    }

    /**
     * Generate a random job title
     *
     * @param bool|null $strict Override strict mode for this call (null = use global)
     * @return string Random job title
     * @throws RuntimeException If strict mode enabled and secure random fails
     * @since 1.0.5
     */
    public static function jobTitle(?bool $strict = null): string
    {
        $levels = ['Junior', 'Senior', 'Lead', 'Principal', 'Chief', 'Associate', 'Assistant', 'Executive', 'Managing', 'Regional'];
        $positions = ['Developer', 'Engineer', 'Manager', 'Director', 'Coordinator', 'Specialist', 'Analyst', 'Consultant', 'Administrator', 'Officer'];

        return self::getElement($levels, $strict) . ' ' . self::getElement($positions, $strict);
    }

}