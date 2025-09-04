<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\Services\ModuleService;
use Exception;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

/**
 * ModuleSeedCommand
 *
 * Console command for running module database seeders.
 * Provides both direct module seeding by name and interactive selection.
 * Discovers all available modules with database seeders and allows seeding of enabled modules.
 *
 * Usage:
 * - php artisan modules:seed                     # Interactive selection
 * - php artisan modules:seed User               # Seed User module
 * - php artisan modules:seed User Auth          # Seed specific modules
 * - php artisan modules:seed --interactive      # Force interactive mode
 * - php artisan modules:seed --all              # Seed all modules with seeders
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleSeedCommand extends Command
{
    protected $signature   = 'modules:seed {modules?*} {--interactive : Force interactive selection} {--all : Seed all modules with seeders} {--force : Force seeding without confirmation}';
    protected $description = 'Run database seeders for one or more modules';

    private ModuleService $moduleService;

    public function __construct(ModuleService $moduleService)
    {
        parent::__construct();
        $this->moduleService = $moduleService;
    }

    /**
     * Execute the console command.
     *
     * Handles both direct module seeding and interactive selection based on provided arguments.
     */
    public function handle(): void
    {
        $modules     = $this->argument('modules');
        $interactive = $this->option('interactive');
        $all         = $this->option('all');

        // Handle --all flag
        if ($all)
        {
            $this->seedAllModules();
            return;
        }

        // If no modules specified or interactive flag is used, show interactive selection
        if (empty($modules) || $interactive)
        {
            $this->showInteractiveSelection();
            return;
        }

        // Seed specific modules
        $this->seedModules($modules);
    }

    /**
     * Show interactive module selection for seeding.
     *
     * Uses Laravel Prompts to provide a beautiful interactive selection experience
     * with module information and confirmation prompts.
     */
    protected function showInteractiveSelection(): void
    {
        $modulesWithSeeders = spin(
            callback: fn() => $this->getModulesWithSeeders(),
            message: '🔍  Discovering modules with database seeders...'
        );

        if (empty($modulesWithSeeders))
        {
            warning('❌  No modules with database seeders found.');
            info('💡  Seeders should be located in Module/Database/Seeders/ directory.');
            return;
        }

        $choices    = [];
        $moduleInfo = [];

        foreach ($modulesWithSeeders as $moduleName => $module)
        {
            $key              = $moduleName;
            $icon             = $this->getModuleTypeIcon($module['type']);
            $choices[$key]    = "{$icon} {$moduleName}";
            $moduleInfo[$key] = $module;
        }

        // Direct multiselect without mode selection
        $this->handleMultipleSeed($choices, $moduleInfo);
    }


    /**
     * Handle multiple module seed selection.
     */
    protected function handleMultipleSeed(array $choices, array $moduleInfo): void
    {
        $selectedKeys = multiselect(
            label: 'Which modules would you like to seed?',
            options: $choices,
            hint: 'Select one or more modules to run their seeders (use space to select, enter to confirm)',
            required: true
        );

        if (empty($selectedKeys))
        {
            info('👍  No modules selected.');
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
        warning("⚠️   You are about to run seeders for {$totalCount} modules:");

        foreach ($selectedModules as $module)
        {
            $icon = $this->getModuleTypeIcon($module['type']);
            $this->line("   {$icon} {$module['name']}");
        }

        if (!$this->option('force'))
        {
            $confirmed = confirm(
                label: "Are you sure you want to run seeders for these {$totalCount} modules?",
                default: true,
                yes: 'Yes, run all seeders',
                no: 'No, cancel',
                hint: 'This will execute database seeders for all selected modules'
            );

            if (!$confirmed)
            {
                info('👍  Operation cancelled.');
                return;
            }
        }

        // Seed modules one by one with progress
        $successful = 0;
        $failed     = 0;

        foreach ($selectedModules as $module)
        {
            try
            {
                $this->seedModule($module['name'], $module, true); // Skip individual messages
                $successful++;
                $icon = $this->getModuleTypeIcon($module['type']);
                info("   {$icon} Seeded: {$module['name']}");
            } catch (Exception $e)
            {
                error("   ❌ Failed: {$module['name']} - {$e->getMessage()}");
                $failed++;
            }
        }

        // Summary
        if ($successful > 0)
        {
            info("✅  Successfully seeded {$successful} modules.");
        }
        if ($failed > 0)
        {
            warning("⚠️   {$failed} modules could not be seeded.");
        }
    }

    /**
     * Seed all modules that have database seeders.
     */
    protected function seedAllModules(): void
    {
        $modulesWithSeeders = $this->getModulesWithSeeders();

        if (empty($modulesWithSeeders))
        {
            warning('❌ No modules with database seeders found.');
            info('💡 Seeders should be located in Module/Database/Seeders/ directory.');
            return;
        }

        // Show confirmation with detailed info
        warning("⚠️   You are about to run seeders for ".count($modulesWithSeeders)." modules:");
        foreach ($modulesWithSeeders as $module)
        {
            $icon = $this->getModuleTypeIcon($module['type']);
            $this->line("   {$icon} {$module['name']}");
        }

        if (!$this->option('force'))
        {
            $confirmed = confirm(
                label: 'Are you sure you want to run seeders for all these modules?',
                default: true,
                yes: 'Yes, seed all',
                no: 'No, cancel',
                hint: 'This will execute database seeders for all modules'
            );

            if (!$confirmed)
            {
                info('👍  Operation cancelled.');
                return;
            }
        }

        // Seed modules with progress
        $successful = 0;
        $failed     = 0;

        foreach ($modulesWithSeeders as $moduleName => $module)
        {
            try
            {
                $this->seedModule($moduleName, $module, true);
                $successful++;
                $icon = $this->getModuleTypeIcon($module['type']);
                info("   {$icon} Seeded: {$moduleName}");
            } catch (Exception $e)
            {
                error("   ❌ Failed: {$moduleName} - {$e->getMessage()}");
                $failed++;
            }
        }

        // Summary
        if ($successful > 0)
        {
            info("✅  Successfully seeded {$successful} modules.");
        }
        if ($failed > 0)
        {
            warning("⚠️   {$failed} modules could not be seeded.");
        }
    }

    /**
     * Seed specific modules by name.
     */
    protected function seedModules(array $moduleNames): void
    {
        $modulesWithSeeders = $this->getModulesWithSeeders();
        $successful         = 0;
        $failed             = 0;

        foreach ($moduleNames as $moduleName)
        {
            if (!isset($modulesWithSeeders[$moduleName]))
            {
                error("❌  Module '{$moduleName}' not found or has no seeders.");
                $failed++;
                continue;
            }

            try
            {
                $this->seedModule($moduleName, $modulesWithSeeders[$moduleName], count($moduleNames) > 1);
                $successful++;
                if (count($moduleNames) > 1)
                {
                    $icon = $this->getModuleTypeIcon($modulesWithSeeders[$moduleName]['type']);
                    info("   {$icon} Seeded: {$moduleName}");
                }
            } catch (Exception $e)
            {
                error("❌  Failed to seed '{$moduleName}': {$e->getMessage()}");
                $failed++;
            }
        }

        if (count($moduleNames) > 1)
        {
            // Summary for multiple modules
            if ($successful > 0)
            {
                info("✅  Successfully seeded {$successful} modules.");
            }
            if ($failed > 0)
            {
                warning("⚠️   {$failed} modules could not be seeded.");
            }
        }
    }

    /**
     * Seed a specific module.
     */
    protected function seedModule(string $moduleName, array $moduleData, bool $skipMessages = false): void
    {
        $module = $this->moduleService->find($moduleName);

        if (!$module)
        {
            throw new Exception("Module '{$moduleName}' not found.");
        }

        if (!$module['enabled'])
        {
            throw new Exception("Module '{$moduleName}' is not enabled.");
        }

        // Get the main DatabaseSeeder for the module
        if (empty($moduleData['seeders']))
        {
            throw new Exception("No DatabaseSeeder found for module '{$moduleName}'.");
        }

        $mainSeeder = $moduleData['seeders'][0]; // We only have one seeder now

        // Get the seeder class name
        $seederClass = $this->getSeederClassName($mainSeeder, $module);

        if (!class_exists($seederClass))
        {
            throw new Exception("Seeder class '{$seederClass}' not found for module '{$moduleName}'.");
        }

        // Run the seeder
        $this->call('db:seed', [
            '--class' => $seederClass,
            '--force' => $this->option('force'),
        ]);

        if (!$skipMessages)
        {
            info("✨  Module '{$moduleName}' has been successfully seeded!");
            info('🌱  Database seeding completed.');
        }
    }


    /**
     * Get the seeder class name from file path and module info.
     */
    protected function getSeederClassName(string $seederFile, array $module): string
    {
        $filename  = basename($seederFile, '.php');
        $namespace = $module['namespace'].'\\'.$module['name'];

        return "{$namespace}\\Database\\Seeders\\{$filename}";
    }

    /**
     * Get all modules that have database seeders.
     */
    protected function getModulesWithSeeders(): array
    {
        $allModules         = $this->moduleService->allEnabled();
        $modulesWithSeeders = [];

        foreach ($allModules as $moduleName => $module)
        {
            $seeders = $this->findModuleSeeders($module);
            if (!empty($seeders))
            {
                $modulesWithSeeders[$moduleName] = array_merge($module, [
                    'seeders' => $seeders,
                ]);
            }
        }

        return $modulesWithSeeders;
    }

    /**
     * Find the main DatabaseSeeder for a specific module.
     */
    protected function findModuleSeeders(array $module): array
    {
        $seedersPath = $module['path'].'/Database/Seeders';

        if (!is_dir($seedersPath))
        {
            return [];
        }

        // Look for the main DatabaseSeeder file: ModuleNameDatabaseSeeder.php
        $mainSeederFile = $seedersPath.'/'.$module['name'].'DatabaseSeeder.php';

        if (file_exists($mainSeederFile))
        {
            return [$mainSeederFile];
        }

        return [];
    }

    /**
     * Get the icon for a module type from configuration.
     */
    protected function getModuleTypeIcon(string $type): string
    {
        $paths = config('modules.paths', []);
        return $paths[$type]['icon'] ?? '📦';
    }
}
