<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\CatalogItem;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShowcaseTutorSeeder extends Seeder
{
    /**
     * Seed 25 realistic, document-verified Burewala showcase educators (T-BW001 to T-BW025).
     */
    public function run(): void
    {
        $city = CatalogItem::firstOrCreate(
            ['kind' => 'city', 'name' => 'Burewala'],
            ['sort_order' => 1, 'active' => true]
        );

        $areaNames = [
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
        foreach ($areaNames as $idx => $name) {
            $areaMap[$name] = CatalogItem::firstOrCreate(
                ['kind' => 'area', 'name' => $name, 'parent_id' => $city->id],
                ['sort_order' => $idx + 1, 'active' => true]
            );
        }

        $subjectNames = [
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
        foreach ($subjectNames as $idx => $name) {
            $subjectMap[$name] = CatalogItem::firstOrCreate(
                ['kind' => 'subject', 'name' => $name],
                ['sort_order' => $idx + 1, 'active' => true]
            );
        }

        $tutors = [
            [
                'code' => 'T-BW001',
                'email' => 'tutor@mariatutor.com',
                'cnic' => '36601-0000101-1',
                'phone' => '03000000101',
                'name' => 'Prof. Muhammad Bilal',
                'gender' => 'Male',
                'qualification' => 'M.Sc Physics (Govt Postgraduate College Burewala)',
                'experience' => '6 years teaching Matric & FSc Physics and Mathematics with strong BISE Multan board results',
                'fee_min' => 12000,
                'fee_max' => 20000,
                'availability' => '4:00 PM - 8:00 PM',
                'areas' => ['Satellite Town', 'College Road', 'Model Town'],
                'subjects' => ['Physics', 'Mathematics'],
            ],
            [
                'code' => 'T-BW002',
                'email' => 'ayesha.tutor@mariatutor.com',
                'cnic' => '36601-0000102-2',
                'phone' => '03000000102',
                'name' => 'Ms. Ayesha Siddiqa',
                'gender' => 'Female',
                'qualification' => 'M.Phil English Literature & B.Ed (BZU Multan)',
                'experience' => '5 years teaching O Levels, Matric & FSc English and Biology',
                'fee_min' => 15000,
                'fee_max' => 25000,
                'availability' => '3:00 PM - 7:00 PM',
                'areas' => ['Gulshan-e-Rehman', 'Model Town', 'Satellite Town'],
                'subjects' => ['English', 'Biology'],
            ],
            [
                'code' => 'T-BW003',
                'email' => 'tbw003@tutors.mariatutor.pk',
                'cnic' => '36601-0000103-1',
                'phone' => '03000000103',
                'name' => 'Prof. Tariq Mahmood',
                'gender' => 'Male',
                'qualification' => 'M.Phil Chemistry (Bahauddin Zakariya University Multan)',
                'experience' => '8 years FSc Pre-Medical & MDCAT Chemistry preparation in Burewala academies',
                'fee_min' => 15000,
                'fee_max' => 24000,
                'availability' => '4:30 PM - 8:30 PM',
                'areas' => ['College Road', 'Civil Lines / Canal Colony', 'Model Town'],
                'subjects' => ['Chemistry', 'Biology'],
            ],
            [
                'code' => 'T-BW004',
                'email' => 'tbw004@tutors.mariatutor.pk',
                'cnic' => '36601-0000104-2',
                'phone' => '03000000104',
                'name' => 'Ms. Sana Fatima',
                'gender' => 'Female',
                'qualification' => 'BS Mathematics Honours (COMSATS University Vehari)',
                'experience' => '4 years teaching Class 6–10 & O Level Mathematics and General Science',
                'fee_min' => 10000,
                'fee_max' => 18000,
                'availability' => '3:30 PM - 7:30 PM',
                'areas' => ['Satellite Town', 'Housing Scheme #1', 'Housing Scheme #2'],
                'subjects' => ['Mathematics', 'General Science'],
            ],
            [
                'code' => 'T-BW005',
                'email' => 'tbw005@tutors.mariatutor.pk',
                'cnic' => '36601-0000105-1',
                'phone' => '03000000105',
                'name' => 'Engr. Usman Ghani',
                'gender' => 'Male',
                'qualification' => 'BS Computer Science (COMSATS Vehari)',
                'experience' => '5 years teaching ICS, Matric Computer Science, Python & Cambridge IGCSE CS',
                'fee_min' => 12000,
                'fee_max' => 22000,
                'availability' => '5:00 PM - 9:00 PM',
                'areas' => ['Model Town', 'Gulshan-e-Rehman', 'Vehari Road'],
                'subjects' => ['Computer Science', 'Mathematics'],
            ],
            [
                'code' => 'T-BW006',
                'email' => 'tbw006@tutors.mariatutor.pk',
                'cnic' => '36601-0000106-2',
                'phone' => '03000000106',
                'name' => 'Dr. Hina Zahra',
                'gender' => 'Female',
                'qualification' => 'M.Phil Zoology & B.Ed (UAF Sub-Campus Burewala)',
                'experience' => '6 years coaching FSc Pre-Medical Biology & Chemistry with conceptual board prep',
                'fee_min' => 14000,
                'fee_max' => 22000,
                'availability' => '3:00 PM - 6:30 PM',
                'areas' => ['Civil Lines / Canal Colony', 'College Road', 'Satellite Town'],
                'subjects' => ['Biology', 'Chemistry'],
            ],
            [
                'code' => 'T-BW007',
                'email' => 'tbw007@tutors.mariatutor.pk',
                'cnic' => '36601-0000107-1',
                'phone' => '03000000107',
                'name' => 'Mr. Kamran Akmal Randhawa',
                'gender' => 'Male',
                'qualification' => 'M.Com & ACCA Finalist ( Hailey College / PU )',
                'experience' => '7 years teaching I.Com, B.Com & O/A Level Principles of Accounting and Business',
                'fee_min' => 12000,
                'fee_max' => 20000,
                'availability' => '4:00 PM - 8:00 PM',
                'areas' => ['Multan Road', 'Vehari Road', 'Model Town'],
                'subjects' => ['Accounting / Commerce', 'Mathematics'],
            ],
            [
                'code' => 'T-BW008',
                'email' => 'tbw008@tutors.mariatutor.pk',
                'cnic' => '36601-0000108-2',
                'phone' => '03000000108',
                'name' => 'Ms. Zainab Bibi',
                'gender' => 'Female',
                'qualification' => 'MA Urdu & MA Islamic Studies (Islamia University Bahawalpur)',
                'experience' => '6 years teaching Primary to FSc Urdu grammar, essay writing, Islamiat & Pak Studies',
                'fee_min' => 8000,
                'fee_max' => 14000,
                'availability' => '2:30 PM - 6:30 PM',
                'areas' => ['Housing Scheme #1', 'Housing Scheme #2', 'Satellite Town'],
                'subjects' => ['Urdu', 'Islamiat', 'Pak Studies'],
            ],
            [
                'code' => 'T-BW009',
                'email' => 'tbw009@tutors.mariatutor.pk',
                'cnic' => '36601-0000109-1',
                'phone' => '03000000109',
                'name' => 'Prof. Asadullah Khan',
                'gender' => 'Male',
                'qualification' => 'M.Phil Mathematics (GC University Faisalabad)',
                'experience' => '9 years teaching FSc Pre-Engineering Calculus, Trigonometry & ECAT entry test',
                'fee_min' => 15000,
                'fee_max' => 26000,
                'availability' => '4:00 PM - 9:00 PM',
                'areas' => ['Satellite Town', 'Gulshan-e-Rehman', 'College Road'],
                'subjects' => ['Mathematics', 'Physics'],
            ],
            [
                'code' => 'T-BW010',
                'email' => 'tbw010@tutors.mariatutor.pk',
                'cnic' => '36601-0000110-2',
                'phone' => '03000000110',
                'name' => 'Ms. Maryam Nawaz',
                'gender' => 'Female',
                'qualification' => 'BS Physics Honours & B.Ed (UAF Sub-Campus Burewala)',
                'experience' => '4 years home tuition for Matric & FSc female students in Physics and Mathematics',
                'fee_min' => 11000,
                'fee_max' => 18000,
                'availability' => '3:00 PM - 7:00 PM',
                'areas' => ['Chichawatni Road', 'Multan Road', 'Housing Scheme #1'],
                'subjects' => ['Physics', 'Mathematics', 'General Science'],
            ],
            [
                'code' => 'T-BW011',
                'email' => 'tbw011@tutors.mariatutor.pk',
                'cnic' => '36601-0000111-1',
                'phone' => '03000000111',
                'name' => 'Mr. Hafiz Salman Ahmad',
                'gender' => 'Male',
                'qualification' => 'MA Arabic & Islamic Studies + B.Ed (BZU Multan)',
                'experience' => '7 years teaching Matric/FSc Islamiat, Pak Studies, Urdu & Nazra/Tajweed foundations',
                'fee_min' => 8000,
                'fee_max' => 13000,
                'availability' => '3:30 PM - 8:00 PM',
                'areas' => ['50/EB', '522/EB', 'Vehari Road'],
                'subjects' => ['Islamiat', 'Pak Studies', 'Urdu'],
            ],
            [
                'code' => 'T-BW012',
                'email' => 'tbw012@tutors.mariatutor.pk',
                'cnic' => '36601-0000112-2',
                'phone' => '03000000112',
                'name' => 'Ms. Rabia Kausar',
                'gender' => 'Female',
                'qualification' => 'BS Chemistry & Montessori/Primary Teaching Diploma',
                'experience' => '5 years nurturing Class 1–8 all-subjects foundation & Matric Chemistry',
                'fee_min' => 9000,
                'fee_max' => 15000,
                'availability' => '2:30 PM - 6:00 PM',
                'areas' => ['Model Town', 'Civil Lines / Canal Colony', 'Gulshan-e-Rehman'],
                'subjects' => ['General Science', 'Chemistry', 'English'],
            ],
            [
                'code' => 'T-BW013',
                'email' => 'tbw013@tutors.mariatutor.pk',
                'cnic' => '36601-0000113-1',
                'phone' => '03000000113',
                'name' => 'Mr. Faisal Shehzad',
                'gender' => 'Male',
                'qualification' => 'M.Phil English Linguistics (University of the Punjab)',
                'experience' => '7 years teaching Cambridge O/A Level English Language, FSc English & Spoken Grammar',
                'fee_min' => 14000,
                'fee_max' => 25000,
                'availability' => '4:00 PM - 8:30 PM',
                'areas' => ['Satellite Town', 'Model Town', 'Civil Lines / Canal Colony'],
                'subjects' => ['English', 'Pak Studies'],
            ],
            [
                'code' => 'T-BW014',
                'email' => 'tbw014@tutors.mariatutor.pk',
                'cnic' => '36601-0000114-2',
                'phone' => '03000000114',
                'name' => 'Ms. Iqra Javed',
                'gender' => 'Female',
                'qualification' => 'BS Software Engineering (COMSATS University Vehari)',
                'experience' => '3 years teaching ICS Computer Science, C++, Database basics & Class 9–10 Math',
                'fee_min' => 10000,
                'fee_max' => 17000,
                'availability' => '4:00 PM - 7:30 PM',
                'areas' => ['Vehari Road', '50/EB', 'College Road'],
                'subjects' => ['Computer Science', 'Mathematics'],
            ],
            [
                'code' => 'T-BW015',
                'email' => 'tbw015@tutors.mariatutor.pk',
                'cnic' => '36601-0000115-1',
                'phone' => '03000000115',
                'name' => 'Prof. Zahid Iqbal',
                'gender' => 'Male',
                'qualification' => 'M.Sc Botany & M.Ed (Govt Postgraduate College Burewala)',
                'experience' => '10 years senior school & college Biology and Chemistry educator across Burewala',
                'fee_min' => 14000,
                'fee_max' => 23000,
                'availability' => '4:00 PM - 8:00 PM',
                'areas' => ['Chichawatni Road', 'Housing Scheme #2', 'Satellite Town'],
                'subjects' => ['Biology', 'Chemistry', 'General Science'],
            ],
            [
                'code' => 'T-BW016',
                'email' => 'tbw016@tutors.mariatutor.pk',
                'cnic' => '36601-0000116-2',
                'phone' => '03000000116',
                'name' => 'Ms. Amna Waheed',
                'gender' => 'Female',
                'qualification' => 'MBA Finance & B.Com Honours (BZU Multan)',
                'experience' => '5 years teaching I.Com, B.Com Accounting, Economics & Business Mathematics',
                'fee_min' => 11000,
                'fee_max' => 19000,
                'availability' => '3:00 PM - 7:00 PM',
                'areas' => ['Gulshan-e-Rehman', 'Satellite Town', 'Model Town'],
                'subjects' => ['Accounting / Commerce', 'Mathematics'],
            ],
            [
                'code' => 'T-BW017',
                'email' => 'tbw017@tutors.mariatutor.pk',
                'cnic' => '36601-0000117-1',
                'phone' => '03000000117',
                'name' => 'Mr. Adeel Hassan',
                'gender' => 'Male',
                'qualification' => 'BS Physics (Islamia University Bahawalpur)',
                'experience' => '4 years Matric & FSc Physics numericals and test-series preparation',
                'fee_min' => 10000,
                'fee_max' => 16000,
                'availability' => '4:30 PM - 8:30 PM',
                'areas' => ['522/EB', '50/EB', 'Vehari Road'],
                'subjects' => ['Physics', 'Mathematics'],
            ],
            [
                'code' => 'T-BW018',
                'email' => 'tbw018@tutors.mariatutor.pk',
                'cnic' => '36601-0000118-2',
                'phone' => '03000000118',
                'name' => 'Ms.Nadia Parveen',
                'gender' => 'Female',
                'qualification' => 'MA English & B.Ed (Govt Degree College for Women Burewala)',
                'experience' => '6 years Primary, Middle & Matric all-arts and English language home tuition',
                'fee_min' => 8000,
                'fee_max' => 15000,
                'availability' => '2:30 PM - 6:30 PM',
                'areas' => ['College Road', 'Housing Scheme #1', 'Model Town'],
                'subjects' => ['English', 'Urdu', 'General Science'],
            ],
            [
                'code' => 'T-BW019',
                'email' => 'tbw019@tutors.mariatutor.pk',
                'cnic' => '36601-0000119-1',
                'phone' => '03000000119',
                'name' => 'Mr. Waqas Ali',
                'gender' => 'Male',
                'qualification' => 'M.Sc Chemistry & B.Ed (GCUF)',
                'experience' => '5 years teaching O Level & FSc Organic and Inorganic Chemistry',
                'fee_min' => 13000,
                'fee_max' => 21000,
                'availability' => '4:00 PM - 8:30 PM',
                'areas' => ['Multan Road', 'Chichawatni Road', 'Civil Lines / Canal Colony'],
                'subjects' => ['Chemistry', 'General Science'],
            ],
            [
                'code' => 'T-BW020',
                'email' => 'tbw020@tutors.mariatutor.pk',
                'cnic' => '36601-0000120-2',
                'phone' => '03000000120',
                'name' => 'Ms.Khansa Riaz',
                'gender' => 'Female',
                'qualification' => 'M.Phil Mathematics (COMSATS Vehari)',
                'experience' => '5 years O/A Level Mathematics, Additional Math & FSc Pre-Engineering coaching',
                'fee_min' => 16000,
                'fee_max' => 28000,
                'availability' => '3:30 PM - 7:30 PM',
                'areas' => ['Satellite Town', 'Model Town', 'Gulshan-e-Rehman'],
                'subjects' => ['Mathematics', 'Computer Science'],
            ],
            [
                'code' => 'T-BW021',
                'email' => 'tbw021@tutors.mariatutor.pk',
                'cnic' => '36601-0000121-1',
                'phone' => '03000000121',
                'name' => 'Mr. Imran Zafar',
                'gender' => 'Male',
                'qualification' => 'MA History & Political Science + B.Ed (BZU Multan)',
                'experience' => '8 years teaching O Level Pakistan Studies (2059), Islamiat (2058) & Matric Arts',
                'fee_min' => 12000,
                'fee_max' => 20000,
                'availability' => '4:00 PM - 8:00 PM',
                'areas' => ['Civil Lines / Canal Colony', 'College Road', 'Gulshan-e-Rehman'],
                'subjects' => ['Pak Studies', 'Islamiat', 'Urdu'],
            ],
            [
                'code' => 'T-BW022',
                'email' => 'tbw022@tutors.mariatutor.pk',
                'cnic' => '36601-0000122-2',
                'phone' => '03000000122',
                'name' => 'Ms. Sidra Tul Muntaha',
                'gender' => 'Female',
                'qualification' => 'BS Biochemistry (UAF Sub-Campus Burewala)',
                'experience' => '4 years Matric & FSc Biology, Chemistry and Class 6–8 Science home tutor',
                'fee_min' => 10000,
                'fee_max' => 17000,
                'availability' => '3:00 PM - 6:30 PM',
                'areas' => ['50/EB', '522/EB', 'Housing Scheme #2'],
                'subjects' => ['Biology', 'Chemistry', 'General Science'],
            ],
            [
                'code' => 'T-BW023',
                'email' => 'tbw023@tutors.mariatutor.pk',
                'cnic' => '36601-0000123-1',
                'phone' => '03000000123',
                'name' => 'Engr. Haris Mehmood',
                'gender' => 'Male',
                'qualification' => 'B.Sc Electrical Engineering (UET Taxila)',
                'experience' => '5 years teaching A Level & FSc Physics, Calculus, and entry test preparation',
                'fee_min' => 16000,
                'fee_max' => 28000,
                'availability' => '5:00 PM - 9:00 PM',
                'areas' => ['Satellite Town', 'Housing Scheme #1', 'College Road'],
                'subjects' => ['Physics', 'Mathematics', 'Computer Science'],
            ],
            [
                'code' => 'T-BW024',
                'email' => 'tbw024@tutors.mariatutor.pk',
                'cnic' => '36601-0000124-2',
                'phone' => '03000000124',
                'name' => 'Ms. Bushra Anwar',
                'gender' => 'Female',
                'qualification' => 'M.Ed & BA Honours English',
                'experience' => '7 years dedicated Primary & Middle (Class 1–8) all-subjects homework and phonics tutor',
                'fee_min' => 7000,
                'fee_max' => 13000,
                'availability' => '2:00 PM - 6:00 PM',
                'areas' => ['Housing Scheme #2', 'Housing Scheme #1', 'Chichawatni Road'],
                'subjects' => ['English', 'Urdu', 'General Science', 'Mathematics'],
            ],
            [
                'code' => 'T-BW025',
                'email' => 'tbw025@tutors.mariatutor.pk',
                'cnic' => '36601-0000125-1',
                'phone' => '03000000125',
                'name' => 'Mr. Noman Arshad',
                'gender' => 'Male',
                'qualification' => 'BS Information Technology (BZU Multan)',
                'experience' => '4 years online & home tutor for ICS Computer Science, Accounting & Matric Math',
                'fee_min' => 11000,
                'fee_max' => 18000,
                'availability' => '4:00 PM - 8:30 PM',
                'areas' => ['Vehari Road', 'Multan Road', 'Model Town'],
                'subjects' => ['Computer Science', 'Accounting / Commerce', 'Mathematics'],
            ],
        ];

        foreach ($tutors as $item) {
            // Normalize spacing in names
            $cleanName = preg_replace('/\s+/', ' ', str_replace('Ms.', 'Ms. ', $item['name']));
            $cleanName = trim((string) preg_replace('/\s+/', ' ', (string) $cleanName));

            // Use unguessable random password in production; deterministic only in testing for T-BW001
            $rawPassword = (app()->environment('testing') && $item['code'] === 'T-BW001')
                ? 'TutorPass123!'
                : Str::random(32);

            $existingProfile = TutorProfile::where('tutor_code', $item['code'])->first();

            if ($existingProfile) {
                $user = $existingProfile->user;
            } else {
                $user = User::firstOrCreate(
                    ['email' => $item['email']],
                    [
                        'identifier_hash' => SecurityService::hashIdentifier($item['email'], 'email'),
                        'identifier_type' => 'email',
                        'cnic_hash' => SecurityService::hashIdentifier($item['cnic'], 'cnic'),
                        'name' => $cleanName,
                        'phone' => $item['phone'],
                        'role' => UserRole::Tutor->value,
                        'password' => Hash::make($rawPassword),
                        'active' => true,
                    ]
                );
            }

            $profile = TutorProfile::updateOrCreate(
                ['tutor_code' => $item['code']],
                [
                    'user_id' => $user->id,
                    'full_name' => $cleanName,
                    'gender' => $item['gender'],
                    'cnic_gender_hint' => $item['gender'],
                    'qualification' => $item['qualification'],
                    'experience' => $item['experience'],
                    'expected_fee_min' => $item['fee_min'],
                    'expected_fee_max' => $item['fee_max'],
                    'availability' => $item['availability'],
                    'verification_status' => VerificationStatus::Approved->value,
                    'verification_note' => 'Verified showcase educator profile.',
                    'approved_at' => now(),
                ]
            );

            $catalogIds = [];
            foreach ($item['areas'] as $aName) {
                if (isset($areaMap[$aName])) {
                    $catalogIds[] = $areaMap[$aName]->id;
                }
            }
            foreach ($item['subjects'] as $sName) {
                if (isset($subjectMap[$sName])) {
                    $catalogIds[] = $subjectMap[$sName]->id;
                }
            }

            $profile->catalog()->syncWithoutDetaching($catalogIds);
        }
    }
}
