# SIBAKA Deployment Configuration

Configuration files for single VPS deployment.

## Files

| File | Purpose | Install Location |
|------|---------|-----------------|
| `sibaka.conf` | Nginx site config (SSL, gzip, PHP-FPM) | `/etc/nginx/sites-available/sibaka.conf` |
| `supervisor.conf` | Queue workers & scheduler | `/etc/supervisor/conf.d/sibaka.conf` |
| `Envoy.blade.php` | Zero-downtime deployment script | Project root (run with `envoy`) |
| `backup.sh` | Daily database & storage backup | `/opt/scripts/sibaka-backup.sh` |

## Server Setup

### Prerequisites

- Ubuntu 22.04+ VPS
- PHP 8.3+ with FPM
- PostgreSQL 16
- Redis 7+
- Nginx
- Supervisor
- Node.js 20+ (build time only)
- Certbot (Let's Encrypt)

### Directory Structure

```
/var/www/sibaka/
├── current -> releases/20240101120000   # Symlink to active release
├── releases/                            # Timestamped release directories
│   ├── 20240101120000/
│   └── 20240102150000/
├── storage/                             # Shared persistent storage
│   ├── app/public/                      # User uploads
│   ├── framework/
│   └── logs/
└── .env                                 # Shared environment config
```

### Installation

1. **Nginx**: `sudo ln -s /etc/nginx/sites-available/sibaka.conf /etc/nginx/sites-enabled/`
2. **SSL**: `sudo certbot --nginx -d sibaka.example.com`
3. **Supervisor**: Copy `supervisor.conf` to `/etc/supervisor/conf.d/sibaka.conf`, then `sudo supervisorctl reread && sudo supervisorctl update`
4. **Backup cron**: `sudo crontab -e` → `0 2 * * * /opt/scripts/sibaka-backup.sh >> /var/log/sibaka-backup.log 2>&1`

### Deployment

```bash
# Install Envoy globally
composer global require laravel/envoy

# Deploy to production
envoy run deploy

# Rollback to previous release
envoy run rollback
```

## Scheduled Tasks

Configured in `routes/console.php`, executed by Supervisor's `sibaka-scheduler`:

| Task | Schedule | Description |
|------|----------|-------------|
| PurgeAnonymousMetadata | Daily 3:00 AM | Delete metadata older than 90 days |
| AutoLockThreads | Daily 4:00 AM | Lock threads inactive for 90+ days |
| prune-audit-logs | Daily 5:00 AM | Remove audit logs older than 365 days |
| moderation:refresh-stats | Every minute | Refresh dashboard stats cache |
| session:gc | Daily 6:00 AM | Clean expired sessions |
| queue:prune-failed | Daily 6:30 AM | Remove failed jobs older than 7 days |
