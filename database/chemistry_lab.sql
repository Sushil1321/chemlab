
CREATE DATABASE IF NOT EXISTS chemistry_lab;
USE chemistry_lab;

-- 1. Users: College Admin and Company Owner
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('college_admin', 'company_owner') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Laboratories
CREATE TABLE laboratories (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    lab_name VARCHAR(150) NOT NULL,
    location VARCHAR(200),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Equipment
CREATE TABLE equipment (
    equipment_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_code VARCHAR(50) NOT NULL UNIQUE,
    equipment_name VARCHAR(150) NOT NULL,
    equipment_type VARCHAR(100),
    lab_id INT,
    equipment_status ENUM('Working', 'Faulty', 'Under Maintenance')
        DEFAULT 'Working',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES laboratories(lab_id)
        ON DELETE SET NULL
);

-- 4. Maintenance requests
CREATE TABLE maintenance_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    reported_by INT NULL,
    issue_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Reported', 'In Progress', 'Resolved')
        DEFAULT 'Reported',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id),
    FOREIGN KEY (reported_by) REFERENCES users(user_id)
        ON DELETE SET NULL
);

-- Sample laboratories
INSERT INTO laboratories (lab_name, location, description) VALUES
('Chemistry Laboratory', 'Science Block', 'General chemistry experiments'),
('Organic Chemistry Laboratory', 'Chemistry Block', 'Organic chemistry experiments'),
('Physical Chemistry Laboratory', 'Science Block', 'Physical chemistry experiments');

-- Sample equipment
INSERT INTO equipment
(equipment_code, equipment_name, equipment_type, lab_id)
VALUES
('CHEM-001', 'Digital Balance', 'Measuring Equipment', 1),
('CHEM-002', 'pH Meter', 'Measuring Equipment', 1),
('CHEM-003', 'Hot Plate', 'Heating Equipment', 2),
('CHEM-004', 'Bunsen Burner', 'Heating Equipment', 2),
('CHEM-005', 'Centrifuge', 'Laboratory Equipment', 3);
