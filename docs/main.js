/* global F1 */

function log() { if (F1.DEBUG) { console.log(...arguments); } }

F1.deferred.push(function initHomePage(app) {

  log('Initializing Home Page. app =', app);

  app.storedTheme = localStorage.getItem('theme');
  app.hamburger = document.getElementById('hamburger');
  app.sidebar = document.getElementById('sidebar');

  app.toggleTheme = function() {
    const currentTheme = document.body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
  };

  app.toggleSidebar = () => app.sidebar.classList.toggle('open');

  app.updateSidebarMode = () => {
    log('Updating sidebar mode');
    if (window.innerWidth <= 900) {
      log('Sidebar mode: mobile');
      app.sidebar.classList.remove('open');
      app.sidebar.classList.add('off-screen-left');
      app.hamburger.style.display = 'inline-block';
    } else {
      log('Sidebar mode: desktop');
      app.sidebar.classList.add('open');
      app.sidebar.classList.remove('off-screen-left');
      app.hamburger.style.display = 'none';
    }
  };

  if (app.storedTheme) {
    document.body.setAttribute('data-theme', app.storedTheme);
  } else {
    const userPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.body.setAttribute('data-theme', userPrefersDark ? 'dark' : 'light');
  }

  window.addEventListener('resize', app.updateSidebarMode);

  app.updateSidebarMode();

});

window.addEventListener('load', function() { F1.deferred.forEach(fn => fn(F1.app)); });