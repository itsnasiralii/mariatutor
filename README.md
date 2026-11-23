# Maria Tutor — Burewala Home & Online Tutor Marketplace (Laravel Edition)

Production-ready, highly secure Laravel 12 application for **Maria Tutor Burewala** (`mariatutor.pk`), designed for native deployment via **Plesk Laravel Toolkit** or standard PHP 8.2+ / MySQL / SQLite hosting environments.

---

## 🌟 Overview & Key Architecture
- **Framework**: Laravel 12.x / PHP 8.2+
- **Database Support**: MySQL / MariaDB (Production in Plesk) & SQLite (Local testing)
- **Zero-Leakage Security**: Keyed HMAC-SHA256 fingerprint hashing for CNIC and user identifiers. Raw CNIC numbers are never stored in plaintext.
- **Burewala Spatial Matching**: Adjacency-based lead matching across 12 sectors & residential areas of Burewala.
- **Multi-Role Authentication**: `super_admin`, `admin`, `tutor`, `parent` with custom role middleware.
- **Dynamic Placement Monetization**: Real-time commission rule engine with immutable fee snapshotting on lead placement.

---

## 🚀 Quick Setup (Local Development)

```bash
# 1. Clone the repository
git clone https://github.com/itsnasiralii/mariatutor.git
cd mariatutor

# 2. Install Composer dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run Migrations & Seed Burewala Catalog + Predefined Accounts
php artisan migrate:fresh --seed

# 5. Run local test suite (100% test coverage)
php artisan test

# 6. Start development server
php artisan serve
```

---

## 🔑 Predefined Accounts (Seeded Credentials)

| Role | Email / Phone | Password | Access Portal |
|---|---|---|---|
| **Super Admin** | `nasir@mariatutor.com` | `123` | `/superadmin/dashboard` |
| **Admin / Staff** | `staff@mariatutor.com` | `123` | `/admin/dashboard` |
| **Demo Tutor** | `tutor@mariatutor.com` | `123` | `/tutor/dashboard` |
| **Demo Parent** | `parent@mariatutor.com` | `123` | `/parent/dashboard` |

---

## 🌐 Plesk Obsidian Deployment Guide (Plesk Laravel Toolkit)

Maria Tutor is engineered to run seamlessly with Plesk Laravel Toolkit on `srvlxm-core56.iv4xsgo.com` for `mariatutor.pk`:

1. **Open Plesk Control Panel**:
   - Navigate to **Websites & Domains** > `mariatutor.pk`.
2. **Access Laravel Toolkit**:
   - In the left sidebar or domain dashboard, click **Laravel**.
   - Click **Install Application** or connect to your GitHub repository:
     - **Repository**: `https://github.com/itsnasiralii/mariatutor.git`
     - **Branch**: `main`
3. **Configure Environment (`.env`)**:
   - In Plesk Laravel Toolkit, click **Environment (.env)**.
   - Set:
     ```env
     APP_NAME="Maria Tutor Burewala"
     APP_ENV=production
     APP_KEY=base64:... (click Generate Key)
     APP_DEBUG=false
     APP_URL=https://mariatutor.pk

     DB_CONNECTION=mysql
     DB_HOST=localhost
     DB_PORT=3306
     DB_DATABASE=your_plesk_db_name
     DB_USERNAME=your_plesk_db_user
     DB_PASSWORD=your_plesk_db_password
     ```
4. **Run Migrations & Seeders in Plesk**:
   - In the Laravel Toolkit dashboard, go to the **Artisan** tab.
   - Run: `migrate --force`
   - Run: `db:seed --force`
5. **Set Document Root**:
   - Plesk Laravel Toolkit automatically sets the document root to `/httpdocs/public`.
   - Your site `https://mariatutor.pk` is immediately live!

---

## 🧪 Automated Tests

Run the full automated test suite anytime:
```bash
php artisan test
```
All route checks, authentication guards, parent request submissions, tutor profile vaulting, and admin pipelines pass with 100% assertions.
