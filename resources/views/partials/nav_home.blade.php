<!-- Responsive Home / Public Navigation Bar -->
<nav class="home-nav" id="homeNav" aria-label="Main navigation">
  <div class="home-nav-inner">
    <!-- Brand -->
    <a class="brand" href="{{ route('home') }}">
      <span class="brand-icon" aria-hidden="true">🎓</span>
      <span class="brand-name">Maria Tutor</span>
    </a>

    <!-- Hamburger Toggle for Mobile -->
    <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation menu" aria-controls="homeNavMenu" aria-expanded="false">
      <span class="nav-toggle-icon" id="navToggleIcon" aria-hidden="true">☰</span>
    </button>

    <!-- Nav Menu (Desktop row, Mobile collapsible dropdown) -->
    <div class="home-nav-menu" id="homeNavMenu">
      <div class="home-nav-links">
        <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🏠</span> Home
        </a>
        <a href="{{ route('explore.tutors') }}" class="nav-link {{ request()->routeIs('explore.tutors', 'tutors.landing') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">🔍</span> Explore Tutors
        </a>
        <a href="{{ route('contract') }}" class="nav-link {{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon" aria-hidden="true">📜</span> Code of Conduct
        </a>

        @if(Auth::check())
          @if(Auth::user()->isSuperAdmin())
            <a href="{{ route('superadmin.dashboard') }}" class="nav-link nav-link-admin font-bold">
              <span class="nav-icon" aria-hidden="true">🏠</span> Admin Dashboard
            </a>
          @elseif(Auth::user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="nav-link nav-link-admin font-bold">
              <span class="nav-icon" aria-hidden="true">🏠</span> Admin Dashboard
            </a>
          @elseif(Auth::user()->isTutor())
            <a href="{{ route('tutor.dashboard') }}" class="nav-link nav-link-tutor font-bold">
              <span class="nav-icon" aria-hidden="true">🎓</span> Tutor Dashboard
            </a>
          @elseif(Auth::user()->isParent())
            <a href="{{ route('parent.dashboard') }}" class="nav-link nav-link-parent font-bold">
              <span class="nav-icon" aria-hidden="true">👨‍👩‍👧</span> Parent Dashboard
            </a>
          @endif
        @else
          <a href="{{ route('auth.parent_login') }}" class="nav-link nav-link-parent">
            <span class="nav-icon" aria-hidden="true">👨‍👩‍👧</span> Parent Portal
          </a>
          <a href="{{ route('auth.tutor_login') }}" class="nav-link nav-link-tutor">
            <span class="nav-icon" aria-hidden="true">🎓</span> Tutor Portal
          </a>
        @endif
      </div>

      <!-- Action Buttons -->
      <div class="home-nav-actions">
        @if(Auth::check())
          <span class="nav-user-pill">
            <span aria-hidden="true">👤</span> {{ Auth::user()->name ?: Auth::user()->email }}
          </span>
          <form method="POST" action="{{ route('logout') }}" style="display:inline; margin:0;">
            @csrf
            <button type="submit" class="btn btn-sm btn-logout">
              <span aria-hidden="true">🚪</span> Log Out
            </button>
          </form>
        @else
          <a href="{{ route('auth.register_parent') }}" class="btn btn-sm btn-request-nav">
            Request a Tutor
          </a>
          <a href="{{ route('auth.register_tutor') }}" class="btn btn-sm btn-primary btn-join-nav">
            Join as Tutor
          </a>
        @endif
      </div>
    </div>
  </div>
</nav>
