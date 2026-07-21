# Running ZimboSocials on the VPS

This stack is built to sit **alongside** the Slyker Tech stack on the same host.
It binds only to `127.0.0.1`, and the existing nginx container (which owns
80/443 and the LetsEncrypt certs) proxies the domain to it.

Why bother moving off cPanel: **real queue workers and a real scheduler**. The
WhatsApp webhook currently does AI calls inline because cPanel's cron only runs
once a minute — that's the cause of the 8–13 second replies, the media
timeouts, and the Gemini timeout tuning. Here, `queue:work` handles it.

## 1. Migrate the data

On the **old** (cPanel) server:

```bash
cd ~/my-app
PHP_BIN=/opt/alt/php83/usr/bin/php ./scripts/migrate-backup.sh
```

Copy the archive across (it contains `.env` — use scp, never a public URL):

```bash
scp storage/app/migration/zimbosocials-migration-*.tar.gz root@vps:/opt/zimbosocials/
```

## 2. Start the stack

On the **VPS**:

```bash
cd /opt/zimbosocials
git clone https://github.com/morebnyemba/zimbosocials.git .

# Set at minimum DB_PASSWORD and DB_ROOT_PASSWORD; APP_PORT defaults to 8081.
cp .env.example .env && nano .env

docker compose up -d --build
DOCKER=1 ./scripts/migrate-restore.sh zimbosocials-migration-*.tar.gz
```

`.env` must point the app at the compose database:

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=zimbosocials
DB_USERNAME=zimbosocials
DB_PASSWORD=<same as compose>
QUEUE_CONNECTION=database
```

## 3. Proxy the domain from the Slyker Tech nginx

Add to `slykertech/nginx.conf` (the container already has
`host.docker.internal:host-gateway` configured for Mailcow, so reuse it):

```nginx
server {
    listen 443 ssl http2;
    server_name zimbosocials.co.zw www.zimbosocials.co.zw;

    ssl_certificate     /etc/letsencrypt/live/zimbosocials.co.zw/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/zimbosocials.co.zw/privkey.pem;

    client_max_body_size 20M;   # payment proof uploads

    location / {
        proxy_pass http://host.docker.internal:8081;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # The WhatsApp webhook can be slow while it calls Gemini.
        proxy_read_timeout 60s;
    }
}
```

Then `docker compose restart nginx` in the Slyker Tech stack.

## 4. Point Meta at the new URL

**The bot stays silent until this is done.** In Meta → WhatsApp → Configuration,
set the callback URL to `https://zimbosocials.co.zw/webhooks/whatsapp` and
re-subscribe to `messages`.

## Everyday commands

```bash
docker compose logs -f app        # application log
docker compose logs -f queue      # queue worker
docker compose ps                 # health
docker compose exec app php artisan schedule:list
docker compose up -d --build      # deploy after a git pull
```

## What changes versus cPanel

| | cPanel | here |
|---|---|---|
| Queue | cron once a minute | real `queue:work` |
| Scheduler | crontab + full php path | `schedule:work` container |
| Webhook | processed inline (8–13s replies) | can be queued |
| ffmpeg | unavailable | installable (Opus voice notes) |
| PUT/PATCH/DELETE | blocked by mod_security | work normally |
