<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/CommandInterface.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/CommandInterface.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

interface CommandInterface
{
    /**
     * Execute the command
     *
     * @param array $args Command line arguments
     * @return void
     */
    public function execute(array $args): void;

    /**
     * Get the command name
     *
     * @return string The unique name identifier for this command
     */
    public function getName(): string;

    /**
     * Get the command description
     *
     * @return string A brief description of what this command does
     */
    public function getDescription(): string;

    /**
     * Get detailed help text for this command
     *
     * @return string Detailed usage instructions and examples
     */
    public function getDetailedHelp(): string;
}