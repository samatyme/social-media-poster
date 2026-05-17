# Social Media Poster — Internal Publishing Platform

A full-featured, SaaS-ready social media publishing platform built with Laravel + Vue 3/Inertia.js.

---

## Stack

| Layer       | Technology                  |
|-------------|-----------------------------|
| Backend     | Laravel 11                  |
| Frontend    | Vue 3 + Inertia.js          |
| Styling     | Tailwind CSS                |
| Database    | MySQL (SQLite for tests)    |
| Queue       | Redis + Laravel Queues      |
| Auth        | Laravel Sanctum             |
| Storage     | Local disk (S3-ready)       |
| Build tool  | Vite                        |

---

## Quick Start

### 1. Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ & npm
- MySQL 8.0+
- Redis (or use `QUEUE_CONNECTION=database` for MySQL-based queues on shared hosting)

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
DB_DATABASE=social_media_poster
DB_USERNAME=root
DB_PASSWORD=your_password

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

PUBLISHER_MODE=mock    # Use 'live' when real API keys are configured
```

### 4. Database setup

```bash
# Create the database first in MySQL
mysql -u root -p -e "CREATE DATABASE social_media_poster;"

# Run migrations
php artisan migrate

# Seed with demo data
php artisan db:seed
```

Demo credentials:
- **Admin:** admin@demo.com / password
- **Editor:** editor@demo.com / password
- **Approver:** approver@demo.com / password

### 5. Storage link

```bash
php artisan storage:link
```

### 6. Build frontend

```bash
# Development with hot reload
npm run dev

# Production build
npm run build
```

### 7. Start the application

```bash
# Start Laravel dev server
php artisan serve

# In another terminal, start queue worker
php artisan queue:work redis --queue=default --tries=3

# Start scheduler (runs every minute)
php artisan schedule:work
```

Visit: http://localhost:8000

---

## cPanel Deployment

### Prerequisites on cPanel
- PHP 8.2+ with extensions: pdo_mysql, redis, gd, fileinfo, mbstring, zip
- Node.js (via NVM or hosting panel)
- Redis (check with your host, or switch to `QUEUE_CONNECTION=database`)

### Steps

1. Upload project files to `public_html/social_media_poster/` (or a subdomain root)
2. Point your domain/subdomain document root to the `public/` folder
3. Create MySQL database via cPanel MySQL Databases
4. Set `.env` values via cPanel or file manager
5. Run via SSH:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm run build
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
6. Set up cPanel Cron Job:
   ```
   * * * * * cd /home/user/public_html/social_media_poster && php artisan schedule:run >> /dev/null 2>&1
   ```
7. For queue worker, use cPanel's "Background Processes" or a cron job:
   ```
   * * * * * cd /home/user/... && php artisan queue:work --max-time=55 --tries=3 >> storage/logs/worker.log 2>&1
   ```

### If Redis is unavailable on shared hosting

Change in `.env`:
```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Then run:
```bash
php artisan queue:table
php artisan migrate
```

---

## Architecture

### Publisher Adapter Pattern

All social publishing goes through `SocialPlatformPublisherInterface`:

```
app/Contracts/SocialPlatformPublisherInterface.php
app/Publishers/
  ├── BasePublisher.php         (shared validation logic)
  ├── MockPublisher.php         (local testing, random pass/fail)
  ├── FacebookPublisher.php     (TODO: Graph API)
  ├── InstagramPublisher.php    (TODO: Graph API)
  ├── XPublisher.php            (TODO: Twitter API v2)
  ├── LinkedInPublisher.php     (TODO: LinkedIn API)
  ├── TikTokPublisher.php       (TODO: TikTok API)
  └── PublisherFactory.php      (resolves by platform or mock mode)
```

Set `PUBLISHER_MODE=mock` to use MockPublisher for all platforms.
Set `PUBLISHER_MODE=live` to use real platform publishers.

### Queue Jobs

| Job                          | Purpose                               |
|------------------------------|---------------------------------------|
| `PublishScheduledPostsJob`   | Cron-triggered, finds due posts       |
| `PublishPostVariantJob`      | Publishes one platform variant        |
| `RefreshSocialTokenJob`      | Refreshes OAuth tokens before expiry  |
| `ProcessMediaJob`            | Generates thumbnails for videos       |

### Post Status Flow

```
draft → pending_approval → approved → scheduled → publishing → published
                                                              → partially_published
                                                              → failed
     → cancelled (at any point before publishing)
```

---

## API Reference

| Method | Endpoint                              | Description            |
|--------|---------------------------------------|------------------------|
| POST   | `/api/auth/register`                  | Create org + admin     |
| POST   | `/api/auth/login`                     | Get access token       |
| GET    | `/api/dashboard`                      | Dashboard stats        |
| GET    | `/api/posts`                          | List posts             |
| POST   | `/api/posts`                          | Create post            |
| GET    | `/api/posts/{id}`                     | Get post with logs     |
| PUT    | `/api/posts/{id}`                     | Update post            |
| DELETE | `/api/posts/{id}`                     | Delete post            |
| POST   | `/api/posts/{id}/schedule`            | Schedule post          |
| POST   | `/api/posts/{id}/publish-now`         | Publish immediately    |
| POST   | `/api/posts/{id}/cancel`              | Cancel scheduled       |
| POST   | `/api/posts/{id}/duplicate`           | Clone post             |
| POST   | `/api/posts/{id}/submit-for-approval` | Submit for review      |
| POST   | `/api/posts/{id}/approve`             | Approve post           |
| POST   | `/api/posts/{id}/reject`              | Reject with notes      |
| GET    | `/api/media`                          | List media             |
| POST   | `/api/media`                          | Upload file            |
| DELETE | `/api/media/{id}`                     | Delete file            |
| GET    | `/api/social-accounts`               | List connected accounts|
| POST   | `/api/social-accounts/connect`       | Connect account        |
| DELETE | `/api/social-accounts/{id}`          | Disconnect account     |
| GET    | `/api/calendar`                       | Calendar events        |
| GET    | `/api/team`                           | Team members           |
| POST   | `/api/team/invite`                    | Invite member          |
| GET    | `/api/settings`                       | App settings           |

---

## Adding a Real Social API

Example: enabling real Facebook publishing

1. Add credentials to `.env`:
   ```env
   PUBLISHER_MODE=live
   FACEBOOK_APP_ID=your_app_id
   FACEBOOK_APP_SECRET=your_secret
   ```

2. Implement `app/Publishers/FacebookPublisher.php`:
   ```php
   public function publishPost(PostVariant $variant): array
   {
       $response = Http::withToken($variant->socialAccount->access_token)
           ->post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
               'message' => $variant->getEffectiveContent(),
           ]);

       if ($response->successful()) {
           return $this->buildSuccessResponse($response['id'], '...');
       }
       return $this->buildErrorResponse($response['error']['message']);
   }
   ```

3. No other changes needed — PublisherFactory routes automatically.

---

## Phase Roadmap

| Phase | Status      | Features                              |
|-------|-------------|---------------------------------------|
| 1     | ✅ Complete  | DB, auth, posts, composer, media, mock publishing, scheduler |
| 2     | 🔄 Partial  | Calendar, approvals, logs, accounts   |
| 3     | 📋 Planned  | Real APIs, analytics, AI assistant, billing |
