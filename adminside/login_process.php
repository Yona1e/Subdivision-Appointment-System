<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT user_id, Email, Password, Role, FirstName, LastName 
            FROM users 
            WHERE Email = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user found
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Check password
        if (password_verify($password, $user['Password'])) {

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['Email'];
            $_SESSION['role'] = $user['Role'];
            $_SESSION['firstName'] = $user['FirstName'];
            $_SESSION['lastName'] = $user['LastName'];

            // Remember Me
            if (isset($_POST['remember'])) {
                setcookie('email', $email, time() + (86400 * 30), "/");
            }

            // Redirect based on role
            if ($user['Role'] === 'Resident') {
                header("Location: ../resident-side/make-reservation.php");
                exit();
            } else if ($user['Role'] === 'Admin') {
                header("Location: overview.php");
                exit();
            } else {
                header("Location: admin.php");
                exit();
            }

        } else {
            // Incorrect password → redirect back
            header("Location: ../login/login.php?error=invalid");
            exit();
        }

    } else {
        // Email not found → redirect back
        header("Location: ../login/login.php?error=invalid");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
