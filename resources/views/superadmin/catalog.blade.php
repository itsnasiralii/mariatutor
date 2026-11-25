@extends('layouts.base')
@section('title', 'Catalog & Coverage Settings | Maria Tutor')
@section('page_title', 'Burewala Sectors & Catalog')

@section('content')
<div class="page-head">
  <div>
    <p class="eyebrow">SYSTEM CONFIGURATION</p>
    <h1 style="font-size: 1.75rem; font-weight: 800; margin: 4px 0 8px 0;">Burewala Sectors &amp; Catalog</h1>
    <p class="muted">Configure locations, subjects, class levels, and teaching modes.</p>
  </div>
  <div>
    <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
</div>

<div class="cards one-two">
  <article class="card">
    <h2>Add Catalog Item</h2>
    <form method="POST" action="{{ route('superadmin.catalog_store') }}">
      @csrf
      <div class="form-group">
        <label for="cat-kind">Category Type</label>
        <select id="cat-kind" name="kind" required>
          <option value="area">Area / Sector</option>
          <option value="subject">Subject</option>
          <option value="class">School Class</option>
          <option value="mode">Teaching Mode</option>
          <option value="city">City</option>
        </select>
      </div>

      <div class="form-group">
        <label for="cat-name">Item Name</label>
        <input id="cat-name" type="text" name="name" maxlength="100" value="{{ old('name') }}" placeholder="e.g. Satellite Town, Biology..." required>
      </div>

      <div class="form-group">
        <label for="cat-order">Sort Order</label>
        <input id="cat-order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}">
      </div>

      <button class="btn btn-primary" type="submit" style="width: 100%; justify-content: center; padding: 12px;">
        + Add to Catalog
      </button>
    </form>
  </article>

  <article class="card">
    <h2>Active Catalog Items</h2>
    @foreach($catalogs as $kind => $items)
      <div style="margin-bottom: 20px;">
        <h3 style="font-size: 0.95rem; font-weight: 700; text-transform: uppercase; color: #4338ca; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 10px;">
          {{ ucfirst(str_replace('_', ' ', $kind)) }} ({{ count($items) }})
        </h3>
        <div class="tag-row">
          @foreach($items as $it)
            <span class="tag">{{ $it->name }}</span>
          @endforeach
        </div>
      </div>
    @endforeach
  </article>
</div>
@endsection
