<?php
/**
 * test_connection.php
 * Test your database connection
 */

// Database connection for XAMPP
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

echo "<h2>Database Connection Test</h2>";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Check if reservations table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'reservations'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ 'reservations' table exists!</p>";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE reservations");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count existing records
        $stmt = $conn->query("SELECT COUNT(*) as count FROM reservations");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>📊 Total reservations: <strong>$count</strong></p>";
        
    } else {
        echo "<p style='color: red;'>❌ 'reservations' table does NOT exist!</p>";
        echo "<p>You need to create it using the SQL provided.</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    
    if ($e->getCode() == 1049) {
        echo "<p style='color: orange;'>⚠️ Database 'subdivision_appointment' does not exist!</p>";
        echo "<p>Create it in phpMyAdmin first.</p>";
    }
}
?>