<?php
/**
 * Installation Checker
 * Run this to verify your setup is correct
 */

echo "<h1>🔍 Community Voting Platform - Installation Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    .check-item { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
    .check-item.success { border-left-color: #28a745; background: #f8fff9; }
    .check-item.error { border-left-color: #dc3545; background: #fff8f8; }
    .check-item.warning { border-left-color: #ffc107; background: #fffef8; }
    .check-item.info { border-left-color: #17a2b8; background: #f8fdff; }
</style>";

$allGood = true;

// Check PHP version
echo "<div class='check-item " . (version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'success' : 'error') . "'>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION;
if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
    echo " ✅ (Compatible)";
} else {
    echo " ❌ (Requires PHP 7.4+)";
    $allGood = false;
}
echo "</div>";

// Check required PHP extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<div class='check-item " . ($loaded ? 'success' : 'error') . "'>";
    echo "<strong>PHP Extension - $ext:</strong> ";
    if ($loaded) {
        echo "✅ Loaded";
    } else {
        echo "❌ Missing";
        $allGood = false;
    }
    echo "</div>";
}

// Check if config file exists
$config_exists = file_exists('config/database.php');
echo "<div class='check-item " . ($config_exists ? 'success' : 'error') . "'>";
echo "<strong>Database Config:</strong> ";
if ($config_exists) {
    echo "✅ Found";
} else {
    echo "❌ Missing config/database.php";
    $allGood = false;
}
echo "</div>";

// Check if uploads directory exists and is writable
$uploads_dir = 'uploads/';
$uploads_exists = is_dir($uploads_dir);
$uploads_writable = is_writable($uploads_dir);

echo "<div class='check-item " . ($uploads_exists && $uploads_writable ? 'success' : 'warning') . "'>";
echo "<strong>Uploads Directory:</strong> ";
if ($uploads_exists && $uploads_writable) {
    echo "✅ Exists and writable";
} elseif ($uploads_exists && !$uploads_writable) {
    echo "⚠️ Exists but not writable";
    $allGood = false;
} else {
    echo "⚠️ Missing - will be created automatically";
}
echo "</div>";

// Check if we can connect to database
if ($config_exists) {
    try {
        require_once 'config/database.php';
        $pdo = getDBConnection();
        echo "<div class='check-item success'>";
        echo "<strong>Database Connection:</strong> ✅ Connected successfully";
        echo "</div>";
        
        // Check if tables exist
        $tables = ['users', 'issues', 'votes', 'comments'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            echo "<div class='check-item " . ($exists ? 'success' : 'warning') . "'>";
            echo "<strong>Table '$table':</strong> ";
            if ($exists) {
                echo "✅ Exists";
            } else {
                echo "⚠️ Missing (will be created by setup script)";
            }
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='check-item error'>";
        echo "<strong>Database Connection:</strong> ❌ Failed: " . $e->getMessage();
        echo "</div>";
        $allGood = false;
    }
}

// Check file permissions
$files_to_check = [
    'index.html' => 'Homepage',
    'auth/login.php' => 'Login Page',
    'auth/register.php' => 'Register Page',
    'issues/create.php' => 'Create Issue Page',
    'assets/css/style.css' => 'CSS Styles',
    'assets/js/voting.js' => 'JavaScript'
];

foreach ($files_to_check as $file => $description) {
    $exists = file_exists($file);
    echo "<div class='check-item " . ($exists ? 'success' : 'error') . "'>";
    echo "<strong>$description:</strong> ";
    if ($exists) {
        echo "✅ Found";
    } else {
        echo "❌ Missing $file";
        $allGood = false;
    }
    echo "</div>";
}

// Final result
echo "<hr>";
if ($allGood) {
    echo "<div class='check-item success'>";
    echo "<h2>🎉 All Checks Passed!</h2>";
    echo "<p>Your Community Voting Platform is ready to use!</p>";
    echo "<p><a href='database/setup.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Run Setup Script</a></p>";
    echo "</div>";
} else {
    echo "<div class='check-item error'>";
    echo "<h2>⚠️ Some Issues Found</h2>";
    echo "<p>Please fix the issues above before proceeding.</p>";
    echo "</div>";
}

echo "<div class='check-item info'>";
echo "<h3>📋 Next Steps:</h3>";
echo "<ol>";
echo "<li>Make sure XAMPP is running (Apache + MySQL)</li>";
echo "<li>If all checks pass, click 'Run Setup Script' above</li>";
echo "<li>After setup, visit the homepage to start using the platform</li>";
echo "</ol>";
echo "</div>";
?>
