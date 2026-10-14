"""Authentication and account lifecycle routes for Parents, Tutors, and Admins."""
from datetime import datetime, timezone
from flask import Blueprint, abort, flash, redirect, render_template, request, session, url_for
from flask_login import current_user, login_required, login_user, logout_user
from extensions import db, limiter
from models import CatalogItem, ParentProfile, TutorProfile, User
from services import (
    CNIC_RE,
    DEFAULT_PAKISTANI_DEGREES,
    DEFAULT_TIMING_SLOTS,
    audit,
    create_tutor_code,
    identifier_hash,
    make_password_hash,
    normalize_cnic,
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
            CatalogItem.kind.in_(["area", "subject", "class", "mode", "qualification", "timing"]),
        )
        .order_by(CatalogItem.kind, CatalogItem.sort_order, CatalogItem.name)
        .all()
    )
    # Ensure qualification and timing have fallbacks if database lacks them
    has_quals = any(item.kind == "qualification" for item in choices)
    if not has_quals:
        for deg in DEFAULT_PAKISTANI_DEGREES:
            choices.append(type("DummyItem", (), {"id": None, "kind": "qualification", "name": deg, "active": True})())
    has_timings = any(item.kind == "timing" for item in choices)
    if not has_timings:
        for slot in DEFAULT_TIMING_SLOTS:
            choices.append(type("DummyItem", (), {"id": None, "kind": "timing", "name": slot, "active": True})())

    if request.method == "POST":
        name = request.form.get("full_name", "").strip()
        cnic_raw = request.form.get("cnic", "").strip()
        cnic = normalize_cnic(cnic_raw)
        phone = request.form.get("phone", "").strip()
        email = request.form.get("email", "").strip().lower() or None
        password = request.form.get("password", "")
        gender = request.form.get("gender", "Prefer not to say")
        if gender not in {"Prefer not to say", "Male", "Female", "Other"}:
            gender = "Prefer not to say"

        if not name or not CNIC_RE.fullmatch(cnic):
            flash("Please enter full name and valid CNIC in XXXXX-XXXXXXX-X format.", "error")
        elif not phone or len(phone) > 32:
            flash("Contact phone number is required.", "error")
        elif email and (len(email) > 254 or "@" not in email):
            flash("Please enter a valid email address.", "error")
        elif not password_ok(password):
            flash("Password must be at least 8 characters long and include uppercase, lowercase, and numeric characters.", "error")
        else:
            digest = identifier_hash(cnic, "cnic")
            if User.query.filter((User.identifier_hash == digest) | (User.cnic_hash == digest)).first():
                flash("An account with this CNIC already exists. Please log in or reset your password.", "error")
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
                    flash("Minimum fee cannot exceed maximum fee.", "error")
                    return render_template("register_tutor.html", choices=choices), 400

                cnic_hint = "Male" if int(cnic[-1]) % 2 else "Female"
                if gender == "Prefer not to say":
                    gender = cnic_hint

                # Dynamic qualification with custom "Other" support
                qual_val = request.form.get("qualification", "").strip()
                custom_qual = request.form.get("qualification_custom", "").strip()
                if qual_val == "__other__" or (not qual_val and custom_qual):
                    qual_val = custom_qual or "Other"

                # Dynamic availability with custom "Other" support
                avail_val = request.form.get("availability", "").strip()
                custom_avail = request.form.get("availability_custom", "").strip()
                if avail_val == "__other__" or (not avail_val and custom_avail):
                    avail_val = custom_avail or "Flexible Timings"

                tutor = TutorProfile(
                    full_name=name,
                    tutor_code=create_tutor_code(),
                    gender=gender,
                    cnic_gender_hint=cnic_hint,
                    qualification=qual_val[:200],
                    experience=request.form.get("experience", "").strip()[:500],
                    availability=avail_val[:300],
                    expected_fee_min=fee_min,
                    expected_fee_max=fee_max,
                )
                user = User(
                    identifier_hash=digest,
                    identifier_type="cnic",
                    cnic_hash=digest,
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
                    f"Tutor account created successfully. Your Tutor ID is {tutor.tutor_code}. Opportunities will appear once verification is approved by administration.",
                    "success",
                )
                return redirect(url_for("auth.tutor_login"))
    return render_template("register_tutor.html", choices=choices)


@bp.route("/register/parent", methods=["GET", "POST"])
@limiter.limit("5 per hour")
def register_parent():
    if current_user.is_authenticated:
        return redirect(url_for("main.dashboard"))
    if request.method == "POST":
        name = request.form.get("full_name", "").strip()
        cnic_raw = request.form.get("cnic", "").strip()
        cnic = normalize_cnic(cnic_raw)
        email = request.form.get("email", "").strip().lower() or None
        phone = request.form.get("phone", "").strip()
        password = request.form.get("password", "")

        if not name:
            flash("Please provide your full name.", "error")
        elif not phone or len(phone) > 32:
            flash("Contact phone number is required.", "error")
        elif cnic_raw and not CNIC_RE.fullmatch(cnic):
            flash("Please enter a valid Pakistani CNIC (13 digits, e.g. 36402-XXXXXXX-X).", "error")
        elif not cnic and not email:
            flash("Please provide your CNIC number or email address for account login.", "error")
        elif email and ("@" not in email or len(email) > 254):
            flash("Please provide a valid email address.", "error")
        elif not password_ok(password):
            flash("Password must be at least 8 characters long and include uppercase, lowercase, and numeric characters.", "error")
        else:
            cnic_digest = identifier_hash(cnic, "cnic") if cnic else None
            email_digest = identifier_hash(email, "email") if email else None

            # Check if CNIC already registered
            if cnic_digest and User.query.filter(
                (User.identifier_hash == cnic_digest) | (User.cnic_hash == cnic_digest)
            ).first():
                flash("An account with this CNIC already exists. Please log in.", "error")
                return render_template("register_parent.html"), 400

            # Check if Email already registered
            if email and User.query.filter(
                (User.identifier_hash == email_digest) | (db.func.lower(User.email) == email.lower())
            ).first():
                flash("An account with this email address already exists. Please log in or reset password.", "error")
                return render_template("register_parent.html"), 400

            primary_hash = cnic_digest if cnic_digest else email_digest
            primary_type = "cnic" if cnic_digest else "email"

            user = User(
                identifier_hash=primary_hash,
                identifier_type=primary_type,
                cnic_hash=cnic_digest,
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
            flash("Parent account created successfully. You may now post a tuition request.", "success")
            login_user(user, remember=False, fresh=True)
            return redirect(url_for("main.parent_dashboard"))
    return render_template("register_parent.html")


def _find_user_by_identifier(identifier: str):
    identifier = (identifier or "").strip()
    if not identifier:
        return None

    # 1. Normalize and check if CNIC (supports dashes, slashes, or 13 raw digits)
    norm_cnic = normalize_cnic(identifier)
    if CNIC_RE.fullmatch(norm_cnic):
        chash = identifier_hash(norm_cnic, "cnic")
        user = User.query.filter(
            (User.identifier_hash == chash) | (User.cnic_hash == chash)
        ).first()
        if user:
            return user

    # 2. Check if Email
    if "@" in identifier:
        ehash = identifier_hash(identifier.lower(), "email")
        user = User.query.filter(
            (User.identifier_hash == ehash) | (db.func.lower(User.email) == identifier.lower())
        ).first()
        if user:
            return user

    # 3. Check if Tutor Code
    if identifier.upper().startswith("T-"):
        tutor_prof = TutorProfile.query.filter(
            db.func.upper(TutorProfile.tutor_code) == identifier.upper()
        ).first()
        if tutor_prof:
            return tutor_prof.user

    # 4. Fallback lookup by generic identifier_hash
    user = User.query.filter_by(identifier_hash=identifier_hash(identifier, "cnic")).first()
    if not user:
        user = User.query.filter_by(identifier_hash=identifier_hash(identifier, "email")).first()
    return user


@bp.route("/admin/login", methods=["GET", "POST"])
@limiter.limit("20 per 10 minutes")
def admin_login():
    # If admin is already logged in for this portal, redirect to dashboard
    admin_uid = session.get("admin_user_id")
    if admin_uid:
        user = db.session.get(User, int(admin_uid))
        if user and user.role in {"admin", "super_admin"}:
            return redirect(url_for("superadmin.dashboard" if user.role == "super_admin" else "admin.dashboard"))

    if request.method == "POST":
        identifier = request.form.get("identifier", "").strip()
        password = request.form.get("password", "")
        user = _find_user_by_identifier(identifier)

        if not user or not user.active or not verify_password(user, password):
            flash("Invalid credentials or account is deactivated.", "error")
            return render_template("admin_login.html"), 401

        if user.role not in {"admin", "super_admin"}:
            flash("This portal is restricted to authorized administrative staff.", "error")
            return render_template("admin_login.html"), 403

        user.last_login_at = datetime.now(timezone.utc)
        audit("login", "user", user.id, "Admin logged into Admin Portal", actor=user)
        db.session.commit()

        # Store independent admin session
        session["admin_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash("Welcome to Maria Tutor Administration Portal.", "success")
        return redirect(url_for("superadmin.dashboard" if user.role == "super_admin" else "admin.dashboard"))

    return render_template("admin_login.html")


@bp.route("/tutor/login", methods=["GET", "POST"])
@limiter.limit("20 per 10 minutes")
def tutor_login():
    tutor_uid = session.get("tutor_user_id")
    if tutor_uid:
        user = db.session.get(User, int(tutor_uid))
        if user and user.role == "tutor":
            return redirect(url_for("main.tutor_dashboard"))

    if request.method == "POST":
        identifier = request.form.get("identifier", "").strip()
        password = request.form.get("password", "")
        user = _find_user_by_identifier(identifier)

        if not user or not user.active or not verify_password(user, password):
            flash("Invalid credentials or account is deactivated.", "error")
            return render_template("tutor_login.html"), 401

        if user.role != "tutor":
            flash("This portal is reserved for registered tutors. Please use your CNIC, Tutor ID, or Email.", "error")
            return render_template("tutor_login.html"), 403

        user.last_login_at = datetime.now(timezone.utc)
        audit("login", "user", user.id, "Tutor logged into Tutor Portal", actor=user)
        db.session.commit()

        # Store independent tutor session
        session["tutor_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash("Welcome to the Tutor Portal.", "success")
        return redirect(url_for("main.tutor_dashboard"))

    return render_template("tutor_login.html")


@bp.route("/parent/login", methods=["GET", "POST"])
@limiter.limit("20 per 10 minutes")
def parent_login():
    parent_uid = session.get("parent_user_id")
    if parent_uid:
        user = db.session.get(User, int(parent_uid))
        if user and user.role == "parent":
            return redirect(url_for("main.parent_dashboard"))

    if request.method == "POST":
        identifier = request.form.get("identifier", "").strip()
        password = request.form.get("password", "")
        user = _find_user_by_identifier(identifier)

        if not user or not user.active or not verify_password(user, password):
            flash("Invalid credentials or account is deactivated.", "error")
            return render_template("parent_login.html"), 401

        if user.role != "parent":
            flash("This portal is reserved for parents and students.", "error")
            return render_template("parent_login.html"), 403

        user.last_login_at = datetime.now(timezone.utc)
        audit("login", "user", user.id, "Parent logged into Parent Portal", actor=user)
        db.session.commit()

        # Store independent parent session
        session["parent_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash("Welcome to the Parent Portal.", "success")
        return redirect(url_for("main.parent_dashboard"))

    return render_template("parent_login.html")


@bp.route("/login", methods=["GET", "POST"])
@limiter.limit("20 per 10 minutes")
def login():
    portal = request.args.get("portal")
    if portal == "admin":
        return redirect(url_for("auth.admin_login"))
    if portal == "tutor":
        return redirect(url_for("auth.tutor_login"))
    if portal == "parent":
        return redirect(url_for("auth.parent_login"))

    if request.method == "POST":
        identifier = request.form.get("identifier", "").strip()
        password = request.form.get("password", "")
        user = _find_user_by_identifier(identifier)

        if not user or not user.active or not verify_password(user, password):
            flash("Invalid credentials or account is deactivated.", "error")
            return render_template("login.html", portal=portal), 401

        user.last_login_at = datetime.now(timezone.utc)
        audit("login", "user", user.id, f"{user.role} logged in via login endpoint", actor=user)
        db.session.commit()

        if user.role in {"super_admin", "admin"}:
            session["admin_user_id"] = str(user.id)
            session["_user_id"] = str(user.id)
            login_user(user, remember=False, fresh=True)
            flash("Welcome to Administration Dashboard.", "success")
            return redirect(url_for("superadmin.dashboard" if user.role == "super_admin" else "admin.dashboard"))
        elif user.role == "tutor":
            session["tutor_user_id"] = str(user.id)
            session["_user_id"] = str(user.id)
            login_user(user, remember=False, fresh=True)
            flash("Welcome to Tutor Portal.", "success")
            return redirect(url_for("main.tutor_dashboard"))
        elif user.role == "parent":
            session["parent_user_id"] = str(user.id)
            session["_user_id"] = str(user.id)
            login_user(user, remember=False, fresh=True)
            flash("Welcome to Parent Portal.", "success")
            return redirect(url_for("main.parent_dashboard"))

    return render_template("login.html", portal=portal)


@bp.route("/quick-switch/<role>", methods=["GET", "POST"])
def quick_switch(role):
    """Instantly switch between demo accounts with role-scoped isolation."""
    role_map = {
        "super_admin": "admin@mariahome.com",
        "admin": "staff@mariahome.com",
        "tutor": "tutor@mariahome.com",
        "parent": "parent@mariahome.com",
    }
    target_email = role_map.get(role)
    if not target_email:
        flash("Invalid role specified for demo switch.", "error")
        return redirect(url_for("main.index"))

    user = User.query.filter(db.func.lower(User.email) == target_email.lower()).first()
    if not user:
        flash(f"Demo user for {role} not found in database. Run seed_burewala.py first.", "error")
        return redirect(url_for("main.index"))

    if user.role in {"super_admin", "admin"}:
        session["admin_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash(f"Switched to {user.role.replace('_', ' ').title()} demo account ({target_email}).", "success")
        return redirect(url_for("superadmin.dashboard" if user.role == "super_admin" else "admin.dashboard"))
    elif user.role == "tutor":
        session["tutor_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash(f"Switched to Tutor demo account ({target_email}).", "success")
        return redirect(url_for("main.tutor_dashboard"))
    elif user.role == "parent":
        session["parent_user_id"] = str(user.id)
        session["_user_id"] = str(user.id)
        login_user(user, remember=False, fresh=True)
        flash(f"Switched to Parent demo account ({target_email}).", "success")
        return redirect(url_for("main.parent_dashboard"))
    return redirect(url_for("main.index"))


@bp.route("/admin/logout", methods=["GET", "POST"])
def admin_logout():
    session.pop("admin_user_id", None)
    session.pop("_user_id", None)
    logout_user()
    flash("You have been logged out of the Administration Portal.", "success")
    return redirect(url_for("auth.admin_login"))


@bp.route("/tutor/logout", methods=["GET", "POST"])
def tutor_logout():
    session.pop("tutor_user_id", None)
    session.pop("_user_id", None)
    logout_user()
    flash("You have been logged out of the Tutor Portal.", "success")
    return redirect(url_for("auth.tutor_login"))


@bp.route("/parent/logout", methods=["GET", "POST"])
def parent_logout():
    session.pop("parent_user_id", None)
    session.pop("_user_id", None)
    logout_user()
    flash("You have been logged out of the Parent Portal.", "success")
    return redirect(url_for("auth.parent_login"))


@bp.route("/logout", methods=["GET", "POST"])
def logout():
    role = getattr(current_user, "role", None) if current_user.is_authenticated else None
    if role in {"admin", "super_admin"}:
        return redirect(url_for("auth.admin_logout"))
    if role == "tutor":
        return redirect(url_for("auth.tutor_logout"))
    if role == "parent":
        return redirect(url_for("auth.parent_logout"))
    session.pop("admin_user_id", None)
    session.pop("tutor_user_id", None)
    session.pop("parent_user_id", None)
    session.pop("_user_id", None)
    logout_user()
    flash("You have been logged out successfully.", "success")
    return redirect(url_for("main.index"))


@bp.route("/forgot-password", methods=["GET", "POST"])
@limiter.limit("4 per hour")
def forgot_password():
    if request.method == "POST":
        flash(
            "If your account exists in our records, an administrator will contact you with a secure password reset link.",
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
