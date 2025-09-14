-- =============================================================================
-- modify_table_options_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Revert table options for {{TABLE_NAME}}

-- WARNING: Customize this with the original table options
ALTER TABLE {{TABLE_NAME}} {{ORIGINAL_TABLE_OPTIONS}};
