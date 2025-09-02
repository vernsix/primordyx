<?php
/**
 * File: /vendor/vernsix/primordyx/CLI/Commands/MakeCommand.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://bitbucket.org/vernsix/primordyx/CLI/Commands/MakeCommand.php
 *
 */

declare(strict_types=1);

namespace PrimordyxCLI;

class MakeCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'make';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Generate new controllers and models';
    }

    /**
     * @inheritDoc
     */
    public function getDetailedHelp(): string
    {
        return "Usage:
  primordyx make controller <Name>    Create a new controller class
  primordyx make model <Name>         Create a new model class

Examples:
  primordyx make controller UserController
  primordyx make model User

Notes:
  - Controller files are created in the 'controllers' directory
  - Model files are created in the 'models' directory
  - Directories will be created if they don't exist
  - Class names will be sanitized to remove invalid characters";
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

        if (count($args) < 2) {
            $this->error("Usage: primordyx make [controller|model] Name");
        }

        $type = $args[0];
        $name = $args[1];
        $className = preg_replace('/[^A-Za-z0-9_]/', '', $name);

        switch ($type) {
            case 'controller':
                $this->makeController($className);
                break;

            case 'model':
                $this->makeModel($className);
                break;

            default:
                $this->error("Unknown make target: $type");
        }
    }

    /**
     * Generate a controller file
     *
     * @param string $className
     * @return void
     */
    private function makeController(string $className): void
    {
        // getcwd() = Current directory where user ran the primordyx command
        // e.g., /home/user/myproject
        $dir = getcwd() . '/controllers'; // = /home/user/myproject/controllers
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "$dir/$className.controller.php";
        if (file_exists($filename)) {
            $this->out("Controller already exists: $filename");
            return;
        }

        $template = <<<PHP
<?php

class $className
{
    public function index()
    {
        echo 'Hello from $className';
    }
}

PHP;

        file_put_contents($filename, $template);
        $this->out("Created controller: $filename");
    }

    /**
     * Generate a model file
     *
     * @param string $className
     * @return void
     */
    private function makeModel(string $className): void
    {
        // getcwd() = Current directory where user ran the primordyx command
        // e.g., /home/user/myproject
        $dir = getcwd() . '/models'; // = /home/user/myproject/models
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "$dir/$className.model.php";
        if (file_exists($filename)) {
            $this->out("Model already exists: $filename");
            return;
        }

        $template = <<<PHP
<?php

class $className
{
    // Define your model logic here
}

PHP;

        file_put_contents($filename, $template);
        $this->out("Created model: $filename");
    }
}