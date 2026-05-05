-- Optimización de índices para denuncias_generales
-- Elimina índices duplicados y agrega índice compuesto útil para filtros por área/estado/fecha.

SET @drop_users_idx_username := (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE users DROP INDEX idx_username', 'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_username'
);
PREPARE stmt FROM @drop_users_idx_username;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_users_idx_email := (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE users DROP INDEX idx_email', 'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_email'
);
PREPARE stmt FROM @drop_users_idx_email;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_complaints_idx_complaint_number := (
    SELECT IF(COUNT(*) > 0, 'ALTER TABLE complaints DROP INDEX idx_complaint_number', 'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'complaints' AND index_name = 'idx_complaint_number'
);
PREPARE stmt FROM @drop_complaints_idx_complaint_number;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_idx_area_status_created := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE complaints ADD INDEX idx_area_status_created (assigned_area, status, created_at)', 'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'complaints' AND index_name = 'idx_area_status_created'
);
PREPARE stmt FROM @add_idx_area_status_created;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
