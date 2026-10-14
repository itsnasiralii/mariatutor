"""Maria Tutor (Burewala Tutor Marketplace) SQLAlchemy data models.

Architectural invariants enforced at the data layer:
1. Raw Pakistani CNIC numbers are NEVER persisted in plaintext; only an HMAC-SHA256
   fingerprint (`identifier_hash`) is stored.
2. Spatial proximity matching is driven by `AreaAdjacency` (Priority 1..5) with zero
   commercial map API dependencies.
3. Confirmed placements snapshot the active commission rule (`commission_snapshot_type`,
   `commission_snapshot_value`) so subsequent commission rule edits never alter historical
   placement calculations.
"""
from datetime import datetime, timezone
from flask_login import UserMixin
from sqlalchemy import UniqueConstraint
from extensions import db


def utcnow():
    return datetime.now(timezone.utc)


tutor_catalog = db.Table(
    "tutor_catalog",
    db.Column("tutor_id", db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), primary_key=True),
    db.Column("catalog_id", db.Integer, db.ForeignKey("catalog_item.id", ondelete="RESTRICT"), primary_key=True),
)


class User(UserMixin, db.Model):
    id = db.Column(db.Integer, primary_key=True)
    identifier_hash = db.Column(db.String(64), unique=True, nullable=False, index=True)
    identifier_type = db.Column(db.String(20), nullable=False)
    email = db.Column(db.String(254), nullable=True, index=True)
    phone = db.Column(db.String(32), nullable=True)
    role = db.Column(db.String(24), nullable=False, index=True)
    password_hash = db.Column(db.String(255), nullable=False)
    active = db.Column(db.Boolean, default=True, nullable=False)
    permissions_json = db.Column(db.Text, default="{}", nullable=False)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    last_login_at = db.Column(db.DateTime(timezone=True))
    tutor = db.relationship("TutorProfile", back_populates="user", uselist=False, cascade="all, delete-orphan")
    parent = db.relationship("ParentProfile", back_populates="user", uselist=False, cascade="all, delete-orphan")


class CatalogItem(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    kind = db.Column(db.String(24), nullable=False, index=True)
    name = db.Column(db.String(100), nullable=False)
    parent_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"))
    active = db.Column(db.Boolean, default=True, nullable=False)
    sort_order = db.Column(db.Integer, default=0, nullable=False)
    parent = db.relationship("CatalogItem", remote_side=[id], backref="children")
    __table_args__ = (UniqueConstraint("kind", "name", "parent_id", name="uq_catalog_kind_name_parent"),)


class AreaAdjacency(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id", ondelete="CASCADE"), nullable=False, index=True)
    nearby_area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id", ondelete="CASCADE"), nullable=False, index=True)
    priority = db.Column(db.Integer, default=1, nullable=False)
    area = db.relationship("CatalogItem", foreign_keys=[area_id])
    nearby_area = db.relationship("CatalogItem", foreign_keys=[nearby_area_id])
    __table_args__ = (UniqueConstraint("area_id", "nearby_area_id", name="uq_area_adjacency"),)


class TutorProfile(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey("user.id", ondelete="CASCADE"), unique=True, nullable=False)
    tutor_code = db.Column(db.String(20), unique=True, nullable=False, index=True)
    full_name = db.Column(db.String(120), nullable=False)
    gender = db.Column(db.String(24), nullable=False, default="Prefer not to say")
    cnic_gender_hint = db.Column(db.String(12))
    qualification = db.Column(db.String(200), default="")
    experience = db.Column(db.String(500), default="")
    expected_fee_min = db.Column(db.Integer)
    expected_fee_max = db.Column(db.Integer)
    availability = db.Column(db.String(300), default="")
    verification_status = db.Column(db.String(32), default="Pending", nullable=False, index=True)
    verification_note = db.Column(db.String(500), default="")
    approved_at = db.Column(db.DateTime(timezone=True))
    user = db.relationship("User", back_populates="tutor")
    catalog = db.relationship("CatalogItem", secondary=tutor_catalog, lazy="selectin")


class ParentProfile(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey("user.id", ondelete="CASCADE"), unique=True, nullable=False)
    full_name = db.Column(db.String(120), nullable=False)
    user = db.relationship("User", back_populates="parent")


class TuitionRequest(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    public_ref = db.Column(db.String(16), unique=True, nullable=False, index=True)
    parent_user_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False, index=True)
    area_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    subject_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    class_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False, index=True)
    mode_id = db.Column(db.Integer, db.ForeignKey("catalog_item.id"), nullable=False)
    subarea_text = db.Column(db.String(120), default="")
    budget_min = db.Column(db.Integer)
    budget_max = db.Column(db.Integer)
    preferred_time = db.Column(db.String(120), nullable=False)
    requirements = db.Column(db.String(500), default="")
    tutor_gender_preference = db.Column(db.String(24), default="Any", nullable=False)
    status = db.Column(db.String(32), default="New request", nullable=False, index=True)
    assigned_admin_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    selected_tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id"))
    contact_released_at = db.Column(db.DateTime(timezone=True))
    commission_snapshot_type = db.Column(db.String(24), nullable=True)
    commission_snapshot_value = db.Column(db.Integer, nullable=True)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    updated_at = db.Column(db.DateTime(timezone=True), default=utcnow, onupdate=utcnow, nullable=False)
    parent = db.relationship("User", foreign_keys=[parent_user_id])
    assigned_admin = db.relationship("User", foreign_keys=[assigned_admin_id])
    area = db.relationship("CatalogItem", foreign_keys=[area_id])
    subject = db.relationship("CatalogItem", foreign_keys=[subject_id])
    school_class = db.relationship("CatalogItem", foreign_keys=[class_id])
    mode = db.relationship("CatalogItem", foreign_keys=[mode_id])
    selected_tutor = db.relationship("TutorProfile", foreign_keys=[selected_tutor_id])


class OpportunityExposure(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id", ondelete="CASCADE"), nullable=False, index=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), nullable=False, index=True)
    shown_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    viewed_at = db.Column(db.DateTime(timezone=True))
    interested_at = db.Column(db.DateTime(timezone=True))
    interest_note = db.Column(db.String(240), default="")
    request = db.relationship("TuitionRequest")
    tutor = db.relationship("TutorProfile")
    __table_args__ = (UniqueConstraint("request_id", "tutor_id", name="uq_exposure_request_tutor"),)


class VerificationDocument(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id", ondelete="CASCADE"), nullable=False, index=True)
    document_type = db.Column(db.String(32), nullable=False)
    original_name = db.Column(db.String(200), nullable=False)
    stored_name = db.Column(db.String(80), unique=True, nullable=False)
    uploaded_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    review_status = db.Column(db.String(24), default="Pending", nullable=False)
    reviewer_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    review_note = db.Column(db.String(500), default="")
    tutor = db.relationship("TutorProfile")
    reviewer = db.relationship("User")


class LeadEvent(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id", ondelete="CASCADE"), nullable=False, index=True)
    actor_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    event_type = db.Column(db.String(60), nullable=False)
    details = db.Column(db.String(500), default="")
    private = db.Column(db.Boolean, default=True, nullable=False)
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    actor = db.relationship("User")


class CommissionRule(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    rule_type = db.Column(db.String(24), nullable=False)  # 'percent' or 'fixed'
    value = db.Column(db.Integer, nullable=False)
    active = db.Column(db.Boolean, default=True, nullable=False)
    effective_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)


class PaymentEntry(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id"), nullable=False, index=True)
    amount_pkr = db.Column(db.Integer, nullable=False)
    platform_commission_pkr = db.Column(db.Integer, nullable=False)
    tutor_amount_pkr = db.Column(db.Integer, nullable=False)
    method = db.Column(db.String(32), nullable=False)
    reference = db.Column(db.String(120), default="")
    status = db.Column(db.String(24), default="Recorded", nullable=False)
    recorded_by_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False)
    notes = db.Column(db.String(500), default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    request = db.relationship("TuitionRequest")
    recorded_by = db.relationship("User")


class Review(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    request_id = db.Column(db.Integer, db.ForeignKey("tuition_request.id"), nullable=False, index=True)
    tutor_id = db.Column(db.Integer, db.ForeignKey("tutor_profile.id"), nullable=False, index=True)
    parent_user_id = db.Column(db.Integer, db.ForeignKey("user.id"), nullable=False)
    rating = db.Column(db.Integer, nullable=False)
    body = db.Column(db.String(1000), default="")
    status = db.Column(db.String(24), default="Pending", nullable=False)
    moderator_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    request = db.relationship("TuitionRequest")
    tutor = db.relationship("TutorProfile")
    parent = db.relationship("User", foreign_keys=[parent_user_id])
    moderator = db.relationship("User", foreign_keys=[moderator_id])


class SystemSetting(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    key = db.Column(db.String(100), unique=True, nullable=False)
    value = db.Column(db.Text, default="", nullable=False)
    updated_at = db.Column(db.DateTime(timezone=True), default=utcnow, onupdate=utcnow, nullable=False)


class AuditEvent(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    actor_id = db.Column(db.Integer, db.ForeignKey("user.id"))
    action = db.Column(db.String(80), nullable=False)
    resource_type = db.Column(db.String(60), nullable=False)
    resource_id = db.Column(db.String(64), default="")
    summary = db.Column(db.String(500), default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False, index=True)
    actor = db.relationship("User")


class AppError(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    error_ref = db.Column(db.String(20), unique=True, nullable=False)
    module = db.Column(db.String(80), nullable=False)
    summary = db.Column(db.String(300), nullable=False)
    details = db.Column(db.Text, default="")
    created_at = db.Column(db.DateTime(timezone=True), default=utcnow, nullable=False)
    resolved = db.Column(db.Boolean, default=False, nullable=False)
