<?php
/**
 * File: /vendor/vernsix/primordyx/src/State.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Geo/State.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Geo;

/**
 * Class State
 *
 * Handles loading and querying state data. This class loads state data from an external JSON file (presumably in
 * the app's storage/states.json file) and provides methods to query state information.
 *
 * @since       1.0.0
 *
 */
class State
{

    /**
     * Cached list of states loaded from JSON.
     *
     * @var array|null
     */
    protected static ?array $listCache = null;

    /**
     * Returns the full list of states and territories from the JSON file.
     *
     * @return array Associative array of states indexed by abbreviation.
     */
    public static function getList(string $statesJsonFilename = 'states.json'): array
    {
        if (self::$listCache !== null) {
            return self::$listCache;
        }
        self::$listCache = json_decode(file_get_contents($statesJsonFilename), true);
        return self::$listCache;
    }

    /**
     * Retrieves a specific state or territory by its abbreviation.
     *
     * @param string $stateOrAbbr The state abbreviation (e.g., 'TX') or name (e.g., 'Texas').
     * @return StateInfo|null The StateInfo object for the state or territory, or null if not found.
     */
    public static function getState(string $stateOrAbbr): ?StateInfo
    {
        $list = self::getList();  // Get the full list of states/territories
        $lookup = strtoupper($stateOrAbbr);  // Standardize the abbreviation to uppercase

        // If we find the abbreviation in the list, return the StateInfo object
        if (isset($list[$lookup])) {
            return new StateInfo($list[$lookup]);
        }

        // Loop through the list to check if the name matches (case-insensitive)
        foreach ($list as $info) {
            if (strcasecmp($info['name'], $stateOrAbbr) === 0) {
                return new StateInfo($info);
            }
        }

        // Return null if the state is not found
        return null;
    }

    /**
     * Gets the capital of a state or territory.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return string|null Capital city, or null if not found.
     */
    public static function getCapital(string $stateOrAbbr): ?string
    {
        $state = self::getState($stateOrAbbr);
        return $state?->capital;
    }

    /**
     * Checks whether the given state abbreviation or name is valid.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if valid, false otherwise.
     */
    public static function isValidState(string $stateOrAbbr): bool
    {
        return self::getState($stateOrAbbr) !== null;
    }

    /**
     * Gets the abbreviation for a state given a name or abbreviation.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return string|null Two-letter abbreviation, or null if not found.
     */
    public static function getAbbreviation(string $stateOrAbbr): ?string
    {
        $state = self::getState($stateOrAbbr);
        return $state?->abbreviation;
    }

    /**
     * Gets the full name of a state.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return string|null Full name of the state.
     */
    public static function getName(string $stateOrAbbr): ?string
    {
        $state = self::getState($stateOrAbbr);
        return $state?->name;
    }

    /**
     * Gets a StateInfo object by capital city.
     *
     * @param string $capital Capital city name.
     * @return StateInfo|null StateInfo object, or null if not found.
     */
    public static function getStateByCapital(string $capital): ?StateInfo
    {
        foreach (self::getList() as $abbr => $info) {
            if (strcasecmp($info['capital'], $capital) === 0) {
                return new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return null;
    }

    /**
     * Returns a random StateInfo object.
     *
     * @return StateInfo|null A random state or territory.
     */
    public static function getStateRandom(): ?StateInfo
    {
        $list = self::getList();
        $abbr = array_rand($list);
        return new StateInfo(['abbreviation' => $abbr] + $list[$abbr]);
    }

    /**
     * Returns the total number of states and territories.
     *
     * @return int Count of entries in the list.
     */
    public static function count(): int
    {
        return count(self::getList());
    }

    /**
     * Returns all states in the given region.
     *
     * @param string $region Region name (e.g., 'West').
     * @return StateInfo[] List of StateInfo objects.
     */
    public static function getStatesByRegion(string $region): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if (strcasecmp($info['region'], $region) === 0) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Returns an HTML <select> element for states.
     *
     * @return string HTML select dropdown of all states.
     */
    public static function dropDown(): string
    {
        $options = '';
        foreach (self::getList() as $abbr => $info) {
            $options .= "<option value=\"$abbr\">{$info['name']}</option>\n";
        }
        return "<select name=\"state\" id=\"state\">\n$options</select>";
    }

    /**
     * Returns all states in a given timezone.
     *
     * @param string $timezone Timezone name (e.g., 'Central').
     * @return StateInfo[] List of StateInfo objects.
     */
    public static function getStatesByTimezone(string $timezone): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if (strcasecmp($info['timezone'], $timezone) === 0) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Determines if the provided entry is a U.S. territory.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if it is a territory, false if state or not found.
     */
    public static function isTerritory(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && !$state->isState;
    }

    /**
     * Checks if a state is in the given region.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @param string $region Region to check.
     * @return bool True if in region.
     */
    public static function isInRegion(string $stateOrAbbr, string $region): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->region, $region) === 0;
    }

    /**
     * Checks if a state is in the given timezone.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @param string $timezone Timezone to check.
     * @return bool True if in timezone.
     */
    public static function isInTimezone(string $stateOrAbbr, string $timezone): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->timezone, $timezone) === 0;
    }

    /**
     * Checks if a state is in the contiguous lower 48.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if in the Lower 48.
     */
    public static function isLower48(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state ? $state->isContiguous : false;
    }

    /**
     * Checks if a state is part of the Western U.S.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if western.
     */
    public static function isWestern(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->region, 'West') === 0;
    }

    /**
     * Checks if a state is in the Southern U.S.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if southern.
     */
    public static function isSouth(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->region, 'South') === 0;
    }

    /**
     * Checks if a state is part of the Deep South cultural region.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if Deep South.
     */
    public static function isDeepSouth(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->culturalRegion, 'Deep South') === 0;
    }

    /**
     * Determines whether the given state or territory is located in the U.S. Northeast region.
     *
     * @param string $stateOrAbbr The state's full name or abbreviation.
     * @return bool True if the state is in the Northeast region; false otherwise.
     */
    public static function isNortheast(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->region, 'Northeast') === 0;
    }


    /**
     * Determines whether the given state or territory is located in the U.S. Midwest region.
     *
     * @param string $stateOrAbbr The state's full name or abbreviation.
     * @return bool True if the state is in the Midwest region; false otherwise.
     */
    public static function isMidwest(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->region, 'Midwest') === 0;
    }

    /**
     * Determines whether the given state was one of the original 13 U.S. colonies.
     *
     * @param string $stateOrAbbr The state's full name or abbreviation.
     * @return bool True if the state was part of the original 13 colonies; false otherwise.
     */
    public static function isOriginal13(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state ? $state->isOriginal13 : false;
    }


    /**
     * Determines whether the given state or territory is considered part of the Rust Belt cultural region.
     *
     * @param string $stateOrAbbr The state's full name or abbreviation.
     * @return bool True if the state is part of the Rust Belt; false otherwise.
     */
    public static function isRustBelt(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state && strcasecmp($state->culturalRegion, 'Rust Belt') === 0;
    }

    /**
     * Determines if the state observes Daylight Saving Time.
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return bool True if observes DST.
     */
    public static function hasDaylightSaving(string $stateOrAbbr): bool
    {
        $state = self::getState($stateOrAbbr);
        return $state ? $state->hasDST : false;
    }

    /**
     * Returns all states with a matching FIPS code.
     *
     * @param string $fips FIPS code to match.
     * @return StateInfo[] List of matches.
     */
    public static function getStatesByFips(string $fips): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if ($info['fips'] === $fips) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Returns all states matching a cultural region.
     *
     * @param string $region Cultural region name.
     * @return StateInfo[] Matching states.
     */
    public static function getStatesByCulturalRegion(string $region): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if (strcasecmp($info['culturalRegion'], $region) === 0) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Converts a state's name into a slug (lowercase, hyphenated).
     *
     * @param string $stateOrAbbr Abbreviation or name.
     * @return string Slug string or empty string if not found.
     */
    public static function toSlug(string $stateOrAbbr): string
    {
        $state = self::getState($stateOrAbbr);
        return $state ? strtolower(str_replace(' ', '-', $state->name)) : '';
    }

    /**
     * Returns all states in a given statehood category.
     *
     * @param string $category Category name (e.g., 'Original', 'Annexed').
     * @return StateInfo[] List of matches.
     */
    public static function getStatesByStatehoodCategory(string $category): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if (strcasecmp($info['statehoodCategory'], $category) === 0) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Returns all states that allow Remote Online Notarization (RON).
     *
     * @return StateInfo[] States with RON support.
     */
    public static function getStatesWithRON(): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if ($info['hasRON']) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Returns all the original 13 colonies.
     *
     * @return StateInfo[] Original 13 states.
     */
    public static function getOriginal13States(): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if ($info['isOriginal13']) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Returns a list of U.S. territories (non-states).
     *
     * @return StateInfo[] Territories only.
     */
    public static function getTerritories(): array
    {
        $result = [];
        foreach (self::getList() as $abbr => $info) {
            if (!$info['isState']) {
                $result[] = new StateInfo(['abbreviation' => $abbr] + $info);
            }
        }
        return $result;
    }

    /**
     * Builds <option> HTML tags from an array of StateInfo objects.
     *
     * @param StateInfo[] $states List of StateInfo objects.
     * @return string HTML <option> block.
     */
    public static function asSelectOptions(array $states): string
    {
        $options = '';
        foreach ($states as $state) {
            $options .= "<option value=\"". $state->abbreviation . "\">" . $state->name . "</option>\n";
        }
        return $options;
    }

    /**
     * Returns a list of all unique cultural regions present in the state list.
     *
     * @return string[] Array of unique cultural region names.
     */
    public static function getAllCulturalRegions(): array
    {
        $regions = array_map(fn($info) => $info['culturalRegion'] ?? '', self::getList());
        return array_values(array_unique(array_filter($regions)));
    }

    /**
     * Returns a list of all unique statehood categories present in the state list.
     *
     * @return string[] Array of unique statehood categories.
     */
    public static function getAllStatehoodCategories(): array
    {
        $categories = array_map(fn($info) => $info['statehoodCategory'] ?? '', self::getList());
        return array_values(array_unique(array_filter($categories)));
    }

    /**
     * Returns a list of all unique geographic regions present in the state list.
     *
     * @return string[] Array of unique region names.
     */
    public static function getAllRegions(): array
    {
        $regions = array_map(fn($info) => $info['region'] ?? '', self::getList());
        return array_values(array_unique(array_filter($regions)));
    }

    /**
     * Groups all states by their geographic region.
     *
     * @return array Associative array where the key is the region name
     *                                    and the value is a list of StateInfo objects.
     */
    public static function groupByRegion(): array
    {
        $groups = [];
        foreach (self::getList() as $abbr => $info) {
            $region = $info['region'] ?? 'Unknown';
            $groups[$region][] = new StateInfo(['abbreviation' => $abbr] + $info);
        }
        return $groups;
    }

    /**
     * Groups all states by their timezone.
     *
     * @return array Associative array where the key is the timezone name
     *                                    and the value is a list of StateInfo objects.
     */
    public static function groupByTimezone(): array
    {
        $groups = [];
        foreach (self::getList() as $abbr => $info) {
            $tz = $info['timezone'] ?? 'Unknown';
            $groups[$tz][] = new StateInfo(['abbreviation' => $abbr] + $info);
        }
        return $groups;
    }


}
