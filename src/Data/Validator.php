<?php
/**
 * File: /vendor/vernsix/primordyx/src/Validator.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Data/alidator.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

use PDOException;
use Primordyx\Database\ConnectionManager;
use Primordyx\Database\QueryTracker;

/**
 * Class Validator
 *
 * A utility class to validate associative arrays against field rules.
 * Supports rule strings (e.g., "required|email|minLength:18") or callables for custom logic.
 * Returns an array of error messages per field — does not throw exceptions.
 *
 * Performs validation on form or data arrays using string-based rule syntax or callables.
 * String rules are separated by pipe (|), and support parameters via colon (e.g., min:18).
 *
 * @since       1.0.0
 */
class Validator
{

    /**
     * Validates data against defined rules.
     *
     * @param array $data   Associative array of data to validate (field => value).
     * @param array $rules  Associative array of rules (field => string rule or callable).
     * @return array<string, string[]> An array of error messages indexed by field.
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $definition) {
            $value = $data[$field] ?? null;

            // Callable rule
            if (is_callable($definition)) {
                $msg = $definition($value, $data);
                if ($msg) $errors[$field][] = $msg;
                continue;
            }

            // Invalid rule format
            if (!is_string($definition)) {
                $msg = "Invalid rule format for '$field': " . print_r($definition, true);
                error_log("[Validator] $msg");
                $errors[$field][] = "Invalid validation rule format for '$field'.";
                continue;
            }

            // String DSL rule
            foreach (explode('|', $definition) as $rule) {
                [$verb, $param] = array_pad(explode(':', $rule, 2), 2, null);

                // Special handling for nullable
                if ($verb === 'nullable' && ($value === null || $value === '')) {
                    // Skip all remaining validations for this field
                    break;
                }

                $method = 'rule' . ucfirst($verb);
                if (method_exists(__CLASS__, $method)) {
                    $msg = self::$method($value, $param, $field, $data);
                    if ($msg) $errors[$field][] = $msg;
                } else {
                    $errors[$field][] = "Unknown validation rule '$verb'.";
                }
            }
        }

        return $errors;
    }

    /* ------------------------------------------------------------------ */
    /*  Built‑in rule handlers (return null on pass, message on fail)    */
    /* ------------------------------------------------------------------ */

    /**
     * Checks if the value is non-empty.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleRequired(mixed $value): ?string
    {
        return ($value === null || $value === '') ? 'is required.' : null;
    }

    /**
     * Checks if the value is a valid email.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleEmail(mixed $value): ?string
    {
        return ($value && !filter_var($value, FILTER_VALIDATE_EMAIL))
            ? 'must be a valid email.' : null;
    }

    /**
     * Checks if the value is numeric.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleNumeric(mixed $value): ?string
    {
        return ($value !== null && !is_numeric($value)) ? 'must be numeric.' : null;
    }

    /**
     * Ensures the value is at least the given minimum.
     *
     * @param mixed $value
     * @param string|null $param
     * @return string|null
     */
    protected static function ruleMin(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        return ($value < $param) ? "must be at least $param." : null;
    }


    /**
     * Ensures the value does not exceed the given maximum.
     *
     * @param mixed $value
     * @param string|null $param
     * @return string|null
     */
    protected static function ruleMax(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        return ($value > $param) ? "must be at most $param." : null;
    }

    /**
     * Validates the value against a regex pattern.
     *
     * @param mixed $value
     * @param string|null $param Regular expression.
     * @return string|null
     */
    protected static function ruleRegex(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        return (!preg_match($param, (string)$value)) ? 'has invalid format.' : null;
    }

    /**
     * Validates that the value is unique in a database table/column.
     *
     * @param mixed $value
     * @param string|null $param Format: "table,column"
     * @return string|null
     * @throws PDOException
     */
    protected static function ruleUnique(mixed $value, string|null $param): ?string
    {
        [$table, $column] = explode(',', $param);
        $pdo = ConnectionManager::getHandle();
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);

        QueryTracker::start();
        $stmt->execute([$value]);
        QueryTracker::stop($sql, [$value]);

        return $stmt->fetchColumn() ? 'is already taken.' : null;
    }

    /**
     * Checks that a value exists in a given table/column.
     *
     * @param mixed $value
     * @param string|null $param Format: "table,column"
     * @return string|null
     * @throws PDOException
     */
    protected static function ruleExists(mixed $value, string|null $param): ?string
    {
        [$table, $column] = explode(',', $param);
        $pdo = ConnectionManager::getHandle();
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
        $stmt = $pdo->prepare($sql);

        QueryTracker::start();
        $stmt->execute([$value]);
        QueryTracker::stop($sql, [$value]);

        return $stmt->fetchColumn() ? null : 'does not exist.';
    }

    /**
     * Checks if the value is within a set of allowed values.
     *
     * @param mixed $value
     * @param string|null $param Comma-separated list of allowed values.
     * @return string|null
     */
    protected static function ruleIn(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        $allowed = array_map('trim', explode(',', $param));
        return in_array($value, $allowed, true) ? null : "must be one of: $param.";
    }

    /**
     * Checks if the value is boolean (or a 0/1 string or integer).
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleBoolean(mixed $value): ?string
    {
        if (is_null($value)) return null;
        if (is_bool($value)) return null;
        if (in_array($value, [0, 1, '0', '1'], true)) return null;
        return 'must be a boolean (true/false or 0/1).';
    }

    /**
     * Checks if the value is an integer (not float, not decimal).
     *
     * Validates that the value is either:
     * - An integer type
     * - A numeric string that represents a whole number (no decimals)
     *
     * Allows null/empty values to pass (for optional fields).
     * Use 'required|integer' to enforce both presence and integer type.
     *
     * note: somehow this was deleted in prior versions.  Doh!
     *
     * @param mixed $value The value to validate
     * @return string|null Error message if validation fails, null on success
     * @since 1.0.4
     *
     * @example Valid integers:
     * - 42 (integer)
     * - "42" (numeric string)
     * - "-10" (negative numeric string)
     * - 0 (zero)
     * - "0" (string zero)
     *
     * @example Invalid integers:
     * - 3.14 (float)
     * - "3.14" (decimal string)
     * - "42.0" (decimal notation)
     * - "abc" (non-numeric)
     * - true/false (boolean)
     */
    protected static function ruleInteger(mixed $value): ?string
    {
        // Allow null or empty string to pass (for optional fields)
        if ($value === null || $value === '') {
            return null;
        }

        // Check if it's an integer type
        if (is_int($value)) {
            return null;
        }

        // Check if it's a numeric string representing a whole number
        if (is_string($value) && is_numeric($value)) {
            // Use filter_var to strictly validate integer strings
            // This rejects decimals like "3.14" or "42.0"
            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return null;
            }
        }

        return 'must be an integer.';
    }

    /**
     * Validates minimum string length (different from numeric min).
     *
     * @param mixed $value
     * @param string|null $param Minimum length
     * @return string|null
     */
    protected static function ruleMinLength(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        $length = mb_strlen((string)$value);
        return ($length < (int)$param) ? "must be at least $param characters." : null;
    }

    /**
     * Validates maximum string length (different from numeric max).
     *
     * @param mixed $value
     * @param string|null $param Maximum length
     * @return string|null
     */
    protected static function ruleMaxLength(mixed $value, string|null $param): ?string
    {
        if ($value === null || $value === '') return null;
        $length = mb_strlen((string)$value);
        return ($length > (int)$param) ? "must be no more than $param characters." : null;
    }

    /**
     * Validates that value contains only alphabetic characters.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleAlpha(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        return !ctype_alpha((string)$value) ? 'must contain only letters.' : null;
    }

    /**
     * Validates that value contains only alphanumeric characters.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleAlphaNum(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        return !ctype_alnum((string)$value) ? 'must contain only letters and numbers.' : null;
    }

    /**
     * Validates that value contains only alphanumeric characters, dashes, and underscores.
     * Perfect for usernames, slugs, etc.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleAlphaDash(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        return !preg_match('/^[\w\-]+$/u', (string)$value)
            ? 'must contain only letters, numbers, dashes, and underscores.' : null;
    }

    /**
     * Marks field as explicitly nullable - skips all other validations if null.
     * Must be processed FIRST in the validation chain.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleNullable(mixed $value): ?string
    {
        // This just marks the field as nullable
        // The actual logic needs to be in the validate() method
        return null;
    }

    /**
     * Validates datetime format, optionally checking against a specific format.
     * Default format is MySQL DATETIME: 'Y-m-d H:i:s'
     *
     * @param mixed $value
     * @param string|null $param Optional datetime format (default: Y-m-d H:i:s)
     * @return string|null
     */
    protected static function ruleDatetime(mixed $value, string|null $param = null): ?string
    {
        if ($value === null || $value === '') return null;

        // Default to MySQL datetime format if no format specified
        $format = $param ?? 'Y-m-d H:i:s';

        // If it's already a DateTime object, it's valid
        if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
            return null;
        }

        // Try to create DateTime from the format
        $date = \DateTime::createFromFormat($format, (string)$value);

        // Check if date is valid and matches the original input
        // The second check ensures "2024-02-30" doesn't validate as "2024-03-01"
        if (!$date || $date->format($format) !== (string)$value) {
            return match($format) {
                'Y-m-d H:i:s' => 'must be a valid datetime (YYYY-MM-DD HH:MM:SS).',
                'Y-m-d' => 'must be a valid date (YYYY-MM-DD).',
                'H:i:s' => 'must be a valid time (HH:MM:SS).',
                default => "must be a valid datetime in format $format."
            };
        }

        return null;
    }

    /**
     * Validates date format (without time).
     * Alias for datetime with Y-m-d format.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function ruleDate(mixed $value): ?string
    {
        return self::ruleDatetime($value, 'Y-m-d');
    }

    /**
     * Validates time format (without date).
     *
     * @param mixed $value
     * @param string|null $param Format like 'H:i:s' or 'H:i'
     * @return string|null
     */
    protected static function ruleTime(mixed $value, string|null $param = null): ?string
    {
        $format = $param ?? 'H:i:s';
        return self::ruleDatetime($value, $format);
    }

    /**
     * Validates timestamp is within MySQL TIMESTAMP range.
     * (1970-01-01 00:00:01 to 2038-01-19 03:14:07 UTC)
     *
     * @param mixed $value Unix timestamp or datetime string
     * @return string|null
     */
    protected static function ruleTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        // Convert to timestamp if it's a datetime string
        $timestamp = is_numeric($value)
            ? (int)$value
            : strtotime((string)$value);

        if ($timestamp === false) {
            return 'must be a valid timestamp.';
        }

        // MySQL TIMESTAMP range limits
        $min = strtotime('1970-01-01 00:00:01 UTC');
        $max = strtotime('2038-01-19 03:14:07 UTC');

        return ($timestamp < $min || $timestamp > $max)
            ? 'must be between 1970-01-01 00:00:01 and 2038-01-19 03:14:07.'
            : null;
    }


}
