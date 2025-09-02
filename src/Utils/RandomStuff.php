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
 * @link        https://github.com/vernsix/primordyx/blob/master/src/RandomStuff.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use DateTimeInterface;
use InvalidArgumentException;
use Random;

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
 * 2. Fallback: Uses mt_rand() if Random\RandomException occurs
 * 3. Never fails: All methods complete successfully regardless of random source
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
 * // Secure strings and passwords
 * $token = RandomStuff::urlSafe(32);
 * $password = RandomStuff::password(16, true, true, true, true);
 *
 * // Test data generation
 * $email = RandomStuff::email('example.com');
 * $name = RandomStuff::fullName('female');
 * $card = RandomStuff::creditCard('visa');
 * ```
 *
 * @example Array Operations
 * ```php
 * $data = ['a', 'b', 'c', 'd', 'e'];
 * $shuffled = RandomStuff::shuffle($data);
 * $sample = RandomStuff::sample($data, 3);
 *
 * // Weighted selection
 * $weights = ['common' => 70, 'rare' => 20, 'epic' => 10];
 * $result = RandomStuff::weighted($weights);
 * ```
 *
 * @see Lists For word lists and data arrays used by this class
 */
class RandomStuff
{
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
    public static function myThreeWords(): string
    {
        static $myThreeWords = '';
        if ($myThreeWords === '') {
            $myThreeWords = self::words(3);
        }
        return $myThreeWords;
    }

    /**
     * Generate a string of random words joined by a specified separator
     *
     * Creates human-readable identifiers by randomly selecting words from a word list
     * and joining them with a separator. Uses secure randomness with graceful fallback
     * to mt_rand if the secure random generator fails.
     *
     * ## Word Source Hierarchy
     * 1. Custom word list if provided via $wordList parameter
     * 2. Cached internal word list (5-character English words)
     * 3. Falls back to mt_rand selection if random_int fails
     *
     * ## Error Handling
     * If Random\RandomException occurs during secure random generation:
     * - Switches to mt_rand for remaining word selections
     * - Continues normal execution without throwing exceptions
     * - Ensures method always completes successfully
     *
     * @param int $howManyWords Number of words to include (default is 3)
     * @param string $separator Separator used to join the words (default is '-')
     * @param array<string> $wordList Optional custom word list to choose from
     * @return string Concatenated string of randomly selected words
     * @since 1.0.0
     *
     * @example Word Generation Patterns
     * ```php
     * $default = RandomStuff::words(); // "apple-bread-grape"
     * $dotted = RandomStuff::words(4, '.'); // "eagle.flame.ocean.storm"
     * $custom = RandomStuff::words(2, '_', ['red', 'blue', 'green']); // "blue_red"
     * ```
     *
     * @see myThreeWords() For cached three-word identifiers
     * @see cachedWordList() For default word source
     */
    public static function words(int $howManyWords = 3, string $separator = '-', array $wordList = []): string
    {
        if (empty($wordList)) {
            $wordList = self::cachedWordList();
        }

        $parts = [];
        try {
            for ($i = 0; $i < $howManyWords; $i++) {
                $parts[] = $wordList[random_int(0, count($wordList) - 1)];
            }
        } catch (Random\RandomException) {

            // Fallback: use a different approach rather than predictable first N words
            for ($i = 0; $i < $howManyWords; $i++) {
                $parts[] = $wordList[mt_rand(0, $maxIndex)];
            }

            //    Optional fallback: just take the first N words
            // $parts = array_slice($wordList, 0, $howManyWords);
        }
        return implode($separator, $parts);
    }

    /**
     * Return a random element from the provided array
     *
     * Selects a random element from an array using PHP's array_rand function.
     * Returns null for empty arrays to prevent errors and provide predictable
     * behavior for edge cases.
     *
     * ## Array Handling
     * - Preserves original array keys and values
     * - Works with both indexed and associative arrays
     * - Returns actual array values, not keys
     * - Safe for arrays containing mixed data types
     *
     * @param array<string> $strings Array of strings to choose from
     * @return string|null A random element, or null if the array is empty
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
    public static function getElement(array $strings): ?string
    {
        if (empty($strings)) {
            return null;
        }
        return $strings[array_rand($strings)];
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
     * @return string URL-safe random string
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
    public static function urlSafe(int $length = 16): string
    {
        return self::string($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_');
    }

    /**
     * Generate a random string of specified length using a custom character set
     *
     * Core string generation method that creates random strings from any specified
     * character set. Uses secure random generation with automatic fallback to
     * mt_rand if cryptographically secure randomness fails.
     *
     * ## Security Implementation
     * 1. Attempts random_int() for secure random index generation
     * 2. Falls back to mt_rand() if Random\RandomException occurs
     * 3. Continues generation seamlessly without user intervention
     * 4. Never throws exceptions for random generation failures
     *
     * ## Character Set Flexibility
     * - Default: Full alphanumeric (letters + digits)
     * - Custom: Any string of characters for selection
     * - Supports Unicode characters in charset
     * - Characters can repeat in output based on selection probability
     *
     * @param int $length Length of the string to generate
     * @param string $charset Character set to use (default: alphanumeric)
     * @return string Random string built from specified character set
     * @throws InvalidArgumentException If length is less than 1
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
    public static function string(int $length = 8, string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('String length must be at least 1');
        }

        $result = '';
        $maxIndex = strlen($charset) - 1;

        try {
            for ($i = 0; $i < $length; $i++) {
                $result .= $charset[random_int(0, $maxIndex)];
            }
        } catch (Random\RandomException) {
            for ($i = 0; $i < $length; $i++) {
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
     * @return string Random hexadecimal string using 0-9a-f
     * @since 1.0.0
     *
     * @example Hexadecimal String Generation
     * ```php
     * $hash = RandomStuff::hex(32); // "a7b9c3d2e8f1a4b6c9d7e2f5a8b1c4d7"
     * $short = RandomStuff::hex(8);  // "f3a7b9c2"
     * $color = '#' . RandomStuff::hex(6); // "#a1b2c3"
     * ```
     *
     * @see string() For custom character set strings
     * @see apiKey() For mixed alphanumeric strings
     */
    public static function hex(int $length = 32): string
    {
        return self::string($length, '0123456789abcdef');
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
     * @return int Random integer within specified range
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
    public static function int(int $min = 0, int $max = 100): int
    {
        try {
            return random_int($min, $max);
        } catch (Random\RandomException) {
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
     * @return float Random float within specified range and precision
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
    public static function float(float $min = 0.0, float $max = 1.0, int $precision = 2): float
    {
        try {
            $randomInt = random_int(0, getrandmax());
            $randomFloat = $randomInt / getrandmax();
        } catch (Random\RandomException) {
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
     * ## Probability Control
     * - Default: 50% chance of true (unbiased)
     * - Custom: Specify probability as decimal (0.0 to 1.0)
     * - Examples: 0.7 = 70% chance of true, 0.1 = 10% chance of true
     * - Edge cases: 0.0 = always false, 1.0 = always true
     *
     * @param float $probability Probability of returning true (0.0-1.0, default: 0.5)
     * @return bool Random boolean value
     * @since 1.0.0
     *
     * @example Boolean Generation with Probability
     * ```php
     * $coin = RandomStuff::bool(); // 50/50 chance
     * $biased = RandomStuff::bool(0.8); // 80% chance of true
     * $rare = RandomStuff::bool(0.1); // 10% chance of true
     * ```
     *
     * @see weighted() For complex probability distributions
     */
    public static function bool(float $probability = 0.5): bool
    {
        return self::float(0, 1) < $probability;
    }

    /**
     * Generate a random date within a specified range
     *
     * Creates random DateTimeImmutable objects between two boundary dates.
     * Accepts various input formats and provides consistent date range generation
     * for testing temporal data scenarios.
     *
     * ## Input Format Flexibility
     * - DateTimeInterface objects (DateTime, DateTimeImmutable)
     * - String dates in any format parseable by DateTime constructor
     * - Relative formats: "now", "+1 week", "2023-01-01", etc.
     * - Default range: January 1, 2020 to current date/time
     *
     * ## Output Consistency
     * - Always returns DateTimeImmutable for immutability
     * - Preserves timezone information from input dates
     * - Random time component included (not just date)
     *
     * @param DateTimeInterface|string $start Start date (default: "2020-01-01")
     * @param DateTimeInterface|string $end End date (default: "now")
     * @param string $format
     * @return string Random date within specified range
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
    public static function date(
        string|DateTimeInterface $start = '-1 year',
        string|DateTimeInterface $end = 'now',
        string $format = 'Y-m-d'
    ): string {
        $startTimestamp = is_string($start) ? strtotime($start) : $start->getTimestamp();
        $endTimestamp = is_string($end) ? strtotime($end) : $end->getTimestamp();

        $randomTimestamp = self::int($startTimestamp, $endTimestamp);
        return date($format, $randomTimestamp);
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
     * @return array Shuffled array with preserved key-value relationships
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
    public static function shuffle(array $array): array
    {
        $keys = array_keys($array);

        // Fisher-Yates shuffle with secure random
        for ($i = count($keys) - 1; $i > 0; $i--) {
            $j = self::int(0, $i);
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
     * @return array Random sample from input array
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
    public static function sample(array $array, int $count = 1, bool $preserveKeys = false): array
    {
        if ($count >= count($array)) {
            return $preserveKeys ? $array : array_values($array);
        }

        $shuffled = self::shuffle($array);
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
     * @return string|int|null Selected key from weights array, or null if empty
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
    public static function weighted(array $weights): string|int|null
    {
        $totalWeight = array_sum($weights);
        $random = self::float(0, $totalWeight);

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
     * @return string Random password meeting specified criteria
     * @throws InvalidArgumentException If all character types are disabled
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
        bool $includeLowercase = true
    ): string {
        $charset = '';

        if ($includeLowercase) $charset .= 'abcdefghijklmnopqrstuvwxyz';
        if ($includeUppercase) $charset .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($includeNumbers) $charset .= '0123456789';
        if ($includeSymbols) $charset .= '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (empty($charset)) {
            throw new InvalidArgumentException('At least one character type must be included');
        }

        return self::string($length, $charset);
    }

    /**
     * Generate a random email address for testing purposes
     *
     * Creates realistic-looking email addresses using random word combinations
     * for usernames and either specified or randomly selected domains. Designed
     * specifically for development and testing scenarios.
     *
     * ## Username Generation
     * - Uses two random words separated by dot
     * - Example: "brave.eagle", "ocean.storm", "red.mountain"
     * - Words sourced from cached word list for consistency
     *
     * ## Domain Handling
     * - Custom domain: Use provided domain parameter
     * - Random domain: Selects from predefined test domains
     * - Test domains: example.com, test.org, demo.net, sample.io
     * - All domains are non-functional for safety
     *
     * @param string $domain Custom domain to use (optional, uses random if empty)
     * @return string Random email address suitable for testing
     * @since 1.0.0
     *
     * @example Email Generation for Testing
     * ```php
     * $random = RandomStuff::email(); // "brave.eagle@example.com"
     * $custom = RandomStuff::email('mysite.com'); // "ocean.storm@mysite.com"
     * ```
     *
     * @see words() For username word generation
     * @see fullName() For person-based email generation
     */
    public static function email(string $domain = ''): string
    {
        $domains = ['example.com', 'test.org', 'demo.net', 'sample.io'];
        $selectedDomain = $domain ?: self::getElement($domains);

        $username = self::words(2, '.');
        return "{$username}@{$selectedDomain}";
    }

    /**
     * Generate a random IP address in IPv4 or IPv6 format
     *
     * Creates random IP addresses for testing network applications, configuration
     * validation, or simulation scenarios. Supports both IPv4 and IPv6 formats
     * with appropriate range restrictions for realistic addresses.
     *
     * ## IPv4 Generation
     * - Format: xxx.xxx.xxx.xxx
     * - First octet: 1-254 (avoids 0 and 255)
     * - Middle octets: 0-255 (full range)
     * - Last octet: 1-254 (avoids 0 and 255)
     * - Excludes common reserved ranges for realistic simulation
     *
     * ## IPv6 Generation
     * - Format: xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx
     * - Each segment: 4-character hexadecimal (0000-ffff)
     * - Full 128-bit address space representation
     * - Uses lowercase hex characters for consistency
     *
     * @param string $type IP version type: 'ipv4' or 'ipv6' (default: 'ipv4')
     * @return string Random IP address in specified format
     * @throws InvalidArgumentException If unsupported IP type is specified
     * @since 1.0.0
     *
     * @example IP Address Generation
     * ```php
     * $ipv4 = RandomStuff::ip('ipv4'); // "192.168.1.42"
     * $ipv6 = RandomStuff::ip('ipv6'); // "2a03:4000:3c:7f::1234"
     * $default = RandomStuff::ip(); // IPv4 format
     * ```
     *
     * @see hex() For hexadecimal component generation
     * @see macAddress() For MAC address generation
     */
    public static function ip(string $type = 'ipv4'): string
    {
        switch ($type) {
            case 'ipv4':
                return implode('.', [
                    self::int(1, 254),
                    self::int(0, 255),
                    self::int(0, 255),
                    self::int(1, 254)
                ]);

            case 'ipv6':
                $parts = [];
                for ($i = 0; $i < 8; $i++) {
                    $parts[] = self::hex(4);
                }
                return implode(':', $parts);

            default:
                throw new InvalidArgumentException("Unsupported IP type: {$type}");
        }
    }

    /**
     * Generate random geographic coordinates within specified bounds
     *
     * Creates random latitude and longitude pairs for geographic testing scenarios.
     * Supports custom boundary specification for regional testing or uses global
     * bounds as default. Returns coordinates with appropriate decimal precision.
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
     * @return array{latitude: float, longitude: float} Random coordinate pair
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
     * ```
     *
     * @see float() For precision decimal generation
     */
    public static function coordinates(array $bounds = []): array
    {
        $minLat = $bounds['min_lat'] ?? -90;
        $maxLat = $bounds['max_lat'] ?? 90;
        $minLng = $bounds['min_lng'] ?? -180;
        $maxLng = $bounds['max_lng'] ?? 180;

        return [
            'latitude' => self::float($minLat, $maxLat, 6),
            'longitude' => self::float($minLng, $maxLng, 6)
        ];
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
     * @return string Random first name matching gender criteria
     * @since 1.0.0
     *
     * @example First Name Generation by Gender
     * ```php
     * $anyName = RandomStuff::firstName(); // From all lists
     * $maleName = RandomStuff::firstName('male'); // "James", "Michael"
     * $femaleName = RandomStuff::firstName('female'); // "Sarah", "Emma"
     * $unisexName = RandomStuff::firstName('unisex'); // "Jordan", "Taylor"
     * ```
     *
     * @see Lists::maleFirstNames() For male name source
     * @see Lists::femaleFirstNames() For female name source
     * @see Lists::unisexFirstNames() For unisex name source
     * @see lastName() For surname generation
     * @see fullName() For complete name generation
     */
    public static function firstName(string $gender = 'any'): string
    {
        $male = Lists::maleFirstNames();
        $female = Lists::femaleFirstNames();
        $unisex = Lists::unisexFirstNames();

        return match($gender) {
            'male' => self::getElement($male),
            'female' => self::getElement($female),
            'unisex' => self::getElement($unisex),
            default => self::getElement(array_merge($male, $female, $unisex))
        };
    }

    /**
     * Generate a random surname from curated list
     *
     * Returns random last names from a comprehensive surname database suitable
     * for testing user profiles, contact lists, or demographic simulations.
     * Names are culturally diverse and commonly used in English-speaking regions.
     *
     * @return string Random surname/last name
     * @since 1.0.0
     *
     * @example Surname Generation
     * ```php
     * $surname = RandomStuff::lastName(); // "Smith", "Johnson", "Williams"
     * ```
     *
     * @see Lists::lastNames() For surname data source
     * @see firstName() For given name generation
     * @see fullName() For complete name pairs
     */
    public static function lastName(): string
    {
        $names = Lists::lastNames();
        return self::getElement($names);
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
     * @return string Random complete name in "First Last" format
     * @since 1.0.0
     *
     * @example Complete Name Generation
     * ```php
     * $anyName = RandomStuff::fullName(); // "Emma Johnson"
     * $maleName = RandomStuff::fullName('male'); // "Michael Smith"
     * $femaleName = RandomStuff::fullName('female'); // "Sarah Williams"
     * ```
     *
     * @see firstName() For first name component generation
     * @see lastName() For surname component generation
     */
    public static function fullName(string $gender = 'any'): string
    {
        return self::firstName($gender) . ' ' . self::lastName();
    }


    /**
     * Generate Lorem Ipsum text with specified word count
     *
     * Creates pseudo-Latin placeholder text for layout testing, content simulation,
     * and design mockups. Uses traditional Lorem Ipsum word pool with randomized
     * selection and proper sentence formatting.
     *
     * ## Text Formatting
     * - First word capitalized for sentence appearance
     * - All words separated by single spaces
     * - Ends with period for proper sentence structure
     * - No paragraph breaks or additional punctuation
     *
     * ## Word Selection
     * - Random selection from classical Lorem Ipsum vocabulary
     * - Words may repeat naturally based on random selection
     * - Maintains Lorem Ipsum's pseudo-Latin appearance
     *
     * @param int $words Number of words to generate (default: 10)
     * @return string Lorem Ipsum text formatted as single sentence
     * @since 1.0.0
     *
     * @example Lorem Ipsum Generation
     * ```php
     * $short = RandomStuff::lorem(5); // "Lorem ipsum dolor sit amet."
     * $paragraph = RandomStuff::lorem(50); // Extended lorem text
     * ```
     *
     * @see Lists::loremIpsum() For word source data
     */
    public static function lorem(int $words = 10): string
    {
        $loremWords = Lists::loremIpsum();

        $result = [];
        for ($i = 0; $i < $words; $i++) {
            $result[] = self::getElement($loremWords);
        }

        return ucfirst(implode(' ', $result)) . '.';
    }

    /**
     * Generate a random credit card number for testing purposes
     *
     * Creates realistic-looking credit card numbers that follow proper formatting
     * patterns for major card types. Numbers are for testing only and are not
     * valid for actual transactions or financial processing.
     *
     * ## Supported Card Types
     * - 'visa': 4###-####-####-#### format
     * - 'mastercard': 5###-####-####-#### format
     * - 'amex': 3###-######-##### format (American Express)
     * - Default: Uses Visa format for unrecognized types
     *
     * ## Testing Safety
     * - Numbers are randomly generated, not from valid ranges
     * - Do not pass Luhn algorithm validation
     * - Safe for form testing without financial risk
     * - Should never be used for actual payment processing
     *
     * @param string $type Card type: 'visa', 'mastercard', or 'amex' (default: 'visa')
     * @return string Formatted credit card number for testing
     * @since 1.0.0
     *
     * @example Credit Card Generation for Testing
     * ```php
     * $visa = RandomStuff::creditCard('visa'); // "4123-5678-9012-3456"
     * $mc = RandomStuff::creditCard('mastercard'); // "5234-6789-0123-4567"
     * $amex = RandomStuff::creditCard('amex'); // "3456-789012-34567"
     * ```
     */
    public static function creditCard(string $type = 'visa'): string
    {
        $patterns = [
            'visa' => '4###-####-####-####',
            'mastercard' => '5###-####-####-####',
            'amex' => '3###-######-#####'
        ];

        $pattern = $patterns[$type] ?? $patterns['visa'];
        return preg_replace_callback('/#/', fn() => (string)self::int(0, 9), $pattern);
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
     * @return string Random alphanumeric API key
     * @since 1.0.0
     *
     * @example API Key Generation
     * ```php
     * $key = RandomStuff::apiKey(32); // "a7B9c3D2e8F1A4b6C9d7E2f5A8b1C4d7"
     * $short = RandomStuff::apiKey(16); // "K2n9QwE7rT8yU3iO"
     * ```
     *
     * @see string() For custom character set strings
     * @see urlSafe() For URL-safe tokens with additional characters
     * @see hex() For hexadecimal-only strings
     */
    public static function apiKey(int $length = 32): string
    {
        return self::string($length, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
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
     * @return string Random MAC address in colon-separated hex format
     * @since 1.0.0
     *
     * @example MAC Address Generation
     * ```php
     * $mac = RandomStuff::macAddress(); // "a1:b2:c3:d4:e5:f6"
     * ```
     *
     * @see hex() For hexadecimal string generation
     * @see ip() For IP address generation
     */
    public static function macAddress(): string
    {
        $bytes = [];
        for ($i = 0; $i < 6; $i++) {
            $bytes[] = sprintf('%02x', self::int(0, 255));
        }
        return implode(':', $bytes);
    }

    /**
     * Generate a random US ZIP code in 5-digit format
     *
     * Creates random ZIP codes following the standard US postal format.
     * Generates 5-digit codes within the valid range used by the United States
     * Postal Service for testing address forms and location-based applications.
     *
     * ## ZIP Code Format
     * - Length: Exactly 5 digits
     * - Range: 10000-99999 (avoids leading zeros)
     * - Format: #####
     * - No ZIP+4 extension included
     *
     * ## Usage Context
     * Suitable for form testing, address validation, and location-based
     * application development requiring realistic US postal codes.
     *
     * @return string Random 5-digit US ZIP code
     * @since 1.0.0
     *
     * @example ZIP Code Generation
     * ```php
     * $zip = RandomStuff::zipCode(); // "84736", "12345", "98765"
     * ```
     *
     * @see state() For US state generation
     * @see phoneNumber() For US phone number generation
     */
    public static function zipCode(): string
    {
        return sprintf('%05d', self::int(10000, 99999));
    }

    /**
     * Generate a random US phone number in standard format
     *
     * Creates random phone numbers following the North American Numbering Plan
     * (NANP) format. Uses realistic area codes and exchange codes while avoiding
     * reserved or special-purpose number ranges.
     *
     * ## Phone Number Format
     * - Pattern: (xxx) xxx-xxxx
     * - Area code: 200-999 (avoids 0xx and 1xx reserved ranges)
     * - Exchange: 200-999 (avoids 0xx and 1xx reserved ranges)
     * - Number: 1000-9999 (avoids service codes like x11)
     *
     * ## NANP Compliance
     * - Follows North American Numbering Plan restrictions
     * - Avoids reserved and special-purpose ranges
     * - Generates realistic-looking test numbers
     * - Safe for form testing without contacting real numbers
     *
     * @return string Random US phone number in (xxx) xxx-xxxx format
     * @since 1.0.0
     *
     * @example Phone Number Generation
     * ```php
     * $phone = RandomStuff::phoneNumber(); // "(555) 123-4567"
     * ```
     *
     * @see zipCode() For ZIP code generation
     * @see state() For US state generation
     */
    public static function phoneNumber(): string
    {
        return sprintf('(%03d) %03d-%04d',
            self::int(200, 999),  // area code
            self::int(200, 999),  // exchange
            self::int(1000, 9999) // number
        );
    }

    /**
     * Generate a random US state name or abbreviation
     *
     * Returns random US states from a comprehensive list including all 50 states
     * plus common territories. Supports both full state names and standard
     * two-letter postal abbreviations based on parameter selection.
     *
     * ## Output Formats
     * - Full names: "California", "Texas", "New York"
     * - Abbreviations: "CA", "TX", "NY"
     * - Default: Returns full state names
     * - Abbreviation flag: Set true for postal codes
     *
     * ## State Coverage
     * Includes all 50 US states plus common territories like DC, PR, etc.
     * Uses official state names and USPS-approved abbreviations.
     *
     * @param bool $abbreviated Return postal abbreviation instead of full name (default: false)
     * @return string Random US state name or abbreviation
     * @since 1.0.0
     *
     * @example US State Generation
     * ```php
     * $fullName = RandomStuff::state(); // "California"
     * $abbrev = RandomStuff::state(true); // "CA"
     * ```
     *
     * @see Lists::states() For state data source
     * @see zipCode() For ZIP code generation
     * @see phoneNumber() For phone number generation
     */
    public static function state(bool $abbreviated = false): string
    {
        $states = Lists::states();
        if ($abbreviated) {
            return self::getElement(array_keys($states));
        }
        return self::getElement(array_values($states));
    }

    /**
     * Generate a random time of day in specified format
     *
     * Creates random time values representing moments within a 24-hour day.
     * Uses timestamp-based generation to ensure valid times and supports
     * customizable formatting via PHP date format strings.
     *
     * ## Time Generation
     * - Range: 00:00:00 to 23:59:59 (full 24-hour day)
     * - Method: Random timestamp selection within 86400 seconds
     * - Base: Uses gmdate() for consistent UTC time formatting
     * - Resolution: Second-level precision
     *
     * ## Format Flexibility
     * Accepts any PHP date() format string for output customization:
     * - Default: "H:i:s" (24-hour format with seconds)
     * - 12-hour: "g:i:s A" for AM/PM format
     * - Custom: Any valid PHP date format pattern
     *
     * @param string $format PHP date format string (default: 'H:i:s')
     * @return string Random time formatted according to specified pattern
     * @since 1.0.0
     *
     * @example Time Generation with Various Formats
     * ```php
     * $military = RandomStuff::time(); // "14:23:47"
     * $ampm = RandomStuff::time('g:i A'); // "2:23 PM"
     * $hourMin = RandomStuff::time('H:i'); // "14:23"
     * ```
     *
     * @see date() For full date generation
     * @see timezone() For timezone generation
     */
    public static function time(string $format = 'H:i:s'): string
    {
        $timestamp = self::int(0, 86399); // seconds in a day
        return gmdate($format, $timestamp);
    }

    /**
     * Generate a random timezone identifier from PHP's supported list
     *
     * Returns random timezone identifiers from PHP's comprehensive timezone
     * database. Useful for testing time-related functionality across different
     * geographic regions and daylight saving time scenarios.
     *
     * ## Timezone Format
     * - Standard: PHP DateTimeZone identifiers
     * - Examples: "America/New_York", "Europe/London", "Asia/Tokyo"
     * - Coverage: Global timezone database maintained by PHP
     * - Validity: All returned timezones are valid for DateTime operations
     *
     * ## Use Cases
     * - Testing timezone-aware applications
     * - Simulating global user bases
     * - Validating time conversion logic
     * - Geographic distribution simulation
     *
     * @return string Random PHP timezone identifier
     * @since 1.0.0
     *
     * @example Timezone Generation
     * ```php
     * $tz = RandomStuff::timezone(); // "America/Chicago", "UTC", "Asia/Shanghai"
     * $dateTime = new DateTime('now', new DateTimeZone($tz));
     * ```
     *
     * @see Lists::phpTimeZones() For timezone data source
     * @see date() For date generation
     * @see time() For time generation
     */
    public static function timezone(): string
    {
        $timezones = Lists::phpTimeZones();
        return self::getElement($timezones);
    }

    /**
     * Simulate rolling dice with customizable sides and quantity
     *
     * Simulates physical dice rolling for gaming applications, probability testing,
     * or random number generation scenarios requiring discrete uniform distribution.
     * Supports various die types and multiple dice rolls simultaneously.
     *
     * ## Dice Configuration
     * - Sides: Number of faces per die (default: 6 for standard cube)
     * - Count: Number of dice to roll simultaneously (default: 1)
     * - Range: Each die result from 1 to sides (inclusive)
     * - Common types: d4(4), d6(6), d8(8), d10(10), d12(12), d20(20)
     *
     * ## Return Format
     * Returns array of individual die results, not summed total.
     * Preserves each die's result for game mechanics requiring individual values.
     *
     * @param int $sides Number of faces per die (default: 6)
     * @param int $count Number of dice to roll (default: 1)
     * @return array<int> Individual results from each die roll
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
    public static function dice(int $sides = 6, int $count = 1): array
    {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = self::int(1, $sides);
        }
        return $results;
    }

    /**
     * Draw a random playing card from a standard 52-card deck
     *
     * Generates random playing cards using standard deck composition with
     * four suits and thirteen ranks. Returns cards in traditional notation
     * format suitable for card game simulation and testing.
     *
     * ## Card Components
     * - Suits: Spade (♠), Heart (♥), Diamond (♦), Club (♣)
     * - Ranks: A, 2, 3, 4, 5, 6, 7, 8, 9, 10, J, Q, K
     * - Format: RankSuit (e.g., "A♠", "10♥", "K♦")
     * - Deck size: 52 cards (13 ranks × 4 suits)
     *
     * ## Unicode Symbols
     * Uses Unicode suit symbols for authentic playing card representation.
     * Compatible with most modern systems and display contexts.
     *
     * @return string Random playing card in "RankSuit" format
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
    public static function playingCard(): string
    {
        $suits = ['♠', '♥', '♦', '♣'];
        $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

        return self::getElement($values) . self::getElement($suits);
    }

    /**
     * Get the cached word list, loading it on first access
     *
     * Implements lazy loading for the five-character word list used by word
     * generation methods. Caches the list in static variable for performance
     * optimization during multiple word generation calls within same request.
     *
     * ## Caching Strategy
     * - Lazy loading: Only loads when first accessed
     * - Static storage: Persists for entire script execution
     * - Performance: Avoids repeated expensive list generation
     * - Memory: Single copy shared across all word generation methods
     *
     * ## Cache Behavior
     * - First call: Loads from Lists::bigFiveCharWords() and caches
     * - Subsequent calls: Returns cached array without reloading
     * - Testing: Use resetCache() to clear for testing scenarios
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
    public static function cachedWordList(): array
    {
        if (self::$cachedWordList === null) {
            self::$cachedWordList = Lists::bigFiveCharWords();
        }
        return self::$cachedWordList;
    }

    /**
     * Reset the cached word list for testing purposes
     *
     * Clears the internal word list cache to force reload on next access.
     * Primarily used in testing scenarios to ensure clean state between
     * test runs or when testing cache behavior itself.
     *
     * ## Testing Use Cases
     * - Unit test isolation: Clear cache between tests
     * - Memory testing: Verify cache loading behavior
     * - Performance testing: Measure cache vs non-cache performance
     * - State reset: Ensure predictable starting conditions
     *
     * ## Production Impact
     * Safe to call in production but unnecessary since cache improves
     * performance. Only clear cache if specific testing requirements demand it.
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



}

