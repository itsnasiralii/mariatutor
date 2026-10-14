# Burewala Tutor Marketplace — MVP Code

This is a small Flask application for the Burewala Phase 1 tutor marketplace. It keeps the user interface server-rendered and uses PostgreSQL in deployment (SQLite locally). There are no OpenAI/GPT SDKs, API keys, prompts, or external AI calls in this code. CNIC document checks are manual by an authorized admin; the gender indicator is only a profile hint derived from the last CNIC digit.

## Current features

- Parent and tutor registration, login, logout, and role-based dashboards.
- Tutor CNIC-format login; the raw CNIC is not stored. A keyed HMAC fingerprint is stored instead. Passwords use Werkzeug scrypt hashes.
- Tutor profile, teaching areas, subjects, classes, modes, fee range, availability, and private document uploads.
- Parent tuition requests with area, subject, class, budget, timing, mode, and tutor-gender preference.
- Admin-managed matching, nearby-area adjacency, private opportunity exposure/view/interest history, and tutor shortlisting.
- Parent/tutor contact release is a separate permission-gated admin action and is audit logged.
- Manual tutor/document verification, private internal notes, configurable basic lead statuses in code, commission rule, payment ledger, review moderation, audit log, and health/error screen.
- CSRF protection, upload extension/size/signature checks, rate limits, security response headers, and private document storage outside the public static directory.

## Run locally

Use Python 3.12 or newer and PostgreSQL for production. For a local demonstration, SQLite is the default.

```bash
python -m venv .venv
source .venv/bin/activate        # Windows PowerShell: .venv\Scripts\Activate.ps1
pip install -r requirements.txt
cp .env.example .env             # Windows: copy .env.example .env
```

Set these values in `.env` before the first start:

- `APP_SECRET_KEY`: long random secret; do not reuse the example.
- `IDENTIFIER_PEPPER`: a separate long random secret. Losing/changing it invalidates all CNIC/email login fingerprints.
- `BOOTSTRAP_SUPERADMIN_EMAIL` and `BOOTSTRAP_SUPERADMIN_PASSWORD`: one-time owner account bootstrap; password must be at least 8 characters and include uppercase, lowercase, and a number.

Then start:

```bash
flask --app app init-db
flask --app app run
```

Open `http://127.0.0.1:5000`. Configure cities/areas, subjects, classes and modes in **Admin → Areas & business settings** before registering test users. The Super Admin is created only when the bootstrap email/password are provided and there is no existing Super Admin. Remove the bootstrap password from deployment settings after the owner account is created.

Run workflow/security regression tests with `pip install -r requirements-dev.txt` followed by `pytest -q`.

## Render setup

`render.yaml` is a starting blueprint for a web service, PostgreSQL database, and private persistent disk. Review the Render plan and current pricing before deployment. Set the bootstrap owner email/password in the Render dashboard; both are marked `sync: false`. Use a unique secret pepper and secret key. The health check is `/healthz`.

The private disk is required for uploaded documents with this implementation. Back up the database and private disk separately, restrict access to both, and test restore procedures. Do not put private uploads under `static/` or a public object bucket. For multiple application instances, replace the in-memory rate-limit store with shared Redis and review session/rate-limit settings.

## Important launch gaps

This is a runnable MVP base, not a claim of production readiness. Before collecting real CNICs or launching publicly, complete these items:

1. Add an approved SMS/email password-reset flow. The current password-reset page intentionally does not send a reset link or reveal/set passwords.
2. Add database migrations and run them as a controlled deployment step. `create_all()` creates new tables but does not safely evolve existing schemas.
3. Publish reviewed privacy/consent/retention terms and document breach handling. Define CNIC/document deletion schedule and backups.
4. Configure persistent private document storage encryption/access policy and validate content with a robust antivirus/image/PDF scanner before opening uploads. Signature checks alone do not make files safe.
5. Add account lockout/monitoring and persistent shared rate limiting; audit recovery and admin offboarding.
6. Replace the starter health/error display with monitored error reporting that does not capture secrets or document contents. Test database/storage failures.
7. Confirm commission contract, payment reconciliation, refund handling, and the exact point at which commission is earned.
8. Verify local legal and operational requirements before collecting identity documents or publishing reviews.

The app deliberately disables public tutor detail pages and exact location pins. Opportunity descriptions omit parent identifiers and sub-area text. Contact information becomes visible only after an authorized introduction. Normal Admin permissions are assigned by the Super Admin.

## Where to look when something fails

| Concern | File |
|---|---|
| Application setup, security headers, CSRF, bootstrap | `app.py` |
| Settings/secrets/database URI | `config.py` |
| Database entities and relationships | `models.py` |
| Password/CNIC fingerprints, permissions, matching | `services.py` |
| Registration and login | `auth.py` |
| Parent/tutor pages and workflows | `routes.py` |
| Admin operations and Super Admin controls | `admin_routes.py` |
| Database/login/rate-limit extensions | `extensions.py` |
| Visual styles | `static/style.css` |
| Page content | `templates/` |
| Dependencies/deployment examples | `requirements.txt`, `render.yaml` |

## Project structure

```text
app.py                  Flask application factory, common security controls and CLI
auth.py                 Registration, login and password-reset placeholder
admin_routes.py         Admin and Super Admin workflows
config.py               Environment-backed runtime configuration
extensions.py           Flask extension instances
models.py               Database models
routes.py                Public, parent and tutor workflows
services.py              Matching, permissions, audit helpers and password/CNIC utilities
requirements.txt        Pinned Python packages
render.yaml             Starter Render service/database/disk configuration
static/style.css         Responsive styling
templates/               Server-rendered HTML pages
instance/                Local-only database, secret key and private uploaded files (ignored by git)
```

## Verification note

This environment could not reach the package index, so dependencies could not be installed and the Flask app could not be launched here. Python syntax compilation is performed separately where possible. Run the local setup above in an environment with package access, then exercise parent request → admin match → tutor interest → admin shortlist → authorized introduction before launch.
