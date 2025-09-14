-- =============================================================================
-- modify_column_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Revert column {{COLUMN}} in {{TABLE_NAME}}

-- WARNING: Customize this with the original column definition
ALTER TABLE {{TABLE_NAME}}
    MODIFY COLUMN {{COLUMN}} {{OLD_COLUMN_TYPE}} {{OLD_COLUMN_OPTIONS}};
