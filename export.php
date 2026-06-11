<?php
/**
 * PATIENTDATAPROGRAM — export.php
 * Export patient records as CSV (opens in Excel)
 */
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db_connect.php';

$patients = $pdo->query("
    SELECT
        p.patient_no,
        p.full_name,
        p.birthdate,
        p.sex,
        p.civil_status,
        p.nationality,
        p.religion,
        p.blood_type,
        p.contact_number,
        p.email,
        p.address,
        p.city,
        p.province,
        p.emergency_name,
        p.emergency_relation,
        p.emergency_contact,
        p.patient_type,
        d.name   AS department,
        dr.full_name AS doctor,
        h.short_name AS hmo,
        p.hmo_card_no,
        p.chief_complaint,
        p.allergies,
        p.registered_at,
        p.registered_by
    FROM patients p
    LEFT JOIN departments  d  ON d.id  = p.department_id
    LEFT JOIN doctors      dr ON dr.id = p.doctor_id
    LEFT JOIN hmo_providers h  ON h.id  = p.hmo_id
    ORDER BY p.registered_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$filename = 'PatientRegistry_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM so Excel opens it correctly
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'Patient No.',
    'Full Name',
    'Birthdate',
    'Age',
    'Sex',
    'Civil Status',
    'Nationality',
    'Religion',
    'Blood Type',
    'Contact Number',
    'Email',
    'Address',
    'City',
    'Province',
    'Emergency Contact Name',
    'Emergency Relation',
    'Emergency Contact No.',
    'Patient Type',
    'Department',
    'Attending Physician',
    'HMO / Insurance',
    'HMO Card No.',
    'Chief Complaint',
    'Known Allergies',
    'Registered At',
    'Registered By',
]);

// Data rows
foreach ($patients as $p) {
    $age = '';
    if (!empty($p['birthdate'])) {
        $dob = new DateTime($p['birthdate']);
        $age = (new DateTime())->diff($dob)->y;
    }
    fputcsv($out, [
        $p['patient_no'],
        $p['full_name'],
        $p['birthdate'],
        $age,
        $p['sex'],
        $p['civil_status'],
        $p['nationality'],
        $p['religion'],
        $p['blood_type'],
        $p['contact_number'],
        $p['email'],
        $p['address'],
        $p['city'],
        $p['province'],
        $p['emergency_name'],
        $p['emergency_relation'],
        $p['emergency_contact'],
        $p['patient_type'],
        $p['department'],
        $p['doctor'],
        $p['hmo'],
        $p['hmo_card_no'],
        $p['chief_complaint'],
        $p['allergies'],
        $p['registered_at'],
        $p['registered_by'],
    ]);
}

fclose($out);
exit;
