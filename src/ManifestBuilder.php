<?php

declare(strict_types=1);

namespace PanicDevs\Modules;

use JsonException;
use RuntimeException;

/**
 * ManifestBuilder
 *
 * Builds and caches a manifest of all available modules in the application.
 * This class discovers modules from configured paths, respects module status
 * configurations, and provides caching for improved performance.
 *
 * Features:
 * - Discovers modules from multiple configured paths (foundation, modules, etc.)
 * - Respects module enable/disable status from modules_statuses.json
 * - Provides intelligent caching with cache freshness detection
 * - Preserves all module.json data while adding computed fields
 * - Sorts modules by priority for proper loading order
 *
 * @package PanicDevs\Modules
 * @author  PanicDevs
 * @since   1.0.0
 */
class ManifestBuilder
{
    /**
     * Static cache for the manifest to avoid rebuilding within the same request.
     */
    protected static ?array $cachedManifest = null;

    /**
     * Initialize the ManifestBuilder.
     */
    public function __construct()
    {
        // Intentionally simple - all logic is in build() method
    }

    /**
     * Build the complete module manifest.
     *
     * This method discovers all modules from configured paths, checks their status,
     * and returns a comprehensive manifest with caching support for performance.
     *
     * The manifest includes:
     * - All data from module.json files
     * - Computed fields (path, type, namespace, enabled status)
     * - Modules sorted by priority (highest first)
     *
     * @throws JsonException If module.json files contain invalid JSON
     */
    public function build(): array
    {
        // Static cache for same request
        if (null !== self::$cachedManifest)
        {
            return self::$cachedManifest;
        }

        // Try cache first if enabled
        $cacheEnabled = config('modules.cache', true);
        $cacheFile    = $this->getCachePath();

        if ($cacheEnabled && file_exists($cacheFile) && $this->isCacheFresh())
        {
            return self::$cachedManifest = include $cacheFile;
        }

        // Discover modules
        $modules = $this->discoverModules();

        // Write cache if enabled
        if ($cacheEnabled)
        {
            $this->writeCache($modules);
        }

        return self::$cachedManifest = $modules;
    }

    /**
     * Get the path to the module manifest cache file.
     */
    protected function getCachePath(): string
    {
        return base_path('bootstrap/cache/modules.php');
    }

    /**
     * Get the path to the module status configuration file.
     *
     * This file contains the enable/disable status for all modules.
     */
    protected function getStatusPath(): string
    {
        return base_path('modules_statuses.json');
    }

    /**
     * Check if the cached manifest is still fresh.
     *
     * The cache is considered fresh if it's newer than the modules_statuses.json file.
     * If no status file exists, the cache is considered fresh.
     */
    protected function isCacheFresh(): bool
    {
        $statusPath = $this->getStatusPath();
        if (!file_exists($statusPath))
        {
            return true; // No status file means no changes
        }

        $cacheTime  = filemtime($this->getCachePath());
        $statusTime = filemtime($statusPath);

        return $cacheTime >= $statusTime;
    }

    /**
     * Discover all modules from configured paths.
     *
     * This method scans all configured module paths, reads module.json files,
     * and builds a comprehensive manifest respecting module status settings.
     */
    protected function discoverModules(): array
    {
        $modules = [];

        // Get module paths configuration and status settings
        $paths    = $this->getModulePaths();
        $statuses = $this->getStatuses();

        foreach ($paths as $pathType => $pathConfig)
        {
            $basePath = base_path($pathConfig['path']);

            if (!is_dir($basePath))
            {
                continue;
            }

            $namespace      = $pathConfig['namespace'];
            $moduleStatuses = $statuses[$pathType] ?? [];

            foreach (glob($basePath.'/*/module.json') as $moduleFile)
            {
                $moduleDir  = dirname($moduleFile);
                $moduleName = basename($moduleDir);

                // Skip disabled modules
                if (!($moduleStatuses[$moduleName] ?? false))
                {
                    continue;
                }

                $moduleData = json_decode(file_get_contents($moduleFile), true);

                // Merge all module.json data with computed fields
                // This preserves custom fields while ensuring standard fields exist
                $modules[$moduleName] = array_merge($moduleData, [
                    // Computed fields
                    'name'      => $moduleName,
                    'path'      => $moduleDir,
                    'type'      => $pathType,
                    'namespace' => $namespace,
                    'enabled'   => $moduleStatuses[$moduleName] ?? false,

                    // Standard fields with defaults (preserves original data)
                    'priority'    => $moduleData['priority'] ?? 0,
                    'providers'   => $moduleData['providers'] ?? [],
                    'files'       => $moduleData['files'] ?? [],
                    'alias'       => $moduleData['alias'] ?? mb_strtolower($moduleName),
                    'title'       => $moduleData['title'] ?? $moduleName,
                    'description' => $moduleData['description'] ?? '',
                    'version'     => $moduleData['version'] ?? '0.0.0',
                    'depends_on'  => $moduleData['depends_on'] ?? [],
                ]);
            }
        }

        // Sort modules by priority (highest first) for proper loading order
        uasort($modules, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        return $modules;
    }

    /**
     * Get module status configuration from modules_statuses.json.
     */
    protected function getStatuses(): array
    {
        $statusPath = $this->getStatusPath();
        if (!file_exists($statusPath))
        {
            return [];
        }

        $data = json_decode(file_get_contents($statusPath), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write the module manifest to cache file.
     *
     * Creates the cache directory if it doesn't exist and writes the manifest
     * as a PHP file that can be included for fast loading.
     *
     * @throws RuntimeException If the cache file cannot be written
     */
    protected function writeCache(array $modules): void
    {
        $cacheFile = $this->getCachePath();
        $cacheDir  = dirname($cacheFile);

        // Ensure cache directory exists
        if (!is_dir($cacheDir))
        {
            mkdir($cacheDir, 0755, true);
        }

        // Write manifest as PHP file for fast inclusion
        $content = '<?php return '.var_export($modules, true).';';
        file_put_contents($cacheFile, $content, LOCK_EX);
    }

    /**
     * Get PSR-4 namespace mappings for a specific module.
     *
     * Generates the namespace mapping based on the module's configured namespace
     * and name. This is used for autoloading module classes.
     */
    public function getModuleNamespaces(array $module): array
    {
        // Namespace format: ConfigNamespace\ModuleName
        // Examples: Foundation\Api, Modules\User, etc.
        $namespace = $module['namespace'].'\\'.$module['name'].'\\';

        return [
            $namespace => '', // Maps to module root directory
        ];
    }

    /**
     * Get configured module paths from configuration.
     *
     * Retrieves the module paths configuration which defines where to look
     * for modules and their associated namespaces.
     */
    protected function getModulePaths(): array
    {
        return config('modules.paths', []);
    }

    /**
     * Clear the module manifest cache.
     *
     * Removes both the in-memory cache and the cached file, forcing
     * a fresh discovery on the next build() call.
     */
    public function clear(): void
    {
        // Clear in-memory cache
        self::$cachedManifest = null;

        // Remove cached file
        $cacheFile = $this->getCachePath();
        if (file_exists($cacheFile))
        {
            unlink($cacheFile);
        }
    }
}
