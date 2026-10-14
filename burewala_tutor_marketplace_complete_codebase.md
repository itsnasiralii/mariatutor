# Maria Tutor — Burewala Tutor Marketplace Complete Production Codebase

Complete, production-grade implementation of the **Maria Tutor (Burewala Tutor Marketplace)** conforming to the official specification (`Burewala_Tutor_Marketplace_Architecture_Specification.docx`).

## Pre-Configured Demo Accounts (Seeded in `seed_burewala.py` & `instance/marketplace.db`)

| Role | Login Identifier (Email / CNIC / Tutor ID) | Password | Portal Capabilities |
| :--- | :--- | :--- | :--- |
| **Super Admin** (`super_admin`) | `admin@mariahome.com` | `Admin123` | Full governance, Burewala catalog & `AreaAdjacency` matrix, commission rules, admin RBAC, audit logs, `/healthz` |
| **Normal Admin** (`admin`) | `staff@mariahome.com` | `Staff123` | Lead pipeline queue, spatial match engine trigger, tutor verification, contact release, payment ledger |
| **Approved Senior Tutor** (`tutor`) | `36402-1234567-1` or `tutor@mariahome.com` or `T-BW001` | `Tutor123` | Anonymized area-level opportunity cards, *I Am Interested* workflow, private document vault, unlocked contacts |
| **Parent / Guardian** (`parent`) | `parent@mariahome.com` | `Parent123` | Submit tuition requests, track matching status, view authorized tutor introductions, submit verified reviews |

## Quick Start Commands

```bash
python -m venv .venv
source .venv/bin/activate        # Windows PowerShell: .venv\Scripts\Activate.ps1
pip install -r requirements.txt
flask --app app init-db
python seed_burewala.py
flask --app app run
```

## FILE 1: `requirements.txt`

```text
Flask==3.1.2
Flask-SQLAlchemy==3.1.1
Flask-Login==0.6.3
Flask-Limiter==3.12
Flask-Migrate==4.1.0
python-dotenv==1.1.1
psycopg[binary]==3.2.10
gunicorn==23.0.0
```

## FILE 2: `config.py`

```python
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
```

## FILE 3: `extensions.py`

```python
"""Shared Flask extension instances initialized by the application factory."""
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from flask_login import LoginManager
from flask_migrate import Migrate
from flask_sqlalchemy import SQLAlchemy

db = SQLAlchemy()
login_manager = LoginManager()
migrate = Migrate()
limiter = Limiter(key_func=get_remote_address, default_limits=[])
```

## FILE 4: `models.py`

```python
"""Maria Tutor (Burewala Tutor Marketplace) SQLAlchemy data models.

Architectural invariants enforced at the data layer:
1. Raw Pakistani CNIC numbers are NEVER persisted in plaintext; only an HMAC-SHA256
   fingerprint (`identifier_hash`) is stored.
2. Spatial proximity matching is driven by `AreaAdjacency` (Priority 1..5) with zero
   commercial map API dependencies.
3. Confirmed placements snapshot the active commission rule (`commission_snapshot_type`,
   `commission_snapshot_value`) so subsequent commission rule edits never alter historical
   placement calculations.
"""
from datetime import datetime, timezone
from flask_login import UserMixin
from sqlalchemy import UniqueConstraint
from extensions import db


def utcnow():
    return datetime.now(timezone.utc)


tutor_catalog = db.Table(
    "tutor_catalog",
    db.Column("tutor_id", db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), primary_key=True),
    db.Column("catalog_id", db.Integer, db.ForeignKey("catalog_item.id", ondelete="RESTRICT"), primary_key=True),
)


class User(UserMixin, db.Model):
    id = db.Column(db.Integer, primary_key=True)
    identifier_hash = db.Column(db.String(64), unique=True, nullable=False, index=True)
    identifier_type = db.Column(db.String(20), nullable=False)
    email = db.Column(db.String(254), nullable=True, index=True)
    phone = db.Column(db.String(32), nullable=True)
    role = db.Column(db.String(24), nullable=False, index=True)
    password_hash = db.Column(db.String(255), nullable=False)
    active = db.Column(db.Boolean, default=True, nullable=False)
    permissions_json = db.Column(db.Text, default="{}", nullable=False)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    last_login_at = db.Column(db.DateTime(timezone=True))
    tutor = db.relationship("TutorProfile", back_populates="user", uselist=False, cascade="all, delete-orphan")
    parent = db.relationship("ParentProfile", back_populates="user", uselist=False, cascade="all, delete-orphan")


class CatalogItem(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    kind = db.Column(db.String(24), nullable=False, index=True)
    name = db.Column(db.String(100), nullable=False)
    parent_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"))
    active = db.Column(db.Boolean, default=True, nullable=False)
    sort_order = db.Column(db.Integer, default=0, nullable=False)
    parent = db.relationship("CatalogItem", remote_side=[id], backref="children")
    __table_args__ = (UniqueConstraint("kind", "name", "parent_id", name="uq_catalog_kind_name_parent"),)


class AreaAdjacency(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id", ondelete="CASCADE"), nullable=False, index=True)
    nearby_area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id", ondelete="CASCADE"), nullable=False, index=True)
    priority = db.Column(db.Integer, default=1, nullable=False)
    area = db.relationship("CatalogItem", foreign_keys=[area_id])
    nearby_area = db.relationship("CatalogItem", foreign_keys=[nearby_area_id])
    __table_args__ = (UniqueConstraint("area_id", "nearby_area_id", name="uq_area_adjacency"),)


class TutorProfile(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey("user.id", ondelete="CASCADE"), unique=True, nullable=False)
    tutor_code = db.Column(db.String(20), unique=True, nullable=False, index=True)
    full_name = db.Column(db.String(120), nullable=False)
    gender = db.Column(db.String(24), nullable=False, default="Prefer not to say")
    cnic_gender_hint = db.Column(db.String(12))
    qualification = db.Column(db.String(200), default="")
    experience = db.Column(db.String(500), default="")
    expected_fee_min = db.Column(db.Integer)
    expected_fee_max = db.Column(db.Integer)
    availability = db.Column(db.String(300), default="")
    verification_status = db.Column(db.String(32), default="Pending", nullable=False, index=True)
    verification_note = db.Column(db.String(500), default="")
    approved_at = db.Column(db.DateTime(timezone=True))
    user = db.relationship("User", back_populates="tutor")
    catalog = db.relationship("CatalogItem", secondary=tutor_catalog, lazy="selectin")


class ParentProfile(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey("user.id", ondelete="CASCADE"), unique=True, nullable=False)
    full_name = db.Column(db.String(120), nullable=False)
    user = db.relationship("User", back_populates="parent")


class TuitionRequest(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    public_ref = db.Column(db.String(16), unique=True, nullable=False, index=True)
    parent_user_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False, index=True)
    area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    subject_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    class_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    mode_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False)
    subarea_text = db.Column(db.String(120), default="")
    budget_min = db.Column(db.Integer)
    budget_max = db.Column(db.Integer)
    preferred_time = db.Column(db.String(120), nullable=False)
    requirements = db.Column(db.String(500), default="")
    tutor_gender_preference = db.Column(db.String(24), default="Any", nullable=False)
    status = db.Column(db.String(32), default="New request", nullable=False, index=True)
    assigned_admin_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    selected_tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id"))
    contact_released_at = db.Column(db.DateTime(timezone=True))
    commission_snapshot_type = db.Column(db.String(24), nullable=True)
    commission_snapshot_value = db.Column(db.Integer, nullable=True)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    updated_at = db.Column(db.DateTime(timezone=True), default=utcnow, onupdate=utcnow, nullable=False)
    parent = db.relationship("User", foreign_keys=[parent_user_id])
    assigned_admin = db.relationship("User", foreign_keys=[assigned_admin_id])
    area = db.relationship("CatalogItem", foreign_keys=[area_id])
    subject = db.relationship("CatalogItem", foreign_keys=[subject_id])
    school_class = db.relationship("CatalogItem", foreign_keys=[class_id])
    mode = db.relationship("CatalogItem", foreign_keys=[mode_id])
    selected_tutor = db.relationship("TutorProfile", foreign_keys=[selected_tutor_id])


class OpportunityExposure(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id", ondelete="CASCADE"), nullable=False, index=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), nullable=False, index=True)
    shown_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    viewed_at = db.Column(db.DateTime(timezone=True))
    interested_at = db.Column(db.DateTime(timezone=True))
    interest_note = db.Column(db.String(240), default="")
    request = db.relationship("TuitionRequest")
    tutor = db.relationship("TutorProfile")
    __table_args__ = (UniqueConstraint("request_id", "tutor_id", name="uq_exposure_request_tutor"),)


class VerificationDocument(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), nullable=False, index=True)
    document_type = db.Column(db.String(32), nullable=False)
    original_name = db.Column(db.String(200), nullable=False)
    stored_name = db.Column(db.String(80), unique=True, nullable=False)
    uploaded_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    review_status = db.Column(db.String(24), default="Pending", nullable=False)
    reviewer_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    review_note = db.Column(db.String(500), default="")
    tutor = db.relationship("TutorProfile")
    reviewer = db.relationship("User")


class LeadEvent(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id", ondelete="CASCADE"), nullable=False, index=True)
    actor_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    event_type = db.Column(db.String(60), nullable=False)
    details = db.Column(db.String(500), default="")
    private = db.Column(db.Boolean, default=True, nullable=False)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    actor = db.relationship("User")


class CommissionRule(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    rule_type = db.Column(db.String(24), nullable=False)  # 'percent' or 'fixed'
    value = db.Column(db.Integer, nullable=False)
    active = db.Column(db.Boolean, default=True, nullable=False)
    effective_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)


class PaymentEntry(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id"), nullable=False, index=True)
    amount_pkr = db.Column(db.Integer, nullable=False)
    platform_commission_pkr = db.Column(db.Integer, nullable=False)
    tutor_amount_pkr = db.Column(db.Integer, nullable=False)
    method = db.Column(db.String(32), nullable=False)
    reference = db.Column(db.String(120), default="")
    status = db.Column(db.String(24), default="Recorded", nullable=False)
    recorded_by_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False)
    notes = db.Column(db.String(500), default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    request = db.relationship("TuitionRequest")
    recorded_by = db.relationship("User")


class Review(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id"), nullable=False, index=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id"), nullable=False, index=True)
    parent_user_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False)
    rating = db.Column(db.Integer, nullable=False)
    body = db.Column(db.String(1000), default="")
    status = db.Column(db.String(24), default="Pending", nullable=False)
    moderator_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    request = db.relationship("TuitionRequest")
    tutor = db.relationship("TutorProfile")
    parent = db.relationship("User", foreign_keys=[parent_user_id])
    moderator = db.relationship("User", foreign_keys=[moderator_id])


class SystemSetting(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    key = db.Column(db.String(100), unique=True, nullable=False)
    value = db.Column(db.Text, default="", nullable=False)
    updated_at = db.Column(db.DateTime(timezone=True), default=utcnow, onupdate=utcnow, nullable=False)


class AuditEvent(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    actor_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    action = db.Column(db.String(80), nullable=False)
    resource_type = db.Column(db.String(60), nullable=False)
    resource_id = db.Column(db.String(64), default="")
    summary = db.Column(db.String(500), default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False, index=True)
    actor = db.relationship("User")


class AppError(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    error_ref = db.Column(db.String(20), unique=True, nullable=False)
    module = db.Column(db.String(80), nullable=False)
    summary = db.Column(db.String(300), nullable=False)
    details = db.Column(db.Text, default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    resolved = db.Column(db.Boolean, default=False, nullable=False)
```

## FILE 5: `services.py`

```python
"""Core business utilities, security decorators, and spatial matching engine."""
from datetime import datetime, timezone
from functools import wraps
import hashlib
import hmac
import json
import re
import secrets
from flask import current_app, flash, redirect, request, url_for
from flask_login import current_user
from werkzeug.security import check_password_hash, generate_password_hash
from extensions import db
from models import (
    AreaAdjacency,
    AuditEvent,
    CatalogItem,
    CommissionRule,
    LeadEvent,
    OpportunityExposure,
    TuitionRequest,
    TutorProfile,
    User,
)

CNIC_RE = re.compile(r"^\d{5}-\d{7}-\d$")
PASSWORD_RE = re.compile(r"^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$")
ADMIN_PERMISSIONS = {
    "view_requests",
    "edit_requests",
    "match_tutors",
    "view_tutor_profiles",
    "verify_tutors",
    "view_documents",
    "view_contacts",
    "record_payments",
    "moderate_reviews",
    "view_audit",
    "release_contacts",
}
LEAD_STATUSES = [
    "New request",
    "Reviewed",
    "Matching",
    "Tutor interested",
    "Parent interested",
    "Demo arranged",
    "Confirmed",
    "Active tuition",
    "Completed",
    "On hold",
    "Cancelled",
    "Rejected",
    "Re-matching required",
]


def now():
    return datetime.now(timezone.utc)


def normalized_identifier(value: str, kind: str) -> str:
    value = (value or "").strip()
    if kind == "cnic":
        return value.replace(" ", "")
    if kind == "email":
        return value.lower()
    if kind == "phone":
        return re.sub(r"[^\d+]", "", value)
    return value.lower()


def identifier_hash(value: str, kind: str) -> str:
    normalized = normalized_identifier(value, kind)
    pepper = current_app.config["IDENTIFIER_PEPPER"].encode("utf-8")
    return hmac.new(pepper, f"{kind}:{normalized}".encode("utf-8"), hashlib.sha256).hexdigest()


def password_ok(value: str) -> bool:
    return bool(PASSWORD_RE.match(value or ""))


def make_password_hash(value: str) -> str:
    return generate_password_hash(value, method="scrypt")


def verify_password(user: User, value: str) -> bool:
    return check_password_hash(user.password_hash, value or "")


def audit(action: str, resource_type: str, resource_id="", summary="", actor=None):
    target = actor or current_user
    actor_id = getattr(target, "id", None) if getattr(target, "is_authenticated", False) else getattr(actor, "id", None)
    db.session.add(
        AuditEvent(
            actor_id=actor_id,
            action=action,
            resource_type=resource_type,
            resource_id=str(resource_id or ""),
            summary=(summary or "")[:500],
        )
    )


def lead_event(request_obj: TuitionRequest, event_type: str, details="", private=True, actor=None):
    target = actor or current_user
    actor_id = getattr(target, "id", None) if getattr(target, "is_authenticated", False) else getattr(actor, "id", None)
    db.session.add(
        LeadEvent(
            request_id=request_obj.id,
            actor_id=actor_id,
            event_type=event_type,
            details=(details or "")[:500],
            private=private,
        )
    )


def has_permission(user: User, permission: str) -> bool:
    if not user.is_authenticated or not user.active:
        return False
    if user.role == "super_admin":
        return True
    if user.role != "admin":
        return False
    try:
        return bool(json.loads(user.permissions_json or "{}").get(permission, False))
    except (TypeError, ValueError):
        return False


def require_permission(permission: str):
    def decorator(fn):
        @wraps(fn)
        def wrapped(*args, **kwargs):
            if not current_user.is_authenticated:
                return redirect(url_for("auth.login", next=request.path))
            if not has_permission(current_user, permission):
                flash(f"Is action ke liye '{permission}' permission zaroori hai.", "error")
                return redirect(url_for("admin.dashboard"))
            return fn(*args, **kwargs)

        return wrapped

    return decorator


def require_roles(*roles):
    def decorator(fn):
        @wraps(fn)
        def wrapped(*args, **kwargs):
            if not current_user.is_authenticated:
                return redirect(url_for("auth.login", next=request.path))
            if current_user.role not in roles:
                role_label = current_user.role.replace("_", " ").title()
                allowed_label = ", ".join(r.replace("_", " ").title() for r in roles)
                flash(
                    f"Access Denied: Yeh page {allowed_label} ke liye hai. Aap filhal '{role_label}' account se login hain. Doosre account ke liye pehle Log Out karein.",
                    "error",
                )
                if current_user.role == "super_admin":
                    return redirect(url_for("superadmin.dashboard"))
                if current_user.role == "admin":
                    return redirect(url_for("admin.dashboard"))
                if current_user.role == "tutor":
                    return redirect(url_for("main.tutor_dashboard"))
                if current_user.role == "parent":
                    return redirect(url_for("main.parent_dashboard"))
                return redirect(url_for("main.index"))
            return fn(*args, **kwargs)

        return wrapped

    return decorator


def active_catalog(kind: str):
    return (
        CatalogItem.query.filter_by(kind=kind, active=True)
        .order_by(CatalogItem.sort_order, CatalogItem.name)
        .all()
    )


def matching_tutors(req: TuitionRequest):
    """Spatial proximity query checking direct area + adjacent areas, subject, class, mode, and approved status."""
    area_priorities = {req.area_id: 0}
    for link in AreaAdjacency.query.filter_by(area_id=req.area_id).all():
        area_priorities[link.nearby_area_id] = min(
            area_priorities.get(link.nearby_area_id, 99), link.priority
        )
    for link in AreaAdjacency.query.filter_by(nearby_area_id=req.area_id).all():
        area_priorities[link.area_id] = min(
            area_priorities.get(link.area_id, 99), link.priority
        )
    allowed_areas = set(area_priorities.keys())

    tutors = (
        TutorProfile.query.join(User)
        .filter(
            User.active.is_(True),
            User.role == "tutor",
            TutorProfile.verification_status == "Approved",
        )
        .all()
    )
    matches = []
    for tutor in tutors:
        ids = {item.id for item in tutor.catalog}
        if req.subject_id not in ids or req.class_id not in ids or req.mode_id not in ids:
            continue
        if req.tutor_gender_preference != "Any" and tutor.gender != req.tutor_gender_preference:
            continue
        if (
            req.budget_max is not None
            and tutor.expected_fee_min is not None
            and tutor.expected_fee_min > req.budget_max
        ):
            continue
        tutor_areas = {item.id for item in tutor.catalog if item.kind == "area"}
        matched_areas = tutor_areas.intersection(allowed_areas)
        if not matched_areas:
            continue

        best_priority = min(area_priorities[a_id] for a_id in matched_areas)
        if best_priority == 0:
            area_points = 20
        elif best_priority == 1:
            area_points = 14
        else:
            area_points = 10

        req_words = {w.lower() for w in req.preferred_time.split() if len(w) > 2}
        tutor_words = {w.lower() for w in (tutor.availability or "").split() if len(w) > 2}
        timing_fit = bool(req_words & tutor_words)
        budget_fit = (
            tutor.expected_fee_min is None
            or req.budget_max is None
            or tutor.expected_fee_min <= req.budget_max
        )
        score = 60 + area_points + (10 if budget_fit else 0) + (5 if timing_fit else 0)
        matches.append((tutor, min(score, 100), req.area_id in tutor_areas))

    return sorted(matches, key=lambda row: (-row[1], row[0].tutor_code))


def generate_opportunities(req: TuitionRequest, actor=None):
    matches = matching_tutors(req)
    for tutor, score, exact_area in matches:
        existing = OpportunityExposure.query.filter_by(request_id=req.id, tutor_id=tutor.id).first()
        if not existing:
            db.session.add(OpportunityExposure(request_id=req.id, tutor_id=tutor.id))
            lead_event(
                req,
                "Opportunity shown",
                f"Tutor {tutor.tutor_code}; score {score}; exact area={exact_area}",
                actor=actor,
            )
    req.status = "Matching" if matches else "Re-matching required"
    lead_event(req, "Matching completed", f"Eligible tutors: {len(matches)}", actor=actor)
    audit("generate_matches", "tuition_request", req.id, f"Eligible tutors: {len(matches)}", actor=actor)
    db.session.commit()
    return matches


def snapshot_commission_for_request(req: TuitionRequest):
    """Snapshot the active CommissionRule onto a placement so later edits never alter past records."""
    if req.commission_snapshot_type and req.commission_snapshot_value is not None:
        return
    rule = (
        CommissionRule.query.filter_by(active=True)
        .order_by(CommissionRule.effective_at.desc())
        .first()
    )
    if rule:
        norm_type = "percent" if rule.rule_type in {"percent", "percentage"} else "fixed"
        req.commission_snapshot_type = norm_type
        req.commission_snapshot_value = rule.value


def create_tutor_code() -> str:
    count = TutorProfile.query.count() + 1
    candidate = f"T-BW{count:03d}"
    if not TutorProfile.query.filter_by(tutor_code=candidate).first():
        return candidate
    return "T-" + secrets.token_hex(4).upper()


def create_request_ref() -> str:
    count = TuitionRequest.query.count() + 1001
    candidate = f"BW-REQ{count}"
    if not TuitionRequest.query.filter_by(public_ref=candidate).first():
        return candidate
    return "BW-" + secrets.token_hex(4).upper()


def tutor_can_see_request(tutor: TutorProfile, req: TuitionRequest) -> bool:
    return OpportunityExposure.query.filter_by(request_id=req.id, tutor_id=tutor.id).first() is not None
```

## FILE 6: `auth.py`

```python
"""Authentication and account lifecycle routes for Parents, Tutors, and Admins."""
from datetime import datetime, timezone
from flask import Blueprint, abort, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required, login_user, logout_user
from extensions import db, limiter
from models import CatalogItem, ParentProfile, TutorProfile, User
from services import (
    CNIC_RE,
    audit,
    create_tutor_code,
    identifier_hash,
    make_password_hash,
    password_ok,
    verify_password,
)

bp = Blueprint("auth", __name__)


@bp.route("/register/tutor", methods=["GET", "POST"])
@limiter.limit("5 per hour")
def register_tutor():
    if current_user.is_authenticated:
        return redirect(url_for("main.dashboard"))
    choices = (
        CatalogItem.query.filter(
            CatalogItem.active.is_(True),
            CatalogItem.kind.in_(["area", "subject", "class", "mode"]),
        )
        .order_by(CatalogItem.kind, CatalogItem.sort_order, CatalogItem.name)
        .all()
    )
    if request.method == "POST":
        name = request.form.get("full_name", "").strip()
        cnic = request.form.get("cnic", "").strip()
        phone = request.form.get("phone", "").strip()
        email = request.form.get("email", "").strip().lower() or None
        password = request.form.get("password", "")
        gender = request.form.get("gender", "Prefer not to say")
        if gender not in {"Prefer not to say", "Male", "Female", "Other"}:
            gender = "Prefer not to say"

        if not name or not CNIC_RE.fullmatch(cnic):
            flash("Name aur CNIC format XXXXX-XXXXXXX-X mein darj karein.", "error")
        elif not phone or len(phone) > 32:
            flash("Contact phone number zaroori hai.", "error")
        elif email and (len(email) > 254 or "@" not in email):
            flash("Email address check karein.", "error")
        elif not password_ok(password):
            flash("Password minimum 8 characters ka ho aur uppercase, lowercase, number shamil hon.", "error")
        else:
            digest = identifier_hash(cnic, "cnic")
            if User.query.filter_by(identifier_hash=digest).first():
                flash("Is CNIC ke saath account pehle se maujood hai. Password reset karein ya Admin se rabta karein.", "error")
            else:
                selected_ids = {int(x) for x in request.form.getlist("catalog_ids") if x.isdigit()}
                selected = (
                    CatalogItem.query.filter(
                        CatalogItem.id.in_(selected_ids), CatalogItem.active.is_(True)
                    ).all()
                    if selected_ids
                    else []
                )
                allowed_kinds = {"area", "subject", "class", "mode"}
                if any(item.kind not in allowed_kinds for item in selected):
                    abort(400)
                fee_min = _int_or_none(request.form.get("expected_fee_min"))
                fee_max = _int_or_none(request.form.get("expected_fee_max"))
                if fee_min is not None and fee_max is not None and fee_min > fee_max:
                    flash("Minimum fee maximum fee se zyada nahi ho sakti.", "error")
                    return render_template("register_tutor.html", choices=choices), 400

                cnic_hint = "Male" if int(cnic[-1]) % 2 else "Female"
                if gender == "Prefer not to say":
                    gender = cnic_hint

                tutor = TutorProfile(
                    full_name=name,
                    tutor_code=create_tutor_code(),
                    gender=gender,
                    cnic_gender_hint=cnic_hint,
                    qualification=request.form.get("qualification", "").strip()[:200],
                    experience=request.form.get("experience", "").strip()[:500],
                    availability=request.form.get("availability", "").strip()[:300],
                    expected_fee_min=fee_min,
                    expected_fee_max=fee_max,
                )
                user = User(
                    identifier_hash=digest,
                    identifier_type="cnic",
                    email=email,
                    phone=phone,
                    role="tutor",
                    password_hash=make_password_hash(password),
                    tutor=tutor,
                )
                tutor.catalog = selected
                db.session.add(user)
                db.session.flush()
                audit("tutor_registered", "tutor", tutor.tutor_code, "Tutor account created", actor=user)
                db.session.commit()
                flash(
                    f"Account bana diya gaya. Aapka Tutor ID {tutor.tutor_code} hai. Verification complete hone tak opportunities nahi dikhengi.",
                    "success",
                )
                return redirect(url_for("auth.login"))
    return render_template("register_tutor.html", choices=choices)


@bp.route("/register/parent", methods=["GET", "POST"])
@limiter.limit("5 per hour")
def register_parent():
    if current_user.is_authenticated:
        return redirect(url_for("main.dashboard"))
    if request.method == "POST":
        name = request.form.get("full_name", "").strip()
        email = request.form.get("email", "").strip().lower()
        phone = request.form.get("phone", "").strip()
        password = request.form.get("password", "")
        if not name or not email or "@" not in email or len(email) > 254 or not phone or len(phone) > 32:
            flash("Name, valid email aur contact phone fill karein.", "error")
        elif not password_ok(password):
            flash("Password minimum 8 characters ka ho aur uppercase, lowercase, number shamil hon.", "error")
        elif User.query.filter_by(identifier_hash=identifier_hash(email, "email")).first():
            flash("Is email par account pehle se hai. Login ya password reset karein.", "error")
        else:
            user = User(
                identifier_hash=identifier_hash(email, "email"),
                identifier_type="email",
                email=email,
                phone=phone,
                role="parent",
                password_hash=make_password_hash(password),
                parent=ParentProfile(full_name=name),
            )
            db.session.add(user)
            db.session.flush()
            audit("parent_registered", "parent", user.id, "Parent account created", actor=user)
            db.session.commit()
            flash("Account tayyar hai. Ab tuition request submit karein.", "success")
            login_user(user, remember=False, fresh=True)
            return redirect(url_for("main.dashboard"))
    return render_template("register_parent.html")


@bp.route("/login", methods=["GET", "POST"])
@limiter.limit("10 per 10 minutes")
def login():
    if current_user.is_authenticated:
        return redirect(url_for("main.dashboard"))
    if request.method == "POST":
        identifier = request.form.get("identifier", "").strip()
        password = request.form.get("password", "")
        kind = "cnic" if CNIC_RE.fullmatch(identifier) else "email"
        user = User.query.filter_by(identifier_hash=identifier_hash(identifier, kind)).first() if identifier else None
        if not user and identifier and "@" in identifier:
            user = User.query.filter(db.func.lower(User.email) == identifier.lower()).first()
        if not user and identifier and identifier.upper().startswith("T-"):
            tutor_prof = TutorProfile.query.filter(
                db.func.upper(TutorProfile.tutor_code) == identifier.upper()
            ).first()
            if tutor_prof:
                user = tutor_prof.user
        if not user or not user.active or not verify_password(user, password):
            flash("Login details durust nahi hain ya account disabled hai.", "error")
            return render_template("login.html"), 401
        user.last_login_at = datetime.now(timezone.utc)
        audit("login", "user", user.id, "Successful login", actor=user)
        db.session.commit()
        login_user(user, remember=False, fresh=True)
        return redirect(url_for("main.dashboard"))
    return render_template("login.html")


@bp.post("/logout")
@login_required
def logout():
    audit("logout", "user", current_user.id, "User logged out")
    db.session.commit()
    logout_user()
    flash("Aap logout ho gaye hain.", "success")
    return redirect(url_for("main.index"))


@bp.route("/forgot-password", methods=["GET", "POST"])
@limiter.limit("4 per hour")
def forgot_password():
    if request.method == "POST":
        flash(
            "Agar aapka account maujood hai, Admin aapko verified contact par secure reset process bhejega.",
            "success",
        )
        return redirect(url_for("auth.login"))
    return render_template("forgot_password.html")


def _int_or_none(value):
    try:
        n = int(value)
        return max(n, 0)
    except (TypeError, ValueError):
        return None
```

## FILE 7: `routes.py`

```python
"""Public, Parent, and Tutor workflow routes with strict zero-leakage privacy controls."""
from datetime import datetime, timezone
from pathlib import Path
from uuid import uuid4
from flask import Blueprint, abort, current_app, flash, jsonify, redirect, render_template, request, url_for
from flask_login import current_user, login_required
from sqlalchemy import text
from werkzeug.utils import secure_filename
from extensions import db, limiter
from models import (
    CatalogItem,
    LeadEvent,
    OpportunityExposure,
    Review,
    TuitionRequest,
    TutorProfile,
    VerificationDocument,
)
from services import (
    active_catalog,
    audit,
    create_request_ref,
    lead_event,
    require_roles,
)

bp = Blueprint("main", __name__)


@bp.get("/healthz")
def healthz():
    try:
        db.session.execute(text("SELECT 1"))
        return jsonify(status="ok", database="ok"), 200
    except Exception:
        db.session.rollback()
        return jsonify(status="degraded", database="error"), 503


@bp.get("/")
def index():
    areas = active_catalog("area")
    subjects = active_catalog("subject")
    available = TutorProfile.query.filter_by(verification_status="Approved").count()
    return render_template("index.html", areas=areas, subjects=subjects, tutor_count=available)


@bp.get("/privacy")
def privacy():
    return render_template("privacy.html")


@bp.get("/dashboard")
@login_required
def dashboard():
    if current_user.role == "super_admin":
        return redirect(url_for("superadmin.dashboard"))
    if current_user.role == "admin":
        return redirect(url_for("admin.dashboard"))
    if current_user.role == "tutor":
        return redirect(url_for("main.tutor_dashboard"))
    return redirect(url_for("main.parent_dashboard"))


@bp.get("/parent/dashboard")
@login_required
@require_roles("parent")
def parent_dashboard():
    requests_list = (
        TuitionRequest.query.filter_by(parent_user_id=current_user.id)
        .order_by(TuitionRequest.created_at.desc())
        .all()
    )
    return render_template("parent_dashboard.html", requests_list=requests_list)


@bp.route("/parent/requests/new", methods=["GET", "POST"])
@login_required
@require_roles("parent")
@limiter.limit("8 per hour")
def parent_request_new():
    catalogs = {kind: active_catalog(kind) for kind in ("area", "subject", "class", "mode")}
    if request.method == "POST":
        values = {}
        for key, kind in (
            ("area_id", "area"),
            ("subject_id", "subject"),
            ("class_id", "class"),
            ("mode_id", "mode"),
        ):
            raw = request.form.get(key, "")
            item = db.session.get(CatalogItem, int(raw)) if raw.isdigit() else None
            if not item or not item.active or item.kind != kind:
                flash("Form selections dobara check karein.", "error")
                return render_template("parent_request_form.html", catalogs=catalogs), 400
            values[key] = item.id
        time_pref = request.form.get("preferred_time", "").strip()[:120]
        if not time_pref:
            flash("Preferred timing zaroor likhein.", "error")
            return render_template("parent_request_form.html", catalogs=catalogs), 400
        budget_min = _positive_int(request.form.get("budget_min"))
        budget_max = _positive_int(request.form.get("budget_max"))
        if budget_min is not None and budget_max is not None and budget_min > budget_max:
            flash("Minimum budget maximum se zyada nahi ho sakta.", "error")
            return render_template("parent_request_form.html", catalogs=catalogs), 400
        gender_pref = request.form.get("tutor_gender_preference", "Any")
        if gender_pref not in {"Any", "Male", "Female"}:
            gender_pref = "Any"

        req = TuitionRequest(
            public_ref=create_request_ref(),
            parent_user_id=current_user.id,
            area_id=values["area_id"],
            subject_id=values["subject_id"],
            class_id=values["class_id"],
            mode_id=values["mode_id"],
            subarea_text=request.form.get("subarea_text", "").strip()[:120],
            tutor_gender_preference=gender_pref,
            budget_min=budget_min,
            budget_max=budget_max,
            preferred_time=time_pref,
            requirements=request.form.get("requirements", "").strip()[:500],
        )
        db.session.add(req)
        db.session.flush()
        lead_event(req, "Parent submitted request", f"Request {req.public_ref}", private=False, actor=current_user)
        audit("create_request", "tuition_request", req.public_ref, "Parent submitted a tuition request")
        db.session.commit()
        flash(f"Request submit ho gayi. Reference: {req.public_ref}", "success")
        return redirect(url_for("main.parent_dashboard"))
    return render_template("parent_request_form.html", catalogs=catalogs)


@bp.get("/parent/requests/<int:request_id>")
@login_required
@require_roles("parent")
def parent_request_detail(request_id):
    req = TuitionRequest.query.filter_by(id=request_id, parent_user_id=current_user.id).first_or_404()
    events = LeadEvent.query.filter_by(request_id=req.id, private=False).order_by(LeadEvent.created_at).all()
    review = Review.query.filter_by(request_id=req.id, parent_user_id=current_user.id).first()
    return render_template("parent_request_detail.html", req=req, events=events, review=review)


@bp.post("/parent/requests/<int:request_id>/review")
@login_required
@require_roles("parent")
@limiter.limit("5 per hour")
def parent_review(request_id):
    req = TuitionRequest.query.filter_by(id=request_id, parent_user_id=current_user.id).first_or_404()
    if req.status != "Completed" or not req.selected_tutor_id:
        abort(400)
    if Review.query.filter_by(request_id=req.id, parent_user_id=current_user.id).first():
        flash("Aap is tuition par pehle review de chuke hain.", "error")
        return redirect(url_for("main.parent_request_detail", request_id=req.id))
    try:
        rating = int(request.form.get("rating", ""))
    except ValueError:
        rating = 0
    if rating not in range(1, 6):
        abort(400)
    review = Review(
        request_id=req.id,
        tutor_id=req.selected_tutor_id,
        parent_user_id=current_user.id,
        rating=rating,
        body=request.form.get("body", "").strip()[:1000],
        status="Pending",
    )
    db.session.add(review)
    audit("submit_review", "tuition_request", req.public_ref, "Parent submitted a review")
    db.session.commit()
    flash("Review moderation ke liye submit ho gaya.", "success")
    return redirect(url_for("main.parent_request_detail", request_id=req.id))


@bp.get("/tutor/dashboard")
@login_required
@require_roles("tutor")
def tutor_dashboard():
    tutor = current_user.tutor
    if not tutor:
        abort(404)
    exposures = (
        OpportunityExposure.query.filter_by(tutor_id=tutor.id)
        .order_by(OpportunityExposure.shown_at.desc())
        .all()
        if tutor.verification_status == "Approved"
        else []
    )
    opportunities = [
        ex
        for ex in exposures
        if ex.request and ex.request.status not in {"Cancelled", "Rejected", "Completed"}
    ]
    released = [
        ex
        for ex in exposures
        if ex.request and ex.request.contact_released_at and ex.request.selected_tutor_id == tutor.id
    ]
    return render_template(
        "tutor_dashboard.html",
        tutor=tutor,
        opportunities=opportunities,
        released=released,
    )


@bp.get("/tutor/opportunities/<int:request_id>")
@login_required
@require_roles("tutor")
def tutor_opportunity(request_id):
    tutor = current_user.tutor
    if not tutor or tutor.verification_status != "Approved":
        abort(403)
    exposure = OpportunityExposure.query.filter_by(request_id=request_id, tutor_id=tutor.id).first_or_404()
    if not exposure.viewed_at:
        exposure.viewed_at = datetime.now(timezone.utc)
        lead_event(
            exposure.request,
            "Tutor viewed opportunity",
            f"Tutor {tutor.tutor_code} opened opportunity",
            actor=current_user,
        )
        audit("view_opportunity", "tuition_request", exposure.request.public_ref, "Tutor opened limited opportunity")
        db.session.commit()
    return render_template(
        "tutor_opportunity.html",
        exposure=exposure,
        req=exposure.request,
        contact_released=bool(
            exposure.request.contact_released_at and exposure.request.selected_tutor_id == tutor.id
        ),
    )


@bp.post("/tutor/opportunities/<int:request_id>/interest")
@login_required
@require_roles("tutor")
@limiter.limit("20 per hour")
def tutor_interest(request_id):
    tutor = current_user.tutor
    if not tutor or tutor.verification_status != "Approved":
        abort(403)
    exposure = OpportunityExposure.query.filter_by(request_id=request_id, tutor_id=tutor.id).first_or_404()
    if not exposure.interested_at:
        exposure.interested_at = datetime.now(timezone.utc)
        exposure.interest_note = request.form.get("note", "").strip()[:240]
        lead_event(
            exposure.request,
            "Tutor expressed interest",
            f"Tutor {tutor.tutor_code} expressed interest",
            actor=current_user,
        )
        if exposure.request.status in {"New request", "Reviewed", "Matching"}:
            exposure.request.status = "Tutor interested"
        audit(
            "tutor_interest",
            "tuition_request",
            exposure.request.public_ref,
            f"Tutor {tutor.tutor_code} interested",
        )
        db.session.commit()
        flash("Aapki interest Admin ko bhej di gayi. Parent ki private details aapko show nahi hongi.", "success")
    else:
        flash("Aap pehle hi interest bhej chuke hain.", "success")
    return redirect(url_for("main.tutor_dashboard"))


@bp.route("/tutor/profile", methods=["GET", "POST"])
@login_required
@require_roles("tutor")
def tutor_profile():
    tutor = current_user.tutor
    choices = {kind: active_catalog(kind) for kind in ("area", "subject", "class", "mode")}
    if request.method == "POST":
        ids = {int(v) for v in request.form.getlist("catalog_ids") if v.isdigit()}
        valid = CatalogItem.query.filter(CatalogItem.id.in_(ids), CatalogItem.active.is_(True)).all() if ids else []
        allowed = {i.id for group in choices.values() for i in group}
        if not tutor or any(i.id not in allowed for i in valid):
            abort(400)
        fee_min = _positive_int(request.form.get("expected_fee_min"))
        fee_max = _positive_int(request.form.get("expected_fee_max"))
        if fee_min is not None and fee_max is not None and fee_min > fee_max:
            flash("Minimum fee maximum fee se zyada nahi ho sakti.", "error")
            return render_template("tutor_profile.html", tutor=tutor, choices=choices), 400
        tutor.full_name = request.form.get("full_name", "").strip()[:120] or tutor.full_name
        gender = request.form.get("gender", tutor.gender)[:24]
        if gender in {"Prefer not to say", "Male", "Female", "Other"}:
            tutor.gender = gender
        tutor.qualification = request.form.get("qualification", "").strip()[:200]
        tutor.experience = request.form.get("experience", "").strip()[:500]
        tutor.availability = request.form.get("availability", "").strip()[:300]
        tutor.expected_fee_min = fee_min
        tutor.expected_fee_max = fee_max
        tutor.catalog = valid
        tutor.verification_status = "Pending"
        tutor.approved_at = None
        audit("update_tutor_profile", "tutor", tutor.tutor_code, "Tutor updated profile; review required")
        db.session.commit()
        flash("Profile update ho gaya; Admin dobara review karega.", "success")
        return redirect(url_for("main.tutor_dashboard"))
    return render_template("tutor_profile.html", tutor=tutor, choices=choices)


@bp.route("/tutor/documents", methods=["GET", "POST"])
@login_required
@require_roles("tutor")
@limiter.limit("10 per hour")
def tutor_documents():
    tutor = current_user.tutor
    if not tutor:
        abort(404)
    if request.method == "POST":
        kind = request.form.get("document_type", "")
        upload = request.files.get("document")
        if kind not in {"cnic_front", "cnic_back", "photo", "qualification"} or not upload or not upload.filename:
            flash("Document type aur file select karein.", "error")
            return redirect(url_for("main.tutor_documents"))
        original = secure_filename(upload.filename)[:200]
        ext = original.rsplit(".", 1)[-1].lower() if "." in original else ""
        if ext not in {"pdf", "png", "jpg", "jpeg"}:
            flash("Sirf PDF, PNG, JPG ya JPEG files upload karein.", "error")
            return redirect(url_for("main.tutor_documents"))
        data = upload.read(8 * 1024 * 1024 + 1)
        if len(data) > 8 * 1024 * 1024:
            flash("File 8 MB se chhoti honi chahiye.", "error")
            return redirect(url_for("main.tutor_documents"))
        signatures = {
            "pdf": b"%PDF-",
            "png": b"\x89PNG\r\n\x1a\n",
            "jpg": b"\xff\xd8\xff",
            "jpeg": b"\xff\xd8\xff",
        }
        if not data.startswith(signatures[ext]):
            flash("File extension aur file content match nahi karte.", "error")
            return redirect(url_for("main.tutor_documents"))
        stored = f"{uuid4().hex}.{ext}"
        path = Path(current_app.config["PRIVATE_UPLOAD_DIR"]) / stored
        path.write_bytes(data)
        doc = VerificationDocument(
            tutor_id=tutor.id,
            document_type=kind,
            original_name=original,
            stored_name=stored,
        )
        db.session.add(doc)
        if tutor.verification_status not in {"Approved", "Under Review"}:
            tutor.verification_status = "Pending"
        audit("upload_private_document", "document", kind, f"Tutor {tutor.tutor_code} uploaded {kind}")
        db.session.commit()
        flash("Document securely upload ho gaya; Admin review karega.", "success")
        return redirect(url_for("main.tutor_documents"))
    docs = (
        VerificationDocument.query.filter_by(tutor_id=tutor.id)
        .order_by(VerificationDocument.uploaded_at.desc())
        .all()
    )
    return render_template("tutor_documents.html", tutor=tutor, docs=docs)


def _positive_int(value):
    try:
        n = int(value)
        return max(0, n)
    except (ValueError, TypeError):
        return None
```

## FILE 8A: `admin_routes.py`

```python
"""Permission-checked Admin operations: lead pipeline, tutor verification, contact release, and payments."""
from datetime import datetime, timezone
import json
import os
from flask import Blueprint, abort, current_app, flash, redirect, render_template, request, send_file, url_for
from flask_login import current_user, login_required
from sqlalchemy import func, text
from werkzeug.utils import secure_filename
from extensions import db
from models import (
    AppError,
    AreaAdjacency,
    AuditEvent,
    CatalogItem,
    CommissionRule,
    LeadEvent,
    OpportunityExposure,
    PaymentEntry,
    Review,
    TuitionRequest,
    TutorProfile,
    User,
    VerificationDocument,
)
from services import (
    ADMIN_PERMISSIONS,
    LEAD_STATUSES,
    active_catalog,
    audit,
    generate_opportunities,
    has_permission,
    identifier_hash,
    lead_event,
    make_password_hash,
    password_ok,
    require_permission,
    require_roles,
    snapshot_commission_for_request,
)

bp = Blueprint("admin", __name__, url_prefix="/admin")
ALLOWED_KINDS = {"city", "area", "block", "subject", "class", "mode", "qualification"}
ALLOWED_VERIFICATION_STATUSES = {
    "Pending",
    "Under Review",
    "Manual review",
    "Approved",
    "Resubmission required",
    "Rejected",
    "Suspicious / restricted",
}


@bp.get("/dashboard")
@login_required
@require_roles("admin", "super_admin")
def dashboard():
    stats = {
        "tutors": TutorProfile.query.count(),
        "verified_tutors": TutorProfile.query.filter_by(verification_status="Approved").count(),
        "pending_verification": TutorProfile.query.filter(
            TutorProfile.verification_status.in_(
                ["Pending", "Under Review", "Manual review", "Pre-screened", "Resubmission required"]
            )
        ).count(),
        "requests": TuitionRequest.query.count(),
        "active_tuitions": TuitionRequest.query.filter_by(status="Active tuition").count(),
        "interest": OpportunityExposure.query.filter(OpportunityExposure.interested_at.is_not(None)).count(),
        "unresolved_errors": AppError.query.filter_by(resolved=False).count(),
        "commission_pkr": db.session.query(
            func.coalesce(func.sum(PaymentEntry.platform_commission_pkr), 0)
        ).scalar(),
    }
    recent = (
        TuitionRequest.query.order_by(TuitionRequest.created_at.desc()).limit(8).all()
        if has_permission(current_user, "view_requests")
        else []
    )
    return render_template("admin_dashboard.html", stats=stats, recent=recent)


@bp.get("/requests")
@login_required
@require_roles("admin", "super_admin")
@require_permission("view_requests")
def requests_list():
    rows = TuitionRequest.query.order_by(TuitionRequest.created_at.desc()).limit(200).all()
    return render_template(
        "admin_requests.html",
        requests_list=rows,
        can_contacts=has_permission(current_user, "view_contacts"),
    )


@bp.get("/requests/<int:request_id>")
@login_required
@require_roles("admin", "super_admin")
@require_permission("view_requests")
def request_detail(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    audit("view_request", "tuition_request", req.public_ref, "Admin opened lead case")
    db.session.commit()
    exposures = OpportunityExposure.query.filter_by(request_id=req.id).order_by(OpportunityExposure.shown_at).all()
    events = LeadEvent.query.filter_by(request_id=req.id).order_by(LeadEvent.created_at).all()
    payments = PaymentEntry.query.filter_by(request_id=req.id).order_by(PaymentEntry.created_at.desc()).all()
    return render_template(
        "admin_request_detail.html",
        req=req,
        exposures=exposures,
        events=events,
        payments=payments,
        can_contacts=has_permission(current_user, "view_contacts"),
        can_match=has_permission(current_user, "match_tutors"),
        can_verify=has_permission(current_user, "verify_tutors"),
        statuses=LEAD_STATUSES,
    )


@bp.post("/requests/<int:request_id>/status")
@login_required
@require_roles("admin", "super_admin")
@require_permission("edit_requests")
def change_status(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    status = request.form.get("status", "")
    if status not in LEAD_STATUSES:
        abort(400)
    old = req.status
    req.status = status
    if status in {"Confirmed", "Active tuition"}:
        snapshot_commission_for_request(req)
    reason = request.form.get("reason", "").strip()[:300]
    lead_event(req, "Status changed", f"{old} → {status}. {reason}", private=False)
    audit("change_lead_status", "tuition_request", req.public_ref, f"{old} → {status}")
    db.session.commit()
    flash("Case status update ho gaya.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/note")
@login_required
@require_roles("admin", "super_admin")
@require_permission("edit_requests")
def add_internal_note(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    note = request.form.get("note", "").strip()[:500]
    if not note:
        flash("Internal note khali hai.", "error")
    else:
        lead_event(req, "Admin internal note", note, private=True)
        audit("add_internal_note", "tuition_request", req.public_ref, "Private internal note added")
        db.session.commit()
        flash("Private note save ho gaya.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/match")
@login_required
@require_roles("admin", "super_admin")
@require_permission("match_tutors")
def match_request(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    matches = generate_opportunities(req, actor=current_user)
    flash(
        f"{len(matches)} eligible tutors ko limited opportunity show ki gayi.",
        "success" if matches else "error",
    )
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/select-tutor")
@login_required
@require_roles("admin", "super_admin")
@require_permission("match_tutors")
def select_tutor(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    raw = request.form.get("tutor_id", "")
    tutor = db.session.get(TutorProfile, int(raw)) if raw.isdigit() else None
    exposure = OpportunityExposure.query.filter_by(request_id=req.id, tutor_id=tutor.id).first() if tutor else None
    if not tutor or not exposure or not exposure.interested_at or tutor.verification_status != "Approved":
        abort(400)
    req.selected_tutor_id = tutor.id
    req.status = "Parent interested"
    snapshot_commission_for_request(req)
    lead_event(
        req,
        "Tutor shortlisted by Admin",
        f"Tutor {tutor.tutor_code} selected for platform follow-up",
        private=False,
    )
    audit("select_tutor", "tuition_request", req.public_ref, f"Selected tutor {tutor.tutor_code}")
    db.session.commit()
    flash("Tutor shortlist ho gaya. Parent se platform ke zariye confirm karein.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/release-contacts")
@login_required
@require_roles("admin", "super_admin")
@require_permission("release_contacts")
def release_contacts(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    if not req.selected_tutor_id:
        abort(400)
    if not req.contact_released_at:
        req.contact_released_at = datetime.now(timezone.utc)
        snapshot_commission_for_request(req)
        lead_event(
            req,
            "Introduction authorized",
            "Parent and selected tutor contact details released after admin authorization",
            private=False,
        )
        audit(
            "release_contacts",
            "tuition_request",
            req.public_ref,
            "Released contact details to selected tutor and parent",
        )
        db.session.commit()
    flash("Introduction authorize ho gayi; selected parties dashboard par contact details dekh sakti hain.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.get("/tutors")
@login_required
@require_roles("admin", "super_admin")
@require_permission("view_tutor_profiles")
def tutors_list():
    rows = TutorProfile.query.order_by(TutorProfile.id.desc()).limit(250).all()
    return render_template(
        "admin_tutors.html",
        tutors=rows,
        can_verify=has_permission(current_user, "verify_tutors"),
    )


@bp.get("/tutors/<int:tutor_id>")
@login_required
@require_roles("admin", "super_admin")
@require_permission("view_tutor_profiles")
def tutor_detail(tutor_id):
    tutor = db.get_or_404(TutorProfile, tutor_id)
    docs = (
        VerificationDocument.query.filter_by(tutor_id=tutor.id)
        .order_by(VerificationDocument.uploaded_at.desc())
        .all()
        if has_permission(current_user, "view_documents")
        else []
    )
    audit("view_tutor_profile", "tutor", tutor.tutor_code, "Admin opened tutor record")
    db.session.commit()
    return render_template(
        "admin_tutor_detail.html",
        tutor=tutor,
        docs=docs,
        can_verify=has_permission(current_user, "verify_tutors"),
        can_docs=has_permission(current_user, "view_documents"),
    )


@bp.post("/tutors/<int:tutor_id>/verify")
@login_required
@require_roles("admin", "super_admin")
@require_permission("verify_tutors")
def verify_tutor(tutor_id):
    tutor = db.get_or_404(TutorProfile, tutor_id)
    status = request.form.get("status", "")
    if status not in ALLOWED_VERIFICATION_STATUSES:
        abort(400)
    old = tutor.verification_status
    tutor.verification_status = status
    tutor.verification_note = request.form.get("note", "").strip()[:500]
    tutor.approved_at = datetime.now(timezone.utc) if status == "Approved" else None
    audit("tutor_verification", "tutor", tutor.tutor_code, f"{old} → {status}")
    db.session.commit()
    flash("Tutor verification status update ho gaya.", "success")
    return redirect(url_for("admin.tutor_detail", tutor_id=tutor.id))


@bp.post("/documents/<int:document_id>/review")
@login_required
@require_roles("admin", "super_admin")
@require_permission("verify_tutors")
def review_document(document_id):
    doc = db.get_or_404(VerificationDocument, document_id)
    state = request.form.get("status", "")
    if state not in {"Accepted", "Approved", "Resubmission required", "Rejected"}:
        abort(400)
    doc.review_status = state
    doc.reviewer_id = current_user.id
    doc.review_note = request.form.get("note", "").strip()[:500]
    audit("review_document", "document", doc.id, f"Set document state {state}")
    db.session.commit()
    flash("Document review update ho gayi.", "success")
    return redirect(url_for("admin.tutor_detail", tutor_id=doc.tutor_id))


@bp.route("/tutors/documents/<int:document_id>")
@login_required
@require_roles("admin", "super_admin")
@require_permission("view_documents")
def private_document(document_id):
    doc = db.get_or_404(VerificationDocument, document_id)
    path = current_app.config["PRIVATE_UPLOAD_DIR"] / doc.stored_name
    if not path.is_file():
        abort(404)
    audit("view_private_document", "document", doc.id, f"Viewed document type {doc.document_type}")
    db.session.commit()
    return send_file(path, as_attachment=True, download_name=secure_filename(doc.original_name), max_age=0)


@bp.route("/settings/catalog", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def catalog_settings():
    return redirect(url_for("superadmin.catalog_settings", code=307 if request.method == "POST" else 302))


@bp.post("/settings/adjacency/<int:link_id>/delete")
@login_required
@require_roles("super_admin")
def delete_adjacency(link_id):
    link = db.get_or_404(AreaAdjacency, link_id)
    audit("delete_area_adjacency", "area_adjacency", link.id, "Removed nearby-area relationship")
    db.session.delete(link)
    db.session.commit()
    flash("Nearby-area link remove ho gaya.", "success")
    return redirect(url_for("superadmin.catalog_settings"))


@bp.route("/settings/commission", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def commission_settings():
    return redirect(url_for("superadmin.commission_settings", code=307 if request.method == "POST" else 302))


@bp.route("/admins", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def admins():
    return redirect(url_for("superadmin.admins", code=307 if request.method == "POST" else 302))


@bp.post("/admins/<int:user_id>/permissions")
@login_required
@require_roles("super_admin")
def update_admin_permissions(user_id):
    user = db.get_or_404(User, user_id)
    if user.role != "admin":
        abort(400)
    flags = {p: request.form.get(p) == "1" for p in ADMIN_PERMISSIONS}
    user.permissions_json = json.dumps(flags)
    user.active = request.form.get("active") == "1"
    audit("update_admin_permissions", "user", user.id, "Updated admin permissions / active state")
    db.session.commit()
    flash("Admin permissions update ho gayi.", "success")
    return redirect(url_for("superadmin.admins"))


@bp.get("/users")
@login_required
@require_roles("super_admin")
def users():
    return redirect(url_for("superadmin.users"))


@bp.post("/users/<int:user_id>/active")
@login_required
@require_roles("super_admin")
def set_user_active(user_id):
    user = db.get_or_404(User, user_id)
    if user.role == "super_admin":
        abort(400)
    user.active = request.form.get("active") == "1"
    audit("set_user_active", "user", user.id, f"Set {user.role} account active={user.active}")
    db.session.commit()
    flash("Account status update ho gaya.", "success")
    return redirect(url_for("superadmin.users"))


@bp.get("/reviews")
@login_required
@require_roles("admin", "super_admin")
@require_permission("moderate_reviews")
def reviews():
    rows = Review.query.order_by(Review.created_at.desc()).limit(250).all()
    return render_template("reviews.html", reviews=rows)


@bp.post("/reviews/<int:review_id>/moderate")
@login_required
@require_roles("admin", "super_admin")
@require_permission("moderate_reviews")
def moderate_review(review_id):
    review = db.get_or_404(Review, review_id)
    status = request.form.get("status", "")
    if status not in {"Approved", "Published", "Rejected", "Hidden"}:
        abort(400)
    review.status = status
    review.moderator_id = current_user.id
    audit("moderate_review", "review", review.id, f"Set review to {status}")
    db.session.commit()
    flash("Review moderation save ho gayi.", "success")
    return redirect(url_for("admin.reviews"))


@bp.route("/requests/<int:request_id>/payment", methods=["POST"])
@login_required
@require_roles("admin", "super_admin")
@require_permission("record_payments")
def record_payment(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    try:
        amount = int(request.form.get("amount_pkr", ""))
    except ValueError:
        amount = 0
    if amount <= 0:
        flash("Payment amount positive PKR mein dein.", "error")
        return redirect(url_for("admin.request_detail", request_id=req.id))

    snapshot_commission_for_request(req)
    if req.commission_snapshot_type and req.commission_snapshot_value is not None:
        r_type = req.commission_snapshot_type
        r_val = req.commission_snapshot_value
    else:
        rule = (
            CommissionRule.query.filter_by(active=True)
            .order_by(CommissionRule.effective_at.desc())
            .first()
        )
        if not rule:
            flash("Pehle Super Admin commission rule configure kare.", "error")
            return redirect(url_for("admin.request_detail", request_id=req.id))
        r_type = "percent" if rule.rule_type in {"percent", "percentage"} else "fixed"
        r_val = rule.value
        req.commission_snapshot_type = r_type
        req.commission_snapshot_value = r_val

    commission = min(amount, r_val if r_type == "fixed" else round(amount * r_val / 100))
    method = request.form.get("method", "Cash").strip()[:32]
    if method not in {"JazzCash", "Easypaisa", "Bank Transfer", "Bank transfer", "Cash"}:
        method = "Cash"

    entry = PaymentEntry(
        request_id=req.id,
        amount_pkr=amount,
        platform_commission_pkr=commission,
        tutor_amount_pkr=amount - commission,
        method=method,
        reference=request.form.get("reference", "").strip()[:120],
        notes=request.form.get("notes", "").strip()[:500],
        recorded_by_id=current_user.id,
    )
    db.session.add(entry)
    lead_event(req, "Payment recorded", f"PKR {amount}; method {entry.method}", actor=current_user)
    audit("record_payment", "tuition_request", req.public_ref, f"Payment PKR {amount}; commission PKR {commission}")
    db.session.commit()
    flash(f"Payment ledger update: commission PKR {commission}, tutor amount PKR {amount - commission}.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.get("/audit")
@login_required
@require_roles("super_admin", "admin")
@require_permission("view_audit")
def audit_log():
    events = AuditEvent.query.order_by(AuditEvent.created_at.desc()).limit(300).all()
    return render_template("audit_log.html", events=events)


@bp.get("/health")
@login_required
@require_roles("super_admin")
def system_health():
    return redirect(url_for("superadmin.system_health"))


@bp.post("/errors/<int:error_id>/resolve")
@login_required
@require_roles("super_admin")
def resolve_error(error_id):
    item = db.get_or_404(AppError, error_id)
    item.resolved = True
    audit("resolve_error", "app_error", item.error_ref, "Marked system error resolved")
    db.session.commit()
    return redirect(url_for("superadmin.system_health"))
```

## FILE 8B: `superadmin_routes.py`

```python
"""Super Admin governance routes (/superadmin/*): catalogs, adjacency graph, commissions, RBAC, audit, health."""
import json
import os
from flask import Blueprint, abort, current_app, flash, redirect, render_template, request, url_for
from flask_login import current_user, login_required
from sqlalchemy import func, text
from extensions import db
from models import (
    AppError,
    AreaAdjacency,
    AuditEvent,
    CatalogItem,
    CommissionRule,
    OpportunityExposure,
    PaymentEntry,
    Review,
    TuitionRequest,
    TutorProfile,
    User,
)
from services import (
    ADMIN_PERMISSIONS,
    active_catalog,
    audit,
    identifier_hash,
    make_password_hash,
    password_ok,
    require_roles,
)

bp = Blueprint("superadmin", __name__, url_prefix="/superadmin")
ALLOWED_KINDS = {"city", "area", "block", "subject", "class", "mode", "qualification"}


@bp.get("/dashboard")
@login_required
@require_roles("super_admin")
def dashboard():
    stats = {
        "tutors": TutorProfile.query.count(),
        "verified_tutors": TutorProfile.query.filter_by(verification_status="Approved").count(),
        "pending_verification": TutorProfile.query.filter(
            TutorProfile.verification_status.in_(
                ["Pending", "Under Review", "Manual review", "Pre-screened", "Resubmission required"]
            )
        ).count(),
        "requests": TuitionRequest.query.count(),
        "active_tuitions": TuitionRequest.query.filter_by(status="Active tuition").count(),
        "interest": OpportunityExposure.query.filter(OpportunityExposure.interested_at.is_not(None)).count(),
        "unresolved_errors": AppError.query.filter_by(resolved=False).count(),
        "commission_pkr": db.session.query(
            func.coalesce(func.sum(PaymentEntry.platform_commission_pkr), 0)
        ).scalar(),
        "total_admins": User.query.filter_by(role="admin").count(),
        "total_users": User.query.count(),
    }
    recent = TuitionRequest.query.order_by(TuitionRequest.created_at.desc()).limit(8).all()
    return render_template("superadmin_dashboard.html", stats=stats, recent=recent)


@bp.get("/users")
@login_required
@require_roles("super_admin")
def users():
    rows = (
        User.query.filter(User.role.in_(["parent", "tutor", "admin"]))
        .order_by(User.role, User.id.desc())
        .limit(500)
        .all()
    )
    return render_template("users.html", users=rows)


@bp.post("/users/<int:user_id>/active")
@login_required
@require_roles("super_admin")
def set_user_active(user_id):
    user = db.get_or_404(User, user_id)
    if user.role == "super_admin":
        abort(400)
    user.active = request.form.get("active") == "1"
    audit("set_user_active", "user", user.id, f"Set {user.role} account active={user.active}")
    db.session.commit()
    flash("Account status update ho gaya.", "success")
    return redirect(url_for("superadmin.users"))


@bp.route("/admins", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def admins():
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        if not email or "@" not in email or not password_ok(password):
            flash("Valid email aur password policy ke mutabiq password dein.", "error")
        elif User.query.filter_by(identifier_hash=identifier_hash(email, "email")).first():
            flash("Is email par account pehle se hai.", "error")
        else:
            flags = {p: request.form.get(p) == "1" for p in ADMIN_PERMISSIONS}
            user = User(
                identifier_hash=identifier_hash(email, "email"),
                identifier_type="email",
                email=email,
                role="admin",
                password_hash=make_password_hash(password),
                permissions_json=json.dumps(flags),
            )
            db.session.add(user)
            db.session.flush()
            audit("create_admin", "user", user.id, "Created admin with configured permissions")
            db.session.commit()
            flash("Admin account bana diya gaya.", "success")
        return redirect(url_for("superadmin.admins"))
    return render_template(
        "admins.html",
        admins=User.query.filter_by(role="admin").order_by(User.id).all(),
        permissions=sorted(ADMIN_PERMISSIONS),
    )


@bp.post("/admins/<int:user_id>/permissions")
@login_required
@require_roles("super_admin")
def update_admin_permissions(user_id):
    user = db.get_or_404(User, user_id)
    if user.role != "admin":
        abort(400)
    flags = {p: request.form.get(p) == "1" for p in ADMIN_PERMISSIONS}
    user.permissions_json = json.dumps(flags)
    user.active = request.form.get("active") == "1"
    audit("update_admin_permissions", "user", user.id, "Updated admin permissions / active state")
    db.session.commit()
    flash("Admin permissions update ho gayi.", "success")
    return redirect(url_for("superadmin.admins"))


@bp.route("/settings/catalog", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def catalog_settings():
    if request.method == "POST":
        action = request.form.get("action", "create")
        if action == "create":
            kind = request.form.get("kind", "")
            name = request.form.get("name", "").strip()[:100]
            parent_raw = request.form.get("parent_id", "")
            parent = db.session.get(CatalogItem, int(parent_raw)) if parent_raw.isdigit() else None
            if kind not in ALLOWED_KINDS or not name:
                flash("Catalog type aur name check karein.", "error")
            elif parent and parent.kind not in {"city", "area"}:
                flash("Area ya block ke liye city/area parent select karein.", "error")
            else:
                db.session.add(
                    CatalogItem(
                        kind=kind,
                        name=name,
                        parent_id=parent.id if parent else None,
                        sort_order=int(request.form.get("sort_order") or 0),
                    )
                )
                audit("create_catalog_item", "catalog_item", kind, f"Created {kind}: {name}")
                db.session.commit()
                flash("Business option create ho gaya.", "success")
        elif action == "update":
            raw = request.form.get("item_id", "")
            item = db.session.get(CatalogItem, int(raw)) if raw.isdigit() else None
            if not item:
                abort(404)
            name = request.form.get("name", "").strip()[:100]
            if name:
                item.name = name
            item.active = request.form.get("active") == "1"
            audit("update_catalog_item", "catalog_item", item.id, f"Updated {item.kind} setting")
            db.session.commit()
            flash("Option update/disable ho gaya.", "success")
        elif action == "adjacency":
            a, b = request.form.get("area_id", ""), request.form.get("nearby_area_id", "")
            first = db.session.get(CatalogItem, int(a)) if a.isdigit() else None
            second = db.session.get(CatalogItem, int(b)) if b.isdigit() else None
            if not first or not second or first.kind != "area" or second.kind != "area" or first.id == second.id:
                flash("Do alag active areas choose karein.", "error")
            elif not AreaAdjacency.query.filter_by(area_id=first.id, nearby_area_id=second.id).first():
                db.session.add(
                    AreaAdjacency(
                        area_id=first.id,
                        nearby_area_id=second.id,
                        priority=max(1, min(int(request.form.get("priority") or 1), 5)),
                    )
                )
                audit("create_area_adjacency", "area_adjacency", first.id, "Nearby area linked")
                db.session.commit()
                flash("Nearby-area link add ho gaya.", "success")
        return redirect(url_for("superadmin.catalog_settings"))

    items = CatalogItem.query.order_by(CatalogItem.kind, CatalogItem.sort_order, CatalogItem.name).all()
    links = AreaAdjacency.query.order_by(AreaAdjacency.area_id).all()
    return render_template(
        "catalog_settings.html",
        items=items,
        links=links,
        kinds=sorted(ALLOWED_KINDS),
        possible_parents=CatalogItem.query.filter(
            CatalogItem.kind.in_(["city", "area"]), CatalogItem.active.is_(True)
        ).all(),
        areas=active_catalog("area"),
    )


@bp.post("/settings/adjacency/<int:link_id>/delete")
@login_required
@require_roles("super_admin")
def delete_adjacency(link_id):
    link = db.get_or_404(AreaAdjacency, link_id)
    audit("delete_area_adjacency", "area_adjacency", link.id, "Removed nearby-area relationship")
    db.session.delete(link)
    db.session.commit()
    flash("Adjacent area link delete ho gaya.", "success")
    return redirect(url_for("superadmin.catalog_settings"))


@bp.route("/settings/commission", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def commission_settings():
    if request.method == "POST":
        kind = request.form.get("rule_type", "")
        if kind not in {"fixed", "percent"}:
            abort(400)
        try:
            value = int(float(request.form.get("value", "")))
        except ValueError:
            value = -1
        if value < 0 or (kind == "percent" and value > 100):
            flash("Valid commission rate ya amount dein.", "error")
            return redirect(url_for("superadmin.commission_settings"))
        name = request.form.get("name", "").strip()[:80] or kind
        CommissionRule.query.update({"active": False})
        rule = CommissionRule(rule_type=kind, value=value, name=name, active=True)
        db.session.add(rule)
        audit("create_commission_rule", "commission_rule", name, f"{kind} rule configured")
        db.session.commit()
        flash("Commission rule save ho gaya. Naye placements is rule ko snapshot karenge.", "success")
        return redirect(url_for("superadmin.commission_settings"))
    return render_template(
        "commission_settings.html",
        rules=CommissionRule.query.order_by(CommissionRule.effective_at.desc()).all(),
    )


@bp.get("/audit")
@login_required
@require_roles("super_admin")
def audit_log():
    events = AuditEvent.query.order_by(AuditEvent.created_at.desc()).limit(300).all()
    return render_template("audit_log.html", events=events)


@bp.get("/health")
@login_required
@require_roles("super_admin")
def system_health():
    checks = {}
    try:
        db.session.execute(text("SELECT 1"))
        checks["Database"] = "Online"
    except Exception:
        db.session.rollback()
        checks["Database"] = "Error"
    folder = current_app.config["PRIVATE_UPLOAD_DIR"]
    checks["Private storage"] = (
        "Writable" if folder.exists() and os.access(folder, os.W_OK) else "Unavailable"
    )
    checks["Spatial Engine"] = "AreaAdjacency Graph (Zero External Map APIs)"
    errors = AppError.query.order_by(AppError.created_at.desc()).limit(100).all()
    return render_template("system_health.html", checks=checks, errors=errors)


@bp.post("/errors/<int:error_id>/resolve")
@login_required
@require_roles("super_admin")
def resolve_error(error_id):
    item = db.get_or_404(AppError, error_id)
    item.resolved = True
    audit("resolve_error", "app_error", item.error_ref, "Marked system error resolved")
    db.session.commit()
    return redirect(url_for("superadmin.system_health"))


@bp.get("/reviews")
@login_required
@require_roles("super_admin")
def reviews():
    rows = Review.query.order_by(Review.created_at.desc()).limit(250).all()
    return render_template("reviews.html", reviews=rows)


@bp.post("/reviews/<int:review_id>/moderate")
@login_required
@require_roles("super_admin")
def moderate_review(review_id):
    review = db.get_or_404(Review, review_id)
    status = request.form.get("status", "")
    if status not in {"Approved", "Published", "Rejected", "Hidden"}:
        abort(400)
    review.status = status
    review.moderator_id = current_user.id
    audit("moderate_review", "review", review.id, f"Set review to {status}")
    db.session.commit()
    flash("Review moderation save ho gayi.", "success")
    return redirect(url_for("superadmin.reviews"))
```

## FILE 9: `app.py`

```python
"""Flask application factory, CSRF enforcement, security headers, error handlers, and CLI."""
import secrets
from flask import Flask, abort, flash, redirect, render_template, request, session, url_for
from flask_login import current_user
from config import Config
from extensions import db, limiter, login_manager, migrate
from models import AppError, User
from services import has_permission, identifier_hash, make_password_hash, password_ok


def create_app(test_config=None):
    app = Flask(__name__, instance_relative_config=True)
    app.config.from_object(Config)
    if test_config:
        app.config.update(test_config)
    app.config["PRIVATE_UPLOAD_DIR"].mkdir(parents=True, exist_ok=True)
    db.init_app(app)
    login_manager.init_app(app)
    login_manager.login_view = "auth.login"
    login_manager.login_message = "Pehle login karein."
    migrate.init_app(app, db)
    limiter.init_app(app)

    from auth import bp as auth_bp
    from routes import bp as main_bp
    from admin_routes import bp as admin_bp
    from superadmin_routes import bp as superadmin_bp

    app.register_blueprint(auth_bp)
    app.register_blueprint(main_bp)
    app.register_blueprint(admin_bp)
    app.register_blueprint(superadmin_bp)

    @login_manager.user_loader
    def load_user(user_id):
        return db.session.get(User, int(user_id))

    @app.before_request
    def protect_state_changes():
        if request.method not in {"POST", "PUT", "PATCH", "DELETE"}:
            return
        if app.config.get("WTF_CSRF_ENABLED") is False:
            return
        expected = session.get("csrf_token", "")
        supplied = request.form.get("csrf_token", "") or request.headers.get("X-CSRF-Token", "")
        if not expected or not supplied or not secrets.compare_digest(expected, supplied):
            abort(400, description="Security token expired. Page refresh karke dobara try karein.")

    @app.context_processor
    def inject_template_helpers():
        def csrf_token():
            if "csrf_token" not in session:
                session["csrf_token"] = secrets.token_urlsafe(32)
            return session["csrf_token"]

        return {
            "csrf_token": csrf_token,
            "current_user": current_user,
            "has_permission": has_permission,
        }

    @app.after_request
    def security_headers(response):
        response.headers.setdefault("X-Content-Type-Options", "nosniff")
        response.headers.setdefault("X-Frame-Options", "DENY")
        response.headers.setdefault("Referrer-Policy", "strict-origin-when-cross-origin")
        response.headers.setdefault("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
        response.headers.setdefault(
            "Content-Security-Policy",
            "default-src 'self'; "
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            "font-src 'self' https://fonts.gstatic.com data:; "
            "script-src 'self' 'unsafe-inline'; "
            "img-src 'self' data:; "
            "object-src 'none'; "
            "base-uri 'self'; "
            "frame-ancestors 'none'",
        )
        if request.endpoint and (
            request.endpoint.startswith("admin") or request.endpoint.startswith("superadmin")
        ):
            response.headers["Cache-Control"] = "no-store"
        return response

    @app.errorhandler(403)
    def forbidden(_error):
        if current_user.is_authenticated:
            role_label = current_user.role.replace("_", " ").title()
            flash(
                f"Access Denied: Aap filhal '{role_label}' account se login hain. Doosre portal ke liye pehle Log Out karein.",
                "error",
            )
            return redirect(url_for("main.dashboard"))
        flash("Is page par jane ke liye pehle login karein.", "error")
        return redirect(url_for("auth.login", next=request.path))

    @app.errorhandler(429)
    def ratelimit_exceeded(_error):
        flash("Bohat zyada requests record hui hain. 1 minute baad dobara koshish karein.", "warning")
        return redirect(request.referrer or url_for("main.dashboard")), 429

    @app.errorhandler(404)
    def not_found(_error):
        flash("Matlooba page dastiyab nahi hai.", "error")
        if current_user.is_authenticated:
            return redirect(url_for("main.dashboard")), 404
        return redirect(url_for("main.index")), 404

    @app.errorhandler(413)
    def upload_too_large(_error):
        flash("File 8 MB se chhoti honi chahiye.", "error")
        return redirect(request.referrer or url_for("main.dashboard")), 413

    @app.errorhandler(500)
    def server_error(_error):
        db.session.rollback()
        ref = "ERR-" + secrets.token_hex(4).upper()
        try:
            db.session.add(
                AppError(
                    error_ref=ref,
                    module=request.endpoint or "application",
                    summary="Unexpected application error",
                    details="Request failed; see restricted server logs.",
                )
            )
            db.session.commit()
        except Exception:
            db.session.rollback()
        app.logger.exception("Unhandled error ref=%s endpoint=%s", ref, request.endpoint)
        return render_template("error.html", error_ref=ref), 500

    @app.cli.command("init-db")
    def init_db_command():
        """Create database tables and optional environment bootstrap Super Admin."""
        db.create_all()
        email = app.config.get("BOOTSTRAP_SUPERADMIN_EMAIL", "").strip().lower()
        password = app.config.get("BOOTSTRAP_SUPERADMIN_PASSWORD", "")
        if email and password and not User.query.filter_by(role="super_admin").first():
            if not password_ok(password):
                raise SystemExit(
                    "Bootstrap password must be 8+ characters with uppercase, lowercase, and a number."
                )
            owner = User(
                identifier_hash=identifier_hash(email, "email"),
                identifier_type="email",
                email=email,
                role="super_admin",
                password_hash=make_password_hash(password),
                permissions_json="{}",
            )
            db.session.add(owner)
            db.session.commit()
            print(f"Initial Super Admin created for {email}; remove bootstrap environment values after first start.")
        else:
            print("Database initialized. Run `python seed_burewala.py` to populate Burewala catalogs and demo accounts.")

    with app.app_context():
        db.create_all()
        email = app.config.get("BOOTSTRAP_SUPERADMIN_EMAIL", "").strip().lower()
        password = app.config.get("BOOTSTRAP_SUPERADMIN_PASSWORD", "")
        if email and password and not User.query.filter_by(role="super_admin").first():
            if password_ok(password):
                db.session.add(
                    User(
                        identifier_hash=identifier_hash(email, "email"),
                        identifier_type="email",
                        email=email,
                        role="super_admin",
                        password_hash=make_password_hash(password),
                    )
                )
                db.session.commit()
            else:
                app.logger.error("Bootstrap admin password rejected: it must satisfy the published password policy.")
    return app


app = create_app()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
```

## FILE 10: `seed_burewala.py`

```python
"""Seed Burewala catalogs, spatial adjacency graph, commission rules, and demo accounts."""
import json
from datetime import datetime, timezone
from app import create_app
from extensions import db
from models import (
    AreaAdjacency,
    CatalogItem,
    CommissionRule,
    ParentProfile,
    TutorProfile,
    User,
)
from services import ADMIN_PERMISSIONS, identifier_hash, make_password_hash

app = create_app()

with app.app_context():
    # 1. City: Burewala
    city = CatalogItem.query.filter_by(kind="city", name="Burewala").first()
    if not city:
        city = CatalogItem(kind="city", name="Burewala", sort_order=1, active=True)
        db.session.add(city)
        db.session.flush()
        print("Created City: Burewala")

    # 2. 12 Burewala Areas
    burewala_areas = [
        "Satellite Town",
        "College Road",
        "Gulshan-e-Rehman",
        "Model Town",
        "Civil Lines / Canal Colony",
        "Vehari Road",
        "Multan Road",
        "Chichawatni Road",
        "Housing Scheme #1",
        "Housing Scheme #2",
        "50/EB",
        "522/EB",
    ]
    area_objs = {}
    for idx, area_name in enumerate(burewala_areas, start=1):
        # Support matching existing "Housing Scheme # 1" if present
        alt_name = area_name.replace("#", "# ")
        item = (
            CatalogItem.query.filter_by(kind="area", name=area_name, parent_id=city.id).first()
            or CatalogItem.query.filter_by(kind="area", name=alt_name, parent_id=city.id).first()
        )
        if not item:
            item = CatalogItem(kind="area", name=area_name, parent_id=city.id, sort_order=idx, active=True)
            db.session.add(item)
            db.session.flush()
            print(f"Created Area: {area_name}")
        else:
            item.name = area_name
            item.sort_order = idx
            item.active = True
        area_objs[area_name] = item

    # 3. Spatial Adjacency Links (Priorities 1 & 2)
    adjacencies = [
        ("Satellite Town", "College Road", 1),
        ("College Road", "Satellite Town", 1),
        ("Satellite Town", "Model Town", 2),
        ("Model Town", "Satellite Town", 2),
        ("College Road", "Gulshan-e-Rehman", 1),
        ("Gulshan-e-Rehman", "College Road", 1),
        ("Model Town", "Civil Lines / Canal Colony", 1),
        ("Civil Lines / Canal Colony", "Model Town", 1),
        ("Housing Scheme #1", "Housing Scheme #2", 1),
        ("Housing Scheme #2", "Housing Scheme #1", 1),
        ("Vehari Road", "50/EB", 2),
        ("50/EB", "Vehari Road", 2),
        ("Multan Road", "522/EB", 2),
        ("522/EB", "Multan Road", 2),
    ]
    for a1_name, a2_name, prio in adjacencies:
        if a1_name in area_objs and a2_name in area_objs:
            a1_id = area_objs[a1_name].id
            a2_id = area_objs[a2_name].id
            adj = AreaAdjacency.query.filter_by(area_id=a1_id, nearby_area_id=a2_id).first()
            if not adj:
                db.session.add(AreaAdjacency(area_id=a1_id, nearby_area_id=a2_id, priority=prio))
            else:
                adj.priority = prio

    # 4. 11 Standard Academic Subjects
    subjects = [
        "Mathematics",
        "Physics",
        "Chemistry",
        "Biology",
        "English",
        "Computer Science",
        "Urdu",
        "Islamic Studies / Quran",
        "General Science",
        "Economics / Commerce",
        "Pakistan Studies",
    ]
    subj_objs = {}
    for idx, subj in enumerate(subjects, start=1):
        item = CatalogItem.query.filter_by(kind="subject", name=subj).first()
        if not item:
            item = CatalogItem(kind="subject", name=subj, sort_order=idx, active=True)
            db.session.add(item)
            db.session.flush()
            print(f"Created Subject: {subj}")
        subj_objs[subj] = item

    # 5. 10 School Classes
    classes = [
        "Primary (Class 1-5)",
        "Middle (Class 6-8)",
        "9th Class (Matric Part 1)",
        "10th Class (Matric Part 2)",
        "FSc Pre-Medical",
        "FSc Pre-Engineering",
        "ICS (Computer Science)",
        "I.Com (Commerce)",
        "O Levels",
        "A Levels",
    ]
    class_objs = {}
    for idx, cls in enumerate(classes, start=1):
        item = CatalogItem.query.filter_by(kind="class", name=cls).first()
        if not item:
            item = CatalogItem(kind="class", name=cls, sort_order=idx, active=True)
            db.session.add(item)
            db.session.flush()
            print(f"Created Class: {cls}")
        class_objs[cls] = item

    # 6. 3 Teaching Modes
    modes = [
        "Home Tuition (Tutor visits student)",
        "Home Tuition (Student visits tutor)",
        "Online Tuition",
    ]
    mode_objs = {}
    for idx, mode in enumerate(modes, start=1):
        item = CatalogItem.query.filter_by(kind="mode", name=mode).first()
        if not item:
            item = CatalogItem(kind="mode", name=mode, sort_order=idx, active=True)
            db.session.add(item)
            db.session.flush()
            print(f"Created Mode: {mode}")
        mode_objs[mode] = item

    # 7. Commission Rules (25% First Month default)
    default_rule = CommissionRule.query.filter_by(name="First Month Placement (25%)").first()
    if not default_rule:
        default_rule = CommissionRule(
            name="First Month Placement (25%)",
            rule_type="percent",
            value=25,
            active=True,
        )
        db.session.add(default_rule)
        print("Created Commission Rule: First Month Placement (25%)")
    else:
        default_rule.rule_type = "percent"
        default_rule.value = 25
        default_rule.active = True

    flat_rule = CommissionRule.query.filter_by(name="Fixed Fee PKR 2,500").first()
    if not flat_rule:
        flat_rule = CommissionRule(
            name="Fixed Fee PKR 2,500",
            rule_type="fixed",
            value=2500,
            active=False,
        )
        db.session.add(flat_rule)

    # 8. Pre-configured Demo Accounts
    # 8a. Super Admin (admin@mariahome.com / Admin123)
    sa_email = "admin@mariahome.com"
    sa_pass = "Admin123"
    sa_hash = identifier_hash(sa_email, "email")
    sa_user = User.query.filter_by(email=sa_email).first() or User.query.filter_by(role="super_admin").first()
    if not sa_user:
        sa_user = User(
            identifier_hash=sa_hash,
            identifier_type="email",
            email=sa_email,
            phone="0300-0000001",
            role="super_admin",
            password_hash=make_password_hash(sa_pass),
            active=True,
            permissions_json="{}",
        )
        db.session.add(sa_user)
        print("Created Super Admin: admin@mariahome.com / Admin123")
    else:
        sa_user.email = sa_email
        sa_user.identifier_hash = sa_hash
        sa_user.password_hash = make_password_hash(sa_pass)
        sa_user.active = True

    # 8b. Normal Admin (staff@mariahome.com / Staff123)
    admin_email = "staff@mariahome.com"
    admin_pass = "Staff123"
    admin_hash = identifier_hash(admin_email, "email")
    admin_user = User.query.filter_by(email=admin_email).first()
    full_perms = json.dumps({p: True for p in sorted(ADMIN_PERMISSIONS)})
    if not admin_user:
        admin_user = User(
            identifier_hash=admin_hash,
            identifier_type="email",
            email=admin_email,
            phone="0300-0000002",
            role="admin",
            password_hash=make_password_hash(admin_pass),
            active=True,
            permissions_json=full_perms,
        )
        db.session.add(admin_user)
        print("Created Normal Admin: staff@mariahome.com / Staff123")
    else:
        admin_user.identifier_hash = admin_hash
        admin_user.password_hash = make_password_hash(admin_pass)
        admin_user.permissions_json = full_perms
        admin_user.active = True

    # 8c. Approved Senior Tutor (tutor@mariahome.com | CNIC: 36402-1234567-1 | Code: T-BW001 | Pass: Tutor123)
    tutor_email = "tutor@mariahome.com"
    tutor_cnic = "36402-1234567-1"
    tutor_pass = "Tutor123"
    tutor_hash = identifier_hash(tutor_cnic, "cnic")
    tutor_user = User.query.filter_by(email=tutor_email).first() or User.query.filter_by(identifier_hash=tutor_hash).first()

    selected_catalog = [
        area_objs["Satellite Town"],
        area_objs["College Road"],
        area_objs["Model Town"],
        subj_objs["Mathematics"],
        subj_objs["Physics"],
        subj_objs["Computer Science"],
        class_objs["9th Class (Matric Part 1)"],
        class_objs["10th Class (Matric Part 2)"],
        class_objs["FSc Pre-Engineering"],
        *mode_objs.values(),
    ]

    if not tutor_user:
        tutor_prof = TutorProfile(
            tutor_code="T-BW001",
            full_name="Sir Usman (Senior Math & Physics Specialist)",
            gender="Male",
            cnic_gender_hint="Male",
            qualification="M.Sc Applied Mathematics (Punjab University)",
            experience="7 years teaching Matric & FSc students in Burewala",
            availability="Mon-Sat, After 4:00 PM to 8:00 PM Evening",
            expected_fee_min=10000,
            expected_fee_max=18000,
            verification_status="Approved",
            approved_at=datetime.now(timezone.utc),
            catalog=selected_catalog,
        )
        tutor_user = User(
            identifier_hash=tutor_hash,
            identifier_type="cnic",
            email=tutor_email,
            phone="0301-9876543",
            role="tutor",
            password_hash=make_password_hash(tutor_pass),
            active=True,
            tutor=tutor_prof,
        )
        db.session.add(tutor_user)
        print("Created Approved Senior Tutor: tutor@mariahome.com (CNIC: 36402-1234567-1, ID: T-BW001) / Tutor123")
    else:
        tutor_user.identifier_hash = tutor_hash
        tutor_user.email = tutor_email
        tutor_user.password_hash = make_password_hash(tutor_pass)
        tutor_user.active = True
        if tutor_user.tutor:
            tutor_user.tutor.tutor_code = "T-BW001"
            tutor_user.tutor.verification_status = "Approved"
            tutor_user.tutor.approved_at = datetime.now(timezone.utc)
            tutor_user.tutor.catalog = selected_catalog

    # 8d. Demo Parent (parent@mariahome.com / Parent123)
    parent_email = "parent@mariahome.com"
    parent_pass = "Parent123"
    parent_hash = identifier_hash(parent_email, "email")
    parent_user = User.query.filter_by(email=parent_email).first() or User.query.filter_by(identifier_hash=parent_hash).first()
    if not parent_user:
        parent_user = User(
            identifier_hash=parent_hash,
            identifier_type="email",
            email=parent_email,
            phone="0300-7654321",
            role="parent",
            password_hash=make_password_hash(parent_pass),
            active=True,
            parent=ParentProfile(full_name="Chaudhry Tariq (Parent)"),
        )
        db.session.add(parent_user)
        print("Created Demo Parent: parent@mariahome.com / Parent123")
    else:
        parent_user.identifier_hash = parent_hash
        parent_user.email = parent_email
        parent_user.password_hash = make_password_hash(parent_pass)
        parent_user.active = True
        if not parent_user.parent:
            parent_user.parent = ParentProfile(full_name="Chaudhry Tariq (Parent)")

    db.session.commit()
    print("Seeding completed successfully!")
```

## FILE 11A: `templates/base.html`

```html
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{% block title %}Maria Tutor — Burewala Tutor Marketplace{% endblock %}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="{{ url_for('static', filename='style.css') }}?v=20261014_3">
</head>
<body>

{% if current_user.is_authenticated %}
<!-- ══ AUTHENTICATED LAYOUT — Sidebar + Main ══ -->
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a class="brand-logo" href="{{ url_for('main.index') }}" style="text-decoration:none;">
        <div class="icon">🎓</div>
        <div>
          <div>Maria Tutor</div>
          <small>Burewala Marketplace</small>
        </div>
      </a>
    </div>

    <nav class="sidebar-nav">

      <!-- Common -->
      <div class="nav-section-title">Main</div>
      {% if current_user.role == 'super_admin' %}
      <a href="{{ url_for('superadmin.dashboard') }}" class="{% if request.endpoint == 'superadmin.dashboard' %}active{% endif %}">
        <span class="nav-icon">🏠</span> Dashboard
      </a>
      {% elif current_user.role == 'admin' %}
      <a href="{{ url_for('admin.dashboard') }}" class="{% if request.endpoint == 'admin.dashboard' %}active{% endif %}">
        <span class="nav-icon">🏠</span> Dashboard
      </a>
      {% elif current_user.role == 'tutor' %}
      <a href="{{ url_for('main.tutor_dashboard') }}" class="{% if request.endpoint == 'main.tutor_dashboard' %}active{% endif %}">
        <span class="nav-icon">🏠</span> Dashboard
      </a>
      {% elif current_user.role == 'parent' %}
      <a href="{{ url_for('main.parent_dashboard') }}" class="{% if request.endpoint == 'main.parent_dashboard' %}active{% endif %}">
        <span class="nav-icon">🏠</span> Dashboard
      </a>
      {% else %}
      <a href="{{ url_for('main.dashboard') }}">
        <span class="nav-icon">🏠</span> Dashboard
      </a>
      {% endif %}

      <!-- Tutor Links -->
      {% if current_user.role == 'tutor' %}
      <div class="nav-section-title">Tutor Operations</div>
      <a href="{{ url_for('main.tutor_dashboard') }}" class="{% if request.endpoint == 'main.tutor_dashboard' %}active{% endif %}">
        <span class="nav-icon">💼</span> Tuition Leads
      </a>
      <a href="{{ url_for('main.tutor_profile') }}" class="{% if request.endpoint == 'main.tutor_profile' %}active{% endif %}">
        <span class="nav-icon">👤</span> My Profile &amp; Subjects
      </a>
      <a href="{{ url_for('main.tutor_documents') }}" class="{% if request.endpoint == 'main.tutor_documents' %}active{% endif %}">
        <span class="nav-icon">📄</span> Identity Documents
      </a>
      {% endif %}

      <!-- Parent Links -->
      {% if current_user.role == 'parent' %}
      <div class="nav-section-title">Parent Operations</div>
      <a href="{{ url_for('main.parent_dashboard') }}" class="{% if request.endpoint == 'main.parent_dashboard' %}active{% endif %}">
        <span class="nav-icon">📋</span> My Tuition Requests
      </a>
      <a href="{{ url_for('main.parent_request_new') }}" class="{% if request.endpoint == 'main.parent_request_new' %}active{% endif %}">
        <span class="nav-icon">➕</span> Request a New Tutor
      </a>
      <a href="{{ url_for('main.index') }}">
        <span class="nav-icon">🔍</span> Explore Areas &amp; Tutors
      </a>
      {% endif %}

      <!-- Admin Links -->
      {% if current_user.role in ['admin','super_admin'] %}
      <div class="nav-section-title">Operations</div>
      {% if has_permission(current_user,'view_requests') %}
      <a href="{{ url_for('admin.requests_list') }}" class="{% if request.endpoint == 'admin.requests_list' %}active{% endif %}">
        <span class="nav-icon">📋</span> Requests Queue
      </a>
      {% endif %}
      {% if has_permission(current_user,'view_tutor_profiles') %}
      <a href="{{ url_for('admin.tutors_list') }}" class="{% if request.endpoint == 'admin.tutors_list' %}active{% endif %}">
        <span class="nav-icon">🎓</span> Tutors Verification
      </a>
      {% endif %}
      {% if has_permission(current_user,'moderate_reviews') and current_user.role == 'admin' %}
      <a href="{{ url_for('admin.reviews') }}" class="{% if request.endpoint == 'admin.reviews' %}active{% endif %}">
        <span class="nav-icon">⭐</span> Reviews Moderation
      </a>
      {% endif %}
      {% if has_permission(current_user,'view_audit') and current_user.role == 'admin' %}
      <a href="{{ url_for('admin.audit_log') }}" class="{% if request.endpoint == 'admin.audit_log' %}active{% endif %}">
        <span class="nav-icon">📜</span> Audit Log
      </a>
      {% endif %}

      {% if current_user.role == 'super_admin' %}
      <div class="nav-section-title">Super Admin</div>
      <a href="{{ url_for('superadmin.users') }}" class="{% if request.endpoint == 'superadmin.users' %}active{% endif %}">
        <span class="nav-icon">👥</span> Users
      </a>
      <a href="{{ url_for('superadmin.catalog_settings') }}" class="{% if request.endpoint == 'superadmin.catalog_settings' %}active{% endif %}">
        <span class="nav-icon">⚙️</span> Areas &amp; Settings
      </a>
      <a href="{{ url_for('superadmin.commission_settings') }}" class="{% if request.endpoint == 'superadmin.commission_settings' %}active{% endif %}">
        <span class="nav-icon">💰</span> Commission
      </a>
      <a href="{{ url_for('superadmin.admins') }}" class="{% if request.endpoint == 'superadmin.admins' %}active{% endif %}">
        <span class="nav-icon">🛡️</span> Admins
      </a>
      <a href="{{ url_for('superadmin.audit_log') }}" class="{% if request.endpoint == 'superadmin.audit_log' %}active{% endif %}">
        <span class="nav-icon">📜</span> Audit Log
      </a>
      <a href="{{ url_for('superadmin.system_health') }}" class="{% if request.endpoint == 'superadmin.system_health' %}active{% endif %}">
        <span class="nav-icon">💚</span> System Health
      </a>
      <a href="{{ url_for('superadmin.reviews') }}" class="{% if request.endpoint == 'superadmin.reviews' %}active{% endif %}">
        <span class="nav-icon">⭐</span> Reviews
      </a>
      {% endif %}
      {% endif %}

    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar">{{ current_user.email[0].upper() if current_user.email else (current_user.phone[0] if current_user.phone else 'U') }}</div>
        <div>
          <div class="user-name" style="max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            {{ current_user.email.split('@')[0] if current_user.email else (current_user.phone or 'User') }}
          </div>
          <div class="user-role">{{ current_user.role.replace('_',' ').title() }}</div>
        </div>
      </div>
      <form action="{{ url_for('auth.logout') }}" method="post" style="margin-top:8px;">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <button type="submit" class="btn btn-sm btn-secondary" style="width:100%; justify-content:center; padding:7px; font-size:0.82rem; background:rgba(255,255,255,0.08); color:#c7d2fe; border:1px solid rgba(255,255,255,0.15);">
          🚪 Log Out / Switch Account
        </button>
      </form>
    </div>
  </aside>

  <!-- Main Wrap -->
  <div class="main-wrap">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <span class="topbar-title">{% block page_title %}Dashboard{% endblock %}</span>
      </div>
      <div class="topbar-right" style="display:flex; align-items:center; gap:12px;">
        <span class="badge {% if current_user.role == 'super_admin' %}badge-primary{% elif current_user.role == 'admin' %}badge-info{% elif current_user.role == 'tutor' %}badge-success{% else %}badge-warning{% endif %}" style="font-size:0.8rem; padding:4px 10px;">
          Portal: {{ current_user.role|replace('_',' ')|upper }}
        </span>
        <span style="color:var(--text); font-size:0.85rem; font-weight:600;">
          {{ current_user.email or current_user.phone or 'Active User' }}
        </span>
        <form action="{{ url_for('auth.logout') }}" method="post" style="display:inline-block; margin:0;">
          <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
          <button type="submit" class="btn btn-sm" style="padding: 5px 12px; font-size: 0.8rem; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;">
            🚪 Log Out / Switch Account
          </button>
        </form>
      </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
      {% with messages = get_flashed_messages(with_categories=true) %}
        {% if messages %}
          <div class="flash-stack">
            {% for category, message in messages %}
              <div class="flash {{ category }}">{{ message }}</div>
            {% endfor %}
          </div>
        {% endif %}
      {% endwith %}
      {% block content %}{% endblock %}
    </main>

  </div><!-- /main-wrap -->
</div><!-- /layout -->

{% else %}
<!-- ══ PUBLIC PAGES — Home / Auth ══ -->
{% block public_content %}
<div class="home-layout">
  <nav class="home-nav">
    <a class="brand" href="{{ url_for('main.index') }}">Maria Tutor · <span>Burewala Marketplace</span></a>
    <div class="home-nav-links">
      <a href="{{ url_for('auth.login') }}">Log in</a>
      <a href="{{ url_for('auth.register_parent') }}">Parent signup</a>
      <a href="{{ url_for('auth.register_tutor') }}" class="btn btn-primary">Tutor signup</a>
    </div>
  </nav>
  <main style="min-height:80vh;padding:40px clamp(20px,5vw,80px);">
    {% with messages = get_flashed_messages(with_categories=true) %}
      {% if messages %}
        <div class="flash-stack">
          {% for category, message in messages %}
            <div class="flash {{ category }}">{{ message }}</div>
          {% endfor %}
        </div>
      {% endif %}
    {% endwith %}
    {% block inner_content %}{{ self.content() }}{% endblock %}
  </main>
  <footer class="home-footer">
    <span>© 2026 Maria Tutor · Burewala Tutor Marketplace · Phase 1</span> &nbsp;·&nbsp;
    <a href="{{ url_for('main.privacy') }}">Privacy &amp; Safety</a>
  </footer>
</div>
{% endblock %}
{% endif %}

</body>
</html>
```

## FILE 11B: `templates/catalog_settings.html`

```html
{% extends 'base.html' %}
{% block title %}Areas & Business Options{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div class="breadcrumb">Admin / <span>Areas & Settings</span></div>
  <h1>Areas & Business Options</h1>
  <p class="muted">Manage Burewala geographic coverage, neighboring area relationships, subjects, school classes, and teaching modes.</p>
</div>

<!-- SECTION 1: ADD NEW BUSINESS OPTION -->
<section class="panel" style="margin-bottom: 28px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Add New Business Option</h2>
      <p class="muted" style="font-size: 0.85rem;">Create a new location, academic subject, class level, or teaching mode.</p>
    </div>
  </div>
  <form method="post" action="{{ url_for('superadmin.catalog_settings' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.catalog_settings') }}" class="form-grid">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    <input type="hidden" name="action" value="create">
    
    <label>
      Type
      <select name="kind" required>
        {% for kind in kinds %}
        <option value="{{ kind }}" {% if kind == 'area' %}selected{% endif %}>{{ kind|title }}</option>
        {% endfor %}
      </select>
    </label>

    <label>
      Name / Title
      <input name="name" required maxlength="100" placeholder="e.g. Satellite Town, Biology, etc.">
    </label>

    <label>
      Parent Location (Optional)
      <select name="parent_id">
        <option value="">No parent (Top level)</option>
        {% for p in possible_parents %}
        <option value="{{ p.id }}">{{ p.kind|title }}: {{ p.name }}</option>
        {% endfor %}
      </select>
    </label>

    <label>
      Display Order
      <input type="number" name="sort_order" value="0" min="0">
    </label>

    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 42px;">
        + Add Option
      </button>
    </div>
  </form>
</section>

<!-- SECTION 2: NEARBY AREA LINKS (SPATIAL ADJACENCY) -->
<section class="panel" style="margin-bottom: 28px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Nearby Area Links (Adjacency Matrix)</h2>
      <p class="muted" style="font-size: 0.85rem;">Defines proximity connections between Burewala areas. The matching engine uses this to match tutors from adjacent areas if no exact local tutor is available.</p>
    </div>
    <span class="badge badge-info">{{ links|length }} Active Links</span>
  </div>

  <!-- Form to add adjacency -->
  <form method="post" action="{{ url_for('superadmin.catalog_settings' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.catalog_settings') }}" class="form-grid" style="background: #f8fafc; padding: 18px; border-radius: var(--radius-sm); border: 1px solid var(--border); margin-bottom: 20px;">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    <input type="hidden" name="action" value="adjacency">

    <label>
      Primary Area
      <select name="area_id" required>
        <option value="">Select Primary Area...</option>
        {% for a in areas %}
        <option value="{{ a.id }}">{{ a.name }}</option>
        {% endfor %}
      </select>
    </label>

    <label>
      Nearby / Adjacent Area
      <select name="nearby_area_id" required>
        <option value="">Select Adjacent Area...</option>
        {% for a in areas %}
        <option value="{{ a.id }}">{{ a.name }}</option>
        {% endfor %}
      </select>
    </label>

    <label>
      Proximity Priority
      <select name="priority">
        <option value="1">Priority 1 (Direct Neighbor / High)</option>
        <option value="2">Priority 2 (Nearby Zone / Medium)</option>
        <option value="3">Priority 3 (Extended Vicinity)</option>
      </select>
    </label>

    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 42px;">
        Connect Areas
      </button>
    </div>
  </form>

  <!-- Clean Data Table for Adjacency Links -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 28%;">Primary Area</th>
          <th style="width: 8%; text-align: center;">Relation</th>
          <th style="width: 28%;">Adjacent Area</th>
          <th style="width: 22%;">Matching Priority</th>
          <th style="width: 14%; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        {% for link in links %}
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--primary);"></span>
              <strong style="color: var(--text);">{{ link.area.name }}</strong>
            </div>
          </td>
          <td style="text-align: center; color: var(--muted); font-size: 1.15rem; font-weight: 700;">
            ↔
          </td>
          <td>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6366f1;"></span>
              <strong style="color: var(--text);">{{ link.nearby_area.name }}</strong>
            </div>
          </td>
          <td>
            {% if link.priority == 1 %}
              <span class="badge badge-success">Priority 1 (Direct Adjacent)</span>
            {% elif link.priority == 2 %}
              <span class="badge badge-info">Priority 2 (Nearby Zone)</span>
            {% else %}
              <span class="badge badge-gray">Priority {{ link.priority }}</span>
            {% endif %}
          </td>
          <td style="text-align: right;">
            <form method="post" action="{{ url_for('superadmin.delete_adjacency' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.delete_adjacency', link_id=link.id) }}" style="display: inline-block; margin: 0;" onsubmit="return confirm('Remove link between {{ link.area.name }} and {{ link.nearby_area.name }}?');">
              <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
              <button type="submit" class="btn btn-sm btn-danger" style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; font-size: 0.8rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Remove
              </button>
            </form>
          </td>
        </tr>
        {% else %}
        <tr>
          <td colspan="5" class="empty">
            <div style="padding: 24px; text-align: center; color: var(--muted);">
              No nearby area links configured yet. Use the form above to connect adjacent areas.
            </div>
          </td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>

<!-- SECTION 3: EXISTING BUSINESS OPTIONS -->
<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Existing Catalog Options</h2>
      <p class="muted" style="font-size: 0.85rem;">Edit names or enable/disable options. Disabled options remain preserved for past records.</p>
    </div>
    
    <!-- Filter Pills -->
    <div style="display: flex; gap: 6px; flex-wrap: wrap;" id="catalog-filter-tabs">
      <button type="button" class="btn btn-sm btn-primary filter-tab active" data-filter="all">All ({{ items|length }})</button>
      <button type="button" class="btn btn-sm btn-secondary filter-tab" data-filter="area">Areas</button>
      <button type="button" class="btn btn-sm btn-secondary filter-tab" data-filter="subject">Subjects</button>
      <button type="button" class="btn btn-sm btn-secondary filter-tab" data-filter="class">Classes</button>
      <button type="button" class="btn btn-sm btn-secondary filter-tab" data-filter="mode">Modes</button>
      <button type="button" class="btn btn-sm btn-secondary filter-tab" data-filter="city">Cities</button>
    </div>
  </div>

  <div class="table-wrap">
    <table id="catalog-table">
      <thead>
        <tr>
          <th style="width: 14%;">Type</th>
          <th style="width: 32%;">Name / Title</th>
          <th style="width: 22%;">Parent Location</th>
          <th style="width: 18%;">Status</th>
          <th style="width: 14%; text-align: right;">Save</th>
        </tr>
      </thead>
      <tbody>
        {% for item in items %}
        <tr data-kind="{{ item.kind }}">
          <td>
            <form id="form-item-{{ item.id }}" method="post" action="{{ url_for('superadmin.catalog_settings' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.catalog_settings') }}">
              <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="item_id" value="{{ item.id }}">
            </form>
            <span class="badge {% if item.kind == 'area' %}badge-info{% elif item.kind == 'subject' %}badge-success{% elif item.kind == 'class' %}badge-warning{% elif item.kind == 'city' %}badge-primary{% else %}badge-gray{% endif %}">
              {{ item.kind|upper }}
            </span>
          </td>
          <td>
            <input name="name" value="{{ item.name }}" form="form-item-{{ item.id }}" required style="padding: 7px 10px; font-size: 0.88rem; width: 100%;">
          </td>
          <td style="color: var(--muted); font-size: 0.88rem;">
            {{ item.parent.name if item.parent else '—' }}
          </td>
          <td>
            <select name="active" form="form-item-{{ item.id }}" style="padding: 7px 10px; font-size: 0.85rem; width: 100%;">
              <option value="1" {{ 'selected' if item.active else '' }}>Active</option>
              <option value="0" {{ 'selected' if not item.active else '' }}>Disabled</option>
            </select>
          </td>
          <td style="text-align: right;">
            <button type="submit" form="form-item-{{ item.id }}" class="btn btn-sm btn-primary">
              Save
            </button>
          </td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>

<!-- Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tabs = document.querySelectorAll('.filter-tab');
  const rows = document.querySelectorAll('#catalog-table tbody tr');

  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      tabs.forEach(t => {
        t.classList.remove('btn-primary', 'active');
        t.classList.add('btn-secondary');
      });
      this.classList.remove('btn-secondary');
      this.classList.add('btn-primary', 'active');

      const filter = this.getAttribute('data-filter');
      rows.forEach(row => {
        if (filter === 'all' || row.getAttribute('data-kind') === filter) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
});
</script>
{% endblock %}
```

## FILE 11C: `templates/parent_dashboard.html`

```html
{% extends 'base.html' %}
{% block title %}Parent Dashboard — Burewala Tutor Marketplace{% endblock %}
{% block page_title %}Parent Dashboard{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Parent Portal / <span>Dashboard</span></div>
    <h1>Parent Dashboard</h1>
    <p class="muted">Manage your tuition requests, track tutor matching progress, and view verified teacher introductions.</p>
  </div>
  <div class="actions">
    <a class="btn btn-primary" href="{{ url_for('main.parent_request_new') }}" style="display: inline-flex; align-items: center; gap: 6px;">
      <span>➕</span> Request a New Tutor
    </a>
  </div>
</div>

<!-- Parent Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ requests_list|length }}</div>
      <div class="stat-label">Total Requests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">⏳</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ requests_list|selectattr('status', 'in', ['New request', 'Reviewed', 'Matching', 'Tutor interested'])|list|length }}
      </div>
      <div class="stat-label">Matching in Progress</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🤝</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ requests_list|selectattr('status', 'in', ['Demo arranged', 'Confirmed', 'Active tuition'])|list|length }}
      </div>
      <div class="stat-label">Active / Placed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value">
        {{ requests_list|selectattr('status', 'equalto', 'Completed')|list|length }}
      </div>
      <div class="stat-label">Completed</div>
    </div>
  </div>
</div>

<!-- Requests Table Section -->
<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Your Tuition Requests</h2>
      <p class="muted" style="font-size: 0.85rem;">Contact details and home locations are kept private until our team authorizes an introduction.</p>
    </div>
    <a href="{{ url_for('main.parent_request_new') }}" class="btn btn-sm btn-secondary">
      + New Request
    </a>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 16%;">Reference ID</th>
          <th style="width: 26%;">Subject & Class</th>
          <th style="width: 20%;">Burewala Area</th>
          <th style="width: 18%;">Matching Status</th>
          <th style="width: 10%;">Submitted</th>
          <th style="width: 10%; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        {% for r in requests_list %}
        <tr>
          <td>
            <strong style="color: var(--primary); font-family: monospace; font-size: 0.95rem;">
              {{ r.public_ref }}
            </strong>
          </td>
          <td>
            <strong>{{ r.subject.name }}</strong>
            <small class="muted" style="display: block;">Class: {{ r.school_class.name }} · {{ r.mode.name }}</small>
          </td>
          <td>
            <span>{{ r.area.name }}</span>
            {% if r.subarea_text %}
            <small class="muted" style="display: block;">Near: {{ r.subarea_text }}</small>
            {% endif %}
          </td>
          <td>
            {% if r.status in ['Confirmed', 'Active tuition'] %}
              <span class="badge badge-success">{{ r.status }}</span>
            {% elif r.status in ['Demo arranged', 'Tutor interested'] %}
              <span class="badge badge-info">{{ r.status }}</span>
            {% elif r.status in ['Matching', 'New request', 'Reviewed'] %}
              <span class="badge badge-warning">{{ r.status }}</span>
            {% elif r.status in ['Cancelled', 'Rejected'] %}
              <span class="badge badge-danger">{{ r.status }}</span>
            {% else %}
              <span class="badge badge-gray">{{ r.status }}</span>
            {% endif %}
          </td>
          <td style="color: var(--muted); font-size: 0.82rem;">
            {{ r.created_at.strftime('%d %b %Y') }}
          </td>
          <td style="text-align: right;">
            <a href="{{ url_for('main.parent_request_detail', request_id=r.id) }}" class="btn btn-sm btn-secondary" style="padding: 5px 12px; font-size: 0.8rem;">
              View Case
            </a>
          </td>
        </tr>
        {% else %}
        <tr>
          <td colspan="6" class="empty">
            <div style="padding: 32px 16px; text-align: center;">
              <div style="font-size: 2.2rem; margin-bottom: 8px;">📚</div>
              <h3 style="font-size: 1.1rem; margin-bottom: 6px;">No Tuition Requests Yet</h3>
              <p class="muted" style="font-size: 0.88rem; max-width: 440px; margin: 0 auto 16px;">
                Submit a tuition request for any class or subject in Burewala. Our team will verify and match top qualified tutors.
              </p>
              <a href="{{ url_for('main.parent_request_new') }}" class="btn btn-primary">
                + Submit Your First Request
              </a>
            </div>
          </td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>
{% endblock %}
```

## FILE 11D: `templates/tutor_dashboard.html`

```html
{% extends 'base.html' %}
{% block title %}Tutor Dashboard — Maria Tutor Burewala{% endblock %}
{% block page_title %}Tutor Dashboard{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div>
    <div class="breadcrumb">Tutor Portal / <span>{{ tutor.tutor_code }}</span></div>
    <h1>Assalam-o-alaikum, {{ tutor.full_name }}</h1>
    <p class="muted">Review matched tuition opportunities, update your teaching availability, and track introduced students.</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="{{ url_for('main.tutor_profile') }}">✏️ Edit Profile &amp; Subjects</a>
    <a class="btn btn-secondary" href="{{ url_for('main.tutor_documents') }}">📄 Documents &amp; CNIC</a>
  </div>
</div>

<!-- Tutor Stats Grid -->
<div class="stats-grid" style="margin-bottom: 28px;">
  <div class="stat-card">
    <div class="stat-icon {% if tutor.verification_status == 'Approved' %}green{% else %}orange{% endif %}">
      {% if tutor.verification_status == 'Approved' %}✅{% else %}⏳{% endif %}
    </div>
    <div class="stat-info">
      <div class="stat-value" style="font-size: 1.15rem;">{{ tutor.verification_status }}</div>
      <div class="stat-label">Profile Status</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">💼</div>
    <div class="stat-info">
      <div class="stat-value">{{ opportunities|length }}</div>
      <div class="stat-label">Matched Opportunities</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ opportunities|selectattr('interested_at')|list|length }}</div>
      <div class="stat-label">Interests Sent</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🎓</div>
    <div class="stat-info">
      <div class="stat-value">{{ released|length }}</div>
      <div class="stat-label">Confirmed Placements</div>
    </div>
  </div>
</div>

{% if tutor.verification_status != 'Approved' %}
<div style="background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid var(--warning); border-radius: var(--radius-sm); padding: 16px 20px; margin-bottom: 24px;">
  <div style="display: flex; align-items: flex-start; gap: 12px;">
    <span style="font-size: 1.3rem;">⚠️</span>
    <div>
      <h3 style="font-size: 0.95rem; font-weight: 700; color: #92400e; margin-bottom: 2px;">Identity Verification Pending ({{ tutor.verification_status }})</h3>
      <p style="font-size: 0.88rem; color: #b45309; margin-bottom: 8px;">
        Aapka profile verification ke marhalay mein hai. Admin approval se pehle parent tuition opportunities show nahi hongi.
      </p>
      <a href="{{ url_for('main.tutor_documents') }}" class="btn btn-sm btn-warning" style="color: #fff; background: #d97706;">
        Upload CNIC &amp; Educational Documents
      </a>
    </div>
  </div>
</div>
{% endif %}

<!-- SECTION 1: CONFIRMED INTRODUCTIONS (CONTACTS RELEASED) -->
{% if released %}
<section class="panel" style="margin-bottom: 28px; border-left: 4px solid var(--success);">
  <div style="margin-bottom: 16px;">
    <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px; color: #065f46;">
      🎉 Authorized Introductions (Contacts Unlocked)
    </h2>
    <p class="muted" style="font-size: 0.85rem;">The administration has approved the introduction. You may now contact the parent directly.</p>
  </div>

  <div class="cards two">
    {% for ex in released %}
    <article class="card" style="border: 1.5px solid #d1fae5; background: #f0fdf4;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <span class="badge badge-success">Contact Authorized</span>
        <small class="muted">{{ ex.request.public_ref }}</small>
      </div>
      <h3 style="font-size: 1.05rem; margin-bottom: 6px;">{{ ex.request.subject.name }} · Class {{ ex.request.school_class.name }}</h3>
      <div style="margin: 10px 0; padding: 12px; background: #ffffff; border-radius: var(--radius-sm); border: 1px solid #e2e8f0;">
        <p style="margin-bottom: 4px;"><strong>Parent Name:</strong> {{ ex.request.parent.parent.full_name if ex.request.parent and ex.request.parent.parent else 'Parent' }}</p>
        <p style="margin-bottom: 4px;"><strong>Contact Phone:</strong> <a href="tel:{{ ex.request.parent.phone }}" style="font-weight: 700; font-size: 1rem; color: var(--primary);">{{ ex.request.parent.phone }}</a></p>
        <p style="margin-bottom: 0;"><strong>Location:</strong> {{ ex.request.area.name }} {% if ex.request.subarea_text %}({{ ex.request.subarea_text }}){% endif %}</p>
      </div>
      <p style="font-size: 0.85rem; color: var(--muted);">Timing: {{ ex.request.preferred_time }} · Mode: {{ ex.request.mode.name }}</p>
    </article>
    {% endfor %}
  </div>
</section>
{% endif %}

<!-- SECTION 2: AREA-LEVEL TUITION OPPORTUNITIES -->
<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Private Area-Level Opportunities</h2>
      <p class="muted" style="font-size: 0.85rem;">Anonymized leads matched to your selected teaching subjects and Burewala areas. Parent contact and street details are hidden prior to authorization.</p>
    </div>
    <span class="badge badge-info">{{ opportunities|length }} Matched</span>
  </div>

  <div class="cards two">
    {% for ex in opportunities %}
    <article class="card opportunity" style="display: flex; flex-direction: column; justify-content: space-between;">
      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <span class="badge {% if ex.request.status in ['New request', 'Matching'] %}badge-warning{% else %}badge-info{% endif %}">
            {{ ex.request.status }}
          </span>
          <small class="muted">{{ ex.request.public_ref }}</small>
        </div>

        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; color: var(--text);">
          {{ ex.request.subject.name }} · Class {{ ex.request.school_class.name }}
        </h3>

        <div style="font-size: 0.88rem; line-height: 1.6; margin-bottom: 14px;">
          <p>📍 <strong>Area:</strong> {{ ex.request.area.name }}, Burewala</p>
          <p>🕒 <strong>Timing:</strong> {{ ex.request.preferred_time }}</p>
          <p>📖 <strong>Mode:</strong> {{ ex.request.mode.name }}</p>
          <p>💰 <strong>Budget:</strong> 
            {% if ex.request.budget_min or ex.request.budget_max %}
              {{ ex.request.budget_min or '' }} {% if ex.request.budget_max %}– {{ ex.request.budget_max }}{% endif %} PKR / month
            {% else %}
              <span class="muted">Negotiable</span>
            {% endif %}
          </p>
        </div>
      </div>

      <div style="padding-top: 12px; border-top: 1px solid var(--border); display: flex; gap: 8px; align-items: center; justify-content: space-between;">
        {% if ex.interested_at %}
          <span class="badge badge-success" style="padding: 6px 12px;">
            ✓ Interest Submitted
          </span>
          <a class="btn btn-sm btn-secondary" href="{{ url_for('main.tutor_opportunity', request_id=ex.request_id) }}">
            Details →
          </a>
        {% else %}
          <form method="post" action="{{ url_for('main.tutor_interest', request_id=ex.request_id) }}" style="flex: 1; margin: 0;">
            <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
              I Am Interested
            </button>
          </form>
          <a class="btn btn-secondary" href="{{ url_for('main.tutor_opportunity', request_id=ex.request_id) }}">
            Details
          </a>
        {% endif %}
      </div>
    </article>
    {% else %}
    <div style="grid-column: 1 / -1; padding: 36px 16px; text-align: center;" class="empty">
      <div style="font-size: 2.2rem; margin-bottom: 8px;">🔍</div>
      {% if tutor.verification_status == 'Approved' %}
        <h3 style="font-size: 1.1rem; margin-bottom: 6px;">No New Opportunities Right Now</h3>
        <p class="muted" style="font-size: 0.88rem; max-width: 440px; margin: 0 auto 16px;">
          As soon as a parent requests a tutor in your selected subjects or areas in Burewala, it will appear here.
        </p>
        <a href="{{ url_for('main.tutor_profile') }}" class="btn btn-secondary">
          Update Subjects &amp; Availability
        </a>
      {% else %}
        <h3 style="font-size: 1.1rem; margin-bottom: 6px;">Approval Required</h3>
        <p class="muted" style="font-size: 0.88rem; max-width: 440px; margin: 0 auto 16px;">
          Once the administrator verifies your documents, relevant opportunities will appear here automatically.
        </p>
      {% endif %}
    </div>
    {% endfor %}
  </div>
</section>
{% endblock %}
```

## FILE 11E: `templates/index.html`

```html
{% extends 'base.html' %}
{% block title %}Find a Tutor in Burewala{% endblock %}

{% block public_content %}
<div class="home-layout">

  <!-- Navbar -->
  <nav class="home-nav">
    <a class="brand" href="{{ url_for('main.index') }}">Burewala <span>Tutor Link</span></a>
    <div class="home-nav-links">
      <a href="{{ url_for('auth.login') }}">Log in</a>
      <a href="{{ url_for('auth.register_parent') }}">Parent signup</a>
      <a href="{{ url_for('auth.register_tutor') }}" class="btn btn-primary">🎓 Tutor signup</a>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero">
    <p class="eyebrow" style="color:#a5b4fc;margin-bottom:12px;">BUREWALA · PUNJAB · PAKISTAN</p>
    <h1>Find the right tutor,<br><span>through a safer process.</span></h1>
    <p>Tell us the class, subject, area and timing you need. Our team reviews the request and coordinates introductions privately.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="{{ url_for('auth.register_parent') }}" style="background:#fff;color:#4f46e5;font-weight:700;">📚 Request a Tutor</a>
      <a class="btn" href="{{ url_for('auth.register_tutor') }}" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);">🎓 Join as a Tutor</a>
      <a class="btn" href="{{ url_for('auth.login') }}" style="background:transparent;color:#a5b4fc;border:1.5px solid rgba(165,180,252,.3);">Log in →</a>
    </div>
  </section>

  <!-- Stats Bar -->
  <div class="stats-bar">
    <div class="stat-item">
      <strong>{{ tutor_count }}</strong>
      <span>Approved Tutors</span>
    </div>
    <div class="stat-item">
      <strong>100%</strong>
      <span>Verified Profiles</span>
    </div>
    <div class="stat-item">
      <strong>Private</strong>
      <span>Contact Policy</span>
    </div>
    <div class="stat-item">
      <strong>Free</strong>
      <span>For Parents</span>
    </div>
  </div>

  <!-- How it Works -->
  <section class="features-section">
    <p class="eyebrow" style="text-align:center;margin-bottom:8px;">HOW IT WORKS</p>
    <h2>A platform-managed introduction</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📝</div>
        <h3>01 · Share the requirement</h3>
        <p>Choose subject, class, Burewala area, budget and preferred time. Takes less than 2 minutes.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔍</div>
        <h3>02 · We shortlist carefully</h3>
        <p>Approved tutors see only a limited area-level opportunity, then choose whether to express interest.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🤝</div>
        <h3>03 · Meet through the platform</h3>
        <p>An admin coordinates the demo and shares contact details only at the authorized stage.</p>
      </div>
    </div>
  </section>

  <!-- Coverage + Subjects -->
  <section class="features-section" style="padding-top:0;">
    <div class="split" style="gap:28px;align-items:start;">
      <div class="card">
        <div class="card-header">
          <div>
            <p class="eyebrow">COVERAGE</p>
            <h3 class="card-title">Starting in Burewala</h3>
          </div>
        </div>
        <p style="color:var(--muted);margin-bottom:16px;">Areas and subjects are managed as the service grows. Private addresses and exact home locations are never published.</p>
        {% if areas %}
          <div class="tag-row">{% for a in areas %}<span class="tag">📍 {{ a.name }}</span>{% endfor %}</div>
        {% else %}
          <p class="muted">Burewala service areas are being set up.</p>
        {% endif %}
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <p class="eyebrow">POPULAR SUBJECTS</p>
            <h3 class="card-title">Available Subjects</h3>
          </div>
        </div>
        {% if subjects %}
          <div class="tag-row">{% for s in subjects %}<span class="tag">📖 {{ s.name }}</span>{% endfor %}</div>
        {% else %}
          <p class="muted">Subjects will appear here once configured.</p>
        {% endif %}
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <h2>Ready to find the perfect tutor?</h2>
    <p>Join hundreds of families in Burewala who trust our platform.</p>
    <div class="hero-actions" style="justify-content:center;">
      <a class="btn" href="{{ url_for('auth.register_parent') }}" style="background:#fff;color:#4f46e5;font-weight:700;">Get Started Free</a>
      <a class="btn" href="{{ url_for('auth.register_tutor') }}" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);">Join as Tutor</a>
    </div>
  </section>

  <footer class="home-footer">
    <span>© 2026 Burewala Tutor Marketplace · Phase 1</span> &nbsp;·&nbsp;
    <a href="{{ url_for('main.privacy') }}">Privacy &amp; Safety</a>
  </footer>
</div>
{% endblock %}
```

## FILE 11F: `templates/login.html`

```html
{% extends 'base.html' %}
{% block title %}Log in — Burewala Tutor Marketplace{% endblock %}

{% block public_content %}
<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="logo-icon">🎓</div>
      <h1>Welcome Back</h1>
      <p>Log in to your Burewala Tutor account</p>
    </div>

    {% with messages = get_flashed_messages(with_categories=true) %}
      {% if messages %}
        <div class="flash-stack">
          {% for category, message in messages %}
            <div class="flash {{ category }}">{{ message }}</div>
          {% endfor %}
        </div>
      {% endif %}
    {% endwith %}

    <form method="post">
      <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">

      <div class="form-group">
        <label for="identifier">Username / Email</label>
        <input id="identifier" name="identifier" autocomplete="username" required
               placeholder="XXXXX-XXXXXXX-X or email@example.com">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter your password">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
        Log in →
      </button>
    </form>

    <div class="auth-links">
      <a href="{{ url_for('auth.forgot_password') }}">Forgot password?</a>
      &nbsp;·&nbsp;
      <a href="{{ url_for('auth.register_parent') }}">Register as Parent</a>
      &nbsp;·&nbsp;
      <a href="{{ url_for('auth.register_tutor') }}">Register as Tutor</a>
    </div>

    <p style="text-align:center;color:var(--muted);font-size:.78rem;margin-top:16px;">
      🔒 Tutors login with CNIC. Your CNIC is stored as a secure fingerprint only.
    </p>
  </div>
</div>
{% endblock %}
```

## FILE 11G: `templates/register_tutor.html`

```html
{% extends 'base.html' %}
{% block title %}Tutor Registration — Maria Tutor Burewala{% endblock %}
{% block content %}
<section class="form-shell wide">
  <p class="eyebrow">TUTOR / TEACHER REGISTRATION</p>
  <h1 style="margin-bottom: 6px;">Build Your Verified Tutor Profile</h1>
  <p class="muted" style="margin-bottom: 20px;">Your profile remains pending until an Admin verifies your credentials. Raw CNIC and private documents are never displayed to parents.</p>

  <form method="post">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    <div class="form-grid" style="margin-bottom: 20px;">
      <label>
        Full Name
        <input name="full_name" required maxlength="120" placeholder="e.g. Sir Usman / Madam Ayesha">
      </label>

      <label>
        CNIC Username (Pakistani Format)
        <input id="cnic-input" name="cnic" required placeholder="XXXXX-XXXXXXX-X" pattern="[0-9]{5}-[0-9]{7}-[0-9]" autocomplete="username">
        <small id="cnic-hint-msg">Example: 36402-8493837-3. Stored only as an HMAC-SHA256 fingerprint.</small>
      </label>

      <label>
        Phone / WhatsApp
        <input name="phone" required autocomplete="tel" placeholder="0300-1234567">
      </label>

      <label>
        Email (Optional)
        <input type="email" name="email" autocomplete="email" placeholder="tutor@example.com">
      </label>

      <label>
        Gender
        <select id="gender-select" name="gender">
          <option value="Prefer not to say">Prefer not to say</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
        <small>CNIC last digit odd/even provides an advisory pre-fill hint only.</small>
      </label>

      <label>
        Password
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Min 8 chars, A-Z, a-z, 0-9">
        <small>Minimum 8 characters including uppercase, lowercase, and a number.</small>
      </label>

      <label>
        Qualification
        <input name="qualification" maxlength="200" placeholder="e.g. M.Sc Mathematics, B.Ed">
      </label>

      <label>
        Availability / Usual Timings
        <input name="availability" maxlength="300" placeholder="e.g. Mon-Sat 4:00 PM to 8:00 PM">
      </label>

      <label>
        Expected Monthly Fee Minimum (PKR)
        <input type="number" name="expected_fee_min" min="0" placeholder="e.g. 8000">
      </label>

      <label>
        Expected Monthly Fee Maximum (PKR)
        <input type="number" name="expected_fee_max" min="0" placeholder="e.g. 18000">
      </label>
    </div>

    <div class="form-group">
      <label>Teaching Experience Summary</label>
      <textarea name="experience" maxlength="500" rows="3" placeholder="Briefly describe your teaching background and schools/colleges taught in Burewala..."></textarea>
    </div>

    {% for kind, label in [('area', 'Burewala Teaching Areas'), ('subject', 'Academic Subjects'), ('class', 'School / College Classes'), ('mode', 'Teaching Modes')] %}
    <fieldset style="border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 18px;">
      <legend style="font-weight: 700; padding: 0 8px; color: var(--primary);">{{ label }}</legend>
      <div class="check-grid">
        {% for item in choices if item.kind == kind %}
        <label class="check">
          <input type="checkbox" name="catalog_ids" value="{{ item.id }}"> {{ item.name }}
        </label>
        {% else %}
        <span class="muted">Options will appear when Super Admin configures them.</span>
        {% endfor %}
      </div>
    </fieldset>
    {% endfor %}

    <label class="check consent" style="display: flex; align-items: center; gap: 10px; margin: 16px 0;">
      <input type="checkbox" required>
      <span>I confirm these details are accurate and authorize Maria Tutor administration to verify my submitted documents.</span>
    </label>

    <button type="submit" class="btn btn-primary">Create Tutor Account →</button>
  </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const cnicInput = document.getElementById('cnic-input');
  const genderSelect = document.getElementById('gender-select');
  const hintMsg = document.getElementById('cnic-hint-msg');
  if (cnicInput && genderSelect) {
    cnicInput.addEventListener('input', function() {
      const val = cnicInput.value.trim();
      if (/^\d{5}-\d{7}-\d$/.test(val)) {
        const lastDigit = parseInt(val.slice(-1), 10);
        const hint = (lastDigit % 2 === 1) ? 'Male' : 'Female';
        if (genderSelect.value === 'Prefer not to say') {
          genderSelect.value = hint;
        }
        if (hintMsg) {
          hintMsg.textContent = 'Advisory CNIC hint: ' + hint + ' (last digit ' + lastDigit + '). You may adjust gender if needed.';
        }
      }
    });
  }
});
</script>
{% endblock %}
```

## FILE 11H: `templates/register_parent.html`

```html
{% extends 'base.html' %}
{% block title %}Parent Registration — Maria Tutor Burewala{% endblock %}
{% block content %}
<section class="form-shell wide" style="max-width: 640px;">
  <p class="eyebrow">PARENT / GUARDIAN</p>
  <h1 style="margin-bottom: 6px;">Create Your Parent Account</h1>
  <p class="muted" style="margin-bottom: 20px;">If the student is under 18, a parent or guardian manages the tuition request. Your phone number and exact home address remain strictly confidential prior to an authorized introduction.</p>

  <form method="post">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    <div class="form-grid" style="margin-bottom: 20px;">
      <label>
        Parent / Guardian Full Name
        <input name="full_name" required maxlength="120" placeholder="e.g. Chaudhry Tariq">
      </label>
      <label>
        Email (Login Username)
        <input type="email" name="email" required autocomplete="email" placeholder="parent@example.com">
      </label>
      <label>
        Phone / WhatsApp Number
        <input name="phone" required autocomplete="tel" placeholder="0300-7654321">
      </label>
      <label>
        Password
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Min 8 chars, A-Z, a-z, 0-9">
        <small>Minimum 8 characters, uppercase + lowercase + number.</small>
      </label>
    </div>
    <button type="submit" class="btn btn-primary">Create Parent Account →</button>
  </form>
</section>
{% endblock %}
```

## FILE 11I: `templates/parent_request_form.html`

```html
{% extends 'base.html' %}{% block title %}Request a tutor{% endblock %}{% block content %}<section class="form-shell wide"><p class="eyebrow">NEW TUITION REQUEST</p><h1>Tell us what you need</h1><p class="muted">Exact address baad mein platform ko privately diya ja sakta hai; tutor ko sirf area-level hint milega.</p><form method="post"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><div class="form-grid">
{% for key,label,kind in [('area_id','Area','area'),('subject_id','Subject','subject'),('class_id','Class','class'),('mode_id','Teaching mode','mode')] %}<label>{{ label }}<select name="{{ key }}" required><option value="">Select {{ label|lower }}</option>{% for item in catalogs[kind] %}<option value="{{ item.id }}">{{ item.name }}</option>{% endfor %}</select></label>{% endfor %}
<label>Block / nearby sub-area (optional)<input name="subarea_text" maxlength="120" placeholder="For Admin only"></label><label>Preferred timing<input name="preferred_time" required maxlength="120" placeholder="Weekdays after 5 PM"></label><label>Preferred tutor gender<select name="tutor_gender_preference"><option>Any</option><option>Female</option><option>Male</option></select></label><label>Budget minimum PKR / month<input type="number" name="budget_min" min="0"></label><label>Budget maximum PKR / month<input type="number" name="budget_max" min="0"></label><label class="span-two">Additional requirements<textarea name="requirements" maxlength="500" rows="4" placeholder="Anything the Admin should know"></textarea></label></div><p class="small muted">Keep sensitive family details out of this field. Your contact and sub-area are private and are not shown to tutors.</p><button class="button">Submit request</button></form></section>{% endblock %}
```

## FILE 11J: `templates/parent_request_detail.html`

```html
{% extends 'base.html' %}{% block title %}Request {{ req.public_ref }}{% endblock %}{% block content %}<div class="page-head"><div><p class="eyebrow">REQUEST {{ req.public_ref }}</p><h1>{{ req.subject.name }} · {{ req.school_class.name }}</h1><span class="status">{{ req.status }}</span></div><a href="{{ url_for('main.parent_dashboard') }}">Back to requests</a></div><div class="cards two"><article class="card"><h2>Requirement</h2><p><b>Area:</b> {{ req.area.name }}</p><p><b>Mode:</b> {{ req.mode.name }}</p><p><b>Timing:</b> {{ req.preferred_time }}</p><p><b>Budget:</b> {{ req.budget_min or '—' }} to {{ req.budget_max or '—' }} PKR/month</p></article><article class="card"><h2>Platform updates</h2>{% for e in events %}<p><b>{{ e.event_type }}</b><br><small>{{ e.created_at.strftime('%d %b %Y %I:%M %p') }}</small></p>{% else %}<p class="muted">Admin is reviewing the request. Private notes are not shown here.</p>{% endfor %}{% if req.contact_released_at and req.selected_tutor %}<div class="notice"><b>Authorized introduction</b><br>{{ req.selected_tutor.full_name }} · {{ req.selected_tutor.user.phone }}{% if req.selected_tutor.user.email %} · {{ req.selected_tutor.user.email }}{% endif %}</div>{% else %}<p class="small muted">Tutor identity and contact details show only after platform authorization.</p>{% endif %}</article></div>{% if req.status == 'Completed' and req.selected_tutor %}<section class="section panel"><h2>Review this tutor</h2>{% if review %}<p>Review status: {{ review.status }} · {{ review.rating }}/5</p>{% else %}<form method="post" action="{{ url_for('main.parent_review',request_id=req.id) }}"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><label>Rating<select name="rating">{% for n in range(1,6) %}<option value="{{ n }}">{{ n }} / 5</option>{% endfor %}</select></label><label>Your review<textarea name="body" maxlength="1000"></textarea></label><button>Submit for moderation</button></form>{% endif %}</section>{% endif %}{% endblock %}
```

## FILE 11K: `templates/tutor_opportunity.html`

```html
{% extends 'base.html' %}{% block title %}Tuition opportunity{% endblock %}{% block content %}<section class="form-shell"><p class="eyebrow">NEW TUITION OPPORTUNITY</p><h1>{{ req.subject.name }} · Class {{ req.school_class.name }}</h1><div class="notice privacy"><b>Privacy protected.</b> Parent/student identity, phone, WhatsApp and exact address are hidden until an admin-authorized introduction.</div><div class="detail-list"><p><b>Area:</b> {{ req.area.name }}, Burewala</p><p><b>Preferred timing:</b> {{ req.preferred_time }}</p><p><b>Teaching mode:</b> {{ req.mode.name }}</p><p><b>Expected budget:</b> {{ req.budget_min or 'Flexible' }}{% if req.budget_max %}–{{ req.budget_max }} PKR/month{% endif %}</p></div>{% if contact_released %}<div class="notice"><b>Platform-authorized introduction</b><br>Parent: {{ req.parent.parent.full_name }}<br>Contact: {{ req.parent.phone }}{% if req.parent.email %} · {{ req.parent.email }}{% endif %}</div>{% endif %}{% if not exposure.interested_at %}<form method="post" action="{{ url_for('main.tutor_interest',request_id=req.id) }}"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><label>Optional note to Admin<textarea name="note" maxlength="240" rows="2"></textarea></label><button class="button">I AM INTERESTED</button></form>{% else %}<p class="notice">Interest sent to platform on {{ exposure.interested_at.strftime('%d %b %Y %I:%M %p') }}.</p>{% endif %}<p><a href="{{ url_for('main.tutor_dashboard') }}">Back</a></p></section>{% endblock %}
```

## FILE 11L: `templates/tutor_profile.html`

```html
{% extends 'base.html' %}{% block title %}Tutor profile{% endblock %}{% block content %}<section class="form-shell wide"><p class="eyebrow">TUTOR PROFILE</p><h1>Update your teaching profile</h1><p class="muted">Profile changes set verification back to Pending so the Admin can review them.</p><form method="post"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><div class="form-grid"><label>Full name<input name="full_name" value="{{ tutor.full_name }}" required></label><label>Gender<select name="gender">{% for g in ['Prefer not to say','Male','Female','Other'] %}<option {% if tutor.gender == g %}selected{% endif %}>{{ g }}</option>{% endfor %}</select></label><label>Qualification<input name="qualification" value="{{ tutor.qualification }}"></label><label>Experience<textarea name="experience" rows="3">{{ tutor.experience }}</textarea></label><label>Availability / usual times<input name="availability" maxlength="300" value="{{ tutor.availability }}"></label><label>Fee minimum PKR<input type="number" name="expected_fee_min" value="{{ tutor.expected_fee_min or '' }}"></label><label>Fee maximum PKR<input type="number" name="expected_fee_max" value="{{ tutor.expected_fee_max or '' }}"></label></div>{% for kind,label in [('area','Teaching areas'),('subject','Subjects'),('class','Classes'),('mode','Modes')] %}<fieldset><legend>{{ label }}</legend><div class="check-grid">{% for item in choices[kind] %}<label class="check"><input type="checkbox" name="catalog_ids" value="{{ item.id }}" {% if item in tutor.catalog %}checked{% endif %}>{{ item.name }}</label>{% else %}<span class="muted">No options configured.</span>{% endfor %}</div></fieldset>{% endfor %}<button class="button">Save profile for review</button></form></section>{% endblock %}
```

## FILE 11M: `templates/tutor_documents.html`

```html
{% extends 'base.html' %}{% block title %}Tutor documents{% endblock %}{% block content %}<div class="page-head"><div><p class="eyebrow">PRIVATE DOCUMENTS</p><h1>Verification documents</h1><p class="muted">Files are stored privately and only authorized verification admins can open them.</p></div></div><section class="form-shell wide"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><div class="form-grid"><label>Document type<select name="document_type" required><option value="cnic_front">CNIC front</option><option value="cnic_back">CNIC back</option><option value="photo">Photo</option><option value="qualification">Degree / certificate</option></select></label><label>Choose file<input type="file" name="document" accept=".pdf,.png,.jpg,.jpeg" required><small>PDF, PNG, JPG or JPEG · maximum 8 MB.</small></label></div><button class="button">Upload securely</button></form></section><section class="section"><h2>Submitted files</h2><div class="table-wrap"><table><thead><tr><th>Type</th><th>File</th><th>Uploaded</th><th>Status</th><th>Admin note</th></tr></thead><tbody>{% for d in docs %}<tr><td>{{ d.document_type.replace('_',' ')|title }}</td><td>{{ d.original_name }}</td><td>{{ d.uploaded_at.strftime('%d %b %Y') }}</td><td>{{ d.review_status }}</td><td>{{ d.review_note or 'Awaiting review' }}</td></tr>{% else %}<tr><td colspan="5" class="empty">Abhi koi document upload nahi hua.</td></tr>{% endfor %}</tbody></table></div></section>{% endblock %}
```

## FILE 11N: `templates/admin_dashboard.html`

```html
{% extends 'base.html' %}
{% block title %}Admin Dashboard{% endblock %}
{% block page_title %}
  {{ 'Super Admin' if current_user.role == 'super_admin' else 'Admin' }} Dashboard
{% endblock %}

{% block content %}

<!-- Page Header -->
<div class="page-header">
  <div>
    <p class="breadcrumb">Operations / <span>Dashboard</span></p>
    <h1>{{ 'Super Admin' if current_user.role == 'super_admin' else 'Admin' }} Control Center</h1>
    <p>Operational visibility for Burewala marketplace.</p>
  </div>
  {% if current_user.role == 'super_admin' %}
  <div class="actions">
    <a href="{{ url_for('superadmin.catalog_settings') }}" class="btn btn-secondary">⚙️ Settings</a>
    <a href="{{ url_for('superadmin.system_health') }}" class="btn btn-secondary">💚 Health</a>
    <a href="{{ url_for('superadmin.audit_log') }}" class="btn btn-primary">📜 Audit Log</a>
  </div>
  {% endif %}
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon purple">🎓</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.tutors }}</div>
      <div class="stat-label">Total Tutors</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.verified_tutors }}</div>
      <div class="stat-label">Verified Tutors</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">⏳</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.pending_verification }}</div>
      <div class="stat-label">Pending Verification</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.requests }}</div>
      <div class="stat-label">Parent Requests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.interest }}</div>
      <div class="stat-label">Tutor Interests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">📚</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.active_tuitions }}</div>
      <div class="stat-label">Active Tuitions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">💰</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.commission_pkr }}</div>
      <div class="stat-label">Commission PKR</div>
    </div>
  </div>
  {% if current_user.role == 'super_admin' %}
  <div class="stat-card">
    <div class="stat-icon red">⚠️</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.unresolved_errors }}</div>
      <div class="stat-label">Open Errors</div>
    </div>
  </div>
  {% endif %}
</div>

<!-- Quick Access Cards -->
<div class="section">
  <div class="section-heading">
    <h2>Quick Actions</h2>
  </div>
  <div class="cards three">
    {% if has_permission(current_user,'view_requests') %}
    <a class="nav-card" href="{{ url_for('admin.requests_list') }}">
      <div style="font-size:2rem;margin-bottom:12px;">📋</div>
      <h3>Requests &amp; Matching</h3>
      <p>Review requests, generate eligible opportunities and see tutor interest.</p>
    </a>
    {% endif %}
    {% if has_permission(current_user,'view_tutor_profiles') %}
    <a class="nav-card" href="{{ url_for('admin.tutors_list') }}">
      <div style="font-size:2rem;margin-bottom:12px;">🎓</div>
      <h3>Tutor Verification</h3>
      <p>Review profiles and documents; record approval or resubmission.</p>
    </a>
    {% endif %}
    {% if current_user.role == 'super_admin' %}
    <a class="nav-card" href="{{ url_for('superadmin.system_health') }}">
      <div style="font-size:2rem;margin-bottom:12px;">💚</div>
      <h3>System Health</h3>
      <p>Database, private storage and recent error events.</p>
    </a>
    {% endif %}
  </div>
</div>

<!-- Recent Requests Table -->
<div class="section">
  <div class="section-heading">
    <h2>Recent Requests</h2>
    {% if has_permission(current_user,'view_requests') %}
    <a href="{{ url_for('admin.requests_list') }}" class="btn btn-secondary btn-sm">View all →</a>
    {% endif %}
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Subject &amp; Class</th>
          <th>Area</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {% for r in recent %}
        <tr>
          <td><span class="reference">{{ r.public_ref }}</span></td>
          <td><strong>{{ r.subject.name }}</strong> · {{ r.school_class.name }}</td>
          <td>📍 {{ r.area.name }}</td>
          <td><span class="badge badge-info">{{ r.status }}</span></td>
          <td><a href="{{ url_for('admin.request_detail', request_id=r.id) }}" class="btn btn-primary btn-sm">Open</a></td>
        </tr>
        {% else %}
        <tr>
          <td colspan="5" class="empty">📭 No visible requests yet.</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</div>

{% endblock %}
```

## FILE 11O: `templates/admin_requests.html`

```html
{% extends 'base.html' %}{% block title %}Requests{% endblock %}{% block content %}<div class="page-head"><div><p class="eyebrow">LEAD OPERATIONS</p><h1>Parent requests</h1><p class="muted">Request details and contact fields are permission controlled.</p></div></div><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Requirement</th><th>Area</th><th>Timing / mode</th><th>Status</th><th>Parent</th><th></th></tr></thead><tbody>{% for r in requests_list %}<tr><td>{{ r.public_ref }}</td><td>{{ r.subject.name }} · {{ r.school_class.name }}</td><td>{{ r.area.name }}{% if r.subarea_text %}<br><small>Sub-area: {{ r.subarea_text }}</small>{% endif %}</td><td>{{ r.preferred_time }}<br>{{ r.mode.name }}</td><td><span class="status">{{ r.status }}</span></td><td>{% if can_contacts %}{{ r.parent.parent.full_name }}<br>{{ r.parent.phone }}{% else %}Restricted{% endif %}</td><td><a href="{{ url_for('admin.request_detail',request_id=r.id) }}">Open case</a></td></tr>{% else %}<tr><td colspan="7" class="empty">No requests.</td></tr>{% endfor %}</tbody></table></div>{% endblock %}
```

## FILE 11P: `templates/admin_request_detail.html`

```html
{% extends 'base.html' %}
{% block title %}Case {{ req.public_ref }} — Admin Pipeline{% endblock %}
{% block page_title %}Lead Case {{ req.public_ref }}{% endblock %}
{% block content %}
<div class="page-head">
  <div>
    <p class="eyebrow">CASE {{ req.public_ref }}</p>
    <h1>{{ req.subject.name }} · {{ req.school_class.name }}</h1>
    <span class="badge badge-info">{{ req.status }}</span>
    {% if req.commission_snapshot_type %}
      <span class="badge badge-primary">Locked Commission: {{ req.commission_snapshot_value }}{% if req.commission_snapshot_type == 'percent' %}%{% else %} PKR{% endif %}</span>
    {% endif %}
  </div>
  <a class="btn btn-secondary" href="{{ url_for('admin.requests_list') }}">← Back to Requests</a>
</div>

<div class="cards two">
  <article class="card">
    <h2>Private Request Details</h2>
    <p><b>Parent:</b> {% if can_contacts %}{{ req.parent.parent.full_name if req.parent and req.parent.parent else 'Parent' }} · {{ req.parent.phone }} · {{ req.parent.email or '' }}{% else %}Contact details restricted by permission{% endif %}</p>
    <p><b>Area:</b> {{ req.area.name }}{% if req.subarea_text %} · {{ req.subarea_text }}{% endif %}, Burewala</p>
    <p><b>Mode:</b> {{ req.mode.name }} · <b>Timing:</b> {{ req.preferred_time }}</p>
    <p><b>Gender Preference:</b> {{ req.tutor_gender_preference }}</p>
    <p><b>Budget:</b> {{ req.budget_min or '—' }}–{{ req.budget_max or '—' }} PKR/month</p>
    <p><b>Requirements:</b> {{ req.requirements or '—' }}</p>
    {% if req.selected_tutor %}
      <p style="margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 8px;">
        <b>Shortlisted Tutor:</b> {{ req.selected_tutor.full_name }} ({{ req.selected_tutor.tutor_code }})
        {% if can_contacts %} · {{ req.selected_tutor.user.phone }} · {{ req.selected_tutor.user.email or '' }}{% endif %}
      </p>
    {% endif %}
  </article>

  <article class="card">
    <h2>Case Actions</h2>
    {% if has_permission(current_user,'edit_requests') %}
      <form method="post" action="{{ url_for('admin.change_status',request_id=req.id) }}" style="margin-bottom: 14px;">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <label>Workflow Status
          <select name="status">
            {% for s in statuses %}
              <option {% if req.status == s %}selected{% endif %}>{{ s }}</option>
            {% endfor %}
          </select>
        </label>
        <label>Reason / Context
          <input name="reason" maxlength="300" placeholder="Status update note">
        </label>
        <button class="btn btn-secondary" type="submit">Update Status</button>
      </form>
    {% endif %}
    {% if can_match %}
      <form class="inline-form" method="post" action="{{ url_for('admin.match_request',request_id=req.id) }}" style="margin-bottom: 14px;">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <button class="btn btn-primary" type="submit">🔍 Generate / Refresh Spatial Matches</button>
      </form>
    {% endif %}
    {% if has_permission(current_user,'edit_requests') %}
      <form method="post" action="{{ url_for('admin.add_internal_note',request_id=req.id) }}">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <label>Private Internal Note
          <textarea name="note" maxlength="500" rows="2" placeholder="Only platform staff can see this"></textarea>
        </label>
        <button class="btn btn-secondary" type="submit">Add Internal Note</button>
      </form>
    {% endif %}
  </article>
</div>

<section class="section">
  <h2>Opportunity Exposure &amp; Tutor Interest</h2>
  <p class="muted" style="margin-bottom: 12px;">Tutors see only the general Burewala area and request attributes. This table tracks who received the anonymized lead and who expressed interest.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tutor</th>
          <th>Verification</th>
          <th>Shown</th>
          <th>Viewed</th>
          <th>Interest</th>
          <th>Admin Action</th>
        </tr>
      </thead>
      <tbody>
        {% for ex in exposures %}
        <tr>
          <td>
            <strong>{{ ex.tutor.full_name }}</strong> · <code>{{ ex.tutor.tutor_code }}</code>
            {% if can_contacts %}<br><small class="muted">{{ ex.tutor.user.phone }}</small>{% endif %}
          </td>
          <td><span class="badge badge-success">{{ ex.tutor.verification_status }}</span></td>
          <td>{{ ex.shown_at.strftime('%d %b %Y %I:%M %p') }}</td>
          <td>{{ ex.viewed_at.strftime('%d %b %I:%M %p') if ex.viewed_at else 'Not viewed' }}</td>
          <td>
            {% if ex.interested_at %}
              <span class="badge badge-info">Yes · {{ ex.interested_at.strftime('%d %b %I:%M %p') }}</span>
              {% if ex.interest_note %}<br><small class="muted">"{{ ex.interest_note }}"</small>{% endif %}
            {% else %}
              No
            {% endif %}
          </td>
          <td>
            {% if can_match and ex.interested_at %}
              <form method="post" action="{{ url_for('admin.select_tutor',request_id=req.id) }}">
                <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
                <input type="hidden" name="tutor_id" value="{{ ex.tutor_id }}">
                <button class="btn btn-sm btn-primary" type="submit">Shortlist Tutor</button>
              </form>
            {% endif %}
          </td>
        </tr>
        {% else %}
        <tr><td colspan="6" class="empty">No tutor opportunities generated yet. Click "Generate / Refresh Spatial Matches" above.</td></tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>

{% if has_permission(current_user,'release_contacts') and req.selected_tutor %}
<section class="section panel" style="border-left: 4px solid var(--success);">
  <h2>Authorized Contact Release (Zero-Leakage Gate)</h2>
  {% if req.contact_released_at %}
    <p class="notice privacy">✓ Contact details released on {{ req.contact_released_at.strftime('%d %b %Y %I:%M %p') }}.</p>
  {% else %}
    <p style="margin-bottom: 12px;">Release parent and shortlisted tutor contact information only after both parties confirm the demo/introduction.</p>
    <form method="post" action="{{ url_for('admin.release_contacts',request_id=req.id) }}">
      <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
      <button class="btn btn-success" type="submit">🔓 Authorize Introduction &amp; Release Contacts</button>
    </form>
  {% endif %}
</section>
{% endif %}

{% if has_permission(current_user,'record_payments') %}
<section class="section">
  <h2>Financial Ledger &amp; Commission Collection</h2>
  <div class="form-shell wide" style="margin-bottom: 20px;">
    <form method="post" action="{{ url_for('admin.record_payment',request_id=req.id) }}">
      <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
      <div class="form-grid">
        <label>Gross Monthly Tuition Received (PKR)
          <input type="number" min="1" name="amount_pkr" required placeholder="e.g. 15000">
        </label>
        <label>Payment Channel
          <select name="method">
            <option value="JazzCash">JazzCash</option>
            <option value="Easypaisa">Easypaisa</option>
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="Cash">Cash</option>
          </select>
        </label>
        <label>Transaction Reference
          <input name="reference" maxlength="120" placeholder="TID / Receipt #">
        </label>
        <label>Notes
          <input name="notes" maxlength="500" placeholder="First month tuition settlement">
        </label>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top: 14px;">Record Payment &amp; Calculate Commission</button>
    </form>
  </div>

  {% if payments is defined and payments %}
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Channel</th>
          <th>Gross PKR</th>
          <th>Platform Commission</th>
          <th>Tutor Payout</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        {% for p in payments %}
        <tr>
          <td>{{ p.created_at.strftime('%d %b %Y') }}</td>
          <td><span class="badge badge-info">{{ p.method }}</span></td>
          <td><strong>PKR {{ p.amount_pkr }}</strong></td>
          <td>PKR {{ p.platform_commission_pkr }}</td>
          <td>PKR {{ p.tutor_amount_pkr }}</td>
          <td><code>{{ p.reference or '—' }}</code></td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
  {% endif %}
</section>
{% endif %}

<section class="section">
  <h2>Internal Activity Timeline</h2>
  <div class="timeline">
    {% for e in events %}
      <article>
        <time>{{ e.created_at.strftime('%d %b %Y %I:%M %p') }} {% if e.private %}<span class="badge badge-warning">Private Staff Note</span>{% endif %}</time>
        <b>{{ e.event_type }}</b>
        <p>{{ e.details }}</p>
      </article>
    {% else %}
      <p class="muted">No events recorded.</p>
    {% endfor %}
  </div>
</section>
{% endblock %}
```

## FILE 11Q: `templates/admin_tutors.html`

```html
{% extends 'base.html' %}{% block title %}Tutors{% endblock %}{% block content %}<div class="page-head"><div><p class="eyebrow">TUTOR SUPPLY</p><h1>Tutor verification queue</h1></div></div><div class="table-wrap"><table><thead><tr><th>Tutor</th><th>Subjects / classes</th><th>Areas</th><th>Fee range</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>{% for t in tutors %}<tr><td>{{ t.full_name }}<br><small>{{ t.tutor_code }}</small></td><td>{{ t.catalog|selectattr('kind','equalto','subject')|map(attribute='name')|join(', ') }}<br>{{ t.catalog|selectattr('kind','equalto','class')|map(attribute='name')|join(', ') }}</td><td>{{ t.catalog|selectattr('kind','equalto','area')|map(attribute='name')|join(', ') }}</td><td>{{ t.expected_fee_min or '—' }}–{{ t.expected_fee_max or '—' }} PKR</td><td><span class="status">{{ t.verification_status }}</span></td><td>{{ t.user.created_at.strftime('%d %b %Y') }}</td><td><a href="{{ url_for('admin.tutor_detail',tutor_id=t.id) }}">Review</a></td></tr>{% else %}<tr><td colspan="7" class="empty">No tutor registrations yet.</td></tr>{% endfor %}</tbody></table></div>{% endblock %}
```

## FILE 11R: `templates/admin_tutor_detail.html`

```html
{% extends 'base.html' %}
{% block title %}Tutor Review {{ tutor.tutor_code }}{% endblock %}
{% block page_title %}Tutor Verification: {{ tutor.tutor_code }}{% endblock %}
{% block content %}
<div class="page-head">
  <div>
    <p class="eyebrow">TUTOR {{ tutor.tutor_code }}</p>
    <h1>{{ tutor.full_name }}</h1>
    <span class="badge {% if tutor.verification_status == 'Approved' %}badge-success{% elif tutor.verification_status == 'Rejected' %}badge-danger{% else %}badge-warning{% endif %}">{{ tutor.verification_status }}</span>
  </div>
  <a class="btn btn-secondary" href="{{ url_for('admin.tutors_list') }}">← Back to Tutors</a>
</div>

<div class="cards two">
  <article class="card">
    <h2>Tutor Profile</h2>
    <p><b>Phone:</b> {% if has_permission(current_user,'view_contacts') %}{{ tutor.user.phone }}{% else %}Restricted{% endif %}</p>
    <p><b>Email:</b> {% if has_permission(current_user,'view_contacts') %}{{ tutor.user.email or '—' }}{% else %}Restricted{% endif %}</p>
    <p><b>Qualification:</b> {{ tutor.qualification or '—' }}</p>
    <p><b>Experience:</b> {{ tutor.experience or '—' }}</p>
    <p><b>Availability:</b> {{ tutor.availability or '—' }}</p>
    <p><b>Gender:</b> {{ tutor.gender }} · <b>CNIC Advisory Hint:</b> {{ tutor.cnic_gender_hint or '—' }}</p>
    <p><b>Selected Catalog Items:</b> {{ tutor.catalog|map(attribute='name')|join(', ') }}</p>
    <p><b>Expected Fee:</b> {{ tutor.expected_fee_min or '—' }}–{{ tutor.expected_fee_max or '—' }} PKR/month</p>
    <p><b>CNIC Security:</b> Raw CNIC is never persisted in plaintext; stored as keyed HMAC-SHA256 fingerprint.</p>
  </article>

  <article class="card">
    <h2>Verification Decision</h2>
    {% if can_verify %}
      <form method="post" action="{{ url_for('admin.verify_tutor',tutor_id=tutor.id) }}">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <label>Verification Status
          <select name="status">
            {% for s in ['Pending','Under Review','Manual review','Approved','Resubmission required','Rejected','Suspicious / restricted'] %}
              <option {% if tutor.verification_status == s %}selected{% endif %}>{{ s }}</option>
            {% endfor %}
          </select>
        </label>
        <label style="margin-top: 10px;">Private Decision Note
          <textarea name="note" maxlength="500" rows="4">{{ tutor.verification_note }}</textarea>
        </label>
        <button class="btn btn-primary" type="submit" style="margin-top: 10px;">Save Verification Decision</button>
      </form>
    {% else %}
      <p class="muted">You do not have verification permission (`verify_tutors`).</p>
    {% endif %}
    <p class="small muted" style="margin-top: 12px;">Gender hint derived from CNIC final digit is advisory only. All document inspections are strictly audit-logged.</p>
  </article>
</div>

<section class="section">
  <h2>Private Document Vault</h2>
  {% if can_docs %}
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Document Type</th>
            <th>Original Filename</th>
            <th>Uploaded</th>
            <th>Review Status</th>
            <th>Inspect &amp; Review</th>
          </tr>
        </thead>
        <tbody>
          {% for d in docs %}
          <tr>
            <td><strong>{{ d.document_type.replace('_',' ')|title }}</strong></td>
            <td>{{ d.original_name }}</td>
            <td>{{ d.uploaded_at.strftime('%d %b %Y') }}</td>
            <td>
              <span class="badge badge-info">{{ d.review_status }}</span>
              {% if d.review_note %}<br><small class="muted">{{ d.review_note }}</small>{% endif %}
            </td>
            <td>
              <a class="btn btn-sm btn-secondary" href="{{ url_for('admin.private_document',document_id=d.id) }}">📥 Download Privately</a>
              {% if can_verify %}
              <form method="post" action="{{ url_for('admin.review_document',document_id=d.id) }}" style="margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap;">
                <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
                <select name="status" style="width: auto; padding: 5px 8px;">
                  <option value="Accepted">Accepted</option>
                  <option value="Resubmission required">Resubmission required</option>
                  <option value="Rejected">Rejected</option>
                </select>
                <input name="note" placeholder="Review note" style="width: 160px; padding: 5px 8px;">
                <button class="btn btn-sm btn-primary" type="submit">Record Review</button>
              </form>
              {% endif %}
            </td>
          </tr>
          {% else %}
          <tr><td colspan="5" class="empty">No verification documents uploaded yet.</td></tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
  {% else %}
    <p class="notice">Document access permission (`view_documents`) required. Every document download is audited.</p>
  {% endif %}
</section>
{% endblock %}
```

## FILE 11S: `templates/superadmin_dashboard.html`

```html
{% extends 'base.html' %}
{% block title %}Super Admin Dashboard — Burewala Tutor Link{% endblock %}
{% block page_title %}Super Admin Control Center{% endblock %}

{% block content %}

<!-- Page Header -->
<div class="page-header">
  <div>
    <p class="breadcrumb">Super Admin / <span>Dashboard</span></p>
    <h1>Super Admin Control Center</h1>
    <p>Complete platform governance and operations for Burewala Tutor Marketplace.</p>
  </div>
  <div class="actions">
    <a href="{{ url_for('superadmin.catalog_settings') }}" class="btn btn-secondary">⚙️ Settings</a>
    <a href="{{ url_for('superadmin.admins') }}" class="btn btn-secondary">🛡️ Admins</a>
    <a href="{{ url_for('superadmin.audit_log') }}" class="btn btn-primary">📜 Audit Log</a>
  </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon purple">👥</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.total_users }}</div>
      <div class="stat-label">Total Users</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">🛡️</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.total_admins }}</div>
      <div class="stat-label">Platform Admins</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🎓</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.tutors }}</div>
      <div class="stat-label">Total Tutors</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.verified_tutors }}</div>
      <div class="stat-label">Verified Tutors</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">⏳</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.pending_verification }}</div>
      <div class="stat-label">Pending Verification</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📋</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.requests }}</div>
      <div class="stat-label">Parent Requests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🤝</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.interest }}</div>
      <div class="stat-label">Tutor Interests</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">📚</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.active_tuitions }}</div>
      <div class="stat-label">Active Tuitions</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">💰</div>
    <div class="stat-info">
      <div class="stat-value">Rs. {{ stats.commission_pkr }}</div>
      <div class="stat-label">Commission Ledger</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">⚠️</div>
    <div class="stat-info">
      <div class="stat-value">{{ stats.unresolved_errors }}</div>
      <div class="stat-label">Open Errors</div>
    </div>
  </div>
</div>

<!-- Super Admin Management Modules -->
<div class="section">
  <div class="section-heading">
    <h2>Platform Management</h2>
  </div>
  <div class="cards three">
    <a class="nav-card" href="{{ url_for('superadmin.users') }}">
      <div style="font-size:2rem;margin-bottom:12px;">👥</div>
      <h3>User Management</h3>
      <p>Manage parent, tutor, and staff accounts. Activate or suspend access.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.admins') }}">
      <div style="font-size:2rem;margin-bottom:12px;">🛡️</div>
      <h3>Admin Access &amp; Roles</h3>
      <p>Create staff admin accounts and fine-tune operational permissions.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.catalog_settings') }}">
      <div style="font-size:2rem;margin-bottom:12px;">⚙️</div>
      <h3>Areas &amp; Catalog</h3>
      <p>Configure cities, sectors, subjects, grade levels, and modes.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.commission_settings') }}">
      <div style="font-size:2rem;margin-bottom:12px;">💰</div>
      <h3>Commission Rules</h3>
      <p>Set platform commission percentage or fixed fee structures.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.audit_log') }}">
      <div style="font-size:2rem;margin-bottom:12px;">📜</div>
      <h3>Audit &amp; Security Logs</h3>
      <p>Track sensitive operations, contact reveals, and verification actions.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.system_health') }}">
      <div style="font-size:2rem;margin-bottom:12px;">💚</div>
      <h3>System Health &amp; Diagnostics</h3>
      <p>Inspect database, disk storage, error traces, and environment status.</p>
    </a>
    <a class="nav-card" href="{{ url_for('admin.requests_list') }}">
      <div style="font-size:2rem;margin-bottom:12px;">📋</div>
      <h3>Live Tuition Requests</h3>
      <p>Review parent requirements and match with qualified tutors.</p>
    </a>
    <a class="nav-card" href="{{ url_for('admin.tutors_list') }}">
      <div style="font-size:2rem;margin-bottom:12px;">🎓</div>
      <h3>Tutor Verification Queue</h3>
      <p>Review submitted CNIC and academic credentials for approval.</p>
    </a>
    <a class="nav-card" href="{{ url_for('superadmin.reviews') }}">
      <div style="font-size:2rem;margin-bottom:12px;">⭐</div>
      <h3>Review Moderation</h3>
      <p>Approve, reject, or hide parent feedback and ratings.</p>
    </a>
  </div>
</div>

<!-- Recent Requests -->
<div class="section">
  <div class="section-heading">
    <h2>Recent Tuition Requests</h2>
    <a href="{{ url_for('admin.requests_list') }}" class="btn btn-secondary btn-sm">View all →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Subject &amp; Class</th>
          <th>Area</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {% for r in recent %}
        <tr>
          <td><span class="reference">{{ r.public_ref }}</span></td>
          <td><strong>{{ r.subject.name }}</strong> · {{ r.school_class.name }}</td>
          <td>📍 {{ r.area.name }}</td>
          <td><span class="badge badge-info">{{ r.status }}</span></td>
          <td><a href="{{ url_for('admin.request_detail', request_id=r.id) }}" class="btn btn-primary btn-sm">Open</a></td>
        </tr>
        {% else %}
        <tr>
          <td colspan="5" class="empty">📭 No visible requests yet.</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</div>

{% endblock %}
```

## FILE 11T: `templates/commission_settings.html`

```html
{% extends 'base.html' %}
{% block title %}Commission Rules{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div class="breadcrumb">Admin / <span>Commission Rules</span></div>
  <h1>Commission Rules & Pricing</h1>
  <p class="muted">Configure platform placement commissions for confirmed home and online tuitions in Burewala.</p>
</div>

<section class="panel" style="margin-bottom: 28px;">
  <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 6px;">Set Active Commission Rule</h2>
  <p class="muted" style="font-size: 0.85rem; margin-bottom: 16px;">New placements will lock in this rule at the time of confirmation. Historical placement calculations are preserved.</p>
  
  <form method="post" class="form-grid">
    <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
    
    <label>
      Rule Name
      <input name="name" required placeholder="e.g. First Month 25%, Fixed Placement Fee">
    </label>
    
    <label>
      Calculation Type
      <select name="rule_type">
        <option value="percent">Percentage (%)</option>
        <option value="fixed">Fixed Amount (PKR)</option>
      </select>
    </label>
    
    <label>
      Value
      <input type="number" min="0" name="value" required placeholder="e.g. 25 for percent or 2500 for PKR">
    </label>
    
    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 42px;">
        Save Active Rule
      </button>
    </div>
  </form>
</section>

<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Commission Rules History</h2>
      <p class="muted" style="font-size: 0.85rem;">All active and historical rule versions.</p>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Rule Name</th>
          <th>Rate / Calculation</th>
          <th>Status</th>
          <th>Effective Since</th>
        </tr>
      </thead>
      <tbody>
        {% for r in rules %}
        <tr>
          <td><strong>{{ r.name }}</strong></td>
          <td>
            <span class="badge badge-info">
              {{ r.value }}{% if r.rule_type == 'percent' or r.rule_type == 'percentage' %}%{% else %} PKR{% endif %}
            </span>
          </td>
          <td>
            {% if r.active %}
              <span class="badge badge-success">Active Rule</span>
            {% else %}
              <span class="badge badge-gray">Archived</span>
            {% endif %}
          </td>
          <td style="color: var(--muted); font-size: 0.85rem;">
            {{ r.effective_at.strftime('%Y-%m-%d %H:%M UTC') }}
          </td>
        </tr>
        {% else %}
        <tr>
          <td colspan="4" class="empty">No commission rules recorded yet.</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>
{% endblock %}
```

## FILE 11U: `templates/admins.html`

```html
{% extends 'base.html' %}{% block content %}<h1>Admin accounts & permissions</h1><section class="panel"><h2>Create admin</h2><form method="post"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><label>Email<input type="email" name="email" required></label><label>Temporary password<input type="password" name="password" minlength="8" required></label><div class="check-grid">{% for p in permissions %}<label><input type="checkbox" name="{{ p }}" value="1"> {{ p.replace('_',' ') }}</label>{% endfor %}</div><button>Create admin</button></form></section>{% for a in admins %}<section class="panel"><h2>{{ a.email }} · {{ 'Active' if a.active else 'Disabled' }}</h2><form method="post" action="{{ url_for('superadmin.update_admin_permissions' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.update_admin_permissions',user_id=a.id) }}"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><div class="check-grid">{% for p in permissions %}<label><input type="checkbox" name="{{ p }}" value="1" {{ 'checked' if has_permission(a,p) else '' }}> {{ p.replace('_',' ') }}</label>{% endfor %}</div><label><input type="checkbox" name="active" value="1" {{ 'checked' if a.active else '' }}> Account active</label><button>Save permissions</button></form></section>{% endfor %}{% endblock %}
```

## FILE 11V: `templates/users.html`

```html
{% extends 'base.html' %}
{% block title %}User Accounts{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div class="breadcrumb">Admin / <span>User Management</span></div>
  <h1>User Accounts & Access</h1>
  <p class="muted">Disable accounts when necessary to maintain historical audit and payment integrity. Permanent deletion is restricted.</p>
</div>

<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Registered Users</h2>
      <p class="muted" style="font-size: 0.85rem;">All tutors, parents, and administrative staff registered on the marketplace.</p>
    </div>
    <span class="badge badge-info">{{ users|length }} Total Users</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 14%;">Role</th>
          <th style="width: 32%;">User / Profile</th>
          <th style="width: 18%;">Contact Phone</th>
          <th style="width: 14%;">Registered</th>
          <th style="width: 10%;">State</th>
          <th style="width: 12%; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        {% for u in users %}
        <tr>
          <td>
            <span class="badge {% if u.role == 'super_admin' %}badge-primary{% elif u.role == 'admin' %}badge-info{% elif u.role == 'tutor' %}badge-success{% else %}badge-warning{% endif %}">
              {{ u.role|replace('_', ' ')|upper }}
            </span>
          </td>
          <td>
            {% if u.tutor %}
              <strong>{{ u.tutor.full_name }}</strong>
              <small class="muted" style="display: block; font-family: monospace;">{{ u.tutor.tutor_code }}</small>
            {% elif u.parent %}
              <strong>{{ u.parent.full_name }}</strong>
              <small class="muted" style="display: block;">Parent Account</small>
            {% else %}
              <strong>{{ u.email or 'Administrator' }}</strong>
            {% endif %}
            <small class="muted" style="display: block; font-size: 0.8rem;">{{ u.email or 'Email not provided' }}</small>
          </td>
          <td style="color: var(--text); font-size: 0.88rem;">
            {{ u.phone or '—' }}
          </td>
          <td style="color: var(--muted); font-size: 0.85rem;">
            {{ u.created_at.strftime('%Y-%m-%d') }}
          </td>
          <td>
            {% if u.active %}
              <span class="badge badge-success">Active</span>
            {% else %}
              <span class="badge badge-danger">Disabled</span>
            {% endif %}
          </td>
          <td style="text-align: right;">
            {% if u.role != 'super_admin' %}
            <form method="post" action="{{ url_for('superadmin.set_user_active' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.set_user_active', user_id=u.id) }}" style="display: inline-block; margin: 0;">
              <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
              <input type="hidden" name="active" value="{{ '0' if u.active else '1' }}">
              <button type="submit" class="btn btn-sm {{ 'btn-danger' if u.active else 'btn-success' }}" style="padding: 5px 12px; font-size: 0.8rem;">
                {{ 'Disable' if u.active else 'Activate' }}
              </button>
            </form>
            {% else %}
            <small class="muted">Owner</small>
            {% endif %}
          </td>
        </tr>
        {% else %}
        <tr>
          <td colspan="6" class="empty">No registered user accounts found.</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>
{% endblock %}
```

## FILE 11W: `templates/audit_log.html`

```html
{% extends 'base.html' %}
{% block title %}Audit Log{% endblock %}

{% block content %}
<div class="page-header" style="margin-bottom: 24px;">
  <div class="breadcrumb">Admin / <span>Security & Audit</span></div>
  <h1>System Audit Log</h1>
  <p class="muted">Tamper-evident record of administrative actions, verification decisions, contact reveals, and system events.</p>
</div>

<section class="panel">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <div>
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 4px;">Recent Events</h2>
      <p class="muted" style="font-size: 0.85rem;">Showing chronological action history (newest first).</p>
    </div>
    <span class="badge badge-info">{{ events|length }} Recorded Events</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width: 18%;">Timestamp</th>
          <th style="width: 22%;">Actor Account</th>
          <th style="width: 18%;">Action</th>
          <th style="width: 16%;">Resource</th>
          <th style="width: 26%;">Event Summary</th>
        </tr>
      </thead>
      <tbody>
        {% for e in events %}
        <tr>
          <td style="color: var(--muted); font-size: 0.82rem; white-space: nowrap;">
            {{ e.created_at.strftime('%Y-%m-%d %H:%M:%S') }}
          </td>
          <td>
            {% if e.actor %}
              <strong>{{ e.actor.email or e.actor.phone or 'User #' ~ e.actor.id }}</strong>
              <small class="muted" style="display: block; font-size: 0.75rem;">{{ e.actor.role|title }}</small>
            {% else %}
              <span class="badge badge-gray">System Automated</span>
            {% endif %}
          </td>
          <td>
            <span class="badge {% if 'delete' in e.action or 'remove' in e.action %}badge-danger{% elif 'create' in e.action or 'approve' in e.action %}badge-success{% elif 'release' in e.action %}badge-warning{% else %}badge-info{% endif %}">
              {{ e.action }}
            </span>
          </td>
          <td style="font-family: monospace; font-size: 0.82rem; color: var(--text);">
            {{ e.resource_type }} #{{ e.resource_id }}
          </td>
          <td style="font-size: 0.88rem; color: var(--text);">
            {{ e.summary }}
          </td>
        </tr>
        {% else %}
        <tr>
          <td colspan="5" class="empty">No audit events recorded yet.</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>
</section>
{% endblock %}
```

## FILE 11X: `templates/reviews.html`

```html
{% extends 'base.html' %}
{% block title %}Review Moderation — Maria Tutor{% endblock %}
{% block page_title %}Review Moderation{% endblock %}
{% block content %}
<div class="page-header">
  <div>
    <div class="breadcrumb">Governance / <span>Reviews</span></div>
    <h1>Parent Review Moderation</h1>
    <p class="muted">Verify and approve feedback submitted by parents for completed tuitions.</p>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Request Ref</th>
        <th>Tutor</th>
        <th>Rating</th>
        <th>Review Body</th>
        <th>Status</th>
        <th>Moderate</th>
      </tr>
    </thead>
    <tbody>
      {% for r in reviews %}
      <tr>
        <td><code>{{ r.request.public_ref }}</code></td>
        <td><strong>{{ r.tutor.full_name }}</strong> ({{ r.tutor.tutor_code }})</td>
        <td><span class="badge badge-warning">★ {{ r.rating }} / 5</span></td>
        <td>{{ r.body }}</td>
        <td><span class="badge badge-info">{{ r.status }}</span></td>
        <td>
          <form method="post" action="{{ url_for('superadmin.moderate_review' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.moderate_review', review_id=r.id) }}" style="display:flex; gap:6px;">
            <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
            <select name="status" style="width:auto; padding:5px 8px;">
              {% for s in ['Approved','Published','Rejected','Hidden'] %}
                <option value="{{ s }}" {{ 'selected' if r.status == s else '' }}>{{ s }}</option>
              {% endfor %}
            </select>
            <button class="btn btn-sm btn-primary" type="submit">Save</button>
          </form>
        </td>
      </tr>
      {% else %}
      <tr><td colspan="6" class="empty">No parent reviews submitted yet.</td></tr>
      {% endfor %}
    </tbody>
  </table>
</div>
{% endblock %}
```

## FILE 11Y: `templates/system_health.html`

```html
{% extends 'base.html' %}{% block content %}<h1>System health & errors</h1><div class="cards">{% for name,state in checks.items() %}<div class="stat"><b>{{ name }}</b><span>{{ state }}</span></div>{% endfor %}</div><section class="panel"><h2>Recent errors</h2><div class="table-wrap"><table><tr><th>Ref</th><th>Time</th><th>Module</th><th>Summary</th><th>Status</th><th>Action</th></tr>{% for e in errors %}<tr><td>{{ e.error_ref }}</td><td>{{ e.created_at.strftime('%Y-%m-%d %H:%M') }}</td><td>{{ e.module }}</td><td>{{ e.summary }}</td><td>{{ 'Resolved' if e.resolved else 'Open' }}</td><td>{% if not e.resolved %}<form method="post" action="{{ url_for('superadmin.resolve_error' if request.endpoint and request.endpoint.startswith('superadmin') else 'admin.resolve_error',error_id=e.id) }}"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><button>Resolve</button></form>{% endif %}</td></tr>{% endfor %}</table></div></section>{% endblock %}
```

## FILE 11Z: `templates/privacy.html`, `templates/forgot_password.html`, `templates/error.html`

### `templates/privacy.html`

```html
{% extends 'base.html' %}{% block content %}<h1>Privacy & safety</h1><section class="panel"><p>We operate as an introduction service for Burewala tuition requests. Parents and tutors do not see each other's direct contact details in public search or opportunity listings.</p><ul><li>Tutor CNIC is used as a login input but is stored as a keyed one-way identifier. Do not reuse this password elsewhere.</li><li>CNIC scans and certificates are stored in private application storage and require an authorized admin permission to review.</li><li>Parents see broad area, class, subject and teaching mode; tutors see only limited opportunity details.</li><li>Admins may view personal contact details only when their Super Admin permission allows it. Important access and changes are audited.</li></ul><p>For a real launch, publish a legally reviewed privacy notice, retention period, consent process and contact channel before collecting documents.</p></section>{% endblock %}
```

### `templates/forgot_password.html`

```html
{% extends 'base.html' %}{% block title %}Password help{% endblock %}{% block content %}<section class="form-shell"><h1>Password help</h1><p>Secure password reset delivery is not connected yet. Contact the platform Admin from your registered phone/email. We will not display or retrieve your current password.</p><form method="post"><input type="hidden" name="csrf_token" value="{{ csrf_token() }}"><label>Registered email or Tutor CNIC<input name="identifier" required></label><button class="button full">Request reset assistance</button></form></section>{% endblock %}
```

### `templates/error.html`

```html
{% extends 'base.html' %}{% block content %}<section class="panel"><h1>Something went wrong</h1><p>We recorded a reference for the support team.</p><p class="reference">{{ error_ref }}</p><a class="button" href="{{ url_for('main.index') }}">Return home</a></section>{% endblock %}
```

## FILE 12: `tests/test_privacy_and_workflow.py`

```python
from pathlib import Path

import pytest

from app import create_app
from extensions import db
from models import CatalogItem, OpportunityExposure, ParentProfile, TutorProfile, TuitionRequest, User
from services import identifier_hash, make_password_hash


@pytest.fixture()
def system(tmp_path):
    app = create_app({
        "TESTING": True,
        "SECRET_KEY": "test-secret-key-long-enough-for-test-only",
        "IDENTIFIER_PEPPER": "independent-test-pepper-long-enough",
        "SQLALCHEMY_DATABASE_URI": "sqlite://",
        "PRIVATE_UPLOAD_DIR": Path(tmp_path) / "private",
        "RATELIMIT_STORAGE_URI": "memory://",
        "WTF_CSRF_ENABLED": False,
    })
    with app.app_context():
        db.drop_all()
        db.create_all()
        city = CatalogItem(kind="city", name="Burewala")
        db.session.add(city)
        db.session.flush()
        area = CatalogItem(kind="area", name="Satellite Town", parent_id=city.id)
        subject = CatalogItem(kind="subject", name="Mathematics")
        school_class = CatalogItem(kind="class", name="10")
        mode = CatalogItem(kind="mode", name="Home Tuition")
        db.session.add_all([area, subject, school_class, mode])
        db.session.flush()

        tutor_password = "Tutor123"
        tutor = TutorProfile(tutor_code="T-TEST001", full_name="Test Tutor", gender="Female",
                             verification_status="Approved", expected_fee_min=10000,
                             availability="Weekdays evening", catalog=[area, subject, school_class, mode])
        tutor_user = User(identifier_hash=identifier_hash("36402-8493837-3", "cnic"),
                          identifier_type="cnic", phone="03001234567", role="tutor",
                          password_hash=make_password_hash(tutor_password), tutor=tutor)
        parent_password = "Parent123"
        parent_user = User(identifier_hash=identifier_hash("parent@example.com", "email"),
                           identifier_type="email", email="parent@example.com", phone="03007654321",
                           role="parent", password_hash=make_password_hash(parent_password),
                           parent=ParentProfile(full_name="Private Parent Name"))
        admin_user = User(identifier_hash=identifier_hash("owner@example.com", "email"),
                          identifier_type="email", email="owner@example.com", role="super_admin",
                          password_hash=make_password_hash("Owner123"))
        db.session.add_all([tutor_user, parent_user, admin_user])
        db.session.commit()
        ids = {"area": area.id, "subject": subject.id, "class": school_class.id, "mode": mode.id}
    client = app.test_client()
    with client.session_transaction() as sess:
        sess["csrf_token"] = "test-csrf"
    yield app, client, ids
    with app.app_context():
        db.session.remove()
        db.drop_all()


def login(client, identifier, password):
    return client.post("/login", data={"csrf_token": "test-csrf", "identifier": identifier,
                                      "password": password}, follow_redirects=True)


def logout(client):
    return client.post("/logout", data={"csrf_token": "test-csrf"}, follow_redirects=True)


def test_private_match_interest_and_authorized_contact_release(system):
    app, client, ids = system
    with app.app_context():
        tutor_user = User.query.filter_by(role="tutor").first()
        tutor_hash = tutor_user.identifier_hash
        assert tutor_hash != "36402-8493837-3"
        assert "36402-8493837-3" not in tutor_user.password_hash

    assert login(client, "parent@example.com", "Parent123").status_code == 200
    response = client.post("/parent/requests/new", data={
        "csrf_token": "test-csrf", "area_id": ids["area"], "subject_id": ids["subject"],
        "class_id": ids["class"], "mode_id": ids["mode"], "preferred_time": "Weekdays evening",
        "budget_min": "10000", "budget_max": "20000", "tutor_gender_preference": "Female",
        "subarea_text": "Private Block A", "requirements": "Private note for admin only",
    }, follow_redirects=True)
    assert response.status_code == 200
    with app.app_context():
        req = TuitionRequest.query.one()
        request_id = req.id
        public_ref = req.public_ref
    parent_page = client.get(f"/parent/requests/{request_id}")
    assert b"03001234567" not in parent_page.data
    assert b"Private Parent Name" not in parent_page.data

    logout(client)
    assert login(client, "owner@example.com", "Owner123").status_code == 200
    assert client.post(f"/admin/requests/{request_id}/match", data={"csrf_token": "test-csrf"}).status_code == 302
    with app.app_context():
        assert OpportunityExposure.query.filter_by(request_id=request_id).count() == 1
    logout(client)

    assert login(client, "36402-8493837-3", "Tutor123").status_code == 200
    opportunity = client.get(f"/tutor/opportunities/{request_id}")
    assert b"Private Parent Name" not in opportunity.data
    assert b"03007654321" not in opportunity.data
    assert b"Private Block A" not in opportunity.data
    assert client.post(f"/tutor/opportunities/{request_id}/interest", data={"csrf_token": "test-csrf"}).status_code == 302
    logout(client)

    assert login(client, "owner@example.com", "Owner123").status_code == 200
    with app.app_context():
        exposure = OpportunityExposure.query.filter_by(request_id=request_id).one()
        tutor_id = exposure.tutor_id
    assert client.post(f"/admin/requests/{request_id}/select-tutor", data={
        "csrf_token": "test-csrf", "tutor_id": tutor_id
    }).status_code == 302
    assert client.post(f"/admin/requests/{request_id}/release-contacts", data={
        "csrf_token": "test-csrf"
    }).status_code == 302
    logout(client)

    assert login(client, "36402-8493837-3", "Tutor123").status_code == 200
    authorized = client.get(f"/tutor/opportunities/{request_id}")
    assert b"Private Parent Name" in authorized.data
    assert b"03007654321" in authorized.data
    logout(client)

    assert login(client, "parent@example.com", "Parent123").status_code == 200
    parent_page = client.get(f"/parent/requests/{request_id}")
    assert b"Test Tutor" in parent_page.data
    assert b"03001234567" in parent_page.data


def test_unexposed_request_is_not_openable_by_tutor(system):
    app, client, ids = system
    with app.app_context():
        parent = User.query.filter_by(role="parent").one()
        req = TuitionRequest(public_ref="BW-PRIVATE1", parent_user_id=parent.id, area_id=ids["area"],
                             subject_id=ids["subject"], class_id=ids["class"], mode_id=ids["mode"],
                             preferred_time="Evening")
        db.session.add(req)
        db.session.commit()
        request_id = req.id
    login(client, "36402-8493837-3", "Tutor123")
    assert client.get(f"/tutor/opportunities/{request_id}").status_code == 404
```
