import unittest
from app import create_app
from extensions import db
from models import User, TuitionRequest, CommissionRule, AuditEvent, LeadEvent, CatalogItem

class CommissionControlTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()
        with self.app.app_context():
            parent = User.query.filter_by(role="parent").first()
            p_id = parent.id if parent else 1
            area = CatalogItem.query.filter_by(kind="area").first()
            subject = CatalogItem.query.filter_by(kind="subject").first()
            cls = CatalogItem.query.filter_by(kind="class").first()
            mode = CatalogItem.query.filter_by(kind="mode").first()
            area_id = area.id if area else 1
            subject_id = subject.id if subject else 1
            class_id = cls.id if cls else 1
            mode_id = mode.id if mode else 1

            req1 = TuitionRequest.query.filter_by(public_ref="BW-REQ1001").first()
            if not req1:
                req1 = TuitionRequest(
                    parent_user_id=p_id,
                    public_ref="BW-REQ1001",
                    area_id=area_id,
                    subject_id=subject_id,
                    class_id=class_id,
                    mode_id=mode_id,
                    status="open",
                    budget_min=7000,
                    budget_max=9000,
                    requirements="Need Physics tutor in Satellite Town",
                    preferred_time="Evening 4:00 PM - 7:00 PM",
                )
                db.session.add(req1)
            req2 = TuitionRequest.query.filter_by(public_ref="BW-REQ1002").first()
            if not req2:
                req2 = TuitionRequest(
                    parent_user_id=p_id,
                    public_ref="BW-REQ1002",
                    area_id=area_id,
                    subject_id=subject_id,
                    class_id=class_id,
                    mode_id=mode_id,
                    status="open",
                    budget_min=8000,
                    budget_max=10000,
                    requirements="Need Math tutor in Model Town",
                    preferred_time="Evening 4:00 PM - 7:00 PM",
                )
                db.session.add(req2)
            db.session.commit()

    def test_01_commission_center_get_and_post_case_override(self):
        # 1. Login as Super Admin
        login_res = self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)
        self.assertEqual(login_res.status_code, 200)

        # 2. GET /superadmin/settings/commission
        res = self.client.get('/superadmin/settings/commission')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Commission Rules &amp; Placement Ledgers", html)
        self.assertIn("Live Cases &amp; Custom Commission Manager", html)
        self.assertIn("BW-REQ1001", html)

        # 3. Apply custom rate for Case 1 (e.g., 35% for high budget parent)
        with self.app.app_context():
            req1 = TuitionRequest.query.filter_by(public_ref="BW-REQ1001").first()
            req1_id = req1.id

        post_res = self.client.post('/superadmin/settings/commission', data={
            'action': 'update_case_commission',
            'request_id': str(req1_id),
            'commission_value': '35',
            'commission_type': 'percent'
        }, follow_redirects=True)
        self.assertEqual(post_res.status_code, 200)
        post_html = post_res.data.decode('utf-8')
        self.assertIn("Commission rate for Case BW-REQ1001 successfully updated to 35%", post_html)

        # 4. Verify DB was updated
        with self.app.app_context():
            req1 = TuitionRequest.query.filter_by(public_ref="BW-REQ1001").first()
            self.assertEqual(req1.commission_snapshot_type, 'percent')
            self.assertEqual(req1.commission_snapshot_value, 35)

            # Check LeadEvent and AuditEvent recorded
            le = LeadEvent.query.filter_by(request_id=req1.id).order_by(LeadEvent.created_at.desc()).first()
            self.assertIn("35%", le.details)

        # 5. Apply custom rate for Case 2 (e.g., 15% concession)
        with self.app.app_context():
            req2 = TuitionRequest.query.filter_by(public_ref="BW-REQ1002").first()
            req2_id = req2.id

        post_res2 = self.client.post('/superadmin/settings/commission', data={
            'action': 'update_case_commission',
            'request_id': str(req2_id),
            'commission_value': '15',
            'commission_type': 'percent'
        }, follow_redirects=True)
        self.assertEqual(post_res2.status_code, 200)

        with self.app.app_context():
            req2 = TuitionRequest.query.filter_by(public_ref="BW-REQ1002").first()
            self.assertEqual(req2.commission_snapshot_type, 'percent')
            self.assertEqual(req2.commission_snapshot_value, 15)

if __name__ == '__main__':
    unittest.main()
