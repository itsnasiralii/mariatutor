import unittest
from app import create_app
from extensions import db
from models import User

class MultiTabSessionTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()

    def test_unauthenticated_redirects(self):
        # Admin protected route -> redirects to admin_login
        res = self.client.get('/admin/dashboard', follow_redirects=False)
        self.assertEqual(res.status_code, 302)
        self.assertIn('/admin/login', res.headers.get('Location', ''))

        # Tutor protected route -> redirects to tutor_login
        res = self.client.get('/tutor/dashboard', follow_redirects=False)
        self.assertEqual(res.status_code, 302)
        self.assertIn('/tutor/login', res.headers.get('Location', ''))

        # Parent protected route -> redirects to parent_login
        res = self.client.get('/parent/dashboard', follow_redirects=False)
        self.assertEqual(res.status_code, 302)
        self.assertIn('/parent/login', res.headers.get('Location', ''))

    def test_simultaneous_multi_tab_access(self):
        # We use a single test_client instance with cookie persistence,
        # simulating a single user browser with multiple tabs!

        # 1. Tab 1: Log in as Admin
        admin_res = self.client.post('/admin/login', data={
            'identifier': 'admin@mariahome.com',
            'password': 'Admin123'
        }, follow_redirects=True)
        self.assertEqual(admin_res.status_code, 200)
        self.assertIn(b'Super Admin', admin_res.data)

        # 2. Tab 2: Log in as Tutor (in the same browser session!)
        tutor_res = self.client.post('/tutor/login', data={
            'identifier': 'tutor@mariahome.com',
            'password': 'Tutor123'
        }, follow_redirects=True)
        self.assertEqual(tutor_res.status_code, 200)
        self.assertIn(b'Tutor', tutor_res.data)

        # 3. Tab 3: Log in as Parent (in the same browser session!)
        parent_res = self.client.post('/parent/login', data={
            'identifier': 'parent@mariahome.com',
            'password': 'Parent123'
        }, follow_redirects=True)
        self.assertEqual(parent_res.status_code, 200)
        self.assertIn(b'Parent', parent_res.data)

        # 4. Now, simulate switching between tabs without re-logging in!
        # Refresh Tab 1: Admin Dashboard
        admin_tab = self.client.get('/admin/dashboard')
        self.assertEqual(admin_tab.status_code, 200, "Admin tab should remain logged in!")
        self.assertIn(b'Admin', admin_tab.data)

        # Refresh Tab 2: Tutor Dashboard
        tutor_tab = self.client.get('/tutor/dashboard')
        self.assertEqual(tutor_tab.status_code, 200, "Tutor tab should remain logged in!")

        # Refresh Tab 3: Parent Dashboard
        parent_tab = self.client.get('/parent/dashboard')
        self.assertEqual(parent_tab.status_code, 200, "Parent tab should remain logged in!")

        # 5. Accessing Super Admin with admin session
        superadmin_tab = self.client.get('/superadmin/dashboard')
        self.assertEqual(superadmin_tab.status_code, 200, "Super admin should be accessible with admin session!")

        print("\n>>> ALL MULTI-TAB SIMULTANEOUS SESSIONS PASSED PERFECTLY! <<<")

if __name__ == '__main__':
    unittest.main()
