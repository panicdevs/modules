<?php

declare(strict_types=1);

/**
 * Module System Configuration
 *
 * This configuration file defines how the module system discovers, loads,
 * and manages modules throughout the application.
 *
 * @package PanicDevs\Modules
 * @author  PanicDevs
 * @since   1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Module Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Define where modules are located and their corresponding PSR-4 namespaces.
    | Each path type can contain multiple modules with module.json files.
    |
    | Structure:
    | 'type_name' => [
    |     'path'      => 'relative/path/from/project/root',
    |     'namespace' => 'PSR4\\Namespace\\Prefix',
    |     'icon'      => '📦', // Optional: icon for CLI display (defaults to 📦)
    |     'protected' => false, // Optional: prevent disabling (defaults to false)
    | ]
    |
    | Examples:
    | - foundation/Api/module.json → Foundation\Api namespace
    | - modules/User/module.json   → Modules\User namespace
    |
    */
    'paths' => [
        'foundation' => [
            'path'      => 'foundation',
            'namespace' => 'Foundation',
            'icon'      => '🏢', // Foundation/Core modules
            'protected' => true, // Cannot be disabled
        ],
        'modules' => [
            'path'      => 'modules',
            'namespace' => 'Modules',
            'icon'      => '📦', // Application modules
            'protected' => false, // Can be disabled
        ],

        /*
        | You can add more module types as needed:
        |
        | 'plugins' => [
        |     'path'      => 'plugins',
        |     'namespace' => 'Plugins',
        |     'icon'      => '🔌', // Plugin modules
        |     'protected' => false, // Can be disabled
        | ],
        | 'themes' => [
        |     'path'      => 'themes',
        |     'namespace' => 'Themes',
        |     'icon'      => '🎨', // Theme modules
        |     'protected' => false, // Can be disabled
        | ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Manifest Caching
    |--------------------------------------------------------------------------
    |
    | Enable or disable caching of the module manifest for performance.
    | When enabled, module discovery results are cached in bootstrap/cache/modules.php
    |
    | - true:  Enable caching (recommended for production)
    | - false: Disable caching (useful for development)
    |
    | Cache is automatically invalidated when modules_statuses.json changes.
    |
    */
    'cache' => env('MODULES_CACHE_ENABLED', true),
];
