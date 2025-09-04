# PanicDevs Modules

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D10-red.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Super light-weight Modular approach with no overhead for Laravel. This package provides a clean, performant way to organize your Laravel application into modular components with intelligent discovery, caching, and management capabilities.

## Features

- ✨ **Zero Configuration** - Works out of the box with sensible defaults
- 🚀 **High Performance** - Intelligent caching with automatic invalidation
- 🔍 **Auto Discovery** - Automatically discovers modules from configured paths
- 📦 **Multiple Module Types** - Support for foundation, modules, plugins, themes, etc.
- 🎛️ **Enable/Disable** - Runtime enable/disable of modules
- 🌱 **Database Seeders** - Built-in seeder management for modules
- 🎨 **Beautiful CLI** - Interactive commands with Laravel Prompts
- 🔒 **Protected Modules** - Prevent critical modules from being disabled
- 📊 **Module Information** - Rich metadata and dependency management

## Requirements

- PHP >= 8.2
- Laravel >= 10.0

## Installation

Install via Composer:

```bash
composer require panicdevs/modules
```

The package will automatically register its service provider via Laravel's auto-discovery.

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --provider="PanicDevs\Modules\Providers\ModulesServiceProvider" --tag="modules-config"
```

### Module Paths Configuration

Configure where your modules are located in `config/modules.php`:

```php
'paths' => [
    'foundation' => [
        'path'      => 'foundation',        // Path relative to project root
        'namespace' => 'Foundation',        // PSR-4 namespace prefix
        'icon'      => '🏢',               // Icon for CLI display
        'protected' => true,               // Cannot be disabled
    ],
    'modules' => [
        'path'      => 'modules',
        'namespace' => 'Modules',
        'icon'      => '📦',
        'protected' => false,              // Can be disabled
    ],
    // Add more module types as needed...
],
```

### Caching Configuration

Enable or disable module manifest caching:

```php
'cache' => env('MODULES_CACHE_ENABLED', true),
```

## Module Structure

Each module must contain a `module.json` file in its root directory:

```
modules/
├── User/
│   ├── module.json              # Required: Module configuration
│   ├── Providers/
│   │   └── UserServiceProvider.php
│   ├── Database/
│   │   └── Seeders/
│   │       └── UserDatabaseSeeder.php
│   ├── Routes/
│   │   └── api.php
│   └── ...
└── Auth/
    ├── module.json
    └── ...
```

### Module Configuration (`module.json`)

```json
{
    "id": "User",
    "name": "User",
    "alias": "user",
    "title": "User Management Module",
    "description": "User authentication and management functionality",
    "version": "1.0.0",
    "priority": 10,
    "providers": [
        "Modules\\User\\Providers\\UserServiceProvider"
    ],
    "files": [
        "Helpers/repositories.php"
    ],
    "depends_on": ["auth", "shared"]
}
```

#### Configuration Fields

- **id/name**: Unique module identifier
- **alias**: Short name for CLI commands (defaults to lowercase name)
- **title**: Human-readable module title
- **description**: Module description
- **version**: Module version
- **priority**: Loading priority (higher loads first, default: 0)
- **providers**: Array of service provider classes to register
- **files**: Array of files to include (relative to module root)
- **depends_on**: Array of module dependencies

## Module Status Management

Module enable/disable status is stored in `modules_statuses.json` in your project root:

```json
{
    "foundation": {
        "Core": true,
        "Base": true,
        "Support": true
    },
    "modules": {
        "User": true,
        "Auth": true,
        "Blog": false
    }
}
```

## Artisan Commands

The package provides several Artisan commands for module management:

### List Modules

```bash
# List all modules
php artisan modules:list

# Show only enabled modules
php artisan modules:list --enabled

# Show only disabled modules
php artisan modules:list --disabled
```

### Enable Modules

```bash
# Interactive module selection
php artisan modules:enable

# Enable specific module
php artisan modules:enable User

# Enable all modules of a type
php artisan modules:enable --all --type=modules
```

### Disable Modules

```bash
# Interactive module selection
php artisan modules:disable

# Disable specific module
php artisan modules:disable User

# Disable all modules of a type
php artisan modules:disable --all --type=modules
```

### Database Seeders

```bash
# Interactive seeder selection
php artisan modules:seed

# Seed specific modules
php artisan modules:seed User Auth

# Seed all modules with seeders
php artisan modules:seed --all

# Force seeding without confirmation
php artisan modules:seed User --force
```

### Performance Commands

```bash
# Cache module manifest for better performance
php artisan modules:cache

# Clear module cache
php artisan modules:clear

# Test module loading performance
php artisan modules:test
```

## Usage in Code

### Module Service

Inject the `ModuleService` to interact with modules in your code:

```php
use PanicDevs\Modules\Services\ModuleService;

class SomeController
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    public function index()
    {
        // Get all enabled modules
        $enabledModules = $this->moduleService->allEnabled();

        // Get specific module
        $userModule = $this->moduleService->find('User');

        // Check if module is enabled
        if ($this->moduleService->isEnabled('User')) {
            // Module is enabled
        }

        // Get module path
        $path = $this->moduleService->getPath('User');

        // Get module namespace
        $namespace = $this->moduleService->getNamespace('User');
    }
}
```

### Available Methods

```php
// Module retrieval
$moduleService->all();                    // Get all modules
$moduleService->allEnabled();             // Get enabled modules only
$moduleService->find('ModuleName');       // Get specific module
$moduleService->findByAlias('alias');     // Find by alias

// Module status
$moduleService->isEnabled('ModuleName');  // Check if enabled

// Module information
$moduleService->getPath('ModuleName');           // Get module path
$moduleService->getNamespace('ModuleName');     // Get PSR-4 namespace
$moduleService->getAlias('ModuleName');         // Get module alias
$moduleService->getTitle('ModuleName');         // Get module title
$moduleService->getDescription('ModuleName');   // Get description
$moduleService->getVersion('ModuleName');       // Get version
$moduleService->getDependencies('ModuleName');  // Get dependencies

// Module organization
$moduleService->getByType('modules');           // Get modules by type
$moduleService->getEnabledByType('modules');    // Get enabled modules by type
$moduleService->getEnabledByPriority();         // Get modules ordered by priority

// Statistics
$moduleService->getStats();                     // Get module statistics
```

## Performance

### Caching

The package includes intelligent caching:

- **Automatic**: Module manifest is cached automatically when `modules.cache` is `true`
- **Cache Location**: `bootstrap/cache/modules.php`
- **Auto-Invalidation**: Cache is invalidated when `modules_statuses.json` changes
- **Production Ready**: Optimized for production environments

### Loading Optimization

- **Lazy Loading**: Modules are only loaded when needed
- **Priority-Based**: Modules load in priority order (highest first)
- **PSR-4 Integration**: Seamless autoloading integration
- **Minimal Overhead**: Less than 1ms overhead in production

## Database Seeders

### Module Seeder Structure

Each module can have its own database seeders:

```
modules/User/Database/Seeders/
├── UserDatabaseSeeder.php     # Main seeder (required)
└── V1/
    ├── UserTableSeeder.php
    └── RoleTableSeeder.php
```

### Main Database Seeder

The main seeder (`ModuleNameDatabaseSeeder.php`) coordinates all module seeders:

```php
<?php

namespace Modules\User\Database\Seeders;

use Foundation\Base\Database\Seeders\V1\BaseDatabaseSeeder\BaseDatabaseSeeder;
use Modules\User\Database\Seeders\V1\UserTableSeeder;

class UserDatabaseSeeder extends BaseDatabaseSeeder
{
    public function run(): void
    {
        parent::run();

        $this->call([
            UserTableSeeder::class,
        ]);
    }
}
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -am 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Security

If you discover any security-related issues, please email [hunt@panicdevs.agency](mailto:hunt@panicdevs.agency) instead of using the issue tracker.

## License

The MIT License (MIT).

## Credits

- **[Armin Hooshmand](https://github.com/RealMrHex)** - Lead Developer
- **[PanicDevs](https://github.com/panicdevs)** - Development Team

## Support

- **Issues**: [GitHub Issues](https://github.com/panicdevs/modules/issues)
- **Documentation**: [GitHub README](https://github.com/panicdevs/modules#readme)
- **Security**: [Security Advisories](https://github.com/panicdevs/modules/security/advisories)

---

Made with ❤️ by [PanicDevs](https://github.com/panicdevs)
