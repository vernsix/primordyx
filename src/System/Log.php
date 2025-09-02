<?php
/**
 * File: /vendor/vernsix/primordyx/src/Log.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/System/Log.php
 *
 */

declare(strict_types=1);
namespace Primordyx\System;

use InvalidArgumentException;
use Primordyx\Utils\RandomStuff;
use RuntimeException;

/**
 * Class Log
 *
 * Simple, powerful logger designed for use with Primordyx EventManager.
 * Features enhanced interpolation, flexible configuration, and secure file handling.
 *
 * This logger is designed to work with EventManager for sophisticated routing:
 * - Different event types can log to different files
 * - Trouble users can have dedicated log files
 * - Multiple handlers can process the same event differently
 * - Each Log instance is configured for a specific purpose
 *
 * Basic usage: Create Log instance with file path, call logging methods
 * EventManager integration: Multiple Log instances achieve file "aliasing"
 * Enhanced interpolation: Supports placeholders with default values
 *
 * @since       1.0.0
 *
 */

class Log
{
    protected string $file;
    protected int $maxSize = 10485760; // 10MB
    protected bool $echoToStdout = false;
    protected bool $silence = false;
    protected bool $useUtc = true;
    protected array $allowedLevels = [];
    protected string $sessionId = '';
    /**
     * @var callable|null Custom log formatter function
     */
    protected $formatter = null;

    /**
     * Log levels for validation
     */
    protected const LEVELS = [
        'EMERGENCY' => 800,
        'ALERT'     => 700,
        'CRITICAL'  => 600,
        'ERROR'     => 500,
        'WARNING'   => 400,
        'NOTICE'    => 300,
        'SUCCESS'   => 250, // Custom level
        'INFO'      => 200,
        'DEBUG'     => 100,
        'TRACE'     => 50,  // Custom level
    ];

    /**
     * Create a new logger instance
     *
     * Each Log instance is configured for a specific file and purpose. Use multiple
     * instances with EventManager to achieve sophisticated routing behavior.
     *
     * Configuration options:
     * - max_size: int - Max file size before rotation (default: 10MB)
     * - echo_to_stdout: bool - Echo logs to stdout (default: false)
     * - silence: bool - Suppress all logging (default: false)
     * - use_utc: bool - Use UTC timestamps (default: true)
     * - allowed_levels: array - Filter specific levels (default: all)
     * - session_id: string - Session identifier (default: auto-generate)
     * - formatter: callable - Custom log entry formatter
     *
     * File "aliasing" achieved via EventManager with multiple Log instances.
     * Different events can route to different files based on data content.
     *
     * @param string $file: string (required) - Log file path
     * @param array $config Configuration options array
     * @throws InvalidArgumentException If file path is not provided or invalid
     */
    public function __construct(string $file = '', array $config = [])
    {
        if (empty($file)) {
            throw new InvalidArgumentException('Log file path is required');
        }

        $this->file = $file;
        $this->maxSize = $config['max_size'] ?? 10485760;
        $this->echoToStdout = $config['echo_to_stdout'] ?? false;
        $this->silence = $config['silence'] ?? false;
        $this->useUtc = $config['use_utc'] ?? true;
        $this->allowedLevels = isset($config['allowed_levels']) ?
            array_map('strtoupper', (array)$config['allowed_levels']) : [];
        $this->sessionId = $config['session_id'] ?? $this->generateSessionId();
        $this->formatter = $config['formatter'] ?? null;
    }

    // ==========================================================================
    // LOGGING METHODS
    // ==========================================================================

    /**
     * Log with an arbitrary level
     *
     * This is the main logging method that all other methods delegate to.
     * Perfect for use in EventManager handlers where you receive structured data.
     *
     * Enhanced interpolation supports placeholders with default values.
     *
     * @param mixed $level Log level (string)
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @throws InvalidArgumentException If log level is invalid
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->isValidLevel($level)) {
            throw new InvalidArgumentException("Invalid log level: {$level}");
        }

        if ($this->silence) {
            return;
        }

        if (!$this->isLevelAllowed($level)) {
            return;
        }

        $this->writeLog($level, $message, $context);
    }

    /**
     * System is unusable
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /**
     * Action must be taken immediately
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    /**
     * Critical conditions
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Error conditions
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Warning conditions
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Normal but significant condition
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    /**
     * Informational messages
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Debug-level messages
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Success-level messages (Custom Level)
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function success(string $message, array $context = []): void
    {
        $this->log('SUCCESS', $message, $context);
    }

    /**
     * Trace-level messages (Custom Level)
     * @param string $message Message with optional placeholders
     * @param array $context Context data for interpolation
     * @return void
     */
    public function trace(string $message, array $context = []): void
    {
        $this->log('TRACE', $message, $context);
    }

    // ==========================================================================
    // CORE LOGGING IMPLEMENTATION
    // ==========================================================================

    /**
     * Write log entry to file with custom formatting support
     *
     * This method handles the actual log writing, including custom formatter
     * support, interpolation, and error handling.
     *
     * @param string $level The log level (already validated)
     * @param string $message The message to log
     * @param array $context Context data for interpolation
     * @return void
     */
    protected function writeLog(string $level, string $message, array $context): void
    {
        if ($this->formatter) {
            // Use custom formatter
            $entry = call_user_func($this->formatter, [
                'timestamp' => $this->useUtc ? gmdate('Y-m-d H:i:s') . ' UTC' : date('Y-m-d H:i:s'),
                'session_id' => $this->sessionId,
                'level' => strtoupper($level),
                'message' => $message,
                'context' => $context,
                'interpolated_message' => $this->interpolate($message, $context)
            ]);
        } else {
            // Default formatter
            $timestamp = $this->useUtc
                ? gmdate('Y-m-d H:i:s') . ' UTC'
                : date('Y-m-d H:i:s');

            $sessionStr = !empty($this->sessionId) ? " {{$this->sessionId}}" : '';
            $interpolatedMessage = $this->interpolate($message, $context);

            $entry = "[{$timestamp}]{$sessionStr} [" . strtoupper($level) . "] {$interpolatedMessage}" . PHP_EOL;
        }

        try {
            $this->writeToFile($entry);

            if ($this->echoToStdout) {
                echo $entry;
            }
        } catch (RuntimeException $e) {
            if ($this->echoToStdout || !$this->silence) {
                error_log("Log Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Enhanced interpolation with default values
     *
     * This is an ENHANCED version that supports default values when context keys are missing.
     *
     * Standard: placeholder format is 'User from ip'
     * Enhanced: supports 'Status: unknown, User: 123' when status missing
     *
     * Placeholders: Use curly braces with optional default after colon
     * Complex data: Arrays and objects are JSON encoded automatically
     *
     * @param string $message Message with placeholder or placeholder:default syntax
     * @param array $context Associative array of placeholder values
     * @return string Interpolated message with placeholders replaced
     */
    protected function interpolate(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        return preg_replace_callback('/\{(\w+)(?::([^}]*))?\}/', function ($matches) use ($context) {
            $key = $matches[1];
            $default = $matches[2] ?? '';
            $value = $context[$key] ?? $default;

            if (is_scalar($value)) {
                return (string)$value;
            }

            if (is_null($value)) {
                return 'null';
            }

            // Handle arrays and objects
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }, $message);
    }

    /**
     * Write content to log file with rotation check
     * @param string $content Content to write
     * @return void
     * @throws RuntimeException If write fails
     */
    protected function writeToFile(string $content): void
    {
        $this->ensureDirectoryExists();
        $this->checkRotation();

        $result = file_put_contents($this->file, $content, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Failed to write to log file: {$this->file}");
        }
    }

    /**
     * Check if file needs rotation and rotate if necessary
     * @return void
     */
    protected function checkRotation(): void
    {
        if (!file_exists($this->file)) {
            return;
        }

        $fileSize = filesize($this->file);
        if ($fileSize === false) {
            throw new RuntimeException("Cannot determine size of log file: {$this->file}");
        }

        if ($fileSize >= $this->maxSize) {
            $this->rotateFile();
        }
    }

    /**
     * Rotate the current log file
     * @return void
     */
    protected function rotateFile(): void
    {
        if (!file_exists($this->file)) {
            return;
        }

        $timestamp = date('Ymd_His');
        $rotatedFile = "{$this->file}.{$timestamp}";

        // Ensure rotated filename is unique
        $counter = 1;
        while (file_exists($rotatedFile)) {
            $rotatedFile = "{$this->file}.{$timestamp}.{$counter}";
            $counter++;
        }

        if (!rename($this->file, $rotatedFile)) {
            throw new RuntimeException("Failed to rotate log file from {$this->file} to {$rotatedFile}");
        }
    }

    /**
     * Ensure the directory exists for the log file
     * @return void
     * @throws RuntimeException If directory creation fails
     */
    protected function ensureDirectoryExists(): void
    {
        $directory = dirname($this->file);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create log directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Log directory is not writable: {$directory}");
        }
    }

    // ==========================================================================
    // VALIDATION
    // ==========================================================================

    /**
     * Check if log level is valid
     * @param string $level Level to check
     * @return bool True if valid level
     */
    protected function isValidLevel(string $level): bool
    {
        return isset(self::LEVELS[strtoupper($level)]);
    }

    /**
     * Check if level is allowed based on configuration
     * @param string $level Level to check
     * @return bool True if level is allowed
     */
    protected function isLevelAllowed(string $level): bool
    {
        if (empty($this->allowedLevels)) {
            return true; // Allow all if no restrictions set
        }

        return in_array(strtoupper($level), $this->allowedLevels, true);
    }

    /**
     * Generate a simple session ID
     * @return string Generated session ID
     */
    protected function generateSessionId(): string
    {
        // return substr(md5(uniqid('', true)), 0, 8);
        return RandomStuff::myThreeWords();
    }

    // ==========================================================================
    // CONFIGURATION METHODS (Unified Getter/Setter Pattern)
    // ==========================================================================

    /**
     * Get/Set maximum file size before rotation
     * @param int|null $bytes New max size in bytes, or null to get current value
     * @return int Previous/current max size value
     * @throws InvalidArgumentException If bytes is less than 1024
     */
    public function maxSize(?int $bytes = null): int
    {
        $old = $this->maxSize;
        if ($bytes !== null) {
            if ($bytes < 1024) {
                throw new InvalidArgumentException('Maximum file size must be at least 1024 bytes');
            }
            $this->maxSize = $bytes;
        }
        return $old;
    }

    /**
     * Get/Set whether to echo logs to stdout
     * @param bool|null $echo New echo setting, or null to get current value
     * @return bool Previous/current echo setting
     */
    public function echoToStdout(?bool $echo = null): bool
    {
        $old = $this->echoToStdout;
        if ($echo !== null) {
            $this->echoToStdout = $echo;
        }
        return $old;
    }

    /**
     * Get/Set silence mode
     * @param bool|null $silence New silence setting, or null to get current value
     * @return bool Previous/current silence setting
     */
    public function silence(?bool $silence = null): bool
    {
        $old = $this->silence;
        if ($silence !== null) {
            $this->silence = $silence;
        }
        return $old;
    }

    /**
     * Get/Set UTC mode
     * @param bool|null $useUtc New UTC setting, or null to get current value
     * @return bool Previous/current UTC setting
     */
    public function useUtc(?bool $useUtc = null): bool
    {
        $old = $this->useUtc;
        if ($useUtc !== null) {
            $this->useUtc = $useUtc;
        }
        return $old;
    }

    /**
     * Get/Set allowed log levels
     * @param array|null $levels New levels array, or null to get current value
     * @return array Previous/current allowed levels
     */
    public function allowedLevels(?array $levels = null): array
    {
        $old = $this->allowedLevels;
        if ($levels !== null) {
            $this->allowedLevels = array_map('strtoupper', $levels);
        }
        return $old;
    }

    /**
     * Get/Set session ID
     * @param string|null $sessionId New session ID, or null to get current value
     * @return string Previous/current session ID
     */
    public function sessionId(?string $sessionId = null): string
    {
        $old = $this->sessionId;
        if ($sessionId !== null) {
            $this->sessionId = $sessionId;
        }
        return $old;
    }

    /**
     * Get/Set custom formatter
     * @param callable|null $formatter New formatter function, or null to get current
     * @return callable|null Previous/current formatter (null = default format)
     */
    public function formatter(?callable $formatter = null): ?callable
    {
        $old = $this->formatter;
        if ($formatter !== null) {
            $this->formatter = $formatter;
        }
        return $old;
    }

    /**
     * Get current file path (read-only)
     * @return string Current log file path
     */
    public function file(): string
    {
        return $this->file;
    }

    /**
     * Clear custom formatter (use default)
     * @return self For method chaining
     */
    public function clearFormatter(): self
    {
        $this->formatter = null;
        return $this;
    }

    // ==========================================================================
    // BUILT-IN FORMATTERS
    // ==========================================================================

    /**
     * JSON formatter - outputs structured JSON logs
     * @return callable JSON formatter function
     */
    public static function jsonFormatter(): callable
    {
        return function(array $data): string {
            $logEntry = [
                'timestamp' => $data['timestamp'],
                'level' => $data['level'],
                'message' => $data['interpolated_message'],
                'context' => $data['context']
            ];

            if (!empty($data['session_id'])) {
                $logEntry['session_id'] = $data['session_id'];
            }

            return json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        };
    }

    /**
     * Compact formatter - minimal output
     * @return callable Compact formatter function
     */
    public static function compactFormatter(): callable
    {
        return function(array $data): string {
            $timestamp = substr($data['timestamp'], 11, 8); // Just the time part
            $level = substr($data['level'], 0, 1); // First letter only
            return "[{$timestamp}] {$level}: {$data['interpolated_message']}" . PHP_EOL;
        };
    }

    /**
     * Detailed formatter - includes extra context
     * @return callable Detailed formatter function
     */
    public static function detailedFormatter(): callable
    {
        return function(array $data): string {
            $sessionStr = !empty($data['session_id']) ? " [{$data['session_id']}]" : '';
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $contextStr = !empty($data['context']) ? " | Context: " . json_encode($data['context'], JSON_UNESCAPED_SLASHES) : '';

            return "[{$data['timestamp']}]{$sessionStr} [{$data['level']}] [{$memoryUsage}MB] {$data['interpolated_message']}{$contextStr}" . PHP_EOL;
        };
    }

    /**
     * Syslog-style formatter
     * @return callable Syslog formatter function
     */
    public static function syslogFormatter(): callable
    {
        return function(array $data): string {
            $hostname = gethostname() ?: 'localhost';
            $appName = 'primordyx';
            $pid = getmypid();

            // Convert level to syslog priority
            $priorities = [
                'EMERGENCY' => 0, 'ALERT' => 1, 'CRITICAL' => 2, 'ERROR' => 3,
                'WARNING' => 4, 'NOTICE' => 5, 'INFO' => 6, 'DEBUG' => 7,
                'SUCCESS' => 6, 'TRACE' => 7
            ];
            $priority = $priorities[$data['level']] ?? 6;

            return "<{$priority}>{$data['timestamp']} {$hostname} {$appName}[{$pid}]: {$data['interpolated_message']}" . PHP_EOL;
        };
    }

    /**
     * Custom web request formatter - great for web applications
     * @return callable Web formatter function
     */
    public static function webFormatter(): callable
    {
        return function(array $data): string {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? ' | ' . substr($_SERVER['HTTP_USER_AGENT'], 0, 50) : '';

            return "[{$data['timestamp']}] [{$data['level']}] [{$ip}] [{$method} {$uri}] {$data['interpolated_message']}{$userAgent}" . PHP_EOL;
        };
    }

    // ==========================================================================
    // UTILITY METHODS
    // ==========================================================================

    /**
     * Force log rotation now
     * @return void
     */
    public function rotateNow(): void
    {
        if (file_exists($this->file())) {
            $this->rotateFile();
        }
    }

    /**
     * Get current configuration
     * @return array Complete configuration array
     */
    public function getConfig(): array
    {
        return [
            'file' => $this->file(),
            'max_size' => $this->maxSize(),
            'echo_to_stdout' => $this->echoToStdout(),
            'silence' => $this->silence(),
            'use_utc' => $this->useUtc(),
            'allowed_levels' => $this->allowedLevels(),
            'session_id' => $this->sessionId(),
            'has_custom_formatter' => $this->formatter() !== null,
        ];
    }

    /**
     * Get file statistics
     * @return array File statistics array
     */
    public function getFileStats(): array
    {
        if (!file_exists($this->file())) {
            return [
                'exists' => false,
                'size' => 0,
                'writable' => false,
                'rotation_needed' => false,
            ];
        }

        $size = filesize($this->file());

        return [
            'exists' => true,
            'size' => $size ?: 0,
            'writable' => is_writable($this->file()),
            'rotation_needed' => ($size ?: 0) >= $this->maxSize(),
            'path' => $this->file(),
            'directory_writable' => is_writable(dirname($this->file())),
        ];
    }

    /**
     * Clean up old rotated log files
     * @param int $maxAge Maximum age in days for rotated files
     * @return int Number of files deleted
     */
    public function cleanupRotatedFiles(int $maxAge = 30): int
    {
        $directory = dirname($this->file());
        $basename = basename($this->file());
        $pattern = preg_quote($basename, '/') . '\.\d{8}_\d{6}(\.\d+)?$';

        $cutoffTime = time() - ($maxAge * 24 * 60 * 60);
        $deletedCount = 0;

        if (!is_dir($directory)) {
            return 0;
        }

        $files = scandir($directory);
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (preg_match("/{$pattern}/", $file)) {
                $filePath = $directory . DIRECTORY_SEPARATOR . $file;
                $fileTime = filemtime($filePath);

                if ($fileTime !== false && $fileTime < $cutoffTime) {
                    if (unlink($filePath)) {
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }



    /**
     * EventManager-Based Trouble User Tracking
     *
     * This approach uses your EventManager system to automatically route trouble user
     * activities to dedicated log files. Here's how it works:
     *
     * 1. SET UP THE HANDLER:
     *    - Register an action handler that listens for 'user.activity' events
     *    - Handler checks if the user_id is in your trouble users list
     *    - If yes, creates dedicated Log instance for that specific user
     *
     * 2. DEDICATED TROUBLE USER LOGGING:
     *    - Each trouble user gets their own log file: logs/trouble_users/user_123.log
     *    - Uses detailed session ID for investigation tracking
     *    - Echo to stdout enabled so you see activity in real-time
     *    - Larger file size to keep more historical data
     *    - Uses CRITICAL level to emphasize this is flagged activity
     *
     * 3. SUMMARY LOGGING:
     *    - Also creates a summary log that aggregates ALL trouble user activity
     *    - Makes it easy to see patterns across multiple problem users
     *    - Uses ALERT level for high visibility
     *
     * 4. NORMAL LOGGING CONTINUES:
     *    - Still logs everything to the general activity log
     *    - So you have both detailed individual logs AND normal application logs
     *
     * 5. USAGE THROUGHOUT APP:
     *    - Anywhere in your app, just fire: EventManager::fire('user.activity', $data)
     *    - The EventManager automatically handles the routing
     *    - No need to check if user is trouble user in your business logic
     *    - Clean separation of concerns
     *
     * BENEFITS:
     * - Automatic routing based on user ID
     * - Real-time monitoring via echo_to_stdout
     * - Individual investigation files per trouble user
     * - Summary view across all trouble users
     * - No code changes needed in your controllers/services
     * - Just fire events and let EventManager handle the logging logic
     *
     *
     * OPTION 2: EventManager-Based Trouble User Tracking
     *
     * This approach uses your EventManager system to automatically route trouble user
     * activities to dedicated log files. Here's how it works:
     *
     * 1. SET UP THE HANDLER (run this once at app startup):
     *
     *    EventManager::add_action('user.activity', function($data) {
     *        $troubleUsers = [123, 456, 789]; // Your problem users
     *
     *        if (in_array($data['user_id'], $troubleUsers)) {
     *            // Individual trouble user log
     *            $troubleLogger = new Log([
     *                'file' => "logs/trouble_users/user_{$data['user_id']}.log",
     *                'session_id' => "investigation_{$data['user_id']}_" . date('Ymd_His'),
     *                'echo_to_stdout' => true,  // Real-time alerts
     *                'max_size' => 52428800     // 50MB for detailed investigation
     *            ]);
     *            $troubleLogger->critical('TROUBLE USER: {user_id} performed {activity} from {ip}', $data);
     *
     *            // Summary log for all trouble users
     *            $summaryLogger = new Log([
     *                'file' => 'logs/trouble_users_summary.log',
     *                'session_id' => 'trouble_summary_' . date('His')
     *            ]);
     *            $summaryLogger->alert('Trouble user {user_id}: {activity}', $data);
     *        }
     *
     *        // Normal logging continues
     *        $generalLogger = new Log(['file' => 'logs/general_activity.log']);
     *        $generalLogger->info('User activity: {activity}', $data);
     *    }, 100, 'Trouble user monitoring');
     *
     * 2. FIRE EVENTS FROM YOUR CONTROLLERS/SERVICES:
     *
     *    // In UserController::login()
     *    EventManager::fire('user.activity', [
     *        'user_id' => $user->id,
     *        'activity' => 'login_attempt',
     *        'ip' => $_SERVER['REMOTE_ADDR'],
     *        'success' => $loginSuccess,
     *        'user_agent' => $_SERVER['HTTP_USER_AGENT']
     *    ]);
     *
     *    // In AdminController::accessUserData()
     *    EventManager::fire('user.activity', [
     *        'user_id' => $currentUserId,
     *        'activity' => 'admin_access_user_data',
     *        'target_user_id' => $targetUserId,
     *        'ip' => $_SERVER['REMOTE_ADDR'],
     *        'admin_level' => $user->adminLevel
     *    ]);
     *
     *    // In PaymentService::processPayment()
     *    EventManager::fire('user.activity', [
     *        'user_id' => $payment->userId,
     *        'activity' => 'payment_attempt',
     *        'amount' => $payment->amount,
     *        'gateway' => 'stripe',
     *        'ip' => $_SERVER['REMOTE_ADDR'],
     *        'status' => $payment->status
     *    ]);
     *
     *    // In ProfileController::changePassword()
     *    EventManager::fire('user.activity', [
     *        'user_id' => $user->id,
     *        'activity' => 'password_change',
     *        'ip' => $_SERVER['REMOTE_ADDR'],
     *        'changed_by' => 'user' // vs 'admin' vs 'system'
     *    ]);
     *
     * 3. WHAT HAPPENS AUTOMATICALLY:
     *
     *    - If user 123 logs in: goes to logs/trouble_users/user_123.log + summary + general
     *    - If user 999 logs in: only goes to general log (not a trouble user)
     *    - You see trouble user activity in real-time via echo_to_stdout
     *    - Each trouble user gets detailed investigation file
     *    - Summary log shows patterns across all problem users
     *
     * 4. SAMPLE LOG OUTPUT:
     *
     *    logs/trouble_users/user_123.log:
     *    [2025-01-15 10:30:15] {investigation_123_20250115_103015} [CRITICAL] TROUBLE USER: 123 performed login_attempt from 192.168.1.50
     *    [2025-01-15 10:31:22] {investigation_123_20250115_103015} [CRITICAL] TROUBLE USER: 123 performed admin_access_user_data from 192.168.1.50
     *
     *    logs/trouble_users_summary.log:
     *    [2025-01-15 10:30:15] [ALERT] Trouble user 123: login_attempt
     *    [2025-01-15 10:31:22] [ALERT] Trouble user 123: admin_access_user_data
     *    [2025-01-15 11:15:33] [ALERT] Trouble user 456: payment_attempt
     *
     * BENEFITS:
     * - Just fire events - EventManager handles all the routing logic
     * - No "if trouble user" checks scattered throughout your code
     * - Easy to add/remove users from trouble list in one place
     * - Real-time monitoring + detailed investigation files
     * - Normal app logging continues unchanged
     */



}
