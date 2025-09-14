-- =============================================================================
-- rename_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Rename column {{OLD_COLUMN}} to {{NEW_COLUMN}} in {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    CHANGE COLUMN {{OLD_COLUMN}} {{NEW_COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}};
