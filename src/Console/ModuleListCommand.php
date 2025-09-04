<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\ManifestBuilder;

/**
 * ModuleListCommand
 *
 * Console command for listing all modules with their current status.
 * Provides filtering options to show only enabled or disabled modules.
 * Displays modules organized by type with priority information.
 *
 * Usage:
 * - php artisan modules:list                # List all modules
 * - php artisan modules:list --enabled      # Show only enabled modules
 * - php artisan modules:list --disabled     # Show only disabled modules
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleListCommand extends Command
{
    protected $signature   = 'modules:list {--enabled : Show only enabled modules} {--disabled : Show only disabled modules}';
    protected $description = 'List all modules with their status';

    /**
     * Execute the console command.
     *
     * Lists all modules with filtering options and displays summary statistics.
     */
    public function handle(): void
    {
        $builder = new ManifestBuilder();
        $modules = $builder->build();

        $showEnabled  = $this->option('enabled');
        $showDisabled = $this->option('disabled');

        // If both or neither are specified, show all
        if (($showEnabled && $showDisabled) || (!$showEnabled && !$showDisabled))
        {
            $showEnabled = $showDisabled = true;
        }

        $this->info('📦 Module Status Overview');
        $this->line('');

        // Group modules by type
        $modulesByType = [];
        foreach ($modules as $moduleName => $module)
        {
            $type                   = $module['type'] ?? 'unknown';
            $modulesByType[$type][] = $module;
        }

        foreach ($modulesByType as $type => $typeModules)
        {
            $this->line("📁 <fg=yellow>".ucfirst($type)." Modules</>");

            foreach ($typeModules as $module)
            {
                $enabled = $module['enabled'];

                // Filter based on options
                if (!$showEnabled && $enabled)
                {
                    continue;
                }
                if (!$showDisabled && !$enabled)
                {
                    continue;
                }

                $status   = $enabled ? '<fg=green>✅ Enabled</>' : '<fg=red>❌ Disabled</>';
                $priority = ' (Priority: '.($module['priority'] ?? 0).')';

                $this->line("   {$module['name']}: {$status}{$priority}");
            }

            $this->line('');
        }

        // Summary
        $enabledCount  = 0;
        $disabledCount = 0;

        foreach ($modules as $module)
        {
            if ($module['enabled'])
            {
                $enabledCount++;
            } else
            {
                $disabledCount++;
            }
        }

        $this->info("📊 Summary: {$enabledCount} enabled, {$disabledCount} disabled modules");
    }

    /**
     * Get available filtering options.
     */
    protected function getFilterOptions(): array
    {
        return [
            'all'      => 'All modules',
            'enabled'  => 'Enabled only',
            'disabled' => 'Disabled only',
        ];
    }
}
