# Redis Configuration Guide for FoodHub API

## Environment Configuration

Copy these Redis configuration lines to your `.env` file for all environments (development, staging, production):

```env
# Redis Configuration (Primary for ALL environments)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_QUEUE_DB=3

# Cache Configuration (Redis by default)
CACHE_DRIVER=redis
CACHE_PREFIX=foodhub_cache_

# Session Configuration (Redis by default)
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_PREFIX=foodhub_session_

# Queue Configuration (Redis by default)
QUEUE_CONNECTION=redis
QUEUE_PREFIX=foodhub_queue_

# Queue Worker Configuration
QUEUE_WORKERS=2
QUEUE_WORKER_TIMEOUT=300
QUEUE_WORKER_MEMORY_LIMIT=512

# Cache Warming Configuration
CACHE_WARMING_ENABLED=true
CACHE_WARMING_INTERVAL=3600

# Performance Monitoring
PERFORMANCE_MONITORING_ENABLED=true
QUERY_LOG_ENABLED=false
SLOW_QUERY_THRESHOLD=1000
```

## Docker Setup

If using Docker, Redis is already configured in `docker-compose.yml`:

```yaml
redis:
  image: redis:7-alpine
  container_name: foodhub_redis
  restart: unless-stopped
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
  networks:
    - foodhub_network
```

## Manual Redis Installation

### Windows
1. Download Redis for Windows from: https://github.com/microsoftarchive/redis/releases
2. Install and start Redis service
3. Redis will be available on `127.0.0.1:6379`

### macOS
```bash
brew install redis
brew services start redis
```

### Ubuntu/Debian
```bash
sudo apt update
sudo apt install redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
```

## Queue Worker Commands

Start queue workers for processing POS sync jobs:

```bash
# Start workers for all queues
php artisan queue:work --queue=high,default,low --tries=5 --timeout=300

# Start workers in background
php artisan queue:work --queue=high,default,low --tries=5 --timeout=300 --daemon

# Monitor queue status
php artisan queue:monitor
```

## Cache Management Commands

```bash
# Clear all cache
php artisan cache:clear

# Clear specific cache tags
php artisan cache:forget restaurant:123:config

# Warm up cache for a restaurant
php artisan tinker
>>> app(App\Services\CachingService::class)->warmUpCache('restaurant-id');

# View cache statistics
php artisan tinker
>>> app(App\Services\CachingService::class)->getCacheStatistics();
```

## Queue Monitoring

Access queue monitoring endpoints (admin only):

```bash
# Get queue statistics
GET /api/queue/monitoring

# Get real-time data
GET /api/queue/monitoring/realtime

# Get POS sync status
GET /api/queue/monitoring/pos-sync-status

# Retry failed job
POST /api/queue/jobs/retry
{
    "job_id": "failed-job-id"
}

# Clear failed jobs
DELETE /api/queue/jobs/failed
```

## Performance Optimization

### Cache Keys Structure
- `menu:{restaurant_id}:items` - Menu items cache
- `restaurant:{restaurant_id}:config` - Restaurant configuration
- `loyalty:{restaurant_id}:rules` - Loyalty program rules
- `pos:connection:{restaurant_id}:{pos_type}` - POS connection status

### Cache TTL Settings
- Menu items: 30 minutes
- Restaurant config: 1 hour
- Loyalty rules: 2 hours
- POS connection: 5 minutes
- Analytics: 10 minutes

### Queue Priorities
- `high` - Order sync, status updates (immediate processing)
- `default` - Payments, inventory (within minutes)
- `low` - Menu sync (can be delayed)

## Troubleshooting

### Redis Connection Issues
```bash
# Test Redis connection
redis-cli ping

# Check Redis logs
tail -f /var/log/redis/redis-server.log

# Monitor Redis commands
redis-cli monitor
```

### Queue Issues
```bash
# Check failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry {job_id}

# Clear all failed jobs
php artisan queue:flush
```

### Cache Issues
```bash
# Check cache driver
php artisan config:show cache

# Test cache functionality
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

## Production Considerations

1. **Redis Persistence**: Enable RDB and AOF for data durability
2. **Memory Management**: Set appropriate `maxmemory` and eviction policies
3. **Security**: Use strong passwords and bind to localhost only
4. **Monitoring**: Implement Redis monitoring and alerting
5. **Backup**: Regular Redis data backups
6. **Scaling**: Consider Redis Cluster for high availability

## Health Checks

```bash
# Check Redis health
curl http://localhost:8000/up

# Check queue health
GET /api/queue/monitoring

# Check cache health
php artisan tinker
>>> Cache::store()->has('health_check');
```
