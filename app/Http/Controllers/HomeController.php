<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\CatalogItem;
use App\Models\SystemSetting;
use App\Models\TutorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /**
     * Columns safe to expose publicly on tutor directory cards (never user phone/email/CNIC).
     */
    private const PUBLIC_TUTOR_COLUMNS = [
        'id',
        'tutor_code',
        'full_name',
        'gender',
        'qualification',
        'experience',
        'expected_fee_min',
        'expected_fee_max',
        'verification_status',
    ];

    public function index()
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();
        $tutorCount = TutorProfile::where('verification_status', VerificationStatus::Approved->value)->count();
        $featuredTutors = TutorProfile::select(self::PUBLIC_TUTOR_COLUMNS)
            ->with(['catalog:id,kind,name'])
            ->where('verification_status', VerificationStatus::Approved->value)
            ->orderBy('tutor_code')
            ->take(6)
            ->get();
        $settings = SystemSetting::all()->pluck('value', 'key');

        return view('index', compact('areas', 'subjects', 'tutorCount', 'featuredTutors', 'settings'));
    }

    public function exploreTutors(Request $request)
    {
        $request->validate([
            'area_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'gender' => 'nullable|in:Male,Female',
        ]);

        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();

        $query = TutorProfile::select(self::PUBLIC_TUTOR_COLUMNS)
            ->with(['catalog:id,kind,name'])
            ->where('verification_status', VerificationStatus::Approved->value);

        if ($request->filled('area_id')) {
            $query->whereHas('catalog', function ($q) use ($request): void {
                $q->where('catalog_items.id', (int) $request->area_id);
            });
        }

        if ($request->filled('subject_id')) {
            $query->whereHas('catalog', function ($q) use ($request): void {
                $q->where('catalog_items.id', (int) $request->subject_id);
            });
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        $tutors = $query->orderBy('tutor_code')->paginate(12)->withQueryString();

        return view('explore_tutors', compact('tutors', 'areas', 'subjects'));
    }

    public function landingPage(string $slug)
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();

        $matchedItem = $areas->concat($subjects)->first(
            static fn (CatalogItem $item) => Str::slug($item->name) === $slug
        );

        if (! $matchedItem) {
            abort(404);
        }

        $tutors = TutorProfile::select(self::PUBLIC_TUTOR_COLUMNS)
            ->with(['catalog:id,kind,name'])
            ->where('verification_status', VerificationStatus::Approved->value)
            ->whereHas('catalog', function ($q) use ($matchedItem): void {
                $q->where('catalog_items.id', $matchedItem->id);
            })
            ->orderBy('id', 'desc')
            ->paginate(12);

        return view('tutors_landing', compact('matchedItem', 'tutors', 'areas', 'subjects'));
    }

    public function contract()
    {
        return view('contract');
    }

    public function privacy()
    {
        return view('privacy');
    }

    public function sitemap()
    {
        $areas = CatalogItem::where('kind', 'area')->where('active', true)->orderBy('sort_order')->get();
        $subjects = CatalogItem::where('kind', 'subject')->where('active', true)->orderBy('sort_order')->get();

        $content = view('sitemap', compact('areas', 'subjects'))->render();

        return response($content, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function healthz()
    {
        try {
            DB::select('SELECT 1');

            return response()->json(['status' => 'ok']);
        } catch (\Throwable) {
            return response()->json(['status' => 'error'], 503);
        }
    }
}
