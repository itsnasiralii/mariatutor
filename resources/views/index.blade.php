@extends('layouts.base')

@php
  $brandName = $settings['brand_name'] ?? $settings['site_title'] ?? 'Maria Tutor';
  $cityName = $settings['city_focus'] ?? $settings['city_name'] ?? 'Burewala';
  $contactPhone = $settings['contact_phone'] ?? $settings['support_whatsapp'] ?? '';
@endphp

@section('title', 'Home Tutor in Burewala – Verified Tutors | Maria Tutor')
@section('meta_description', 'Find document-verified male and female home and online tutors in Burewala, Punjab. Free parent matching across Satellite Town, Model Town & all 12 sectors.')
@section('canonical', route('home'))

@push('structured_data')
@php
  $orgSchema = [
      '@context' => 'https://schema.org',
      '@type' => 'EducationalOrganization',
      'name' => $brandName,
      'url' => route('home'),
      'logo' => asset('images/icon-512.png'),
      'image' => asset('images/og-preview.png'),
      'description' => 'Home and online tutor matching platform in Burewala, Punjab, Pakistan connecting parents with CNIC and degree-reviewed teachers.',
      'areaServed' => [
          '@type' => 'City',
          'name' => $cityName,
          'containedInPlace' => [
              '@type' => 'AdministrativeArea',
              'name' => 'Punjab, Pakistan',
          ],
      ],
  ];
  if (!empty($contactPhone)) {
      $orgSchema['telephone'] = $contactPhone;
  }
  $faqSchema = [
      '@context' => 'https://schema.org',
      '@type' => 'FAQPage',
      'mainEntity' => [
          [
              '@type' => 'Question',
              'name' => 'Is requesting a home tutor in Burewala free for parents?',
              'acceptedAnswer' => [
                  '@type' => 'Answer',
                  'text' => 'Yes. Submitting a tuition request and coordinating an initial trial demo class through Maria Tutor is completely free for parents and guardians.',
              ],
          ],
          [
              '@type' => 'Question',
              'name' => 'How does Maria Tutor verify teachers?',
              'acceptedAnswer' => [
                  '@type' => 'Answer',
                  'text' => 'Registered tutors submit CNIC and educational degree documents to our private verification vault for staff review before their profiles are approved.',
              ],
          ],
          [
              '@type' => 'Question',
              'name' => 'Are parent phone numbers and house addresses kept private?',
              'acceptedAnswer' => [
                  '@type' => 'Answer',
                  'text' => 'Yes. Tutors only see sector-level opportunities (such as Satellite Town or Model Town). Contact details are shared only after administrative authorization.',
              ],
          ],
      ],
  ];
@endphp
<script type="application/ld+json">{!! json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('public_content')
<div class="home-layout">

  <!-- Responsive Navbar -->
  @include('partials.nav_home')

  <!-- Hero -->
  <section class="hero">
    <p class="eyebrow" style="color:#a5b4fc;margin-bottom:12px;">{{ strtoupper($cityName) }} · PUNJAB · PAKISTAN</p>
    <h1>Find the right home tutor in {{ $cityName }},<br><span>through a safe &amp; managed process.</span></h1>
    <p>Parents submit class, subjects &amp; timing needs. Approved tutors receive anonymized opportunities across {{ $cityName }}'s 12 residential sectors.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="{{ route('auth.register_parent') }}" style="background:#fff;color:#4f46e5;font-weight:700;"><span aria-hidden="true">📚</span> Request a Tutor (Parents)</a>
      <a class="btn" href="{{ route('explore.tutors') }}" style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.4);font-weight:700;"><span aria-hidden="true">🔍</span> Explore Tutors Directory</a>
      <a class="btn" href="{{ route('auth.register_tutor') }}" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);font-weight:700;"><span aria-hidden="true">🎓</span> Join as a Tutor</a>
      <a class="btn" href="{{ route('auth.parent_login') }}" style="background:transparent;color:#fed7aa;border:1.5px solid rgba(255,255,255,.3);">Parent Login →</a>
    </div>
  </section>

  <!-- Stats Bar -->
  <div class="stats-bar">
    <div class="stat-item">
      <strong>{{ $tutorCount }}</strong>
      <span>Approved Tutors</span>
    </div>
    <div class="stat-item">
      <strong>Reviewed</strong>
      <span>CNIC &amp; Degree Docs</span>
    </div>
    <div class="stat-item">
      <strong>Private</strong>
      <span>Contact Policy</span>
    </div>
    <div class="stat-item">
      <strong>Free</strong>
      <span>For Parents</span>
    </div>
  </div>

  <!-- Primary Options for Parents & Tutors -->
  <section class="features-section" style="padding-bottom: 24px;">
    <p class="eyebrow" style="text-align:center;margin-bottom:8px;">CHOOSE YOUR PATHWAY</p>
    <h2 style="text-align:center;margin-bottom:28px;">Connect with trusted home &amp; online tuition in {{ $cityName }}</h2>

    <div class="pathway-grid">

      <!-- 1. Parent Card -->
      <div class="pathway-card parent-card" style="background:#ffffff; border:2.5px solid #fed7aa; border-radius:18px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(234,88,12,0.1); display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
            <div style="width:52px; height:52px; border-radius:14px; background:#ffedd5; display:flex; align-items:center; justify-content:center; font-size:1.8rem;" aria-hidden="true">
              👨‍👩‍👧
            </div>
            <div>
              <h3 style="font-size:1.25rem; font-weight:800; color:#9a3412; margin:0;">Parents &amp; Students</h3>
              <span style="font-size:0.84rem; color:#c2410c; font-weight:600;">Find an Experienced Home or Online Tutor</span>
            </div>
          </div>
          <p style="color:#64748b; font-size:0.9rem; margin-bottom:18px; line-height:1.55;">
            Submit your child's class, subject, and area in {{ $cityName }}. Our team arranges a tutor demo without publishing your phone number publicly.
          </p>
          <ul style="font-size:0.86rem; color:#475569; margin-bottom:24px; display:flex; flex-direction:column; gap:8px;">
            <li>✓ Free tuition matching for {{ $cityName }} families</li>
            <li>✓ CNIC &amp; academic document-reviewed tutors</li>
            <li>✓ Home visits across all 12 {{ $cityName }} sectors</li>
            <li>✓ Private contact protection until authorized</li>
          </ul>
        </div>
        <div class="pathway-card-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
          <a href="{{ route('auth.register_parent') }}" class="btn" style="flex:1; min-width:140px; justify-content:center; background:#ea580c; color:#fff; font-weight:700; padding:12px; font-size:0.92rem;">
            Request a Tutor →
          </a>
          <a href="{{ route('auth.parent_login') }}" class="btn" style="background:#fff; color:#ea580c; border:1.5px solid #ea580c; font-weight:700; padding:12px 18px; font-size:0.92rem; min-width:120px; justify-content:center;">
            Parent Login
          </a>
        </div>
      </div>

      <!-- 2. Tutor Card -->
      <div class="pathway-card tutor-card" style="background:#ffffff; border:2.5px solid #bbf7d0; border-radius:18px; padding:32px 28px; box-shadow:0 10px 25px -5px rgba(22,163,74,0.1); display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
            <div style="width:52px; height:52px; border-radius:14px; background:#dcfce7; display:flex; align-items:center; justify-content:center; font-size:1.8rem;" aria-hidden="true">
              🎓
            </div>
            <div>
              <h3 style="font-size:1.25rem; font-weight:800; color:#166534; margin:0;">Tutors &amp; Teachers</h3>
              <span style="font-size:0.84rem; color:#15803d; font-weight:600;">Teach &amp; Earn in {{ $cityName }}</span>
            </div>
          </div>
          <p style="color:#64748b; font-size:0.9rem; margin-bottom:18px; line-height:1.55;">
            Build your tutor profile and upload degree credentials. Browse anonymized student leads across Satellite Town, College Road, Model Town, and nearby sectors.
          </p>
          <ul style="font-size:0.86rem; color:#475569; margin-bottom:24px; display:flex; flex-direction:column; gap:8px;">
            <li>✓ Set your own expected monthly tuition fee</li>
            <li>✓ Zero platform fee until confirmed placement</li>
            <li>✓ Keyed HMAC CNIC hashing &amp; private document vault</li>
            <li>✓ Spatial matching to nearby student sectors</li>
          </ul>
        </div>
        <div class="pathway-card-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
          <a href="{{ route('auth.register_tutor') }}" class="btn" style="flex:1; min-width:140px; justify-content:center; background:#16a34a; color:#fff; font-weight:700; padding:12px; font-size:0.92rem;">
            Join as Tutor →
          </a>
          <a href="{{ route('auth.tutor_login') }}" class="btn" style="background:#fff; color:#16a34a; border:1.5px solid #16a34a; font-weight:700; padding:12px 18px; font-size:0.92rem; min-width:120px; justify-content:center;">
            Tutor Login
          </a>
        </div>
      </div>

    </div>
  </section>

  <!-- How it Works -->
  @if(isset($featuredTutors) && count($featuredTutors) > 0)
  <section class="features-section" style="padding-bottom: 12px;">
    <p class="eyebrow" style="text-align:center;margin-bottom:8px;">MEET OUR EDUCATORS</p>
    <h2 style="text-align:center;margin-bottom:12px;">Featured Verified Tutors in {{ $cityName }}</h2>
    <p style="text-align:center;color:var(--muted);max-width:640px;margin:0 auto 32px;">
      Browse qualified male and female subject specialists across {{ $cityName }}. Request a coordinated trial demo class with any educator.
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 20px; margin-bottom: 28px;">
      @foreach($featuredTutors as $tutor)
        <article class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom: 12px;">
              <div>
                <span class="badge badge-primary font-mono">{{ $tutor->tutor_code }}</span>
                <h3 style="margin: 6px 0 2px 0; font-size:1.12rem; color:#1e293b;">{{ $tutor->full_name }}</h3>
                <span style="font-size:0.82rem; color:#64748b;">{{ $tutor->gender }} Tutor</span>
              </div>
              <span class="badge badge-success">✓ Verified</span>
            </div>

            <p style="font-size:0.85rem; color:#475569; margin-bottom:8px;"><strong><span aria-hidden="true">🎓</span> Qualification:</strong> {{ $tutor->qualification }}</p>
            <p style="font-size:0.85rem; color:#475569; margin-bottom:10px;"><strong><span aria-hidden="true">💼</span> Experience:</strong> {{ $tutor->experience }}</p>

            @if($tutor->expected_fee_min)
              <p style="font-size:0.85rem; color:#15803d; font-weight:700; margin-bottom:10px;">
                <span aria-hidden="true">💰</span> Expected Fee: PKR {{ number_format($tutor->expected_fee_min) }} – {{ number_format($tutor->expected_fee_max) }} / month
              </p>
            @endif

            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:8px;">
              @foreach($tutor->catalog as $item)
                @if(in_array($item->kind, ['area', 'subject']))
                  <a href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($item->name)) }}" style="font-size:0.74rem; background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px;">
                    <span aria-hidden="true">{{ $item->kind === 'area' ? '📍' : '📖' }}</span> {{ $item->name }}
                  </a>
                @endif
              @endforeach
            </div>
          </div>

          <div style="margin-top: 18px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
            @if(Auth::check() && Auth::user()->isParent())
              <a href="{{ route('parent.request_new', ['tutor_id' => $tutor->id]) }}" class="btn btn-primary" style="width:100%; justify-content:center;">
                Request Trial Demo Class →
              </a>
            @else
              <a href="{{ route('auth.register_parent') }}" class="btn btn-primary" style="width:100%; justify-content:center;">
                Request Trial Demo Class →
              </a>
            @endif
          </div>
        </article>
      @endforeach
    </div>

    <div style="text-align:center;">
      <a href="{{ route('explore.tutors') }}" class="btn btn-primary" style="padding: 12px 28px; font-size: 0.96rem;">
        Explore All {{ $tutorCount }} Verified Tutors in {{ $cityName }} →
      </a>
    </div>
  </section>
  @endif

  <section class="features-section">
    <p class="eyebrow" style="text-align:center;margin-bottom:8px;">HOW IT WORKS</p>
    <h2>A platform-managed introduction</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon" aria-hidden="true">📝</div>
        <h3>01 · Share the requirement</h3>
        <p>Choose subject, class, {{ $cityName }} area, budget and preferred time. Takes less than 2 minutes.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" aria-hidden="true">🔍</div>
        <h3>02 · We shortlist carefully</h3>
        <p>Approved tutors see only an area-level opportunity, then choose whether to express interest.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" aria-hidden="true">🤝</div>
        <h3>03 · Meet through the platform</h3>
        <p>An administrator coordinates the demo class and shares contact details only at the authorized stage.</p>
      </div>
    </div>
  </section>

  <!-- Coverage + Subjects (Linked to Local SEO Landing Pages) -->
  <section class="features-section" style="padding-top:0;">
    <div class="split" style="gap:28px;align-items:start;">
      <div class="card">
        <div class="card-header">
          <div>
            <p class="eyebrow">COVERAGE SECTORS</p>
            <h3 class="card-title">Home Tuition Areas in {{ $cityName }}</h3>
          </div>
        </div>
        <p style="color:var(--muted);margin-bottom:16px;">Select a sector to browse approved tutors covering that neighborhood. Exact house addresses are never published.</p>
        @if(count($areas) > 0)
          <div class="tag-row">
            @foreach($areas as $a)
              <a class="tag" href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($a->name)) }}">
                <span aria-hidden="true">📍</span> {{ $a->name }}
              </a>
            @endforeach
          </div>
        @else
          <p class="muted">{{ $cityName }} service areas are being configured.</p>
        @endif
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <p class="eyebrow">ACADEMIC SUBJECTS</p>
            <h3 class="card-title">Browse Tutors by Subject</h3>
          </div>
        </div>
        <p style="color:var(--muted);margin-bottom:16px;">Primary, Matric, FSc, ICS, I.Com, and O/A Level subject specialists.</p>
        @if(count($subjects) > 0)
          <div class="tag-row">
            @foreach($subjects as $s)
              <a class="tag" href="{{ route('tutors.landing', \Illuminate\Support\Str::slug($s->name)) }}">
                <span aria-hidden="true">📖</span> {{ $s->name }}
              </a>
            @endforeach
          </div>
        @else
          <p class="muted">Subjects will appear here once configured.</p>
        @endif
      </div>
    </div>
  </section>

  <!-- FAQ & Local Contact Section -->
  <section class="features-section" style="padding-top:0;">
    <p class="eyebrow" style="text-align:center;margin-bottom:8px;">QUESTIONS &amp; SUPPORT</p>
    <h2>Frequently Asked Questions</h2>
    <div class="faq-grid">
      <article class="faq-card">
        <h3>Is requesting a tutor free for parents?</h3>
        <p>Yes. Registering as a parent, submitting your tuition requirement, and coordinating a trial demo class is free for families.</p>
      </article>
      <article class="faq-card">
        <h3>How are tutor profiles checked?</h3>
        <p>Tutors register with their 13-digit CNIC and upload degree/transcript copies into our private document vault for admin review before approval.</p>
      </article>
      <article class="faq-card">
        <h3>Which classes &amp; fee ranges are supported?</h3>
        <p>We cover Class 1–8, Matric, FSc, ICS, I.Com, and Cambridge O/A Levels. Typical monthly home tuition fees in {{ $cityName }} range from PKR 8,000 to PKR 25,000 depending on grade and subjects.</p>
      </article>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <h2>Ready to find the right tutor in {{ $cityName }}?</h2>
    <p>Submit your child's subject and sector requirements in under two minutes.</p>
    <div class="hero-actions" style="justify-content:center;">
      <a class="btn" href="{{ route('auth.register_parent') }}" style="background:#fff;color:#4f46e5;font-weight:700;">Get Started Free</a>
      <a class="btn" href="{{ route('auth.register_tutor') }}" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3);">Join as Tutor</a>
    </div>
  </section>

  <footer class="home-footer">
    <span>© {{ date('Y') }} {{ $brandName }} · {{ $cityName }}, Punjab, Pakistan</span>
    @if(!empty($contactPhone))
      <span class="footer-sep"> &nbsp;·&nbsp; </span>
      <span>Support: {{ $contactPhone }}</span>
    @endif
    <span class="footer-sep"> &nbsp;·&nbsp; </span>
    <a href="{{ route('explore.tutors') }}">Explore Tutors</a>
    <span class="footer-sep"> &nbsp;·&nbsp; </span>
    <a href="{{ route('contract') }}">Safeguarding Charter</a>
    <span class="footer-sep"> &nbsp;·&nbsp; </span>
    <a href="{{ route('privacy') }}">Privacy Policy</a>
  </footer>
</div>
@endsection
