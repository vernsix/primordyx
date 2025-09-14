-- =============================================================================
-- drop_constraint_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Add constraint {{CONSTRAINT_NAME}} back to {{TABLE_NAME}}

-- WARNING: Customize this with the actual constraint definition
ALTER TABLE {{TABLE_NAME}}
    ADD CONSTRAINT {{CONSTRAINT_NAME}} {{CONSTRAINT_DEFINITION}};
