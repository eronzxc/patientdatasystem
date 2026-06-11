<?php
/**
 * PATIENTDATAPROGRAM — login.php
 */
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u && $p) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$u]);
        $user = $stmt->fetch();
        if ($user && password_verify($p, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username']  = $user['username'];
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            header('Location: dashboard.php'); exit;
        } else { $error = 'Invalid username or password.'; }
    } else { $error = 'Please enter your username and password.'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — Login</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --navy:#0D2137;--emerald:#1A7F5A;--emerald-mid:#22A872;--emerald-light:#E8F5F0;--emerald-border:rgba(26,127,90,.25);
    --red:#C0392B;--red-light:#FDECEA;--red-border:rgba(192,57,43,.25);
    --surface:#EEF2F7;--white:#fff;--border:#DDE3EC;--border-mid:#C4CDD9;
    --text-1:#0D1F35;--text-2:#4A607A;--text-3:#8FA3BA;
    --font:'Segoe UI',system-ui,sans-serif;--radius:10px;--radius-sm:6px;
}
html,body{height:100%;font-family:var(--font);background:var(--surface);display:flex;align-items:center;justify-content:center;}
.wrap{width:100%;max-width:420px;padding:20px;}
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 6px 32px rgba(13,33,55,.12);overflow:hidden;}
.card-head{background:var(--navy);padding:32px;text-align:center;}
.logo{width:50px;height:50px;background:var(--emerald);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;}
.logo svg{width:24px;height:24px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.sys-name{font-size:15px;font-weight:800;color:#fff;letter-spacing:.06em;}
.sys-sub{font-size:11px;color:rgba(255,255,255,.4);margin-top:4px;}
.card-body{padding:30px 32px 34px;}
.title{font-size:18px;font-weight:700;color:var(--text-1);}
.sub{font-size:12px;color:var(--text-3);margin-top:3px;margin-bottom:22px;}
.flash{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:500;margin-bottom:18px;background:var(--red-light);border:1px solid var(--red-border);color:var(--red);}
.flash svg{width:14px;height:14px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:15px;}
label{font-size:11.5px;font-weight:600;color:var(--text-2);}
input{padding:9px 12px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:13.5px;font-family:var(--font);color:var(--text-1);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;}
input:focus{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(26,127,90,.12);background:var(--white);}
input::placeholder{color:var(--text-3);}
.pw-wrap{position:relative;}
.pw-wrap input{width:100%;padding-right:42px;}
.toggle-pw{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-3);display:flex;align-items:center;padding:4px;}
.toggle-pw svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.btn-login{width:100%;padding:12px;background:var(--emerald);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:700;font-family:var(--font);cursor:pointer;margin-top:6px;transition:background .15s,box-shadow .15s;}
.btn-login:hover{background:var(--emerald-mid);box-shadow:0 4px 14px rgba(26,127,90,.28);}
.footer{text-align:center;font-size:11px;color:var(--text-3);margin-top:18px;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="card-head">
      <div class="logo"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div class="sys-name">PATIENTDATAPROGRAM</div>
      <div class="sys-sub">Patient Registry &amp; Information System</div>
    </div>
    <div class="card-body">
      <div class="title">Welcome back</div>
      <div class="sub">Sign in to access the patient registry.</div>
      <?php if($error):?><div class="flash"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?=htmlspecialchars($error)?></div><?php endif;?>
      <form method="POST">
        <div class="fg"><label>Username</label><input type="text" name="username" value="<?=htmlspecialchars($_POST['username']??'')?>" placeholder="Enter username" autocomplete="username" required autofocus></div>
        <div class="fg"><label>Password</label>
          <div class="pw-wrap">
            <input type="password" id="pw" name="password" placeholder="Enter password" autocomplete="current-password" required>
            <button type="button" class="toggle-pw" onclick="togglePw()"><svg id="eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
      </form>
    </div>
  </div>
  <div class="footer">PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</div>
</div>
<script>
function togglePw(){const i=document.getElementById('pw'),e=document.getElementById('eye');if(i.type==='password'){i.type='text';e.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';}else{i.type='password';e.innerHTML='<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';}}
</script>
</body>
</html>
