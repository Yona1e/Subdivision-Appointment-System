<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://cdn.boxicons.com/3.0.7/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="login1.css">
  <!-- Boxicons -->
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <title>Facility Reservation System - Login</title>
</head>

<body>
  <div class="wrapper">
    <form id="loginForm" method="POST" action="../adminside/login_process.php">
      <h1>Facility Reservation System</h1>
      <h3></h3>

      <div id="errorMessage" class="alert alert-danger" style="display: none; margin-top: 10px;" role="alert"></div>

      <!-- Email Input -->
      <div class="input-box">
        <div class="mb-3 position-relative">
          <i class='bx bx-envelope input-icon'></i>
          <input type="text" class="form-control ps-5" id="email" name="email" placeholder="Enter your email" required>
        </div>
      </div>
      <!-- Password -->
      <!-- Password -->
      <div class="input-box">
        <div class="mb-3 position-relative">
          <i class='bx bx-lock-alt input-icon'></i>
          <input type="password" class="form-control ps-5" id="password" name="password"
            placeholder="Enter your password" required>
          <i class='bx bx-hide toggle-password' id="togglePassword"></i>
        </div>
      </div>

      <!-- Remember Me -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember">
        <label class="form-check-label" for="remember">Remember Me</label>
      </div>

      <!-- Submit Button -->
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">
          <span class="btn-text">Sign In</span>
        </button>
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

            const errorMessage = document.getElementById('errorMessage');

            if (error === 'invalid') {
                errorMessage.textContent = 'Invalid email or password.';
                errorMessage.style.display = 'flex';

                // Auto-hide after 5 seconds
                setTimeout(function () {
                    errorMessage.style.opacity = '0';
                    setTimeout(function () {
                        errorMessage.style.display = 'none';
                        errorMessage.style.opacity = '1';
                    }, 300);
                }, 5000);
            } else if (error === 'archived') {
                errorMessage.textContent = 'Your account has been archived. Please contact your administrator to reactivate it.';
                errorMessage.classList.remove('alert-danger');
                errorMessage.classList.add('alert-warning');
                errorMessage.style.display = 'flex';

                // Keep archived message visible longer (12s)
                setTimeout(function () {
                    errorMessage.style.opacity = '0';
                    setTimeout(function () {
                        errorMessage.style.display = 'none';
                        errorMessage.style.opacity = '1';
                    }, 300);
                }, 12000);
            }
        });

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            this.classList.toggle('bx-hide');
            this.classList.toggle('bx-show');
        });

        // Add loading state to button on submit
        const loginForm = document.getElementById('loginForm');
        const submitBtn = loginForm.querySelector('.btn-primary');

        loginForm.addEventListener('submit', function () {
            submitBtn.classList.add('loading');
            submitBtn.querySelector('.btn-text').textContent = 'Signing In...';
        });
    </script>


</body>

</html>