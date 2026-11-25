<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\AuditEvent;
use App\Models\CatalogItem;
use App\Models\ParentProfile;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(Request $request, $portal = null)
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login', compact('portal'));
    }

    public function showParentLogin()
    {
        return $this->showLogin(request(), 'parent');
    }

    public function showTutorLogin()
    {
        return $this->showLogin(request(), 'tutor');
    }

    public function showAdminLogin()
    {
        return $this->showLogin(request(), 'admin');
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:254',
            'password' => 'required|string|max:255',
        ]);

        $rawIdentifier = trim((string) $request->input('identifier'));
        $password = (string) $request->input('password');

        // Check if identifier is CNIC (has dashes or 13 digits)
        $cleanDigits = preg_replace('/\D/', '', $rawIdentifier);
        $user = null;

        if (strlen($cleanDigits) === 13) {
            $cnicNormalized = SecurityService::normalizeCnic($rawIdentifier);
            $cnicHash = SecurityService::hashIdentifier($cnicNormalized, 'cnic');
            $user = User::where('cnic_hash', $cnicHash)
                ->orWhere('identifier_hash', $cnicHash)
                ->first();
        }

        // Try direct email or phone
        if (! $user) {
            $user = User::where('email', strtolower($rawIdentifier))
                ->orWhere('phone', $rawIdentifier)
                ->first();
        }

        // Try Tutor Code (e.g. T-BW001)
        if (! $user && str_starts_with(strtoupper($rawIdentifier), 'T-BW')) {
            $profile = TutorProfile::where('tutor_code', strtoupper($rawIdentifier))->first();
            if ($profile) {
                $user = $profile->user;
            }
        }

        // Validate user and password with uniform generic message
        if (! $user || ! Hash::check($password, $user->password)) {
            AuditEvent::record(
                'login_failed',
                'auth',
                $user?->id,
                'Failed login attempt for identifier: '.Str::mask($rawIdentifier, '*', 2),
                null
            );

            return back()->withInput(['identifier' => $rawIdentifier])->with('error', 'Invalid login credentials. Please try again.');
        }

        if (! $user->active) {
            AuditEvent::record(
                'login_blocked_inactive',
                'user',
                $user->id,
                'Deactivated user attempted login.',
                $user->id
            );

            return back()->withInput(['identifier' => $rawIdentifier])->with('error', 'Your account has been deactivated. Please contact support.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->redirectBasedOnRole($user);
    }

    public function showRegisterParent()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.register_parent');
    }

    public function registerParent(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:120',
            'phone' => 'required|string|max:32',
            'cnic' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && strlen(preg_replace('/\D/', '', (string) $value)) !== 13) {
                    $fail('The CNIC must contain exactly 13 digits.');
                }
            }],
            'email' => 'nullable|email|max:254',
            'password' => ['required', 'string', 'max:255', Password::min(10)->mixedCase()->numbers()],
        ]);

        $email = $request->filled('email') ? strtolower(trim((string) $request->email)) : null;
        $phone = trim((string) $request->phone);
        $cnicNormalized = $request->filled('cnic') ? SecurityService::normalizeCnic((string) $request->cnic) : null;
        $cnicHash = $cnicNormalized ? SecurityService::hashIdentifier($cnicNormalized, 'cnic') : null;

        $idType = $email ? 'email' : 'phone';
        $identifier = $email ?: $phone;
        $idHash = SecurityService::hashIdentifier($identifier, $idType);

        $duplicateQuery = User::where('identifier_hash', $idHash)->orWhere('phone', $phone);
        if ($email) {
            $duplicateQuery->orWhere('email', $email);
        }
        if ($cnicHash) {
            $duplicateQuery->orWhere('cnic_hash', $cnicHash);
        }

        if ($duplicateQuery->exists()) {
            return back()->withInput($request->except('password'))->with('error', 'An account with this phone, email, or CNIC already exists. Please log in.');
        }

        $user = User::create([
            'identifier_hash' => $idHash,
            'identifier_type' => $idType,
            'cnic_hash' => $cnicHash,
            'name' => trim((string) $request->full_name),
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Parent->value,
            'password' => Hash::make((string) $request->password),
            'active' => true,
        ]);

        ParentProfile::create([
            'user_id' => $user->id,
            'full_name' => trim((string) $request->full_name),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('parent.dashboard')->with('success', 'Parent account created successfully!');
    }

    public function showRegisterTutor()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $classes = CatalogItem::where('kind', 'class')->where('active', true)->orderBy('sort_order')->get();
        $modes = CatalogItem::where('kind', 'mode')->where('active', true)->orderBy('sort_order')->get();

        return view('auth.register_tutor', compact('areas', 'subjects', 'classes', 'modes'));
    }

    public function registerTutor(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:120',
            'cnic' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D/', '', (string) $value)) !== 13) {
                    $fail('The CNIC must contain exactly 13 digits.');
                }
            }],
            'phone' => 'required|string|max:32',
            'email' => 'nullable|email|max:254',
            'password' => ['required', 'string', 'max:255', Password::min(10)->mixedCase()->numbers()],
            'gender' => 'required|in:Male,Female,Prefer not to say',
            'qualification' => 'nullable|string|max:200',
            'experience' => 'nullable|string|max:500',
            'availability' => 'nullable|string|max:300',
            'expected_fee_min' => 'nullable|integer|min:0|max:500000',
            'expected_fee_max' => 'nullable|integer|min:0|max:500000|gte:expected_fee_min',
            'catalog_ids' => 'nullable|array',
            'catalog_ids.*' => 'integer|exists:catalog_items,id',
        ]);

        $email = $request->filled('email') ? strtolower(trim((string) $request->email)) : null;
        $phone = trim((string) $request->phone);
        $cnicNormalized = SecurityService::normalizeCnic((string) $request->cnic);
        $cnicHash = SecurityService::hashIdentifier($cnicNormalized, 'cnic');

        if (User::where('cnic_hash', $cnicHash)->exists()) {
            return back()->withInput($request->except('password'))->with('error', 'A tutor profile with this CNIC is already registered.');
        }

        $idType = $email ? 'email' : 'cnic';
        $identifier = $email ?: $cnicNormalized;
        $idHash = SecurityService::hashIdentifier($identifier, $idType);

        $duplicateQuery = User::where('identifier_hash', $idHash)->orWhere('phone', $phone);
        if ($email) {
            $duplicateQuery->orWhere('email', $email);
        }
        if ($duplicateQuery->exists()) {
            return back()->withInput($request->except('password'))->with('error', 'An account with this email or phone number already exists.');
        }

        $user = User::create([
            'identifier_hash' => $idHash,
            'identifier_type' => $idType,
            'cnic_hash' => $cnicHash,
            'name' => trim((string) $request->full_name),
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Tutor->value,
            'password' => Hash::make((string) $request->password),
            'active' => true,
        ]);

        $tutorCode = SecurityService::generateTutorCode();

        $profile = TutorProfile::create([
            'user_id' => $user->id,
            'tutor_code' => $tutorCode,
            'full_name' => trim((string) $request->full_name),
            'gender' => $request->gender,
            'cnic_gender_hint' => SecurityService::cnicGenderHint($cnicNormalized),
            'qualification' => $request->qualification ?: '',
            'experience' => $request->experience ?: '',
            'expected_fee_min' => $request->expected_fee_min,
            'expected_fee_max' => $request->expected_fee_max,
            'availability' => $request->availability ?: '4:00 PM - 8:00 PM',
            'verification_status' => VerificationStatus::Pending->value,
        ]);

        if ($request->filled('catalog_ids')) {
            $profile->catalog()->sync($request->catalog_ids);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('tutor.dashboard')->with('success', 'Registration submitted! Your Tutor Code is '.$tutorCode.'. Upload your verification documents to get approved.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been logged out.');
    }

    protected function redirectBasedOnRole(User $user)
    {
        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->isTutor()) {
            return redirect()->route('tutor.dashboard');
        }

        return redirect()->route('parent.dashboard');
    }
}
