<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/AbstractCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/AbstractCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

// When a command class like InstallCommand extends AbstractCommand,
// PHP will trigger the autoloader to load this file automatically.
// The autoloader will look for AbstractCommand.php in the CLI directory.

abstract class AbstractCommand implements CommandInterface
{
    /**
     * Output a message
     *
     * @param string $message
     * @return void
     */
    protected function out(string $message): void
    {
        Output::out($message);
    }

    /**
     * Output an error and exit
     *
     * @param string $message
     * @return never
     */
    protected function error(string $message): never
    {
        Output::error($message);
    }

    /**
     * Get detailed help text for this command
     *
     * Default implementation returns empty string.
     * Override in child classes to provide detailed help.
     *
     * @return string
     */
    public function getDetailedHelp(): string
    {
        return '';
    }
}