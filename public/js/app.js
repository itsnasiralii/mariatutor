document.addEventListener('DOMContentLoaded', function () {
  // Authenticated Sidebar Toggle (Mobile Drawer + Accessibility)
  var sidebar = document.getElementById('sidebar');
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebarClose = document.getElementById('sidebarClose');
  var sidebarBackdrop = document.getElementById('sidebarBackdrop');

  function setSidebarOpen(isOpen) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', isOpen);
    if (sidebarBackdrop) {
      sidebarBackdrop.classList.toggle('active', isOpen);
    }
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  }

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      setSidebarOpen(!sidebar.classList.contains('open'));
    });
  }

  if (sidebarClose && sidebar) {
    sidebarClose.addEventListener('click', function () {
      setSidebarOpen(false);
    });
  }

  if (sidebarBackdrop && sidebar) {
    sidebarBackdrop.addEventListener('click', function () {
      setSidebarOpen(false);
    });
  }

  // Public Home Navigation Toggle
  var navToggle = document.getElementById('navToggle');
  var navMenu = document.getElementById('homeNavMenu');
  var navIcon = document.getElementById('navToggleIcon');

  function setNavOpen(isOpen) {
    if (!navMenu || !navToggle) return;
    navMenu.classList.toggle('open', isOpen);
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (navIcon) {
      navIcon.textContent = isOpen ? '✕' : '☰';
    }
  }

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
      setNavOpen(!navMenu.classList.contains('open'));
    });
  }

  // Close open drawers/menus on Escape key
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (sidebar && sidebar.classList.contains('open')) {
        setSidebarOpen(false);
        if (sidebarToggle) sidebarToggle.focus();
      }
      if (navMenu && navMenu.classList.contains('open')) {
        setNavOpen(false);
        if (navToggle) navToggle.focus();
      }
    }
  });
});
