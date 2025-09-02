<?php
/**
 * File: /vendor/vernsix/primordyx/src/View.php
 *
 * @package     Primordyx
 * @author      Vern Six vernsix@gmail.com
 * @copyright   Copyright (c) 2025
 * @license     MIT License
 * @since       1.0.0
 * @version     1.0.0
 * @link        https://github.com/vernsix/primordyx/blob/master/src/View.php
 *
 */

declare(strict_types=1);

namespace Primordyx\View;

use Primordyx\Utils\Strings;
use RuntimeException;
use Throwable;

/**
 * Static templating engine with template inheritance, includes, and custom filters
 *
 * A lightweight yet powerful template engine that provides static-only access to
 * template rendering functionality with comprehensive syntax support. Designed as a
 * single static class for simplicity while offering advanced templating features
 * including layout inheritance, partials, custom filters, and dot notation variable access.
 *
 * ## Templating Features
 * - **Layout inheritance**: {{extends}} and {{section}} syntax
 * - **Variable output**: {{variable}} with optional filter chains
 * - **Control structures**: {{if}}, {{elseif}}, {{else}}, {{endif}}
 * - **Loops**: {{each item in array using 'template'}} syntax
 * - **Includes**: {{include 'template'}} for static partials
 * - **Embeds**: {{embed 'template' with [data]}} for dynamic partials
 * - **Custom filters**: Register and use custom data transformations
 * - **Dot notation**: Access nested array data with user.name syntax
 *
 * ## Static Architecture
 * All functionality is accessed through static methods with shared global state.
 * Template data, filters, sections, and configuration are maintained in static
 * properties, making the engine simple to use but requiring careful state management
 * in complex applications.
 *
 * ## Template Compilation Process
 * 1. **File Loading**: Template files loaded from configured view path
 * 2. **Syntax Compilation**: Custom template syntax compiled to PHP code
 * 3. **Variable Extraction**: Template data extracted into local scope
 * 4. **Code Execution**: Compiled PHP code executed with output buffering
 * 5. **Layout Processing**: Section content inserted into layout templates
 *
 * ## Usage Patterns
 *
 * @example Basic Template Rendering
 * ```php
 * // Set template directory and shared data
 * View::path('/app/views');
 * View::with(['user' => $userData, 'title' => 'Dashboard']);
 *
 * // Render template to browser
 * View::output('dashboard.php');
 * ```
 *
 * @example Template Inheritance
 * ```php
 * // layout.php:
 * // <html><head><title>{{title}}</title></head>
 * // <body>{{fill 'content'}}</body></html>
 *
 * // page.php:
 * // {{extends 'layout.php'}}
 * // {{section 'content'}}<h1>{{heading}}</h1>{{endsection}}
 *
 * View::with(['title' => 'My Page', 'heading' => 'Welcome']);
 * View::output('page.php');
 * ```
 *
 * @example Custom Filters
 * ```php
 * // Register custom filter
 * View::registerCustomFilter('currency', fn($val) => '$' . number_format($val, 2));
 *
 * // Use in template: {{price|currency}}
 * View::with(['price' => 29.99]);
 * View::output('product.php'); // Outputs: $29.99
 * ```
 *
 * @since 1.0.0
 */
class View
{
    /**
     * @var array<string, mixed> Shared data available to all templates
     *
     * Global template data storage that persists across template renders. All data
     * added via with() or share() methods is stored here and made available to
     * templates through variable extraction. Supports nested arrays accessible
     * via dot notation in templates.
     */
    protected static array $data = [];

    /**
     * @var array<string, callable> Custom filters registered by the user
     *
     * Registry of custom filter functions that can be applied to template variables
     * using pipe syntax. Each filter is a callable that receives the value and
     * optional parameter, returning the transformed value.
     *
     * Built-in 'default' filter is handled specially in parseExpression().
     */
    protected static array $filters = [];

    /**
     * @var array<string, string> Template sections for layout inheritance
     *
     * Storage for template sections captured during compilation. When templates
     * use {{section 'name'}}content{{endsection}} syntax, the content is stored
     * here and can be inserted into layouts using {{fill 'name'}}.
     */
    protected static array $sections = [];

    /**
     * @var string|null Current layout template
     *
     * Stores the layout template filename when a template uses {{extends 'layout'}}
     * syntax. During output(), if a layout is set, the main template content is
     * rendered first to populate sections, then the layout is rendered.
     */
    protected static ?string $layout = null;

    /**
     * @var string|null Path to view templates directory
     *
     * Base filesystem path where template files are located. All template filenames
     * passed to render() and output() methods are resolved relative to this path.
     * Must be set before rendering any templates.
     */
    protected static ?string $viewPath = null;

    /**
     * Get or set the view templates directory path
     *
     * Manages the base filesystem path where template files are located. Can function
     * as both getter and setter depending on whether a parameter is provided. All
     * template filenames in render() and output() calls are resolved relative to this path.
     *
     * ## Path Resolution
     * Template files are loaded using: `{$viewPath}/{$template}`
     *
     * Ensure the path exists and is readable before rendering templates, as file
     * not found errors will be thrown during render() if templates can't be located.
     *
     * @param string|null $newViewPath New absolute filesystem path to set, or null to just get current value
     *
     * @return string|null The previous path value (useful for temporary path changes)
     *
     * @example Setting Template Directory
     * ```php
     * // Set template directory
     * View::path('/var/www/myapp/templates');
     *
     * // Later render templates from that directory
     * View::output('home.php'); // Loads /var/www/myapp/templates/home.php
     * ```
     *
     * @example Temporary Path Change
     * ```php
     * // Save current path and temporarily change
     * $oldPath = View::path('/tmp/special-templates');
     * View::output('email.php'); // From special directory
     * View::path($oldPath); // Restore original path
     * ```
     *
     * @see View::render() Template file loading process
     * @since 1.0.0
     */
    public static function path(?string $newViewPath = null): ?string
    {
        $oldViewPath = self::$viewPath;
        if ($newViewPath !== null) {
            self::$viewPath = $newViewPath;
        }
        return $oldViewPath;
    }

    /**
     * Merge data array into shared template data
     *
     * Adds multiple key-value pairs to the global template data storage using
     * array_merge(). New data is merged with existing data, with new values
     * overriding existing keys. All merged data becomes available in templates
     * as variables and via dot notation access.
     *
     * ## Data Persistence
     * Data added via with() persists across all subsequent template renders until
     * explicitly overridden or the request ends. This makes it ideal for setting
     * common data like user information, site configuration, or shared variables.
     *
     * ## Array Merging Behavior
     * Uses array_merge() so numeric keys are reindexed, but string keys preserve
     * their associations. Nested arrays are not recursively merged.
     *
     * @param array<string, mixed> $data Associative array of data to merge into template context
     *
     * @return void
     *
     * @example Basic Data Sharing
     * ```php
     * // Set common template data
     * View::with([
     *     'siteName' => 'My Website',
     *     'user' => ['name' => 'John', 'role' => 'admin'],
     *     'navigation' => $menuItems
     * ]);
     *
     * // All templates now have access to these variables:
     * // {{siteName}}, {{user.name}}, {{user.role}}
     * ```
     *
     * @example Controller Data Pattern
     * ```php
     * // In controller, set page-specific data
     * View::with([
     *     'pageTitle' => 'User Dashboard',
     *     'userData' => $user->getData(),
     *     'stats' => $analytics->getStats()
     * ]);
     * View::output('dashboard.php');
     * ```
     *
     * @see View::share() For setting single key-value pairs
     * @see View::$data Global template data storage
     * @since 1.0.0
     */
    public static function with(array $data): void
    {
        self::$data = array_merge(self::$data, $data);
    }

    /**
     * Set a single key-value pair in shared template data
     *
     * Adds or updates a single variable in the global template data storage. More
     * convenient than with() when setting individual values, especially in conditional
     * logic or loops where you need to set data incrementally.
     *
     * ## Direct Assignment
     * Uses direct array assignment rather than merging, so it's slightly more
     * efficient for single values than with(['key' => 'value']).
     *
     * @param string $key The variable name to use in templates
     * @param mixed $value The value to assign (any type supported)
     *
     * @return void
     *
     * @example Conditional Data Setting
     * ```php
     * // Set data conditionally
     * View::share('userRole', $user->isAdmin() ? 'admin' : 'user');
     * View::share('debugMode', APP_DEBUG);
     *
     * // Use in templates: {{userRole}}, {{debugMode}}
     * ```
     *
     * @example Incremental Data Building
     * ```php
     * View::share('pageTitle', 'Products');
     *
     * if ($category) {
     *     View::share('categoryName', $category->name);
     *     View::share('breadcrumbs', $category->getBreadcrumbs());
     * }
     *
     * View::output('products.php');
     * ```
     *
     * @see View::with() For setting multiple values at once
     * @see View::$data Global template data storage
     * @since 1.0.0
     */
    public static function share(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }

    /**
     * Register a custom filter function for use in templates
     *
     * Adds a custom transformation function that can be applied to template variables
     * using pipe syntax ({{variable|filtername}} or {{variable|filtername:'param'}}).
     * Filters receive the variable value and optional parameter, returning the
     * transformed result.
     *
     * ## Filter Function Signature
     * Filters should accept (mixed $value, mixed $param = null) and return the
     * transformed value. The $param comes from template syntax like |filter:'param'.
     *
     * ## Built-in Filters
     * The 'default' filter is handled specially by the template engine and doesn't
     * need to be registered. It provides fallback values for null/empty variables.
     *
     * @param string $name Filter name to use in template pipe syntax
     * @param callable $callback Function to execute for this filter
     *
     * @return void
     *
     * @example Custom Filter Registration
     * ```php
     * // Register formatting filters
     * View::registerCustomFilter('upper', fn($val) => strtoupper($val));
     * View::registerCustomFilter('currency', fn($val) => '$' . number_format($val, 2));
     * View::registerCustomFilter('truncate', fn($val, $len) => substr($val, 0, $len) . '...');
     *
     * // Use in templates:
     * // {{name|upper}}
     * // {{price|currency}}
     * // {{description|truncate:'50'}}
     * ```
     *
     * @example Advanced Filter with Parameter
     * ```php
     * View::registerCustomFilter('pluralize', function($count, $word) {
     *     return $count . ' ' . ($count == 1 ? $word : $word . 's');
     * });
     *
     * // Template: {{itemCount|pluralize:'item'}}
     * // Output: "1 item" or "5 items"
     * ```
     *
     * @see View::applyFilter() Filter execution during template compilation
     * @see View::parseExpression() Template filter syntax parsing
     * @since 1.0.0
     */
    public static function registerCustomFilter(string $name, callable $callback): void
    {
        self::$filters[$name] = $callback;
    }

    /**
     * Render and output a template to the browser
     *
     * Main template rendering method that compiles and outputs a template directly
     * to the browser. Handles layout inheritance by first rendering the main template
     * to populate sections, then rendering the layout template if one was specified
     * via {{extends}} syntax.
     *
     * ## Layout Processing Order
     * 1. **Main Template**: Rendered first to capture sections and determine layout
     * 2. **Layout Template**: If {{extends}} was used, layout is rendered with populated sections
     * 3. **Direct Output**: If no layout, main template output is sent directly to browser
     *
     * ## Section Population
     * During main template rendering, {{section}} blocks are captured into the
     * $sections array. The layout can then insert this content using {{fill}} syntax.
     *
     * @param string $template Template filename relative to view path
     *
     * @return void
     *
     * @throws RuntimeException If template file not found or compilation/execution fails
     *
     * @example Simple Template Output
     * ```php
     * View::path('/app/views');
     * View::with(['title' => 'Home', 'user' => $userData]);
     * View::output('home.php'); // Renders and outputs directly
     * ```
     *
     * @example Layout Inheritance Output
     * ```php
     * // page.php contains: {{extends 'layout.php'}}
     * View::output('page.php');
     * // 1. Renders page.php (captures sections, sets layout)
     * // 2. Renders layout.php (inserts captured sections)
     * ```
     *
     * @see View::render() Internal rendering logic
     * @see View::compile() Template compilation process
     * @since 1.0.0
     */
    public static function output(string $template): void
    {
        $mainContent = self::render($template, self::$data);
        if (self::$layout) {
            echo self::render(self::$layout, self::$data);
        } else {
            echo $mainContent;
        }
    }

    /**
     * Render a template file with data and return compiled content
     *
     * Core template processing method that handles file loading, compilation, variable
     * extraction, and PHP code execution. Used internally by output() and recursively
     * by include/embed template syntax. Returns the rendered content as a string
     * rather than outputting directly.
     *
     * ## Processing Pipeline
     * 1. **Path Resolution**: Builds full filesystem path from view path and template name
     * 2. **File Validation**: Checks file exists and provides helpful error with available files
     * 3. **Compilation**: Converts template syntax to executable PHP code
     * 4. **Variable Extraction**: Makes template data available as local PHP variables
     * 5. **Code Execution**: Executes compiled PHP with output buffering
     * 6. **Error Handling**: Wraps execution in try-catch with detailed error information
     *
     * ## Variable Scope
     * Template data is extracted into local scope, so $data['user'] becomes $user
     * within the template. This allows natural PHP variable access alongside
     * template syntax.
     *
     * ## Error Reporting
     * Provides detailed error messages including:
     * - Full filesystem path attempted
     * - List of available files in view directory
     * - Compiled PHP code when execution fails
     *
     * @param string $template Template filename relative to view path
     * @param array<string, mixed> $vars Data to make available in template scope
     *
     * @return string Rendered template content
     *
     * @throws RuntimeException If template file not found or compilation/execution fails
     *
     * @example Internal Template Rendering
     * ```php
     * // Used by include syntax: {{include 'header.php'}}
     * $content = self::render('header.php', self::$data);
     * ```
     *
     * @example Partial Rendering with Custom Data
     * ```php
     * // Used by embed syntax: {{embed 'item.php' with ['item' => $product]}}
     * $customData = array_merge(self::$data, ['item' => $product]);
     * $content = self::render('item.php', $customData);
     * ```
     *
     * @see View::compile() Template compilation process
     * @see View::output() Public template output method
     * @since 1.0.0
     */
    protected static function render(string $template, array $vars): string
    {
        $fullPath = self::$viewPath . '/' . $template;

        if (!file_exists($fullPath)) {
            $availableFiles = array_map('basename', glob(self::$viewPath . '/*'));
            throw new RuntimeException(
                "View file not found: '$template'\n" .
                "Full path: $fullPath\n" .
                "Available files: " . implode(', ', $availableFiles)
            );
        }

        $contents = file_get_contents($fullPath);
        $compiled = self::compile($contents);

        extract($vars);
        ob_start();
        try {
            eval(' ?>' . $compiled . '<?php ');
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Eval failed in template '$template': " . $e->getMessage() .
                "\nCompiled Code:\n" . $compiled
            );
        }
        return ob_get_clean();
    }

    /**
     * Compile template syntax into executable PHP code
     *
     * Transforms custom template syntax into PHP code through a series of regex
     * replacements. Handles all template features including inheritance, control
     * structures, loops, includes, embeds, and variable output with filters.
     *
     * ## Compilation Order
     * Template syntax is processed in specific order to handle dependencies:
     * 1. **Layout inheritance**: {{extends}} and {{section}} blocks
     * 2. **Section insertion**: {{fill}} syntax for layout content
     * 3. **Control structures**: {{if}}/{{else}}/{{endif}} blocks
     * 4. **Loop syntax**: {{each}} constructs with template rendering
     * 5. **Include/Embed**: Static and dynamic partial rendering
     * 6. **Variable output**: {{variable}} with optional filter chains
     *
     * ## Each Loop Processing
     * The {{each item in array using 'template'}} syntax supports dot notation
     * for nested arrays and renders the specified template for each item with
     * the item data merged into template context.
     *
     * ## Filter Chain Support
     * Variable output supports filter chains like {{variable|filter1|filter2:'param'}}
     * which are converted to nested function calls during compilation.
     *
     * @param string $template Raw template content from file
     *
     * @return string Compiled PHP code ready for execution
     *
     * @example Template Syntax Compilation
     * ```php
     * // Input template syntax:
     * // {{extends 'layout.php'}}
     * // {{if user.isAdmin}}{{name|upper}}{{endif}}
     *
     * // Compiled PHP output:
     * // <?php if ($user['isAdmin']): ?>
     * // <?php echo strtoupper($name); ?>
     * // <?php endif; ?>
     * ```
     *
     * @see View::parseExpression() Variable and filter parsing
     * @see View::resolveVar() Dot notation resolution
     * @since 1.0.0
     */
    protected static function compile(string $template): string
    {
        // ide was giving a warning about the ternary having the same value on each side, so I rewrote it as a function
        // and kept what I originally wrote in case it gave me any issues.
        //
        // $template = preg_replace_callback("/{{\s*extends\s+'(.*?)'\s*}}/", fn($m) => (self::$layout = $m[1]) ? '' : '', $template);
        // $template = preg_replace_callback("/{{\s*section\s+'(.*?)'\s*}}(.*?){{\s*endsection\s*}}/s", fn($m) => (self::$sections[$m[1]] = $m[2]) ? '' : '', $template);
        //
        $template = preg_replace_callback("/{{\s*extends\s+'(.*?)'\s*}}/", function ($m) {
            self::$layout = $m[1];
            return '';
        }, $template);
        $template = preg_replace_callback("/{{\s*section\s+'(.*?)'\s*}}(.*?){{\s*endsection\s*}}/s", function ($m) {
            self::$sections[$m[1]] = $m[2];
            return '';
        }, $template);


        $template = preg_replace_callback("/{{\s*fill\s+'(.*?)'\s*}}/", fn($m) => self::$sections[$m[1]] ?? '', $template);

        $template = preg_replace_callback("/{{\s*if\s+(.*?)\s*}}(.*?)({{\s*elseif\s+(.*?)\s*}}(.*?))?({{\s*else\s*}}(.*?))?{{\s*endif\s*}}/s", function ($m) {
            $code = "<?php if (" . self::parseExpression($m[1]) . "): ?>{$m[2]}";
            if (!empty($m[4])) $code .= "<?php elseif (" . self::parseExpression($m[4]) . "): ?>{$m[5]}";
            if (!empty($m[7])) $code .= "<?php else: ?>{$m[7]}";
            return $code . "<?php endif; ?>";
        }, $template);

        // FIXED: Updated regex to handle dot notation in variable names (like upload_results.files)
        $template = preg_replace_callback("/{{\s*each\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+in\s+([a-zA-Z_][a-zA-Z0-9_.]*)\s+using\s+'(.*?)'\s*}}/", function ($m) {
            $itemVar = $m[1];        // e.g., "file"
            $arrayVar = $m[2];       // e.g., "upload_results.files"
            $templateFile = $m[3];   // e.g., "partials/file-item.php"

            return "<?php \$_arrayData = \\Primordyx\\View::resolveVar('{$arrayVar}'); " .
                "if (is_array(\$_arrayData)): " .
                "foreach (\$_arrayData as \${$itemVar}): ?>" .
                "<?= \\Primordyx\\View::render('{$templateFile}', array_merge(\\Primordyx\\View::\$data, ['{$itemVar}' => \${$itemVar}])) ?>" .
                "<?php endforeach; endif; ?>";
        }, $template);

        $template = preg_replace_callback("/{{\s*include\s+'(.*?)'\s*}}/", function ($m) {
            try {
                return addcslashes(self::render($m[1], self::$data), '$');
            } catch (RuntimeException $e) {
                return "<!-- Include error: {$e->getMessage()} -->";
            }
        }, $template);

        $template = preg_replace_callback("/{{\s*embed\s+'(.*?)'\s+with\s+(\[.*?])\s*}}/", function ($m) {
            try {
                return addcslashes(self::render($m[1], self::resolveArray($m[2])), '$');
            } catch (RuntimeException $e) {
                return "<!-- Embed error: {$e->getMessage()} -->";
            }
        }, $template);

        return preg_replace_callback('/{{\s*(.*?)\s*}}/', fn($m) => '<?php echo ' . self::parseExpression($m[1]) . '; ?>', $template);
    }

    /**
     * Parse template expressions with filters into PHP code
     *
     * Converts template variable expressions with optional filter chains into
     * executable PHP code. Handles pipe syntax for chaining multiple filters
     * and the special 'default' filter for fallback values.
     *
     * ## Filter Chain Processing
     * Processes filters left-to-right, with each filter receiving the output
     * of the previous step. The special 'default' filter uses PHP null coalescing
     * rather than a registered function for better performance.
     *
     * ## Variable Resolution
     * Variables are resolved through varAccess() to handle dot notation like
     * user.profile.name becoming $user['profile']['name'].
     *
     * @param string $expr Template expression like "name|upper|default:'Anonymous'"
     *
     * @return string PHP code for the expression
     *
     * @example Filter Chain Parsing
     * ```php
     * // Input: "user.name|upper|default:'Guest'"
     * // Output: "\\Primordyx\\View::applyFilter('upper', ($user['name']) ?? 'Guest')"
     * ```
     *
     * @example Default Filter Handling
     * ```php
     * // Input: "title|default:'Untitled'"
     * // Output: "($title) ?? 'Untitled'"
     * ```
     *
     * @see View::varAccess() Dot notation to PHP array access conversion
     * @see View::applyFilter() Filter execution wrapper
     * @since 1.0.0
     */
    protected static function parseExpression(string $expr): string
    {
        $parts = preg_split('/\|/', $expr);
        $base = trim(array_shift($parts) ?? '');
        // $base = trim(array_shift($parts));
        $code = self::varAccess($base);

        foreach ($parts as $pipe) {
            $pipe = trim($pipe);
            if ($pipe === '') continue;

            $filterParts = explode(':', $pipe, 2);
            $filter = trim($filterParts[0]);
            $arg = isset($filterParts[1]) ? trim($filterParts[1], "'\"") : null;

            if ($filter === 'default') {
                $defaultVal = var_export($arg, true);
                $code = "($code) ?? $defaultVal";
            } else {
                $argExpr = $arg ? ', ' . var_export($arg, true) : '';
                $code = "\\Primordyx\\View::applyFilter('$filter', $code$argExpr)";
            }
        }

        return $code;
    }

    /**
     * Convert dot notation to PHP array access syntax
     *
     * Transforms template dot notation like 'user.profile.name' into PHP array
     * access syntax like "$user['profile']['name']". Supports arbitrary nesting
     * depth and handles variable names with underscores and alphanumeric characters.
     *
     * ## Syntax Transformation
     * - First segment becomes PHP variable: user → $user
     * - Additional segments become array keys: .name → ['name']
     * - Chained access: user.profile.name → $user['profile']['name']
     *
     * @param string $expr Dot notation expression from template
     *
     * @return string PHP variable access code
     *
     * @example Basic Variable Access
     * ```php
     * // Input: "title"
     * // Output: "$title"
     * ```
     *
     * @example Nested Array Access
     * ```php
     * // Input: "user.profile.email"
     * // Output: "$user['profile']['email']"
     * ```
     *
     * @see View::parseExpression() Expression parsing that uses this method
     * @see View::resolveVar() Runtime variable resolution
     * @since 1.0.0
     */
    protected static function varAccess(string $expr): string
    {
        $parts = explode('.', trim($expr));
        $var = '$' . array_shift($parts);
        foreach ($parts as $part) {
            $var .= "['" . trim($part) . "']";
        }
        return $var;
    }

    /**
     * Resolve a dot notation variable from template data at runtime
     *
     * Walks through template data array using dot notation path to extract
     * nested values. Used by template compilation for dynamic operations like
     * {{each}} loops where variable paths need to be resolved at runtime
     * rather than compile time.
     *
     * ## Resolution Process
     * Starting with the full template data array, walks through each dot-separated
     * segment as an array key. Returns null if any segment in the path doesn't
     * exist or if a non-array is encountered where an array is expected.
     *
     * ## Safe Navigation
     * Provides safe navigation through nested structures - missing keys or
     * type mismatches return null rather than throwing errors.
     *
     * @param string $expr Dot notation expression to resolve
     *
     * @return mixed The resolved value or null if path not found
     *
     * @example Simple Variable Resolution
     * ```php
     * // Template data: ['title' => 'My Page']
     * // resolveVar('title') returns 'My Page'
     * ```
     *
     * @example Nested Array Resolution
     * ```php
     * // Template data: ['user' => ['profile' => ['name' => 'John']]]
     * // resolveVar('user.profile.name') returns 'John'
     * // resolveVar('user.missing.name') returns null
     * ```
     *
     * @see View::varAccess() Compile-time equivalent for PHP code generation
     * @since 1.0.0
     */
    protected static function resolveVar(string $expr): mixed
    {
        $parts = explode('.', trim($expr));
        $val = self::$data;
        foreach ($parts as $part) {
            if (!is_array($val) || !array_key_exists($part, $val)) return null;
            $val = $val[$part];
        }
        return $val;
    }

    /**
     * Parse array syntax from template and resolve variables
     *
     * Processes array syntax used in {{embed}} directives to create data arrays
     * with resolved variables. Parses template syntax like ['key' => variable]
     * and resolves variable references to actual values from template data.
     *
     * ## Array Syntax Support
     * Handles template syntax where array values are variable references rather
     * than literal values. The variable references are resolved using resolveVar()
     * to get actual values from the template data context.
     *
     * ## Merge Behavior
     * Returns merged array of template data and resolved array values, allowing
     * embedded templates to access both global data and specific passed values.
     *
     * @param string $input Array syntax from template like "['item' => product.name]"
     *
     * @return array<string, mixed> Merged data array with resolved variables
     *
     * @example Array Syntax Parsing
     * ```php
     * // Template: {{embed 'item.php' with ['product' => item.name, 'price' => item.cost]}}
     * // Input: "['product' => item.name, 'price' => item.cost]"
     * // Returns merged array with resolved item.name and item.cost values
     * ```
     *
     * @see View::resolveVar() Variable resolution used for array values
     * @since 1.0.0
     */
    protected static function resolveArray(string $input): array
    {
        $result = [];
        $pattern = "/'(.*?)'\s*=>\s*([a-zA-Z_][a-zA-Z0-9_.]*)/";
//      $pattern = "/'(.*?)'\s*=>\s*([a-zA-Z_][a-zA-Z0-9_\.]*)/";
        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $result[$m[1]] = self::resolveVar($m[2]);
            }
        }
        return array_merge(self::$data, $result);
    }

    /**
     * Apply a registered filter to a value with optional parameter
     *
     * Executes custom filter functions registered via registerCustomFilter().
     * Called during template execution when variables use pipe syntax with
     * custom filters. Provides safe execution with fallback for missing filters.
     *
     * ## Filter Execution
     * Looks up filter function in the $filters registry and calls it with the
     * value and optional parameter. If filter doesn't exist, returns the
     * original value unchanged to prevent template errors.
     *
     * ## Parameter Handling
     * Second parameter is optional and comes from template syntax like
     * {{variable|filter:'parameter'}} where 'parameter' becomes the second
     * argument to the filter function.
     *
     * @param string $filter Filter name from template syntax
     * @param mixed $value Value to filter
     * @param mixed $arg Optional parameter for the filter
     *
     * @return mixed Filtered value or original value if filter not found
     *
     * @example Filter Application
     * ```php
     * // Template: {{name|upper}}
     * // Calls: applyFilter('upper', $nameValue)
     *
     * // Template: {{text|truncate:'50'}}
     * // Calls: applyFilter('truncate', $textValue, '50')
     * ```
     *
     * @see View::registerCustomFilter() Filter registration
     * @see View::parseExpression() Filter parsing during compilation
     * @since 1.0.0
     */
    public static function applyFilter(string $filter, mixed $value, mixed $arg = null): mixed
    {
        if (isset(self::$filters[$filter])) {
            return call_user_func(self::$filters[$filter], $value, $arg);
        }

        return match ($filter) {
            'upper' => strtoupper($value),
            'lower' => strtolower($value),
            'ucfirst' => ucfirst($value),
            'lcfirst' => lcfirst($value),
            'ucwords' => ucwords($value),
            'reverse' => Strings::reverse($value),
            'keys' => is_array($value) ? array_keys($value) : [],
            'values' => is_array($value) ? array_values($value) : [],
            'join' => is_array($value) ? implode($arg ?? ', ', $value) : $value,
            'e', 'escape' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'nl2br' => nl2br($value),
            'trim' => trim($value),
            'slug', 'slugify' => Strings::slugify($value),
            'camel' => Strings::camelCase($value),
            'snake' => Strings::snake_case($value),
            'kebab' => Strings::kebabCase($value),
            'clean' => Strings::clean($value),
            'truncate' => Strings::truncate($value, (int)$arg, ''),
            'rot13' => Strings::rot13($value),
            'only-alpha' => Strings::onlyAlpha($value),
            'normalize-whitespace' => Strings::normalizeWhitespace($value),
            'length' => is_string($value) || is_array($value) ? count((array)$value) : 0,
            'json' => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            // 'raw'       => $value,
            default => $value
        };
    }
}
