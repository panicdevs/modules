<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Console;

use Illuminate\Console\Command;
use PanicDevs\Modules\ManifestBuilder;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;
use function Laravel\Prompts\spin;

/**
 * ModuleTestCommand
 *
 * Console command for testing module system performance and functionality.
 * Provides cache performance testing, module discovery benchmarks,
 * and detailed module information display.
 *
 * Usage:
 * - php artisan modules:test                # Basic module discovery test
 * - php artisan modules:test --cache        # Include cache performance testing
 * - php artisan modules:test --details      # Show detailed module information
 *
 * @package PanicDevs\Modules\Console
 * @author  PanicDevs
 * @since   1.0.0
 */
class ModuleTestCommand extends Command
{
    protected $signature   = 'modules:test {--details : Show detailed module information} {--cache : Test cache performance} {--interactive : Interactive test mode}';
    protected $description = 'Test module loading performance and functionality';

    /**
     * Execute the console command.
     *
     * Runs various tests and benchmarks on the module system based on provided options.
     */
    public function handle(): void
    {
        if ($this->option('interactive'))
        {
            $this->handleInteractiveMode();
            return;
        }

        info('🧪 Module System Performance Test');
        $this->line('<fg=gray>═══════════════════════════════════════════════════════════</fg=gray>');
        $this->newLine();

        if ($this->option('cache'))
        {
            $this->testCachePerformance();
        }

        $this->testModuleDiscovery();

        if ($this->option('details'))
        {
            $this->showModuleDetails();
        }

        $this->newLine();
        info('🎉 All tests completed!');
    }

    /**
     * Handle interactive test mode with Laravel Prompts.
     */
    protected function handleInteractiveMode(): void
    {
        info('🧪 Welcome to Interactive Module Testing!');

        $testOptions = multiselect(
            label: 'Which tests would you like to run?',
            options: [
                'discovery'  => '🔍 Module Discovery Test',
                'cache'      => '⚡ Cache Performance Test',
                'details'    => '📋 Detailed Module Information',
                'validation' => '✅ Module Validation Test'
            ],
            default: ['discovery'],
            hint: 'Select one or more tests to run'
        );

        if (empty($testOptions))
        {
            warning('No tests selected. Exiting.');
            return;
        }

        if (in_array('discovery', $testOptions))
        {
            $this->testModuleDiscovery();
        }

        if (in_array('cache', $testOptions))
        {
            $this->testCachePerformance();
        }

        if (in_array('validation', $testOptions))
        {
            $this->testModuleValidation();
        }

        if (in_array('details', $testOptions))
        {
            $this->showModuleDetails();
        }

        info('🎉 All selected tests completed!');
    }

    /**
     * Test module validation by checking configurations and files.
     */
    protected function testModuleValidation(): void
    {
        info('✅ Testing Module Validation');

        $modules = spin(
            callback: fn() => (new ManifestBuilder())->build(),
            message: '🔍 Validating modules...'
        );

        $issues = [];
        foreach ($modules as $name => $module)
        {
            // Check if module.json exists
            $moduleJsonPath = $module['path'].'/module.json';
            if (!file_exists($moduleJsonPath))
            {
                $issues[] = "❌ {$name}: Missing module.json";
                continue;
            }

            // Check required files
            if (!empty($module['files']))
            {
                foreach ($module['files'] as $file)
                {
                    $filePath = $module['path'].'/'.$file;
                    if (!file_exists($filePath))
                    {
                        $issues[] = "⚠️  {$name}: Missing required file '{$file}'";
                    }
                }
            }

            // Check providers
            if (!empty($module['providers']))
            {
                foreach ($module['providers'] as $provider)
                {
                    if (!class_exists($provider))
                    {
                        $issues[] = "⚠️  {$name}: Provider class '{$provider}' not found";
                    }
                }
            }

            // Check dependencies
            if (!empty($module['depends_on']))
            {
                foreach ($module['depends_on'] as $dependency)
                {
                    if (!isset($modules[$dependency]))
                    {
                        $issues[] = "❌ {$name}: Missing dependency '{$dependency}'";
                    } elseif (!$modules[$dependency]['enabled'])
                    {
                        $issues[] = "⚠️  {$name}: Dependency '{$dependency}' is disabled";
                    }
                }
            }
        }

        if (empty($issues))
        {
            info('🎉 All modules passed validation!');
        } else
        {
            warning('Found '.count($issues).' validation issues:');
            foreach ($issues as $issue)
            {
                $this->line("   {$issue}");
            }
        }

        $this->newLine();
    }

    /**
     * Test cache performance by comparing cold vs warm start times.
     *
     * Clears cache, measures cold start time, then measures warm start time
     * to demonstrate caching effectiveness.
     */
    protected function testCachePerformance(): void
    {
        $this->info('📊 <fg=yellow>Testing Cache Performance</fg=yellow>');
        $this->newLine();

        $builder = new ManifestBuilder();
        $builder->clear();
        $this->line('   🗑️ Cache cleared');

        // Cold start
        $start    = microtime(true);
        $modules  = $builder->build();
        $coldTime = (microtime(true) - $start) * 1000;
        $this->line("   🥶 Cold start: <fg=magenta>{$coldTime}ms</fg=magenta>");

        // Warm start
        $start    = microtime(true);
        $modules  = $builder->build();
        $warmTime = (microtime(true) - $start) * 1000;
        $this->line("   🔥 Warm start: <fg=green>{$warmTime}ms</fg=green>");

        $improvement = $coldTime > 0 ? round((($coldTime - $warmTime) / $coldTime) * 100, 1) : 0;
        $this->line("   ⚡️ Improvement: <fg=cyan>{$improvement}%</fg=cyan>");
        $this->newLine();
    }

    /**
     * Test module discovery functionality and performance.
     *
     * Measures discovery time and displays module statistics organized by type.
     */
    protected function testModuleDiscovery(): void
    {
        $this->info('🔍 <fg=yellow>Testing Module Discovery</fg=yellow>');
        $this->newLine();

        $start           = microtime(true);
        $manifestBuilder = new ManifestBuilder();
        $modules         = $manifestBuilder->build();
        $end             = microtime(true);

        $loadTime = ($end - $start) * 1000;

        if ($loadTime > 1)
        {
            $this->line("   ⏱️ Load time: <fg=red>{$loadTime}ms</fg=red> (⚠️  Over 1ms!)");
        } else
        {
            $this->line("   ⏱️ Load time: <fg=green>{$loadTime}ms</fg=green> (✅ Under 1ms)");
        }

        $counts       = [];
        $totalEnabled = 0;
        foreach ($modules as $name => $module)
        {
            $type          = $module['type'] ?? 'unknown';
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            if ($module['enabled'])
            {
                $totalEnabled++;
            }
        }

        $this->line("   📦 Total: <fg=cyan>".count($modules)."</fg=cyan> | ✅ Enabled: <fg=green>{$totalEnabled}</fg=green>");

        foreach ($counts as $type => $count)
        {
            $this->line("   📁 {$type}: <fg=yellow>{$count}</fg=yellow>");
        }
        $this->newLine();
    }

    /**
     * Display detailed information about all discovered modules.
     *
     * Shows module name, status, type, and priority for each module using table format.
     */
    protected function showModuleDetails(): void
    {
        info('📋 Module Details');

        $modules = spin(
            callback: fn() => (new ManifestBuilder())->build(),
            message: '🔍 Loading module details...'
        );

        $rows = [];
        foreach ($modules as $name => $module)
        {
            $status   = $module['enabled'] ? '✅ Enabled' : '❌ Disabled';
            $priority = (string)($module['priority'] ?? 0);
            $type     = ucfirst($module['type'] ?? 'unknown');
            $version  = $module['version'] ?? '0.0.0';
            $icon     = $this->getModuleTypeIcon($module['type'] ?? 'unknown');

            $rows[] = [
                "{$icon} {$name}",
                $type,
                $status,
                $priority,
                $version
            ];
        }

        table(
            headers: ['Module', 'Type', 'Status', 'Priority', 'Version'],
            rows: $rows
        );

        $this->newLine();
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
