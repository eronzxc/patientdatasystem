<?php
/**
 * PATIENTDATAPROGRAM — index.php
 * Patient Registry & Information System
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db_connect.php';

$action = $_POST['action'] ?? '';

// ─── AUTO-GENERATE PATIENT NUMBER ───────────────────────
function generatePatientNo(PDO $pdo): string {
    $year = date('Y');
    $row  = $pdo->query("SELECT COUNT(*) AS cnt FROM patients WHERE YEAR(registered_at) = $year")->fetch();
    $seq  = (int)$row['cnt'] + 1;
    return 'PDP-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

// ─── ACTION HANDLER (PRG) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // — Register Patient —
    if ($action === 'register_patient') {
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

        if ($fn && $bd && $sex) {
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
        } else {
            $_SESSION['error'] = "Full Name, Birthdate, and Sex are required.";
        }
        header('Location: index.php'); exit;
    }

    // — Edit Patient —
    if ($action === 'edit_patient') {
        $id  = (int)($_POST['patient_id']    ?? 0);
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

        if ($id && $fn && $bd && $sex) {
            $stmt = $pdo->prepare("UPDATE patients SET
                full_name=?,birthdate=?,sex=?,civil_status=?,nationality=?,religion=?,blood_type=?,
                contact_number=?,email=?,address=?,city=?,province=?,
                emergency_name=?,emergency_relation=?,emergency_contact=?,
                patient_type=?,department_id=?,doctor_id=?,hmo_id=?,hmo_card_no=?,
                chief_complaint=?,allergies=?
                WHERE id=?");
            $stmt->execute([$fn,$bd,$sex,$cs,$nat,$rel,$bt,$con,$em,$adr,$cty,$prv,
                            $enm,$erl,$eco,$pt,$did,$drid,$hid,$hcn,$cc,$alg,$id]);
            $_SESSION['success'] = "Patient <strong>" . htmlspecialchars($fn) . "</strong> updated.";
        } else {
            $_SESSION['error'] = "Full Name, Birthdate, and Sex are required.";
        }
        header('Location: index.php'); exit;
    }

    // — Delete Patient —
    if ($action === 'delete_patient' && $_SESSION['user_role'] === 'Admin') {
        $id = (int)($_POST['patient_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
            $_SESSION['success'] = "Patient record deleted.";
        }
        header('Location: index.php'); exit;
    }
}

// ─── FETCH DATA ──────────────────────────────────────────
$patients    = $pdo->query("
    SELECT p.*,
           d.name AS dept_name, d.code AS dept_code,
           dr.full_name AS doctor_name,
           h.short_name AS hmo_name
    FROM patients p
    LEFT JOIN departments  d  ON d.id  = p.department_id
    LEFT JOIN doctors      dr ON dr.id = p.doctor_id
    LEFT JOIN hmo_providers h ON h.id  = p.hmo_id
    ORDER BY p.registered_at ASC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$doctors     = $pdo->query("SELECT dr.*, d.name AS dept_name FROM doctors dr JOIN departments d ON d.id=dr.department_id WHERE dr.is_active=1 ORDER BY dr.full_name ASC")->fetchAll();
$hmos        = $pdo->query("SELECT * FROM hmo_providers WHERE is_active=1 ORDER BY name ASC")->fetchAll();

// Stats
$total   = count($patients);
$out     = count(array_filter($patients, fn($p) => $p['patient_type'] === 'Outpatient'));
$inp     = count(array_filter($patients, fn($p) => $p['patient_type'] === 'Inpatient'));
$emr     = count(array_filter($patients, fn($p) => $p['patient_type'] === 'Emergency'));

$flash_success = $_SESSION['success'] ?? null; unset($_SESSION['success']);
$flash_error   = $_SESSION['error']   ?? null; unset($_SESSION['error']);

$patients_json    = json_encode($patients,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$departments_json = json_encode($departments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$doctors_json     = json_encode($doctors,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$role = $_SESSION['user_role'];
$can_write  = in_array($role, ['Admin','Nurse','Encoder']);
$can_delete = $role === 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PatientDataProgram — Patient Registry</title>
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
/* SHELL */
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
/* STAT CHIPS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-bottom:18px;}
.sc{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;border-top:3px solid var(--border);}
.sc.s-total{border-top-color:var(--navy);}
.sc.s-out{border-top-color:var(--emerald);}
.sc.s-in{border-top-color:var(--blue);}
.sc.s-emr{border-top-color:var(--red);}
.sc-lbl{font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text-3);}
.sc-val{font-size:26px;font-weight:800;line-height:1;margin-top:5px;}
.s-total .sc-val{color:var(--navy);}
.s-out   .sc-val{color:var(--emerald);}
.s-in    .sc-val{color:var(--blue);}
.s-emr   .sc-val{color:var(--red);}
/* FILTER BAR */
.filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
.filter-bar input{flex:1;min-width:200px;padding:8px 12px 8px 34px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:13px;font-family:var(--font);color:var(--text-1);background:var(--white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238FA3BA' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 10px center;outline:none;transition:border-color .15s,box-shadow .15s;}
.filter-bar input:focus{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(26,127,90,.10);}
.filter-bar select{padding:8px 10px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:12.5px;font-family:var(--font);color:var(--text-2);background:var(--white);outline:none;cursor:pointer;transition:border-color .15s;}
.filter-bar select:focus{border-color:var(--emerald);}
/* CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.card-hdr{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--border);}
.card-hdr h2{font-size:12.5px;font-weight:700;color:var(--navy);}
.card-hdr svg{width:15px;height:15px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.ch-right{margin-left:auto;font-size:11px;color:var(--text-3);}
/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);background:var(--surface);border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:11px 14px;font-size:13px;color:var(--text-1);border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#F8FAFB;}
.no-data{text-align:center;padding:40px;color:var(--text-3);font-size:13px;}
/* BADGES */
.badge{display:inline-block;padding:2px 9px;border-radius:99px;font-size:10.5px;font-weight:700;}
.b-out{background:var(--emerald-light);color:var(--emerald);border:1px solid var(--emerald-border);}
.b-in{background:var(--blue-light);color:var(--blue);border:1px solid var(--blue-border);}
.b-emr{background:var(--red-light);color:var(--red);border:1px solid var(--red-border);}
.b-m{background:#EBF2FC;color:#1A5BA8;border:1px solid rgba(26,91,168,.2);}
.b-f{background:#F5EBF9;color:#7B2D9E;border:1px solid rgba(123,45,158,.2);}
.b-o{background:var(--gold-light);color:var(--gold);border:1px solid var(--gold-border);}
/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--radius-sm);font-size:12.5px;font-weight:600;font-family:var(--font);cursor:pointer;border:1px solid transparent;transition:background .12s,box-shadow .12s;text-decoration:none;}
.btn-primary{background:var(--emerald);color:#fff;}
.btn-primary:hover{background:var(--emerald-mid);box-shadow:0 2px 10px rgba(26,127,90,.25);}
.btn-secondary{background:var(--white);color:var(--text-2);border-color:var(--border-mid);}
.btn-secondary:hover{background:var(--surface);}
.btn-warn{background:var(--gold-light);color:var(--gold);border-color:var(--gold-border);}
.btn-warn:hover{background:#fce9c5;}
.btn-danger{background:var(--red-light);color:var(--red);border-color:var(--red-border);}
.btn-danger:hover{background:#fad7d3;}
.btn-sm{padding:4px 10px;font-size:11.5px;}
.btn-info{background:var(--blue-light);color:var(--blue);border-color:var(--blue-border);}
.btn-info:hover{background:#d4e6f9;}
/* MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(13,33,55,.5);z-index:900;align-items:flex-start;justify-content:center;padding:30px 16px;overflow-y:auto;}
.modal-bg.open{display:flex;}
.modal-box{background:var(--white);border-radius:var(--radius);width:100%;max-width:700px;box-shadow:0 10px 50px rgba(0,0,0,.22);margin:auto;}
.modal-hdr{display:flex;align-items:center;gap:9px;padding:16px 22px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--white);z-index:10;}
.modal-hdr h3{font-size:15px;font-weight:700;color:var(--navy);}
.modal-hdr svg{width:16px;height:16px;fill:none;stroke:var(--emerald);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.modal-close{margin-left:auto;background:none;border:none;cursor:pointer;color:var(--text-3);font-size:22px;line-height:1;padding:0 4px;}
.modal-close:hover{color:var(--text-1);}
.modal-body{padding:22px;}
/* FORM */
.sect-title{font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--text-3);margin:18px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border);}
.sect-title:first-child{margin-top:0;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-grid.g3{grid-template-columns:1fr 1fr 1fr;}
.form-grid.g1{grid-template-columns:1fr;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:11.5px;font-weight:600;color:var(--text-2);}
.fg input,.fg select,.fg textarea{padding:8px 10px;border:1px solid var(--border-mid);border-radius:var(--radius-sm);font-size:13px;font-family:var(--font);color:var(--text-1);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--emerald);box-shadow:0 0 0 3px rgba(26,127,90,.12);background:var(--white);}
.fg textarea{resize:vertical;min-height:64px;}
.req{color:var(--red);}
.form-actions{display:flex;gap:8px;margin-top:18px;justify-content:flex-end;}
/* DETAIL MODAL */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
.detail-row{padding:9px 14px;border-bottom:1px solid var(--border);}
.detail-row:nth-child(odd){background:var(--surface);}
.detail-lbl{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-3);margin-bottom:3px;}
.detail-val{font-size:13px;color:var(--text-1);}
.detail-val.mono{font-family:var(--font-mono);font-size:12px;}
.span-2{grid-column:1/-1;}
@media(max-width:600px){.form-grid,.form-grid.g3,.detail-grid{grid-template-columns:1fr;}.stats{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>
<div class="app">

<!-- SIDEBAR -->
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
  <a href="index.php" class="nav-item active">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    Patient Registry
  </a>
  <?php if($can_write):?>
  <a href="register.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
    Register Patient
  </a>
  <?php endif;?>
  <?php if(in_array($role,['Admin'])):?>
  <div class="nav-label">Administration</div>
  <a href="doctors.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
    Doctors &amp; Departments
  </a>
  <a href="users.php" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <?php endif;?>
  <div class="sb-footer">PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
    <span class="tb-title">Dashboard</span>
    <span class="tb-bc">/ Patient Registry</span>
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
      <span><?=$flash_success?></span>
    </div>
    <?php endif;?>
    <?php if($flash_error):?>
    <div class="flash err">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span><?=$flash_error?></span>
    </div>
    <?php endif;?>

    <!-- Page Header -->
    <div class="page-hdr">
      <div>
        <h1>Patient Registry</h1>
        <p>Register, view, and manage patient records.</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <a href="export.php" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;text-decoration:none;padding:8px 14px;font-size:12.5px;">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export CSV
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats">
      <div class="sc s-total"><div class="sc-lbl">Total Patients</div><div class="sc-val"><?=$total?></div></div>
      <div class="sc s-out"><div class="sc-lbl">Outpatient</div><div class="sc-val"><?=$out?></div></div>
      <div class="sc s-in"><div class="sc-lbl">Inpatient</div><div class="sc-val"><?=$inp?></div></div>
      <div class="sc s-emr"><div class="sc-lbl">Emergency</div><div class="sc-val"><?=$emr?></div></div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <input type="text" id="search-q" placeholder="Search by name (first name, last name) or patient number" oninput="applyFilters()">
      <select id="f-type" onchange="applyFilters()">
        <option value="">All Types</option>
        <option value="Outpatient">Outpatient</option>
        <option value="Inpatient">Inpatient</option>
        <option value="Emergency">Emergency</option>
      </select>
      <select id="f-dept" onchange="applyFilters()">
        <option value="">All Departments</option>
        <?php foreach($departments as $d):?>
        <option value="<?=htmlspecialchars($d['name'])?>"><?=htmlspecialchars($d['name'])?></option>
        <?php endforeach;?>
      </select>
      <select id="f-doc" onchange="applyFilters()">
        <option value="">All Doctors</option>
        <?php foreach($doctors as $d):?>
        <option value="<?=htmlspecialchars($d['full_name'])?>"><?=htmlspecialchars($d['full_name'])?></option>
        <?php endforeach;?>
      </select>
      <select id="f-sex" onchange="applyFilters()">
        <option value="">All</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-hdr">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h2>Patient List</h2>
        <span class="ch-right" id="rec-count"><?=$total?> record<?=$total!==1?'s':''?></span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Patient No.</th>
              <th>Full Name</th>
              <th>Age / Sex</th>
              <th>Type</th>
              <th>Department</th>
              <th>Doctor</th>
              <th>HMO</th>
              <th>Registered</th>
              <th style="width:110px;"></th>
            </tr>
          </thead>
          <tbody id="patient-tbody">
            <?php if(empty($patients)):?>
            <tr><td colspan="9" class="no-data">No patients registered yet.</td></tr>
            <?php else: foreach($patients as $i=>$p):
              $dob  = new DateTime($p['birthdate']);
              $age  = (new DateTime())->diff($dob)->y;
              $tclass = ['Outpatient'=>'b-out','Inpatient'=>'b-in','Emergency'=>'b-emr'][$p['patient_type']] ?? 'b-out';
              $sclass = ['Male'=>'b-m','Female'=>'b-f','Other'=>'b-o'][$p['sex']] ?? 'b-o';
            ?>
            <tr
              data-name="<?=strtolower(htmlspecialchars($p['full_name']))?>"
              data-pno="<?=strtolower(htmlspecialchars($p['patient_no']))?>"
              data-type="<?=htmlspecialchars($p['patient_type'])?>"
              data-dept="<?=htmlspecialchars($p['dept_name']??'')?>"
              data-doc="<?=htmlspecialchars($p['doctor_name']??'')?>"
              data-sex="<?=htmlspecialchars($p['sex'])?>">
              <td style="font-family:var(--font-mono);font-size:11.5px;color:var(--text-2);"><?=htmlspecialchars($p['patient_no'])?></td>
              <td><strong><?=htmlspecialchars($p['full_name'])?></strong></td>
              <td><?=$age?> y/o &nbsp;<span class="badge <?=$sclass?>"><?=htmlspecialchars($p['sex'])?></span></td>
              <td><span class="badge <?=$tclass?>"><?=htmlspecialchars($p['patient_type'])?></span></td>
              <td style="font-size:12px;color:var(--text-2);"><?=htmlspecialchars($p['dept_name'] ?? '—')?></td>
              <td style="font-size:12px;"><?=htmlspecialchars($p['doctor_name'] ?? '—')?></td>
              <td style="font-size:11.5px;color:var(--text-2);"><?=htmlspecialchars($p['hmo_name'] ?? '—')?></td>
              <td style="font-size:11.5px;color:var(--text-3);"><?=date('M d, Y',strtotime($p['registered_at']))?></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button class="btn btn-info btn-sm" onclick='openDetail(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)'>View</button>
                  <?php if($can_write):?>
                  <button class="btn btn-warn btn-sm" onclick='openEditModal(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)'>Edit</button>
                  <?php endif;?>
                  <?php if($can_delete):?>
                  <form method="POST" onsubmit="return confirm('Delete this patient record?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete_patient">
                    <input type="hidden" name="patient_id" value="<?=$p['id']?>">
                    <button type="submit" class="btn btn-danger btn-sm">Del</button>
                  </form>
                  <?php endif;?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif;?>
          </tbody>
        </table>
      </div>
    </div>
  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<!-- ═══════════════════════════════════════════════════════════
     REGISTER / EDIT MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal-bg" id="reg-modal">
  <div class="modal-box">
    <div class="modal-hdr">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      <h3 id="modal-title">Register Patient</h3>
      <button class="modal-close" onclick="closeRegModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="index.php" id="patient-form">
        <input type="hidden" name="action" id="form-action" value="register_patient">
        <input type="hidden" name="patient_id" id="form-pid" value="">

        <div class="sect-title">Personal Information</div>
        <div class="form-grid g1">
          <div class="fg"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" id="f-full-name" placeholder="Last Name, First Name Middle Name" required></div>
        </div>
        <div class="form-grid g3" style="margin-top:12px;">
          <div class="fg"><label>Birthdate <span class="req">*</span></label><input type="date" name="birthdate" id="f-birthdate" required></div>
          <div class="fg"><label>Sex <span class="req">*</span></label>
            <select name="sex" id="f-sex-f" required>
              <option value="">— Select —</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="fg"><label>Civil Status</label>
            <select name="civil_status" id="f-civil">
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
              <option value="Separated">Separated</option>
              <option value="Annulled">Annulled</option>
            </select>
          </div>
        </div>
        <div class="form-grid g3" style="margin-top:12px;">
          <div class="fg"><label>Blood Type</label>
            <select name="blood_type" id="f-bt">
              <option value="Unknown">Unknown</option>
              <option value="A+">A+</option><option value="A-">A-</option>
              <option value="B+">B+</option><option value="B-">B-</option>
              <option value="AB+">AB+</option><option value="AB-">AB-</option>
              <option value="O+">O+</option><option value="O-">O-</option>
            </select>
          </div>
          <div class="fg"><label>Nationality</label><input type="text" name="nationality" id="f-nat" value="Filipino"></div>
          <div class="fg"><label>Religion</label>
            <select name="religion" id="f-rel">
              <option value="">— Select —</option>
              <option value="Roman Catholic">Roman Catholic</option>
              <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
              <option value="Islam">Islam</option>
              <option value="Born Again Christian">Born Again Christian</option>
              <option value="Seventh-day Adventist">Seventh-day Adventist</option>
              <option value="Protestant">Protestant</option>
              <option value="Jehovah's Witness">Jehovah's Witness</option>
              <option value="Aglipayan">Aglipayan</option>
              <option value="Buddhism">Buddhism</option>
              <option value="Other">Other</option>
              <option value="None">None</option>
            </select>
          </div>
        </div>

        <div class="sect-title">Contact Information</div>
        <div class="form-grid">
          <div class="fg"><label>Contact Number</label><input type="text" name="contact_number" id="f-con" placeholder="09XX XXX XXXX"></div>
          <div class="fg"><label>Email Address</label><input type="email" name="email" id="f-em" placeholder="email@example.com"></div>
        </div>
        <div class="form-grid g1" style="margin-top:12px;">
          <div class="fg"><label>Street Address</label><input type="text" name="address" id="f-adr" placeholder="House No., Street, Barangay"></div>
        </div>
        <div class="form-grid" style="margin-top:12px;">
          <div class="fg"><label>City / Municipality</label><input type="text" name="city" id="f-city" placeholder="e.g. Lipa City"></div>
          <div class="fg"><label>Province</label>
            <select name="province" id="f-prov">
              <option value="">— Select Province —</option>
              <option>Metro Manila (NCR)</option>
              <option>Batangas</option><option>Cavite</option><option>Laguna</option>
              <option>Quezon</option><option>Rizal</option><option>Bulacan</option>
              <option>Pampanga</option><option>Tarlac</option><option>Nueva Ecija</option>
              <option>Bataan</option><option>Zambales</option><option>Ilocos Norte</option>
              <option>Ilocos Sur</option><option>La Union</option><option>Pangasinan</option>
              <option>Cagayan</option><option>Isabela</option><option>Benguet</option>
              <option>Ifugao</option><option>Mountain Province</option>
              <option>Cebu</option><option>Bohol</option><option>Negros Occidental</option>
              <option>Negros Oriental</option><option>Iloilo</option><option>Antique</option>
              <option>Capiz</option><option>Aklan</option><option>Guimaras</option>
              <option>Davao del Sur</option><option>Davao del Norte</option><option>Davao de Oro</option>
              <option>Cotabato</option><option>South Cotabato</option><option>Sarangani</option>
              <option>Zamboanga del Norte</option><option>Zamboanga del Sur</option>
              <option>Misamis Oriental</option><option>Misamis Occidental</option>
              <option>Bukidnon</option><option>Lanao del Norte</option>
              <option>Eastern Samar</option><option>Leyte</option><option>Samar</option>
              <option>Biliran</option><option>Northern Samar</option><option>Southern Leyte</option>
              <option>Albay</option><option>Camarines Norte</option><option>Camarines Sur</option>
              <option>Catanduanes</option><option>Masbate</option><option>Sorsogon</option>
              <option>Palawan</option><option>Oriental Mindoro</option><option>Occidental Mindoro</option>
              <option>Marinduque</option><option>Romblon</option>
              <option>Aurora</option><option>Quirino</option>
              <option>Other</option>
            </select>
          </div>
        </div>

        <div class="sect-title">Emergency Contact</div>
        <div class="form-grid g3">
          <div class="fg"><label>Contact Person</label><input type="text" name="emergency_name" id="f-enm" placeholder="Full name"></div>
          <div class="fg"><label>Relationship</label>
            <select name="emergency_relation" id="f-erl">
              <option value="">— Select —</option>
              <option value="Spouse">Spouse</option>
              <option value="Parent">Parent</option>
              <option value="Child">Child</option>
              <option value="Sibling">Sibling</option>
              <option value="Relative">Relative</option>
              <option value="Guardian">Guardian</option>
              <option value="Friend">Friend</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="fg"><label>Contact Number</label><input type="text" name="emergency_contact" id="f-eco" placeholder="09XX XXX XXXX"></div>
        </div>

        <div class="sect-title">Clinical Information</div>
        <div class="form-grid g3">
          <div class="fg"><label>Patient Type <span class="req">*</span></label>
            <select name="patient_type" id="f-pt" required>
              <option value="Outpatient">Outpatient</option>
              <option value="Inpatient">Inpatient</option>
              <option value="Emergency">Emergency</option>
            </select>
          </div>
          <div class="fg"><label>Department</label>
            <select name="department_id" id="f-dept-f" onchange="filterDoctors(this.value)">
              <option value="">— Select Department —</option>
              <?php foreach($departments as $d):?>
              <option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="fg"><label>Attending Physician</label>
            <select name="doctor_id" id="f-doc-f">
              <option value="">— Select Doctor —</option>
              <?php foreach($doctors as $d):?>
              <option value="<?=$d['id']?>" data-dept="<?=$d['department_id']?>"><?=htmlspecialchars($d['full_name'])?></option>
              <?php endforeach;?>
            </select>
          </div>
        </div>
        <div class="form-grid" style="margin-top:12px;">
          <div class="fg"><label>HMO / Insurance</label>
            <select name="hmo_id" id="f-hmo">
              <option value="">— Select HMO —</option>
              <?php foreach($hmos as $h):?>
              <option value="<?=$h['id']?>"><?=htmlspecialchars($h['name'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="fg"><label>HMO Card / Policy No.</label><input type="text" name="hmo_card_no" id="f-hcn" placeholder="Card or policy number"></div>
        </div>
        <div class="form-grid g1" style="margin-top:12px;">
          <div class="fg"><label>Chief Complaint / Reason for Visit</label><textarea name="chief_complaint" id="f-cc" placeholder="Describe chief complaint…"></textarea></div>
        </div>
        <div class="form-grid g1" style="margin-top:12px;">
          <div class="fg"><label>Known Allergies</label><textarea name="allergies" id="f-alg" placeholder="e.g. Penicillin, NSAIDs, Shellfish… or None"></textarea></div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary" id="submit-btn">Register Patient</button>
          <button type="button" class="btn btn-secondary" onclick="closeRegModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══ VIEW DETAIL MODAL ═══════════════════════════════ -->
<div class="modal-bg" id="detail-modal">
  <div class="modal-box">
    <div class="modal-hdr">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <h3>Patient Details</h3>
      <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
        <button class="btn btn-ghost btn-sm" id="btn-print-pdf" onclick="printPatientPDF()" style="display:flex;align-items:center;gap:5px;font-size:12px;">
          <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Print / PDF
        </button>
        <button class="modal-close" onclick="closeDetail()">&times;</button>
      </div>
    </div>
    <div class="modal-body" id="detail-content"></div>
  </div>
</div>

<script>
const DOCTORS = <?=$doctors_json?>;

// ── FILTER TABLE ──────────────────────────────────────────
function applyFilters() {
    const q    = document.getElementById('search-q').value.toLowerCase();
    const type = document.getElementById('f-type').value;
    const dept = document.getElementById('f-dept').value.toLowerCase();
    const doc  = document.getElementById('f-doc').value.toLowerCase();
    const sex  = document.getElementById('f-sex').value;
    let vis = 0;
    document.querySelectorAll('#patient-tbody tr[data-name]').forEach(r => {
        const nameParts = r.dataset.name.split(/[\s,]+/).filter(Boolean);
        const nameMatch = !q || nameParts.some(part => part.startsWith(q)) || r.dataset.name.includes(q) || r.dataset.pno.includes(q);
        const ok =
            nameMatch &&
            (!type || r.dataset.type === type) &&
            (!dept || r.dataset.dept.toLowerCase().includes(dept)) &&
            (!doc  || r.dataset.doc.toLowerCase().includes(doc)) &&
            (!sex  || r.dataset.sex === sex);
        r.style.display = ok ? '' : 'none';
        if (ok) vis++;
    });
    document.getElementById('rec-count').textContent = vis + ' record' + (vis !== 1 ? 's' : '');
}

// ── DOCTOR FILTER BY DEPT ─────────────────────────────────
function filterDoctors(deptId) {
    const sel = document.getElementById('f-doc-f');
    const prev = sel.value;
    sel.innerHTML = '<option value="">— Select Doctor —</option>';
    DOCTORS.forEach(d => {
        if (!deptId || String(d.department_id) === String(deptId)) {
            const o = document.createElement('option');
            o.value = d.id;
            o.dataset.dept = d.department_id;
            o.textContent = d.full_name + (d.dept_name ? ' (' + d.dept_name + ')' : '');
            if (String(d.id) === String(prev)) o.selected = true;
            sel.appendChild(o);
        }
    });
}

// ── REGISTER MODAL ────────────────────────────────────────
function openRegModal() {
    document.getElementById('modal-title').textContent = 'Register Patient';
    document.getElementById('form-action').value = 'register_patient';
    document.getElementById('form-pid').value = '';
    document.getElementById('submit-btn').textContent = 'Register Patient';
    document.getElementById('patient-form').reset();
    filterDoctors('');
    document.getElementById('reg-modal').classList.add('open');
}
function closeRegModal() { document.getElementById('reg-modal').classList.remove('open'); }

function openEditModal(p) {
    document.getElementById('modal-title').textContent = 'Edit Patient';
    document.getElementById('form-action').value = 'edit_patient';
    document.getElementById('form-pid').value   = p.id;
    document.getElementById('submit-btn').textContent = 'Save Changes';

    setVal('f-full-name', p.full_name);
    setVal('f-birthdate', p.birthdate);
    setVal('f-sex-f',    p.sex);
    setVal('f-civil',    p.civil_status);
    setVal('f-nat',      p.nationality);
    setVal('f-rel',      p.religion);
    setVal('f-bt',       p.blood_type);
    setVal('f-con',      p.contact_number);
    setVal('f-em',       p.email);
    setVal('f-adr',      p.address);
    setVal('f-city',     p.city);
    setVal('f-prov',     p.province);
    setVal('f-enm',      p.emergency_name);
    setVal('f-erl',      p.emergency_relation);
    setVal('f-eco',      p.emergency_contact);
    setVal('f-pt',       p.patient_type);
    setVal('f-dept-f',   p.department_id);
    filterDoctors(p.department_id);
    setVal('f-doc-f',    p.doctor_id);
    setVal('f-hmo',      p.hmo_id);
    setVal('f-hcn',      p.hmo_card_no);
    setVal('f-cc',       p.chief_complaint);
    setVal('f-alg',      p.allergies);

    document.getElementById('reg-modal').classList.add('open');
}
function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val ?? '';
}

// ── DETAIL MODAL ──────────────────────────────────────────
let currentPatient = null;

function printPatientPDF() {
    if (!currentPatient) return;
    const p = currentPatient;
    const dob = new Date(p.birthdate);
    const age = Math.floor((new Date() - dob) / (365.25*24*60*60*1000));
    const now = new Date().toLocaleString('en-PH', {dateStyle:'long', timeStyle:'short'});
    const typeColor = {Outpatient:'#1A7F5A', Inpatient:'#1A5BA8', Emergency:'#C0392B'}[p.patient_type] || '#333';
    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Patient Record - ${p.patient_no}</title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:'Segoe UI',sans-serif;font-size:12px;color:#1a1a2e;background:#fff;padding:24px;}
      .header{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #0D2137;padding-bottom:12px;margin-bottom:16px;}
      .logo-area{display:flex;align-items:center;gap:10px;}
      .logo-box{width:36px;height:36px;background:#0D2137;border-radius:7px;display:flex;align-items:center;justify-content:center;}
      .logo-box svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
      .sys-name{font-size:13px;font-weight:800;color:#0D2137;letter-spacing:.05em;}
      .sys-sub{font-size:9px;color:#888;margin-top:2px;}
      .print-meta{text-align:right;font-size:10px;color:#666;}
      .patient-no{font-size:11px;font-family:monospace;color:#555;margin-bottom:4px;}
      .patient-name{font-size:20px;font-weight:800;color:#0D2137;margin-bottom:4px;}
      .type-badge{display:inline-block;padding:2px 10px;border-radius:99px;font-size:10px;font-weight:700;color:#fff;background:${typeColor};}
      .section{margin-top:16px;}
      .section-title{font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#888;border-bottom:1px solid #e0e0e0;padding-bottom:4px;margin-bottom:10px;}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;}
      .field{margin-bottom:2px;}
      .field-lbl{font-size:9.5px;color:#888;font-weight:600;text-transform:uppercase;letter-spacing:.06em;}
      .field-val{font-size:12px;color:#0D2137;font-weight:500;margin-top:1px;}
      .field.span-2{grid-column:span 2;}
      .footer{margin-top:24px;border-top:1px solid #eee;padding-top:10px;font-size:9.5px;color:#aaa;display:flex;justify-content:space-between;}
      @media print{body{padding:10px;} @page{margin:1cm;}}
    </style></head><body>
    <div class="header">
      <div class="logo-area">
        <div class="logo-box"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
        <div><div class="sys-name">PATIENTDATAPROGRAM</div><div class="sys-sub">Patient Registry &amp; Information System</div></div>
      </div>
      <div class="print-meta"><strong>PATIENT RECORD</strong><br>Printed: ${now}</div>
    </div>
    <div class="patient-no">${p.patient_no}</div>
    <div class="patient-name">${p.full_name}</div>
    <div style="margin-top:6px;"><span class="type-badge">${p.patient_type}</span></div>

    <div class="section">
      <div class="section-title">Personal Information</div>
      <div class="grid">
        <div class="field"><div class="field-lbl">Birthdate</div><div class="field-val">${p.birthdate} (${age} y/o)</div></div>
        <div class="field"><div class="field-lbl">Sex</div><div class="field-val">${p.sex}</div></div>
        <div class="field"><div class="field-lbl">Civil Status</div><div class="field-val">${p.civil_status||'—'}</div></div>
        <div class="field"><div class="field-lbl">Blood Type</div><div class="field-val">${p.blood_type||'—'}</div></div>
        <div class="field"><div class="field-lbl">Nationality</div><div class="field-val">${p.nationality||'—'}</div></div>
        <div class="field"><div class="field-lbl">Religion</div><div class="field-val">${p.religion||'—'}</div></div>
        <div class="field"><div class="field-lbl">Contact No.</div><div class="field-val">${p.contact_number||'—'}</div></div>
        <div class="field"><div class="field-lbl">Email</div><div class="field-val">${p.email||'—'}</div></div>
        <div class="field span-2"><div class="field-lbl">Address</div><div class="field-val">${[p.address,p.city,p.province].filter(Boolean).join(', ')||'—'}</div></div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Emergency Contact</div>
      <div class="grid">
        <div class="field"><div class="field-lbl">Name</div><div class="field-val">${p.emergency_name||'—'}</div></div>
        <div class="field"><div class="field-lbl">Relationship</div><div class="field-val">${p.emergency_relation||'—'}</div></div>
        <div class="field"><div class="field-lbl">Contact No.</div><div class="field-val">${p.emergency_contact||'—'}</div></div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Medical Information</div>
      <div class="grid">
        <div class="field"><div class="field-lbl">Department</div><div class="field-val">${p.dept_name||'—'}</div></div>
        <div class="field"><div class="field-lbl">Attending Physician</div><div class="field-val">${p.doctor_name||'—'}</div></div>
        <div class="field"><div class="field-lbl">HMO / Insurance</div><div class="field-val">${p.hmo_name||'—'}</div></div>
        <div class="field"><div class="field-lbl">HMO Card No.</div><div class="field-val">${p.hmo_card_no||'—'}</div></div>
        <div class="field span-2"><div class="field-lbl">Chief Complaint</div><div class="field-val">${p.chief_complaint||'—'}</div></div>
        <div class="field span-2"><div class="field-lbl">Known Allergies</div><div class="field-val">${p.allergies||'None'}</div></div>
      </div>
    </div>

    <div class="section">
      <div class="section-title">Registration</div>
      <div class="grid">
        <div class="field"><div class="field-lbl">Registered</div><div class="field-val">${p.registered_at?.substring(0,10)||'—'}</div></div>
        <div class="field"><div class="field-lbl">Registered By</div><div class="field-val">${p.registered_by||'—'}</div></div>
      </div>
    </div>

    <div class="footer"><span>PatientDataProgram v1.0 &nbsp;·&nbsp; OJT Prototype</span><span>${p.patient_no}</span></div>
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
    const w = window.open('','_blank','width=800,height=900');
    w.document.write(html);
    w.document.close();
}

function openDetail(p) {
    currentPatient = p;
    const dob = new Date(p.birthdate);
    const age = Math.floor((new Date() - dob) / (365.25*24*60*60*1000));
    const tmap = {Outpatient:'b-out',Inpatient:'b-in',Emergency:'b-emr'};
    document.getElementById('detail-content').innerHTML = `
        <div class="detail-grid">
            <div class="detail-row"><div class="detail-lbl">Patient No.</div><div class="detail-val mono">${esc(p.patient_no)}</div></div>
            <div class="detail-row"><div class="detail-lbl">Patient Type</div><div class="detail-val"><span class="badge ${tmap[p.patient_type]||'b-out'}">${esc(p.patient_type)}</span></div></div>
            <div class="detail-row span-2"><div class="detail-lbl">Full Name</div><div class="detail-val" style="font-size:15px;font-weight:700;">${esc(p.full_name)}</div></div>
            <div class="detail-row"><div class="detail-lbl">Birthdate</div><div class="detail-val">${esc(p.birthdate)} (${age} y/o)</div></div>
            <div class="detail-row"><div class="detail-lbl">Sex</div><div class="detail-val">${esc(p.sex)}</div></div>
            <div class="detail-row"><div class="detail-lbl">Civil Status</div><div class="detail-val">${esc(p.civil_status||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Blood Type</div><div class="detail-val">${esc(p.blood_type||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Nationality</div><div class="detail-val">${esc(p.nationality||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Religion</div><div class="detail-val">${esc(p.religion||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Contact No.</div><div class="detail-val">${esc(p.contact_number||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Email</div><div class="detail-val">${esc(p.email||'—')}</div></div>
            <div class="detail-row span-2"><div class="detail-lbl">Address</div><div class="detail-val">${[p.address,p.city,p.province].filter(Boolean).map(esc).join(', ')||'—'}</div></div>
            <div class="detail-row"><div class="detail-lbl">Emergency Contact</div><div class="detail-val">${esc(p.emergency_name||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Relationship</div><div class="detail-val">${esc(p.emergency_relation||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Emergency No.</div><div class="detail-val">${esc(p.emergency_contact||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Department</div><div class="detail-val">${esc(p.dept_name||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Attending Physician</div><div class="detail-val">${esc(p.doctor_name||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">HMO / Insurance</div><div class="detail-val">${esc(p.hmo_name||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">HMO Card No.</div><div class="detail-val mono">${esc(p.hmo_card_no||'—')}</div></div>
            <div class="detail-row span-2"><div class="detail-lbl">Chief Complaint</div><div class="detail-val">${esc(p.chief_complaint||'—')}</div></div>
            <div class="detail-row span-2"><div class="detail-lbl">Known Allergies</div><div class="detail-val">${esc(p.allergies||'None')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Registered</div><div class="detail-val">${esc(p.registered_at?.substring(0,10)||'—')}</div></div>
            <div class="detail-row"><div class="detail-lbl">Registered By</div><div class="detail-val">${esc(p.registered_by||'—')}</div></div>
        </div>`;
    document.getElementById('detail-modal').classList.add('open');
}
function closeDetail() { document.getElementById('detail-modal').classList.remove('open'); }

function esc(str) {
    return String(str??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
document.addEventListener('keydown', e => { if(e.key==='Escape'){closeRegModal();closeDetail();} });
</script>
</body>
</html>