"""Permission-checked Admin operations: lead pipeline, tutor verification, contact release, and payments."""
from datetime import datetime, timezone
import json
import os
from flask import Blueprint, abort, current_app, flash, redirect, render_template, request, send_file, session, url_for
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
    ParentProfile,
    PaymentEntry,
    Review,
    TuitionRequest,
    TutorProfile,
    User,
    VerificationDocument,
)
from services import (
    ADMIN_PERMISSIONS,
    DEFAULT_PAKISTANI_DEGREES,
    DEFAULT_TIMING_SLOTS,
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
ALLOWED_KINDS = {"city", "area", "block", "subject", "class", "mode", "qualification", "timing"}
ALLOWED_VERIFICATION_STATUSES = {
    "Pending",
    "Under Review",
    "Manual review",
    "Approved",
    "Resubmission required",
    "Rejected",
    "Suspicious / restricted",
}


@bp.route("/")
def admin_root():
    admin_uid = session.get("admin_user_id")
    if admin_uid:
        user = db.session.get(User, int(admin_uid))
        if user and user.role in {"admin", "super_admin"}:
            return redirect(url_for("superadmin.dashboard" if user.role == "super_admin" else "admin.dashboard"))
    return redirect(url_for("auth.admin_login"))


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
    flash("Tuition request status updated successfully.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/note")
@login_required
@require_roles("admin", "super_admin")
@require_permission("edit_requests")
def add_internal_note(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    note = request.form.get("note", "").strip()[:500]
    if not note:
        flash("Internal note cannot be empty.", "error")
    else:
        lead_event(req, "Admin internal note", note, private=True)
        audit("add_internal_note", "tuition_request", req.public_ref, "Private internal note added")
        db.session.commit()
        flash("Private internal note saved successfully.", "success")
    return redirect(url_for("admin.request_detail", request_id=req.id))


@bp.post("/requests/<int:request_id>/match")
@login_required
@require_roles("admin", "super_admin")
@require_permission("match_tutors")
def match_request(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    matches = generate_opportunities(req, actor=current_user)
    flash(
        f"Opportunity exposed to {len(matches)} eligible tutors based on spatial matching.",
        "success" if matches else "info",
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
    flash("Tutor shortlisted successfully. Please confirm trial demo with the parent.", "success")
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
    flash("Contact introduction authorized. Parties can now view verified contacts on their respective dashboards.", "success")
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
    flash("Tutor verification status updated successfully.", "success")
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
    flash("Document review status updated successfully.", "success")
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
@require_roles("super_admin", "admin")
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
    flash("Spatial adjacency link removed successfully.", "success")
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
    flash("Admin permissions updated successfully.", "success")
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
    flash("User account status updated successfully.", "success")
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
    flash("Review moderation updated successfully.", "success")
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
        flash("Please enter a positive payment amount in PKR.", "error")
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
            flash("Please configure an active commission rule first before recording payments.", "error")
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


def _positive_int(value):
    try:
        n = int(value)
        return max(0, n)
    except (ValueError, TypeError):
        return None


# ══════════════════════════════════════════════════════════════════
# TUTOR PROFILE CRUD FOR ADMIN / SUPER ADMIN
# ══════════════════════════════════════════════════════════════════
@bp.route("/tutors/<int:tutor_id>/edit", methods=["GET", "POST"])
@login_required
@require_roles("admin", "super_admin")
def edit_tutor(tutor_id):
    tutor = db.get_or_404(TutorProfile, tutor_id)
    choices = {
        kind: active_catalog(kind)
        for kind in ("area", "subject", "class", "mode", "qualification", "timing")
    }
    if not choices.get("qualification"):
        choices["qualification"] = [
            type("DummyItem", (), {"id": None, "kind": "qualification", "name": deg, "active": True})
            for deg in DEFAULT_PAKISTANI_DEGREES
        ]
    if not choices.get("timing"):
        choices["timing"] = [
            type("DummyItem", (), {"id": None, "kind": "timing", "name": slot, "active": True})
            for slot in DEFAULT_TIMING_SLOTS
        ]

    if request.method == "POST":
        full_name = request.form.get("full_name", "").strip()[:120]
        phone = request.form.get("phone", "").strip()[:32]
        email = request.form.get("email", "").strip().lower()[:254]
        gender = request.form.get("gender", "Prefer not to say")[:24]

        qual_val = request.form.get("qualification", "").strip()
        custom_qual = request.form.get("qualification_custom", "").strip()
        if qual_val == "__other__" or (not qual_val and custom_qual):
            qual_val = custom_qual or "Other"

        experience = request.form.get("experience", "").strip()[:500]

        avail_val = request.form.get("availability", "").strip()
        custom_avail = request.form.get("availability_custom", "").strip()
        if avail_val == "__other__" or (not avail_val and custom_avail):
            avail_val = custom_avail or "Flexible Timings"

        fee_min = _positive_int(request.form.get("expected_fee_min"))
        fee_max = _positive_int(request.form.get("expected_fee_max"))
        if fee_min is not None and fee_max is not None and fee_min > fee_max:
            flash("Minimum expected fee cannot exceed maximum fee.", "error")
            return render_template("admin_edit_tutor.html", tutor=tutor, choices=choices), 400

        status = request.form.get("verification_status", tutor.verification_status)
        if status in ALLOWED_VERIFICATION_STATUSES:
            tutor.verification_status = status
            if status == "Approved" and not tutor.approved_at:
                tutor.approved_at = datetime.now(timezone.utc)
            elif status != "Approved":
                tutor.approved_at = None

        tutor.verification_note = request.form.get("verification_note", "").strip()[:500]

        ids = {int(v) for v in request.form.getlist("catalog_ids") if v.isdigit()}
        valid = (
            CatalogItem.query.filter(CatalogItem.id.in_(ids), CatalogItem.active.is_(True)).all()
            if ids
            else []
        )
        tutor.catalog = valid

        if full_name:
            tutor.full_name = full_name
        tutor.gender = gender if gender in {"Prefer not to say", "Male", "Female", "Other"} else tutor.gender
        tutor.qualification = qual_val[:200]
        tutor.experience = experience
        tutor.availability = avail_val[:300]
        tutor.expected_fee_min = fee_min
        tutor.expected_fee_max = fee_max

        if tutor.user:
            tutor.user.phone = phone
            if email and email != tutor.user.email:
                tutor.user.email = email
                if tutor.user.identifier_type == "email":
                    tutor.user.identifier_hash = identifier_hash(email, "email")
            tutor.user.active = request.form.get("active") == "1"

            new_pw = request.form.get("new_password", "").strip()
            if new_pw:
                if len(new_pw) < 8:
                    flash("New password must be at least 8 characters long.", "error")
                    return render_template("admin_edit_tutor.html", tutor=tutor, choices=choices), 400
                tutor.user.password_hash = make_password_hash(new_pw)

        audit("admin_edit_tutor", "tutor", tutor.tutor_code, f"Admin updated tutor profile {tutor.full_name}")
        db.session.commit()
        flash(f"Tutor profile for {tutor.full_name} ({tutor.tutor_code}) updated successfully.", "success")

        next_url = request.args.get("next")
        if next_url:
            return redirect(next_url)
        return redirect(url_for("admin.tutor_detail", tutor_id=tutor.id))

    return render_template("admin_edit_tutor.html", tutor=tutor, choices=choices)


# ══════════════════════════════════════════════════════════════════
# PARENT PROFILE CRUD FOR ADMIN / SUPER ADMIN
# ══════════════════════════════════════════════════════════════════
@bp.route("/parents/<int:parent_id>/edit", methods=["GET", "POST"])
@login_required
@require_roles("admin", "super_admin")
def edit_parent(parent_id):
    parent = db.get_or_404(ParentProfile, parent_id)
    requests = TuitionRequest.query.filter_by(parent_user_id=parent.user_id).order_by(TuitionRequest.id.desc()).all()

    if request.method == "POST":
        full_name = request.form.get("full_name", "").strip()[:120]
        phone = request.form.get("phone", "").strip()[:32]
        email = request.form.get("email", "").strip().lower()[:254]

        if full_name:
            parent.full_name = full_name

        if parent.user:
            parent.user.phone = phone
            if email and email != parent.user.email:
                parent.user.email = email
                if parent.user.identifier_type == "email":
                    parent.user.identifier_hash = identifier_hash(email, "email")
            parent.user.active = request.form.get("active") == "1"

            new_pw = request.form.get("new_password", "").strip()
            if new_pw:
                if len(new_pw) < 8:
                    flash("New password must be at least 8 characters long.", "error")
                    return render_template("admin_edit_parent.html", parent=parent, requests=requests), 400
                parent.user.password_hash = make_password_hash(new_pw)

        audit("admin_edit_parent", "parent", parent.user_id, f"Admin updated parent profile {parent.full_name}")
        db.session.commit()
        flash(f"Parent profile for {parent.full_name} updated successfully.", "success")
        return redirect(url_for("superadmin.users"))

    return render_template("admin_edit_parent.html", parent=parent, requests=requests)


# ══════════════════════════════════════════════════════════════════
# DELETE USER ACCOUNT & PROFILE (TUTOR OR PARENT)
# ══════════════════════════════════════════════════════════════════
def delete_user_account(user_id):
    user = db.get_or_404(User, user_id)
    if (getattr(current_user, "is_authenticated", False) and user.id == current_user.id) or user.role == "super_admin":
        flash("Super Admin account cannot be deleted.", "error")
        return redirect(url_for("superadmin.users"))

    user_label = user.email or user.phone or f"User #{user.id}"
    if user.tutor:
        user_label = f"Tutor {user.tutor.full_name} ({user.tutor.tutor_code})"
        tutor_id = user.tutor.id
        docs = VerificationDocument.query.filter_by(tutor_id=tutor_id).all()
        for doc in docs:
            file_path = current_app.config["PRIVATE_UPLOAD_DIR"] / doc.stored_name
            if file_path.exists():
                try:
                    file_path.unlink()
                except Exception:
                    pass
        db.session.execute(db.text("UPDATE tuition_request SET selected_tutor_id = NULL WHERE selected_tutor_id = :tid"), {"tid": tutor_id})
        db.session.execute(db.text("DELETE FROM tutor_catalog WHERE tutor_id = :tid"), {"tid": tutor_id})
        db.session.execute(db.text("DELETE FROM verification_document WHERE tutor_id = :tid"), {"tid": tutor_id})
        db.session.execute(db.text("DELETE FROM opportunity_exposure WHERE tutor_id = :tid"), {"tid": tutor_id})
        db.session.execute(db.text("DELETE FROM review WHERE tutor_id = :tid"), {"tid": tutor_id})
        db.session.execute(db.text("DELETE FROM tutor_profile WHERE id = :tid"), {"tid": tutor_id})
    elif user.parent:
        user_label = f"Parent {user.parent.full_name}"
        parent_reqs = TuitionRequest.query.filter_by(parent_user_id=user.id).all()
        for req in parent_reqs:
            db.session.execute(db.text("DELETE FROM lead_event WHERE request_id = :rid"), {"rid": req.id})
            db.session.execute(db.text("DELETE FROM opportunity_exposure WHERE request_id = :rid"), {"rid": req.id})
            db.session.execute(db.text("DELETE FROM review WHERE request_id = :rid"), {"rid": req.id})
            db.session.execute(db.text("DELETE FROM payment_entry WHERE request_id = :rid"), {"rid": req.id})
            db.session.execute(db.text("DELETE FROM tuition_request WHERE id = :rid"), {"rid": req.id})
        db.session.execute(db.text("DELETE FROM parent_profile WHERE id = :pid"), {"pid": user.parent.id})

    audit("delete_user", "user", user.id, f"Admin deleted {user.role} user {user_label}")
    db.session.execute(db.text("DELETE FROM user WHERE id = :uid"), {"uid": user.id})
    db.session.commit()
    flash(f"User account for {user_label} has been deleted successfully.", "success")
    return redirect(url_for("superadmin.users"))


@bp.post("/users/<int:user_id>/delete")
@login_required
@require_roles("admin", "super_admin")
def delete_user(user_id):
    return delete_user_account(user_id)


# ══════════════════════════════════════════════════════════════════
# TUITION REQUEST EDITING FOR ADMIN / SUPER ADMIN
# ══════════════════════════════════════════════════════════════════
@bp.route("/requests/<int:request_id>/edit", methods=["GET", "POST"])
@login_required
@require_roles("admin", "super_admin")
@require_permission("edit_requests")
def edit_request(request_id):
    req = db.get_or_404(TuitionRequest, request_id)
    catalogs = {kind: active_catalog(kind) for kind in ("area", "subject", "class", "mode")}

    if request.method == "POST":
        area_id = request.form.get("area_id", type=int)
        subject_id = request.form.get("subject_id", type=int)
        class_id = request.form.get("class_id", type=int)
        mode_id = request.form.get("mode_id", type=int)

        if not all([area_id, subject_id, class_id, mode_id]):
            flash("Please ensure Area, Subject, Class, and Mode are selected.", "error")
            return render_template("admin_edit_request.html", req=req, catalogs=catalogs), 400

        budget_min = _positive_int(request.form.get("budget_min"))
        budget_max = _positive_int(request.form.get("budget_max"))
        if budget_min is not None and budget_max is not None and budget_min > budget_max:
            flash("Minimum budget cannot exceed maximum budget.", "error")
            return render_template("admin_edit_request.html", req=req, catalogs=catalogs), 400

        req.area_id = area_id
        req.subject_id = subject_id
        req.class_id = class_id
        req.mode_id = mode_id
        req.subarea_text = request.form.get("subarea_text", "").strip()[:120]
        req.budget_min = budget_min
        req.budget_max = budget_max
        req.preferred_time = request.form.get("preferred_time", req.preferred_time).strip()[:120]
        req.requirements = request.form.get("requirements", "").strip()[:500]

        g_pref = request.form.get("tutor_gender_preference", "Any")
        if g_pref in {"Any", "Male", "Female"}:
            req.tutor_gender_preference = g_pref

        comm_val = request.form.get("commission_snapshot_value")
        comm_type = request.form.get("commission_snapshot_type")
        if comm_val is not None and comm_val.strip() != "":
            try:
                cv = int(float(comm_val))
                if cv >= 0:
                    req.commission_snapshot_value = cv
                    req.commission_snapshot_type = "fixed" if comm_type == "fixed" else "percent"
            except (ValueError, TypeError):
                pass

        lead_event(req, "Admin updated request details", f"Edited by {current_user.email or current_user.role}", actor=current_user)
        audit("admin_edit_request", "tuition_request", req.public_ref, "Admin updated academic request parameters")
        db.session.commit()
        flash(f"Tuition request {req.public_ref} details updated successfully.", "success")
        return redirect(url_for("admin.request_detail", request_id=req.id))

    return render_template("admin_edit_request.html", req=req, catalogs=catalogs)



