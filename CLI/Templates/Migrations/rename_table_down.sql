-- =============================================================================
-- rename_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Rename table {{NEW_TABLE}} back to {{OLD_TABLE}}

RENAME TABLE {{NEW_TABLE}} TO {{OLD_TABLE}};
