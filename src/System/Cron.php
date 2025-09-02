<?php
/**
 * File: /vendor/vernsix/primordyx/src/Cron.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Cron.php
 *
 */

declare(strict_types=1);
namespace Primordyx\System;

use Exception;
use InvalidArgumentException;
use Primordyx\Events\EventManager;
use Primordyx\Time\Timer;
use ReflectionMethod;
use Throwable;

/**
 * Class Cron
 *
 * For scheduling and executing background jobs in the Primordyx framework.
 *
 * Jobs are stored as JSON and can include a cron-style schedule, named parameters, and an optional "once" flag.
 * Supports file-based locking, memory/timing stats, and auto-unregistering run-once jobs after execution.
 *
 * NOTE: Requires explicit initialization via Cron::init($basePath) before use.
 *
 * @since 1.0.0
 */
class Cron {
    protected static ?string $basePath = null;
    protected static string $registryFile;
    protected static string $timestampsFile;
    protected static string $lockDir;
    protected static bool $initialized = false;

    /**
     * Initialize the Cron class with the base path for cron files.
     *
     * @param string $basePath The base directory where cron files will be stored
     * @throws InvalidArgumentException If the path doesn't exist or isn't writable
     */
    public static function init(string $basePath): void {
        if (!is_dir($basePath)) {
            throw new InvalidArgumentException("Cron base path does not exist: $basePath");
        }

        if (!is_writable($basePath)) {
            throw new InvalidArgumentException("Cron base path is not writable: $basePath");
        }

        self::$basePath = rtrim($basePath, '/\\');
        self::$registryFile = self::$basePath . '/cron-jobs.json';
        self::$timestampsFile = self::$basePath . '/cron-timestamps.json';
        self::$lockDir = self::$basePath . '/cron-locks/';
        self::$initialized = true;
    }

    /**
     * Check if the Cron class has been initialized.
     */
    public static function isInitialized(): bool {
        return self::$initialized;
    }

    /**
     * Get the current base path.
     */
    public static function getBasePath(): ?string {
        return self::$basePath;
    }

    /**
     * Reset the initialization state (useful for testing).
     */
    public static function reset(): void {
        self::$basePath = null;
        self::$initialized = false;
    }

    /**
     * Ensure the class is initialized.
     *
     * @throws Exception If not initialized
     */
    protected static function ensureInitialized(): void {
        if (!self::$initialized) {
            throw new Exception(
                'Cron class not initialized. Call Cron::init($basePath) first.'
            );
        }
    }

    /**
     * Register or update a cron job for scheduled execution.
     *
     * Creates a new cron job or updates an existing one with the same ID. Jobs are persisted
     * to the registry file and will be checked for execution during each dispatch() call.
     *
     * @param string $expression Cron expression in standard 5-field format: 'minute hour day month weekday'
     *                          Supports basic patterns:
     *                          - '*' = any value
     *                          - '*\/n' = every n units (e.g., '*\/15' = every 15 minutes)
     *                          - specific numbers (e.g., '0' = exactly at minute 0)
     *                          Examples: '0 * * * *' (hourly), '*\/5 * * * *' (every 5 min), '0 0 * * 0' (weekly)
     *
     * @param string $id        Unique identifier for this job. If a job with this ID already exists,
     *                          it will be completely replaced with the new configuration.
     *
     * @param string $classMethod The callback in 'ClassName::methodName' format. The class will be
     *                           auto-loaded from the cron base path if not already loaded.
     *
     * @param array $args       Named parameters to pass to the method. Keys should match the method's
     *                          parameter names. Missing args will use defaults if available, or throw
     *                          an exception if required. Example: ['userId' => 123, 'action' => 'cleanup']
     *
     * @param bool $once        If true, the job will automatically be removed from the registry
     *                          after successful execution (run-once job). Defaults to false.
     *
     * @throws InvalidArgumentException If $classMethod is not in 'Class::method' format
     * @throws Exception If the cron system is not initialized
     *
     * @example
     * ```php
     * // Schedule a daily cleanup at midnight
     * Cron::schedule('0 0 * * *', 'daily-cleanup', 'CleanupTasks::purgeOldLogs');
     *
     * // Schedule every 10 minutes with named arguments
     * Cron::schedule('*\/10 * * * *', 'user-sync', 'UserSync::syncBatch', ['batchSize' => 100]);
     *
     * // One-time job to run at next 2 AM
     * Cron::schedule('0 2 * * *', 'migration-task', 'Migration::runV2Migration', [], true);
     * ```
     */
    public static function schedule(string $expression, string $id, string $classMethod, array $args = [], bool $once = false): void {
        self::ensureInitialized();

        if (!str_contains($classMethod, '::')) {
            throw new InvalidArgumentException("Expected 'Class::method' format for callback");
        }

        $registry = file_exists(self::$registryFile)
            ? json_decode(file_get_contents(self::$registryFile), true)
            : [];

        $registry[$id] = [
            'schedule' => $expression,
            'callback' => $classMethod,
            'args'     => $args,
            'once'     => $once,
        ];

        file_put_contents(self::$registryFile, json_encode($registry, JSON_PRETTY_PRINT));
    }

    /**
     * Get all registered cron jobs from the registry.
     *
     * Returns the complete job registry as an associative array where keys are job IDs
     * and values contain the job configuration (schedule, callback, args, once flag).
     *
     * @return array Associative array of all registered jobs. Returns empty array if no jobs
     *               are registered or registry file doesn't exist. Structure:
     *               [
     *                   'job-id' => [
     *                       'schedule' => '0 * * * *',           // Cron expression
     *                       'callback' => 'ClassName::method',   // Method to call
     *                       'args'     => ['key' => 'value'],    // Named arguments
     *                       'once'     => false                  // Run-once flag
     *                   ],
     *                   // ... more jobs
     *               ]
     *
     * @throws Exception If the cron system is not initialized
     *
     * @example
     * ```php
     * $jobs = Cron::listJobs();
     * foreach ($jobs as $id => $config) {
     *     echo "Job: $id runs {$config['schedule']} calling {$config['callback']}\n";
     * }
     * ```
     */
    public static function listJobs(): array {
        self::ensureInitialized();

        if (!file_exists(self::$registryFile)) {
            return [];
        }

        return json_decode(file_get_contents(self::$registryFile), true);
    }

    /**
     * Check all registered jobs and execute any that are due to run.
     *
     * This is the main execution method that should be called regularly (typically from a
     * system cron job every minute). It performs the following operations:
     *
     * 1. Loads all registered jobs from the registry
     * 2. Checks each job's schedule against current time and last execution
     * 3. Uses file-based locking to prevent concurrent execution of the same job
     * 4. Executes due jobs with proper argument mapping and error handling
     * 5. Tracks execution time and memory usage for each job
     * 6. Auto-removes jobs marked with 'once' flag after successful execution
     * 7. Updates timestamps and registry files
     *
     * Jobs are skipped if:
     * - Not due according to cron schedule and last run time
     * - Already locked by another process (with stale lock detection after 1 hour)
     * - Missing required arguments or invalid callback
     *
     * Events Fired:
     * - 'cron.job.skipped' => ['jobId' => $id, 'reason' => 'locked', 'age' => $seconds]
     * - 'cron.lock.stale' => ['jobId' => $id, 'age' => $seconds]
     * - 'cron.job.starting' => ['jobId' => $id, 'job' => $jobConfig]
     * - 'cron.job.completed' => ['jobId' => $id, 'elapsed' => $seconds, 'memoryPeak' => $bytes]
     * - 'cron.job.removed' => ['jobId' => $id, 'reason' => 'one-time']
     * - 'cron.job.failed' => ['jobId' => $id, 'error' => $message, 'exception' => $throwable]
     *
     * @throws Exception If the cron system is not initialized, or if critical file
     *                   operations fail (registry/timestamp file writes)
     *
     * @example
     * ```php
     * // Typical usage in a system cron job that runs every minute:
     * // * * * * * /usr/bin/php /path/to/your/app/cron-runner.php
     *
     * // In cron-runner.php:
     * Cron::init('/path/to/cron/storage');
     *
     * // Register logging actions to handle cron events
     * EventManager::add_action('cron.job.starting', function($data) {
     *     Log::info("Running cron: {$data['jobId']}");
     * });
     *
     * EventManager::add_action('cron.job.failed', function($data) {
     *     Log::error("Cron {$data['jobId']} failed: {$data['error']}");
     * });
     *
     * EventManager::add_action('cron.job.completed', function($data) {
     *     Log::info("Cron {$data['jobId']} finished in {$data['elapsed']}s using {$data['memoryPeak']} memory");
     * });
     *
     * Cron::dispatch(); // Checks and runs all due jobs
     * ```
     *
     * @see Cron::isDue() For schedule evaluation logic
     * @see Cron::schedule() For job registration
     */
    public static function dispatch(): void {
        self::ensureInitialized();

        $registry = self::listJobs();

        if (!is_dir(self::$lockDir)) {
            mkdir(self::$lockDir, 0755, true);
        }

        $timestamps = file_exists(self::$timestampsFile)
            ? json_decode(file_get_contents(self::$timestampsFile), true)
            : [];

        $now = time();

        foreach ($registry as $id => $job) {
            $last = $timestamps[$id] ?? 0;
            if (!self::isDue($job['schedule'], $now, $last)) {
                continue;
            }

            $lockFile = self::$lockDir . '/' . $id . '.lock';

            if (file_exists($lockFile)) {
                $age = $now - filemtime($lockFile);
                if ($age < 3600) {
                    EventManager::fire('cron.job.skipped', ['jobId' => $id, 'reason' => 'locked', 'age' => $age]);
                    continue;
                }
                EventManager::fire('cron.lock.stale', ['jobId' => $id, 'age' => $age]);
            }

            touch($lockFile);
            Timer::start($id);

            try {
                EventManager::fire('cron.job.starting', ['jobId' => $id, 'job' => $job]);

                [$class, $method] = explode('::', $job['callback']);
                $args = $job['args'] ?? [];

                if (!class_exists($class)) {
                    $classFile = self::$basePath . '/' . $class . '.php';
                    if (file_exists($classFile)) {
                        require_once $classFile;
                    }
                }

                if (!class_exists($class)) {
                    throw new Exception("Class $class not found for cron job $id");
                }

                if (!method_exists($class, $method)) {
                    throw new Exception("Method $method not found in class $class");
                }

                $ref = new ReflectionMethod($class, $method);
                $orderedArgs = [];

                foreach ($ref->getParameters() as $param) {
                    $name = $param->getName();
                    if (array_key_exists($name, $args)) {
                        $orderedArgs[] = $args[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $orderedArgs[] = $param->getDefaultValue();
                    } else {
                        throw new Exception("Missing required argument '$name' for $class::$method");
                    }
                }

                call_user_func_array([$class, $method], $orderedArgs);

                $timestamps[$id] = $now;
                $elapsed = Timer::stop($id);
                $mem = Timer::getTimer($id)['memory_peak'] ?? 0;
                EventManager::fire('cron.job.completed', ['jobId' => $id, 'elapsed' => $elapsed, 'memoryPeak' => $mem]);

                if (!empty($job['once'])) {
                    unset($registry[$id]);
                    EventManager::fire('cron.job.removed', ['jobId' => $id, 'reason' => 'one-time']);
                }
            } catch (Throwable $e) {
                EventManager::fire('cron.job.failed', ['jobId' => $id, 'error' => $e->getMessage(), 'exception' => $e]);
            } finally {
                if (file_exists($lockFile)) {
                    unlink($lockFile);
                }
            }
        }

        file_put_contents(self::$timestampsFile, json_encode($timestamps, JSON_PRETTY_PRINT));
        file_put_contents(self::$registryFile, json_encode($registry, JSON_PRETTY_PRINT));
    }

    /**
     * Check if a cron job is due to run based on its schedule expression and last execution time.
     *
     * Evaluates whether a job should execute by parsing the cron expression against the current
     * time and ensuring it hasn't already run within the same minute. This prevents duplicate
     * executions when dispatch() is called multiple times within a single minute.
     *
     * The cron expression must be in standard 5-field format: "minute hour day month weekday"
     * All fields must match the current time for the job to be considered due.
     *
     * Minute-level granularity: Jobs will not run more than once per minute, even if the
     * cron expression would otherwise allow it.
     *
     * @param string $expr Cron expression in 5-field format. Supported patterns:
     *                     - '*' = matches any value
     *                     - '*\/n' = matches every nth value (e.g., '*\/15' = every 15 units)
     *                     - 'n' = matches exactly value n (e.g., '0' = exactly 0)
     *                     Examples:
     *                     - '0 * * * *' = every hour at minute 0
     *                     - '*\/5 * * * *' = every 5 minutes
     *                     - '30 2 * * 1' = 2:30 AM every Monday
     *
     * @param int $now     Current Unix timestamp to evaluate against
     * @param int $last    Unix timestamp of the job's last execution (0 if never run)
     *
     * @return bool True if the job is due to run, false otherwise
     *
     * @example
     * ```php
     * $now = time();
     * $lastRun = 1735689600; // Some previous timestamp
     *
     * // Check if hourly job is due
     * if (Cron::isDue('0 * * * *', $now, $lastRun)) {
     *     echo "Hourly job should run now";
     * }
     *
     * // Check if job should run every 15 minutes
     * if (Cron::isDue('*\/15 * * * *', $now, $lastRun)) {
     *     echo "15-minute job should run now";
     * }
     * ```
     */
    public static function isDue(string $expr, int $now, int $last): bool {
        if (floor($now / 60) === floor($last / 60)) {
            return false;
        }

        [$min, $hour, $dom, $mon, $dow] = explode(' ', $expr);
        $dt = getdate($now);

        return self::match($min, $dt['minutes']) &&
            self::match($hour, $dt['hours']) &&
            self::match($dom, $dt['mday']) &&
            self::match($mon, $dt['mon']) &&
            self::match($dow, $dt['wday']);
    }

    /**
     * Check whether a single cron expression field matches a time value.
     *
     * This is a helper method used by isDue() to evaluate individual fields of a cron expression
     * against their corresponding time values. Handles the three supported cron field patterns.
     *
     * Supported patterns:
     * - '*' = wildcard, matches any value
     * - '*\/n' = step values, matches every nth occurrence (e.g., '*\/5' matches 0,5,10,15...)
     * - 'n' = exact match, matches only the specific numeric value
     *
     * @param string $expr  The cron field expression to evaluate (e.g., '*', '15', '*\/5')
     * @param int    $value The actual time value to check against (e.g., current minute, hour, etc.)
     *
     * @return bool True if the expression matches the value, false otherwise
     *
     * @example
     * ```php
     * // Wildcard always matches
     * Cron::match('*', 25);     // returns true
     * Cron::match('*', 0);      // returns true
     *
     * // Exact matches
     * Cron::match('15', 15);    // returns true
     * Cron::match('15', 30);    // returns false
     *
     * // Step values
     * Cron::match('*\/5', 0);   // returns true (0 % 5 === 0)
     * Cron::match('*\/5', 15);  // returns true (15 % 5 === 0)
     * Cron::match('*\/5', 7);   // returns false (7 % 5 !== 0)
     * ```
     */
    protected static function match(string $expr, int $value): bool {
        if ($expr === '*') {
            return true;
        }

        if (preg_match('/^\*\/(\d+)$/', $expr, $m)) {
            return $value % (int)$m[1] === 0;
        }

        return (int)$expr === $value;
    }

    /**
     * Remove a job from the schedule by ID.
     *
     * Removes the specified job from the registry file if it exists. Fires a
     * 'cron.job.unscheduled' event upon successful removal.
     *
     * @param string $id The unique identifier of the job to remove
     *
     * @return bool True if the job was found and removed, false if the job ID doesn't exist
     *
     * @throws Exception If the cron system is not initialized
     *
     * @example
     * ```php
     * if (Cron::unschedule('daily-cleanup')) {
     *     echo "Job removed successfully";
     * } else {
     *     echo "Job not found";
     * }
     * ```
     */
    public static function unschedule(string $id): bool {
        self::ensureInitialized();

        if (!file_exists(self::$registryFile)) {
            return false;
        }

        $registry = json_decode(file_get_contents(self::$registryFile), true);
        if (!isset($registry[$id])) {
            return false;
        }

        unset($registry[$id]);
        file_put_contents(self::$registryFile, json_encode($registry, JSON_PRETTY_PRINT));
        EventManager::fire('cron.job.unscheduled', ['jobId' => $id]);
        return true;
    }
}