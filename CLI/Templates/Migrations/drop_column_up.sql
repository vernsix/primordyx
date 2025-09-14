-- =============================================================================
-- drop_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Drop column {{COLUMN}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP COLUMN {{COLUMN}};
