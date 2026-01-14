<?php
session_start();

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $birthday = $_POST['birthday'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $block = trim($_POST['block']);
    $lot = trim($_POST['lot']);
    $streetName = trim($_POST['street_name']);
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($role) || empty($block) || empty($lot) || empty($streetName)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: create-account.php");
        exit();
    }
    
    // Check if email already exists
    $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE Email = ?");
    $checkEmail->execute([$email]);
    
    if ($checkEmail->rowCount() > 0) {
        $_SESSION['error'] = "Email already exists!";
        header("Location: create-account.php");
        exit();
    }
    
    // Handle profile picture upload
    $profilePictureURL = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/profile_pictures/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = uniqid('profile_', true) . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                $profilePictureURL = $uploadPath;
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            header("Location: create-account.php");
            exit();
        }
    }
    
    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Determine role value (capitalize first letter to match ENUM)
    $roleValue = ucfirst(strtolower($role));
    
    try {
        // Insert user into database
        $sql = "INSERT INTO users (FirstName, LastName, Email, Password, Role, Block, Lot, StreetName, ProfilePictureURL, Status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $roleValue,
            $block,
            $lot,
            $streetName,
            $profilePictureURL
        ]);
        
        $_SESSION['success'] = "Account created successfully!";
        header("Location: create-account.php");
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error creating account: " . $e->getMessage();
        header("Location: create-account.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="create-user1.css">
    <title>Create Account</title>
</head>

<body>

<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <header class="sidebar-header">
            <img src="../../asset/logo.png" alt="Header Logo" class="header-logo">
            <button class="sidebar-toggle">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
        </header>

        <div class="sidebar-content">
            <ul class="menu-list">
                <li class="menu-item">
                    <a href="../overview.php" class="menu-link">
                        <img src="../../asset/home.png" class="menu-icon">
                        <span class="menu-label">Overview</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../reserverequests.php" class="menu-link">
                        <img src="../../asset/makeareservation.png" class="menu-icon">
                        <span class="menu-label">Requests</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../reservations.php" class="menu-link">
                        <img src="../../asset/reservations.png" class="menu-icon">
                        <span class="menu-label">Reservations</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <img src="../../asset/profile.png" class="menu-icon">
                        <span class="menu-label">My Account</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="create-account.php" class="menu-link">
                        <img src="../../asset/profile.png" class="menu-icon">
                        <span class="menu-label">Create Account</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <div class="reservation-card container-fluid px-5" 
             style="background: #fff; padding: 35px 60px; border-radius: 12px; 
                    box-shadow: 0 2px 12px rgba(0,0,0,0.12); max-width: 1350px; margin: 30px auto;">
            <div class="page-header mb-4" 
                 style="width: 100%; display: block; font-size: 1.9rem; font-weight: 600; 
                        border-bottom: 2px solid #ddd; padding-bottom: 15px; margin-bottom: 40px;">
                Create Account
            </div>

            <div class="card-body">

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <!-- PERSONAL INFORMATION SECTION -->
                    <div class="p-4 mb-4 border rounded bg-light" style="width: 100%;">
                        <h5 class="fw-bold mb-3" style="font-size: 1.2rem;">Personal Information</h5>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" placeholder="e.g., John" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" placeholder="e.g., De La Cruz" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Birthday</label>
                                <input type="date" class="form-control" name="birthday" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" placeholder="Enter email address" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option disabled selected>Select Role</option>
                                    <option value="resident">Resident</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" name="profile_picture" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <!-- ADDRESS INFORMATION SECTION -->
                    <div class="p-4 mb-4 border rounded bg-light" style="width: 100%;">
                        <h5 class="fw-bold mb-3" style="font-size: 1.2rem;">Address Information</h5>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Block</label>
                                <input type="text" class="form-control" name="block" placeholder="e.g., 5" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Lot</label>
                                <input type="text" class="form-control" name="lot" placeholder="e.g., 12" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Street Name</label>
                                <input type="text" class="form-control" name="street_name" placeholder="e.g., Acacia Street" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4 p-4">
                        <button type="submit" class="btn btn-primary" style="padding: 12px; font-size: 1.1rem; font-weight: 500;">
                            Create Account
                        </button>
                    </div>

                </form>

            </div>
        </div>

    </div>

</div>

<script src="../../resident-side/javascript/sidebar.js"></script>

</body>
</html>
