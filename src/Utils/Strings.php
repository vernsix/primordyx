<?php
/**
 * File: /vendor/vernsix/primordyx/src/Strings.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Strings.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class strings
 *
 * A comprehensive utility class for working with strings in a multibyte-safe, Unicode-aware way.
 * Provides tools for transformation, sanitization, validation, analysis, formatting, security, and fun.
 *
 * ------------------------------------------------------------------
 * 🔤 TRANSFORMATION METHODS
 * - toCamelCase()
 * - toPascalCase()         (via ucfirst(toCamelCase()))
 * - toSnakeCase()
 * - toKebabCase()
 * - toTitleCase()
 * - smartTitleCase()
 * - slugify()
 * - slugToTitle()
 * - reverse()
 * - reverseWords()
 * - reverseLines()
 * - truncate()
 * - truncateMiddle()
 * - limitWords()
 * - wrap()
 * - padLeft()
 * - padRight()
 * - center()
 * - indent()
 * - initials()
 * - removePrefix()
 * - removeSuffix()
 * - replaceFirstPartOfString()
 * - incrementString()
 *
 * ------------------------------------------------------------------
 * SANITIZATION / CLEANUP
 * - clean()
 * - collapseWhitespace()
 * - stripPunctuation()
 * - stripDiacritics()
 * - stripHtmlButAllow()
 * - strip()
 * - normalizeWhitespace()
 * - normalizeNewlinesToCrlf()
 * - toAscii()
 * - toAsciiSafe()
 * - removeAccents()
 * - stripNonPrintable()
 * - sanitizeFilename()
 *
 * ------------------------------------------------------------------
 * VALIDATION & CHECKING
 * - isAlpha()
 * - isAlnum()
 * - isLower()
 * - isUpper()
 * - hasMixedCase()
 * - isLetters()
 * - isLettersOrDigits()
 * - isNumericString()
 * - isBlank()
 * - isJson()
 * - isUrl()
 * - isEmail()
 * - isUuid()
 * - isHex()
 * - isValidBase64()
 * - isAscii()
 * - isPrintable()
 * - isSentenceLike()
 * - startsWith()
 * - endsWith()
 * - startsWithAny()
 * - endsWithAny()
 * - contains()
 * - containsAny()
 * - hasPrefixAny()
 * - matchesPattern()
 * - equalsIgnoreCase()
 * - detectCaseStyle()
 *
 * ------------------------------------------------------------------
 * SECURITY & AUTH
 * - constantTimeEquals()
 * - signHmac()
 * - verifyHmacSignature()
 * - secureCompare()           (alias of constantTimeEquals)
 * - verifyTwilioSignature()
 *
 * ------------------------------------------------------------------
 * 🔒 OBFUSCATION
 * - obfuscateEmail()
 * - obfuscatePhoneNumber()
 * - obfuscateCreditCard()
 * - obfuscatePostalCode()
 * - obfuscatePassword()
 *
 * ------------------------------------------------------------------
 * 📊 ANALYSIS & STATS
 * - length()
 * - byteLength()
 * - wordCount()
 * - sentenceCount()
 * - charFrequency()
 * - detectEncoding()
 *
 * ------------------------------------------------------------------
 * FUN & MISC
 * - rot13()
 * - shuffle()
 * - randomString()
 * - randomSlug()
 * - isPalindrome()
 * - extractBetween()
 * - wordWrapHtmlSafe()
 * - removeDuplicateWords()
 *
 * ------------------------------------------------------------------
 * CONVERSION & UTILITY
 * - normalizeHex()
 * - sanitizeHex()
 * - guid()
 * - uuid()
 * - uuidV7()
 * - toSlugId()
 */
class Strings
{

    /**
     * Converts a string into a URL-friendly "slug" format.
     *
     * This method transforms the input string to lowercase, replaces all non-alphanumeric
     * characters with hyphens, and trims any leading or trailing hyphens. It's commonly
     * used to create clean, SEO-friendly URL segments.
     *
     * @param string $str The input string to convert into slug form.
     *
     * @return string A slugified version of the string, containing only lowercase letters,
     *                digits, and hyphens. Consecutive non-alphanumeric characters are replaced
     *                by a single hyphen.
     *
     * @example
     * ```php
     * Strings::slugify('Hello World!');        // 'hello-world'
     * Strings::slugify('PHP & MySQL Tips');    // 'php-mysql-tips'
     * Strings::slugify('  Leading/Trailing  '); // 'leading-trailing'
     * ```
     *
     * @edgecase If the input string is empty or contains no alphanumeric characters,
     *           the return value will be an empty string.
     *
     * @note Only basic ASCII characters are handled. Accented characters (e.g., é, ñ)
     *       will be removed rather than converted. To support Unicode or transliteration,
     *       additional normalization steps (e.g., `iconv()`, `transliterator_transliterate()`)
     *       would be required.
     */
    public static function slugify(string $str): string
    {
        $str = strtolower($str);
        $str = preg_replace('/[^a-z0-9]+/', '-', $str);
        return trim($str, '-');
    }


    /**
     * Converts a slug-formatted string into a human-readable title case string.
     *
     * This method replaces hyphens and underscores with spaces and then applies title casing.
     * It is useful for transforming URL-friendly slugs (e.g., `my-awesome-title`) into
     * readable titles (e.g., `My Awesome Title`).
     *
     * @param string $slug The slug string to convert. Typically lowercase, words separated by `-` or `_`.
     *
     * @return string A title-cased version of the input, with separators replaced by spaces.
     *
     * @example
     * ```php
     * Strings::slugToTitle('my-awesome-title');     // 'My Awesome Title'
     * Strings::slugToTitle('another_example_here'); // 'Another Example Here'
     * Strings::slugToTitle('mixed-separators_here'); // 'Mixed Separators Here'
     * ```
     *
     * @edgecase If the slug is an empty string, the result will also be an empty string.
     *           If the slug contains characters beyond alphanumerics, dashes, or underscores,
     *           they will remain in the result unless handled by `titleCase()`.
     *
     * @note This function depends on `self::titleCase()` for casing logic.
     *       If `titleCase()` has locale-specific behavior or Unicode quirks,
     *       those will affect the output of this method as well.
     */
    public static function slugToTitle(string $slug): string
    {
        return self::titleCase(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * Checks whether a string starts with a given substring.
     *
     * This method determines if `$haystack` begins with the specified `$needle`.
     * It uses `strncmp()` to compare the start of `$haystack` with `$needle` up to the length of `$needle`.
     *
     * @param string $haystack The full string to evaluate.
     * @param string $needle   The substring to check for at the start of `$haystack`.
     *
     * @return bool Returns `true` if `$haystack` starts with `$needle`, otherwise `false`.
     *
     * @example
     * ```php
     * Strings::startsWith('hello world', 'hello'); // true
     * Strings::startsWith('hello world', 'Hello'); // false (case-sensitive)
     * Strings::startsWith('abc', '');              // true (empty needle)
     * ```
     *
     * @edgecase If `$needle` is an empty string, the function will return `true` regardless of `$haystack`.
     *           This may be unintuitive but is consistent with how prefix checks are typically defined.
     *
     * @note This function is **case-sensitive**. If you need case-insensitive behavior,
     *       consider converting both strings to lowercase before comparison (e.g., using `strtolower()`).
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /**
     * Checks whether a string ends with a given substring.
     *
     * This method determines if the `$haystack` string ends with the specified `$needle`.
     * It compares the ending segment of `$haystack` (based on the length of `$needle`) to `$needle` itself.
     *
     * @param string $haystack The full string to evaluate.
     * @param string $needle   The substring to check for at the end of `$haystack`.
     *
     * @return bool Returns `true` if `$haystack` ends with `$needle`, or if `$needle` is an empty string; otherwise, `false`.
     *
     * @example
     * ```php
     * Strings::endsWith('filename.txt', '.txt'); // true
     * Strings::endsWith('filename.txt', '.TXT'); // false (case-sensitive)
     * Strings::endsWith('hello', '');            // true (empty needle)
     * ```
     *
     * @edgecase An empty `$needle` will always return `true`, regardless of `$haystack`. This behavior
     *           might be unexpected in some cases. It stems from the logic that any string "ends with" an empty string.
     *
     * @note This function is **case-sensitive**. For case-insensitive checks, consider converting both strings
     *       to a common case (`strtolower()` or `mb_strtolower()`) before comparison.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || str_ends_with($haystack, $needle);
    }


    /**
     * Determines whether a given substring exists within a larger string.
     *
     * This method checks if `$needle` is found anywhere within `$haystack`.
     * It uses `strpos()` under the hood, which is case-sensitive and returns
     * `false` if the substring is not found.
     *
     * @param string $haystack The string to search within.
     * @param string $needle   The substring to search for.
     *
     * @return bool Returns `true` if `$needle` exists within `$haystack`, otherwise `false`.
     *
     * @example
     * ```php
     * Strings::contains('hello world', 'world'); // true
     * Strings::contains('hello world', 'World'); // false (case-sensitive)
     * ```
     *
     * @edgecase If `$needle` is an empty string, this function will always return `true`,
     *           because `strpos()` treats an empty string as being found at the beginning of any string.
     *           Example: Strings::contains('anything', '') === true
     *
     * @note This function is **case-sensitive**. For case-insensitive checks,
     *       use `stripos()` instead of `strpos()`.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Truncates a string to a specified length and appends an ellipsis (or other trailing marker).
     *
     * This method uses multibyte-safe functions to truncate `$string` to the desired `$length`,
     * including space for the `$ellipsis` if the original string exceeds that length.
     * If the string is already within the limit, it is returned unchanged.
     *
     * @param string $string   The input string to truncate.
     * @param int    $length   The maximum length of the returned string, including the ellipsis.
     *                         Must be at least as long as the ellipsis itself.
     * @param string $ellipsis The string to append to the end of truncated text. Defaults to a single Unicode ellipsis character (`…`).
     *
     * @return string The truncated string, with the ellipsis appended if truncation occurred.
     *
     * @example
     * ```php
     * Strings::truncate('This is a long sentence.', 10);           // 'This is a…'
     * Strings::truncate('Short text', 20);                         // 'Short text'
     * Strings::truncate('Multibyte 💡 text here', 15);             // 'Multibyte 💡…'
     * Strings::truncate('Cut mid-word test', 7, '...');            // 'Cut...'
     * ```
     *
     * @edgecase If `$length` is less than or equal to the length of `$ellipsis`, the function
     *           may return just a portion of the ellipsis or an empty string. This can lead
     *           to unexpected results. Example: truncate('test', 2, '...') returns '.'.
     *
     * @note This method uses `mb_strlen()` and `mb_substr()` for proper multibyte (e.g., UTF-8) character handling.
     *       If your environment lacks the `mbstring` extension, it may produce incorrect behavior.
     */
    public static function truncate(string $string, int $length, string $ellipsis = '…'): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }
        return mb_substr($string, 0, $length - mb_strlen($ellipsis)) . $ellipsis;
    }

    /**
     * Cleans up excess whitespace from a string.
     *
     * This method collapses all sequences of whitespace (spaces, tabs, newlines) into a single space,
     * and trims leading and trailing whitespace. Useful for sanitizing user input or preparing strings for display.
     *
     * @param string $str The input string to clean.
     *
     * @return string A trimmed string with all internal whitespace normalized to single spaces.
     *
     * @example
     * ```php
     * Strings::clean("  This   is  \n a   test\tstring. "); // 'This is a test string.'
     * Strings::clean("No extra   spaces");                 // 'No extra spaces'
     * ```
     *
     * @edgecase If the input string contains only whitespace, the result will be an empty string.
     *           If the input is already clean, it is returned unchanged.
     *
     * @note This function does **not** remove or escape HTML tags, special characters, or punctuation—
     *       it deals with whitespace only.
     */
    public static function clean(string $str): string
    {
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    /**
     * Converts a string into camelCase format, intelligently handling various input styles.
     *
     * This method normalizes strings by converting delimiters (`-`, `_`, and spaces) into camelCase format.
     * If the input string already appears to be in camelCase or PascalCase (and contains no delimiters),
     * it will simply lowercase the first character to ensure camelCase conformity.
     *
     * @param string $str The input string to convert. May include delimiters or be already in PascalCase or camelCase.
     *
     * @return string The camelCased version of the string, with delimiters removed and word boundaries preserved.
     *
     * @example
     * ```php
     * Strings::camelCase('first_name');         // 'firstName'
     * Strings::camelCase('AlreadyCamelCase');   // 'alreadyCamelCase'
     * Strings::camelCase('user id');            // 'userId'
     * Strings::camelCase('super-duper-helper'); // 'superDuperHelper'
     * Strings::camelCase('JustWordsNoDelimiters'); // 'justWordsNoDelimiters'
     * ```
     *
     * @edgecase If the string contains no delimiters and already resembles camelCase or PascalCase,
     *           only the first character is lowercased.
     *           Empty strings will return as-is.
     *
     * @note This function does **not** alter internal capitalization of words in preformatted camelCase input.
     *       It assumes you want to preserve existing word boundaries unless delimiters indicate otherwise.
     */
    public static function camelCase(string $str): string
    {
        // If no delimiters exist and it already looks camelCased or PascalCased, leave it alone
        if (!preg_match('/[-_\s]/', $str)) {
            return lcfirst($str); // Only make sure the first char is lowercase
        }

        // Normalize to camelCase from delimited input
        $str = str_replace(['-', '_'], ' ', $str);
        $str = ucwords($str);
        $str = str_replace(' ', '', $str);
        return lcfirst($str);
    }


    /**
     * Converts a string into snake_case format.
     *
     * This method replaces all sequences of non-letter and non-number characters with underscores,
     * trims leading/trailing underscores, and lowercases the result. It uses a Unicode-aware regex,
     * making it suitable for international input as well.
     *
     * @param string $str The input string to convert. Can contain spaces, punctuation, symbols, etc.
     *
     * @return string A snake_cased version of the string, consisting of lowercase letters, numbers, and underscores.
     *
     * @example
     * ```php
     * Strings::snake_case('Hello World!');           // 'hello_world'
     * Strings::snake_case('already_snake_case');     // 'already_snake_case'
     * Strings::snake_case('Multiple---delimiters');  // 'multiple_delimiters'
     * Strings::snake_case('Ünicode & Symbols ™️');   // 'ünicode_symbols'
     * ```
     *
     * @edgecase Input with only non-alphanumeric characters (e.g., '!!!') will result in an empty string after trimming.
     *           Multiple adjacent non-word characters are collapsed into a single underscore.
     *
     * @note This function uses Unicode character classes (`\p{L}` for letters, `\p{N}` for numbers)
     *       and requires the `u` modifier to properly handle multibyte input.
     *       If the `mbstring` or `pcre` extension is misconfigured or missing, results may be unreliable.
     */
    public static function snake_case(string $str): string
    {
        $str = preg_replace('/[^\p{L}\p{N}]+/u', '_', $str);
        return strtolower(trim($str, '_'));
    }

    /**
     * Converts a string into kebab-case format.
     *
     * This method replaces all sequences of non-letter and non-number characters with hyphens,
     * trims leading and trailing hyphens, and lowercases the result. It's ideal for creating
     * SEO-friendly URL slugs or CSS class names from arbitrary input.
     *
     * @param string $str The input string to convert. May contain spaces, punctuation, or symbols.
     *
     * @return string A kebab-cased version of the string, with lowercase letters, numbers, and hyphens only.
     *
     * @example
     * ```php
     * Strings::kebabCase('Hello World!');           // 'hello-world'
     * Strings::kebabCase('already-kebab-case');     // 'already-kebab-case'
     * Strings::kebabCase('Multiple---delimiters');  // 'multiple-delimiters'
     * Strings::kebabCase('Ünicode & Symbols ™️');   // 'ünicode-symbols'
     * ```
     *
     * @edgecase If the input contains only symbols or whitespace, the result may be an empty string.
     *           Consecutive non-word characters are collapsed into a single hyphen.
     *
     * @note This function is Unicode-aware via `\p{L}` (letters) and `\p{N}` (numbers),
     *       and uses the `u` modifier for proper multibyte string handling.
     */
    public static function kebabCase(string $str): string
    {
        $str = preg_replace('/[^\p{L}\p{N}]+/u', '-', $str);
        return strtolower(trim($str, '-'));
    }

    /**
     * Determines whether a given string is valid JSON.
     *
     * This method attempts to decode the string using `json_decode()` and checks whether any errors occurred.
     * It returns `true` if the string is valid JSON, even if the decoded result is `null`, `false`, or another non-object value.
     *
     * @param string $str The string to test for JSON validity.
     *
     * @return bool Returns `true` if the string is valid JSON syntax; otherwise `false`.
     *
     * @example
     * ```php
     * Strings::isJson('{"key":"value"}');      // true
     * Strings::isJson('[1, 2, 3]');             // true
     * Strings::isJson('"Just a string"');      // true
     * Strings::isJson('null');                 // true
     * Strings::isJson('not json');             // false
     * ```
     *
     * @edgecase This function will return `true` for valid JSON *values* like `"true"`, `"42"`, or `"null"`,
     *           which may not be what some callers expect if they're only checking for objects or arrays.
     *
     * @note This does not check the *type* of JSON structure — only that the input is syntactically valid.
     *       To ensure the decoded result is an array or object, you must additionally inspect the result of `json_decode()`.
     */
    public static function isJson(string $str): bool
    {
        json_decode($str);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Compares two strings for equality, ignoring case differences.
     *
     * This method performs a case-insensitive comparison by converting both input strings to lowercase
     * before comparing them using strict equality (`===`).
     *
     * @param string $a The first string to compare.
     * @param string $b The second string to compare.
     *
     * @return bool Returns `true` if the strings are equal when case is ignored; otherwise `false`.
     *
     * @example
     * ```php
     * Strings::equalsIgnoreCase('Hello', 'hello');     // true
     * Strings::equalsIgnoreCase('Test', 'TEST');       // true
     * Strings::equalsIgnoreCase('abc', 'abC');         // true
     * Strings::equalsIgnoreCase('abc', 'abcd');        // false
     * ```
     *
     * @edgecase Locale-sensitive characters (like `ß`, `İ`, or accented letters) may not behave as expected
     *           with `strtolower()` depending on the environment and encoding. For full Unicode case folding,
     *           use `mb_strtolower()` with an appropriate locale.
     *
     * @note This function is ASCII-case-insensitive by default. It may not account for locale or multibyte edge cases
     *       unless `mbstring` functions are explicitly used.
     */
    public static function equalsIgnoreCase(string $a, string $b): bool
    {
        return strtolower($a) === strtolower($b);
    }

    /**
     * Checks whether a string starts with any of the given prefixes.
     *
     * This method iterates over an array of possible prefixes and returns `true` as soon as
     * one of them matches the beginning of the `$haystack` string. It uses the `startsWith()` method internally,
     * so comparison is case-sensitive by default.
     *
     * @param string $haystack The string to evaluate.
     * @param array  $prefixes An array of prefix strings to test against the start of `$haystack`.
     *
     * @return bool Returns `true` if `$haystack` starts with at least one of the specified prefixes; otherwise `false`.
     *
     * @example
     * ```php
     * Strings::hasPrefixAny('foobar', ['foo', 'bar']);     // true
     * Strings::hasPrefixAny('hello world', ['hi', 'hey']); // false
     * Strings::hasPrefixAny('data.json', ['data.', 'meta.']); // true
     * ```
     *
     * @edgecase An empty `$prefixes` array will always return `false` since there's nothing to check against.
     *           If any prefix in the array is an empty string, it will always match (per `startsWith()` logic),
     *           causing the function to return `true` immediately.
     *
     * @note This function performs a **case-sensitive** comparison.
     *       To make it case-insensitive, use a modified version of `startsWith()` with case normalization.
     */
    public static function hasPrefixAny(string $haystack, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (self::startsWith($haystack, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extracts the substring found between two delimiters within a string.
     *
     * This method searches for the first occurrence of `$start` and `$end` within `$str`,
     * and returns the substring between them. If either delimiter is not found in the proper order,
     * the function returns `null`.
     *
     * @param string $str   The full input string to search within.
     * @param string $start The starting delimiter. The result will begin immediately after this.
     * @param string $end   The ending delimiter. The result will end just before this.
     *
     * @return string|null Returns the substring between `$start` and `$end`, or `null` if the delimiters aren't found in order.
     *
     * @example
     * ```php
     * Strings::between('abc [target] def', '[', ']');     // 'target'
     * Strings::between('name: John, age: 42', 'name: ', ','); // 'John'
     * Strings::between('<<value>>', '<<', '>>');          // 'value'
     * ```
     *
     * @edgecase If `$start` is not found, or `$end` is not found after `$start`, the result is `null`.
     *           If `$start` and `$end` are the same and only appear once, or appear out of order, the result is `null`.
     *           If the string between delimiters is empty (e.g., `abc[]def`), an empty string is returned (`''`), not `null`.
     *
     * @note This method only captures the **first** matching pair of delimiters. It does not support nested or repeated patterns.
     *       The search is case-sensitive and non-greedy.
     */
    public static function between(string $str, string $start, string $end): ?string
    {
        $startPos = strpos($str, $start);
        if ($startPos === false) return null;
        $startPos += strlen($start);
        $endPos = strpos($str, $end, $startPos);
        if ($endPos === false) return null;
        return substr($str, $startPos, $endPos - $startPos);
    }

    /**
     * Pads a string on the left side to a specified total length using a given padding character.
     *
     * This method uses `str_pad()` with `STR_PAD_LEFT` to add the specified character to the beginning
     * of the string until the desired length is reached. If the original string is already at or longer
     * than the target length, it is returned unchanged.
     *
     * @param string $str    The input string to pad.
     * @param int    $length The total length of the resulting string after padding.
     * @param string $char   The character to pad with. Must be a non-empty string. Defaults to a space `' '`.
     *
     * @return string The left-padded string, or the original string if it is already equal to or longer than `$length`.
     *
     * @example
     * ```php
     * Strings::padLeft('42', 5);               // '   42'
     * Strings::padLeft('abc', 6, '0');         // '000abc'
     * Strings::padLeft('already long', 5);     // 'already long'
     * ```
     *
     * @edgecase If `$char` is an empty string, `str_pad()` will emit a warning and return `false`.
     *           Always ensure `$char` is a non-empty string.
     *
     * @note Padding is repeated as needed to fill the gap. If the padding string is longer than one character,
     *       it will be truncated if it doesn’t divide evenly into the padding length.
     */
    public static function padLeft(string $str, int $length, string $char = ' '): string
    {
        return str_pad($str, $length, $char, STR_PAD_LEFT);
    }

    /**
     * Pads a string on the right side to a specified total length using a given padding character.
     *
     * This method uses `str_pad()` with `STR_PAD_RIGHT` to add the specified character to the end
     * of the string until the desired length is reached. If the original string is already at or longer
     * than the target length, it is returned unchanged.
     *
     * @param string $str    The input string to pad.
     * @param int    $length The total desired length of the resulting string after padding.
     * @param string $char   The character to pad with. Must be a non-empty string. Defaults to a space `' '`.
     *
     * @return string The right-padded string, or the original string if it is already equal to or longer than `$length`.
     *
     * @example
     * ```php
     * Strings::padRight('42', 5);               // '42   '
     * Strings::padRight('abc', 6, '.');         // 'abc...'
     * Strings::padRight('already long', 5);     // 'already long'
     * ```
     *
     * @edgecase If `$char` is an empty string, `str_pad()` will emit a warning and return `false`.
     *           Be sure to use a non-empty string for padding.
     *
     * @note If the padding string is more than one character, it will repeat as needed and be truncated
     *       to fit the exact length.
     */
    public static function padRight(string $str, int $length, string $char = ' '): string
    {
        return str_pad($str, $length, $char, STR_PAD_RIGHT);
    }

    /**
     * Repeats a string a specified number of times.
     *
     * This method uses `str_repeat()` to duplicate the input string `$times` number of times.
     * It returns the concatenated result. If `$times` is 0 or less, an empty string is returned.
     *
     * @param string $str   The string to repeat.
     * @param int    $times The number of times to repeat the string. Should be a non-negative integer.
     *
     * @return string The repeated string, or an empty string if `$times` is 0 or negative.
     *
     * @example
     * ```php
     * Strings::repeat('abc', 3);     // 'abcabcabc'
     * Strings::repeat('*', 5);       // '*****'
     * Strings::repeat('xyz', 0);     // ''
     * ```
     *
     * @edgecase If `$times` is negative, `str_repeat()` returns an empty string without error.
     *           If `$str` is an empty string, the result will always be an empty string, regardless of `$times`.
     *
     * @note This function does not trim or insert separators between repetitions.
     *       If you want space- or delimiter-separated repetitions, use a loop or `implode()`.
     */
    public static function repeat(string $str, int $times): string
    {
        return str_repeat($str, $times);
    }

    /**
     * Converts a UTF-8 string to an ASCII-only representation by transliterating accented and special characters.
     *
     * This method uses `iconv()` with the `TRANSLIT` and `IGNORE` flags to attempt conversion of multibyte characters
     * into their closest ASCII equivalents (e.g., `ü` → `u`, `ñ` → `n`). Characters that cannot be transliterated are dropped.
     *
     * @param string $str The UTF-8 encoded input string to convert.
     *
     * @return string The ASCII-only version of the input string, with accents and special characters removed or replaced.
     *
     * @example
     * ```php
     * Strings::toAscii('Jalapeño');     // 'Jalapeno'
     * Strings::toAscii('français');     // 'francais'
     * Strings::toAscii('naïve café');   // 'naive cafe'
     * ```
     *
     * @edgecase Characters that cannot be represented in ASCII and are not translatable will be **silently discarded**.
     *           If the input string is already ASCII, the result will be identical.
     *
     * @note This function depends on the `iconv` extension. If `iconv()` is not available or fails, it may return `false`
     *       or an empty string, depending on the PHP environment.
     *       Also, behavior of transliteration may vary slightly across systems or locales.
     */
    public static function toAscii(string $str): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    }

    /**
     * Truncates a string after a specified number of words and appends an ellipsis (or other trailing marker).
     *
     * This method splits the input string into words using whitespace, and returns the first `$maxWords`
     * joined by spaces. If the input has fewer than or equal to `$maxWords`, it is returned unchanged.
     *
     * @param string $str       The input string to process.
     * @param int    $maxWords  The maximum number of words to retain.
     * @param string $ellipsis  The string to append if truncation occurs. Defaults to a Unicode ellipsis (`…`).
     *
     * @return string The truncated string if word count exceeds the limit, or the original string otherwise.
     *
     * @example
     * ```php
     * Strings::limitWords('The quick brown fox jumps over the lazy dog', 4);      // 'The quick brown fox…'
     * Strings::limitWords('Short sentence.', 5);                                   // 'Short sentence.'
     * Strings::limitWords('One two three four five', 3, '...');                    // 'One two three...'
     * ```
     *
     * @edgecase If `$maxWords` is zero or negative, the result will be just the ellipsis.
     *           If the input string is empty or contains only whitespace, it returns an empty string.
     *
     * @note Words are determined using `preg_split('/\s+/')`, so any sequence of whitespace is considered a separator.
     *       This method does not account for punctuation or HTML tags when splitting words.
     */
    public static function limitWords(string $str, int $maxWords, string $ellipsis = '…'): string
    {
        $words = preg_split('/\s+/', trim($str));
        if (count($words) <= $maxWords) return $str;
        return implode(' ', array_slice($words, 0, $maxWords)) . $ellipsis;
    }

    /**
     * Escapes special characters in a string for safe output in HTML.
     *
     * This method converts characters like `<`, `>`, `&`, `"`, and `'` into their HTML-encoded equivalents
     * to prevent XSS (Cross-Site Scripting) attacks or HTML injection. It uses `htmlspecialchars()` with
     * `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8 encoding for broad compatibility and safety.
     *
     * @param string $str The raw input string to escape for HTML output.
     *
     * @return string A safely escaped string suitable for embedding in HTML.
     *
     * @example
     * ```php
     * Strings::safeHtml('<script>alert("XSS")</script>'); // '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
     * Strings::safeHtml("Tom & Jerry's \"Cat\"");         // 'Tom &amp; Jerry&#039;s &quot;Cat&quot;'
     * ```
     *
     * @edgecase If the string contains invalid UTF-8 sequences, `ENT_SUBSTITUTE` ensures they are replaced with
     *           a Unicode replacement character (`�`) rather than causing encoding errors or breaking output.
     *
     * @note This method escapes both double and single quotes (`ENT_QUOTES`), making it safe for use in attribute values.
     *       Always use this (or similar escaping) when inserting untrusted content into HTML pages.
     */
    public static function safeHtml(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Strips all HTML tags from a string except those explicitly allowed.
     *
     * This method uses `strip_tags()` to remove all HTML and PHP tags from the input string,
     * while optionally preserving a whitelist of specified tags. Useful when sanitizing user input
     * while still permitting basic formatting (e.g., `<b>`, `<i>`, `<br>`).
     *
     * @param string $str         The input string potentially containing HTML.
     * @param string $allowedTags A string of allowed tags in angle brackets (e.g., `'<b><i><br>'`).
     *                            Tags must be lowercase and well-formed to be preserved.
     *
     * @return string The sanitized string with only the allowed tags preserved.
     *
     * @example
     * ```php
     * Strings::stripHtmlButAllow('<b>Hello</b> <script>bad()</script>', '<b>'); // '<b>Hello</b> bad()'
     * Strings::stripHtmlButAllow('<i>Italic</i><br><u>Underlined</u>', '<i><br>'); // '<i>Italic</i><br>Underlined'
     * Strings::stripHtmlButAllow('No HTML at all'); // 'No HTML at all'
     * ```
     *
     * @edgecase The `$allowedTags` string must use lowercase tag names — uppercase versions will not match and will be stripped.
     *           Any tag not listed exactly (e.g., `<BR>` or `<i class="foo">`) will be stripped, even if visually similar.
     *
     * @note This method does **not** validate or close malformed HTML. It simply removes disallowed tags without restructuring the DOM.
     *       For stricter or more sophisticated HTML filtering, consider using a dedicated HTML purifier library.
     */
    public static function stripHtmlButAllow(string $str, string $allowedTags = '<b><i><br>'): string
    {
        return strip_tags($str, $allowedTags);
    }

    /**
     * Converts a string to title case using multibyte-safe logic.
     *
     * This method uses `mb_convert_case()` with the `MB_CASE_TITLE` mode to capitalize the first letter of each word,
     * while converting the rest to lowercase. It supports UTF-8 encoded strings and is safe for multibyte characters.
     *
     * @param string $str The input string to convert to title case.
     *
     * @return string The title-cased version of the input string.
     *
     * @example
     * ```php
     * Strings::titleCase('hello world');        // 'Hello World'
     * Strings::titleCase('mULTIbYTE çharacters'); // 'Multibyte Çharacters'
     * Strings::titleCase('123 go!');            // '123 Go!'
     * ```
     *
     * @edgecase Words that start with non-letter characters (e.g., numbers or punctuation) will remain unchanged at the beginning,
     *           and only subsequent alphabetic characters will be affected.
     *
     * @note This function uses `mb_convert_case()` with `'UTF-8'` encoding and requires the `mbstring` extension.
     *       It lowercases the rest of each word — so acronyms or intentional capitalization (e.g., “iPhone”) may be normalized.
     */
    public static function titleCase(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
    }

    /**
     * Validates whether a given string is a well-formed email address.
     *
     * This method uses `filter_var()` with the `FILTER_VALIDATE_EMAIL` flag to check
     * if the input string adheres to the general format of an email address (e.g., `user@example.com`).
     *
     * @param string $str The input string to validate as an email address.
     *
     * @return bool Returns `true` if the input is a valid email address, otherwise `false`.
     *
     * @example
     * ```php
     * Strings::isEmail('user@example.com');    // true
     * Strings::isEmail('bad-email@');          // false
     * Strings::isEmail('user@localhost');      // false (fails under strict RFC rules)
     * Strings::isEmail('user.name+tag@domain.com'); // true
     * ```
     *
     * @edgecase Some technically valid but rare email formats may fail (e.g., Unicode domains, quoted local-parts),
     *           depending on PHP version and `filter_var()` implementation.
     *
     * @note This validation checks basic syntax only. It does not verify whether the domain exists
     *       or whether the email address is deliverable.
     */
    public static function isEmail(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates whether a given string is a well-formed URL.
     *
     * This method uses `filter_var()` with the `FILTER_VALIDATE_URL` flag to determine
     * if the input string conforms to standard URL formatting (e.g., includes a valid scheme and host).
     *
     * @param string $str The input string to validate as a URL.
     *
     * @return bool Returns `true` if the input is a valid URL, otherwise `false`.
     *
     * @example
     * ```php
     * Strings::isUrl('https://example.com');         // true
     * Strings::isUrl('http://localhost:8000/test');  // true
     * Strings::isUrl('ftp://example.com/resource');  // true
     * Strings::isUrl('www.example.com');             // false (missing scheme)
     * ```
     *
     * @edgecase URLs without a scheme (like `www.example.com`) will fail, even though browsers accept them.
     *           URLs with uncommon schemes or IP addresses may be flagged as invalid depending on the environment.
     *
     * @note This method only checks the **syntactic validity** of the URL — it does not confirm that the domain exists
     *       or that the URL is reachable.
     */
    public static function isUrl(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validates whether a given string is a well-formed UUID (version 1 through 5).
     *
     * This method uses a regular expression to check that the input matches the standard UUID format:
     * `xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx`, where:
     * - `M` is a version digit (1–5)
     * - `N` is a variant digit (8, 9, a, or b)
     *
     * @param string $str The string to validate as a UUID.
     *
     * @return bool Returns `true` if the string is a valid version 1–5 UUID, otherwise `false`.
     *
     * @example
     * ```php
     * Strings::isUuid('550e8400-e29b-41d4-a716-446655440000'); // true
     * Strings::isUuid('not-a-uuid');                           // false
     * Strings::isUuid('123e4567-e89b-12d3-a456-426614174000'); // true
     * ```
     *
     * @edgecase This validator only accepts UUIDs with hyphens and lowercase/uppercase hex characters.
     *           It will reject nil UUIDs (`00000000-0000-0000-0000-000000000000`) if version or variant bits don't conform.
     *
     * @note This checks only the **format and version compliance** — it does not guarantee the UUID was generated
     *       correctly or is unique. Adjust the regex if you need to accept other UUID variants (e.g., version 0 or nil UUIDs).
     */
    public static function isUuid(string $str): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $str) === 1;
    }

    /**
     * Returns the number of characters in a string, using multibyte-safe logic.
     *
     * This method uses `mb_strlen()` to count characters rather than bytes, making it safe for UTF-8
     * and other multibyte encodings. It's suitable for accurately measuring the visible or logical
     * length of a string containing Unicode characters.
     *
     * @param string $str The input string to measure.
     *
     * @return int The number of characters in the string.
     *
     * @example
     * ```php
     * Strings::length('Hello');          // 5
     * Strings::length('💡 Idea!');       // 7
     * Strings::length('naïve');          // 5
     * ```
     *
     * @edgecase If the input string is empty, the result will be 0.
     *
     * @note This method depends on the `mbstring` extension. If it's not available,
     *       `mb_strlen()` may cause a fatal error or return incorrect results.
     */
    public static function length(string $str): int
    {
        return mb_strlen($str);
    }

    /**
     * Counts the number of words in a string, ignoring any HTML tags.
     *
     * This method first strips all HTML tags from the input using `strip_tags()`, then counts
     * the remaining words using `str_word_count()`. A "word" is any sequence of characters considered
     * a word by `str_word_count()`, which primarily matches ASCII letter sequences.
     *
     * @param string $str The input string to analyze. Can include HTML.
     *
     * @return int The number of words in the plain-text version of the string.
     *
     * @example
     * ```php
     * Strings::wordCount('Hello world!');                       // 2
     * Strings::wordCount('<p>Hello <strong>world</strong></p>'); // 2
     * Strings::wordCount('Él vive en México.');                 // 4 (may vary if multibyte-aware logic is needed)
     * ```
     *
     * @edgecase `str_word_count()` may undercount or skip words that contain accented characters, emojis,
     *           or characters outside the basic ASCII range unless locale settings are adjusted.
     *           HTML entities like `&amp;` are preserved and may count as words depending on context.
     *
     * @note This method is not multibyte-aware and may give inaccurate results for non-English or
     *       non-ASCII content. For full Unicode-aware word counting, consider using `preg_match_all()`
     *       with a Unicode word boundary regex.
     */
    public static function wordCount(string $str): int
    {
        return str_word_count(strip_tags($str));
    }

    /**
     * Removes all occurrences of the specified characters from a string.
     *
     * This method uses `str_replace()` to remove every instance of each character (or substring)
     * listed in the `$chars` array from the input string. It is a simple way to filter out unwanted characters.
     *
     * @param string $str   The input string to clean.
     * @param array  $chars An array of characters or substrings to remove from the input.
     *
     * @return string The resulting string after all specified characters/substrings are removed.
     *
     * @example
     * ```php
     * Strings::strip('hello world', ['l', ' ']);     // 'heoworld'
     * Strings::strip('(123) 456-7890', ['(', ')', '-', ' ']); // '1234567890'
     * Strings::strip('abcXYZ123', ['a', '1', 'Z']);  // 'bcXY23'
     * ```
     *
     * @edgecase If `$chars` is an empty array, the input string is returned unchanged.
     *           If any of the values in `$chars` are longer substrings (e.g., `'abc'`), entire matches will be removed.
     *
     * @note This function performs exact, case-sensitive replacements. If you need case-insensitive stripping,
     *       consider normalizing the input and `$chars` array to lowercase or using `str_ireplace()`.
     */
    public static function strip(string $str, array $chars): string
    {
        return str_replace($chars, '', $str);
    }

    /**
     * Determines whether a string matches a given regular expression pattern.
     *
     * This method uses `preg_match()` to test whether the input string matches the specified pattern.
     * It returns `true` if the pattern matches at least once, and `false` otherwise.
     *
     * @param string $str     The input string to evaluate.
     * @param string $pattern A valid regular expression pattern, including delimiters (e.g., `'/^abc/i'`).
     *
     * @return bool Returns `true` if the pattern matches the string; otherwise `false`.
     *
     * @example
     * ```php
     * Strings::matchesPattern('hello123', '/\d+/');     // true
     * Strings::matchesPattern('abc', '/^a/');           // true
     * Strings::matchesPattern('test', '/\d+/');         // false
     * Strings::matchesPattern('Test', '/test/i');       // true (case-insensitive)
     * ```
     *
     * @edgecase If the pattern is invalid (e.g., bad syntax or delimiters), `preg_match()` will return `false`
     *           and may trigger a warning or error depending on error reporting settings.
     *
     * @note Always validate or escape user-supplied patterns before using them, to avoid security issues
     *       or unintended behavior. Patterns must include valid delimiters and optional flags.
     */
    public static function matchesPattern(string $str, string $pattern): bool
    {
        return preg_match($pattern, $str) === 1;
    }

    /**
     * Extracts a contextual excerpt around the first occurrence of a query string.
     *
     * This method finds the first occurrence of `$query` (case-insensitive) in `$str`,
     * then returns a snippet of text surrounding it — with up to `$radius` characters
     * before and after the match — wrapped in ellipses.
     *
     * @param string $str    The full text to search within.
     * @param string $query  The substring to find within the text.
     * @param int    $radius The number of characters to include before and after the match. Defaults to 50.
     *
     * @return string A trimmed excerpt centered around the match, wrapped in `...`, or an empty string if no match is found.
     *
     * @example
     * ```php
     * Strings::excerpt('The quick brown fox jumps over the lazy dog', 'fox');
     * // '...quick brown fox jumps over the lazy...'
     *     *
     * Strings::excerpt('Nothing to see here', 'missing');
     * // ''
     * ```
     *
     * @edgecase If the query isn't found, the function returns an empty string.
     *           If the match is near the start or end of the string, the excerpt may be shorter than expected.
     *           If `$query` appears multiple times, only the first match is considered.
     *
     * @note This method does not highlight the query term or ensure word boundaries — it’s intended for simple previews.
     *       Ellipses are always added at both ends, even if the excerpt begins at position 0 or ends at the string’s end.
     */
    public static function excerpt(string $str, string $query, int $radius = 50): string
    {
        $pos = stripos($str, $query);
        if ($pos === false) return '';
        $start = max(0, $pos - $radius);
        $excerpt = substr($str, $start, $radius * 2 + strlen($query));
        return '...' . trim($excerpt) . '...';
    }

    /**
     * Wraps long text inside HTML content without breaking tags.
     *
     * This method applies `wordwrap()` only to text content found between HTML tags,
     * preserving the structure of the HTML while wrapping visible text at the specified width.
     * It avoids breaking tags or inserting line breaks within HTML elements.
     *
     * @param string $str   The HTML-containing string to wrap.
     * @param int    $width The maximum line width before inserting a break. Defaults to 75 characters.
     * @param string $break The string to insert as the break. Defaults to a newline character (`"\n"`).
     *
     * @return string The HTML string with visible text word-wrapped, leaving tags untouched.
     *
     * @example
     * ```php
     * Strings::wordWrapHtmlSafe('<p>This is a very long sentence that needs to wrap properly within HTML.</p>', 20);
     * // '<p>This is a very long\nsentence that needs to\nwrap properly within\nHTML.</p>'
     * ```
     *
     * @edgecase Only visible text between tags is affected. Content inside tags like `<script>` or `<style>` may not be handled as expected.
     *           Text inside attributes (e.g., `title="..."`) is not affected or wrapped.
     *           If the HTML is malformed (e.g., unclosed tags), behavior may be unpredictable.
     *
     * @note This function uses a regular expression to match content between `>` and `<`,
     *       so it assumes reasonably well-formed HTML. It's designed for basic formatting tasks
     *       — not for deeply nested or edge-case HTML.
     */
    public static function wordWrapHtmlSafe(string $str, int $width = 75, string $break = "\n"): string
    {
        return preg_replace_callback('/>([^<]+)</', function ($matches) use ($width, $break) {
            return '>' . wordwrap($matches[1], $width, $break, true) . '<';
        }, $str);
    }

    /**
     * Normalizes all whitespace in a string to single spaces and trims the result.
     *
     * This method collapses all consecutive whitespace characters (spaces, tabs, newlines, etc.)
     * into a single space using a Unicode-aware regular expression. Leading and trailing whitespace
     * are also removed with `trim()`.
     *
     * @param string $str The input string to normalize.
     *
     * @return string A clean string with all internal whitespace reduced to single spaces, and no leading or trailing whitespace.
     *
     * @example
     * ```php
     * Strings::normalizeWhitespace("  This\t is\n\nmessy   text. ");  // 'This is messy text.'
     * Strings::normalizeWhitespace("Line 1\nLine 2\r\nLine 3");       // 'Line 1 Line 2 Line 3'
     * ```
     *
     * @edgecase If the input string contains only whitespace, the result will be an empty string.
     *           Unicode whitespace (e.g., non-breaking space `\u00A0`) is treated as whitespace due to the `\s` pattern and `u` modifier.
     *
     * @note The regular expression uses the `u` (Unicode) modifier for multibyte safety.
     *       This function is ideal for sanitizing user input or normalizing content before display or storage.
     */
    public static function normalizeWhitespace(string $str): string
    {
        return trim(preg_replace('/\s+/u', ' ', $str));
    }

    /**
     * Converts a string to a slug and appends an identifier, forming a slug-ID composite string.
     *
     * This method applies `slugify()` to the input string to generate a URL-friendly slug, then appends
     * a hyphen followed by the provided `$id`. It is commonly used for creating SEO-friendly URLs
     * that include both readable text and a unique identifier.
     *
     * @param string     $str The input string to convert into a slug.
     * @param int|string $id  The identifier to append. Typically a numeric ID, but any scalar value can be used.
     *
     * @return string The combined slug and ID string, formatted as `slugified-string-id`.
     *
     * @example
     * ```php
     * Strings::toSlugId('Hello World', 42);         // 'hello-world-42'
     * Strings::toSlugId('Ünicode Title', 'abc123'); // 'unicode-title-abc123'
     * ```
     *
     * @edgecase If `$str` results in an empty slug (e.g., if it only contains symbols), the result will be `-$id`.
     *           If `$id` is not scalar, it will be coerced to string, which may produce unintended output (e.g., `'Array'` or `'Object'`).
     *
     * @note This method relies on `self::slugify()`, which removes special characters and collapses whitespace.
     *       Be sure to validate or sanitize `$id` if used in URLs or filenames.
     */
    public static function toSlugId(string $str, int|string $id): string
    {
        return self::slugify($str) . '-' . $id;
    }

    /**
     * Wraps a string with a given prefix and suffix.
     *
     * This method prepends the specified `$prefix` and appends the `$suffix` to the input string.
     * It is useful for surrounding content with quotation marks, brackets, tags, or other markers.
     *
     * @param string $str    The input string to wrap.
     * @param string $prefix The string to prepend.
     * @param string $suffix The string to append.
     *
     * @return string The resulting string with `$prefix` and `$suffix` added.
     *
     * @example
     * ```php
     * Strings::wrap('value', '[', ']');         // '[value]'
     * Strings::wrap('text', '"', '"');          // '"text"'
     * Strings::wrap('hello', '<b>', '</b>');    // '<b>hello</b>'
     * ```
     *
     * @edgecase If `$str` is an empty string, only the prefix and suffix are returned (e.g., `wrap('', '[', ']')` → `'[]'`).
     *           If either `$prefix` or `$suffix` is empty, only the non-empty part is added.
     *
     * @note This method does not escape or validate the input or wrappers — it's a raw string operation.
     */
    public static function wrap(string $str, string $prefix, string $suffix): string
    {
        return $prefix . $str . $suffix;
    }

    /**
     * Converts an integer into its ordinal string representation (e.g., 1 → "1st", 2 → "2nd").
     *
     * This method appends the appropriate ordinal suffix (`st`, `nd`, `rd`, `th`) to a number,
     * handling special exceptions for 11, 12, and 13, which always use `th` regardless of the last digit.
     *
     * @param int $number The integer to convert.
     *
     * @return string The ordinal representation of the number (e.g., "21st", "42nd", "113th").
     *
     * @example
     * ```php
     * Strings::ordinal(1);   // '1st'
     * Strings::ordinal(2);   // '2nd'
     * Strings::ordinal(3);   // '3rd'
     * Strings::ordinal(4);   // '4th'
     * Strings::ordinal(11);  // '11th'
     * Strings::ordinal(22);  // '22nd'
     * Strings::ordinal(113); // '113th'
     * ```
     *
     * @edgecase Numbers ending in 11, 12, or 13 always return `'th'` due to English ordinal rules,
     *           even though they end in 1, 2, or 3 (e.g., 111 → `'111th'`, not `'111st'`).
     *
     * @note This method assumes input is a non-negative integer. Negative numbers will still return a valid string
     *       with the correct suffix, but the format (e.g., `-1st`) may not be meaningful in all contexts.
     */
    public static function ordinal(int $number): string
    {
        $suffix = ['th','st','nd','rd','th','th','th','th','th','th'];
        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return $number . 'th';
        }
        return $number . $suffix[$number % 10];
    }

    /**
     * Centers a string within a field of a given width using a specified padding character.
     *
     * This method calculates how much space is needed on each side of the string and pads
     * it symmetrically (or near-symmetrically if the total padding is odd). Padding is added
     * with the specified character, defaulting to a space.
     *
     * @param string $str   The input string to center.
     * @param int    $width The total width of the resulting string, including padding.
     * @param string $pad   The character used for padding. Must be a non-empty string. Defaults to a space `' '`.
     *
     * @return string The centered string, padded to the specified width. If `$width` is less than or equal
     *                to the string's length, the original string is returned unchanged.
     *
     * @example
     * ```php
     * Strings::center('hello', 11);         // '   hello   '
     * Strings::center('hello', 10, '-');    // '---hello--'
     * Strings::center('text', 4);           // 'text'
     * ```
     *
     * @edgecase If the padding character is an empty string, `str_repeat()` will emit a warning and return `false`.
     *           If the total padding is odd, the right side will receive one extra character.
     *
     * @note This method uses `mb_strlen()` to ensure multibyte character safety when calculating width.
     */
    public static function center(string $str, int $width, string $pad = ' '): string
    {
        $padding = $width - mb_strlen($str);
        if ($padding <= 0) return $str;
        $left = (int)floor($padding / 2);
        $right = $padding - $left;
        return str_repeat($pad, $left) . $str . str_repeat($pad, $right);
    }

    /**
     * Masks all but the last few characters of a string using a specified mask character.
     *
     * This method replaces all characters in the string with a masking character (e.g., `*`),
     * except for the last `$visible` characters. It's commonly used for partially hiding sensitive data
     * like credit card numbers or email usernames.
     *
     * @param string $str       The input string to mask.
     * @param int    $visible   The number of characters to leave visible at the end of the string. Defaults to 4.
     * @param string $maskChar  The character to use for masking. Defaults to `'*'`.
     *
     * @return string The masked string, with all but the last `$visible` characters replaced by `$maskChar`.
     *
     * @example
     * ```php
     * Strings::mask('123456789', 4);       // '*****6789'
     * Strings::mask('secret', 2, '#');     // '####et'
     * Strings::mask('short', 10);          // '*****' (input too short to preserve visibility)
     * ```
     *
     * @edgecase If `$visible` is greater than or equal to the length of `$str`, the entire string will be masked.
     *           If `$maskChar` is an empty string, `str_repeat()` will emit a warning and return `false`.
     *
     * @note This method uses `mb_strlen()` and `mb_substr()` to ensure multibyte safety.
     *       Always validate `$maskChar` to avoid unexpected behavior when empty.
     */
    public static function mask(string $str, int $visible = 4, string $maskChar = '*'): string
    {
        $len = mb_strlen($str);
        if ($visible >= $len) return str_repeat($maskChar, $len);
        return str_repeat($maskChar, $len - $visible) . mb_substr($str, -$visible);
    }

    /**
     * Toggles the case of each character in a string.
     *
     * This method loops through each character of the input string, converting
     * uppercase letters to lowercase and lowercase letters to uppercase.
     * Non-alphabetic characters are left unchanged.
     *
     * @param string $str The input string to toggle.
     *
     * @return string A new string with the case of each letter inverted.
     *
     * @example
     * ```php
     * Strings::toggleCase('Hello World');   // 'hELLO wORLD'
     * Strings::toggleCase('123abcABC');     // '123ABCabc'
     * Strings::toggleCase('!@#');           // '!@#' (unchanged)
     * ```
     *
     * @edgecase Multibyte characters (e.g., accented or non-Latin characters) are not supported correctly,
     *           since `str_split()` and `ctype_upper()` operate on single bytes. Characters like `é` or `ß`
     *           may be split incorrectly or ignored.
     *
     * @note This function is **not multibyte safe**. For Unicode-safe case toggling,
     *       a more robust approach using `mb_*` functions and normalization would be needed.
     */
    public static function toggleCase(string $str): string
    {
        return implode('', array_map(function ($c) {
            return ctype_upper($c) ? strtolower($c) : strtoupper($c);
        }, str_split($str)));
    }

    /**
     * Calculates the percentage similarity between two strings.
     *
     * This method uses PHP’s `similar_text()` function to determine how similar two strings are,
     * returning a float percentage from 0 to 100 representing their likeness.
     *
     * @param string $a The first string to compare.
     * @param string $b The second string to compare.
     *
     * @return float A similarity percentage between 0 and 100, where 100 means the strings are identical.
     *
     * @example
     * ```php
     * Strings::similarity('apple', 'apples');      // 91.666...
     * Strings::similarity('hello', 'yellow');      // 60.0
     * Strings::similarity('cat', 'dog');           // 0.0
     * ```
     *
     * @edgecase An empty string compared with another string results in `0.0`.
     *           Case differences are treated as mismatches — the comparison is case-sensitive.
     *
     * @note This function uses a time-consuming algorithm for long strings, as `similar_text()` has O(N^3) complexity.
     *       For faster or fuzzy comparisons, consider `levenshtein()` or external string similarity libraries.
     */
    public static function similarity(string $a, string $b): float
    {
        similar_text($a, $b, $percent);
        return $percent;
    }

    /**
     * Sanitizes a string for use as a safe filename by replacing disallowed characters with underscores.
     *
     * This method replaces any character that is not a letter, digit, underscore (`_`), hyphen (`-`), or dot (`.`)
     * with an underscore. It helps prevent issues with file systems, URLs, or uploads that reject unsafe characters.
     *
     * @param string $str The input string to sanitize.
     *
     * @return string A filename-safe version of the input string.
     *
     * @example
     * ```php
     * Strings::sanitizeFilename('My Report (Final).pdf');    // 'My_Report__Final_.pdf'
     * Strings::sanitizeFilename('user@domain.com');          // 'user_domain.com'
     * Strings::sanitizeFilename('file name*&^%.txt');        // 'file_name____.txt'
     * ```
     *
     * @edgecase If the input contains only disallowed characters, the result will be all underscores.
     *           This method does not collapse consecutive underscores or trim the result — it is literal.
     *
     * @note This method does not check for reserved filenames (like `CON`, `NUL`, or `COM1` on Windows),
     *       nor does it enforce length limits. Consider additional validation if targeting specific filesystems.
     */
    public static function sanitizeFilename(string $str): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $str);
    }


    /**
     * Normalizes all newline characters in a string to CRLF (`\r\n`) format.
     *
     * This method first converts all carriage returns (`\r`) to line feeds (`\n`) to avoid producing
     * duplicate carriage returns when normalizing. It then replaces all line feeds with CRLF sequences.
     * This is useful for ensuring consistent line endings for systems (like Windows or email protocols)
     * that expect CRLF format.
     *
     * @param string $input The input string that may contain mixed or inconsistent newline characters.
     *
     * @return string The string with all newlines normalized to `\r\n`.
     *
     * @example
     * ```php
     * Strings::normalize_newlines_to_crlf("Line 1\rLine 2\nLine 3\r\n");
     * // "Line 1\r\nLine 2\r\nLine 3\r\n"
     * ```
     *
     * @edgecase Any existing `\r\n` sequences will be preserved correctly after transformation.
     *           Lone `\r` or `\n` sequences will be safely converted into consistent `\r\n`.
     *
     * @note This method is useful when preparing files for Windows environments, email headers,
     *       or protocols where CRLF is the required newline format.
     */
    public static function normalize_newlines_to_crlf(string $input): string {
        // First, convert all lone CR to LF (to avoid creating \r\r\n below)
        $input = str_replace("\r", "\n", $input);
        // Then, convert all LF to CRLF
        return str_replace("\n", "\r\n", $input);
    }

    /**
     * Generates a cryptographically secure random string of a given length, optionally with a prefix.
     *
     * This method builds a random string using characters from the specified `$characterPool`.
     * If a `$prefix` is provided, it is prepended to the result and counts toward the total length.
     * The function ensures the final string is exactly `$length` characters long, trimming or limiting
     * as needed. Uses `random_int()` to ensure cryptographic security.
     *
     * @param int    $length        The total desired length of the output string, including prefix. Defaults to 64.
     * @param string $prefix        A string to prepend to the output. The remaining characters will be randomly generated.
     * @param string $characterPool A string containing the characters to randomly choose from. Defaults to a URL-safe, unambiguous set (no `0`, `O`, `l`, `1`).
     *
     * @return string A random string of exactly `$length` characters, starting with `$prefix` if provided.
     *
     * @example
     * ```php
     * Strings::randomString(10);                       // e.g., 'a8B7jk3Mnp'
     * Strings::randomString(16, 'id_');                // e.g., 'id_7GkP8xLqJ93R'
     * Strings::randomString(5, '', 'ABC123');          // e.g., 'C1BA2'
     * ```
     *
     * @edgecase If `$characterPool` is empty or `$length <= strlen($prefix)`, the method returns the
     *           prefix truncated or padded to the desired length.
     *
     * @note This method uses PHP's `random_int()` and **is cryptographically secure**, making it suitable
     *       for tokens, nonces, session keys, or other security-sensitive purposes.
     *       However, it does **not** guarantee uniqueness. For universally unique identifiers, use a UUID generator.
     *
     * @throws Exception
     */
    public static function randomString(int $length = 64, string $prefix = '', string $characterPool = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'): string
    {
        $randomString = $prefix;
        $poolLength = strlen($characterPool);

        if ($poolLength === 0 || $length <= strlen($randomString)) {
            return substr($randomString, 0, $length);
        }

        $charsToAdd = $length - strlen($randomString);
        for ($i = 0; $i < $charsToAdd; $i++) {
            $randomChar = $characterPool[random_int(0, $poolLength - 1)];
            $randomString .= $randomChar;
        }

        return $randomString;
    }

    /**
     * Replaces the beginning of a string with a new prefix of the same length.
     *
     * This method replaces the first N characters of `$original` with the entire `$newFrontPiece`,
     * where N is the length of `$newFrontPiece`. The rest of the original string remains unchanged.
     *
     * @param string $original      The original string whose beginning will be replaced.
     * @param string $newFrontPiece The string that will replace the first part of `$original`. Its full length is used.
     *
     * @return string The resulting string with the beginning replaced by `$newFrontPiece`.
     *
     * @example
     * ```php
     * Strings::replaceFirstPartOfString('username123', 'admin');   // 'adminme123'
     * Strings::replaceFirstPartOfString('abcdef', 'XY');           // 'XYcdef'
     * ```
     *
     * @edgecase If `$newFrontPiece` is longer than `$original`, the result will include all of `$newFrontPiece`,
     *           followed by an empty string from `substr()`. This may result in truncation or unexpected output.
     *           If `$newFrontPiece` is an empty string, the result is identical to `$original`.
     *
     * @note This is a low-level utility that performs raw byte-length replacement (via `strlen()` and `substr()`),
     *       not multibyte-safe. If working with UTF-8 strings containing multibyte characters, consider using
     *       `mb_strlen()` and `mb_substr()` for accuracy.
     */
    public static function replaceFirstPartOfString(string $original, string $newFrontPiece): string
    {
        $lengthOfNewFrontPiece = strlen($newFrontPiece);
        return $newFrontPiece . substr($original, $lengthOfNewFrontPiece);
    }


    /**
     * Generates a RFC 4122–compliant UUID (version 4), with optional casing.
     *
     * UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * - 4 indicates version 4 (random-based)
     * - y is one of 8, 9, A, or B (variant 1)
     *
     * @param bool $uppercase If true (default), returns UUID in uppercase. If false, returns lowercase.
     *
     * @return string A 36-character UUID v4 string.
     *
     * @example
     * ```php
     * Strings::uuid();              // '3F6C0A7E-9F57-42DB-9A73-91F3A984D317'
     * Strings::uuid(false);         // '3f6c0a7e-9f57-42db-9a73-91f3a984d317'
     * ```
     *
     * @throws Exception;
     */
    public static function uuid(bool $uppercase = true): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Set version to 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Set variant to 10

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $uppercase ? strtoupper($uuid) : strtolower($uuid);
    }

    /**
     * Generates a UUID version 7 (time-ordered + random) based on the draft UUIDv7 spec.
     *
     * UUIDv7 uses:
     * - 48 bits: milliseconds since Unix epoch
     * - 12 bits: version and partial randomness
     * - 62 bits: additional randomness
     *
     * This format is ideal for systems that require globally unique, time-sortable identifiers.
     *
     * @param bool $uppercase Whether to return the UUID in uppercase. Defaults to true.
     *
     * @return string A 36-character RFC-style UUIDv7 string.
     *
     * @example
     * ```php
     * Strings::uuidv7();        // '017F22E2-79B0-7CC3-98C4-DC0C0C07398F'
     * Strings::uuidv7(false);   // '017f22e2-79b0-7cc3-98c4-dc0c0c07398f'
     * ```
     *
     * @throws Exception
     */
    public static function uuidv7(bool $uppercase = true): string
    {
        $timestamp = (int) (microtime(true) * 1000); // Unix time in milliseconds
        $timeHex = str_pad(dechex($timestamp), 12, '0', STR_PAD_LEFT);

        $randBytes = random_bytes(10);
        $randHex = bin2hex($randBytes);

        // Insert version (7) into bits 48–51
        $timeHex[12] = '7';

        // Insert variant (10xx) into bits 64–65 (first nibble of 9th byte)
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
     * Generates a ULID (Universally Unique Lexicographically Sortable Identifier).
     *
     * A ULID is a 26-character Base32-encoded string composed of:
     * - 48 bits: timestamp (in milliseconds since Unix epoch)
     * - 80 bits: cryptographically secure random data
     *
     * It sorts lexicographically and is safe for use in filenames, URLs, database keys, and logs.
     *
     *
     * @param bool     $uppercase  Whether to return the ULID in uppercase (default) or lowercase.
     *
     * @return string A 26-character ULID string.
     *
     * @example
     * ```php
     * Strings::ulid();                              // e.g., '01HZX7RCEJMBFZZC1P4EW6Q7NH'
     * Strings::ulid(1700000000000);                 // ULID with a fixed timestamp
     * Strings::ulid(null, false);                   // Lowercase ULID using current timestamp
     * Strings::ulid(1700000000000, false);          // '01hzb5xq4rg23ndgjtw4ht9q4f'
     * ```
     *
     * @throws Exception
     */
   public static function ulid(bool $uppercase = true): string
    {
        // Get timestamp in milliseconds (48 bits)
        $time = (int)(microtime(true) * 1000);
        $timeBytes = pack('J', $time); // 8 bytes (we only need the last 6)

        // Get 80 bits (10 bytes) of randomness
        $random = random_bytes(10);

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
     * Extracts the timestamp (in milliseconds since Unix epoch) from a ULID string.
     *
     * The first 10 characters of a ULID encode a 48-bit timestamp using Crockford Base32.
     * This method decodes it back into an integer Unix timestamp in milliseconds.
     *
     * @param string $ulid A valid 26-character ULID string.
     *
     * @return int The timestamp in milliseconds since epoch.
     *
     * @throws InvalidArgumentException If the input is not a valid ULID.
     *
     * @example
     * ```php
     * $ulid = Strings::ulid(1700000000000);
     * $ms = Strings::ulidTimestamp($ulid); // 1700000000000
     * ```
     */
    public static function ulidTimestamp(string $ulid): int
    {
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $ulid)) {
            throw new InvalidArgumentException("Invalid ULID format.");
        }

        $ulid = strtoupper(substr($ulid, 0, 10)); // First 10 chars = timestamp

        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $charMap = array_flip(str_split($alphabet));

        $bits = '';
        foreach (str_split($ulid) as $char) {
            if (!isset($charMap[$char])) {
                throw new InvalidArgumentException("Invalid character in ULID: $char");
            }
            $bits .= str_pad(decbin($charMap[$char]), 5, '0', STR_PAD_LEFT);
        }

        return bindec(substr($bits, 0, 48));
    }

    /**
     * Extracts the timestamp from a ULID and returns it as a DateTimeImmutable object.
     *
     * This is useful for converting a ULID into a human-readable or formatted datetime.
     *
     * @param string $ulid A valid 26-character ULID.
     *
     * @return DateTimeImmutable A UTC DateTimeImmutable representing the ULID's timestamp.
     *
     * @throws InvalidArgumentException If the ULID is not valid.
     *
     * @example
     * ```php
     * $ulid = Strings::ulid(); // e.g., '01HZX7RCEJMBFZZC1P4EW6Q7NH'
     * $dt = Strings::ulidTimestampToDateTime($ulid);
     * echo $dt->format('Y-m-d H:i:s'); // e.g., "2023-11-14 21:33:20"
     * ```
     *
     * @throws Exception
     */
    public static function ulidTimestampToDateTime(string $ulid): DateTimeImmutable
    {
        $ms = self::ulidTimestamp($ulid); // Reuse existing method
        $sec = intdiv($ms, 1000);
        $micro = ($ms % 1000) * 1000;

        return (new DateTimeImmutable('@' . $sec))->setTimezone(new DateTimeZone('UTC'))->modify("+$micro microseconds");
    }

    /**
     * Determines whether a string is blank (empty or contains only whitespace).
     *
     * This method trims the string and checks if anything remains.
     * Returns true for strings like "", "   ", "\n", or "\t".
     *
     * @param string $str The input string to check.
     *
     * @return bool True if the string is blank or only whitespace; false otherwise.
     *
     * @example
     * ```php
     * Strings::isBlank("");           // true
     * Strings::isBlank("    ");       // true
     * Strings::isBlank("\n\t\r");     // true
     * Strings::isBlank("hello");      // false
     * ```
     */
    public static function isBlank(string $str): bool
    {
        return trim($str) === '';
    }

    /**
     * Removes accents and diacritics from a UTF-8 string by converting to ASCII.
     *
     * This method uses iconv to transliterate accented characters to their closest
     * ASCII equivalents (e.g., é → e, ü → u). Any remaining non-ASCII characters are stripped.
     *
     * @param string $str The input UTF-8 string.
     *
     * @return string The ASCII-safe, accent-stripped version of the input.
     *
     * @example
     * ```php
     * Strings::removeAccents("Crème brûlée");     // "Creme brulee"
     * Strings::removeAccents("München");          // "Munchen"
     * Strings::removeAccents("façade");           // "facade"
     * ```
     *
     * @note This function removes all non-ASCII characters. Symbols like em-dashes (—) or smart quotes (“ ”) will be stripped.
     */
    public static function removeAccents(string $str): string
    {
        return preg_replace('/[^\x00-\x7F]/u', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str));
    }

    /**
     * Checks whether the given string starts with any of the specified prefixes.
     *
     * Iterates through an array of possible prefixes and returns true as soon
     * as one matches the beginning of the input string.
     *
     * @param string $haystack The string to check.
     * @param array  $prefixes An array of string prefixes to test against.
     *
     * @return bool True if the string starts with any prefix in the array; false otherwise.
     *
     * @example
     * ```php
     * Strings::startsWithAny('circle6maildrop.com', ['https://', 'circle']);     // true
     * Strings::startsWithAny('example.txt', ['test', 'demo']);                   // false
     * ```
     *
     * @note Comparison is case-sensitive. Use strtolower() on both haystack and prefixes if you need case-insensitive behavior.
     */
    public static function startsWithAny(string $haystack, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($haystack, $prefix)) return true;
        }
        return false;
    }

    /**
     * Checks whether the given string ends with any of the specified suffixes.
     *
     * Iterates through an array of possible suffixes and returns true as soon
     * as one matches the end of the input string.
     *
     * @param string $haystack The string to check.
     * @param array  $suffixes An array of string suffixes to test against.
     *
     * @return bool True if the string ends with any suffix in the array; false otherwise.
     *
     * @example
     * ```php
     * Strings::endsWithAny('report.csv', ['.csv', '.txt']);       // true
     * Strings::endsWithAny('hello-world.js', ['.php', '.html']);  // false
     * ```
     *
     * @note Comparison is case-sensitive. Use strtolower() on both haystack and suffixes if you need case-insensitive behavior.
     */
    public static function endsWithAny(string $haystack, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($haystack, $suffix)) return true;
        }
        return false;
    }

    /**
     * Obfuscates an email address by masking the username portion with asterisks.
     *
     * Reveals only the first and last character of the local part (before the @),
     * and preserves the domain. Useful for safely displaying or logging emails
     * without exposing the full address.
     *
     * @param string $email The full email address to obfuscate.
     *
     * @return string The obfuscated email, e.g., j****e@example.com
     *
     * @example
     * ```php
     * Strings::obfuscateEmail('john.doe@example.com');    // 'j******e@example.com'
     * Strings::obfuscateEmail('a@b.com');                 // 'a@b.com'
     * Strings::obfuscateEmail('xy@domain.com');           // 'x*y@domain.com'
     * ```
     *
     * @note This method assumes a valid email with a single '@'. Invalid input may cause errors or incorrect formatting.
     */
    public static function obfuscateEmail(string $email): string
    {
        [$user, $domain] = explode('@', $email);
        return substr($user, 0, 1) . str_repeat('*', max(1, strlen($user) - 2)) . substr($user, -1) . '@' . $domain;
    }

    /**
     * Obfuscates a phone number by masking the middle digits with asterisks.
     *
     * Keeps the first 3 digits and the last 4 digits visible, replacing the middle
     * portion with asterisks. Non-digit characters are stripped before processing.
     *
     * @param string $phoneNumber The input phone number (may contain spaces, dashes, etc.).
     *
     * @return string The obfuscated phone number, e.g., 123****7890
     *
     * @example
     * ```php
     * Strings::obfuscatePhoneNumber('123-456-7890');    // '123***7890'
     * Strings::obfuscatePhoneNumber('(800) 555-1212');  // '800*****1212'
     * ```
     *
     * @note This assumes U.S.-style phone numbers with at least 7 digits.
     *       If fewer than 7 digits, the result may expose more or all digits.
     */
    public static function obfuscatePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        $len = strlen($phoneNumber);
        return substr($phoneNumber, 0, 3) . str_repeat('*', max(0, $len - 3)) . substr($phoneNumber, -4);
    }

    /**
     * Obfuscates a credit card number by masking all but the first and last 4 digits.
     *
     * Non-digit characters (spaces, dashes, etc.) are stripped before processing.
     * Optionally formats the result in grouped blocks of 4 digits/asterisks for readability.
     *
     * @param string $creditCard The raw credit card number input.
     * @param bool   $grouped    Whether to format the result with grouped blocks (e.g., 4111 **** **** 1111). Defaults to false.
     *
     * @return string The obfuscated credit card string.
     *
     * @example
     * ```php
     * Strings::obfuscateCreditCard('4111 1111 1111 1111');          // '4111********1111'
     * Strings::obfuscateCreditCard('4111-1111-1111-1111', true);    // '4111 **** **** 1111'
     * ```
     *
     * @note The method does not perform any credit card validation (Luhn, issuer, etc.).
     *       Input should contain at least 8 digits to produce a meaningful result.
     */
    public static function obfuscateCreditCard(string $creditCard, bool $grouped = false, string $maskChar = '*'): string
    {
        $creditCard = preg_replace('/[^0-9]/', '', $creditCard);
        $len = strlen($creditCard);

        if ($len < 8) {
            return str_repeat($maskChar, $len); // not enough digits to safely reveal
        }

        $first = substr($creditCard, 0, 4);
        $last  = substr($creditCard, -4);
        $maskedLen = $len - 8;
        $masked = str_repeat($maskChar, $maskedLen);

        if (!$grouped) {
            return $first . $masked . $last;
        }

        // Format grouped output
        $groups = [];

        if ($len === 15) { // likely Amex
            $groups[] = $first;
            $groups[] = substr($masked, 0, 6);       // middle 6 masked
            $groups[] = substr($last, -5);           // last 5 digits
        } else {
            $maskedGroups = str_split($masked, 4);
            $groups = array_merge([$first], $maskedGroups, [$last]);
        }

        return implode(' ', array_filter($groups));
    }

    /**
     * Obfuscates a postal code by masking the middle digits with asterisks.
     *
     * Keeps the first 3 digits and the last 4 digits (if present), masking anything in between.
     * Non-digit characters are stripped before processing.
     *
     * @param string $postalCode The postal code (ZIP, ZIP+4, etc.) to obfuscate.
     *
     * @return string The obfuscated postal code, e.g., 787****0423
     *
     * @example
     * ```php
     * Strings::obfuscatePostalCode('78701');           // '787*1'
     * Strings::obfuscatePostalCode('78701-0423');      // '787****0423'
     * ```
     *
     * @note This assumes U.S.-style ZIP or ZIP+4 formats. Input with fewer than 5 digits may not be obfuscated meaningfully.
     *       If the input has fewer than 4 trailing digits, the ending portion will be as long as possible.
     */
    public static function obfuscatePostalCode(string $postalCode): string
    {
        $postalCode = preg_replace('/[^0-9]/', '', $postalCode);
        $len = strlen($postalCode);
        return substr($postalCode, 0, 3) . str_repeat('*', max(0, $len - 3)) . substr($postalCode, -4);
    }

    /**
     * Obfuscates the middle portion of a string, preserving the beginning and end.
     *
     * @param string $input        The string to obfuscate (API key, token, etc.).
     * @param int    $visibleStart Number of visible characters at the beginning.
     * @param int    $visibleEnd   Number of visible characters at the end.
     * @param string $maskChar     Masking character to use. Defaults to '*'.
     *
     * @return string The obfuscated string.
     *
     * @example
     * ```php
     * Strings::obfuscateMiddle('sk_test_abc1234567890xyz', 7, 4);  // 'sk_test****************xyz'
     * Strings::obfuscateMiddle('abc123', 2, 2);                    // 'ab**23'
     * ```
     */
    public static function obfuscateMiddle(string $input, int $visibleStart = 3, int $visibleEnd = 4, string $maskChar = '*'): string
    {
        $len = strlen($input);
        $visibleTotal = $visibleStart + $visibleEnd;

        if ($len <= $visibleTotal) {
            return $input;
        }

        $start = substr($input, 0, $visibleStart);
        $end   = substr($input, -$visibleEnd);
        $maskedLength = $len - $visibleTotal;

        return $start . str_repeat($maskChar, $maskedLength) . $end;
    }


    /**
     * Appends or increments a trailing numeric suffix on a string, optionally zero-padded.
     *
     * When a numeric suffix is present (e.g., "_7", "-099"), it is incremented.
     * If no such suffix is found, "1" is appended using the separator and optional padding.
     * When $strictSuffixOnly is true, suffix must be exactly separator followed by digits (e.g., "_123").
     *
     * @param string   $str               The input string to modify.
     * @param string   $separator         The separator to use before the number. Default is "_".
     * @param int|null $padWidth          Optional zero-padding width (e.g., 3 → "007").
     * @param bool     $strictSuffixOnly  If true, only increments suffixes with exact separator + digits. Default is false.
     *
     * @return string The incremented or newly suffixed string.
     *
     * @example
     * ```php
     * Strings::incrementString('file');                         // 'file_1'
     * Strings::incrementString('report-v2', '-', 3);            // 'report-v003'
     * Strings::incrementString('report-v2', '-', 3, true);      // 'report-v2-001' (v2 not incremented)
     * Strings::incrementString('build-2023.12.01', '-', 3, true); // 'build-2023.12.01-001'
     * ```
     */
    public static function incrementString(
        string $str,
        string $separator = '_',
        ?int $padWidth = null,
        bool $strictSuffixOnly = false
    ): string {
        if ($strictSuffixOnly) {
            // Only match strings ending in exactly "separator + digits"
            $pattern = '/^(.*)' . preg_quote($separator, '/') . '(\d+)$/';
        } else {
            // Match separator + digits even with embedded letters
            $pattern = '/^(.*?' . preg_quote($separator, '/') . ')(\d+)$/';
        }

        if (preg_match($pattern, $str, $matches)) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            $newNumber = $number + 1;

            $width = $padWidth ?? strlen($matches[2]);
            $padded = str_pad((string)$newNumber, $width, '0', STR_PAD_LEFT);

            return $prefix . $padded;
        }

        $suffix = $padWidth ? str_pad('1', $padWidth, '0', STR_PAD_LEFT) : '1';
        return $str . $separator . $suffix;
    }

    /**
     * Decrements a trailing numeric suffix on a string, optionally removing it if it reaches zero.
     *
     * If the string ends in a numeric suffix (e.g., "_002"), it will be decremented.
     * Preserves separator and zero-padding unless the number reaches 0 and $removeIfZero is true.
     * If no numeric suffix is found, the original string is returned unchanged.
     *
     * @param string   $str            The input string to modify.
     * @param string   $separator      The separator before the number. Default is "_".
     * @param int|null $padWidth       If provided, output is zero-padded to this width. Otherwise, preserves original width.
     * @param bool     $removeIfZero   If true, removes the numeric suffix entirely when result reaches 0. Default is false.
     * @param bool     $strictSuffixOnly If true, only decrements suffixes of the form "separator + digits". Default is false.
     *
     * @return string The decremented or cleaned string.
     *
     * @example
     * ```php
     * Strings::decrementString('file_007');                    // 'file_006'
     * Strings::decrementString('file_001', '_', 3, true);      // 'file'
     * Strings::decrementString('report-v2', '-', 3, true, true); // 'report-v2-000'
     * Strings::decrementString('file', '_');                   // 'file'
     * ```
     */
    public static function decrementString(
        string $str,
        string $separator = '_',
        ?int $padWidth = null,
        bool $removeIfZero = false,
        bool $strictSuffixOnly = false
    ): string {
        if ($strictSuffixOnly) {
            $pattern = '/^(.*)' . preg_quote($separator, '/') . '(\d+)$/';
        } else {
            $pattern = '/^(.*?' . preg_quote($separator, '/') . ')(\d+)$/';
        }

        if (preg_match($pattern, $str, $matches)) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            $newNumber = $number - 1;

            if ($newNumber <= 0 && $removeIfZero) {
                return rtrim($prefix, $separator); // cleanly remove trailing separator
            }

            $width = $padWidth ?? strlen($matches[2]);
            $padded = str_pad((string)max(0, $newNumber), $width, '0', STR_PAD_LEFT);

            return $prefix . $padded;
        }

        return $str; // no suffix to decrement
    }

    /**
     * Loads and filters a clean word list from a file like /usr/share/dict/words, with static caching.
     *
     * Only includes lowercase ASCII words without punctuation, between $minLen and $maxLen characters.
     *
     * @param string $path   Path to the wordlist file. Defaults to /usr/share/dict/words.
     * @param int    $minLen Minimum word length to include. Defaults to 3.
     * @param int    $maxLen Maximum word length to include. Defaults to 12.
     *
     * @return array An array of lowercase, clean words.
     *
     * @throws RuntimeException If the file is missing or unreadable.
     */
    public static function loadCleanWordList(string $path = '/usr/share/dict/words', int $minLen = 3, int $maxLen = 12): array
    {
        static $cache = [];

        $key = "$path:$minLen:$maxLen";

        if (!isset($cache[$key])) {
            if (!file_exists($path)) {
                throw new RuntimeException("Wordlist not found at: $path");
            }

            $words = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $filtered = array_values(array_filter(array_map('strtolower', $words), function ($word) use ($minLen, $maxLen) {
                return preg_match('/^[a-z]{' . $minLen . ',' . $maxLen . '}$/', $word);
            }));

            $cache[$key] = $filtered;
        }

        return $cache[$key];
    }

    /**
     * Generates a human-friendly random slug using two words and a number.
     *
     * Useful for URLs, filenames, short codes, etc. Optionally customizable.
     *
     * @param array|null $wordlist    Optional preloaded word list. Loads from /usr/share/dict/words by default.
     * @param int        $numberRange Max numeric suffix. Defaults to 100.
     * @param string     $separator   Separator between slug parts (e.g., '-', '_', '.'). Defaults to '-'.
     * @param string     $prefix      Optional prefix string to prepend. No separator automatically added.
     * @param string     $suffix      Optional suffix string to append. No separator automatically added.
     *
     * @return string Slug in the format: {$prefix}word{$sep}word{$sep}number{$suffix}
     *
     * @example
     * ```php
     * Strings::randomSlugFromWordlist();                            // 'silent-sunset-42'
     * Strings::randomSlugFromWordlist(null, 999, '_');              // 'wild_cloud_314'
     * Strings::randomSlugFromWordlist(null, 100, '-', 'inv-', '-qa'); // 'inv-bold-stone-91-qa'
     * ```
     *
     * @throws Exception
     */
    public static function randomSlugFromWordlist(
        ?array $wordlist = null,
        int $numberRange = 100,
        string $separator = '-',
        string $prefix = '',
        string $suffix = ''
    ): string {
        $wordlist ??= self::loadCleanWordList();

        if (count($wordlist) < 2) {
            throw new RuntimeException("Wordlist must contain at least 2 clean words.");
        }

        $word1 = $wordlist[random_int(0, count($wordlist) - 1)];
        $word2 = $wordlist[random_int(0, count($wordlist) - 1)];
        $num   = random_int(0, $numberRange);

        $core = implode($separator, [$word1, $word2, $num]);

        return $prefix . $core . $suffix;
    }


    /**
     * Checks whether the input string contains any of the given substrings.
     *
     * Iterates through the array of needles and returns true as soon as one is found
     * within the haystack. Optionally performs case-insensitive matching.
     *
     * @param string $haystack   The string to search within.
     * @param array  $needles    An array of substrings to look for.
     * @param bool   $ignoreCase Whether to perform case-insensitive matching. Default is false.
     *
     * @return bool True if any needle is found in the haystack; false otherwise.
     *
     * @example
     * ```php
     * Strings::containsAny('Hello World', ['world'], true);    // true
     * Strings::containsAny('ABC123', ['abc']);                 // false
     * Strings::containsAny('circle6mail', ['Mail'], true);     // true
     * ```
     */
    public static function containsAny(string $haystack, array $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Removes all non-alphabetic characters from the input string.
     *
     * Retains only uppercase and lowercase English letters (A–Z, a–z).
     * All digits, punctuation, symbols, and whitespace are stripped.
     *
     * @param string $str The input string to sanitize.
     *
     * @return string The string containing only alphabetic characters.
     *
     * @example
     * ```php
     * Strings::onlyAlpha('abc123!@#');      // 'abc'
     * Strings::onlyAlpha('Circle-6 Mail');  // 'CircleMail'
     * Strings::onlyAlpha('42');             // ''
     * ```
     *
     * @note This does not support accented or Unicode letters. Use a Unicode-aware version
     *       (e.g., `\p{L}`) if working with non-ASCII alphabets.
     */
    public static function onlyAlpha(string $str): string
    {
        return preg_replace('/[^a-zA-Z]/', '', $str);
    }

    /**
     * Removes all non-letter characters from the input string, including digits and symbols.
     *
     * This version is Unicode-aware and preserves letters from all languages and alphabets
     * (e.g., Latin, Cyrillic, Greek, accented characters, etc.).
     *
     * @param string $str The input string to filter.
     *
     * @return string The string containing only Unicode letter characters.
     *
     * @example
     * ```php
     * Strings::onlyLetters('abc123');         // 'abc'
     * Strings::onlyLetters('Crème brûlée!');  // 'Crèmebrûlée'
     * Strings::onlyLetters('Москва 2024');    // 'Москва'
     * ```
     *
     * @note Uses Unicode `\p{L}` character class to match any kind of letter.
     *       Requires the `u` (UTF-8) modifier to work properly with multibyte characters.
     */
    public static function onlyLetters(string $str): string
    {
        return preg_replace('/[^\p{L}]+/u', '', $str);
    }

    /**
     * Randomly shuffles the characters in a multibyte string.
     *
     * Splits the string into an array of characters, randomizes the order using shuffle(),
     * and returns the resulting string. Useful for non-critical randomness (e.g., games, CAPTCHAs).
     *
     * @param string $str The input string to shuffle.
     *
     * @return string The string with its characters randomly reordered.
     *
     * @example
     * ```php
     * Strings::shuffle('hello');        // might return 'lohel', 'elhol', etc.
     * Strings::shuffle('áéíóú');        // preserves multibyte characters correctly
     * ```
     *
     * @note This is not cryptographically secure. Do not use for password generation or security tokens.
     *       Multibyte-safe via mb_str_split().
     */
    public static function shuffle(string $str): string
    {
        $array = mb_str_split($str);
        shuffle($array);
        return implode('', $array);
    }

    /**
     * Indents each line of a string by a given number of characters.
     *
     * Adds padding to the beginning of each line in a multiline string using the specified character.
     * Useful for formatting logs, code, or nested structures.
     *
     * @param string $str    The input string, possibly multiline.
     * @param int    $spaces The number of characters to indent each line. Default is 4.
     * @param string $char   The character to use for indentation. Default is a space.
     *
     * @return string The indented string.
     *
     * @example
     * ```php
     * Strings::indent("line 1\nline 2");           // "    line 1\n    line 2"
     * Strings::indent("foo\nbar", 2, '-');         // "--foo\n--bar"
     * ```
     *
     * @note Applies to each line independently using `^` and multiline (`/m`) regex mode.
     */
    public static function indent(string $str, int $spaces = 4, string $char = ' '): string
    {
        return preg_replace('/^/m', str_repeat($char, $spaces), $str);
    }

    /**
     * Removes a fixed number of leading characters (indentation) from each line.
     *
     * Useful for reversing `indent()`, reformatting text, or cleaning up deeply nested blocks.
     * Only removes characters if they match the given character and are present at the start of the line.
     *
     * @param string $str    The multiline string to outdent.
     * @param int    $count  Number of leading characters to remove per line. Default is 4.
     * @param string $char   The character to remove. Default is space.
     *
     * @return string The de-indented string.
     *
     * @example
     * ```php
     * Strings::outdent("    line 1\n    line 2");     // "line 1\nline 2"
     * Strings::outdent("--foo\n--bar", 2, '-');       // "foo\nbar"
     * ```
     *
     * @note This only removes characters if they match the specified character exactly.
     *       Mixed indentation (e.g. tabs + spaces) must be normalized first.
     */
    public static function outdent(string $str, int $count = 4, string $char = ' '): string
    {
        $pattern = '/^' . preg_quote(str_repeat($char, $count), '/') . '/m';
        return preg_replace($pattern, '', $str);
    }

    /**
     * Removes the smallest common leading whitespace from all non-empty lines in a multiline string.
     *
     * Useful for cleaning up heredoc strings, templates, or indented blocks
     * without needing to know the exact indentation level.
     *
     * @param string $str The input string with consistent leading indentation.
     *
     * @return string The dedented string.
     *
     * @example
     * ```php
     * Strings::dedent("    line 1\n    line 2\n    line 3");    // "line 1\nline 2\nline 3"
     * Strings::dedent("  foo\n    bar\n  baz");                 // "foo\n  bar\nbaz"
     * ```
     *
     * @note Blank lines are ignored when calculating minimum indentation.
     *       Works with spaces, tabs, or mixed whitespace — but doesn’t normalize them.
     */
    public static function dedent(string $str): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $str);
        $indents = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (preg_match('/^( *)/', $line, $match)) {
                $indents[] = strlen($match[1]);
            }
        }

        $minIndent = $indents ? min($indents) : 0;

        if ($minIndent > 0) {
            $str = preg_replace('/^ {' . $minIndent . '}/m', '', $str);
        }

        return $str;
    }

    /**
     * Removes the given prefix from the start of a string, if present.
     *
     * If the string begins with the specified prefix, it is removed.
     * Otherwise, the original string is returned unchanged.
     *
     * @param string $str    The input string to process.
     * @param string $prefix The prefix to remove if found at the beginning.
     *
     * @return string The string without the prefix (if removed).
     *
     * @example
     * ```php
     * Strings::removePrefix('unhappy', 'un');       // 'happy'
     * Strings::removePrefix('hello world', 'hi');   // 'hello world' (unchanged)
     * Strings::removePrefix('abc123', 'abc');       // '123'
     * ```
     *
     * @note Comparison is case-sensitive. Use strtolower() if case-insensitive behavior is needed.
     */
    public static function removePrefix(string $str, string $prefix): string
    {
        return str_starts_with($str, $prefix) ? substr($str, strlen($prefix)) : $str;
    }

    /**
     * Removes the given suffix from the end of a string, if present.
     *
     * If the string ends with the specified suffix, it is removed.
     * Otherwise, the original string is returned unchanged.
     *
     * @param string $str    The input string to process.
     * @param string $suffix The suffix to remove if found at the end.
     *
     * @return string The string without the suffix (if removed).
     *
     * @example
     * ```php
     * Strings::removeSuffix('filename.txt', '.txt');     // 'filename'
     * Strings::removeSuffix('report_final', '_final');   // 'report'
     * Strings::removeSuffix('document.PDF', '.pdf');     // 'document.PDF' (case-sensitive)
     * ```
     *
     * @note Comparison is case-sensitive. Use strtolower() or str_ends_with(strtolower(...)) if you need case-insensitive behavior.
     */
    public static function removeSuffix(string $str, string $suffix): string
    {
        return str_ends_with($str, $suffix) ? substr($str, 0, -strlen($suffix)) : $str;
    }

    /**
     * Truncates a string to a maximum number of characters, optionally avoiding mid-word breaks.
     *
     * If the string is shorter than or equal to the limit, it is returned as-is.
     * If truncated, the ellipsis is appended and included in the total length.
     *
     * @param string  $str         The input string.
     * @param int     $maxChars    Maximum total characters, including ellipsis.
     * @param string  $ellipsis    String to append after truncation. Default is '…'.
     * @param bool    $preserveWords If true, avoid breaking words in the middle.
     *
     * @return string The truncated string, with ellipsis if applicable.
     *
     * @example
     * ```php
     * Strings::limitChars("Hello world, how are you?", 10);                  // "Hello w…"
     * Strings::limitChars("Hello world, how are you?", 10, '…', true);       // "Hello…"
     * Strings::limitChars("Short", 10);                                      // "Short"
     * ```
     */
    public static function limitChars(string $str, int $maxChars, string $ellipsis = '…', bool $preserveWords = false): string
    {
        if (mb_strlen($str) <= $maxChars) {
            return $str;
        }

        $available = $maxChars - mb_strlen($ellipsis);
        if ($available <= 0) {
            return mb_substr($ellipsis, 0, $maxChars); // fallback if maxChars is smaller than ellipsis
        }

        $truncated = mb_substr($str, 0, $available);

        if ($preserveWords) {
            // Trim back to last full word if possible
            $spacePos = mb_strrpos($truncated, ' ');
            if ($spacePos !== false) {
                $truncated = mb_substr($truncated, 0, $spacePos);
            }
        }

        return rtrim($truncated) . $ellipsis;
    }

    /**
     * Truncates a string containing HTML to a maximum number of visible characters.
     *
     * This method:
     * - Strips tags for counting visible characters
     * - Preserves whole HTML tag structure (no broken tags)
     * - Optionally avoids breaking mid-word
     * - Appends an ellipsis or custom suffix if truncated
     *
     * @param string $html            The HTML string.
     * @param int    $maxChars        Max visible characters, including ellipsis.
     * @param string $ellipsis        What to append if truncated. Default is '…'.
     * @param bool   $preserveWords   Whether to avoid cutting mid-word.
     *
     * @return string Truncated HTML-safe string.
     *
     * @example
     * ```php
     * Strings::limitHtmlSafeChars('<b>Hello</b> world!', 8); // "<b>Hello</b> w…"
     * ```
     */
    public static function limitHtmlSafeChars(string $html, int $maxChars, string $ellipsis = '…', bool $preserveWords = false): string
    {
        $visibleText = strip_tags($html);

        if (mb_strlen($visibleText) <= $maxChars) {
            return $html;
        }

        $available = $maxChars - mb_strlen($ellipsis);
        if ($available <= 0) {
            return mb_substr($ellipsis, 0, $maxChars);
        }

        // Extract plain text up to visible limit
        $truncatedPlain = mb_substr($visibleText, 0, $available);

        if ($preserveWords) {
            $space = mb_strrpos($truncatedPlain, ' ');
            if ($space !== false) {
                $truncatedPlain = mb_substr($truncatedPlain, 0, $space);
            }
        }

        // Walk through HTML, rebuild up to matching plain content
        $output = '';
        $count = 0;
        $inTag = false;

        for ($i = 0; $i < mb_strlen($html); $i++) {
            $char = mb_substr($html, $i, 1);
            $output .= $char;

            if ($char === '<') {
                $inTag = true;
            } elseif ($char === '>') {
                $inTag = false;
            } elseif (!$inTag) {
                $count++;
                if ($count >= mb_strlen($truncatedPlain)) {
                    break;
                }
            }
        }

        // Close any unclosed tags
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // suppress warnings
        $dom->loadHTML(mb_convert_encoding($output, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        $clean = $dom->saveHTML($body);
        $clean = preg_replace('~^<body>|</body>$~', '', $clean); // remove <body> wrapper

        return rtrim($clean) . $ellipsis;
    }

    /**
     * Compares two strings in constant time to prevent timing attacks.
     *
     * This method uses hash_equals() to safely compare secrets like API tokens,
     * HMAC signatures, or passwords without leaking information through response timing.
     *
     * Unlike a regular `===` comparison, this function ensures that the comparison
     * takes the same amount of time regardless of how much of the input matches,
     * which helps defend against timing-based side-channel attacks.
     *
     * @param string $a The expected (trusted) string.
     * @param string $b The actual (untrusted) string to compare.
     *
     * @return bool True if the strings are identical; false otherwise.
     *
     * @example
     * ```php
     * Strings::constantTimeEquals('secret123', 'secret123');  // true
     * Strings::constantTimeEquals('secret123', 'secret124');  // false
     * ```
     *
     * @note This function should always be used for comparing sensitive values like tokens, signatures,
     *       or passwords — especially when accepting user input in authentication or verification flows.
     */
    public static function constantTimeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * Determines if a string contains only alphabetic characters (A–Z, a–z).
     *
     * Returns true if the string is non-empty and contains only English letters.
     * No digits, spaces, symbols, or non-ASCII letters are allowed.
     *
     * @param string $str The input string to test.
     *
     * @return bool True if the string contains only A–Z or a–z; false otherwise.
     *
     * @example
     * ```php
     * Strings::isAlpha('abc');        // true
     * Strings::isAlpha('ABCdef');     // true
     * Strings::isAlpha('abc123');     // false
     * Strings::isAlpha('café');       // false (accented é is not A–Z)
     * ```
     *
     * @note This is ASCII-only. For Unicode letters (e.g., accents, other scripts),
     *       use Strings::onlyLetters() with a Unicode-aware regex like \p{L}.
     */
    public static function isAlpha(string $str): bool
    {
        return preg_match('/^[a-zA-Z]+$/', $str) === 1;
    }

    /**
     * Determines if a string contains only Unicode letters (from any language).
     *
     * Uses the Unicode character class \p{L} to match all letter types, including
     * accented Latin letters, Cyrillic, Greek, and others. Does not allow digits, punctuation, or symbols.
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if the string contains only letters; false otherwise.
     *
     * @example
     * ```php
     * Strings::isLetters('abc');           // true
     * Strings::isLetters('CrèmeBrûlée');   // true
     * Strings::isLetters('Москва');        // true
     * Strings::isLetters('hello123');      // false
     * Strings::isLetters('naïve!');        // false (contains punctuation)
     * ```
     *
     * @note Blank strings will return false. Matching is Unicode-aware and multibyte-safe.
     */
    public static function isLetters(string $str): bool
    {
        return $str !== '' && preg_match('/^\p{L}+$/u', $str) === 1;
    }

    /**
     * Checks whether a string contains only alphanumeric characters (A–Z, a–z, 0–9).
     *
     * Returns true if the string is non-empty and contains only ASCII letters and digits.
     * No whitespace, punctuation, symbols, or Unicode characters are allowed.
     *
     * @param string $str The input string to check.
     *
     * @return bool True if the string contains only alphanumeric ASCII characters; false otherwise.
     *
     * @example
     * ```php
     * Strings::isAlnum('abc123');      // true
     * Strings::isAlnum('ABC');         // true
     * Strings::isAlnum('123');         // true
     * Strings::isAlnum('abc 123');     // false (contains space)
     * Strings::isAlnum('naïve');       // false (accented i)
     * ```
     *
     * @note This is an ASCII-only check. For Unicode support (e.g., non-English letters and digits),
     *       use a Unicode-aware alternative like `isLettersOrDigits()`.
     */
    public static function isAlnum(string $str): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $str) === 1;
    }

    /**
     * Determines if a string contains only Unicode letters or digits.
     *
     * Uses the Unicode character classes \p{L} (letters) and \p{N} (numbers) to validate
     * multilingual, multibyte-safe alphanumeric content. Accepts non-English characters and digits from any script.
     *
     * @param string $str The string to evaluate.
     *
     * @return bool True if the string contains only letters or digits (no punctuation or symbols); false otherwise.
     *
     * @example
     * ```php
     * Strings::isLettersOrDigits('abc123');      // true
     * Strings::isLettersOrDigits('Crème123');    // true
     * Strings::isLettersOrDigits('Москва2024');  // true
     * Strings::isLettersOrDigits('abc!123');     // false (contains punctuation)
     * ```
     *
     * @note This method is multibyte-aware and supports full Unicode. An empty string will return false.
     */
    public static function isLettersOrDigits(string $str): bool
    {
        return $str !== '' && preg_match('/^[\p{L}\p{N}]+$/u', $str) === 1;
    }

    /**
     * Checks whether all characters in the string are lowercase.
     *
     * Uses mb_strtolower() to compare against the original input. Returns true only
     * if the input is non-empty and already entirely lowercase (including multibyte characters).
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if all characters are lowercase; false otherwise.
     *
     * @example
     * ```php
     * Strings::isLower('hello');         // true
     * Strings::isLower('HELLO');         // false
     * Strings::isLower('Crème');         // false (C is uppercase)
     * Strings::isLower('straße');        // true (ß is lowercase)
     * ```
     *
     * @note This method is multibyte-safe. Returns false for empty strings.
     */
    public static function isLower(string $str): bool
    {
        return $str !== '' && mb_strtolower($str) === $str;
    }

    /**
     * Checks whether all characters in the string are uppercase.
     *
     * Uses mb_strtoupper() to compare against the original input. Returns true only
     * if the input is non-empty and already entirely uppercase (including multibyte characters).
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if all characters are uppercase; false otherwise.
     *
     * @example
     * ```php
     * Strings::isUpper('HELLO');         // true
     * Strings::isUpper('hello');         // false
     * Strings::isUpper('CRÈME');         // true
     * Strings::isUpper('München');       // false (ü is lowercase)
     * ```
     *
     * @note This method is multibyte-safe and works with accented/Unicode characters.
     *       Returns false for empty strings.
     */
    public static function isUpper(string $str): bool
    {
        return $str !== '' && mb_strtoupper($str) === $str;
    }

    /**
     * Determines if a string contains both uppercase and lowercase characters.
     *
     * Useful for detecting password strength, formatting errors, or inconsistent casing.
     *
     * @param string $str The string to evaluate.
     *
     * @return bool True if the string contains at least one lowercase and one uppercase character; false otherwise.
     *
     * @example
     * ```php
     * Strings::hasMixedCase('Password123');  // true
     * Strings::hasMixedCase('password');     // false
     * Strings::hasMixedCase('PASSWORD');     // false
     * ```
     *
     * @note This function is multibyte-safe.
     */
    public static function hasMixedCase(string $str): bool
    {
        return preg_match('/\p{Ll}/u', $str) && preg_match('/\p{Lu}/u', $str);
    }

    /**
     * Determines if a string is in title case (each word starts with an uppercase letter, followed by lowercase).
     *
     * Checks that all words begin with \p{Lu} (uppercase letter) followed by zero or more \p{Ll} (lowercase letters).
     *
     * @param string $str The string to check.
     *
     * @return bool True if all words are title-cased; false otherwise.
     *
     * @example
     * ```php
     * Strings::isTitleCase('The Quick Brown Fox');    // true
     * Strings::isTitleCase('the Quick Brown Fox');    // false
     * Strings::isTitleCase('Das Ist Gut');            // true
     * ```
     *
     * @note This method is Unicode-aware and multibyte-safe. Words are split on whitespace.
     */
    public static function isTitleCase(string $str): bool
    {
        foreach (preg_split('/\s+/u', trim($str)) as $word) {
            if (!preg_match('/^\p{Lu}\p{Ll}*$/u', $word)) {
                return false;
            }
        }
        return $str !== '';
    }

    /**
     * Converts a string to title case, capitalizing the first letter of each word.
     *
     * Supports multibyte characters and Unicode scripts. Uses mb_convert_case() with MB_CASE_TITLE.
     *
     * @param string $str The input string to convert.
     *
     * @return string The title-cased version of the input.
     *
     * @example
     * ```php
     * Strings::toTitleCase('the quick brown fox');     // 'The Quick Brown Fox'
     * Strings::toTitleCase('grüße aus münchen');       // 'Grüße Aus München'
     * ```
     */
    public static function toTitleCase(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Determines if a string is strictly title case: each word starts with a single uppercase letter,
     * followed only by lowercase letters — excluding all-uppercase acronyms like 'NASA' or 'HTTP'.
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if the string is strictly title-cased; false otherwise.
     *
     * @example
     * ```php
     * Strings::isStrictTitleCase('The Quick Brown Fox');   // true
     * Strings::isStrictTitleCase('NASA Launch Site');      // false
     * Strings::isStrictTitleCase('The NASA Space Center'); // false
     * ```
     *
     * @note This method is multibyte-safe and Unicode-aware. Words are split on whitespace.
     */
    public static function isStrictTitleCase(string $str): bool
    {
        foreach (preg_split('/\s+/u', trim($str)) as $word) {
            if (!preg_match('/^\p{Lu}\p{Ll}+$/u', $word)) {
                return false;
            }
        }
        return $str !== '';
    }

    /**
     * Converts a string to title case while preserving acronyms and lowercasing stop words.
     *
     * Capitalizes each word unless:
     * - It's a known acronym (e.g., NASA, PHP), which stays uppercase
     * - It's a stop word (e.g., and, the, in), which remains lowercase — unless it's the first word
     *
     * @param string $str         The input string to convert.
     * @param array  $acronyms    Acronyms to preserve in uppercase. Default includes common technical acronyms.
     * @param array  $stopWords   Stop words to keep lowercase unless at the start. Default includes common English stop words.
     *
     * @return string The smartly title-cased string.
     *
     * @example
     * ```php
     * Strings::smartTitleCase('introduction to php and html');
     * // 'Introduction to PHP and HTML'
     *
     * Strings::smartTitleCase('the future of ai in nasa');
     * // 'The Future of AI in NASA'
     * ```
     */
    public static function smartTitleCase(
        string $str,
        array $acronyms = ['ID', 'HTML', 'CSS', 'PHP', 'NASA', 'API', 'URL', 'JSON'],
        array $stopWords = ['and', 'or', 'but', 'for', 'nor', 'a', 'an', 'the', 'in', 'on', 'at', 'to', 'by', 'of', 'with']
    ): string {
        $words = preg_split('/(\s+)/u', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
        $acronymMap = array_change_key_case(array_flip($acronyms), CASE_LOWER);
        $stopMap    = array_change_key_case(array_flip($stopWords), CASE_LOWER);

        foreach ($words as $i => &$word) {
            if (trim($word) === '') continue;

            $lower = mb_strtolower($word);

            if (isset($acronymMap[$lower])) {
                $word = strtoupper($word);
            } elseif ($i === 0 || !isset($stopMap[$lower])) {
                $word = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
            } else {
                $word = $lower;
            }
        }

        return implode('', $words);
    }


    /**
     * Reverses a string safely with full multibyte (UTF-8) support.
     *
     * This is a Unicode-aware replacement for strrev(), which is not safe for multibyte characters.
     * Useful for working with accented characters, non-Latin scripts, and emoji.
     *
     * @param string $str The input string to reverse.
     *
     * @return string The reversed string.
     *
     * @example
     * ```php
     * Strings::reverse('hello');           // 'olleh'
     * Strings::reverse('áéíóú');           // 'úóíéá'
     * Strings::reverse('Доброе утро');     // 'орту еорбоД'
     * Strings::reverse('👩‍🚀💫🚀');           // '🚀💫👩‍🚀' (may break emoji clusters visually)
     * ```
     *
     * @note Uses mb_str_split() to correctly handle Unicode characters.
     *       Emoji or combining characters may not always render properly after reversal due to their complexity.
     */
    public static function reverse(string $str): string
    {
        return implode('', array_reverse(mb_str_split($str)));
    }

    /**
     * Reverses the order of words in a string while preserving the words themselves.
     *
     * Splits the string by whitespace (spaces, tabs, newlines), reverses the order of the words,
     * and joins them back together with a single space.
     *
     * @param string $str The input string containing words.
     *
     * @return string The string with word order reversed.
     *
     * @example
     * ```php
     * Strings::reverseWords('The quick brown fox');   // 'fox brown quick The'
     * Strings::reverseWords('Привет мир');            // 'мир Привет'
     * ```
     *
     * @note Whitespace is normalized to single spaces in the output. Multibyte characters are fully supported.
     *       Punctuation stays attached to words (e.g., 'hello!' becomes 'hello!').
     */
    public static function reverseWords(string $str): string
    {
        $words = preg_split('/\s+/u', trim($str));
        return implode(' ', array_reverse($words));
    }

    /**
     * Reverses the order of lines in a multiline string.
     *
     * Splits the string on line breaks (handles LF, CRLF, and CR), reverses the order of lines,
     * and rejoins them using the original line break format where possible.
     *
     * @param string $str The input multiline string.
     *
     * @return string The string with lines in reverse order.
     *
     * @example
     * ```php
     * Strings::reverseLines("line 1\nline 2\nline 3");
     * // "line 3\nline 2\nline 1"
     * ```
     *
     * @note Handles LF (\n), CRLF (\r\n), and CR (\r) line endings. Output will use the same line endings as the input.
     */
    public static function reverseLines(string $str): string
    {
        // Detect line ending used (default to \n)
        preg_match('/\r\n|\r|\n/', $str, $matches);
        $eol = $matches[0] ?? "\n";

        $lines = preg_split('/\R/u', $str);
        return implode($eol, array_reverse($lines));
    }

    /**
     * Determines whether a string is a valid hexadecimal number.
     *
     * Validates that the string contains only hexadecimal characters (0–9, a–f, A–F).
     * Optionally allows a "0x" or "0X" prefix if $allowPrefix is true.
     *
     * @param string $str          The string to evaluate.
     * @param bool   $allowPrefix  Whether to allow an optional '0x' or '0X' prefix. Default is false.
     *
     * @return bool True if the string is a valid hex value; false otherwise.
     *
     * @example
     * ```php
     * Strings::isHex('deadBEEF');           // true
     * Strings::isHex('0x123abc');           // false
     * Strings::isHex('0x123abc', true);     // true
     * Strings::isHex('g123');               // false
     * Strings::isHex('');                   // false
     * ```
     */
    public static function isHex(string $str, bool $allowPrefix = false): bool
    {
        if ($allowPrefix) {
            return preg_match('/^0x[a-fA-F0-9]+$/', $str) === 1 || preg_match('/^0X[a-fA-F0-9]+$/', $str) === 1;
        }

        return preg_match('/^[a-fA-F0-9]+$/', $str) === 1;
    }

    /**
     * Normalizes a hexadecimal string by removing the optional "0x"/"0X" prefix
     * and optionally converting to lowercase or uppercase.
     *
     * @param string $hex         The input hex string (with or without prefix).
     * @param bool   $toUpper     Whether to convert the result to uppercase. Default is false (lowercase).
     *
     * @return string The normalized hex string with no prefix and consistent casing.
     *
     * @example
     * ```php
     * Strings::normalizeHex('0xDEADBEEF');    // 'deadbeef'
     * Strings::normalizeHex('abc123');        // 'abc123'
     * Strings::normalizeHex('0xABC123', true); // 'ABC123'
     * ```
     *
     * @note Does not validate the input. Use Strings::isHex() first if needed.
     */
    public static function normalizeHex(string $hex, bool $toUpper = false): string
    {
        $hex = preg_replace('/^0x/i', '', $hex);
        return $toUpper ? strtoupper($hex) : strtolower($hex);
    }

    /**
     * Validates and normalizes a hexadecimal string.
     *
     * If the input is a valid hexadecimal string (optionally with a "0x"/"0X" prefix),
     * returns the normalized hex string (prefix removed, casing standardized).
     * If the input is invalid, returns null.
     *
     * @param string $hex         The input hex string to validate and sanitize.
     * @param bool   $toUpper     Whether to return the result in uppercase. Default is false (lowercase).
     *
     * @return string|null The cleaned hex string, or null if invalid.
     *
     * @example
     * ```php
     * Strings::sanitizeHex('0xABC123');        // 'abc123'
     * Strings::sanitizeHex('ABC123', true);    // 'ABC123'
     * Strings::sanitizeHex('g123');            // null
     * ```
     *
     * @note This method combines isHex() and normalizeHex() with prefix support.
     */
    public static function sanitizeHex(string $hex, bool $toUpper = false): ?string
    {
        if (!self::isHex($hex, true)) {
            return null;
        }

        return self::normalizeHex($hex, $toUpper);
    }

    /**
     * Checks if a string contains only printable characters.
     *
     * Uses the Unicode `\P{C}` property to reject control characters (e.g., newlines, null bytes, etc.).
     * Returns true if the string is non-empty and contains no control or non-character code points.
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if all characters are printable; false otherwise.
     *
     * @example
     * ```php
     * Strings::isPrintable('hello');              // true
     * Strings::isPrintable("hello\nworld");       // false
     * Strings::isPrintable('🚀 Launch!');          // true
     * Strings::isPrintable("abc\x00def");         // false (contains null byte)
     * ```
     *
     * @note This is Unicode-aware and multibyte-safe. It does not filter whitespace like spaces or tabs,
     *       only non-printable control characters (General Category: C* in Unicode).
     */
    public static function isPrintable(string $str): bool
    {
        return preg_match('/^\P{C}+$/u', $str) === 1;
    }

    /**
     * Removes all non-printable (control) characters from a string.
     *
     * Uses the Unicode `\P{C}` property to retain only printable characters.
     * This includes visible characters, whitespace, and multibyte characters,
     * but strips out control codes like newlines, null bytes, bell characters, etc.
     *
     * @param string $str The input string to sanitize.
     *
     * @return string The cleaned string with only printable characters remaining.
     *
     * @example
     * ```php
     * Strings::stripNonPrintable("abc\x00\x1Fdef");   // 'abcdef'
     * Strings::stripNonPrintable("Hello\nWorld");     // 'HelloWorld'
     * Strings::stripNonPrintable("🚀 Launch!");        // '🚀 Launch!' (unchanged)
     * ```
     *
     * @note This method is Unicode-aware and multibyte-safe. Whitespace such as spaces and tabs are preserved.
     */
    public static function stripNonPrintable(string $str): string
    {
        return preg_replace('/\p{C}+/u', '', $str);
    }

    /**
     * Checks whether a string contains only 7-bit ASCII characters.
     *
     * Returns true if the string contains only standard printable ASCII characters (code points 0–127),
     * including control characters, spaces, and basic punctuation. Any multibyte or extended characters
     * will cause this to return false.
     *
     * @param string $str The input string to evaluate.
     *
     * @return bool True if the string is valid 7-bit ASCII; false otherwise.
     *
     * @example
     * ```php
     * Strings::isAscii('Hello123');         // true
     * Strings::isAscii('Café');             // false (é is non-ASCII)
     * Strings::isAscii("Line\nBreak");      // true (newline is ASCII)
     * ```
     *
     * @note Uses mb_check_encoding() for reliable detection. If you want to detect only printable
     *       ASCII, use Strings::isPrintable() in combination with this method.
     */
    public static function isAscii(string $str): bool
    {
        return mb_check_encoding($str, 'ASCII');
    }

    /**
     * Converts a string to ASCII by transliterating or removing non-ASCII characters.
     *
     * Uses iconv() to transliterate characters like "é" → "e", "ü" → "u", etc.
     * Falls back to stripping any remaining multibyte characters not handled by transliteration.
     *
     * @param string $str The input string to sanitize.
     * @param string $replacement Optional string to insert in place of untranslatable characters. Default is empty string.
     *
     * @return string A best-effort ASCII-safe version of the input.
     *
     * @example
     * ```php
     * Strings::toAsciiSafe('Café');              // 'Cafe'
     * Strings::toAsciiSafe('Grüße aus München'); // 'Grusse aus Munchen'
     * Strings::toAsciiSafe('👋🌍');               // '' (emoji are removed)
     * ```
     *
     * @note iconv() may behave differently across platforms. Remaining non-ASCII characters are stripped using a regex cleanup.
     */
    public static function toAsciiSafe(string $str, string $replacement = ''): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        $ascii = $ascii === false ? '' : $ascii;
        return preg_replace('/[^\x00-\x7F]/', $replacement, $ascii);
    }

    /**
     * Collapses all sequences of whitespace (spaces, tabs, newlines) into a single space.
     *
     * Trims leading and trailing whitespace, then normalizes all internal whitespace to a single space character.
     *
     * @param string $str The input string.
     *
     * @return string The string with normalized whitespace.
     *
     * @example
     * ```php
     * Strings::collapseWhitespace(" Hello\t \n world!  ");  // 'Hello world!'
     * ```
     */
    public static function collapseWhitespace(string $str): string
    {
        return trim(preg_replace('/\s+/u', ' ', $str));
    }

    /**
     * Removes all punctuation characters from a string, preserving letters, numbers, and whitespace.
     *
     * Useful for search indexing or cleaning input text.
     *
     * @param string $str The input string.
     *
     * @return string The string with punctuation removed.
     *
     * @example
     * ```php
     * Strings::stripPunctuation("Hello, world!");  // 'Hello world'
     * ```
     */
    public static function stripPunctuation(string $str): string
    {
        return preg_replace('/\p{P}+/u', '', $str);
    }

    /**
     * Removes diacritical marks (accents) from characters while preserving the base letter.
     *
     * For example: é → e, ü → u, ñ → n.
     *
     * @param string $str The input string.
     *
     * @return string The de-accented string.
     *
     * @example
     * ```php
     * Strings::stripDiacritics("Crème brûlée");  // 'Creme brulee'
     * ```
     */
    public static function stripDiacritics(string $str): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return $normalized === false ? '' : preg_replace('/[^\x00-\x7F]/', '', $normalized);
    }

    /**
     * Determines if a string is a valid numeric value (int or float).
     *
     * Accepts optional signs, decimal points, and scientific notation (e.g., '1e6').
     *
     * @param string $str The input string to test.
     *
     * @return bool True if the string is numeric; false otherwise.
     *
     * @example
     * ```php
     * Strings::isNumericString('123');      // true
     * Strings::isNumericString('-12.5e3');  // true
     * Strings::isNumericString('123abc');   // false
     * ```
     */
    public static function isNumericString(string $str): bool
    {
        return is_numeric($str);
    }

    /**
     * Attempts to detect the encoding of a string using mb_detect_encoding().
     *
     * Tries common encodings including UTF-8, ISO-8859-1, and Windows-1252.
     *
     * @param string $str The input string.
     *
     * @return string|false The detected encoding (e.g., 'UTF-8') or false if undetectable.
     *
     * @example
     * ```php
     * Strings::detectEncoding("hello");   // 'ASCII' or 'UTF-8'
     * ```
     */
    public static function detectEncoding(string $str): string|false
    {
        return mb_detect_encoding($str, mb_list_encodings(), true);
    }

    /**
     * Returns a frequency map of characters in the given string.
     *
     * Keys are characters, values are occurrence counts. Multibyte-safe.
     *
     * @param string $str The input string.
     *
     * @return array Associative array of character => count.
     *
     * @example
     * ```php
     * Strings::charFrequency('hello') returns:
     * ['h' => 1, 'e' => 1, 'l' => 2, 'o' => 1]
     * ```
     */
    public static function charFrequency(string $str): array
    {
        $chars = mb_str_split($str);
        return array_count_values($chars);
    }

    /**
     * Applies the ROT13 cipher to a string.
     *
     * Replaces each letter with the one 13 positions ahead in the alphabet.
     * Non-alphabetic characters are left unchanged. Case is preserved.
     *
     * @param string $str The input string.
     *
     * @return string The ROT13-encoded string.
     *
     * @example
     * ```php
     * Strings::rot13('Hello');  // 'Uryyb'
     * Strings::rot13('Uryyb');  // 'Hello'
     * ```
     */
    public static function rot13(string $str): string
    {
        return str_rot13($str);
    }

    /**
     * Extracts the uppercase initials from a name or phrase.
     *
     * Useful for avatars, monograms, or shorthand labels.
     *
     * @param string $str The input string (e.g., full name).
     *
     * @return string The concatenated initials in uppercase.
     *
     * @example
     * ```php
     * Strings::initials('John Doe');        // 'JD'
     * Strings::initials('alice cooper');    // 'AC'
     * ```
     */
    public static function initials(string $str): string
    {
        preg_match_all('/\b(\p{L})/u', $str, $matches);
        return mb_strtoupper(implode('', $matches[1]));
    }

    /**
     * Removes consecutive duplicate words in a string.
     *
     * Useful for cleaning up repeated input like "this this is is fine".
     *
     * @param string $str The input string.
     *
     * @return string The cleaned string with duplicates removed.
     *
     * @example
     * ```php
     * Strings::removeDuplicateWords('this this is is a test');  // 'this is a test'
     * ```
     */
    public static function removeDuplicateWords(string $str): string
    {
        return preg_replace('/\b(\w+)\b(?:\s+\1\b)+/iu', '$1', $str);
    }

    /**
     * Checks whether a string is a valid base64-encoded string.
     *
     * Validates character set and length. Optionally accepts padding.
     *
     * @param string $str The input string to check.
     *
     * @return bool True if the string is valid base64; false otherwise.
     *
     * @example
     * ```php
     * Strings::isValidBase64('aGVsbG8=');      // true
     * Strings::isValidBase64('hello');         // false
     * ```
     */
    public static function isValidBase64(string $str): bool
    {
        return base64_encode(base64_decode($str, true)) === $str;
    }

    /**
     * Generates an HMAC signature for a payload using the given key and algorithm.
     *
     * Useful for API authentication or secure data signing.
     *
     * @param string $payload The message to sign.
     * @param string $key     The secret key.
     * @param string $algo    The hashing algorithm to use. Default is 'sha256'.
     *
     * @return string The generated HMAC hash as a hex string.
     *
     * @example
     * ```php
     * Strings::signHmac('data', 'secret');             // 'aabbcc...'
     * Strings::signHmac('payload', 'key', 'sha1');     // SHA1 HMAC
     * ```
     */
    public static function signHmac(string $payload, string $key, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $payload, $key);
    }

    /**
     * Verifies an HMAC signature against a message and secret key using a constant-time comparison.
     *
     * This is useful for authenticating webhooks or signed payloads. It securely compares
     * the provided signature with a locally generated one, avoiding timing attacks.
     *
     * @param string $payload   The raw message or data (e.g., webhook body).
     * @param string $signature The HMAC signature to verify (hex or base64).
     * @param string $secret    The shared secret key used to generate the original signature.
     * @param string $algo      Hashing algorithm to use (e.g. 'sha256', 'sha1'). Default is 'sha256'.
     * @param bool   $isBase64  Whether the signature is base64-encoded instead of hex.
     *
     * @return bool True if the signature is valid; false otherwise.
     *
     * @example
     * ```php
     * // Verifying a GitHub-style HMAC signature:
     * Strings::verifyHmacSignature($json, $_SERVER['HTTP_X_HUB_SIGNATURE_256'], $secret, 'sha256');
     * ```
     */
    public static function verifyHmacSignature(
        string $payload,
        string $signature,
        string $secret,
        string $algo = 'sha256',
        bool $isBase64 = false
    ): bool {
        $expected = hash_hmac($algo, $payload, $secret, !$isBase64); // binary or hex
        return hash_equals($expected, $isBase64 ? base64_decode($signature) : $signature);
    }

    /**
     * Verifies a Twilio webhook signature.
     *
     * Reconstructs Twilio’s signing scheme by combining the request URL and sorted POST params,
     * then validates the Base64 HMAC-SHA1 signature against the X-Twilio-Signature header.
     *
     * @param string $url        The full request URL (no query string).
     * @param array  $params     The POST parameters (from $_POST).
     * @param string $signature  The value of X-Twilio-Signature header.
     * @param string $authToken  Your Twilio auth token.
     *
     * @return bool True if the signature matches; false otherwise.
     *
     * @example
     * ```php
     * Strings::verifyTwilioSignature(
     *     'https://example.com/handler',
     *     $_POST,
     *     $_SERVER['HTTP_X_TWILIO_SIGNATURE'],
     *     getenv('TWILIO_AUTH_TOKEN')
     * );
     * ```
     */
    public static function verifyTwilioSignature(
        string $url,
        array $params,
        string $signature,
        string $authToken
    ): bool {
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return hash_equals($expected, $signature);
    }



    /**
     * Counts the number of sentences in a string.
     *
     * A sentence is defined as ending with a period (.), question mark (?), or exclamation point (!),
     * followed by a space or end of string.
     *
     * @param string $str The input text.
     *
     * @return int Number of sentences found.
     *
     * @example
     * ```php
     * Strings::sentenceCount('This is one. And another!');  // 2
     * ```
     */
    public static function sentenceCount(string $str): int
    {
        preg_match_all('/[.!?](?=\s|$)/u', $str, $matches);
        return count($matches[0]);
    }

    /**
     * Determines if a string resembles a sentence (starts with uppercase and ends with punctuation).
     *
     * Useful for validating user input, UI messages, or natural language checks.
     *
     * @param string $str The string to evaluate.
     *
     * @return bool True if the string looks like a sentence; false otherwise.
     *
     * @example
     * ```php
     * Strings::isSentenceLike('Hello world.');   // true
     * Strings::isSentenceLike('hello world');    // false
     * ```
     */
    public static function isSentenceLike(string $str): bool
    {
        return preg_match('/^\p{Lu}.*[.!?]$/u', trim($str)) === 1;
    }

    /**
     * Converts a string to snake_case.
     *
     * Replaces spaces and camelCase boundaries with underscores.
     *
     * @param string $str The input string.
     *
     * @return string The snake_cased string.
     *
     * @example
     * ```php
     * Strings::toSnakeCase('HelloWorld');     // 'hello_world'
     * Strings::toSnakeCase('user name');      // 'user_name'
     * ```
     */
    public static function toSnakeCase(string $str): string
    {
        $str = preg_replace('/([a-z])([A-Z])/u', '$1_$2', $str);
        $str = preg_replace('/[\s\-]+/u', '_', $str);
        return strtolower(trim($str, '_'));
    }

    /**
     * Converts a string to camelCase.
     *
     * Removes separators (space, dash, underscore), capitalizes each word,
     * and lowercases the first character.
     *
     * @param string $str The input string.
     *
     * @return string The camelCased string.
     *
     * @example
     * ```php
     * Strings::toCamelCase('user name');      // 'userName'
     * Strings::toCamelCase('hello_world');    // 'helloWorld'
     * ```
     */
    public static function toCamelCase(string $str): string
    {
        $str = str_replace(['-', '_'], ' ', strtolower($str));
        $str = str_replace(' ', '', ucwords($str));
        return lcfirst($str);
    }

    /**
     * Converts a string to kebab-case.
     *
     * Converts spaces, camelCase, and underscores into hyphens.
     *
     * @param string $str The input string.
     *
     * @return string The kebab-cased string.
     *
     * @example
     * ```php
     * Strings::toKebabCase('HelloWorld');     // 'hello-world'
     * Strings::toKebabCase('user_name');      // 'user-name'
     * ```
     */
    public static function toKebabCase(string $str): string
    {
        $str = preg_replace('/([a-z])([A-Z])/u', '$1-$2', $str);
        $str = preg_replace('/[\s_]+/u', '-', $str);
        return strtolower(trim($str, '-'));
    }

    /**
     * Attempts to detect the casing style of a string.
     *
     * Returns one of: 'camel', 'pascal', 'snake', 'kebab', 'title', 'upper', 'lower', or 'unknown'.
     *
     * @param string $str The string to analyze.
     *
     * @return string The detected case style (or 'unknown').
     *
     * @example
     * ```php
     * Strings::detectCaseStyle('helloWorld');     // 'camel'
     * Strings::detectCaseStyle('HelloWorld');     // 'pascal'
     * Strings::detectCaseStyle('hello_world');    // 'snake'
     * Strings::detectCaseStyle('hello-world');    // 'kebab'
     * Strings::detectCaseStyle('HELLO WORLD');    // 'upper'
     * ```
     */
    public static function detectCaseStyle(string $str): string
    {
        if (preg_match('/^[a-z]+(?:[A-Z][a-z0-9]*)+$/', $str)) return 'camel';
        if (preg_match('/^[A-Z][a-z0-9]*(?:[A-Z][a-z0-9]*)*$/', $str)) return 'pascal';
        if (preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)+$/', $str)) return 'snake';
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', $str)) return 'kebab';
        if (preg_match('/^[A-Z][a-z]+(?:\s[A-Z][a-z]+)*$/', $str)) return 'title';
        if (preg_match('/^[A-Z\s]+$/', $str)) return 'upper';
        if (preg_match('/^[a-z\s]+$/', $str)) return 'lower';

        return 'unknown';
    }


    public static function formatCurrency(float $amount): string
    {
        $formatted = number_format(abs($amount), 2);
        return ($amount < 0 ? '-' : '') . '$' . $formatted;
    }






}
