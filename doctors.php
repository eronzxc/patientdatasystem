<?php
/**
 * PATIENTDATAPROGRAM — doctors.php
 * Doctors & Departments Management (Admin only)
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['user_role'] !== 'Admin') { header('Location: dashboard.php'); exit; }
require_once 'db_connect.php';

$msg = $err = '';

// ─── HANDLE ACTIONS ──────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_POST['id']  ?? $_GET['id']  ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add_doctor') {
        $full_name   = trim($_POST['full_name']  ?? '');
        $prc_no      = trim($_POST['prc_no']     ?? '');
        $dept_id     = (int)($_POST['dept_id']   ?? 0);
        $specialization = trim($_POST['specialization'] ?? '');
        $contact     = trim($_POST['contact']    ?? '');
        if (!$full_name || !$dept_id) {
            $err = 'Full name and department are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO doctors (full_name, prc_no, department_id, specialization, contact_no, is_active, created_at) VALUES (?,?,?,?,?,1,NOW())");
            $stmt->execute([$full_name, $prc_no ?: null, $dept_id ?: null, $specialization ?: null, $contact ?: null]);
            $msg = "Doctor <strong>" . htmlspecialchars($full_name) . "</strong> added successfully.";
        }
    }

    elseif ($action === 'edit_doctor') {
        $full_name   = trim($_POST['full_name']  ?? '');
        $prc_no      = trim($_POST['prc_no']     ?? '');
        $dept_id     = (int)($_POST['dept_id']   ?? 0);
        $specialization = trim($_POST['specialization'] ?? '');
        $contact     = trim($_POST['contact']    ?? '');
        if (!$full_name || !$id) {
            $err = 'Invalid data.';
        } else {
            $stmt = $pdo->prepare("UPDATE doctors SET full_name=?, prc_no=?, department_id=?, specialization=?, contact_no=? WHERE id=?");
            $stmt->execute([$full_name, $prc_no ?: null, $dept_id ?: null, $specialization ?: null, $contact ?: null, $id]);
            $msg = "Doctor record updated.";
        }
    }

    elseif ($action === 'toggle_doctor') {
        $pdo->prepare("UPDATE doctors SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        $msg = "Doctor status updated.";
    }

    elseif ($action === 'add_dept') {
        $name = trim($_POST['dept_name'] ?? '');
        $code = strtoupper(trim($_POST['dept_code'] ?? ''));
        if (!$name || !$code) {
            $err = 'Department name and code are required.';
        } else {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE code=?");
            $exists->execute([$code]);
            if ($exists->fetchColumn() > 0) {
                $err = "Department code <strong>$code</strong> already exists.";
            } else {
                $pdo->prepare("INSERT INTO departments (name, code) VALUES (?,?)")->execute([$name, $code]);
                $msg = "Department <strong>" . htmlspecialchars($name) . "</strong> added.";
            }
        }
    }

    elseif ($action === 'edit_dept') {
        $name = trim($_POST['dept_name'] ?? '');
        $code = strtoupper(trim($_POST['dept_code'] ?? ''));
        if (!$name || !$code || !$id) {
            $err = 'Invalid data.';
        } else {
            $pdo->prepare("UPDATE departments SET name=?, code=? WHERE id=?")->execute([$name, $code, $id]);
            $msg = "Department updated.";
        }
    }

    elseif ($action === 'delete_dept') {
        $used = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE department_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $err = "Cannot delete: department still has assigned doctors.";
        } else {
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            $msg = "Department deleted.";
        }
    }

    if ($msg || $err) {
        // redirect with flash to avoid re-POST
        $_SESSION['flash_msg'] = $msg;
        $_SESSION['flash_err'] = $err;
        header('Location: doctors.php' . ($_GET['tab'] ? '?tab=' . htmlspecialchars($_GET['tab']) : ''));
        exit;
    }
}

// Read flash
if (!empty($_SESSION['flash_msg'])) { $msg = $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); }
if (!empty($_SESSION['flash_err'])) { $err = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }

$tab = $_GET['tab'] ?? 'doctors';

// ─── FETCH DATA ───────────────────────────────────────────
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$search    = trim($_GET['search'] ?? '');
$filter_dept = (int)($_GET['dept'] ?? 0);
$filter_status = $_GET['status'] ?? '';

$where = "WHERE 1";
$params = [];
if ($search) { $where .= " AND (dr.full_name LIKE ? OR dr.prc_no LIKE ? OR dr.specialization LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }
if ($filter_dept) { $where .= " AND dr.department_id=?"; $params[] = $filter_dept; }
if ($filter_status === 'active')   { $where .= " AND dr.is_active=1"; }
if ($filter_status === 'inactive') { $where .= " AND dr.is_active=0"; }

$doctors = $pdo->prepare("
    SELECT dr.*, d.name AS dept_name, d.code AS dept_code
    FROM doctors dr
    LEFT JOIN departments d ON d.id = dr.department_id
    $where
    ORDER BY dr.full_name ASC
");
$doctors->execute($params);
$doctors = $doctors->fetchAll();

$total_active   = $pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active=1")->fetchColumn();
$total_inactive = $pdo->query("SELECT COUNT(*) FROM doctors WHERE is_active=0")->fetchColumn();
$total_depts    = count($departments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — Doctors &amp; Departments</title>
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
/* TABS */
.tabs{display:flex;gap:2px;margin-bottom:18px;border-bottom:2px solid var(--border);}
.tab-btn{padding:9px 20px;font-size:12.5px;font-weight:600;color:var(--text-2);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .12s,border-color .12s;display:flex;align-items:center;gap:7px;}
.tab-btn:hover{color:var(--navy);}
.tab-btn.active{color:var(--emerald);border-bottom-color:var(--emerald);}
.tab-btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
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
.btn-blue{background:var(--blue);color:#fff;border-color:var(--blue);}
.btn-blue:hover{background:#1e6fc4;}
.btn-sm{padding:5px 10px;font-size:11.5px;}
.btn-ghost{background:transparent;color:var(--text-2);border-color:var(--border);}
.btn-ghost:hover{background:var(--surface);}
.btn-danger{background:var(--red-light);color:var(--red);border-color:var(--red-border);}
.btn-danger:hover{background:#fbddda;}
.btn svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* CARD / TABLE */
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.card-hdr{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--border);}
.card-hdr h2{font-size:12.5px;font-weight:700;color:var(--navy);}
.card-hdr svg{width:15px;height:15px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
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
/* DEPT CODE BADGE */
.dept-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;color:#fff;background:var(--navy-mid);}
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
/* DEPT TABLE extra */
.dept-actions{display:flex;gap:6px;}
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
  <a href="doctors.php" class="nav-item active">
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    Doctors &amp; Depts
  </a>
  <a href="users.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <div class="sb-footer">PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</div>
</aside>

<!-- ═══ MAIN ══════════════════════════════════════════════ -->
<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="tb-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
    <div class="tb-title">Doctors &amp; Departments</div>
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
        <h1>Doctors &amp; Departments</h1>
        <p>Manage attending physicians and hospital departments</p>
      </div>
    </div>

    <?php if($msg): ?><div class="alert alert-ok"><?=$msg?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-err"><?=$err?></div><?php endif; ?>

    <!-- STAT CHIPS -->
    <div class="info-row">
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--blue)">
          <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$total_depts?></div><div class="ic-lbl">Departments</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--emerald)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$total_active?></div><div class="ic-lbl">Active Doctors</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--text-3)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)$total_inactive?></div><div class="ic-lbl">Inactive Doctors</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--navy)">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div><div class="ic-val"><?=(int)($total_active+$total_inactive)?></div><div class="ic-lbl">Total Doctors</div></div>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
      <a href="?tab=doctors" class="tab-btn <?=$tab==='doctors'?'active':''?>">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Doctors
      </a>
      <a href="?tab=departments" class="tab-btn <?=$tab==='departments'?'active':''?>">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Departments
      </a>
    </div>

    <?php if($tab === 'doctors'): ?>
    <!-- ── DOCTORS TAB ──────────────────────────────────── -->
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="tab" value="doctors">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" placeholder="Search by name, PRC No., specialization…" value="<?=htmlspecialchars($search)?>">
        </div>
        <select name="dept" class="flt">
          <option value="">All Departments</option>
          <?php foreach($departments as $d): ?>
          <option value="<?=$d['id']?>" <?=$filter_dept==$d['id']?'selected':''?>><?=htmlspecialchars($d['name'])?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="flt">
          <option value="">All Status</option>
          <option value="active"   <?=$filter_status==='active'  ?'selected':''?>>Active</option>
          <option value="inactive" <?=$filter_status==='inactive'?'selected':''?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
        <?php if($search||$filter_dept||$filter_status): ?>
        <a href="?tab=doctors" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </form>
      <button class="btn btn-primary" onclick="openModal('modal-add-doctor')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Doctor
      </button>
    </div>

    <div class="card">
      <div class="card-hdr">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h2>Attending Physicians</h2>
        <span class="ch-right"><?=count($doctors)?> result<?=count($doctors)!==1?'s':''?></span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>PRC No.</th>
              <th>Specialization</th>
              <th>Department</th>
              <th>Contact</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($doctors)): ?>
          <tr><td colspan="8" class="no-data">No doctors found.</td></tr>
          <?php else: $i=1; foreach($doctors as $dr): ?>
          <tr>
            <td style="color:var(--text-3);font-size:11.5px"><?=$i++?></td>
            <td style="font-weight:600"><?=htmlspecialchars($dr['full_name'])?></td>
            <td style="font-family:var(--font-mono);font-size:11.5px;color:var(--text-2)"><?=htmlspecialchars($dr['prc_no']??'—')?></td>
            <td style="font-size:12px;color:var(--text-2)"><?=htmlspecialchars($dr['specialization']??'—')?></td>
            <td>
              <?php if($dr['dept_code']): ?>
              <span class="dept-badge"><?=htmlspecialchars($dr['dept_code'])?></span>
              <span style="font-size:11.5px;color:var(--text-2);margin-left:5px"><?=htmlspecialchars($dr['dept_name']??'')?></span>
              <?php else: ?>
              <span style="color:var(--text-3)">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-2)"><?=htmlspecialchars($dr['contact_no']??'—')?></td>
            <td>
              <span class="badge <?=$dr['is_active']?'b-active':'b-inactive'?>">
                <?=$dr['is_active']?'Active':'Inactive'?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:5px">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEditDoctor(<?=$dr['id']?>,<?=htmlspecialchars(json_encode($dr['full_name']))?>,<?=htmlspecialchars(json_encode($dr['prc_no']??''))?>,<?=(int)($dr['department_id']??0)?>,<?=htmlspecialchars(json_encode($dr['specialization']??''))?>,<?=htmlspecialchars(json_encode($dr['contact_no']??''))?>)">
                  Edit
                </button>
                <form method="POST" onsubmit="return confirm('Toggle status for this doctor?')">
                  <input type="hidden" name="action" value="toggle_doctor">
                  <input type="hidden" name="id" value="<?=$dr['id']?>">
                  <button type="submit" class="btn btn-sm <?=$dr['is_active']?'btn-danger':'btn-ghost'?>">
                    <?=$dr['is_active']?'Deactivate':'Activate'?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php else: ?>
    <!-- ── DEPARTMENTS TAB ─────────────────────────────── -->
    <div class="toolbar">
      <div style="flex:1"></div>
      <button class="btn btn-blue" onclick="openModal('modal-add-dept')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Department
      </button>
    </div>

    <div class="card">
      <div class="card-hdr">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <h2>Hospital Departments</h2>
        <span class="ch-right"><?=count($departments)?> department<?=count($departments)!==1?'s':''?></span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Code</th>
              <th>Department Name</th>
              <th>Doctors Assigned</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if(empty($departments)): ?>
          <tr><td colspan="5" class="no-data">No departments yet.</td></tr>
          <?php else: $i=1; foreach($departments as $d):
            $dc = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE department_id=?");
            $dc->execute([$d['id']]);
            $dc_cnt = $dc->fetchColumn();
          ?>
          <tr>
            <td style="color:var(--text-3);font-size:11.5px"><?=$i++?></td>
            <td><span class="dept-badge"><?=htmlspecialchars($d['code'])?></span></td>
            <td style="font-weight:600"><?=htmlspecialchars($d['name'])?></td>
            <td style="font-size:12px;color:var(--text-2)"><?=(int)$dc_cnt?> doctor<?=$dc_cnt!=1?'s':''?></td>
            <td>
              <div class="dept-actions">
                <button class="btn btn-ghost btn-sm"
                  onclick="openEditDept(<?=$d['id']?>,<?=htmlspecialchars(json_encode($d['name']))?>,<?=htmlspecialchars(json_encode($d['code']))?>)">
                  Edit
                </button>
                <form method="POST" onsubmit="return confirm('Delete this department? Make sure no doctors are assigned.')">
                  <input type="hidden" name="action" value="delete_dept">
                  <input type="hidden" name="id" value="<?=$d['id']?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<!-- ═══ MODAL: ADD DOCTOR ════════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-add-doctor">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Add New Doctor</h3>
      <button class="modal-close" onclick="closeModal('modal-add-doctor')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_doctor">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="Dr. Juan Dela Cruz" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">PRC License No.</label>
            <input type="text" name="prc_no" class="form-control" placeholder="e.g. 0123456">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact" class="form-control" placeholder="09XX-XXX-XXXX">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Specialization</label>
          <input type="text" name="specialization" class="form-control" placeholder="e.g. Cardiology, Internal Medicine">
        </div>
        <div class="form-group">
          <label class="form-label">Department <span class="req">*</span></label>
          <select name="dept_id" class="form-control" required>
            <option value="">— Select Department —</option>
            <?php foreach($departments as $d): ?>
            <option value="<?=$d['id']?>">[<?=htmlspecialchars($d['code'])?>] <?=htmlspecialchars($d['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-doctor')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Doctor</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: EDIT DOCTOR ═══════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-edit-doctor">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Edit Doctor</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-doctor')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" id="form-edit-doctor">
      <input type="hidden" name="action" value="edit_doctor">
      <input type="hidden" name="id" id="edit-doc-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" id="edit-doc-name" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">PRC License No.</label>
            <input type="text" name="prc_no" id="edit-doc-prc" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact" id="edit-doc-contact" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Specialization</label>
          <input type="text" name="specialization" id="edit-doc-spec" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Department</label>
          <select name="dept_id" id="edit-doc-dept" class="form-control">
            <option value="">— Select Department —</option>
            <?php foreach($departments as $d): ?>
            <option value="<?=$d['id']?>">[<?=htmlspecialchars($d['code'])?>] <?=htmlspecialchars($d['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-doctor')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: ADD DEPT ══════════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-add-dept">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Add Department</h3>
      <button class="modal-close" onclick="closeModal('modal-add-dept')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_dept">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Department Code <span class="req">*</span></label>
            <input type="text" name="dept_code" class="form-control" placeholder="e.g. CARDIO" maxlength="10" style="text-transform:uppercase" required>
          </div>
          <div class="form-group">
            <label class="form-label">Department Name <span class="req">*</span></label>
            <input type="text" name="dept_name" class="form-control" placeholder="e.g. Cardiology" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-add-dept')">Cancel</button>
        <button type="submit" class="btn btn-blue">Add Department</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: EDIT DEPT ═════════════════════════════════ -->
<div class="modal-overlay hidden" id="modal-edit-dept">
  <div class="modal">
    <div class="modal-hdr">
      <h3>Edit Department</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-dept')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form method="POST" id="form-edit-dept">
      <input type="hidden" name="action" value="edit_dept">
      <input type="hidden" name="id" id="edit-dept-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Department Code <span class="req">*</span></label>
            <input type="text" name="dept_code" id="edit-dept-code" class="form-control" maxlength="10" style="text-transform:uppercase" required>
          </div>
          <div class="form-group">
            <label class="form-label">Department Name <span class="req">*</span></label>
            <input type="text" name="dept_name" id="edit-dept-name" class="form-control" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-dept')">Cancel</button>
        <button type="submit" class="btn btn-blue">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click',e=>{ if(e.target===o) o.classList.add('hidden'); });
});

function openEditDoctor(id, name, prc, deptId, spec, contact){
  document.getElementById('edit-doc-id').value      = id;
  document.getElementById('edit-doc-name').value    = name;
  document.getElementById('edit-doc-prc').value     = prc;
  document.getElementById('edit-doc-spec').value    = spec;
  document.getElementById('edit-doc-contact').value = contact;
  const sel = document.getElementById('edit-doc-dept');
  sel.value = deptId || '';
  openModal('modal-edit-doctor');
}

function openEditDept(id, name, code){
  document.getElementById('edit-dept-id').value   = id;
  document.getElementById('edit-dept-name').value = name;
  document.getElementById('edit-dept-code').value = code;
  openModal('modal-edit-dept');
}

// Auto-uppercase dept code
document.querySelectorAll('input[name="dept_code"]').forEach(el=>{
  el.addEventListener('input', ()=>{ el.value = el.value.toUpperCase(); });
});
</script>
</body>
</html>
