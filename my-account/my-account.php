<?php
session_start();

// Redirect if not logged in or not a resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login/login.php");
    exit();
}

// Prevent page caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
$host = 'localhost';
$dbname = 'facilityreservationsystem';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT FirstName, LastName, Email, Birthday, Block, Lot, StreetName, ProfilePictureURL, password
    FROM users
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login/login.php");
    exit();
}

// Profile picture fallback
$profilePic = !empty($user['ProfilePictureURL'])
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

// Verify the file exists, otherwise use default
if (!empty($user['ProfilePictureURL']) && !file_exists('../' . $user['ProfilePictureURL'])) {
    $profilePic = '../asset/default-profile.png';
}

// User's full name for sidebar
$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Layout Styles -->
    <link rel="stylesheet" href="my-account1.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />



    <title>My Account</title>

</head>

<body>

    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <div class="profile-section">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-photo">
                    <div class="profile-info">
                        <p class="profile-name"><?= $userName ?></p>
                        <p class="profile-role">Resident</p>
                    </div>
                </div>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <ul class="menu-list">
                    <li class="menu-item">
                        <a href="../home/home.php" class="menu-link">
                            <img src="../asset/home.png" class="menu-icon">
                            <span class="menu-label">Home</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../resident-side/make-reservation.php" class="menu-link">
                            <img src="../asset/makeareservation.png" class="menu-icon">
                            <span class="menu-label">Make a Reservation</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../my-reservations/myreservations.php" class="menu-link">
                            <img src="../asset/reservations.png" class="menu-icon">
                            <span class="menu-label"> My Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="my-account.php" class="menu-link active">
                            <img src="../asset/profile.png" class="menu-icon">
                            <span class="menu-label">My Account</span>
                        </a>
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
                    My Account
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <!-- PROFILE PICTURE -->
                        <div class="col-md-4 text-center">

                            <img id="profilePreview" src="<?= htmlspecialchars($profilePic) ?>"
                                class="rounded-circle img-thumbnail mb-3"
                                style="width:180px;height:180px;object-fit:cover;" alt="Profile Picture">

                            <form action="update_profile_picture.php" method="POST" enctype="multipart/form-data"
                                id="profilePicForm">

                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <input type="file" name="profile_pic" id="profilePicInput"
                                    accept="image/jpeg,image/jpg,image/png,image/gif" hidden
                                    onchange="validateAndPreviewProfilePic(this)">

                                <button type="button" class="btn btn-primary w-100 mb-3"
                                    onclick="document.getElementById('profilePicInput').click();">
                                    Choose New Picture
                                </button>

                                <button type="submit" class="btn btn-success w-100" id="saveBtn" disabled>
                                    Save Profile Picture
                                </button>

                                <small class="text-muted d-block mt-2">
                                    Max size: 5MB. Allowed: JPG, PNG, GIF
                                </small>
                            </form>
                        </div>

                        <!-- USER INFO -->
                        <div class="col-md-8">

                            <div id="alertContainer"></div>

                            <div class="card p-3 mb-4">
                                <h5 class="mb-3">Personal Information</h5>
                                <p><strong>Name:</strong>
                                    <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?>
                                </p>
                                <p><strong>Email:</strong>
                                    <?= htmlspecialchars($user['Email']) ?>
                                </p>
                                <p><strong>Birthday:</strong>
                                    <?= htmlspecialchars($user['Birthday']) ?>
                                </p>
                                <p class="mb-0"><strong>Address:</strong>
                                    <?= htmlspecialchars("Blk. {$user['Block']}, Lt. {$user['Lot']}, {$user['StreetName']} St.") ?>
                                </p>
                            </div>

                            <div class="card p-3">
                                <h5 class="mb-3">Change Password</h5>
                                <form id="passwordForm">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                    <input type="password" name="old_password" class="form-control mb-4"
                                        placeholder="Old Password" required>

                                    <input type="password" name="new_password" class="form-control mb-4"
                                        placeholder="New Password" required id="newPassword">

                                    <input type="password" name="confirm_password" class="form-control mb-4"
                                        placeholder="Confirm Password" required id="confirmPassword">

                                    <button type="submit" class="btn btn-warning w-100">Update Password</button>
                                </form>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resident-side/javascript/sidebar.js"></script>

    <script>
        const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        function validateAndPreviewProfilePic(input) {
            const saveBtn = document.getElementById('saveBtn');
            const profilePreview = document.getElementById('profilePreview');

            if (!input.files || !input.files[0]) {
                saveBtn.disabled = true;
                return;
            }

            const file = input.files[0];

            if (!ALLOWED_TYPES.includes(file.type)) {
                alert('Invalid file type. Please select a JPG, PNG, or GIF image.');
                input.value = '';
                saveBtn.disabled = true;
                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                alert('File is too large. Maximum size is 5MB.');
                input.value = '';
                saveBtn.disabled = true;
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                profilePreview.src = e.target.result;
                saveBtn.disabled = false;
            };
            reader.onerror = function () {
                alert('Error reading file.');
                input.value = '';
                saveBtn.disabled = true;
            };
            reader.readAsDataURL(file);
        }

        // Password update via AJAX
        document.getElementById('passwordForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const oldPassword = this.old_password.value;
            const newPassword = this.new_password.value;
            const confirmPassword = this.confirm_password.value;

            if (newPassword !== confirmPassword) {
                alert('New password and confirmation do not match.');
                return;
            }

            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters long.');
                return;
            }

            fetch('update-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    old_password: oldPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('alertContainer');
                    container.innerHTML = `<div class="alert alert-${data.status === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
            ${data.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

                    if (data.status === 'success') {
                        this.reset();
                    }
                })
                .catch(err => console.error(err));
        });
    </script>

</body>

</html>