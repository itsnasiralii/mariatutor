"""Create or update ready-to-test Demo Parent and Demo Tutor accounts."""
from app import create_app
from extensions import db
from models import User, ParentProfile, TutorProfile, CatalogItem
from services import identifier_hash, make_password_hash

app = create_app()

with app.app_context():
    # ── 1. DEMO PARENT ───────────────────────────────────────
    parent_email = "parent@mariahome.com"
    parent_pass = "Parent123"
    parent_hash = identifier_hash(parent_email, "email")
    
    parent_user = User.query.filter_by(email=parent_email).first()
    if not parent_user:
        parent_user = User.query.filter_by(identifier_hash=parent_hash).first()

    if not parent_user:
        parent_prof = ParentProfile(full_name="Chaudhry Tariq (Parent)")
        parent_user = User(
            identifier_hash=parent_hash,
            identifier_type="email",
            email=parent_email,
            phone="0300-7654321",
            role="parent",
            password_hash=make_password_hash(parent_pass),
            active=True,
            parent=parent_prof
        )
        db.session.add(parent_user)
        print("Created Demo Parent: parent@mariahome.com / Parent123")
    else:
        parent_user.password_hash = make_password_hash(parent_pass)
        parent_user.active = True
        if not parent_user.parent:
            parent_user.parent = ParentProfile(full_name="Chaudhry Tariq (Parent)")
        print("Updated Demo Parent: parent@mariahome.com / Parent123")

    # ── 2. DEMO TUTOR ────────────────────────────────────────
    tutor_email = "tutor@mariahome.com"
    tutor_cnic = "36402-1234567-1"
    tutor_pass = "Tutor123"
    tutor_hash = identifier_hash(tutor_cnic, "cnic")
    
    tutor_user = User.query.filter_by(email=tutor_email).first()
    if not tutor_user:
        tutor_user = User.query.filter_by(identifier_hash=tutor_hash).first()

    # Load some areas, subjects, classes, modes
    areas = CatalogItem.query.filter(CatalogItem.kind == "area", CatalogItem.name.in_(["Satellite Town", "College Road", "Model Town"])).all()
    subjects = CatalogItem.query.filter(CatalogItem.kind == "subject", CatalogItem.name.in_(["Mathematics", "Physics"])).all()
    classes = CatalogItem.query.filter(CatalogItem.kind == "class", CatalogItem.name.in_(["10th Class (Matric Part 2)", "FSc Pre-Engineering"])).all()
    modes = CatalogItem.query.filter(CatalogItem.kind == "mode").all()
    all_selected = areas + subjects + classes + modes

    if not tutor_user:
        tutor_prof = TutorProfile(
            tutor_code="T-BUR001",
            full_name="Sir Usman (Math & Physics Specialist)",
            gender="Male",
            cnic_gender_hint="Male",
            qualification="M.Sc Applied Mathematics (Punjab University)",
            experience="7 years teaching Matric & FSc students in Burewala",
            availability="Mon-Sat, 4:00 PM to 8:00 PM",
            expected_fee_min=10000,
            expected_fee_max=18000,
            verification_status="Approved",
            catalog=all_selected
        )
        tutor_user = User(
            identifier_hash=tutor_hash,
            identifier_type="cnic",
            email=tutor_email,
            phone="0301-9876543",
            role="tutor",
            password_hash=make_password_hash(tutor_pass),
            active=True,
            tutor=tutor_prof
        )
        db.session.add(tutor_user)
        print("Created Demo Tutor: tutor@mariahome.com (CNIC: 36402-1234567-1) / Tutor123")
    else:
        tutor_user.password_hash = make_password_hash(tutor_pass)
        tutor_user.active = True
        if tutor_user.tutor:
            tutor_user.tutor.verification_status = "Approved"
            tutor_user.tutor.catalog = all_selected
        print("Updated Demo Tutor: tutor@mariahome.com (CNIC: 36402-1234567-1) / Tutor123")

    db.session.commit()
    print("Demo credentials ready!")
