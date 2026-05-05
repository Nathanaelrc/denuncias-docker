-- ============================================================================
-- MIGRACIÓN: Agregar roles superadmin y auditor a base de datos existente
-- Ejecutar UNA SOLA VEZ en bases de datos ya inicializadas
-- ============================================================================

USE denuncias;

-- Ampliar el ENUM de roles en tabla users (si la BD ya existe)
ALTER TABLE users
    MODIFY COLUMN role ENUM('superadmin', 'admin', 'investigador', 'viewer', 'auditor') NOT NULL DEFAULT 'viewer';

-- Verificar resultado
SELECT 'ENUM actualizado correctamente' AS resultado;
SHOW COLUMNS FROM users LIKE 'role';

-- Opcional: ver distribución de roles actual
SELECT role, COUNT(*) as total FROM users GROUP BY role ORDER BY FIELD(role, 'superadmin','admin','investigador','viewer','auditor');
