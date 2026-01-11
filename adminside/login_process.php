<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $generatedID = trim($_POST['generatedID']);
    $password = $_POST['password'];
    
    // Prepare statement to prevent SQL injection
    $sql = "SELECT UserID, GeneratedID, Password, Role, FirstName, LastName FROM users WHERE GeneratedID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $generatedID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (direct comparison for plain text passwords)
        if ($password == $user['Password']) {
            // Login successful - Set session variables FIRST
            $_SESSION['userID'] = $user['UserID'];
            $_SESSION['generatedID'] = $user['GeneratedID'];
            $_SESSION['role'] = $user['Role'];
            $_SESSION['firstName'] = $user['FirstName'];
            $_SESSION['lastName'] = $user['LastName'];
            
            // Handle "Remember Me" functionality
            if (isset($_POST['remember'])) {
                setcookie('generatedID', $generatedID, time() + (86400 * 30), "/");
            }
            
            // Redirect based on role
            if ($user['Role'] == 'Resident') {
                header("Location: ../login/resident.php");
                exit();
            } elseif ($user['Role'] == 'Admin') {
                header("Location: admin.php");
                exit();
            } else {
                // Default redirect for other roles
                header("Location: admin.php");
                exit();
            }
            
        } else {
            // Password doesn't match
            header("Location: login.html?error=invalid");
            exit();
        }
    } else {
        // User not found
        header("Location: login.html?error=invalid");
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>