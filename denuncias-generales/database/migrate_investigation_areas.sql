-- Migracion: areas de investigacion para portal denuncias generales
-- Ejecutar en la base denuncias_generales

ALTER TABLE users
    ADD COLUMN investigator_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') NULL AFTER role;

ALTER TABLE complaints
    ADD COLUMN assigned_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') NULL AFTER incident_location_nonce;

ALTER TABLE complaints
    ADD INDEX idx_assigned_area (assigned_area),
    ADD INDEX idx_area_status (assigned_area, status);

-- Asignacion inicial opcional para investigadores existentes
UPDATE users SET investigator_area = 'concesiones' WHERE role = 'investigador' AND username = 'revisor1';
UPDATE users SET investigator_area = 'finanzas' WHERE role = 'investigador' AND username = 'revisor2';
