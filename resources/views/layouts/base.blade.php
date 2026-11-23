<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Maria Tutor')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?v=20261123_laravel_1">
</head>
<body>

@if(Auth::check() && !request()->routeIs('home'))
<!-- ══ AUTHENTICATED LAYOUT — Sidebar + Main ══ -->
<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <a class="brand-logo" href="{{ route('home') }}" style="text-decoration:none;">
        <div class="icon">🎓</div>
        <div>
          <div>Maria Tutor</div>
          <small>Administration</small>
        </div>
      </a>
      <button type="button" class="sidebar-close-btn" id="sidebarClose" aria-label="Close sidebar">✕</button>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Main</div>
      @if(Auth::user()->isSuperAdmin())
        <a href="{{ route('superadmin.dashboard') }}" class="{{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isAdmin())
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isTutor())
        <a href="{{ route('tutor.dashboard') }}" class="{{ request()->routeIs('tutor.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">🏠</span> Dashboard
        </a>
      @elseif(Auth::user()->isParent())
        <a href="{{ route('parent.dashboard') }}" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">🏠</span> Dashboard
        </a>
      @endif

      <!-- Tutor Links -->
      @if(Auth::user()->isTutor())
        <div class="nav-section-title">Tutor Operations</div>
        <a href="{{ route('tutor.dashboard') }}" class="{{ request()->routeIs('tutor.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">💼</span> Tuition Leads
        </a>
        <a href="{{ route('tutor.profile') }}" class="{{ request()->routeIs('tutor.profile') ? 'active' : '' }}">
          <span class="nav-icon">👤</span> My Profile &amp; Subjects
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon">📜</span> Code of Conduct &amp; Contract
        </a>
      @endif

      <!-- Parent Links -->
      @if(Auth::user()->isParent())
        <div class="nav-section-title">Parent Operations</div>
        <a href="{{ route('parent.dashboard') }}" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">📋</span> My Tuition Requests
        </a>
        <a href="{{ route('parent.request_new') }}" class="{{ request()->routeIs('parent.request_new') ? 'active' : '' }}">
          <span class="nav-icon">➕</span> Request a New Tutor
        </a>
        <a href="{{ route('explore.tutors') }}" class="{{ request()->routeIs('explore.tutors') ? 'active' : '' }}">
          <span class="nav-icon">🔍</span> Explore Areas &amp; Tutors
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon">📜</span> Tuition Agreement &amp; Rules
        </a>
      @endif

      <!-- Admin Links -->
      @if(Auth::user()->isAdmin())
        <div class="nav-section-title">Operations</div>
        <a href="{{ route('admin.requests') }}" class="{{ request()->routeIs('admin.requests*') ? 'active' : '' }}">
          <span class="nav-icon">📋</span> Requests Queue
        </a>
        <a href="{{ route('admin.tutors') }}" class="{{ request()->routeIs('admin.tutors*') ? 'active' : '' }}">
          <span class="nav-icon">🎓</span> Tutors Verification
        </a>
        <a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon">📜</span> Safeguarding &amp; Contract
        </a>

        @if(Auth::user()->isSuperAdmin())
          <div class="nav-section-title">Super Admin</div>
          <a href="{{ route('superadmin.catalog') }}" class="{{ request()->routeIs('superadmin.catalog') ? 'active' : '' }}">
            <span class="nav-icon">⚙️</span> Areas &amp; Settings
          </a>
          <a href="{{ route('superadmin.commission') }}" class="{{ request()->routeIs('superadmin.commission') ? 'active' : '' }}">
            <span class="nav-icon">💰</span> Commission Rules
          </a>
        @endif
      @endif

      <div class="nav-section-title">Account</div>
      <form method="POST" action="{{ route('logout') }}" style="margin: 4px 0 0 0;">
        @csrf
        <button type="submit" class="sidebar-logout-btn" style="width:100%; text-align:left; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:8px 12px; display:flex; align-items:center; gap:8px;">
          <span class="nav-icon">🚪</span> Sign Out
        </button>
      </form>
    </nav>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation menu">☰</button>
        <span class="topbar-title">@yield('page_title', 'Maria Tutor')</span>
      </div>
      <div class="user-pill">
        <span>👤 {{ Auth::user()->name ?: Auth::user()->email }} ({{ ucfirst(Auth::user()->role) }})</span>
      </div>
    </header>

    <!-- Page Body -->
    <main class="page-body">
      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
      @endif
      @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
      @endif

      @yield('content')
    </main>

    <footer class="app-footer">
      <span>© 2026 Maria Tutor · All Rights Reserved</span>
    </footer>
  </div>
</div>

@else
<!-- ══ PUBLIC / GUEST LAYOUT ══ -->
<div class="public-wrapper">
  @if(session('success'))
    <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
      <div class="alert alert-success">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
      <div class="alert alert-error">{{ session('error') }}</div>
    </div>
  @endif
  @if(session('warning'))
    <div style="max-width: 1100px; margin: 16px auto 0; padding: 0 16px;">
      <div class="alert alert-warning">{{ session('warning') }}</div>
    </div>
  @endif

  @yield('public_content')

  @yield('content')
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('sidebar');
  var toggle = document.getElementById('sidebarToggle');
  var close = document.getElementById('sidebarClose');

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
  }
  if (close && sidebar) {
    close.addEventListener('click', function () {
      sidebar.classList.remove('open');
    });
  }
});
</script>
</body>
</html>
