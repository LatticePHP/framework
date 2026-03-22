# Anvil -- Server Management & Deployment CLI for LatticePHP

## Overview

Anvil is LatticePHP's server management and deployment CLI -- the terminal-native equivalent of **Laravel Forge** but built as a LatticePHP package (`lattice/anvil`). Where Forge provides a web dashboard for provisioning servers and deploying applications, Anvil provides the same capabilities through a rich interactive terminal UI and scriptable CLI commands.

Anvil mirrors the feature set of [forge-cli](https://github.com/boparaiamrit/forge-cli): server provisioning (Nginx, PHP, Node.js, databases, caching), site management (per-framework Nginx templates, SSL, health checks), service lifecycle management (start/stop/restart across 11 service categories), monitoring (CPU, memory, disk, load), and maintenance (log management, cron jobs, disk cleanup). But Anvil goes further by integrating directly into the LatticePHP ecosystem: `php lattice deploy` for zero-downtime deployments, `php lattice provision` for full server setup, and hooks into Nightwatch for deploy event tracking.

### What Anvil Does

- **Provisions servers** with optimized installations of Nginx, PHP (8.0-8.5), Node.js, Redis, MySQL/MariaDB, PostgreSQL, Docker, and more
- **Manages sites** with per-framework Nginx templates (Laravel, Next.js, Nuxt.js, static), WebSocket proxies, and multiple upstream configurations
- **Handles SSL** via Let's Encrypt (Certbot) with automatic renewal and certificate monitoring
- **Controls services** across 11 categories (Web, PHP, Database, Cache, Queue, Mail, Monitoring, Security, SSL, System, Docker) with a unified dashboard
- **Monitors servers** with live CPU, memory, disk, and load metrics plus historical tracking
- **Deploys LatticePHP applications** with zero-downtime using symlink-based releases
- **Provides a rich CLI TUI** with arrow-key navigation, color-coded output, breadcrumbs, and interactive menus -- following the same design patterns as other Lattice CLIs

---

## Architecture

### CLI-First Design

Unlike Forge (web dashboard) or even forge-cli (Python script wrapping Forge's API), Anvil runs directly on the target server. There is no external API, no SaaS dependency, and no web interface. Anvil is a PHP CLI tool that:

1. Detects the current server environment (installed services, versions, configurations)
2. Presents an interactive TUI for managing everything
3. Executes system commands (apt, systemctl, nginx, certbot, etc.) directly
4. Tracks all changes with a lineage/audit trail

```
+-------------------+       +-------------------+       +-------------------+
|   Operator        |       |   Anvil CLI       |       |   Server          |
|   (terminal)      | <---> |   (PHP process)   | ----> |   (services)      |
|                   |       |                   |       |                   |
|   Interactive TUI |       |   System detect   |       |   Nginx           |
|   Arrow keys      |       |   Menu system     |       |   PHP-FPM         |
|   Breadcrumbs     |       |   Installers      |       |   MySQL / PgSQL   |
|                   |       |   Site managers   |       |   Redis           |
|                   |       |   Service ctrl    |       |   Node.js / PM2   |
|                   |       |   Monitoring      |       |   Certbot         |
|                   |       |   Audit trail     |       |   Docker          |
+-------------------+       +-------------------+       +-------------------+
```

### Package Structure

Anvil is installed as a Composer package (`lattice/anvil`) within a LatticePHP application. It registers CLI commands via `AnvilServiceProvider`:

```
packages/anvil/
  composer.json
  src/
    AnvilServiceProvider.php
    Commands/
      ProvisionCommand.php        # php lattice provision
      DeployCommand.php           # php lattice deploy
      AnvilCommand.php            # php lattice anvil (interactive TUI)
      ServerStatusCommand.php     # php lattice anvil:status
      SiteCreateCommand.php       # php lattice anvil:site:create
      ...
    Detection/
      SystemDetector.php          # Detect installed services and versions
      NginxDetector.php
      PhpDetector.php
      NodeDetector.php
      DatabaseDetector.php
      ...
    Installers/
      NginxInstaller.php
      PhpInstaller.php
      NodeInstaller.php
      RedisInstaller.php
      MysqlInstaller.php
      PostgresInstaller.php
      CertbotInstaller.php
      ComposerInstaller.php
      DockerInstaller.php
      Pm2Installer.php
      SupervisorInstaller.php
    Sites/
      SiteManager.php
      NginxTemplateEngine.php
      Templates/
        laravel.nginx.conf
        nextjs.nginx.conf
        nuxtjs.nginx.conf
        static.nginx.conf
        websocket-proxy.nginx.conf
    SSL/
      CertbotManager.php
      CertificateChecker.php
      RenewalScheduler.php
    Services/
      ServiceManager.php
      ServiceCategory.php
    Monitoring/
      ServerMonitor.php
      MetricsCollector.php
      AlertManager.php
    Deployment/
      Deployer.php
      ReleaseManager.php
      EnvironmentSync.php
    Security/
      SecurityAuditor.php
      CveScanner.php
    Logs/
      LogManager.php
      LogTailer.php
    TUI/
      MenuSystem.php
      Breadcrumbs.php
      ProgressBar.php
      Table.php
      ...
    Config/
      anvil.php
    State/
      StateManager.php            # Tracks lineage/audit trail
      AuditEntry.php
```

---

## Rich CLI TUI

Anvil's terminal UI follows the design patterns established by other Lattice CLI tools (Chronos, Nightwatch, Loom). It uses `symfony/console` as the foundation with custom rendering for:

- **Arrow-key menus**: Navigate options with up/down arrows, Enter to select
- **Breadcrumbs**: Show the navigation path (e.g., `Server > Sites > myapp.com > SSL`)
- **Color-coded output**: Green for success, red for errors, yellow for warnings, cyan for info
- **Progress bars**: For long-running operations (installations, deployments)
- **Live tables**: Real-time metrics with auto-refresh
- **Confirmation prompts**: For destructive operations (delete, restart, terminate)

### Interactive vs Non-Interactive

Every feature in Anvil is accessible through both modes:

| Interactive (TUI)                        | Non-Interactive (CLI)                            |
|------------------------------------------|--------------------------------------------------|
| `php lattice anvil` (menu-driven)        | `php lattice anvil:status` (print and exit)      |
| Navigate to Sites > Create              | `php lattice anvil:site:create --type=laravel`   |
| Navigate to Services > Restart PHP      | `php lattice anvil:service:restart php8.4-fpm`   |
| Navigate to SSL > Issue Certificate     | `php lattice anvil:ssl:issue myapp.com`          |
| Navigate to Monitoring > Dashboard      | `php lattice anvil:monitor --watch`              |

---

## Feature Categories

### 1. Server Provisioning

Install and configure server software with optimized defaults:

- **Nginx**: Latest stable, optimized `nginx.conf` (worker processes, connections, gzip, security headers)
- **PHP**: Multiple versions (8.0-8.5), extension bundles per use case (Laravel: mbstring, xml, curl, zip, bcmath, gd, intl; WordPress: mysql, gd, imagick, xml)
- **Node.js**: Via NVM for version management
- **Databases**: MySQL/MariaDB and PostgreSQL with secure defaults
- **Cache**: Redis with optimized memory settings
- **Tools**: Certbot, Composer, PM2, Supervisor
- **Docker**: Docker Engine + Docker Compose

### 2. Site Management

Create and manage web application sites:

- **Framework-specific templates**: Hardened Nginx configurations for Laravel (public root, PHP-FPM, asset caching), Next.js (reverse proxy to Node.js), Nuxt.js (reverse proxy), static sites
- **WebSocket proxy**: Nginx configuration for proxying WebSocket connections (for Ripple)
- **Multiple upstreams**: Route URL prefixes to different backend ports (e.g., `/api` to PHP-FPM, `/ws` to Ripple, `/` to Next.js)
- **HTTP Basic Auth**: Quick authentication provisioning for staging environments
- **Health checks**: DNS resolution, HTTP/HTTPS connectivity, SSL certificate validity

### 3. SSL & Security

- **Let's Encrypt**: Automated certificate issuance via Certbot (HTTP-01 and DNS-01 challenges)
- **Auto-renewal**: Systemd timer for automatic certificate renewal
- **Certificate monitoring**: Track expiry dates, warn when certificates are near expiry
- **Security auditing**: Scan Nginx config, PHP settings, and server configuration for common vulnerabilities
- **CVE scanning**: Check installed package versions against known vulnerabilities

### 4. Service Management

Unified control over 11 service categories:

| Category     | Services                                               |
|--------------|--------------------------------------------------------|
| Web          | Nginx, Apache, Caddy                                  |
| PHP          | PHP-FPM (all installed versions)                      |
| Database     | MySQL, MariaDB, PostgreSQL                            |
| Cache        | Redis, Memcached                                      |
| Queue        | Supervisor, PM2 (for queue workers)                   |
| Mail         | Postfix, Dovecot                                      |
| Monitoring   | Prometheus Node Exporter, Grafana                     |
| Security     | UFW, Fail2ban                                         |
| SSL          | Certbot                                               |
| System       | cron, systemd-resolved, ssh                           |
| Docker       | Docker Engine, Docker Compose                         |

### 5. Monitoring & Maintenance

- **Live dashboard**: CPU, memory, disk, load average, network I/O -- refreshed every second
- **Historical metrics**: Store snapshots for 1h, 6h, 24h, 7d views
- **Alerts**: Configurable thresholds (CPU > 90%, disk > 85%, memory > 90%)
- **Disk management**: Find large files, detect duplicates, manage swap, suggest cleanup
- **Log management**: Centralized viewing of Nginx, PHP-FPM, application, and system logs with search and error summarization
- **Cron management**: List, add, edit, remove cron jobs with validation

---

## LatticePHP Deployment Integration

### `php lattice deploy`

Zero-downtime deployment of a LatticePHP application:

1. **Pull latest code** (`git pull` or `git fetch` + `git checkout`)
2. **Install dependencies** (`composer install --no-dev --optimize-autoloader`)
3. **Run migrations** (`php lattice migrate`)
4. **Clear caches** (`php lattice cache:clear`, `php lattice config:clear`, `php lattice route:clear`)
5. **Build assets** (optional: `npm run build` if frontend assets exist)
6. **Symlink switch** (atomic: `ln -sfn releases/20260322143000 current`)
7. **Restart queue workers** (`php lattice queue:restart` or Supervisor reload)
8. **Health check** (hit the application URL, verify 200 response)
9. **Notify Nightwatch** (record deploy event for correlation with metrics)

### `php lattice provision`

Full interactive server setup wizard:

1. Detect current OS and installed packages
2. Present multi-select menu: which services to install?
3. Install selected services with optimized configurations
4. Create the first site with SSL
5. Set up queue workers and scheduler
6. Run security audit
7. Print summary of everything installed and configured

---

## Dependencies

| Package               | Purpose                                                |
|-----------------------|--------------------------------------------------------|
| `symfony/console`     | CLI command framework and terminal I/O                 |
| `symfony/process`     | Execute system commands (apt, systemctl, nginx, etc.)  |
| `lattice/module`      | `#[Module]` attribute for `AnvilModule` registration   |

### Optional Integration

| Package               | Purpose                                              |
|-----------------------|------------------------------------------------------|
| `lattice/nightwatch`  | Record deploy events for monitoring correlation      |
| `lattice/ripple`      | Broadcast deploy status to connected dashboards      |
| `lattice/database`          | Database migrations during deployment                |
| `lattice/cache`       | Cache clearing during deployment                     |

---

## Design Inspiration

### forge-cli (Python)
- Interactive TUI with arrow-key menus
- 11 service categories with unified start/stop/restart
- System detection and auto-configuration
- Security auditing and CVE scanning
- Reference: [github.com/boparaiamrit/forge-cli](https://github.com/boparaiamrit/forge-cli)

### Laravel Forge
- Server provisioning with optimized defaults
- Site management with framework-specific templates
- SSL via Let's Encrypt
- Queue worker and scheduler management

### Key Design Principles
- **Terminal-native**: No web dashboard, no SaaS dependency. Everything happens in the terminal.
- **Direct execution**: Anvil runs ON the server and executes commands directly. No SSH abstraction, no remote API.
- **Audit everything**: Every change Anvil makes is recorded in a lineage trail. You can always see what was done, when, and why.
- **Safe by default**: Destructive operations require confirmation. Installations use hardened defaults. Security audit runs automatically after provisioning.
- **Scriptable**: Every interactive feature has a non-interactive CLI counterpart for automation and CI/CD.
