<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/MessageQueuePublishCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/MessageQueuePublishCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Exception;
use Primordyx\Events\MessageQueue;

class MessageQueuePublishCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'messagequeue:publish';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Publish a message to the MessageQueue';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx messagequeue:publish <event> [key=value]...

Arguments:
  event         The event name (e.g., user.registered, email.send)
  key=value     Event arguments as key=value pairs

Examples:
  primordyx messagequeue:publish user.registered user_id=123 email=john@example.com
  primordyx messagequeue:publish email.send to=admin@example.com subject=\"Welcome\" body=\"Hello World\"
  primordyx messagequeue:publish order.completed order_id=456 total=99.99

Notes:
  - Event names typically use dot notation (e.g., user.registered)
  - Arguments are passed as key=value pairs
  - Use quotes for values containing spaces
  - Messages are queued and processed asynchronously";
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

        // Need at least an event name
        if (count($args) < 1) {
            $this->error("Usage: primordyx messagequeue:publish <event> [key=value]...");
        }

        // Extract event name
        $event = array_shift($args);

        // Parse key=value arguments
        $namedArgs = [];
        foreach ($args as $arg) {
            if (str_contains($arg, '=')) {
                [$key, $val] = explode('=', $arg, 2);

                // Try to parse JSON values
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $namedArgs[$key] = $decoded;
                } else {
                    // Use as string
                    $namedArgs[$key] = $val;
                }
            } else {
                $this->out("Warning: Ignoring argument without '=' sign: $arg");
            }
        }

        // Configure MessageQueue if not already configured
        if (!MessageQueue::isConfigured()) {
            MessageQueue::configure(APP_STORAGE_PATH . '/messagequeue');
        }

        // Publish the message
        try {
            MessageQueue::publish($event, $namedArgs);
            $this->out("✅ Message published successfully!");
            $this->out("Event: $event");

            if (!empty($namedArgs)) {
                $this->out("Arguments: " . json_encode($namedArgs, JSON_PRETTY_PRINT));
            }

            $this->out("");
            $this->out("Run 'primordyx messagequeue:consume' to process the queue.");

        } catch (Exception $e) {
            $this->error("Failed to publish message: " . $e->getMessage());
        }
    }
}