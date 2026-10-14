"""Super Admin governance routes (/superadmin/*): catalogs, adjacency graph, commissions, RBAC, audit, health."""
import json
import os
from flask import Blueprint, abort, current_app, flash, redirect, render_template, request, session, url_for
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
    get_budget_settings,
    identifier_hash,
    lead_event,
    make_password_hash,
    password_ok,
    require_roles,
    set_budget_settings,
)

bp = Blueprint("superadmin", __name__, url_prefix="/superadmin")
ALLOWED_KINDS = {"city", "area", "block", "subject", "class", "mode", "qualification", "timing"}


@bp.route("/")
def superadmin_root():
    admin_uid = session.get("admin_user_id")
    if admin_uid:
        user = db.session.get(User, int(admin_uid))
        if user and user.role == "super_admin":
            return redirect(url_for("superadmin.dashboard"))
        elif user and user.role == "admin":
            return redirect(url_for("admin.dashboard"))
    return redirect(url_for("auth.admin_login"))


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
    flash("User account status updated successfully.", "success")
    return redirect(url_for("superadmin.users"))


@bp.post("/users/<int:user_id>/delete")
@login_required
@require_roles("super_admin")
def delete_user(user_id):
    from admin_routes import delete_user_account
    return delete_user_account(user_id)


@bp.route("/admins", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def admins():
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        if not email or "@" not in email or not password_ok(password):
            flash("Please enter a valid email and a password adhering to security policy.", "error")
        elif User.query.filter_by(identifier_hash=identifier_hash(email, "email")).first():
            flash("An account with this email address already exists.", "error")
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
            flash("Admin account created successfully.", "success")
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
    flash("Admin permissions updated successfully.", "success")
    return redirect(url_for("superadmin.admins"))


@bp.route("/settings/catalog", methods=["GET", "POST"])
@login_required
@require_roles("super_admin", "admin")
def catalog_settings():
    if request.method == "POST":
        action = request.form.get("action", "create")
        if action == "create":
            kind = request.form.get("kind", "")
            name = request.form.get("name", "").strip()[:100]
            parent_raw = request.form.get("parent_id", "")
            parent = db.session.get(CatalogItem, int(parent_raw)) if parent_raw.isdigit() else None
            if kind not in ALLOWED_KINDS or not name:
                flash("Please verify catalog type and name.", "error")
            elif parent and parent.kind not in {"city", "area"}:
                flash("Please select an active parent city/area.", "error")
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
                flash("Catalog option created successfully.", "success")
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
            flash("Catalog option updated successfully.", "success")
        elif action == "adjacency":
            a, b = request.form.get("area_id", ""), request.form.get("nearby_area_id", "")
            first = db.session.get(CatalogItem, int(a)) if a.isdigit() else None
            second = db.session.get(CatalogItem, int(b)) if b.isdigit() else None
            if not first or not second or first.kind != "area" or second.kind != "area" or first.id == second.id:
                flash("Please choose two distinct active areas.", "error")
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
        elif action == "update_budget":
            try:
                min_b = int(request.form.get("default_budget_min", 7000))
                max_b = int(request.form.get("default_budget_max", 10000))
            except (ValueError, TypeError):
                min_b, max_b = 7000, 10000
            if min_b < 1000 or max_b < min_b:
                flash("Invalid budget range. Minimum budget cannot exceed maximum budget.", "error")
            else:
                set_budget_settings(min_b, max_b, actor=current_user)
                flash(f"Marketplace tuition budget updated: PKR {min_b:,} to PKR {max_b:,} per month. Updated across all parent & tutor forms.", "success")
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
        budget_settings=get_budget_settings(),
    )


@bp.post("/settings/adjacency/<int:link_id>/delete")
@login_required
@require_roles("super_admin", "admin")
def delete_adjacency(link_id):
    link = db.get_or_404(AreaAdjacency, link_id)
    audit("delete_area_adjacency", "area_adjacency", link.id, "Removed nearby-area relationship")
    db.session.delete(link)
    db.session.commit()
    flash("Adjacent area link deleted successfully.", "success")
    return redirect(url_for("superadmin.catalog_settings"))


@bp.route("/settings/commission", methods=["GET", "POST"])
@login_required
@require_roles("super_admin")
def commission_settings():
    if request.method == "POST":
        action = request.form.get("action", "save_global_rule")
        
        # ══ 1. INDIVIDUAL CASE COMMISSION OVERRIDE ══
        if action == "update_case_commission":
            request_id = request.form.get("request_id")
            req = db.session.get(TuitionRequest, int(request_id)) if request_id and request_id.isdigit() else None
            if not req:
                flash("Tuition request case not found.", "error")
                return redirect(url_for("superadmin.commission_settings"))
            
            c_type = request.form.get("commission_type", "percent")
            if c_type not in {"percent", "fixed"}:
                c_type = "percent"
            try:
                c_val = int(float(request.form.get("commission_value", "25")))
            except (ValueError, TypeError):
                c_val = 25
            
            if c_val < 0 or (c_type == "percent" and c_val > 100):
                flash("Please enter a valid commission percentage (0–100%) or positive amount.", "error")
                return redirect(url_for("superadmin.commission_settings"))
            
            old_str = f"{req.commission_snapshot_value}{'%' if req.commission_snapshot_type == 'percent' else ' PKR'}" if req.commission_snapshot_value is not None else "Global Default"
            req.commission_snapshot_type = c_type
            req.commission_snapshot_value = c_val
            new_str = f"{c_val}{'%' if c_type == 'percent' else ' PKR'}"
            
            lead_event(req, "Commission Rate Adjusted", f"Super Admin set custom commission rate: {new_str} (was {old_str})", private=True, actor=current_user)
            audit("update_case_commission", "tuition_request", req.public_ref, f"Commission rate updated to {new_str}")
            db.session.commit()
            flash(f"Commission rate for Case {req.public_ref} successfully updated to {new_str}.", "success")
            return redirect(url_for("superadmin.commission_settings"))
        
        # ══ 2. GLOBAL DEFAULT COMMISSION RULE ══
        else:
            kind = request.form.get("rule_type", "")
            if kind not in {"fixed", "percent"}:
                abort(400)
            try:
                value = int(float(request.form.get("value", "")))
            except ValueError:
                value = -1
            if value < 0 or (kind == "percent" and value > 100):
                flash("Please provide a valid commission rate or fixed amount.", "error")
                return redirect(url_for("superadmin.commission_settings"))
            name = request.form.get("name", "").strip()[:80] or (f"Default {value}%" if kind == "percent" else f"Fixed PKR {value}")
            CommissionRule.query.update({"active": False})
            rule = CommissionRule(rule_type=kind, value=value, name=name, active=True)
            db.session.add(rule)
            audit("create_commission_rule", "commission_rule", name, f"{kind} rule configured")
            db.session.commit()
            flash("Global default commission rule saved successfully. New placements will inherit this active rule.", "success")
            return redirect(url_for("superadmin.commission_settings"))

    # GET: Prepare rules, active rate, and all tuition cases
    rules = CommissionRule.query.order_by(CommissionRule.effective_at.desc()).all()
    active_rule = CommissionRule.query.filter_by(active=True).order_by(CommissionRule.effective_at.desc()).first()
    active_rate = active_rule.value if active_rule else 25
    active_type = active_rule.rule_type if active_rule else "percent"
    
    # All cases ordered newest first
    requests_list = TuitionRequest.query.order_by(TuitionRequest.created_at.desc()).all()
    
    raw_comm = db.session.query(
        func.coalesce(func.sum(PaymentEntry.platform_commission_pkr), 0)
    ).scalar()
    total_commission_collected = int(raw_comm or 0)
    assigned_tutors_count = sum(1 for r in requests_list if r.selected_tutor)

    return render_template(
        "commission_settings.html",
        rules=rules,
        active_rule=active_rule,
        active_rate=active_rate,
        active_type=active_type,
        requests=requests_list,
        total_commission_collected=total_commission_collected,
        assigned_tutors_count=assigned_tutors_count,
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
    flash("Review moderation updated successfully.", "success")
    return redirect(url_for("superadmin.reviews"))
