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
    login_manager.login_message = "Please sign in to access this page."
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

    @login_manager.unauthorized_handler
    def unauthorized():
        path = request.path
        if path.startswith("/admin") or path.startswith("/superadmin"):
            flash("Please log in to access the Administration Portal.", "warning")
            return redirect(url_for("auth.admin_login", next=request.path))
        if path.startswith("/tutor"):
            flash("Please log in to access the Tutor Portal.", "warning")
            return redirect(url_for("auth.tutor_login", next=request.path))
        if path.startswith("/parent"):
            flash("Please log in to access the Parent Portal.", "warning")
            return redirect(url_for("auth.parent_login", next=request.path))
        return redirect(url_for("auth.login", next=request.path))

    @app.before_request
    def route_scoped_session():
        path = request.path
        if path.startswith("/admin") or path.startswith("/superadmin"):
            admin_uid = session.get("admin_user_id")
            if admin_uid:
                session["_user_id"] = str(admin_uid)
            else:
                session.pop("_user_id", None)
        elif path.startswith("/tutor"):
            tutor_uid = session.get("tutor_user_id")
            if tutor_uid:
                session["_user_id"] = str(tutor_uid)
            else:
                session.pop("_user_id", None)
        elif path.startswith("/parent"):
            parent_uid = session.get("parent_user_id")
            if parent_uid:
                session["_user_id"] = str(parent_uid)
            else:
                session.pop("_user_id", None)

    @app.before_request
    def protect_state_changes():
        if request.method not in {"POST", "PUT", "PATCH", "DELETE"}:
            return
        if app.config.get("WTF_CSRF_ENABLED") is False:
            return
        expected = session.get("csrf_token", "")
        supplied = request.form.get("csrf_token", "") or request.headers.get("X-CSRF-Token", "")
        if not expected or not supplied or not secrets.compare_digest(expected, supplied):
            abort(400, description="Security token expired. Please refresh the page and try again.")

    @app.context_processor
    def inject_template_helpers():
        def csrf_token():
            if "csrf_token" not in session:
                session["csrf_token"] = secrets.token_urlsafe(32)
            return session["csrf_token"]

        from services import get_budget_settings

        return {
            "csrf_token": csrf_token,
            "current_user": current_user,
            "has_permission": has_permission,
            "market_budget": get_budget_settings(),
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
        path = request.path
        if path.startswith("/admin") or path.startswith("/superadmin"):
            flash("Access restricted to authorized Admin and Staff accounts only.", "error")
            return redirect(url_for("auth.admin_login", next=request.path))
        if path.startswith("/tutor"):
            flash("Access restricted to registered Tutor accounts only.", "error")
            return redirect(url_for("auth.tutor_login", next=request.path))
        if path.startswith("/parent"):
            flash("Access restricted to registered Parent accounts only.", "error")
            return redirect(url_for("auth.parent_login", next=request.path))
        flash("Please log in to continue.", "error")
        return redirect(url_for("auth.login", next=request.path))

    @app.errorhandler(429)
    def ratelimit_exceeded(_error):
        flash("Too many requests received. Please wait 1 minute before trying again.", "warning")
        return redirect(request.referrer or url_for("main.dashboard")), 429

    @app.errorhandler(404)
    def not_found(_error):
        flash("The requested page could not be found.", "error")
        if current_user.is_authenticated:
            return redirect(url_for("main.dashboard")), 404
        return redirect(url_for("main.index")), 404

    @app.errorhandler(413)
    def upload_too_large(_error):
        flash("Uploaded file must be 8 MB or smaller.", "error")
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
        # Auto-seed Burewala catalogs, areas, subjects, and admin if database is fresh
        from models import CatalogItem
        if not CatalogItem.query.first():
            try:
                from seed_burewala import seed_database
                seed_database(app)
            except Exception as e:
                app.logger.warning("Auto-seed on startup: %s", e)

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
