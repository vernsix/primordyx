# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Primordyx is a lightweight PHP framework for modern web applications built around familiar MVC structure. It emphasizes clarity, control, and fast starts without excessive magic or bloat. Requires PHP 8.2+ and follows PSR-4/PSR-12 coding standards.

## Core Architecture

### Request Flow
1. **Routing**: `Router::init()` detects context (HTTP/CLI), extracts path from `$_GET['rt']` or `$argv[1]`
2. **Middleware Pipeline**: Executes in registration order, can redirect or halt execution
3. **Route Dispatch**: Extracts named parameters from patterns like `/users/{id}`, calls handler
4. **Response**: Controller/handler outputs response directly (no response objects)

### Database Layer
- **Model**: Abstract ORM base class extends `Primordyx\Database\Model`
- **Soft Deletes**: Enabled by default with `deleted_at`/`restored_at` timestamps
- **Query Builder**: Fluent interface for complex queries, automatic parameter binding
- **Connection Manager**: Lazy-loads PDO connections from config sections starting with `database_`
- **Query Tracking**: `QueryTracker` monitors performance (optional)
- **Column Introspection**: Models use `DESCRIBE` queries cached via `Cargo` class

### Application Autoloading
- **Framework Classes**: `Primordyx\*` namespace loaded by Composer
- **Application Classes**: Loaded by `AppAutoLoader::enable($appRoot, 'app')`
- **Directory Structure**: Scans `app/` recursively, maps directories to namespaces
- **Example**: `app/Controllers/HomeController.php` → `Controllers\HomeController`
- **No namespacing required**: Application classes live in the global namespace or their directory-based namespace

### Session & Security
- **Safe Class**: Tamper-resistant session storage in `Primordyx\Data\Safe`
- **AuthManager**: Complete auth system with brute-force protection, role-based access
- **CSRF Protection**: `CsrfManager` generates/validates tokens
- **Crypto**: Encryption utilities in `Primordyx\Security\Crypto`
- **Password Hashing**: Uses PHP's `password_hash()`/`password_verify()`

### Event System
- **EventManager**: Simple event dispatcher for framework events
- **MessageQueue**: File-based async event queue (pending/failed/completed directories)
- **Router Events**: `router.init`, `router.middleware.before/after`, `router.dispatching`, `router.404`

## Essential Commands

### CLI Tool
All CLI commands run via `primordyx` binary (installed in `vendor/bin/`):

```bash
# Check framework version
primordyx version

# Validate environment and config
primordyx doctor

# Create new controller
primordyx make controller HomeController

# Create new model
primordyx make model User

# Database migrations
primordyx migrate status      # Check migration status
primordyx migrate up          # Run pending migrations
primordyx migrate down        # Rollback last migration
primordyx migrate create CreateUsersTable  # Create new migration

# Message queue processing
primordyx messagequeue:consume
primordyx messagequeue:publish event_name '{"key":"value"}'

# Database seeding
primordyx seed run
```

### Multi-Tenant Projects
If multiple config files exist (e.g., `app.ini`, `client1.ini`), specify tenant:
```bash
primordyx migrate up --tenant=client1
```

### Testing & Linting
```bash
# Code style checking (PSR-12)
./vendor/bin/phpcs

# Auto-fix code style issues
./vendor/bin/phpcbf
```

## Development Patterns

### Creating a New Route
```php
// In routes.php
use Primordyx\Routing\Router;

Router::init();

// Simple GET route
Router::get('/users', [], function() {
    (new Controllers\UserController())->index();
});

// Route with named parameters
Router::get('/users/{id}', [AuthMiddleware::class], function($id) {
    (new Controllers\UserController())->show($id);
});

// POST route for forms
Router::post('/users', [CsrfMiddleware::class], function() {
    (new Controllers\UserController())->store();
});

Router::dispatch();
```

### Creating a Model
Models auto-discover table columns and cache them via Cargo:

```php
namespace Models;

use Primordyx\Database\Model;

class User extends Model
{
    protected string $table = 'users';  // Optional - auto-generates from class name
    protected string $primaryKey = 'id';
    protected bool $softDelete = true;
    protected bool $timestamps = true;

    protected array $casts = [
        'active' => 'bool',
        'age' => 'int',
        'created_at' => 'datetime'
    ];

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'username' => 'required|min:3'
        ];
    }
}

// Usage
$user = (new User())->find(123);
$user->name = 'Updated';
$user->save();

// Query builder
$users = (new User())
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->getAsModels();
```

### Middleware Implementation
All middleware must implement `handle(string $method, string $path, string $originalRoute, array $namedParams)`:

```php
class AuthMiddleware
{
    public function handle(string $method, string $path, string $originalRoute, array $namedParams)
    {
        // Return null/false to continue
        if (AuthManager::isLoggedIn()) {
            return null;
        }

        // Return string URL to redirect
        return '/login';

        // Return array with 'error' key to halt with HTTP status
        // return ['error' => 401, 'message' => 'Unauthorized'];
    }
}
```

### Configuration Structure
Config files in `config/*.ini` format:

```ini
[database_default]
driver = mysql
host = localhost
port = 3306
database = myapp
username = dbuser
password = dbpass
charset = utf8mb4

[app]
name = "My App"
debug = true
timezone = America/Chicago

[error_logging]
enabled = true
filename = /var/www/app/storage/logs/error.log
```

Access via `Config::get('key', 'section', 'default')`.

### CLI Command Registration
```php
// In routes.php for CLI context
Router::cli('cache/clear', [], function() {
    CacheManager::clear();
    echo "Cache cleared successfully\n";
});

Router::cli('user/create/{name}', [ValidationMiddleware::class], function($name, ...$args) {
    // $name from route, $args contains remaining CLI arguments
    $user = User::create(['name' => $name]);
    echo "Created user: {$user->name} (ID: {$user->id})\n";
});
```

## Important Conventions

### Routing Normalization
- **HTTP**: Paths must have leading slash, trailing slashes removed (except root `/`)
- **CLI**: Leading slashes removed from commands for command-style routing
- **Named Parameters**: Use `{paramName}` syntax, matched with `([^/]+)` regex

### Database Conventions
- Primary key: `id` (configurable via `$primaryKey`)
- Timestamps: `created_at`, `updated_at` (if `$timestamps = true`)
- Soft deletes: `deleted_at`, `restored_at` (if `$softDelete = true`)
- Table names: Lowercase class name + 's' (e.g., `User` → `users`)

### Error Handling
- Model validation errors: Check `$model->hasErrors()` and `$model->getErrors()`
- Database errors: Stored in `$errors['db']` array on model
- Failed login attempts: Tracked in `failed_attempts`, `last_failed`, `locked_until` columns
- QueryTracker: Use `QueryTracker::start()` / `QueryTracker::stop($sql, $params)` around queries

### Security Best Practices
- Never commit `.env` or `*.local.ini` files
- Use `Safe::set()`/`Safe::get()` for tamper-resistant session data
- Always validate user input via Model `rules()` method
- Use parameterized queries (QueryBuilder handles this automatically)
- Failed login protection: Default 5 attempts, 15-minute lockout

## Namespace Structure

Framework classes use `Primordyx\` namespace:
- `Primordyx\Config\` - Configuration management (Config, Ini)
- `Primordyx\Data\` - Sessions, caching, validation, Safe class
- `Primordyx\Database\` - Models, QueryBuilder, ConnectionManager
- `Primordyx\Events\` - EventManager, MessageQueue
- `Primordyx\Geo\` - GPS and location utilities
- `Primordyx\Http\` - HTTP clients, cookies, throttling
- `Primordyx\Mail\` - Email functionality
- `Primordyx\Routing\` - Router and request parsing
- `Primordyx\Security\` - AuthManager, Crypto, CSRF, bot detection
- `Primordyx\System\` - Logging, cron, file utilities
- `Primordyx\Time\` - Timer and time helpers
- `Primordyx\Utils\` - General utilities
- `Primordyx\View\` - Template system

Application classes typically have no namespace or directory-based namespaces (e.g., `Controllers\`, `Models\`).

## Common Gotchas

1. **Router must be initialized**: Always call `Router::init()` before registering routes
2. **Models need column cache**: First instantiation runs `DESCRIBE` query, subsequent uses cached columns
3. **AppAutoLoader call**: Must call `AppAutoLoader::enable($appRoot, 'app')` in bootstrap to load application classes
4. **Config loading**: Call `Config::initialize($configFile, 'app')` before using `Config::get()`
5. **Database connections**: Registered lazily via `ConnectionManager::registerConfig()` from config sections
6. **Soft deletes active by default**: Set `protected bool $softDelete = false` to disable
7. **CLI tenant detection**: Multi-config projects require `--tenant=name` parameter
8. **Middleware return values**: `null`/`false` continues, `string` redirects, `array` with `error` key halts
9. **Route dispatch never returns**: Both `Router::dispatch()` and `AuthManager::login()` always exit
10. **MessageQueue needs configuration**: Call `MessageQueue::configure('/path/to/queue')` before use

## File References

- Router implementation: `src/Routing/Router.php`
- Model base class: `src/Database/Model.php`
- AuthManager: `src/Security/AuthManager.php`
- AppAutoLoader: `src/AppAutoLoader.php`
- CLI entry point: `bin/primordyx`
- Config class: `src/Config/Config.php`
- Safe session storage: `src/Data/Safe.php`
- MessageQueue: `src/Events/MessageQueue.php`
