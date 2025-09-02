<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/VersionCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/VersionCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

use Primordyx\PrimordyxInfo;

class VersionCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'version';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Show the framework version';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx version    Display the current Primordyx framework version";
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

        // Get version from the framework's PrimordyxInfo class
        $version = PrimordyxInfo::version();
        $this->out("Primordyx version $version");
    }
}