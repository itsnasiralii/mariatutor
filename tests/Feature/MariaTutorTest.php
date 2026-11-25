<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\AreaAdjacency;
use App\Models\AuditEvent;
use App\Models\CatalogItem;
use App\Models\CommissionRule;
use App\Models\OpportunityExposure;
use App\Models\SystemSetting;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class MariaTutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_seo_sitemap_and_healthz(): void
    {
        $area = CatalogItem::factory()->area('Satellite Town')->create();
        $subject = CatalogItem::factory()->subject('Mathematics')->create();
        $tutor = TutorProfile::factory()->approved()->create([
            'full_name' => 'Prof. Public Listing',
        ]);
        $tutor->catalog()->attach([$area->id, $subject->id]);

        $home = $this->get('/');
        $home->assertStatus(200)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false);

        $explore = $this->get('/explore-tutors');
        $explore->assertStatus(200)
            ->assertSee('Prof. Public Listing')
            ->assertDontSee($tutor->user->phone)
            ->assertDontSee($tutor->user->email);

        // Ensure guest explore page does not duplicate main heading
        $this->assertSame(1, substr_count($explore->getContent(), '<h1'));

        $this->get('/tutors/burewala/satellite-town')
            ->assertStatus(200)
            ->assertSee('Home Tutors in Satellite Town, Burewala')
            ->assertSee('Prof. Public Listing')
            ->assertDontSee($tutor->user->phone);

        $this->get('/tutors/burewala/mathematics')
            ->assertStatus(200)
            ->assertSee('Mathematics Tutors in Burewala');

        $this->get('/tutors/burewala/non-existent-sector')->assertStatus(404);

        $this->get('/contract')->assertStatus(200);
        $this->get('/privacy')->assertStatus(200)->assertSee('Zero-Leakage Privacy Policy');
        $this->get('/login')->assertStatus(200)->assertSee('noindex,nofollow', false);
        $this->get('/register/parent')->assertStatus(200);
        $this->get('/register/tutor')->assertStatus(200);

        // Logged-in user can also view privacy, contract, and explore pages without blank output
        $parent = User::factory()->parentRole()->create();
        $this->actingAs($parent)->get('/privacy')
            ->assertStatus(200)
            ->assertSee('Zero-Leakage Privacy Policy');

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/tutors/burewala/satellite-town')
            ->assertSee('/tutors/burewala/mathematics');

        $health = $this->getJson('/healthz');
        $health->assertStatus(200)
            ->assertExactJson(['status' => 'ok']);

        $this->get('/up')->assertStatus(404);
    }

    public function test_security_headers_are_present_on_responses(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_guest_is_redirected_from_all_protected_routes(): void
    {
        $protectedGetRoutes = [
            '/parent/dashboard',
            '/parent/requests/new',
            '/tutor/dashboard',
            '/tutor/profile',
            '/admin/dashboard',
            '/admin/tutors',
            '/admin/requests',
            '/superadmin/dashboard',
            '/superadmin/catalog',
            '/superadmin/commission',
        ];

        foreach ($protectedGetRoutes as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_role_authorization_boundaries_enforce_403(): void
    {
        $parent = User::factory()->parentRole()->create();
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::factory()->for($tutorUser, 'user')->create();
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        // Parent cannot open tutor, admin, or superadmin portals
        $this->actingAs($parent)->get('/parent/dashboard')->assertStatus(200);
        $this->actingAs($parent)->get('/tutor/dashboard')->assertStatus(403);
        $this->actingAs($parent)->get('/admin/dashboard')->assertStatus(403);
        $this->actingAs($parent)->get('/superadmin/dashboard')->assertStatus(403);

        // Tutor cannot open parent, admin, or superadmin portals
        $this->actingAs($tutorUser)->get('/tutor/dashboard')->assertStatus(200);
        $this->actingAs($tutorUser)->get('/parent/dashboard')->assertStatus(403);
        $this->actingAs($tutorUser)->get('/admin/dashboard')->assertStatus(403);
        $this->actingAs($tutorUser)->get('/superadmin/dashboard')->assertStatus(403);

        // Admin can open admin routes, but gets 403 on superadmin routes
        $this->actingAs($admin)->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get('/superadmin/dashboard')->assertStatus(403);

        // SuperAdmin can open both admin and superadmin routes
        $this->actingAs($superAdmin)->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($superAdmin)->get('/superadmin/dashboard')->assertStatus(200);

        // Unknown role cannot be saved on User model
        $this->expectException(InvalidArgumentException::class);
        User::factory()->create(['role' => 'hacker_role']);
    }

    public function test_deactivated_user_cannot_login_or_keep_active_session(): void
    {
        $inactiveUser = User::factory()->parentRole()->inactive()->create([
            'email' => 'blocked@example.com',
        ]);

        $response = $this->post('/login', [
            'identifier' => 'blocked@example.com',
            'password' => 'SecurePass123',
        ]);

        $response->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'login_blocked_inactive',
            'resource_id' => (string) $inactiveUser->id,
        ]);

        // Active user deactivated mid-session is logged out on next protected request
        $activeUser = User::factory()->parentRole()->create();
        $this->actingAs($activeUser)->get('/parent/dashboard')->assertStatus(200);

        $activeUser->update(['active' => false]);
        $this->actingAs($activeUser->fresh())->get('/parent/dashboard')->assertRedirect('/login');
    }

    public function test_parent_registration_password_policy_and_login(): void
    {
        // Weak password rejected
        $this->post('/register/parent', [
            'full_name' => 'Test Parent',
            'phone' => '03009998887',
            'password' => 'weakpass',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();

        // Strong password succeeds
        $response = $this->post('/register/parent', [
            'full_name' => 'Tariq Guardian',
            'phone' => '03009998887',
            'email' => 'tariq.guardian@example.com',
            'cnic' => '36402-7654321-1',
            'password' => 'StrongPass123',
        ]);

        $response->assertRedirect('/parent/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('parent_profiles', ['full_name' => 'Tariq Guardian']);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        // Can sign back in via CNIC
        $this->post('/login', [
            'identifier' => '3640276543211',
            'password' => 'StrongPass123',
        ])->assertRedirect('/parent/dashboard');
    }

    public function test_tutor_registration_duplicate_cnic_and_fee_validation(): void
    {
        $area = CatalogItem::factory()->area('Model Town')->create();
        $subject = CatalogItem::factory()->subject('Physics')->create();

        // Inverted fee range rejected
        $this->post('/register/tutor', [
            'full_name' => 'Fee Test Tutor',
            'cnic' => '36402-5555555-2',
            'phone' => '03015555555',
            'password' => 'StrongPass123',
            'gender' => 'Female',
            'expected_fee_min' => 25000,
            'expected_fee_max' => 10000,
        ])->assertSessionHasErrors('expected_fee_max');

        // Valid tutor registration
        $this->post('/register/tutor', [
            'full_name' => 'Ms. Sana Physics',
            'cnic' => '36402-5555555-2',
            'phone' => '03015555555',
            'email' => 'sana@example.com',
            'password' => 'StrongPass123',
            'gender' => 'Female',
            'expected_fee_min' => 12000,
            'expected_fee_max' => 20000,
            'catalog_ids' => [$area->id, $subject->id],
        ])->assertRedirect('/tutor/dashboard');

        // Raw CNIC is never stored in users or tutor_profiles
        $this->assertDatabaseMissing('users', ['cnic_hash' => '36402-5555555-2']);
        $this->assertDatabaseHas('tutor_profiles', [
            'full_name' => 'Ms. Sana Physics',
            'cnic_gender_hint' => 'Female',
            'verification_status' => 'Pending',
        ]);

        $this->post('/logout');

        // Duplicate CNIC (even without dashes) is rejected cleanly
        $dup = $this->post('/register/tutor', [
            'full_name' => 'Duplicate CNIC Tutor',
            'cnic' => '3640255555552',
            'phone' => '03016666666',
            'email' => 'other@example.com',
            'password' => 'StrongPass123',
            'gender' => 'Female',
        ]);

        $dup->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_login_rate_limiting_blocks_brute_force(): void
    {
        User::factory()->parentRole()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'identifier' => 'target@example.com',
                'password' => 'WrongPass999',
            ])->assertSessionHas('error');
        }

        $this->assertGreaterThanOrEqual(5, AuditEvent::where('action', 'login_failed')->count());

        // 6th attempt within 1 minute triggers HTTP 429 Too Many Requests
        $this->post('/login', [
            'identifier' => 'target@example.com',
            'password' => 'WrongPass999',
        ])->assertStatus(429);
    }

    public function test_request_submission_matches_adjacent_areas_gender_and_approved_only(): void
    {
        $satelliteTown = CatalogItem::factory()->area('Satellite Town')->create();
        $collegeRoad = CatalogItem::factory()->area('College Road')->create();
        $distantSector = CatalogItem::factory()->area('522/EB')->create();
        $physics = CatalogItem::factory()->subject('Physics')->create();
        $class = CatalogItem::factory()->schoolClass('Class 10')->create();
        $mode = CatalogItem::factory()->mode('Home Tuition')->create();

        AreaAdjacency::create([
            'area_id' => $satelliteTown->id,
            'nearby_area_id' => $collegeRoad->id,
            'priority' => 1,
        ]);

        // 1. Approved Female in requested area -> SHOULD MATCH
        $tutorDirect = TutorProfile::factory()->approved()->female()->create();
        $tutorDirect->catalog()->attach([$satelliteTown->id, $physics->id]);

        // 2. Approved Female in adjacent area -> SHOULD MATCH
        $tutorAdjacent = TutorProfile::factory()->approved()->female()->create();
        $tutorAdjacent->catalog()->attach([$collegeRoad->id, $physics->id]);

        // 3. Pending Female in requested area -> MUST NOT MATCH
        $tutorPending = TutorProfile::factory()->pending()->female()->create();
        $tutorPending->catalog()->attach([$satelliteTown->id, $physics->id]);

        // 4. Approved Male in requested area -> MUST NOT MATCH (Female requested)
        $tutorMale = TutorProfile::factory()->approved()->male()->create();
        $tutorMale->catalog()->attach([$satelliteTown->id, $physics->id]);

        // 5. Approved Female in non-adjacent area -> MUST NOT MATCH
        $tutorDistant = TutorProfile::factory()->approved()->female()->create();
        $tutorDistant->catalog()->attach([$distantSector->id, $physics->id]);

        $parent = User::factory()->parentRole()->create();

        // Invalid budget (max < min) rejected
        $this->actingAs($parent)->post('/parent/requests/new', [
            'area_id' => $satelliteTown->id,
            'subject_id' => $physics->id,
            'class_id' => $class->id,
            'mode_id' => $mode->id,
            'budget_min' => 20000,
            'budget_max' => 10000,
            'preferred_time' => '4:00 PM - 6:00 PM',
            'tutor_gender_preference' => 'Female',
        ])->assertSessionHasErrors('budget_max');

        // Valid submission
        $this->actingAs($parent)->post('/parent/requests/new', [
            'area_id' => $satelliteTown->id,
            'subject_id' => $physics->id,
            'class_id' => $class->id,
            'mode_id' => $mode->id,
            'budget_min' => 12000,
            'budget_max' => 18000,
            'preferred_time' => '4:00 PM - 6:00 PM',
            'tutor_gender_preference' => 'Female',
        ])->assertRedirect('/parent/dashboard');

        $tuitionReq = TuitionRequest::firstOrFail();
        $exposedTutorIds = OpportunityExposure::where('request_id', $tuitionReq->id)
            ->pluck('tutor_id')
            ->all();

        $this->assertEqualsCanonicalizing([$tutorDirect->id, $tutorAdjacent->id], $exposedTutorIds);
    }

    public function test_admin_verification_under_review_approval_and_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = TutorProfile::factory()->pending()->create(['full_name' => 'Candidate Tutor']);

        CommissionRule::create([
            'name' => 'Default 50 Percent',
            'rule_type' => 'percent',
            'value' => 50,
            'active' => true,
            'effective_at' => now(),
        ]);

        $req = TuitionRequest::factory()->create();

        // Unapproved tutor cannot be assigned
        $this->actingAs($admin)->post("/admin/requests/{$req->id}/assign", [
            'tutor_id' => $tutor->id,
            'status' => 'Placed',
        ])->assertSessionHas('error');

        $this->assertNull($req->fresh()->selected_tutor_id);

        // Set status to 'Under Review' (previously broken validation + filter)
        $this->actingAs($admin)->post("/admin/tutors/{$tutor->id}/verify", [
            'status' => VerificationStatus::UnderReview->value,
            'note' => 'Checking transcripts',
        ])->assertSessionHas('success');

        $this->assertSame('Under Review', $tutor->fresh()->verification_status);
        $this->actingAs($admin)->get('/admin/tutors?status=under-review')
            ->assertStatus(200)
            ->assertSee('Candidate Tutor');

        // Not visible publicly until Approved
        $this->get('/explore-tutors')->assertDontSee('Candidate Tutor');

        // Approve tutor -> visible publicly + AuditEvent logged
        $this->actingAs($admin)->post("/admin/tutors/{$tutor->id}/verify", [
            'status' => VerificationStatus::Approved->value,
            'note' => 'Verified originals',
        ])->assertSessionHas('success');

        $this->get('/explore-tutors')->assertSee('Candidate Tutor');
        $this->assertDatabaseHas('audit_events', [
            'action' => 'tutor_verification_updated',
            'resource_id' => (string) $tutor->id,
        ]);

        // Assign Approved tutor with Placed + release_contact
        $this->actingAs($admin)->post("/admin/requests/{$req->id}/assign", [
            'tutor_id' => $tutor->id,
            'status' => 'Placed',
            'release_contact' => '1',
        ])->assertSessionHas('success');

        $req->refresh();
        $this->assertSame($tutor->id, $req->selected_tutor_id);
        $this->assertSame('Placed', $req->status);
        $this->assertNotNull($req->contact_released_at);
        $this->assertSame('percent', $req->commission_snapshot_type);
        $this->assertSame(50, $req->commission_snapshot_value);

        // Parent dashboard shows tutor phone after contact release
        $this->actingAs($req->parent)->get('/parent/dashboard')
            ->assertStatus(200)
            ->assertSee($tutor->user->phone);
    }

    public function test_document_upload_validation_limit_and_protected_admin_download(): void
    {
        Storage::fake('local');

        $tutorUser = User::factory()->tutor()->create();
        $profile = TutorProfile::factory()->for($tutorUser, 'user')->create();
        $admin = User::factory()->admin()->create();
        $otherParent = User::factory()->parentRole()->create();

        // Disallowed PHP file rejected
        $badFile = UploadedFile::fake()->create('shell.php', 20, 'text/x-php');
        $this->actingAs($tutorUser)->post('/tutor/documents/upload', [
            'document_type' => 'CNIC Front & Back',
            'document_file' => $badFile,
        ])->assertSessionHasErrors('document_file');

        // Oversized (> 8 MB) file rejected
        $hugeFile = UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf');
        $this->actingAs($tutorUser)->post('/tutor/documents/upload', [
            'document_type' => 'Degree / Transcript',
            'document_file' => $hugeFile,
        ])->assertSessionHasErrors('document_file');

        // Valid PDF upload succeeds & uses MIME-derived extension
        $validPdf = UploadedFile::fake()->create('msc_degree.pdf', 250, 'application/pdf');
        $this->actingAs($tutorUser)->post('/tutor/documents/upload', [
            'document_type' => 'Degree / Transcript',
            'document_file' => $validPdf,
        ])->assertSessionHas('success');

        $doc = VerificationDocument::firstOrFail();
        $this->assertTrue(Str::endsWith($doc->stored_name, '.pdf'));
        $this->assertNotNull($doc->uploaded_at);
        Storage::disk('local')->assertExists('private_documents/'.$doc->stored_name);

        // Non-admin cannot download private verification document
        $this->actingAs($otherParent)->get("/admin/documents/{$doc->id}/download")->assertStatus(403);
        $this->actingAs($tutorUser)->get("/admin/documents/{$doc->id}/download")->assertStatus(403);

        // Admin can download and audit event is recorded
        $this->actingAs($admin)->get("/admin/documents/{$doc->id}/download")
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action' => 'document_downloaded',
            'resource_id' => (string) $doc->id,
        ]);

        // Tutor detail page renders original_name and download link
        $this->actingAs($admin)->get("/admin/tutors/{$profile->id}")
            ->assertStatus(200)
            ->assertSee('msc_degree.pdf')
            ->assertSee("/admin/documents/{$doc->id}/download");

        // Cap of 10 documents per tutor enforced
        for ($i = 0; $i < 9; $i++) {
            VerificationDocument::create([
                'tutor_id' => $profile->id,
                'document_type' => 'Other',
                'original_name' => "extra_{$i}.pdf",
                'stored_name' => Str::uuid().'.pdf',
                'review_status' => 'Pending',
                'uploaded_at' => now(),
            ]);
        }

        $eleventh = UploadedFile::fake()->create('eleventh.pdf', 100, 'application/pdf');
        $this->actingAs($tutorUser)->post('/tutor/documents/upload', [
            'document_type' => 'Other',
            'document_file' => $eleventh,
        ])->assertSessionHas('error');

        $this->assertSame(10, $profile->documents()->count());
    }

    public function test_superadmin_settings_whitelist_commission_and_catalog_validation(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        // Settings whitelist ignores unapproved keys
        $this->actingAs($superAdmin)->post('/superadmin/settings', [
            'brand_name' => 'Maria Tutor Official',
            'city_focus' => 'Burewala',
            'injected_arbitrary_key' => 'evil_payload',
        ])->assertSessionHas('success');

        $this->assertSame('Maria Tutor Official', SystemSetting::get('brand_name'));
        $this->assertNull(SystemSetting::get('injected_arbitrary_key'));

        // Commission percent > 100 rejected, negative rejected
        $this->actingAs($superAdmin)->post('/superadmin/commission', [
            'name' => 'Invalid Rule',
            'rule_type' => 'percent',
            'value' => 150,
        ])->assertSessionHasErrors('value');

        $this->actingAs($superAdmin)->post('/superadmin/commission', [
            'name' => 'Negative Rule',
            'rule_type' => 'fixed',
            'value' => -500,
        ])->assertSessionHasErrors('value');

        // Valid commission rule succeeds
        $this->actingAs($superAdmin)->post('/superadmin/commission', [
            'name' => '40% Placement Fee',
            'rule_type' => 'percent',
            'value' => 40,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('commission_rules', ['name' => '40% Placement Fee', 'value' => 40, 'active' => true]);

        // Catalog kind restricted to allowed kinds
        $this->actingAs($superAdmin)->post('/superadmin/catalog', [
            'kind' => 'arbitrary_kind',
            'name' => 'Bad Kind',
        ])->assertSessionHasErrors('kind');

        $this->actingAs($superAdmin)->post('/superadmin/catalog', [
            'kind' => 'class',
            'name' => 'BS Honours',
            'sort_order' => 15,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('catalog_items', ['kind' => 'class', 'name' => 'BS Honours']);
    }

    public function test_seeder_and_rehash_identifiers_command(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(12, CatalogItem::where('kind', 'area')->count());
        $this->assertGreaterThan(0, AreaAdjacency::count());
        $this->assertSame(25, TutorProfile::where('verification_status', VerificationStatus::Approved->value)->count());

        // Explore page renders paginated showcase tutors cleanly with custom pagination
        $explorePage1 = $this->get('/explore-tutors');
        $explorePage1->assertStatus(200)
            ->assertSee('T-BW001')
            ->assertSee('Showing')
            ->assertSee('page=2', false);

        $this->get('/explore-tutors?page=3')
            ->assertStatus(200)
            ->assertSee('T-BW025');

        // Standalone artisan command is idempotent
        $this->artisan('tutors:seed-showcase')->assertExitCode(0);
        $this->assertSame(25, TutorProfile::where('verification_status', VerificationStatus::Approved->value)->count());

        // Rotate CNIC_HASH_KEY and verify rehash command updates email/phone hashes cleanly
        $user = User::where('identifier_type', 'email')->firstOrFail();
        $oldHash = $user->identifier_hash;

        config(['app.cnic_hash_key' => 'rotated-test-cnic-secret-key-2026-xyz']);

        $this->artisan('security:rehash-identifiers')
            ->assertExitCode(0);

        $this->assertNotSame($oldHash, $user->fresh()->identifier_hash);
    }
}
