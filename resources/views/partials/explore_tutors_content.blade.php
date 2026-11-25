<!-- Header Banner -->
<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%); border-radius: 14px; padding: clamp(20px, 4vw, 36px); margin-bottom: 24px; color: #fff; box-shadow: 0 8px 24px rgba(49, 46, 129, 0.2);">
  <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
    <div>
      <span style="background: rgba(255,255,255,0.15); color: #c7d2fe; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 10px;">
        Burewala Tutor Directory · Document-Reviewed Tutors
      </span>
      <h1 style="font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 800; margin: 0 0 8px 0; color: #ffffff; letter-spacing: -0.02em;">
        Explore Areas &amp; Verified Tutors
      </h1>
      <p style="color: #cbd5e1; font-size: 0.95rem; margin: 0; max-width: 650px; line-height: 1.5;">
        Browse approved home and online educators across Burewala's 12 sectors. Review academic qualifications, subject specialties, and expected fees before requesting a demo class.
      </p>
    </div>

    <div style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%; max-width: 320px;">
      @if(Auth::check() && Auth::user()->isParent())
        <a href="{{ route('parent.request_new') }}" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: #fff; font-weight: 700; padding: 12px 22px;">
          <span aria-hidden="true">➕</span> Post Custom Tuition Request
        </a>
      @else
        <a href="{{ route('auth.register_parent') }}" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: #fff; font-weight: 700; padding: 12px 22px;">
          <span aria-hidden="true">📚</span> Request a Tutor (Free)
        </a>
      @endif
    </div>
  </div>

  <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.12); display: flex; align-items: center; gap: 12px; font-size: 0.84rem; color: #e0e7ff;">
    <span style="font-size: 1.2rem;" aria-hidden="true">🛡️</span>
    <span>
      <strong>Zero-Bypass Privacy Protection:</strong> Teacher phone numbers and private documents are protected. Maria Tutor coordinates interviews and reviews identity documents to safeguard parents and teachers alike.
    </span>
  </div>
</div>

<!-- Search & Filter Controls -->
<div class="panel" style="margin-bottom: 24px;">
  <form method="GET" action="{{ route('explore.tutors') }}" class="form-grid">
    <div>
      <label for="filter-area"><span aria-hidden="true">📍</span> Area / Sector</label>
      <select id="filter-area" name="area_id">
        <option value="">All Burewala Areas</option>
        @foreach($areas as $a)
          <option value="{{ $a->id }}" {{ (string) request('area_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label for="filter-subject"><span aria-hidden="true">📚</span> Subject</label>
      <select id="filter-subject" name="subject_id">
        <option value="">All Subjects</option>
        @foreach($subjects as $s)
          <option value="{{ $s->id }}" {{ (string) request('subject_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label for="filter-gender"><span aria-hidden="true">👤</span> Gender</label>
      <select id="filter-gender" name="gender">
        <option value="">All Genders</option>
        <option value="Male" {{ request('gender') === 'Male' ? 'selected' : '' }}>Male Tutors Only</option>
        <option value="Female" {{ request('gender') === 'Female' ? 'selected' : '' }}>Female Tutors Only</option>
      </select>
    </div>

    <div>
      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 11px; justify-content: center;">
        Filter Tutors
      </button>
    </div>
  </form>
</div>

<!-- Tutors Grid -->
<h2 style="margin-bottom: 16px;">Approved Educators ({{ $tutors->total() }})</h2>
<div class="tutors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
  @forelse($tutors as $tutor)
    <article class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom: 12px;">
          <div>
            <span class="badge badge-primary font-mono">{{ $tutor->tutor_code }}</span>
            <h3 style="margin: 6px 0 2px 0; font-size:1.15rem; color:#1e293b;">{{ $tutor->full_name }}</h3>
            <span style="font-size:0.82rem; color:#64748b;">{{ $tutor->gender }} Tutor</span>
          </div>
          <span class="badge badge-success">✓ Approved</span>
        </div>

        <p style="font-size:0.86rem; color:#475569; margin-bottom:10px;"><strong><span aria-hidden="true">🎓</span> Qualification:</strong> {{ $tutor->qualification ?: 'Credentials Reviewed' }}</p>
        <p style="font-size:0.86rem; color:#475569; margin-bottom:12px;"><strong><span aria-hidden="true">💼</span> Experience:</strong> {{ $tutor->experience ?: 'Experienced tutor' }}</p>

        @if($tutor->expected_fee_min)
          <p style="font-size:0.86rem; color:#15803d; font-weight:700; margin-bottom:12px;">
            <span aria-hidden="true">💰</span> Expected Fee: PKR {{ number_format($tutor->expected_fee_min) }} – {{ number_format($tutor->expected_fee_max) }} / month
          </p>
        @endif

        <div style="margin-top: 10px;">
          <strong style="font-size:0.78rem; color:#64748b; text-transform:uppercase;">Subjects &amp; Areas:</strong>
          <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
            @foreach($tutor->catalog as $item)
              @if(in_array($item->kind, ['area', 'subject']))
                <a href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($item->name)) }}" style="font-size:0.75rem; background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px;">
                  <span aria-hidden="true">{{ $item->kind === 'area' ? '📍' : '📖' }}</span> {{ $item->name }}
                </a>
              @endif
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
    </article>
  @empty
    <div class="card" style="grid-column: 1 / -1; padding: 40px; text-align:center;">
      <p style="color:#64748b; font-size:1.05rem; margin-bottom:12px;">No approved tutors found matching this filter criteria.</p>
      <a href="{{ route('explore.tutors') }}" class="btn btn-secondary">Clear Filter</a>
    </div>
  @endforelse
</div>

<div style="margin-top: 30px;">
  {{ $tutors->links() }}
</div>
