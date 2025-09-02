-- =============================================================================
-- drop_constraint_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Drop constraint {{CONSTRAINT_NAME}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP {{CONSTRAINT_TYPE}} {{CONSTRAINT_NAME}};
