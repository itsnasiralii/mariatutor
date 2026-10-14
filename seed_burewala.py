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
    SystemSetting,
    TutorProfile,
    User,
)
from services import ADMIN_PERMISSIONS, identifier_hash, make_password_hash


def seed_database(app=None):
    if app is None:
        from app import create_app
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
    
        # 6b. 45+ Common Pakistani Qualification Degrees
        pakistani_degrees = [
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
        for idx, deg in enumerate(pakistani_degrees, start=1):
            item = CatalogItem.query.filter_by(kind="qualification", name=deg).first()
            if not item:
                item = CatalogItem(kind="qualification", name=deg, sort_order=idx, active=True)
                db.session.add(item)
    
        # 6c. Daily Availability & Timings
        timing_slots = [
            "Daily 4:00 PM - 8:00 PM (Evening)",
            "After 5:00 PM - 9:00 PM (Night)",
            "Morning 8:00 AM - 1:00 PM",
            "Afternoon 1:00 PM - 5:00 PM",
            "Weekends Only (Saturday & Sunday)",
            "Flexible Timings (Mutual Agreement)",
        ]
        for idx, slot in enumerate(timing_slots, start=1):
            item = CatalogItem.query.filter_by(kind="timing", name=slot).first()
            if not item:
                item = CatalogItem(kind="timing", name=slot, sort_order=idx, active=True)
                db.session.add(item)
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
    
        # 7b. Dynamic Market Budget Settings (Default: Min 7,000 PKR, Max 10,000 PKR)
        min_setting = SystemSetting.query.filter_by(key="default_budget_min").first()
        if not min_setting:
            db.session.add(SystemSetting(key="default_budget_min", value="7000"))
        else:
            min_setting.value = "7000"
    
        max_setting = SystemSetting.query.filter_by(key="default_budget_max").first()
        if not max_setting:
            db.session.add(SystemSetting(key="default_budget_max", value="10000"))
        else:
            max_setting.value = "10000"
        print("Configured Marketplace Budget: PKR 7,000 - 10,000 / month")
    
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
                expected_fee_min=7000,
                expected_fee_max=10000,
                verification_status="Approved",
                approved_at=datetime.now(timezone.utc),
                catalog=selected_catalog,
            )
            tutor_user = User(
                identifier_hash=tutor_hash,
                identifier_type="cnic",
                cnic_hash=tutor_hash,
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
            tutor_user.cnic_hash = tutor_hash
            tutor_user.email = tutor_email
            tutor_user.password_hash = make_password_hash(tutor_pass)
            tutor_user.active = True
            if tutor_user.tutor:
                tutor_user.tutor.tutor_code = "T-BW001"
                tutor_user.tutor.expected_fee_min = 7000
                tutor_user.tutor.expected_fee_max = 10000
                tutor_user.tutor.verification_status = "Approved"
                tutor_user.tutor.approved_at = datetime.now(timezone.utc)
                tutor_user.tutor.catalog = selected_catalog
    
        def find_available_tutor_code(pref):
            existing = TutorProfile.query.filter_by(tutor_code=pref).first()
            if not existing:
                return pref
            for i in range(1, 100):
                cand = f"T-BW{i:03d}"
                if not TutorProfile.query.filter_by(tutor_code=cand).first():
                    return cand
            return f"T-BW999"
    
        # 8c-2. Approved Female Tutor: Miss Ayesha (Biology & Chemistry)
        ayesha_email = "ayesha.tutor@mariahome.com"
        ayesha_cnic = "36402-2345678-2"
        ayesha_hash = identifier_hash(ayesha_cnic, "cnic")
        ayesha_user = User.query.filter_by(email=ayesha_email).first() or User.query.filter_by(identifier_hash=ayesha_hash).first()
        ayesha_catalog = [
            area_objs["Satellite Town"],
            area_objs["Model Town"],
            subj_objs["Biology"],
            subj_objs["Chemistry"],
            subj_objs["General Science"],
            class_objs["9th Class (Matric Part 1)"],
            class_objs["10th Class (Matric Part 2)"],
            class_objs["FSc Pre-Medical"],
            mode_objs["Home Tuition (Tutor visits student)"],
        ]
        if not ayesha_user:
            ayesha_code = find_available_tutor_code("T-BW004")
            ayesha_prof = TutorProfile(
                tutor_code=ayesha_code,
                full_name="Miss Ayesha Siddiqua (Pre-Medical Specialist)",
                gender="Female",
                cnic_gender_hint="Female",
                qualification="M.Phil Chemistry & Biology (Bahauddin Zakariya University)",
                experience="6 years teaching Matric & FSc Pre-Medical female students in Satellite Town & Model Town",
                availability="Daily 3:00 PM - 7:00 PM (Afternoon/Evening)",
                expected_fee_min=7500,
                expected_fee_max=10000,
                verification_status="Approved",
                approved_at=datetime.now(timezone.utc),
                catalog=ayesha_catalog,
            )
            ayesha_user = User(
                identifier_hash=ayesha_hash,
                identifier_type="cnic",
                cnic_hash=ayesha_hash,
                email=ayesha_email,
                phone="0302-1234567",
                role="tutor",
                password_hash=make_password_hash("Tutor123"),
                active=True,
                tutor=ayesha_prof,
            )
            db.session.add(ayesha_user)
            print(f"Created Approved Tutor: Miss Ayesha ({ayesha_code})")
        else:
            ayesha_user.identifier_hash = ayesha_hash
            ayesha_user.cnic_hash = ayesha_hash
            ayesha_user.email = ayesha_email
            ayesha_user.password_hash = make_password_hash("Tutor123")
            ayesha_user.active = True
            if ayesha_user.tutor:
                ayesha_user.tutor.expected_fee_min = 7500
                ayesha_user.tutor.expected_fee_max = 10000
                ayesha_user.tutor.verification_status = "Approved"
                ayesha_user.tutor.approved_at = datetime.now(timezone.utc)
                ayesha_user.tutor.catalog = ayesha_catalog
    
        # 8c-3. Approved CS & Math Tutor: Engr. Bilal Ahmad
        bilal_email = "bilal.tutor@mariahome.com"
        bilal_cnic = "36402-3456789-1"
        bilal_hash = identifier_hash(bilal_cnic, "cnic")
        bilal_user = User.query.filter_by(email=bilal_email).first() or User.query.filter_by(identifier_hash=bilal_hash).first()
        bilal_catalog = [
            area_objs["College Road"],
            area_objs["Gulshan-e-Rehman"],
            subj_objs["Computer Science"],
            subj_objs["Mathematics"],
            class_objs["ICS (Computer Science)"],
            class_objs["10th Class (Matric Part 2)"],
            mode_objs["Home Tuition (Tutor visits student)"],
            mode_objs["Online Tuition"],
        ]
        if not bilal_user:
            bilal_code = find_available_tutor_code("T-BW005")
            bilal_prof = TutorProfile(
                tutor_code=bilal_code,
                full_name="Engr. Bilal Ahmad (ICS & Software Engineering)",
                gender="Male",
                cnic_gender_hint="Male",
                qualification="Bachelor of Science in Software Engineering (BSSE)",
                experience="5 years teaching Computer Science, ICS, and Mathematics in Burewala",
                availability="Daily Evening after 5:00 PM",
                expected_fee_min=7000,
                expected_fee_max=9500,
                verification_status="Approved",
                approved_at=datetime.now(timezone.utc),
                catalog=bilal_catalog,
            )
            bilal_user = User(
                identifier_hash=bilal_hash,
                identifier_type="cnic",
                cnic_hash=bilal_hash,
                email=bilal_email,
                phone="0303-7654321",
                role="tutor",
                password_hash=make_password_hash("Tutor123"),
                active=True,
                tutor=bilal_prof,
            )
            db.session.add(bilal_user)
            print(f"Created Approved Tutor: Engr. Bilal ({bilal_code})")
        else:
            bilal_user.identifier_hash = bilal_hash
            bilal_user.cnic_hash = bilal_hash
            bilal_user.email = bilal_email
            bilal_user.password_hash = make_password_hash("Tutor123")
            bilal_user.active = True
            if bilal_user.tutor:
                bilal_user.tutor.expected_fee_min = 7000
                bilal_user.tutor.expected_fee_max = 9500
                bilal_user.tutor.verification_status = "Approved"
                bilal_user.tutor.approved_at = datetime.now(timezone.utc)
                bilal_user.tutor.catalog = bilal_catalog
    
        # 8d. Demo Parent (parent@mariahome.com / CNIC: 36402-7654321-1 / Parent123)
        parent_email = "parent@mariahome.com"
        parent_cnic = "36402-7654321-1"
        parent_pass = "Parent123"
        parent_cnic_hash = identifier_hash(parent_cnic, "cnic")
        parent_email_hash = identifier_hash(parent_email, "email")
        parent_user = (
            User.query.filter_by(email=parent_email).first()
            or User.query.filter_by(identifier_hash=parent_cnic_hash).first()
            or User.query.filter_by(identifier_hash=parent_email_hash).first()
        )
        if not parent_user:
            parent_user = User(
                identifier_hash=parent_cnic_hash,
                identifier_type="cnic",
                cnic_hash=parent_cnic_hash,
                email=parent_email,
                phone="0300-7654321",
                role="parent",
                password_hash=make_password_hash(parent_pass),
                active=True,
                parent=ParentProfile(full_name="Chaudhry Tariq (Parent)"),
            )
            db.session.add(parent_user)
            print("Created Demo Parent: parent@mariahome.com / CNIC: 36402-7654321-1 / Parent123")
        else:
            parent_user.identifier_hash = parent_cnic_hash
            parent_user.identifier_type = "cnic"
            parent_user.cnic_hash = parent_cnic_hash
            parent_user.email = parent_email
            parent_user.password_hash = make_password_hash(parent_pass)
            parent_user.active = True
            if not parent_user.parent:
                parent_user.parent = ParentProfile(full_name="Chaudhry Tariq (Parent)")
    
        db.session.commit()
        print("Seeding completed successfully!")

if __name__ == "__main__":
    seed_database()
