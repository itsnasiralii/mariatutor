@extends('layouts.base')
@section('title', 'Parent Requests — Admin Pipeline')

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">LEAD OPERATIONS</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">Parent Requests Queue</h1>
    <p class="muted" style="color: #64748b; margin: 0;">Confidential tuition requests managed under strict privacy controls.</p>
  </div>
  <div>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
</div>

<div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 14px rgba(0,0,0,0.02);">
  <div class="table-wrap">
    <table style="width: 100%; border-collapse: collapse;">
      <thead>
        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
          <th style="padding: 12px 10px;">Reference</th>
          <th style="padding: 12px 10px;">Requirement</th>
          <th style="padding: 12px 10px;">Area</th>
          <th style="padding: 12px 10px;">Timing / Mode</th>
          <th style="padding: 12px 10px;">Status</th>
          <th style="padding: 12px 10px;">Parent Contact</th>
          <th style="padding: 12px 10px;">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($requests as $r)
          <tr style="border-bottom: 1px solid #f1f5f9;">
            <td style="padding: 12px 10px; font-weight: 700; color: #4338ca;">{{ $r->public_ref }}</td>
            <td style="padding: 12px 10px;">
              <b>{{ $r->subject->name ?? 'Subject' }}</b><br>
              <small class="muted">{{ $r->schoolClass->name ?? 'Class' }}</small>
            </td>
            <td style="padding: 12px 10px;">
              {{ $r->area->name ?? 'Burewala' }}
              @if($r->subarea_text)
                <br><small class="muted">{{ $r->subarea_text }}</small>
              @endif
            </td>
            <td style="padding: 12px 10px;">
              {{ $r->preferred_time ?? 'Flexible' }}<br>
              <small class="muted">{{ $r->mode->name ?? 'Home' }}</small>
            </td>
            <td style="padding: 12px 10px;">
              <span class="badge {{ $r->status == 'Placed' ? 'badge-success' : ($r->status == 'Open' ? 'badge-warning' : 'badge-info') }}" style="padding: 4px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;">
                {{ $r->status }}
              </span>
            </td>
            <td style="padding: 12px 10px;">
              {{ $r->parent->parentProfile->full_name ?? $r->parent->email }}<br>
              <small class="muted">{{ $r->parent->phone ?? '—' }}</small>
            </td>
            <td style="padding: 12px 10px;">
              <a href="{{ route('admin.request_detail', $r->id) }}" class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">Open Case</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">No tuition requests found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top: 20px;">
    {{ $requests->links() }}
  </div>
</div>
@endsection
