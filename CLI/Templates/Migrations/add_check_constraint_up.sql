-- =============================================================================
-- add_check_constraint_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add check constraint {{CONSTRAINT_NAME}} to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    ADD CONSTRAINT {{CONSTRAINT_NAME}} CHECK ({{CHECK_CONDITION}});
