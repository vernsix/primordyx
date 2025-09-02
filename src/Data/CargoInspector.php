<?php
/**
 * File: /vendor/vernsix/primordyx/src/CargoInspector.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/CargoInspector.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Data;

/**
 * Class CargoInspector
 *
 * A quick little UI to show what's in cargo.  BE CAREFUL!  This will show to the end user.  This is really
 * meant for testing only.
 *
 * @since 1.0.0
 *
 */
class CargoInspector {

    /**
     * Renders a complete HTML interface for inspecting all Cargo container instances
     *
     * Generates a comprehensive debug view showing:
     * - All container instances with their keys, values, and metadata
     * - Container lock status and protection information
     * - Snapshot/version history for each container
     * - Recent activity logs with timestamps
     * - Interactive features (collapsible values, expired key filtering)
     *
     * @return string Complete HTML document with embedded CSS and JavaScript
     *
     * @since 1.0.0
     *
     * @example
     * ```php
     * echo CargoInspector::render();
     * // Outputs full HTML debugging interface
     * ```
     */
    public static function render(): string {
        if (!class_exists('cargo')) {
            return "<p><strong>Error:</strong> cargo class not found.</p>";
        }

        $html = <<<'HTML'
<style>
.cargo-box { border:1px solid #ccc; margin-bottom:20px; padding:15px; border-radius:8px; font-family:sans-serif; }
.cargo-box h2 { margin-top:0; }
table { width:100%; border-collapse:collapse; margin-bottom:10px; }
th, td { border:1px solid #ccc; padding:6px 10px; text-align:left; vertical-align:top; }
pre { background:#f8f8f8; padding:10px; overflow:auto; margin:0; max-height:200px; }
.label { font-size: 0.8em; background: #eef; color: #336; padding: 2px 6px; border-radius: 4px; margin-left: 10px; white-space: nowrap; }
.meta-col { white-space: nowrap; }
.expired-row { opacity: 0.4; }
.toggle-btn { cursor: pointer; color: #00f; text-decoration: underline; font-size: 0.9em; margin-left: 10px; }
</style>

<h1>Cargo Inspector</h1>
<label><input type="checkbox" id="toggle-expired-checkbox" checked> Hide expired keys</label>
HTML;

        foreach (Cargo::allInstances() as $name => $data) {
            $container = Cargo::getInstance($name);
            $log = $container->getLog();
            $versions = $container->listVersions();
            $locked = $container->isLocked() ? 'Yes' : 'No';

            $html .= "<div class='cargo-box'>";
            $html .= "<h2>Container: <code>$name</code> <span class='label'>Locked: $locked</span></h2>";

            $html .= "<h3>Keys & Values</h3><table><thead><tr><th>Key</th><th>Value</th><th>Meta</th></tr></thead><tbody>";
            foreach ($data as $key => $value) {
                $html .= self::renderKeyRow($key, $value, $container->isProtected($key));
            }
            $html .= "</tbody></table>";

            if (!empty($versions)) {
                $html .= "<h3>Snapshots</h3><ul>";
                foreach ($versions as $versionName) {
                    $html .= "<li><code>$versionName</code></li>";
                }
                $html .= "</ul>";
            }

            if (!empty($log)) {
                $html .= "<h3>Recent Log</h3><pre>";
                foreach ($log as $entry) {
                    $line = "[" . $entry['time'] . "] " . $entry['action'];
                    if (!empty($entry['key'])) $line .= " {$entry['key']}";
                    if (isset($entry['value'])) {
                        $val = is_scalar($entry['value']) ? $entry['value'] : json_encode($entry['value']);
                        $line .= " → " . $val;
                    }
                    $html .= htmlspecialchars($line) . "\n";
                }
                $html .= "</pre>";
            }

            $html .= "</div>";
        }

        $html .= '<script>
document.addEventListener("DOMContentLoaded", function () {
    // Collapse toggles
    document.querySelectorAll("[data-toggle-id]").forEach(el => {
        el.addEventListener("click", function () {
            const id = el.getAttribute("data-toggle-id");
            const target = document.getElementById(id);
            if (target) {
                target.style.display = (target.style.display === "none") ? "block" : "none";
            }
        });
    });

    // Expired visibility toggle
    const toggleExpired = document.getElementById("toggle-expired-checkbox");
    if (toggleExpired) {
        toggleExpired.addEventListener("change", function () {
            document.querySelectorAll(".expired-row").forEach(row => {
                row.style.display = this.checked ? "none" : "";
            });
        });
        toggleExpired.dispatchEvent(new Event("change"));
    }
});
</script>';

        return $html;
    }


    /**
     * Renders a single table row for a Cargo container key-value pair
     *
     * Generates an HTML table row with:
     * - Key name in first column
     * - Collapsible value display with toggle functionality
     * - Metadata column showing expiration, protection, size/count info
     * - Proper styling classes for expired/protected entries
     *
     * @param string $key The container key name
     * @param mixed $value The stored value (may be wrapped with expiration data)
     * @param bool $isProtected Whether this key is write-protected
     *
     * @return string HTML table row (<tr>) with embedded data and styling
     *
     * @since 1.0.0
     */
    private static function renderKeyRow(string $key, mixed $value, bool $isProtected = false): string {
        $meta = [];
        $classes = [];
        $isExpired = false;
        $collapseId = uniqid("val_");

        if (is_array($value)) {
            $hasExpiresKey = array_key_exists('__cargo_expires', $value);
            $expires = $hasExpiresKey ? $value['__cargo_expires'] : null;

            if ($expires !== null) {
                $isExpired = (time() > $expires);
                $meta[] = $isExpired
                    ? "<span class='label' style='background:#fcc;color:#900;'>Expired</span>"
                    : "<span class='label'>Expires: " . date('H:i:s', $expires) . "</span>";

                if (!$isExpired) {
                    $ttl = $expires - time();
                    $meta[] = "<span class='label'>TTL: {$ttl}s</span>";
                }
            } elseif ($hasExpiresKey) {
                $meta[] = "<span class='label'>No Expiration</span>";
            }

            $value = $value['__cargo_value'] ?? $value;
        }

        if ($isProtected) {
            $meta[] = "<span class='label' style='background:#ffe;color:#660;'>Protected</span>";
        }

        if (is_string($value)) {
            $meta[] = "<span class='label'>Size: " . strlen($value) . "</span>";
        } elseif (is_array($value)) {
            $meta[] = "<span class='label'>Items: " . count($value) . "</span>";
        }

        if ($isExpired) {
            $classes[] = 'expired-row';
        }

        $escaped = htmlspecialchars(print_r($value, true));
        $metaHtml = implode(' ', $meta);
        $rowClass = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

        return "<tr" . $rowClass . "><td>$key</td>
            <td>
                <span class='toggle-btn' data-toggle-id=\"$collapseId\">[toggle]</span>
                <div id=\"$collapseId\" style=\"display:none\"><pre>$escaped</pre></div>
            </td>
            <td class='meta-col'>$metaHtml</td></tr>";
    }
}
