# ElectoFlow Multi-Tenant Campaign Platform

ElectoFlow is a multi-tenant campaign management and election operations platform. It supports campaign CRM, field coordination, messaging, call-center workflows, incidents, turnout, and controlled election-results ingestion and analytics.

ElectoFlow does **not** provide electronic voting functionality.

## Technology

- Laravel 12 and PHP 8.2+
- React 19 and Vite
- MySQL
- Docker Compose for a reproducible local environment

## Start ElectoFlow with Docker

### Requirements

- Docker Desktop must be installed and running.

### First start

From the project folder, run:

```powershell
docker compose up --build
```

The first start builds the containers, installs dependencies, creates the database tables, and inserts the development accounts. It can take several minutes.

Open ElectoFlow at:

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000
- MySQL from Windows: `localhost:3307`

Development sign-in:

- Tenant administrator: `admin@cedra.test` / `password`
- Platform administrator: `platform@electoflow.test` / `password`

These credentials are development data only and must never be used for a public deployment.

### Later starts

```powershell
docker compose up
```

Use `Ctrl+C` to stop the foreground process. To stop containers started in the background, run:

```powershell
docker compose down
```

Docker stores its MySQL data in a named volume, independently of the existing XAMPP/MySQL database. The container database is exposed on port `3307` to avoid conflicting with XAMPP's normal port `3306`.

### Useful commands

Run the backend tests:

```powershell
docker compose exec backend php artisan test
```

Run the frontend checks:

```powershell
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```

View running containers:

```powershell
docker compose ps
```

View recent logs:

```powershell
docker compose logs --tail 100
```

### Reset only the Docker environment

The following command deletes the Docker database and its container-managed dependencies. It does not delete the source code or the existing XAMPP database.

```powershell
docker compose down --volumes
docker compose up --build
```

## Run without Docker

The original local workflow remains available.

Backend:

```powershell
cd backend
php artisan serve
```

Frontend, in another terminal:

```powershell
cd frontend
npm run dev
```

The local backend runs at http://127.0.0.1:8000 and the frontend at http://localhost:5173.
