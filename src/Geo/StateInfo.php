<?php
/**
 * File: /vendor/vernsix/primordyx/src/StateInfo.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Geo/StateInfo.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Geo;

use JsonSerializable;

/**
 * Class StateInfo
 *
 *  This class represents a state or territory, encapsulating various data attributes related to it.
 *  The data is expected to be passed as an associative array, and the object can be easily serialized to JSON.
 *
 * @since       1.0.0
 *
 */
class StateInfo implements JsonSerializable
{
    /**
     * @var string The abbreviation of the state (e.g., 'TX', 'CA')
     */
    public string $abbreviation;

    /**
     * @var string The full name of the state (e.g., 'Texas', 'California')
     */
    public string $name;

    /**
     * @var string The capital city of the state (e.g., 'Austin', 'Sacramento')
     */
    public string $capital;

    /**
     * @var string The primary timezone of the state (e.g., 'Central', 'Pacific')
     */
    public string $timezone;

    /**
     * @var array The timezone offsets for standard and daylight time (e.g., ['standard' => '-06:00', 'daylight' => '-05:00'])
     */
    public array $timezoneOffset;

    /**
     * @var array The timezone abbreviations for standard and daylight time (e.g., ['standard' => 'CST', 'daylight' => 'CDT'])
     */
    public array $timezoneAbbr;

    /**
     * @var string The region of the state (e.g., 'South', 'West', 'Midwest')
     */
    public string $region;

    /**
     * @var string The FIPS code for the state (e.g., '48' for Texas)
     */
    public string $fips;

    /**
     * @var int|null The year the state was admitted to the Union or null for territories
     */
    public ?int $admitted;

    /**
     * @var bool True if the state is part of the contiguous 48 states
     */
    public bool $isContiguous;

    /**
     * @var bool True if the state observes Daylight Saving Time (DST)
     */
    public bool $hasDST;

    /**
     * @var string The official nickname of the state (e.g., 'The Lone Star State', 'The Golden State')
     */
    public string $nickname;

    /**
     * @var string The state bird (e.g., 'Northern Mockingbird')
     */
    public string $bird;

    /**
     * @var string The state flower (e.g., 'Bluebonnet')
     */
    public string $flower;

    /**
     * @var string The state tree (e.g., 'Pecan')
     */
    public string $tree;

    /**
     * @var string The state motto (e.g., 'Friendship', 'Eureka')
     */
    public string $motto;

    /**
     * @var bool True if this is a state, false for territories
     */
    public bool $isState;

    /**
     * @var string The ISO 3166-2 code for the state (e.g., 'US-TX')
     */
    public string $isoCode;

    /**
     * @var int|null The state's constitutional order of ratification or null for territories
     */
    public ?int $constitutionalOrder;

    /**
     * @var string The cultural region to which the state belongs (e.g., 'Deep South', 'New England')
     */
    public string $culturalRegion;

    /**
     * @var array An array of state abbreviations that border this state (e.g., ['TX', 'OK', 'AR'])
     */
    public array $borders;

    /**
     * @var bool True if the state was one of the original 13 colonies, false otherwise
     */
    public bool $isOriginal13;

    /**
     * @var bool True if the state allows Remote Online Notarization (RON)
     */
    public bool $hasRON;

    /**
     * @var string The category of statehood: 'Original Colony', 'Pre-Civil War', 'Post-Civil War', etc.
     */
    public string $statehoodCategory;

    /**
     * Constructor for the StateInfo class.
     *
     * Initializes the state information based on the passed array.
     *
     * @param array $info An associative array containing the state or territory data.
     */
    public function __construct(array $info)
    {
        $this->abbreviation = $info['abbreviation'] ?? '';
        $this->name = $info['name'] ?? '';
        $this->capital = $info['capital'] ?? '';
        $this->timezone = $info['timezone'] ?? '';
        $this->timezoneOffset = $info['timezoneOffset'] ?? ['standard' => '', 'daylight' => ''];
        $this->timezoneAbbr = $info['timezoneAbbr'] ?? ['standard' => '', 'daylight' => ''];
        $this->region = $info['region'] ?? '';
        $this->fips = $info['fips'] ?? '';
        $this->admitted = $info['admitted'] ?? null;
        $this->isContiguous = $info['isContiguous'] ?? false;
        $this->hasDST = $info['hasDST'] ?? false;
        $this->nickname = $info['nickname'] ?? '';
        $this->bird = $info['bird'] ?? '';
        $this->flower = $info['flower'] ?? '';
        $this->tree = $info['tree'] ?? '';
        $this->motto = $info['motto'] ?? '';
        $this->isState = $info['isState'] ?? false;
        $this->isoCode = $info['isoCode'] ?? '';
        $this->constitutionalOrder = $info['constitutionalOrder'] ?? null;
        $this->culturalRegion = $info['culturalRegion'] ?? '';
        $this->borders = $info['borders'] ?? [];
        $this->isOriginal13 = $info['isOriginal13'] ?? false;
        $this->hasRON = $info['hasRON'] ?? false;
        $this->statehoodCategory = $info['statehoodCategory'] ?? '';
    }

    /**
     * Implements JsonSerializable to convert to JSON easily.
     *
     * @return array An array of the state data suitable for JSON encoding.
     */
    public function jsonSerialize(): array
    {
        return [
            'abbreviation' => $this->abbreviation,
            'name' => $this->name,
            'capital' => $this->capital,
            'timezone' => $this->timezone,
            'timezoneOffset' => $this->timezoneOffset,
            'timezoneAbbr' => $this->timezoneAbbr,
            'region' => $this->region,
            'fips' => $this->fips,
            'admitted' => $this->admitted,
            'isContiguous' => $this->isContiguous,
            'hasDST' => $this->hasDST,
            'nickname' => $this->nickname,
            'bird' => $this->bird,
            'flower' => $this->flower,
            'tree' => $this->tree,
            'motto' => $this->motto,
            'isState' => $this->isState,
            'isoCode' => $this->isoCode,
            'constitutionalOrder' => $this->constitutionalOrder,
            'culturalRegion' => $this->culturalRegion,
            'borders' => $this->borders,
            'isOriginal13' => $this->isOriginal13,
            'hasRON' => $this->hasRON,
            'statehoodCategory' => $this->statehoodCategory
        ];
    }
}
