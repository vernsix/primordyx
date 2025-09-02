-- =============================================================================
-- copy_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop copied table {{DESTINATION_TABLE}}

DROP TABLE IF EXISTS {{DESTINATION_TABLE}};
