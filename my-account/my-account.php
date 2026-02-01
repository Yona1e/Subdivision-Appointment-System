<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Resident') {
    header("Location: ../login/login.php");
    exit();
}

// Cache prevention
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// DB
$conn = new PDO(
    "mysql:host=localhost;dbname=facilityreservationsystem;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Fetch user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT FirstName, LastName, Email, Birthday, Block, Lot, StreetName, ProfilePictureURL
    FROM users WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login/login.php");
    exit();
}

$profilePic = (!empty($user['ProfilePictureURL']) && file_exists('../' . $user['ProfilePictureURL']))
    ? '../' . $user['ProfilePictureURL']
    : '../asset/default-profile.png';

$userName = htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="my-account1.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

    <title>My Account</title>
</head>

<body>
    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <header class="sidebar-header">
                <a href="../my-account/my-account.php" class="profile-link">
                    <div class="profile-section">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-photo">
                        <div class="profile-info">
                            <p class="profile-name">
                                <?= $userName ?>
                            </p>
                            <p class="profile-role">Resident</p>
                        </div>
                    </div>
                </a>
                <button class="sidebar-toggle">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
            </header>

            <div class="sidebar-content">
                <ul class="menu-list">
                    <li class="menu-item"><a href="../home/home.php" class="menu-link"><img src="../asset/home.png"
                                class="menu-icon"><span class="menu-label">Home</span></a></li>
                    <li class="menu-item"><a href="../resident-side/make-reservation.php" class="menu-link"><img
                                src="../asset/makeareservation.png" class="menu-icon"><span class="menu-label">Make a
                                Reservation</span></a></li>
                    <li class="menu-item"><a href="../my-reservations/myreservations.php" class="menu-link"><img
                                src="../asset/reservations.png" class="menu-icon"><span class="menu-label">My
                                Reservations</span></a></li>
                    <li class="menu-item"><a href="../my-account/my-account.php" class="menu-link active"><img
                                src="../asset/profile.png" class="menu-icon"><span class="menu-label">My
                                Account</span></a></li>
                </ul>
            </div>

            <div class="logout-section">
                <a href="../adminside/log-out.php" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" class="menu-icon"><span class="menu-label">Log
                        Out</span>
                </a>
            </div>
        </aside>


        <!-- MAIN -->
        <div class="main-content">
            <div class="reservation-card">
                <div class="page-header">My Account</div>

                <div class="card-body">
                    <div class="row g-4">

                        <!-- PROFILE PIC -->
                        <div class="col-md-4 text-center">
                            <img id="profilePreview" src="<?= htmlspecialchars($profilePic) ?>"
                                class="rounded-circle img-thumbnail mb-3"
                                style="width:180px;height:180px;object-fit:cover;">

                            <form action="update_profile_picture.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="file" name="profile_pic" hidden id="profilePicInput"
                                    accept="image/jpeg,image/png" onchange="previewPic(this)">
                                <button type="button" class="btn btn-primary w-100 mb-2"
                                    onclick="profilePicInput.click()">Choose Picture</button>
                                <button type="submit" class="btn btn-success w-100" id="saveBtn" disabled>
                                    Save Profile Picture
                                </button>
                            </form>
                        </div>

                        <!-- USER INFO -->
                        <div class="col-md-8">
                            <div id="alertContainer"></div>

                            <div class="card p-3 mb-4">
                                <h5>Personal Information</h5>
                                <p><strong>Name:</strong>
                                    <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($user['Email']) ?></p>
                                <p><strong>Birthday:</strong> <?= htmlspecialchars($user['Birthday']) ?></p>
                                <p><strong>Address:</strong>
                                    <?= htmlspecialchars("Blk {$user['Block']} Lt {$user['Lot']} {$user['StreetName']} St.") ?>
                                </p>
                            </div>

                            <div class="card p-3">
                                <h5>Change Password</h5>

                                <form id="passwordForm">
                                    <input type="password" name="old_password" class="form-control mb-3"
                                        placeholder="Old Password" required>
                                    <input type="password" name="new_password" class="form-control mb-3"
                                        placeholder="New Password" required>
                                    <input type="password" name="confirm_password" class="form-control mb-3"
                                        placeholder="Confirm Password" required>
                                    <button type="submit" class="btn btn-warning w-100">Update Password</button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../resident-side/javascript/sidebar.js"></script>

        <script>
            function previewPic(input) {
                if (input.files[0]) {
                    document.getElementById('profilePreview').src = URL.createObjectURL(input.files[0]);
                    document.getElementById('saveBtn').disabled = false;
                }
            }

            // PASSWORD AJAX
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('passwordForm');

                form.addEventListener('submit', e => {
                    e.preventDefault();

                    const oldP = form.old_password.value.trim();
                    const newP = form.new_password.value.trim();
                    const confirmP = form.confirm_password.value.trim();

                    if (oldP === newP) {
                        alert('New password cannot be the same as old password.');
                        return;
                    }

                    if (newP !== confirmP) {
                        alert('Passwords do not match.');
                        return;
                    }

                    if (newP.length < 6) {
                        alert('Password must be at least 6 characters.');
                        return;
                    }

                    fetch('update-password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            old_password: oldP,
                            new_password: newP,
                            confirm_password: confirmP
                        })
                    })
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('alertContainer').innerHTML = `
        <div class="alert alert-${data.status === 'success' ? 'success' : 'danger'} alert-dismissible fade show">
            ${data.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
                            if (data.status === 'success') form.reset();
                        });
                });
            });
        </script>

</body>

</html>