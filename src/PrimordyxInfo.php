<?php
/**
 * File: /vendor/vernsix/primordyx/src/PrimordyxInfo.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/PrimordyxInfo.php
 *
 */

declare(strict_types=1);
namespace Primordyx;

/**
 * Class PrimordyxInfo
 *
 * Primordyx Framework Information
 *
 * @since       1.0.0
 *
 */
class PrimordyxInfo
{
    /**
     * Get framework version
     *
     * @return string Framework version
     */
    public static function version(): string
    {
        return '1.0.0';
    }
}