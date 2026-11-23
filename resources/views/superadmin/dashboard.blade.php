@extends('layouts.base')
@section('title', 'Super Admin Dashboard — Maria Tutor')

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">PLATFORM GOVERNANCE</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">Super Admin Control Center</h1>
    <p class="muted" style="color: #64748b; margin: 0;">Full authority over Maria Tutor operations, Burewala coverage, and revenue settings.</p>
  </div>
  <div style="display: flex; gap: 8px;">
    <a href="{{ route('superadmin.catalog') }}" class="btn btn-secondary">📍 Sectors & Catalog</a>
    <a href="{{ route('superadmin.commission') }}" class="btn btn-primary">💰 Commission Rules</a>
  </div>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">
  <div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px;">
    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Users</div>
    <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 6px;">{{ $stats['total_users'] }}</div>
  </div>
  <div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px;">
    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Tutors</div>
    <div style="font-size: 2rem; font-weight: 800; color: #4338ca; margin-top: 6px;">{{ $stats['tutors_count'] }}</div>
  </div>
  <div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px;">
    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Verified Tutors</div>
    <div style="font-size: 2rem; font-weight: 800; color: #16a34a; margin-top: 6px;">{{ $stats['approved_tutors'] }}</div>
  </div>
  <div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px;">
    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Tuition Requests</div>
    <div style="font-size: 2rem; font-weight: 800; color: #0284c7; margin-top: 6px;">{{ $stats['requests_count'] }}</div>
  </div>
  <div class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px;">
    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Placed Cases</div>
    <div style="font-size: 2rem; font-weight: 800; color: #059669; margin-top: 6px;">{{ $stats['placed_count'] }}</div>
  </div>
</div>

<div class="cards two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">
  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Platform Settings</h2>
    <form method="POST" action="{{ route('superadmin.settings_update') }}">
      @csrf
      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Platform Brand Name
        <input type="text" name="brand_name" value="{{ $settings['brand_name'] ?? 'Maria Tutor' }}" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;">
      </label>
      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Official Support WhatsApp
        <input type="text" name="support_whatsapp" value="{{ $settings['support_whatsapp'] ?? '03001234567' }}" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;">
      </label>
      <label style="display: block; margin-bottom: 16px; font-weight: 600;">City Focus
        <input type="text" name="city_focus" value="{{ $settings['city_focus'] ?? 'Burewala' }}" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;">
      </label>
      <button class="btn btn-primary" type="submit" style="padding: 10px 20px; font-weight: 700; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer;">Save Settings</button>
    </form>
  </article>

  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 16px;">Recent User Registrations</h2>
    <div class="table-wrap">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
            <th style="padding: 8px;">User / Phone</th>
            <th style="padding: 8px;">Role</th>
            <th style="padding: 8px;">Joined</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $u)
            <tr style="border-bottom: 1px solid #f1f5f9;">
              <td style="padding: 8px;">
                <b>{{ $u->phone }}</b><br>
                <small class="muted">{{ $u->email }}</small>
              </td>
              <td style="padding: 8px;"><span class="badge badge-info">{{ $u->role }}</span></td>
              <td style="padding: 8px; font-size: 0.85rem;">{{ $u->created_at->format('M d') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </article>
</div>
@endsection
