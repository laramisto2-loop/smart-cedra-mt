# Smart Cedra MT Platform

Multi-Tenant Campaign Management & Election Operations Platform.

## Project Overview

This project is a SaaS platform designed to support campaign operations, field coordination, CRM workflows, messaging, and election result ingestion/analytics.

The system does NOT provide electronic voting functionality.

---

# Tech Stack

## Backend
- Laravel 12
- PHP 8.2
- REST API

## Frontend
- React
- Vite

## Database
- SQLite (development)
- MySQL/PostgreSQL (planned)

## Dev Tools
- Git
- npm
- Composer

---

# Project Structure

smart-cedra-mt/
│
├── backend/     # Laravel API backend
├── frontend/    # React admin frontend

---

# Backend Setup

```bash
cd backend
php artisan serve

Backend runs on:
http://127.0.0.1:8000

Frontend Setup
cd frontend
npm run dev

Frontend runs on:
http://localhost:5173


If tomorrow you close everything and want to reopen frontend:
You’d do:
cd C:\Users\user1\Desktop\smart-cedra-mt\frontend
npm run dev
Then React starts again.

Similarly For Backend
cd C:\Users\user1\Desktop\smart-cedra-mt\backend
php artisan serve
starts Laravel backend again.