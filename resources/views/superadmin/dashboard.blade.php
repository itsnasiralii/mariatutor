@extends('layouts.base')
@section('title', 'Super Admin Dashboard | Maria Tutor')
@section('page_title', 'Super Admin Control Center')

@section('content')
<div class="page-head">
  <div>
    <p class="eyebrow">PLATFORM GOVERNANCE</p>
    <h1 style="font-size: 1.75rem; font-weight: 800; margin: 4px 0 8px 0;">Super Admin Control Center</h1>
    <p class="muted">Full authority over Maria Tutor operations, Burewala coverage, and revenue settings.</p>
  </div>
  <div class="actions">
    <a href="{{ route('superadmin.catalog') }}" class="btn btn-secondary"><span aria-hidden="true">📍</span> Sectors &amp; Catalog</a>
    <a href="{{ route('superadmin.commission') }}" class="btn btn-primary"><span aria-hidden="true">💰</span> Commission Rules</a>
  </div>
</div>

<div class="stats-grid">
  <div class="card">
    <div class="eyebrow" style="color: #64748b;">Total Users</div>
    <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 6px;">{{ $stats['total_users'] }}</div>
  </div>
  <div class="card">
    <div class="eyebrow" style="color: #64748b;">Total Tutors</div>
    <div style="font-size: 2rem; font-weight: 800; color: #4338ca; margin-top: 6px;">{{ $stats['tutors_count'] }}</div>
  </div>
  <div class="card">
    <div class="eyebrow" style="color: #64748b;">Verified Tutors</div>
    <div style="font-size: 2rem; font-weight: 800; color: #16a34a; margin-top: 6px;">{{ $stats['approved_tutors'] }}</div>
  </div>
  <div class="card">
    <div class="eyebrow" style="color: #64748b;">Tuition Requests</div>
    <div style="font-size: 2rem; font-weight: 800; color: #0284c7; margin-top: 6px;">{{ $stats['requests_count'] }}</div>
  </div>
  <div class="card">
    <div class="eyebrow" style="color: #64748b;">Placed Cases</div>
    <div style="font-size: 2rem; font-weight: 800; color: #059669; margin-top: 6px;">{{ $stats['placed_count'] }}</div>
  </div>
</div>

<div class="cards two">
  <article class="card">
    <h2>Platform Settings</h2>
    <form method="POST" action="{{ route('superadmin.settings_update') }}">
      @csrf
      <div class="form-group">
        <label for="set-brand">Platform Brand Name</label>
        <input id="set-brand" type="text" name="brand_name" maxlength="255" value="{{ old('brand_name', $settings['brand_name'] ?? 'Maria Tutor') }}">
      </div>
      <div class="form-group">
        <label for="set-phone">Official Contact Phone</label>
        <input id="set-phone" type="text" name="contact_phone" maxlength="255" value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}" placeholder="e.g. +92 300 0000000">
      </div>
      <div class="form-group">
        <label for="set-wa">Official Support WhatsApp</label>
        <input id="set-wa" type="text" name="support_whatsapp" maxlength="255" value="{{ old('support_whatsapp', $settings['support_whatsapp'] ?? '') }}" placeholder="e.g. +92 300 0000000">
      </div>
      <div class="form-group">
        <label for="set-city">City Focus</label>
        <input id="set-city" type="text" name="city_focus" maxlength="255" value="{{ old('city_focus', $settings['city_focus'] ?? 'Burewala') }}">
      </div>
      <button class="btn btn-primary" type="submit">Save Settings</button>
    </form>
  </article>

  <article class="card">
    <h2>Recent User Registrations</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>User / Contact</th>
            <th>Role</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $u)
            <tr>
              <td>
                <b>{{ $u->name ?: ($u->phone ?: $u->email) }}</b><br>
                <small class="muted">{{ $u->email ?: $u->phone }}</small>
              </td>
              <td><span class="badge badge-info">{{ $u->role }}</span></td>
              <td style="font-size: 0.85rem;">{{ $u->created_at->format('M d') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </article>
</div>
@endsection
