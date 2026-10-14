import sys
from app import create_app
from models import User, TutorProfile, TuitionRequest, CatalogItem
from services import verify_password, identifier_hash

app = create_app({"TESTING": True, "WTF_CSRF_ENABLED": False})

print("=" * 60)
print("VERIFYING BUREWALA TUTOR MARKETPLACE (ROOT)")
print("=" * 60)

with app.app_context():
    users = User.query.all()
    print(f"Total registered users: {len(users)}")
    for u in users:
        print(f"  - [{u.role.upper()}]: email={u.email}, id_type={u.identifier_type}, active={u.active}")

    # Check Burewala catalog
    areas = CatalogItem.query.filter_by(kind="area").all()
    subjects = CatalogItem.query.filter_by(kind="subject").all()
    classes = CatalogItem.query.filter_by(kind="class").all()
    modes = CatalogItem.query.filter_by(kind="mode").all()
    print(f"\nCatalog: {len(areas)} areas, {len(subjects)} subjects, {len(classes)} classes, {len(modes)} modes")

# Test Public Routes
print("\n--- Testing Public Routes ---")
with app.test_client() as client:
    for route in ["/", "/login", "/register/tutor", "/register/parent", "/forgot-password", "/privacy"]:
        res = client.get(route)
        print(f"  GET {route:20} -> Status {res.status_code}")
        assert res.status_code == 200, f"Route {route} failed with {res.status_code}"

# Test Login with all credentials
print("\n--- Testing Login Credentials ---")
credentials_to_test = [
    ("admin@mariahome.com", "Admin123", "/superadmin/dashboard"),
    ("staff@mariahome.com", "Staff123", "/admin/dashboard"),
    ("36402-1234567-1", "Tutor123", "/tutor/dashboard"),
    ("tutor@mariahome.com", "Tutor123", "/tutor/dashboard"),
    ("T-BW001", "Tutor123", "/tutor/dashboard"),
    ("parent@mariahome.com", "Parent123", "/parent/dashboard"),
]

for cred, pw, expected_dest in credentials_to_test:
    with app.test_client() as c:
        res = c.post("/login", data={"identifier": cred, "password": pw}, follow_redirects=True)
        current_path = res.request.path
        status_ok = (expected_dest in current_path)
        print(f"  Login '{cred:25}' -> Status {res.status_code}, Final: {current_path} [{'PASS' if status_ok else 'FAIL'}]")
        if not status_ok:
            print(f"    Failed details: data={res.data.decode('utf-8', errors='ignore')[:300]}")
            sys.exit(1)

# Test authenticated portal pages
print("\n--- Testing Portal Dashboards ---")
with app.test_client() as tutor_client:
    tutor_client.post("/login", data={"identifier": "tutor@mariahome.com", "password": "Tutor123"})
    res = tutor_client.get("/tutor/dashboard")
    print(f"  GET /tutor/dashboard      -> Status {res.status_code} [{'PASS' if res.status_code == 200 else 'FAIL'}]")
    assert res.status_code == 200

with app.test_client() as admin_client:
    admin_client.post("/login", data={"identifier": "staff@mariahome.com", "password": "Staff123"})
    res = admin_client.get("/admin/dashboard")
    print(f"  GET /admin/dashboard      -> Status {res.status_code} [{'PASS' if res.status_code == 200 else 'FAIL'}]")
    assert res.status_code == 200

with app.test_client() as superadmin_client:
    superadmin_client.post("/login", data={"identifier": "admin@mariahome.com", "password": "Admin123"})
    res = superadmin_client.get("/superadmin/dashboard")
    print(f"  GET /superadmin/dashboard -> Status {res.status_code} [{'PASS' if res.status_code == 200 else 'FAIL'}]")
    assert res.status_code == 200

with app.test_client() as parent_client:
    parent_client.post("/login", data={"identifier": "parent@mariahome.com", "password": "Parent123"})
    res = parent_client.get("/parent/dashboard")
    print(f"  GET /parent/dashboard     -> Status {res.status_code} [{'PASS' if res.status_code == 200 else 'FAIL'}]")
    assert res.status_code == 200

print("\nALL VERIFICATIONS PASSED SUCCESSFULLY!")
