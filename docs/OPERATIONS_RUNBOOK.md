# ElectoFlow Operations Runbook

## Purpose

This runbook explains how to install, start, verify, back up, and troubleshoot the ElectoFlow application.

## Requirements

### Recommended reproducible setup

- Docker Desktop with Docker Compose

### Original local setup

- XAMPP with Apache and MySQL
- PHP 8.2 or later
- Composer
- Node.js and npm
- Git

## Docker Setup

Docker is the recommended setup for demonstrations, handover, and reproducible development. It runs the React frontend, Laravel backend, queue worker, and MySQL database as one managed environment.

Start Docker Desktop. From the project folder, run:

    docker compose up --build

Open `http://localhost:5173` after all services report that they are running.

Development accounts:

- Tenant administrator: `admin@cedra.test` / `password`
- Platform administrator: `platform@electoflow.test` / `password`

Stop the environment with:

    docker compose down

The Docker database is stored in a named volume and exposed to Windows on port `3307`. It is separate from the XAMPP database on port `3306`.

Check service status and recent logs with:

    docker compose ps
    docker compose logs --tail 100

Run verification inside the containers with:

    docker compose exec backend php artisan test
    docker compose exec frontend npm run lint
    docker compose exec frontend npm run build

## First-Time Setup Without Docker

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
