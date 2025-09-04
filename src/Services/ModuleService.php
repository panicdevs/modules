<?php

declare(strict_types=1);

namespace PanicDevs\Modules\Services;

use PanicDevs\Modules\ManifestBuilder;

class ModuleService
{
    private ManifestBuilder $manifestBuilder;
    private ?array $cachedModules = null;

    public function __construct(ManifestBuilder $manifestBuilder)
    {
        $this->manifestBuilder = $manifestBuilder;
    }

    /**
     * Get all enabled modules
     */
    public function allEnabled(): array
    {
        if (null === $this->cachedModules)
        {
            $this->cachedModules = $this->manifestBuilder->build();
        }

        return array_filter($this->cachedModules, fn($module) => $module['enabled']);
    }

    /**
     * Get all modules (enabled and disabled)
     */
    public function all(): array
    {
        if (null === $this->cachedModules)
        {
            $this->cachedModules = $this->manifestBuilder->build();
        }

        return $this->cachedModules;
    }

    /**
     * Get a specific module by name
     */
    public function find(string $name): ?array
    {
        $modules = $this->all();
        return $modules[$name] ?? null;
    }

    /**
     * Check if a module exists and is enabled
     */
    public function isEnabled(string $name): bool
    {
        $module = $this->find($name);
        return $module && $module['enabled'];
    }

    /**
     * Get module path
     */
    public function getPath(string $name): ?string
    {
        $module = $this->find($name);
        return $module ? $module['path'] : null;
    }

    /**
     * Get module namespace
     */
    public function getNamespace(string $name): ?string
    {
        $module = $this->find($name);
        if (!$module)
        {
            return null;
        }

        return $module['namespace'].'\\'.$module['name'].'\\';
    }

    /**
     * Get module alias (from module.json, fallback to lowercase name)
     */
    public function getAlias(string $name): ?string
    {
        $module = $this->find($name);
        return $module ? $module['alias'] : null;
    }

    /**
     * Get module config path
     */
    public function getConfigPath(string $name): ?string
    {
        $path = $this->getPath($name);
        return $path ? $path.'/Config' : null;
    }

    /**
     * Get module language path
     */
    public function getLanguagePath(string $name): ?string
    {
        $path = $this->getPath($name);
        return $path ? $path.'/Lang' : null;
    }

    /**
     * Get module views path
     */
    public function getViewsPath(string $name): ?string
    {
        $path = $this->getPath($name);
        return $path ? $path.'/Resources/views' : null;
    }

    /**
     * Get modules ordered by priority (for loading order)
     */
    public function getEnabledByPriority(): array
    {
        $modules = $this->allEnabled();

        // Sort by priority (lower number = higher priority)
        uasort($modules, fn($a, $b) => ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0));

        return $modules;
    }

    /**
     * Get modules by type
     */
    public function getByType(string $type): array
    {
        $modules = $this->all();
        return array_filter($modules, fn($module) => $module['type'] === $type);
    }

    /**
     * Get enabled modules by type
     */
    public function getEnabledByType(string $type): array
    {
        $modules = $this->allEnabled();
        return array_filter($modules, fn($module) => $module['type'] === $type);
    }

    /**
     * Get module providers
     */
    public function getProviders(string $name): array
    {
        $module = $this->find($name);
        return $module ? $module['providers'] : [];
    }

    /**
     * Get module files to include
     */
    public function getFiles(string $name): array
    {
        $module = $this->find($name);
        return $module ? $module['files'] : [];
    }

    /**
     * Get module title
     */
    public function getTitle(string $name): ?string
    {
        $module = $this->find($name);
        return $module ? $module['title'] : null;
    }

    /**
     * Get module description
     */
    public function getDescription(string $name): ?string
    {
        $module = $this->find($name);
        return $module ? $module['description'] : null;
    }

    /**
     * Get module version
     */
    public function getVersion(string $name): ?string
    {
        $module = $this->find($name);
        return $module ? (string) $module['version'] : null;
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(string $name): array
    {
        $module = $this->find($name);
        return $module ? $module['depends_on'] : [];
    }

    /**
     * Find module by alias
     */
    public function findByAlias(string $alias): ?array
    {
        $modules = $this->all();
        foreach ($modules as $module)
        {
            if ($module['alias'] === $alias)
            {
                return $module;
            }
        }
        return null;
    }

    /**
     * Get all module aliases mapped to names
     */
    public function getAllAliases(): array
    {
        $modules = $this->all();
        $aliases = [];
        foreach ($modules as $module)
        {
            $aliases[$module['alias']] = $module['name'];
        }
        return $aliases;
    }

    /**
     * Clear the module cache
     */
    public function clearCache(): void
    {
        $this->manifestBuilder->clear();
        $this->cachedModules = null;
    }

    /**
     * Get module statistics
     */
    public function getStats(): array
    {
        $modules = $this->all();
        $enabled = $this->allEnabled();

        $byType = [];
        foreach ($modules as $module)
        {
            $type          = $module['type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total'    => count($modules),
            'enabled'  => count($enabled),
            'disabled' => count($modules) - count($enabled),
            'by_type'  => $byType,
        ];
    }
}
