<!-- Responsive Home / Public Navigation Bar -->
<nav class="home-nav" id="homeNav">
  <div class="home-nav-inner">
    <!-- Brand -->
    <a class="brand" href="{{ route('home') }}">
      <span class="brand-icon">🎓</span>
      <span class="brand-name">Maria Tutor</span>
    </a>

    <!-- Hamburger Toggle for Mobile -->
    <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation menu" aria-expanded="false">
      <span class="nav-toggle-icon" id="navToggleIcon">☰</span>
    </button>

    <!-- Nav Menu (Desktop row, Mobile collapsible dropdown) -->
    <div class="home-nav-menu" id="homeNavMenu">
      <div class="home-nav-links">
        <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
          <span class="nav-icon">🏠</span> Home
        </a>
        <a href="{{ route('explore.tutors') }}" class="nav-link {{ request()->routeIs('explore.tutors') ? 'active' : '' }}">
          <span class="nav-icon">🔍</span> Explore Tutors
        </a>
        <a href="{{ route('contract') }}" class="nav-link {{ request()->routeIs('contract') ? 'active' : '' }}">
          <span class="nav-icon">📜</span> Code of Conduct
        </a>

        @if(Auth::check())
          @if(Auth::user()->isSuperAdmin())
            <a href="{{ route('superadmin.dashboard') }}" target="_blank" class="nav-link nav-link-admin font-bold">
              <span class="nav-icon">🏠</span> Admin Dashboard
            </a>
          @elseif(Auth::user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" target="_blank" class="nav-link nav-link-admin font-bold">
              <span class="nav-icon">🏠</span> Admin Dashboard
            </a>
          @elseif(Auth::user()->isTutor())
            <a href="{{ route('tutor.dashboard') }}" target="_blank" class="nav-link nav-link-tutor font-bold">
              <span class="nav-icon">🎓</span> Tutor Dashboard
            </a>
          @elseif(Auth::user()->isParent())
            <a href="{{ route('parent.dashboard') }}" target="_blank" class="nav-link nav-link-parent font-bold">
              <span class="nav-icon">👨‍👩‍👧</span> Parent Dashboard
            </a>
          @endif
        @else
          <a href="{{ route('auth.parent_login') }}" target="_blank" class="nav-link nav-link-parent">
            <span class="nav-icon">👨‍👩‍👧</span> Parent Portal
          </a>
          <a href="{{ route('auth.tutor_login') }}" target="_blank" class="nav-link nav-link-tutor">
            <span class="nav-icon">🎓</span> Tutor Portal
          </a>
        @endif
      </div>

      <!-- Action Buttons -->
      <div class="home-nav-actions">
        @if(Auth::check())
          <span class="nav-user-pill">
            👤 {{ Auth::user()->name ?: Auth::user()->email }}
          </span>
          <form method="POST" action="{{ route('logout') }}" style="display:inline; margin:0;">
            @csrf
            <button type="submit" class="btn btn-sm btn-logout">
              🚪 Log Out
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('homeNavMenu');
  var icon = document.getElementById('navToggleIcon');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (icon) icon.textContent = isOpen ? '✕' : '☰';
    });
  }
});
</script>
