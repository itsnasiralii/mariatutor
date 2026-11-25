@php
  $isArea = $matchedItem->kind === 'area';
@endphp

<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%); border-radius: 14px; padding: clamp(22px, 4vw, 36px); margin-bottom: 24px; color: #fff;">
  <p class="eyebrow" style="color: #a5b4fc; margin-bottom: 8px;">
    BUREWALA {{ $isArea ? 'SECTOR DIRECTORY' : 'SUBJECT SPECIALISTS' }}
  </p>
  <h1 style="font-size: clamp(1.5rem, 4vw, 2.1rem); font-weight: 800; margin: 0 0 10px 0; color: #fff;">
    {{ $isArea ? "Home Tutors in {$matchedItem->name}, Burewala" : "{$matchedItem->name} Tutors in Burewala" }}
  </h1>
  <p style="color: #cbd5e1; max-width: 720px; margin: 0 0 18px 0;">
    @if($isArea)
      Browse document-reviewed male and female teachers available for home and online tuition in {{ $matchedItem->name }} and adjacent Burewala neighborhoods.
    @else
      Connect with approved {{ $matchedItem->name }} educators across Burewala for Primary, Matric, FSc, ICS, and O/A Level exam preparation.
    @endif
  </p>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    @if(Auth::check() && Auth::user()->isParent())
      <a href="{{ route('parent.request_new') }}" class="btn btn-success">Post Tuition Request →</a>
    @else
      <a href="{{ route('auth.register_parent') }}" class="btn btn-success">Request a Tutor (Free) →</a>
    @endif
    <a href="{{ route('explore.tutors') }}" class="btn btn-secondary">All Burewala Tutors</a>
  </div>
</div>

<h2 style="margin-bottom: 16px;">Matching Approved Tutors ({{ $tutors->total() }})</h2>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 32px;">
  @forelse($tutors as $tutor)
    <article class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom: 12px;">
          <div>
            <span class="badge badge-primary font-mono">{{ $tutor->tutor_code }}</span>
            <h3 style="margin: 6px 0 2px 0; font-size:1.15rem;">{{ $tutor->full_name }}</h3>
            <span class="muted" style="font-size:0.82rem;">{{ $tutor->gender }} Tutor</span>
          </div>
          <span class="badge badge-success">✓ Approved</span>
        </div>

        <p style="font-size:0.86rem; margin-bottom:8px;"><strong>Qualification:</strong> {{ $tutor->qualification ?: 'Credentials Reviewed' }}</p>
        <p style="font-size:0.86rem; margin-bottom:10px;"><strong>Experience:</strong> {{ $tutor->experience ?: 'Experienced educator' }}</p>

        @if($tutor->expected_fee_min)
          <p style="font-size:0.86rem; color:#15803d; font-weight:700; margin-bottom:10px;">
            Expected Fee: PKR {{ number_format($tutor->expected_fee_min) }} – {{ number_format($tutor->expected_fee_max) }} / month
          </p>
        @endif

        <div class="tag-row" style="margin-top: 8px;">
          @foreach($tutor->catalog as $item)
            @if(in_array($item->kind, ['area', 'subject']))
              <a class="tag" href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($item->name)) }}">{{ $item->name }}</a>
            @endif
          @endforeach
        </div>
      </div>

      <div style="margin-top: 18px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
        @if(Auth::check() && Auth::user()->isParent())
          <a href="{{ route('parent.request_new', ['tutor_id' => $tutor->id]) }}" class="btn btn-primary" style="width:100%; justify-content:center;">Book Trial Demo →</a>
        @else
          <a href="{{ route('auth.register_parent') }}" class="btn btn-primary" style="width:100%; justify-content:center;">Request Demo Class →</a>
        @endif
      </div>
    </article>
  @empty
    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 36px;">
      <p class="muted" style="margin-bottom: 14px;">No approved tutors are listed directly under {{ $matchedItem->name }} yet, or nearby sector tutors may cover your home.</p>
      <a href="{{ route('auth.register_parent') }}" class="btn btn-primary">Submit a Custom Request for {{ $matchedItem->name }}</a>
    </div>
  @endforelse
</div>

<div style="margin-bottom: 32px;">
  {{ $tutors->links() }}
</div>

<div class="cards two">
  <section class="card">
    <h2>Other Burewala Sectors</h2>
    <div class="tag-row">
      @foreach($areas as $a)
        <a class="tag" href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($a->name)) }}">{{ $a->name }}</a>
      @endforeach
    </div>
  </section>
  <section class="card">
    <h2>Browse by Subject</h2>
    <div class="tag-row">
      @foreach($subjects as $s)
        <a class="tag" href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($s->name)) }}">{{ $s->name }}</a>
      @endforeach
    </div>
  </section>
</div>
