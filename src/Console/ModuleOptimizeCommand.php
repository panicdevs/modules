<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\ManifestBuilder;

/**
 * ModuleOptimizeCommand
 *
 * Optimizes module loading by caching the module manifest.
 * This command is automatically called by Laravel's `optimize` command.
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleOptimizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:cache
                           {--force : Force cache regeneration even if cache exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache the module manifest for better performance';

    /**
     * Execute the console command.
     */
    public function handle(ManifestBuilder $manifestBuilder): int
    {
        $this->info('Caching module manifest...');

        $cacheFile = base_path('bootstrap/cache/modules.php');

        // Check if cache exists and force flag
        if (!$this->option('force') && file_exists($cacheFile))
        {
            $this->info('Module cache already exists. Use --force to regenerate.');
            return self::SUCCESS;
        }

        // Clear existing cache first
        $manifestBuilder->clear();

        // Build and cache the manifest
        $modules = $manifestBuilder->build();

        $enabledCount = count(array_filter($modules, fn($module) => $module['enabled']));
        $totalCount   = count($modules);

        $this->info("Module manifest cached successfully!");
        $this->line("- <fg=green>{$enabledCount}</> enabled modules");
        $this->line("- <fg=yellow>{$totalCount}</> total modules");
        $this->line("- Cache file: <fg=blue>{$cacheFile}</>");

        return self::SUCCESS;
    }
}
