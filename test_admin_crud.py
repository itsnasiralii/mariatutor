import html as html_lib
import unittest
from app import create_app
from extensions import db
from models import User, TutorProfile, ParentProfile, TuitionRequest, CatalogItem
from services import make_password_hash

class AdminCrudTestCase(unittest.TestCase):
    def setUp(self):
        self.app = create_app({'TESTING': True, 'WTF_CSRF_ENABLED': False})
        self.client = self.app.test_client()

    def test_01_superadmin_users_page_has_edit_and_delete(self):
        # Login as Super Admin
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)
        res = self.client.get('/superadmin/users')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("✏️ Edit", html)
        self.assertIn("🗑️ Delete", html)
        self.assertIn("Disable", html)

    def test_02_edit_tutor_profile(self):
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)
        
        with self.app.app_context():
            tutor = TutorProfile.query.first()
            tutor_id = tutor.id
            orig_name = tutor.full_name
            math_item = CatalogItem.query.filter_by(kind="subject", name="Mathematics").first()
            math_id = math_item.id if math_item else None

        # GET edit page
        res = self.client.get(f'/admin/tutors/{tutor_id}/edit')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Edit Educator Profile", html)
        self.assertTrue(orig_name in html or html_lib.escape(orig_name) in html)

        # POST update
        post_data = {
            'full_name': 'Sir Usman Updated By Admin',
            'phone': '0300-9999888',
            'email': 'usman.admin_updated@mariahome.com',
            'gender': 'Male',
            'qualification': 'Master of Science (MSc)',
            'experience': '10 years teaching Matric & FSc Physics & Math in Satellite Town Burewala.',
            'availability': 'Daily 4:00 PM - 8:00 PM (Evening)',
            'expected_fee_min': '8000',
            'expected_fee_max': '12000',
            'verification_status': 'Approved',
            'verification_note': 'Profile verified and updated directly by Admin.',
            'active': '1',
        }
        if math_id:
            post_data['catalog_ids'] = [str(math_id)]

        res_post = self.client.post(f'/admin/tutors/{tutor_id}/edit', data=post_data, follow_redirects=True)
        self.assertEqual(res_post.status_code, 200)

        # Verify in database
        with self.app.app_context():
            updated_tutor = db.session.get(TutorProfile, tutor_id)
            self.assertEqual(updated_tutor.full_name, 'Sir Usman Updated By Admin')
            self.assertEqual(updated_tutor.user.phone, '0300-9999888')
            self.assertEqual(updated_tutor.expected_fee_min, 8000)
            self.assertEqual(updated_tutor.expected_fee_max, 12000)
            self.assertEqual(updated_tutor.qualification, 'Master of Science (MSc)')
            # Restore
            updated_tutor.full_name = orig_name
            updated_tutor.user.phone = '0301-9876543'
            updated_tutor.user.email = 'tutor@mariahome.com'
            db.session.commit()

    def test_03_edit_parent_profile(self):
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)
        
        with self.app.app_context():
            parent = ParentProfile.query.first()
            parent_id = parent.id
            orig_parent_name = parent.full_name
            orig_parent_email = parent.user.email
            orig_parent_phone = parent.user.phone

        # GET edit page
        res = self.client.get(f'/admin/parents/{parent_id}/edit')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Edit Parent Account", html)

        # POST update
        res_post = self.client.post(f'/admin/parents/{parent_id}/edit', data={
            'full_name': 'Chaudhry Tariq (Admin Verified)',
            'phone': '0300-7654322',
            'email': 'parent.updated@mariahome.com',
            'active': '1',
        }, follow_redirects=True)
        self.assertEqual(res_post.status_code, 200)

        with self.app.app_context():
            from services import identifier_hash
            updated_p = db.session.get(ParentProfile, parent_id)
            self.assertEqual(updated_p.full_name, 'Chaudhry Tariq (Admin Verified)')
            self.assertEqual(updated_p.user.phone, '0300-7654322')
            # Restore
            updated_p.full_name = orig_parent_name
            updated_p.user.email = orig_parent_email
            updated_p.user.identifier_hash = identifier_hash(orig_parent_email, 'email')
            updated_p.user.phone = orig_parent_phone
            db.session.commit()

    def test_04_edit_tuition_request(self):
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)

        with self.app.app_context():
            req = TuitionRequest.query.first()
            if not req:
                return
            req_id = req.id
            sub_id = req.subject_id
            cls_id = req.class_id
            area_id = req.area_id
            mode_id = req.mode_id

        # GET edit page
        res = self.client.get(f'/admin/requests/{req_id}/edit')
        self.assertEqual(res.status_code, 200)
        html = res.data.decode('utf-8')
        self.assertIn("Edit Lead Case", html)

        # POST update
        res_post = self.client.post(f'/admin/requests/{req_id}/edit', data={
            'subject_id': str(sub_id),
            'class_id': str(cls_id),
            'area_id': str(area_id),
            'mode_id': str(mode_id),
            'subarea_text': 'Street 5, Model Town Burewala',
            'budget_min': '8000',
            'budget_max': '11000',
            'preferred_time': '05:00 PM – 06:00 PM',
            'tutor_gender_preference': 'Male',
            'requirements': 'Need focused preparation for Board exams.',
        }, follow_redirects=True)
        self.assertEqual(res_post.status_code, 200)

        with self.app.app_context():
            updated_req = db.session.get(TuitionRequest, req_id)
            self.assertEqual(updated_req.subarea_text, 'Street 5, Model Town Burewala')
            self.assertEqual(updated_req.budget_min, 8000)
            self.assertEqual(updated_req.budget_max, 11000)
            self.assertEqual(updated_req.preferred_time, '05:00 PM – 06:00 PM')

    def test_05_delete_user(self):
        self.client.post('/admin/login', data={'identifier': 'admin@mariahome.com', 'password': 'Admin123'}, follow_redirects=True)

        # Create temporary dummy user
        with self.app.app_context():
            temp_user = User(
                identifier_hash='test-hash-del-123',
                identifier_type='email',
                email='temp.user.delete@mariahome.com',
                phone='0399-9999999',
                role='parent',
                password_hash=make_password_hash('TempPass123'),
                active=True,
            )
            db.session.add(temp_user)
            db.session.flush()
            temp_parent = ParentProfile(user_id=temp_user.id, full_name='Temporary Parent To Delete')
            db.session.add(temp_parent)
            db.session.commit()
            temp_id = temp_user.id

        # Delete user
        res_del = self.client.post(f'/superadmin/users/{temp_id}/delete', follow_redirects=True)
        self.assertEqual(res_del.status_code, 200)

        with self.app.app_context():
            deleted_check = db.session.get(User, temp_id)
            self.assertIsNone(deleted_check)

if __name__ == '__main__':
    unittest.main()
