-- ============================================================
-- PATIENTDATAPROGRAM — Database Setup
-- Run this ONCE in phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS patientdataprogram
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE patientdataprogram;

-- ── DEPARTMENTS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL UNIQUE,
    code       VARCHAR(20)  NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO departments (name, code) VALUES
('Emergency Medicine',           'EM'),
('Internal Medicine',            'IM'),
('Pediatrics',                   'PEDIA'),
('Obstetrics & Gynecology',      'OB-GYN'),
('Surgery',                      'SURG'),
('Orthopedics',                  'ORTHO'),
('Cardiology',                   'CARDIO'),
('Neurology',                    'NEURO'),
('Pulmonology',                  'PULMO'),
('Nephrology',                   'NEPHRO'),
('Gastroenterology',             'GASTRO'),
('Ophthalmology',                'OPHTHA'),
('Ear, Nose & Throat (ENT)',     'ENT'),
('Dermatology',                  'DERM'),
('Psychiatry',                   'PSYCH'),
('Oncology',                     'ONCO'),
('Urology',                      'URO'),
('Endocrinology',                'ENDO'),
('Rheumatology',                 'RHEUM'),
('Rehabilitation Medicine',      'REHAB'),
('Radiology',                    'RAD'),
('Pathology & Laboratory',       'PATH'),
('Neonatology / NICU',           'NICU'),
('Family Medicine',              'FM'),
('Infectious Disease',           'ID');

-- ── DOCTORS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS doctors (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(150) NOT NULL,
    department_id INT          NOT NULL,
    prc_number    VARCHAR(30)  NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- ── HMO / INSURANCE PROVIDERS ────────────────────────────
CREATE TABLE IF NOT EXISTS hmo_providers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    short_name VARCHAR(60)  NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1
);

INSERT INTO hmo_providers (name, short_name) VALUES
('PhilHealth (PHIC)',                          'PhilHealth'),
('Maxicare Healthcare Corporation',            'Maxicare'),
('Intellicare (Asalus Corporation)',           'Intellicare'),
('Medicard Philippines',                       'Medicard'),
('PhilCare Health Systems',                   'PhilCare'),
('Caritas Health Shield',                     'Caritas'),
('Pacific Cross Health Insurance',            'Pacific Cross'),
('Value Care Health Systems',                 'Value Care'),
('Health Maintenance Inc. (HMI)',             'HMI'),
('Generali Pilipinas',                        'Generali'),
('Sun Life Grepa Healthcare',                 'Sun Life Grepa'),
('AXA Philippines',                           'AXA'),
('Insular Health Care',                       'Insular Health'),
('FamilyDoc',                                 'FamilyDoc'),
('Eastwest Healthcare',                       'Eastwest'),
('Kaiser International Healthgroup',          'Kaiser'),
('Cocolife Healthcare',                       'Cocolife'),
('Government Service Insurance System (GSIS)','GSIS'),
('Social Security System (SSS)',              'SSS'),
('None / Self-Pay',                           'Self-Pay');

-- ── PATIENTS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS patients (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_no      VARCHAR(20)  NOT NULL UNIQUE,
    full_name       VARCHAR(200) NOT NULL,
    birthdate       DATE         NOT NULL,
    sex             ENUM('Male','Female','Other') NOT NULL,
    civil_status    ENUM('Single','Married','Widowed','Separated','Annulled') DEFAULT 'Single',
    nationality     VARCHAR(80)  DEFAULT 'Filipino',
    religion        VARCHAR(80)  NULL,
    blood_type      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    contact_number  VARCHAR(30)  NULL,
    email           VARCHAR(120) NULL,
    address         TEXT         NULL,
    city            VARCHAR(100) NULL,
    province        VARCHAR(100) NULL,
    emergency_name     VARCHAR(150) NULL,
    emergency_relation VARCHAR(80)  NULL,
    emergency_contact  VARCHAR(30)  NULL,
    patient_type    ENUM('Outpatient','Inpatient','Emergency') NOT NULL DEFAULT 'Outpatient',
    department_id   INT  NULL,
    doctor_id       INT  NULL,
    hmo_id          INT  NULL,
    hmo_card_no     VARCHAR(60)  NULL,
    chief_complaint TEXT NULL,
    allergies       TEXT NULL,
    registered_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    registered_by   VARCHAR(100) NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id)     REFERENCES doctors(id)     ON DELETE SET NULL,
    FOREIGN KEY (hmo_id)        REFERENCES hmo_providers(id) ON DELETE SET NULL
);

-- ── USERS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    username    VARCHAR(80)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('Admin','Nurse','Encoder','Read-Only') NOT NULL DEFAULT 'Encoder',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME     NULL
);

-- Default admin — username: admin / password: Admin@1234
INSERT INTO users (full_name, username, password, role, is_active) VALUES
('System Administrator', 'admin',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Admin', 1);
