<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\LeadEvent;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_tutors' => TutorProfile::count(),
            'approved_tutors' => TutorProfile::where('verification_status', 'Approved')->count(),
            'pending_verifications' => TutorProfile::where('verification_status', 'Pending')->count(),
            'total_requests' => TuitionRequest::count(),
            'active_requests' => TuitionRequest::whereNotIn('status', ['Placed', 'Cancelled'])->count(),
        ];

        $pendingTutors = TutorProfile::with('user')
            ->where('verification_status', 'Pending')
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
        $status = $request->input('status', 'all');
        $query = TutorProfile::with(['user', 'catalog']);

        if ($status !== 'all') {
            $query->where('verification_status', ucfirst($status));
        }

        $tutors = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.tutors', compact('tutors', 'status'));
    }

    public function tutorDetail($id)
    {
        $tutor = TutorProfile::with(['user', 'catalog', 'documents'])->findOrFail($id);
        return view('admin.tutor_detail', compact('tutor'));
    }

    public function verifyTutor(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected,Pending',
            'note' => 'nullable|string|max:500',
        ]);

        $tutor = TutorProfile::findOrFail($id);
        $tutor->update([
            'verification_status' => $request->status,
            'verification_note' => $request->note ?: '',
            'approved_at' => $request->status === 'Approved' ? now() : null,
        ]);

        return back()->with('success', "Tutor {$tutor->full_name} status updated to {$request->status}.");
    }

    public function requests()
    {
        $requests = TuitionRequest::with(['area', 'subject', 'schoolClass', 'parent', 'selectedTutor'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.requests', compact('requests'));
    }

    public function requestDetail($id)
    {
        $req = TuitionRequest::with(['area', 'subject', 'schoolClass', 'mode', 'parent', 'selectedTutor', 'exposures.tutor'])
            ->findOrFail($id);

        $approvedTutors = TutorProfile::where('verification_status', 'Approved')->orderBy('full_name')->get();

        return view('admin.request_detail', compact('req', 'approvedTutors'));
    }

    public function assignTutor(Request $request, $id)
    {
        $request->validate([
            'tutor_id' => 'required|exists:tutor_profiles,id',
            'status' => 'required|string',
        ]);

        $req = TuitionRequest::findOrFail($id);
        $req->update([
            'selected_tutor_id' => $request->tutor_id,
            'status' => $request->status,
            'assigned_admin_id' => Auth::id(),
            'contact_released_at' => $request->boolean('release_contact') ? now() : $req->contact_released_at,
        ]);

        LeadEvent::create([
            'request_id' => $req->id,
            'actor_id' => Auth::id(),
            'event_type' => 'Tutor Assigned',
            'details' => "Tutor #{$request->tutor_id} assigned. Status set to {$request->status}.",
            'private' => false,
        ]);

        return back()->with('success', 'Tutor assignment saved successfully.');
    }
}
