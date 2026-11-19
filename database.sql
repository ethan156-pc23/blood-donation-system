-- Blood Donation System - MySQL schema + seed data
-- Import with:  mysql -u root -p < database.sql   (or via phpMyAdmin "Import")
CREATE DATABASE IF NOT EXISTS blood_donation_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_donation_app;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log, settings, weekly_collections, inventory_movements, storage_locations,
  blood_inventory, drives, appointments, donors, password_resets, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(60) NOT NULL DEFAULT 'Triage Nurse',
  facility VARCHAR(80) NOT NULL DEFAULT 'HQ Central Center',
  access_level VARCHAR(60) NOT NULL DEFAULT 'Pre-screening',
  status ENUM('Active','Pending','Suspended') NOT NULL DEFAULT 'Pending',
  avatar VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE donors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medical_id VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  blood_type ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  phone VARCHAR(30) NULL,
  status ENUM('Eligible','Deferred','Suspended') NOT NULL DEFAULT 'Eligible',
  last_donation DATE NULL,
  last_center VARCHAR(80) NULL,
  eligibility_note VARCHAR(200) NULL,
  avatar VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (blood_type), INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  appt_date DATE NOT NULL,
  slot_time TIME NOT NULL,
  center VARCHAR(40) NOT NULL DEFAULT 'HQ Central',
  location VARCHAR(80) NOT NULL,
  screen_status ENUM('Pre-Screened','Pending Lab','Approved','Deferred') NOT NULL DEFAULT 'Pending Lab',
  checked_in TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
  INDEX (appt_date)
) ENGINE=InnoDB;

CREATE TABLE blood_inventory (
  blood_type ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') PRIMARY KEY,
  units INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE storage_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  units INT NOT NULL DEFAULT 0,
  capacity_pct TINYINT NOT NULL DEFAULT 0,
  temperature VARCHAR(40) NOT NULL DEFAULT '-4°C stable'
) ENGINE=InnoDB;

CREATE TABLE inventory_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  type ENUM('Dispatch','Intake','Waste') NOT NULL,
  blood_type VARCHAR(4) NULL,
  units INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE drives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  drive_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  staffing VARCHAR(160) NOT NULL,
  registered INT NOT NULL DEFAULT 0,
  target INT NOT NULL DEFAULT 50,
  status ENUM('Active','Upcoming','Completed') NOT NULL DEFAULT 'Upcoming',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE weekly_collections (
  week_no TINYINT PRIMARY KEY,
  units INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
  skey VARCHAR(60) PRIMARY KEY,
  svalue VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  detail VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Seed data ----------
INSERT INTO users (name,email,password_hash,role,facility,access_level,status,avatar) VALUES
('Tantan Casquejo','tantancasquejo@gmail.com','$2y$10$ZyP/.FnFgREEsDweTb2a9eOheSNziDSRfMJ4KI722/qY9MvNiwHJi','Triage Nurse','HQ Central Center','Pre-screening','Active',NULL),
('Klein Taneo','kleintaneoo@gmail.com','$2y$10$YnyZm3RhwswM6T4MYnxch.gVsUCW3teVytdB1MvBoqj9EV3yYn9SK','System Administrator','HQ Center + Mobile','Admin Ops','Active',NULL);

INSERT INTO donors (medical_id,name,blood_type,phone,status,last_donation,last_center,eligibility_note,avatar) VALUES
('HMC-8021-99','Jeffrel','O-','+63 917 000 0001','Eligible','2026-08-12','HQ Central Center','Next eligible: Immediately','sarah.png'),
('HMC-4112-04','Andrei','A+','+63 917 000 0002','Deferred','2026-09-28','Campus Mobile Drive','Low Hemoglobin. Rel: Nov 10','david.png'),
('HMC-9904-12','Aliyah','AB-','+63 917 000 0003','Eligible','2026-07-04','Metro Hub Mobile','Next eligible: Immediately','marcus.png'),
('HMC-3310-77','Mariz','O+','+63 917 000 0004','Suspended','2026-01-15','HQ Central Center','Medical Restriction Flag','chloe.png');

INSERT INTO appointments (donor_id,appt_date,slot_time,center,location,screen_status,checked_in) VALUES
(1,CURDATE(),'09:30:00','HQ Central','HQ Central Room B','Pre-Screened',0),
(2,CURDATE(),'10:15:00','Campus Mobile Drive','Campus Mobile Truck','Pending Lab',0),
(3,CURDATE(),'11:00:00','HQ Central','HQ Central Room A','Approved',0),
(4,CURDATE(),'11:45:00','HQ Central','HQ Central Room B','Deferred',0);

INSERT INTO blood_inventory (blood_type,units) VALUES
('O+',142),('O-',12),('A+',110),('A-',42),('B+',85),('B-',18),('AB+',162),('AB-',54);

INSERT INTO storage_locations (name,units,capacity_pct,temperature) VALUES
('HQ Central Blood Bank',654,85,'-4°C stable'),
('North Regional Clinic',142,60,'-4°C stable'),
('General Hospital Depot',85,40,'-4°C stable');

INSERT INTO inventory_movements (description,type,blood_type,units,created_at) VALUES
('Transferred 12 Units of A- to City Hospital','Dispatch','A-',12,NOW() - INTERVAL 10 MINUTE),
('Collected 45 Units Whole Blood from Campus Drive','Intake',NULL,45,NOW() - INTERVAL 1 HOUR),
('Discarded 2 Expired Units of AB- (Testing Flag)','Waste','AB-',2,NOW() - INTERVAL 4 HOUR);

INSERT INTO drives (name,drive_date,start_time,end_time,staffing,registered,target,status) VALUES
('State University Gym','2026-10-24','09:00:00','16:00:00','4 Phlebotomists, 1 Nurse',32,50,'Active'),
('Metro Office Park Hub','2026-10-26','10:00:00','15:00:00','3 Phlebotomists, 1 Admin',18,40,'Upcoming'),
('Downtown Community Center','2026-10-22','08:00:00','18:00:00','5 Phlebotomists, 2 Nurses',45,40,'Completed');

INSERT INTO weekly_collections (week_no,units) VALUES (1,120),(2,145),(3,98),(4,110),(5,165),(6,154);

INSERT INTO settings (skey,svalue) VALUES
('two_factor','1'),('session_timeout','1'),('ip_whitelist','0'),
('org_name','HemoCare Ops Central'),('fda_license','LIC-9981-CLIN'),
('inventory_floor','100'),('critical_below','25'),('moderate_below','50'),
('ytd_units','3114'),('pass_rate','96.4'),('donors_this_week','124');

INSERT INTO audit_log (title,detail,created_at) VALUES
('Authorized login: Dr. Thorne','HQ Console • IP: 10.42.0.12',NOW() - INTERVAL 2 MINUTE),
('Inventory Threshold Modified','Type O- floor decreased to 10 Units',NOW() - INTERVAL 1 HOUR),
('MFA Token Reset Triggered','Sent code to Nurse Jenkins',NOW() - INTERVAL 3 HOUR),
('Suspicious login block','Blocked IP: 192.168.1.99',NOW() - INTERVAL 1 DAY);
