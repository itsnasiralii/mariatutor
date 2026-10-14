"""Environment-backed application settings; secrets are never committed."""
from pathlib import Path
import os
import secrets
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent
INSTANCE = ROOT / "instance"
INSTANCE.mkdir(exist_ok=True)
load_dotenv(ROOT / ".env")

DEFAULT_DEV_SECRET = "maria-tutor-burewala-dev-secret-key-2026-do-not-use-in-prod"
DEFAULT_DEV_PEPPER = "maria-tutor-burewala-dev-cnic-pepper-2026-do-not-use-in-prod"


def _local_secret(filename: str, fallback: str) -> str:
    path = INSTANCE / filename
    if path.exists():
        content = path.read_text(encoding="utf-8").strip()
        if content:
            return content
    value = fallback or secrets.token_urlsafe(48)
    path.write_text(value, encoding="utf-8")
    try:
        path.chmod(0o600)
    except OSError:
        pass
    return value


class Config:
    SECRET_KEY = (
        os.environ.get("SECRET_KEY")
        or os.environ.get("APP_SECRET_KEY")
        or _local_secret("app_secret.key", DEFAULT_DEV_SECRET)
    )
    # Stable keyed HMAC pepper makes CNIC login possible without storing raw CNIC in plaintext.
    IDENTIFIER_PEPPER = (
        os.environ.get("IDENTIFIER_PEPPER")
        or _local_secret("identifier_pepper.key", DEFAULT_DEV_PEPPER)
    )
    SQLALCHEMY_DATABASE_URI = os.environ.get(
        "DATABASE_URL", f"sqlite:///{INSTANCE / 'marketplace.db'}"
    )
    if SQLALCHEMY_DATABASE_URI.startswith("postgres://"):
        SQLALCHEMY_DATABASE_URI = SQLALCHEMY_DATABASE_URI.replace(
            "postgres://", "postgresql+psycopg://", 1
        )
    elif SQLALCHEMY_DATABASE_URI.startswith("postgresql://"):
        SQLALCHEMY_DATABASE_URI = SQLALCHEMY_DATABASE_URI.replace(
            "postgresql://", "postgresql+psycopg://", 1
        )
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    MAX_CONTENT_LENGTH = 8 * 1024 * 1024  # 8 MB max upload limit
    PRIVATE_UPLOAD_DIR = Path(
        os.environ.get("PRIVATE_UPLOAD_DIR", str(INSTANCE / "private_documents"))
    )
    SESSION_COOKIE_HTTPONLY = True
    SESSION_COOKIE_SAMESITE = "Lax"
    SESSION_COOKIE_SECURE = os.environ.get("COOKIE_SECURE", "0").lower() in {"1", "true", "yes"}
    PERMANENT_SESSION_LIFETIME = 60 * 60 * 12
    RATELIMIT_STORAGE_URI = os.environ.get("RATELIMIT_STORAGE_URI", "memory://")
    RATELIMIT_HEADERS_ENABLED = True
    RATELIMIT_ENABLED = os.environ.get("RATELIMIT_ENABLED", "0").lower() in {"1", "true", "yes"}
    TEMPLATES_AUTO_RELOAD = True
    BOOTSTRAP_SUPERADMIN_EMAIL = os.environ.get("BOOTSTRAP_SUPERADMIN_EMAIL", "")
    BOOTSTRAP_SUPERADMIN_PASSWORD = os.environ.get("BOOTSTRAP_SUPERADMIN_PASSWORD", "")
