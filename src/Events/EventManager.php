<?php
/**
 * File: /vendor/vernsix/primordyx/src/EventManager.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Events/EventManager.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Events;

use Primordyx\Config\Config;
use Primordyx\Utils\Callback;
use Throwable;

/**
 * Class EventManager
 *
 * Static event system for handling both fire-and-forget actions and returnable filters.
 *
 * Internals:
 * ----------
 * This class maintains two internal registries:
 *
 * - self::$actions : for non-returning, side-effect-driven event callbacks
 * - self::$filters : for value-transforming filter pipelines
 *
 * Structure of $actions and $filters:
 * -----------------------------------
 * [
 *     'event.name' => [                    // Event name (string)
 *         priority (int) => [              // Priority number (e.g., 10, 50, 100)
 *             [                            // One or more callbacks at this priority
 *                 'callback' => callable,  // Closure, function name, or [ClassName, 'method']
 *                 'description' => string  // Optional description for debugging
 *             ],
 *             ...
 *         ],
 *         ...
 *     ],
 *     ...
 * ]
 *
 * Priority Behavior:
 * ------------------
 * - Callbacks are grouped by numeric priority.
 * - **Higher priority numbers run before lower ones.** (e.g., 100 before 90 before 10)
 * - Callbacks at the same priority execute in the order they were added.
 * - Execution order is handled via `krsort()` — descending numeric priority.
 *
 * Filter Execution (via fire()):
 * ------------------------------
 * - Filters run **first**, in descending priority order.
 * - All filters REGISTERED TO THE EVENT BEING FIRED run — they are not subject to the floor.
 * - Each filter receives the current value and additional arguments, and returns a transformed result.
 * - The final value after all filters is passed as the first argument to actions.
 *
 * Action Execution (via fire()):
 * ------------------------------
 * - Actions run **after all filters**, in descending priority order.
 * - An internal "floor" starts at 0 and defines the **lowest action priority** eligible to run.
 * - If an action callback returns a numeric value, it can **raise** the floor (but never lower it).
 * - Any subsequent actions with lower priority are skipped.
 * - Callbacks returning null or non-numeric values do not affect the floor.
 * - Skipped actions can optionally trigger `__event.action.skipped` if `event_fire_skipped_actions` is true in config.
 * - Actions do not affect the return value of `fire()`.
 *
 * Return Behavior:
 * ----------------
 * - If filters are registered for the event:
 *     - `fire()` returns the final transformed value from the last filter.
 * - If no filters are present:
 *     - `fire()` returns the first argument passed (i.e., `$args[0] ?? null`).
 *
 * Listener Inspection:
 * --------------------
 * The `listeners()` method returns an array of all registered callbacks, grouped by event,
 * including type (action/filter), priority, description, and callback metadata.
 *
 *
 * Example Usage:
 * --------------
 *
 * // Register an action to send a welcome email after registration
 * EventManager::add_action('user.register', function($user) {
 *     Mailer::sendWelcomeEmail($user);
 * }, 100, 'Send welcome email');
 *
 * // Register another action to log the registration
 * EventManager::add_action('user.register', function($user) {
 *     Logger::info("User registered: " . $user->email);
 * }, 50, 'Log user registration');
 *
 * // Fire the event
 * EventManager::fire('user.register', $user);
 *
 *
 * // Register a filter to sanitize content before rendering
 * EventManager::add_filter('content.render', function($content) {
 *     return strip_tags($content);
 * }, 80, 'Strip HTML tags');
 *
 * // Register another filter to convert Markdown
 * EventManager::add_filter('content.render', function($content) {
 *     return Markdown::parse($content);
 * }, 60, 'Convert Markdown to HTML');
 *
 * // Apply filters and get the final rendered output
 * $safeHtml = EventManager::fire('content.render', $rawInput);
 *
 *
 * Advanced Example: Filters + Actions with Priority + Floor Control
 * -----------------------------------------------------------------
 * Suppose you're preparing outbound content for publishing,
 * and you want to:
 * - Filter the content (sanitize, transform)
 * - Log what's happening
 * - Allow a high-priority action to suppress lower-priority actions
 *
 * // Filter to clean HTML (runs first)
 * EventManager::add_filter('content.prepare', function ($content, $user) {
 *     return strip_tags($content);
 * }, 100, 'Remove unwanted HTML');
 *
 * // Filter to convert markdown to HTML (runs after cleaning)
 * EventManager::add_filter('content.prepare', function ($content, $user) {
 *     return Markdown::toHtml($content);
 * }, 90, 'Parse Markdown');
 *
 * // Filter to append a footer
 * EventManager::add_filter('content.prepare', function ($content, $user) {
 *     return $content . "\n\n-- Powered by Primordyx --";
 * }, 70, 'Append platform footer');
 *
 * // Action to check user role and suppress lower-priority actions
 * EventManager::add_action('content.prepare', function ($content, $user) {
 *     if (!$user->hasPermission('publish_advanced')) {
 *         return 90; // Raise floor: skip any action with priority < 90
 *     }
 * }, 95, 'Enforce user permission threshold');
 *
 * // Action to log that publishing was attempted
 * EventManager::add_action('content.prepare', function ($content, $user) {
 *     Logger::info("User {$user->id} is preparing content to publish.");
 * }, 80, 'Log publishing intent');
 *
 * // Fire the event
 * $finalContent = EventManager::fire('content.prepare', $rawContent, $user);
 *
 * // The result includes all filter modifications; actions can still be suppressed.
 * echo $finalContent;
 *
 * @since       1.0.0
 *
 */
class EventManager
{
    protected static array $actions = [];
    protected static array $filters = [];
    protected static bool|null $fireSkipped = null;
    protected static array $fireNotifications = [];


    /**
     * Registers an event name pattern for real-time debugging and monitoring.
     *
     * This debugging utility allows developers to monitor event firing by registering
     * partial event names or patterns. When any event is fired that contains one of
     * the registered strings, detailed logging information is automatically written
     * to the error log.
     *
     * The logging is completely non-intrusive and separate from the main framework
     * logic, making it safe to use in development without affecting performance or
     * behavior in production environments.
     *
     * ### What Gets Logged:
     * - The exact event name that was fired
     * - The total number of arguments passed to the event
     * - The actual argument values serialized as JSON
     *
     * ### Pattern Matching:
     * The function uses substring matching via `str_contains()`, so registering
     * a partial string like "user" will match events such as "user.login",
     * "user.register", "admin.user.delete", etc.
     *
     * ### Log Output Format:
     * ```
     * EVENT FIRING: {event_name} | Args ({count}): {json_args}
     * ```
     *
     * ### Performance Considerations:
     * - Notifications are stored in memory and checked on every `fire()` call
     * - JSON serialization occurs only for matching events
     * - Logging uses `error_log()` which may have I/O overhead in high-traffic scenarios
     * - Consider clearing notifications in production: `EventManager::$fireNotifications = [];`
     *
     * @param string $key The event name pattern to monitor. Can be a partial string
     *                   that will match any event containing this substring.
     *
     * @return void
     *
     * @example
     * ```php
     * // Monitor all user-related events
     * EventManager::notify('user');
     *
     * // Monitor a specific event
     * EventManager::notify('content.render');
     *
     * // Monitor authentication events
     * EventManager::notify('auth');
     *
     * // Fire an event that will be logged (matches 'user' pattern)
     * EventManager::fire('user.login', $user, $sessionData);
     *
     * // Results in error log output:
     * // EVENT FIRING: user.login | Args (2): [{"id":123,"email":"user@example.com"}, {"token":"abc123","expires":1640995200}]
     * ```
     *
     * @since 1.0.0
     */
    public static function notify(string $key): void {
        self::$fireNotifications[] = $key;
    }



    /**
     * Registers a callback to run when the specified action event is fired.
     *
     * Actions are executed in descending priority order after all filters have run.
     * Multiple callbacks can be registered at the same priority; they will run
     * in the order they were added.
     *
     * If a callback returns a numeric value during execution, it may raise the
     * "priority floor" — suppressing execution of any subsequent actions with a
     * lower priority. Callbacks that return null or non-numeric values do not affect the floor.
     *
     * If the floor prevents a callback from running, and the `event_fire_skipped_actions` setting
     * is enabled via `Bundle::appConfig()`, the system will emit a
     * `__event.action.skipped` event with metadata about the skipped callback.
     *
     * @param string   $event       The name of the action event to listen for.
     * @param callable $callback    The function or method to invoke. Can be a closure,
     *                              function name, or an array like [ClassName::class, 'methodName'].
     * @param int      $priority    Optional. The priority of the callback. Higher numbers run first. Default is 10.
     * @param string   $description Optional. A developer-facing label used for introspection/debugging.
     *
     * @return void
     */
    public static function add_action(string $event, callable $callback, int $priority = 10, string $description = ''): void
    {
        self::$actions[$event][$priority][] = [
            'callback' => $callback,
            'description' => $description,
        ];
    }


    /**
     * Registers a callback to modify a value when the specified filter event is fired.
     *
     * Filters are executed in descending priority order when `fire()` is called.
     * Multiple callbacks can be registered at the same priority; they will run
     * in the order they were added.
     *
     * Each filter receives the current value as its first argument and may return a
     * modified version. The final return value of `fire()` reflects the output of
     * the last filter that ran.
     *
     * Filters always run, regardless of any priority floor raised by actions.
     * They cannot suppress or skip one another, and they do not influence the floor.
     *
     * @param string   $event       The name of the filter event to listen for.
     * @param callable $callback    The function or method to invoke. Can be a closure,
     *                              function name, or an array like [ClassName::class, 'methodName'].
     * @param int      $priority    Optional. The priority of the callback. Higher numbers run first. Default is 10.
     * @param string   $description Optional. A developer-facing label used for introspection/debugging.
     *
     * @return void
     */
    public static function add_filter(string $event, callable $callback, int $priority = 10, string $description = ''): void
    {
        self::$filters[$event][$priority][] = [
            'callback' => $callback,
            'description' => $description,
        ];
    }

    /**
     * Fires an event, triggering all registered filters and actions FOR THE GIVEN EVENT NAME.
     *
     * ### Filter Behavior:
     * - Filters are executed first, in descending priority order.
     * - All registered filters run — they are not subject to the floor.
     * - Each filter receives the current value (the first argument to `fire()`) and any additional arguments.
     * - Each filter must return a value, which is passed to the next filter in the chain.
     * - The final filtered value is passed as the first argument to the actions that follow.
     *
     * ### Action Behavior:
     * - Actions are executed after filters, in descending priority order.
     * - A "floor" mechanism starts at 0 and suppresses execution of any action with a lower priority.
     * - If an action returns a numeric value, it may raise the floor (but never lower it).
     * - Callbacks returning null or non-numeric values do not affect the floor.
     * - Actions do not affect the return value of this method.
     *
     * ### Skipped Action Notifications:
     * - If the `event_fire_skipped_actions` setting is enabled via your ini (returned by `Bundle::appConfig()->getBool()`,
     *   then any action callback skipped due to the floor will trigger a call to:
     *   `EventManager::fire('__event.action.skipped', $event, $priority, $info, $description)`
     * - This can be used by developers to log or monitor which callbacks were suppressed and why.
     * - $info will look something like this...
     *          [
     *              'label'      => 'MyClass::handleLogin',    // Human-readable identifier
     *              'type'       => 'method',                  // 'function', 'closure', or 'method'
     *              'is_static'  => true,                      // true if static method
     *              'class'      => 'MyClass',                 // Present for class methods
     *              'method'     => 'handleLogin',             // Present for class methods
     *              'function'   => null,                      // Present if global function
     *              'file'       => '/var/www/app/MyClass.php',// Path to file where callback is defined
     *              'line'       => 42,                        // Line number (if available)
     *          ]
     *
     * ### Return Value:
     * - If filters are registered and run: returns the final filtered value.
     * - If no filters are registered: returns the first argument passed to `fire()` (i.e., `$args[0] ?? null`).
     *
     * @param string $event The name of the event to fire.
     * @param mixed  ...$args Arguments to pass to each filter and action callback.
     *
     * @return mixed The final result of filter processing, or the first argument if no filters exist.
     */
    public static function fire(string $event, ...$args): mixed
    {

        // error_log matching events to help debug issues like events not firing or incorrect argument counts
        // this keeps the logging completely out of the Primordyx
        foreach (self::$fireNotifications as $needle) {
            if (str_contains($event, $needle)) {
                error_log(sprintf(
                    'EVENT FIRING: %s | Args (%d): %s',
                    $event,
                    count($args),
                    json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ));
            }
        }


        if (self::$fireSkipped === null) {
            try {
                self::$fireSkipped = Config::getBool('event_fire_skipped_actions', 'app');
            } catch (Throwable) {
                // App config not initialized - disable skipped action notifications
                self::$fireSkipped = false;
            }
        }

        // Run filters first — filters are always executed in descending priority
        if (!empty(self::$filters[$event])) {
            krsort(self::$filters[$event]); // descending
            $value = $args[0] ?? null;

            foreach (self::$filters[$event] as $priorityGroup) {
                foreach ($priorityGroup as $item) {
                    try {
                        $value = call_user_func_array(
                            $item['callback'],
                            array_merge([$value], array_slice($args, 1))
                        );
                    } catch (Throwable $e) {
                        // FRAMEWORK FIX: Log filter errors but continue
                        error_log("EventManager: Filter failed for event '{$event}': " . $e->getMessage());
                        // Continue with current value unchanged
                    }
                }
            }

            // Update the first arg for actions
            $args[0] = $value;
        }

        $floor = 0;

        // Run actions second — floor may suppress lower-priority actions
        if (!empty(self::$actions[$event])) {
            krsort(self::$actions[$event]); // descending

            foreach (self::$actions[$event] as $priority => $priorityGroup) {

                if ($priority < $floor) {
                    if (self::$fireSkipped) {
                        foreach ($priorityGroup as $item) {
                            try {
                                $info = Callback::info($item['callback']);
                                self::fire('__event.action.skipped', $event, $priority, $info, $item['description'] ?? '');
                            } catch (Throwable $e) {
                                // FRAMEWORK FIX: Ignore errors in skipped action notifications
                                error_log("EventManager: Skipped action notification failed: " . $e->getMessage());
                            }
                        }
                    }
                    continue;
                }

                foreach ($priorityGroup as $item) {
                    try {
                        $result = call_user_func_array($item['callback'], $args);
                        if (is_numeric($result) && $result > $floor) {
                            $floor = (int)$result;
                        }
                    } catch (Throwable $e) {
                        // FRAMEWORK FIX: Log action errors but continue
                        error_log("EventManager: Action failed for event '{$event}': " . $e->getMessage());
                        // Continue processing other actions
                    }
                }
            }
        }

        return $args[0] ?? null;
    }


    /**
     * Returns a list of all registered action and filter listeners, optionally scoped to a single event.
     *
     * This method is primarily used for debugging, introspection, or developer tools.
     * Each returned listener includes metadata such as type, priority, callback label,
     * resolved callback info (from Callback::info), and optional description.
     *
     * If an event name is provided, only listeners registered for that event are returned.
     * If no event is specified, all listeners across all events are returned.
     *
     * @param string|null $event Optional. The specific event name to inspect. If null, returns all listeners.
     *
     * @return array An associative array of listeners grouped by event name. Each group contains
     *               an indexed array of listeners with the following structure:
     *               [
     *                   'type' => 'action' | 'filter',
     *                   'priority' => int,
     *                   'label' => string,        // Human-readable label of the callback
     *                   'info' => array,          // Raw info from Callback::info()
     *                   'description' => string   // Developer-defined description (optional)
     *               ]
     */
    public static function listeners(string $event = null): array
    {
        $result = [];

        $collect = function (array $group, string $type) use (&$result, $event) {
            foreach ($group as $evt => $priorities) {
                if ($event !== null && $evt !== $event) continue;
                foreach ($priorities as $priority => $callbacks) {
                    foreach ($callbacks as $item) {
                        $callback = $item['callback'];
                        $description = $item['description'] ?? '';


                        $info = Callback::info($callback);
                        /*
                         * $info will look something like this...
                         * [
                         *      'label'      => 'MyClass::handleLogin',    // Human-readable identifier
                         *      'type'       => 'method',                  // 'function', 'closure', or 'method'
                         *      'is_static'  => true,                      // true if static method
                         *      'class'      => 'MyClass',                 // Present for class methods
                         *      'method'     => 'handleLogin',             // Present for class methods
                         *      'function'   => null,                      // Present if global function
                         *      'file'       => '/var/www/app/MyClass.php',// Path to file where callback is defined
                         *      'line'       => 42,                        // Line number (if available)
                         * ]
                         */

                        $result[$evt][] = [
                            'type' => $type,
                            'priority' => $priority,
                            'label' => $info['label'],
                            'info' => $info,
                            'description' => $description,
                        ];
                    }
                }
            }
        };

        $collect(self::$actions, 'action');
        $collect(self::$filters, 'filter');

        return $result;
    }

}
