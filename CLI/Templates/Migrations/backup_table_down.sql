-- =============================================================================
-- backup_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop backup table {{BACKUP_TABLE}}

DROP TABLE IF EXISTS {{BACKUP_TABLE}};
