-- =============================================================================
-- modify_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Modify column {{COLUMN}} in {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    MODIFY COLUMN {{COLUMN}} {{NEW_COLUMN_TYPE}} {{NEW_COLUMN_OPTIONS}};
