-- ============================================================
-- PATIENTDATAPROGRAM — Sample Doctors & Patients
-- Run this in phpMyAdmin > patientdataprogram > SQL tab
-- ============================================================

USE patientdataprogram;

-- ── DOCTORS ───────────────────────────────────────────────
INSERT INTO doctors (full_name, department_id, prc_number, is_active) VALUES
-- Emergency Medicine (1)
('Dr. Jose Ramon Dela Cruz',        1, 'PRC-0012341', 1),
('Dr. Maria Cristina Reyes',        1, 'PRC-0012342', 1),
('Dr. Ferdinand Santos',            1, 'PRC-0012343', 1),

-- Internal Medicine (2)
('Dr. Angelica Bautista',           2, 'PRC-0012344', 1),
('Dr. Roberto Villanueva',          2, 'PRC-0012345', 1),
('Dr. Lourdes Manalo',              2, 'PRC-0012346', 1),

-- Pediatrics (3)
('Dr. Corazon Mercado',             3, 'PRC-0012347', 1),
('Dr. Danilo Aquino',               3, 'PRC-0012348', 1),
('Dr. Theresa Gonzales',            3, 'PRC-0012349', 1),

-- OB-GYN (4)
('Dr. Rosario Navarro',             4, 'PRC-0012350', 1),
('Dr. Marites Soriano',             4, 'PRC-0012351', 1),
('Dr. Elena Castillo',              4, 'PRC-0012352', 1),

-- Surgery (5)
('Dr. Eduardo Flores',              5, 'PRC-0012353', 1),
('Dr. Renato Pascual',              5, 'PRC-0012354', 1),
('Dr. Carmela Domingo',             5, 'PRC-0012355', 1),

-- Orthopedics (6)
('Dr. Gregorio Mendoza',            6, 'PRC-0012356', 1),
('Dr. Allan Ramos',                 6, 'PRC-0012357', 1),

-- Cardiology (7)
('Dr. Natividad Torres',            7, 'PRC-0012358', 1),
('Dr. Vicente Hernandez',           7, 'PRC-0012359', 1),
('Dr. Milagros Dizon',              7, 'PRC-0012360', 1),

-- Neurology (8)
('Dr. Ernesto Tolentino',           8, 'PRC-0012361', 1),
('Dr. Felicitas Magno',             8, 'PRC-0012362', 1),

-- Pulmonology (9)
('Dr. Severino Ocampo',             9, 'PRC-0012363', 1),
('Dr. Ligaya Delos Reyes',          9, 'PRC-0012364', 1),

-- Nephrology (10)
('Dr. Primitivo Aguilar',          10, 'PRC-0012365', 1),
('Dr. Zenaida Cruz',               10, 'PRC-0012366', 1),

-- Gastroenterology (11)
('Dr. Bonifacio Liwanag',          11, 'PRC-0012367', 1),
('Dr. Lorena Padilla',             11, 'PRC-0012368', 1),

-- Ophthalmology (12)
('Dr. Cynthia Andrade',            12, 'PRC-0012369', 1),
('Dr. Mario Salazar',              12, 'PRC-0012370', 1),

-- ENT (13)
('Dr. Arlene Buenaventura',        13, 'PRC-0012371', 1),
('Dr. Nestor Cabrera',             13, 'PRC-0012372', 1),

-- Dermatology (14)
('Dr. Gloria Dela Vega',           14, 'PRC-0012373', 1),
('Dr. Patrick Lim',                14, 'PRC-0012374', 1),

-- Psychiatry (15)
('Dr. Remedios Fontanilla',        15, 'PRC-0012375', 1),
('Dr. Oscar Evangelista',          15, 'PRC-0012376', 1),

-- Oncology (16)
('Dr. Soledad Macapagal',          16, 'PRC-0012377', 1),
('Dr. Renaldo Bacani',             16, 'PRC-0012378', 1),

-- Urology (17)
('Dr. Dominador Sison',            17, 'PRC-0012379', 1),
('Dr. Lilia Villafuerte',          17, 'PRC-0012380', 1),

-- Endocrinology (18)
('Dr. Pacita Roxas',               18, 'PRC-0012381', 1),
('Dr. Herminio Guevarra',          18, 'PRC-0012382', 1),

-- Rheumatology (19)
('Dr. Consolacion Perez',          19, 'PRC-0012383', 1),

-- Rehabilitation Medicine (20)
('Dr. Aurelio Bondoc',             20, 'PRC-0012384', 1),
('Dr. Vivian Esquivel',            20, 'PRC-0012385', 1),

-- Family Medicine (24)
('Dr. Resurreccion Macaraeg',      24, 'PRC-0012386', 1),
('Dr. Glenda Tupas',               24, 'PRC-0012387', 1),
('Dr. Jerome Abad',                24, 'PRC-0012388', 1),

-- Infectious Disease (25)
('Dr. Wilhelmina Chua',            25, 'PRC-0012389', 1),
('Dr. Basilio Recto',              25, 'PRC-0012390', 1);

-- ── UPDATE EXISTING PATIENT (Aaron Ludwig — assign to Family Medicine / Dr. Resurreccion Macaraeg) ──
UPDATE patients 
SET department_id = 24, doctor_id = (SELECT id FROM doctors WHERE full_name = 'Dr. Resurreccion Macaraeg' LIMIT 1)
WHERE patient_no = 'PDP-2026-00001';

-- ── SAMPLE PATIENTS (mixed types) ─────────────────────────
INSERT INTO patients 
(patient_no, full_name, birthdate, sex, civil_status, nationality, religion, blood_type,
 contact_number, address, city, province,
 emergency_name, emergency_relation, emergency_contact,
 patient_type, department_id, doctor_id, hmo_id, chief_complaint, registered_by)
VALUES

-- OUTPATIENT
('PDP-2026-00002','Reyes, Maria Cristina B.','1985-03-22','Female','Married','Filipino','Roman Catholic','A+',
 '0917-234-5678','123 Maligaya St., Brgy. Poblacion','Quezon City','Metro Manila',
 'Reyes, Juan','Spouse','0917-888-9999',
 'Outpatient',2,4,2,NULL,'admin'),

('PDP-2026-00003','Santos, Rodrigo P.','1970-11-05','Male','Married','Filipino','Roman Catholic','B+',
 '0918-345-6789','456 Sampaguita Ave.','Caloocan','Metro Manila',
 'Santos, Nelia','Spouse','0918-777-1234',
 'Outpatient',7,19,1,NULL,'admin'),

('PDP-2026-00004','Dela Cruz, Analyn T.','1995-07-14','Female','Single','Filipino','Baptist','O-',
 '0920-456-7890','789 Rosal St., Brgy. 5','Marikina','Metro Manila',
 'Dela Cruz, Perla','Mother','0920-555-4321',
 'Outpatient',14,34,5,NULL,'admin'),

('PDP-2026-00005','Garcia, Emmanuel L.','1960-01-30','Male','Widowed','Filipino','Iglesia ni Cristo','AB+',
 '0921-567-8901','12 Narra Rd., Brgy. San Jose','Pasig','Metro Manila',
 'Garcia, Lito','Son','0921-444-5678',
 'Outpatient',10,25,1,NULL,'admin'),

('PDP-2026-00006','Flores, Jasmine R.','2000-09-09','Female','Single','Filipino','Roman Catholic','A-',
 '0922-678-9012','34 Ilang-Ilang St.','Las Piñas','Metro Manila',
 'Flores, Dante','Father','0922-333-6789',
 'Outpatient',4,10,3,NULL,'admin'),

-- INPATIENT
('PDP-2026-00007','Villanueva, Carlos M.','1955-06-18','Male','Married','Filipino','Roman Catholic','O+',
 '0923-789-0123','56 Bagong Pag-asa St.','Malabon','Metro Manila',
 'Villanueva, Nora','Spouse','0923-222-7890',
 'Inpatient',7,18,2,NULL,'admin'),

('PDP-2026-00008','Aquino, Leonora S.','1948-12-03','Female','Widowed','Filipino','Roman Catholic','B-',
 '0924-890-1234','78 Dalisay Lane','Mandaluyong','Metro Manila',
 'Aquino, Bart','Son','0924-111-8901',
 'Inpatient',8,21,1,NULL,'admin'),

('PDP-2026-00009','Mendoza, Danilo F.','1978-04-25','Male','Married','Filipino','Born Again Christian','A+',
 '0925-901-2345','90 Pag-asa Blvd.','San Juan','Metro Manila',
 'Mendoza, Cora','Spouse','0925-999-9012',
 'Inpatient',5,14,4,NULL,'admin'),

('PDP-2026-00010','Torres, Rowena G.','1990-08-17','Female','Married','Filipino','Roman Catholic','AB-',
 '0926-012-3456','21 Mapayapa St.','Muntinlupa','Metro Manila',
 'Torres, Alex','Spouse','0926-888-0123',
 'Inpatient',2,5,7,NULL,'admin'),

('PDP-2026-00011','Bautista, Lorenzo A.','1942-02-11','Male','Married','Filipino','Roman Catholic','O+',
 '0927-123-4567','43 Masagana Ave.','Taguig','Metro Manila',
 'Bautista, Nena','Spouse','0927-777-1234',
 'Inpatient',9,23,1,NULL,'admin'),

-- EMERGENCY
('PDP-2026-00012','Navarro, Kristine Joy P.','2002-05-20','Female','Single','Filipino','Roman Catholic','O+',
 '0928-234-5678','65 Mabuhay St.','Parañaque','Metro Manila',
 'Navarro, Gerry','Father','0928-666-2345',
 'Emergency',1,1,20,NULL,'admin'),

('PDP-2026-00013','Castillo, Ramil B.','1988-10-07','Male','Married','Filipino','Roman Catholic','B+',
 '0929-345-6789','87 Bagong Silang Rd.','Valenzuela','Metro Manila',
 'Castillo, Linda','Spouse','0929-555-3456',
 'Emergency',1,2,1,NULL,'admin'),

('PDP-2026-00014','Pascual, Glenda N.','1972-03-15','Female','Married','Filipino','Iglesia ni Cristo','A+',
 '0930-456-7890','109 Tahimik St.','Navotas','Metro Manila',
 'Pascual, Romy','Spouse','0930-444-4567',
 'Emergency',5,15,2,NULL,'admin'),

('PDP-2026-00015','Ramos, Benedicto C.','1965-07-22','Male','Married','Filipino','Roman Catholic','O-',
 '0931-567-8901','131 Magiting Blvd.','Pasay','Metro Manila',
 'Ramos, Cita','Spouse','0931-333-5678',
 'Emergency',6,16,9,NULL,'admin');
