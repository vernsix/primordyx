-- =============================================================================
-- add_check_constraint_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop check constraint {{CONSTRAINT_NAME}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP CHECK {{CONSTRAINT_NAME}};
