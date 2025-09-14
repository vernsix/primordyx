-- =============================================================================
-- add_unique_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop unique constraint {{CONSTRAINT_NAME}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP INDEX {{CONSTRAINT_NAME}};
