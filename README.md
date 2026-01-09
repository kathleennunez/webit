# webIT.

Subscription-based webinar and virtual event management web app built with PHP, Bootstrap 5, and MySQL (XAMPP).

## Requirements
- PHP 8+
- MySQL (XAMPP)

## Run locally
1. Create a MySQL database (default: `webit`) in XAMPP.
2. Update database credentials in `config.php` if needed.
3. From the project root:
   ```bash
   php -S localhost:8000
   ```
4. Open `http://localhost:8000` in your browser.

## Entry points
- Landing page: `/index.php`
- Sign in: `/app/login.php`
- Sign up: `/app/signup.php`
- Admin center (admin only): `/app/admin.php`
- Browse: `/app/home.php`
- Dashboard: `/app/dashboard.php`
- Create webinar: `/app/create-webinar.php`
- Subscription: `/app/subscription.php`

## Data storage
All data is stored in MySQL (XAMPP) using normalized tables. If you previously used the legacy `data_store` table, migrate it with:
```bash
php scripts/migrate_data_store.php
```

## Uploads
Uploaded files are organized in:
- `uploads/avatars/` (profile images)
- `uploads/covers/` (webinar cover images)

## Premium webinars
- Only subscribed accounts can create premium webinars.
- Premium webinars can set a custom USD price.
- Registration requires payment (PayPal button is UI-only by default).

## Seed data (optional)
To generate sample users and webinars:
```bash
php scripts/seed_data.php
```

## Admin access
- Admin accounts are restricted to the admin center and do not see user/host features.
- Promote a user by setting `users.role = 'admin'` in MySQL.

## Notes
- Dark/light mode toggle is stored in `localStorage`.
- The notification dropdown shows the latest unread updates.
