<?php

declare(strict_types=1);

namespace Lattice\Compiler\Manifest;

use Lattice\Compiler\Exceptions\StaleManifestException;
use Lattice\Compiler\Graph\ModuleGraph;
use Lattice\Compiler\Graph\ModuleNode;

final class ManifestLoader
{
    /**
     * Load a compiled manifest and reconstruct the ModuleGraph.
     *
     * @throws StaleManifestException If the manifest is missing or invalid
     */
    public function load(string $path): ModuleGraph
    {
        if (!$this->isValid($path)) {
            throw new StaleManifestException($path);
        }

        $data = require $path;

        $modules = [];

        foreach ($data['modules'] as $name => $nodeData) {
            $modules[$name] = ModuleNode::fromArray($nodeData);
        }

        return new ModuleGraph($modules, $data['topological_order']);
    }

    /**
     * Check whether the manifest file exists and contains valid data.
     */
    public function isValid(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $data = require $path;

        if (!is_array($data)) {
            return false;
        }

        if (!isset($data['modules'], $data['topological_order'], $data['compiled_at'])) {
            return false;
        }

        return true;
    }
}
