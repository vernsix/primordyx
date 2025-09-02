<?php
/**
 * EventManager Demo Program for Primordyx Framework
 *
 * This demo illustrates the comprehensive event system capabilities:
 * - Actions and Filters
 * - Priority-based execution
 * - Floor control (action suppression)
 * - Event debugging and monitoring
 * - Real-world integration examples
 * - Introspection and debugging tools
 *
 * Run from command line: php EventManagerDemo.php
 */

declare(strict_types=1);

// Assume Primordyx is autoloaded - in real app this would be handled by your bootstrap
// require_once 'path/to/primordyx/autoload.php';

use Primordyx\Events\EventManager;

class EventManagerDemo
{
    private static int $stepCounter = 1;

    public static function run(): void
    {
        echo "=== PRIMORDYX EVENTMANAGER COMPREHENSIVE DEMO ===\n\n";

        // Enable debugging for specific events
        self::setupEventDebugging();

        // Demo 1: Basic Actions
        self::demoBasicActions();

        // Demo 2: Basic Filters
        self::demoBasicFilters();

        // Demo 3: Priority System
        self::demoPrioritySystem();

        // Demo 4: Floor Control (Action Suppression)
        self::demoFloorControl();

        // Demo 5: Combined Actions and Filters
        self::demoCombinedExample();

        // Demo 6: Real-world User Registration Example
        self::demoUserRegistration();

        // Demo 7: Content Processing Pipeline
        self::demoContentPipeline();

        // Demo 8: Introspection and Debugging
        self::demoIntrospection();

        echo "\n=== DEMO COMPLETE ===\n";
        echo "Check your error log for debug output from notify() calls!\n";
    }

    private static function setupEventDebugging(): void
    {
        self::printStep("Setting up event debugging");

        // Monitor user-related events
        EventManager::notify('user');

        // Monitor content processing
        EventManager::notify('content');

        // Monitor demo events
        EventManager::notify('demo');

        echo "✓ Event debugging enabled for: 'user', 'content', 'demo' patterns\n";
        echo "  (Check error_log for detailed event firing information)\n\n";
    }

    private static function demoBasicActions(): void
    {
        self::printStep("Basic Actions - Simple Event Handling");

        // Register some basic actions
        EventManager::add_action('demo.hello', function($name) {
            echo "  → Hello action says: Welcome, {$name}!\n";
        }, 10, 'Welcome greeting');

        EventManager::add_action('demo.hello', function($name) {
            echo "  → Hello action says: Nice to meet you, {$name}!\n";
        }, 10, 'Nice to meet you greeting');

        EventManager::add_action('demo.hello', function($name) {
            echo "  → Hello action says: Have a great day, {$name}!\n";
        }, 5, 'Have a great day (lower priority)');

        echo "Firing 'demo.hello' event with name 'Developer'...\n";
        EventManager::fire('demo.hello', 'Developer');

        echo "Notice: Higher priority (10) actions run before lower priority (5)\n\n";
    }

    private static function demoBasicFilters(): void
    {
        self::printStep("Basic Filters - Data Transformation");

        // Register filters to transform text
        EventManager::add_filter('demo.format_text', function($text) {
            echo "  → Filter: Converting to uppercase\n";
            return strtoupper($text);
        }, 10, 'Convert to uppercase');

        EventManager::add_filter('demo.format_text', function($text) {
            echo "  → Filter: Adding brackets\n";
            return "[$text]";
        }, 5, 'Add decorative brackets');

        echo "Starting with: 'hello world'\n";
        $result = EventManager::fire('demo.format_text', 'hello world');
        echo "Final result: '{$result}'\n";
        echo "Notice: Filters chain together - output of one becomes input of next\n\n";
    }

    private static function demoPrioritySystem(): void
    {
        self::printStep("Priority System - Execution Order Control");

        // Register actions with different priorities
        EventManager::add_action('demo.priority', function() {
            echo "  → Priority 100: I run FIRST (highest priority)\n";
        }, 100, 'Highest priority action');

        EventManager::add_action('demo.priority', function() {
            echo "  → Priority 50: I run SECOND (medium priority)\n";
        }, 50, 'Medium priority action');

        EventManager::add_action('demo.priority', function() {
            echo "  → Priority 10: I run THIRD (low priority)\n";
        }, 10, 'Low priority action');

        EventManager::add_action('demo.priority', function() {
            echo "  → Priority 1: I run LAST (lowest priority)\n";
        }, 1, 'Lowest priority action');

        echo "Firing 'demo.priority' event...\n";
        EventManager::fire('demo.priority');
        echo "Rule: Higher priority numbers execute first (descending order)\n\n";
    }

    private static function demoFloorControl(): void
    {
        self::printStep("Floor Control - Action Suppression System");

        // Register actions that demonstrate floor control
        EventManager::add_action('demo.floor_control', function($user) {
            echo "  → Security Check (Priority 100): Checking user permissions...\n";
            if ($user['role'] !== 'admin') {
                echo "    ⚠️  User is not admin - RAISING FLOOR to 80!\n";
                echo "    📛 This will suppress any actions with priority < 80\n";
                return 80; // This raises the floor, suppressing lower priority actions
            }
            echo "    ✓ Admin user - allowing all actions to continue\n";
            return null; // No floor change
        }, 100, 'Security permission check');

        EventManager::add_action('demo.floor_control', function($user) {
            echo "  → Admin Panel Access (Priority 90): Setting up admin interface...\n";
        }, 90, 'Admin panel setup');

        EventManager::add_action('demo.floor_control', function($user) {
            echo "  → Advanced Features (Priority 85): Loading advanced tools...\n";
        }, 85, 'Advanced features loader');

        EventManager::add_action('demo.floor_control', function($user) {
            echo "  → Basic Logging (Priority 70): Recording user activity...\n";
        }, 70, 'Basic activity logging');

        EventManager::add_action('demo.floor_control', function($user) {
            echo "  → Welcome Message (Priority 60): Showing welcome...\n";
        }, 60, 'Welcome message display');

        echo "TEST 1: Regular user (should suppress priority < 80)\n";
        EventManager::fire('demo.floor_control', ['role' => 'user', 'name' => 'John']);

        echo "\nTEST 2: Admin user (should allow all actions)\n";
        EventManager::fire('demo.floor_control', ['role' => 'admin', 'name' => 'Admin']);

        echo "Floor Control allows high-priority actions to suppress lower ones\n\n";
    }

    private static function demoCombinedExample(): void
    {
        self::printStep("Combined Actions + Filters - Complete Event Pipeline");

        // Filters to transform the content
        EventManager::add_filter('demo.publish_content', function($content, $user) {
            echo "  → Filter (Priority 100): Sanitizing HTML...\n";
            return strip_tags($content);
        }, 100, 'HTML sanitization');

        EventManager::add_filter('demo.publish_content', function($content, $user) {
            echo "  → Filter (Priority 90): Converting markdown...\n";
            return str_replace('**', '<strong>', str_replace('**', '</strong>', $content));
        }, 90, 'Basic markdown conversion');

        EventManager::add_filter('demo.publish_content', function($content, $user) {
            echo "  → Filter (Priority 80): Adding footer...\n";
            return $content . "\n\n-- Published by {$user['name']} --";
        }, 80, 'Add content footer');

        // Actions to handle the publishing process
        EventManager::add_action('demo.publish_content', function($content, $user) {
            echo "  → Action (Priority 100): Logging publish attempt...\n";
            echo "    📝 User '{$user['name']}' publishing content (" . strlen($content) . " chars)\n";
        }, 100, 'Log publishing activity');

        EventManager::add_action('demo.publish_content', function($content, $user) {
            echo "  → Action (Priority 90): Sending notifications...\n";
            echo "    📧 Notifying subscribers about new content\n";
        }, 90, 'Send publication notifications');

        EventManager::add_action('demo.publish_content', function($content, $user) {
            echo "  → Action (Priority 80): Updating search index...\n";
            echo "    🔍 Adding content to search database\n";
        }, 80, 'Update search index');

        $rawContent = "Welcome to **Primordyx** - the <script>alert('xss')</script> amazing framework!";
        $user = ['name' => 'Developer', 'id' => 123];

        echo "Original content: '{$rawContent}'\n";
        echo "Processing through complete publication pipeline...\n\n";

        $finalContent = EventManager::fire('demo.publish_content', $rawContent, $user);

        echo "\nFinal processed content:\n";
        echo "'{$finalContent}'\n";
        echo "Notice: Filters transformed the content, actions handled side effects\n\n";
    }

    private static function demoUserRegistration(): void
    {
        self::printStep("Real-world Example - User Registration System");

        // User registration filters
        EventManager::add_filter('user.register.validate', function($userData) {
            echo "  → Validation Filter: Checking email format...\n";
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("Invalid email format");
            }
            return $userData;
        }, 100, 'Email validation');

        EventManager::add_filter('user.register.validate', function($userData) {
            echo "  → Validation Filter: Checking password strength...\n";
            if (strlen($userData['password']) < 8) {
                throw new RuntimeException("Password too weak");
            }
            return $userData;
        }, 90, 'Password strength validation');

        EventManager::add_filter('user.register.process', function($userData) {
            echo "  → Processing Filter: Hashing password...\n";
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            return $userData;
        }, 100, 'Password hashing');

        EventManager::add_filter('user.register.process', function($userData) {
            echo "  → Processing Filter: Setting registration timestamp...\n";
            $userData['registered_at'] = date('Y-m-d H:i:s');
            return $userData;
        }, 90, 'Add registration timestamp');

        // User registration actions
        EventManager::add_action('user.register.complete', function($userData) {
            echo "  → Welcome Action: Sending welcome email...\n";
            echo "    📧 Welcome email sent to {$userData['email']}\n";
        }, 100, 'Send welcome email');

        EventManager::add_action('user.register.complete', function($userData) {
            echo "  → Analytics Action: Recording registration...\n";
            echo "    📊 User registration tracked in analytics\n";
        }, 90, 'Track registration analytics');

        EventManager::add_action('user.register.complete', function($userData) {
            echo "  → Setup Action: Creating user profile...\n";
            echo "    👤 Default profile created for {$userData['name']}\n";
        }, 80, 'Create default profile');

        $newUser = [
            'name' => 'Jane Developer',
            'email' => 'jane@example.com',
            'password' => 'securepass123'
        ];

        echo "Registering user: {$newUser['name']} ({$newUser['email']})\n\n";

        try {
            // Validate user data
            $validatedUser = EventManager::fire('user.register.validate', $newUser);
            echo "✓ Validation passed\n\n";

            // Process user data
            $processedUser = EventManager::fire('user.register.process', $validatedUser);
            echo "✓ Processing completed\n\n";

            // Complete registration (actions only)
            EventManager::fire('user.register.complete', $processedUser);
            echo "✓ Registration completed successfully!\n\n";

        } catch (Exception $e) {
            echo "❌ Registration failed: " . $e->getMessage() . "\n\n";
        }
    }

    private static function demoContentPipeline(): void
    {
        self::printStep("Content Processing Pipeline - Blog Post Publication");

        // Content preparation filters
        EventManager::add_filter('content.prepare', function($content) {
            echo "  → Content Filter: Removing dangerous HTML...\n";
            return strip_tags($content, '<p><br><strong><em><ul><ol><li>');
        }, 100, 'Sanitize HTML content');

        EventManager::add_filter('content.prepare', function($content) {
            echo "  → Content Filter: Processing shortcodes...\n";
            return str_replace('[highlight]', '<mark>', str_replace('[/highlight]', '</mark>', $content));
        }, 90, 'Process content shortcodes');

        EventManager::add_filter('content.prepare', function($content) {
            echo "  → Content Filter: Auto-linking URLs...\n";
            return preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1">$1</a>', $content);
        }, 80, 'Auto-link URLs');

        // Content publishing actions with permission control
        EventManager::add_action('content.publish', function($content, $author) {
            echo "  → Permission Action: Checking publishing rights...\n";
            if ($author['role'] !== 'editor' && $author['role'] !== 'admin') {
                echo "    🚫 User lacks publishing permissions - suppressing lower priority actions\n";
                return 70; // Suppress actions with priority < 70
            }
            echo "    ✅ User has publishing permissions\n";
            return 0;
        }, 100, 'Check publishing permissions');

        EventManager::add_action('content.publish', function($content, $author) {
            echo "  → Publishing Action: Saving to database...\n";
            echo "    💾 Content saved with ID: " . rand(1000, 9999) . "\n";
        }, 80, 'Save content to database');

        EventManager::add_action('content.publish', function($content, $author) {
            echo "  → Publishing Action: Updating sitemap...\n";
            echo "    🗺️  Sitemap updated with new content\n";
        }, 75, 'Update XML sitemap');

        EventManager::add_action('content.publish', function($content, $author) {
            echo "  → Notification Action: Alerting social media...\n";
            echo "    📱 Posted to social media channels\n";
        }, 60, 'Social media notification');

        EventManager::add_action('content.publish', function($content, $author) {
            echo "  → Analytics Action: Recording publication...\n";
            echo "    📈 Content analytics tracking started\n";
        }, 50, 'Start analytics tracking');

        $blogPost = "Check out this [highlight]amazing[/highlight] new feature! Visit https://primordyx.com for more info. <script>alert('hack')</script>";

        echo "TEST 1: Regular user trying to publish\n";
        $regularUser = ['name' => 'John Writer', 'role' => 'contributor'];

        $processedContent = EventManager::fire('content.prepare', $blogPost);
        echo "Processed content preview: " . substr($processedContent, 0, 100) . "...\n\n";

        EventManager::fire('content.publish', $processedContent, $regularUser);

        echo "\nTEST 2: Editor publishing content\n";
        $editor = ['name' => 'Sarah Editor', 'role' => 'editor'];
        EventManager::fire('content.publish', $processedContent, $editor);

        echo "\n";
    }

    private static function demoIntrospection(): void
    {
        self::printStep("Introspection & Debugging - Event System Analysis");

        // Add some test listeners for introspection
        EventManager::add_action('test.introspection', function() {
            return "Test action";
        }, 100, 'Test action for introspection');

        EventManager::add_filter('test.introspection', function($value) {
            return $value . " filtered";
        }, 50, 'Test filter for introspection');

        echo "Getting all registered listeners...\n\n";

        $allListeners = EventManager::listeners();

        echo "REGISTERED EVENT LISTENERS SUMMARY:\n";
        echo str_repeat("-", 50) . "\n";

        foreach ($allListeners as $eventName => $listeners) {
            echo "Event: '{$eventName}' ({" . count($listeners) . "} listeners)\n";

            foreach ($listeners as $listener) {
                echo "  [{$listener['type']}] Priority {$listener['priority']}: {$listener['label']}\n";
                if (!empty($listener['description'])) {
                    echo "      Description: {$listener['description']}\n";
                }
            }
            echo "\n";
        }

        echo "SPECIFIC EVENT ANALYSIS:\n";
        echo str_repeat("-", 30) . "\n";

        $demoListeners = EventManager::listeners('demo.publish_content');
        if (!empty($demoListeners['demo.publish_content'])) {
            echo "Analyzing 'demo.publish_content' event:\n";
            foreach ($demoListeners['demo.publish_content'] as $listener) {
                echo "  • {$listener['type']} (Priority {$listener['priority']}): {$listener['description']}\n";
                echo "    Callback: {$listener['label']}\n";
                echo "    File: " . ($listener['info']['file'] ?? 'Unknown') . "\n";
                echo "    Line: " . ($listener['info']['line'] ?? 'Unknown') . "\n\n";
            }
        }

        echo "This introspection is invaluable for:\n";
        echo "• Debugging event flow issues\n";
        echo "• Understanding plugin/module interactions\n";
        echo "• Optimizing event performance\n";
        echo "• Documentation generation\n\n";
    }

    private static function printStep(string $title): void
    {
        echo str_repeat("=", 60) . "\n";
        echo "STEP " . self::$stepCounter++ . ": {$title}\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// Simulate EventManager if not available (for demo purposes)
if (!class_exists('Primordyx\EventManager')) {
    echo "Note: This demo assumes EventManager is available via Primordyx framework.\n";
    echo "In a real application, ensure Primordyx is properly autoloaded.\n\n";

    // You could include a mock EventManager here for standalone testing
    // but for this demo, we'll assume the real EventManager is available
}

// Run the demo
try {
    EventManagerDemo::run();
} catch (Exception $e) {
    echo "Demo Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}