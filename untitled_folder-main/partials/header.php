<?php if (!headers_sent()) { header('X-Content-Type-Options: nosniff'); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CivicPulse - Community Voice Platform</title>
  <link rel="stylesheet" href="/untitled_folder/assets/css/style.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script>
    // Theme initialization
    (function(){
      try {
        var theme = localStorage.getItem('theme');
        if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          theme = 'dark';
        }
        if (theme === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch(e) {}
    })();
  </script>
</head>
<body>
  <div class="app-container">
    <!-- Header -->
    <header class="site-header">
      <div class="header-left">
        <button id="sidebarToggle" class="burger-menu" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="false">
          <span></span>
          <span></span>
          <span></span>
        </button>
        <h1 class="site-title">
          <i class="fas fa-vote-yea"></i>
          CivicPulse
        </h1>
      </div>
      
      <div class="site-actions">
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
          <input type="checkbox" id="themeToggleCheckbox" aria-hidden="true">
          <span class="theme-slider"></span>
        </button>
      </div>
    </header>

    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>


