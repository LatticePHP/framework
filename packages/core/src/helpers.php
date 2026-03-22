<?php

declare(strict_types=1);

use Lattice\Core\Application;
use Lattice\Http\Response;

if (!function_exists('app')) {
    /**
     * Get the application instance or resolve a binding.
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $instance = Application::getInstance();
        if ($abstract === null) {
            return $instance;
        }
        return $instance->getContainer()->make($abstract, $parameters);
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve an abstract from the container.
     */
    function resolve(string $abstract, array $parameters = []): mixed
    {
        return app($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get a configuration value.
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = app('config');
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable with type casting.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        // Type casting
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception.
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        throw match ($code) {
            401 => new \Lattice\Http\Exception\UnauthorizedException($message ?: 'Unauthorized'),
            403 => new \Lattice\Http\Exception\ForbiddenException($message ?: 'Forbidden'),
            404 => new \Lattice\Http\Exception\NotFoundException($message ?: 'Not Found'),
            429 => new \Lattice\Http\Exception\HttpException($message ?: 'Too Many Requests', 429),
            default => new \Lattice\Http\Exception\HttpException($message ?: 'Error', $code),
        };
    }
}

if (!function_exists('abort_if')) {
    /**
     * Throw an HTTP exception if the given condition is true.
     */
    function abort_if(bool $condition, int $code, string $message = ''): void
    {
        if ($condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * Throw an HTTP exception unless the given condition is true.
     */
    function abort_unless(bool $condition, int $code, string $message = ''): void
    {
        if (!$condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('now')) {
    /**
     * Get the current date and time as an immutable instance.
     */
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}

if (!function_exists('response')) {
    /**
     * Create a new response instance.
     */
    function response(mixed $data = null, int $status = 200, array $headers = []): Response
    {
        if ($data === null) {
            return Response::noContent();
        }
        return Response::json($data, $status);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application.
     */
    function base_path(string $path = ''): string
    {
        return app()->getBasePath() . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path.
     */
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path.
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue.
     */
    function dispatch(object $job): string
    {
        /** @var \Lattice\Queue\JobInterface $job */
        return \Lattice\Queue\Facades\Queue::dispatch($job);
    }
}

if (!function_exists('dispatch_after')) {
    /**
     * Dispatch a job to the queue after a delay.
     */
    function dispatch_after(int $delaySeconds, object $job): string
    {
        /** @var \Lattice\Queue\JobInterface $job */
        return \Lattice\Queue\Facades\Queue::later($delaySeconds, $job);
    }
}

if (!function_exists('encrypt')) {
    /**
     * Encrypt a value.
     */
    function encrypt(mixed $value): string
    {
        return app(\Lattice\Auth\Encryption\Encrypter::class)->encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypt a value.
     */
    function decrypt(string $payload): mixed
    {
        return app(\Lattice\Auth\Encryption\Encrypter::class)->decrypt($payload);
    }
}

if (!function_exists('cache')) {
    /**
     * Get a cache value, or return the cache manager when called without arguments.
     */
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $manager = app(\Lattice\Cache\CacheManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->store()->get($key, $default);
    }
}

if (!function_exists('storage_disk')) {
    /**
     * Get a filesystem disk instance.
     */
    function storage_disk(?string $name = null): \Lattice\Filesystem\FilesystemInterface
    {
        return app(\Lattice\Filesystem\FilesystemManager::class)->disk($name ?? 'default');
    }
}

if (!function_exists('logger')) {
    /**
     * Get the logger instance.
     */
    function logger(): object
    {
        return app('logger');
    }
}
