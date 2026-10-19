import unittest
from app import create_app
from extensions import db
from models import User, AuditEvent

class ContractTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()
        with self.app.app_context():
            AuditEvent.query.filter_by(action="contract_accepted").delete()
            db.session.commit()

    def test_01_public_contract_page(self):
        res = self.client.get('/contract')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Code of Conduct", html)
        self.assertIn("Child Protection", html)
        self.assertIn("Verified CNIC", html)
        self.assertIn("Sexual Harassment", html)
        self.assertIn("PPC 376", html)
        self.assertIn("PECA 2016", html)
        self.assertIn("Rules of Engagement: DO's vs. DON'Ts", html)

    def test_02_redirect_routes(self):
        res_tutor = self.client.get('/tutor/contract')
        self.assertEqual(res_tutor.status_code, 302)
        self.assertTrue(res_tutor.headers['Location'].endswith('/contract'))

        res_parent = self.client.get('/parent/contract')
        self.assertEqual(res_parent.status_code, 302)
        self.assertTrue(res_parent.headers['Location'].endswith('/contract'))

    def test_03_authenticated_contract_digital_acceptance(self):
        # Login as tutor
        login_res = self.client.post('/tutor/login', data={'identifier': 'tutor@mariahome.com', 'password': 'Tutor123'}, follow_redirects=True)
        self.assertEqual(login_res.status_code, 200)

        # POST accept contract with action='accept'
        res = self.client.post('/contract', data={'action': 'accept'}, follow_redirects=True)
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("successfully signed and accepted", html)

        # Check AuditEvent in database
        with self.app.app_context():
            event = AuditEvent.query.filter_by(action="contract_accepted").order_by(AuditEvent.created_at.desc()).first()
            self.assertIsNotNone(event)
            self.assertIn("digitally accepted and signed", event.summary)

if __name__ == '__main__':
    unittest.main()
