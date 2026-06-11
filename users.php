<?php
/**
 * PATIENTDATAPROGRAM — users.php
 * User Account Management (Admin only)
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['user_role'] !== 'Admin') { header('Location: dashboard.php'); exit; }
require_once 'db_connect.php';

$msg = $err = '';

// ─── HANDLE ACTIONS ──────────────────────────────────────
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add_user') {
        $username  = trim($_POST['username']  ?? '');
        $fullname  = trim($_POST['fullname']  ?? '');
        $password  = $_POST['password']       ?? '';
        $role      = $_POST['role']           ?? '';
        $email     = trim($_POST['email']     ?? '');
        $valid_roles = ['Admin','Encoder','Nurse','Read-Only'];

        if (!$username || !$fullname || !$password || !in_array($role, $valid_roles)) {
            $err = 'Username, full name, password, and a valid role are required.';
        } elseif (strlen($password) < 8) {
            $err = 'Password must be at least 8 characters.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
            $chk->execute([$username]);
            if ($chk->fetchColumn() > 0) {
                $err = "Username <strong>" . htmlspecialchars($username) . "</strong> is already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, full_name, password, role, email, is_active, created_at) VALUES (?,?,?,?,?,1,NOW())")
                    ->execute([$username, $fullname, $hash, $role, $email ?: null]);
                $msg = "User <strong>" . htmlspecialchars($username) . "</strong> created successfully.";
            }
        }
    }

    elseif ($action === 'edit_user') {
        $fullname  = trim($_POST['fullname']  ?? '');
        $role      = $_POST['role']           ?? '';
        $email     = trim($_POST['email']     ?? '');
        $valid_roles = ['Admin','Encoder','Nurse','Read-Only'];
        if (!$fullname || !in_array($role, $valid_roles) || !$id) {
            $err = 'Invalid data.';
        } elseif ($id == $_SESSION['user_id'] && $role !== 'Admin') {
            $err = 'You cannot remove your own Admin role.';
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, role=?, email=? WHERE id=?")
                ->execute([$fullname, $role, $email ?: null, $id]);
            $msg = "User updated.";
        }
    }

    elseif ($action === 'reset_password') {
        $new_pass  = $_POST['new_password']   ?? '';
        if (strlen($new_pass) < 8) {
            $err = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
            $msg = "Password reset successfully.";
        }
    }

    elseif ($action === 'toggle_user') {
        if ($id == $_SESSION['user_id']) {
            $err = 'You cannot deactivate your own account.';
        } else {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            $msg = "User status updated.";
        }
    }

    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_err'] = $err;
    header('Location: users.php');
    exit;
}

if (!empty($_SESSION['flash_msg'])) { $msg = $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); }
if (!empty($_SESSION['flash_err'])) { $err = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }

// ─── FETCH ────────────────────────────────────────────────
$search       = trim($_GET['search']  ?? '');
$filter_role  = $_GET['role']         ?? '';
$filter_status= $_GET['status']       ?? '';

$where  = "WHERE 1";
$params = [];
if ($search) {
    $where .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $s = "%$search%";
    $params = [$s,$s,$s];
}
if ($filter_role)   { $where .= " AND role=?";      $params[] = $filter_role; }
if ($filter_status === 'active')   { $where .= " AND is_active=1"; }
if ($filter_status === 'inactive') { $where .= " AND is_active=0"; }

$users_stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$users_stmt->execute($params);
$users = $users_stmt->fetchAll();

$count_total    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$count_active   = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$count_admin    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='Admin' AND is_active=1")->fetchColumn();
$count_staff    = $pdo->query("SELECT COUNT(*) FROM users WHERE role!='Admin' AND is_active=1")->fetchColumn();

$role_colors = ['Admin'=>'--navy','Encoder'=>'--emerald','Nurse'=>'--blue','Read-Only'=>'--teal'];
$role_lights = ['Admin'=>'--surface','Encoder'=>'--emerald-light','Nurse'=>'--blue-light','Read-Only'=>'--teal-light'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — User Management</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --navy:#0D2137;--navy-mid:#163352;--navy-hover:#1E4470;
    --emerald:#1A7F5A;--emerald-mid:#22A872;--emerald-light:#E8F5F0;--emerald-border:rgba(26,127,90,.25);
    --gold:#B8820A;--gold-light:#FDF3E3;--gold-border:rgba(184,130,10,.25);
    --red:#C0392B;--red-light:#FDECEA;--red-border:rgba(192,57,43,.25);
    --blue:#1A5BA8;--blue-light:#EBF2FC;--blue-border:rgba(26,91,168,.25);
    --teal:#0E7B8E;--teal-light:#E6F5F7;--teal-border:rgba(14,123,142,.25);
    --purple:#7B2D9E;--purple-light:#F5EBF9;--purple-border:rgba(123,45,158,.2);
    --surface:#F4F7FA;--white:#fff;--border:#DDE3EC;--border-mid:#C4CDD9;
    --text-1:#0D1F35;--text-2:#4A607A;--text-3:#8FA3BA;
    --sidebar-w:248px;--header-h:54px;
    --font:'Segoe UI',system-ui,sans-serif;--font-mono:'Consolas','Courier New',monospace;
    --radius:9px;--radius-sm:5px;
}
html,body{height:100%;font-family:var(--font);font-size:14px;background:var(--surface);color:var(--text-1);}
.app{display:flex;height:100vh;overflow:hidden;}
/* SIDEBAR */
.sidebar{width:var(--sidebar-w);min-width:var(--sidebar-w);background:var(--navy);display:flex;flex-direction:column;overflow-y:auto;box-shadow:2px 0 16px rgba(0,0,0,.18);}
.sb-brand{padding:0 16px;height:var(--header-h);display:flex;align-items:center;gap:11px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;}
.sb-logo{width:34px;height:34px;background:var(--emerald);border-radius:8px;display:flex;align-items:center;justify-content:center;}
.sb-logo svg{width:18px;height:18px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.sb-name{font-size:10px;font-weight:800;color:#fff;letter-spacing:.08em;}
.sb-sub{font-size:9px;color:rgba(255,255,255,.35);margin-top:3px;}
.nav-label{padding:14px 16px 5px;font-size:9px;font-weight:700;letter-spacing:.14em;color:rgba(255,255,255,.3);text-transform:uppercase;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 16px;color:rgba(255,255,255,.6);font-size:12.5px;text-decoration:none;border-left:3px solid transparent;transition:background .12s,color .12s;cursor:pointer;}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
.nav-item.active{background:rgba(26,127,90,.18);color:#fff;border-left-color:var(--emerald-mid);font-weight:600;}
.nav-item svg{width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.sb-footer{margin-top:auto;padding:12px 16px;font-size:9.5px;color:rgba(255,255,255,.2);border-top:1px solid rgba(255,255,255,.07);}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;}
.topbar{height:var(--header-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 22px;gap:10px;flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.tb-icon svg{width:16px;height:16px;fill:none;stroke:var(--navy);stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.tb-title{font-size:14px;font-weight:700;color:var(--text-1);}
.tb-bc{font-size:12px;color:var(--text-3);}
.tb-right{margin-left:auto;display:flex;align-items:center;gap:14px;}
.pill{display:flex;align-items:center;gap:6px;background:var(--emerald-light);border:1px solid var(--emerald-border);border-radius:99px;padding:4px 12px;}
.pill-dot{width:7px;height:7px;background:var(--emerald);border-radius:50%;}
.pill span{font-size:11px;font-weight:600;color:var(--emerald);}
.tb-user{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-2);}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;}
.content{flex:1;overflow-y:auto;padding:22px;}
/* PAGE HEADER */
.page-hdr{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.page-hdr h1{font-size:19px;font-weight:800;color:var(--navy);}
.page-hdr p{font-size:12px;color:var(--text-3);margin-top:3px;}
/* ALERT */
.alert{padding:11px 16px;border-radius:var(--radius-sm);font-size:12.5px;margin-bottom:16px;border:1px solid;}
.alert-ok{background:var(--emerald-light);color:var(--emerald);border-color:var(--emerald-border);}
.alert-err{background:var(--red-light);color:var(--red);border-color:var(--red-border);}
/* STAT CHIPS */
.info-row{display:flex;gap:11px;margin-bottom:18px;}
.info-chip{flex:1;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:13px 16px;display:flex;align-items:center;gap:12px;}
.ic-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ic-icon svg{width:16px;height:16px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.ic-val{font-size:20px;font-weight:800;color:var(--navy);line-height:1;}
.ic-lbl{font-size:10.5px;color:var(--text-3);margin-top:2px;}
/* TOOLBAR */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.search-box{position:relative;flex:1;min-width:200px;}
.search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;fill:none;stroke:var(--text-3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}
.search-box input{width:100%;padding:8px 10px 8px 32px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12.5px;font-family:var(--font);color:var(--text-1);background:var(--white);outline:none;}
.search-box input:focus{border-color:var(--emerald);}
select.flt{padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12.5px;font-family:var(--font);color:var(--text-1);background:var(--white);outline:none;cursor:pointer;}
select.flt:focus{border-color:var(--emerald);}
/* BTN */
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:600;font-family:var(--font);cursor:pointer;border:1px solid transparent;transition:background .12s,box-shadow .12s;text-decoration:none;}
.btn-primary{background:var(--emerald);color:#fff;border-color:var(--emerald);}
.btn-primary:hover{background:var(--emerald-mid);}
.btn-purple{background:var(--purple);color:#fff;border-color:var(--purple);}
.btn-purple:hover{background:#8d36b4;}
.btn-sm{padding:5px 10px;font-size:11.5px;}
.btn-ghost{background:transparent;color:var(--text-2);border-color:var(--border);}
.btn-ghost:hover{background:var(--surface);}
.btn-danger{background:var(--red-light);color:var(--red);border-color:var(--red-border);}
.btn-danger:hover{background:#fbddda;}
.btn-warn{background:var(--gold-light);color:var(--gold);border-color:var(--gold-border);}
.btn-warn:hover{background:#fce9c0;}
.btn svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* CARD / TABLE */
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.card-hdr{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--border);}
.card-hdr h2{font-size:12.5px;font-weight:700;color:var(--navy);}
.card-hdr svg{width:15px;height:15px;fill:none;stroke:var(--purple);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.ch-right{margin-left:auto;font-size:11.5px;color:var(--text-3);}
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);background:var(--surface);border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:10px 14px;font-size:12.5px;color:var(--text-1);border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#F8FAFB;}
.no-data{text-align:center;padding:36px;color:var(--text-3);font-size:13px;}
/* BADGES */
.badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:10.5px;font-weight:700;}
.b-active{background:var(--emerald-light);color:var(--emerald);border:1px solid var(--emerald-border);}
.b-inactive{background:var(--surface);color:var(--text-3);border:1px solid var(--border);}
.b-admin{background:var(--navy);color:#fff;}
.b-encoder{background:var(--emerald-light);color:var(--emerald);border:1px solid var(--emerald-border);}
.b-nurse{background:var(--blue-light);color:var(--blue);border:1px solid var(--blue-border);}
.b-readonly{background:var(--teal-light);color:var(--teal);border:1px solid var(--teal-border);}
/* YOU badge */
.you-chip{display:inline-block;font-size:9px;font-weight:700;color:var(--gold);background:var(--gold-light);border:1px solid var(--gold-border);border-radius:99px;padding:1px 6px;margin-left:5px;vertical-align:middle;}
/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.hidden{display:none;}
.modal{background:var(--white);border-radius:var(--radius);width:100%;max-width:480px;box-shadow:0 8px 40px rgba(0,0,0,.18);}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);}
.modal-hdr h3{font-size:14px;font-weight:700;color:var(--navy);}
.modal-close{background:none;border:none;cursor:pointer;color:var(--text-3);padding:4px;border-radius:4px;display:flex;}
.modal-close:hover{color:var(--text-1);background:var(--surface);}
.modal-close svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.modal-body{padding:20px;}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border);}
/* FORM */
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:11.5px;font-weight:600;color:var(--text-2);margin-bottom:5px;}
.form-label span.req{color:var(--red);}
.form-control{width:100%;padding:8px 11px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:13px;font-family:var(--font);color:var(--text-1);background:var(--white);outline:none;transition:border-color .15s;}
.form-control:focus{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(26,127,90,.1);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.hint{font-size:10.5px;color:var(--text-3);margin-top:4px;}
/* ROLE LEGEND */
.role-legend{display:flex;gap:10px;flex-wrap:wrap;padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface);}
.rl-item{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-2);}
</style>
</head>
<body>
<div class="app">

<!-- ═══ SIDEBAR ══════════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
    <div>
      <div class="sb-name">PATIENTDATAPROGRAM</div>
      <div class="sb-sub">Patient Registry System</div>
    </div>
  </div>
  <div class="nav-label">Main</div>
  <a href="dashboard.php" class="nav-item">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <div class="nav-label">Patient Records</div>
  <a href="index.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    Patient Registry
  </a>
  <a href="register.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
    Register Patient
  </a>
  <div class="nav-label">Administration</div>
  <a href="doctors.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    Doctors &amp; Depts
  </a>
  <a href="users.php" class="nav-item active">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <div class="sb-footer">PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</div>
</aside>

<!-- ═══ MAIN ══════════════════════════════════════════════ -->
<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="tb-icon">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div class="tb-title">User Management</div>
    <div class="tb-bc">/ Administration</div>
    <div class="tb-right">
      <div class="pill"><div class="pill-dot"></div><span>System Online</span></div>
      <div class="tb-user">
        <div class="avatar"><?=strtoupper(substr($_SESSION['username']??'A',0,2))?></div>
        <span><?=htmlspecialchars($_SESSION['username']??'Admin')?></span>
        <a href="logout.php" style="font-size:11.5px;color:var(--red);text-decoration:none;margin-left:4px;">Logout</a>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <div class="page-hdr">
      <div>
        <h1>User Management</h1>
        <p>Create and manage system user accounts — Admin access only</p>
      </div>
    </div>

    <?php if($msg): ?><div class="alert alert-ok"><?=$msg?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-err"><?=$err?></div><?php endif; ?>

    <!-- STAT CHIPS -->
    <div class="info-row">
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--navy)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$count_total?></div><div class="ic-lbl">Total Users</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--emerald)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$count_active?></div><div class="ic-lbl">Active Users</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--purple)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M12 14c-5 0-8 2-8 3v1h16v-1c0-1-3-3-8-3z"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$count_admin?></div><div class="ic-lbl">Admins</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--blue)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$count_staff?></div><div class="ic-lbl">Staff Users</div></div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" placeholder="Search by username, name, or email…" value="<?=htmlspecialchars($search)?>">
        </div>
        <select name="role" class="flt">
          <option value="">All Roles</option>
          <option value="Admin"     <?=$filter_role==='Admin'    ?'selected':''?>>Admin</option>
          <option value="Encoder"   <?=$filter_role==='Encoder'  ?'selected':''?>>Encoder</option>
          <option value="Nurse"     <?=$filter_role==='Nurse'    ?'selected':''?>>Nurse</option>
          <option value="Read-Only" <?=$filter_role==='Read-Only'?'selected':''?>>Read-Only</option>
        </select>
        <select name="status" class="flt">
          <option value="">All Status</option>
          <option value="active"   <?=$filter_status==='active'  ?'selected':''?>>Active</option>
          <option value="inactive" <?=$filter_status==='inactive'?'selected':''?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
        <?php if($search||$filter_role||$filter_status): ?>
        <a href="users.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </form>
      <button class="btn btn-purple" onclick="openModal('modal-add-user')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add User
      </button>
    </div>

    <!-- TABLE -->
    <div class="card">
      <!-- Role Legend -->
      <div class="role-legend">
        <span style="font-size:11px;font-weight:700;color:var(--text-3);margin-right:4px">ROLES:</span>
        <div class="rl-item"><span class="badge b-admin">Admin</span> Full access</div>
        <div class="rl-item"><span class="badge b-encoder">Encoder</span> Register &amp; edit patients</div>
        <div class="rl-item"><span class="badge b-nurse">Nurse</span> View &amp; update records</div>
        <div class="rl-item"><span class="badge b-readonly">Read-Only</span> View only</div>
      </div>
      <div class="card-hdr">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <h2>System Users</h2>
        <span class="ch-right"><?=count($users)?> result<?=count($users)!==1?'s':''?></span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Username</th>
              <th>Full Name</th>
              <th>Role</th>
              <th>Email</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($users)): ?>
          <tr><td colspan="8" class="no-data">No users found.</td></tr>
          <?php else: $i=1; foreach($users as $u):
            $role_badge = [
                'Admin'     => 'b-admin',
                'Encoder'   => 'b-encoder',
                'Nurse'     => 'b-nurse',
                'Read-Only' => 'b-readonly',
            ][$u['role']] ?? 'b-readonly';
            $is_me = ($u['id'] == $_SESSION['user_id']);
          ?>
          <tr>
            <td style="color:var(--text-3);font-size:11.5px"><?=$i++?></td>
            <td style="font-family:var(--font-mono);font-size:12px;font-weight:600">
              <?=htmlspecialchars($u['username'])?>
              <?php if($is_me): ?><span class="you-chip">YOU</span><?php endif; ?>
            </td>
            <td style="font-weight:600"><?=htmlspecialchars($u['full_name'])?></td>
            <td><span class="badge <?=$role_badge?>"><?=htmlspecialchars($u['role'])?></span></td>
            <td style="font-size:12px;color:var(--text-2)"><?=htmlspecialchars($u['email']??'—')?></td>
            <td><span class="badge <?=$u['is_active']?'b-active':'b-inactive'?>"><?=$u['is_active']?'Active':'Inactive'?></span></td>
            <td style="font-size:11.5px;color:var(--text-3);white-space:nowrap">
              <?=date('M j, Y', strtotime($u['created_at']))?>
            </td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEditUser(<?=$u['id']?>,<?=htmlspecialchars(json_encode($u['full_name']))?>,<?=htmlspecialchars(json_encode($u['role']))?>,<?=htmlspecialchars(json_encode($u['email']??''))?>)">
                  Edit
                </button>
                <button class="btn btn-warn btn-sm"
                  onclick="openResetPwd(<?=$u['id']?>,<?=htmlspecialchars(json_encode($u['username']))?>)">
                  Reset PW
                </button>
                <?php if(!$is_me): ?>
                <form method="POST" onsubmit="return confirm('Toggle status for this user?')">
                  <input type="hidden" name="action" value="toggle_user">
                  <input type="hidden" name="id" value="<?=$u['id']?>">
                  <button type="submit" class="btn btn-sm <?=$u['is_active']?'btn-danger':'btn-ghost'?>">
                    <?=$u['is_active']?'Deactivate':'Activate'?>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<!-- ═══ MODAL: ADD USER ══════════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-add-user">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Add New User</h3>
      <button class="modal-close" onclick="closeModal('modal-add-user')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="e.g. jdelacruz" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="req">*</span></label>
            <select name="role" class="form-control" required>
              <option value="">— Select Role —</option>
              <option value="Admin">Admin</option>
              <option value="Encoder">Encoder</option>
              <option value="Nurse">Nurse</option>
              <option value="Read-Only">Read-Only</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="fullname" class="form-control" placeholder="Juan Dela Cruz" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="email@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Password <span class="req">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required autocomplete="new-password">
          <div class="hint">Minimum 8 characters. The user should change this on first login.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-user')">Cancel</button>
        <button type="submit" class="btn btn-purple">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: EDIT USER ═════════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-edit-user">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Edit User</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="id" id="edit-user-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="fullname" id="edit-user-fullname" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role <span class="req">*</span></label>
          <select name="role" id="edit-user-role" class="form-control" required>
            <option value="Admin">Admin</option>
            <option value="Encoder">Encoder</option>
            <option value="Nurse">Nurse</option>
            <option value="Read-Only">Read-Only</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" id="edit-user-email" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-user')">Cancel</button>
        <button type="submit" class="btn btn-purple">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: RESET PASSWORD ════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-reset-pwd">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Reset Password</h3>
      <button class="modal-close" onclick="closeModal('modal-reset-pwd')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" id="reset-user-id">
      <div class="modal-body">
        <p style="font-size:12.5px;color:var(--text-2);margin-bottom:14px">
          Setting a new password for: <strong id="reset-user-name" style="color:var(--navy)"></strong>
        </p>
        <div class="form-group">
          <label class="form-label">New Password <span class="req">*</span></label>
          <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required autocomplete="new-password">
          <div class="hint">Inform the user of their new password after resetting.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-reset-pwd')">Cancel</button>
        <button type="submit" class="btn btn-warn">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click',e=>{ if(e.target===o) o.classList.add('hidden'); });
});

function openEditUser(id, fullname, role, email){
  document.getElementById('edit-user-id').value       = id;
  document.getElementById('edit-user-fullname').value = fullname;
  document.getElementById('edit-user-email').value    = email;
  document.getElementById('edit-user-role').value     = role;
  openModal('modal-edit-user');
}

function openResetPwd(id, username){
  document.getElementById('reset-user-id').value   = id;
  document.getElementById('reset-user-name').textContent = username;
  openModal('modal-reset-pwd');
}
</script>
</body>
</html>
