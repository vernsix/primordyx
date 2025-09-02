-- =============================================================================
-- add_generated_column_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add generated column {{COLUMN}} to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    ADD COLUMN {{COLUMN}} {{COLUMN_TYPE}}
    GENERATED ALWAYS AS ({{GENERATION_EXPRESSION}}) {{GENERATION_TYPE}};
