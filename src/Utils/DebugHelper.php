<?php
/**
 * File: /vendor/vernsix/primordyx/src/DebugHelper.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils/DebugHelper.php
 *
 */

declare(strict_types=1);

namespace Primordyx\Utils;

/**
 * Development debugging utility for readable array output and call stack analysis
 *
 * DebugHelper provides essential debugging tools that automatically adapt output
 * formatting based on execution environment (CLI vs web). Includes array dumping
 * with proper formatting and call stack analysis for tracing execution flow.
 *
 * ## Environment Adaptation
 * - **CLI**: Plain text output with ASCII separators
 * - **Web**: HTML output with inline CSS styling and XSS protection
 *
 * ## Core Features
 * - Array dumping with nested structure support
 * - Call stack analysis with configurable depth
 * - Automatic environment detection and formatting
 * - Safe handling of mixed data types and null values
 *
 * ## Security Warning
 * **Development only** - Never use in production. Can expose sensitive data
 * and internal application structure.
 *
 * @since 1.0.0
 *
 * @example Basic Usage
 * ```php
 * // Debug array data
 * DebugHelper::dump(['key' => 'value', 'nested' => ['data' => 123]]);
 *
 * // Trace function calls
 * $caller = DebugHelper::getCallerInfo();
 * error_log("Called by: $caller");
 * ```
 */
class DebugHelper
{

    /**
     * Analyze debug backtrace to identify function call relationships and source locations
     *
     * Provides two modes: single caller analysis (default) for focused debugging,
     * and full stack analysis for complete execution path tracing.
     *
     * ## Modes
     * - **Single Caller**: Returns "caller() called callee() in file.php on line 123"
     * - **Full Stack**: Returns numbered list of complete call chain with error_log() output
     *
     * ## Depth Parameter
     * Controls which stack frame to analyze:
     * - depth=1: Immediate caller of getCallerInfo()
     * - depth=2: Default, typical caller-callee relationship
     * - depth>2: Higher up the call stack
     *
     * @param int $depth Stack frame depth to analyze. Default 2 for typical caller-callee relationship.
     * @param bool $fullStack When true, returns complete call stack and logs to error_log().
     * @return string Human-readable caller information or complete call stack.
     *
     * @example Single Caller
     * ```php
     * function authenticate($user) {
     *     $caller = DebugHelper::getCallerInfo();
     *     error_log("Auth called by: $caller");
     *     // Logs: "userLogin() called authenticate() in login.php on line 15"
     * }
     * ```
     *
     * @example Full Stack Analysis
     * ```php
     * function deepFunction() {
     *     $trace = DebugHelper::getCallerInfo(2, true);
     *     file_put_contents('trace.log', $trace);
     *     // Outputs complete execution path with numbered stack frames
     * }
     * ```
     *
     * @see debug_backtrace() PHP function used internally
     * @since 1.0.0
     */
    public static function getCallerInfo(int $depth = 2, bool $fullStack = false): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        if ($fullStack) {
            $lines = [];
            foreach (array_slice($trace, 1) as $i => $frame) {
                $class = $frame['class'] ?? null;
                $func = $frame['function'] ?? '[unknown]';
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? '?';
                error_log($lines[] = "#{$i} {$class}->{$func}() called in {$file} on line {$line}");
            }
            return implode(PHP_EOL, $lines);
        }

        $caller = $trace[$depth] ?? null;
        $callee = $trace[$depth - 1] ?? null;

        if (!$caller || !$callee) {
            return 'Caller information not available.';
        }

        $callerFunc = $caller['function'] ?? '[unknown]';
        $callerFile = $caller['file'] ?? '[internal]';
        $callerLine = $caller['line'] ?? '?';
        $calleeFunc = $callee['function'] ?? '[unknown]';

        return "{$callerFunc}() called {$calleeFunc}() in {$callerFile} on line {$callerLine}";
    }

    /**
     * Output associative array contents in environment-appropriate readable format
     *
     * Automatically detects CLI vs web environment and formats output accordingly.
     * CLI uses plain text with ASCII separators, web uses HTML with inline styling.
     *
     * ## Output Formats
     * - **CLI**: Plain text headers, uppercase keys, raw print_r() for arrays
     * - **Web**: HTML styling, monospace font, color coding, XSS protection
     *
     * ## Data Handling
     * - Nested arrays with proper indentation
     * - Mixed data types (scalars, arrays, null values)
     * - Safe null value representation
     * - Large array support
     *
     * @param array $result Associative array containing data to display for debugging.
     * @return void Outputs formatted content directly, no return value.
     *
     * @example CLI Output
     * ```php
     * DebugHelper::dump(['status' => 'ok', 'data' => ['count' => 5]]);
     * // === Debug Dump ===
     * // STATUS:
     * // ok
     * // DATA:
     * // Array( [count] => 5 )
     * // === END ===
     * ```
     *
     * @example Web Output
     * ```php
     * DebugHelper::dump(['users' => [1, 2, 3], 'meta' => null]);
     * // Styled HTML container with monospace font and proper formatting
     * ```
     *
     * @example Development Workflow
     * ```php
     * if (defined('DEBUG_MODE') && DEBUG_MODE) {
     *     DebugHelper::dump(['request' => $_POST, 'session' => $_SESSION]);
     * }
     * ```
     *
     * @see php_sapi_name() Used for environment detection
     * @see print_r() Used for CLI array formatting
     * @see htmlspecialchars() Used for web XSS protection
     * @since 1.0.0
     */
    public static function dump(array $result): void
    {
        $isCli = (php_sapi_name() === 'cli');

        if ($isCli) {
            echo "\n=== Debug Dump ===\n\n";
            foreach ($result as $key => $val) {
                echo strtoupper($key) . ":\n";
                if (is_array($val)) {
                    print_r($val);
                } else {
                    echo $val . "\n";
                }
                echo "\n";
            }
            echo "=== END ===\n\n";
            return;
        }

        echo '<div style="font-family: monospace; background: #f9f9f9; color: #333; border: 1px solid #ccc; padding: 1em; margin: 1em 0; overflow: auto;">';

        foreach ($result as $key => $val) {
            echo "<strong style='color: #005'>\$result['$key']</strong>:<br>";
            if (is_array($val)) {
                echo '<pre style="margin: 0.5em 0 1em 1em; background: #fff; padding: 0.5em; border-left: 3px solid #ccc;">' . htmlspecialchars(print_r($val, true)) . '</pre>';
            } else {
                echo '<div style="white-space: pre-wrap; margin: 0.5em 0 1em 1em; background: #fff; padding: 0.5em; border-left: 3px solid #ccc;">' . htmlspecialchars($val ?? 'null') . '</div>';

            }
        }

        echo '</div>';
    }

}
