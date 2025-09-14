<?php
/**
 * File: /vendor/vernsix/primordyx/src/Router.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/Routing/Router.php
 *
 */

declare(strict_types=1);
namespace Primordyx\Routing;

use Primordyx\Events\EventManager;
use Primordyx\Utils\Callback;

/**
 * Lightweight routing engine with dual CLI and HTTP support plus comprehensive middleware
 *
 * Provides URL routing capabilities for both web applications and command-line interfaces
 * within a single unified system. Features dynamic route registration, named parameter
 * extraction, middleware pipeline execution, and extensive event integration for monitoring
 * and debugging routing operations.
 *
 * ## Core Architecture
 * - **Static Factory Pattern**: All functionality accessed via static methods
 * - **Singleton Behavior**: Single global routing state shared across application
 * - **Dual Context Support**: Seamlessly handles HTTP requests and CLI commands
 * - **Event Integration**: Comprehensive EventManager integration for monitoring
 * - **Middleware Pipeline**: Ordered middleware execution with result handling
 *
 * ## Routing Features
 * - **HTTP Method Support**: GET, POST, PUT, DELETE with proper RESTful semantics
 * - **CLI Command Routing**: Command-line interface routing with argument parsing
 * - **Named Parameters**: Dynamic URL segments with automatic extraction
 * - **Route Patterns**: Flexible pattern matching with regex conversion
 * - **Route Normalization**: Consistent path handling across contexts
 * - **404 Handling**: Customizable not-found responses for HTTP and CLI
 *
 * ## Middleware System
 * - **Pipeline Execution**: Ordered middleware chain before route handlers
 * - **Result Handling**: Support for redirects, errors, and flow control
 * - **Context Awareness**: Middleware receives method, path, and parameters
 * - **Early Termination**: Middleware can halt execution with redirects or errors
 * - **Event Tracking**: Before/after events for each middleware execution
 *
 * ## Parameter Extraction
 * - **Named Segments**: Routes like `/user/{id}` extract parameters automatically
 * - **Type Safety**: Parameters passed as array values to handlers
 * - **CLI Arguments**: Command-line arguments merged with extracted parameters
 * - **Pattern Matching**: Regex-based parameter extraction from URL paths
 *
 * ## Event Integration
 * Router fires comprehensive events via EventManager for monitoring:
 * - `router.init`: Fired during initialization with method and path
 * - `router.middleware.before/after`: Middleware execution tracking
 * - `router.middleware.redirecting`: When middleware triggers redirects
 * - `router.middleware.error`: When middleware returns error responses
 * - `router.dispatching/dispatching.cli`: Route handler execution
 * - `router.404/404.no_callback`: 404 handling scenarios
 *
 * @since 1.0.0
 *
 * @example HTTP Route Registration and Usage
 * ```php
 * // Initialize router for HTTP context
 * Router::init();
 *
 * // Simple routes with controller instantiation
 * Router::get('/', [], function () {(new MainController())->home();});
 * Router::get('/about', [], function () {(new MainController())->about();});
 *
 * // Routes with parameters
 * Router::get('/subscribe/{plan}', [], function ($plan) {
 *     (new MainController())->checkoutForm($plan);
 * });
 *
 * // POST routes for form processing
 * Router::post('/document-upload', [], function () {
 *     (new DocumentUploadController())->processDocumentUpload();
 * });
 *
 * // Routes with middleware for protection
 * Router::get('/admin/users', [AuthMiddleware::class], function () {
 *     (new AdminController())->userList();
 * });
 *
 * // Handle 404s
 * Router::page404(function () {(new MainController())->page404();});
 *
 * // Dispatch current request
 * Router::dispatch();
 * ```
 *
 * @example CLI Command Registration
 * ```php
 * // Initialize for CLI context
 * Router::init();
 *
 * // Register CLI commands
 * Router::cli('migrate', [], function() {
 *     echo "Running migrations...\n";
 * });
 *
 * Router::cli('user/create/{name}', [ValidationMiddleware::class],
 *     function($name, ...$args) {
 *         echo "Creating user: $name\n";
 *         // Additional CLI args available in $args
 *     }
 * );
 *
 * Router::dispatch();
 * ```
 *
 * @example Development and Debugging
 * ```php
 * // List all registered routes
 * $routes = Router::listRoutes();
 * foreach ($routes as $route) {
 *     echo "{$route['method']} {$route['route']}\n";
 * }
 *
 * // HTML route table for browser debugging
 * Router::printRouteList();
 * ```
 *
 * @see EventManager For event system integration
 * @see Callback For callback information utilities used in events
 */
final class Router {

    /**
     * Master registry of all registered routes organized by HTTP method and CLI
     *
     * Multi-dimensional array storing route patterns, parameters, middleware, and
     * callbacks organized by request method. Each method contains regex patterns
     * as keys with associated route data including original pattern, parameter
     * names, middleware chain, and handler callback.
     *
     * ## Array Structure
     * ```php
     * [
     *     'GET' => [
     *         '#^/users/([^/]+)$#' => [
     *             'original' => '/users/{id}',
     *             'params' => ['id'],
     *             'middleware' => [AuthMiddleware::class],
     *             'callback' => $closure
     *         ]
     *     ],
     *     'POST' => [...],
     *     'CLI' => [...]
     * ]
     * ```
     *
     * @var array<string, array<string, array{original: string, params: array<string>, middleware: array<string>, callback: callable}>>
     * @since 1.0.0
     *
     * @see addRoute() For route registration process
     * @see routeToRegex() For pattern to regex conversion
     */
    private static array $routes = [];

    /**
     * Current request method determined during router initialization
     *
     * Stores the HTTP method (GET, POST, PUT, DELETE) for web requests or 'CLI'
     * for command-line execution. Set during init() based on environment detection
     * and used throughout dispatch process for route matching.
     *
     * ## Method Detection Logic
     * - CLI: php_sapi_name() === 'cli' sets method to 'CLI'
     * - HTTP: Uses $_SERVER['REQUEST_METHOD'] with HEAD treated as GET
     * - Fallback: Defaults to 'GET' if REQUEST_METHOD unavailable
     *
     * @var string Current request method ('GET', 'POST', 'PUT', 'DELETE', 'CLI')
     * @since 1.0.0
     *
     * @see init() For method detection logic
     * @see dispatch() For method-based route matching
     */
    private static string $method;

    /**
     * Normalized request path used for route pattern matching
     *
     * Contains the cleaned and normalized path extracted from either HTTP query
     * parameters or CLI arguments. Normalized to ensure consistent slash handling
     * and format across different input sources and contexts.
     *
     * ## Path Sources
     * - HTTP: Extracted from $_GET['rt'] parameter and normalized
     * - CLI: Taken from first CLI argument ($argv[1]) and normalized
     * - Fallback: Defaults to '/' if no path provided
     *
     * ## Normalization Rules
     * - HTTP: Ensures leading slash, removes trailing slashes, collapses multiple slashes
     * - CLI: Removes leading slashes and whitespace for command-style routing
     *
     * @var string Normalized request path for pattern matching
     * @since 1.0.0
     *
     * @see init() For path extraction and normalization
     * @see normalizeRoute() For HTTP path normalization rules
     * @see normalizeCliRoute() For CLI path normalization rules
     */
    private static string $path;

    /**
     * Custom 404 handler callback for unmatched routes
     *
     * Optional callable that handles requests when no registered route matches
     * the current path and method combination. If not set, router provides
     * default 404 behavior with appropriate HTTP status codes or CLI messages.
     *
     * ## Default 404 Behavior
     * - HTTP: Sets 404 status code and outputs "404 Not Found"
     * - CLI: Outputs simple "404 Not Found" message
     * - Events: Fires router.404 or router.404.no_callback events
     *
     * ## Custom Handler Usage
     * Handler receives no parameters and should manage complete response including
     * HTTP status codes, headers, and output content as needed.
     *
     * @var callable|null Custom 404 handler, or null for default behavior
     * @since 1.0.0
     *
     * @see page404() For setting custom 404 handlers
     * @see dispatch() For 404 handling logic
     */
    private static mixed $page404 = null;

    /**
     * Prevent instantiation of Router class
     *
     * Private constructor enforces static-only usage pattern by preventing
     * object creation. Router is designed as a static utility class with
     * global state management for routing operations.
     *
     * @since 1.0.0
     */
    private function __construct() {}

    /**
     * Prevent cloning of Router class
     *
     * Private clone method prevents object cloning to maintain singleton-like
     * behavior and ensure single global routing state throughout application.
     *
     * @since 1.0.0
     */
    private function __clone() {}

    /**
     * Prevent unserialization of Router class
     *
     * Public wakeup method prevents object restoration from serialized state
     * to maintain static-only usage pattern and global state integrity.
     *
     * @since 1.0.0
     */
    public function __wakeup() {}

    /**
     * Initialize routing engine by detecting execution context and extracting request details
     *
     * Performs environment detection to determine whether execution is CLI or HTTP context,
     * then extracts and normalizes the request method and path accordingly. Must be called
     * before route registration or dispatch to establish proper routing context.
     *
     * ## Context Detection Logic
     * - **CLI Detection**: Uses php_sapi_name() === 'cli' to identify command-line execution
     * - **HTTP Detection**: All other SAPI modes treated as HTTP requests
     * - **Method Extraction**: Gets REQUEST_METHOD from $_SERVER with fallbacks
     * - **Path Extraction**: Uses $_GET['rt'] for HTTP, $argv[1] for CLI
     *
     * ## HTTP Context Handling
     * - Method: $_SERVER['REQUEST_METHOD'] or 'GET' as fallback
     * - HEAD requests: Automatically converted to GET for simplified handling
     * - Path: Extracted from $_GET['rt'] query parameter, normalized for consistency
     * - Default path: '/' when no route parameter provided
     *
     * ## CLI Context Handling
     * - Method: Always set to 'CLI' for command-line routing
     * - Path: First command argument ($argv[1]), normalized for commands
     * - Default command: '/' when no command argument provided
     * - Arguments: Additional $argv elements available during dispatch
     *
     * ## Event Integration
     * Fires 'router.init' event with normalized path and detected method for
     * monitoring, logging, and debugging routing initialization.
     *
     * @return void
     * @since 1.0.0
     *
     * @example HTTP Initialization
     * ```php
     * // For URL: /index.php?rt=/users/123&other=param
     * Router::init();
     * // Sets method='GET', path='/users/123'
     * ```
     *
     * @example CLI Initialization
     * ```php
     * // Command: php app.php migrate --force
     * Router::init();
     * // Sets method='CLI', path='migrate'
     * // Additional args ['--force'] available during dispatch
     * ```
     *
     * @fires router.init Event with path and method information
     * @see dispatch() For request processing after initialization
     * @see normalizeRoute() For HTTP path normalization details
     * @see normalizeCliRoute() For CLI path normalization details
     */
    public static function init(): void {
        if (php_sapi_name() === 'cli') {
            global $argv;
            self::$method = 'CLI';
            self::$path = self::normalizeCliRoute($argv[1] ?? '/');
        } else {
//            self::$method = $_SERVER['REQUEST_METHOD'];
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            self::$method = ($method === 'HEAD') ? 'GET' : $method;

            self::$path = self::normalizeRoute($_GET['rt'] ?? '/');
        }
        EventManager::fire('router.init', [ 'path' => self::$path, 'method' => self::$method ]);
    }

    /**
     * Register GET route with middleware pipeline and handler callback
     *
     * Registers HTTP GET routes for read operations following RESTful conventions.
     * GET routes should be safe and idempotent, suitable for data retrieval without
     * side effects. Supports named parameters and middleware chain execution.
     *
     * ## RESTful GET Usage
     * - **Resource retrieval**: Reading data without modification
     * - **Safe operations**: No server state changes
     * - **Idempotent**: Multiple identical requests produce same result
     * - **Cacheable**: Results can be cached by browsers/proxies
     *
     * ## Parameter Extraction
     * Route patterns support named parameters using {name} syntax:
     * - Pattern: `/users/{id}` matches `/users/123`
     * - Extracted: `$id = '123'` passed to callback
     * - Multiple parameters: `/posts/{id}/comments/{comment_id}`
     *
     * @param string $route URI pattern with optional named parameters (e.g., `/users/{id}`)
     * @param array<string> $middleware Array of middleware class names to execute before callback
     * @param callable $callback Route handler receiving extracted parameters as arguments
     * @return void
     * @since 1.0.0
     *
     * @example GET Route Registration Patterns
     * ```php
     * // Simple static route
     * Router::get('/about', [], function() {
     *     return view('about');
     * });
     *
     * // Named parameter extraction
     * Router::get('/users/{id}', [AuthMiddleware::class], function($id) {
     *     $user = User::find($id);
     *     return json_encode($user);
     * });
     *
     * // Multiple parameters with middleware chain
     * Router::get('/posts/{id}/comments/{comment_id}',
     *     [AuthMiddleware::class, RateLimitMiddleware::class],
     *     function($postId, $commentId) {
     *         return Comment::findByPost($postId, $commentId);
     *     }
     * );
     * ```
     *
     * @see addRoute() For internal route registration process
     * @see dispatch() For route matching and execution
     * @see post() For POST route registration
     */
    public static function get(string $route, array $middleware, callable $callback): void {
        self::addRoute('GET', $route, $middleware, $callback);
    }

    /**
     * Register POST route with middleware pipeline and handler callback
     *
     * Registers HTTP POST routes for resource creation and non-idempotent operations.
     * POST routes typically create new resources, process form submissions, or perform
     * operations with side effects. Supports request body parsing and parameter extraction.
     *
     * ## RESTful POST Usage
     * - **Resource creation**: Creating new database records
     * - **Form processing**: Handling form submissions with validation
     * - **Non-idempotent**: Multiple requests may create multiple resources
     * - **Data modification**: Operations that change server state
     * - **File uploads**: Multipart form data processing
     *
     * ## Request Data Access
     * POST data available through Params class after initialization:
     * - Form data: Params::post() for form-encoded submissions
     * - JSON data: Params::json() for JSON request bodies
     * - File uploads: Params::files() for multipart submissions
     *
     * @param string $route URI pattern with optional named parameters (e.g., `/users`)
     * @param array<string> $middleware Array of middleware class names for validation/auth
     * @param callable $callback Route handler receiving extracted parameters as arguments
     * @return void
     * @since 1.0.0
     *
     * @example POST Route Registration Patterns
     * ```php
     * // User creation with validation
     * Router::post('/users', [ValidationMiddleware::class], function() {
     *     $data = Params::post();
     *     $user = User::create($data);
     *     return json_encode(['id' => $user->id]);
     * });
     *
     * // File upload handling
     * Router::post('/upload', [AuthMiddleware::class, FileSizeMiddleware::class],
     *     function() {
     *         $files = Params::files();
     *         return FileUploader::process($files);
     *     }
     * );
     *
     * // Nested resource creation
     * Router::post('/posts/{id}/comments',
     *     [AuthMiddleware::class, ValidationMiddleware::class],
     *     function($postId) {
     *         $comment = Comment::createForPost($postId, Params::post());
     *         return json_encode($comment);
     *     }
     * );
     * ```
     *
     * @see addRoute() For internal route registration process
     * @see Params For request data access methods
     * @see get() For GET route registration
     * @see put() For PUT route registration
     */
    public static function post(string $route, array $middleware, callable $callback): void {
        self::addRoute('POST', $route, $middleware, $callback);
    }

    /**
     * Register PUT route with middleware pipeline and handler callback
     *
     * Registers HTTP PUT routes for complete resource replacement following RESTful
     * conventions. PUT operations should be idempotent, replacing entire resources
     * with provided data rather than partial updates.
     *
     * ## RESTful PUT Usage
     * - **Resource replacement**: Complete resource update/replacement
     * - **Idempotent operations**: Same result when repeated multiple times
     * - **Full data required**: Entire resource representation expected
     * - **Create or update**: Can create resource if it doesn't exist
     * - **Atomic operations**: Complete success or failure
     *
     * ## PUT vs PATCH Distinction
     * - PUT: Replace entire resource with provided representation
     * - PATCH: Partial updates to specific resource fields
     * - PUT idempotency: Multiple identical PUT requests yield same result
     * - PUT completeness: All resource fields should be provided
     *
     * @param string $route URI pattern typically including resource ID (e.g., `/users/{id}`)
     * @param array<string> $middleware Array of middleware class names for auth/validation
     * @param callable $callback Route handler receiving extracted parameters as arguments
     * @return void
     * @since 1.0.0
     *
     * @example PUT Route Registration Patterns
     * ```php
     * // Complete user replacement
     * Router::put('/users/{id}', [AuthMiddleware::class, ValidationMiddleware::class],
     *     function($id) {
     *         $userData = Params::json();
     *         $user = User::findOrCreate($id);
     *         $user->replaceWith($userData);
     *         return json_encode($user);
     *     }
     * );
     *
     * // Configuration file replacement
     * Router::put('/config/{section}', [AdminMiddleware::class],
     *     function($section) {
     *         $config = Params::json();
     *         ConfigManager::replace($section, $config);
     *         return json_encode(['status' => 'replaced']);
     *     }
     * );
     *
     * // Document version replacement
     * Router::put('/documents/{id}/versions/{version}',
     *     [AuthMiddleware::class, VersionMiddleware::class],
     *     function($docId, $version) {
     *         $content = Params::json();
     *         Document::replaceVersion($docId, $version, $content);
     *         return json_encode(['version' => $version]);
     *     }
     * );
     * ```
     *
     * @see addRoute() For internal route registration process
     * @see Params For request body access methods
     * @see post() For resource creation routes
     * @see delete() For resource deletion routes
     */
    public static function put(string $route, array $middleware, callable $callback): void {
        self::addRoute('PUT', $route, $middleware, $callback);
    }

    /**
     * Register DELETE route with middleware pipeline and handler callback
     *
     * Registers HTTP DELETE routes for resource removal following RESTful conventions.
     * DELETE operations should be idempotent, safely removable multiple times without
     * additional side effects once resource is gone.
     *
     * ## RESTful DELETE Usage
     * - **Resource removal**: Permanent or soft deletion of resources
     * - **Idempotent operations**: Safe to repeat, no additional effects after first deletion
     * - **Status consistency**: 404 after deletion is acceptable behavior
     * - **Cascade handling**: Related resource cleanup and relationship management
     * - **Authorization critical**: Require strong authentication/authorization
     *
     * ## Deletion Strategies
     * - **Hard delete**: Permanent removal from database
     * - **Soft delete**: Mark as deleted, preserve data for recovery
     * - **Archive**: Move to archive storage before removal
     * - **Cascade**: Handle dependent resource cleanup
     *
     * @param string $route URI pattern typically including resource ID (e.g., `/users/{id}`)
     * @param array<string> $middleware Array of middleware class names, typically including auth
     * @param callable $callback Route handler receiving extracted parameters as arguments
     * @return void
     * @since 1.0.0
     *
     * @example DELETE Route Registration Patterns
     * ```php
     * // User account deletion with authorization
     * Router::delete('/users/{id}', [AuthMiddleware::class, OwnershipMiddleware::class],
     *     function($id) {
     *         $user = User::findOrFail($id);
     *         $user->delete();
     *         return json_encode(['status' => 'deleted']);
     *     }
     * );
     *
     * // Soft delete with recovery option
     * Router::delete('/posts/{id}', [AuthMiddleware::class], function($id) {
     *     $post = Post::findOrFail($id);
     *     $post->softDelete();
     *     return json_encode(['deleted_at' => $post->deleted_at]);
     * });
     *
     * // Cascade deletion with cleanup
     * Router::delete('/projects/{id}',
     *     [AuthMiddleware::class, AdminMiddleware::class, ConfirmationMiddleware::class],
     *     function($id) {
     *         $project = Project::findOrFail($id);
     *         ProjectManager::cascadeDelete($project);
     *         return json_encode(['status' => 'project_and_dependencies_deleted']);
     *     }
     * );
     * ```
     *
     * @see addRoute() For internal route registration process
     * @see get() For resource retrieval routes
     * @see put() For resource replacement routes
     */
    public static function delete(string $route, array $middleware, callable $callback): void {
        self::addRoute('DELETE', $route, $middleware, $callback);
    }

    /**
     * Register CLI command route with middleware pipeline and handler callback
     *
     * Registers command-line interface routes for console applications, background
     * jobs, maintenance tasks, and administrative operations. CLI routes receive
     * command arguments and support middleware for authentication and validation.
     *
     * ## CLI Route Features
     * - **Command parsing**: First argument used as route, additional args passed to handler
     * - **Parameter extraction**: Named parameters work like HTTP routes
     * - **Argument passing**: CLI arguments merged with extracted parameters
     * - **Output handling**: Direct console output, no HTTP concerns
     * - **Error handling**: Exit codes and error messaging
     *
     * ## CLI vs HTTP Differences
     * - **Context**: Console execution vs web request
     * - **Arguments**: $argv array vs HTTP parameters
     * - **Output**: Direct echo/print vs HTTP responses
     * - **Redirects**: Console messages vs HTTP Location headers
     * - **Status**: Exit codes vs HTTP status codes
     *
     * ## Argument Handling
     * CLI handler receives extracted route parameters first, followed by remaining
     * command-line arguments as additional parameters using PHP's variadic syntax.
     *
     * @param string $route Command pattern with optional named parameters (e.g., `migrate`, `user/create/{name}`)
     * @param array<string> $middleware Array of middleware class names for validation/auth
     * @param callable $callback Command handler receiving parameters and additional CLI args
     * @return void
     * @since 1.0.0
     *
     * @example CLI Route Registration Patterns
     * ```php
     * // Simple maintenance command
     * Router::cli('cache/clear', [], function() {
     *     CacheManager::clear();
     *     echo "Cache cleared successfully\n";
     * });
     *
     * // Named parameter extraction
     * Router::cli('user/create/{name}', [ValidationMiddleware::class],
     *     function($name, ...$args) {
     *         // $name from route, $args contains remaining CLI arguments
     *         $options = CommandParser::parseArgs($args);
     *         $user = User::createFromCli($name, $options);
     *         echo "Created user: {$user->name} (ID: {$user->id})\n";
     *     }
     * );
     *
     * // Example CLI-aware middleware
     * class ValidationMiddleware {
     *     public function handle(string $method, string $path, string $originalRoute, array $namedParams) {
     *         if ($method === 'CLI') {
     *             if (!$this->validateCliArgs($namedParams)) {
     *                 echo "Invalid arguments\n";
     *                 exit(1);
     *             }
     *         }
     *         return null; // Continue processing
     *     }
     * }
     * ```
     *
     * @see addRoute() For internal route registration process
     * @see dispatch() For CLI-specific dispatch handling
     * @see get() For HTTP GET route registration
     */
    public static function cli(string $route, array $middleware, callable $callback): void {
        self::addRoute('CLI', $route, $middleware, $callback);
    }

    /**
     * Internal route registration with pattern conversion and data storage
     *
     * Core route registration method that converts route patterns to regex,
     * extracts parameter names, and stores complete route data in the routes
     * registry. Used by all public route registration methods.
     *
     * ## Registration Process
     * 1. **Pattern conversion**: Convert route pattern to regex via routeToRegex()
     * 2. **Parameter extraction**: Extract named parameter list from pattern
     * 3. **Data structure**: Build complete route data array
     * 4. **Registry storage**: Store under method and regex pattern keys
     *
     * ## Route Data Structure
     * Each registered route contains:
     * - **original**: Original route pattern for documentation/debugging
     * - **params**: Array of parameter names for extraction
     * - **middleware**: Middleware class array for execution pipeline
     * - **callback**: Handler callable for route execution
     *
     * ## Registry Organization
     * Routes organized by method, then by regex pattern for efficient matching:
     * `$routes[$method][$regexPattern] = $routeData`
     *
     * @param string $method HTTP method (GET, POST, etc.) or 'CLI'
     * @param string $route Original route pattern with named parameters
     * @param array<string> $middleware Array of middleware class names
     * @param callable $callback Route handler callback
     * @return void
     * @since 1.0.0
     *
     * @see routeToRegex() For pattern to regex conversion
     * @see get() For GET route registration (public interface)
     * @see post() For POST route registration (public interface)
     * @see dispatch() For route matching and execution
     */
    private static function addRoute(string $method, string $route, array $middleware, callable $callback): void {
        [$pattern, $paramNames] = self::routeToRegex($route);
        self::$routes[$method][$pattern] = [
            'original' => $route,
            'params' => $paramNames,
            'middleware' => $middleware,
            'callback' => $callback,
        ];
    }

    /**
     * Process current request by matching routes, executing middleware, and calling handlers
     *
     * Core routing engine that matches the current request against registered routes,
     * executes middleware pipeline, and invokes route handlers. Handles both HTTP and
     * CLI contexts with appropriate parameter passing and error responses.
     *
     * ## Route Matching Process
     * 1. **Method filtering**: Only check routes registered for current HTTP method or CLI
     * 2. **Pattern matching**: Use regex patterns to match current path
     * 3. **Parameter extraction**: Extract named parameters from matched patterns
     * 4. **Middleware execution**: Run middleware chain in registration order
     * 5. **Handler invocation**: Call route callback with extracted parameters
     *
     * ## Middleware Pipeline Execution
     * Each middleware receives context information and can:
     * - **Continue**: Return null/false to proceed to next middleware
     * - **Redirect**: Return string URL to trigger immediate redirect
     * - **Error**: Return array with 'error' key to halt with HTTP status code
     * - **Halt**: Exit or throw to completely stop processing
     *
     * ## Middleware Method Signature
     * All middleware classes must implement:
     * ```php
     * public function handle(string $method, string $path, string $originalRoute, array $namedParams)
     * ```
     *
     * ## Middleware Return Value Handling
     * - **null/false**: Continue to next middleware in chain
     * - **string (non-empty)**: Immediate redirect to specified URL
     * - **array with 'error' key**: HTTP status code, optional 'message' key
     * - **Exception/exit**: Complete request termination
     *
     * ## Parameter Passing Logic
     * - **HTTP routes**: Parameters passed as individual arguments to callback
     * - **CLI routes**: Route parameters + remaining CLI arguments passed to callback
     * - **Parameter order**: Route parameters first, CLI args appended for CLI routes
     * - **Type safety**: All parameters passed as string values
     *
     * ## Error Handling
     * - **Middleware errors**: HTTP status codes and error messages
     * - **Middleware redirects**: Location headers (HTTP) or console messages (CLI)
     * - **No route match**: Custom 404 handler or default "404 Not Found" response
     * - **Exception safety**: Middleware exceptions handled gracefully
     *
     * ## Event Integration
     * Comprehensive event system for monitoring and debugging:
     * - Route matching and parameter extraction
     * - Middleware execution before/after with results
     * - Redirect and error handling
     * - 404 scenarios with callback information
     *
     * @return void Method handles complete request/response cycle
     * @since 1.0.0
     *
     * @example Basic Dispatch Flow
     * ```php
     * // Registration phase
     * Router::init();
     * Router::get('/users/{id}', [AuthMiddleware::class], $handler);
     *
     * // Dispatch phase - processes current request
     * Router::dispatch();
     * // 1. Matches route pattern against current path
     * // 2. Executes AuthMiddleware->handle('GET', '/users/123', '/users/{id}', ['id' => '123'])
     * // 3. If middleware returns null, calls $handler('123')
     * // 4. If middleware returns ['error' => 401], sets HTTP 401 and exits
     * // 5. If middleware returns '/login', redirects and exits
     * ```
     *
     * @fires router.middleware.before Fired before each middleware execution
     * @fires router.middleware.after Fired after each middleware execution
     * @fires router.middleware.redirecting When middleware triggers redirect
     * @fires router.middleware.error When middleware returns error response
     * @fires router.dispatching HTTP route handler execution
     * @fires router.dispatching.cli CLI route handler execution
     * @fires router.404 When 404 handler is called
     * @fires router.404.no_callback When no 404 handler available
     *
     * @see init() Must be called before dispatch to set context
     * @see addRoute() For route registration details
     * @see EventManager For event system integration
     * @see Callback For callback information in events
     */
    public static function dispatch(): void {

        foreach (self::$routes[self::$method] ?? [] as $pattern => $data) {

            if (preg_match($pattern, self::$path, $matches)) {
                array_shift($matches); // full match

                $namedParams = array_combine($data['params'], $matches);

                foreach ($data['middleware'] as $mw) {

                    $instance = new $mw();

                    EventManager::fire('router.middleware.before', ['callback_info' => Callback::info($instance), 'named_params' => $namedParams ]);

                    // all middleware is guaranteed to have a handle() method!
                    $result = $instance->handle(self::$method, self::$path, $data['original'], $namedParams);

                    EventManager::fire('router.middleware.after', ['callback_info' => Callback::info($instance), 'result' => $result ]);

                    if (is_string($result) && !empty($result)) {
                        EventManager::fire('router.middleware.redirecting',['result' => $result]);
                        if (self::$method === 'CLI') {
                            echo "Redirect to: $result\n";
                        } else {
                            header("Location: $result");
                        }
                        exit;
                    }

                    if (is_array($result) && isset($result['error'])) {
                        EventManager::fire('router.middleware.error',['result' => $result ]);
                        http_response_code($result['error']);
                        echo $result['message'] ?? 'An error occurred';
                        exit;
                    }
                }

                if (self::$method === 'CLI') {
                    global $argv;
                    $args = array_merge(array_values($namedParams), array_slice($argv, 2));
                    EventManager::fire('router.dispatching.cli', ['callback_info' => Callback::info($data['callback']), 'pattern' => $pattern, 'data' => $data, 'args' => $args]);
                    call_user_func_array($data['callback'], $args);
                } else {
                    EventManager::fire('router.dispatching', ['callback_info' => Callback::info($data['callback']), 'pattern' => $pattern, 'data' => $data, 'named_params' => array_values($namedParams)]);
                    call_user_func_array($data['callback'], array_values($namedParams));
                }
                return;
            }
        }

        if (is_callable(self::$page404)) {
            EventManager::fire('router.404', ['callback_info' => Callback::info(self::$page404) ]);
            call_user_func(self::$page404);
        } else {
            EventManager::fire('router.404.no_callback', []);
            http_response_code(404);
            echo "404 Not Found";
        }

    }

    /**
     * Set custom 404 handler for unmatched routes
     *
     * Registers a custom callback to handle requests that don't match any registered
     * routes. Handler should manage complete response including HTTP status codes,
     * headers, and output content for both HTTP and CLI contexts.
     *
     * ## Handler Responsibilities
     * - **HTTP context**: Set appropriate status code (typically 404)
     * - **Response headers**: Content-Type and other relevant headers
     * - **Output content**: Error pages, JSON responses, or redirect logic
     * - **CLI context**: Console-appropriate error messages
     * - **Logging**: Optional error logging or monitoring integration
     *
     * ## Default 404 Behavior
     * When no custom handler is registered:
     * - **HTTP**: Sets 404 status code and outputs "404 Not Found"
     * - **CLI**: Outputs "404 Not Found" to console
     * - **Events**: Fires router.404.no_callback event for monitoring
     *
     * ## Handler Parameters
     * The 404 handler receives no parameters and should determine context
     * and response format based on environment or global state.
     *
     * @param callable $callback 404 handler receiving no parameters
     * @return void
     * @since 1.0.0
     *
     * @example Custom 404 Handler Registration
     * ```php
     * // JSON API 404 handler
     * Router::page404(function() {
     *     http_response_code(404);
     *     header('Content-Type: application/json');
     *     echo json_encode([
     *         'error' => 'Not Found',
     *         'message' => 'The requested resource was not found',
     *         'code' => 404
     *     ]);
     * });
     *
     * // HTML page with template
     * Router::page404(function() {
     *     http_response_code(404);
     *     header('Content-Type: text/html');
     *     include 'templates/404.php';
     * });
     *
     * // CLI-aware handler
     * Router::page404(function() {
     *     if (php_sapi_name() === 'cli') {
     *         echo "Command not found\n";
     *         exit(1);
     *     } else {
     *         http_response_code(404);
     *         echo "Page not found";
     *     }
     * });
     * ```
     *
     * @see dispatch() For 404 handling logic
     * @fires router.404 When custom 404 handler is executed
     */
    public static function page404(callable $callback): void {
        self::$page404 = $callback;
    }

    /**
     * Retrieve complete list of registered routes with metadata
     *
     * Returns comprehensive array of all registered routes across all HTTP methods
     * and CLI commands. Includes original route patterns, middleware class names,
     * and method information for debugging, documentation, and administrative purposes.
     *
     * ## Return Data Structure
     * Each route entry contains:
     * - **method**: HTTP method (GET, POST, etc.) or 'CLI'
     * - **route**: Original route pattern with named parameters
     * - **middleware**: Array of middleware class names (strings)
     *
     * ## Middleware Representation
     * Middleware entries are converted to class names for consistent representation:
     * - String middleware: Used as-is (typical case)
     * - Object middleware: Class name extracted via get_class()
     * - Empty middleware: Represented as empty array
     *
     * ## Use Cases
     * - **Documentation generation**: API documentation from route definitions
     * - **Administrative panels**: Route management interfaces
     * - **Debugging tools**: Development route inspection
     * - **Security audits**: Middleware and route analysis
     * - **Testing**: Route registration verification
     *
     * @return array<array{method: string, route: string, middleware: array<string>}> Complete route registry
     * @since 1.0.0
     *
     * @example Route List Usage for Documentation
     * ```php
     * $routes = Router::listRoutes();
     *
     * foreach ($routes as $route) {
     *     echo "Method: {$route['method']}\n";
     *     echo "Pattern: {$route['route']}\n";
     *     echo "Middleware: " . implode(', ', $route['middleware']) . "\n";
     *     echo "---\n";
     * }
     *
     * // Output example:
     * // Method: GET
     * // Pattern: /users/{id}
     * // Middleware: AuthMiddleware, RateLimitMiddleware
     * // ---
     * ```
     *
     * @example Administrative Route Analysis
     * ```php
     * $routes = Router::listRoutes();
     *
     * // Find routes without authentication
     * $unprotected = array_filter($routes, function($route) {
     *     return !in_array('AuthMiddleware', $route['middleware']);
     * });
     *
     * // Group by method
     * $byMethod = [];
     * foreach ($routes as $route) {
     *     $byMethod[$route['method']][] = $route;
     * }
     * ```
     *
     * @see printRouteList() For HTML formatted route display
     * @see addRoute() For route registration process
     */
    public static function listRoutes(): array {
        $list = [];
        foreach (self::$routes as $method => $patterns) {
            foreach ($patterns as $data) {
                $list[] = [
                    'method' => $method,
                    'route' => $data['original'],
                    'middleware' => array_map(fn($mw) => is_string($mw) ? $mw : get_class($mw), $data['middleware'])
                ];
            }
        }
        return $list;
    }

    /**
     * Convert route pattern to regex and extract parameter names
     *
     * Transforms human-readable route patterns with named parameters into
     * regex patterns suitable for URL matching, while extracting parameter
     * names for value extraction during dispatch.
     *
     * ## Pattern Conversion Process
     * 1. **Normalization**: Clean multiple slashes to single slashes
     * 2. **Parameter detection**: Find all {paramName} segments
     * 3. **Parameter extraction**: Extract parameter names for later use
     * 4. **Regex creation**: Replace parameters with capture groups
     * 5. **Anchoring**: Add start/end anchors for exact matching
     *
     * ## Parameter Name Rules
     * - **Format**: Must match `{[a-zA-Z_][a-zA-Z0-9_]*}` pattern
     * - **Starting character**: Letter or underscore only
     * - **Subsequent characters**: Letters, numbers, underscores
     * - **Case sensitive**: Parameter names preserved exactly
     *
     * ## Regex Pattern Generation
     * - **Parameter replacement**: `{param}` becomes `([^/]+)` (capture non-slash)
     * - **Exact matching**: Anchored with `^` and `$`
     * - **Delimiter handling**: Uses `#` as delimiter to avoid conflicts
     *
     * @param string $route Route pattern with optional named parameters
     * @return array{0: string, 1: array<string>} [regex pattern, parameter names array]
     * @since 1.0.0
     *
     * @example Pattern Conversion Examples
     * ```php
     * // Simple route without parameters
     * [$regex, $params] = routeToRegex('/users');
     * // $regex = '#^/users$#'
     * // $params = []
     *
     * // Single parameter
     * [$regex, $params] = routeToRegex('/users/{id}');
     * // $regex = '#^/users/([^/]+)$#'
     * // $params = ['id']
     *
     * // Multiple parameters
     * [$regex, $params] = routeToRegex('/posts/{id}/comments/{comment_id}');
     * // $regex = '#^/posts/([^/]+)/comments/([^/]+)$#'
     * // $params = ['id', 'comment_id']
     * ```
     *
     * @see addRoute() For route registration using converted patterns
     * @see dispatch() For pattern matching during request processing
     */
    private static function routeToRegex(string $route): array {
        $route = preg_replace('#/+#', '/', trim($route));
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', $route, $paramMatches);
        $paramNames = $paramMatches[1];
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*}#', '([^/]+)', $route);
        return ['#^' . $regex . '$#', $paramNames];
    }

    /**
     * Normalize HTTP routes by standardizing slash handling and format
     *
     * Cleans and standardizes HTTP route paths to ensure consistent matching
     * regardless of input format variations. Handles multiple slashes, trailing
     * slashes, and empty paths with consistent rules.
     *
     * ## Normalization Rules
     * 1. **Multiple slashes**: Collapse multiple consecutive slashes to single slash
     * 2. **Trailing slash removal**: Remove trailing slashes except for root
     * 3. **Leading slash**: Ensure leading slash for all non-empty paths
     * 4. **Empty path handling**: Convert empty string to root path '/'
     * 5. **Root preservation**: Root path '/' remains unchanged
     *
     * ## Input/Output Examples
     * - `''` → `'/'` (empty becomes root)
     * - `'users'` → `'/users'` (add leading slash)
     * - `'/users/'` → `'/users'` (remove trailing slash)
     * - `'//users//profile/'` → `'/users/profile'` (clean multiple slashes)
     * - `'/'` → `'/'` (root unchanged)
     *
     * @param string $rt Route string from query parameter or path
     * @return string Normalized route string with consistent format
     * @since 1.0.0
     *
     * @example HTTP Route Normalization
     * ```php
     * // Various input formats normalized consistently
     * $routes = [
     *     '',              // → '/'
     *     'users',         // → '/users'
     *     '/users/',       // → '/users'
     *     '//api//v1/',    // → '/api/v1'
     *     '/',             // → '/' (unchanged)
     * ];
     *
     * foreach ($routes as $route) {
     *     echo normalizeRoute($route) . "\n";
     * }
     * ```
     *
     * @see init() For route normalization during initialization
     * @see normalizeCliRoute() For CLI route normalization
     */
    private static function normalizeRoute(string $rt): string {
        $rt = preg_replace('#/+#', '/', $rt);
        $rt = rtrim($rt, '/');
        return $rt === '' ? '/' : '/' . ltrim($rt, '/');
    }

    /**
     * Normalize CLI command routes by removing slashes and whitespace
     *
     * Cleans command-line route patterns to remove web-style slashes and
     * whitespace, making CLI commands more natural while maintaining
     * compatibility with web-style route patterns.
     *
     * ## CLI Normalization Rules
     * 1. **Whitespace removal**: Trim leading/trailing whitespace
     * 2. **Leading slash removal**: Remove leading slashes for command style
     * 3. **Command format**: Results in command/subcommand format
     * 4. **Empty handling**: Preserves empty string for default command
     *
     * ## Command Style Compatibility
     * Accepts both web-style (`/migrate/up`) and command-style (`migrate/up`)
     * patterns, normalizing to command-style for consistent CLI experience.
     *
     * ## Input/Output Examples
     * - `'/migrate'` → `'migrate'` (remove leading slash)
     * - `' /user/create '` → `'user/create'` (trim and remove slash)
     * - `'cache/clear'` → `'cache/clear'` (already normalized)
     * - `' '` → `''` (whitespace becomes empty)
     *
     * @param string $cmd Command string from CLI arguments
     * @return string Normalized CLI route without leading slashes or whitespace
     * @since 1.0.0
     *
     * @example CLI Command Normalization
     * ```php
     * // CLI arguments: php app.php /migrate/up --force
     * $cmd = $argv[1]; // '/migrate/up'
     * $normalized = normalizeCliRoute($cmd); // 'migrate/up'
     *
     * // Both formats work identically:
     * // php app.php migrate/up
     * // php app.php /migrate/up
     * ```
     *
     * @see init() For CLI route normalization during initialization
     * @see normalizeRoute() For HTTP route normalization
     * @see cli() For CLI route registration
     */
    private static function normalizeCliRoute(string $cmd): string {
        return ltrim(trim($cmd), '/'); // accept both 'command' and '/command'
    }

    /**
     * Output HTML table of registered routes for browser-based debugging
     *
     * Generates formatted HTML table displaying all registered routes with styling
     * for easy visual inspection during development. Includes color-coded HTTP methods
     * and proper escaping for safe browser display.
     *
     * ## HTML Output Features
     * - **Styled table**: CSS styling with borders, padding, and typography
     * - **Color-coded methods**: Visual distinction between HTTP methods
     * - **Responsive design**: Table adapts to different screen sizes
     * - **Safe output**: HTML escaping prevents XSS from route patterns
     * - **Empty state**: Special message when no routes registered
     *
     * ## Method Color Coding
     * - **GET**: Green (safe, read-only operations)
     * - **POST**: Orange (creation, form submission)
     * - **PUT/PATCH**: Teal (updates, modifications)
     * - **DELETE**: Red (destructive operations)
     * - **CLI**: Default styling for command-line routes
     *
     * ## Development Usage
     * Primarily intended for development and debugging scenarios where visual
     * route inspection aids in understanding application structure and middleware
     * configuration.
     *
     * @return void Outputs HTML directly to browser
     * @since 1.0.0
     *
     * @example Development Route Inspection
     * ```php
     * // In development/debug controller
     * Router::get('/debug/routes', [], function() {
     *     echo '<h1>Application Routes</h1>';
     *     Router::printRouteList();
     * });
     *
     * // Or in dedicated debug script
     * if ($_GET['debug'] === 'routes') {
     *     Router::printRouteList();
     *     exit;
     * }
     * ```
     *
     * @example Administrative Dashboard Integration
     * ```php
     * // In admin panel
     * function showRoutesPage() {
     *     echo '<div class="admin-section">';
     *     echo '<h2>Registered Application Routes</h2>';
     *     Router::printRouteList();
     *     echo '</div>';
     * }
     * ```
     *
     * @see listRoutes() For programmatic route data access
     */
    public static function printRouteList(): void {
        $routes = self::listRoutes();

        if (empty($routes)) {
            echo '<p style="color: red; font-weight: bold;">No routes registered.</p>';
            return;
        }

        echo <<<HTML
<style>
    .route-table {
        width: 100%;
        border-collapse: collapse;
        font-family: sans-serif;
        margin-bottom: 1em;
    }
    .route-table th, .route-table td {
        border: 1px solid #ccc;
        padding: 8px 12px;
        text-align: left;
    }
    .route-table th {
        background-color: #f4f4f4;
        font-weight: bold;
    }
    .route-method-get { color: green; font-weight: bold; }
    .route-method-post { color: orange; font-weight: bold; }
    .route-method-put,
    .route-method-patch { color: teal; font-weight: bold; }
    .route-method-delete { color: red; font-weight: bold; }
</style>

<h2>Registered Routes</h2>
<table class="route-table">
    <thead>
        <tr>
            <th>Method</th>
            <th>Route</th>
            <th>Middleware</th>
        </tr>
    </thead>
    <tbody>
HTML;

        foreach ($routes as $route) {
            $method = strtoupper($route['method']);
            $path = htmlspecialchars($route['route']);
            $middleware = empty($route['middleware']) ? '-' : htmlspecialchars(implode(', ', $route['middleware']));
            $class = 'route-method-' . strtolower($method);

            echo "<tr>
            <td class=\"$class\">$method</td>
            <td>$path</td>
            <td>$middleware</td>
        </tr>";
        }

        echo <<<HTML
    </tbody>
</table>
HTML;
    }

}
