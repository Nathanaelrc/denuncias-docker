-- =============================================
-- Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - BD v1.0
-- Basado en legislación chilena general
-- Separado del portal Ley Karin
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE denuncias_generales;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'admin', 'investigador', 'viewer', 'auditor') NOT NULL DEFAULT 'viewer',
    investigator_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    must_change_password TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Denuncias ciudadanas: campos sensibles encriptados
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(20) NOT NULL UNIQUE,
    complaint_type VARCHAR(100) NOT NULL DEFAULT 'general',

    description_encrypted BLOB NOT NULL,
    description_nonce VARBINARY(24) NOT NULL,

    -- Empresa / institución denunciada (reemplaza "accused")
    involved_persons_encrypted BLOB DEFAULT NULL,
    involved_persons_nonce VARBINARY(24) DEFAULT NULL,

    evidence_description_encrypted BLOB DEFAULT NULL,
    evidence_description_nonce VARBINARY(24) DEFAULT NULL,

    is_anonymous TINYINT(1) DEFAULT 1,

    -- Datos del denunciante
    reporter_name_encrypted BLOB DEFAULT NULL,
    reporter_name_nonce VARBINARY(24) DEFAULT NULL,
    reporter_lastname_encrypted BLOB DEFAULT NULL,
    reporter_lastname_nonce VARBINARY(24) DEFAULT NULL,
    reporter_email_encrypted BLOB DEFAULT NULL,
    reporter_email_nonce VARBINARY(24) DEFAULT NULL,
    reporter_phone_encrypted BLOB DEFAULT NULL,
    reporter_phone_nonce VARBINARY(24) DEFAULT NULL,
    reporter_department_encrypted BLOB DEFAULT NULL,
    reporter_department_nonce VARBINARY(24) DEFAULT NULL,

    -- Entidad/empresa denunciada
    accused_name_encrypted BLOB DEFAULT NULL,
    accused_name_nonce VARBINARY(24) DEFAULT NULL,
    accused_name_hmac VARCHAR(64) DEFAULT NULL,   -- HMAC del nombre normalizado para filtrado de conflictos
    accused_department_encrypted BLOB DEFAULT NULL,
    accused_department_nonce VARBINARY(24) DEFAULT NULL,
    accused_position_encrypted BLOB DEFAULT NULL,
    accused_position_nonce VARBINARY(24) DEFAULT NULL,

    -- Testigos / N° referencia
    witnesses_encrypted BLOB DEFAULT NULL,
    witnesses_nonce VARBINARY(24) DEFAULT NULL,

    incident_date DATE DEFAULT NULL,
    incident_location_encrypted BLOB DEFAULT NULL,
    incident_location_nonce VARBINARY(24) DEFAULT NULL,

    assigned_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') DEFAULT NULL,

    status ENUM('recibida','en_investigacion','resuelta','desestimada','archivada') DEFAULT 'recibida',
    priority ENUM('normal','alta','urgente') DEFAULT 'normal',

    resolution_encrypted BLOB DEFAULT NULL,
    resolution_nonce VARBINARY(24) DEFAULT NULL,

    investigator_id INT DEFAULT NULL,
    assigned_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_type (complaint_type),
    INDEX idx_accused_name_hmac (accused_name_hmac),
    INDEX idx_created (created_at),
    INDEX idx_assigned_area (assigned_area),
    -- Índices compuestos para el dashboard y filtros frecuentes
    INDEX idx_status_created (status, created_at),
    INDEX idx_type_status (complaint_type, status),
    INDEX idx_area_status (assigned_area, status),
    INDEX idx_area_status_created (assigned_area, status, created_at),
    INDEX idx_investigator_status (investigator_id, status),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS complaint_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name_encrypted BLOB NOT NULL,
    original_name_nonce VARBINARY(24) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: TOKENS DE CONFLICTO DE INTERÉS
-- =============================================
CREATE TABLE IF NOT EXISTS complaint_conflict_tokens (
    complaint_id INT NOT NULL,
    token_hmac   VARCHAR(64) NOT NULL,
    INDEX idx_token      (token_hmac),
    INDEX idx_complaint  (complaint_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS complaint_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    content_encrypted BLOB NOT NULL,
    content_nonce VARBINARY(24) NOT NULL,
    is_confidential TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS investigation_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    content_encrypted BLOB NOT NULL,
    content_nonce VARBINARY(24) NOT NULL,
    is_confidential TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES notification_groups(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_group (user_id, group_id),
    INDEX idx_user (user_id),
    INDEX idx_group (group_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_slug VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT DEFAULT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: COLA DE CORREOS ASÍNCRONA
-- =============================================
CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    next_attempt_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    last_error TEXT DEFAULT NULL,
    locked_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_next (status, next_attempt_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

INSERT INTO notification_groups (name, slug, description) VALUES
('Todas las Notificaciones',  'todas',           'Recibir todas las notificaciones del sistema'),
('Nueva Denuncia',            'denuncia_creada', 'Cuando se crea una nueva denuncia ciudadana'),
('Asignación de Casos',       'asignacion',      'Cuando un caso es asignado a un revisor'),
('En Revisión',               'investigacion',   'Actualizaciones durante la revisión de un caso'),
('Denuncia Resuelta',         'resuelta',        'Cuando una denuncia es marcada como resuelta'),
('Denuncia Cerrada',          'cerrada',         'Cuando una denuncia es archivada o desestimada');

-- Usuarios iniciales (contraseña: password — CAMBIAR EN PRIMER LOGIN)
INSERT INTO users (name, username, email, password, role, investigator_area, department, position, must_change_password) VALUES
('Super Administrador', 'superadmin', 'superadmin@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'superadmin', NULL, 'Dirección', 'Super Administrador del Sistema', 1),

('Administrador Ciudadano', 'admin.ciudadano', 'admin.ciudadano@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', NULL, 'Atención Ciudadana', 'Administrador Portal Ciudadano', 1),

('Coordinador Denuncias', 'coordinador', 'coordinador@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', NULL, 'Atención Ciudadana', 'Coordinador de Denuncias', 1),

('Revisor Concesiones', 'revisor.concesiones', 'concesiones@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'concesiones', 'Concesiones', 'Revisor de Área Concesiones', 1),

('Revisor Ingeniería', 'revisor.ingenieria', 'ingenieria@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'ingenieria', 'Ingeniería', 'Revisor de Área Ingeniería', 1),

('Revisor Finanzas', 'revisor.finanzas', 'finanzas@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'finanzas', 'Finanzas', 'Revisor de Área Finanzas', 1),

('Revisor Sostenibilidad', 'revisor.sostenibilidad', 'sostenibilidad@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'sostenibilidad', 'Sostenibilidad', 'Revisor de Área Sostenibilidad', 1);

INSERT INTO notification_subscriptions (user_id, group_id)
SELECT u.id, ng.id FROM users u CROSS JOIN notification_groups ng
WHERE u.role = 'admin' AND ng.slug = 'todas';

INSERT INTO notification_subscriptions (user_id, group_id)
SELECT u.id, ng.id FROM users u CROSS JOIN notification_groups ng
WHERE u.role = 'investigador' AND ng.slug IN ('denuncia_creada', 'asignacion', 'investigacion');

SELECT '✓ Base de datos Denuncias Ciudadanas v1.0 inicializada.' AS mensaje;
SELECT CONCAT('Usuarios: ', COUNT(*)) AS info FROM users;
