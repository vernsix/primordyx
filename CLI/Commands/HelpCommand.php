<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/HelpCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/Commands/HelpCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

class HelpCommand extends AbstractCommand
{
    /**
     * @var CommandRegistry
     */
    private CommandRegistry $registry;

    /**
     * @param CommandRegistry $registry
     */
    public function __construct(CommandRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'help';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Show this help message';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx help              Show basic command list
  primordyx help <command>    Show detailed help for a specific command
  primordyx --help            Show comprehensive help for all commands";
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args): void
    {
        // Check if --all flag is present (set by launcher for top-level --help)
        $showAll = in_array('--all', $args);

        // Check if asking for help on specific command
        $commandName = null;
        foreach ($args as $arg) {
            if ($arg !== '--all' && !str_starts_with($arg, '-')) {
                $commandName = $arg;
                break;
            }
        }

        if ($commandName && isset($this->registry->getCommands()[$commandName])) {
            // Show help for specific command
            $this->showCommandHelp($commandName);
        } elseif ($showAll) {
            // Show comprehensive help for all commands
            $this->showAllHelp();
        } else {
            // Show basic command list
            $this->showBasicHelp();
        }
    }

    /**
     * Show basic command list
     *
     * @return void
     */
    private function showBasicHelp(): void
    {
        $this->out("Primordyx CLI - Available Commands:");

        $commands = $this->registry->getCommands();
        $maxLength = 0;

        // Find the longest command name for alignment
        foreach ($commands as $command) {
            $length = strlen($command->getName());
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        // Display each command with description
        foreach ($commands as $command) {
            $name = str_pad($command->getName(), $maxLength + 2);
            $this->out("  {$name} {$command->getDescription()}");
        }

        $this->out("");
        $this->out("For detailed help on a command, use: primordyx help <command>");
        $this->out("For comprehensive help, use: primordyx --help");
    }

    /**
     * Show detailed help for a specific command
     *
     * @param string $commandName
     * @return void
     */
    private function showCommandHelp(string $commandName): void
    {
        $command = $this->registry->getCommands()[$commandName];

        $this->out("Command: {$command->getName()}");
        $this->out("Description: {$command->getDescription()}");
        $this->out("");

        $detailedHelp = $command->getDetailedHelp();
        if ($detailedHelp) {
            $this->out($detailedHelp);
        } else {
            $this->out("No detailed help available for this command.");
        }
    }

    /**
     * Show comprehensive help for all commands
     *
     * @return void
     */
    private function showAllHelp(): void
    {
        $this->out("Primordyx CLI - Comprehensive Help");
        $this->out("==================================");
        $this->out("");

        foreach ($this->registry->getCommands() as $command) {
            $this->out("Command: {$command->getName()}");
            $this->out("Description: {$command->getDescription()}");

            $detailedHelp = $command->getDetailedHelp();
            if ($detailedHelp) {
                $this->out("");
                // Indent detailed help for readability
                $lines = explode("\n", $detailedHelp);
                foreach ($lines as $line) {
                    $this->out("  " . $line);
                }
            }

            $this->out("");
            $this->out(str_repeat("-", 60));
            $this->out("");
        }
    }
}
