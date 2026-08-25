# ElectoFlow Operations Runbook

## Purpose

This runbook explains how to install, start, verify, back up, and troubleshoot the ElectoFlow application.

## Requirements

- XAMPP with Apache and MySQL
- PHP 8.2 or later
- Composer
- Node.js and npm
- Git

## First-Time Setup

### 1. Start the database

Open XAMPP Control Panel and start Apache and MySQL.

Create an empty MySQL database named `electoflow`.

### 2. Configure the backend

Open `backend/.env` and configure:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=electoflow
    DB_USERNAME=root
    DB_PASSWORD=

Never commit the real `.env` file.

Then run:

    cd C:\Users\user1\Desktop\smart-cedra-mt\backend
    composer install
    php artisan key:generate
    php artisan migrate --seed

### 3. Configure the frontend

Run:

    cd C:\Users\user1\Desktop\smart-cedra-mt\frontend
    npm install

## Starting the Application

Start the backend in one PowerShell window:

    cd C:\Users\user1\Desktop\smart-cedra-mt\backend
    php artisan serve

Start the frontend in another PowerShell window:

    cd C:\Users\user1\Desktop\smart-cedra-mt\frontend
    npm run dev

Open `http://localhost:5173` in the browser.

## Verification

Run the backend checks:

    cd C:\Users\user1\Desktop\smart-cedra-mt\backend
    vendor\bin\pint --test app routes database tests
    php artisan test

Run the frontend checks:

    cd C:\Users\user1\Desktop\smart-cedra-mt\frontend
    npm run lint
    npm run build
    node --check public\sw.js

The Vite chunk-size message is currently a non-blocking warning.

## Private Evidence and Backups

Tally-sheet evidence is stored under `backend/storage/app/private` and must only be accessed through authorized application endpoints.

Backups should include:

- The MySQL `electoflow` database
- `backend/storage/app/private`
- Environment and deployment configuration stored securely outside Git

## Common Problems

### Permissions appear restricted

Run:

    cd C:\Users\user1\Desktop\smart-cedra-mt\backend
    php artisan db:seed --class=RbacSeeder

Then sign out and sign in again.

### The second tally entry is unavailable

The second entry must be submitted by a different authorized user. Sign in with another account, such as the Cedra Field Agent.

### CSV columns do not separate in Excel

Download a newly generated export. The export includes `sep=,` so Excel can detect the comma delimiter.

### The frontend cannot reach the backend

Confirm that `php artisan serve` is running at `http://127.0.0.1:8000`.

## Production Checklist

Before production deployment:

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Use HTTPS
- Use unique production credentials
- Protect the `.env` file
- Configure scheduled database and private-file backups
- Keep messaging-provider credentials outside Git
- Run all backend and frontend verification checks