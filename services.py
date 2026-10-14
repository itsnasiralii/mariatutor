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
    SystemSetting,
)

CNIC_RE = re.compile(r"^\d{5}[-/]\d{7}[-/]\d$")
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

DEFAULT_PAKISTANI_DEGREES = [
    "Matriculation (SSC)",
    "Intermediate (FA)",
    "Intermediate (FSc Pre-Medical)",
    "Intermediate (FSc Pre-Engineering)",
    "Intermediate (ICS)",
    "Intermediate (I.Com)",
    "Intermediate (A-Levels)",
    "Associate Degree in Arts (ADA)",
    "Associate Degree in Science (ADS)",
    "Associate Degree in Commerce (ADC)",
    "Bachelor of Arts (BA)",
    "Bachelor of Science (BSc)",
    "Bachelor of Commerce (B.Com)",
    "Bachelor of Business Administration (BBA)",
    "Bachelor of Computer Science (BSCS)",
    "Bachelor of Science in Information Technology (BSIT)",
    "Bachelor of Science in Software Engineering (BSSE)",
    "Bachelor of Science in Artificial Intelligence (BSAI)",
    "Bachelor of Science in Cyber Security",
    "Bachelor of Science in Electrical Engineering (BSEE)",
    "Bachelor of Science in Mechanical Engineering",
    "Bachelor of Science in Civil Engineering",
    "Doctor of Pharmacy (Pharm-D)",
    "Bachelor of Medicine and Bachelor of Surgery (MBBS)",
    "Bachelor of Dental Surgery (BDS)",
    "Doctor of Veterinary Medicine (DVM)",
    "Bachelor of Science in Nursing (BSN)",
    "Master of Arts (MA)",
    "Master of Science (MSc)",
    "Master of Business Administration (MBA)",
    "Master of Computer Science (MCS)",
    "Master of Information Technology (MIT)",
    "Master of Philosophy (MPhil)",
    "Master of Science (MS)",
    "Doctor of Philosophy (PhD)",
    "Bachelor of Education (B.Ed.)",
    "Master of Education (M.Ed.)",
    "Bachelor of Laws (LLB)",
    "Master of Laws (LLM)",
    "Chartered Accountancy (CA)",
    "Association of Chartered Certified Accountants (ACCA)",
    "Cost and Management Accountancy (CMA)",
    "Diploma of Associate Engineering (DAE)",
    "Bachelor of Fine Arts (BFA)",
    "Bachelor of Design (B.Des.)",
    "Bachelor of Social Work (BSW)",
]

DEFAULT_TIMING_SLOTS = [
    "Daily 4:00 PM - 8:00 PM (Evening)",
    "After 5:00 PM - 9:00 PM (Night)",
    "Morning 8:00 AM - 1:00 PM",
    "Afternoon 1:00 PM - 5:00 PM",
    "Weekends Only (Saturday & Sunday)",
    "Flexible Timings (Mutual Agreement)",
]


def now():
    return datetime.now(timezone.utc)


def normalize_cnic(value: str) -> str:
    """Normalize any Pakistani CNIC string (with hyphens, slashes, or raw 13 digits) to standard XXXXX-XXXXXXX-X."""
    if not value:
        return ""
    digits = re.sub(r"\D", "", str(value))
    if len(digits) == 13:
        return f"{digits[:5]}-{digits[5:12]}-{digits[12]}"
    return str(value).strip()


def normalized_identifier(value: str, kind: str) -> str:
    value = (value or "").strip()
    if kind == "cnic":
        digits = re.sub(r"\D", "", value)
        if len(digits) == 13:
            return f"{digits[:5]}-{digits[5:12]}-{digits[12]}"
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
                if request.path.startswith("/admin") or request.path.startswith("/superadmin"):
                    flash("Please sign in to access the Administration Portal.", "warning")
                    return redirect(url_for("auth.admin_login", next=request.path))
                if request.path.startswith("/tutor"):
                    flash("Please sign in to access the Tutor Portal.", "warning")
                    return redirect(url_for("auth.tutor_login", next=request.path))
                if request.path.startswith("/parent"):
                    flash("Please sign in to access the Parent Portal.", "warning")
                    return redirect(url_for("auth.parent_login", next=request.path))
                return redirect(url_for("auth.login", next=request.path))
            if current_user.role not in roles:
                allowed_label = ", ".join(r.replace("_", " ").title() for r in roles)
                flash(
                    f"Access Denied: This area is restricted to {allowed_label}.",
                    "warning",
                )
                if request.path.startswith("/admin") or request.path.startswith("/superadmin"):
                    return redirect(url_for("auth.admin_login"))
                if request.path.startswith("/tutor"):
                    return redirect(url_for("auth.tutor_login"))
                if request.path.startswith("/parent"):
                    return redirect(url_for("auth.parent_login"))
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


def get_budget_settings() -> dict:
    """Retrieve dynamic minimum and maximum tuition budget settings, defaulting to 7,000 and 10,000 PKR."""
    try:
        min_setting = SystemSetting.query.filter_by(key="default_budget_min").first()
        max_setting = SystemSetting.query.filter_by(key="default_budget_max").first()
        min_val = int(min_setting.value) if min_setting and min_setting.value.isdigit() else 7000
        max_val = int(max_setting.value) if max_setting and max_setting.value.isdigit() else 10000
        return {"min": min_val, "max": max_val}
    except Exception:
        return {"min": 7000, "max": 10000}


def set_budget_settings(min_val: int, max_val: int, actor=None) -> dict:
    """Update dynamic minimum and maximum tuition budget settings in SystemSetting."""
    min_setting = SystemSetting.query.filter_by(key="default_budget_min").first()
    if not min_setting:
        min_setting = SystemSetting(key="default_budget_min", value=str(min_val))
        db.session.add(min_setting)
    else:
        min_setting.value = str(min_val)

    max_setting = SystemSetting.query.filter_by(key="default_budget_max").first()
    if not max_setting:
        max_setting = SystemSetting(key="default_budget_max", value=str(max_val))
        db.session.add(max_setting)
    else:
        max_setting.value = str(max_val)

    audit("update_budget_settings", "system_setting", "market_budget", f"Updated default budget: PKR {min_val} - {max_val}", actor=actor)
    db.session.commit()
    return {"min": min_val, "max": max_val}

