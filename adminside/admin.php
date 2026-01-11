<?php
session_start();

// Check if user is logged in and is an Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "facilityreservationsystem");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all users from database
$sql = "SELECT UserID, GeneratedID, Role, FirstName, LastName FROM users ORDER BY UserID ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - All Users</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Admin Dashboard - All Users</h1>
                <p class="text-muted">Logged in as: <?php echo $_SESSION['firstName'] . ' ' . $_SESSION['lastName']; ?> (Admin)</p>
            </div>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Total Users: <?php echo $result->num_rows; ?></h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>User ID</th>
                                <th>Generated ID</th>
                                <th>Role</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['UserID']; ?></td>
                                    <td><?php echo $user['GeneratedID']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['Role'] == 'Admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                            <?php echo $user['Role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['FirstName']; ?></td>
                                    <td><?php echo $user['LastName']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No users found in the database.</div>
        <?php endif; ?>
    </div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>
<?php
$conn->close();
?>