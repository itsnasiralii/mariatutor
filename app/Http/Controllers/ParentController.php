<?php

namespace App\Http\Controllers;

use App\Models\AreaAdjacency;
use App\Models\CatalogItem;
use App\Models\OpportunityExposure;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $requests = TuitionRequest::with(['area', 'subject', 'schoolClass', 'mode', 'selectedTutor'])
            ->where('parent_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('parent.dashboard', compact('requests', 'user'));
    }

    public function showRequestForm(Request $request)
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $classes = CatalogItem::where('kind', 'class')->where('active', true)->orderBy('sort_order')->get();
        $modes = CatalogItem::where('kind', 'mode')->where('active', true)->orderBy('sort_order')->get();

        $preferredTutor = null;
        if ($request->filled('tutor_id')) {
            $preferredTutor = TutorProfile::find($request->tutor_id);
        }

        return view('parent.request_form', compact('areas', 'subjects', 'classes', 'modes', 'preferredTutor'));
    }

    public function submitRequest(Request $request)
    {
        $request->validate([
            'area_id' => 'required|exists:catalog_items,id',
            'subject_id' => 'required|exists:catalog_items,id',
            'class_id' => 'required|exists:catalog_items,id',
            'mode_id' => 'required|exists:catalog_items,id',
            'subarea_text' => 'nullable|string|max:120',
            'budget_min' => 'nullable|numeric',
            'budget_max' => 'nullable|numeric',
            'preferred_time' => 'required|string|max:120',
            'requirements' => 'nullable|string|max:500',
            'tutor_gender_preference' => 'required|string',
        ]);

        $tuitionRequest = TuitionRequest::create([
            'public_ref' => SecurityService::generateRequestRef(),
            'parent_user_id' => Auth::id(),
            'area_id' => $request->area_id,
            'subject_id' => $request->subject_id,
            'class_id' => $request->class_id,
            'mode_id' => $request->mode_id,
            'subarea_text' => $request->subarea_text,
            'budget_min' => $request->budget_min,
            'budget_max' => $request->budget_max,
            'preferred_time' => $request->preferred_time,
            'requirements' => $request->requirements,
            'tutor_gender_preference' => $request->tutor_gender_preference,
            'status' => 'New request',
        ]);

        // Find spatial adjacent areas
        $adjacentAreaIds = AreaAdjacency::where('area_id', $request->area_id)
            ->pluck('nearby_area_id')
            ->toArray();
        $relevantAreaIds = array_merge([$request->area_id], $adjacentAreaIds);

        // Find approved matching tutors
        $matchingTutors = TutorProfile::where('verification_status', 'Approved')
            ->whereHas('catalog', function ($q) use ($relevantAreaIds) {
                $q->whereIn('catalog_items.id', $relevantAreaIds);
            })
            ->whereHas('catalog', function ($q) use ($request) {
                $q->where('catalog_items.id', $request->subject_id);
            });

        if ($request->tutor_gender_preference !== 'Any') {
            $matchingTutors->where('gender', $request->tutor_gender_preference);
        }

        $tutors = $matchingTutors->get();

        foreach ($tutors as $tutor) {
            OpportunityExposure::firstOrCreate([
                'request_id' => $tuitionRequest->id,
                'tutor_id' => $tutor->id,
            ], [
                'shown_at' => now(),
            ]);
        }

        return redirect()->route('parent.dashboard')->with('success', 'Tuition request submitted! Ref: ' . $tuitionRequest->public_ref . '. Maria Tutor management will coordinate your free demo class.');
    }
}
