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
 * Supports rule strings (e.g., "required|email|min:18") or callables for custom logic.
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

}
