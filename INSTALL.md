# 📦 Installation & Setup Guide — Regional Carpooling Platform
> **PFE Challenge EMC 2026** — Education Media Company  
> **Candidate:** Moaad Daoudi  

---

## 📋 Prerequisites
- **Docker Desktop** (version 24+ with Docker Compose v2)
- **Git**
- A modern web browser

---

## ⚡ Quick Start (1-Command Launch)

### 1. Clone the repository
```bash
git clone https://github.com/Moaad-Daoudi/emc-pfe-covoiturage.git
cd emc-pfe-covoiturage
```

### 2. Configure Environment
```bash
cp .env.example .env.local
```

### 3. Start the Environment with Docker Compose
```bash
docker compose up -d
```
This automatically starts:
- `covoit_php`: PHP 8.3-FPM with all required extensions
- `covoit_web`: Nginx web server on port `8000`
- `covoit_mysql`: MySQL 8.0 on port `3306`

---

## 🌐 Application Access

| Interface | URL | Purpose |
| :--- | :--- | :--- |
| **Public Platform** | [http://localhost:8000](http://localhost:8000) | Search, book segments, driver space, reviews |
| **Admin Back-Office** | [http://localhost:8000/admin](http://localhost:8000/admin) | Full administrative management (EasyAdmin 4) |

---

## 👤 Demo Accounts (Pre-configured in Fixtures)

| Account | Email | Password | Role & Purpose |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@covoit.ma` | `secret123` | Full back-office management at `/admin` |
| **Dual Account (Amina)** | `amina@covoit.ma` | `secret123` | Driver with verified car & active passenger bookings |
| **Second Driver (Karim)** | `karim@covoit.ma` | `secret123` | Driver with past completed trips (for testing reviews) |
| **Passenger Only (Youssef)**| `youssef@covoit.ma` | `secret123` | Passenger without a car (for testing booking flows) |

*Tip: The `/login` page features a **1-Click Demo Login** box to sign in with any of these accounts in 1 click.*

---

## 🧪 Running Automated Tests

Run the full PHPUnit test suite inside the container:
```bash
docker compose exec php bin/phpunit
```
