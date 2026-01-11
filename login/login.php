<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="login.css">
  <title>Facility Reservation System - Login</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="login-card">
          <div class="card">
            <div class="card-header text-center">
              <h4 class="mb-0">Facility Reservation System</h4>
            </div>
            <div class="card-body">
              <!-- Error Message Display -->
              <div id="errorMessage" class="alert alert-danger" style="display: none;" role="alert">
                <strong>Login Failed!</strong> Invalid Generated ID or Password.
              </div>

              <form id="loginForm" method="POST" action="../adminside/login_process.php">
                <!-- GeneratedID (Username) -->
                <div class="mb-3">
                  <label for="generatedID" class="form-label">Username (Generated ID)</label>
                  <input type="text" class="form-control" id="generatedID" name="generatedID" placeholder="Enter your Generated ID" required>
                </div>

                <!-- Password -->
                <div class="mb-3">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
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
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Check for error parameter in URL
    window.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      const error = urlParams.get('error');
      
      if (error === 'invalid') {
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.style.display = 'block';
        
        // Optional: Auto-hide after 5 seconds
        setTimeout(function() {
          errorMessage.style.display = 'none';
        }, 5000);
      }
    });
  </script>

</body>

</html>