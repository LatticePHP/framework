<?php

declare(strict_types=1);

namespace Lattice\Prism\Auth;

use Lattice\Prism\Database\Project;

final class ApiKeyAuthenticator
{
    /**
     * In-memory project store keyed by ID.
     *
     * @var array<string, Project>
     */
    private array $projects = [];

    /**
     * Register a project for authentication lookups.
     */
    public function registerProject(Project $project): void
    {
        $this->projects[$project->id] = $project;
    }

    /**
     * Authenticate a raw API key, returning the matching project or null.
     *
     * Checks the X-Prism-Key header value or Bearer token against all registered
     * project API key hashes.
     */
    public function authenticate(string $rawKey): ?Project
    {
        if ($rawKey === '') {
            return null;
        }

        $hash = Project::hashApiKey($rawKey);

        foreach ($this->projects as $project) {
            if ($project->apiKeyHash === $hash) {
                return $project;
            }
        }

        return null;
    }

    /**
     * Extract the API key from request headers.
     *
     * Supports:
     * - X-Prism-Key: <key>
     * - Authorization: Bearer <key>
     *
     * @param array<string, string|list<string>> $headers
     */
    public function extractKey(array $headers): ?string
    {
        // Normalize header names to lowercase
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? ($value[0] ?? '') : $value;
        }

        // X-Prism-Key takes precedence
        if (isset($normalized['x-prism-key']) && $normalized['x-prism-key'] !== '') {
            return $normalized['x-prism-key'];
        }

        // Fall back to Authorization: Bearer
        if (isset($normalized['authorization'])) {
            $auth = $normalized['authorization'];
            if (str_starts_with($auth, 'Bearer ')) {
                $token = substr($auth, 7);
                if ($token !== '') {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * Reset all registered projects (for testing).
     */
    public function reset(): void
    {
        $this->projects = [];
    }
}
