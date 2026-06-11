<?php
/**
 * PATIENTDATAPROGRAM — dashboard.php
 * Overview & Summary Dashboard
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db_connect.php';

$role = $_SESSION['user_role'];

// ─── STATS ───────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(patient_type='Outpatient')  AS outpatient,
        SUM(patient_type='Inpatient')   AS inpatient,
        SUM(patient_type='Emergency')   AS emergency,
        SUM(DATE(registered_at)=CURDATE()) AS today,
        SUM(YEARWEEK(registered_at,1)=YEARWEEK(CURDATE(),1)) AS this_week,
        SUM(MONTH(registered_at)=MONTH(CURDATE()) AND YEAR(registered_at)=YEAR(CURDATE())) AS this_month
    FROM patients
")->fetch();

// ─── DEPT BREAKDOWN ──────────────────────────────────────
$dept_stats = $pdo->query("
    SELECT d.name, d.code, COUNT(p.id) AS cnt
    FROM departments d
    LEFT JOIN patients p ON p.department_id = d.id
    GROUP BY d.id
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll();

// ─── HMO BREAKDOWN ───────────────────────────────────────
$hmo_stats = $pdo->query("
    SELECT h.short_name AS name, COUNT(p.id) AS cnt
    FROM hmo_providers h
    LEFT JOIN patients p ON p.hmo_id = h.id
    GROUP BY h.id
    ORDER BY cnt DESC
    LIMIT 6
")->fetchAll();

// ─── RECENT PATIENTS ─────────────────────────────────────
$recent = $pdo->query("
    SELECT p.patient_no, p.full_name, p.patient_type, p.sex,
           p.registered_at, d.name AS dept_name, dr.full_name AS doctor_name
    FROM patients p
    LEFT JOIN departments d  ON d.id  = p.department_id
    LEFT JOIN doctors dr     ON dr.id = p.doctor_id
    ORDER BY p.registered_at DESC
    LIMIT 10
")->fetchAll();

// ─── DOCTOR COUNT ────────────────────────────────────────
$doc_count  = $pdo->query("SELECT COUNT(*) FROM doctors  WHERE is_active=1")->fetchColumn();
$dept_count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$user_count = $pdo->query("SELECT COUNT(*) FROM users    WHERE is_active=1")->fetchColumn();

// ─── DAILY TREND (last 7 days) ───────────────────────────
$trend = $pdo->query("
    SELECT DATE(registered_at) AS day, COUNT(*) AS cnt
    FROM patients
    WHERE registered_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(registered_at)
    ORDER BY day ASC
")->fetchAll();

// Fill missing days
$trend_map = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trend_map[$d] = 0;
}
foreach ($trend as $t) $trend_map[$t['day']] = (int)$t['cnt'];
$trend_labels = array_map(fn($d) => date('D', strtotime($d)), array_keys($trend_map));
$trend_values = array_values($trend_map);
$trend_max    = max(max($trend_values), 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — Dashboard</title>
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
/* WELCOME BANNER */
.welcome-banner{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 60%,var(--emerald) 100%);border-radius:var(--radius);padding:22px 28px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
.wb-left h2{font-size:17px;font-weight:800;color:#fff;margin-bottom:4px;}
.wb-left p{font-size:12px;color:rgba(255,255,255,.55);}
.wb-right{display:flex;gap:10px;flex-shrink:0;}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:600;font-family:var(--font);cursor:pointer;border:1px solid transparent;transition:background .12s,box-shadow .12s;text-decoration:none;}
.btn-white{background:#fff;color:var(--navy);}
.btn-white:hover{background:#f0f4f8;}
.btn-outline-white{background:transparent;color:#fff;border-color:rgba(255,255,255,.4);}
.btn-outline-white:hover{background:rgba(255,255,255,.1);}
.btn svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* STAT GRID */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-bottom:18px;}
.sc{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;border-top:3px solid var(--border);}
.sc.s-total{border-top-color:var(--navy);}
.sc.s-out{border-top-color:var(--emerald);}
.sc.s-in{border-top-color:var(--blue);}
.sc.s-emr{border-top-color:var(--red);}
.sc.s-today{border-top-color:var(--gold);}
.sc.s-week{border-top-color:var(--teal);}
.sc.s-doc{border-top-color:var(--purple);}
.sc.s-month{border-top-color:var(--navy-mid);}
.sc-lbl{font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text-3);}
.sc-val{font-size:26px;font-weight:800;line-height:1;margin-top:5px;}
.sc-sub{font-size:10.5px;color:var(--text-3);margin-top:4px;}
.s-total .sc-val{color:var(--navy);}
.s-out   .sc-val{color:var(--emerald);}
.s-in    .sc-val{color:var(--blue);}
.s-emr   .sc-val{color:var(--red);}
.s-today .sc-val{color:var(--gold);}
.s-week  .sc-val{color:var(--teal);}
.s-doc   .sc-val{color:var(--purple);}
.s-month .sc-val{color:var(--navy-mid);}
/* 2-COL LAYOUT */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
.three-col{display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:14px;}
/* CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;height:100%;}
.card-hdr{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--border);}
.card-hdr h2{font-size:12.5px;font-weight:700;color:var(--navy);}
.card-hdr svg{width:15px;height:15px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.ch-right{margin-left:auto;font-size:11px;color:var(--text-3);}
.ch-link{margin-left:auto;font-size:11px;color:var(--emerald);text-decoration:none;font-weight:600;}
.ch-link:hover{text-decoration:underline;}
.card-body{padding:14px 18px;}
/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);background:var(--surface);border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:10px 14px;font-size:12.5px;color:var(--text-1);border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#F8FAFB;}
.no-data{text-align:center;padding:30px;color:var(--text-3);font-size:13px;}
/* BADGES */
.badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:10.5px;font-weight:700;}
.b-out{background:var(--emerald-light);color:var(--emerald);border:1px solid var(--emerald-border);}
.b-in{background:var(--blue-light);color:var(--blue);border:1px solid var(--blue-border);}
.b-emr{background:var(--red-light);color:var(--red);border:1px solid var(--red-border);}
.b-m{background:#EBF2FC;color:#1A5BA8;border:1px solid rgba(26,91,168,.2);}
.b-f{background:#F5EBF9;color:#7B2D9E;border:1px solid rgba(123,45,158,.2);}
/* DEPT BAR */
.dept-list{padding:4px 0;}
.dept-row{display:flex;align-items:center;gap:10px;padding:8px 18px;border-bottom:1px solid var(--border);}
.dept-row:last-child{border-bottom:none;}
.dept-code{font-size:10px;font-weight:700;color:var(--white);background:var(--navy-mid);border-radius:4px;padding:2px 7px;min-width:52px;text-align:center;flex-shrink:0;}
.dept-name{font-size:12px;color:var(--text-1);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.dept-bar-wrap{width:90px;height:7px;background:var(--border);border-radius:99px;overflow:hidden;flex-shrink:0;}
.dept-bar-fill{height:100%;background:var(--emerald);border-radius:99px;transition:width .4s;}
.dept-cnt{font-size:11.5px;font-weight:700;color:var(--text-2);min-width:22px;text-align:right;}
/* HMO LIST */
.hmo-list{padding:4px 0;}
.hmo-row{display:flex;align-items:center;gap:10px;padding:9px 18px;border-bottom:1px solid var(--border);}
.hmo-row:last-child{border-bottom:none;}
.hmo-name{font-size:12px;color:var(--text-1);flex:1;}
.hmo-cnt{font-size:12px;font-weight:700;color:var(--navy);}
/* QUICK ACTIONS */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:14px 18px;}
.qa-btn{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);text-decoration:none;transition:background .12s,box-shadow .12s;background:var(--surface);}
.qa-btn:hover{background:var(--white);box-shadow:0 2px 8px rgba(0,0,0,.08);}
.qa-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.qa-icon svg{width:14px;height:14px;fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.qa-label{font-size:12px;font-weight:600;color:var(--text-1);}
.qa-sub{font-size:10.5px;color:var(--text-3);margin-top:1px;}
/* TREND CHART */
.trend-chart{padding:14px 18px 8px;}
.trend-bars{display:flex;align-items:flex-end;gap:6px;height:80px;}
.trend-bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;}
.trend-bar-bg{flex:1;width:100%;background:var(--surface);border-radius:4px 4px 0 0;display:flex;align-items:flex-end;overflow:hidden;}
.trend-bar{width:100%;background:var(--emerald);border-radius:4px 4px 0 0;transition:height .4s;min-height:2px;}
.trend-bar.today{background:var(--navy);}
.trend-val{font-size:10px;font-weight:700;color:var(--text-2);}
.trend-day{font-size:9.5px;color:var(--text-3);font-weight:600;}
.trend-label{font-size:10px;color:var(--text-3);margin-top:8px;text-align:center;}
/* INFO ROW */
.info-row{display:flex;gap:14px;margin-bottom:14px;}
.info-chip{flex:1;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:13px 16px;display:flex;align-items:center;gap:12px;}
.ic-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ic-icon svg{width:16px;height:16px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.ic-val{font-size:20px;font-weight:800;color:var(--navy);line-height:1;}
.ic-lbl{font-size:10.5px;color:var(--text-3);margin-top:2px;}
@media(max-width:768px){.stat-grid{grid-template-columns:1fr 1fr;}.two-col,.three-col{grid-template-columns:1fr;}.info-row{flex-direction:column;}.wb-right{display:none;}}
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
  <a href="dashboard.php" class="nav-item active">
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
  <?php if(in_array($role,['Admin'])):?>
  <div class="nav-label">Administration</div>
  <a href="doctors.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    Doctors &amp; Depts
  </a>
  <a href="users.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <?php endif;?>
  <div class="sb-footer">PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</div>
</aside>

<!-- ═══ MAIN ══════════════════════════════════════════════ -->
<div class="main">
  <div class="topbar">
    <div class="tb-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div>
    <span class="tb-title">Dashboard</span>
    <span class="tb-bc">/ Overview</span>
    <div class="tb-right">
      <div class="pill"><div class="pill-dot"></div><span>System Online</span></div>
      <div class="tb-user">
        <div class="avatar"><?=strtoupper(substr($_SESSION['user_name'],0,2))?></div>
        <?=htmlspecialchars($_SESSION['user_name'])?>
        &nbsp;<a href="logout.php" style="font-size:11px;color:var(--red);text-decoration:none;font-weight:600;">Logout</a>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="wb-left">
        <h2>Welcome back, <?=htmlspecialchars(explode(' ',$_SESSION['user_name'])[0])?> 👋</h2>
        <p><?=date('l, F j, Y')?> &nbsp;·&nbsp; <?=htmlspecialchars($role)?> Account &nbsp;·&nbsp; PatientDataProgram</p>
      </div>
      <div class="wb-right">
        <a href="register.php" class="btn btn-white">
          <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          Register Patient
        </a>
        <a href="index.php" class="btn btn-outline-white">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          View Registry
        </a>
      </div>
    </div>

    <!-- Stat Chips -->
    <div class="stat-grid">
      <div class="sc s-total">
        <div class="sc-lbl">Total Patients</div>
        <div class="sc-val"><?=(int)$stats['total']?></div>
        <div class="sc-sub">All time registered</div>
      </div>
      <div class="sc s-out">
        <div class="sc-lbl">Outpatient</div>
        <div class="sc-val"><?=(int)$stats['outpatient']?></div>
        <div class="sc-sub"><?=$stats['total']>0 ? round($stats['outpatient']/$stats['total']*100).'%' : '0%'?> of total</div>
      </div>
      <div class="sc s-in">
        <div class="sc-lbl">Inpatient</div>
        <div class="sc-val"><?=(int)$stats['inpatient']?></div>
        <div class="sc-sub"><?=$stats['total']>0 ? round($stats['inpatient']/$stats['total']*100).'%' : '0%'?> of total</div>
      </div>
      <div class="sc s-emr">
        <div class="sc-lbl">Emergency</div>
        <div class="sc-val"><?=(int)$stats['emergency']?></div>
        <div class="sc-sub"><?=$stats['total']>0 ? round($stats['emergency']/$stats['total']*100).'%' : '0%'?> of total</div>
      </div>
    </div>

    <!-- Info Row -->
    <div class="info-row">
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--gold)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="ic-val"><?=(int)$stats['today']?></div><div class="ic-lbl">Registered Today</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--teal)"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="ic-val"><?=(int)$stats['this_week']?></div><div class="ic-lbl">This Week</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--navy-mid)"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
        <div><div class="ic-val"><?=(int)$stats['this_month']?></div><div class="ic-lbl">This Month</div></div>
      </div>
      <div class="info-chip">
        <div class="ic-icon" style="background:var(--purple)"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
        <div><div class="ic-val"><?=(int)$doc_count?></div><div class="ic-lbl">Active Doctors</div></div>
      </div>
    </div>

    <!-- 7-Day Trend + Quick Actions -->
    <div class="two-col" style="margin-bottom:14px;">
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          <h2>Registrations — Last 7 Days</h2>
        </div>
        <div class="trend-chart">
          <div class="trend-bars">
            <?php foreach($trend_map as $day => $cnt):
                $pct = $trend_max > 0 ? round($cnt / $trend_max * 100) : 0;
                $isToday = $day === date('Y-m-d');
                $lbl = date('D', strtotime($day));
            ?>
            <div class="trend-bar-wrap">
              <div class="trend-val"><?=$cnt ?: ''?></div>
              <div class="trend-bar-bg">
                <div class="trend-bar<?=$isToday?' today':''?>" style="height:<?=$pct>0?$pct:2?>%"></div>
              </div>
              <div class="trend-day"><?=$lbl?></div>
            </div>
            <?php endforeach;?>
          </div>
          <div class="trend-label">■ <span style="color:var(--emerald)">Previous days</span> &nbsp; ■ <span style="color:var(--navy)">Today</span></div>
        </div>
      </div>

      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          <h2>Quick Actions</h2>
        </div>
        <div class="qa-grid">
          <a href="register.php" class="qa-btn">
            <div class="qa-icon" style="background:var(--emerald)"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
            <div><div class="qa-label">Register Patient</div><div class="qa-sub">New patient entry</div></div>
          </a>
          <a href="index.php" class="qa-btn">
            <div class="qa-icon" style="background:var(--navy)"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            <div><div class="qa-label">Patient Registry</div><div class="qa-sub">Search & filter</div></div>
          </a>
          <?php if($role==='Admin'):?>
          <a href="doctors.php" class="qa-btn">
            <div class="qa-icon" style="background:var(--blue)"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
            <div><div class="qa-label">Manage Doctors</div><div class="qa-sub">Add or update</div></div>
          </a>
          <a href="users.php" class="qa-btn">
            <div class="qa-icon" style="background:var(--purple)"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <div><div class="qa-label">User Accounts</div><div class="qa-sub">Manage access</div></div>
          </a>
          <?php else:?>
          <a href="index.php?filter=today" class="qa-btn">
            <div class="qa-icon" style="background:var(--gold)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div><div class="qa-label">Today's Patients</div><div class="qa-sub"><?=(int)$stats['today']?> registered</div></div>
          </a>
          <a href="index.php?type=Emergency" class="qa-btn">
            <div class="qa-icon" style="background:var(--red)"><svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div><div class="qa-label">Emergency Cases</div><div class="qa-sub"><?=(int)$stats['emergency']?> on record</div></div>
          </a>
          <?php endif;?>
        </div>
      </div>
    </div>

    <!-- Recent + Dept + HMO -->
    <div class="three-col">
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <h2>Recent Registrations</h2>
          <a href="index.php" class="ch-link">View All →</a>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Patient No.</th>
                <th>Name</th>
                <th>Type</th>
                <th>Department</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($recent)):?>
              <tr><td colspan="5" class="no-data">No patients registered yet.</td></tr>
              <?php else: foreach($recent as $r):
                $tmap = ['Outpatient'=>'b-out','Inpatient'=>'b-in','Emergency'=>'b-emr'];
                $dt   = date('M j, Y', strtotime($r['registered_at']));
              ?>
              <tr>
                <td style="font-family:var(--font-mono);font-size:11.5px;color:var(--text-2)"><?=htmlspecialchars($r['patient_no'])?></td>
                <td style="font-weight:600"><?=htmlspecialchars($r['full_name'])?></td>
                <td><span class="badge <?=$tmap[$r['patient_type']]??'b-out'?>"><?=htmlspecialchars($r['patient_type'])?></span></td>
                <td style="font-size:12px;color:var(--text-2)"><?=htmlspecialchars($r['dept_name']??'—')?></td>
                <td style="font-size:11.5px;color:var(--text-3);white-space:nowrap"><?=$dt?></td>
              </tr>
              <?php endforeach; endif;?>
            </tbody>
          </table>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;">
        <!-- Dept Breakdown -->
        <div class="card">
          <div class="card-hdr">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            <h2>Top Departments</h2>
          </div>
          <div class="dept-list">
            <?php
            $max_dept = max(array_column($dept_stats, 'cnt') ?: [1]);
            foreach($dept_stats as $ds):
                $pct = $max_dept > 0 ? round($ds['cnt']/$max_dept*100) : 0;
            ?>
            <div class="dept-row">
              <span class="dept-code"><?=htmlspecialchars($ds['code'])?></span>
              <span class="dept-name"><?=htmlspecialchars($ds['name'])?></span>
              <div class="dept-bar-wrap"><div class="dept-bar-fill" style="width:<?=$pct?>%"></div></div>
              <span class="dept-cnt"><?=(int)$ds['cnt']?></span>
            </div>
            <?php endforeach;?>
          </div>
        </div>

        <!-- HMO Breakdown -->
        <div class="card">
          <div class="card-hdr">
            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <h2>HMO / Insurance</h2>
          </div>
          <div class="hmo-list">
            <?php foreach($hmo_stats as $hs): if($hs['cnt']==0) continue; ?>
            <div class="hmo-row">
              <span class="hmo-name"><?=htmlspecialchars($hs['name'])?></span>
              <span class="hmo-cnt"><?=(int)$hs['cnt']?></span>
            </div>
            <?php endforeach;?>
            <?php if(empty(array_filter($hmo_stats, fn($h)=>$h['cnt']>0))):?>
            <div style="padding:18px;text-align:center;font-size:12px;color:var(--text-3)">No HMO data yet.</div>
            <?php endif;?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->
</body>
</html>
