<?php
require_once __DIR__ . '/../../config/config.php';
require_auth();
if ($_SESSION['user']['role'] !== 'employee') {
  header('Location:' . BASE_URL . '/public/admin/index.php');
  exit;
}

$userId = $_SESSION['user']['id'];

// Seed reminders
$pdo->prepare("
  INSERT IGNORE INTO notifications (user_id, vehicle_id, type, due_on)
  SELECT v.user_id, v.id, 'service', v.next_service
  FROM vehicles v
  WHERE v.user_id=? AND v.next_service BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
")->execute([$userId, REMINDER_DAYS]);

$pdo->prepare("
  INSERT IGNORE INTO notifications (user_id, vehicle_id, type, due_on)
  SELECT v.user_id, v.id, 'insurance', v.insurance_expiry
  FROM vehicles v
  WHERE v.user_id=? AND v.insurance_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
")->execute([$userId, REMINDER_DAYS]);

// Vehicles
$vehStmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id=? ORDER BY id DESC");
$vehStmt->execute([$userId]);
$vehicles = $vehStmt->fetchAll();

// Unread notifications
$bell = $pdo->prepare("
  SELECT n.id, n.type, n.due_on, v.make_model, v.plate
  FROM notifications n JOIN vehicles v ON v.id=n.vehicle_id
  WHERE n.user_id=? AND n.read_at IS NULL ORDER BY n.due_on ASC
");
$bell->execute([$userId]);
$unread = $bell->fetchAll();

// Repairs
$repStmt = $pdo->prepare("
  SELECT r.id, r.vehicle_id, r.note, r.tag, r.created_at
  FROM repairs r JOIN vehicles v ON v.id=r.vehicle_id
  WHERE v.user_id=? ORDER BY r.created_at DESC
");
$repStmt->execute([$userId]);
$repairs = $repStmt->fetchAll();
$repByVeh = [];
foreach ($repairs as $r) { $repByVeh[$r['vehicle_id']][] = $r; }

// ── TREND: vehicles added this week vs last week ──
$trendStmt = $pdo->prepare("
  SELECT
    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS this_week,
    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             AND created_at <  DATE_SUB(CURDATE(), INTERVAL 7 DAY)  THEN 1 ELSE 0 END) AS last_week
  FROM vehicles WHERE user_id=?
");
$trendStmt->execute([$userId]);
$trend     = $trendStmt->fetch();
$trendDiff = (int)$trend['this_week'] - (int)$trend['last_week'];

// ── TREND: how many more due next 7 days vs current ──
$dueTrendStmt = $pdo->prepare("
  SELECT
    SUM(CASE WHEN next_service BETWEEN CURDATE()
                               AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)  THEN 1 ELSE 0 END) AS next_week
  FROM vehicles WHERE user_id=?
");
$dueTrendStmt->execute([$userId]);
$dueTrend = $dueTrendStmt->fetch();

// ── Helpers ──
function getInitials(string $s): string {
  $w = preg_split('/\s+/', trim($s));
  return count($w) >= 2
    ? strtoupper(substr($w[0],0,1).substr($w[1],0,1))
    : strtoupper(substr($s,0,2));
}

$palettes = [
  ['bg'=>'#e0f2fe','color'=>'#0369a1'],
  ['bg'=>'#fce7f3','color'=>'#9d174d'],
  ['bg'=>'#d1fae5','color'=>'#065f46'],
  ['bg'=>'#fef3c7','color'=>'#92400e'],
  ['bg'=>'#ede9fe','color'=>'#5b21b6'],
  ['bg'=>'#e0e7ff','color'=>'#3730a3'],
  ['bg'=>'#fdf2f8','color'=>'#86198f'],
  ['bg'=>'#f0fdf4','color'=>'#166534'],
];

function avatarStyle(string $status, int $i, array $p): array {
  if ($status === 'Expired')   return ['bg'=>'#fee2e2','color'=>'#991b1b'];
  if ($status === 'Due Soon')  return ['bg'=>'#fef9c3','color'=>'#854d0e'];
  if ($status === 'Completed') return ['bg'=>'#dcfce7','color'=>'#166534'];
  return $p[$i % count($p)];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard • AutoTrack+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/app.css?v=20251011">
  <style>
    #emp-badge { display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px }

    /* ── Stat cards ── */
    .stat-cards { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px }
    .stat-card {
      background:var(--card);border:1px solid var(--ring);border-radius:14px;
      padding:16px 18px;box-shadow:0 10px 25px rgba(0,0,0,.06);
      display:flex;flex-direction:column;gap:12px;
    }
    .stat-card-top { display:flex;align-items:flex-start;justify-content:space-between;gap:8px }
    .stat-card-label { font-size:12px;color:var(--muted);font-weight:500;letter-spacing:.02em;margin-bottom:6px }
    .stat-card-num { font-size:34px;font-weight:700;line-height:1;letter-spacing:-.02em }
    .stat-card-icon {
      width:36px;height:36px;border-radius:10px;
      display:grid;place-items:center;font-size:17px;flex-shrink:0;
    }
    .trend-pill {
      display:inline-flex;align-items:center;gap:5px;
      font-size:12px;font-weight:500;padding:4px 9px;border-radius:99px;
    }
    .trend-up   { background:#dcfce7;color:#166534 }
    .trend-down { background:#fee2e2;color:#991b1b }
    .trend-warn { background:#fef9c3;color:#854d0e }
    .trend-flat { background:#f1f5f9;color:#64748b }

    /* ── Vehicle card avatars ── */
    .v-row { display:flex;align-items:flex-start;gap:10px }
    .v-avatar {
      width:38px;height:38px;border-radius:10px;flex-shrink:0;
      display:grid;place-items:center;font-size:12px;font-weight:700;
      letter-spacing:.03em;transition:transform .15s;
    }
    .vehicle:hover .v-avatar { transform:scale(1.1) }
    .v-body { flex:1;min-width:0 }
    .v-name { font-weight:700;font-size:14px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis }

    /* quick-add */
    .quick-add { display:grid;grid-template-columns:minmax(220px,1.2fr) 110px 150px 170px 170px 120px 120px;gap:10px;align-items:center;margin-top:10px }
    .quick-add .input { height:36px;padding:8px 10px;border-radius:8px }
    .quick-add .btn   { height:36px;border-radius:8px;padding:0 14px }
    .fieldcap { display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:4px }
    @media(max-width:1100px){.quick-add{grid-template-columns:1fr 110px 1fr 1fr 1fr 120px 120px}}
    @media(max-width:860px) {.quick-add{grid-template-columns:1fr 110px 1fr;grid-auto-rows:auto}.stat-cards{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="topbar">
  <strong>AutoTrack+ • Dashboard</strong>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:center;position:relative">
    <a class="btn btn--ghost" href="<?php echo BASE_URL; ?>/public/employee/history.php" style="text-decoration:none">History</a>

    <button id="bell" class="btn btn--ghost" style="position:relative">🔔
      <?php if (count($unread)): ?>
        <span style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:999px;font-size:11px;padding:2px 6px;"><?php echo count($unread); ?></span>
      <?php endif; ?>
    </button>

    <div id="bell-dd" class="tile" style="display:none;position:absolute;right:100px;top:42px;width:360px;max-height:360px;overflow:auto;z-index:100">
      <div style="font-weight:700;margin-bottom:6px">Notifications</div>
      <?php if (!count($unread)): ?>
        <div class="empty">No reminders. You're all set 🎉</div>
      <?php else: foreach ($unread as $n): ?>
        <div class="repair-row">
          <div>
            <div class="repair-title">
              <?php echo $n['type']==='service' ? '🔧 Service due' : '🛡️ Insurance expiring'; ?>
              – <?php echo htmlspecialchars($n['make_model']); ?> (<?php echo htmlspecialchars($n['plate']); ?>)
            </div>
            <div class="repair-meta">Due on <?php echo htmlspecialchars($n['due_on']); ?></div>
          </div>
          <form method="post" action="<?php echo BASE_URL; ?>/actions/notify_mark_read.php">
            <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
            <button class="btn btn--primary" style="width:auto">Dismiss</button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <span class="badge" id="emp-badge">Emp Code: <?php echo htmlspecialchars($_SESSION['user']['emp_code'] ?? '—'); ?></span>
    <a href="<?php echo BASE_URL; ?>/public/logout.php" class="btn btn--primary" style="text-decoration:none">Logout</a>
  </div>
</div>

<div style="padding:18px" class="grid">

  <!-- ── STAT CARDS ── -->
  <div class="stat-cards">

    <!-- Active Vehicles -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-card-label">Active Vehicles</div>
        <div class="stat-card-icon" style="background:#eff6ff">🚗</div>
      </div>
      <div class="stat-card-num" id="cAll"><?php echo count($vehicles); ?></div>
      <?php if ($trendDiff === 0): ?>
        <span class="trend-pill trend-flat">→ No change this week</span>
      <?php elseif ($trendDiff > 0): ?>
        <span class="trend-pill trend-up">↑ <?php echo abs($trendDiff); ?> added this week</span>
      <?php else: ?>
        <span class="trend-pill trend-down">↓ <?php echo abs($trendDiff); ?> removed this week</span>
      <?php endif; ?>
    </div>

    <!-- Due Soon -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-card-label">Due Soon</div>
        <div class="stat-card-icon" style="background:#fef9c3">⏳</div>
      </div>
      <div class="stat-card-num" id="cDue" style="color:#b45309">0</div>
      <?php $dueNext = (int)($dueTrend['next_week'] ?? 0); ?>
      <?php if ($dueNext > 0): ?>
        <span class="trend-pill trend-warn">⚠ <?php echo $dueNext; ?> more due next 7 days</span>
      <?php else: ?>
        <span class="trend-pill trend-flat">✓ None due next 7 days</span>
      <?php endif; ?>
    </div>

    <!-- Expired -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-card-label">Expired</div>
        <div class="stat-card-icon" style="background:#fee2e2">🔴</div>
      </div>
      <div class="stat-card-num" id="cExp" style="color:#b91c1c">0</div>
      <span class="trend-pill trend-flat" id="exp-trend-pill">✓ All clear</span>
    </div>

  </div>

  <!-- ── MAIN TILE ── -->
  <div class="tile">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
      <input class="input" id="q" placeholder="Search by make, model, or plate…">
      <div class="pills">
        <span class="pill active" data-filter="All">All</span>
        <span class="pill" data-filter="Due Soon">Due Soon</span>
        <span class="pill" data-filter="Expired">Expired</span>
        <span class="pill" data-filter="Completed">Completed</span>
      </div>
    </div>

    <form method="post" action="<?php echo BASE_URL; ?>/actions/add_vehicle.php" class="quick-add">
      <input class="input" name="make_model" placeholder="Make & Model" required>
      <input class="input" name="year" placeholder="Year" type="number" required>
      <input class="input" name="plate" placeholder="Plate" required>
      <div>
        <span class="fieldcap">Next Service</span>
        <input class="input" name="next_service" type="date" required>
      </div>
      <div>
        <span class="fieldcap">Insurance Expiry</span>
        <input class="input" name="insurance_expiry" type="date" required>
      </div>
      <input class="input" name="mileage" placeholder="Mileage" type="number" required>
      <button class="btn btn--accent" style="width:auto">Add Vehicle</button>
    </form>

    <div class="grid" style="margin-top:14px;grid-template-columns:300px 1fr;gap:16px">

      <!-- LEFT: vehicle list -->
      <div class="vehicles" id="listLeft">
        <?php foreach ($vehicles as $i => $v):
          $ini = getInitials($v['make_model']);
          $pal = avatarStyle($v['status'], $i, $palettes);
        ?>
          <div class="vehicle"
            data-id="<?php echo $v['id']; ?>"
            data-status="<?php echo $v['status']; ?>"
            data-text="<?php echo htmlspecialchars(strtolower($v['make_model'].' '.$v['plate'])); ?>">

            <div class="v-row">
              <div class="v-avatar"
                style="background:<?php echo $pal['bg']; ?>;color:<?php echo $pal['color']; ?>">
                <?php echo $ini; ?>
              </div>
              <div class="v-body">
                <div class="v-name">
                  <?php echo htmlspecialchars($v['make_model']); ?>
                  (<span class="js-inline" data-field="year" data-id="<?php echo $v['id']; ?>"><?php echo (int)$v['year']; ?></span>)
                </div>
                <span class="badge" data-status="<?php echo $v['status']; ?>" style="float:right;margin-top:-20px"><?php echo $v['status']; ?></span>
                <div class="small">
                  Plate: <span class="js-inline" data-field="plate" data-id="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['plate']); ?></span>
                  • Next: <?php echo $v['next_service'] ?: '—'; ?>
                  • Miles left: <?php echo (int)$v['miles_left']; ?>
                </div>
              </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:10px">
              <form method="post" action="<?php echo BASE_URL; ?>/actions/remove_vehicle.php"
                onsubmit="return confirm('Remove this vehicle and all its repairs?')">
                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                <button class="btn btn--danger" style="width:auto">Remove</button>
              </form>
              <a class="btn btn--primary" style="width:auto;text-decoration:none"
                href="<?php echo BASE_URL; ?>/public/employee/details.php?id=<?php echo $v['id']; ?>">Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- RIGHT: detail panel -->
      <div class="tile" id="detail">
        <div class="empty">Select a vehicle from the list to see details and repairs.</div>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
const vehicles = <?php echo json_encode($vehicles); ?>;
const repairsByVehicle = <?php echo json_encode($repByVeh); ?>;

const PALETTES = [
  {bg:'#e0f2fe',color:'#0369a1'},{bg:'#fce7f3',color:'#9d174d'},
  {bg:'#d1fae5',color:'#065f46'},{bg:'#fef3c7',color:'#92400e'},
  {bg:'#ede9fe',color:'#5b21b6'},{bg:'#e0e7ff',color:'#3730a3'},
  {bg:'#fdf2f8',color:'#86198f'},{bg:'#f0fdf4',color:'#166534'},
];

function avatarPalette(status, index) {
  if (status === 'Expired')   return {bg:'#fee2e2', color:'#991b1b'};
  if (status === 'Due Soon')  return {bg:'#fef9c3', color:'#854d0e'};
  if (status === 'Completed') return {bg:'#dcfce7', color:'#166534'};
  return PALETTES[index % PALETTES.length];
}

function getInitials(name) {
  const w = name.trim().split(/\s+/);
  return w.length >= 2 ? (w[0][0]+w[1][0]).toUpperCase() : name.slice(0,2).toUpperCase();
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function deriveVehicleStatus(id) {
  const reps = repairsByVehicle[id] || [];
  if (reps.some(r=>r.tag==='Expired'))  return 'Expired';
  if (reps.some(r=>r.tag==='Due Soon')) return 'Due Soon';
  return 'Completed';
}

function updateVehicleCardStatus(id, status) {
  const card = document.querySelector(`.vehicle[data-id="${id}"]`);
  if (!card) return;
  card.dataset.status = status;
  const badge = card.querySelector('span.badge');
  if (badge) { badge.dataset.status = status; badge.textContent = status; }
  // Update avatar color to match new status
  const avatar = card.querySelector('.v-avatar');
  if (avatar) {
    const idx = [...document.querySelectorAll('.vehicle')].indexOf(card);
    const p = avatarPalette(status, idx);
    avatar.style.background = p.bg;
    avatar.style.color = p.color;
  }
  refreshCounts();
}

function repairItem(r) {
  const when = new Date(r.created_at.replace(' ','T'));
  const stamp = isNaN(when) ? r.created_at : when.toLocaleString();
  return `<div class="repair-row">
    <div style="min-width:0">
      <div class="repair-title">${escapeHtml(r.note)} <span class="badge" style="margin-left:6px">${r.tag}</span></div>
      <div class="repair-meta">${stamp}</div>
    </div>
    <form class="js-update-tag" method="post" action="${BASE_URL}/actions/update_repair_tag.php" style="display:flex;gap:6px;align-items:center">
      <input type="hidden" name="id" value="${r.id}">
      <select class="tag-select input" name="tag" style="width:120px">
        <option ${r.tag==='Due Soon'?'selected':''}>Due Soon</option>
        <option ${r.tag==='Expired'?'selected':''}>Expired</option>
        <option ${r.tag==='Completed'?'selected':''}>Completed</option>
      </select>
    </form>
    <form method="post" action="${BASE_URL}/actions/delete_repair.php" onsubmit="return confirm('Delete this repair?');">
      <input type="hidden" name="id" value="${r.id}">
      <button class="btn btn--danger" style="width:auto">Delete</button>
    </form>
  </div>`;
}

let currentVehicleId = null;

function selectVehicle(id) {
  currentVehicleId = id;
  const v = vehicles.find(x=>x.id==id);
  const reps = repairsByVehicle[id] || [];
  if (!v) return;
  const status = deriveVehicleStatus(id);
  const idx = vehicles.indexOf(v);
  const p = avatarPalette(status, idx);
  document.getElementById('detail').innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:4px">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:40px;height:40px;border-radius:10px;background:${p.bg};color:${p.color};display:grid;place-items:center;font-size:13px;font-weight:700;flex-shrink:0">${escapeHtml(getInitials(v.make_model))}</div>
        <h2 style="font-size:16px;font-weight:700">${escapeHtml(v.make_model)} (${escapeHtml(String(v.year))})</h2>
      </div>
      <span class="badge" data-status="${status}">${status}</span>
    </div>
    <div class="metrics" style="margin:10px 0">
      <div class="metric"><div class="label">Mileage</div><h2>${Number(v.mileage).toLocaleString()}</h2></div>
      <div class="metric"><div class="label">Next Service</div><h2>${v.next_service||'—'}</h2></div>
      <div class="metric"><div class="label">Miles Left</div><h2>${Number(v.miles_left).toLocaleString()}</h2></div>
    </div>
    <form method="post" action="${BASE_URL}/actions/add_repair.php" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="vehicle_id" value="${v.id}">
      <input class="input" name="note" placeholder="e.g., Brake pads" required>
      <select class="input" name="tag"><option>Due Soon</option><option>Expired</option><option>Completed</option></select>
      <button class="btn btn--accent" style="width:auto">Add</button>
    </form>
    <h3 style="margin:16px 0 8px">Repairs History</h3>
    <div class="repair-list">${reps.length?reps.map(repairItem).join(''):'<div class="empty">No repairs yet.</div>'}</div>
  `;
  updateVehicleCardStatus(id, status);
}

function renderPills(active) {
  document.querySelectorAll('.pill').forEach(p=>p.classList.toggle('active',p.dataset.filter===active));
}

function refreshCounts() {
  const cards = [...document.querySelectorAll('.vehicle')];
  let due=0, exp=0;
  cards.forEach(el=>{
    if (el.dataset.status==='Due Soon') due++;
    else if (el.dataset.status==='Expired') exp++;
  });
  document.getElementById('cAll').textContent = cards.length;
  document.getElementById('cDue').textContent = due;
  document.getElementById('cExp').textContent = exp;
  // Update expired trend pill live
  const pill = document.getElementById('exp-trend-pill');
  if (pill) {
    if (exp===0) { pill.className='trend-pill trend-flat'; pill.textContent='✓ All clear'; }
    else         { pill.className='trend-pill trend-down'; pill.textContent=`↑ ${exp} need attention`; }
  }
}

function applyFilter(f) {
  renderPills(f);
  const q=(document.getElementById('q').value||'').trim().toLowerCase();
  document.querySelectorAll('.vehicle').forEach(el=>{
    el.style.display=((f==='All'||el.dataset.status===f)&&(!q||el.dataset.text.includes(q)))?'':'none';
  });
  refreshCounts();
}

document.querySelectorAll('.pills .pill').forEach(p=>p.addEventListener('click',()=>applyFilter(p.dataset.filter)));
document.getElementById('q').addEventListener('input',()=>applyFilter(document.querySelector('.pills .pill.active').dataset.filter));

const bellBtn=document.getElementById('bell'), dd=document.getElementById('bell-dd');
if(bellBtn&&dd){
  bellBtn.addEventListener('click',()=>dd.style.display=dd.style.display==='none'?'block':'none');
  document.addEventListener('click',e=>{if(!bellBtn.contains(e.target)&&!dd.contains(e.target))dd.style.display='none';});
}

function wireInline(){
  document.querySelectorAll('.js-inline').forEach(el=>{
    el.contentEditable='true';
    el.style.outline='2px dashed transparent';
    el.addEventListener('focus',()=>el.style.outline='2px dashed #93c5fd');
    el.addEventListener('blur',async()=>{
      el.style.outline='2px dashed transparent';
      const id=el.dataset.id,field=el.dataset.field,value=el.textContent.trim();
      try{
        const fd=new FormData();fd.append('id',id);fd.append('field',field);fd.append('value',value);
        const res=await fetch(`${BASE_URL}/actions/update_vehicle_field.php`,{method:'POST',body:fd,credentials:'same-origin'});
        if(!res.ok)throw 0; toast('Saved','ok');
      }catch{toast('Save failed','err');}
    });
  });
}

document.addEventListener('change',async(e)=>{
  const sel=e.target.closest('.js-update-tag .tag-select');
  if(!sel)return;
  const form=sel.closest('form'),fd=new FormData(form),newTag=sel.value,rid=fd.get('id');
  try{
    const res=await fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'});
    if(!res.ok)throw new Error();
    const list=repairsByVehicle[currentVehicleId]||[];
    const item=list.find(r=>String(r.id)===String(rid));
    if(item)item.tag=newTag;
    const rowBadge=form.parentElement.querySelector('.repair-title .badge');
    if(rowBadge)rowBadge.textContent=newTag;
    const s=deriveVehicleStatus(currentVehicleId);
    const hb=document.querySelector('#detail .badge[data-status]');
    if(hb){hb.dataset.status=s;hb.textContent=s;}
    updateVehicleCardStatus(currentVehicleId,s);
    toast('Status updated','ok');
  }catch{toast('Update failed','warn');}
});

function toast(msg,tone='ok'){
  let host=document.getElementById('toast-host');
  if(!host){host=document.createElement('div');host.id='toast-host';document.body.appendChild(host);}
  const el=document.createElement('div');el.className=`toast ${tone}`;el.textContent=msg;
  host.appendChild(el);
  requestAnimationFrame(()=>el.classList.add('show'));
  setTimeout(()=>el.classList.remove('show'),2200);
  setTimeout(()=>el.remove(),2600);
}

applyFilter('All');
wireInline();
(function(){
  Object.keys(repairsByVehicle).forEach(vid=>updateVehicleCardStatus(vid,deriveVehicleStatus(vid)));
  refreshCounts();
})();

// Auto-select first vehicle so the panel is never empty
const firstVehicle = document.querySelector('.vehicle[data-id]');
if (firstVehicle) selectVehicle(firstVehicle.dataset.id);
</script>
<script defer src="<?php echo BASE_URL; ?>/public/assets/app.js?v=20251011"></script>
</body>
</html>