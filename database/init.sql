-- =============================================
-- Portal de Denuncias Empresa Portuaria Coquimbo - Base de Datos v1.0
-- Sistema independiente de denuncias Ley Karin
--
-- ENCRIPTACIÓN: Los campos sensibles (description,
-- reporter_*, accused_*, witnesses, resolution, etc.)
-- se almacenan encriptados con AES-256-GCM vía PHP sodium.
-- Solo usuarios admin/investigador pueden desencriptar
-- a través de la capa de aplicación.
-- =============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE denuncias;

-- =============================================
-- TABLA: USUARIOS DEL PORTAL DE DENUNCIAS
-- Roles separados del portal de soporte
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'investigador', 'viewer') NOT NULL DEFAULT 'viewer',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    must_change_password TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: DENUNCIAS
-- Campos sensibles almacenados como BLOB (encriptados)
-- =============================================
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(20) NOT NULL UNIQUE,
    complaint_type ENUM('acoso_laboral', 'acoso_sexual', 'violencia_laboral', 'discriminacion', 'represalia', 'otro') NOT NULL,

    -- Campos encriptados (BLOB para almacenar datos cifrados)
    description_encrypted BLOB NOT NULL,
    description_nonce VARBINARY(24) NOT NULL,

    involved_persons_encrypted BLOB DEFAULT NULL,
    involved_persons_nonce VARBINARY(24) DEFAULT NULL,

    evidence_description_encrypted BLOB DEFAULT NULL,
    evidence_description_nonce VARBINARY(24) DEFAULT NULL,

    is_anonymous TINYINT(1) DEFAULT 1,

    -- Datos del denunciante (encriptados)
    reporter_name_encrypted BLOB DEFAULT NULL,
    reporter_name_nonce VARBINARY(24) DEFAULT NULL,

    reporter_email_encrypted BLOB DEFAULT NULL,
    reporter_email_nonce VARBINARY(24) DEFAULT NULL,

    reporter_phone_encrypted BLOB DEFAULT NULL,
    reporter_phone_nonce VARBINARY(24) DEFAULT NULL,

    reporter_department_encrypted BLOB DEFAULT NULL,
    reporter_department_nonce VARBINARY(24) DEFAULT NULL,

    -- Datos del denunciado (encriptados)
    accused_name_encrypted BLOB DEFAULT NULL,
    accused_name_nonce VARBINARY(24) DEFAULT NULL,
    accused_name_hmac VARCHAR(64) DEFAULT NULL,   -- HMAC del nombre normalizado para filtrado de conflictos

    accused_department_encrypted BLOB DEFAULT NULL,
    accused_department_nonce VARBINARY(24) DEFAULT NULL,

    accused_position_encrypted BLOB DEFAULT NULL,
    accused_position_nonce VARBINARY(24) DEFAULT NULL,

    -- Testigos (encriptados)
    witnesses_encrypted BLOB DEFAULT NULL,
    witnesses_nonce VARBINARY(24) DEFAULT NULL,

    -- Datos del incidente (no encriptados, necesarios para filtrar)
    incident_date DATE DEFAULT NULL,
    incident_location_encrypted BLOB DEFAULT NULL,
    incident_location_nonce VARBINARY(24) DEFAULT NULL,

    -- Estado y gestión
    status ENUM('recibida', 'en_investigacion', 'resuelta', 'desestimada', 'archivada') DEFAULT 'recibida',
    priority ENUM('normal', 'alta', 'urgente') DEFAULT 'normal',

    -- Resolución (encriptada)
    resolution_encrypted BLOB DEFAULT NULL,
    resolution_nonce VARBINARY(24) DEFAULT NULL,

    investigator_id INT DEFAULT NULL,
    assigned_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint_number (complaint_number),
    INDEX idx_status (status),
    INDEX idx_type (complaint_type),
    INDEX idx_created (created_at),
    INDEX idx_accused_name_hmac (accused_name_hmac)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: EVIDENCIA / ARCHIVOS ADJUNTOS
-- =============================================
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
-- Permite filtrar denuncias donde el nombre, cargo o dpto. del acusado
-- coincide con cualquier token de identidad del investigador.
-- =============================================
CREATE TABLE IF NOT EXISTS complaint_conflict_tokens (
    complaint_id INT NOT NULL,
    token_hmac   VARCHAR(64) NOT NULL,
    INDEX idx_token      (token_hmac),
    INDEX idx_complaint  (complaint_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TABLA: HISTORIAL / LOGS DE DENUNCIAS
-- =============================================
CREATE TABLE IF NOT EXISTS complaint_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description_encrypted BLOB DEFAULT NULL,
    description_nonce VARBINARY(24) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    is_confidential TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: NOTAS DE INVESTIGACIÓN (encriptadas)
-- =============================================
CREATE TABLE IF NOT EXISTS investigation_notes (
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

-- =============================================
-- TABLA: LOGS DE ACTIVIDAD
-- =============================================
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

-- =============================================
-- TABLA: SESIONES DE RESET DE CONTRASEÑA
-- =============================================
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

-- =============================================================================
-- DATOS INICIALES
-- =============================================================================

-- =============================================
-- TABLA: GRUPOS DE NOTIFICACIÓN
-- =============================================
CREATE TABLE IF NOT EXISTS notification_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- =============================================
-- TABLA: SUSCRIPCIÓN DE USUARIOS A GRUPOS
-- =============================================
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

-- =============================================
-- TABLA: NOTIFICACIONES INDIVIDUALES
-- =============================================
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

-- Grupos de notificación predefinidos
INSERT INTO notification_groups (name, slug, description) VALUES
('Todas las Notificaciones', 'todas', 'Recibir absolutamente todas las notificaciones del sistema'),
('Creación de Denuncias', 'denuncia_creada', 'Cuando se crea una nueva denuncia en el portal'),
('Asignación de Casos', 'asignacion', 'Cuando un caso es asignado a un investigador'),
('Investigación en Curso', 'investigacion', 'Actualizaciones durante la investigación de un caso'),
('Denuncias Resueltas', 'resuelta', 'Cuando una denuncia es marcada como resuelta'),
('Denuncias Cerradas', 'cerrada', 'Cuando una denuncia es archivada o desestimada');

-- Suscribir admins a todas las notificaciones por defecto
INSERT INTO notification_subscriptions (user_id, group_id)
SELECT u.id, ng.id
FROM users u
CROSS JOIN notification_groups ng
WHERE u.role = 'admin' AND ng.slug = 'todas';

-- Suscribir investigadores a creación, asignación e investigación
INSERT INTO notification_subscriptions (user_id, group_id)
SELECT u.id, ng.id
FROM users u
CROSS JOIN notification_groups ng
WHERE u.role = 'investigador' AND ng.slug IN ('denuncia_creada', 'asignacion', 'investigacion');

-- Usuarios del portal de denuncias (Contraseña: password)
INSERT INTO users (name, username, email, password, role, department, position, must_change_password) VALUES
('Administrador Denuncias', 'admin.denuncias', 'admin.denuncias@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'Recursos Humanos', 'Administrador Canal de Denuncias', 1),

('Comité de Ética', 'comite.etica', 'etica@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'Recursos Humanos', 'Presidente Comité de Ética', 1),

('Investigador 1', 'investigador1', 'investigador1@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'Recursos Humanos', 'Investigador Ley Karin', 1),

('Investigador 2', 'investigador2', 'investigador2@epco.cl',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'investigador', 'Legal', 'Abogado Investigador', 1);

-- =============================================
-- VERIFICACIÓN FINAL
-- =============================================
SELECT '✓ Base de datos Denuncias v1.0 inicializada exitosamente!' AS mensaje;
SELECT CONCAT('Usuarios: ', COUNT(*)) AS info FROM users;
