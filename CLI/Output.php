<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Output.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Output.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

class Output
{
    /**
     * Output a message to stdout
     *
     * @param string $message
     * @return void
     */
    public static function out(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    /**
     * Output an error message to stderr and exit
     *
     * @param string $message
     * @return never
     */
    public static function error(string $message): never
    {
        fwrite(STDERR, PHP_EOL . "Error: " . $message . PHP_EOL);
        exit(1);
    }
}