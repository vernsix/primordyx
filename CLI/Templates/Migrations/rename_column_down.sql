-- =============================================================================
-- rename_column_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Rename column {{NEW_COLUMN}} back to {{OLD_COLUMN}} in {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    CHANGE COLUMN {{NEW_COLUMN}} {{OLD_COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}};
