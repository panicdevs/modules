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

/**
 * ModuleDisableCommand
 *
 * Console command for disabling modules in the application.
 * Provides both direct module disabling by name and interactive selection.
 * Prevents disabling of protected module types (like foundation modules).
 *
 * Usage:
 * - php artisan modules:disable                    # Interactive selection
 * - php artisan modules:disable User              # Disable User module (default type: modules)
 * - php artisan modules:disable User --type=modules # Disable User module from modules type
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleDisableCommand extends Command
{
    protected $signature   = 'modules:disable {module?} {--type= : Module type from configured paths} {--all : Disable all modules of specified type} {--force : Force disable protected modules}';
    protected $description = 'Disable a module or multiple modules';

    /**
     * Execute the console command.
     *
     * Handles both direct module disabling and interactive selection based on provided arguments.
     */
    public function handle(): void
    {
        $moduleName = $this->argument('module');
        $type       = $this->option('type');
        $all        = $this->option('all');
        $force      = $this->option('force');

        // Handle --all flag
        if ($all)
        {
            $this->disableAllModules($type, $force);
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

        $this->disableModule($moduleName, $type, $force);
    }

    /**
     * Show interactive module selection for disabling.
     *
     * Uses Laravel Prompts to provide a beautiful interactive selection experience.
     * Protected modules are included when --force is used.
     */
    protected function showInteractiveSelection(): void
    {
        info('🔍 Discovering available modules...');

        $force      = $this->option('force');
        $statuses   = $this->getStatuses();
        $choices    = [];
        $moduleInfo = [];

        foreach ($statuses as $type => $modules)
        {
            foreach ($modules as $module => $status)
            {
                if ($status && ($force || !$this->isModuleTypeProtected($type))) // Include protected modules when forced
                {$key                 = "$type/$module";
                    $icon             = $this->getModuleTypeIcon($type);
                    $protectedLabel   = $this->isModuleTypeProtected($type) ? ' [PROTECTED]' : '';
                    $choices[$key]    = "{$icon} {$module} ({$type}){$protectedLabel}";
                    $moduleInfo[$key] = ['name' => $module, 'type' => $type];
                }
            }
        }

        if (empty($choices))
        {
            if ($force)
            {
                warning('No modules available to disable!');
                info('💡 All modules are already disabled.');
            } else
            {
                warning('No modules available to disable!');
                info('💡 Note: Protected modules cannot be disabled without --force flag.');
            }
            return;
        }

        // Ask user if they want to disable single or multiple modules
        $mode = select(
            label: 'How would you like to disable modules?',
            options: [
                'single'   => '📦 Disable a single module',
                'multiple' => '📦📦 Disable multiple modules',
            ],
            hint: $force ? 'Force mode active - can disable protected modules' : 'Normal mode - protected modules excluded'
        );

        if ('multiple' === $mode)
        {
            $this->handleMultipleDisable($choices, $moduleInfo, $force);
        } else
        {
            $this->handleSingleDisable($choices, $moduleInfo, $force);
        }
    }

    /**
     * Handle single module disable selection.
     */
    protected function handleSingleDisable(array $choices, array $moduleInfo, bool $force): void
    {
        $hintText = $force
            ? 'Protected modules marked with [PROTECTED] will be force disabled!'
            : 'Select a module to disable';

        $selected = select(
            label: 'Which module would you like to disable?',
            options: $choices,
            hint: $hintText
        );

        $moduleData = $moduleInfo[$selected];

        // Enhanced confirmation prompt
        $isProtected = $this->isModuleTypeProtected($moduleData['type']);
        $forceText   = ($force && $isProtected) ? ' (FORCE DISABLING PROTECTED MODULE)' : '';
        $warningText = ($force && $isProtected) ? 'This will force disable a protected module!' : 'This action will immediately disable the module';

        $confirmed = confirm(
            label: "Are you sure you want to disable the '{$moduleData['name']}' module?{$forceText}",
            default: false,
            yes: 'Yes, disable it',
            no: 'No, keep it enabled',
            hint: $warningText
        );

        if ($confirmed)
        {
            $this->disableModule($moduleData['name'], $moduleData['type'], $force);
        } else
        {
            info('👍 Module remains enabled.');
        }
    }

    /**
     * Handle multiple module disable selection.
     */
    protected function handleMultipleDisable(array $choices, array $moduleInfo, bool $force): void
    {
        $hintText = $force
            ? 'Select modules to disable (including protected ones with [PROTECTED] label)'
            : 'Select multiple modules to disable';

        $selectedKeys = multiselect(
            label: 'Which modules would you like to disable?',
            options: $choices,
            hint: $hintText
        );

        if (empty($selectedKeys))
        {
            info('👍 No modules selected.');
            return;
        }

        // Gather selected modules info
        $selectedModules = [];
        $protectedCount  = 0;
        foreach ($selectedKeys as $key)
        {
            $moduleData        = $moduleInfo[$key];
            $selectedModules[] = $moduleData;
            if ($this->isModuleTypeProtected($moduleData['type']))
            {
                $protectedCount++;
            }
        }

        // Show summary and confirmation
        $totalCount = count($selectedModules);
        warning("⚠️  You are about to disable {$totalCount} modules:");

        foreach ($selectedModules as $module)
        {
            $icon           = $this->getModuleTypeIcon($module['type']);
            $protectedLabel = $this->isModuleTypeProtected($module['type']) ? ' [PROTECTED]' : '';
            $this->line("   {$icon} {$module['name']} ({$module['type']}){$protectedLabel}");
        }

        if ($protectedCount > 0)
        {
            warning("   🛡️  {$protectedCount} protected modules will be force disabled!");
        }

        $confirmed = confirm(
            label: "Are you sure you want to disable these {$totalCount} modules?",
            default: false,
            yes: 'Yes, disable all selected',
            no: 'No, cancel',
            hint: $protectedCount > 0 ? 'This includes protected modules!' : 'This will disable multiple modules'
        );

        if (!$confirmed)
        {
            info('👍 Operation cancelled.');
            return;
        }

        // Disable modules one by one with progress
        $successful = 0;
        $failed     = 0;

        foreach ($selectedModules as $module)
        {
            try
            {
                $this->disableModule($module['name'], $module['type'], $force, true); // Skip individual confirmations and messages
                $successful++;
                $icon = $this->getModuleTypeIcon($module['type']);
                info("   {$icon} Disabled: {$module['name']}");
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
            info("✅ Successfully disabled {$successful} modules.");
        }
        if ($failed > 0)
        {
            warning("⚠️  {$failed} modules could not be disabled.");
        }
        if ($successful > 0)
        {
            warning('💡 Remember to clear your application cache if needed.');
        }
    }

    /**
     * Disable a specific module.
     *
     * Validates the module exists and is not protected before disabling.
     * Updates the modules_statuses.json file and clears the manifest cache.
     */
    protected function disableModule(string $moduleName, string $type, bool $force = false, bool $skipMessages = false): void
    {
        // Check if this module type is protected (unless forced)
        if (!$force && $this->isModuleTypeProtected($type))
        {
            error("🚫 Modules of type '{$type}' cannot be disabled (protected).");
            warning('Protected modules are essential for system operation.');
            warning('💡 Use --force to override protection (use with caution).');
            return;
        }

        if ($force && $this->isModuleTypeProtected($type))
        {
            warning("⚠️  Force disabling protected module '{$moduleName}' of type '{$type}'!");
        }

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

        if (!($statuses[$type][$moduleName] ?? false))
        {
            warning("⚠️  Module '{$moduleName}' is already disabled.");
            return;
        }

        $statuses[$type][$moduleName] = false;

        file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT));

        // Clear cache to force rebuild (only once per operation)
        if (!$skipMessages)
        {
            $builder = new ManifestBuilder();
            $builder->clear();
        }

        if (!$skipMessages)
        {
            info("✅ Module '{$moduleName}' has been successfully disabled!");
            warning('💡 Remember to clear your application cache if needed.');
        }
    }

    /**
     * Disable all modules of a specific type.
     */
    protected function disableAllModules(?string $type, bool $force): void
    {
        // If no type specified, let user choose or use all types
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
                    label: 'Which module type would you like to disable all modules for?',
                    options: array_combine($availableTypes, $availableTypes),
                    hint: 'This will disable ALL modules of the selected type'
                );
            }
        }

        $statuses = $this->getStatuses();

        if (!isset($statuses[$type]))
        {
            error("❌ Module type '{$type}' not found.");
            info('Available types: '.implode(', ', array_keys($this->getConfigPaths())));
            return;
        }

        $modulesToDisable = [];
        foreach ($statuses[$type] as $moduleName => $status)
        {
            if ($status) // Only enabled modules
            {// Check if we can disable this module
                if ($force || !$this->isModuleTypeProtected($type))
                {
                    $modulesToDisable[] = $moduleName;
                }
            }
        }

        if (empty($modulesToDisable))
        {
            if ($this->isModuleTypeProtected($type) && !$force)
            {
                warning("⚠️  No modules to disable. Type '{$type}' is protected.");
                info('💡 Use --force to override protection.');
            } else
            {
                info("✅ All modules of type '{$type}' are already disabled.");
            }
            return;
        }

        // Show confirmation with detailed info
        $protectedWarning = $this->isModuleTypeProtected($type) ? ' (PROTECTED TYPE)' : '';
        $forceText        = $force ? ' with --force' : '';

        warning("⚠️  You are about to disable ".count($modulesToDisable)." modules of type '{$type}'{$protectedWarning}{$forceText}:");
        foreach ($modulesToDisable as $module)
        {
            $this->line("   📦 {$module}");
        }

        $confirmed = confirm(
            label: 'Are you sure you want to disable all these modules?',
            default: false,
            yes: 'Yes, disable all',
            no: 'No, cancel',
            hint: $force ? 'This will force disable protected modules!' : 'This action will disable multiple modules'
        );

        if (!$confirmed)
        {
            info('👍 Operation cancelled.');
            return;
        }

        // Disable modules with progress
        $successful = 0;
        $failed     = 0;

        foreach ($modulesToDisable as $moduleName)
        {
            try
            {
                if ($this->moduleExists($moduleName, $type))
                {
                    $statuses[$type][$moduleName] = false;
                    $successful++;
                    $icon = $this->getModuleTypeIcon($type);
                    info("   {$icon} Disabled: {$moduleName}");
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
            info("✅ Successfully disabled {$successful} modules of type '{$type}'.");
        }
        if ($failed > 0)
        {
            warning("⚠️  {$failed} modules could not be disabled.");
        }
        if ($successful > 0)
        {
            warning('💡 Remember to clear your application cache if needed.');
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
     * Get configured module paths with fallback to hardcoded defaults.
     *
     * Retrieves module path configuration from config or provides sensible defaults
     * if configuration is not available.
     */
    protected function getConfigPaths(): array
    {
        $paths = config('modules.paths', []);

        // Fallback to hardcoded paths if config is empty
        if (empty($paths))
        {
            $paths = [
                'foundation' => [
                    'path'      => 'foundation',
                    'namespace' => 'Foundation',
                ],
                'modules' => [
                    'path'      => 'modules',
                    'namespace' => 'Modules',
                ],
            ];
        }

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
     * Check if a module type is protected from being disabled.
     */
    protected function isModuleTypeProtected(string $type): bool
    {
        $paths = $this->getConfigPaths();
        return $paths[$type]['protected'] ?? false;
    }

    /**
     * Get the default module type (first non-protected type).
     */
    protected function getDefaultModuleType(): ?string
    {
        $paths = $this->getConfigPaths();
        foreach ($paths as $type => $config)
        {
            if (!($config['protected'] ?? false))
            {
                return $type;
            }
        }
        return null;
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
