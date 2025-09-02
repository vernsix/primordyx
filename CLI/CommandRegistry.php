<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/CommandRegistry.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/CLI/CommandRegistry.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

class CommandRegistry
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    /**
     * Initialize the registry with all available commands
     */
    public function __construct()
    {
        // When we create new instances of these classes, PHP's autoloader
        // will automatically require the necessary files:
        // HelpCommand -> CLI/Commands/HelpCommand.php
        // VersionCommand -> CLI/Commands/VersionCommand.php, etc.
        $this->register(new DoctorCommand());
        $this->register(new HelpCommand($this));
        $this->register(new MakeCommand());
        $this->register(new MigrateCommand());
        $this->register(new SeedCommand());
        $this->register(new MessageQueueConsumeCommand());
        $this->register(new MessageQueuePublishCommand());
        $this->register(new VersionCommand());
    }

    /**
     * Register a command
     *
     * @param CommandInterface $command
     * @return void
     */
    public function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * Get all registered commands
     *
     * @return array<string, CommandInterface>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Run a command based on arguments
     *
     * @param array $args
     * @return void
     */
    public function run(array $args): void
    {
        // Extract command name
        $commandName = array_shift($args) ?? 'help';

        if (!isset($this->commands[$commandName])) {
            Output::error("Unknown command: $commandName\nRun 'primordyx help' to see available commands.");
        }

        $this->commands[$commandName]->execute($args);
    }
}