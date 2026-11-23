<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CatalogItem;
use App\Models\AreaAdjacency;
use App\Models\TutorProfile;
use App\Models\ParentProfile;
use App\Models\CommissionRule;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            ['College Road', 'Satellite Town', 1],
            ['Satellite Town', 'Model Town', 2],
            ['Model Town', 'Satellite Town', 2],
            ['College Road', 'Gulshan-e-Rehman', 1],
            ['Gulshan-e-Rehman', 'College Road', 1],
            ['Model Town', 'Civil Lines / Canal Colony', 1],
            ['Civil Lines / Canal Colony', 'Model Town', 1],
            ['Housing Scheme #1', 'Housing Scheme #2', 1],
            ['Housing Scheme #2', 'Housing Scheme #1', 1],
            ['Vehari Road', '50/EB', 2],
            ['50/EB', 'Vehari Road', 2],
            ['Multan Road', '522/EB', 2],
            ['522/EB', 'Multan Road', 2],
        ];

        foreach ($adjacencies as [$a1, $a2, $prio]) {
            if (isset($areaMap[$a1], $areaMap[$a2])) {
                AreaAdjacency::firstOrCreate([
                    'area_id' => $areaMap[$a1]->id,
                    'nearby_area_id' => $areaMap[$a2]->id,
                ], [
                    'priority' => $prio,
                ]);
            }
        }

        // 4. Subjects
        $subjects = [
            'Mathematics',
            'Physics',
            'Chemistry',
            'Biology',
            'English',
            'Computer Science',
            'Urdu',
            'Islamiyat',
            'General Science',
            'Pakistan Studies',
            'Commerce / Accounting',
        ];
        $subjectMap = [];
        foreach ($subjects as $idx => $s) {
            $subjectMap[$s] = CatalogItem::firstOrCreate([
                'kind' => 'subject',
                'name' => $s,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
        }

        // 5. Classes
        $classes = [
            'Class 1-5 (Primary)',
            'Class 6-8 (Middle)',
            'Class 9 (SSC-I Matric)',
            'Class 10 (SSC-II Matric)',
            'FSc Pre-Medical (HSSC)',
            'FSc Pre-Engineering',
            'ICS (Computer Science)',
            'I.Com (Commerce)',
            'O Levels (Cambridge)',
            'A Levels (Cambridge)',
        ];
        $classMap = [];
        foreach ($classes as $idx => $c) {
            $classMap[$c] = CatalogItem::firstOrCreate([
                'kind' => 'class',
                'name' => $c,
            ], [
                'sort_order' => $idx + 1,
                'active' => true,
            ]);
        }

        // 6. Modes
        $modes = [
            'Home Tuition (Teacher visits student home)',
            'Online Tuition (1-on-1 Zoom / Teams)',
            'Tutor Center (Student visits teacher)',
        ];
        foreach ($modes as $idx => $m) {
            CatalogItem::firstOrCreate([
                'kind' => 'mode',
                'name' => $m,
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
        SystemSetting::firstOrCreate(['key' => 'site_title'], ['value' => 'Maria Tutor']);
        SystemSetting::firstOrCreate(['key' => 'city_name'], ['value' => 'Burewala']);
        SystemSetting::firstOrCreate(['key' => 'contact_phone'], ['value' => '03280966439']);
        SystemSetting::firstOrCreate(['key' => 'whatsapp_number'], ['value' => '03280966439']);

        // 9. Predefined Live Users
        // Helper to hash identifier
        $hashId = fn($str) => hash_hmac('sha256', strtolower(trim($str)), 'maria-tutor-secret-pepper');

        // Super Admin: nasir@mariatutor.com / 123
        User::firstOrCreate([
            'email' => 'nasir@mariatutor.com',
        ], [
            'identifier_hash' => $hashId('nasir@mariatutor.com'),
            'identifier_type' => 'email',
            'name' => 'Nasir Ali (Super Admin)',
            'role' => 'super_admin',
            'password' => Hash::make('123'),
            'active' => true,
            'permissions_json' => json_encode(['all' => true]),
        ]);

        // Staff Admin: staff@mariatutor.com / 123
        User::firstOrCreate([
            'email' => 'staff@mariatutor.com',
        ], [
            'identifier_hash' => $hashId('staff@mariatutor.com'),
            'identifier_type' => 'email',
            'name' => 'Staff Administrator',
            'role' => 'admin',
            'password' => Hash::make('123'),
            'active' => true,
            'permissions_json' => json_encode(['verify_tutors' => true, 'manage_requests' => true]),
        ]);

        // Tutor: tutor@mariatutor.com / 123 (CNIC: 36402-1111111-1)
        $tutorUser = User::firstOrCreate([
            'email' => 'tutor@mariatutor.com',
        ], [
            'identifier_hash' => $hashId('tutor@mariatutor.com'),
            'identifier_type' => 'email',
            'cnic_hash' => $hashId('36402-1111111-1'),
            'name' => 'Prof. Muhammad Bilal',
            'phone' => '03001234567',
            'role' => 'tutor',
            'password' => Hash::make('123'),
            'active' => true,
        ]);

        $tutorProfile = TutorProfile::firstOrCreate([
            'user_id' => $tutorUser->id,
        ], [
            'tutor_code' => 'T-BW001',
            'full_name' => 'Prof. Muhammad Bilal',
            'gender' => 'Male',
            'qualification' => 'M.Sc Physics (Govt College Burewala)',
            'experience' => '6 years teaching Matric & FSc Physics & Math',
            'expected_fee_min' => 12000,
            'expected_fee_max' => 20000,
            'availability' => '4:00 PM - 8:00 PM',
            'verification_status' => 'Approved',
            'verification_note' => 'CNIC verified and transcripts checked.',
            'approved_at' => now(),
        ]);

        // Attach some catalog areas & subjects to Tutor
        $tutorProfile->catalog()->syncWithoutDetaching([
            $areaMap['Satellite Town']->id,
            $areaMap['College Road']->id,
            $areaMap['Model Town']->id,
            $subjectMap['Physics']->id,
            $subjectMap['Mathematics']->id,
        ]);

        // Female Demo Tutor: ayesha.tutor@mariatutor.com / 123 (CNIC: 36402-3333333-2)
        $tutorUser2 = User::firstOrCreate([
            'email' => 'ayesha.tutor@mariatutor.com',
        ], [
            'identifier_hash' => $hashId('ayesha.tutor@mariatutor.com'),
            'identifier_type' => 'email',
            'cnic_hash' => $hashId('36402-3333333-2'),
            'name' => 'Ms. Ayesha Siddiqa',
            'phone' => '03019876543',
            'role' => 'tutor',
            'password' => Hash::make('123'),
            'active' => true,
        ]);

        $tutorProfile2 = TutorProfile::firstOrCreate([
            'user_id' => $tutorUser2->id,
        ], [
            'tutor_code' => 'T-BW002',
            'full_name' => 'Ms. Ayesha Siddiqa',
            'gender' => 'Female',
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

        // Parent: parent@mariatutor.com / 123 (CNIC: 36402-2222222-1)
        $parentUser = User::firstOrCreate([
            'email' => 'parent@mariatutor.com',
        ], [
            'identifier_hash' => $hashId('parent@mariatutor.com'),
            'identifier_type' => 'email',
            'cnic_hash' => $hashId('36402-2222222-1'),
            'name' => 'Chaudhry Tariq Mehmood',
            'phone' => '03217654321',
            'role' => 'parent',
            'password' => Hash::make('123'),
            'active' => true,
        ]);

        ParentProfile::firstOrCreate([
            'user_id' => $parentUser->id,
        ], [
            'full_name' => 'Chaudhry Tariq Mehmood',
        ]);
    }
}
