<?php
/**
 * File: /vendor/vernsix/primordyx/src/Conversions.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Utils/Conversions.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Utils;

use InvalidArgumentException;

/**
 * Class Conversions
 *
 * A comprehensive utility class providing static methods for converting between various
 * units of measurement including weight, temperature, length, and fractions. This class
 * supports common conversions for both imperial and metric systems, mathematical fraction
 * operations, and specialized formatting functions.
 *
 * Key features:
 * - Weight conversions (ounces, grams, pounds, kilograms)
 * - Temperature conversions (Celsius, Fahrenheit, Kelvin) with validation methods
 * - Length conversions (millimeters, inches, centimeters, feet, meters, yards)
 * - Comprehensive fraction operations (arithmetic, formatting, parsing)
 * - Mixed number handling and standard fraction approximations
 *
 * All conversion factors use precise values from international standards.
 * Fraction operations include GCD/LCM calculations for proper reduction.
 *
 * @since       1.0.0
 *
 */
class Conversions
{
    // -------------------------
    // WEIGHT CONVERSIONS
    // -------------------------

    /**
     * Converts ounces to grams.
     *
     * Uses the standard conversion factor: 1 ounce = 28.3495 grams
     *
     * @param float $oz Weight in ounces
     * @return float Weight in grams
     */
    public static function ouncesToGrams(float $oz): float {
        return $oz * 28.3495;
    }

    /**
     * Converts grams to ounces.
     *
     * Uses the standard conversion factor: 1 gram = 1/28.3495 ounces
     *
     * @param float $grams Weight in grams
     * @return float Weight in ounces
     */
    public static function gramsToOunces(float $grams): float {
        return $grams / 28.3495;
    }

    /**
     * Converts pounds to kilograms.
     *
     * Uses the international avoirdupois pound definition: 1 pound = 0.45359237 kilograms
     *
     * @param float $lbs Weight in pounds
     * @return float Weight in kilograms
     */
    public static function poundsToKilograms(float $lbs): float {
        return $lbs * 0.45359237;
    }

    /**
     * Converts kilograms to pounds.
     *
     * Uses the international avoirdupois pound definition: 1 kilogram = 1/0.45359237 pounds
     *
     * @param float $kg Weight in kilograms
     * @return float Weight in pounds
     */
    public static function kilogramsToPounds(float $kg): float {
        return $kg / 0.45359237;
    }

    /**
     * Converts ounces to pounds.
     *
     * Uses the standard definition: 1 pound = 16 ounces
     *
     * @param float $oz Weight in ounces
     * @return float Weight in pounds
     */
    public static function ouncesToPounds(float $oz): float {
        return $oz / 16;
    }

    /**
     * Converts pounds to ounces.
     *
     * Uses the standard definition: 1 pound = 16 ounces
     *
     * @param float $lbs Weight in pounds
     * @return float Weight in ounces
     */
    public static function poundsToOunces(float $lbs): float {
        return $lbs * 16;
    }

    /**
     * Converts grams to kilograms.
     *
     * Uses metric system definition: 1 kilogram = 1000 grams
     *
     * @param float $g Weight in grams
     * @return float Weight in kilograms
     */
    public static function gramsToKilograms(float $g): float {
        return $g / 1000;
    }

    /**
     * Converts kilograms to grams.
     *
     * Uses metric system definition: 1 kilogram = 1000 grams
     *
     * @param float $kg Weight in kilograms
     * @return float Weight in grams
     */
    public static function kilogramsToGrams(float $kg): float {
        return $kg * 1000;
    }

    // -------------------------
    // TEMPERATURE CONVERSIONS
    // -------------------------

    /**
     * Converts Celsius to Fahrenheit.
     *
     * Uses the formula: F = (C × 9/5) + 32
     *
     * @param float $c Temperature in Celsius
     * @return float Temperature in Fahrenheit
     */
    public static function celsiusToFahrenheit(float $c): float {
        return ($c * 9 / 5) + 32;
    }

    /**
     * Converts Fahrenheit to Celsius.
     *
     * Uses the formula: C = (F - 32) × 5/9
     *
     * @param float $f Temperature in Fahrenheit
     * @return float Temperature in Celsius
     */
    public static function fahrenheitToCelsius(float $f): float {
        return ($f - 32) * 5 / 9;
    }

    /**
     * Converts Celsius to Kelvin.
     *
     * Uses the formula: K = C + 273.15
     *
     * @param float $c Temperature in Celsius
     * @return float Temperature in Kelvin
     */
    public static function celsiusToKelvin(float $c): float {
        return $c + 273.15;
    }

    /**
     * Converts Kelvin to Celsius.
     *
     * Uses the formula: C = K - 273.15
     *
     * @param float $k Temperature in Kelvin
     * @return float Temperature in Celsius
     */
    public static function kelvinToCelsius(float $k): float {
        return $k - 273.15;
    }

    /**
     * Converts Fahrenheit to Kelvin.
     *
     * Combines Fahrenheit to Celsius conversion with Celsius to Kelvin
     *
     * @param float $f Temperature in Fahrenheit
     * @return float Temperature in Kelvin
     */
    public static function fahrenheitToKelvin(float $f): float {
        return ($f - 32) * 5 / 9 + 273.15;
    }

    /**
     * Converts Kelvin to Fahrenheit.
     *
     * Combines Kelvin to Celsius conversion with Celsius to Fahrenheit
     *
     * @param float $k Temperature in Kelvin
     * @return float Temperature in Fahrenheit
     */
    public static function kelvinToFahrenheit(float $k): float {
        return ($k - 273.15) * 9 / 5 + 32;
    }

    /**
     * Converts temperature between any supported units using a unified interface.
     *
     * Supports conversions between Celsius (c), Fahrenheit (f), and Kelvin (k).
     * Case-insensitive unit specifications.
     *
     * @param float $value Temperature value to convert
     * @param string $from Source unit ('c', 'f', or 'k')
     * @param string $to Target unit ('c', 'f', or 'k')
     * @return float Converted temperature value
     * @throws InvalidArgumentException If conversion combination is invalid
     */
    public static function convertTemperature(float $value, string $from, string $to): float {
        $from = strtolower($from);
        $to = strtolower($to);
        return match ("$from-$to") {
            'c-f' => self::celsiusToFahrenheit($value),
            'c-k' => self::celsiusToKelvin($value),
            'f-c' => self::fahrenheitToCelsius($value),
            'f-k' => self::fahrenheitToKelvin($value),
            'k-c' => self::kelvinToCelsius($value),
            'k-f' => self::kelvinToFahrenheit($value),
            default => throw new InvalidArgumentException("Invalid conversion: $from to $to")
        };
    }

    /**
     * Checks if a temperature represents the boiling point of water.
     *
     * Uses a tolerance of 0.01 degrees to account for floating-point precision.
     * Reference points: 100°C, 212°F, 373.15K
     *
     * @param float $temp Temperature value to check
     * @param string $unit Temperature unit ('c', 'f', or 'k')
     * @return bool True if temperature is at boiling point
     */
    public static function isBoilingPoint(float $temp, string $unit): bool {
        return match (strtolower($unit)) {
            'c' => abs($temp - 100) < 0.01,
            'f' => abs($temp - 212) < 0.01,
            'k' => abs($temp - 373.15) < 0.01,
            default => false
        };
    }

    /**
     * Checks if a temperature represents the freezing point of water.
     *
     * Uses a tolerance of 0.01 degrees to account for floating-point precision.
     * Reference points: 0°C, 32°F, 273.15K
     *
     * @param float $temp Temperature value to check
     * @param string $unit Temperature unit ('c', 'f', or 'k')
     * @return bool True if temperature is at freezing point
     */
    public static function isFreezingPoint(float $temp, string $unit): bool {
        return match (strtolower($unit)) {
            'c' => abs($temp) < 0.01,
            'f' => abs($temp - 32) < 0.01,
            'k' => abs($temp - 273.15) < 0.01,
            default => false
        };
    }

    // -------------------------
    // LENGTH CONVERSIONS
    // -------------------------

    /**
     * Converts millimeters to inches.
     *
     * Uses the international definition: 1 inch = 25.4 millimeters exactly
     *
     * @param float $mm Length in millimeters
     * @return float Length in inches
     */
    public static function millimetersToInches(float $mm): float {
        return $mm / 25.4;
    }

    /**
     * Converts inches to millimeters.
     *
     * Uses the international definition: 1 inch = 25.4 millimeters exactly
     *
     * @param float $in Length in inches
     * @return float Length in millimeters
     */
    public static function inchesToMillimeters(float $in): float {
        return $in * 25.4;
    }

    /**
     * Converts centimeters to inches.
     *
     * Uses the international definition: 1 inch = 2.54 centimeters exactly
     *
     * @param float $cm Length in centimeters
     * @return float Length in inches
     */
    public static function centimetersToInches(float $cm): float {
        return $cm / 2.54;
    }

    /**
     * Converts inches to centimeters.
     *
     * Uses the international definition: 1 inch = 2.54 centimeters exactly
     *
     * @param float $in Length in inches
     * @return float Length in centimeters
     */
    public static function inchesToCentimeters(float $in): float {
        return $in * 2.54;
    }

    /**
     * Converts feet to meters.
     *
     * Uses the international foot definition: 1 foot = 0.3048 meters exactly
     *
     * @param float $ft Length in feet
     * @return float Length in meters
     */
    public static function feetToMeters(float $ft): float {
        return $ft * 0.3048;
    }

    /**
     * Converts meters to feet.
     *
     * Uses the international foot definition: 1 foot = 0.3048 meters exactly
     *
     * @param float $m Length in meters
     * @return float Length in feet
     */
    public static function metersToFeet(float $m): float {
        return $m / 0.3048;
    }

    /**
     * Converts yards to meters.
     *
     * Uses the international yard definition: 1 yard = 0.9144 meters exactly
     *
     * @param float $yd Length in yards
     * @return float Length in meters
     */
    public static function yardsToMeters(float $yd): float {
        return $yd * 0.9144;
    }

    /**
     * Converts meters to yards.
     *
     * Uses the international yard definition: 1 yard = 0.9144 meters exactly
     *
     * @param float $m Length in meters
     * @return float Length in yards
     */
    public static function metersToYards(float $m): float {
        return $m / 0.9144;
    }

    /**
     * Converts inches to feet.
     *
     * Uses the standard definition: 1 foot = 12 inches
     *
     * @param float $in Length in inches
     * @return float Length in feet
     */
    public static function inchesToFeet(float $in): float {
        return $in / 12;
    }

    /**
     * Converts feet to inches.
     *
     * Uses the standard definition: 1 foot = 12 inches
     *
     * @param float $ft Length in feet
     * @return float Length in inches
     */
    public static function feetToInches(float $ft): float {
        return $ft * 12;
    }

    /**
     * Converts inches to millimeters and rounds to the nearest whole millimeter.
     *
     * Useful for practical measurements where sub-millimeter precision is not needed.
     *
     * @param float $inches Length in inches
     * @return float Length in millimeters, rounded to nearest whole number
     */
    public static function roundToNearestMillimeter(float $inches): float {
        return round(self::inchesToMillimeters($inches));
    }

    /**
     * Converts inches to millimeters and returns as a fraction string.
     *
     * @param float $inches Length in inches
     * @param int $precision Maximum denominator for fraction approximation (default: 16)
     * @return string Millimeter measurement as a fraction string
     */
    public static function inchesToMMFraction(float $inches, int $precision = 16): string {
        return self::decimalToFraction(self::inchesToMillimeters($inches), $precision);
    }

    /**
     * Converts millimeters to inches and returns as closest fraction string.
     *
     * @param float $mm Length in millimeters
     * @param int $precision Maximum denominator for fraction approximation (default: 16)
     * @return string Inch measurement as a fraction string
     */
    public static function millimetersToClosestFraction(float $mm, int $precision = 16): string {
        return self::decimalToFraction(self::millimetersToInches($mm), $precision);
    }

    // -------------------------
    // FRACTION OPERATIONS
    // -------------------------

    /**
     * Converts a fraction string to its decimal equivalent.
     *
     * Handles both simple fractions (e.g., "3/4") and whole numbers.
     * Uses array_reduce for elegant division chain handling.
     *
     * @param string $fraction Fraction in format "numerator/denominator" or whole number
     * @return float Decimal equivalent of the fraction
     */
    public static function fractionToDecimal(string $fraction): float {
        return str_contains($fraction, '/')
            ? array_reduce(explode('/', $fraction), fn($c, $n) => $c / $n, 1)
            : (float)$fraction;
    }

    /**
     * Converts a decimal number to its closest fraction representation.
     *
     * Uses an iterative algorithm to find the fraction with the smallest error
     * within the specified precision (maximum denominator). Handles mixed numbers
     * by separating whole and fractional parts.
     *
     * @param float $decimal Decimal number to convert
     * @param int $precision Maximum denominator to consider (default: 16)
     * @return string Fraction string, potentially including whole number part
     */
    public static function decimalToFraction(float $decimal, int $precision = 16): string {
        $whole = floor($decimal);
        $fraction = $decimal - $whole;
        if ($fraction == 0) return (string)$whole;

        $best = [1, 1, abs($fraction - 1)];
        for ($d = 1; $d <= $precision; $d++) {
            $n = round($fraction * $d);
            $err = abs($fraction - $n / $d);
            if ($err < $best[2]) $best = [$n, $d, $err];
        }

        $frac = "$best[0]/$best[1]";
        return $whole > 0 ? "$whole $frac" : $frac;
    }

    /**
     * Reduces a fraction to its lowest terms using GCD.
     *
     * @param string $fraction Fraction in "numerator/denominator" format
     * @return string Reduced fraction string
     */
    public static function reduceFraction(string $fraction): string {
        [$n, $d] = explode('/', $fraction);
        $g = self::gcd((int)$n, (int)$d);
        return ((int)$n / $g) . '/' . ((int)$d / $g);
    }

    /**
     * Adds two fractions and returns the result in reduced form.
     *
     * @param string $f1 First fraction in "numerator/denominator" format
     * @param string $f2 Second fraction in "numerator/denominator" format
     * @return string Sum as a reduced fraction string
     */
    public static function addFractions(string $f1, string $f2): string {
        [$n1, $d1] = explode('/', $f1);
        [$n2, $d2] = explode('/', $f2);
        $cd = self::lcm((int)$d1, (int)$d2);
        $sum = ($n1 * $cd / $d1) + ($n2 * $cd / $d2);
        return self::reduceFraction("$sum/$cd");
    }

    /**
     * Subtracts the second fraction from the first and returns the result in reduced form.
     *
     * @param string $f1 First fraction (minuend) in "numerator/denominator" format
     * @param string $f2 Second fraction (subtrahend) in "numerator/denominator" format
     * @return string Difference as a reduced fraction string
     */
    public static function subtractFractions(string $f1, string $f2): string {
        [$n1, $d1] = explode('/', $f1);
        [$n2, $d2] = explode('/', $f2);
        $cd = self::lcm((int)$d1, (int)$d2);
        $diff = ($n1 * $cd / $d1) - ($n2 * $cd / $d2);
        return self::reduceFraction("$diff/$cd");
    }

    /**
     * Multiplies two fractions and returns the result in reduced form.
     *
     * @param string $f1 First fraction in "numerator/denominator" format
     * @param string $f2 Second fraction in "numerator/denominator" format
     * @return string Product as a reduced fraction string
     */
    public static function multiplyFractions(string $f1, string $f2): string {
        [$n1, $d1] = explode('/', $f1);
        [$n2, $d2] = explode('/', $f2);
        return self::reduceFraction(($n1 * $n2) . '/' . ($d1 * $d2));
    }

    /**
     * Divides the first fraction by the second and returns the result in reduced form.
     *
     * @param string $f1 Dividend fraction in "numerator/denominator" format
     * @param string $f2 Divisor fraction in "numerator/denominator" format
     * @return string Quotient as a reduced fraction string
     */
    public static function divideFractions(string $f1, string $f2): string {
        [$n1, $d1] = explode('/', $f1);
        [$n2, $d2] = explode('/', $f2);
        return self::reduceFraction(($n1 * $d2) . '/' . ($d1 * $n2));
    }

    /**
     * Converts a mixed number to an improper fraction.
     *
     * Handles strings in format "whole fraction" (e.g., "2 3/4") and converts
     * to improper fraction format (e.g., "11/4").
     *
     * @param string $mixed Mixed number in "whole numerator/denominator" format
     * @return string Improper fraction string
     */
    public static function mixedToImproper(string $mixed): string {
        if (str_contains($mixed, ' ')) {
            [$w, $f] = explode(' ', $mixed);
            [$n, $d] = explode('/', $f);
            return (((int)$w * (int)$d) + (int)$n) . '/' . $d;
        }
        return $mixed;
    }

    /**
     * Converts an improper fraction to a mixed number.
     *
     * @param string $improper Improper fraction in "numerator/denominator" format
     * @return string Mixed number string or whole number if no remainder
     */
    public static function improperToMixed(string $improper): string {
        [$n, $d] = explode('/', $improper);
        $w = intdiv((int)$n, (int)$d);
        $r = (int)$n % (int)$d;
        return $r === 0 ? (string)$w : "$w $r/$d";
    }

    /**
     * Finds the closest standard fraction to a given decimal value.
     *
     * Compares the decimal against a list of common fractions and returns
     * the one with the smallest absolute difference.
     *
     * @param float $decimal Decimal value to approximate
     * @param array $options Array of fraction strings to choose from
     * @return string Closest matching fraction from the options
     */
    public static function nearestStandardFraction(float $decimal, array $options = ['1/2','1/4','1/8','1/16']): string {
        return array_reduce($options,
            fn($carry, $opt) => abs($decimal - self::fractionToDecimal($opt)) < abs($decimal - self::fractionToDecimal($carry)) ? $opt : $carry
        );
    }

    /**
     * Formats a fraction string, converting improper fractions to whole numbers when appropriate.
     *
     * If the numerator is evenly divisible by the denominator, returns the whole number.
     * Otherwise, returns the fraction in reduced form.
     *
     * @param string $fraction Fraction in "numerator/denominator" format
     * @return string Formatted fraction or whole number string
     */
    public static function formatFraction(string $fraction): string {
        [$n, $d] = explode('/', $fraction);
        return ((int)$n % (int)$d === 0)
            ? (string)((int)$n / (int)$d)
            : self::reduceFraction($fraction);
    }

    /**
     * Validates whether a string represents a valid fraction format.
     *
     * Checks for proper fraction format with optional negative sign.
     * Pattern: optional minus, digits, slash, digits
     *
     * @param string $input String to validate as fraction
     * @return bool True if input matches valid fraction pattern
     */
    public static function isValidFraction(string $input): bool {
        return preg_match('/^-?\d+\/\d+$/', trim($input)) === 1;
    }

    /**
     * Parses mixed fraction input and converts to decimal.
     *
     * Handles both mixed fractions ("2 3/4") and simple fractions ("3/4").
     * Uses regex to identify mixed fraction pattern and calculate accordingly.
     *
     * @param string $input Mixed fraction or simple fraction string
     * @return float Decimal equivalent
     */
    public static function parseMixedFraction(string $input): float {
        $input = trim($input);
        return preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $input, $m)
            ? (int)$m[1] + ((int)$m[2] / (int)$m[3])
            : self::fractionToDecimal($input);
    }

    /**
     * Converts a fraction string to millimeters (assuming fraction represents inches).
     *
     * @param string $fraction Fraction representing inches
     * @return float Length in millimeters, rounded to 2 decimal places
     */
    public static function fractionToMillimeters(string $fraction): float {
        return round(self::inchesToMillimeters(self::fractionToDecimal($fraction)), 2);
    }

    /**
     * Calculates the Greatest Common Divisor of two integers using Euclidean algorithm.
     *
     * Recursive implementation that handles negative numbers by taking absolute values.
     *
     * @param int $a First integer
     * @param int $b Second integer
     * @return int Greatest common divisor
     */
    protected static function gcd(int $a, int $b): int {
        return $b === 0 ? abs($a) : self::gcd($b, $a % $b);
    }

    /**
     * Calculates the Least Common Multiple of two integers.
     *
     * Uses the relationship: LCM(a,b) = |a*b| / GCD(a,b)
     *
     * @param int $a First integer
     * @param int $b Second integer
     * @return int Least common multiple
     */
    protected static function lcm(int $a, int $b): int {
        return abs($a * $b) / self::gcd($a, $b);
    }
}