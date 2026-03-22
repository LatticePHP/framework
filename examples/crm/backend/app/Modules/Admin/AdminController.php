<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

/**
 * Serves the unified admin portal page.
 *
 * Provides a self-contained HTML dashboard at /admin that links to all
 * LatticePHP monitoring dashboards (Chronos, Loom, Nightwatch) and
 * displays basic system information.
 */
#[Controller('/admin')]
final class AdminController
{
    private const string FRAMEWORK_VERSION = '1.0.0';

    /**
     * Render the admin portal HTML page.
     */
    #[Get('/')]
    public function index(): Response
    {
        $phpVersion = PHP_VERSION;
        $environment = $_ENV['APP_ENV'] ?? 'production';
        $frameworkVersion = self::FRAMEWORK_VERSION;
        $serverTime = date('Y-m-d H:i:s T');

        $html = $this->renderPortal(
            phpVersion: $phpVersion,
            environment: $environment,
            frameworkVersion: $frameworkVersion,
            serverTime: $serverTime,
        );

        return new Response(
            statusCode: 200,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            body: $html,
        );
    }

    /**
     * Health check endpoint for monitoring.
     */
    #[Get('/health')]
    public function health(): Response
    {
        return ResponseFactory::json([
            'status' => 'healthy',
            'php_version' => PHP_VERSION,
            'framework' => 'LatticePHP',
            'version' => self::FRAMEWORK_VERSION,
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'timestamp' => date('c'),
        ]);
    }

    private function renderPortal(
        string $phpVersion,
        string $environment,
        string $frameworkVersion,
        string $serverTime,
    ): string {
        $envBadgeColor = match ($environment) {
            'local', 'development' => '#22c55e',
            'staging' => '#f59e0b',
            'testing' => '#3b82f6',
            default => '#ef4444',
        };

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>LatticePHP Admin Portal</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: #0f0f23;
                    color: #e2e8f0;
                    min-height: 100vh;
                    line-height: 1.6;
                }

                .header {
                    background: linear-gradient(135deg, #1a1a3e 0%, #0f0f23 100%);
                    border-bottom: 1px solid #2d2d5e;
                    padding: 2rem 2rem 1.5rem;
                }

                .header-inner {
                    max-width: 1200px;
                    margin: 0 auto;
                }

                .brand {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 0.5rem;
                }

                .brand h1 {
                    font-size: 1.75rem;
                    font-weight: 700;
                    background: linear-gradient(135deg, #818cf8, #a78bfa);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .brand .logo {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, #818cf8, #6366f1);
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 900;
                    font-size: 1.2rem;
                    color: #fff;
                }

                .subtitle {
                    color: #94a3b8;
                    font-size: 0.95rem;
                }

                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 2rem;
                }

                .section-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #94a3b8;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 1rem;
                }

                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                    gap: 1.25rem;
                    margin-bottom: 2.5rem;
                }

                .card {
                    background: #1a1a3e;
                    border: 1px solid #2d2d5e;
                    border-radius: 12px;
                    padding: 1.5rem;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    color: inherit;
                    display: block;
                    position: relative;
                    overflow: hidden;
                }

                .card:hover {
                    border-color: #818cf8;
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
                }

                .card-header {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 0.75rem;
                }

                .card-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.25rem;
                    flex-shrink: 0;
                }

                .card-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #f1f5f9;
                }

                .card-status {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: #22c55e;
                    margin-left: auto;
                    box-shadow: 0 0 6px #22c55e;
                }

                .card-description {
                    color: #94a3b8;
                    font-size: 0.9rem;
                    line-height: 1.5;
                }

                .card-path {
                    display: inline-block;
                    margin-top: 0.75rem;
                    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
                    font-size: 0.8rem;
                    color: #818cf8;
                    background: rgba(99, 102, 241, 0.1);
                    padding: 0.25rem 0.5rem;
                    border-radius: 4px;
                }

                .icon-chronos { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
                .icon-loom { background: rgba(34, 211, 238, 0.15); color: #22d3ee; }
                .icon-nightwatch { background: rgba(167, 139, 250, 0.15); color: #a78bfa; }
                .icon-health { background: rgba(34, 197, 94, 0.15); color: #22c55e; }

                .system-info {
                    background: #1a1a3e;
                    border: 1px solid #2d2d5e;
                    border-radius: 12px;
                    padding: 1.5rem;
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                    gap: 1rem;
                }

                .info-item {
                    display: flex;
                    flex-direction: column;
                    gap: 0.25rem;
                }

                .info-label {
                    font-size: 0.8rem;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .info-value {
                    font-size: 0.95rem;
                    color: #e2e8f0;
                    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
                }

                .env-badge {
                    display: inline-block;
                    padding: 0.15rem 0.5rem;
                    border-radius: 9999px;
                    font-size: 0.8rem;
                    font-weight: 600;
                    background: {$envBadgeColor}20;
                    color: {$envBadgeColor};
                    border: 1px solid {$envBadgeColor}40;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="header-inner">
                    <div class="brand">
                        <div class="logo">L</div>
                        <h1>LatticePHP Admin Portal</h1>
                    </div>
                    <p class="subtitle">Unified dashboard for monitoring, workflows, and queue management</p>
                </div>
            </div>

            <div class="container">
                <h2 class="section-title">Dashboards</h2>
                <div class="grid">
                    <a href="/chronos" class="card">
                        <div class="card-header">
                            <div class="card-icon icon-chronos">&#9203;</div>
                            <span class="card-title">Chronos</span>
                            <div class="card-status"></div>
                        </div>
                        <p class="card-description">
                            Workflow execution dashboard. Monitor running workflows, inspect event histories,
                            send signals, and retry failed executions.
                        </p>
                        <span class="card-path">/chronos</span>
                    </a>

                    <a href="/loom" class="card">
                        <div class="card-header">
                            <div class="card-icon icon-loom">&#9881;</div>
                            <span class="card-title">Loom</span>
                            <div class="card-status"></div>
                        </div>
                        <p class="card-description">
                            Queue monitoring dashboard. Track job throughput, inspect failed jobs,
                            monitor workers, and manage retry policies.
                        </p>
                        <span class="card-path">/loom</span>
                    </a>

                    <a href="/nightwatch" class="card">
                        <div class="card-header">
                            <div class="card-icon icon-nightwatch">&#9789;</div>
                            <span class="card-title">Nightwatch</span>
                            <div class="card-status"></div>
                        </div>
                        <p class="card-description">
                            Unified monitoring. Debug mode for development with request tracing,
                            production metrics with sampling and aggregation.
                        </p>
                        <span class="card-path">/nightwatch</span>
                    </a>

                    <a href="/api/health" class="card">
                        <div class="card-header">
                            <div class="card-icon icon-health">&#9829;</div>
                            <span class="card-title">Health Check</span>
                            <div class="card-status"></div>
                        </div>
                        <p class="card-description">
                            Application health endpoint. Returns system status, PHP version,
                            and environment information as JSON.
                        </p>
                        <span class="card-path">/api/health</span>
                    </a>
                </div>

                <h2 class="section-title">System Information</h2>
                <div class="system-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value">{$phpVersion}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Framework</span>
                            <span class="info-value">LatticePHP v{$frameworkVersion}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Environment</span>
                            <span class="info-value"><span class="env-badge">{$environment}</span></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Time</span>
                            <span class="info-value">{$serverTime}</span>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
