<?php
/**
 * File: /vendor/vernsix/primordyx/src/Callback.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Callback.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Class Callback
 *
 * A comprehensive utility class for inspecting and analyzing PHP callables.
 *
 * This class provides detailed introspection capabilities for various types of
 * callables including functions, methods (static and instance), closures, and
 * invokable objects. It leverages PHP's reflection capabilities to extract
 * metadata about parameters, file locations, and callable types.
 *
 * @since 1.0.0
 *
 */
class Callback
{

    /**
     * Analyzes a callable and returns comprehensive information about it.
     *
     * This method inspects various types of PHP callables and extracts detailed
     * metadata including parameter counts, callable type, source location, and
     * a human-readable label. It handles all standard PHP callable formats:
     *
     * - String function names: 'strlen', 'array_map'
     * - Array method calls: [$object, 'method'], ['Class', 'staticMethod']
     * - Closure objects: function() { ... }
     * - Invokable objects: objects implementing __invoke()
     *
     * The method is fault-tolerant and will return error information for
     * invalid callables rather than throwing exceptions.
     *
     * @param callable $callback The callable to analyze. Can be a function name,
     *                          array containing [object/class, method], closure,
     *                          or invokable object.
     *
     * @return array{
     *     type: string,
     *     label: string,
     *     required: int,
     *     total: int,
     *     name: string,
     *     filename: string|false
     * } Associative array containing:
     *   - 'type': Type of callable ('function', 'method', 'closure', 'invokable', 'unknown', 'invalid')
     *   - 'label': Human-readable description of the callable
     *   - 'required': Number of required parameters (non-optional)
     *   - 'total': Total number of parameters including optional ones
     *   - 'name': Internal name of the function/method
     *   - 'filename': Path to file where callable is defined, or false if internal
     *
     * @example
     * ```php
     * // Analyzing a built-in function
     * $info = Callback::info('strlen');
     * // Returns: ['type' => 'function', 'label' => 'strlen', 'required' => 1, 'total' => 1, ...]
     *
     * // Analyzing a method call
     * $info = Callback::info([$myObject, 'doSomething']);
     * // Returns: ['type' => 'method', 'label' => 'MyClass->doSomething', ...]
     *
     * // Analyzing a static method
     * $info = Callback::info(['MyClass', 'staticMethod']);
     * // Returns: ['type' => 'method', 'label' => 'MyClass::staticMethod', ...]
     *
     * // Analyzing a closure
     * $closure = function($a, $b = 'default') { return $a . $b; };
     * $info = Callback::info($closure);
     * // Returns: ['type' => 'closure', 'label' => 'Closure@line 15', 'required' => 1, 'total' => 2, ...]
     * ```
     *
     */
    public static function info(callable $callback): array
    {
        try {
            if (is_string($callback)) {
                $reflection = new ReflectionFunction($callback);


                return [
                    'type' => 'function',
                    'label' => $callback,
                    'required' => $reflection->getNumberOfRequiredParameters(),
                    'total' => $reflection->getNumberOfParameters(),
                    'name' => $reflection->getName(),
                    'filename' => $reflection->getFileName()
                ];
            }

            if (is_array($callback) && count($callback) === 2) {
                $reflection = new ReflectionMethod($callback[0], $callback[1]);
                $label = is_object($callback[0])
                    ? get_class($callback[0]) . '->' . $callback[1]
                    : $callback[0] . '::' . $callback[1];

                return [
                    'type' => 'method',
                    'label' => $label,
                    'required' => $reflection->getNumberOfRequiredParameters(),
                    'total' => $reflection->getNumberOfParameters(),
                    'name' => $reflection->getName(),
                    'filename' => $reflection->getFileName()
                ];
            }

            if ($callback instanceof Closure) {
                $reflection = new ReflectionFunction($callback);
                return [
                    'type' => 'closure',
                    'label' => 'Closure@line ' . $reflection->getStartLine(),
                    'required' => $reflection->getNumberOfRequiredParameters(),
                    'total' => $reflection->getNumberOfParameters(),
                    'name' => $reflection->getName(),
                    'filename' => $reflection->getFileName()
                ];
            }

            if (is_object($callback) && method_exists($callback, '__invoke')) {
                /** @var object $callback */
                $class = get_class($callback);
                $reflection = new ReflectionMethod($callback, '__invoke');
                return [
                    'type' => 'invokable',
                    'label' => $class . '::__invoke',
                    'required' => $reflection->getNumberOfRequiredParameters(),
                    'total' => $reflection->getNumberOfParameters(),
                    'name' => $reflection->getName(),
                    'filename' => $reflection->getFileName()
                ];
            }

            return [
                'type' => 'unknown',
                'label' => 'unknown',
                'required' => 0,
                'total' => 0,
                'name' => 'unknown',
                'filename' => 'unknown'
            ];
        } catch (Throwable $e) {
            return [
                'type' => 'invalid',
                'label' => 'error: ' . $e->getMessage(),
                'required' => 0,
                'total' => 0,
                'name' => 'invalid',
                'filename' => 'invalid'
            ];
        }
    }  // info

}