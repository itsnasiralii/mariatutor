<!-- Header Banner -->
<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%); border-radius: 14px; padding: clamp(20px, 4vw, 36px); margin-bottom: 24px; color: #fff; box-shadow: 0 8px 24px rgba(49, 46, 129, 0.2);">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
    <div>
      <span style="background: rgba(255,255,255,0.15); color: #c7d2fe; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 10px;">
        Burewala Tutor Directory · 100% Background Checked
      </span>
      <h1 style="font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 800; margin: 0 0 8px 0; color: #ffffff; letter-spacing: -0.02em;">
        Explore Areas &amp; Verified Tutors
      </h1>
      <p style="color: #cbd5e1; font-size: 0.95rem; margin: 0; max-width: 650px; line-height: 1.5;">
        Browse registered home and online educators across Burewala's 12 sectors. Review academic qualifications, subject specialties, and experience before requesting a trial class.
      </p>
    </div>

    <div style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%; max-width: 320px;">
      @if(Auth::check() && Auth::user()->isParent())
        <a href="{{ route('parent.request_new') }}" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: #fff; font-weight: 700; padding: 12px 22px; border-radius: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
          ➕ Post Custom Tuition Request
        </a>
      @else
        <a href="{{ route('auth.register_parent') }}" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: #fff; font-weight: 700; padding: 12px 22px; border-radius: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
          📚 Request a Tutor (Free)
        </a>
      @endif
    </div>
  </div>

  <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.12); display: flex; align-items: center; gap: 12px; font-size: 0.84rem; color: #e0e7ff;">
    <span style="font-size: 1.2rem;">🛡️</span>
    <span>
      <strong>Zero-Bypass Privacy Protection:</strong> Teacher phone numbers and private documents are protected. Maria Tutor platform coordinates interviews and verifies identity to safeguard parents and teachers alike.
    </span>
  </div>
</div>

<!-- Search & Filter Controls -->
<div class="panel" style="margin-bottom: 24px; padding: clamp(16px, 3vw, 24px); border-radius: 12px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
  <form method="GET" action="{{ route('explore.tutors') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
    <div>
      <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 6px;">📍 Area / Sector</label>
      <select name="area_id" style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: #f8fafc;">
        <option value="">All Burewala Areas</option>
        @foreach($areas as $a)
          <option value="{{ $a->id }}" {{ request('area_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 6px;">📚 Subject</label>
      <select name="subject_id" style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: #f8fafc;">
        <option value="">All Subjects</option>
        @foreach($subjects as $s)
          <option value="{{ $s->id }}" {{ request('subject_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 6px;">👤 Gender</label>
      <select name="gender" style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: #f8fafc;">
        <option value="">All Genders</option>
        <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Male Tutors Only</option>
        <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Female Tutors Only</option>
      </select>
    </div>

    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; font-weight: 700; justify-content: center;">
        Filter Tutors
      </button>
    </div>
  </form>
</div>

<!-- Tutors Grid -->
<div class="tutors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
  @forelse($tutors as $tutor)
    <div class="tutor-card" style="background:#fff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 20px; display:flex; flex-direction:column; justify-content:space-between; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom: 12px;">
          <div>
            <span style="font-size:0.75rem; font-weight:700; color:#6366f1; background:#e0e7ff; padding:2px 8px; border-radius:4px;">{{ $tutor->tutor_code }}</span>
            <h3 style="margin: 6px 0 2px 0; font-size:1.15rem; color:#1e293b;">{{ $tutor->full_name }}</h3>
            <span style="font-size:0.82rem; color:#64748b;">{{ $tutor->gender }} Tutor</span>
          </div>
          <span style="font-size:0.75rem; font-weight:700; color:#059669; background:#dcfce7; padding:4px 8px; border-radius:999px;">✓ Verified</span>
        </div>

        <p style="font-size:0.86rem; color:#475569; margin-bottom:10px;"><strong>🎓 Qualification:</strong> {{ $tutor->qualification ?: 'Degree Verified' }}</p>
        <p style="font-size:0.86rem; color:#475569; margin-bottom:12px;"><strong>💼 Experience:</strong> {{ $tutor->experience ?: 'Experienced tutor' }}</p>

        @if($tutor->expected_fee_min)
          <p style="font-size:0.86rem; color:#15803d; font-weight:700; margin-bottom:12px;">
            💰 Expected Fee: PKR {{ number_format($tutor->expected_fee_min) }} - {{ number_format($tutor->expected_fee_max) }} / month
          </p>
        @endif

        <div style="margin-top: 10px;">
          <strong style="font-size:0.78rem; color:#64748b; text-transform:uppercase;">Subjects &amp; Areas:</strong>
          <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
            @foreach($tutor->catalog as $item)
              <span style="font-size:0.75rem; background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px;">
                {{ $item->kind == 'area' ? '📍' : '📖' }} {{ $item->name }}
              </span>
            @endforeach
          </div>
        </div>
      </div>

      <div style="margin-top: 20px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
        @if(Auth::check() && Auth::user()->isParent())
          <a href="{{ route('parent.request_new', ['tutor_id' => $tutor->id]) }}" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;">
            Book Trial Demo with This Tutor →
          </a>
        @else
          <a href="{{ route('auth.register_parent') }}" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;">
            Request Trial Demo Class →
          </a>
        @endif
      </div>
    </div>
  @empty
    <div style="grid-column: 1 / -1; padding: 40px; text-align:center; background:#fff; border-radius:12px; border: 1.5px solid #e2e8f0;">
      <p style="color:#64748b; font-size:1.1rem; margin-bottom:12px;">No approved tutors found matching this filter criteria.</p>
      <a href="{{ route('explore.tutors') }}" class="btn btn-secondary">Clear Filter</a>
    </div>
  @endforelse
</div>

<div style="margin-top: 30px;">
  {{ $tutors->links() }}
</div>
