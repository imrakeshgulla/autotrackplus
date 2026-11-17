<?php
// CLI-only integration suite. Creates/drops ONLY its own randomly named database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$name = 'autotrackplus_auth_test_' . bin2hex(random_bytes(5));
$admin = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = null;
$count = 0;
function check(bool $condition, string $message): void { global $count; if (!$condition) throw new RuntimeException($message); $count++; echo "PASS $message\n"; }
function run_cli(array $args): string {
    global $root;
    $process = proc_open(array_merge(['php'], $args), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process)) throw new RuntimeException('CLI failed: ' . $error);
    return $output;
}
class Browser {
    public string $cookie = '';
    public string $csrf = '';
    public function request(string $path, ?array $data = null): array {
        $headers = ['Cookie: ' . $this->cookie];
        if ($data !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http'=>['method'=>$data === null ? 'GET' : 'POST','header'=>implode("\r\n", $headers),'content'=>$data === null ? '' : http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]);
        $body = file_get_contents('http://127.0.0.1:8091/autotrackplus/' . $path, false, $context);
        if ($body === false) throw new RuntimeException('HTTP failed: ' . $path);
        $status = (int)explode(' ', $http_response_header[0])[1]; $location = '';
        foreach ($http_response_header as $line) {
            if (stripos($line, 'Set-Cookie: PHPSESSID=') === 0) $this->cookie = explode(';', substr($line, 12))[0];
            if (stripos($line, 'Location: ') === 0) $location = substr($line, 10);
        }
        if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match)) $this->csrf = $match[1];
        check($status < 500, explode('?', $path)[0] . ' has no server error');
        return ['status'=>$status,'body'=>$body,'location'=>$location,'headers'=>$http_response_header];
    }
    public function post(string $path, array $data): array { return $this->request($path, ['csrf'=>$this->csrf] + $data); }
}
function mail_link(string $to): string {
    $messages = json_decode(file_get_contents('http://mailpit:8025/api/v1/messages'), true)['messages'];
    foreach ($messages as $message) {
        if (($message['To'][0]['Address'] ?? '') !== $to) continue;
        $detail = json_decode(file_get_contents('http://mailpit:8025/api/v1/message/' . $message['ID']), true);
        if (preg_match('/set-password\.php\?token=([a-f0-9]{64})/', $detail['HTML'], $match)) return $match[1];
    }
    throw new RuntimeException('Setup email not captured for test recipient.');
}
try {
    putenv('DB_NAME=' . $name);
    putenv('APP_URL=http://127.0.0.1:8091/autotrackplus');
    // Recreate the legacy shape to test a real upgrade, including existing business data.
    $schema = explode('-- Password support for fresh installations', file_get_contents($root . '/sql/schema.sql'))[0];
    $schema = preg_replace('/^  (password_hash|password_changed_at|password_setup_authorized_at|session_version) .*\n/m', '', $schema);
    $schema = str_replace('autotrackplus', $name, $schema);
    $admin->exec($schema);
    $admin->exec("INSERT INTO users (first_name,last_name,email,emp_code,is_verified) VALUES ('Test','Employee','employee-{$name}@example.test','001234',1),('Test','Disabled','disabled-{$name}@example.test','001235',0)");
    $admin->exec("INSERT INTO vehicles (user_id,make_model,year,plate) VALUES (2,'Test Van',2020,'TEST')");
    $admin->exec("INSERT INTO repairs (vehicle_id,note) VALUES (1,'Preserve this repair')");
    $before = [];
    foreach (['users','vehicles','repairs','notifications','activity_log'] as $table) $before[$table] = $admin->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    check(str_contains(run_cli(['bin/migrate.php']), 'Applied'), 'legacy migration applies');
    check(str_contains(run_cli(['bin/migrate.php']), 'already applied'), 'migration is repeat-safe');
    foreach ($before as $table=>$rows) {
        $columns = $rows ? implode(',', array_map(fn($c)=>"`$c`", array_keys($rows[0]))) : '*';
        check($rows === $admin->query("SELECT $columns FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC), "$table original data preserved");
    }
    require $root . '/config/config.php';
    $log = sys_get_temp_dir() . '/' . $name . '.log';
    $server = proc_open(['php','-S','127.0.0.1:8091','-t',dirname($root)], [0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']], $pipes, $root);
    for ($i=0;$i<30;$i++) { $socket = @fsockopen('127.0.0.1',8091); if ($socket) { fclose($socket); break; } usleep(100000); }
    $b = new Browser(); $r = $b->request('public/login.php');
    check(str_contains($r['body'], 'name="password"'), 'login requires password input');
    check(str_contains(implode(' ', $r['headers']), 'HttpOnly') && str_contains(implode(' ', $r['headers']), 'SameSite=Lax'), 'session cookie flags set for local HTTP');
    $r=$b->post('actions/auth_login.php',['emp_code'=>'999999']); check(str_contains($r['location'],'err=1'),'code alone rejected');
    check($b->request('public/admin/index.php')['status']===302,'unauthenticated admin access rejected');
    $legacy = new Browser();
    $legacyId = bin2hex(random_bytes(16));
    file_put_contents('/tmp/sess_' . $legacyId, 'user|a:2:{s:2:"id";i:1;s:4:"role";s:5:"admin";}');
    $legacy->cookie = 'PHPSESSID=' . $legacyId;
    check($legacy->request('public/admin/index.php')['status']===302,'legacy code-only sessions rejected');
    check($b->request('actions/auth_login.php',['emp_code'=>'999999','password'=>'anything'])['status']===403,'login CSRF enforced');
    check($b->request('actions/auth_login.php')['status']===405,'login GET rejected');
    check($b->post('actions/auth_register.php',['emp_code'=>'123456'])['status']===403,'self-registration cannot claim employee codes');
    check($b->request('public/verify.php?token=legacy')['status']===410,'legacy verification cannot activate account');
    $b->request('public/request-password.php');
    $r=$b->post('public/request-password.php',['emp_code'=>'001234']); check(str_contains($r['body'],'If this account is eligible'),'recovery response is generic');
    check((int)$pdo->query('SELECT COUNT(*) FROM password_tokens')->fetchColumn()===0,'legacy verified flag does not authorize email setup');
    $email = 'admin-' . $name . '@example.test';
    run_cli(['bin/issue-password-setup.php','--code=999999','--email='.$email,'--confirm-identity']);
    $token=mail_link($email);
    check($pdo->query('SELECT token_hash FROM password_tokens')->fetchColumn()===hash('sha256',$token),'database stores token hash only');
    $b->request('public/set-password.php?token='.$token); $b->request('public/set-password.php');
    $r=$b->post('public/set-password.php',['password'=>'short','password_confirmation'=>'short']); check(str_contains($r['body'],'15 to 128'),'short passwords rejected');
    $password='Unique cedar river lantern 42!';
    $r=$b->post('public/set-password.php',['password'=>$password,'password_confirmation'=>'different']); check(str_contains($r['body'],'do not match'),'confirmation mismatch rejected');
    $r=$b->post('public/set-password.php',['password'=>$password,'password_confirmation'=>$password]); check(str_contains($r['location'],'password-set'),'admin setup via captured email succeeds');
    check(password_verify($password,$pdo->query('SELECT password_hash FROM users WHERE id=1')->fetchColumn()),'Argon2id hash verifies');
    check(!consume_password_link($token,$password),'used token cannot be replayed');
    $b->request('public/login.php');
    $r=$b->post('actions/auth_login.php',['emp_code'=>'999999','password'=>'wrong']); check(str_contains($r['location'],'err=1'),'wrong password rejected');
    $oldCookie=$b->cookie;
    $r=$b->post('actions/auth_login.php',['emp_code'=>'999999','password'=>$password]); check(str_contains($r['location'],'admin/index.php'),'admin password login succeeds');
    check($b->cookie!==$oldCookie,'login rotates session ID');
    $r=$b->request('public/admin/index.php'); check($r['status']===200 && str_contains($r['body'],'Send password invitation'),'admin dashboard works');
    check(!str_contains($r['body'],'<?=') && preg_match('/action="\/autotrackplus\/actions\/admin_add_user.php"[^>]*>\s*<input type="hidden" name="csrf"/', $r['body'])===1,'admin form action and CSRF render correctly');
    $r=$b->post('actions/admin_add_user.php',['first_name'=>'Invited','last_name'=>'Employee','email'=>'invited-'.$name.'@example.test','emp_code'=>'001236','role'=>'employee','confirm_identity'=>'on']); check($r['status']===302,'admin creates invited employee');
    $newToken=mail_link('invited-'.$name.'@example.test'); check(consume_password_link($newToken,$password),'new employee invitation works');
    $disabledEmail='disabled-'.$name.'@example.test';
    run_cli(['bin/issue-password-setup.php','--code=001235','--email='.$disabledEmail,'--confirm-identity']);
    check(consume_password_link(mail_link($disabledEmail),$password),'disabled account can choose a password');
    check((int)$pdo->query('SELECT is_verified FROM users WHERE id=3')->fetchColumn()===0,'password setup does not activate disabled accounts');
    $r=$b->post('actions/admin_invite_user.php',['id'=>2,'confirm_identity'=>'on']); check($r['status']===302,'admin can invite existing employee');
    $employeeToken=mail_link('employee-'.$name.'@example.test');
    issue_password_link(2); $replacementToken=mail_link('employee-'.$name.'@example.test');
    check(!consume_password_link($employeeToken,$password),'resending invalidates old link');
    $pdo->exec('UPDATE password_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE user_id=2');
    check(!consume_password_link($replacementToken,$password),'expired token rejected');
    issue_password_link(2); $replacementToken=mail_link('employee-'.$name.'@example.test'); check(consume_password_link($replacementToken,$password),'existing employee sets password');
    $employee=new Browser(); $employee->request('public/login.php');
    $r=$employee->post('actions/auth_login.php',['emp_code'=>'001234','password'=>$password]); check(str_contains($r['location'],'employee/index.php'),'employee login preserves leading zeroes');
    $r=$employee->request('public/employee/index.php'); check($r['status']===200 && str_contains($r['body'],'Test Van'),'employee dashboard and original vehicle work');
    foreach (['details.php?id=1','history.php','notifications.php'] as $page) check($employee->request('public/employee/'.$page)['status']===200,'existing employee '.$page.' works');
    check($employee->post('actions/admin_invite_user.php',['id'=>1,'confirm_identity'=>'on'])['status']===403,'employee cannot issue admin invitations');
    check($employee->post('actions/update_repair_tag.php',['id'=>1,'tag'=>'Completed'])['status']===200,'repair tag updates work with MySQL and CSRF');
    check($employee->post('actions/add_repair.php',['vehicle_id'=>1,'note'=>'Additional test repair','tag'=>'Due Soon'])['status']===302,'repair creation works with CSRF');
    $repairId=$pdo->query('SELECT MAX(id) FROM repairs')->fetchColumn();
    check($employee->post('actions/delete_repair.php',['id'=>$repairId])['status']===302,'repair removal works with CSRF');
    $r=$employee->post('actions/update_vehicle_field.php',['id'=>1,'field'=>'plate','value'=>'TEST-EDIT']); check($r['status']===200 && str_contains($r['body'],'true'),'employee AJAX edits work with CSRF');
    $r=$employee->request('actions/update_vehicle_field.php',['id'=>1,'field'=>'plate','value'=>'NO']); check($r['status']===403,'employee edits reject missing CSRF');
    // Authorized users can request reset mail without an administrator.
    $employee->request('public/request-password.php');
    $r=$employee->post('public/request-password.php',['emp_code'=>'001234']);
    check(str_contains($r['body'],'If this account is eligible'),'authorized reset request remains generic');
    // Reset revokes prior authenticated sessions and changes the credential.
     $replacementToken=mail_link('employee-'.$name.'@example.test'); check(consume_password_link($replacementToken,'Another unique woodland passphrase!'),'password reset works');
    check($employee->request('public/employee/index.php')['status']===302,'reset revokes existing sessions');
    $employee->request('public/login.php'); $r=$employee->post('actions/auth_login.php',['emp_code'=>'001234','password'=>$password]); check(str_contains($r['location'],'err=1'),'old password rejected after reset');
    // Disabling an account immediately revokes access, even if later reenabled.
    $b->post('actions/admin_toggle_verify.php',['id'=>2,'to'=>'0']);
    $employee->request('public/login.php'); $r=$employee->post('actions/auth_login.php',['emp_code'=>'001234','password'=>'Another unique woodland passphrase!']); check(str_contains($r['location'],'err=1'),'disabled account cannot log in');
    $anon = new Browser(); $anon->request('public/login.php');
    for ($i=0;$i<11;$i++) $r=$anon->post('actions/auth_login.php',['emp_code'=>'888888','password'=>'wrong']);
    check($r['status']===429,'login throttling enforced across repeated attempts');
    $b->post('public/logout.php', []); check($b->request('public/admin/index.php')['status']===302,'logout clears authentication');
    check(!str_contains(file_get_contents($log),'Fatal error'),'no PHP fatal errors in HTTP flows');
    echo "Completed $count assertions in isolated database.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // Delete only messages addressed to this run's unique test recipients.
    $messages = json_decode(file_get_contents('http://mailpit:8025/api/v1/messages?limit=1000'), true)['messages'] ?? [];
    $ids = [];
    foreach ($messages as $message) {
        $recipient = $message['To'][0]['Address'] ?? '';
        if (in_array($recipient, ['admin-'.$name.'@example.test','employee-'.$name.'@example.test','invited-'.$name.'@example.test','disabled-'.$name.'@example.test'], true)) $ids[] = $message['ID'];
    }
    if ($ids) file_get_contents('http://mailpit:8025/api/v1/messages', false, stream_context_create(['http'=>['method'=>'DELETE','header'=>'Content-Type: application/json','content'=>json_encode(['IDs'=>$ids])]]));
    // Only this run's generated test database is eligible for cleanup.
    if (preg_match('/^autotrackplus_auth_test_[a-f0-9]{10}$/D',$name)) $admin->exec("DROP DATABASE IF EXISTS `$name`");
}
