# Rasa POS

Custom POS & Outlet Management System — Laravel 13 + MySQL.

## Requirements

- PHP 8.3+
- MySQL 8+
- Composer
- Node.js 20+

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set MySQL credentials in `.env`:

```
DB_CONNECTION=mysql
DB_DATABASE=xiway_pos
DB_USERNAME=root
DB_PASSWORD=your_password
```

```bash
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve
```

Open http://localhost:8000

## Demo accounts

Password for all users: `password`

| Email | Role |
| --- | --- |
| admin@example.com | Super Admin |
| manager@example.com | Outlet Manager |
| cashier@example.com | Cashier |
| kitchen@example.com | Kitchen Staff |
| captain@example.com | Captain |
| bar@example.com | Bar Staff |

## Tests

```bash
php artisan test
```
