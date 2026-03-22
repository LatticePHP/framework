# 12 — Anvil (Server Management & Deployment CLI)

> Build a terminal-native server management and deployment CLI: interactive TUI with menus and breadcrumbs, system detection, package installers, site management, SSL, service control, zero-downtime deployment, monitoring, disk and log management

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/module/`
- Libraries: `symfony/console`, `symfony/process`
- Optional: `lattice/nightwatch`, `lattice/ripple`, `lattice/database`, `lattice/cache`

## Subtasks

### 1. [ ] Core CLI framework — interactive menus, breadcrumbs, rich terminal output
- Build interactive CLI framework using `symfony/console` with custom Rich-style renderer
- Implement menu system with arrow key navigation, space to select, enter to confirm
- Implement breadcrumb navigation (e.g., `Main > Sites > Create`)
- Implement color-coded status output (green=installed, red=missing, yellow=outdated)
- Implement progress spinners for long-running operations
- Implement confirmation prompts for destructive actions
- Implement configuration management (YAML/JSON config store)
- Implement state tracking with full audit trail (lineage): every change recorded with what, when, who
- Implement history viewer for audit trail
- Implement resume for interrupted operations
- Create `AnvilServiceProvider` and `AnvilModule` with `#[Module]` attribute

#### Detailed Steps
1. Create `TUI/MenuSystem.php` with arrow key input handling, option rendering, multi-select support
2. Create `TUI/Breadcrumbs.php` tracking navigation path, rendering `>` separated trail
3. Create `TUI/ProgressBar.php` and `TUI/Spinner.php` for long operations
4. Create `TUI/Table.php` for formatted table output with column alignment and color
5. Create `Config/AnvilConfig.php` for YAML/JSON config loading and saving
6. Create `State/StateManager.php` with `record(string $action, array $details): void` and `getHistory(): array`
7. Create `State/AuditEntry.php` value object: timestamp, action, details, user
8. Register all commands via `AnvilServiceProvider` and `AnvilModule`

#### Verification
- [ ] Arrow key menu renders options, highlights current, returns selection
- [ ] Breadcrumbs display navigation path and update on menu transitions
- [ ] Color-coded output shows green/red/yellow status correctly
- [ ] State manager records actions and history viewer displays them

### 2. [ ] System detection — Nginx, PHP, Node.js, Redis, MySQL, PostgreSQL, Docker
- Implement `SystemDetector` coordinator that runs all detectors and builds unified status
- Implement `NginxDetector` — detect version, running status, config path (`nginx -v`, `systemctl status nginx`)
- Implement `PhpDetector` — detect installed versions, active extensions, FPM pool status
- Implement `NodeDetector` — detect NVM installation, active Node.js version
- Implement `RedisDetector` — detect version, memory usage (`redis-cli info`)
- Implement `MysqlDetector` — detect MySQL/MariaDB version and running status
- Implement `PostgresDetector` — detect version and running status
- Implement `CertbotDetector` — detect installation and existing certificates
- Implement `ComposerDetector` — detect version
- Implement `DockerDetector` — detect version and running containers
- Implement unified status dashboard: formatted table with all detected services, version, status

#### Detailed Steps
1. Create `Detection/DetectorInterface.php` with `detect(): DetectionResult` method
2. Create `Detection/DetectionResult.php` value object: name, installed (bool), version, status (running/stopped/unknown), details (array)
3. Implement each detector using `symfony/process` to run system commands and parse output
4. Create `SystemDetector.php` that aggregates all detector results into a summary table
5. Handle missing commands gracefully (service not installed = `installed: false`)
6. Tests for all detectors using mocked command outputs

#### Verification
- [ ] Each detector correctly identifies installed vs. not-installed services
- [ ] Version parsing works for all supported services
- [ ] Unified dashboard renders formatted table with color-coded statuses
- [ ] Missing services show "Not installed" without errors

### 3. [ ] Package installers — Nginx, PHP (8.0-8.5), Node.js, Redis, MySQL, PostgreSQL, Docker
- Implement `NginxInstaller` with optimized production config (worker processes, connections, gzip, security headers)
- Implement `PhpInstaller` supporting versions 8.0, 8.1, 8.2, 8.3, 8.4, 8.5
- Implement PHP extension bundles: Laravel bundle (mbstring, xml, curl, zip, bcmath, gd, intl), WordPress bundle, custom selection from 40+ extensions with descriptions
- Implement smart FPM pool configuration: auto-tune `pm.max_children`, `pm.start_servers` based on available RAM/swap
- Implement `NodeInstaller` via NVM (LTS or specific version)
- Implement `RedisInstaller` with memory optimization
- Implement `MysqlInstaller` and `MariadbInstaller` with secure defaults
- Implement `PostgresInstaller`
- Implement `CertbotInstaller`, `ComposerInstaller`, `Pm2Installer`, `SupervisorInstaller`
- Implement `DockerInstaller` (Docker Engine + Docker Compose)
- Implement `MemcachedInstaller`
- Implement multi-select installation menu: user selects which services to install, then installs sequentially with progress

#### Detailed Steps
1. Create `Installers/InstallerInterface.php` with `install(): void`, `isInstalled(): bool`, `getVersion(): ?string`
2. Each installer: run `apt update`, `apt install -y <packages>`, configure optimized defaults, start and enable service
3. PHP installer: add `ondrej/php` PPA, install specified version, install selected extensions, configure FPM pool
4. FPM auto-tune: calculate `pm.max_children = (total_ram - reserved_ram) / avg_process_memory`, set start/min/max servers proportionally
5. Multi-select menu: present all installers with checkboxes, install selected in dependency order, show progress per service
6. Record each installation in audit trail
7. Tests: verify correct apt/systemctl commands are generated for each installer

#### Verification
- [ ] Each installer runs correct system commands for its package
- [ ] PHP installer supports all versions 8.0-8.5 with extension bundles
- [ ] FPM auto-tune calculates reasonable pool sizes based on system RAM
- [ ] Multi-select menu allows choosing multiple services and installs them sequentially

### 4. [ ] Site management — Laravel, Next.js, Nuxt.js, static templates + health checks
- Implement site creation wizard with framework selection (Laravel, Next.js, Nuxt.js, static)
- Implement `NginxTemplateEngine` with hardened configs per framework:
  - Laravel: PHP-FPM upstream, `/public` document root, `.env` protection, asset caching
  - Next.js: reverse proxy to Node.js port via PM2, WebSocket support
  - Nuxt.js: reverse proxy to Node.js port via PM2
  - Static HTML: direct file serving with caching headers
- Implement WebSocket proxy configuration (Upgrade headers, keepalive for Ripple)
- Implement multiple upstream proxies: route URL prefixes to different backend ports (e.g., `/api` to PHP-FPM, `/ws` to Ripple, `/` to Next.js)
- Implement HTTP Basic Auth provisioning (htpasswd) for staging environments
- Implement site enable/disable (`sites-available` / `sites-enabled` symlink pattern)
- Implement site deletion with full cleanup (config, logs, certificates)
- Implement health checks: DNS resolution, HTTP connectivity, HTTPS connectivity, SSL certificate validation
- Implement live log viewing with color-coded output (errors red, warnings yellow)
- Implement site list with status overview
- Tests for Nginx config generation and health check logic

#### Detailed Steps
1. Create `Sites/SiteManager.php` with `create()`, `enable()`, `disable()`, `delete()`, `list()`, `healthCheck()` methods
2. Create `Sites/NginxTemplateEngine.php` that renders framework-specific templates with variable substitution (domain, port, root path, upstream)
3. Create template files: `laravel.nginx.conf`, `nextjs.nginx.conf`, `nuxtjs.nginx.conf`, `static.nginx.conf`, `websocket-proxy.nginx.conf`
4. Site creation wizard: prompt for domain, framework, PHP version (if Laravel), Node port (if Next/Nuxt), document root, enable SSL?
5. Write rendered config to `/etc/nginx/sites-available/{domain}`, symlink to `sites-enabled`, run `nginx -t && systemctl reload nginx`
6. Health check: `dig +short {domain}` for DNS, `curl -s -o /dev/null -w "%{http_code}" http://{domain}` for HTTP, verify SSL cert dates
7. Record all site operations in audit trail

#### Verification
- [ ] Site wizard creates correct Nginx config for each framework type
- [ ] Laravel template includes PHP-FPM upstream and public root
- [ ] Next.js template includes reverse proxy and WebSocket support
- [ ] Health checks verify DNS, HTTP, HTTPS, and SSL certificate validity
- [ ] Site enable/disable toggles the symlink correctly

### 5. [ ] SSL + security — Let's Encrypt, auto-renewal, auditor, CVE scanner
- Implement Let's Encrypt via Certbot (HTTP-01 verification)
- Implement DNS verification for wildcard certificates
- Implement auto-renewal setup (systemd timer or cron)
- Implement renewal tracking table: last renewal, next renewal, certificate status (valid/expiring/expired)
- Implement color-coded expiry warnings: green >30d, yellow <30d, red <7d
- Implement certificate revocation
- Implement configuration auditor: scan Nginx config, PHP settings, MySQL config for security issues
- Implement CVE scanner integration: check installed package versions against known vulnerabilities
- Implement ClamAV integration for malware scanning
- Implement firewall rule management (UFW)
- Implement SSH hardening recommendations
- Implement security report generation

#### Detailed Steps
1. Create `SSL/CertbotManager.php` with `issue(string $domain)`, `renew(string $domain)`, `revoke(string $domain)`, `status(): array`
2. Run `certbot certonly --nginx -d {domain}` for HTTP verification, `certbot certonly --dns-{provider} -d "*.{domain}"` for DNS
3. Create `SSL/RenewalScheduler.php` that installs systemd timer: `certbot renew --deploy-hook "systemctl reload nginx"`
4. Create `SSL/CertificateChecker.php` that reads cert files and parses expiry dates
5. Create `Security/SecurityAuditor.php` scanning: Nginx (server_tokens off, X-Frame-Options, Content-Security-Policy), PHP (expose_php, display_errors, allow_url_fopen), MySQL (root password, remote access)
6. Create `Security/CveScanner.php` using `apt list --upgradable` and matching against CVE databases
7. Generate security report with findings, severity levels, and remediation steps

#### Verification
- [ ] Certbot issues a valid Let's Encrypt certificate for a domain
- [ ] Auto-renewal timer is installed and fires correctly
- [ ] Certificate status shows color-coded expiry warnings
- [ ] Security auditor identifies common misconfigurations
- [ ] CVE scanner reports known vulnerabilities in installed packages

### 6. [ ] Service management — 11 categories, dashboard, quick actions
- Implement service dashboard covering 11 categories: Web (Nginx, Apache, Caddy), PHP (PHP-FPM all versions), Database (MySQL, MariaDB, PostgreSQL), Cache (Redis, Memcached), Queue (Supervisor, PM2), Mail (Postfix, Dovecot), Monitoring (Prometheus Node Exporter, Grafana), Security (UFW, Fail2ban), SSL (Certbot), System (cron, systemd-resolved, ssh), Docker (Docker Engine, Docker Compose)
- Auto-detect which services are installed and their status
- Implement start/stop/restart/reload per service via `systemctl`
- Implement enable/disable on boot per service
- Implement quick actions: restart all PHP-FPM pools, restart all web servers, reload all configs
- Display memory usage per service (from `systemctl status` or `/proc`)
- Display uptime per service
- Implement service health monitoring: check if service is responding (not just running)

#### Detailed Steps
1. Create `Services/ServiceManager.php` with `start()`, `stop()`, `restart()`, `reload()`, `enable()`, `disable()`, `status()`, `memoryUsage()`, `uptime()` per service
2. Create `Services/ServiceCategory.php` enum with all 11 categories and their member services
3. Dashboard view: table with category, service name, status (running/stopped), memory, uptime, enabled (yes/no)
4. Quick action menu: list predefined bulk actions, execute with confirmation
5. Health monitoring: beyond `systemctl status`, check actual responsiveness (e.g., `redis-cli ping`, `mysqladmin ping`, HTTP request to Nginx)
6. Tests for service detection and action command generation

#### Verification
- [ ] Dashboard lists all installed services with correct status
- [ ] Start/stop/restart/reload commands execute correctly per service
- [ ] Quick actions restart groups of services (e.g., all PHP-FPM pools)
- [ ] Memory usage and uptime display for each running service
- [ ] Health monitoring distinguishes between "running" and "responding"

### 7. [ ] LatticePHP deployment — `php lattice deploy`, zero-downtime, rollback
- Implement `php lattice deploy` command with full deployment pipeline:
  1. Git pull with branch selection
  2. `composer install --no-dev --optimize-autoloader`
  3. `php lattice migrate`
  4. Config and route cache clear/rebuild
  5. Build assets (optional: `npm run build`)
  6. Symlink switch: atomic `ln -sfn releases/{timestamp} current` for zero-downtime
  7. Queue worker restart
  8. Health check (HTTP request to app URL, verify 200)
  9. Notify Nightwatch (deploy event for metric correlation)
- Implement rollback to previous release: switch symlink to previous release directory
- Implement environment management: `.env` sync, secret rotation
- Implement `php lattice provision` — full interactive server setup wizard:
  1. Detect OS and installed packages
  2. Multi-select menu: which services to install
  3. Install selected services with optimized configs
  4. Create first site with SSL
  5. Set up queue workers and scheduler
  6. Run security audit
  7. Print summary
- Implement queue worker management: start/stop/restart, process count configuration
- Implement scheduler cron registration
- Implement maintenance mode toggle
- Integration with Nightwatch: deploy events, post-deploy health check

#### Detailed Steps
1. Create `Deployment/Deployer.php` with `deploy(array $options): DeployResult` method
2. Create `Deployment/ReleaseManager.php` managing `releases/` directory with timestamped releases and `current` symlink
3. Zero-downtime: create new release directory, prepare everything, then atomically switch symlink
4. Rollback: `ReleaseManager::rollback()` switches symlink to previous release, restart workers
5. Create `Deployment/EnvironmentSync.php` for `.env` file management and secret rotation
6. Create `Commands/DeployCommand.php` and `Commands/ProvisionCommand.php`
7. Tests for deployment pipeline (mock git, composer, migration) and zero-downtime symlink strategy

#### Verification
- [ ] `php lattice deploy` executes all deployment steps in correct order
- [ ] Zero-downtime: old release serves requests until symlink switches atomically
- [ ] Rollback restores previous release and restarts workers
- [ ] `php lattice provision` interactively installs selected services and creates a site
- [ ] Health check after deploy verifies the application responds with 200

### 8. [ ] Monitoring + disk + log management + tests

#### Monitoring & Alerts
- Implement live dashboard: CPU, memory, disk, load average, network I/O (refreshed every second)
- Implement historical data views: 1h, 6h, 24h, 7d
- Implement metric collection cron (every 5 minutes)
- Implement alert system with configurable thresholds (CPU > 90%, disk > 85%, memory > 90%)
- Implement warning and critical levels per metric
- Implement alert history viewer and acknowledgement

#### Disk Management
- Implement disk overview: filesystem usage, inodes, color-coded warnings
- Implement directory analysis: identify space hogs in /var, /home, /tmp
- Implement quick cleanup: APT cache, old kernels, temp files, old logs
- Implement deep cleanup: pip, npm, composer caches, journal logs
- Implement Docker cleanup: unused images, containers, volumes
- Implement log rotation status and manual rotation trigger
- Implement large file finder (100MB, 500MB, 1GB thresholds)
- Implement old file finder (not accessed in 30-365 days)
- Implement duplicate file finder
- Implement swap management: create, configure, remove
- Implement swappiness tuning for production
- Implement automatic cleanup cron jobs

#### Log Management
- Implement centralized log viewer: Nginx access/error, site-specific, PHP-FPM, system logs
- Implement real-time log tail with syntax highlighting
- Implement search and filter: by IP, status code, URL, keyword
- Implement error summary: top errors, top IPs, recent issues
- Implement log rotation management

#### Tests
- Tests for all detectors (mocked command outputs)
- Tests for all installers (verify correct system commands)
- Tests for site creation (Nginx config generation)
- Tests for health check logic
- Tests for deployment pipeline (mock git, composer, migration)
- Tests for zero-downtime symlink strategy
- Tests for service detection and action commands
- Tests for monitoring metric collection and alerting

#### Verification
- [ ] Live dashboard displays real-time CPU, memory, disk, and load metrics
- [ ] Alerts fire when thresholds are exceeded
- [ ] Disk management identifies large files and performs cleanup
- [ ] Log viewer displays and filters logs with syntax highlighting
- [ ] All test suites pass for detectors, installers, site management, and deployment

## Integration Verification
- [ ] Fresh server: `php lattice provision` detects OS, installs Nginx + PHP + Redis, creates a Laravel site with SSL
- [ ] Site health check verifies DNS, HTTP, HTTPS, and SSL validity
- [ ] `php lattice deploy` pulls code, runs migrations, switches symlink with zero downtime
- [ ] Rollback restores previous release successfully
- [ ] Service dashboard shows all installed services with correct statuses
- [ ] Security auditor identifies and reports common misconfigurations
- [ ] Monitoring alerts fire when CPU/memory/disk thresholds are exceeded
