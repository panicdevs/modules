<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Providers;

use Illuminate\Support\ServiceProvider;
use PanicDevs\Modules\Console\ModuleTestCommand;
use PanicDevs\Modules\Console\ModuleEnableCommand;
use PanicDevs\Modules\Console\ModuleDisableCommand;
use PanicDevs\Modules\Console\ModuleListCommand;
use PanicDevs\Modules\Console\ModuleOptimizeCommand;
use PanicDevs\Modules\Console\ModuleOptimizeClearCommand;
use PanicDevs\Modules\Console\ModuleSeedCommand;
use PanicDevs\Modules\ManifestBuilder;
use PanicDevs\Modules\Services\ModuleService;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Ultra-lightweight register - <1ms overhead
     */
    public function register(): void
    {
        // Merge config early
        $this->mergeConfigFrom(__DIR__.'/../../config/modules.php', 'modules');

        // Bind ManifestBuilder as singleton
        $this->app->singleton(ManifestBuilder::class, fn () => new ManifestBuilder());

        // Bind ModuleService as singleton
        $this->app->singleton(ModuleService::class, fn ($app): ModuleService
        => new ModuleService($app->make(ManifestBuilder::class)));

        // Bind 'modules' alias for app('modules') access
        $this->app->alias(ModuleService::class, 'modules');

        // Don't register autoloading here - defer to boot phase
    }

    /**
     * Lightweight boot - defer heavy operations
     */
    public function boot(): void
    {
        // Only register console commands and publishes - defer provider loading
        if ($this->app->runningInConsole())
        {
            $this->commands([
                ModuleTestCommand::class,
                ModuleEnableCommand::class,
                ModuleDisableCommand::class,
                ModuleListCommand::class,
                ModuleOptimizeCommand::class,
                ModuleOptimizeClearCommand::class,
                ModuleSeedCommand::class,
            ]);
            $this->publishes([
                __DIR__.'/../../config/modules.php' => config_path('modules.php'),
            ], 'modules-config');

            // Register optimization commands with Laravel's optimize system
            $this->optimizes('modules:cache', clear: 'modules:clear');
        }

        // Load providers during boot phase (before routing)
        $this->safeLoadProviders();
    }

    /**
     * Ultra-fast autoloading registration with static caching
     */
    protected function registerModuleAutoloading(): void
    {
        // Use a static cache to avoid repeated calls
        static $autoloadingRegistered = false;

        if ($autoloadingRegistered)
        {
            return;
        }

        /** @var ManifestBuilder $manifestBuilder */
        $manifestBuilder = $this->app->make(ManifestBuilder::class);
        $modules         = $manifestBuilder->build();

        // Get Composer autoloader once
        $loader = $this->getComposerLoader();
        if (!$loader)
        {
            return;
        }

        foreach ($modules as $module)
        {
            if (!$module['enabled'])
            {
                continue;
            }

            $namespaces = $manifestBuilder->getModuleNamespaces($module);
            foreach ($namespaces as $namespace => $relativePath)
            {
                // Module path is the full path to the module directory
                $loader->addPsr4($namespace, $module['path']);
            }
        }

        $autoloadingRegistered = true;
    }

    /**
     * Lazy-load module service providers after application boot
     */
    protected function loadModuleProviders(): void
    {
        // Just load all providers - keep it simple
        $this->loadAllProviders();
    }



    /**
     * Load all module providers at once
     */
    protected function loadAllProviders(): void
    {
        /** @var ManifestBuilder $manifestBuilder */
        $manifestBuilder = $this->app->make(ManifestBuilder::class);
        $modules         = $manifestBuilder->build();

        foreach ($modules as $module)
        {
            if (!$module['enabled'])
            {
                continue;
            }

            $this->loadProvidersForModule($module);
        }
    }

    /**
     * Load providers for a single module
     */
    protected function loadProvidersForModule(array $module): void
    {
        // 1. Load files FIRST (helper files, etc.)
        foreach ($module['files'] ?? [] as $file)
        {
            $filePath = $module['path'].'/'.mb_ltrim($file, '/');
            if (file_exists($filePath))
            {
                require_once $filePath;
            }
        }

        // 2. Load providers SECOND
        foreach ($module['providers'] ?? [] as $providerClass)
        {
            if (class_exists($providerClass))
            {
                $this->app->register($providerClass);
            }
        }

        // Fallback: Auto-discover providers if none explicitly defined
        if (empty($module['providers']))
        {
            $providersPath = $module['path'].'/Providers';
            if (is_dir($providersPath))
            {
                $providers = glob($providersPath.'/*ServiceProvider.php');

                foreach ($providers as $providerFile)
                {
                    $className = $this->getProviderClassName($providerFile, $module);

                    if (class_exists($className))
                    {
                        $this->app->register($className);
                    }
                }
            }
        }
    }

    /**
     * Get provider class name from file path and module info
     */
    protected function getProviderClassName(string $providerFile, array $module): string
    {
        $filename = basename($providerFile, '.php');

        // Use the namespace from module data
        $baseNamespace = $module['namespace'];

        return "{$baseNamespace}\\{$module['name']}\\Providers\\{$filename}";
    }

    /**
     * Get Composer autoloader with fallbacks
     */
    protected function getComposerLoader()
    {
        static $loader = null;

        if (null !== $loader)
        {
            return $loader;
        }

        $autoloadPath = base_path('vendor/autoload.php');
        if (file_exists($autoloadPath))
        {
            $loader = require $autoloadPath;
            return $loader;
        }

        return $loader = false;
    }

    /**
     * Safely load providers without triggering container issues
     */
    protected function safeLoadProviders(): void
    {
        // Read modules directly from cached file to avoid container resolution
        $cacheFile = base_path('bootstrap/cache/modules.php');

        if (!file_exists($cacheFile))
        {
            // Create cache if it doesn't exist
            $builder = new ManifestBuilder();
            $modules = $builder->build();
        } else
        {
            // Load from cache
            $modules = include $cacheFile;
        }

        // Register autoloading first
        $loader = $this->getComposerLoader();
        if ($loader)
        {
            foreach ($modules as $module)
            {
                if (!isset($module['enabled']) || !$module['enabled'])
                {
                    continue;
                }
                // Register namespace directly
                $namespace = $module['namespace'].'\\'.$module['name'].'\\';
                $loader->addPsr4($namespace, $module['path']);
            }
        }

        // Load providers
        foreach ($modules as $module)
        {
            if (!$module['enabled'])
            {
                continue;
            }

            $this->loadProvidersForModule($module);
        }
    }
}
