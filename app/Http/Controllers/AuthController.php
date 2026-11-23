<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ParentProfile;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $rawIdentifier = trim($request->input('identifier'));
        $password = $request->input('password');

        // Check if identifier is CNIC (has dashes or 13 digits)
        $cleanDigits = preg_replace('/\D/', '', $rawIdentifier);
        $user = null;

        if (strlen($cleanDigits) === 13) {
            $cnicNormalized = SecurityService::normalizeCnic($rawIdentifier);
            $cnicHash = SecurityService::hashIdentifier($cnicNormalized, 'cnic');
            $user = User::where('cnic_hash', $cnicHash)->orWhere('identifier_hash', $cnicHash)->first();
        }

        // Try direct email or phone
        if (!$user) {
            $user = User::where('email', strtolower($rawIdentifier))
                ->orWhere('phone', $rawIdentifier)
                ->first();
        }

        // Try Tutor Code (e.g. T-BW001)
        if (!$user && str_starts_with(strtoupper($rawIdentifier), 'T-BW')) {
            $profile = TutorProfile::where('tutor_code', strtoupper($rawIdentifier))->first();
            if ($profile) {
                $user = $profile->user;
            }
        }

        // Validate user and password
        if (!$user || !Hash::check($password, $user->password)) {
            // Also support predefined testing password '123' if needed
            if (!($user && ($password === '123' || Hash::check($password, $user->password)))) {
                return back()->withInput()->with('error', 'Invalid login credentials or account not found.');
            }
        }

        if (!$user->active) {
            return back()->withInput()->with('error', 'Your account is deactivated. Please contact support.');
        }

        Auth::login($user, $request->boolean('remember'));
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
            'cnic' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:254',
            'password' => 'required|string|min:6',
        ]);

        $cnicNormalized = $request->filled('cnic') ? SecurityService::normalizeCnic($request->cnic) : null;
        $cnicHash = $cnicNormalized ? SecurityService::hashIdentifier($cnicNormalized, 'cnic') : null;

        $identifier = $request->email ? strtolower(trim($request->email)) : trim($request->phone);
        $idHash = SecurityService::hashIdentifier($identifier, 'email');

        if (User::where('identifier_hash', $idHash)->exists() || ($cnicHash && User::where('cnic_hash', $cnicHash)->exists())) {
            return back()->withInput()->with('error', 'An account with this phone/email or CNIC already exists. Please log in.');
        }

        $user = User::create([
            'identifier_hash' => $idHash,
            'identifier_type' => $request->email ? 'email' : 'phone',
            'cnic_hash' => $cnicHash,
            'name' => $request->full_name,
            'email' => $request->email ? strtolower(trim($request->email)) : null,
            'phone' => trim($request->phone),
            'role' => 'parent',
            'password' => Hash::make($request->password),
            'active' => true,
        ]);

        ParentProfile::create([
            'user_id' => $user->id,
            'full_name' => $request->full_name,
        ]);

        Auth::login($user);

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
            'cnic' => 'required|string|max:20',
            'phone' => 'required|string|max:32',
            'email' => 'nullable|email|max:254',
            'password' => 'required|string|min:6',
            'gender' => 'required|string',
            'qualification' => 'nullable|string|max:200',
            'experience' => 'nullable|string|max:500',
            'expected_fee_min' => 'nullable|numeric',
            'expected_fee_max' => 'nullable|numeric',
            'catalog_ids' => 'nullable|array',
        ]);

        $cnicNormalized = SecurityService::normalizeCnic($request->cnic);
        $cnicHash = SecurityService::hashIdentifier($cnicNormalized, 'cnic');

        if (User::where('cnic_hash', $cnicHash)->exists()) {
            return back()->withInput()->with('error', 'A tutor profile with this CNIC is already registered.');
        }

        $identifier = $request->email ? strtolower(trim($request->email)) : $cnicNormalized;
        $idHash = SecurityService::hashIdentifier($identifier, 'cnic');

        $user = User::create([
            'identifier_hash' => $idHash,
            'identifier_type' => 'cnic',
            'cnic_hash' => $cnicHash,
            'name' => $request->full_name,
            'email' => $request->email ? strtolower(trim($request->email)) : null,
            'phone' => trim($request->phone),
            'role' => 'tutor',
            'password' => Hash::make($request->password),
            'active' => true,
        ]);

        $tutorCode = SecurityService::generateTutorCode();

        $profile = TutorProfile::create([
            'user_id' => $user->id,
            'tutor_code' => $tutorCode,
            'full_name' => $request->full_name,
            'gender' => $request->gender,
            'qualification' => $request->qualification ?: '',
            'experience' => $request->experience ?: '',
            'expected_fee_min' => $request->expected_fee_min,
            'expected_fee_max' => $request->expected_fee_max,
            'availability' => $request->availability ?: '4:00 PM - 8:00 PM',
            'verification_status' => 'Pending',
        ]);

        if ($request->has('catalog_ids')) {
            $profile->catalog()->sync($request->catalog_ids);
        }

        Auth::login($user);

        return redirect()->route('tutor.dashboard')->with('success', 'Registration submitted! Your Tutor Code is ' . $tutorCode . '. Upload your verification documents to get approved.');
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
