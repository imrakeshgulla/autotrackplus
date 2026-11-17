<?php
require_once __DIR__ . '/../../config/config.php'; require_auth();
if ($_SESSION['user']['role'] !== 'admin'){ header('Location: ' . BASE_URL . '/public/employee/index.php'); exit; }

$users = $pdo->query("SELECT id,first_name,last_name,email,emp_code,role,is_verified,password_hash,created_at
                      FROM users ORDER BY created_at DESC")->fetchAll();
$vehicles = $pdo->query("SELECT v.*, CONCAT(u.first_name,' ',u.last_name) AS owner 
                         FROM vehicles v JOIN users u ON u.id=v.user_id ORDER BY v.id DESC")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin • AutoTrack+</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/app.css">
</head>
<body>
<div class="topbar">
  <strong>Admin Panel</strong>
  <div style="margin-left:auto">
    <form method="post" action="<?= BASE_URL ?>/public/logout.php" style="display:inline"><?= csrf_field() ?><button type="submit" style="color:#fff;background:#334155;padding:6px 10px;border:0;border-radius:8px;cursor:pointer">Logout</button></form>
  </div>
</div>

<div style="padding:18px" class="grid">

  <?php if (isset($_SESSION['admin_message'])): ?><p role="status"><?= h($_SESSION['admin_message']) ?></p><?php unset($_SESSION['admin_message']); endif; ?>
  <!-- Add Employee -->
  <div class="tile">
    <h2 style="margin:0 0 10px">Add Employee</h2>
    <form method="post" action="<?php echo BASE_URL; ?>/actions/admin_add_user.php" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf_field() ?>
      <input class="input" name="first_name" placeholder="First name" required style="flex:1 1 160px">
      <input class="input" name="last_name" placeholder="Last name" required style="flex:1 1 160px">
      <input class="input" type="email" name="email" placeholder="Email" required style="flex:2 1 240px">
      <input class="input" name="emp_code" maxlength="6" pattern="\d{6}" placeholder="6-digit code" required style="flex:0 0 120px">
      <select class="input" name="role" style="flex:0 0 140px">
        <option value="employee">employee</option>
        <option value="admin">admin</option>
      </select>
      <label><input type="checkbox" name="confirm_identity" required> I confirmed this employee’s identity and email.</label><button class="btn" style="width:auto">Create and invite</button>
    </form>
  </div>

  <!-- Employees -->
  <div class="tile">
    <h2 style="margin:0 0 10px">Employees</h2>
    <div class="vehicles">
      <?php foreach($users as $u): ?>
        <div class="vehicle" style="display:flex;align-items:center;gap:10px;justify-content:space-between">
          <div>
            <strong><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></strong>
            <span class="badge"><?php echo $u['role']; ?></span>
            <div style="font-size:12px;color:#6b7280">
              Emp: <?php echo $u['emp_code']; ?> • <?php echo htmlspecialchars($u['email']); ?> • 
              <?php echo $u['is_verified']?'Active':'Disabled'; ?> • <?= $u['password_hash'] ? 'Password set' : 'Password setup needed' ?>
            </div>
          </div>
          <div style="display:flex;gap:6px">
            <form method="post" action="<?= BASE_URL ?>/actions/admin_invite_user.php"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <label><input type="checkbox" name="confirm_identity" required> Identity and listed email confirmed</label>
              <button class="btn">Send password invitation</button>
            </form>
            <form method="post" action="<?php echo BASE_URL; ?>/actions/admin_toggle_verify.php"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
              <input type="hidden" name="to" value="<?php echo $u['is_verified']?0:1; ?>">
              <button class="btn" style="width:auto;background:#0ea5e9">
                <?php echo $u['is_verified']?'Unverify':'Verify'; ?>
              </button>
            </form>
            <?php if ($u['id'] !== $_SESSION['user']['id']): ?>
            <form method="post" action="<?php echo BASE_URL; ?>/actions/admin_delete_user.php" onsubmit="return confirm('Delete this user? This cannot be undone.')"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
              <button class="btn" style="width:auto;background:#ef4444">Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- All Vehicles -->
  <div class="tile">
    <h2 style="margin:0 0 10px">All Vehicles</h2>
    <div class="pills">
      <span class="pill active" data-filter="All">All</span>
      <span class="pill" data-filter="Due Soon">Due Soon</span>
      <span class="pill" data-filter="Expired">Expired</span>
      <span class="pill" data-filter="Completed">Completed</span>
    </div>
    <div class="vehicles" id="vlist">
      <?php foreach($vehicles as $v): ?>
        <div class="vehicle" data-status="<?php echo $v['status']; ?>">
          <strong><?php echo htmlspecialchars($v['make_model']); ?> (<?php echo $v['year']; ?>)</strong>
          <span class="badge" style="float:right"><?php echo $v['status']; ?></span>
          <div style="font-size:12px;color:#6b7280">
            Owner: <?php echo htmlspecialchars($v['owner']); ?> • Plate: <?php echo h($v['plate']); ?> • Next: <?php echo $v['next_service']; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function renderPills(active){
  document.querySelectorAll('.pill').forEach(p=>p.classList.toggle('active', p.dataset.filter===active));
}
function filterAdmin(f){
  renderPills(f);
  document.querySelectorAll('#vlist .vehicle').forEach(el=>{
    el.style.display = (f==='All' || el.dataset.status===f) ? '' : 'none';
  });
}
document.querySelectorAll('.pill').forEach(p=>p.addEventListener('click',()=>filterAdmin(p.dataset.filter)));
filterAdmin('All');
</script>
</body>
</html>
