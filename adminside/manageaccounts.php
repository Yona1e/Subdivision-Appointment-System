<?php
session_start();

/* =======================
   SECURITY & CACHE
======================= */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

/* =======================
   DATABASE
======================= */
$pdo = new PDO(
    "mysql:host=localhost;dbname=facilityreservationsystem",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* =======================
   CSRF TOKEN
======================= */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =======================
   UPDATE USER
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: manageaccounts.php");
        exit();
    }

    $sql = "
        UPDATE users SET
            FirstName   = :first_name,
            LastName    = :last_name,
            Birthday    = :birthday,
            Email       = :email,
            Role        = :role,
            Block       = :block,
            Lot         = :lot,
            StreetName  = :street,
            Status      = :status
    ";

    $params = [
        ':first_name' => $_POST['first_name'],
        ':last_name'  => $_POST['last_name'],
        ':birthday'   => $_POST['birthday'] ?: null,
        ':email'      => $_POST['email'],
        ':role'       => $_POST['role'],
        ':block'      => $_POST['block'],
        ':lot'        => $_POST['lot'],
        ':street'     => $_POST['street_name'],
        ':status'     => $_POST['status'],
        ':user_id'    => (int)$_POST['user_id']
    ];

    if (!empty($_POST['password'])) {
        $sql .= ", Password = :password";
        $params[':password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    $sql .= " WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['success'] = "Account updated successfully.";
    header("Location: manageaccounts.php");
    exit();
}

/* =======================
   FETCH USERS
======================= */
$users = $pdo->query("
    SELECT user_id, FirstName, LastName, Birthday, Email,
           Role, Block, Lot, StreetName, Status
    FROM users
    ORDER BY user_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="stylesheet" href="overview.css">
    <link rel="stylesheet" href="../resident-side/style/side-navigation1.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                        <a href="overview.php" class="menu-link active">
                            <img src="../asset/home.png" alt="Home Icon" class="menu-icon">
                            <span class="menu-label">Overview</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reserverequests.php" class="menu-link">
                            <img src="../asset/makeareservation.png" alt="Make a Reservation Icon" class="menu-icon">
                            <span class="menu-label">Requests</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="reservations.php" class="menu-link">
                            <img src="../asset/reservations.png" alt="Reservations Icon" class="menu-icon">
                            <span class="menu-label">Reservations</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="manageaccounts.php" class="menu-link">
                            <img src="../asset/profile.png" alt="My Account Icon" class="menu-icon">
                            <span class="menu-label">My Account</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="create-account.php" class="menu-link">
                            <img src="../asset/profile.png" alt="Create Account Icon" class="menu-icon">
                            <span class="menu-label">Create Account</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="logout-section">
                <a href="log-out.php" method="post" class="logout-link menu-link">
                    <img src="https://api.iconify.design/mdi/logout.svg" alt="Logout" class="menu-icon">
                    <span class="menu-label">Log Out</span>
                </a>
            </div>
        </aside>

<!-- MAIN CONTENT -->
    <div class="main-content">  

        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                
        <h1 class="mb-0">Admin Dashboard</h1>
    </div>

    


    </div>  


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>


<script src="../resident-side/javascript/sidebar.js"></script>
</body>
</html>
