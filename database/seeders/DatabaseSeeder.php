<?php

namespace Database\Seeders;

use App\Models\AreaAdjacency;
use App\Models\CatalogItem;
use App\Models\CommissionRule;
use App\Models\ParentProfile;
use App\Models\SystemSetting;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. City: Burewala
        $city = CatalogItem::firstOrCreate([
            'kind' => 'city',
            'name' => 'Burewala',
        ], [
            'sort_order' => 1,
            'active' => true,
        ]);

        // 2. 12 Burewala Areas
        $areas = [
            'Satellite Town',
            'College Road',
            'Gulshan-e-Rehman',
            'Model Town',
            'Civil Lines / Canal Colony',
            'Vehari Road',
            'Multan Road',
            'Chichawatni Road',
            'Housing Scheme #1',
            'Housing Scheme #2',
            '50/EB',
            '522/EB',
        ];

        $areaMap = [];
        foreach ($areas as $idx => $name) {
            $item = CatalogItem::firstOrCreate([
                'kind' => 'area',
                'name' => $name,
                'parent_id' => $city->id,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
            $areaMap[$name] = $item;
        }

        // 3. Spatial Adjacencies
        $adjacencies = [
            ['Satellite Town', 'College Road', 1],
            ['Satellite Town', 'Gulshan-e-Rehman', 1],
            ['Satellite Town', 'Model Town', 2],
            ['College Road', 'Model Town', 1],
            ['College Road', 'Civil Lines / Canal Colony', 1],
            ['Gulshan-e-Rehman', 'Model Town', 1],
            ['Model Town', 'Civil Lines / Canal Colony', 1],
            ['Civil Lines / Canal Colony', 'Vehari Road', 1],
            ['Vehari Road', 'Multan Road', 2],
            ['Vehari Road', '50/EB', 2],
            ['Multan Road', 'Chichawatni Road', 2],
            ['Housing Scheme #1', 'Housing Scheme #2', 1],
            ['Housing Scheme #1', 'Satellite Town', 2],
            ['50/EB', '522/EB', 2],
        ];

        foreach ($adjacencies as $adj) {
            if (isset($areaMap[$adj[0]], $areaMap[$adj[1]])) {
                AreaAdjacency::firstOrCreate([
                    'area_id' => $areaMap[$adj[0]]->id,
                    'nearby_area_id' => $areaMap[$adj[1]]->id,
                ], [
                    'priority' => $adj[2],
                ]);

                AreaAdjacency::firstOrCreate([
                    'area_id' => $areaMap[$adj[1]]->id,
                    'nearby_area_id' => $areaMap[$adj[0]]->id,
                ], [
                    'priority' => $adj[2],
                ]);
            }
        }

        // 4. Academic Subjects
        $subjects = [
            'Mathematics',
            'Physics',
            'Chemistry',
            'Biology',
            'English',
            'Urdu',
            'Computer Science',
            'Islamiat',
            'Pak Studies',
            'General Science',
            'Accounting / Commerce',
        ];

        $subjectMap = [];
        foreach ($subjects as $idx => $sName) {
            $sub = CatalogItem::firstOrCreate([
                'kind' => 'subject',
                'name' => $sName,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
            $subjectMap[$sName] = $sub;
        }

        // 5. School Classes
        $classes = [
            'Class 1-5 (Primary)',
            'Class 6-8 (Middle)',
            'Class 9 (Matric Part 1)',
            'Class 10 (Matric Part 2)',
            'O Levels (Cambridge)',
            'A Levels (Cambridge)',
            'FSc Pre-Medical',
            'FSc Pre-Engineering',
            'ICS (Computer Science)',
            'I.Com / B.Com',
        ];

        foreach ($classes as $idx => $cName) {
            CatalogItem::firstOrCreate([
                'kind' => 'class',
                'name' => $cName,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
        }

        // 6. Teaching Modes
        $modes = [
            'Tutor Visits Student Home (Burewala)',
            'Online Classes (Zoom / Google Meet)',
            'Student Visits Tutor Academy / Home',
        ];

        foreach ($modes as $idx => $mName) {
            CatalogItem::firstOrCreate([
                'kind' => 'mode',
                'name' => $mName,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
        }

        // 7. Commission Rules
        CommissionRule::firstOrCreate([
            'name' => 'Standard First Month Placement Commission',
        ], [
            'rule_type' => 'percent',
            'value' => 50,
            'active' => true,
            'effective_at' => now(),
        ]);

        // 8. System Settings
        $contactPhone = (string) (config('app.contact_phone') ?: '');
        $whatsappNumber = (string) (config('app.whatsapp_number') ?: $contactPhone);

        SystemSetting::firstOrCreate(['key' => 'brand_name'], ['value' => 'Maria Tutor']);
        SystemSetting::firstOrCreate(['key' => 'site_title'], ['value' => 'Maria Tutor']);
        SystemSetting::firstOrCreate(['key' => 'city_focus'], ['value' => 'Burewala']);
        SystemSetting::firstOrCreate(['key' => 'city_name'], ['value' => 'Burewala']);
        SystemSetting::firstOrCreate(['key' => 'contact_phone'], ['value' => $contactPhone]);
        SystemSetting::firstOrCreate(['key' => 'support_whatsapp'], ['value' => $whatsappNumber]);
        SystemSetting::firstOrCreate(['key' => 'whatsapp_number'], ['value' => $whatsappNumber]);

        // 9. Super Admin creation
        $isProduction = app()->environment('production');
        $adminEmail = config('app.admin_email');
        $adminPassword = config('app.admin_password');

        if ($isProduction && (empty($adminEmail) || empty($adminPassword))) {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD environment variables are required in production.');
        }

        $adminEmail = $adminEmail ?: 'admin@mariatutor.pk';
        $adminPassword = $adminPassword ?: (app()->environment('testing') ? 'AdminSecure123!' : Str::random(16));

        if (! app()->environment('testing')) {
            $this->command?->info("Seeding Super Admin: {$adminEmail}");
            if (! config('app.admin_password')) {
                $this->command?->warn("Generated Super Admin Password: {$adminPassword}");
            }
        }

        User::firstOrCreate([
            'email' => strtolower(trim((string) $adminEmail)),
        ], [
            'identifier_hash' => SecurityService::hashIdentifier((string) $adminEmail, 'email'),
            'identifier_type' => 'email',
            'name' => 'Super Administrator',
            'role' => 'super_admin',
            'password' => Hash::make((string) $adminPassword),
            'active' => true,
            'permissions_json' => json_encode(['all' => true]),
        ]);

        // 10. Demo Users (Local and Testing Environments only)
        if (app()->environment('local', 'testing')) {
            $demoStaffPassword = app()->environment('testing') ? 'StaffPass123!' : Str::random(14);
            $demoTutorPassword = app()->environment('testing') ? 'TutorPass123!' : Str::random(14);
            $demoParentPassword = app()->environment('testing') ? 'ParentPass123!' : Str::random(14);

            if (! app()->environment('testing')) {
                $this->command?->info("Demo Staff Admin: staff@mariatutor.com / {$demoStaffPassword}");
                $this->command?->info("Demo Tutor: tutor@mariatutor.com / {$demoTutorPassword}");
                $this->command?->info("Demo Parent: parent@mariatutor.com / {$demoParentPassword}");
            }

            // Staff Admin
            User::firstOrCreate([
                'email' => 'staff@mariatutor.com',
            ], [
                'identifier_hash' => SecurityService::hashIdentifier('staff@mariatutor.com', 'email'),
                'identifier_type' => 'email',
                'name' => 'Staff Administrator',
                'role' => 'admin',
                'password' => Hash::make($demoStaffPassword),
                'active' => true,
                'permissions_json' => json_encode(['verify_tutors' => true, 'manage_requests' => true]),
            ]);

            // Demo Tutor 1
            $tutorUser = User::firstOrCreate([
                'email' => 'tutor@mariatutor.com',
            ], [
                'identifier_hash' => SecurityService::hashIdentifier('tutor@mariatutor.com', 'email'),
                'identifier_type' => 'email',
                'cnic_hash' => SecurityService::hashIdentifier('36402-1111111-1', 'cnic'),
                'name' => 'Prof. Muhammad Bilal',
                'phone' => '03000000001',
                'role' => 'tutor',
                'password' => Hash::make($demoTutorPassword),
                'active' => true,
            ]);

            $tutorProfile = TutorProfile::firstOrCreate([
                'user_id' => $tutorUser->id,
            ], [
                'tutor_code' => 'T-BW001',
                'full_name' => 'Prof. Muhammad Bilal',
                'gender' => 'Male',
                'cnic_gender_hint' => 'Male',
                'qualification' => 'M.Sc Physics (Govt College Burewala)',
                'experience' => '6 years teaching Matric & FSc Physics & Math',
                'expected_fee_min' => 12000,
                'expected_fee_max' => 20000,
                'availability' => '4:00 PM - 8:00 PM',
                'verification_status' => 'Approved',
                'verification_note' => 'CNIC verified and transcripts checked.',
                'approved_at' => now(),
            ]);

            $tutorProfile->catalog()->syncWithoutDetaching([
                $areaMap['Satellite Town']->id,
                $areaMap['College Road']->id,
                $areaMap['Model Town']->id,
                $subjectMap['Physics']->id,
                $subjectMap['Mathematics']->id,
            ]);

            // Demo Tutor 2
            $tutorUser2 = User::firstOrCreate([
                'email' => 'ayesha.tutor@mariatutor.com',
            ], [
                'identifier_hash' => SecurityService::hashIdentifier('ayesha.tutor@mariatutor.com', 'email'),
                'identifier_type' => 'email',
                'cnic_hash' => SecurityService::hashIdentifier('36402-3333333-2', 'cnic'),
                'name' => 'Ms. Ayesha Siddiqa',
                'phone' => '03000000002',
                'role' => 'tutor',
                'password' => Hash::make($demoTutorPassword),
                'active' => true,
            ]);

            $tutorProfile2 = TutorProfile::firstOrCreate([
                'user_id' => $tutorUser2->id,
            ], [
                'tutor_code' => 'T-BW002',
                'full_name' => 'Ms. Ayesha Siddiqa',
                'gender' => 'Female',
                'cnic_gender_hint' => 'Female',
                'qualification' => 'M.Phil English & B.Ed',
                'experience' => '4 years teaching O Levels & Matric English / Biology',
                'expected_fee_min' => 15000,
                'expected_fee_max' => 25000,
                'availability' => '3:00 PM - 7:00 PM',
                'verification_status' => 'Approved',
                'verification_note' => 'Verified female tutor for Burewala home tuition.',
                'approved_at' => now(),
            ]);

            $tutorProfile2->catalog()->syncWithoutDetaching([
                $areaMap['Model Town']->id,
                $areaMap['Gulshan-e-Rehman']->id,
                $subjectMap['English']->id,
                $subjectMap['Biology']->id,
            ]);

            // Demo Parent
            $parentUser = User::firstOrCreate([
                'email' => 'parent@mariatutor.com',
            ], [
                'identifier_hash' => SecurityService::hashIdentifier('parent@mariatutor.com', 'email'),
                'identifier_type' => 'email',
                'cnic_hash' => SecurityService::hashIdentifier('36402-2222222-1', 'cnic'),
                'name' => 'Chaudhry Tariq Mehmood',
                'phone' => '03000000003',
                'role' => 'parent',
                'password' => Hash::make($demoParentPassword),
                'active' => true,
            ]);

            ParentProfile::firstOrCreate([
                'user_id' => $parentUser->id,
            ], [
                'full_name' => 'Chaudhry Tariq Mehmood',
            ]);
        }
    }
}
