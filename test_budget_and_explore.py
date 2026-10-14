import unittest
from app import create_app
from extensions import db
from models import User, TutorProfile, TuitionRequest, SystemSetting
from services import get_budget_settings, set_budget_settings

class BudgetAndExploreTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()

    def test_01_budget_settings_and_admin_control(self):
        with self.app.app_context():
            # 1. Verify default budget is 7,000 and 10,000
            b = get_budget_settings()
            self.assertEqual(b['min'], 7000)
            self.assertEqual(b['max'], 10000)

        # 2. Log in as Super Admin and update budget via Admin Panel
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)
        res = self.client.post('/superadmin/settings/catalog', data={
            'action': 'update_budget',
            'default_budget_min': '8000',
            'default_budget_max': '12000',
        }, follow_redirects=True)
        self.assertEqual(res.status_code, 200)

        with self.app.app_context():
            updated_b = get_budget_settings()
            self.assertEqual(updated_b['min'], 8000)
            self.assertEqual(updated_b['max'], 12000)

            # Reset back to 7000 and 10000 as requested by user
            set_budget_settings(7000, 10000)
            reset_b = get_budget_settings()
            self.assertEqual(reset_b['min'], 7000)
            self.assertEqual(reset_b['max'], 10000)

    def test_02_explore_areas_and_tutors_privacy_firewall(self):
        # Test public explore route
        res = self.client.get('/explore-tutors')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')

        # Tutor names & qualifications should be visible
        self.assertIn("Sir Usman", html)
        self.assertIn("Miss Ayesha", html)
        self.assertIn("Engr. Bilal", html)
        self.assertIn("Pre-Medical", html)
        self.assertIn("Request This Tutor", html)

        # STRICT ANTI-BYPASS PRIVACY FIREWALL:
        # None of the private contact details should EVER leak in the HTML!
        self.assertNotIn("0301-9876543", html, "Tutor phone leaked in public HTML!")
        self.assertNotIn("0302-1234567", html, "Tutor phone leaked in public HTML!")
        self.assertNotIn("36402-1234567-1", html, "Tutor CNIC leaked in public HTML!")
        self.assertNotIn("tutor@mariahome.com", html, "Tutor email leaked in public HTML!")
        self.assertNotIn("ayesha.tutor@mariahome.com", html, "Tutor email leaked in public HTML!")

    def test_03_parent_request_form_layout_and_defaults(self):
        # Log in as parent
        self.client.post('/parent/login', data={'identifier': 'parent@mariahome.com', 'password': 'Parent123'}, follow_redirects=True)
        
        # Open request new page
        res = self.client.get('/parent/requests/new')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')

        # Check default budget fields prefilled with 7000 and 10000
        self.assertIn('value="7000"', html)
        self.assertIn('value="10000"', html)
        self.assertIn("Academic Requirements", html)
        self.assertIn("Zero Direct-Leakage Privacy", html)

        # Test with preferred tutor query parameter
        with self.app.app_context():
            usman = TutorProfile.query.filter_by(tutor_code="T-BW001").first()
            usman_id = usman.id

        res_pref = self.client.get(f'/parent/requests/new?tutor_id={usman_id}')
        self.assertEqual(res_pref.status_code, 200)
        html_pref = res_pref.data.decode('utf-8')
        self.assertIn("Targeting Educator: Sir Usman", html_pref)
        self.assertIn("T-BW001", html_pref)

    def test_04_parent_explore_tutors_authenticated(self):
        # Log in as parent
        self.client.post('/parent/login', data={'identifier': 'parent@mariahome.com', 'password': 'Parent123'}, follow_redirects=True)

        res = self.client.get('/parent/explore-tutors')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Explore Areas &amp; Verified Tutors", html)
        self.assertIn("Sir Usman", html)

    def test_05_authenticated_root_not_blank(self):
        # Log in as parent and access '/'
        self.client.post('/parent/login', data={'identifier': 'parent@mariahome.com', 'password': 'Parent123'}, follow_redirects=True)
        res = self.client.get('/')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Find the right home tutor", html)
        self.assertIn("Explore Tutors", html)
        # Verify page is not a blank white container
        self.assertGreater(len(html), 1000)

if __name__ == '__main__':
    unittest.main()
