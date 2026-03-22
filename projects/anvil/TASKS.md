# Anvil — Tasks

## Phase 1 — Core Framework

- [ ] Interactive CLI framework using symfony/console + custom Rich-style renderer
- [ ] Menu system with arrow key navigation, space to select, enter to confirm
- [ ] Breadcrumb navigation (Main > Sites > Create)
- [ ] Color-coded status output (green=installed, red=missing, yellow=outdated)
- [ ] Progress spinners for long operations
- [ ] Confirmation prompts for destructive actions
- [ ] Configuration management (YAML/JSON config store)
- [ ] State tracking with full audit trail (lineage)
- [ ] History viewer (what changed, when, by whom)
- [ ] Resume interrupted operations
- [ ] AnvilServiceProvider and AnvilModule

## Phase 2 — System Detection

- [ ] Nginx detector (version, status, config path)
- [ ] PHP detector (versions, extensions, FPM status)
- [ ] Node.js detector (NVM, active version)
- [ ] Redis detector (version, memory usage)
- [ ] MySQL/MariaDB detector (version, status)
- [ ] PostgreSQL detector (version, status)
- [ ] Certbot detector (installed, certificates)
- [ ] Composer detector (version)
- [ ] Docker detector (version, running containers)
- [ ] Unified status dashboard (table with all detected services)
- [ ] Tests for all detectors (mock system command outputs)

## Phase 3 — Package Installers

- [ ] Nginx installer with optimized production config
- [ ] PHP installer (8.0, 8.1, 8.2, 8.3, 8.4, 8.5)
- [ ] PHP extension bundles (Laravel bundle, WordPress bundle, custom)
- [ ] 40+ individual PHP extensions with descriptions
- [ ] Smart FPM pool configuration (auto-tune based on RAM/swap)
- [ ] Node.js installer via NVM (LTS or specific version)
- [ ] Redis installer with memory optimization
- [ ] MySQL installer with secure defaults
- [ ] MariaDB installer
- [ ] PostgreSQL installer
- [ ] Certbot installer
- [ ] Composer installer
- [ ] PM2 installer
- [ ] Supervisor installer
- [ ] Docker and Docker Compose installer
- [ ] Memcached installer
- [ ] Multi-select installation menu
- [ ] Tests for installer commands (verify correct apt/systemctl calls)

## Phase 4 — Site Management

- [ ] Site creation wizard with framework selection
- [ ] Laravel site template (PHP-FPM, document root, .env)
- [ ] Next.js site template (reverse proxy to Node port, PM2)
- [ ] Nuxt.js site template (reverse proxy, PM2)
- [ ] Static HTML site template (direct file serving)
- [ ] Hardened Nginx config templates per framework
- [ ] WebSocket proxy configuration (Upgrade headers, keepalive)
- [ ] Multiple upstream proxies (route URL prefixes to different ports)
- [ ] HTTP Basic Auth provisioning (htpasswd)
- [ ] Site enable/disable
- [ ] Site deletion with cleanup
- [ ] Health checks (DNS resolution, HTTP, HTTPS, SSL validation)
- [ ] Live log viewing with color-coded output
- [ ] Site list with status overview
- [ ] Tests for site creation (verify Nginx config generation)
- [ ] Tests for health check logic

## Phase 5 — SSL Certificates

- [ ] Let's Encrypt via Certbot (HTTP verification)
- [ ] DNS verification for wildcard certificates
- [ ] Auto-renewal setup (systemd timer or cron)
- [ ] Renewal tracking table (last renewal, next renewal)
- [ ] Certificate status display (valid, expiring, expired)
- [ ] Color-coded expiry warnings (green >30d, yellow <30d, red <7d)
- [ ] Certificate revocation

## Phase 6 — Security

- [ ] Configuration auditor (scan Nginx, PHP, MySQL for security issues)
- [ ] CVE scanner integration
- [ ] ClamAV integration for malware scanning
- [ ] Firewall rule management (UFW)
- [ ] SSH hardening recommendations
- [ ] Security report generation

## Phase 7 — Service Management

- [ ] Service dashboard (11 categories: Web, PHP, Database, Cache, Queue, Mail, Monitoring, Security, SSL, System, Docker)
- [ ] Auto-detect installed services
- [ ] Start/stop/restart/reload per service
- [ ] Enable/disable on boot
- [ ] Quick actions (restart all PHP-FPM, restart web servers, reload all)
- [ ] Memory usage per service
- [ ] Uptime display
- [ ] Service health monitoring
- [ ] Tests for service detection and action commands

## Phase 8 — Monitoring and Alerts

- [ ] Live dashboard (CPU, memory, disk, load average)
- [ ] Historical data views (1h, 6h, 24h, 7d)
- [ ] Metric collection cron (every 5 minutes)
- [ ] Alert system with configurable thresholds
- [ ] Warning and critical levels per metric
- [ ] Alert history viewer and acknowledgement
- [ ] Service health monitoring (Nginx, PHP-FPM, MySQL, Redis)

## Phase 9 — Disk Management

- [ ] Disk overview (filesystem usage, inodes, warnings)
- [ ] Directory analysis (space hogs in /var, /home, /tmp)
- [ ] Quick cleanup (APT cache, old kernels, temp files, old logs)
- [ ] Deep cleanup (pip, npm, composer caches, journals)
- [ ] Docker cleanup (unused images, containers, volumes)
- [ ] Log rotation status and manual rotation
- [ ] Large file finder (100MB, 500MB, 1GB thresholds)
- [ ] Old file finder (not accessed in 30-365 days)
- [ ] Duplicate file finder
- [ ] Swap management (create, configure, remove)
- [ ] Swappiness tuning for production
- [ ] Automatic cleanup cron jobs

## Phase 10 — Log Management

- [ ] Centralized log viewer (Nginx, site-specific, system logs)
- [ ] Real-time log tail with syntax highlighting
- [ ] Search and filter (by IP, status code, URL, keyword)
- [ ] Error summary (top errors, top IPs, recent issues)
- [ ] Log rotation management

## Phase 11 — LatticePHP Deployment Integration

- [ ] `php lattice deploy` — full deployment command
- [ ] Git pull with branch selection
- [ ] Composer install (--no-dev --optimize-autoloader)
- [ ] Database migration (php lattice migrate)
- [ ] Config and route cache clear/rebuild
- [ ] Queue worker restart
- [ ] Zero-downtime deployment (symlink strategy)
- [ ] Rollback to previous release
- [ ] Environment management (.env sync, secret rotation)
- [ ] `php lattice provision` — full server setup wizard
- [ ] Queue worker management (start/stop/restart, process count)
- [ ] Scheduler cron registration
- [ ] Integration with Nightwatch (deploy events, post-deploy health check)
- [ ] Maintenance mode toggle
- [ ] Tests for deployment pipeline (mock git, composer, migration commands)
- [ ] Tests for zero-downtime symlink strategy

## Phase 12 — Polish

- [ ] Self-update system
- [ ] Bash completion for all commands
- [ ] Comprehensive documentation
- [ ] Tests for all modules
- [ ] Error handling and recovery for all operations
