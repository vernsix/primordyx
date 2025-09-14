-- =============================================================================
-- drop_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create table {{TABLE_NAME}}

-- WARNING: This will recreate the table structure but NOT the data!
-- Customize this with your actual table structure

CREATE TABLE {{TABLE_NAME}} (
                                id INT AUTO_INCREMENT PRIMARY KEY,
    -- Add your actual column definitions here
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                deleted_at TIMESTAMP NULL DEFAULT NULL,
                                restored_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
