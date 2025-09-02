<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/DoctorCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/DoctorCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

class DoctorCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'doctor';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Run environment checks and diagnostics';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx doctor    Check your environment for Primordyx requirements

Checks performed:
  - PHP version (requires 8.2+)
  - Required PHP extensions
  - Directory permissions
  
Required extensions:
  - pdo, openssl, curl, gd, fileinfo, mbstring,
  - iconv, ctype, libxml, dom, json";
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

        $this->out("Running Primordyx environment check:");
        $this->out("");

        $hasErrors = false;

        // Check PHP version (requires ^8.2)
        $phpVersion = phpversion();
        $phpVersionOk = version_compare($phpVersion, '8.2.0', '>=');
        $status = $phpVersionOk ? '✓' : '✗';
        $this->out("PHP Version: $phpVersion $status" . ($phpVersionOk ? '' : ' (requires PHP 8.2+)'));
        if (!$phpVersionOk) $hasErrors = true;

        $this->out("");
        $this->out("Required PHP Extensions:");

        // Check all required extensions from composer.json
        $requiredExtensions = [
            'pdo'      => 'PDO (Database)',
            'openssl'  => 'OpenSSL (Encryption)',
            'curl'     => 'cURL (HTTP requests)',
            'gd'       => 'GD (Image processing)',
            'fileinfo' => 'Fileinfo (File type detection)',
            'mbstring' => 'Multibyte String',
            'iconv'    => 'iconv (Character encoding)',
            'ctype'    => 'ctype (Character type checking)',
            'libxml'   => 'libxml (XML processing)',
            'dom'      => 'DOM (Document Object Model)',
            'json'     => 'JSON (Data interchange)'
        ];

        foreach ($requiredExtensions as $ext => $description) {
            $loaded = extension_loaded($ext);
            $status = $loaded ? '✓' : '✗';
            $this->out("  $ext: $status $description");
            if (!$loaded) $hasErrors = true;
        }

        $this->out("");
        $this->out("Directory Permissions:");

        // Check writable directories in the project
        $projectDirs = ['logs', 'controllers', 'models', 'migrations'];
        foreach ($projectDirs as $dir) {
            // getcwd() = Current directory where user ran the primordyx command
            $path = getcwd() . "/$dir"; // e.g., /home/user/myproject/logs
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);

            if (!$exists) {
                $this->out("  $dir: ⚠ (not found - will be created when needed)");
            } else {
                $status = $writable ? '✓' : '✗';
                $this->out("  $dir: $status" . ($writable ? ' (writable)' : ' (not writable)'));
                if (!$writable) $hasErrors = true;
            }
        }

        $this->out("");
        if ($hasErrors) {
            $this->out("⚠️ Some issues were found. Please fix them for optimal functionality.");
        } else {
            $this->out("✅ All checks passed! Your environment is ready for Primordyx.");
        }
    }
}