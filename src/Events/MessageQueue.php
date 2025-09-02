<?php
/**
 * File: /vendor/vernsix/primordyx/src/MessageQueue.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/MessageQueue.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Events;

use ErrorException;
use RuntimeException;
use Throwable;

/**
 * Class MessageQueue
 *
 * A simple event queue system for handling asynchronous, file-based message passing between
 * processes or components. Events are stored as JSON files and dispatched using a user-supplied
 * dispatcher callable. Failed and completed jobs are routed to respective directories.
 *
 * The MessageQueue operates on a simple file-based queue system:
 * 1. Events are queued as JSON files in the pending directory
 * 2. A processor reads and dispatches events using a user-provided callable
 * 3. Successfully handled jobs are moved to completed directory (or deleted)
 * 4. Unhandled jobs remain in pending directory for future processing
 * 5. Failed jobs are moved to failed directory for later inspection
 *
 * Event Processing States:
 * - Handled (dispatcher returns true): Job moved to completed directory or deleted
 * - Not Handled (dispatcher returns false): Job remains in pending for retry/other processors
 * - Failed (dispatcher throws exception): Job moved to failed directory for inspection
 *
 * Configuration:
 * The MessageQueue must be configured with a base directory before use via the configure() method.
 * This base directory will contain three subdirectories: pending, failed, and completed.
 * All paths are explicitly set and no application constants are required.
 *
 * File Structure:
 * - Base directory: /path/to/messagequeue/
 * - Pending jobs: {base}/pending/{timestamp}_{event}.json
 * - Failed jobs: {base}/failed/{timestamp}_{event}.json
 * - Completed jobs: {base}/completed/{timestamp}_{event}.json
 * - Job format: {"event": "string", "args": {}, "timestamp": 123456789}
 * - Uses file locking to prevent concurrent processing
 *
 * Dependencies:
 * - Requires explicit configuration via configure() method before use
 * - Creates directories automatically with 0777 permissions
 * - Base directory must be writable by the process
 * - Dispatcher callable must return true/false to indicate handling status
 *
 * @since       1.0.0
 *
 * @example
 * ```php
 * // Configure the message queue with a base directory
 * MessageQueue::configure('/var/app/queue');
 * ```
 *
 * @example
 * ```php
 * // Publish a new event to the queue
 * MessageQueue::publish('user.registered', ['user_id' => 123, 'email' => 'test@example.com']);
 * ```
 *
 * @example
 * ```php
 * // Consume all pending events from the queue
 * MessageQueue::consume(function($event, $args) {
 *     return match($event) {
 *         'user.registered' => (EmailService::sendWelcome($args['email']), true),
 *         'order.completed' => (NotificationService::notify($args['user_id']), true),
 *         default => false // Not handled - leave in pending
 *     };
 * });
 * ```
 *
 * @see MessageQueue::configure() For initial setup
 * @see MessageQueue::publish() For publishing events to the queue
 * @see MessageQueue::consume() For processing the queue
 * @see MessageQueue::count() For checking queue status
 */
class MessageQueue
{

    /** @var string|null Directory for pending job files */
    protected static ?string $pendingDir = null;

    /** @var string|null Directory for failed job files */
    protected static ?string $failedDir = null;

    /** @var string|null Directory for completed job files */
    protected static ?string $completedDir = null;

    /** @var bool Whether to keep completed job files or delete them after processing */
    protected static bool $keepCompleted = true;

    /** @var bool Whether the message queue has been configured */
    protected static bool $configured = false;

    /**
     * Configure the MessageQueue with a base directory for queue operations.
     *
     * This method must be called before using any other MessageQueue methods. It sets up
     * the directory structure needed for queue operations by creating three subdirectories
     * under the provided base path: pending, failed, and completed.
     *
     * Directory Structure Created:
     * - {basePath}/pending - Where new jobs are queued
     * - {basePath}/failed - Where failed jobs are moved for inspection
     * - {basePath}/completed - Where successful jobs are archived (if keepCompleted is true)
     *
     * The base directory and all subdirectories are created automatically if they don't exist,
     * with 0777 permissions (subject to umask). The base path must be writable by the process
     * that will be creating and processing jobs.
     *
     * Configuration is idempotent - calling this method multiple times with the same path
     * is safe and will not cause issues. However, changing the path after jobs have been
     * queued may result in those jobs being "lost" until the original path is restored.
     *
     * @param string $basePath Absolute path to the base directory for queue operations.
     *                         Must be writable by the process. Subdirectories will be created here.
     * @return void
     * @throws RuntimeException If the base path cannot be created or is not writable.
     *
     * @example
     * ```php
     * // Configure with application-specific queue directory
     * MessageQueue::configure('/var/app/queue');
     * ```
     *
     * @example
     * ```php
     * // Configure with tenant-specific directory
     * $tenant = getCurrentTenant();
     * MessageQueue::configure("/var/queues/$tenant");
     * ```
     *
     * @example
     * ```php
     * // Configure with temporary directory for testing
     * MessageQueue::configure('/tmp/test_queue');
     * ```
     *
     * @see MessageQueue::ensureDirectories() Creates the directory structure
     * @see MessageQueue::isConfigured() Check if configuration has been completed
     */
    public static function configure(string $basePath): void
    {
        $basePath = rtrim($basePath, '/');

        self::$pendingDir = $basePath . '/pending';
        self::$failedDir = $basePath . '/failed';
        self::$completedDir = $basePath . '/completed';

        self::ensureDirectories();
        self::$configured = true;

        // Verify base directory is writable
        if (!is_writable($basePath)) {
            throw new RuntimeException("MessageQueue base directory is not writable: $basePath");
        }
    }

    /**
     * Check if the MessageQueue has been properly configured.
     *
     * Returns true if configure() has been called successfully, false otherwise.
     * This is useful for validation in applications to ensure the queue system
     * is ready before attempting to use it.
     *
     * @return bool True if configured, false if configure() has not been called.
     *
     * @example
     * ```php
     * // Validate configuration before publishing messages
     * if (!MessageQueue::isConfigured()) {
     *     throw new RuntimeException('MessageQueue must be configured before use');
     * }
     * MessageQueue::publish('test.event', []);
     * ```
     *
     * @see MessageQueue::configure() Method that sets up the message queue
     */
    public static function isConfigured(): bool
    {
        return self::$configured;
    }

    /**
     * Validate that the MessageQueue has been configured before use.
     *
     * Internal method called by all public methods to ensure the message queue
     * is properly set up before attempting any operations. Throws a clear
     * exception if configuration is missing.
     *
     * @return void
     * @throws RuntimeException If configure() has not been called.
     */
    private static function requireConfiguration(): void
    {
        if (!self::$configured) {
            throw new RuntimeException('MessageQueue must be configured with configure() before use');
        }
    }

    /**
     * Get or set whether completed job files should be retained after processing.
     *
     * By default, completed jobs are moved to the completed directory for auditing.
     * Setting this to false will delete completed job files immediately after
     * successful processing, saving disk space but losing the audit trail.
     *
     * This setting affects the behavior of the process() method - when keepCompleted
     * is true, successful jobs are moved to the completed directory; when false,
     * they are deleted immediately.
     *
     * @param bool|null $keepCompleted New setting: true to keep completed files,
     *                                 false to delete them, null to just get current value
     * @return bool The previous value of the keepCompleted setting
     *
     * @example
     * ```php
     * // Get current setting
     * $current = MessageQueue::keepCompleted(null);
     * ```
     *
     * @example
     * ```php
     * // Enable keeping completed files for audit trail
     * $old = MessageQueue::keepCompleted(true);
     * ```
     *
     * @example
     * ```php
     * // Disable to save disk space
     * MessageQueue::keepCompleted(false);
     * ```
     *
     * @see MessageQueue::consume() Method affected by this setting
     * @see MessageQueue::completedDir() For setting the completed files directory
     */
    public static function keepCompleted(bool|null $keepCompleted): bool
    {
        $old = self::$keepCompleted;
        if ($keepCompleted !== null) {
            self::$keepCompleted = $keepCompleted;
        }
        return $old;
    }

    /**
     * Get or set the directory used for pending jobs.
     *
     * If a path is provided, updates the directory and returns the previous value.
     * Ensures the new directory exists and is writable. This is where new job files
     * are created when using publish() and where the consumer looks for jobs
     * to execute.
     *
     * Note: This method allows fine-grained control over individual directories,
     * but using configure() is the recommended approach for initial setup.
     *
     * The pending directory should be writable by the web server process and any
     * CLI scripts that will process the queue. Directory permissions are set to
     * 0777 when created automatically.
     *
     * @param string|null $path Optional new absolute path to set.
     *                          If null, returns current path without changing it.
     * @return string The previous pending directory path.
     * @throws RuntimeException If MessageQueue not configured or path not writable.
     *
     * @example
     * ```php
     * // Get current pending directory
     * $current = MessageQueue::pendingDir();
     * ```
     *
     * @example
     * ```php
     * // Set new pending directory
     * $old = MessageQueue::pendingDir('/var/queue/pending');
     * ```
     *
     * @see MessageQueue::configure() Recommended method for initial setup
     * @see MessageQueue::publish() Creates files in this directory
     * @see MessageQueue::consume() Reads files from this directory
     * @see MessageQueue::ensureDirectories() Creates directory if needed
     */
    public static function pendingDir(?string $path = null): string
    {
        self::requireConfiguration();
        $old = self::$pendingDir;
        if ($path !== null) {
            self::$pendingDir = $path;
            self::ensureDirectories();
            if (!is_writable(self::$pendingDir)) {
                throw new RuntimeException("Pending directory is not writable: $path");
            }
        }
        return $old;
    }

    /**
     * Get or set the directory used for failed jobs.
     *
     * If a path is provided, updates the directory and returns the previous value.
     * Ensures the new directory exists and is writable. This is where job files
     * are moved when they fail during processing, allowing for later inspection,
     * debugging, or manual reprocessing.
     *
     * Failed jobs retain their original filename when moved, making it easy to
     * identify when and what type of job failed. The failed directory serves as
     * an important debugging and monitoring tool.
     *
     * @param string|null $path Optional new absolute path to set.
     *                          If null, returns current path without changing it.
     * @return string The previous failed directory path.
     * @throws RuntimeException If MessageQueue not configured or path not writable.
     *
     * @example
     * ```php
     * // Get current failed directory
     * $current = MessageQueue::failedDir();
     * ```
     *
     * @example
     * ```php
     * // Set new failed directory
     * $old = MessageQueue::failedDir('/var/queue/failed');
     * ```
     *
     * @see MessageQueue::configure() Recommended method for initial setup
     * @see MessageQueue::consume() Moves failed jobs to this directory
     * @see MessageQueue::ensureDirectories() Creates directory if needed
     */
    public static function failedDir(?string $path = null): string
    {
        self::requireConfiguration();
        $old = self::$failedDir;
        if ($path !== null) {
            self::$failedDir = $path;
            self::ensureDirectories();
            if (!is_writable(self::$failedDir)) {
                throw new RuntimeException("Failed directory is not writable: $path");
            }
        }
        return $old;
    }

    /**
     * Get or set the directory used for completed jobs.
     *
     * If a path is provided, updates the directory and returns the previous value.
     * Ensures the new directory exists and is writable. This is where successfully
     * processed job files are moved (if keepCompleted is true), providing an audit
     * trail of all completed work.
     *
     * The completed directory is useful for debugging, auditing, and understanding
     * system activity. Files here can be safely deleted periodically to manage
     * disk space, or archived for long-term record keeping.
     *
     * @param string|null $path Optional new absolute path to set.
     *                          If null, returns current path without changing it.
     * @return string The previous completed directory path.
     * @throws RuntimeException If MessageQueue not configured or path not writable.
     *
     * @example
     * ```php
     * // Get current completed directory
     * $current = MessageQueue::completedDir();
     * ```
     *
     * @example
     * ```php
     * // Set new completed directory
     * $old = MessageQueue::completedDir('/var/queue/completed');
     * ```
     *
     * @see MessageQueue::configure() Recommended method for initial setup
     * @see MessageQueue::consume() Moves completed jobs to this directory
     * @see MessageQueue::keepCompleted() Controls whether files are moved here
     * @see MessageQueue::ensureDirectories() Creates directory if needed
     */
    public static function completedDir(?string $path = null): string
    {
        self::requireConfiguration();
        $old = self::$completedDir;
        if ($path !== null) {
            self::$completedDir = $path;
            self::ensureDirectories();
            if (!is_writable(self::$completedDir)) {
                throw new RuntimeException("Completed directory is not writable: $path");
            }
        }
        return $old;
    }

    /**
     * Ensure that all necessary directories exist and are properly set up.
     *
     * Creates the pending, failed, and completed directories if they do not exist.
     * Directories are created recursively with 0777 permissions, making them
     * writable by all users (subject to umask). This method is called automatically
     * by other methods that need to ensure directory existence.
     *
     * This is an internal method that handles the infrastructure setup required
     * for the MessageQueue to function properly. It's safe to call multiple times
     * as it only creates directories that don't already exist.
     *
     * @return void
     * @throws RuntimeException If any directory cannot be created.
     *
     * @see MessageQueue::configure() Calls this during initial setup
     * @see MessageQueue::pendingDir() Calls this when setting new path
     * @see MessageQueue::failedDir() Calls this when setting new path
     * @see MessageQueue::completedDir() Calls this when setting new path
     * @see MessageQueue::publish() Calls this before creating job files
     * @see MessageQueue::consume() Calls this before processing jobs
     */
    protected static function ensureDirectories(): void
    {
        foreach ([self::$pendingDir, self::$failedDir, self::$completedDir] as $dir) {
            if ($dir && !is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    throw new RuntimeException("Failed to create directory: $dir");
                }
            }
        }
    }

    /**
     * Publish a new event message to the queue for later processing.
     *
     * Creates a JSON file in the pending directory containing the event name,
     * arguments, and timestamp. The filename uses microtime for ordering and
     * includes the event name for easy identification.
     *
     * Job files are created with the format: {microtime}_{event}.json
     * Content structure: {"event": "string", "args": {}, "timestamp": 123456789}
     *
     * This method is thread-safe as each call generates a unique filename based
     * on microtime. The created file will be picked up by the next consume() call.
     *
     * @param string $event     The event name or type (e.g., 'user.registered', 'email.send').
     *                          Used for routing in the dispatcher and included in filename.
     * @param array  $namedArgs An associative array of arguments to be passed to the dispatcher.
     *                          Can contain any JSON-serializable data needed by the event handler.
     * @return void
     * @throws RuntimeException If MessageQueue not configured.
     *
     * @example
     * ```php
     * // Publish a user registration event
     * MessageQueue::publish('user.registered', [
     *     'user_id' => 123,
     *     'email' => 'user@example.com',
     *     'name' => 'John Doe'
     * ]);
     * ```
     *
     * @example
     * ```php
     * // Publish an email notification
     * MessageQueue::publish('email.send', [
     *     'to' => 'admin@example.com',
     *     'subject' => 'New Order',
     *     'template' => 'order_notification',
     *     'data' => ['order_id' => 456]
     * ]);
     * ```
     *
     * @see MessageQueue::configure() Must be called before this method
     * @see MessageQueue::consume() Processes queued messages
     * @see MessageQueue::count() Check number of queued messages
     * @see MessageQueue::ensureDirectories() Ensures pending directory exists
     */
    public static function publish(string $event, array $namedArgs): void
    {
        self::requireConfiguration();
        self::ensureDirectories();
        $filename = self::$pendingDir . '/' . microtime(true) . "_$event.json";
        $job = [
            'event' => $event,
            'args' => $namedArgs,
            'timestamp' => time()
        ];
        file_put_contents($filename, json_encode($job));
    }

    /**
     * Consume all pending messages by dispatching them using the provided callable.
     *
     * Iterates through all JSON files in the pending directory and calls the provided
     * dispatcher function for each valid job. Uses a lock file to prevent multiple
     * processes from running simultaneously, ensuring jobs are not processed twice.
     *
     * Message Processing Flow:
     * 1. Acquires exclusive lock to prevent concurrent processing
     * 2. Reads each .json file in pending directory
     * 3. Calls dispatcher with event name and arguments
     * 4. If dispatcher returns true: moves to completed directory or deletes (based on keepCompleted setting)
     * 5. If dispatcher returns false: leaves job in pending directory for future processing
     * 6. If dispatcher throws exception: moves to failed directory for inspection
     * 7. Releases lock and cleans up lock file
     *
     * Event Handling Logic:
     * - Dispatcher must return true to indicate the event was successfully handled
     * - Dispatcher must return false to indicate the event was not handled (leaves in pending)
     * - Dispatcher throwing an exception indicates a failure (moves to failed)
     * - PHP errors are converted to exceptions using custom error handler
     * - Lock is always released, even if processing fails
     *
     * @param callable $dispatcher A function to call for each event.
     *                             Signature: function(string $event, array $args): bool
     *                             Must return true if event was handled, false if not handled.
     *                             Throw exceptions to mark as failed.
     * @return void
     * @throws RuntimeException If MessageQueue not configured.
     *
     * @example
     * ```php
     * // Basic event processing with match expression
     * MessageQueue::consume(function($event, $args) {
     *     return match($event) {
     *         'user.registered' => (UserService::sendWelcome($args['user_id']), true),
     *         'order.completed' => (EmailService::sendReceipt($args['order_id']), true),
     *         'email.send' => (MailService::send($args['to'], $args['subject'], $args['body']), true),
     *         default => false // Not handled - leave in pending
     *     };
     * });
     * ```
     *
     * @example
     * ```php
     * // Event processing with explicit handling logic
     * MessageQueue::consume(function($event, $args) {
     *     switch($event) {
     *         case 'user.registered':
     *             UserService::sendWelcome($args['user_id']);
     *             return true; // Successfully handled
     *
     *         case 'email.send':
     *             if (!isset($args['to']) || !isset($args['subject'])) {
     *                 throw new InvalidArgumentException('Missing email parameters');
     *             }
     *             MailService::send($args['to'], $args['subject'], $args['body']);
     *             return true; // Successfully handled
     *
     *         default:
     *             return false; // Not handled - will remain in pending
     *     }
     * });
     * ```
     *
     * @example
     * ```php
     * // Processing with error handling and conditional logic
     * MessageQueue::consume(function($event, $args) {
     *     try {
     *         $handled = EventHandler::dispatch($event, $args);
     *         if ($handled) {
     *             Logger::info("Processed event: $event");
     *             return true;
     *         } else {
     *             Logger::debug("Event not handled by this processor: $event");
     *             return false; // Let another processor handle it
     *         }
     *     } catch (Exception $e) {
     *         Logger::error("Event failed: $event - " . $e->getMessage());
     *         throw $e; // Re-throw to mark job as failed
     *     }
     * });
     * ```
     *
     * @see MessageQueue::configure() Must be called before this method
     * @see MessageQueue::publish() Creates jobs processed by this method
     * @see MessageQueue::count() Check how many jobs will be processed
     * @see MessageQueue::keepCompleted() Controls completed job file handling
     * @see MessageQueue::failedDir() Where failed jobs are moved
     * @see MessageQueue::completedDir() Where successful jobs are moved
     */
    public static function consume(callable $dispatcher): void
    {
        self::requireConfiguration();
        self::ensureDirectories();
        $lockFile = self::$pendingDir . '/.running.lock';
        $lock = fopen($lockFile, 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            echo "Another message queue processor is already running.\n";
            return;
        }

        try {
            foreach (glob(self::$pendingDir . '/*.json') as $file) {
                if (basename($file) === '.running.lock') continue;
                $job = json_decode(file_get_contents($file), true);
                $failed = false;
                $handled = false;

                try {
                    if ($job && isset($job['event'], $job['args'])) {
                        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$failed, $file) {
                            $failed = true;
                            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
                        });

                        $result = $dispatcher($job['event'], $job['args']);
                        $handled = ($result === true);
                    }
                } catch (Throwable $e) {
                    $failed = true;
                    echo "Failed to process event file: $file ({$e->getMessage()})\n";
                } finally {
                    restore_error_handler();
                }

                // Determine what to do with the job file
                if ($failed) {
                    // Exception thrown - move to failed directory
                    rename($file, self::$failedDir . '/' . basename($file));
                } elseif ($handled) {
                    // Dispatcher returned true - move to completed or delete
                    if (self::$keepCompleted) {
                        rename($file, self::$completedDir . '/' . basename($file));
                    } else {
                        unlink($file);
                    }
                // } else {
                    // Dispatcher returned false or null - leave in pending for retry
                    // File stays where it is for future processing
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            unlink($lockFile);
        }
    }

    /**
     * Get the number of messages currently in the queue.
     *
     * Counts all .json files in the pending directory, which represents the
     * number of messages waiting to be processed. This is useful for monitoring
     * queue depth and determining if processing is keeping up with message publishing.
     *
     * The count includes only valid job files (.json extension) and excludes
     * any lock files or other non-job files that might exist in the directory.
     *
     * @return int Number of .json job files in the pending directory.
     * @throws RuntimeException If MessageQueue not configured.
     *
     * @example
     * ```php
     * // Check if queue is empty
     * if (MessageQueue::count() === 0) {
     *     echo "No messages in queue\n";
     * }
     * ```
     *
     * @example
     * ```php
     * // Monitor queue depth
     * $count = MessageQueue::count();
     * echo "Messages in queue: $count\n";
     * if ($count > 100) {
     *     echo "Queue is getting backed up!\n";
     * }
     * ```
     *
     * @example
     * ```php
     * // Wait for queue to be processed
     * while (MessageQueue::count() > 0) {
     *     sleep(1);
     *     echo "Waiting for " . MessageQueue::count() . " messages to be processed...\n";
     * }
     * ```
     *
     * @see MessageQueue::configure() Must be called before this method
     * @see MessageQueue::publish() Increases this count
     * @see MessageQueue::consume() Decreases this count
     * @see MessageQueue::pendingDir() Directory where pending messages are stored
     */
    public static function count(): int
    {
        self::requireConfiguration();
        self::ensureDirectories();
        return count(glob(self::$pendingDir . '/*.json'));
    }

}