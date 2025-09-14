-- =============================================================================
-- migrate_data_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Remove migrated data from {{TARGET_TABLE}}

-- Remove migrated data
DELETE FROM {{TARGET_TABLE}}
WHERE {{ROLLBACK_CONDITION}};
