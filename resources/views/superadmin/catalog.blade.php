@extends('layouts.base')
@section('title', 'Catalog & Coverage Settings — Maria Tutor')

@section('content')
<div class="page-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
  <div>
    <p class="eyebrow" style="color: #6366f1; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; margin: 0 0 4px 0;">SYSTEM CONFIGURATION</p>
    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">Burewala Sectors & Catalog</h1>
    <p class="muted" style="color: #64748b; margin: 0;">Configure locations, subjects, class levels, and teaching modes.</p>
  </div>
  <div>
    <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
</div>

<div class="cards two" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 28px;">
  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 16px;">Add Catalog Item</h2>
    <form method="POST" action="{{ route('superadmin.catalog_store') }}">
      @csrf
      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Category Type
        <select name="kind" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;" required>
          <option value="area">Area / Sector</option>
          <option value="subject">Subject</option>
          <option value="school_class">School Class</option>
          <option value="mode">Teaching Mode</option>
        </select>
      </label>

      <label style="display: block; margin-bottom: 12px; font-weight: 600;">Item Name
        <input type="text" name="name" placeholder="e.g. Satellite Town, Biology..." class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;" required>
      </label>

      <label style="display: block; margin-bottom: 16px; font-weight: 600;">Sort Order
        <input type="number" name="sort_order" value="0" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 4px;">
      </label>

      <button class="btn btn-primary" type="submit" style="width: 100%; padding: 12px; font-weight: 700; background: #4f46e5; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
        + Add to Catalog
      </button>
    </form>
  </article>

  <article class="card" style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 24px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 16px;">Active Catalog Items</h2>
    @foreach($catalogs as $kind => $items)
      <div style="margin-bottom: 20px;">
        <h3 style="font-size: 1rem; font-weight: 700; text-transform: uppercase; color: #4338ca; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 10px;">
          {{ ucfirst(str_replace('_', ' ', $kind)) }} ({{ count($items) }})
        </h3>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
          @foreach($items as $it)
            <span style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #1e293b;">
              {{ $it->name }}
            </span>
          @endforeach
        </div>
      </div>
    @endforeach
  </article>
</div>
@endsection
