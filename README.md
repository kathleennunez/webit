# webIT.

Subscription-based webinar and virtual event management web app built with PHP, Bootstrap 5, and JSON storage.

## Requirements
- PHP 8+

## Run locally
1. From the project root:
   ```bash
   php -S localhost:8000
   ```
2. Open `http://localhost:8000` in your browser.

## Entry points
- Landing page: `/index.php`
- Sign in: `/login.php`
- Sign up: `/signup.php`
- Browse: `/home.php`
- Dashboard: `/dashboard.php`
- Create webinar: `/create-webinar.php`
- Subscription: `/subscription.php`

## Data storage
All data is stored in `data/` as JSON files:
- `users.json`
- `webinars.json`
- `registrations.json`
- `payments.json`
- `subscriptions.json`
- `notifications.json`
- `timezones.json`
- `canceled.json`

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

## Notes
- Dark/light mode toggle is stored in `localStorage`.
- The notification dropdown shows the latest unread updates.
