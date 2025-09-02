<?php
/**
 * File: /vendor/vernsix/primordyx/src/TimeHelper.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/TimeHelper.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Time;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Throwable;


/**
 * Comprehensive time and timezone utility with formatting, comparison, and conversion capabilities
 *
 * Static utility class providing extensive time manipulation, timezone conversion, duration
 * formatting, and relative time calculation functionality. Handles microsecond-precision
 * timestamps, ISO 8601 formatting, timezone comparisons, and human-readable time descriptions
 * for international applications and precise time tracking.
 *
 * ## Core Capabilities
 * - **ISO 8601 Formatting**: Precise timestamp formatting with microsecond support
 * - **Timezone Operations**: Conversion, comparison, and offset calculations
 * - **Duration Formatting**: Human-readable elapsed time and duration strings
 * - **Relative Time**: "Time ago" and "time until" descriptions
 * - **Localization**: Local timezone formatting and system integration
 * - **Precision Timing**: Microsecond-accurate timestamp handling
 *
 * ## Timestamp Precision
 * All methods support microsecond-precision floating-point timestamps from microtime(true)
 * and handle both integer Unix timestamps and fractional seconds for high-precision
 * timing applications like performance monitoring and API benchmarking.
 *
 * ## Timezone Support
 * Comprehensive timezone handling with:
 * - Daylight Saving Time (DST) detection
 * - UTC offset calculations in seconds and hours
 * - Regional timezone listing and validation
 * - Cross-timezone time comparison and conversion
 * - Local system timezone integration
 *
 * ## Error Handling Philosophy
 * Methods use graceful error handling returning null or "Invalid timestamp" strings
 * rather than throwing exceptions, making the class safe for use in logging and
 * display contexts where exceptions could disrupt application flow.
 *
 * ## Integration Points
 * - **Timer Class**: Provides timing utilities via TimeHelper::now() and elapsedDescription()
 * - **Token Class**: ISO formatting via TimeHelper::iso() for token expiration
 * - **Logging Systems**: Timestamp formatting for audit trails and debugging
 * - **API Responses**: Standardized time formatting for client consumption
 *
 * @since 1.0.0
 *
 * @example Basic Timestamp Formatting
 * ```php
 * // Current time in various formats
 * $now = TimeHelper::now(); // 1705334400.123456
 * $iso = TimeHelper::iso($now); // "2025-01-15T14:00:00.123Z"
 * $local = TimeHelper::toLocal($now); // "2025-01-15 9:00:00 AM"
 * $current = TimeHelper::nowIso(); // Current time as ISO string
 * ```
 *
 * @example Timezone Operations
 * ```php
 * // Compare timezones
 * $comparison = TimeHelper::compareTimezones('America/New_York', 'Europe/London');
 * $localToUTC = TimeHelper::compareLocalTo('UTC');
 *
 * // Timezone information
 * $nycInfo = TimeHelper::describeTimezone('America/New_York');
 * $isDST = TimeHelper::isDst('Europe/London');
 * $offset = TimeHelper::utcOffsetHours('Asia/Tokyo'); // 9.0
 * ```
 *
 * @example Duration and Relative Time
 * ```php
 * // Duration formatting
 * $duration = TimeHelper::secondsToDuration(3665); // "1h 1m 5s"
 * $elapsed = TimeHelper::elapsedDescription($startTime, $endTime); // "1.23 sec"
 *
 * // Relative time descriptions
 * $ago = TimeHelper::timeAgo(time() - 3600); // "1 hours ago"
 * $until = TimeHelper::timeUntil(time() + 1800); // "in 30 minutes"
 * $relative = TimeHelper::relative($timestamp); // "3 minutes ago" or "in 5 hours"
 * ```
 *
 * @example Advanced Usage
 * ```php
 * // Parse ISO dates
 * $timestamp = TimeHelper::parseIso('2025-01-15T14:30:00.000Z');
 *
 * // List timezones by region
 * $europeanZones = TimeHelper::listTimezones('EUROPE');
 * $allZones = TimeHelper::listTimezones();
 *
 * // Validation and utilities
 * $isValid = TimeHelper::isValidTimezone('America/New_York'); // true
 * $rounded = TimeHelper::roundToNearestSecond(1705334400.789); // 1705334401
 * ```
 *
 * @example Performance Monitoring Integration
 * ```php
 * class ApiTimer {
 *     private static $startTime;
 *
 *     public static function begin() {
 *         self::$startTime = TimeHelper::now();
 *     }
 *
 *     public static function end() {
 *         $elapsed = TimeHelper::elapsedDescription(self::$startTime);
 *         error_log("API call completed in: $elapsed");
 *         return $elapsed;
 *     }
 * }
 * ```
 */
class TimeHelper
{

    /**
     * Convert timestamp to ISO 8601 format with microsecond precision and timezone control
     *
     * Formats floating-point timestamps into standardized ISO 8601 strings with optional
     * UTC or local timezone representation. Handles microsecond precision and provides
     * consistent international timestamp formatting for APIs, logging, and data exchange.
     *
     * ## ISO 8601 Format Features
     * - **Microsecond precision**: Preserves fractional seconds up to microseconds
     * - **Timezone flexibility**: UTC (Z suffix) or local timezone (+/-offset)
     * - **Standards compliance**: Full ISO 8601 specification adherence
     * - **Error handling**: Returns "Invalid timestamp" for malformed input
     *
     * ## Output Format Examples
     * - **UTC**: "2025-01-15T14:30:00.123Z"
     * - **Local**: "2025-01-15T09:30:00.123-05:00"
     * - **Error**: "Invalid timestamp"
     *
     * ## Precision Handling
     * Extracts microseconds from floating-point timestamps and incorporates them
     * into the formatted string using DateTimeImmutable modification for accuracy.
     *
     * @param float $ts Unix timestamp with microsecond precision (e.g., microtime(true))
     * @param bool $utc Whether to format in UTC (true) or local timezone (false)
     * @return string ISO 8601 formatted timestamp or "Invalid timestamp" on error
     * @since 1.0.0
     *
     * @example Timestamp Formatting Scenarios
     * ```php
     * $now = microtime(true); // 1705334400.123456
     *
     * // UTC formatting (default)
     * $utc = TimeHelper::iso($now, true);  // "2025-01-15T14:00:00.123Z"
     * $utc = TimeHelper::iso($now);        // Same as above (UTC default)
     *
     * // Local timezone formatting
     * $local = TimeHelper::iso($now, false); // "2025-01-15T09:00:00.123-05:00"
     *
     * // Error handling
     * $invalid = TimeHelper::iso(-1);        // "Invalid timestamp"
     * $invalid = TimeHelper::iso(INF);       // "Invalid timestamp"
     * ```
     *
     * @example API Response Integration
     * ```php
     * function formatApiResponse($data, $timestamp) {
     *     return [
     *         'data' => $data,
     *         'timestamp' => TimeHelper::iso($timestamp, true),
     *         'server_time' => TimeHelper::iso(microtime(true), false)
     *     ];
     * }
     * ```
     *
     * @see timestamp() For alias to this method
     * @see nowIso() For current time ISO formatting
     * @see timestampToLocalString() For human-readable local formatting
     */
    public static function iso(float $ts, bool $utc = true): string
    {
        if (!is_finite($ts) || $ts <= 0) return 'Invalid timestamp';

        try {

            $tsInt = (int) floor($ts);
            $usec = (int) round(($ts - $tsInt) * 1_000_000);

            $dt = (new DateTimeImmutable('@' . $tsInt))
                ->setTimezone($utc ? new DateTimeZone('UTC') : new DateTimeZone(date_default_timezone_get()));

            $dt = $dt->modify("+$usec microseconds");

            $suffix = $utc ? 'Z' : $dt->format('P');

            return $dt->format('Y-m-d\TH:i:s.v') . $suffix;
        } catch (Throwable) {
            return 'Invalid timestamp';
        }
    }

    /**
     * Convert timestamp to human-readable local time string with optional date inclusion
     *
     * Formats timestamps into user-friendly local time representations using 12-hour
     * format with AM/PM indicators. Provides flexible date inclusion for different
     * display contexts and maintains microsecond precision in calculations.
     *
     * ## Output Formats
     * - **With date**: "2025-01-15 9:30:00 AM"
     * - **Time only**: "9:30:00 AM"
     * - **Error**: "Invalid timestamp"
     *
     * ## Localization Features
     * - Uses system default timezone via date_default_timezone_get()
     * - 12-hour format with AM/PM for user familiarity
     * - Microsecond precision preserved during conversion
     * - Graceful error handling for invalid timestamps
     *
     * ## Use Cases
     * - User interface time display
     * - Log file human-readable timestamps
     * - Dashboard and report time formatting
     * - Email and notification timestamps
     *
     * @param float $ts Unix timestamp with microsecond precision
     * @param bool $includeDate Whether to include date portion (default: true)
     * @return string Human-readable local time string or "Invalid timestamp" on error
     * @throws Exception If DateTimeImmutable operations fail
     * @since 1.0.0
     *
     * @example Local Time Display Scenarios
     * ```php
     * $timestamp = microtime(true);
     *
     * // Full date and time display
     * $full = TimeHelper::timestampToLocalString($timestamp);
     * echo $full; // "2025-01-15 9:30:00 AM"
     *
     * // Time-only display for same-day events
     * $timeOnly = TimeHelper::timestampToLocalString($timestamp, false);
     * echo $timeOnly; // "9:30:00 AM"
     *
     * // Error handling
     * $invalid = TimeHelper::timestampToLocalString(-1); // "Invalid timestamp"
     * ```
     *
     * @example User Interface Integration
     * ```php
     * function formatEventTime($eventTimestamp, $isToday = false) {
     *     return TimeHelper::timestampToLocalString($eventTimestamp, !$isToday);
     * }
     *
     * // Usage in templates
     * $eventTime = formatEventTime($event->timestamp, $event->isToday());
     * echo "Event starts at: $eventTime";
     * ```
     *
     * @see toLocal() For alias to this method
     * @see iso() For ISO 8601 formatting
     * @see elapsedDescription() For duration formatting
     */
    public static function timestampToLocalString(float $ts, bool $includeDate = true): string
    {
        if (!is_finite($ts) || $ts <= 0) return 'Invalid timestamp';

        $tsInt = (int) floor($ts);
        $usec = (int) round(($ts - $tsInt) * 1_000_000);

        $dt = (new DateTimeImmutable('@' . $tsInt))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $dt = $dt->modify("+$usec microseconds");

        return $dt->format($includeDate ? 'Y-m-d g:i:s A' : 'g:i:s A');
    }

    /**
     * Alias for iso() method providing convenient timestamp formatting
     *
     * Convenience method that delegates to iso() for consistent ISO 8601 timestamp
     * formatting. Maintains same functionality and parameters as iso() while providing
     * more intuitive method naming for timestamp formatting operations.
     *
     * @param float $ts Unix timestamp with microsecond precision
     * @param bool $utc Whether to format in UTC (true) or local timezone (false)
     * @return string ISO 8601 formatted timestamp or "Invalid timestamp" on error
     * @throws Exception If underlying iso() operations fail
     * @since 1.0.0
     *
     * @example Alias Usage
     * ```php
     * $now = microtime(true);
     *
     * // These are equivalent
     * $iso1 = TimeHelper::iso($now, true);
     * $iso2 = TimeHelper::timestamp($now, true);
     * // Both return: "2025-01-15T14:30:00.123Z"
     * ```
     *
     * @see iso() For complete method documentation and examples
     */
    public static function timestamp(float $ts, bool $utc = true): string
    {
        return self::iso($ts, $utc);
    }

    /**
     * Get current timestamp formatted as ISO 8601 string
     *
     * Convenience method that combines current time retrieval with ISO formatting,
     * providing immediate access to properly formatted current timestamps for
     * logging, API responses, and data recording.
     *
     * ## Current Time Sources
     * Uses microtime(true) internally to capture current time with microsecond
     * precision, then formats using iso() method for consistent output.
     *
     * @param bool $utc Whether to format in UTC (true) or local timezone (false)
     * @return string Current time as ISO 8601 formatted string
     * @throws Exception If timestamp formatting operations fail
     * @since 1.0.0
     *
     * @example Current Time Formatting
     * ```php
     * // Current time in UTC
     * $utcNow = TimeHelper::nowIso();        // "2025-01-15T14:30:00.123Z"
     * $utcNow = TimeHelper::nowIso(true);    // Same as above
     *
     * // Current time in local timezone
     * $localNow = TimeHelper::nowIso(false); // "2025-01-15T09:30:00.123-05:00"
     * ```
     *
     * @example Logging Integration
     * ```php
     * function logEvent($message, $level = 'INFO') {
     *     $timestamp = TimeHelper::nowIso();
     *     error_log("[$timestamp] [$level] $message");
     * }
     *
     * logEvent('User logged in successfully');
     * // Logs: [2025-01-15T14:30:00.123Z] [INFO] User logged in successfully
     * ```
     *
     * @see iso() For timestamp formatting with custom timestamps
     * @see now() For current timestamp as float
     */
    public static function nowIso(bool $utc = true): string
    {
        return self::iso(microtime(true), $utc);
    }

    /**
     * Alias for timestampToLocalString() providing convenient local time formatting
     *
     * Convenience method that delegates to timestampToLocalString() for human-readable
     * local time display. Maintains same functionality while providing intuitive
     * method naming for local time conversion operations.
     *
     * @param float $ts Unix timestamp with microsecond precision
     * @param bool $withDate Whether to include date portion (default: true)
     * @return string Human-readable local time string or "Invalid timestamp" on error
     * @throws Exception If underlying timestampToLocalString() operations fail
     * @since 1.0.0
     *
     * @example Alias Usage
     * ```php
     * $timestamp = microtime(true);
     *
     * // These are equivalent
     * $local1 = TimeHelper::timestampToLocalString($timestamp, true);
     * $local2 = TimeHelper::toLocal($timestamp, true);
     * // Both return: "2025-01-15 9:30:00 AM"
     * ```
     *
     * @see timestampToLocalString() For complete method documentation and examples
     */
    public static function toLocal(float $ts, bool $withDate = true): string
    {
        return self::timestampToLocalString($ts, $withDate);
    }

    /**
     * Round floating-point timestamp to nearest whole second
     *
     * Converts microsecond-precision timestamps to integer seconds using standard
     * rounding rules. Useful for reducing precision when exact microsecond timing
     * is not required or for compatibility with systems expecting integer timestamps.
     *
     * ## Rounding Behavior
     * - **0.4 seconds**: Rounds down to 0
     * - **0.5 seconds**: Rounds up to 1
     * - **0.6 seconds**: Rounds up to 1
     * - Uses PHP's round() function for consistent behavior
     *
     * @param float $ts Floating-point timestamp with microseconds
     * @return int Integer timestamp rounded to nearest second
     * @since 1.0.0
     *
     * @example Timestamp Rounding
     * ```php
     * $precise = microtime(true); // 1705334400.789
     * $rounded = TimeHelper::roundToNearestSecond($precise); // 1705334401
     *
     * // Multiple timestamps
     * $timestamps = [1705334400.2, 1705334400.5, 1705334400.8];
     * foreach ($timestamps as $ts) {
     *     echo TimeHelper::roundToNearestSecond($ts) . "\n";
     * }
     * // Output: 1705334400, 1705334401, 1705334401
     * ```
     *
     * @example Database Storage
     * ```php
     * function storeEvent($eventData) {
     *     $eventData['timestamp'] = TimeHelper::roundToNearestSecond(microtime(true));
     *     Database::insert('events', $eventData);
     * }
     * ```
     */
    public static function roundToNearestSecond(float $ts): int
    {
        return (int) round($ts);
    }

    /**
     * Format elapsed time between timestamps as human-readable duration string
     *
     * Calculates time difference and formats into intuitive duration descriptions
     * with appropriate units (milliseconds, seconds, minutes, hours). Automatically
     * selects most appropriate unit and precision for optimal readability.
     *
     * ## Format Selection Logic
     * - **< 1 second**: "123.45 ms" (milliseconds with precision)
     * - **< 1 minute**: "12.34 sec" (seconds with precision)
     * - **< 1 hour**: "5m 23.45s" (minutes and seconds)
     * - **≥ 1 hour**: "2h 15m" (hours and minutes only)
     *
     * ## Precision Control
     * Precision parameter controls decimal places in final unit display,
     * providing flexibility for different contexts from debugging (high precision)
     * to user display (low precision).
     *
     * @param float $start Start timestamp (usually from microtime(true))
     * @param float|null $end End timestamp (default: current time)
     * @param int $precision Decimal places for time display (default: 2)
     * @return string Human-readable elapsed time description
     * @since 1.0.0
     *
     * @example Performance Timing
     * ```php
     * $startTime = microtime(true);
     *
     * // ... perform some operation ...
     * usleep(1500000); // Sleep for 1.5 seconds
     *
     * $endTime = microtime(true);
     * $duration = TimeHelper::elapsedDescription($startTime, $endTime);
     * echo "Operation took: $duration"; // "Operation took: 1.50 sec"
     *
     * // Without end time (uses current time)
     * $duration2 = TimeHelper::elapsedDescription($startTime);
     * echo "Total elapsed: $duration2";
     * ```
     *
     * @example Different Precision Levels
     * ```php
     * $start = microtime(true);
     * usleep(123456); // ~0.123 seconds
     * $end = microtime(true);
     *
     * $high = TimeHelper::elapsedDescription($start, $end, 6); // "123.456000 ms"
     * $med = TimeHelper::elapsedDescription($start, $end, 2);  // "123.46 ms"
     * $low = TimeHelper::elapsedDescription($start, $end, 0);  // "123 ms"
     * ```
     *
     * @example Timer Integration
     * ```php
     * class OperationTimer {
     *     private $phases = [];
     *
     *     public function startPhase($name) {
     *         $this->phases[$name] = microtime(true);
     *     }
     *
     *     public function endPhase($name) {
     *         if (isset($this->phases[$name])) {
     *             $duration = TimeHelper::elapsedDescription($this->phases[$name]);
     *             echo "Phase '$name' took: $duration\n";
     *         }
     *     }
     * }
     * ```
     *
     * @see now() For current timestamp generation
     * @see secondsToDuration() For seconds-to-duration conversion
     */
    public static function elapsedDescription(float $start, ?float $end = null, int $precision = 2): string
    {
        $end = $end ?? microtime(true);
        $elapsed = $end - $start;

        if ($elapsed < 1) {
            return number_format($elapsed * 1000, $precision) . ' ms';
        } elseif ($elapsed < 60) {
            return number_format($elapsed, $precision) . ' sec';
        } elseif ($elapsed < 3600) {
            $mins = floor($elapsed / 60);
            $secs = fmod($elapsed, 60);
            return "{$mins}m " . number_format($secs, $precision) . "s";
        } else {
            $hours = floor($elapsed / 3600);
            $mins = floor(fmod($elapsed, 3600) / 60);
            return "{$hours}h {$mins}m";
        }
    }

    /**
     * Check if timezone is currently observing Daylight Saving Time
     *
     * Determines DST status for specified timezone by examining current date
     * and timezone rules. Essential for applications dealing with time-sensitive
     * operations across regions with different DST observance patterns.
     *
     * ## DST Detection Method
     * Uses DateTime::format('I') which returns 1 during DST periods and 0 otherwise.
     * Checks current system time against timezone's DST rules.
     *
     * ## Return Values
     * - **true**: Timezone currently observing DST
     * - **false**: Timezone not observing DST (standard time)
     * - **null**: Invalid timezone or detection failure
     *
     * @param string $tz Valid timezone identifier (e.g., 'America/New_York')
     * @return bool|null DST status or null if timezone invalid
     * @since 1.0.0
     *
     * @example DST Detection
     * ```php
     * // Check various timezones for DST
     * $zones = ['America/New_York', 'Europe/London', 'Asia/Tokyo', 'UTC'];
     *
     * foreach ($zones as $zone) {
     *     $dst = TimeHelper::isDst($zone);
     *     $status = $dst === true ? 'DST' : ($dst === false ? 'Standard' : 'Unknown');
     *     echo "$zone: $status\n";
     * }
     * ```
     *
     * @example Conditional Time Display
     * ```php
     * function displayTimeWithDST($timezone) {
     *     $info = TimeHelper::describeTimezone($timezone);
     *     $dstStatus = TimeHelper::isDst($timezone) ? ' (DST)' : ' (Standard)';
     *
     *     return $info['local_time'] . $dstStatus;
     * }
     *
     * echo displayTimeWithDST('America/New_York'); // "2025-01-15 14:30:00 (Standard)"
     * ```
     *
     * @see currentOffset() For current UTC offset calculation
     * @see describeTimezone() For comprehensive timezone information
     */
    public static function isDst(string $tz): ?bool
    {
        try {
            $zone = new DateTimeZone($tz);
            $now = new DateTime('now', $zone);
            return (bool) $now->format('I');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get current UTC offset for timezone in seconds
     *
     * Calculates the current UTC offset for specified timezone, accounting for
     * Daylight Saving Time and regional time variations. Returns offset in seconds
     * for precise time calculations and conversions.
     *
     * ## Offset Calculation
     * - **Positive values**: Timezone ahead of UTC (e.g., +3600 for UTC+1)
     * - **Negative values**: Timezone behind UTC (e.g., -18000 for UTC-5)
     * - **Zero**: UTC timezone
     * - **null**: Invalid timezone
     *
     * ## DST Awareness
     * Automatically accounts for Daylight Saving Time when calculating offset,
     * providing accurate offset for current date and time.
     *
     * @param string $tz Valid timezone identifier
     * @return int|null UTC offset in seconds, or null if timezone invalid
     * @since 1.0.0
     *
     * @example Offset Calculations
     * ```php
     * // Get offsets for various timezones
     * $offsets = [
     *     'UTC' => TimeHelper::currentOffset('UTC'),                    // 0
     *     'EST' => TimeHelper::currentOffset('America/New_York'),       // -18000 (winter)
     *     'GMT' => TimeHelper::currentOffset('Europe/London'),         // 0 (winter)
     *     'JST' => TimeHelper::currentOffset('Asia/Tokyo'),            // 32400
     * ];
     *
     * foreach ($offsets as $zone => $offset) {
     *     $hours = $offset / 3600;
     *     echo "$zone: UTC" . ($hours >= 0 ? '+' : '') . $hours . "\n";
     * }
     * ```
     *
     * @example Time Conversion
     * ```php
     * function convertToTimezone($utcTimestamp, $targetTimezone) {
     *     $offset = TimeHelper::currentOffset($targetTimezone);
     *     if ($offset !== null) {
     *         return $utcTimestamp + $offset;
     *     }
     *     return $utcTimestamp; // Return original if conversion fails
     * }
     * ```
     *
     * @see utcOffsetHours() For offset in hours
     * @see timezoneOffset() For offset between two timezones
     * @see isDst() For DST status detection
     */
    public static function currentOffset(string $tz): ?int
    {
        try {
            $zone = new DateTimeZone($tz);
            $now = new DateTime('now', $zone);
            return $zone->getOffset($now);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Describe how long ago a timestamp occurred relative to reference point
     *
     * Generates human-readable "time ago" descriptions for past timestamps relative
     * to current time or specified reference point. Automatically selects appropriate
     * time unit for optimal readability and user understanding.
     *
     * ## Time Unit Selection
     * - **< 1 second**: "just now"
     * - **< 1 minute**: "X seconds ago"
     * - **< 1 hour**: "X minutes ago"
     * - **< 1 day**: "X hours ago"
     * - **≥ 1 day**: "X days ago"
     *
     * ## Reference Point Flexibility
     * Allows custom reference timestamp for "ago" calculations, enabling relative
     * time descriptions from any point in time rather than just current moment.
     *
     * @param float $ts Timestamp to describe (must be in the past)
     * @param float|null $reference Reference point (default: current time)
     * @return string Human-readable "time ago" description
     * @since 1.0.0
     *
     * @example Past Time Descriptions
     * ```php
     * $now = time();
     *
     * // Various past timestamps
     * echo TimeHelper::timeAgo($now - 30);    // "30 seconds ago"
     * echo TimeHelper::timeAgo($now - 300);   // "5 minutes ago"
     * echo TimeHelper::timeAgo($now - 3600);  // "1 hours ago"
     * echo TimeHelper::timeAgo($now - 86400); // "1 days ago"
     * echo TimeHelper::timeAgo($now - 1);     // "just now"
     * ```
     *
     * @example Custom Reference Point
     * ```php
     * $eventTime = strtotime('2025-01-15 12:00:00');
     * $referenceTime = strtotime('2025-01-15 14:30:00');
     *
     * $description = TimeHelper::timeAgo($eventTime, $referenceTime);
     * echo $description; // "2 hours ago" (from reference point)
     * ```
     *
     * @example Social Media Timeline
     * ```php
     * function formatPostTime($postTimestamp) {
     *     return TimeHelper::timeAgo($postTimestamp);
     * }
     *
     * foreach ($posts as $post) {
     *     echo $post->content . " - " . formatPostTime($post->created_at) . "\n";
     * }
     * ```
     *
     * @see timeUntil() For future time descriptions
     * @see relative() For automatic past/future detection
     */
    public static function timeAgo(float $ts, ?float $reference = null): string
    {
        $reference = $reference ?? microtime(true);
        $delta = $reference - $ts;

        if ($delta < 1) return 'just now';
        elseif ($delta < 60) return floor($delta) . ' seconds ago';
        elseif ($delta < 3600) return floor($delta / 60) . ' minutes ago';
        elseif ($delta < 86400) return floor($delta / 3600) . ' hours ago';
        else return floor($delta / 86400) . ' days ago';
    }

    /**
     * Describe how far into the future a timestamp is from reference point
     *
     * Generates human-readable "time until" descriptions for future timestamps
     * relative to current time or specified reference point. Complements timeAgo()
     * for comprehensive relative time description capabilities.
     *
     * ## Time Unit Selection
     * - **< 1 second**: "any moment now"
     * - **< 1 minute**: "in X seconds"
     * - **< 1 hour**: "in X minutes"
     * - **< 1 day**: "in X hours"
     * - **≥ 1 day**: "in X days"
     *
     * ## Future Time Applications
     * Ideal for countdowns, scheduled events, expiration times, and deadline
     * notifications where users need intuitive future time understanding.
     *
     * @param float $ts Future timestamp to describe
     * @param float|null $reference Reference point (default: current time)
     * @return string Human-readable "time until" description
     * @since 1.0.0
     *
     * @example Future Time Descriptions
     * ```php
     * $now = time();
     *
     * // Various future timestamps
     * echo TimeHelper::timeUntil($now + 1);     // "any moment now"
     * echo TimeHelper::timeUntil($now + 30);    // "in 30 seconds"
     * echo TimeHelper::timeUntil($now + 300);   // "in 5 minutes"
     * echo TimeHelper::timeUntil($now + 3600);  // "in 1 hours"
     * echo TimeHelper::timeUntil($now + 86400); // "in 1 days"
     * ```
     *
     * @example Event Countdown
     * ```php
     * function getEventCountdown($eventTimestamp) {
     *     if ($eventTimestamp > time()) {
     *         return "Event starts " . TimeHelper::timeUntil($eventTimestamp);
     *     } else {
     *         return "Event started " . TimeHelper::timeAgo($eventTimestamp);
     *     }
     * }
     *
     * $eventTime = strtotime('+2 hours');
     * echo getEventCountdown($eventTime); // "Event starts in 2 hours"
     * ```
     *
     * @example Token Expiration Display
     * ```php
     * class TokenManager {
     *     public static function getExpirationDescription($token) {
     *         $expiry = $token->getExpirationTime();
     *
     *         if ($expiry > time()) {
     *             return "Token expires " . TimeHelper::timeUntil($expiry);
     *         } else {
     *             return "Token expired " . TimeHelper::timeAgo($expiry);
     *         }
     *     }
     * }
     * ```
     *
     * @see timeAgo() For past time descriptions
     * @see relative() For automatic past/future detection
     */
    public static function timeUntil(float $ts, ?float $reference = null): string
    {
        $reference = $reference ?? microtime(true);
        $delta = $ts - $reference;

        if ($delta < 1) return 'any moment now';
        elseif ($delta < 60) return 'in ' . floor($delta) . ' seconds';
        elseif ($delta < 3600) return 'in ' . floor($delta / 60) . ' minutes';
        elseif ($delta < 86400) return 'in ' . floor($delta / 3600) . ' hours';
        else return 'in ' . floor($delta / 86400) . ' days';
    }

    /**
     * Generate relative time description automatically detecting past or future
     *
     * Intelligent relative time formatter that automatically determines whether
     * timestamp is in past or future and applies appropriate description format.
     * Combines timeAgo() and timeUntil() functionality with "now" detection for
     * comprehensive relative time handling.
     *
     * ## Automatic Direction Detection
     * - **Past timestamps**: Uses timeAgo() format ("X ago")
     * - **Future timestamps**: Uses timeUntil() format ("in X")
     * - **Current time**: Returns "now" for timestamps within 0.5 seconds
     *
     * ## Precision Threshold
     * Timestamps within 0.5 seconds of reference point are considered "now"
     * to handle minor timing variations and provide stable user experience.
     *
     * @param float $ts Timestamp to describe (past, present, or future)
     * @param float|null $reference Reference point (default: current time)
     * @return string Automatic relative time description
     * @since 1.0.0
     *
     * @example Automatic Relative Time
     * ```php
     * $now = time();
     *
     * // Automatic detection
     * echo TimeHelper::relative($now - 3600); // "1 hours ago"
     * echo TimeHelper::relative($now + 1800); // "in 30 minutes"
     * echo TimeHelper::relative($now);        // "now"
     * echo TimeHelper::relative($now + 0.3);  // "now" (within threshold)
     * ```
     *
     * @example Universal Timeline Display
     * ```php
     * function formatTimelineEvent($eventTimestamp) {
     *     return TimeHelper::relative($eventTimestamp);
     * }
     *
     * $events = [
     *     ['name' => 'Meeting', 'time' => time() + 3600],
     *     ['name' => 'Launch', 'time' => time() - 1800],
     *     ['name' => 'Current', 'time' => time()],
     * ];
     *
     * foreach ($events as $event) {
     *     $when = formatTimelineEvent($event['time']);
     *     echo "{$event['name']}: $when\n";
     * }
     * // Output:
     * // Meeting: in 1 hours
     * // Launch: 30 minutes ago
     * // Current: now
     * ```
     *
     * @example Smart Activity Feed
     * ```php
     * class ActivityFeed {
     *     public static function formatActivity($activity) {
     *         $timeDesc = TimeHelper::relative($activity->timestamp);
     *         return "{$activity->user} {$activity->action} $timeDesc";
     *     }
     * }
     *
     * // Usage
     * echo ActivityFeed::formatActivity($activity);
     * // "john posted a comment 5 minutes ago"
     * // "jane scheduled a meeting in 2 hours"
     * ```
     *
     * @see timeAgo() For past-only descriptions
     * @see timeUntil() For future-only descriptions
     */
    public static function relative(float $ts, ?float $reference = null): string
    {
        $reference = $reference ?? microtime(true);
        $delta = $ts - $reference;

        if (abs($delta) < 0.5) return 'now';

        return $delta < 0
            ? self::timeAgo($ts, $reference)
            : self::timeUntil($ts, $reference);
    }

    /**
     * Convert seconds into human-readable duration string with appropriate units
     *
     * Transforms numeric seconds into intuitive duration descriptions using hours,
     * minutes, and seconds notation. Automatically selects relevant units and
     * ensures at least one unit is always displayed for clarity.
     *
     * ## Duration Format Logic
     * - **Hours**: Included if ≥ 3600 seconds (1 hour)
     * - **Minutes**: Included if ≥ 60 seconds (1 minute)
     * - **Seconds**: Always included unless zero and other units present
     * - **Zero duration**: Shows "0s" rather than empty string
     *
     * ## Output Examples
     * - **3665 seconds**: "1h 1m 5s"
     * - **125 seconds**: "2m 5s"
     * - **45 seconds**: "45s"
     * - **0 seconds**: "0s"
     *
     * @param float|int $seconds Duration in seconds (fractional seconds truncated)
     * @return string Human-readable duration string
     * @since 1.0.0
     *
     * @example Duration Formatting
     * ```php
     * // Various duration lengths
     * echo TimeHelper::secondsToDuration(3665);  // "1h 1m 5s"
     * echo TimeHelper::secondsToDuration(125);   // "2m 5s"
     * echo TimeHelper::secondsToDuration(45);    // "45s"
     * echo TimeHelper::secondsToDuration(0);     // "0s"
     * echo TimeHelper::secondsToDuration(7200);  // "2h"
     * echo TimeHelper::secondsToDuration(3600);  // "1h"
     * ```
     *
     * @example Process Timing Display
     * ```php
     * function displayProcessTime($startTime) {
     *     $elapsed = time() - $startTime;
     *     $duration = TimeHelper::secondsToDuration($elapsed);
     *     echo "Process running for: $duration\n";
     * }
     *
     * $processStart = time() - 3725; // Started ~1 hour ago
     * displayProcessTime($processStart); // "Process running for: 1h 2m 5s"
     * ```
     *
     * @example Video Duration Formatting
     * ```php
     * class VideoMetadata {
     *     public static function formatDuration($durationSeconds) {
     *         return TimeHelper::secondsToDuration($durationSeconds);
     *     }
     * }
     *
     * $videos = [
     *     ['title' => 'Tutorial', 'duration' => 1845],
     *     ['title' => 'Quick Tip', 'duration' => 90],
     * ];
     *
     * foreach ($videos as $video) {
     *     $duration = VideoMetadata::formatDuration($video['duration']);
     *     echo "{$video['title']}: $duration\n";
     * }
     * // Output:
     * // Tutorial: 30m 45s
     * // Quick Tip: 1m 30s
     * ```
     *
     * @see elapsedDescription() For elapsed time formatting with precision
     */
    public static function secondsToDuration(float|int $seconds): string
    {
        $seconds = (int) $seconds;
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        $out = [];
        if ($h) $out[] = "{$h}h";
        if ($m) $out[] = "{$m}m";
        if ($s || empty($out)) $out[] = "{$s}s";

        return implode(' ', $out);
    }

    /**
     * Parse ISO 8601 date string and convert to floating-point timestamp
     *
     * Converts ISO 8601 formatted date strings back into Unix timestamps with
     * microsecond precision. Handles various ISO 8601 formats including timezone
     * indicators and provides robust parsing for API data and stored timestamps.
     *
     * ## Supported ISO Formats
     * - **Basic**: "2025-01-15T14:30:00"
     * - **With timezone**: "2025-01-15T14:30:00Z"
     * - **With offset**: "2025-01-15T14:30:00+05:00"
     * - **With microseconds**: "2025-01-15T14:30:00.123456Z"
     *
     * ## Error Handling
     * Returns null for invalid date strings rather than throwing exceptions,
     * making it safe for use in validation and data processing contexts.
     *
     * @param string $iso ISO 8601 formatted date string
     * @return float|null Unix timestamp with microseconds, or null if parsing fails
     * @since 1.0.0
     *
     * @example ISO String Parsing
     * ```php
     * // Various ISO format parsing
     * $timestamps = [
     *     TimeHelper::parseIso('2025-01-15T14:30:00Z'),        // UTC
     *     TimeHelper::parseIso('2025-01-15T09:30:00-05:00'),   // EST offset
     *     TimeHelper::parseIso('2025-01-15T14:30:00.123Z'),    // With microseconds
     *     TimeHelper::parseIso('invalid-date'),                 // null (error)
     * ];
     *
     * foreach ($timestamps as $ts) {
     *     echo $ts !== null ? date('Y-m-d H:i:s', $ts) : 'Invalid';
     *     echo "\n";
     * }
     * ```
     *
     * @example API Data Processing
     * ```php
     * function processApiTimestamp($isoString) {
     *     $timestamp = TimeHelper::parseIso($isoString);
     *
     *     if ($timestamp !== null) {
     *         return [
     *             'unix_timestamp' => $timestamp,
     *             'local_display' => TimeHelper::toLocal($timestamp),
     *             'relative' => TimeHelper::relative($timestamp)
     *         ];
     *     }
     *
     *     return ['error' => 'Invalid timestamp format'];
     * }
     *
     * $result = processApiTimestamp('2025-01-15T14:30:00Z');
     * ```
     *
     * @example Validation and Conversion
     * ```php
     * class DateValidator {
     *     public static function validateAndConvert($dateString) {
     *         $timestamp = TimeHelper::parseIso($dateString);
     *
     *         if ($timestamp === null) {
     *             throw new InvalidArgumentException('Invalid ISO date format');
     *         }
     *
     *         return $timestamp;
     *     }
     * }
     * ```
     *
     * @see iso() For formatting timestamps to ISO strings
     * @see nowIso() For current time as ISO string
     */
    public static function parseIso(string $iso): ?float
    {
        try {
            $dt = new DateTime($iso);
            return (float) $dt->format('U.u');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get current timestamp with microsecond precision
     *
     * Returns current Unix timestamp as floating-point number including microseconds
     * for high-precision timing operations. Wrapper around microtime(true) providing
     * consistent interface for current time access throughout TimeHelper methods.
     *
     * ## Precision Details
     * - **Integer part**: Unix timestamp in seconds since epoch
     * - **Fractional part**: Microseconds (6 decimal places)
     * - **Example**: 1705334400.123456 represents precise moment in time
     *
     * ## Use Cases
     * - Performance timing and benchmarking
     * - High-precision timestamp generation
     * - Timer implementations
     * - Duration calculations requiring microsecond accuracy
     *
     * @return float Current Unix timestamp with microsecond precision
     * @since 1.0.0
     *
     * @example High-Precision Timing
     * ```php
     * $start = TimeHelper::now();
     *
     * // Perform some operation
     * for ($i = 0; $i < 1000; $i++) {
     *     hash('sha256', "test$i");
     * }
     *
     * $end = TimeHelper::now();
     * $duration = $end - $start;
     *
     * echo "Operation took: " . number_format($duration * 1000, 2) . " ms\n";
     * ```
     *
     * @example Timestamp Comparison
     * ```php
     * $timestamp1 = TimeHelper::now();
     * usleep(100000); // Sleep 0.1 seconds
     * $timestamp2 = TimeHelper::now();
     *
     * $difference = $timestamp2 - $timestamp1;
     * echo "Time difference: " . number_format($difference, 6) . " seconds\n";
     * // Output: "Time difference: 0.100123 seconds"
     * ```
     *
     * @example Timer Class Integration
     * ```php
     * class SimpleTimer {
     *     private $startTime;
     *
     *     public function start() {
     *         $this->startTime = TimeHelper::now();
     *     }
     *
     *     public function elapsed() {
     *         return TimeHelper::now() - $this->startTime;
     *     }
     *
     *     public function stop() {
     *         $elapsed = $this->elapsed();
     *         echo "Timer: " . TimeHelper::elapsedDescription($this->startTime) . "\n";
     *         return $elapsed;
     *     }
     * }
     * ```
     *
     * @see elapsedDescription() For formatting elapsed time
     * @see iso() For formatting timestamps
     */
    public static function now(): float
    {
        return microtime(true);
    }

    /**
     * Calculate hour difference between two timezones at specific point in time
     *
     * Computes timezone offset difference in hours, accounting for Daylight Saving
     * Time rules at the specified date and time. Essential for accurate cross-timezone
     * calculations and scheduling applications.
     *
     * ## Offset Calculation
     * - **Positive result**: Target timezone ahead of source timezone
     * - **Negative result**: Target timezone behind source timezone
     * - **Zero result**: Timezones have same offset
     * - **DST awareness**: Automatically accounts for DST rules
     *
     * ## Temporal Accuracy
     * Uses specific DateTime for calculation, ensuring accurate results even
     * when DST transition dates differ between timezones or when calculating
     * historical or future offsets.
     *
     * @param string $fromTz Source timezone identifier
     * @param string $toTz Target timezone identifier
     * @param DateTimeInterface|null $at Specific date/time for calculation (default: now)
     * @return int Hour difference between timezones
     * @throws Exception If timezone identifiers are invalid
     * @since 1.0.0
     *
     * @example Timezone Offset Calculations
     * ```php
     * // Current time offset calculations
     * $offset1 = TimeHelper::timezoneOffset('UTC', 'America/New_York');
     * echo "NYC is UTC$offset1\n"; // "NYC is UTC-5" (winter) or "UTC-4" (summer)
     *
     * $offset2 = TimeHelper::timezoneOffset('Europe/London', 'Asia/Tokyo');
     * echo "Tokyo is $offset2 hours ahead of London\n";
     * ```
     *
     * @example Historical Offset Calculation
     * ```php
     * // Calculate offset for specific historical date
     * $historicalDate = new DateTime('2024-07-15'); // Summer date
     * $winterOffset = TimeHelper::timezoneOffset('UTC', 'Europe/London');
     * $summerOffset = TimeHelper::timezoneOffset('UTC', 'Europe/London', $historicalDate);
     *
     * echo "London winter offset: UTC$winterOffset\n"; // UTC+0
     * echo "London summer offset: UTC$summerOffset\n"; // UTC+1 (BST)
     * ```
     *
     * @example Meeting Scheduler
     * ```php
     * function scheduleMeeting($meetingTimeUTC, $participantTimezones) {
     *     $schedule = [];
     *
     *     foreach ($participantTimezones as $name => $timezone) {
     *         $offset = TimeHelper::timezoneOffset('UTC', $timezone);
     *         $localTime = $meetingTimeUTC + ($offset * 3600);
     *         $schedule[$name] = date('Y-m-d H:i:s', $localTime) . " ($timezone)";
     *     }
     *
     *     return $schedule;
     * }
     *
     * $utcMeeting = strtotime('2025-01-15 14:00:00 UTC');
     * $participants = [
     *     'Alice' => 'America/New_York',
     *     'Bob' => 'Europe/London',
     *     'Charlie' => 'Asia/Tokyo'
     * ];
     *
     * $schedule = scheduleMeeting($utcMeeting, $participants);
     * ```
     *
     * @see currentOffset() For single timezone UTC offset
     * @see compareTimezones() For comprehensive timezone comparison
     */
    public static function timezoneOffset(string $fromTz, string $toTz, ?DateTimeInterface $at = null): int
    {
        $at = $at ?? new DateTime('now');

        $from = new DateTimeZone($fromTz);
        $to   = new DateTimeZone($toTz);

        $fromOffset = $from->getOffset($at);
        $toOffset   = $to->getOffset($at);

        return (int)(($toOffset - $fromOffset) / 3600);
    }

    /**
     * Get list of timezone identifiers with optional regional filtering
     *
     * Returns array of valid timezone identifiers from PHP's timezone database,
     * with optional filtering by geographic region. Essential for timezone selection
     * interfaces and validation systems.
     *
     * ## Regional Filtering Options
     * - **'AFRICA'**: African timezones (Africa/Cairo, etc.)
     * - **'AMERICA'**: American timezones (America/New_York, etc.)
     * - **'ASIA'**: Asian timezones (Asia/Tokyo, etc.)
     * - **'EUROPE'**: European timezones (Europe/London, etc.)
     * - **'AUSTRALIA'**: Australian timezones
     * - **'PACIFIC'**: Pacific region timezones
     * - **'UTC'**: UTC and related timezones
     * - **null or 'ALL'**: All available timezones
     *
     * ## Case Insensitivity
     * Region parameter is case-insensitive ('europe', 'EUROPE', 'Europe' all work).
     *
     * @param string|null $region Optional region filter (case-insensitive)
     * @return array<string> Array of timezone identifier strings
     * @since 1.0.0
     *
     * @example Regional Timezone Listing
     * ```php
     * // Get all European timezones
     * $europeanZones = TimeHelper::listTimezones('EUROPE');
     * echo "European timezones: " . count($europeanZones) . "\n";
     * foreach (array_slice($europeanZones, 0, 5) as $zone) {
     *     echo "- $zone\n";
     * }
     * // Output includes: Europe/London, Europe/Paris, Europe/Berlin, etc.
     *
     * // Get all timezones
     * $allZones = TimeHelper::listTimezones();
     * echo "Total timezones: " . count($allZones) . "\n";
     * ```
     *
     * @example Timezone Selection Interface
     * ```php
     * function buildTimezoneSelectOptions($region = null) {
     *     $timezones = TimeHelper::listTimezones($region);
     *     $options = [];
     *
     *     foreach ($timezones as $timezone) {
     *         $info = TimeHelper::describeTimezone($timezone);
     *         $label = $timezone . ' (UTC' .
     *                 ($info['utc_offset'] >= 0 ? '+' : '') .
     *                 $info['utc_offset'] . ')';
     *         $options[$timezone] = $label;
     *     }
     *
     *     return $options;
     * }
     *
     * // Build dropdown for American timezones
     * $americanOptions = buildTimezoneSelectOptions('AMERICA');
     * ```
     *
     * @example Timezone Validation
     * ```php
     * function validateTimezone($timezone, $allowedRegion = null) {
     *     $validTimezones = TimeHelper::listTimezones($allowedRegion);
     *     return in_array($timezone, $validTimezones, true);
     * }
     *
     * // Validate user-provided timezone
     * $userTimezone = $_POST['timezone'] ?? '';
     * if (validateTimezone($userTimezone)) {
     *     echo "Valid timezone: $userTimezone";
     * } else {
     *     echo "Invalid timezone provided";
     * }
     * ```
     *
     * @see isValidTimezone() For single timezone validation
     * @see describeTimezone() For timezone information
     */
    public static function listTimezones(?string $region = null): array
    {
        $regions = [
            'AFRICA' => DateTimeZone::AFRICA,
            'AMERICA' => DateTimeZone::AMERICA,
            'ANTARCTICA' => DateTimeZone::ANTARCTICA,
            'ASIA' => DateTimeZone::ASIA,
            'ATLANTIC' => DateTimeZone::ATLANTIC,
            'AUSTRALIA' => DateTimeZone::AUSTRALIA,
            'EUROPE' => DateTimeZone::EUROPE,
            'INDIAN' => DateTimeZone::INDIAN,
            'PACIFIC' => DateTimeZone::PACIFIC,
            'UTC' => DateTimeZone::UTC,
            'ALL' => DateTimeZone::ALL,
        ];

        if ($region && isset($regions[strtoupper($region)])) {
            return DateTimeZone::listIdentifiers($regions[strtoupper($region)]);
        }

        return DateTimeZone::listIdentifiers();
    }

    /**
     * Get comprehensive information about specific timezone
     *
     * Returns detailed timezone metadata including current local time, UTC offset,
     * DST status, and timezone abbreviation. Provides complete timezone context
     * for display and calculation purposes.
     *
     * ## Returned Information Structure
     * - **name**: Timezone identifier (e.g., 'America/New_York')
     * - **local_time**: Current time in timezone (Y-m-d H:i:s format)
     * - **utc_offset**: Hours offset from UTC (float, can be negative)
     * - **is_dst**: Boolean indicating current DST status
     * - **abbreviation**: Timezone abbreviation (e.g., 'EST', 'PST')
     *
     * ## Error Handling
     * Returns null for invalid timezone identifiers rather than throwing
     * exceptions, enabling safe use in validation and display contexts.
     *
     * @param string $tz Valid timezone identifier
     * @return array|null Associative array of timezone information, or null if invalid
     * @since 1.0.0
     *
     * @example Timezone Information Display
     * ```php
     * $zones = ['America/New_York', 'Europe/London', 'Asia/Tokyo'];
     *
     * foreach ($zones as $zone) {
     *     $info = TimeHelper::describeTimezone($zone);
     *     if ($info) {
     *         echo "Timezone: {$info['name']}\n";
     *         echo "Local Time: {$info['local_time']}\n";
     *         echo "UTC Offset: " . ($info['utc_offset'] >= 0 ? '+' : '') . $info['utc_offset'] . "\n";
     *         echo "DST Active: " . ($info['is_dst'] ? 'Yes' : 'No') . "\n";
     *         echo "Abbreviation: {$info['abbreviation']}\n";
     *         echo "---\n";
     *     }
     * }
     * ```
     *
     * @example World Clock Implementation
     * ```php
     * class WorldClock {
     *     private static $watchedTimezones = [
     *         'New York' => 'America/New_York',
     *         'London' => 'Europe/London',
     *         'Tokyo' => 'Asia/Tokyo',
     *         'Sydney' => 'Australia/Sydney'
     *     ];
     *
     *     public static function displayAll() {
     *         foreach (self::$watchedTimezones as $city => $timezone) {
     *             $info = TimeHelper::describeTimezone($timezone);
     *             if ($info) {
     *                 $dst = $info['is_dst'] ? ' (DST)' : '';
     *                 echo "$city: {$info['local_time']} {$info['abbreviation']}$dst\n";
     *             }
     *         }
     *     }
     * }
     *
     * WorldClock::displayAll();
     * // New York: 2025-01-15 09:30:00 EST
     * // London: 2025-01-15 14:30:00 GMT
     * // Tokyo: 2025-01-15 23:30:00 JST
     * // Sydney: 2025-01-16 01:30:00 AEDT (DST)
     * ```
     *
     * @example Timezone Selection with Details
     * ```php
     * function getTimezoneOptionsWithDetails($region = null) {
     *     $timezones = TimeHelper::listTimezones($region);
     *     $options = [];
     *
     *     foreach ($timezones as $tz) {
     *         $info = TimeHelper::describeTimezone($tz);
     *         if ($info) {
     *             $dst = $info['is_dst'] ? ' DST' : '';
     *             $label = "{$info['name']} (UTC{$info['utc_offset']} {$info['abbreviation']}$dst)";
     *             $options[$tz] = $label;
     *         }
     *     }
     *
     *     return $options;
     * }
     * ```
     *
     * @see isDst() For DST status only
     * @see currentOffset() For offset only
     * @see compareTimezones() For timezone comparison
     */
    public static function describeTimezone(string $tz): ?array
    {
        try {
            $zone = new DateTimeZone($tz);
            $now = new DateTime('now', $zone);

            return [
                'name'        => $tz,
                'local_time'  => $now->format('Y-m-d H:i:s'),
                'utc_offset'  => $zone->getOffset($now) / 3600,
                'is_dst'      => (bool) $now->format('I'),
                'abbreviation'=> $now->format('T'),
            ];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Compare two timezones and return comprehensive difference analysis
     *
     * Provides detailed comparison between two timezones including local times,
     * UTC offsets, abbreviations, and hour difference. Essential for scheduling
     * applications and cross-timezone coordination.
     *
     * ## Returned Comparison Structure
     * - **from**: Source timezone information (timezone, local_time, utc_offset, abbreviation)
     * - **to**: Target timezone information (same structure as 'from')
     * - **difference_hours**: Numeric hour difference (positive = target ahead)
     *
     * ## Difference Calculation
     * - **Positive difference**: Target timezone ahead of source
     * - **Negative difference**: Target timezone behind source
     * - **Zero difference**: Timezones currently have same offset
     *
     * @param string $fromTz Source timezone identifier
     * @param string $toTz Target timezone identifier
     * @return array|null Comprehensive timezone comparison, or null if either timezone invalid
     * @since 1.0.0
     *
     * @example Timezone Comparison
     * ```php
     * $comparison = TimeHelper::compareTimezones('America/New_York', 'Asia/Tokyo');
     *
     * if ($comparison) {
     *     echo "From: {$comparison['from']['timezone']}\n";
     *     echo "Local time: {$comparison['from']['local_time']} ({$comparison['from']['abbreviation']})\n";
     *     echo "\n";
     *     echo "To: {$comparison['to']['timezone']}\n";
     *     echo "Local time: {$comparison['to']['local_time']} ({$comparison['to']['abbreviation']})\n";
     *     echo "\n";
     *     echo "Time difference: {$comparison['difference_hours']} hours\n";
     * }
     *
     * // Output:
     * // From: America/New_York
     * // Local time: 2025-01-15 09:00:00 (EST)
     * // To: Asia/Tokyo
     * // Local time: 2025-01-15 23:00:00 (JST)
     * // Time difference: 14 hours
     * ```
     *
     * @example Meeting Time Calculator
     * ```php
     * function findBestMeetingTime($participantTimezones, $preferredHour = 14) {
     *     $baseTimezone = reset($participantTimezones);
     *     $analysis = [];
     *
     *     foreach ($participantTimezones as $participant => $timezone) {
     *         $comparison = TimeHelper::compareTimezones($baseTimezone, $timezone);
     *         if ($comparison) {
     *             $localHour = $preferredHour + $comparison['difference_hours'];
     *             $analysis[$participant] = [
     *                 'timezone' => $timezone,
     *                 'local_hour' => $localHour,
     *                 'suitable' => ($localHour >= 9 && $localHour <= 17)
     *             ];
     *         }
     *     }
     *
     *     return $analysis;
     * }
     *
     * $participants = [
     *     'Alice' => 'America/New_York',
     *     'Bob' => 'Europe/London',
     *     'Charlie' => 'Asia/Singapore'
     * ];
     *
     * $analysis = findBestMeetingTime($participants, 14);
     * ```
     *
     * @example Travel Planning
     * ```php
     * function planTravelTimes($departureTimezone, $arrivalTimezone, $flightDuration) {
     *     $comparison = TimeHelper::compareTimezones($departureTimezone, $arrivalTimezone);
     *
     *     if ($comparison) {
     *         $timeDiff = $comparison['difference_hours'];
     *
     *         return [
     *             'departure_tz' => $departureTimezone,
     *             'arrival_tz' => $arrivalTimezone,
     *             'time_difference' => $timeDiff,
     *             'jet_lag_hours' => abs($timeDiff),
     *             'flight_duration_hours' => $flightDuration,
     *             'total_travel_impact' => $flightDuration + abs($timeDiff)
     *         ];
     *     }
     *
     *     return null;
     * }
     * ```
     *
     * @see compareLocalTo() For comparing local timezone to target
     * @see formatCompare() For human-readable comparison formatting
     * @see printCompare() For direct comparison output
     */
    public static function compareTimezones(string $fromTz, string $toTz): ?array
    {
        try {
            $now = new DateTime('now');
            $fromZone = new DateTimeZone($fromTz);
            $toZone = new DateTimeZone($toTz);

            $fromTime = clone $now;
            $toTime = clone $now;

            $fromTime->setTimezone($fromZone);
            $toTime->setTimezone($toZone);

            $fromOffset = $fromZone->getOffset($now);
            $toOffset = $toZone->getOffset($now);

            return [
                'from' => [
                    'timezone'     => $fromTz,
                    'local_time'   => $fromTime->format('Y-m-d H:i:s'),
                    'utc_offset'   => $fromOffset / 3600,
                    'abbreviation' => $fromTime->format('T'),
                ],
                'to' => [
                    'timezone'     => $toTz,
                    'local_time'   => $toTime->format('Y-m-d H:i:s'),
                    'utc_offset'   => $toOffset / 3600,
                    'abbreviation' => $toTime->format('T'),
                ],
                'difference_hours' => ($toOffset - $fromOffset) / 3600
            ];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Compare local system timezone to target timezone
     *
     * Convenience method that compares the system's default timezone (from
     * date_default_timezone_get()) to specified target timezone. Simplifies
     * common use case of comparing user's local time to specific timezone.
     *
     * ## Local Timezone Source
     * Uses PHP's date_default_timezone_get() to determine local timezone,
     * which can be set via date_default_timezone_set() or php.ini configuration.
     *
     * @param string $targetTz Target timezone to compare against local
     * @return array|null Timezone comparison data, or null if target timezone invalid
     * @since 1.0.0
     *
     * @example Local to Target Comparison
     * ```php
     * // Assuming local timezone is America/New_York
     * $comparison = TimeHelper::compareLocalTo('Europe/London');
     *
     * if ($comparison) {
     *     echo "Your local time: {$comparison['from']['local_time']}\n";
     *     echo "London time: {$comparison['to']['local_time']}\n";
     *     echo "Difference: {$comparison['difference_hours']} hours\n";
     * }
     * ```
     *
     * @example User Timezone Display
     * ```php
     * function showUserTimezone($targetTimezone) {
     *     $comparison = TimeHelper::compareLocalTo($targetTimezone);
     *
     *     if ($comparison) {
     *         $diff = $comparison['difference_hours'];
     *         $direction = $diff >= 0 ? 'ahead of' : 'behind';
     *         $hours = abs($diff);
     *
     *         return "Your local time is $hours hours $direction $targetTimezone";
     *     }
     *
     *     return "Unable to compare with $targetTimezone";
     * }
     *
     * echo showUserTimezone('UTC');
     * // "Your local time is 5 hours behind UTC"
     * ```
     *
     * @see compareTimezones() For comparing any two timezones
     * @see localTimezone() For getting local timezone identifier
     */
    public static function compareLocalTo(string $targetTz): ?array
    {
        $localTz = date_default_timezone_get();
        return self::compareTimezones($localTz, $targetTz);
    }

    /**
     * Get local to target timezone comparison as formatted JSON string
     *
     * Combines compareLocalTo() functionality with JSON formatting for API
     * responses, configuration files, or debugging output. Provides pretty-printed
     * JSON for improved readability.
     *
     * @param string $targetTz Target timezone to compare against local
     * @return string Pretty-printed JSON comparison data
     * @since 1.0.0
     *
     * @example JSON Comparison Output
     * ```php
     * $json = TimeHelper::compareLocalToJson('Asia/Tokyo');
     * echo $json;
     *
     * // Output:
     * // {
     * //     "from": {
     * //         "timezone": "America/New_York",
     * //         "local_time": "2025-01-15 09:00:00",
     * //         "utc_offset": -5,
     * //         "abbreviation": "EST"
     * //     },
     * //     "to": {
     * //         "timezone": "Asia/Tokyo",
     * //         "local_time": "2025-01-15 23:00:00",
     * //         "utc_offset": 9,
     * //         "abbreviation": "JST"
     * //     },
     * //     "difference_hours": 14
     * // }
     * ```
     *
     * @example API Response Integration
     * ```php
     * function getTimezoneComparison($targetTimezone) {
     *     $json = TimeHelper::compareLocalToJson($targetTimezone);
     *
     *     header('Content-Type: application/json');
     *     echo $json;
     * }
     * ```
     *
     * @see compareLocalTo() For array format comparison
     * @see formatCompare() For human-readable string format
     */
    public static function compareLocalToJson(string $targetTz): string
    {
        return json_encode(self::compareLocalTo($targetTz), JSON_PRETTY_PRINT);
    }

    /**
     * Format timezone comparison data into human-readable display string
     *
     * Transforms timezone comparison array into formatted multi-line string with
     * visual separators and clear information hierarchy. Ideal for console output,
     * reports, or debugging displays.
     *
     * ## Format Structure
     * - Header with timezone names and arrow indicator
     * - Separator line for visual clarity
     * - From timezone: time, abbreviation, UTC offset
     * - To timezone: time, abbreviation, UTC offset
     * - Separator line
     * - Summary with hour difference
     *
     * ## Error Handling
     * Returns "Invalid comparison data." for malformed or missing comparison data.
     *
     * @param array $data Timezone comparison data from compareTimezones()
     * @return string Formatted multi-line comparison display
     * @since 1.0.0
     *
     * @example Formatted Comparison Display
     * ```php
     * $comparison = TimeHelper::compareTimezones('America/New_York', 'Europe/London');
     * $formatted = TimeHelper::formatCompare($comparison);
     * echo $formatted;
     *
     * // Output:
     * // Comparing America/New_York → Europe/London
     * // -------------------------------------
     * // From: 2025-01-15 09:00:00 (EST, UTC-5)
     * // To:   2025-01-15 14:00:00 (GMT, UTC+0)
     * // -------------------------------------
     * // Time difference: 5 hours
     * ```
     *
     * @example Report Generation
     * ```php
     * function generateTimezoneReport($timezones) {
     *     $report = "Timezone Comparison Report\n";
     *     $report .= str_repeat('=', 50) . "\n\n";
     *
     *     $baseTimezone = array_shift($timezones);
     *
     *     foreach ($timezones as $timezone) {
     *         $comparison = TimeHelper::compareTimezones($baseTimezone, $timezone);
     *         $report .= TimeHelper::formatCompare($comparison) . "\n\n";
     *     }
     *
     *     return $report;
     * }
     * ```
     *
     * @see compareTimezones() For generating comparison data
     * @see printCompare() For direct output formatting
     */
    public static function formatCompare(array $data): string
    {
        if (!$data || !isset($data['from'], $data['to'], $data['difference_hours'])) {
            return "Invalid comparison data.";
        }

        return sprintf(
            "Comparing %s → %s" .
            "-------------------------------------" .
            "From: %s (%s, UTC%s)" .
            "To:   %s (%s, UTC%s)" .
            "-------------------------------------" .
            "Time difference: %s hours",
            $data['from']['timezone'],
            $data['to']['timezone'],
            $data['from']['local_time'],
            $data['from']['abbreviation'],
            $data['from']['utc_offset'],
            $data['to']['local_time'],
            $data['to']['abbreviation'],
            $data['to']['utc_offset'],
            $data['difference_hours']
        );
    }

    /**
     * Output formatted timezone comparison directly to console
     *
     * Convenience method that combines compareTimezones() and formatCompare()
     * to provide immediate formatted output for debugging and console applications.
     * Eliminates need for intermediate variables in simple display scenarios.
     *
     * @param string $fromTz Source timezone identifier
     * @param string $toTz Target timezone identifier
     * @return void Outputs formatted comparison directly via echo
     * @since 1.0.0
     *
     * @example Direct Console Output
     * ```php
     * // Direct output for debugging
     * TimeHelper::printCompare('America/Los_Angeles', 'Asia/Tokyo');
     *
     * // Output:
     * // Comparing America/Los_Angeles → Asia/Tokyo
     * // -------------------------------------
     * // From: 2025-01-15 06:00:00 (PST, UTC-8)
     * // To:   2025-01-15 23:00:00 (JST, UTC+9)
     * // -------------------------------------
     * // Time difference: 17 hours
     * ```
     *
     * @example Quick Timezone Debugging
     * ```php
     * // Quick debugging in console applications
     * echo "Meeting timezone analysis:\n";
     * TimeHelper::printCompare('America/New_York', 'Europe/Berlin');
     * echo "\n";
     * TimeHelper::printCompare('Europe/Berlin', 'Asia/Singapore');
     * ```
     *
     * @see compareTimezones() For getting comparison data
     * @see formatCompare() For formatting without direct output
     */
    public static function printCompare(string $fromTz, string $toTz): void
    {
        $data = self::compareTimezones($fromTz, $toTz);
        echo self::formatCompare($data);
    }



    /**
     * Validate timezone identifier against PHP's timezone database
     *
     * Checks if provided string is valid timezone identifier recognized by PHP's
     * DateTimeZone class. Essential for user input validation and configuration
     * verification before timezone operations.
     *
     * ## Validation Method
     * Uses DateTimeZone::listIdentifiers() to get authoritative list of valid
     * timezone identifiers and performs exact string matching.
     *
     * @param string $tz Timezone identifier to validate
     * @return bool True if timezone is valid, false otherwise
     * @since 1.0.0
     *
     * @example Timezone Validation
     * ```php
     * $timezones = [
     *     'America/New_York',    // Valid
     *     'Europe/London',       // Valid
     *     'Invalid/Timezone',    // Invalid
     *     'UTC',                 // Valid
     *     'GMT',                 // Valid
     *     'America/Invalid'      // Invalid
     * ];
     *
     * foreach ($timezones as $tz) {
     *     $valid = TimeHelper::isValidTimezone($tz) ? 'Valid' : 'Invalid';
     *     echo "$tz: $valid\n";
     * }
     * ```
     *
     * @example User Input Validation
     * ```php
     * function validateUserTimezone($userInput) {
     *     if (empty($userInput)) {
     *         return ['valid' => false, 'error' => 'Timezone cannot be empty'];
     *     }
     *
     *     if (TimeHelper::isValidTimezone($userInput)) {
     *         return ['valid' => true, 'timezone' => $userInput];
     *     }
     *
     *     return ['valid' => false, 'error' => 'Invalid timezone identifier'];
     * }
     *
     * $result = validateUserTimezone($_POST['timezone'] ?? '');
     * if ($result['valid']) {
     *     // Proceed with valid timezone
     *     $info = TimeHelper::describeTimezone($result['timezone']);
     * } else {
     *     echo "Error: " . $result['error'];
     * }
     * ```
     *
     * @example Configuration Validation
     * ```php
     * class AppConfig {
     *     public static function setTimezone($timezone) {
     *         if (TimeHelper::isValidTimezone($timezone)) {
     *             date_default_timezone_set($timezone);
     *             return true;
     *         }
     *
     *         throw new InvalidArgumentException("Invalid timezone: $timezone");
     *     }
     * }
     * ```
     *
     * @see listTimezones() For getting list of valid timezones
     * @see describeTimezone() For timezone information
     */
    public static function isValidTimezone(string $tz): bool
    {
        return in_array($tz, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Get system's default timezone identifier
     *
     * Returns the currently configured default timezone for the PHP system.
     * Wrapper around date_default_timezone_get() providing consistent interface
     * for accessing system timezone configuration.
     *
     * ## Timezone Source
     * Returns timezone set by:
     * 1. date_default_timezone_set() function calls
     * 2. date.timezone php.ini directive
     * 3. System timezone detection (if available)
     * 4. UTC fallback (if no timezone configured)
     *
     * @return string Current system default timezone identifier
     * @since 1.0.0
     *
     * @example System Timezone Display
     * ```php
     * $systemTz = TimeHelper::localTimezone();
     * echo "System timezone: $systemTz\n";
     *
     * // Display current local time
     * $localTime = TimeHelper::toLocal(TimeHelper::now());
     * echo "Local time: $localTime ($systemTz)\n";
     * ```
     *
     * @example Application Configuration
     * ```php
     * function initializeApplication() {
     *     $configTz = Config::get('app.timezone', 'UTC');
     *     $currentTz = TimeHelper::localTimezone();
     *
     *     if ($currentTz !== $configTz) {
     *         echo "Setting timezone from $currentTz to $configTz\n";
     *         date_default_timezone_set($configTz);
     *     }
     * }
     * ```
     *
     * @example User Timezone Comparison
     * ```php
     * function compareWithUserTimezone($userTimezone) {
     *     $systemTz = TimeHelper::localTimezone();
     *     return TimeHelper::compareTimezones($systemTz, $userTimezone);
     * }
     * ```
     *
     * @see compareLocalTo() For comparing local timezone to others
     * @see describeTimezone() For local timezone details
     */
    public static function localTimezone(): string
    {
        return date_default_timezone_get();
    }

    /**
     * Get timezone's current UTC offset in hours as floating-point number
     *
     * Converts timezone's UTC offset from seconds to hours for more intuitive
     * display and calculation. Handles fractional hour offsets and DST transitions
     * automatically.
     *
     * ## Offset Representation
     * - **Positive values**: Timezone ahead of UTC (e.g., 9.0 for UTC+9)
     * - **Negative values**: Timezone behind UTC (e.g., -5.0 for UTC-5)
     * - **Fractional values**: Timezones with 30/45 minute offsets (e.g., 5.5 for UTC+5:30)
     * - **null**: Invalid timezone identifier
     *
     * @param string $tz Valid timezone identifier
     * @return float|null UTC offset in hours, or null if timezone invalid
     * @since 1.0.0
     *
     * @example Hour Offset Display
     * ```php
     * $timezones = [
     *     'UTC',
     *     'America/New_York',
     *     'Asia/Tokyo',
     *     'Asia/Kolkata',      // UTC+5:30
     *     'Australia/Adelaide'  // UTC+9:30 (with DST variations)
     * ];
     *
     * foreach ($timezones as $tz) {
     *     $offset = TimeHelper::utcOffsetHours($tz);
     *     if ($offset !== null) {
     *         $sign = $offset >= 0 ? '+' : '';
     *         echo "$tz: UTC$sign$offset\n";
     *     }
     * }
     *
     * // Output:
     * // UTC: UTC+0
     * // America/New_York: UTC-5 (winter) or UTC-4 (summer)
     * // Asia/Tokyo: UTC+9
     * // Asia/Kolkata: UTC+5.5
     * // Australia/Adelaide: UTC+9.5 or UTC+10.5 (DST)
     * ```
     *
     * @example Offset-Based Time Calculation
     * ```php
     * function convertUtcToTimezone($utcTimestamp, $targetTimezone) {
     *     $offsetHours = TimeHelper::utcOffsetHours($targetTimezone);
     *     if ($offsetHours !== null) {
     *         return $utcTimestamp + ($offsetHours * 3600);
     *     }
     *     return $utcTimestamp; // Return unchanged if conversion fails
     * }
     *
     * $utcTime = time();
     * $tokyoTime = convertUtcToTimezone($utcTime, 'Asia/Tokyo');
     * ```
     *
     * @example Display Formatting
     * ```php
     * function formatTimezoneOffset($timezone) {
     *     $offset = TimeHelper::utcOffsetHours($timezone);
     *
     *     if ($offset === null) {
     *         return 'Invalid timezone';
     *     }
     *
     *     if ($offset == 0) {
     *         return 'UTC';
     *     }
     *
     *     $sign = $offset > 0 ? '+' : '';
     *     $hours = floor(abs($offset));
     *     $minutes = (abs($offset) - $hours) * 60;
     *
     *     if ($minutes == 0) {
     *         return "UTC$sign" . ($offset > 0 ? $hours : -$hours);
     *     } else {
     *         return "UTC$sign" . ($offset > 0 ? $hours : -$hours) . ':' . sprintf('%02d', $minutes);
     *     }
     * }
     *
     * echo formatTimezoneOffset('Asia/Kolkata'); // "UTC+5:30"
     * ```
     *
     * @see currentOffset() For offset in seconds
     * @see timezoneOffset() For difference between two timezones
     * @see describeTimezone() For comprehensive timezone information
     */
    public static function utcOffsetHours(string $tz): ?float
    {
        $offset = self::currentOffset($tz);
        return is_int($offset) ? $offset / 3600 : null;
    }

}
