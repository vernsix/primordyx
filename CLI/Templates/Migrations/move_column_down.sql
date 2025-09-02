-- =============================================================================
-- move_column_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Move column {{COLUMN}} back to original position in {{TABLE_NAME}}

-- WARNING: Customize this with the original column position
ALTER TABLE {{TABLE_NAME}}
    MODIFY COLUMN {{COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}} {{ORIGINAL_POSITION}};
