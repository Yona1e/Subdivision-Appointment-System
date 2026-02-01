<?php
session_start();

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch logged-in user's information
$loggedInUserName = "Admin";
$loggedInUserProfilePic = "../asset/profile.jpg"; // Default profile picture

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT FirstName, LastName, ProfilePictureURL FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $loggedInUserName = $userData['FirstName'] . ' ' . $userData['LastName'];

        if (!empty($userData['ProfilePictureURL']) && file_exists('../' . $userData['ProfilePictureURL'])) {
            $loggedInUserProfilePic = '../' . $userData['ProfilePictureURL'];
        }
    }
    $stmt->close();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid security token. Please try again.";
        header("Location: create-account.php");
        exit();
    }

    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $block = trim($_POST['block']);
    $lot = trim($_POST['lot']);
    $streetName = trim($_POST['street_name']);

    // Validate required fields
    if (
        empty($firstName) || empty($lastName) || empty($email) || empty($password) ||
        empty($role) || empty($block) || empty($lot) || empty($streetName) || empty($birthday)
    ) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: create-account.php");
        exit();
    }

    // Validate Names (Letters and spaces only)
    if (!preg_match("/^[a-zA-Z\s]+$/", $firstName) || !preg_match("/^[a-zA-Z\s]+$/", $lastName)) {
        $_SESSION['error'] = "Names must contain letters only!";
        header("Location: create-account.php");
        exit();
    }
    // Auto-capitalize names
    $firstName = ucwords(strtolower($firstName));
    $lastName = ucwords(strtolower($lastName));

    // Validate Block and Lot (Numbers only, max 2 chars)
    if (!ctype_digit($block) || strlen($block) > 2) {
        $_SESSION['error'] = "Block must be a number with max 2 digits!";
        header("Location: create-account.php");
        exit();
    }
    if (!ctype_digit($lot) || strlen($lot) > 2) {
        $_SESSION['error'] = "Lot must be a number with max 2 digits!";
        header("Location: create-account.php");
        exit();
    }

    // Validate email format (Standard + Strict TLD check)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: create-account.php");
        exit();
    }
    // Strict TLD check: Must contain only letters (no numbers like .com1)
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $_SESSION['error'] = "Invalid email domain! TLD must contain letters only.";
        header("Location: create-account.php");
        exit();
    }

    // Validate password length
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long!";
        header("Location: create-account.php");
        exit();
    }

    // Validate birthday and age (Must be 18+)
    if ($birthday !== null) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $birthday) {
            $_SESSION['error'] = "Invalid birthday format!";
            header("Location: create-account.php");
            exit();
        }

        // Check if birthday is not in the future
        $today = new DateTime();
        if ($dateObj > $today) {
            $_SESSION['error'] = "Birthday cannot be in the future!";
            header("Location: create-account.php");
            exit();
        }

        // Check age >= 18
        $age = $getAge = $dateObj->diff($today)->y;
        if ($age < 18) {
            $_SESSION['error'] = "Account creation failed. User must be at least 18 years old.";
            header("Location: create-account.php");
            exit();
        }
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
    $profilePictureURL = 'uploads/profile_pictures/default.png'; // Default picture path
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {

        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

        $fileSize = $_FILES['profile_picture']['size'];
        $fileTmpName = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileMimeType = mime_content_type($fileTmpName);

        // Validate file size
        if ($fileSize > $maxFileSize) {
            $_SESSION['error'] = "Profile picture is too large. Maximum size is 5MB.";
            header("Location: create-account.php");
            exit();
        }

        // Validate file extension
        if (!in_array($fileExtension, $allowedExtensions)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            header("Location: create-account.php");
            exit();
        }

        // Validate MIME type
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            $_SESSION['error'] = "Invalid file format detected.";
            header("Location: create-account.php");
            exit();
        }

        // Validate that it's actually an image
        $imageInfo = getimagesize($fileTmpName);
        if ($imageInfo === false) {
            $_SESSION['error'] = "File is not a valid image.";
            header("Location: create-account.php");
            exit();
        }

        // FIXED: Use absolute path from document root
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Subdivision-Appointment-System/uploads/profile_pictures/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $_SESSION['error'] = "Failed to create upload directory.";
                header("Location: create-account.php");
                exit();
            }
        }

        // Create .htaccess to prevent PHP execution in upload directory
        $htaccessPath = $uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "php_flag engine off\nOptions -Indexes";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        $newFileName = 'profile_' . uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            chmod($uploadPath, 0644);
            // FIXED: Store relative path from web root
            $profilePictureURL = 'uploads/profile_pictures/' . $newFileName;
        } else {
            $_SESSION['error'] = "Failed to upload profile picture.";
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
        $sql = "INSERT INTO users (FirstName, LastName, Birthday, Email, Password, Role, Block, Lot, StreetName, ProfilePictureURL, Status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $firstName,
            $lastName,
            $birthday,
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

    } catch (PDOException $e) {
        // If database insert fails and file was uploaded, delete the file
        if ($profilePictureURL) {
            $deleteFile = $_SERVER['DOCUMENT_ROOT'] . '/Subdivision-Appointment-System/' . $profilePictureURL;
            if (file_exists($deleteFile)) {
                unlink($deleteFile);
            }
        }

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

    <link rel="stylesheet" href="create-account.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <title>Create Account</title>

</head>

<body>

    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <div class="profile-section">
                    <img src="<?= htmlspecialchars($loggedInUserProfilePic) ?>" alt="Profile" class="profile-photo"
                        onerror="this.src='../asset/profile.jpg'">
                    <div class="profile-info">
                        <p class="profile-name">
                            <?= htmlspecialchars($loggedInUserName) ?>
                        </p>
                        <p class="profile-role">Admin</p>
                    </div>
                </div>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="overview.php" class="menu-link">
                            <img src="../asset/home.png" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reserverequests.php" class="menu-link">
                            <img src="../asset/makeareservation.png" class="menu-icon">
                            <span class="menu-label">Requests</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reservations.php" class="menu-link">
                            <img src="../asset/reservations.png" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="quick-reservation/quick-reservation.php" class="menu-link">
                            <img src="../asset/Vector.png" class="menu-icon">
                            <span class="menu-label">Quick Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="manageaccounts.php" class="menu-link">
                            <img src="../asset/manage2.png" alt="Manage Accounts Icon" class="menu-icon">
                            <span class="menu-label">Manage Accounts</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="create-account.php" class="menu-link active">
                            <img src="../asset/profile.png" class="menu-icon">
                            <span class="menu-label">Create Account</span>
                        </a>
                    </li>
                    <li class="menu-item">
                    </li>
                </ul>
            </div>
            <div class="logout-section">
                <a href="../adminside/log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="main-content">

            <div class="reservation-card">
                <div class="page-header">
                    Create Account
                </div>

                <div class="card-body">

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php
                            echo htmlspecialchars($_SESSION['success']);
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php
                            echo htmlspecialchars($_SESSION['error']);
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="createAccountForm">

                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <!-- PERSONAL INFORMATION SECTION -->
                        <div class="p-4 mb-4 border rounded bg-light" style="width: 100%;">
                            <h5 class="fw-bold mb-3" style="font-size: 1.2rem;">Personal Information</h5>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" placeholder="e.g., John"
                                        required maxlength="75">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name"
                                        placeholder="e.g., De La Cruz" required maxlength="75">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Birthday <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="birthday"
                                        max="<?= date('Y-m-d', strtotime('-18 years')) ?>" id="birthdayInput" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email"
                                        placeholder="Enter email address" required maxlength="64">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password"
                                        placeholder="Minimum 6 characters" required minlength="6" maxlength="64">
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role" required>
                                        <option value="" disabled selected>Select Role</option>
                                        <option value="resident">Resident</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Profile Picture <span
                                            class="text-muted">(Optional)</span></label>
                                    <input type="file" class="form-control" name="profile_picture"
                                        accept="image/jpeg,image/jpg,image/png,image/gif" id="profilePicInput">
                                    <small class="text-muted">Max 5MB. Allowed: JPG, PNG, GIF</small>
                                </div>
                            </div>
                        </div>

                        <!-- ADDRESS INFORMATION SECTION -->
                        <div class="p-4 mb-4 border rounded bg-light" style="width: 100%;">
                            <h5 class="fw-bold mb-3" style="font-size: 1.2rem;">Address Information</h5>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Block <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="block" placeholder="e.g., 5" required
                                        maxlength="2" id="blockInput">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Lot <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="lot" placeholder="e.g., 12" required
                                        maxlength="2" id="lotInput">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Street Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="street_name"
                                        placeholder="e.g., Acacia Street" required maxlength="64">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4 p-4">
                            <button type="submit" class="btn btn-primary"
                                style="padding: 12px; font-size: 1.1rem; font-weight: 500;">
                                Create Account
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>

    </div>

    <script src="../../resident-side/javascript/sidebar.js"></script>

    <script>
        // Client-side validation for profile picture
        const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        document.getElementById('profilePicInput')?.addEventListener('change', function (e) {
            const file = e.target.files[0];

            if (file) {
                // Check file size
                if (file.size > MAX_FILE_SIZE) {
                    alert('File is too large. Maximum size is 5MB.');
                    e.target.value = '';
                    return;
                }

                // Check file type
                if (!ALLOWED_TYPES.includes(file.type)) {
                    alert('Invalid file type. Please select a JPG, PNG, or GIF image.');
                    e.target.value = '';
                    return;
                }
            }
        });

        // Birthday validation
        document.getElementById('birthdayInput')?.addEventListener('change', function (e) {
            const selectedDate = new Date(e.target.value);
            const today = new Date();

            // Calculate age
            let age = today.getFullYear() - selectedDate.getFullYear();
            const m = today.getMonth() - selectedDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < selectedDate.getDate())) {
                age--;
            }

            if (selectedDate > today) {
                alert('Birthday cannot be in the future.');
                e.target.value = '';
            } else if (age < 18) {
                alert('User must be at least 18 years old.');
                e.target.value = '';
            }
        });

        // Form submission validation
        document.getElementById('createAccountForm')?.addEventListener('submit', function (e) {
            const password = document.querySelector('input[name="password"]').value;

            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }

            // Email Validation (Strict TLD)
            const email = document.querySelector('input[name="email"]').value;
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Invalid email format. Domain must contain letters only (e.g., .com, .ph).');
                return false;
            }

            const birthday = document.querySelector('input[name="birthday"]').value;
            if (!birthday) {
                e.preventDefault();
                alert('Birthday is required.');
                return false;
            }
        });

        // Name Validation: Letters only, Auto-Capitalize
        function validateNameInput(input) {
            // Remove non-letters/spaces
            input.value = input.value.replace(/[^a-zA-Z\s]/g, '');

            // Auto-capitalize first letter of each word
            const words = input.value.split(' ');
            for (let i = 0; i < words.length; i++) {
                if (words[i].length > 0) {
                    words[i] = words[i][0].toUpperCase() + words[i].substr(1);
                }
            }
            input.value = words.join(' ');
        }

        const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="last_name"]');
        nameInputs.forEach(input => {
            input.addEventListener('input', function () {
                validateNameInput(this);
            });
        });

        // Block & Lot Validation: Numbers only, max 2 digits
        const numberInputs = document.querySelectorAll('input[name="block"], input[name="lot"]');
        numberInputs.forEach(input => {
            input.addEventListener('input', function () {
                // Remove non-numbers
                this.value = this.value.replace(/[^0-9]/g, '');

                // Enforce max length 2
                if (this.value.length > 2) {
                    this.value = this.value.slice(0, 2);
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
        integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y"
        crossorigin="anonymous"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>
</body>

</html>