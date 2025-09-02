-- =============================================================================
-- add_generated_column_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop generated column {{COLUMN}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP COLUMN {{COLUMN}};
