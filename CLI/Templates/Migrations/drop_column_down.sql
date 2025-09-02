-- =============================================================================
-- drop_column_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Add column {{COLUMN}} back to {{TABLE_NAME}}

-- WARNING: Customize this with the actual column definition
ALTER TABLE {{TABLE_NAME}}
    ADD COLUMN {{COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}} {{AFTER}};
