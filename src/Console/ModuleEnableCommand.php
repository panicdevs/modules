<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\ManifestBuilder;
use Exception;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

/**
 * ModuleEnableCommand
 *
 * Console command for enabling modules in the application.
 * Provides both direct module enabling by name and interactive selection.
 * Discovers all available modules and allows enabling of disabled ones.
 *
 * Usage:
 * - php artisan modules:enable                     # Interactive selection
 * - php artisan modules:enable User               # Enable User module (default type: modules)
 * - php artisan modules:enable User --type=modules # Enable User module from modules type
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleEnableCommand extends Command
{
    protected $signature   = 'modules:enable {module?} {--type= : Module type from configured paths} {--all : Enable all modules of specified type}';
    protected $description = 'Enable a module or multiple modules';

    /**
     * Execute the console command.
     *
     * Handles both direct module enabling and interactive selection based on provided arguments.
     */
    public function handle(): void
    {
        $moduleName = $this->argument('module');
        $type       = $this->option('type');
        $all        = $this->option('all');

        // Handle --all flag
        if ($all)
        {
            $this->enableAllModules($type);
            return;
        }

        // If no module specified, show interactive selection
        if (!$moduleName)
        {
            $this->showInteractiveSelection();
            return;
        }

        // If no type specified, try to infer from available types
        if (!$type)
        {
            $type = $this->getDefaultModuleType();
            if (!$type)
            {
                error('❌ No module type specified and no default type available.');
                info('Available types: '.implode(', ', array_keys($this->getConfigPaths())));
                return;
            }
        }

        $this->enableModule($moduleName, $type);
    }

    /**
     * Show interactive module selection for enabling.
     *
     * Uses Laravel Prompts to provide a beautiful interactive selection experience
     * with module information and confirmation prompts.
     */
    protected function showInteractiveSelection(): void
    {
        $allModules = spin(
            callback: fn() => $this->getAllModules(),
            message: '🔍 Discovering available modules...'
        );

        $statuses   = $this->getStatuses();
        $choices    = [];
        $moduleInfo = [];

        foreach ($allModules as $type => $modules)
        {
            foreach ($modules as $module)
            {
                $status = $statuses[$type][$module] ?? false;
                if (!$status)
                {
                    $key              = "$type/$module";
                    $icon             = $this->getModuleTypeIcon($type);
                    $choices[$key]    = "{$icon} {$module} ({$type})";
                    $moduleInfo[$key] = [
                        'name' => $module,
                        'type' => $type,
                        'path' => $this->getModulePath($module, $type)
                    ];
                }
            }
        }

        if (empty($choices))
        {
            info('✅ All available modules are already enabled!');
            warning('💡 Use `php artisan modules:list` to see all module statuses.');
            return;
        }

        // Ask user if they want to enable single or multiple modules
        $mode = select(
            label: 'How would you like to enable modules?',
            options: [
                'single'   => '📦 Enable a single module',
                'multiple' => '📦📦 Enable multiple modules',
            ],
            hint: 'Choose your preferred mode'
        );

        if ('multiple' === $mode)
        {
            $this->handleMultipleEnable($choices, $moduleInfo);
        } else
        {
            $this->handleSingleEnable($choices, $moduleInfo);
        }
    }

    /**
     * Handle single module enable selection.
     */
    protected function handleSingleEnable(array $choices, array $moduleInfo): void
    {
        $selected = select(
            label: 'Which module would you like to enable?',
            options: $choices,
            hint: 'Select a module to enable it and make its functionality available'
        );

        $moduleData = $moduleInfo[$selected];

        // Show module information
        info("📝 Module: {$moduleData['name']}");
        info("📁 Type: {$moduleData['type']}");
        info("📋 Path: {$moduleData['path']}");

        // Confirmation prompt
        $confirmed = confirm(
            label: "Enable the '{$moduleData['name']}' module?",
            default: true,
            yes: 'Yes, enable it',
            no: 'No, cancel',
            hint: 'This will make the module available for use'
        );

        if ($confirmed)
        {
            $this->enableModule($moduleData['name'], $moduleData['type']);
        } else
        {
            info('👍 Operation cancelled.');
        }
    }

    /**
     * Handle multiple module enable selection.
     */
    protected function handleMultipleEnable(array $choices, array $moduleInfo): void
    {
        $selectedKeys = multiselect(
            label: 'Which modules would you like to enable?',
            options: $choices,
            hint: 'Select multiple modules to enable'
        );

        if (empty($selectedKeys))
        {
            info('👍 No modules selected.');
            return;
        }

        // Gather selected modules info
        $selectedModules = [];
        foreach ($selectedKeys as $key)
        {
            $moduleData        = $moduleInfo[$key];
            $selectedModules[] = $moduleData;
        }

        // Show summary and confirmation
        $totalCount = count($selectedModules);
        warning("⚠️  You are about to enable {$totalCount} modules:");

        foreach ($selectedModules as $module)
        {
            $icon = $this->getModuleTypeIcon($module['type']);
            $this->line("   {$icon} {$module['name']} ({$module['type']})");
        }

        $confirmed = confirm(
            label: "Are you sure you want to enable these {$totalCount} modules?",
            default: true,
            yes: 'Yes, enable all selected',
            no: 'No, cancel',
            hint: 'This will make all selected modules available for use'
        );

        if (!$confirmed)
        {
            info('👍 Operation cancelled.');
            return;
        }

        // Enable modules one by one with progress
        $successful = 0;
        $failed     = 0;

        foreach ($selectedModules as $module)
        {
            try
            {
                $this->enableModule($module['name'], $module['type'], true); // Skip individual messages
                $successful++;
                $icon = $this->getModuleTypeIcon($module['type']);
                info("   {$icon} Enabled: {$module['name']}");
            } catch (Exception $e)
            {
                error("   ❌ Failed: {$module['name']} - {$e->getMessage()}");
                $failed++;
            }
        }

        // Clear cache once at the end
        if ($successful > 0)
        {
            $builder = new ManifestBuilder();
            $builder->clear();
        }

        // Summary
        if ($successful > 0)
        {
            info("✅ Successfully enabled {$successful} modules.");
            info('🚀 All enabled modules are now available for use.');
        }
        if ($failed > 0)
        {
            warning("⚠️  {$failed} modules could not be enabled.");
        }
    }

    /**
     * Enable a specific module.
     *
     * Validates the module exists before enabling.
     * Updates the modules_statuses.json file and clears the manifest cache.
     */
    protected function enableModule(string $moduleName, string $type, bool $skipMessages = false): void
    {
        $statusFile = base_path('modules_statuses.json');
        $statuses   = $this->getStatuses();

        $availableTypes = array_keys($this->getConfigPaths());
        if (!isset($statuses[$type]))
        {
            error("❌ Module type '{$type}' not found.");
            info('Available types: '.implode(', ', $availableTypes));
            return;
        }

        if (!$this->moduleExists($moduleName, $type))
        {
            error("❌ Module '{$moduleName}' not found in '{$type}' directory.");
            return;
        }

        if ($statuses[$type][$moduleName] ?? false)
        {
            warning("✅ Module '{$moduleName}' is already enabled.");
            return;
        }

        $statuses[$type][$moduleName] = true;

        file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT));

        // Clear cache to force rebuild (only once per operation)
        if (!$skipMessages)
        {
            $builder = new ManifestBuilder();
            $builder->clear();
        }

        if (!$skipMessages)
        {
            info("✨ Module '{$moduleName}' has been successfully enabled!");
            info('🚀 The module is now available for use.');
        }
    }

    /**
     * Enable all modules of a specific type.
     */
    protected function enableAllModules(?string $type): void
    {
        // If no type specified, let user choose
        if (!$type)
        {
            $availableTypes = array_keys($this->getConfigPaths());

            if (1 === count($availableTypes))
            {
                $type = $availableTypes[0];
                info("🎯 Using the only available type: {$type}");
            } else
            {
                $type = select(
                    label: 'Which module type would you like to enable all modules for?',
                    options: array_combine($availableTypes, $availableTypes),
                    hint: 'This will enable ALL modules of the selected type'
                );
            }
        }

        $allModules = $this->getAllModules();
        $statuses   = $this->getStatuses();

        if (!isset($allModules[$type]))
        {
            error("❌ Module type '{$type}' not found or has no modules.");
            info('Available types: '.implode(', ', array_keys($this->getConfigPaths())));
            return;
        }

        $modulesToEnable = [];
        foreach ($allModules[$type] as $moduleName)
        {
            $isEnabled = $statuses[$type][$moduleName] ?? false;
            if (!$isEnabled) // Only disabled modules
            {$modulesToEnable[] = $moduleName;
            }
        }

        if (empty($modulesToEnable))
        {
            info("✅ All modules of type '{$type}' are already enabled.");
            return;
        }

        // Show confirmation with detailed info
        warning("⚠️  You are about to enable ".count($modulesToEnable)." modules of type '{$type}':");
        foreach ($modulesToEnable as $module)
        {
            $icon = $this->getModuleTypeIcon($type);
            $this->line("   {$icon} {$module}");
        }

        $confirmed = confirm(
            label: 'Are you sure you want to enable all these modules?',
            default: true,
            yes: 'Yes, enable all',
            no: 'No, cancel',
            hint: 'This will make all modules available for use'
        );

        if (!$confirmed)
        {
            info('👍 Operation cancelled.');
            return;
        }

        // Enable modules with progress
        $successful = 0;
        $failed     = 0;

        foreach ($modulesToEnable as $moduleName)
        {
            try
            {
                if ($this->moduleExists($moduleName, $type))
                {
                    $statuses[$type][$moduleName] = true;
                    $successful++;
                    $icon = $this->getModuleTypeIcon($type);
                    info("   {$icon} Enabled: {$moduleName}");
                } else
                {
                    warning("   ⚠️  Skipped: {$moduleName} (not found)");
                    $failed++;
                }
            } catch (Exception $e)
            {
                error("   ❌ Failed: {$moduleName} - {$e->getMessage()}");
                $failed++;
            }
        }

        // Save the updated statuses
        if ($successful > 0)
        {
            $statusFile = base_path('modules_statuses.json');
            file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT));

            // Clear cache
            $builder = new ManifestBuilder();
            $builder->clear();
        }

        // Summary
        if ($successful > 0)
        {
            info("✅ Successfully enabled {$successful} modules of type '{$type}'.");
            info('🚀 All enabled modules are now available for use.');
        }
        if ($failed > 0)
        {
            warning("⚠️  {$failed} modules could not be enabled.");
        }
    }

    /**
     * Check if a module exists in the specified type directory.
     *
     * Verifies the existence of module.json file in the expected location.
     */
    protected function moduleExists(string $moduleName, string $type): bool
    {
        $paths = $this->getConfigPaths();

        if (!isset($paths[$type]))
        {
            return false;
        }

        $modulePath = base_path($paths[$type]['path'].'/'.$moduleName.'/module.json');
        return file_exists($modulePath);
    }

    /**
     * Get the full path for a module.
     */
    protected function getModulePath(string $moduleName, string $type): string
    {
        $paths = $this->getConfigPaths();
        return base_path($paths[$type]['path'].'/'.$moduleName);
    }

    /**
     * Discover all available modules from configured paths.
     *
     * Scans all module type directories and returns discovered modules
     * organized by type.
     */
    protected function getAllModules(): array
    {
        $paths = $this->getConfigPaths();

        $allModules = [];
        foreach ($paths as $type => $pathConfig)
        {
            $fullPath = base_path($pathConfig['path']);
            if (is_dir($fullPath))
            {
                $modules = [];
                foreach (glob($fullPath.'/*/module.json') as $moduleFile)
                {
                    $modules[] = basename(dirname($moduleFile));
                }
                $allModules[$type] = $modules;
            }
        }

        return $allModules;
    }

    /**
     * Get configured module paths with fallback to hardcoded defaults.
     *
     * Retrieves module path configuration from config or provides sensible defaults
     * if configuration is not available.
     */
    protected function getConfigPaths(): array
    {
        $paths = config('modules.paths', []);

        return $paths;
    }

    /**
     * Get the icon for a module type from configuration.
     */
    protected function getModuleTypeIcon(string $type): string
    {
        $paths = $this->getConfigPaths();
        return $paths[$type]['icon'] ?? '📦';
    }

    /**
     * Get the default module type (first available type).
     */
    protected function getDefaultModuleType(): ?string
    {
        $paths = $this->getConfigPaths();
        return array_key_first($paths);
    }

    /**
     * Get current module status configuration.
     *
     * Reads the modules_statuses.json file or creates default structure
     * based on configured module types.
     */
    protected function getStatuses(): array
    {
        $statusFile = base_path('modules_statuses.json');
        if (!file_exists($statusFile))
        {
            // Create default structure based on config
            $paths           = $this->getConfigPaths();
            $defaultStatuses = [];
            foreach (array_keys($paths) as $type)
            {
                $defaultStatuses[$type] = [];
            }
            return $defaultStatuses;
        }

        $content  = file_get_contents($statusFile);
        $statuses = json_decode($content, true);

        if (!is_array($statuses))
        {
            // Create default structure based on config
            $paths           = $this->getConfigPaths();
            $defaultStatuses = [];
            foreach (array_keys($paths) as $type)
            {
                $defaultStatuses[$type] = [];
            }
            return $defaultStatuses;
        }

        return $statuses;
    }
}
