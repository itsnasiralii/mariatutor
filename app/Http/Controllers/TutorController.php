<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\CatalogItem;
use App\Models\OpportunityExposure;
use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TutorController extends Controller
{
    public const MAX_DOCUMENTS_PER_TUTOR = 10;

    public const ALLOWED_DOCUMENT_TYPES = [
        'CNIC Front & Back',
        'Degree / Transcript',
        'Experience Certificate',
        'Other',
    ];

    public function dashboard()
    {
        $user = Auth::user();
        $profile = $user->tutorProfile;

        if (! $profile) {
            return redirect()->route('home')->with('error', 'Tutor profile not found.');
        }

        $stats = [
            'total_exposures' => OpportunityExposure::where('tutor_id', $profile->id)->count(),
            'interested_count' => OpportunityExposure::where('tutor_id', $profile->id)->whereNotNull('interested_at')->count(),
        ];

        $exposures = OpportunityExposure::with(['request.area', 'request.subject', 'request.schoolClass', 'request.mode'])
            ->where('tutor_id', $profile->id)
            ->orderBy('shown_at', 'desc')
            ->paginate(15);

        $documents = $profile->documents()->orderBy('created_at', 'desc')->get();

        return view('tutor.dashboard', compact('user', 'profile', 'exposures', 'documents', 'stats'));
    }

    public function expressInterest(Request $request, $id)
    {
        $request->validate([
            'interest_note' => 'nullable|string|max:240',
        ]);

        $user = Auth::user();
        $profile = $user->tutorProfile;

        $exposure = OpportunityExposure::where('id', $id)
            ->where('tutor_id', $profile->id)
            ->firstOrFail();

        $exposure->update([
            'interested_at' => now(),
            'interest_note' => $request->input('interest_note', 'Interested in this tuition lead'),
        ]);

        return back()->with('success', 'You have expressed interest in this tuition assignment! Admin will review and coordinate the demo.');
    }

    public function profile()
    {
        $user = Auth::user();
        $profile = $user->tutorProfile;

        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $classes = CatalogItem::where('kind', 'class')->where('active', true)->orderBy('sort_order')->get();
        $modes = CatalogItem::where('kind', 'mode')->where('active', true)->orderBy('sort_order')->get();

        $selectedCatalogIds = $profile->catalog()->pluck('catalog_items.id')->toArray();

        return view('tutor.profile', compact('user', 'profile', 'areas', 'subjects', 'classes', 'modes', 'selectedCatalogIds'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $profile = $user->tutorProfile;

        $request->validate([
            'full_name' => 'required|string|max:120',
            'qualification' => 'nullable|string|max:200',
            'experience' => 'nullable|string|max:500',
            'expected_fee_min' => 'nullable|integer|min:0|max:500000',
            'expected_fee_max' => 'nullable|integer|min:0|max:500000|gte:expected_fee_min',
            'availability' => 'nullable|string|max:300',
            'catalog_ids' => 'nullable|array',
            'catalog_ids.*' => 'integer|exists:catalog_items,id',
        ]);

        $profile->update([
            'full_name' => trim((string) $request->full_name),
            'qualification' => $request->qualification,
            'experience' => $request->experience,
            'expected_fee_min' => $request->expected_fee_min,
            'expected_fee_max' => $request->expected_fee_max,
            'availability' => $request->availability,
        ]);

        $profile->catalog()->sync($request->input('catalog_ids', []));

        return redirect()->route('tutor.dashboard')->with('success', 'Profile updated successfully.');
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string|max:32|in:'.implode(',', self::ALLOWED_DOCUMENT_TYPES),
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:8192',
        ]);

        $user = Auth::user();
        $profile = $user->tutorProfile;

        if ($profile->documents()->count() >= self::MAX_DOCUMENTS_PER_TUTOR) {
            return back()->with('error', 'You have reached the maximum limit of '.self::MAX_DOCUMENTS_PER_TUTOR.' verification documents.');
        }

        $file = $request->file('document_file');
        $detectedExtension = strtolower((string) ($file->extension() ?: 'bin'));
        $storedName = Str::uuid().'.'.$detectedExtension;

        $file->storeAs('private_documents', $storedName, 'local');

        $cleanOriginalName = mb_substr(basename((string) $file->getClientOriginalName()), 0, 200);

        $doc = VerificationDocument::create([
            'tutor_id' => $profile->id,
            'document_type' => $request->document_type,
            'original_name' => $cleanOriginalName ?: ('document.'.$detectedExtension),
            'stored_name' => $storedName,
            'review_status' => 'Pending',
            'uploaded_at' => now(),
        ]);

        AuditEvent::record(
            'document_uploaded',
            'verification_document',
            $doc->id,
            "Tutor {$profile->tutor_code} uploaded '{$doc->document_type}'."
        );

        return back()->with('success', 'Document uploaded securely. Our verification team will review it within 24 hours.');
    }
}
