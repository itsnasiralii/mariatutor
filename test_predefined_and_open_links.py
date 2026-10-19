import unittest
from app import create_app
from extensions import db
from models import User
from predefined_accounts import ensure_predefined_accounts

class PredefinedAndOpenLinksTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()
        with self.app.app_context():
            ensure_predefined_accounts()

    def test_01_predefined_accounts_login(self):
        # 1. Super Admin: nasir@mariatutor.com / 123
        c1 = self.app.test_client()
        res1 = c1.post('/admin/login', data={'identifier': 'nasir@mariatutor.com', 'password': '123'}, follow_redirects=False)
        self.assertEqual(res1.status_code, 302)
        self.assertIn('/superadmin/dashboard', res1.headers.get('Location', ''))

        # 2. Staff Admin: staff@mariatutor.com / 123
        c2 = self.app.test_client()
        res2 = c2.post('/admin/login', data={'identifier': 'staff@mariatutor.com', 'password': '123'}, follow_redirects=False)
        self.assertEqual(res2.status_code, 302)
        self.assertIn('/admin/dashboard', res2.headers.get('Location', ''))

        # 3. Tutor: tutor@mariatutor.com / 123
        c3 = self.app.test_client()
        res3 = c3.post('/tutor/login', data={'identifier': 'tutor@mariatutor.com', 'password': '123'}, follow_redirects=False)
        self.assertEqual(res3.status_code, 302)
        self.assertIn('/tutor/dashboard', res3.headers.get('Location', ''))

        # 4. Parent: parent@mariatutor.com / 123
        c4 = self.app.test_client()
        res4 = c4.post('/parent/login', data={'identifier': 'parent@mariatutor.com', 'password': '123'}, follow_redirects=False)
        self.assertEqual(res4.status_code, 302)
        self.assertIn('/parent/dashboard', res4.headers.get('Location', ''))

    def test_02_demo_boxes_removed_from_login_pages(self):
        pages = ['/login', '/admin/login', '/tutor/login', '/parent/login']
        for p in pages:
            res = self.client.get(p)
            self.assertEqual(res.status_code, 200)
            html = res.data.decode('utf-8')
            self.assertNotIn("⚡ 1-Click", html)
            self.assertNotIn("⚡ Demo", html)
            self.assertNotIn("Chaudhry Tariq", html)

    def test_03_quick_switch_route_disabled(self):
        res = self.client.get('/quick-switch/super_admin', follow_redirects=False)
        self.assertEqual(res.status_code, 302)
        self.assertIn('/admin/login', res.headers.get('Location', ''))

    def test_04_home_page_open_website_and_portal_links(self):
        res = self.client.get('/')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')

        # Direct access box should be removed from homepage body
        self.assertNotIn("Open Website Portals in New Window", html)
        self.assertNotIn("Live Portals &amp; Direct Access", html)

        # Check Home button in top navbar
        self.assertIn('href="/" class="nav-link active"', html)
        self.assertIn("Home", html)

        # Check top navigation links specifically
        self.assertIn('href="/parent/login" target="_blank" class="nav-link nav-link-parent"', html)
        self.assertIn('href="/tutor/login" target="_blank" class="nav-link nav-link-tutor"', html)
        self.assertIn('href="/admin/login" target="_blank" class="nav-link nav-link-staff"', html)

if __name__ == '__main__':
    unittest.main()
