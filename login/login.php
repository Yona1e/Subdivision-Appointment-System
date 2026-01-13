<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href='https://cdn.boxicons.com/3.0.7/fonts/basic/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="login1.css">


  <title>Facility Reservation System - Login</title>
</head>

<body>
  <div class="wrapper">
    <form id="loginForm" method="POST" action="../adminside/login_process.php">
      <h1>Facility Reservation System</h1>
      <h3></h3>
      <div class="input-box">
        <div class="mb-3">
          
          <input type="text" class="form-control" id="generatedID" name="generatedID"
            placeholder="Enter your Username" required>
        </div>
      </div>

      <!-- Password -->
      <div class="input-box">
        <div class="mb-3">
          
          <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password"
            required>
        </div>
      </div>

      <!-- Remember Me -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label" for="remember">Remember Me</label>
      </div>
      <!-- Submit Button -->
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Sign In</button>
      </div>
    </form>
  </div>


  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Check for error parameter in URL
    window.addEventListener('DOMContentLoaded', function () {
      const urlParams = new URLSearchParams(window.location.search);
      const error = urlParams.get('error');

      if (error === 'invalid') {
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.style.display = 'block';

        // Optional: Auto-hide after 5 seconds
        setTimeout(function () {
          errorMessage.style.display = 'none';
        }, 5000);
      }
    });
  </script>

</body>

</html>