<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\AuditEvent;
use App\Models\CatalogItem;
use App\Models\CommissionRule;
use App\Models\SystemSetting;
use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public const ALLOWED_CATALOG_KINDS = ['area', 'subject', 'class', 'mode', 'city'];

    public const ALLOWED_SETTING_KEYS = [
        'brand_name',
        'support_whatsapp',
        'city_focus',
        'site_title',
        'city_name',
        'contact_phone',
        'whatsapp_number',
    ];

    public function dashboard()
    {
        $tutorCounts = TutorProfile::selectRaw("
            COUNT(*) as tutors_count,
            SUM(CASE WHEN verification_status = '".VerificationStatus::Approved->value."' THEN 1 ELSE 0 END) as approved_tutors
        ")->first();

        $requestCounts = TuitionRequest::selectRaw("
            COUNT(*) as requests_count,
            SUM(CASE WHEN status = 'Placed' THEN 1 ELSE 0 END) as placed_count
        ")->first();

        $stats = [
            'total_users' => User::count(),
            'tutors_count' => (int) ($tutorCounts->tutors_count ?? 0),
            'approved_tutors' => (int) ($tutorCounts->approved_tutors ?? 0),
            'requests_count' => (int) ($requestCounts->requests_count ?? 0),
            'placed_count' => (int) ($requestCounts->placed_count ?? 0),
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
            'kind' => 'required|string|in:'.implode(',', self::ALLOWED_CATALOG_KINDS),
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $item = CatalogItem::create([
            'kind' => $request->kind,
            'name' => trim((string) $request->name),
            'sort_order' => $request->sort_order ?? 0,
            'active' => true,
        ]);

        AuditEvent::record(
            'catalog_item_created',
            'catalog_item',
            $item->id,
            "Added '{$item->name}' to catalog kind '{$item->kind}'."
        );

        return back()->with('success', "Item {$item->name} added to {$item->kind}.");
    }

    public function commissionSettings()
    {
        $rules = CommissionRule::orderBy('created_at', 'desc')->get();

        return view('superadmin.commission', compact('rules'));
    }

    public function updateCommission(Request $request)
    {
        $maxRule = $request->input('rule_type') === 'percent' ? 'max:100' : 'max:1000000';

        $request->validate([
            'name' => 'required|string|max:100',
            'rule_type' => 'required|in:percent,fixed',
            'value' => ['required', 'integer', 'min:0', $maxRule],
        ]);

        CommissionRule::where('active', true)->update(['active' => false]);

        $rule = CommissionRule::create([
            'name' => trim((string) $request->name),
            'rule_type' => $request->rule_type,
            'value' => $request->value,
            'active' => true,
            'effective_at' => now(),
        ]);

        AuditEvent::record(
            'commission_rule_created',
            'commission_rule',
            $rule->id,
            "Activated commission rule '{$rule->name}': {$rule->value} ({$rule->rule_type})."
        );

        return back()->with('success', 'New commission rule activated.');
    }

    public function updateSettings(Request $request)
    {
        $validationRules = [];
        foreach (self::ALLOWED_SETTING_KEYS as $key) {
            $validationRules[$key] = 'nullable|string|max:255';
        }

        $validated = $request->validate($validationRules);

        $changedKeys = [];
        foreach (self::ALLOWED_SETTING_KEYS as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, (string) ($validated[$key] ?? ''));
                $changedKeys[] = $key;
            }
        }

        if (! empty($changedKeys)) {
            AuditEvent::record(
                'settings_updated',
                'system_setting',
                null,
                'Updated platform settings: '.implode(', ', $changedKeys)
            );
        }

        return back()->with('success', 'Platform settings updated successfully.');
    }
}
