<?php

namespace Tests\Feature;

use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MariaTutorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_pages_load_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/explore-tutors');
        $response->assertStatus(200);

        $response = $this->get('/contract');
        $response->assertStatus(200);

        $response = $this->get('/privacy');
        $response->assertStatus(200);

        $response = $this->get('/login');
        $response->assertStatus(200);

        $response = $this->get('/register/parent');
        $response->assertStatus(200);

        $response = $this->get('/register/tutor');
        $response->assertStatus(200);

        $response = $this->get('/healthz');
        $response->assertStatus(200);
    }

    public function test_parent_flows(): void
    {
        $parent = User::where('role', 'parent')->first();
        $this->assertNotNull($parent);

        $this->actingAs($parent)->get('/parent/dashboard')->assertStatus(200);
        $this->actingAs($parent)->get('/parent/requests/new')->assertStatus(200);
    }

    public function test_tutor_flows(): void
    {
        $tutor = User::where('role', 'tutor')->first();
        $this->assertNotNull($tutor);

        $this->actingAs($tutor)->get('/tutor/dashboard')->assertStatus(200);
        $this->actingAs($tutor)->get('/tutor/profile')->assertStatus(200);
    }

    public function test_admin_flows(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $this->actingAs($admin)->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/tutors')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/requests')->assertStatus(200);

        $tutor = TutorProfile::first();
        if ($tutor) {
            $this->actingAs($admin)->get("/admin/tutors/{$tutor->id}")->assertStatus(200);
        }

        $req = TuitionRequest::first();
        if ($req) {
            $this->actingAs($admin)->get("/admin/requests/{$req->id}")->assertStatus(200);
        }
    }

    public function test_superadmin_flows(): void
    {
        $superadmin = User::where('role', 'super_admin')->first();
        $this->assertNotNull($superadmin);

        $this->actingAs($superadmin)->get('/superadmin/dashboard')->assertStatus(200);
        $this->actingAs($superadmin)->get('/superadmin/catalog')->assertStatus(200);
        $this->actingAs($superadmin)->get('/superadmin/commission')->assertStatus(200);
    }
}
