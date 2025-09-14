-- =============================================================================
-- add_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add column {{COLUMN}} to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    ADD COLUMN {{COLUMN}} {{COLUMN_TYPE}} {{COLUMN_OPTIONS}} {{AFTER}};
