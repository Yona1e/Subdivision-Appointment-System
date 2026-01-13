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

    // ✅ FIXED column name: user_id
    $sql = "SELECT user_id, GeneratedID, Password, Role, FirstName, LastName 
            FROM users 
            WHERE GeneratedID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $generatedID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Password check (plain text — consider hashing later)
        if ($password === $user['Password']) {

            // ✅ FIXED session variable names
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['generatedID'] = $user['GeneratedID'];
            $_SESSION['role'] = $user['Role'];
            $_SESSION['firstName'] = $user['FirstName'];
            $_SESSION['lastName'] = $user['LastName'];

            // Remember Me
            if (isset($_POST['remember'])) {
                setcookie('generatedID', $generatedID, time() + (86400 * 30), "/");
            }

            // Redirect by role
            if ($user['Role'] === 'Resident') {
                header("Location: ../resident-side/make-reservation.php");
                exit();
            } elseif ($user['Role'] === 'Admin') {
                header("Location: overview.php");
                exit();
            } else {
                header("Location: admin.php");
                exit();
            }

        } else {
            header("Location: login.html?error=invalid");
            exit();
        }
    } else {
        header("Location: login.html?error=invalid");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
