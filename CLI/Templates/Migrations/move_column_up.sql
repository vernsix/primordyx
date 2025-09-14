-- =============================================================================
-- move_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Move column {{COLUMN}} in {{TABLE_NAME}} (MySQL specific)

ALTER TABLE {{TABLE_NAME}}
    MODIFY COLUMN {{COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}} {{POSITION}};
