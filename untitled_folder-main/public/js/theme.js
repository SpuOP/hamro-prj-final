(function(){
  var checkbox = document.getElementById('themeToggleCheckbox');
  function setTheme(isDark){
    document.documentElement.classList.toggle('dark', !!isDark);
    try{ localStorage.setItem('theme', isDark ? 'dark' : 'light'); }catch(e){}
    if(checkbox){ checkbox.setAttribute('aria-pressed', String(!!isDark)); checkbox.checked = !!isDark; }
  }
  var stored = null; try{ stored = localStorage.getItem('theme'); }catch(e){}
  var isDark = (stored ? stored === 'dark' : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches));
  if(isDark){ document.documentElement.classList.add('dark'); }
  if(checkbox){ setTheme(isDark); checkbox.addEventListener('change', function(){ setTheme(checkbox.checked); }); }

  var burger = document.getElementById('sidebarToggle');
  var sidebar = document.querySelector('aside.sidebar');
  if(burger && sidebar){ burger.addEventListener('click', function(){ var open=sidebar.classList.toggle('open'); burger.setAttribute('aria-expanded', String(open)); }); }

  // Active link highlight fallback by path
  try{
    var links = document.querySelectorAll('aside.sidebar a.nav-card');
    var here = location.pathname;
    links.forEach(function(a){ if(here.indexOf(a.getAttribute('href')) !== -1){ a.classList.add('active-link'); } });
  }catch(e){}
})();


