<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/MessageQueueConsumeCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/MessageQueueConsumeCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Exception;
use Primordyx\Events\MessageQueue;
use RuntimeException;

class MessageQueueConsumeCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'messagequeue:consume';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Process pending MessageQueue messages';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx messagequeue:consume    Process all pending messages in the queue

Description:
  Processes queued messages by looking for handler files in the project's
  'handlers' directory. Handler files should follow the naming convention:
  {event}.{priority}.php (e.g., user.registered.10.php)

  Lower priority numbers are executed first.

Examples:
  primordyx messagequeue:consume

  # In a cron job:
  * * * * * /usr/bin/php /path/to/project/vendor/bin/primordyx messagequeue:consume

Notes:
  - Handlers must be in the 'handlers' directory of your project root
  - Each handler file should return a callable
  - Messages remain in pending if no handler is found
  - Failed messages are moved to the failed directory";
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args): void
    {
        // Check for --help
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->out($this->getDetailedHelp());
            return;
        }

        // Configure MessageQueue
        $queuePath = APP_STORAGE_PATH . '/messagequeue';

        try {
            MessageQueue::configure($queuePath);
        } catch (RuntimeException) {
            // Already configured, that's fine
        }

        // Check for handlers directory
        $handlersPath = APP_ROOT . '/handlers';
        if (!is_dir($handlersPath)) {
            $this->out("No handlers directory found at: $handlersPath");
            $this->out("Create a 'handlers' directory in your project root to process events.");
            return;
        }

        // Show current queue status
        $pending = MessageQueue::count();
        $this->out("Processing MessageQueue...");
        $this->out("Pending messages: $pending");

        if ($pending === 0) {
            $this->out("No messages to process.");
            return;
        }

        // Create the dispatcher
        $dispatcher = function ($event, $args) use ($handlersPath) {
            $handlerFiles = glob($handlersPath . '/' . $event . '.*.php');

            // Sort by priority (lower numbers first)
            usort($handlerFiles, function ($a, $b) {
                preg_match('/\.(\d+)\.php$/', $a, $ma);
                preg_match('/\.(\d+)\.php$/', $b, $mb);
                return ($ma[1] ?? 10) <=> ($mb[1] ?? 10);
            });

            if (empty($handlerFiles)) {
                echo "[MISSING] No handlers found for event: $event\n";
                return false; // Leave in pending
            }

            $handled = false;
            foreach ($handlerFiles as $file) {
                $handler = include $file;
                if (is_callable($handler)) {
                    $handler($args);
                    $handled = true;
                    echo "[HANDLED] $event by " . basename($file) . "\n";
                } else {
                    echo "[SKIP] Handler in " . basename($file) . " is not callable\n";
                }
            }

            return $handled;
        };

        // Process the queue
        try {
            MessageQueue::consume($dispatcher);

            // Show final status
            $remaining = MessageQueue::count();
            $this->out("");
            $this->out("Queue processing complete.");
            $this->out("Remaining messages: $remaining");

        } catch (Exception $e) {
            $this->error("MessageQueue processing error: " . $e->getMessage());
        }
    }
}