<?php
/**
 * PATIENTDATAPROGRAM — register.php
 * Full-page Patient Registration Form
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db_connect.php';

$role = $_SESSION['user_role'];
if (!in_array($role, ['Admin','Nurse','Encoder'])) {
    header('Location: index.php'); exit;
}

// ─── AUTO-GENERATE PATIENT NUMBER ───────────────────────
function generatePatientNo(PDO $pdo): string {
    $year = date('Y');
    $row  = $pdo->query("SELECT COUNT(*) AS cnt FROM patients WHERE YEAR(registered_at) = $year")->fetch();
    $seq  = (int)$row['cnt'] + 1;
    return 'PDP-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

// ─── HANDLE SUBMIT ───────────────────────────────────────
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fn  = trim($_POST['full_name']      ?? '');
    $bd  = trim($_POST['birthdate']      ?? '');
    $sex = trim($_POST['sex']            ?? '');
    $cs  = trim($_POST['civil_status']   ?? 'Single');
    $nat = trim($_POST['nationality']    ?? 'Filipino');
    $rel = trim($_POST['religion']       ?? '');
    $bt  = trim($_POST['blood_type']     ?? 'Unknown');
    $con = trim($_POST['contact_number'] ?? '');
    $em  = trim($_POST['email']          ?? '');
    $adr = trim($_POST['address']        ?? '');
    $cty = trim($_POST['city']           ?? '');
    $prv = trim($_POST['province']       ?? '');
    $enm = trim($_POST['emergency_name']     ?? '');
    $erl = trim($_POST['emergency_relation'] ?? '');
    $eco = trim($_POST['emergency_contact']  ?? '');
    $pt  = trim($_POST['patient_type']   ?? 'Outpatient');
    $did = (int)($_POST['department_id'] ?? 0) ?: null;
    $drid= (int)($_POST['doctor_id']     ?? 0) ?: null;
    $hid = (int)($_POST['hmo_id']        ?? 0) ?: null;
    $hcn = trim($_POST['hmo_card_no']    ?? '');
    $cc  = trim($_POST['chief_complaint'] ?? '');
    $alg = trim($_POST['allergies']      ?? '');

    if (!$fn)  $errors[] = "Full Name is required.";
    if (!$bd)  $errors[] = "Birthdate is required.";
    if (!$sex) $errors[] = "Sex is required.";

    if (empty($errors)) {
        $pno = generatePatientNo($pdo);
        $stmt = $pdo->prepare("INSERT INTO patients
            (patient_no,full_name,birthdate,sex,civil_status,nationality,religion,blood_type,
             contact_number,email,address,city,province,
             emergency_name,emergency_relation,emergency_contact,
             patient_type,department_id,doctor_id,hmo_id,hmo_card_no,
             chief_complaint,allergies,registered_by,registered_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$pno,$fn,$bd,$sex,$cs,$nat,$rel,$bt,$con,$em,$adr,$cty,$prv,
                        $enm,$erl,$eco,$pt,$did,$drid,$hid,$hcn,$cc,$alg,
                        $_SESSION['user_name']]);

        $_SESSION['success'] = "Patient <strong>" . htmlspecialchars($fn) . "</strong> registered. Patient No: <strong>$pno</strong>";
        header('Location: register.php?done=1'); exit;
    }
    // Keep form values on error
    $v = compact('fn','bd','sex','cs','nat','rel','bt','con','em','adr','cty','prv','enm','erl','eco','pt','did','drid','hid','hcn','cc','alg');
} else {
    $v = ['fn'=>'','bd'=>'','sex'=>'','cs'=>'Single','nat'=>'Filipino','rel'=>'','bt'=>'Unknown',
          'con'=>'','em'=>'','adr'=>'','cty'=>'','prv'=>'','enm'=>'','erl'=>'','eco'=>'',
          'pt'=>'Outpatient','did'=>'','drid'=>'','hid'=>'','hcn'=>'','cc'=>'','alg'=>''];
}

$flash_success = $_SESSION['success'] ?? null; unset($_SESSION['success']);

// ─── FETCH LOOKUPS ───────────────────────────────────────
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$doctors     = $pdo->query("SELECT dr.*, d.name AS dept_name FROM doctors dr JOIN departments d ON d.id=dr.department_id WHERE dr.is_active=1 ORDER BY dr.full_name ASC")->fetchAll();
$hmos        = $pdo->query("SELECT * FROM hmo_providers WHERE is_active=1 ORDER BY name ASC")->fetchAll();

$doctors_json = json_encode($doctors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Philippine provinces list
$provinces = [
    'Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao',
    'Aurora','Basilan','Bataan','Batanes','Batangas','Benguet','Biliran',
    'Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur',
    'Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro',
    'Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental',
    'Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte',
    'Ilocos Sur','Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte',
    'Lanao del Sur','Leyte','Maguindanao del Norte','Maguindanao del Sur',
    'Marinduque','Masbate','Metro Manila (NCR)','Misamis Occidental','Misamis Oriental',
    'Mountain Province','Negros Occidental','Negros Oriental','Northern Samar',
    'Nueva Ecija','Nueva Vizcaya','Occidental Mindoro','Oriental Mindoro','Palawan',
    'Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar',
    'Sarangani','Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat',
    'Sulu','Surigao del Norte','Surigao del Sur','Tarlac','Tawi-Tawi','Zambales',
    'Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'
];

$religions = ['Roman Catholic','Islam','Iglesia ni Cristo','Seventh-day Adventist',
    'United Church of Christ','Baptist','Born Again Christian','Jehovah\'s Witnesses',
    'Aglipayan (Philippine Independent Church)','Buddhism','Other / Not Specified'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — Register Patient</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --navy:#0D2137;--navy-mid:#163352;--navy-hover:#1E4470;
    --emerald:#1A7F5A;--emerald-mid:#22A872;--emerald-light:#E8F5F0;--emerald-border:rgba(26,127,90,.25);
    --gold:#B8820A;--gold-light:#FDF3E3;--gold-border:rgba(184,130,10,.25);
    --red:#C0392B;--red-light:#FDECEA;--red-border:rgba(192,57,43,.25);
    --blue:#1A5BA8;--blue-light:#EBF2FC;--blue-border:rgba(26,91,168,.25);
    --teal:#0E7B8E;--teal-light:#E6F5F7;--teal-border:rgba(14,123,142,.25);
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
/* FLASH */
.flash{display:flex;align-items:flex-start;gap:10px;padding:11px 16px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:13px;font-weight:500;}
.flash.ok{background:var(--emerald-light);border:1px solid var(--emerald-border);color:var(--emerald);}
.flash.err{background:var(--red-light);border:1px solid var(--red-border);color:var(--red);}
.flash svg{width:15px;height:15px;flex-shrink:0;margin-top:1px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
/* PAGE HEADER */
.page-hdr{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.page-hdr h1{font-size:19px;font-weight:800;color:var(--navy);}
.page-hdr p{font-size:12px;color:var(--text-3);margin-top:3px;}
/* CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px;}
.card-hdr{display:flex;align-items:center;gap:9px;padding:13px 22px;border-bottom:1px solid var(--border);background:var(--surface);}
.card-hdr h2{font-size:12px;font-weight:700;color:var(--navy);letter-spacing:.04em;text-transform:uppercase;}
.card-hdr svg{width:15px;height:15px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.card-body{padding:20px 22px;}
/* FORM GRID */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-grid.g3{grid-template-columns:1fr 1fr 1fr;}
.form-grid.g1{grid-template-columns:1fr;}
.form-grid.g4{grid-template-columns:1fr 1fr 1fr 1fr;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg label{font-size:11.5px;font-weight:600;color:var(--text-2);}
.fg input,.fg select,.fg textarea{padding:9px 11px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:13px;font-family:var(--font);color:var(--text-1);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s;width:100%;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(26,127,90,.12);}
.fg textarea{resize:vertical;min-height:70px;}
.fg select{cursor:pointer;appearance:auto;}
.req{color:var(--red);}
/* PATIENT TYPE RADIO BUTTONS */
.type-group{display:flex;gap:10px;flex-wrap:wrap;}
.type-opt{flex:1;min-width:110px;}
.type-opt input[type=radio]{position:absolute;opacity:0;width:0;}
.type-opt label{display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 12px;border:2px solid var(--border-mid);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;font-weight:600;color:var(--text-2);transition:all .15s;background:var(--surface);text-align:center;}
.type-opt input[type=radio]:checked + label.t-out{background:var(--emerald-light);border-color:var(--emerald);color:var(--emerald);}
.type-opt input[type=radio]:checked + label.t-in{background:var(--blue-light);border-color:var(--blue);color:var(--blue);}
.type-opt input[type=radio]:checked + label.t-emr{background:var(--red-light);border-color:var(--red);color:var(--red);}
.type-opt label:hover{background:var(--white);border-color:var(--border);}
.type-opt label svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* DIVIDER */
.sect-div{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-3);margin-bottom:14px;padding-bottom:7px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.sect-div svg{width:13px;height:13px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* SUBMIT ROW */
.form-actions{display:flex;gap:10px;align-items:center;padding:16px 22px;background:var(--surface);border-top:1px solid var(--border);border-radius:0 0 var(--radius) var(--radius);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:var(--radius-sm);font-size:13px;font-weight:600;font-family:var(--font);cursor:pointer;border:1px solid transparent;transition:background .12s,box-shadow .12s;text-decoration:none;}
.btn-primary{background:var(--emerald);color:#fff;}
.btn-primary:hover{background:var(--emerald-mid);box-shadow:0 2px 10px rgba(26,127,90,.25);}
.btn-secondary{background:var(--white);color:var(--text-2);border-color:var(--border-mid);}
.btn-secondary:hover{background:var(--surface);}
.btn-danger{background:var(--red-light);color:var(--red);border-color:var(--red-border);}
.btn svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
/* PATIENT NO PREVIEW */
.pno-preview{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--navy);border-radius:var(--radius-sm);margin-bottom:18px;}
.pno-preview .lbl{font-size:10.5px;color:rgba(255,255,255,.5);font-weight:600;letter-spacing:.06em;text-transform:uppercase;}
.pno-preview .val{font-size:15px;font-weight:800;color:#fff;font-family:var(--font-mono);letter-spacing:.08em;}
.pno-preview svg{width:14px;height:14px;fill:none;stroke:rgba(255,255,255,.4);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;margin-left:auto;}
/* REQUIRED NOTE */
.req-note{font-size:11px;color:var(--text-3);margin-bottom:14px;}
@media(max-width:768px){.form-grid,.form-grid.g3,.form-grid.g4{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.form-grid,.form-grid.g3,.form-grid.g4{grid-template-columns:1fr;}}
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
  <a href="register.php" class="nav-item active">
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
    <div class="tb-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
    <span class="tb-title">Register Patient</span>
    <span class="tb-bc">/ New Patient Entry</span>
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

    <?php if($flash_success):?>
    <div class="flash ok">
      <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span><?=$flash_success?> — <a href="index.php" style="color:inherit;font-weight:700;">View Registry</a> or fill below to register another.</span>
    </div>
    <?php endif;?>

    <?php if(!empty($errors)):?>
    <div class="flash err">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <?php foreach($errors as $e): echo '<div>'.htmlspecialchars($e).'</div>'; endforeach;?>
      </div>
    </div>
    <?php endif;?>

    <!-- Page Header -->
    <div class="page-hdr">
      <div>
        <h1>Register New Patient</h1>
        <p>Fill in the form below. Fields marked <span class="req">*</span> are required.</p>
      </div>
      <a href="index.php" class="btn btn-secondary">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Registry
      </a>
    </div>

    <form method="POST" action="register.php" id="reg-form">

      <!-- ── PATIENT TYPE ── -->
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <h2>Patient Classification</h2>
        </div>
        <div class="card-body">
          <div class="fg">
            <label>Patient Type <span class="req">*</span></label>
            <div class="type-group">
              <div class="type-opt">
                <input type="radio" name="patient_type" id="t-out" value="Outpatient" <?=$v['pt']==='Outpatient'?'checked':''?>>
                <label for="t-out" class="t-out">
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Outpatient
                </label>
              </div>
              <div class="type-opt">
                <input type="radio" name="patient_type" id="t-in" value="Inpatient" <?=$v['pt']==='Inpatient'?'checked':''?>>
                <label for="t-in" class="t-in">
                  <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                  Inpatient
                </label>
              </div>
              <div class="type-opt">
                <input type="radio" name="patient_type" id="t-emr" value="Emergency" <?=$v['pt']==='Emergency'?'checked':''?>>
                <label for="t-emr" class="t-emr">
                  <svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Emergency
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── PERSONAL INFO ── -->
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <h2>Personal Information</h2>
        </div>
        <div class="card-body">
          <div class="req-note"><span class="req">*</span> Required fields</div>

          <div class="form-grid g1" style="margin-bottom:14px;">
            <div class="fg">
              <label for="full_name">Full Name <span class="req">*</span></label>
              <input type="text" id="full_name" name="full_name" value="<?=htmlspecialchars($v['fn'])?>" placeholder="Last Name, First Name Middle Name" required>
            </div>
          </div>

          <div class="form-grid g3" style="margin-bottom:14px;">
            <div class="fg">
              <label for="birthdate">Date of Birth <span class="req">*</span></label>
              <input type="date" id="birthdate" name="birthdate" value="<?=htmlspecialchars($v['bd'])?>" max="<?=date('Y-m-d')?>" required>
            </div>
            <div class="fg">
              <label for="age-display">Age</label>
              <input type="text" id="age-display" readonly placeholder="Auto-computed" style="background:var(--surface);color:var(--text-2);">
            </div>
            <div class="fg">
              <label for="sex">Sex <span class="req">*</span></label>
              <select id="sex" name="sex" required>
                <option value="">— Select —</option>
                <option value="Male"   <?=$v['sex']==='Male'?'selected':''?>>Male</option>
                <option value="Female" <?=$v['sex']==='Female'?'selected':''?>>Female</option>
                <option value="Other"  <?=$v['sex']==='Other'?'selected':''?>>Other</option>
              </select>
            </div>
          </div>

          <div class="form-grid g4" style="margin-bottom:14px;">
            <div class="fg">
              <label for="civil_status">Civil Status</label>
              <select id="civil_status" name="civil_status">
                <?php foreach(['Single','Married','Widowed','Separated','Annulled'] as $o): ?>
                <option value="<?=$o?>" <?=$v['cs']===$o?'selected':''?>><?=$o?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="blood_type">Blood Type</label>
              <select id="blood_type" name="blood_type">
                <?php foreach(['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $o): ?>
                <option value="<?=$o?>" <?=$v['bt']===$o?'selected':''?>><?=$o?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="nationality">Nationality</label>
              <input type="text" id="nationality" name="nationality" value="<?=htmlspecialchars($v['nat'])?>" placeholder="Filipino">
            </div>
            <div class="fg">
              <label for="religion">Religion</label>
              <select id="religion" name="religion">
                <option value="">— Select —</option>
                <?php foreach($religions as $r): ?>
                <option value="<?=htmlspecialchars($r)?>" <?=$v['rel']===$r?'selected':''?>><?=htmlspecialchars($r)?></option>
                <?php endforeach;?>
              </select>
            </div>
          </div>

          <div class="form-grid" style="margin-bottom:0;">
            <div class="fg">
              <label for="contact_number">Contact Number</label>
              <input type="tel" id="contact_number" name="contact_number" value="<?=htmlspecialchars($v['con'])?>" placeholder="09XX-XXX-XXXX">
            </div>
            <div class="fg">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" value="<?=htmlspecialchars($v['em'])?>" placeholder="email@example.com">
            </div>
          </div>
        </div>
      </div>

      <!-- ── ADDRESS ── -->
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <h2>Address</h2>
        </div>
        <div class="card-body">
          <div class="form-grid g1" style="margin-bottom:14px;">
            <div class="fg">
              <label for="address">Street / Barangay Address</label>
              <input type="text" id="address" name="address" value="<?=htmlspecialchars($v['adr'])?>" placeholder="House No., Street, Barangay">
            </div>
          </div>
          <div class="form-grid">
            <div class="fg">
              <label for="province">Province</label>
              <select id="province" name="province" onchange="filterCities(this.value)">
                <option value="">— Select Province —</option>
                <?php foreach($provinces as $p): ?>
                <option value="<?=htmlspecialchars($p)?>" <?=$v['prv']===$p?'selected':''?>><?=htmlspecialchars($p)?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="city">City / Municipality</label>
              <select id="city" name="city">
                <option value="">— Select Province First —</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- ── EMERGENCY CONTACT ── -->
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.57 1.22h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.08 6.08l.91-.91a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21.73 16.92z"/></svg>
          <h2>Emergency Contact</h2>
        </div>
        <div class="card-body">
          <div class="form-grid g3">
            <div class="fg">
              <label for="emergency_name">Contact Name</label>
              <input type="text" id="emergency_name" name="emergency_name" value="<?=htmlspecialchars($v['enm'])?>" placeholder="Full name">
            </div>
            <div class="fg">
              <label for="emergency_relation">Relationship</label>
              <select id="emergency_relation" name="emergency_relation">
                <option value="">— Select —</option>
                <?php foreach(['Spouse','Parent','Child','Sibling','Grandparent','Relative','Guardian','Friend','Other'] as $o): ?>
                <option value="<?=$o?>" <?=$v['erl']===$o?'selected':''?>><?=$o?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="emergency_contact">Contact Number</label>
              <input type="tel" id="emergency_contact" name="emergency_contact" value="<?=htmlspecialchars($v['eco'])?>" placeholder="09XX-XXX-XXXX">
            </div>
          </div>
        </div>
      </div>

      <!-- ── CLINICAL INFO ── -->
      <div class="card">
        <div class="card-hdr">
          <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          <h2>Clinical &amp; Hospital Assignment</h2>
        </div>
        <div class="card-body">

          <div class="sect-div">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            Department &amp; Attending Physician
          </div>
          <div class="form-grid" style="margin-bottom:18px;">
            <div class="fg">
              <label for="department_id">Department</label>
              <select id="department_id" name="department_id" onchange="filterDoctors(this.value)">
                <option value="">— Select Department —</option>
                <?php foreach($departments as $d): ?>
                <option value="<?=(int)$d['id']?>" <?=$v['did']==$d['id']?'selected':''?>>
                  <?=htmlspecialchars($d['name'])?>
                </option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="doctor_id">Attending Physician</label>
              <select id="doctor_id" name="doctor_id">
                <option value="">— Select Physician —</option>
                <?php foreach($doctors as $d): ?>
                <option value="<?=(int)$d['id']?>" data-dept="<?=(int)$d['department_id']?>" <?=$v['drid']==$d['id']?'selected':''?>>
                  <?=htmlspecialchars($d['full_name'])?> (<?=htmlspecialchars($d['dept_name'])?>)
                </option>
                <?php endforeach;?>
              </select>
            </div>
          </div>

          <div class="sect-div">
            <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            HMO / Insurance Coverage
          </div>
          <div class="form-grid" style="margin-bottom:18px;">
            <div class="fg">
              <label for="hmo_id">HMO / Insurance Provider</label>
              <select id="hmo_id" name="hmo_id">
                <option value="">— Select Provider —</option>
                <?php foreach($hmos as $h): ?>
                <option value="<?=(int)$h['id']?>" <?=$v['hid']==$h['id']?'selected':''?>>
                  <?=htmlspecialchars($h['name'])?>
                </option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="fg">
              <label for="hmo_card_no">HMO Card / Member Number</label>
              <input type="text" id="hmo_card_no" name="hmo_card_no" value="<?=htmlspecialchars($v['hcn'])?>" placeholder="Leave blank if none">
            </div>
          </div>

          <div class="sect-div">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Clinical Notes
          </div>
          <div class="form-grid">
            <div class="fg">
              <label for="chief_complaint">Chief Complaint</label>
              <textarea id="chief_complaint" name="chief_complaint" placeholder="Primary reason for visit…"><?=htmlspecialchars($v['cc'])?></textarea>
            </div>
            <div class="fg">
              <label for="allergies">Known Allergies</label>
              <textarea id="allergies" name="allergies" placeholder="Medications, food, etc. (None if none)…"><?=htmlspecialchars($v['alg'])?></textarea>
            </div>
          </div>

        </div>
        <!-- Submit -->
        <div class="form-actions">
          <button type="submit" class="btn btn-primary" id="submit-btn">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Register Patient
          </button>
          <button type="reset" class="btn btn-secondary" onclick="resetForm()">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
            Clear Form
          </button>
          <a href="index.php" class="btn btn-secondary">Cancel</a>
          <span style="margin-left:auto;font-size:11.5px;color:var(--text-3);">
            Logged in as <strong style="color:var(--text-2)"><?=htmlspecialchars($_SESSION['user_name'])?></strong> — will be recorded as registrar
          </span>
        </div>
      </div>

    </form>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<script>
const ALL_DOCTORS = <?=$doctors_json?>;

// ── Province → Cities Map ─────────────────────────────────
const CITIES = {
  "Abra":["Bangued","Boliney","Bucay","Bucloc","Daguioman","Danglas","Dolores","La Paz","Lacub","Lagangilang","Lagayan","Langiden","Licuan-Baay","Luba","Malibcong","Manabo","Peñarrubia","Pidigan","Pilar","Sallapadan","San Gregorio","San Isidro","San Juan","San Quintin","Tayum","Tineg","Tubo","Villaviciosa"],
  "Agusan del Norte":["Buenavista","Butuan City","Cabadbaran City","Carmen","Jabonga","Kitcharao","Las Nieves","Libertad","Magallanes","Nasipit","Remedios T. Romualdez","Santiago","Tubay"],
  "Agusan del Sur":["Bayugan City","Bunawan","Esperanza","La Paz","Loreto","Prosperidad","Rosario","San Francisco","San Luis","Santa Josefa","Sibagat","Talacogon","Trento","Veruela"],
  "Aklan":["Altavas","Balete","Banga","Batan","Buruanga","Ibajay","Kalibo","Lezo","Libacao","Madalag","Makato","Malay","Malinao","Nabas","New Washington","Numancia","Tangalan"],
  "Albay":["Bacacay","Camalig","Daraga","Guinobatan","Jovellar","Legazpi City","Libon","Ligao City","Malilipot","Malinao","Manito","Oas","Pio Duran","Polangui","Rapu-Rapu","Santo Domingo","Tabaco City","Tiwi"],
  "Antique":["Anini-y","Barbaza","Belison","Bugasong","Caluya","Culasi","Hamtic","Laua-an","Libertad","Pandan","Patnongon","San Jose de Buenavista","San Remigio","Sebaste","Sibalom","Tibiao","Tobias Fornier","Valderrama"],
  "Apayao":["Calanasan","Conner","Flora","Kabugao","Luna","Pudtol","Santa Marcela"],
  "Aurora":["Baler","Casiguran","Dilasag","Dinalungan","Dingalan","Dipaculao","Maria Aurora","San Luis"],
  "Basilan":["Akbar","Al-Barka","Hadji Mohammad Ajul","Hadji Muhtamad","Isabela City","Lamitan City","Lantawan","Maluso","Sumisip","Tabuan-Lasa","Tipo-Tipo","Tuburan","Ungkaya Pukan"],
  "Bataan":["Abucay","Bagac","Balanga City","Dinalupihan","Hermosa","Limay","Mariveles","Morong","Orani","Orion","Pilar","Samal"],
  "Batanes":["Basco","Itbayat","Ivana","Mahatao","Sabtang","Uyugan"],
  "Batangas":["Agoncillo","Alitagtag","Balayan","Balete","Batangas City","Bauan","Calaca","Calatagan","Cuenca","Ibaan","Laurel","Lemery","Lian","Lipa City","Lobo","Mabini","Malvar","Mataas na Kahoy","Nasugbu","Padre Garcia","Rosario","San Jose","San Juan","San Luis","San Nicolas","San Pascual","Santa Teresita","Santo Tomas","Taal","Talisay","Tanauan City","Taysan","Tingloy","Tuy"],
  "Benguet":["Atok","Baguio City","Bakun","Bokod","Buguias","Itogon","Kabayan","Kibungan","La Trinidad","Mankayan","Sablan","Tuba","Tublay"],
  "Biliran":["Almeria","Biliran","Cabucgayan","Caibiran","Culaba","Kawayan","Maripipi","Naval"],
  "Bohol":["Alburquerque","Alicia","Anda","Antequera","Baclayon","Balilihan","Batuan","Bilar","Buenavista","Calape","Candijay","Carmen","Catigbian","Clarin","Corella","Cortes","Dagohoy","Danao","Dauis","Dimiao","Duero","Garcia Hernandez","Guindulman","Inabanga","Jagna","Jetafe","Lila","Loay","Loboc","Loon","Mabini","Maribojoc","Panglao","Pilar","Pres. Carlos P. Garcia","Sagbayan","San Isidro","San Miguel","Sevilla","Sierra Bullones","Sikatuna","Tagbilaran City","Talibon","Trinidad","Tubigon","Ubay","Valencia"],
  "Bukidnon":["Baungon","Cabanglasan","Damulog","Dangcagan","Don Carlos","Impasug-ong","Kadingilan","Kalilangan","Kibawe","Kitaotao","Lantapan","Libona","Malaybalay City","Malitbog","Manolo Fortich","Maramag","Pangantucan","Quezon","San Fernando","Sumilao","Talakag","Valencia City"],
  "Bulacan":["Angat","Balagtas","Baliuag","Bocaue","Bulacan","Bustos","Calumpit","Doña Remedios Trinidad","Guiguinto","Hagonoy","Malolos City","Marilao","Meycauayan City","Norzagaray","Obando","Pandi","Paombong","Plaridel","Pulilan","San Ildefonso","San Jose del Monte City","San Miguel","San Rafael","Santa Maria"],
  "Cagayan":["Abulug","Alcala","Allacapan","Amulung","Aparri","Baggao","Ballesteros","Buguey","Calayan","Camalaniugan","Claveria","Enrile","Gattaran","Gonzaga","Iguig","Lal-lo","Lasam","Pamplona","Peñablanca","Piat","Rizal","Sanchez-Mira","Santa Ana","Santa Praxedes","Santa Teresita","Santo Niño","Solana","Tuao","Tuguegarao City"],
  "Camarines Norte":["Basud","Capalonga","Daet","Jose Panganiban","Labo","Mercedes","Paracale","San Lorenzo Ruiz","San Vicente","Santa Elena","Talisay","Vinzons"],
  "Camarines Sur":["Baao","Balatan","Bato","Bombon","Buhi","Bula","Camaligan","Canaman","Caramoan","Del Gallego","Gainza","Garchitorena","Goa","Iriga City","Lagonoy","Libmanan","Lupi","Magarao","Milaor","Minalabac","Nabua","Naga City","Ocampo","Pamplona","Pasacao","Pili","Presentacion","Ragay","Sagñay","San Fernando","San Jose","Sipocot","Siruma","Tigaon","Tinambac"],
  "Camiguin":["Catarman","Guinsiliban","Mahinog","Mambajao","Sagay"],
  "Capiz":["Cuartero","Dao","Dumalag","Dumarao","Ivisan","Jamindan","Ma-ayon","Mambusao","Panay","Panitan","Pilar","Pontevedra","President Roxas","Roxas City","Sapi-an","Sigma","Tapaz"],
  "Catanduanes":["Bagamanoc","Baras","Bato","Caramoran","Gigmoto","Pandan","Panganiban","San Andres","San Miguel","Viga","Virac"],
  "Cavite":["Alfonso","Amadeo","Bacoor City","Carmona","Cavite City","Dasmariñas City","General Emilio Aguinaldo","General Mariano Alvarez","General Trias City","Imus City","Indang","Kawit","Magallanes","Maragondon","Mendez","Naic","Noveleta","Rosario","Silang","Tagaytay City","Tanza","Ternate","Trece Martires City"],
  "Cebu":["Alcantara","Alcoy","Alegria","Aloguinsan","Argao","Asturias","Badian","Balamban","Bantayan","Barili","Bogo City","Boljoon","Borbon","Carcar City","Carmen","Catmon","Cebu City","Compostela","Consolacion","Cordova","Daanbantayan","Dalaguete","Danao City","Dumanjug","Ginatilan","Lapu-Lapu City","Liloan","Madridejos","Malabuyoc","Mandaue City","Medellin","Minglanilla","Moalboal","Naga City","Oslob","Pilar","Pinamungajan","Poro","Ronda","Samboan","San Fernando","San Francisco","San Remigio","Santa Fe","Santander","Sibonga","Sogod","Tabogon","Tabuelan","Talisay City","Toledo City","Tuburan","Tudela"],
  "Cotabato":["Alamada","Allah Valley","Aleosan","Antipas","Arakan","Banisilan","Carmen","Kabacan","Kidapawan City","Libungan","Magpet","Makilala","Matalam","Midsayap","M'lang","Pigcawayan","Pikit","President Roxas","Tulunan"],
  "Davao de Oro":["Compostela","Laak","Mabini","Maco","Maragusan","Mawab","Monkayo","Montevista","Nabunturan","New Bataan","Pantukan"],
  "Davao del Norte":["Asuncion","Braulio E. Dujali","Carmen","Kapalong","New Corella","Panabo City","Samal","San Isidro","Santo Tomas","Tagum City","Talaingod"],
  "Davao del Sur":["Bansalan","Davao City","Digos City","Hagonoy","Kiblawan","Magsaysay","Malalag","Matanao","Padada","Santa Cruz","Sulop"],
  "Davao Occidental":["Don Marcelino","Jose Abad Santos","Malita","Santa Maria","Sarangani"],
  "Davao Oriental":["Baganga","Banaybanay","Boston","Caraga","Cateel","Governor Generoso","Lupon","Manay","Mati City","San Isidro","Tarragona"],
  "Dinagat Islands":["Basilisa","Cagdianao","Dinagat","Libjo","Loreto","San Jose","Tubajon"],
  "Eastern Samar":["Arteche","Balangiga","Balangkayan","Borongan City","Can-avid","Dolores","General MacArthur","Giporlos","Guiuan","Hernani","Jipapad","Lawaan","Llorente","Maslog","Maydolong","Mercedes","Oras","Quinapondan","Salcedo","San Julian","San Policarpo","Sulat","Taft"],
  "Guimaras":["Buenavista","Jordan","Nueva Valencia","San Lorenzo","Sibunag"],
  "Ifugao":["Alfonso Lista","Aguinaldo","Asipulo","Banaue","Hingyon","Hungduan","Kiangan","Lagawe","Lamut","Mayoyao","Tinoc"],
  "Ilocos Norte":["Adams","Bacarra","Badoc","Bangui","Banna","Batac City","Burgos","Carasi","Currimao","Dingras","Dumalneg","Laoag City","Marcos","Nueva Era","Pagudpud","Paoay","Pasuquin","Piddig","Pinili","San Nicolas","Sarrat","Solsona","Vintar"],
  "Ilocos Sur":["Alilem","Banayoyo","Bantay","Burgos","Cabugao","Candon City","Caoayan","Cervantes","Galimuyod","Gregorio del Pilar","Lidlidda","Magsingal","Mankayan","Nagbukel","Narvacan","Quirino","Salcedo","San Emilio","San Esteban","San Ildefonso","San Juan","San Vicente","Santa","Santa Catalina","Santa Cruz","Santa Lucia","Santa Maria","Santiago","Sigay","Sinait","Sugpon","Suyo","Tagudin","Vigan City"],
  "Iloilo":["Ajuy","Alimodian","Anilao","Badiangan","Balasan","Banate","Barotac Nuevo","Barotac Viejo","Batad","Bingawan","Cabatuan","Calinog","Carles","Concepcion","Dingle","Dueñas","Dumangas","Estancia","Guimbal","Igbaras","Iloilo City","Janiuay","Lambunao","Leganes","Lemery","Leon","Maasin","Miagao","Mina","New Lucena","Oton","Passi City","Pavia","Pototan","San Dionisio","San Enrique","San Joaquin","San Miguel","San Rafael","Santa Barbara","Sara","Tigbauan","Tubungan","Zarraga"],
  "Isabela":["Alicia","Angadanan","Aurora","Benito Soliven","Burgos","Cabagan","Cabatuan","Cauayan City","Cordon","Delfin Albano","Dinapigue","Divilacan","Echague","Gamu","Ilagan City","Jones","Luna","Maconacon","Mallig","Naguilian","Palanan","Quezon","Quirino","Ramon","Reina Mercedes","Roxas","San Agustin","San Guillermo","San Isidro","San Manuel","San Mariano","San Mateo","San Pablo","Santa Maria","Santiago City","Santo Tomas","Tumauini"],
  "Kalinga":["Balbalan","Lubuagan","Pasil","Pinukpuk","Rizal","Tabuk City","Tanudan","Tinglayan"],
  "La Union":["Agoo","Aringay","Bacnotan","Bagulin","Balaoan","Bangar","Bauang","Burgos","Caba","Luna","Naguilian","Pugo","Rosario","San Fernando City","San Gabriel","San Juan","Santo Tomas","Santol","Subusub","Sudipen","Tubao"],
  "Laguna":["Alaminos","Bay","Biñan City","Cabuyao City","Calamba City","Calauan","Cavinti","Famy","Kalayaan","Liliw","Los Baños","Luisiana","Lumban","Mabitac","Magdalena","Majayjay","Nagcarlan","Paete","Pagsanjan","Pakil","Pangil","Pila","Rizal","San Pablo City","San Pedro City","Santa Cruz","Santa Maria","Santa Rosa City","Siniloan","Victoria"],
  "Lanao del Norte":["Bacolod","Baloi","Baroy","Iligan City","Kapatagan","Kauswagan","Kolambugan","Lala","Linamon","Magsaysay","Maigo","Munai","Nunungan","Pantao Ragat","Pantar","Poona Piagapo","Salvador","Sapad","Sultan Naga Dimaporo","Tagoloan","Tangcal","Tubod"],
  "Lanao del Sur":["Amai Manabilang","Bacolod-Kalawi","Balabagan","Balindong","Bayang","Binidayan","Bubong","Butig","Calanogas","Ganassi","Kapai","Kapatagan","Lumba-Bayabao","Lumbaca-Unayan","Lumbatan","Lumbayanague","Madalum","Madamba","Maguing","Malabang","Marantao","Marawi City","Marogong","Masiu","Molundo","Mulondo","Pagayawan","Piagapo","Picong","Poona Bayabao","Pualas","Saguiaran","Sultan Dumalondong","Sultan Gumander","Tagoloan II","Tamparan","Taraka","Tubaran","Tugaya","Wao"],
  "Leyte":["Abuyog","Alangalang","Albuera","Babatngon","Barugo","Bato","Baybay City","Burauen","Calubian","Capoocan","Carigara","Dagami","Dulag","Hilongos","Hindang","Inopacan","Isabel","Jaro","Javier","Julita","Kananga","La Paz","Leyte","MacArthur","Mahaplag","Matag-ob","Matalom","Mayorga","Merida","Ormoc City","Palo","Palompon","Pastrana","San Isidro","San Miguel","Santa Fe","Tabango","Tabontabon","Tacloban City","Tanauan","Tolosa","Tunga","Villaba"],
  "Maguindanao del Norte":["Barira","Buldon","Datu Blah T. Sinsuat","Datu Odin Sinsuat","Kabuntalan","Matanog","Parang","Sultan Kudarat","Sultan Mastura","Upi"],
  "Maguindanao del Sur":["Ampatuan","Buluan","Datu Abdullah Sangki","Datu Anggal Midtimbang","Datu Hoffer Ampatuan","Datu Paglas","Datu Piang","Datu Salibo","Datu Saudi-Ampatuan","Datu Unsay","General Salipada K. Pendatun","Guindulungan","Imam Sudais","Mangudadatu","Mamasapano","Pagalungan","Paglat","Pandag","Rajah Buayan","Shariff Aguak","Shariff Saydona Mustapha","South Upi","Sultan sa Barongis","Talayan","Talitay"],
  "Marinduque":["Boac","Buenavista","Gasan","Mogpog","Santa Cruz","Torrijos"],
  "Masbate":["Aroroy","Baleno","Balud","Batuan","Cataingan","Cawayan","Claveria","Dimasalang","Esperanza","Mandaon","Masbate City","Milagros","Mobo","Monreal","Palanas","Pio V. Corpuz","Placer","San Fernando","San Jacinto","San Pascual","Uson"],
  "Metro Manila (NCR)":["Caloocan City","Las Piñas City","Makati City","Malabon City","Mandaluyong City","Manila City","Marikina City","Muntinlupa City","Navotas City","Parañaque City","Pasay City","Pasig City","Pateros","Quezon City","San Juan City","Taguig City","Valenzuela City"],
  "Misamis Occidental":["Aloran","Baliangao","Bonifacio","Calamba","Clarin","Concepcion","Don Victoriano Chiongbian","Jimenez","Lopez Jaena","Oroquieta City","Ozamiz City","Panaon","Plaridel","Sapang Dalaga","Sinacaban","Tangub City","Tudela"],
  "Misamis Oriental":["Alubijid","Balingasag","Balingoan","Binuangan","Cagayan de Oro City","Catarman","El Salvador City","Gingoog City","Gitagum","Initao","Jasaan","Kinoguitan","Lagonglong","Laguindingan","Libertad","Lugait","Magsaysay","Manticao","Medina","Naawan","Opol","Salay","Sugbongcogon","Tagoloan","Talisayan","Villanueva"],
  "Mountain Province":["Barlig","Bauko","Besao","Bontoc","Natonin","Paracelis","Sabangan","Sadanga","Sagada","Tadian"],
  "Negros Occidental":["Bacolod City","Bago City","Binalbagan","Calatrava","Candoni","Cauayan","Enrique B. Magalona","Escalante City","Hinigaran","Hinoba-an","Ilog","Isabela","Kabankalan City","La Carlota City","La Castellana","Manapla","Moises Padilla","Murcia","Pontevedra","Pulupandan","Sagay City","San Carlos City","San Enrique","Silay City","Sipalay City","Talisay City","Toboso","Valladolid","Victorias City"],
  "Negros Oriental":["Amlan","Ayungon","Bacong","Bais City","Basay","Bayawan City","Bindoy","Canlaon City","Dauin","Dumaguete City","Guihulngan City","Jimalalud","La Libertad","Mabinay","Manjuyod","Pamplona","San Jose","Santa Catalina","Siaton","Sibulan","Tanjay City","Tayasan","Valencia","Vallehermoso","Zamboanguita"],
  "Northern Samar":["Allen","Biri","Bobon","Capul","Catarman","Catubig","Gamay","Laoang","Lapinig","Las Navas","Lavezares","Lope de Vega","Mapanas","Mondragon","Palapag","Pambujan","Rosario","San Antonio","San Isidro","San Jose","San Roque","San Vicente","Silvino Lobos","Victoria"],
  "Nueva Ecija":["Aliaga","Bongabon","Cabanatuan City","Cabiao","Carranglan","Cuyapo","Gabaldon","Gapan City","General Mamerto Natividad","General Tinio","Guimba","Jaen","Laur","Licab","Llanera","Lupao","Muñoz City","Nampicuan","Palayan City","Pantabangan","Peñaranda","Quezon","Rizal","San Antonio","San Isidro","San Jose City","San Leonardo","Santa Rosa","Santo Domingo","Science City of Muñoz","Talavera","Talugtug","Zaragoza"],
  "Nueva Vizcaya":["Alfonso Castañeda","Ambaguio","Aritao","Bagabag","Bambang","Bayombong","Diadi","Dupax del Norte","Dupax del Sur","Kasibu","Kayapa","Quezon","Santa Fe","Solano","Villaverde"],
  "Occidental Mindoro":["Abra de Ilog","Calintaan","Looc","Lubang","Magsaysay","Mamburao","Paluan","Rizal","Sablayan","San Jose","Santa Cruz"],
  "Oriental Mindoro":["Baco","Bansud","Bongabong","Bulalacao","Calapan City","Gloria","Mansalay","Naujan","Pinamalayan","Pola","Puerto Galera","Roxas","San Teodoro","Socorro","Victoria"],
  "Palawan":["Aborlan","Agutaya","Araceli","Balabac","Bataraza","Brooks Point","Busuanga","Cagayancillo","Coron","Culion","Cuyo","Dumaran","El Nido","Kalayaan","Linapacan","Magsaysay","Narra","Puerto Princesa City","Quezon","Rizal","Roxas","San Vicente","Sofronio Española","Taytay"],
  "Pampanga":["Angeles City","Apalit","Arayat","Bacolor","Candaba","Floridablanca","Guagua","Lubao","Mabalacat City","Macabebe","Magalang","Masantol","Mexico","Minalin","Porac","San Fernando City","San Luis","San Simon","Santa Ana","Santa Rita","Santo Tomas","Sasmuan"],
  "Pangasinan":["Agno","Aguilar","Alaminos City","Alcala","Anda","Asingan","Balungao","Bani","Basista","Bautista","Bayambang","Binalonan","Binmaley","Bolinao","Bugallon","Burgos","Calasiao","Dagupan City","Dasol","Infanta","Labrador","Laoac","Lingayen","Mabini","Malasiqui","Manaoag","Mangaldan","Mangatarem","Mapandan","Natividad","Pozorrubio","Rosales","San Carlos City","San Fabian","San Jacinto","San Manuel","San Nicolas","San Quintin","Santa Barbara","Santa Maria","Santo Tomas","Sison","Sual","Tayug","Umingan","Urbiztondo","Urdaneta City","Villasis"],
  "Quezon":["Agdangan","Alabat","Atimonan","Buenavista","Burdeos","Calauag","Candelaria","Catanauan","Dolores","General Luna","General Nakar","Guinayangan","Gumaca","Infanta","Jomalig","Lopez","Lucban","Lucena City","Macalelon","Mauban","Mulanay","Padre Burgos","Pagbilao","Panukulan","Patnanungan","Perez","Pitogo","Plaridel","Polillo","Quezon","Real","Sampaloc","San Andres","San Antonio","San Francisco","San Narciso","Sariaya","Tagkawayan","Tayabas City","Tiaong","Unisan"],
  "Quirino":["Aglipay","Cabarroguis","Diffun","Maddela","Nagtipunan","Saguday"],
  "Rizal":["Angono","Antipolo City","Baras","Binangonan","Cainta","Cardona","Jala-Jala","Morong","Pililla","Rodriguez","San Mateo","Tanay","Taytay","Teresa"],
  "Romblon":["Alcantara","Banton","Cajidiocan","Calatrava","Concepcion","Corcuera","Ferrol","Looc","Magdiwang","Odiongan","Romblon","San Agustin","San Andres","San Fernando","San Jose","Santa Fe","Santa Maria"],
  "Samar":["Almagro","Basey","Calbayog City","Calbiga","Catbalogan City","Daram","Gandara","Hinabangan","Jiabong","Marabut","Matuguinao","Motiong","Pagsanghan","Paranas","Pinabacdao","San Jorge","San Jose de Buan","San Sebastian","Santa Rita","Santo Niño","Tagapul-an","Talalora","Tarangnan","Villareal","Zumarraga"],
  "Sarangani":["Alabel","Glan","Kiamba","Maasim","Maitum","Malapatan","Malungon"],
  "Siquijor":["Enrique Villanueva","Larena","Lazi","Maria","San Juan","Siquijor"],
  "Sorsogon":["Barcelona","Bulan","Bulusan","Casiguran","Castilla","Donsol","Gubat","Irosin","Juban","Magallanes","Matnog","Pilar","Prieto Diaz","Santa Magdalena","Sorsogon City"],
  "South Cotabato":["Banga","General Santos City","Koronadal City","Lake Sebu","Norala","Polomolok","Santo Niño","Surallah","T'boli","Tampakan","Tantangan","Tupi"],
  "Southern Leyte":["Anahawan","Bontoc","Hinunangan","Hinundayan","Libagon","Liloan","Limasawa","Maasin City","Macrohon","Malitbog","Padre Burgos","Pintuyan","Saint Bernard","San Francisco","San Juan","San Ricardo","Silago","Sogod","Tomas Oppus"],
  "Sultan Kudarat":["Bagumbayan","Columbio","Esperanza","Isulan","Kalamansig","Lambayong","Lebak","Lutayan","Palimbang","President Quirino","Senator Ninoy Aquino","Tacurong City"],
  "Sulu":["Hadji Panglima Tahil","Indanan","Jolo","Kalingalan Caluang","Lugus","Luuk","Maimbung","Old Panamao","Omar","Pandami","Pang","Parang","Patikul","Sigumbal","Simunal","Talipao","Tapul","Tongkil"],
  "Surigao del Norte":["Alegria","Bacuag","Burgos","Claver","Dapa","Del Carmen","General Luna","Gigaquit","Mainit","Malimono","Pilar","Placer","San Benito","San Francisco","San Isidro","Santa Monica","Sison","Socorro","Surigao City","Tagana-an","Tubod"],
  "Surigao del Sur":["Barobo","Bayabas","Bislig City","Cagwait","Cantilan","Carmen","Carrascal","Cortes","Hinatuan","Lanuza","Lianga","Lingig","Madrid","Marihatag","San Agustin","San Miguel","Tagbina","Tago","Tandag City"],
  "Tarlac":["Anao","Bamban","Camiling","Capas","Concepcion","Gerona","La Paz","Mayantoc","Moncada","Paniqui","Pura","Ramos","San Clemente","San Jose","San Manuel","Santa Ignacia","Tarlac City","Victoria"],
  "Tawi-Tawi":["Bongao","Languyan","Mapun","Panglima Sugala","Sapa-Sapa","Sibutu","Simunul","Sitangkai","South Ubian","Tandubas","Turtle Islands"],
  "Zambales":["Botolan","Cabangan","Candelaria","Castillejos","Iba","Masinloc","Olongapo City","Palauig","San Antonio","San Felipe","San Marcelino","San Narciso","Santa Cruz","Subic"],
  "Zamboanga del Norte":["Baliguian","Dapitan City","Dipolog City","Godod","Gutalac","Jose Dalman","Kalawit","Katipunan","La Libertad","Labason","Liloy","Manukan","Mutia","Piñan","Polanco","President Manuel A. Roxas","Rizal","Salug","Sergio Osmeña Sr.","Siayan","Sibuco","Sibutad","Sindangan","Siocon","Sirawai","Tampilisan"],
  "Zamboanga del Sur":["Aurora","Bayog","Dimataling","Dinas","Dumalinao","Dumingag","Guipos","Josefina","Kumalarang","Labangan","Lapuyan","Mahayag","Margosatubig","Midsalip","Molave","Pagadian City","Pitogo","Ramon Magsaysay","San Miguel","San Pablo","Sominot","Tabina","Tukuran","Vincenzo A. Sagun","Zamboanga City"],
  "Zamboanga Sibugay":["Alicia","Buug","Diplahan","Imelda","Ipil","Kabasalan","Mabuhay","Malangas","Naga","Olutanga","Payao","Roseller Lim","Siay","Talusan","Titay","Tungawan"]
};

function filterCities(province) {
    const sel = document.getElementById('city');
    sel.innerHTML = '';
    if (!province || !CITIES[province]) {
        sel.innerHTML = '<option value="">— Select Province First —</option>';
        return;
    }
    sel.innerHTML = '<option value="">— Select City/Municipality —</option>';
    CITIES[province].forEach(c => {
        const o = document.createElement('option');
        o.value = c; o.textContent = c;
        sel.appendChild(o);
    });
}

// Init on load if province already selected (e.g. on error re-render)
window.addEventListener('load', () => {
    const prov = document.getElementById('province').value;
    const savedCity = <?=json_encode($v['cty'])?>;
    if (prov) {
        filterCities(prov);
        if (savedCity) document.getElementById('city').value = savedCity;
    }
});

// ── Age Auto-Compute ──────────────────────────────────────
document.getElementById('birthdate').addEventListener('change', function() {
    const val = this.value;
    if (!val) { document.getElementById('age-display').value = ''; return; }
    const dob = new Date(val);
    const age = Math.floor((new Date() - dob) / (365.25*24*60*60*1000));
    document.getElementById('age-display').value = age >= 0 ? age + ' years old' : '';
});
// Compute on load if editing
window.addEventListener('load', () => {
    const bd = document.getElementById('birthdate').value;
    if (bd) document.getElementById('birthdate').dispatchEvent(new Event('change'));
});

// ── Doctor Filter by Department ───────────────────────────
function filterDoctors(deptId) {
    const sel  = document.getElementById('doctor_id');
    const prev = sel.value;
    sel.innerHTML = '<option value="">— Select Physician —</option>';
    ALL_DOCTORS.forEach(d => {
        if (!deptId || String(d.department_id) === String(deptId)) {
            const o = document.createElement('option');
            o.value      = d.id;
            o.dataset.dept = d.department_id;
            o.textContent = d.full_name + ' (' + d.dept_name + ')';
            if (String(d.id) === String(prev)) o.selected = true;
            sel.appendChild(o);
        }
    });
}

// Init doctor filter if dept pre-selected
(function() {
    const deptSel = document.getElementById('department_id');
    if (deptSel.value) filterDoctors(deptSel.value);
})();

// ── Clear Form ────────────────────────────────────────────
function resetForm() {
    document.getElementById('age-display').value = '';
    filterDoctors('');
    // Reset radio to Outpatient
    document.getElementById('t-out').checked = true;
}
</script>
</body>
</html>
