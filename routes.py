"""Public, Parent, and Tutor workflow routes with strict zero-leakage privacy controls."""
from datetime import datetime, timezone
from pathlib import Path
from uuid import uuid4
from flask import Blueprint, abort, current_app, flash, jsonify, redirect, render_template, request, session, url_for
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
    User,
    VerificationDocument,
)
from services import (
    DEFAULT_PAKISTANI_DEGREES,
    DEFAULT_TIMING_SLOTS,
    active_catalog,
    audit,
    create_request_ref,
    lead_event,
    require_roles,
)

bp = Blueprint("main", __name__)


@bp.route("/tutor")
def tutor_root():
    tutor_uid = session.get("tutor_user_id")
    if tutor_uid:
        user = db.session.get(User, int(tutor_uid))
        if user and user.role == "tutor":
            return redirect(url_for("main.tutor_dashboard"))
    return redirect(url_for("auth.tutor_login"))


@bp.route("/parent")
def parent_root():
    parent_uid = session.get("parent_user_id")
    if parent_uid:
        user = db.session.get(User, int(parent_uid))
        if user and user.role == "parent":
            return redirect(url_for("main.parent_dashboard"))
    return redirect(url_for("auth.parent_login"))


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
    raw_tutor_id = request.args.get("tutor_id") or request.form.get("preferred_tutor_id")
    preferred_tutor = None
    if raw_tutor_id and str(raw_tutor_id).isdigit():
        preferred_tutor = TutorProfile.query.filter_by(id=int(raw_tutor_id), verification_status="Approved").first()

    # Filter catalogs to only show the relevant subjects, classes, areas, and modes of the selected tutor!
    if preferred_tutor:
        t_items = preferred_tutor.catalog
        t_areas = [item for item in t_items if item.kind == "area" and item.active]
        t_subjects = [item for item in t_items if item.kind == "subject" and item.active]
        t_classes = [item for item in t_items if item.kind == "class" and item.active]
        t_modes = [item for item in t_items if item.kind == "mode" and item.active]

        if t_areas:
            catalogs["area"] = t_areas
        if t_subjects:
            catalogs["subject"] = t_subjects
        if t_classes:
            catalogs["class"] = t_classes
        if t_modes:
            catalogs["mode"] = t_modes

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
                flash("Please check your form selections and try again.", "error")
                return render_template("parent_request_form.html", catalogs=catalogs, preferred_tutor=preferred_tutor), 400
            values[key] = item.id

        # Hourly dropdown or custom timing
        raw_time = request.form.get("preferred_time", "").strip()
        custom_time = request.form.get("preferred_time_custom", "").strip()
        if raw_time == "__other__" or (not raw_time and custom_time):
            time_pref = custom_time or "Flexible Timings"
        else:
            time_pref = raw_time or "Flexible Timings"
        time_pref = time_pref[:120]

        from services import get_budget_settings
        b_settings = get_budget_settings()
        budget_min = _positive_int(request.form.get("budget_min")) or b_settings["min"]
        budget_max = _positive_int(request.form.get("budget_max")) or b_settings["max"]
        if budget_min is not None and budget_max is not None and budget_min > budget_max:
            flash("Minimum budget cannot exceed maximum budget.", "error")
            return render_template("parent_request_form.html", catalogs=catalogs, preferred_tutor=preferred_tutor), 400
        gender_pref = request.form.get("tutor_gender_preference", "Any")
        if gender_pref not in {"Any", "Male", "Female"}:
            gender_pref = "Any"

        user_reqs = request.form.get("requirements", "").strip()[:400]
        if preferred_tutor:
            tutor_note = f"[Specifically Requested Tutor: {preferred_tutor.tutor_code} - {preferred_tutor.full_name}] "
            user_reqs = (tutor_note + user_reqs)[:500]

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
            requirements=user_reqs,
            selected_tutor_id=preferred_tutor.id if preferred_tutor else None,
        )
        db.session.add(req)
        db.session.flush()

        lead_msg = f"Request {req.public_ref}"
        if preferred_tutor:
            lead_msg += f" (Parent requested {preferred_tutor.tutor_code})"
        lead_event(req, "Parent submitted request", lead_msg, private=False, actor=current_user)
        audit("create_request", "tuition_request", req.public_ref, "Parent submitted a tuition request")
        db.session.commit()
        flash(f"Tuition request submitted successfully. Reference: {req.public_ref}. Maria Tutor administration will contact you to schedule a free trial demo.", "success")
        return redirect(url_for("main.parent_dashboard"))

    return render_template("parent_request_form.html", catalogs=catalogs, preferred_tutor=preferred_tutor)


@bp.route("/parent/explore-tutors")
@bp.route("/explore-tutors")
def parent_explore_tutors():
    catalogs = {kind: active_catalog(kind) for kind in ("area", "subject", "class", "mode")}
    query = (
        TutorProfile.query.filter_by(verification_status="Approved")
        .join(User)
        .filter(User.active.is_(True))
    )

    selected_area_id = request.args.get("area_id", type=int)
    selected_subject_id = request.args.get("subject_id", type=int)
    selected_class_id = request.args.get("class_id", type=int)
    selected_gender = request.args.get("gender", "").strip()
    search_keyword = request.args.get("q", "").strip()

    if selected_gender in {"Male", "Female"}:
        query = query.filter(TutorProfile.gender == selected_gender)

    if selected_area_id:
        query = query.filter(TutorProfile.catalog.any(CatalogItem.id == selected_area_id))
    if selected_subject_id:
        query = query.filter(TutorProfile.catalog.any(CatalogItem.id == selected_subject_id))
    if selected_class_id:
        query = query.filter(TutorProfile.catalog.any(CatalogItem.id == selected_class_id))
    if search_keyword:
        kw = f"%{search_keyword}%"
        query = query.filter(
            db.or_(
                TutorProfile.full_name.ilike(kw),
                TutorProfile.qualification.ilike(kw),
                TutorProfile.experience.ilike(kw),
                TutorProfile.tutor_code.ilike(kw),
            )
        )

    tutors = query.order_by(TutorProfile.id.desc()).all()

    return render_template(
        "explore_tutors.html",
        tutors=tutors,
        catalogs=catalogs,
        selected_area_id=selected_area_id,
        selected_subject_id=selected_subject_id,
        selected_class_id=selected_class_id,
        selected_gender=selected_gender,
        search_keyword=search_keyword,
    )


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
        flash("You have already reviewed this tuition request.", "error")
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
    flash("Your review has been submitted for moderation.", "success")
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
        flash("Your interest has been submitted to administration. Contact details remain private until authorized.", "success")
    else:
        flash("You have already indicated interest in this opportunity.", "success")
    return redirect(url_for("main.tutor_dashboard"))


@bp.route("/tutor/profile", methods=["GET", "POST"])
@login_required
@require_roles("tutor")
def tutor_profile():
    tutor = current_user.tutor
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
        ids = {int(v) for v in request.form.getlist("catalog_ids") if v.isdigit()}
        valid = (
            CatalogItem.query.filter(CatalogItem.id.in_(ids), CatalogItem.active.is_(True)).all()
            if ids
            else []
        )
        allowed = {
            i.id
            for k in ("area", "subject", "class", "mode")
            for i in choices.get(k, [])
            if hasattr(i, "id") and i.id is not None
        }
        if not tutor or any(i.id not in allowed for i in valid):
            abort(400)
        fee_min = _positive_int(request.form.get("expected_fee_min"))
        fee_max = _positive_int(request.form.get("expected_fee_max"))
        if fee_min is not None and fee_max is not None and fee_min > fee_max:
            flash("Minimum expected fee cannot exceed maximum fee.", "error")
            return render_template("tutor_profile.html", tutor=tutor, choices=choices), 400
        tutor.full_name = request.form.get("full_name", "").strip()[:120] or tutor.full_name
        gender = request.form.get("gender", tutor.gender)[:24]
        if gender in {"Prefer not to say", "Male", "Female", "Other"}:
            tutor.gender = gender

        # Dynamic qualification with custom "Other" support
        qual_val = request.form.get("qualification", "").strip()
        custom_qual = request.form.get("qualification_custom", "").strip()
        if qual_val == "__other__" or (not qual_val and custom_qual):
            qual_val = custom_qual or "Other"
        tutor.qualification = qual_val[:200]

        tutor.experience = request.form.get("experience", "").strip()[:500]

        # Dynamic availability with custom "Other" support
        avail_val = request.form.get("availability", "").strip()
        custom_avail = request.form.get("availability_custom", "").strip()
        if avail_val == "__other__" or (not avail_val and custom_avail):
            avail_val = custom_avail or "Flexible Timings"
        tutor.availability = avail_val[:300]

        tutor.expected_fee_min = fee_min
        tutor.expected_fee_max = fee_max
        tutor.catalog = valid
        tutor.verification_status = "Pending"
        tutor.approved_at = None
        audit("update_tutor_profile", "tutor", tutor.tutor_code, "Tutor updated profile; review required")
        db.session.commit()
        flash("Profile updated successfully. Our administration will review the changes.", "success")
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
            flash("Please select both a document type and a valid file.", "error")
            return redirect(url_for("main.tutor_documents"))
        original = secure_filename(upload.filename)[:200]
        ext = original.rsplit(".", 1)[-1].lower() if "." in original else ""
        if ext not in {"pdf", "png", "jpg", "jpeg"}:
            flash("Only PDF, PNG, JPG, or JPEG files are allowed.", "error")
            return redirect(url_for("main.tutor_documents"))
        data = upload.read(8 * 1024 * 1024 + 1)
        if len(data) > 8 * 1024 * 1024:
            flash("Uploaded file must be 8 MB or smaller.", "error")
            return redirect(url_for("main.tutor_documents"))
        signatures = {
            "pdf": b"%PDF-",
            "png": b"\x89PNG\r\n\x1a\n",
            "jpg": b"\xff\xd8\xff",
            "jpeg": b"\xff\xd8\xff",
        }
        if not data.startswith(signatures[ext]):
            flash("The uploaded file content does not match its file extension.", "error")
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
        flash("Document uploaded securely. Our administration will review it shortly.", "success")
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
