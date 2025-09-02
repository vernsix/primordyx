# 📦 Installing Primordyx

Primordyx is a modern PHP framework available publicly on GitHub and Packagist.

---

## ⚙️ Step 1: Install Primordyx via Composer

In your project directory (e.g. `/var/www/html`) run:

```bash
composer require vernsix/primordyx
```

That's it! Composer will automatically:
- Download the latest version from Packagist
- Install all dependencies
- Set up the autoloader
- Register the CLI tools

---

## 🧭 Step 2: Add Primordyx CLI to Your PATH (Optional)

### Option A: Global Installation (Recommended)
For system-wide access to the `primordyx` command:

```bash
# Find your global bin directory
composer global config bin-dir --absolute

# Add to your ~/.bashrc (or ~/.zshrc)
echo 'export PATH="$(composer global config bin-dir --absolute):$PATH"' >> ~/.bashrc

# Reload your shell
source ~/.bashrc
```

### Option B: Project-Specific Usage
Alternatively, you can run CLI commands directly from your project root without PATH setup:

```bash
./vendor/bin/primordyx help
```

### Test the CLI:
```bash
primordyx help
```

---

## 🚀 Step 3: Getting Started

Create your own project structure with the directories and files you need:

```bash
mkdir controllers models views middleware storage/logs
touch index.php bootstrap.php routes.php
```

Then configure your database and start building!

---

## 🛠️ Step 4: Configure Your Project

### If Using Starter App:
The starter app includes example configuration files. Simply copy and customize:

```bash
# Copy the example config
cp config/app.ini.example config/app.ini

# Edit with your actual database settings
nano config/app.ini
```

### If Starting From Scratch:
Create your configuration directory and files:

```bash
mkdir config
```

Create a configuration file (e.g., `config/app.ini`) with your database and application settings:

```ini
[database_defaukt]
host = localhost
dbname = myapp
username = dbuser
password = dbpass
charset = utf8mb4

[app]
name = "My App"
debug = true
```

### Development Overrides:
For both approaches, you can create a `.local.ini` file that overrides production settings:
```bash
cp config/app.ini config/app.local.ini
# Edit app.local.ini with development-specific settings
```

---

## 📁 Step 5: Directory Structure

Here's a typical Primordyx project structure:

```
/your-project-root              ← usually /var/www/html on most servers
├── .primordyx/                 ← CLI configuration files          
├── classes/                    ← custom classes
├── config/                     ← configuration files
├── controllers/                ← controller classes
├── middleware/                 ← middleware classes
├── migrations/                 ← database migrations
├── models/                     ← model classes  
├── views/                      ← view templates
├── storage/                    ← application storage
│   ├── logs/                   ← log files
│   └── messagequeue/           ← queue storage
│       ├── pending/
│       ├── completed/
│       └── failed/
├── public/                     ← web root (optional)
│    ├── assets/
│    ├── css/
│    ├── js/
│    └── index.php               ← entry point
├── composer.json               
├── bootstrap.php               ← application initialization
└── routes.php                  ← route definitions

```

> ⚠️ **Security Note:** Configure your web server to only serve files from the public directory (or from the root if you're not using a public directory). Never expose your classes, config, or storage directories to web access.

---

## 🎯 Step 6: What's Next?

1. **Explore the CLI tools:**
   ```bash
   primordyx help
   ```

2. **Check your environment:**
   ```bash
   primordyx doctor
   ```

3. **Create your first controller:**
   ```bash
   primordyx make controller HomeController
   ```

4. **Create your first model:**
   ```bash
   primordyx make model User
   ```

5. **Manage database migrations:**
   ```bash
   primordyx migrate status    # Check migration status
   primordyx migrate up        # Run pending migrations
   ```

6. **Check framework version:**
   ```bash
   primordyx version
   ```

7. **Process message queue (if using):**
   ```bash
   primordyx messagequeue:consume
   ```

8. **Check out the resources:**
   - [Framework documentation](https://github.com/vernsix/primordyx)
   - [Starter app with examples](https://github.com/vernsix/primordyx-starter)
   - [Issues and discussions on GitHub](https://github.com/vernsix/primordyx/issues)

---

## 🏗️ Framework Architecture

Primordyx uses a clean, organized namespace structure:

### Core Namespaces
- **`Primordyx\Config\`** - Configuration management (Config, Ini)
- **`Primordyx\Data\`** - Sessions, caching, validation, data export
- **`Primordyx\Database\`** - Models, query builder, connections, persistence
- **`Primordyx\Events\`** - Event management and message queue system
- **`Primordyx\Geo\`** - GPS, location, and geographic utilities
- **`Primordyx\Http\`** - HTTP clients, status codes, cookies, throttling
- **`Primordyx\Mail\`** - Email functionality
- **`Primordyx\Routing\`** - Core routing engine and request parsing
- **`Primordyx\Security\`** - Authentication, encryption, bot detection
- **`Primordyx\System\`** - System utilities (cron, logging, file loading)
- **`Primordyx\Time\`** - Timer and time helper utilities
- **`Primordyx\Utils\`** - General purpose helper utilities
- **`Primordyx\View\`** - Template/view system

### Usage Examples
```php
// Import framework classes as needed
use Primordyx\Routing\Router;
use Primordyx\Database\Model;
use Primordyx\Events\MessageQueue;
use Primordyx\Security\AuthManager;

// Your application classes stay in the global namespace or your own
class UserController {
    // Your controller code
}
```

---

## 🌟 Quick Start Example

### Using the Starter App:
```bash
cd /var/www/html
git clone https://github.com/vernsix/primordyx-starter.git .
composer install
# Edit config/app.ini with your database settings
primordyx doctor
```

### From Scratch:
```json
{
   "require": {
      "vernsix/primordyx": "^1.0"
   }
}
```

```php
<?php
require_once 'vendor/autoload.php';

use Primordyx\Routing\Router;

Router::init();

Router::get('/', [], function() {
    echo "Hello, Primordyx!";
});

Router::dispatch();
```

Run `composer install` and you're ready to go!

Happy coding! 🚀