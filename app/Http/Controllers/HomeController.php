<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\TutorProfile;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $tutorCount = TutorProfile::where('verification_status', 'Approved')->count();

        return view('index', compact('areas', 'subjects', 'tutorCount'));
    }

    public function exploreTutors(Request $request)
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();

        $query = TutorProfile::with(['catalog', 'user'])
            ->where('verification_status', 'Approved');

        if ($request->filled('area_id')) {
            $query->whereHas('catalog', function ($q) use ($request) {
                $q->where('catalog_items.id', $request->area_id);
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('catalog', function ($q) use ($request) {
                $q->where('catalog_items.id', $request->subject_id);
            });
        }

        if ($request->filled('gender') && in_array($request->gender, ['Male', 'Female'])) {
            $query->where('gender', $request->gender);
        }

        $tutors = $query->paginate(12)->withQueryString();

        return view('explore_tutors', compact('tutors', 'areas', 'subjects'));
    }

    public function contract()
    {
        return view('contract');
    }

    public function privacy()
    {
        return view('privacy');
    }

    public function healthz()
    {
        return response()->json([
            'status' => 'ok',
            'database' => 'ok',
            'framework' => 'Laravel 11/12',
        ]);
    }
}
