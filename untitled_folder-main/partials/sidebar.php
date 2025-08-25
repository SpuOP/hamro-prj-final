<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
$isLogged = isLoggedIn();
$isAdmin = !empty($_SESSION['is_admin']);
$path = $_SERVER['PHP_SELF'] ?? '';

function navItem($href, $label, $icon, $current) {
  $active = (strpos($current, $href) !== false) ? ' active' : '';
  echo '<a class="nav-item' . $active . '" href="' . htmlspecialchars($href) . '">'
      . '<span class="nav-icon">' . $icon . '</span>'
      . '<span class="nav-label">' . htmlspecialchars($label) . '</span>'
      . '</a>';
}
?>

<aside class="sidebar" id="sidebar" aria-label="Primary Navigation">
  <nav class="sidebar-nav">
    <?php navItem('/untitled_folder/index.html', 'Home', '<i class="fas fa-home"></i>', $path); ?>
    
    <?php if ($isLogged): ?>
      <?php navItem('/untitled_folder/issues/create.php', 'Post Issue', '<i class="fas fa-plus-circle"></i>', $path); ?>
      <?php navItem('/untitled_folder/contact.php', 'Contact', '<i class="fas fa-envelope"></i>', $path); ?>
      <?php navItem('/untitled_folder/about.php', 'About Us', '<i class="fas fa-info-circle"></i>', $path); ?>
      
      <?php if ($isAdmin): ?>
        <div class="nav-divider"></div>
        <div class="nav-section-title">Admin</div>
        <?php navItem('/untitled_folder/admin/dashboard.php', 'Dashboard', '<i class="fas fa-tachometer-alt"></i>', $path); ?>
        <?php navItem('/untitled_folder/admin/users.php', 'User Management', '<i class="fas fa-users"></i>', $path); ?>
        <?php navItem('/untitled_folder/admin/moderation.php', 'Moderation', '<i class="fas fa-shield-alt"></i>', $path); ?>
        <?php navItem('/untitled_folder/admin/settings.php', 'Settings', '<i class="fas fa-cog"></i>', $path); ?>
      <?php endif; ?>
      
      <div class="nav-divider"></div>
      <?php navItem('/untitled_folder/auth/logout.php', 'Logout', '<i class="fas fa-sign-out-alt"></i>', $path); ?>
    <?php else: ?>
      <?php navItem('/untitled_folder/auth/register.php', 'Register', '<i class="fas fa-user-plus"></i>', $path); ?>
      <?php navItem('/untitled_folder/auth/login.php', 'Login', '<i class="fas fa-sign-in-alt"></i>', $path); ?>
      <?php navItem('/untitled_folder/contact.php', 'Contact', '<i class="fas fa-envelope"></i>', $path); ?>
      <?php navItem('/untitled_folder/about.php', 'About Us', '<i class="fas fa-info-circle"></i>', $path); ?>
    <?php endif; ?>
  </nav>
</aside>


