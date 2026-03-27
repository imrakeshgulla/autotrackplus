<?php require_once __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register • AutoTrack+</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/app.css">
</head>
<body>
  <div class="page">
    <form class="card" method="post" action="<?php echo BASE_URL; ?>/actions/auth_register.php">
      <div class="logo"><div class="logo-badge">AT+</div><div><strong>AutoTrack+</strong><br><small>Create account</small></div></div>
      <h1>Register</h1>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input class="input" name="first_name" placeholder="First name" required>
        <input class="input" name="last_name"  placeholder="Last name" required>
      </div>

      <input class="input" type="email" name="email" placeholder="Email" required style="margin-top:10px">
      <input class="input" name="emp_code" maxlength="6" pattern="\d{6}" placeholder="6-digit Employee Code" required style="margin-top:10px">

      <?php if(!empty($_GET['err'])): ?>
        <div class="error"><?php echo htmlspecialchars($_GET['err']); ?></div>
      <?php endif; ?>

      <button class="btn" type="submit">Register</button>

      <p style="margin-top:10px;font-size:12px">
        Already registered? <a href="<?php echo BASE_URL; ?>/public/login.php">Login</a>
      </p>
    </form>
  </div>
</body>
</html>
