<?php
/**
 * Database Setup Script
 * Run this once to set up your database
 */

require_once '../config/database.php';

echo "<h2>Community Voting Platform - Database Setup</h2>";

try {
    // Create database if it doesn't exist
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✅ Database '" . DB_NAME . "' created/verified successfully</p>";
    
    // Connect to the specific database
    $pdo = getDBConnection();
    
    // Initialize tables
    initDatabase();
    echo "<p>✅ Database tables created successfully</p>";
    
    // Check if we need to add sample data
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        // Add sample data
        echo "<p>📝 Adding sample data...</p>";
        
        // Sample users
        $sampleUsers = [
            ['admin', 'admin@community.com', 'admin123'],
            ['john_doe', 'john@example.com', 'password123'],
            ['jane_smith', 'jane@example.com', 'password123']
        ];
        
        foreach ($sampleUsers as $user) {
            $hashedPassword = password_hash($user[2], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$user[0], $user[1], $hashedPassword]);
        }
        echo "<p>✅ Sample users created</p>";
        
        // Sample issues
        $sampleIssues = [
            ['Pothole on Main Street', 'There is a large pothole on Main Street near the intersection with Oak Avenue. It\'s causing damage to vehicles and is a safety hazard.', 1],
            ['Street Light Out', 'The street light on the corner of Pine Street and Elm Road has been out for over a week. It\'s very dark and unsafe at night.', 2],
            ['Garbage Collection Issue', 'Garbage collection has been inconsistent on Maple Drive. Some weeks they skip our street entirely.', 3]
        ];
        
        foreach ($sampleIssues as $issue) {
            $stmt = $pdo->prepare("INSERT INTO issues (title, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$issue[0], $issue[1], $issue[2]]);
        }
        echo "<p>✅ Sample issues created</p>";
        
        // Sample votes
        $stmt = $pdo->prepare("INSERT INTO votes (issue_id, user_id, vote_type) VALUES (?, ?, ?)");
        $stmt->execute([1, 2, 'upvote']);
        $stmt->execute([1, 3, 'upvote']);
        $stmt->execute([2, 1, 'upvote']);
        $stmt->execute([3, 1, 'downvote']);
        
        // Update vote counts
        updateIssueVoteCount(1);
        updateIssueVoteCount(2);
        updateIssueVoteCount(3);
        
        echo "<p>✅ Sample votes created</p>";
        
        // Sample comments
        $sampleComments = [
            [1, 2, 'This pothole is getting worse every day. I saw a car bottom out on it yesterday.'],
            [1, 3, 'I agree, this needs immediate attention. It\'s right in the middle of the road.'],
            [2, 1, 'I\'ve reported this to the city multiple times. Hopefully this platform will get more attention.'],
            [3, 2, 'Same issue on our street. The collection schedule seems random lately.']
        ];
        
        foreach ($sampleComments as $comment) {
            $stmt = $pdo->prepare("INSERT INTO comments (issue_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$comment[0], $comment[1], $comment[2]]);
        }
        echo "<p>✅ Sample comments created</p>";
    }
    
    echo "<h3>🎉 Setup Complete!</h3>";
    echo "<p>Your Community Voting Platform is ready to use!</p>";
    echo "<p><strong>Sample Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>John:</strong> john_doe / password123</li>";
    echo "<li><strong>Jane:</strong> jane_smith / password123</li>";
    echo "</ul>";
    echo "<p><a href='../index.html'>Go to Homepage</a> | <a href='../auth/login.php'>Login</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/database.php</p>";
}
?>
