<?php
/**
 * File: /vendor/vernsix/primordyx/src/Timer.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Timer.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Time;

use Exception;
use Primordyx\Events\EventManager;

/**
 * Class Timer
 *
 * A static utility class for tracking named execution timers with microsecond precision.
 * Supports multiple concurrent timers, lap timing, UTC/local time modes, and comprehensive
 * metrics collection. Integrates with Primordyx EventManager for timer lifecycle events.
 *
 * Features:
 * - Named timer management with start/stop/restart functionality
 * - Lap timing for performance profiling within timer sessions
 * - Real-time elapsed time calculation for running timers
 * - Comprehensive metrics including longest/shortest timers and averages
 * - Event firing for timer lifecycle (started, stopped, removed, etc.)
 * - JSON export capabilities for timer data persistence
 * - Bulk operations on all running timers
 *
 * Usage Examples:
 *
 * Basic timer operations:
 *   Timer::start('api_call', 'Fetching user data from API');
 *   // ... perform work ...
 *   $elapsed = Timer::stop('api_call');
 *   echo Timer::summary('api_call'); // "[api_call] [Fetching user data from API] - 1.234s"
 *
 * Lap timing for detailed profiling:
 *   Timer::start('database_batch');
 *   Timer::lap('database_batch', 'Connected to DB');
 *   // ... database operations ...
 *   Timer::lap('database_batch', 'Finished user queries');
 *   // ... more operations ...
 *   Timer::stop('database_batch');
 *
 * Metrics and analysis:
 *   $metrics = Timer::metrics();
 *   echo "Total active timers: {$metrics['total']}";
 *   echo "Average execution time: {$metrics['average_elapsed']}s";
 *
 * Bulk operations:
 *   Timer::setLapOnAllRunning('Checkpoint 1');
 *   Timer::stopAllRunning();
 *
 * @since       1.0.0
 *
 */
class Timer
{
    /**
     * Internal storage for all active and stopped timers, indexed by timer key.
     *
     * Each timer entry contains:
     * - 'key': string - The unique identifier for this timer
     * - 'start': float - Unix timestamp with microseconds when timer was started
     * - 'end': float|null - Unix timestamp with microseconds when timer was stopped (null if running)
     * - 'elapsed': float|null - Total elapsed seconds (null if still running)
     * - 'description': string - Human-readable description of what this timer tracks
     * - 'running': int - 1 if timer is active, 0 if stopped
     * - 'laps': array - Array of lap entries with 'label', 'timestamp', and 'elapsed' keys
     *
     * @var array<string, array{key: string, start: float, end: float|null, elapsed: float|null, description: string, running: int, laps: array}>
     */
    protected static array $timers = [];

    /**
     * Controls whether time formatting uses UTC timezone.
     *
     * When true, all time formatting operations will use UTC timezone.
     * When false, uses the system's local timezone for time display.
     * This setting affects human-readable time output but does not impact
     * the underlying timer calculations which always use Unix timestamps.
     *
     * @var bool
     */
    protected static bool $useUtc = true;

    /**
     * Global flag to enable or disable timer activity logging.
     *
     * When enabled, timer operations may generate log entries or trigger
     * additional diagnostic output. This is separate from the EventManager
     * events that are always fired regardless of this setting.
     *
     * @var bool
     */
    protected static bool $logging = true;

    /**
     * Controls debug mode for additional verbose timer information.
     *
     * When enabled, provides more detailed logging and diagnostic information
     * about timer operations. Used primarily during development and troubleshooting.
     *
     * @var bool
     */
    protected static bool $debug = false;

    /**
     * Enable or disable logging of timer activity.
     *
     * This method acts as both a getter and setter. When called without parameters,
     * it returns the current logging state. When called with a boolean parameter,
     * it updates the logging state and returns the previous value.
     *
     * @param bool|null $on True to enable logging, false to disable, null to just get current state.
     * @return bool The previous logging state before any changes were made.
     */
    public static function logging(?bool $on = null): bool
    {
        $previous = self::$logging;
        if ($on !== null) {
            self::$logging = $on;
        }
        return $previous;
    }

    /**
     * Enable or disable debug mode for verbose timer information.
     *
     * Debug mode provides additional diagnostic information about timer operations.
     * This method acts as both getter and setter, returning the previous state
     * when making changes.
     *
     * @param bool|null $on True to enable debug mode, false to disable, null to just get current state.
     * @return bool The previous debug state before any changes were made.
     */
    public static function debug(?bool $on = null): bool
    {
        $previous = self::$debug;
        if ($on !== null) {
            self::$debug = $on;
        }
        return $previous;
    }

    /**
     * Get or set the UTC timezone formatting preference.
     *
     * Controls whether human-readable time formatting uses UTC or local timezone.
     * Does not affect the underlying timer calculations which always use Unix timestamps.
     *
     * @param bool|null $on True for UTC formatting, false for local timezone, null to just get current state.
     * @return bool The current UTC formatting state (before any changes if setting).
     */
    public static function useUtc(?bool $on = null): bool
    {
        $current = self::$useUtc;
        if (is_bool($on)) self::$useUtc = $on;
        return $current;
    }

    /**
     * Retrieve timer data by key, automatically creating the timer if it doesn't exist.
     *
     * This is a convenience method that ensures a timer exists before returning its data.
     * If the requested timer doesn't exist, it will be automatically started with a
     * default description indicating it was auto-created.
     *
     * @param string $key The unique identifier for the timer.
     * @return array The complete timer data array containing all timer properties.
     */
    public static function getTimer(string $key): array
    {
        if (!isset(self::$timers[$key])) self::start($key, "Called getTimer but it wasn't available yet, so I created one");
        return self::$timers[$key];
    }

    /**
     * Return the complete internal timers array without any processing.
     *
     * Provides direct access to the raw timer storage for advanced use cases,
     * debugging, or when you need to inspect the exact internal timer structure.
     * Use with caution as direct manipulation of this data could break timer consistency.
     *
     * @return array The complete internal timers array with all timer data.
     */
    public static function timersRaw(): array
    {
        return self::$timers;
    }

    /**
     * Check whether a timer with the specified key exists.
     *
     * Returns true if a timer has been created with the given key, regardless
     * of whether it's currently running or stopped. Returns false if no timer
     * with that key has been created.
     *
     * @param string $key The timer key to check for existence.
     * @return bool True if the timer exists, false otherwise.
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function has(string $key): bool
    {
        return isset(self::$timers[$key]);
    }

    /**
     * Start a new timer with the specified key and optional description.
     *
     * Creates a new timer entry and begins timing immediately. If a timer with the
     * same key already exists, it will be overwritten. The timer starts in a "running"
     * state and will continue until explicitly stopped.
     *
     * Fires 'timer.started' event through EventManager with the timer data.
     *
     * @param string $key Unique identifier for this timer (e.g., 'api_call', 'database_query').
     * @param string $description Human-readable description of what this timer tracks.
     * @return float The Unix timestamp (with microseconds) when the timer was started.
     */
    public static function start(string $key, string $description = ''): float
    {
        $now = TimeHelper::now();
        self::$timers[$key] = [
            'key' => $key,
            'start' => $now,
            'end' => null,
            'elapsed' => null,
            'description' => $description,
            'running' => 1,
            'laps' => []
        ];
        EventManager::fire('timer.started', self::$timers[$key]);
        return $now;
    }

    /**
     * Stop a running timer and calculate its total elapsed time.
     *
     * Marks the timer as stopped, records the end timestamp, and calculates the
     * total elapsed time in seconds. If the timer doesn't exist, it will be
     * automatically created first (with a default description) and then immediately stopped.
     *
     * Fires 'timer.stopped' event through EventManager with the final timer data.
     *
     * @param string $key The unique identifier of the timer to stop.
     * @return float Total elapsed time in seconds (with microsecond precision).
     */
    public static function stop(string $key): float
    {
        $t = self::getTimer($key);
        $end = TimeHelper::now();
        $start = $t['start'];
        $elapsed = $end - $start;
        self::$timers[$key]['end'] = $end;
        self::$timers[$key]['elapsed'] = $elapsed;
        self::$timers[$key]['running'] = 0;
        EventManager::fire('timer.stopped', self::$timers[$key]);
        return $elapsed;
    }

    /**
     * Completely remove a timer from the internal storage.
     *
     * Permanently deletes the timer and all its associated data including
     * lap times and elapsed time information. This action cannot be undone.
     *
     * Fires 'timer.removed' event through EventManager before deletion.
     *
     * @param string $key The unique identifier of the timer to remove.
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function remove(string $key): void
    {
        EventManager::fire('timer.removed', self::$timers[$key]);
        unset(self::$timers[$key]);
    }

    /**
     * Get the current elapsed time for a timer in seconds.
     *
     * For running timers, calculates elapsed time from start to current time.
     * For stopped timers, returns the final elapsed time that was calculated when stopped.
     * If the timer doesn't exist, it will be automatically created first.
     *
     * @param string $key The unique identifier of the timer.
     * @return int|null Elapsed time in seconds, or null if calculation fails.
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function elapsed(string $key): int|null
    {
        $t = self::getTimer($key);
        $start = $t['start'];
        $end = $t['running'] ? TimeHelper::now() : ($t['end'] ?? TimeHelper::now());
        return $t['running'] ? $end - $start : $t['elapsed'];
    }

    /**
     * Remove all timers and reset the internal timer storage.
     *
     * Permanently deletes all timer data including running and stopped timers.
     * This operation cannot be undone and will clear all lap times and metrics.
     *
     * Fires 'timer.cleared_all' event through EventManager before clearing.
     *
     * @return void
     */
    public static function clear(): void
    {
        EventManager::fire('timer.cleared_all', self::$timers);
        self::$timers = [];
    }

    /**
     * Stop an existing timer and immediately restart it with a new description.
     *
     * This is equivalent to calling stop() followed by start(), but maintains
     * the timer's history and fires appropriate lifecycle events. Useful for
     * resetting a timer while keeping the same key and updating its purpose.
     *
     * Fires multiple events: 'timer.restarting', 'timer.stopped', 'timer.started', and 'timer.restarted'.
     *
     * @param string $key The unique identifier of the timer to restart.
     * @param string $description New description for the restarted timer.
     * @return float The Unix timestamp when the timer was restarted.
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function restart(string $key, string $description = ''): float
    {
        EventManager::fire('timer.restarting', self::$timers[$key]);
        self::stop($key);
        $now = self::start($key, $description);
        EventManager::fire('timer.restarted', self::$timers[$key]);
        return $now;
    }

    /**
     * Add a lap marker to a running timer for performance profiling.
     *
     * Records an intermediate timing point within a timer's execution, useful for
     * tracking performance of different phases within a longer operation. Each lap
     * includes a timestamp and the elapsed time from the timer's start.
     *
     * If the timer doesn't exist, it will be automatically created first.
     * Fires 'timer.lap.set' event through EventManager with updated timer data.
     *
     * @param string $key The unique identifier of the timer to add a lap to.
     * @param string $label Descriptive label for this lap (e.g., 'Database connected', 'Data processed').
     * @return float The Unix timestamp when the lap was recorded.
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function lap(string $key, string $label = ''): float
    {
        if (!isset(self::$timers[$key])) self::start($key);
        $now = TimeHelper::now();
        self::$timers[$key]['laps'][] = [
            'label' => $label,
            'timestamp' => $now,
            'elapsed' => $now - self::$timers[$key]['start']
        ];
        EventManager::fire('timer.lap.set', self::$timers[$key]);
        return $now;
    }

    /**
     * Find the fastest lap time across all timers and their lap data.
     *
     * Searches through all recorded laps in all timers to find the one with
     * the shortest elapsed time. Useful for identifying the best performance
     * among similar operations being timed across different timer instances.
     *
     * @return array Associative array containing 'timer_key', 'label', 'elapsed', and 'elapsed_human' for the fastest lap, or empty array if no laps exist.
     */
    public static function fastestLap(): array
    {
        $fastest = [];

        foreach (self::$timers as $key => $timer) {
            foreach ($timer['laps'] ?? [] as $lap) {
                if (!isset($lap['elapsed'])) {
                    continue;
                }

                if (empty($fastest) || $lap['elapsed'] < $fastest['elapsed']) {
                    $fastest = [
                        'timer_key' => $key,
                        'label' => $lap['label'] ?? '',
                        'elapsed' => $lap['elapsed'],
                        'elapsed_human' => TimeHelper::elapsedDescription(0, $lap['elapsed'])
                    ];
                }
            }
        }

        return $fastest;
    }

    /**
     * Retrieve all lap data for a specific timer.
     *
     * Returns an array of all lap markers that have been recorded for the specified timer.
     * Each lap entry contains the label, timestamp, and elapsed time from timer start.
     *
     * @param string $key The unique identifier of the timer.
     * @return array Array of lap data, each containing 'label', 'timestamp', and 'elapsed' keys.
     */
    public static function getLaps(string $key): array
    {
        return self::$timers[$key]['laps'] ?? [];
    }

    /**
     * Get the current operational status of a timer.
     *
     * Returns a string indicating whether the timer is currently running or has been stopped.
     * Useful for conditional logic based on timer state.
     *
     * @param string $key The unique identifier of the timer.
     * @return string|null 'running' if timer is active, 'stopped' if timer has been stopped, null if timer doesn't exist.
     */
    public static function status(string $key): ?string
    {
        if (!isset(self::$timers[$key])) return null;
        return self::$timers[$key]['running'] ? 'running' : 'stopped';
    }

    /**
     * Get the description text for a timer.
     *
     * Returns the human-readable description that was provided when the timer
     * was created or last restarted. Useful for displaying timer purpose in logs or reports.
     *
     * @param string $key The unique identifier of the timer.
     * @return string|null The timer's description, or null if timer doesn't exist.
     */
    public static function description(string $key): ?string
    {
        if (!isset(self::$timers[$key])) return null;
        return self::$timers[$key]['description'];
    }

    /**
     * Update the description of an existing timer.
     *
     * Allows changing the descriptive text associated with a timer without
     * affecting its timing data. If the timer doesn't exist, it will be
     * automatically created with the new description.
     *
     * Fires 'timer.description.changing' and 'timer.description.changed' events.
     *
     * @param string $key The unique identifier of the timer.
     * @param string $description The new description text to assign.
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function changeDescription(string $key, string $description): void
    {
        if (!isset(self::$timers[$key])) self::start($key);
        EventManager::fire('timer.description.changing', self::$timers[$key]);
        self::$timers[$key]['description'] = $description;
        EventManager::fire('timer.description.changed', self::$timers[$key]);
    }

    /**
     * Export a timer's complete data as formatted JSON.
     *
     * Converts all timer data including timing information, description, and lap data
     * into a pretty-printed JSON string suitable for logging, debugging, or persistence.
     *
     * @param string $key The unique identifier of the timer.
     * @return string|null Pretty-printed JSON representation of timer data, or null if timer doesn't exist.
     */
    public static function asJson(string $key): ?string
    {
        if (!isset(self::$timers[$key])) return null;
        return json_encode(self::$timers[$key], JSON_PRETTY_PRINT);
    }

    /**
     * Generate a human-readable summary string for a timer.
     *
     * Creates a concise, formatted string showing the timer key, description (if present),
     * and elapsed time in human-readable format. Perfect for logging or display purposes.
     *
     * Format: "[timer_key] [description] - elapsed_time"
     * Example: "[api_call] [Fetching user data] - 1.234s"
     *
     * @param string $key The unique identifier of the timer.
     * @return string|null Formatted summary string, or null if timer doesn't exist.
     */
    public static function summary(string $key): ?string
    {
        if (!isset(self::$timers[$key])) return null;
        $t = self::$timers[$key];
        $description = $t['description'] ? " [{$t['description']}]" : '';
        $elapsed = $t['running'] ? TimeHelper::elapsedDescription($t['start']) : TimeHelper::elapsedDescription($t['start'], $t['end']);
        return "[$key]$description - $elapsed";
    }

    /**
     * Stop all currently running timers.
     *
     * Iterates through all active timers and stops each one that is currently running.
     * Stopped timers are left unchanged. This is useful for bulk cleanup operations
     * or when shutting down a process.
     *
     * Fires 'timer.stopping_all_running' event before stopping any timers.
     *
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function stopAllRunning(): void
    {
        EventManager::fire('timer.stopping_all_running', self::$timers);
        foreach (self::$timers as $key => $t) {
            if ($t['running']) {
                self::stop($key);
            }
        }
    }

    /**
     * Internal utility method to find either the longest or shortest timer.
     *
     * Searches through all timers (or just running ones) to find the extreme value
     * based on elapsed time. Used by the longest() and shortest() public methods.
     *
     * @param bool $includeRunningOnly If true, only considers currently running timers.
     * @param string $mode Either 'longest' or 'shortest' to determine which extreme to find.
     * @return array|null Array with 'key', 'description', 'elapsed', and 'elapsed_human' keys, or null if no timers match criteria.
     */
    private static function getExtreme(bool $includeRunningOnly, string $mode): ?array
    {
        $extreme = null;
        $now = TimeHelper::now();

        foreach (self::$timers as $key => $t) {
            if ($includeRunningOnly && !$t['running']) {
                continue;
            }

            $start = $t['start'];
            $end = $t['running'] ? $now : ($t['end'] ?? $now);
            $elapsed = $t['running'] ? $end - $start : $t['elapsed'];
            $elapsedHuman = TimeHelper::elapsedDescription($start, $end);

            $isBetter = match ($mode) {
                'longest' => $extreme === null || $elapsed > $extreme['elapsed'],
                'shortest' => $extreme === null || $elapsed < $extreme['elapsed'],
                default => false,
            };

            if ($isBetter) {
                $extreme = [
                    'key' => $key,
                    'description' => $t['description'],
                    'elapsed' => $elapsed,
                    'elapsed_human' => $elapsedHuman
                ];
            }
        }

        return $extreme;
    }

    /**
     * Find the timer with the longest elapsed time.
     *
     * Searches through all timers to find the one with the greatest elapsed time.
     * Can optionally be restricted to only running timers. For running timers,
     * elapsed time is calculated from start to current time.
     *
     * @param bool $includeRunningOnly If true, only considers currently running timers.
     * @return array|null Array containing 'key', 'description', 'elapsed', and 'elapsed_human', or null if no timers exist.
     */
    public static function longest(bool $includeRunningOnly = false): ?array
    {
        return self::getExtreme($includeRunningOnly, 'longest');
    }

    /**
     * Find the timer with the shortest elapsed time.
     *
     * Searches through all timers to find the one with the least elapsed time.
     * Can optionally be restricted to only running timers. For running timers,
     * elapsed time is calculated from start to current time.
     *
     * @param bool $includeRunningOnly If true, only considers currently running timers.
     * @return array|null Array containing 'key', 'description', 'elapsed', and 'elapsed_human', or null if no timers exist.
     */
    public static function shortest(bool $includeRunningOnly = false): ?array
    {
        return self::getExtreme($includeRunningOnly, 'shortest');
    }

    /**
     * Generate comprehensive metrics and statistics across all timers.
     *
     * Calculates aggregate statistics including timer counts, total elapsed time,
     * averages, and identifies the longest and shortest timers. Useful for
     * performance analysis and system monitoring.
     *
     * @param bool $includeRunningOnly If true, calculations only include currently running timers.
     * @return array Comprehensive metrics array containing:
     *   - 'total': Total number of timers included in calculations
     *   - 'running': Number of currently running timers
     *   - 'stopped': Number of stopped timers
     *   - 'total_elapsed': Sum of all elapsed times in seconds
     *   - 'average_elapsed': Average elapsed time in seconds
     *   - 'longest': Array with details of longest timer (or null)
     *   - 'shortest': Array with details of shortest timer (or null)
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function metrics(bool $includeRunningOnly = false): array
    {
        $count = 0;
        $totalElapsed = 0.0;
        $runningCount = 0;
        $stoppedCount = 0;

        foreach (self::$timers as $t) {
            if ($includeRunningOnly && !$t['running']) {
                continue;
            }

            $elapsed = $t['running']
                ? TimeHelper::now() - $t['start']
                : ($t['elapsed'] ?? 0);

            $totalElapsed += $elapsed;
            $count++;

            if ($t['running']) {
                $runningCount++;
            } else {
                $stoppedCount++;
            }
        }

        $average = $count ? $totalElapsed / $count : 0;

        $longest = self::longest($includeRunningOnly);
        $shortest = self::shortest($includeRunningOnly);

        return [
            'total' => $count,
            'running' => $runningCount,
            'stopped' => $stoppedCount,
            'total_elapsed' => round($totalElapsed, 6),
            'average_elapsed' => round($average, 6),
            'longest' => $longest ? [
                'key' => $longest['key'],
                'description' => $longest['description'],
                'elapsed' => $longest['elapsed'],
                'elapsed_human' => $longest['elapsed_human']
            ] : null,
            'shortest' => $shortest ? [
                'key' => $shortest['key'],
                'description' => $shortest['description'],
                'elapsed' => $shortest['elapsed'],
                'elapsed_human' => $shortest['elapsed_human']
            ] : null
        ];
    }

    /**
     * Export all timer data as pretty-printed JSON.
     *
     * Converts the complete internal timer storage to a formatted JSON string
     * suitable for logging, debugging, or external analysis. Includes all timer
     * data, lap times, and metadata.
     *
     * @return string Pretty-printed JSON representation of all timer data.
     */
    public static function allRawJson(): string
    {
        return json_encode(self::$timers, JSON_PRETTY_PRINT);
    }

    /**
     * Add a lap marker to all currently running timers.
     *
     * Convenient bulk operation that applies the same lap label to every timer
     * that is currently in a running state. Useful for marking synchronization
     * points across multiple concurrent operations.
     *
     * Fires 'timer.set_lap_on_all_running' event before processing.
     *
     * @param string $label Descriptive label to apply to each lap.
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function setLapOnAllRunning(string $label = ''): void
    {
        EventManager::fire('timer.set_lap_on_all_running', self::$timers);
        foreach (self::$timers as $key => $t) {
            if ($t['running']) {
                self::lap($key, $label);
            }
        }
    }

    /**
     * Add a final lap to all running timers and then stop them.
     *
     * Bulk operation that first adds a lap marker (typically labeled as "Final lap")
     * to all running timers, then stops each of them. Useful for graceful shutdown
     * or end-of-process timing collection.
     *
     * Fires 'timer.stopping_all_with_laps' event before processing.
     *
     * @param string $lapLabel Label for the final lap added to each timer before stopping.
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function stopAllWithLap(string $lapLabel = 'Final lap'): void
    {
        EventManager::fire('timer.stopping_all_with_laps', self::$timers);
        foreach (self::$timers as $key => $t) {
            if ($t['running']) {
                self::lap($key, $lapLabel);
                self::stop($key);
            }
        }
    }

    /**
     * Restart all currently running timers while preserving their descriptions.
     *
     * Bulk operation that stops and immediately restarts every running timer,
     * maintaining their original descriptions. Useful for synchronizing timing
     * periods across multiple operations or resetting timing while keeping context.
     *
     * Fires 'timer.restart_all_running' event before processing.
     *
     * @return void
     * @throws Exception If logging or time formatting operations fail.
     */
    public static function restartAllRunning(): void
    {
        EventManager::fire('timer.restart_all_running', self::$timers);
        foreach (self::$timers as $key => $t) {
            if ($t['running']) {
                $desc = $t['description'];
                self::restart($key, $desc);
            }
        }
    }

    /**
     * Export all timer data to a JSON file for persistence or analysis.
     *
     * Writes the complete timer dataset to the specified file in pretty-printed
     * JSON format. Useful for preserving timing data beyond the current process
     * lifecycle or for external analysis tools.
     *
     * @param string $filename Full path and filename where JSON data should be written.
     * @return void
     * @throws Exception If file write operations fail or if logging operations fail.
     */
    public static function export(string $filename): void
    {
        file_put_contents($filename, self::allRawJson());
    }

    /**
     * Get the most recently created timer (LIFO - Last In, First Out).
     *
     * Returns the timer data for whichever timer was most recently added to
     * the internal storage. Useful for stack-like timer management or when
     * working with nested timing operations.
     *
     * @return array|null Complete timer data array for the most recent timer, or null if no timers exist.
     */
    public static function peek(): ?array
    {
        return empty(self::$timers) ? null : end(self::$timers);
    }
}