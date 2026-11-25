<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Home Tutor in Burewala – Verified Tutors | Maria Tutor')</title>
  <meta name="description" content="@yield('meta_description', 'Find verified male and female home and online tutors in Burewala, Punjab. Maria Tutor matches families with CNIC and degree-checked teachers.')">
  <meta name="robots" content="@yield('robots', Auth::check() ? 'noindex,nofollow' : 'index,follow')">
  <link rel="canonical" href="@yield('canonical', url()->current())">

  <!-- Open Graph / Social Sharing -->
  <meta property="og:site_name" content="Maria Tutor">
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title', 'Home Tutor in Burewala – Verified Tutors | Maria Tutor')))">
  <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', 'Find verified male and female home and online tutors in Burewala, Punjab.')))">
  <meta property="og:url" content="@yield('canonical', url()->current())">
  <meta property="og:image" content="@yield('og_image', asset('images/og-preview.png'))">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="@yield('og_title', trim($__env->yieldContent('title', 'Home Tutor in Burewala – Verified Tutors | Maria Tutor')))">
  <meta name="twitter:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', 'Find verified male and female home and online tutors in Burewala, Punjab.')))">
  <meta name="twitter:image" content="@yield('og_image', asset('images/og-preview.png'))">

  <!-- Icons -->
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192.png') }}">
  <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512.png') }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

  <!-- Fonts & Styles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ @filemtime(public_path('css/style.css')) ?: 1 }}">
  @stack('structured_data')
</head>
<body>

@if(Auth::check() && !request()->routeIs('home'))
<!-- ══ AUTHENTICATED LAYOUT — Sidebar + Main ══ -->
<div class="layout">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar" aria-label="Portal navigation">
    <div class="sidebar-brand">
      <a class="brand-logo" href="{{ route('home') }}">
        <div class="icon" aria-hidden="true">🎓</div>
        <div>
          <div>Maria Tutor</div>
          <small>
            @if(Auth::user()->isParent()) Parent Portal
            @elseif(Auth::user()->isTutor()) Tutor Portal
            @elseif(Auth::user()->isSuperAdmin()) Super Admin
            @elseif(Auth::user()->isAdmin()) Administration
            @else Portal @endif
          </small>
        </div>
      </a>
      <button type="button" class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">✕</button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Main</div>
      @if(Auth::user()->isSuperAdmin())
        <a href="{{ route('superadmin.dashboard') }}" class="{{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isTutor())
        <a href="{{ route('tutor.dashboard') }}" class="{{ request()->routeIs('tutor.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isParent())
        <a href="{{ route('parent.dashboard') }}" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🏠</span> Dashboard
        </a>
      @endif

      <!-- Tutor Links -->
      @if(Auth::user()->isTutor())
        <div class="nav-section-title">Tutor Operations</div>
        <a href="{{ route('tutor.dashboard') }}" class="{{ request()->routeIs('tutor.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">💼</span> Tuition Leads
        </a>
        <a href="{{ route('tutor.profile') }}" class="{{ request()->routeIs('tutor.profile') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">👤</span> My Profile &amp; Subjects
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📜</span> Code of Conduct &amp; Contract
        </a>
      @endif

      <!-- Parent Links -->
      @if(Auth::user()->isParent())
        <div class="nav-section-title">Parent Operations</div>
        <a href="{{ route('parent.dashboard') }}" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📋</span> My Tuition Requests
        </a>
        <a href="{{ route('parent.request_new') }}" class="{{ request()->routeIs('parent.request_new') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">➕</span> Request a New Tutor
        </a>
        <a href="{{ route('explore.tutors') }}" class="{{ request()->routeIs('explore.tutors', 'tutors.landing') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🔍</span> Explore Areas &amp; Tutors
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📜</span> Tuition Agreement &amp; Rules
        </a>
      @endif

      <!-- Admin Links -->
      @if(Auth::user()->isAdmin())
        <div class="nav-section-title">Operations</div>
        <a href="{{ route('admin.requests') }}" class="{{ request()->routeIs('admin.requests*', 'admin.request_*') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📋</span> Requests Queue
        </a>
        <a href="{{ route('admin.tutors') }}" class="{{ request()->routeIs('admin.tutors*', 'admin.tutor_*') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🎓</span> Tutors Verification
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📜</span> Safeguarding &amp; Contract
        </a>

        @if(Auth::user()->isSuperAdmin())
          <div class="nav-section-title">Super Admin</div>
          <a href="{{ route('superadmin.catalog') }}" class="{{ request()->routeIs('superadmin.catalog') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true">⚙️</span> Areas &amp; Settings
          </a>
          <a href="{{ route('superadmin.commission') }}" class="{{ request()->routeIs('superadmin.commission') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true">💰</span> Commission Rules
          </a>
        @endif
      @endif

      <div class="nav-section-title">Account</div>
      <form method="POST" action="{{ route('logout') }}" style="margin: 4px 0 0 0;">
        @csrf
        <button type="submit" class="sidebar-logout-btn">
          <span class="nav-icon" aria-hidden="true">🚪</span> Sign Out
        </button>
      </form>
    </nav>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="main-wrap">
    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <span class="topbar-title">@yield('page_title', 'Maria Tutor')</span>
      </div>
      <div class="user-pill">
        <span aria-hidden="true">👤</span>
        <span>{{ Auth::user()->name ?: Auth::user()->email }} ({{ str_replace('_', ' ', ucfirst(Auth::user()->role)) }})</span>
      </div>
    </header>

    <!-- Page Body -->
    <main class="page-body">
      @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
      @endif
      @if(session('warning'))
        <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error" role="alert">
          <strong>Please fix the following issues:</strong>
          <ul>
            @foreach($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @yield('content')
    </main>

    <footer class="app-footer">
      <span>© {{ date('Y') }} Maria Tutor · Burewala, Punjab · <a href="{{ route('privacy') }}">Privacy Policy</a></span>
    </footer>
  </div>
</div>

@else
<!-- ══ PUBLIC / GUEST LAYOUT ══ -->
<div class="public-wrapper">
  @if(!request()->routeIs('login', 'auth.*'))
    @if(session('success'))
      <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
      </div>
    @endif
    @if(session('error'))
      <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
        <div class="alert alert-error" role="alert">{{ session('error') }}</div>
      </div>
    @endif
    @if(session('warning'))
      <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
        <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
      </div>
    @endif
  @endif

  @hasSection('public_content')
    @yield('public_content')
  @else
    @yield('content')
  @endif
</div>
@endif

<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) ?: 1 }}" defer></script>
@stack('scripts')
</body>
</html>
