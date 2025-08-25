<?php
/**
 * Setup Script for CivicPulse Verification System
 * Run this script once to set up the new verification database tables
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
            <title>Setup CivicPulse Verification System</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-5'>
    <div class='row justify-content-center'>
        <div class='col-md-8'>
            <div class='card'>
                <div class='card-header bg-primary text-white'>
                    <h3 class='mb-0'>🔧 CivicPulse Setup</h3>
                </div>
                <div class='card-body'>";

try {
    $pdo = getDBConnection();
    
    echo "<h5>Setting up verification system...</h5>";
    
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/database/verification_system.sql');
    
    // Split into individual queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            try {
                $pdo->exec($query);
                $successCount++;
                echo "<div class='alert alert-success'><i class='fas fa-check'></i> Query executed successfully</div>";
            } catch (PDOException $e) {
                $errorCount++;
                echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Query skipped (may already exist): " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    echo "<hr>";
    echo "<div class='alert alert-info'>";
    echo "<h5>📊 Setup Summary:</h5>";
    echo "<ul>";
    echo "<li><strong>Successful queries:</strong> $successCount</li>";
    echo "<li><strong>Skipped queries:</strong> $errorCount</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Setup Complete!</h5>";
            echo "<p><strong>Your CivicPulse verification system is now ready!</strong></p>";
    echo "<h6>What's been set up:</h6>";
    echo "<ul>";
    echo "<li>🏘️ Communities database with sample Nepali cities</li>";
    echo "<li>📝 User applications system with document upload</li>";
    echo "<li>👨‍💼 Admin panel for reviewing applications</li>";
    echo "<li>🆔 Special ID generation system</li>";
    echo "<li>📧 Email notification system</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='alert alert-warning'>";
    echo "<h6>⚠️ Important Next Steps:</h6>";
    echo "<ol>";
    echo "<li><strong>Admin Access:</strong> Default admin login - Username: <code>admin</code>, Password: <code>admin123</code></li>";
    echo "<li><strong>Change Admin Password:</strong> Please change the default admin password immediately</li>";
    echo "<li><strong>Email Setup:</strong> Configure proper email settings in includes/email_functions.php</li>";
    echo "<li><strong>File Permissions:</strong> Ensure uploads/proof_documents/ directory is writable</li>";
    echo "<li><strong>Security:</strong> Remove or protect this setup file after use</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='d-grid gap-2 mt-4'>";
    echo "<a href='admin/index.html' class='btn btn-primary btn-lg'>";
    echo "<i class='fas fa-user-shield me-2'></i>Access Admin Panel";
    echo "</a>";
    echo "<a href='index.html' class='btn btn-success btn-lg'>";
    echo "<i class='fas fa-home me-2'></i>Go to Main Site";
    echo "</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Setup Failed</h5>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}

echo "</div>
            </div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js'></script>
</body>
</html>";
?>
