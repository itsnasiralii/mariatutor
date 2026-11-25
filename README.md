# Maria Tutor — Burewala Home & Online Tutor Marketplace

Production Laravel application for **Maria Tutor** (`mariatutor.pk`), built for **Plesk Laravel Toolkit** or standard PHP 8.3+ / MySQL / SQLite environments.

---

## Overview & Key Architecture
- **Framework**: Laravel 13.x / PHP 8.3+
- **Database Support**: MySQL / MariaDB (Production in Plesk) & SQLite (Local development / automated tests)
- **Zero-Plaintext CNIC Security**: Keyed HMAC-SHA256 fingerprint hashing using a dedicated `CNIC_HASH_KEY` with domain-separated prefixes (`cnic:`, `email:`, `phone:`). Raw CNIC numbers are never stored in plaintext.
- **Burewala Spatial Matching**: Adjacency-based lead matching across 12 residential sectors of Burewala inside atomic database transactions.
- **Multi-Role Access Control**: `super_admin`, `admin`, `tutor`, `parent` backed by `App\Enums\UserRole`, role middleware, rate limiting, security headers, and `AuditEvent` logging.
- **Private Verification Vault**: MIME-validated document uploads stored outside `public/`, accessible only to administrators via audited streaming downloads.

---

## Quick Setup (Local Development)

```bash
# 1. Clone the repository
git clone https://github.com/itsnasiralii/mariatutor.git
cd mariatutor

# 2. Install Composer dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run migrations & seed Burewala catalog (prints random local demo credentials to console)
php artisan migrate:fresh --seed

# 5. Run automated feature & security test suite
php artisan test

# 6. Start development server
php artisan serve
```

> **Security Note**: `DatabaseSeeder` never seeds demo accounts in `production`. In production, `ADMIN_EMAIL` and `ADMIN_PASSWORD` must be provided via `.env`.

---

## Required Production Environment Variables (`.env`)

```env
APP_NAME="Maria Tutor"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://mariatutor.pk

# Dedicated 32+ character secret key for HMAC-SHA256 identifier & CNIC hashing
CNIC_HASH_KEY=replace_with_strong_random_secret

# Initial Super Admin bootstrap credentials (used only by db:seed)
ADMIN_EMAIL=admin@mariatutor.pk
ADMIN_PASSWORD=replace_with_strong_password

# Public support contact details (optional)
CONTACT_PHONE=
WHATSAPP_NUMBER=

LOG_LEVEL=warning
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

### Rotating `CNIC_HASH_KEY` on Existing Installations
1. Back up the production database first.
2. Update `CNIC_HASH_KEY` in `.env` and clear config cache (`php artisan config:clear`).
3. Run `php artisan security:rehash-identifiers` to re-hash stored email and phone `identifier_hash` digests under the new key. (Because raw CNICs are never stored in plaintext, users who previously logged in by CNIC can sign in using their registered email, phone number, or `T-BW*` Tutor Code.)
