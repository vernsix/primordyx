<?php
/**
 * Safe Session Demonstration Controller
 *
 * Example controller showing practical usage of the Safe session system.
 * Demonstrates setting sample data and displaying Safe contents in a web application context.
 *
 * ROUTES TO ADD:
 *
 * // Add these routes to your router configuration:
 * Router::get('/safe/demo/setup', [], function () {(new SafeDemoController())->setupSession();});
 * Router::get('/safe/demo/display', [], function () {(new SafeDemoController())->displaySession();});
 * Router::get('/safe/demo', [], function () {(new SafeDemoController())->index();});
 *
 * USAGE:
 * 1. Visit /safe/demo/setup to populate session with demo data
 * 2. Visit /safe/demo/display to view all session contents
 * 3. Visit /safe/demo for overview and navigation links
 *
 * @package     Primordyx Examples
 * @author      Vern Six vernsix@gmail.com
 * @since       1.0.0
 */

use Primordyx\Data\Safe;

class SafeDemoController
{
    /**
     * Demo overview page with navigation links
     *
     * ROUTE: GET /safe/demo
     *
     * @return string HTML page with demo navigation and instructions
     * @throws Exception
     */
    public function index(): string
    {
        $isSessionStarted = Safe::isStarted();
        $sessionData = $isSessionStarted ? Safe::all() : [];
        $dataCount = count($sessionData);

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safe Session Demo - Primordyx Framework</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #7f8c8d; margin-bottom: 30px; }
        .demo-nav { display: flex; gap: 15px; margin: 30px 0; }
        .demo-btn { padding: 12px 24px; border: none; border-radius: 6px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .demo-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .status-box { padding: 20px; border-radius: 6px; margin: 20px 0; }
        .status-active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-inactive { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
        .feature-card { padding: 20px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #3498db; }
        .feature-card h3 { margin-top: 0; color: #2c3e50; }
        .code-example { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 14px; overflow-x: auto; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Safe Session Demo</h1>
        <p class="subtitle">Primordyx Framework - Secure Database-Backed Sessions</p>
        
        <div class="status-box ' . ($isSessionStarted ? 'status-active' : 'status-inactive') . '">
            <strong>Session Status:</strong> ' . ($isSessionStarted ? "Active with $dataCount keys stored" : 'No active session') . '<br>
            <strong>Session ID:</strong> ' . ($isSessionStarted ? substr(Safe::getId(), 0, 16) . '...' : 'None') . '<br>
            <strong>Lifetime:</strong> ' . (Safe::getLifetime() / 3600) . ' hours
        </div>
        
        <div class="demo-nav">
            <a href="/safe/demo/setup" class="demo-btn btn-primary">🚀 Setup Demo Data</a>
            <a href="/safe/demo/display" class="demo-btn btn-success">👁️ Display Session</a>
            <a href="/safe/demo" class="demo-btn btn-info">🔄 Refresh Status</a>
        </div>
        
        <h2>What This Demo Shows</h2>
        <div class="feature-list">
            <div class="feature-card">
                <h3>🛡️ Security Features</h3>
                <p>Cryptographically secure session IDs, secure cookie handling, and database persistence instead of vulnerable file storage.</p>
            </div>
            <div class="feature-card">
                <h3>⚡ Flash Messages</h3>
                <p>One-time messages for user notifications that automatically disappear after being displayed once.</p>
            </div>
            <div class="feature-card">
                <h3>🛒 Complex Data</h3>
                <p>Shopping carts, user preferences, multi-step forms, and feature flags - all stored securely in sessions.</p>
            </div>
            <div class="feature-card">
                <h3>🔧 Easy Integration</h3>
                <p>Drop-in replacement for $_SESSION with enhanced security and automatic expiration management.</p>
            </div>
        </div>
        
        <h2>Quick Start Code</h2>
        <div class="code-example">// Start Safe session system
Safe::start();

// Store data (any serializable type)
Safe::set(\'user_id\', 123);
Safe::set(\'preferences\', [\'theme\' => \'dark\']);

// Flash messages (shown once)
Safe::flash(\'success\', \'Welcome back!\');

// Retrieve data
$userId = Safe::get(\'user_id\');
$message = Safe::getFlash(\'success\');

// Security operations
Safe::regenerate(); // Prevent session fixation
Safe::destroy();    // Complete logout</div>

        <h2>Demo Instructions</h2>
        <ol>
            <li><strong>Setup Demo Data:</strong> Click "Setup Demo Data" to populate the session with realistic sample data including user info, shopping cart, preferences, and flash messages.</li>
            <li><strong>Display Session:</strong> Click "Display Session" to see a beautifully formatted view of all session contents, including flash message consumption.</li>
            <li><strong>Experiment:</strong> Try refreshing, navigating between pages, and observe how flash messages work (appear once, then disappear).</li>
        </ol>
        
        <p><strong>Note:</strong> This demo uses database-backed session storage. Session data persists across page loads and survives server restarts, unlike file-based PHP sessions.</p>
    </div>
</body>
</html>';
    }

    /**
     * Setup comprehensive demo session data
     *
     * ROUTE: GET /safe/demo/setup
     *
     * Populates the Safe session with realistic application data including user authentication,
     * preferences, shopping cart, flash messages, and security tokens to demonstrate
     * the full capabilities of the Safe session system.
     *
     * @return string HTML response confirming setup completion
     * @throws Exception If Safe session operations fail
     */
    public function setupSession(): string
    {
        try {
            // Start the Safe session system
            if (!Safe::start()) {
                throw new RuntimeException('Failed to start Safe session');
            }

            // 1. User authentication data
            Safe::set('user_id', 1234);
            Safe::set('username', 'john_doe');
            Safe::set('user_role', 'administrator');
            Safe::set('login_time', time());
            Safe::set('last_activity', time());

            // 2. User profile information
            Safe::set('user_profile', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+1-555-123-4567',
                'timezone' => 'America/New_York',
                'language' => 'en_US'
            ]);

            // 3. User preferences and settings
            Safe::set('preferences', [
                'theme' => 'dark',
                'notifications_enabled' => true,
                'items_per_page' => 25,
                'default_currency' => 'USD',
                'two_factor_enabled' => true
            ]);

            // 4. Application state data
            Safe::set('current_page', '/dashboard');
            Safe::set('breadcrumbs', ['Home', 'Dashboard', 'User Profile']);
            Safe::set('sidebar_collapsed', false);

            // 5. Shopping cart or workspace data
            Safe::set('shopping_cart', [
                'items' => [
                    ['id' => 101, 'name' => 'Wireless Headphones', 'price' => 99.99, 'qty' => 1],
                    ['id' => 205, 'name' => 'USB-C Cable', 'price' => 19.99, 'qty' => 2],
                    ['id' => 312, 'name' => 'Phone Case', 'price' => 29.99, 'qty' => 1]
                ],
                'total' => 169.97,
                'tax' => 13.60,
                'shipping' => 5.99,
                'discount_code' => 'SAVE10',
                'discount_amount' => 16.97
            ]);

            // 6. Form data preservation (for multi-step forms)
            Safe::set('form_step_1', [
                'company_name' => 'Acme Corporation',
                'industry' => 'Technology',
                'company_size' => '50-100 employees'
            ]);

            Safe::set('form_step_2', [
                'billing_address' => '123 Business Ave',
                'billing_city' => 'New York',
                'billing_state' => 'NY',
                'billing_zip' => '10001'
            ]);

            // 7. Security and audit data
            Safe::set('ip_address', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            Safe::set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
            Safe::set('failed_login_attempts', 0);
            Safe::set('session_regenerated_at', time());

            // 8. Flash messages (will be consumed when displayed)
            Safe::flash('success', 'Welcome back, John! Your session has been restored.');
            Safe::flash('info', 'You have 3 new notifications waiting.');
            Safe::flash('warning', 'Your password expires in 7 days. Consider updating it.');
            Safe::flash('error', 'Failed to sync with external service. Retrying in background.');

            // 9. Temporary data with expiration context
            Safe::set('password_reset_token', bin2hex(random_bytes(32)));
            Safe::set('password_reset_expires', time() + 3600); // 1 hour from now
            Safe::set('email_verification_pending', true);

            // 10. Feature flags and A/B testing
            Safe::set('feature_flags', [
                'new_dashboard' => true,
                'beta_checkout' => false,
                'advanced_search' => true,
                'mobile_app_promo' => true
            ]);

            Safe::set('ab_test_groups', [
                'checkout_flow' => 'variant_b',
                'pricing_display' => 'variant_a',
                'homepage_layout' => 'control'
            ]);

            // Save the session data
            if (!Safe::save()) {
                throw new RuntimeException('Failed to save Safe session data');
            }

            // Return success page
            return $this->renderSuccessPage('Demo session data has been set up successfully!', [
                'Total keys stored' => count(Safe::all()),
                'Session ID' => substr(Safe::getId(), 0, 16) . '...',
                'Data includes' => 'User auth, profile, cart, preferences, flash messages, security tokens, feature flags',
                'Next step' => 'Visit <a href="/safe/demo/display">Display Session</a> to see all data'
            ]);

        } catch (Exception $e) {
            error_log("Safe demo setup error: " . $e->getMessage());
            return $this->renderErrorPage('Failed to setup demo session data', $e->getMessage());
        }
    }

    /**
     * Display complete Safe session contents with formatted output
     *
     * ROUTE: GET /safe/demo/display
     *
     * Retrieves and displays all session data including regular data and flash messages.
     * Provides both human-readable output and structured data for debugging purposes.
     * Flash messages are consumed (removed) when displayed.
     *
     * @return string Formatted HTML output showing all Safe contents
     * @throws Exception If Safe session operations fail
     */
    public function displaySession(): string
    {
        try {
            // Ensure Safe is started
            if (!Safe::isStarted()) {
                if (!Safe::start()) {
                    return $this->renderErrorPage('Session Error', 'Could not start Safe session');
                }
            }

            $output = [];
            $output[] = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
            $output[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            $output[] = '<title>Safe Session Contents - Primordyx Framework</title>';
            $output[] = $this->getDisplayStyles();
            $output[] = '</head><body>';

            $output[] = '<div class="safe-demo-display">';
            $output[] = '<div class="header">';
            $output[] = '<h1>🔐 Safe Session Contents</h1>';
            $output[] = '<div class="nav-links">';
            $output[] = '<a href="/safe/demo" class="nav-btn">← Back to Demo Home</a>';
            $output[] = '<a href="/safe/demo/setup" class="nav-btn">🔄 Reload Demo Data</a>';
            $output[] = '</div>';
            $output[] = '</div>';

            // Session metadata
            $sessionId = Safe::getId();
            $lifetime = Safe::getLifetime();
            $output[] = '<div class="session-info">';
            $output[] = '<h2>📋 Session Information</h2>';
            $output[] = '<div class="info-grid">';
            $output[] = '<div class="info-item"><strong>Session ID:</strong> ' . substr($sessionId, 0, 16) . '...' . substr($sessionId, -8) . '</div>';
            $output[] = '<div class="info-item"><strong>Lifetime:</strong> ' . ($lifetime / 3600) . ' hours (' . number_format($lifetime) . ' seconds)</div>';
            $output[] = '<div class="info-item"><strong>Status:</strong> ' . (Safe::isStarted() ? '✅ Active' : '❌ Inactive') . '</div>';
            $output[] = '<div class="info-item"><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</div>';
            $output[] = '</div>';
            $output[] = '</div>';

            // Flash messages (consumed when retrieved)
            $output[] = '<div class="flash-messages">';
            $output[] = '<h2>⚡ Flash Messages</h2>';

            $flashTypes = ['success', 'info', 'warning', 'error'];
            $hasFlashMessages = false;

            foreach ($flashTypes as $type) {
                $message = Safe::getFlash($type);
                if ($message) {
                    $hasFlashMessages = true;
                    $icon = match($type) {
                        'success' => '✅',
                        'info' => 'ℹ️',
                        'warning' => '⚠️',
                        'error' => '❌',
                        default => '💬'
                    };
                    $output[] = '<div class="flash-' . $type . '">';
                    $output[] = $icon . ' <strong>' . ucfirst($type) . ':</strong> ' . htmlspecialchars($message);
                    $output[] = '</div>';
                }
            }

            if (!$hasFlashMessages) {
                $output[] = '<p class="no-data">No flash messages available (they may have been consumed on a previous page load)</p>';
            } else {
                $output[] = '<p class="flash-note"><strong>Note:</strong> Flash messages are consumed when displayed and will not appear on the next page load.</p>';
            }
            $output[] = '</div>';

            // All session data
            $allData = Safe::all();
            $output[] = '<div class="session-data">';
            $output[] = '<h2>💾 All Session Data</h2>';

            if (empty($allData)) {
                $output[] = '<p class="no-data">No session data found. <a href="/safe/demo/setup">Set up demo data</a> first.</p>';
            } else {
                // Group data by category for better display
                $categories = [
                    'user_' => ['name' => '👤 User Information', 'data' => []],
                    'form_' => ['name' => '📝 Form Data', 'data' => []],
                    'feature_' => ['name' => '🚩 Feature Flags', 'data' => []],
                    'ab_test_' => ['name' => '🧪 A/B Testing', 'data' => []],
                    'password_' => ['name' => '🔐 Security Tokens', 'data' => []],
                    'shopping_' => ['name' => '🛒 Shopping Cart', 'data' => []],
                    '_flash.' => ['name' => '⚡ Flash Data (Remaining)', 'data' => []],
                    '' => ['name' => '📂 General Data', 'data' => []] // catch-all
                ];

                foreach ($allData as $key => $value) {
                    $categoryKey = '';
                    foreach (array_keys($categories) as $prefix) {
                        if ($prefix && str_starts_with($key, $prefix)) {
                            $categoryKey = $prefix;
                            break;
                        }
                    }
                    $categories[$categoryKey]['data'][$key] = $value;
                }

                foreach ($categories as $categoryKey => $category) {
                    if (empty($category['data'])) continue;

                    $output[] = '<div class="data-category">';
                    $output[] = '<h3>' . $category['name'] . '</h3>';
                    $output[] = '<div class="data-table-container">';
                    $output[] = '<table class="data-table">';
                    $output[] = '<thead><tr><th>Key</th><th>Type</th><th>Value</th></tr></thead>';
                    $output[] = '<tbody>';

                    foreach ($category['data'] as $key => $value) {
                        $type = gettype($value);
                        $displayValue = $this->formatValueForDisplay($value);

                        $output[] = '<tr>';
                        $output[] = '<td><code class="key-name">' . htmlspecialchars($key) . '</code></td>';
                        $output[] = '<td><span class="type-badge type-' . $type . '">' . $type . '</span></td>';
                        $output[] = '<td class="value-cell">' . $displayValue . '</td>';
                        $output[] = '</tr>';
                    }

                    $output[] = '</tbody></table>';
                    $output[] = '</div>';
                    $output[] = '</div>';
                }
            }

            $output[] = '</div>';

            // Summary statistics
            $dataCount = count($allData);
            $dataSize = strlen(serialize($allData));
            $output[] = '<div class="session-stats">';
            $output[] = '<h2>📊 Session Statistics</h2>';
            $output[] = '<div class="stats-grid">';
            $output[] = '<div class="stat-item"><strong>Total Keys:</strong> ' . $dataCount . '</div>';
            $output[] = '<div class="stat-item"><strong>Serialized Size:</strong> ' . $this->formatBytes($dataSize) . '</div>';
            $output[] = '<div class="stat-item"><strong>Memory Usage:</strong> ' . $this->formatBytes(memory_get_usage()) . '</div>';
            $output[] = '<div class="stat-item"><strong>Peak Memory:</strong> ' . $this->formatBytes(memory_get_peak_usage()) . '</div>';
            $output[] = '</div>';
            $output[] = '</div>';

            $output[] = '</div>'; // close safe-demo-display
            $output[] = '</body></html>';

            return implode("\n", $output);

        } catch (Exception $e) {
            error_log("Safe demo display error: " . $e->getMessage());
            return $this->renderErrorPage('Display Error', 'Error displaying Safe contents: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to format values for display in HTML
     *
     * @param mixed $value The value to format
     * @return string Formatted HTML string
     */
    private function formatValueForDisplay(mixed $value): string
    {
        switch (gettype($value)) {
            case 'array':
                if (empty($value)) {
                    return '<em class="empty-value">empty array</em>';
                }
                // For small arrays, show inline
                if (count($value) <= 3 && !is_array(reset($value))) {
                    $items = array_map('htmlspecialchars', array_map('strval', $value));
                    return '<code class="inline-array">[' . implode(', ', $items) . ']</code>';
                }
                // For larger/complex arrays, show formatted JSON
                return '<pre class="json-data">' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';

            case 'boolean':
                return $value ? '<strong class="bool-true">true</strong>' : '<strong class="bool-false">false</strong>';

            case 'NULL':
                return '<em class="null-value">null</em>';

            case 'integer':
                // Format timestamps as human readable
                if ($value > 1000000000 && $value < 2147483647) { // Looks like a timestamp
                    return '<span class="timestamp">' . number_format($value) . ' <small>(' . date('Y-m-d H:i:s', $value) . ')</small></span>';
                }
                return '<strong class="number">' . number_format($value) . '</strong>';

            case 'double':
                return '<strong class="number">' . number_format($value, 2) . '</strong>';

            case 'string':
            default:
                // Truncate very long strings
                if (strlen($value) > 150) {
                    return '<span class="string-value">' . htmlspecialchars(substr($value, 0, 150)) . '</span><small class="truncated">... (' . number_format(strlen($value)) . ' chars)</small>';
                }
                return '<span class="string-value">' . htmlspecialchars($value) . '</span>';
        }
    }

    /**
     * Helper method to format bytes in human readable format
     *
     * @param int $bytes Number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted string with units
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return number_format($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Render success page with formatted output
     *
     * @param string $title Success message title
     * @param array $details Additional details to display
     * @return string HTML success page
     */
    private function renderSuccessPage(string $title, array $details = []): string
    {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        $html .= '<title>Success - Safe Demo</title>';
        $html .= '<style>body{font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;background:#f8f9fa;}';
        $html .= '.success{background:#d4edda;color:#155724;padding:20px;border-radius:6px;border:1px solid #c3e6cb;}';
        $html .= '.details{background:white;padding:20px;margin:20px 0;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}';
        $html .= '.nav-links{margin:20px 0;}.nav-btn{display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:4px;margin-right:10px;}';
        $html .= '</style></head><body>';

        $html .= '<div class="success"><h2>✅ ' . htmlspecialchars($title) . '</h2></div>';

        if (!empty($details)) {
            $html .= '<div class="details"><h3>Details:</h3><ul>';
            foreach ($details as $key => $value) {
                $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . $value . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '<div class="nav-links">';
        $html .= '<a href="/safe/demo" class="nav-btn">← Demo Home</a>';
        $html .= '<a href="/safe/demo/display" class="nav-btn">👁️ View Session</a>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Render error page with formatted output
     *
     * @param string $title Error title
     * @param string $message Error message
     * @return string HTML error page
     */
    private function renderErrorPage(string $title, string $message): string
    {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        $html .= '<title>Error - Safe Demo</title>';
        $html .= '<style>body{font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;background:#f8f9fa;}';
        $html .= '.error{background:#f8d7da;color:#721c24;padding:20px;border-radius:6px;border:1px solid #f5c6cb;}';
        $html .= '.nav-links{margin:20px 0;}.nav-btn{display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:4px;margin-right:10px;}';
        $html .= '</style></head><body>';

        $html .= '<div class="error"><h2>❌ ' . htmlspecialchars($title) . '</h2>';
        $html .= '<p>' . htmlspecialchars($message) . '</p></div>';

        $html .= '<div class="nav-links">';
        $html .= '<a href="/safe/demo" class="nav-btn">← Demo Home</a>';
        $html .= '<a href="/safe/demo/setup" class="nav-btn">🔄 Try Setup Again</a>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Get CSS styles for the display page
     *
     * @return string CSS style block
     */
    private function getDisplayStyles(): string
    {
        return '<style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                margin: 0; padding: 20px; background: #f8f9fa; line-height: 1.6; 
            }
            .safe-demo-display { 
                max-width: 1400px; margin: 0 auto; background: white; 
                border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; padding: 30px; text-align: center; 
            }
            .header h1 { margin: 0 0 20px 0; font-size: 2.5em; }
            .nav-links { display: flex; gap: 15px; justify-content: center; }
            .nav-btn { 
                background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; 
                text-decoration: none; border-radius: 20px; transition: all 0.3s; 
            }
            .nav-btn:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
            .session-info, .flash-messages, .session-data, .session-stats { 
                margin: 0; padding: 30px; 
            }
            .session-info { background: #f8f9fa; }
            .flash-messages { background: white; }
            .session-data { background: #f8f9fa; }
            .session-stats { background: white; }
            h2 { color: #2c3e50; margin: 0 0 20px 0; font-size: 1.8em; }
            h3 { color: #34495e; margin: 20px 0 15px 0; font-size: 1.4em; }
            .info-grid, .stats-grid { 
                display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                gap: 15px; margin: 20px 0; 
            }
            .info-item, .stat-item { 
                background: white; padding: 15px; border-radius: 6px; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            }
            .session-stats .stat-item { background: #f8f9fa; }
            .data-category { margin: 30px 0; }
            .data-table-container { overflow-x: auto; margin: 15px 0; }
            .data-table { 
                width: 100%; border-collapse: collapse; background: white; 
                border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            }
            .data-table th { 
                background: #6c757d; color: white; padding: 12px; text-align: left; 
                font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px; 
            }
            .data-table td { padding: 12px; border-bottom: 1px solid #e9ecef; vertical-align: top; }
            .data-table tr:hover { background: #f8f9fa; }
            .key-name { 
                background: #e9ecef; color: #495057; padding: 4px 8px; border-radius: 3px; 
                font-family: "SF Mono", Monaco, "Cascadia Code", "Roboto Mono", Consolas, monospace; 
                font-size: 0.9em; 
            }
            .type-badge { 
                padding: 4px 10px; border-radius: 12px; font-size: 0.8em; 
                font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; 
            }
            .type-string { background: #d1ecf1; color: #0c5460; }
            .type-integer { background: #cce5ff; color: #004085; }
            .type-array { background: #e2d9f3; color: #5a2d82; }
            .type-boolean { background: #fff3cd; color: #856404; }
            .type-double { background: #d4edda; color: #155724; }
            .value-cell { max-width: 400px; }
            .json-data { 
                background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; 
                font-family: monospace; font-size: 13px; line-height: 1.4; 
                overflow-x: auto; margin: 8px 0; max-height: 300px; overflow-y: auto; 
            }
            .inline-array { 
                background: #f1f3f4; padding: 4px 8px; border-radius: 4px; 
                font-family: monospace; font-size: 0.9em; 
            }
            .bool-true { color: #28a745; }
            .bool-false { color: #dc3545; }
            .null-value { color: #6c757d; font-style: italic; }
            .empty-value { color: #6c757d; font-style: italic; }
            .number { color: #007bff; }
            .string-value { color: #495057; }
            .timestamp { color: #6f42c1; }
            .timestamp small { color: #6c757d; font-weight: normal; }
            .truncated { color: #6c757d; }
            .flash-success { 
                color: #155724; background: #d4edda; padding: 12px; border-radius: 6px; 
                margin: 8px 0; border-left: 4px solid #28a745; 
            }
            .flash-info { 
                color: #0c5460; background: #d1ecf1; padding: 12px; border-radius: 6px; 
                margin: 8px 0; border-left: 4px solid #17a2b8; 
            }
            .flash-warning { 
                color: #856404; background: #fff3cd; padding: 12px; border-radius: 6px; 
                margin: 8px 0; border-left: 4px solid #ffc107; 
            }
            .flash-error { 
                color: #721c24; background: #f8d7da; padding: 12px; border-radius: 6px; 
                margin: 8px 0; border-left: 4px solid #dc3545; 
            }
            .flash-note { 
                background: #e7f3ff; color: #0066cc; padding: 10px; border-radius: 4px; 
                font-size: 0.9em; border-left: 3px solid #007bff; 
            }
            .no-data { 
                color: #6c757d; font-style: italic; text-align: center; 
                padding: 40px; background: #f8f9fa; border-radius: 6px; 
            }
            @media (max-width: 768px) {
                body { padding: 10px; }
                .safe-demo-display { margin: 0; }
                .header { padding: 20px; }
                .header h1 { font-size: 2em; }
                .session-info, .flash-messages, .session-data, .session-stats { padding: 20px; }
                .info-grid, .stats-grid { grid-template-columns: 1fr; }
                .nav-links { flex-direction: column; align-items: center; }
                .data-table-container { font-size: 0.9em; }
            }
        </style>';
    }
}