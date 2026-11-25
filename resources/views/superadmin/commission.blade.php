@extends('layouts.base')
@section('title', 'Commission Rules | Maria Tutor')
@section('page_title', 'Commission Rules')

@section('content')
<div class="page-head">
  <div>
    <p class="eyebrow">MONETIZATION &amp; REVENUE</p>
    <h1 style="font-size: 1.75rem; font-weight: 800; margin: 4px 0 8px 0;">Commission Rules Management</h1>
    <p class="muted">Configure platform commission structures for Burewala tuition placements.</p>
  </div>
  <div>
    <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
</div>

<div class="cards one-two">
  <article class="card">
    <h2>Create Commission Rule</h2>
    <form method="POST" action="{{ route('superadmin.commission_update') }}">
      @csrf
      <div class="form-group">
        <label for="comm-name">Rule Name</label>
        <input id="comm-name" type="text" name="name" maxlength="100" value="{{ old('name') }}" placeholder="e.g. Standard Placement Fee" required>
      </div>

      <div class="form-group">
        <label for="comm-type">Calculation Type</label>
        <select id="comm-type" name="rule_type" required>
          <option value="percent" {{ old('rule_type') === 'percent' ? 'selected' : '' }}>Percentage of 1st Month Fee (0–100%)</option>
          <option value="fixed" {{ old('rule_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (PKR)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="comm-val">Value</label>
        <input id="comm-val" type="number" name="value" min="0" value="{{ old('value') }}" placeholder="e.g. 50 (for 50%) or 2000 (PKR)" required>
      </div>

      <button class="btn btn-primary" type="submit" style="width: 100%; justify-content: center; padding: 12px;">
        Activate Rule
      </button>
    </form>
  </article>

  <article class="card">
    <h2>Active &amp; Historical Rules</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rule Name</th>
            <th>Rate</th>
            <th>Status</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rules as $r)
            <tr>
              <td style="font-weight: 700;">{{ $r->name }}</td>
              <td style="font-weight: 800; color: #4338ca;">
                {{ $r->value }}{{ $r->rule_type === 'percent' ? '%' : ' PKR' }}
              </td>
              <td>
                <span class="badge {{ $r->active ? 'badge-success' : 'badge-gray' }}">
                  {{ $r->active ? 'Active' : 'Archived' }}
                </span>
              </td>
              <td class="muted" style="font-size: 0.85rem;">
                {{ $r->created_at->format('M d, Y') }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="empty">No commission rules created yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</div>
@endsection
