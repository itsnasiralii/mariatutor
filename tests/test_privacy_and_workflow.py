from pathlib import Path

import pytest

from app import create_app
from extensions import db
from models import CatalogItem, OpportunityExposure, ParentProfile, TutorProfile, TuitionRequest, User
from services import identifier_hash, make_password_hash


@pytest.fixture()
def system(tmp_path):
    app = create_app({
        "TESTING": True,
        "SECRET_KEY": "test-secret-key-long-enough-for-test-only",
        "IDENTIFIER_PEPPER": "independent-test-pepper-long-enough",
        "SQLALCHEMY_DATABASE_URI": "sqlite://",
        "PRIVATE_UPLOAD_DIR": Path(tmp_path) / "private",
        "RATELIMIT_STORAGE_URI": "memory://",
        "WTF_CSRF_ENABLED": False,
    })
    with app.app_context():
        db.drop_all()
        db.create_all()
        city = CatalogItem(kind="city", name="Burewala")
        db.session.add(city)
        db.session.flush()
        area = CatalogItem(kind="area", name="Satellite Town", parent_id=city.id)
        subject = CatalogItem(kind="subject", name="Mathematics")
        school_class = CatalogItem(kind="class", name="10")
        mode = CatalogItem(kind="mode", name="Home Tuition")
        db.session.add_all([area, subject, school_class, mode])
        db.session.flush()

        tutor_password = "Tutor123"
        tutor = TutorProfile(tutor_code="T-TEST001", full_name="Test Tutor", gender="Female",
                             verification_status="Approved", expected_fee_min=10000,
                             availability="Weekdays evening", catalog=[area, subject, school_class, mode])
        tutor_user = User(identifier_hash=identifier_hash("36402-8493837-3", "cnic"),
                          identifier_type="cnic", phone="03001234567", role="tutor",
                          password_hash=make_password_hash(tutor_password), tutor=tutor)
        parent_password = "Parent123"
        parent_user = User(identifier_hash=identifier_hash("parent@example.com", "email"),
                           identifier_type="email", email="parent@example.com", phone="03007654321",
                           role="parent", password_hash=make_password_hash(parent_password),
                           parent=ParentProfile(full_name="Private Parent Name"))
        admin_user = User(identifier_hash=identifier_hash("owner@example.com", "email"),
                          identifier_type="email", email="owner@example.com", role="super_admin",
                          password_hash=make_password_hash("Owner123"))
        db.session.add_all([tutor_user, parent_user, admin_user])
        db.session.commit()
        ids = {"area": area.id, "subject": subject.id, "class": school_class.id, "mode": mode.id}
    client = app.test_client()
    with client.session_transaction() as sess:
        sess["csrf_token"] = "test-csrf"
    yield app, client, ids
    with app.app_context():
        db.session.remove()
        db.drop_all()


def login(client, identifier, password):
    return client.post("/login", data={"csrf_token": "test-csrf", "identifier": identifier,
                                      "password": password}, follow_redirects=True)


def logout(client):
    return client.post("/logout", data={"csrf_token": "test-csrf"}, follow_redirects=True)


def test_private_match_interest_and_authorized_contact_release(system):
    app, client, ids = system
    with app.app_context():
        tutor_user = User.query.filter_by(role="tutor").first()
        tutor_hash = tutor_user.identifier_hash
        assert tutor_hash != "36402-8493837-3"
        assert "36402-8493837-3" not in tutor_user.password_hash

    assert login(client, "parent@example.com", "Parent123").status_code == 200
    response = client.post("/parent/requests/new", data={
        "csrf_token": "test-csrf", "area_id": ids["area"], "subject_id": ids["subject"],
        "class_id": ids["class"], "mode_id": ids["mode"], "preferred_time": "Weekdays evening",
        "budget_min": "10000", "budget_max": "20000", "tutor_gender_preference": "Female",
        "subarea_text": "Private Block A", "requirements": "Private note for admin only",
    }, follow_redirects=True)
    assert response.status_code == 200
    with app.app_context():
        req = TuitionRequest.query.one()
        request_id = req.id
        public_ref = req.public_ref
    parent_page = client.get(f"/parent/requests/{request_id}")
    assert b"03001234567" not in parent_page.data
    assert b"Private Parent Name" not in parent_page.data

    logout(client)
    assert login(client, "owner@example.com", "Owner123").status_code == 200
    assert client.post(f"/admin/requests/{request_id}/match", data={"csrf_token": "test-csrf"}).status_code == 302
    with app.app_context():
        assert OpportunityExposure.query.filter_by(request_id=request_id).count() == 1
    logout(client)

    assert login(client, "36402-8493837-3", "Tutor123").status_code == 200
    opportunity = client.get(f"/tutor/opportunities/{request_id}")
    assert b"Private Parent Name" not in opportunity.data
    assert b"03007654321" not in opportunity.data
    assert b"Private Block A" not in opportunity.data
    assert client.post(f"/tutor/opportunities/{request_id}/interest", data={"csrf_token": "test-csrf"}).status_code == 302
    logout(client)

    assert login(client, "owner@example.com", "Owner123").status_code == 200
    with app.app_context():
        exposure = OpportunityExposure.query.filter_by(request_id=request_id).one()
        tutor_id = exposure.tutor_id
    assert client.post(f"/admin/requests/{request_id}/select-tutor", data={
        "csrf_token": "test-csrf", "tutor_id": tutor_id
    }).status_code == 302
    assert client.post(f"/admin/requests/{request_id}/release-contacts", data={
        "csrf_token": "test-csrf"
    }).status_code == 302
    logout(client)

    assert login(client, "36402-8493837-3", "Tutor123").status_code == 200
    authorized = client.get(f"/tutor/opportunities/{request_id}")
    assert b"Private Parent Name" in authorized.data
    assert b"03007654321" in authorized.data
    logout(client)

    assert login(client, "parent@example.com", "Parent123").status_code == 200
    parent_page = client.get(f"/parent/requests/{request_id}")
    assert b"Test Tutor" in parent_page.data
    assert b"03001234567" in parent_page.data


def test_unexposed_request_is_not_openable_by_tutor(system):
    app, client, ids = system
    with app.app_context():
        parent = User.query.filter_by(role="parent").one()
        req = TuitionRequest(public_ref="BW-PRIVATE1", parent_user_id=parent.id, area_id=ids["area"],
                             subject_id=ids["subject"], class_id=ids["class"], mode_id=ids["mode"],
                             preferred_time="Evening")
        db.session.add(req)
        db.session.commit()
        request_id = req.id
    login(client, "36402-8493837-3", "Tutor123")
    assert client.get(f"/tutor/opportunities/{request_id}").status_code == 404
