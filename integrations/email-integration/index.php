<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Send OTP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card p-4 rounded-4">
          <h4 class="mb-3">Create Account</h4>

          <form action="mailer.php" method="POST">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <button type="submit" class="btn btn-primary w-100">
              Register & Send OTP
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</body>
</html>