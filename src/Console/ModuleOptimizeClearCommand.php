<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\ManifestBuilder;

/**
 * ModuleOptimizeClearCommand
 *
 * Clears module optimization cache.
 * This command is automatically called by Laravel's `optimize:clear` command.
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleOptimizeClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cached module manifest';

    /**
     * Execute the console command.
     */
    public function handle(ManifestBuilder $manifestBuilder): int
    {
        $this->info('Clearing module cache...');

        $cacheFile = base_path('bootstrap/cache/modules.php');

        if (!file_exists($cacheFile))
        {
            $this->info('Module cache does not exist.');
            return self::SUCCESS;
        }

        // Clear the cache using ManifestBuilder
        $manifestBuilder->clear();

        $this->info('Module cache cleared successfully!');
        $this->line("- Removed: <fg=blue>{$cacheFile}</>");

        return self::SUCCESS;
    }
}
