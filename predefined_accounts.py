"""Ensure predefined production accounts exist and have correct passwords and profiles."""
import json
from datetime import datetime, timezone
from extensions import db
from models import User, TutorProfile, ParentProfile, CatalogItem
from services import make_password_hash, identifier_hash, ADMIN_PERMISSIONS


def get_available_tutor_code(preferred="T-BW002"):
    existing = TutorProfile.query.filter_by(tutor_code=preferred).first()
    if not existing:
        return preferred
    for i in range(2, 200):
        code = f"T-BW{i:03d}"
        if not TutorProfile.query.filter_by(tutor_code=code).first():
            return code
    return "T-BW999"


def ensure_predefined_accounts():
    """Guarantee that the 4 predefined live accounts requested by the administrator exist.
    
    1. Super Admin : nasir@mariatutor.com  | pw: 123
    2. Staff Admin : staff@mariatutor.com  | pw: 123
    3. Tutor       : tutor@mariatutor.com  | pw: 123 (CNIC: 36402-1111111-1)
    4. Parent      : parent@mariatutor.com | pw: 123 (CNIC: 36402-2222222-1)
    """
    try:
        # ══ 1. SUPER ADMIN: nasir@mariatutor.com / 123 ══
        sa_email = "nasir@mariatutor.com"
        sa_hash = identifier_hash(sa_email, "email")
        sa_user = User.query.filter(
            (db.func.lower(User.email) == sa_email) | (User.identifier_hash == sa_hash)
        ).first()

        if not sa_user:
            sa_user = User(
                identifier_hash=sa_hash,
                identifier_type="email",
                email=sa_email,
                phone="0300-0000001",
                role="super_admin",
                password_hash=make_password_hash("123"),
                active=True,
                permissions_json="{}",
            )
            db.session.add(sa_user)
        else:
            sa_user.email = sa_email
            sa_user.identifier_hash = sa_hash
            sa_user.role = "super_admin"
            sa_user.password_hash = make_password_hash("123")
            sa_user.active = True

        # ══ 2. STAFF ADMIN: staff@mariatutor.com / 123 ══
        staff_email = "staff@mariatutor.com"
        staff_hash = identifier_hash(staff_email, "email")
        full_perms = json.dumps({p: True for p in sorted(ADMIN_PERMISSIONS)})
        staff_user = User.query.filter(
            (db.func.lower(User.email) == staff_email) | (User.identifier_hash == staff_hash)
        ).first()

        if not staff_user:
            staff_user = User(
                identifier_hash=staff_hash,
                identifier_type="email",
                email=staff_email,
                phone="0300-0000002",
                role="admin",
                password_hash=make_password_hash("123"),
                active=True,
                permissions_json=full_perms,
            )
            db.session.add(staff_user)
        else:
            staff_user.email = staff_email
            staff_user.identifier_hash = staff_hash
            staff_user.role = "admin"
            staff_user.password_hash = make_password_hash("123")
            staff_user.permissions_json = full_perms
            staff_user.active = True

        # ══ 3. TUTOR: tutor@mariatutor.com / 123 ══
        tutor_email = "tutor@mariatutor.com"
        tutor_cnic = "36402-1111111-1"
        tutor_email_hash = identifier_hash(tutor_email, "email")
        tutor_cnic_hash = identifier_hash(tutor_cnic, "cnic")
        tutor_user = User.query.filter(
            (db.func.lower(User.email) == tutor_email)
            | (User.identifier_hash == tutor_email_hash)
            | (User.cnic_hash == tutor_cnic_hash)
        ).first()

        catalog_items = CatalogItem.query.filter(CatalogItem.active.is_(True)).all()

        if not tutor_user:
            code = get_available_tutor_code("T-BW002")
            tutor_prof = TutorProfile(
                tutor_code=code,
                full_name="Senior Educator (Burewala)",
                gender="Male",
                cnic_gender_hint="Male",
                qualification="Master of Science (MSc)",
                experience="7 years teaching Matric & FSc students across Burewala",
                availability="Daily 4:00 PM - 8:00 PM (Evening)",
                expected_fee_min=7000,
                expected_fee_max=10000,
                verification_status="Approved",
                approved_at=datetime.now(timezone.utc),
                catalog=catalog_items[:12] if catalog_items else [],
            )
            tutor_user = User(
                identifier_hash=tutor_email_hash,
                identifier_type="email",
                cnic_hash=tutor_cnic_hash,
                email=tutor_email,
                phone="0301-1111111",
                role="tutor",
                password_hash=make_password_hash("123"),
                active=True,
                tutor=tutor_prof,
            )
            db.session.add(tutor_user)
        else:
            tutor_user.email = tutor_email
            tutor_user.password_hash = make_password_hash("123")
            tutor_user.active = True
            if not tutor_user.cnic_hash:
                tutor_user.cnic_hash = tutor_cnic_hash
            if not tutor_user.tutor:
                code = get_available_tutor_code("T-BW002")
                tutor_user.tutor = TutorProfile(
                    tutor_code=code,
                    full_name="Senior Educator (Burewala)",
                    gender="Male",
                    cnic_gender_hint="Male",
                    qualification="Master of Science (MSc)",
                    experience="7 years teaching Matric & FSc students across Burewala",
                    availability="Daily 4:00 PM - 8:00 PM (Evening)",
                    expected_fee_min=7000,
                    expected_fee_max=10000,
                    verification_status="Approved",
                    approved_at=datetime.now(timezone.utc),
                    catalog=catalog_items[:12] if catalog_items else [],
                )
            else:
                tutor_user.tutor.verification_status = "Approved"

        # ══ 4. PARENT: parent@mariatutor.com / 123 ══
        parent_email = "parent@mariatutor.com"
        parent_cnic = "36402-2222222-1"
        parent_email_hash = identifier_hash(parent_email, "email")
        parent_cnic_hash = identifier_hash(parent_cnic, "cnic")
        parent_user = User.query.filter(
            (db.func.lower(User.email) == parent_email)
            | (User.identifier_hash == parent_email_hash)
            | (User.cnic_hash == parent_cnic_hash)
        ).first()

        if not parent_user:
            parent_prof = ParentProfile(
                full_name="Parent Account (Burewala Family)",
            )
            parent_user = User(
                identifier_hash=parent_email_hash,
                identifier_type="email",
                cnic_hash=parent_cnic_hash,
                email=parent_email,
                phone="0300-2222222",
                role="parent",
                password_hash=make_password_hash("123"),
                active=True,
                parent=parent_prof,
            )
            db.session.add(parent_user)
        else:
            parent_user.email = parent_email
            parent_user.password_hash = make_password_hash("123")
            parent_user.active = True
            if not parent_user.cnic_hash:
                parent_user.cnic_hash = parent_cnic_hash
            if not parent_user.parent:
                parent_user.parent = ParentProfile(
                    full_name="Parent Account (Burewala Family)",
                )

        db.session.commit()
        return True
    except Exception as e:
        db.session.rollback()
        print("ensure_predefined_accounts exception:", e)
        return False
