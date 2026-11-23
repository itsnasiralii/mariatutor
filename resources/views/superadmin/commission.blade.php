@extends('layouts.base')
@section('title', 'Commission Rules & Placement Controls — Super Admin')

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">MONETIZATION & REVENUE</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">💰 Commission Rules Management</h1>
    <p class="muted" style="color: #64748b; margin: 0;">Configure platform commission structures for Burewala tuition placements.</p>
  </div>
  <div>
    <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
</div>

<div class="cards two" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 28px;">
  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 16px;">Create Commission Rule</h2>
    <form method="POST" action="{{ route('superadmin.commission_update') }}">
      @csrf
      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Rule Name
        <input type="text" name="name" placeholder="e.g. Standard Placement Fee" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;" required>
      </label>

      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Calculation Type
        <select name="rule_type" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;" required>
          <option value="percent">Percentage of 1st Month Fee (%)</option>
          <option value="fixed">Fixed Amount (PKR)</option>
        </select>
      </label>

      <label style="display: block; margin-bottom: 16px; font-weight: 600;">Value
        <input type="number" name="value" placeholder="e.g. 50 (for 50%) or 2000 (PKR)" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;" required>
      </label>

      <button class="btn btn-primary" type="submit" style="width: 100%; padding: 12px; font-weight: 700; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
        Activate Rule
      </button>
    </form>
  </article>

  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 16px;">Active & Historical Rules</h2>
    <div class="table-wrap">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
            <th style="padding: 10px;">Rule Name</th>
            <th style="padding: 10px;">Rate</th>
            <th style="padding: 10px;">Status</th>
            <th style="padding: 10px;">Created</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rules as $r)
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 10px; font-weight: 700;">{{ $r->name }}</td>
              <td style="padding: 10px; font-weight: 800; color: #4338ca;">
                {{ $r->value }}{{ $r->rule_type == 'percent' ? '%' : ' PKR' }}
              </td>
              <td style="padding: 10px;">
                <span class="badge {{ $r->active ? 'badge-success' : 'badge-warning' }}" style="padding: 4px 8px; border-radius: 999px; font-size: 0.8rem;">
                  {{ $r->active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td style="padding: 10px; font-size: 0.85rem; color: #64748b;">
                {{ $r->created_at->format('M d, Y') }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" style="text-align: center; padding: 20px; color: #64748b;">No rules created yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</div>
@endsection
