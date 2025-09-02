<?php
/**
 * File: /vendor/vernsix/primordyx/src/BlackHorse.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils/BlackHorse.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use InvalidArgumentException;
use Primordyx\Events\EventManager;

/**
 * Static Price Encoding Utility for Retail Inventory Management
 *
 * Provides a simple substitution cipher for encoding decimal prices into alphabetic codes,
 * allowing retailers to display encoded cost information on price stickers that staff can
 * decode but customers cannot easily understand.
 *
 * ## Encoding System Overview
 * The encoding uses any 10-character word with unique letters where each letter maps to
 * a digit (1-9, 0). Prices are converted to cent values and then encoded letter-by-letter.
 *
 * ## Key Features
 * - **Substitution Cipher**: Maps digits to letters using a customizable code word
 * - **Flexible Encoding Length**: Supports various price ranges via configurable output length
 * - **Customer ID Encoding**: Additional methods for encoding customer IDs with privacy offset
 * - **Method Chaining**: Fluent interface for setting code word and encoding in one statement
 * - **Event Integration**: Uses Primordyx EventManager for error reporting
 *
 * ## Digit Mapping Example
 * For the default code word "BLACKHORSE":
 * - B=1, L=2, A=3, C=4, K=5, H=6, O=7, R=8, S=9, E=0
 *
 * ## Usage Patterns
 *
 * ### Chained Style
 * ```php
 * $encoded = BlackHorse::setCodeWord('NIGHTCRAWL')->encode(12.34); // "EEBLAC"
 * $price = BlackHorse::decode('EEBLAC'); // 12.34
 * ```
 *
 * ### Separate Style
 * ```php
 * BlackHorse::setCodeWord('WORKINGDAY');
 * $encode1 = BlackHorse::encode(1234.56);
 * $encode2 = BlackHorse::encode(987.54);
 * $decode1 = BlackHorse::decode('EEBLAC');
 * ```
 *
 * ### Customer ID Encoding
 * ```php
 * BlackHorse::setCodeWord('BLACKHORSE');
 * $publicId = BlackHorse::encodeCustomerId(47);  // Adds offset of 25000
 * $customerId = BlackHorse::decodeCustomerId($publicId);  // Returns 47
 * ```
 *
 * ## Example Code Words
 * Any 10-character combination with unique letters works:
 * - BLACKHORSE (default)
 * - THUNDERBOX
 * - NIGHTCRAWL
 * - WORKINGDAY
 * - PRODUCTKEY
 * - MASTERLOCK
 *
 * ## Maximum Encodable Values by Length
 * - Length 4: Up to $99.99
 * - Length 6: Up to $9999.99 (default)
 * - Length 8: Up to $999999.99
 *
 * ## Security Considerations
 * - Not cryptographically secure - designed for staff/customer separation only
 * - Should not be used for sensitive data protection
 * - Code word changes affect all subsequent operations globally
 *
 * @see EventManager For error event handling
 * @package Primordyx
 * @since 1.0.0
 */
class BlackHorse
{
    /**
     * Current code word being used for encoding/decoding
     *
     * @var string
     */
    private static string $codeWord = 'BLACKHORSE';

    /**
     * Set the code word for subsequent encode/decode operations
     *
     * Validates and sets a new code word that will be used for all subsequent
     * encoding and decoding operations. The code word must contain exactly 10
     * unique letters. Converts the input to uppercase automatically.
     *
     * ## Method Chaining
     * Returns the class name to enable fluent interface pattern:
     * ```php
     * $encoded = BlackHorse::setCodeWord('NIGHTCRAWL')->encode(12.34);
     * ```
     *
     * ## Global State Warning
     * Changing the code word affects ALL subsequent BlackHorse operations
     * across the entire application until changed again.
     *
     * @since 1.0.0
     * @static
     *
     * @param string $codeWord Code word containing exactly 10 unique letters
     *
     * @return string Returns class name for method chaining
     *
     * @throws InvalidArgumentException If code word doesn't have exactly 10 unique letters
     *
     * @example Basic Usage
     * ```php
     * BlackHorse::setCodeWord('THUNDERBOX');
     * ```
     *
     * @example Method Chaining
     * ```php
     * $encoded = BlackHorse::setCodeWord('NIGHTCRAWL')->encode(45.67);
     * ```
     *
     * @fires blackhorse.invalid_codeword When code word validation fails
     */
    public static function setCodeWord(string $codeWord): string
    {
        $codeWord = strtoupper($codeWord);
        self::validateCodeWord($codeWord);
        self::$codeWord = $codeWord;
        return self::class;
    }

    /**
     * Get the current code word being used for encoding/decoding
     *
     * Returns the currently active code word. Useful for debugging or
     * when you need to verify which code word is currently in use.
     *
     * @since 1.0.0
     * @static
     *
     * @return string Current code word in uppercase
     *
     * @example
     * ```php
     * $current = BlackHorse::getCodeWord();  // Returns "BLACKHORSE" or current word
     * ```
     */
    public static function getCodeWord(): string
    {
        return self::$codeWord;
    }

    /**
     * Validate code word format
     *
     * @since 1.0.0
     * @param string $codeWord Code word to validate
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private static function validateCodeWord(string $codeWord): void
    {
        if (strlen($codeWord) !== 10 || count(array_unique(str_split($codeWord))) !== 10) {
            EventManager::fire('blackhorse.invalid_codeword', [
                'codeWord' => $codeWord,
                'message' => 'Code word must be exactly 10 unique letters'
            ]);
            throw new InvalidArgumentException('Code word must be exactly 10 unique letters: '. $codeWord);
        }
    }

    /**
     * Encode a decimal price into an alphabetic code
     *
     * Converts a decimal price (dollars.cents) into an encoded alphabetic string
     * using the current code word as a substitution cipher. The price is first
     * converted to cents, padded with leading zeros to the specified length,
     * then each digit is mapped to its corresponding letter.
     *
     * ## Encoding Process
     * 1. Convert price to cents: 12.34 → 1234
     * 2. Pad with zeros: 1234 → 001234 (for length 6)
     * 3. Map digits to letters: 001234 → EELBAC
     *
     * ## Length Parameter Guidelines
     * - Length 4: For prices up to $99.99
     * - Length 6: For prices up to $9999.99 (default)
     * - Length 8: For prices up to $999999.99
     *
     * @since 1.0.0
     * @static
     *
     * @param float $price The decimal price to encode (e.g., 12.34 for $12.34)
     * @param int $length Number of characters in output (default: 6)
     *
     * @return string Encoded alphabetic price string of specified length
     *
     * @example Basic Encoding
     * ```php
     * $encoded = BlackHorse::encode(12.34);  // Returns "EELBAC" (6 chars)
     * ```
     *
     * @example Custom Length
     * ```php
     * $encoded = BlackHorse::encode(5.67, 4);   // Returns "ECHI" (4 chars)
     * $encoded = BlackHorse::encode(1234.56, 8); // Returns "EELBACHI" (8 chars)
     * ```
     */
    public static function encode(float $price, int $length = 6): string
    {
        $priceCents = str_pad((string)(int)($price * 100), $length, '0', STR_PAD_LEFT);

        $encoded = '';
        foreach (str_split($priceCents) as $digit) {
            $index = ($digit == '0') ? 9 : (int)$digit - 1;
            $encoded .= self::$codeWord[$index];
        }
        return $encoded;
    }

    /**
     * Decode an alphabetic code back to the original decimal price
     *
     * Reverses the encoding process by converting each letter back to its
     * corresponding digit using the current code word, then converting the
     * resulting cent value back to decimal dollars.
     *
     * ## Decoding Process
     * 1. Map each letter to digit: EELBAC → 001234
     * 2. Convert cents to dollars: 001234 → 12.34
     *
     * ## Error Handling
     * Throws an exception if the encoded string contains any character
     * that doesn't exist in the current code word. Also fires an event
     * for logging purposes before throwing.
     *
     * @since 1.0.0
     * @static
     *
     * @param string $encoded Encoded alphabetic price string of any length
     *
     * @return float The decoded price in decimal dollars (e.g., 12.34)
     *
     * @throws InvalidArgumentException If encoded string contains characters not in code word
     *
     * @example Successful Decoding
     * ```php
     * BlackHorse::setCodeWord('BLACKHORSE');
     * $price = BlackHorse::decode('EELBAC');  // Returns 12.34
     * ```
     *
     * @example Error Handling
     * ```php
     * try {
     *     $price = BlackHorse::decode('XYZ123');  // Invalid characters
     * } catch (InvalidArgumentException $e) {
     *     echo "Decoding failed: " . $e->getMessage();
     * }
     * ```
     *
     * @fires blackhorse.invalid_character When an invalid character is encountered
     */
    public static function decode(string $encoded): float
    {
        $decoded = '';
        foreach (str_split($encoded) as $char) {
            $position = strpos(self::$codeWord, $char);
            if ($position === false) {
                EventManager::fire('blackhorse.invalid_character', [
                    'character' => $char,
                    'encoded' => $encoded,
                    'message' => "Invalid character '$char' in encoded price: '$encoded'"
                ]);
                throw new InvalidArgumentException("Invalid character '$char' in encoded price: '$encoded'");
            }
            $digit = ($position == 9) ? '0' : (string)($position + 1);
            $decoded .= $digit;
        }

        return (int)$decoded / 100;
    }


    /**
     * Encode a customer ID into a public-facing obfuscated ID
     *
     * Adds a privacy offset to a customer ID before encoding it, making it
     * suitable for use in public URLs, order references, or anywhere you need
     * to reference a customer without exposing their actual database ID.
     *
     * ## Privacy Offset
     * The method adds 25000 to the customer ID before encoding. This ensures:
     * - Customer IDs don't start from obvious low numbers
     * - The encoded result is longer and less guessable
     * - Sequential customer IDs don't produce sequential codes
     *
     * @since 1.0.0
     * @static
     *
     * @param int $customerId The internal customer ID to encode
     *
     * @return string Encoded public customer ID string
     *
     * @example Basic Usage
     * ```php
     * BlackHorse::setCodeWord('BLACKHORSE');
     * $publicId = BlackHorse::encodeCustomerId(47);
     * // Customer 47 becomes 25047, then encoded
     * ```
     *
     * @example URL Generation
     * ```php
     * $customerId = 1234;
     * $publicId = BlackHorse::encodeCustomerId($customerId);
     * $url = "https://example.com/customer/{$publicId}";
     * ```
     *
     * @see BlackHorse::decodeCustomerId() For reversing this operation
     */
    public static function encodeCustomerId(int $customerId): string
    {
        $offset = 25000; // Customer 47 becomes 25047
        // Don't specify length - let it encode the full number naturally
        return self::encode((float)($customerId + $offset));
    }

    /**
     * Decode a public customer ID back to the internal customer ID
     *
     * Reverses the customer ID encoding by decoding the alphabetic string
     * and removing the privacy offset to retrieve the original internal
     * customer ID.
     *
     * ## Error Handling
     * If the encoded public ID contains invalid characters, the underlying
     * decode() method will throw an InvalidArgumentException. Consider
     * wrapping calls in try-catch blocks when processing untrusted input.
     *
     * @since 1.0.0
     * @static
     *
     * @param string $publicId The encoded public customer ID to decode
     *
     * @return int The original internal customer ID
     *
     * @throws InvalidArgumentException If public ID contains invalid characters
     *
     * @example Basic Usage
     * ```php
     * $customerId = BlackHorse::decodeCustomerId($publicId);  // Returns original ID
     * ```
     *
     * @example With Error Handling
     * ```php
     * try {
     *     $customerId = BlackHorse::decodeCustomerId($publicId);
     *     $customer = Customer::find($customerId);
     * } catch (InvalidArgumentException $e) {
     *     // Handle invalid public ID
     *     return response('Invalid customer reference', 400);
     * }
     * ```
     *
     * @see BlackHorse::encodeCustomerId() For creating public customer IDs
     */
    public static function decodeCustomerId(string $publicId): int
    {
        $offset = 25000;
        return (int)(self::decode($publicId) - $offset);
    }


}