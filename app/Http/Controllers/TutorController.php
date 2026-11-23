<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\OpportunityExposure;
use App\Models\TutorProfile;
use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TutorController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $profile = $user->tutorProfile;

        if (!$profile) {
            return redirect()->route('home')->with('error', 'Tutor profile not found.');
        }

        $exposures = OpportunityExposure::with(['request.area', 'request.subject', 'request.schoolClass', 'request.mode'])
            ->where('tutor_id', $profile->id)
            ->orderBy('shown_at', 'desc')
            ->get();

        $documents = $profile->documents()->orderBy('created_at', 'desc')->get();

        return view('tutor.dashboard', compact('user', 'profile', 'exposures', 'documents'));
    }

    public function expressInterest(Request $request, $id)
    {
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
            'expected_fee_min' => 'nullable|numeric',
            'expected_fee_max' => 'nullable|numeric',
            'availability' => 'nullable|string|max:300',
            'catalog_ids' => 'nullable|array',
        ]);

        $profile->update([
            'full_name' => $request->full_name,
            'qualification' => $request->qualification,
            'experience' => $request->experience,
            'expected_fee_min' => $request->expected_fee_min,
            'expected_fee_max' => $request->expected_fee_max,
            'availability' => $request->availability,
        ]);

        if ($request->has('catalog_ids')) {
            $profile->catalog()->sync($request->catalog_ids);
        }

        return redirect()->route('tutor.dashboard')->with('success', 'Profile updated successfully.');
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string|max:32',
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:8192',
        ]);

        $user = Auth::user();
        $profile = $user->tutorProfile;

        $file = $request->file('document_file');
        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('private_documents', $storedName);

        VerificationDocument::create([
            'tutor_id' => $profile->id,
            'document_type' => $request->document_type,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'review_status' => 'Pending',
        ]);

        return back()->with('success', 'Document uploaded securely. Our verification team will review it within 24 hours.');
    }
}
