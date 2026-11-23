<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\CommissionRule;
use App\Models\SystemSetting;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'tutors_count' => TutorProfile::count(),
            'approved_tutors' => TutorProfile::where('verification_status', 'Approved')->count(),
            'requests_count' => TuitionRequest::count(),
            'placed_count' => TuitionRequest::where('status', 'Placed')->count(),
            'commission_rules' => CommissionRule::count(),
        ];

        $users = User::orderBy('created_at', 'desc')->take(10)->get();
        $settings = SystemSetting::all()->pluck('value', 'key');

        return view('superadmin.dashboard', compact('stats', 'users', 'settings'));
    }

    public function catalog()
    {
        $catalogs = CatalogItem::orderBy('kind')->orderBy('sort_order')->get()->groupBy('kind');
        return view('superadmin.catalog', compact('catalogs'));
    }

    public function storeCatalogItem(Request $request)
    {
        $request->validate([
            'kind' => 'required|string',
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        CatalogItem::create([
            'kind' => $request->kind,
            'name' => $request->name,
            'sort_order' => $request->sort_order ?: 0,
            'active' => true,
        ]);

        return back()->with('success', "Item {$request->name} added to {$request->kind}.");
    }

    public function commissionSettings()
    {
        $rules = CommissionRule::orderBy('created_at', 'desc')->get();
        return view('superadmin.commission', compact('rules'));
    }

    public function updateCommission(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'rule_type' => 'required|in:percent,fixed',
            'value' => 'required|integer',
        ]);

        CommissionRule::create([
            'name' => $request->name,
            'rule_type' => $request->rule_type,
            'value' => $request->value,
            'active' => true,
            'effective_at' => now(),
        ]);

        return back()->with('success', 'New commission rule activated.');
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->except('_token') as $key => $val) {
            SystemSetting::set($key, $val);
        }

        return back()->with('success', 'Platform settings updated successfully.');
    }
}
