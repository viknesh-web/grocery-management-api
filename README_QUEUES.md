# Queue System for WhatsApp

## Local Development

Start queue worker:
```bash
php artisan queue:work --queue=whatsapp,default --tries=3
```

Or use queue daemon:
```bash
php artisan queue:listen --queue=whatsapp,default
```

## Monitor Queues

Check status:
```bash
php artisan whatsapp:monitor
```

View failed jobs:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan whatsapp:retry-failed --limit=10
```

Retry all failed:
```bash
php artisan queue:retry all
```

## Production Setup

1. Use Supervisor to keep workers running
2. Use Redis for better performance
3. Monitor with Laravel Horizon (optional)

## API Changes

### Async Sending (Default)
```bash
curl -X POST /api/v1/whatsapp/send-message \
  -d "async=true" \
  -d "customer_ids=[1,2,3]"

Response:
{
  "success": true,
  "data": {
    "queued": true,
    "customer_count": 3,
    "message": "Messages queued for 3 customers"
  }
}
```

### Synchronous Sending (Old behavior)
```bash
curl -X POST /api/v1/whatsapp/send-message \
  -d "async=false" \
  -d "customer_ids=[1,2,3]"

Response:
{
  "success": true,
  "data": {
    "total": 3,
    "successful": 2,
    "failed": 1,
    "results": [...]
  }
}
```

## Setup Instructions

1. Update `.env`:
   ```
   QUEUE_CONNECTION=database
   ```

2. Run migrations:
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

3. Start worker (development):
   ```bash
   php artisan queue:work --queue=whatsapp,default --tries=3
   ```

4. For production, configure Supervisor (see supervisor config example in docs)

