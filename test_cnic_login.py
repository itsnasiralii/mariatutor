"""Test suite verifying CNIC login for Parents and Tutors, normalization, and auto-formatting."""
import unittest
from app import create_app
from extensions import db
from models import User
from services import normalize_cnic, identifier_hash


class TestCNICLoginAndNormalization(unittest.TestCase):
    def setUp(self):
        self.app = create_app({"TESTING": True, "WTF_CSRF_ENABLED": False})
        self.client = self.app.test_client()

    def test_normalize_cnic(self):
        # 13 digits with hyphens
        self.assertEqual(normalize_cnic("36402-1234567-1"), "36402-1234567-1")
        # 13 digits with slashes
        self.assertEqual(normalize_cnic("36402/1234567/1"), "36402-1234567-1")
        # 13 raw digits
        self.assertEqual(normalize_cnic("3640212345671"), "36402-1234567-1")
        # 13 digits with spaces
        self.assertEqual(normalize_cnic(" 36402 1234567 1 "), "36402-1234567-1")

    def test_parent_login_via_cnic_standard(self):
        resp = self.client.post(
            "/parent/login",
            data={"identifier": "36402-7654321-1", "password": "Parent123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/parent/dashboard", resp.headers["Location"])

    def test_parent_login_via_cnic_raw_digits(self):
        resp = self.client.post(
            "/parent/login",
            data={"identifier": "3640276543211", "password": "Parent123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/parent/dashboard", resp.headers["Location"])

    def test_parent_login_via_cnic_slashes(self):
        resp = self.client.post(
            "/parent/login",
            data={"identifier": "36402/7654321/1", "password": "Parent123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/parent/dashboard", resp.headers["Location"])

    def test_parent_login_via_email(self):
        resp = self.client.post(
            "/parent/login",
            data={"identifier": "parent@mariahome.com", "password": "Parent123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/parent/dashboard", resp.headers["Location"])

    def test_tutor_login_via_cnic(self):
        resp = self.client.post(
            "/tutor/login",
            data={"identifier": "36402-1234567-1", "password": "Tutor123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/tutor/dashboard", resp.headers["Location"])

    def test_tutor_login_via_cnic_raw(self):
        resp = self.client.post(
            "/tutor/login",
            data={"identifier": "3640212345671", "password": "Tutor123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/tutor/dashboard", resp.headers["Location"])

    def test_tutor_login_via_code(self):
        resp = self.client.post(
            "/tutor/login",
            data={"identifier": "T-BW001", "password": "Tutor123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/tutor/dashboard", resp.headers["Location"])

    def test_admin_login_via_email(self):
        resp = self.client.post(
            "/admin/login",
            data={"identifier": "admin@mariahome.com", "password": "Admin123"},
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)
        self.assertIn("/superadmin/dashboard", resp.headers["Location"])

    def test_register_parent_with_cnic_and_login(self):
        import random
        mid = f"{random.randint(1000000, 9999999)}"
        new_cnic = f"36402-{mid}-1"
        email = f"parent_{mid}@example.com"
        resp = self.client.post(
            "/register/parent",
            data={
                "full_name": "Test Father Burewala",
                "cnic": new_cnic,
                "email": email,
                "phone": "0300-1122334",
                "password": "Password123",
            },
            follow_redirects=False,
        )
        self.assertEqual(resp.status_code, 302)

        # Log out
        self.client.get("/parent/logout")

        # Log in with CNIC
        resp2 = self.client.post(
            "/parent/login",
            data={"identifier": new_cnic, "password": "Password123"},
            follow_redirects=False,
        )
        self.assertEqual(resp2.status_code, 302)
        self.assertIn("/parent/dashboard", resp2.headers["Location"])

        # Log out
        self.client.get("/parent/logout")

        # Log in with Email
        resp3 = self.client.post(
            "/parent/login",
            data={"identifier": email, "password": "Password123"},
            follow_redirects=False,
        )
        self.assertEqual(resp3.status_code, 302)
        self.assertIn("/parent/dashboard", resp3.headers["Location"])

        # Clean up test user
        with self.app.app_context():
            u = User.query.filter_by(email=email).first()
            if u:
                db.session.delete(u)
                db.session.commit()


if __name__ == "__main__":
    unittest.main()
