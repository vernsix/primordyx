-- =============================================================================
-- create_table_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create table {{TABLE_NAME}}

CREATE TABLE {{TABLE_NAME}} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    {{COLUMN_DEFINITIONS}}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    restored_at TIMESTAMP NULL DEFAULT NULL
) ENGINE={{ENGINE}} DEFAULT CHARSET={{CHARSET}} COLLATE={{COLLATION}};
