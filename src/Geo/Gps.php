<?php
/**
 * File: /vendor/vernsix/primordyx/src/Gps.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Geo/Gps.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Geo;

use InvalidArgumentException;
use RuntimeException;

/**
 * GPS coordinate utility class with geospatial operations
 *
 * Provides GPS coordinate manipulation, conversion, and calculation methods for
 * geospatial applications. Supports decimal degrees and DMS coordinate formats,
 * distance calculations using Haversine formula, bearing computations, coordinate
 * projections, and export formats including GeoJSON and GPX.
 *
 * ## Core Features
 * - Coordinate format conversion (decimal degrees, DMS)
 * - Great circle distance calculations (miles, km, nautical miles)
 * - Bearing calculations with compass directions
 * - Coordinate projection from distance/bearing vectors
 * - Geofencing operations (point-in-radius checking)
 * - GeoJSON export (points, circles, feature collections)
 * - GPX file import/export (waypoints, tracks, routes)
 *
 * ## Method Design Pattern
 * **Static Methods** are used for:
 * - Factory methods that create GPS instances (fromDecimal, fromDMS, fromGpxFile)
 * - Pure utility functions that don't need coordinate state (dmsToDecimal, decimalToDMS)
 * - Operations on collections of coordinates (exportTrack, exportRoute)
 *
 * **Instance Methods** are used for:
 * - Accessing specific coordinate data (getLatitude, getLongitude, asArray)
 * - Operations between two coordinate instances (distanceTo, bearingTo, isWithin)
 * - Formatting/exporting individual coordinate data (toGeoJson, toGpx, __toString)
 * - Creating new coordinates from current instance (project)
 *
 * This design allows both stateless utility operations and stateful coordinate
 * manipulations, providing flexibility for different usage patterns.
 *
 * ## Usage Examples
 * ```php
 * $temple = Gps::fromDecimal(31.052948, -97.099264);
 * $austin = Gps::fromDecimal(30.267200, -97.743100);
 *
 * $distance = $temple->distanceTo($austin, 'mi'); // ~65.4 miles
 * $bearing = $temple->bearingTo($austin); // ~195.8 degrees
 * $projected = $temple->project(100, 45, 'mi'); // 100 miles NE
 * ```
 *
 * @package Primordyx
 * @since 1.0.0
 */
class Gps
{
    /**
     * Latitude coordinate in decimal degrees
     *
     * North-south position as decimal degrees. Positive values = northern hemisphere,
     * negative values = southern hemisphere. Valid range: -90.0 to +90.0 degrees.
     *
     * @var float Decimal degrees latitude (-90.0 to +90.0)
     * @since 1.0.0
     */
    protected float $latitude;

    /**
     * Longitude coordinate in decimal degrees
     *
     * East-west position as decimal degrees. Positive values = eastern hemisphere,
     * negative values = western hemisphere. Valid range: -180.0 to +180.0 degrees.
     *
     * @var float Decimal degrees longitude (-180.0 to +180.0)
     * @since 1.0.0
     */
    protected float $longitude;

    /**
     * Create GPS coordinate instance from decimal degree values
     *
     * Initializes GPS coordinate with latitude and longitude in decimal degree format.
     * Uses standard geographic coordinate system (WGS84).
     *
     * @param float $latitude Latitude in decimal degrees (-90.0 to +90.0)
     * @param float $longitude Longitude in decimal degrees (-180.0 to +180.0)
     * @since 1.0.0
     */
    public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Static factory method to create GPS instance from decimal coordinates
     *
     * Provides fluent interface for creating GPS instances. Functionally equivalent
     * to constructor but offers better readability in method chaining.
     *
     * @param float $latitude Latitude in decimal degrees (-90.0 to +90.0)
     * @param float $longitude Longitude in decimal degrees (-180.0 to +180.0)
     * @return self New GPS coordinate instance
     * @since 1.0.0
     */
    public static function fromDecimal(float $latitude, float $longitude): self
    {
        return new self($latitude, $longitude);
    }

    /**
     * Create GPS instance from Degrees/Minutes/Seconds coordinate strings
     *
     * Parses DMS coordinate strings and converts to decimal degrees. Supports
     * multiple formats: "31°03'10.6\"N", "31:03:10.6N", "31 03 10.6 N".
     *
     * @param string $latStr Latitude DMS string (e.g., "31°03'10.6\"N")
     * @param string $lonStr Longitude DMS string (e.g., "97°05'57.4\"W")
     * @return self New GPS coordinate instance
     * @throws InvalidArgumentException When DMS strings cannot be parsed
     * @since 1.0.0
     */
    public static function fromDMS(string $latStr, string $lonStr): self
    {
        $lat = self::dmsToDecimal($latStr);
        $lon = self::dmsToDecimal($lonStr);
        return new self($lat, $lon);
    }

    /**
     * Convert DMS coordinate string to decimal degree value
     *
     * Parses Degrees/Minutes/Seconds strings into decimal degrees using regex pattern
     * matching. Handles multiple separator styles and cardinal directions.
     *
     * Formula: Decimal = Degrees + (Minutes/60) + (Seconds/3600)
     * Applied sign based on cardinal direction (S/W = negative).
     *
     * @param string $dms DMS coordinate string (e.g., "31°03'10.6\"N")
     * @return float Decimal degree value with appropriate sign
     * @throws InvalidArgumentException When DMS string format is invalid
     * @since 1.0.0
     */
    public static function dmsToDecimal(string $dms): float
    {
        $pattern = '/(\d+)[°:\s]+(\d+)[\'\s]+([\d.]+)"?\s*([NSEW])/i';

        if (!preg_match($pattern, $dms, $matches)) {
            throw new InvalidArgumentException("Invalid DMS format: $dms");
        }

        [$_, $deg, $min, $sec, $dir] = $matches;

        $deg = (float) $deg;
        $min = (float) $min;
        $sec = (float) $sec;

        $decimal = $deg + ($min / 60) + ($sec / 3600);

        $dir = strtoupper($dir);
        if ($dir === 'S' || $dir === 'W') {
            $decimal *= -1;
        }

        return $decimal;
    }

    /**
     * Convert decimal degrees to DMS format string
     *
     * Converts decimal degree coordinate to Degrees/Minutes/Seconds string format
     * with appropriate cardinal direction. Handles latitude and longitude coordinate types.
     *
     * Formula breakdown: Extract degrees, calculate minutes and seconds from remainder.
     * Cardinal directions: lat = N/S, lon = E/W based on positive/negative values.
     *
     * @param float $decimal Decimal degree coordinate value
     * @param string $type Coordinate type ('lat' for latitude, 'lon' for longitude)
     * @return string DMS formatted string (e.g., "31°03'10.60\"N")
     * @throws InvalidArgumentException When type is not 'lat' or 'lon'
     * @since 1.0.0
     */
    public static function decimalToDMS(float $decimal, string $type): string
    {
        $isNegative = $decimal < 0;
        $decimal = abs($decimal);

        $deg = (int) $decimal;
        $minFloat = ($decimal - $deg) * 60;
        $min = (int) $minFloat;
        $sec = ($minFloat - $min) * 60;

        $dir = match (strtolower($type)) {
            'lat' => $isNegative ? 'S' : 'N',
            'lon' => $isNegative ? 'W' : 'E',
            default => throw new InvalidArgumentException("Invalid type: $type"),
        };

        return sprintf("%02d°%02d'%05.2f\"%s", $deg, $min, $sec, $dir);
    }

    /**
     * Get the latitude coordinate in decimal degrees
     *
     * Returns north-south coordinate. Positive = northern hemisphere,
     * negative = southern hemisphere.
     *
     * @return float Latitude in decimal degrees (-90.0 to +90.0)
     * @since 1.0.0
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * Get the longitude coordinate in decimal degrees
     *
     * Returns east-west coordinate. Positive = eastern hemisphere,
     * negative = western hemisphere.
     *
     * @return float Longitude in decimal degrees (-180.0 to +180.0)
     * @since 1.0.0
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * Get latitude coordinate in DMS format string
     *
     * Returns latitude as Degrees/Minutes/Seconds string with cardinal direction.
     * Uses decimalToDMS() internally with 'lat' type parameter.
     *
     * @return string Latitude in DMS format (e.g., "31°03'10.60\"N")
     * @since 1.0.0
     */
    public function getLatitudeDMS(): string
    {
        return self::decimalToDMS($this->latitude, 'lat');
    }

    /**
     * Get longitude coordinate in DMS format string
     *
     * Returns longitude as Degrees/Minutes/Seconds string with cardinal direction.
     * Uses decimalToDMS() internally with 'lon' type parameter.
     *
     * @return string Longitude in DMS format (e.g., "97°05'57.40\"W")
     * @since 1.0.0
     */
    public function getLongitudeDMS(): string
    {
        return self::decimalToDMS($this->longitude, 'lon');
    }

    /**
     * Return coordinate data as array with decimal and DMS formats
     *
     * Provides comprehensive coordinate data in both decimal degrees and DMS formats.
     * Useful for API responses, data export, and display purposes.
     *
     * @return array{latitude: float, longitude: float, latitude_dms: string, longitude_dms: string} Complete coordinate data
     * @since 1.0.0
     */
    public function asArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'latitude_dms' => $this->getLatitudeDMS(),
            'longitude_dms' => $this->getLongitudeDMS(),
        ];
    }

    /**
     * Calculate great circle distance to another GPS coordinate
     *
     * Uses Haversine formula to compute shortest distance between two points on
     * Earth's surface. Supports miles, kilometers, and nautical miles.
     *
     * Earth radius constants: 3958.8 mi, 6371.0 km, 3440.1 nmi
     * Accuracy: ±0.5% for most terrestrial distances.
     *
     * @param Gps $other Target GPS coordinate for distance calculation
     * @param string $unit Distance unit ('mi', 'km', or 'nmi')
     * @return float Distance between coordinates in specified unit
     * @since 1.0.0
     */
    public function distanceTo(Gps $other, string $unit = 'mi'): float
    {
        $earthRadius = match (strtolower($unit)) {
            'km', 'kilometers' => 6371.0,
            'nmi', 'nautical' => 3440.1,
            default => 3958.8, // miles
        };

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($other->latitude);
        $lonTo = deg2rad($other->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Calculate initial bearing to another coordinate with optional compass direction
     *
     * Computes initial compass bearing using spherical trigonometry. Can return
     * numeric bearing in degrees or compass direction string.
     *
     * Bearing reference: 0°=North, 90°=East, 180°=South, 270°=West
     *
     * @param Gps $other Target GPS coordinate for bearing calculation
     * @param bool $asDirection If true, returns compass string (e.g., 'NW')
     * @return float|string Degrees (0.0-360.0) or compass direction string
     * @since 1.0.0
     */
    public function bearingTo(Gps $other, bool $asDirection = false): float|string
    {
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $deltaLon = deg2rad($other->longitude - $this->longitude);

        $y = sin($deltaLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLon);

        $bearing = atan2($y, $x);
        $bearingDeg = (rad2deg($bearing) + 360) % 360;

        if ($asDirection) {
            return self::bearingToCompass($bearingDeg);
        }

        return $bearingDeg;
    }

    /**
     * Convert numeric degree bearing to compass direction string
     *
     * Maps bearing in degrees to 16-point compass rose direction. Used internally
     * by bearingTo() when compass direction string is requested.
     *
     * Direction mapping: 0°=N, 22.5°=NNE, 45°=NE, etc.
     *
     * @param float $degrees Bearing in degrees (0.0-360.0)
     * @return string Compass point abbreviation (N, NNE, NE, ENE, etc.)
     * @since 1.0.0
     */
    protected static function bearingToCompass(float $degrees): string
    {
        $directions = [
            'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
            'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'
        ];
        $index = (int) round($degrees / 22.5) % 16;
        return $directions[$index];
    }

    /**
     * Convert GPS coordinate to string representation
     *
     * Returns coordinates as "latitude, longitude" format for debugging,
     * logging, and display purposes.
     *
     * @return string Formatted coordinate string "latitude, longitude"
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return "{$this->latitude}, {$this->longitude}";
    }

    /**
     * Project new coordinate from current position using distance and bearing
     *
     * Calculates new GPS coordinate by projecting specified distance in given
     * direction using spherical trigonometry. Accounts for Earth's curvature.
     *
     * @param float $distance Distance to project in specified unit
     * @param float $bearing Direction in degrees (0° = North, clockwise)
     * @param string $unit Distance unit ('mi', 'km', or 'nmi')
     * @return Gps New GPS coordinate at projected location
     * @since 1.0.0
     */
    public function project(float $distance, float $bearing, string $unit = 'mi'): Gps
    {
        $radius = match (strtolower($unit)) {
            'km', 'kilometers' => 6371.0,
            'nmi', 'nautical' => 3440.1,
            default => 3958.8, // miles
        };

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $bearingRad = deg2rad($bearing);
        $distanceRatio = $distance / $radius;

        $lat2 = asin(sin($lat1) * cos($distanceRatio) +
            cos($lat1) * sin($distanceRatio) * cos($bearingRad));

        $lon2 = $lon1 + atan2(
                sin($bearingRad) * sin($distanceRatio) * cos($lat1),
                cos($distanceRatio) - sin($lat1) * sin($lat2)
            );

        return new Gps(rad2deg($lat2), rad2deg($lon2));
    }

    /**
     * Check if this coordinate is within specified distance from center point
     *
     * Performs geofencing validation by calculating distance from center and
     * comparing against radius. Uses same Haversine calculation as distanceTo().
     *
     * @param float $distance Maximum distance (radius) for inclusion
     * @param Gps $center Center point for distance measurement
     * @param string $unit Distance unit ('mi', 'km', or 'nmi')
     * @return bool True if this coordinate is within the specified radius
     * @since 1.0.0
     */
    public function isWithin(float $distance, Gps $center, string $unit = 'mi'): bool
    {
        return $center->distanceTo($this, $unit) <= $distance;
    }

    /**
     * Convert GPS coordinate to GeoJSON Point geometry or Feature
     *
     * Exports GPS coordinate as GeoJSON-compliant structure. Supports Point
     * geometry or full Feature object. Uses [longitude, latitude] coordinate order.
     *
     * @param bool $asFeature If true, returns Feature object; if false, returns Point geometry
     * @return array GeoJSON-compliant array structure
     * @since 1.0.0
     */
    public function toGeoJson(bool $asFeature = false): array
    {
        $point = [
            'type' => 'Point',
            'coordinates' => [$this->longitude, $this->latitude]
        ];

        if ($asFeature) {
            return [
                'type' => 'Feature',
                'geometry' => $point,
                'properties' => [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude
                ]
            ];
        }

        return $point;
    }


    /**
     *
     * // Sample points
     * $points = [
     * Gps::fromDecimal(31.052948, -97.099264),   // Temple, TX
     * Gps::fromDecimal(32.7767, -96.7970),       // Dallas, TX
     * Gps::fromDecimal(29.7604, -95.3698),       // Houston, TX
     * ];
     *
     * // Convert each to a GeoJSON feature
     * $features = [];
     * foreach ($points as $point) {
     * $features[] = $point->toGeoJson(true);
     * }
     *
     * // Build the full GeoJSON FeatureCollection
     * $geojson = [
     * 'type' => 'FeatureCollection',
     * 'features' => $features
     * ];
     *
     * // Save to a .geojson file
     * file_put_contents('points.geojson', json_encode($geojson, JSON_PRETTY_PRINT));
     *
     * echo "GeoJSON file created: points.geojson\n";
     *
     *
     * {
     * "type": "FeatureCollection",
     * "features": [
     * {
     * "type": "Feature",
     * "geometry": {
     * "type": "Point",
     * "coordinates": [-97.099264, 31.052948]
     * },
     * "properties": {
     * "latitude": 31.052948,
     * "longitude": -97.099264
     * }
     * },
     * {
     * "type": "Feature",
     * "geometry": {
     * "type": "Point",
     * "coordinates": [-96.797, 32.7767]
     * },
     * "properties": {
     * "latitude": 32.7767,
     * "longitude": -96.797
     * }
     * },
     * ...
     * ]
     * }
     *
     * <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
     * <script>
     * const map = L.map('map').setView([31.0, -97.0], 6);
     * L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
     *
     * // Load GeoJSON points from file
     * fetch('points.geojson')
     * .then(res => res.json())
     * .then(data => {
     * L.geoJSON(data).addTo(map);
     * });
     * </script>
     *
     *
     *
     */

    /**
     * Generate circular polygon geometry approximating radius around coordinate
     *
     * Creates GeoJSON Polygon geometry approximating circle with specified radius.
     * Uses coordinate projection to generate evenly-spaced polygon vertices.
     *
     * @param float $radius Circle radius in specified unit
     * @param int $segments Number of polygon vertices (default: 64)
     * @param string $unit Distance unit ('mi', 'km', or 'nmi')
     * @return array GeoJSON Polygon geometry with circular approximation
     * @since 1.0.0
     */
    public function toGeoJsonCircle(float $radius, int $segments = 64, string $unit = 'mi'): array
    {
        $circle = [];
        $angleStep = 360 / $segments;

        for ($i = 0; $i <= $segments; $i++) {
            $bearing = $i * $angleStep;
            $point = $this->project($radius, $bearing, $unit);
            $circle[] = [$point->longitude, $point->latitude]; // GeoJSON = lon, lat
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [ $circle ]
        ];
    }

    /**
     * $temple = Gps::fromDecimal(31.052948, -97.099264);
     *
     * // Create 10-mile radius circle GeoJSON
     * $circleJson = $temple->toGeoJsonCircle(10); // 10 mi radius
     *
     * // Wrap as a Feature if needed
     * $feature = [
     * 'type' => 'Feature',
     * 'geometry' => $circleJson,
     * 'properties' => [
     * 'radius' => 10,
     * 'unit' => 'mi',
     * 'center' => "$temple"
     * ]
     * ];
     *
     * // Save to file
     * file_put_contents('temple-radius.geojson', json_encode($feature, JSON_PRETTY_PRINT));
     *
     *
     * How it looks in leaflet
     *
     * fetch('temple-radius.geojson')
     * .then(res => res.json())
     * .then(data => {
     * L.geoJSON(data, {
     * style: { color: 'red', fillOpacity: 0.3 }
     * }).addTo(map);
     * });
     *
     *
     */



    /**
     * Create GeoJSON FeatureCollection with point and optional circular boundary
     *
     * Generates complete GeoJSON FeatureCollection containing GPS coordinate as
     * Feature point and optionally includes circular polygon for radius boundary.
     *
     * @param float $radius Optional radius for circular boundary (0 = no circle)
     * @param int $segments Number of points in circle polygon (default: 64)
     * @param string $unit Distance unit for radius ('mi', 'km', or 'nmi')
     * @return array Complete GeoJSON FeatureCollection array
     * @since 1.0.0
     */
    public function toGeoJsonFeatureCollection(float $radius = 0, int $segments = 64, string $unit = 'mi'): array
    {
        $features = [ $this->toGeoJson(true) ];

        // Add the central point as a Feature
//        $features[] = $this->toGeoJson(true);

        // Optionally add a radius circle polygon
        if ($radius > 0) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => $this->toGeoJsonCircle($radius, $segments, $unit),
                'properties' => [
                    'radius' => $radius,
                    'unit' => $unit,
                    'center' => (string) $this
                ]
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * $temple = Gps::fromDecimal(31.052948, -97.099264);
     * $geojson = $temple->toGeoJsonFeatureCollection(10); // 10 mi radius
     *
     * file_put_contents('temple.geojson', json_encode($geojson, JSON_PRETTY_PRINT));
     *
     */

    /**
     * Export GPS coordinate as GPX waypoint for GPS device compatibility
     *
     * Creates complete GPX 1.1 XML document containing single waypoint with
     * current GPS coordinate. Compatible with GPS devices and mapping software.
     *
     * @param string $name Waypoint name for GPS device display (default: 'Waypoint')
     * @return string Complete GPX XML document ready for file export
     * @since 1.0.0
     */
    public function toGpx(string $name = 'Waypoint'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="GpsClass" xmlns="http://www.topografix.com/GPX/1/1">
  <wpt lat="{$this->latitude}" lon="{$this->longitude}">
    <name>{$name}</name>
  </wpt>
</gpx>
XML;
    }

    /**
     * Import GPS coordinates from GPX file waypoints
     *
     * Parses GPX files and extracts waypoint coordinates into GPS instances.
     * Supports standard GPX 1.1 format with basic error handling.
     *
     * @param string $filename Path to GPX file for import
     * @return Gps[] Array of GPS instances from file waypoints
     * @throws InvalidArgumentException When file is not found
     * @throws RuntimeException When GPX file cannot be parsed
     * @since 1.0.0
     */
    public static function fromGpxFile(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("File not found: $filename");
        }

        $xml = simplexml_load_file($filename);
        if (!$xml) {
            throw new RuntimeException("Invalid GPX file: $filename");
        }

        $points = [];
        foreach ($xml->wpt as $wpt) {
            $lat = (float) $wpt['lat'];
            $lon = (float) $wpt['lon'];
            $points[] = new self($lat, $lon);
        }

        return $points;
    }

    /**
     * Export array of GPS points as GPX track with segments
     *
     * Creates GPX 1.1 compliant track file from GPS coordinate array. Tracks
     * represent recorded paths with sequential track points in track segments.
     *
     * @param Gps[] $points Array of GPS coordinate instances for track
     * @param string $trackName Name for the track (default: 'Track')
     * @return string Complete GPX XML string ready for file export
     * @since 1.0.0
     */
    public static function exportTrack(array $points, string $trackName = 'Track'): string
    {
        $segments = '';
        foreach ($points as $point) {
            $segments .= sprintf(
                '      <trkpt lat="%f" lon="%f"></trkpt>' . PHP_EOL,
                $point->getLatitude(),
                $point->getLongitude()
            );
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="GpsClass" xmlns="http://www.topografix.com/GPX/1/1">
  <trk>
    <name>{$trackName}</name>
    <trkseg>
{$segments}    </trkseg>
  </trk>
</gpx>
XML;
    }

    /**
     * Export array of GPS points as GPX route for navigation
     *
     * Creates GPX 1.1 compliant route file from GPS coordinate array. Routes
     * represent planned navigation paths with sequential route points.
     *
     * @param Gps[] $points Array of GPS coordinate instances for route
     * @param string $routeName Name for the route (default: 'Route')
     * @return string Complete GPX XML string ready for file export
     * @since 1.0.0
     */
    public static function exportRoute(array $points, string $routeName = 'Route'): string
    {
        $steps = '';
        foreach ($points as $point) {
            $steps .= sprintf(
                '    <rtept lat="%f" lon="%f"></rtept>' . PHP_EOL,
                $point->getLatitude(),
                $point->getLongitude()
            );
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="GpsClass" xmlns="http://www.topografix.com/GPX/1/1">
  <rte>
    <name>{$routeName}</name>
{$steps}  </rte>
</gpx>
XML;
    }


}
