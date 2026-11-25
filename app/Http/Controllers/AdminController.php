<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\AuditEvent;
use App\Models\CommissionRule;
use App\Models\LeadEvent;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public const REQUEST_STATUSES = [
        'New request',
        'Matching',
        'Demo scheduled',
        'Confirmed',
        'Placed',
        'Completed',
        'Cancelled',
    ];

    public function dashboard()
    {
        $tutorCounts = TutorProfile::selectRaw("
            COUNT(*) as total_tutors,
            SUM(CASE WHEN verification_status = 'Approved' THEN 1 ELSE 0 END) as approved_tutors,
            SUM(CASE WHEN verification_status = 'Pending' THEN 1 ELSE 0 END) as pending_verifications
        ")->first();

        $requestCounts = TuitionRequest::selectRaw("
            COUNT(*) as total_requests,
            SUM(CASE WHEN status NOT IN ('Placed', 'Cancelled', 'Completed') THEN 1 ELSE 0 END) as active_requests
        ")->first();

        $stats = [
            'total_users' => User::count(),
            'total_tutors' => (int) ($tutorCounts->total_tutors ?? 0),
            'approved_tutors' => (int) ($tutorCounts->approved_tutors ?? 0),
            'pending_verifications' => (int) ($tutorCounts->pending_verifications ?? 0),
            'total_requests' => (int) ($requestCounts->total_requests ?? 0),
            'active_requests' => (int) ($requestCounts->active_requests ?? 0),
        ];

        $pendingTutors = TutorProfile::with('user')
            ->whereIn('verification_status', [VerificationStatus::Pending->value, VerificationStatus::UnderReview->value])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $recentRequests = TuitionRequest::with(['area', 'subject', 'schoolClass', 'parent'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingTutors', 'recentRequests'));
    }

    public function tutors(Request $request)
    {
        $status = (string) $request->input('status', 'all');
        $query = TutorProfile::with(['user', 'catalog']);

        $enumStatus = VerificationStatus::fromFilter($status);
        if ($enumStatus !== null) {
            $query->where('verification_status', $enumStatus->value);
        }

        $tutors = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.tutors', compact('tutors', 'status'));
    }

    public function tutorDetail($id)
    {
        $tutor = TutorProfile::with(['user', 'catalog', 'documents'])->findOrFail($id);
        $statuses = VerificationStatus::values();

        return view('admin.tutor_detail', compact('tutor', 'statuses'));
    }

    public function verifyTutor(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(VerificationStatus::values())],
            'note' => 'nullable|string|max:500',
        ]);

        $tutor = TutorProfile::findOrFail($id);
        $previousStatus = $tutor->verification_status;

        $tutor->update([
            'verification_status' => $request->status,
            'verification_note' => $request->note ?: '',
            'approved_at' => $request->status === VerificationStatus::Approved->value ? now() : null,
        ]);

        AuditEvent::record(
            'tutor_verification_updated',
            'tutor_profile',
            $tutor->id,
            "Tutor {$tutor->tutor_code} status changed from {$previousStatus} to {$request->status}."
        );

        return back()->with('success', "Tutor {$tutor->full_name} status updated to {$request->status}.");
    }

    public function downloadDocument($id)
    {
        $document = VerificationDocument::with('tutor')->findOrFail($id);
        $relativePath = 'private_documents/'.$document->stored_name;

        if (! Storage::disk('local')->exists($relativePath)) {
            abort(404, 'Verification document file not found in private storage.');
        }

        AuditEvent::record(
            'document_downloaded',
            'verification_document',
            $document->id,
            "Downloaded document '{$document->original_name}' ({$document->document_type}) for tutor #{$document->tutor_id}."
        );

        return Storage::disk('local')->download($relativePath, $document->original_name);
    }

    public function requests()
    {
        $requests = TuitionRequest::with(['area', 'subject', 'schoolClass', 'mode', 'parent.parentProfile', 'selectedTutor'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.requests', compact('requests'));
    }

    public function requestDetail($id)
    {
        $req = TuitionRequest::with([
            'area',
            'subject',
            'schoolClass',
            'mode',
            'parent.parentProfile',
            'selectedTutor.user',
            'exposures.tutor',
        ])->findOrFail($id);

        $approvedTutors = TutorProfile::where('verification_status', VerificationStatus::Approved->value)
            ->orderBy('full_name')
            ->get();

        $requestStatuses = self::REQUEST_STATUSES;

        return view('admin.request_detail', compact('req', 'approvedTutors', 'requestStatuses'));
    }

    public function assignTutor(Request $request, $id)
    {
        $request->validate([
            'tutor_id' => 'required|exists:tutor_profiles,id',
            'status' => ['required', Rule::in(self::REQUEST_STATUSES)],
            'release_contact' => 'nullable|boolean',
        ]);

        $chosenTutor = TutorProfile::findOrFail($request->tutor_id);
        if ($chosenTutor->verification_status !== VerificationStatus::Approved->value) {
            return back()->with('error', 'Only Approved tutors can be assigned to tuition requests.');
        }

        $req = TuitionRequest::findOrFail($id);
        $releaseContact = $request->boolean('release_contact');

        $updates = [
            'selected_tutor_id' => $chosenTutor->id,
            'status' => $request->status,
            'assigned_admin_id' => Auth::id(),
            'contact_released_at' => $releaseContact ? ($req->contact_released_at ?? now()) : $req->contact_released_at,
        ];

        if ($request->status === 'Placed' && empty($req->commission_snapshot_type)) {
            $activeRule = CommissionRule::where('active', true)->orderBy('effective_at', 'desc')->first();
            if ($activeRule) {
                $updates['commission_snapshot_type'] = $activeRule->rule_type;
                $updates['commission_snapshot_value'] = $activeRule->value;
            }
        }

        $req->update($updates);

        LeadEvent::create([
            'request_id' => $req->id,
            'actor_id' => Auth::id(),
            'event_type' => 'Tutor Assigned',
            'details' => "Tutor {$chosenTutor->tutor_code} assigned. Status set to {$request->status}."
                .($releaseContact ? ' Contact released.' : ''),
            'private' => false,
        ]);

        AuditEvent::record(
            $releaseContact ? 'tutor_assigned_contact_released' : 'tutor_assigned',
            'tuition_request',
            $req->id,
            "Assigned tutor {$chosenTutor->tutor_code} to {$req->public_ref} with status '{$request->status}'"
                .($releaseContact ? ' and released contact.' : '.')
        );

        return back()->with('success', 'Tutor assignment saved successfully.');
    }
}
