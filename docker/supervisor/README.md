# SurpriseMoi Supervisor Configuration

This directory contains Supervisor configuration files for managing Laravel background processes.

## Files

- `surprisemoi-queue.conf` - Queue worker configuration (2 processes)
- `surprisemoi-scheduler.conf` - Laravel scheduler configuration

## Setup on VPS

1. Run the setup script:

```bash
cd /var/www/surprise_moi
sudo bash scripts/setup-supervisor.sh
```

2. Check status:

```bash
sudo supervisorctl status
```

## Manual Setup (if needed)

1. Copy config files:

```bash
sudo cp docker/supervisor/*.conf /etc/supervisor/conf.d/
```

2. Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

3. Start services:

```bash
sudo supervisorctl start surprisemoi-queue:*
sudo supervisorctl start surprisemoi-scheduler:*
```

## Common Commands

```bash
# Check status
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart surprisemoi-queue:*

# Restart scheduler
sudo supervisorctl restart surprisemoi-scheduler:*

# Stop all services
sudo supervisorctl stop surprisemoi-queue:* surprisemoi-scheduler:*

# View logs
tail -f /var/www/surprise_moi/storage/logs/queue-worker.log
tail -f /var/www/surprise_moi/storage/logs/scheduler.log
```

## Architecture

- **Queue Workers**: Run inside the `surprisemoi-app` Docker container via `docker exec`
- **Scheduler**: Runs Laravel's `schedule:work` command (replaces cron)
- **User**: Runs as `deploy` user on the host, executes as `www-data` inside container
- **Processes**: 2 queue worker processes for redundancy

## Notes

- Workers automatically restart if they crash
- Logs rotate at 10MB with 5 backups
- Queue workers have a max execution time of 3600 seconds (1 hour)
- Each job can run for up to 300 seconds (5 minutes)
