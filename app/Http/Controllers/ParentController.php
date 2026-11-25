<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\AreaAdjacency;
use App\Models\CatalogItem;
use App\Models\OpportunityExposure;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParentController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $counts = TuitionRequest::where('parent_user_id', $user->id)
            ->selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status IN ('New request', 'Matching', 'Tutor interested') THEN 1 ELSE 0 END) as matching_count,
                SUM(CASE WHEN status IN ('Demo scheduled', 'Confirmed', 'Placed') THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count
            ")
            ->first();

        $stats = [
            'total' => (int) ($counts->total_count ?? 0),
            'matching' => (int) ($counts->matching_count ?? 0),
            'active' => (int) ($counts->active_count ?? 0),
            'completed' => (int) ($counts->completed_count ?? 0),
        ];

        $requests = TuitionRequest::with(['area', 'subject', 'schoolClass', 'mode', 'selectedTutor.user'])
            ->where('parent_user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('parent.dashboard', compact('requests', 'user', 'stats'));
    }

    public function showRequestForm(Request $request)
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $classes = CatalogItem::where('kind', 'class')->where('active', true)->orderBy('sort_order')->get();
        $modes = CatalogItem::where('kind', 'mode')->where('active', true)->orderBy('sort_order')->get();

        $preferredTutor = null;
        if ($request->filled('tutor_id')) {
            $preferredTutor = TutorProfile::where('verification_status', VerificationStatus::Approved->value)
                ->select(['id', 'tutor_code', 'full_name', 'gender', 'qualification'])
                ->find($request->tutor_id);
        }

        return view('parent.request_form', compact('areas', 'subjects', 'classes', 'modes', 'preferredTutor'));
    }

    public function submitRequest(Request $request)
    {
        $request->validate([
            'area_id' => 'required|integer|exists:catalog_items,id',
            'subject_id' => 'required|integer|exists:catalog_items,id',
            'class_id' => 'required|integer|exists:catalog_items,id',
            'mode_id' => 'required|integer|exists:catalog_items,id',
            'subarea_text' => 'nullable|string|max:120',
            'budget_min' => 'nullable|integer|min:0|max:500000',
            'budget_max' => 'nullable|integer|min:0|max:500000|gte:budget_min',
            'preferred_time' => 'required|string|max:120',
            'requirements' => 'nullable|string|max:500',
            'tutor_gender_preference' => 'required|in:Any,Male,Female',
        ]);

        $tuitionRequest = DB::transaction(function () use ($request) {
            $created = TuitionRequest::create([
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
            $relevantAreaIds = array_values(array_unique(array_merge([(int) $request->area_id], $adjacentAreaIds)));

            // Find approved matching tutors
            $matchingQuery = TutorProfile::where('verification_status', VerificationStatus::Approved->value)
                ->whereHas('catalog', function ($q) use ($relevantAreaIds): void {
                    $q->whereIn('catalog_items.id', $relevantAreaIds);
                })
                ->whereHas('catalog', function ($q) use ($request): void {
                    $q->where('catalog_items.id', $request->subject_id);
                });

            if ($request->tutor_gender_preference !== 'Any') {
                $matchingQuery->where('gender', $request->tutor_gender_preference);
            }

            $tutorIds = $matchingQuery->pluck('id');

            if ($tutorIds->isNotEmpty()) {
                $now = now();
                $rows = $tutorIds->map(fn ($tutorId) => [
                    'request_id' => $created->id,
                    'tutor_id' => $tutorId,
                    'shown_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                OpportunityExposure::upsert(
                    $rows,
                    ['request_id', 'tutor_id'],
                    ['updated_at']
                );
            }

            return $created;
        });

        return redirect()->route('parent.dashboard')->with(
            'success',
            'Tuition request submitted! Ref: '.$tuitionRequest->public_ref.'. Maria Tutor management will coordinate your free demo class.'
        );
    }
}
