-- =============================================================================
-- add_unique_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add unique constraint {{CONSTRAINT_NAME}} to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    ADD CONSTRAINT {{CONSTRAINT_NAME}} UNIQUE ({{COLUMNS}});
