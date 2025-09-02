-- =============================================================================
-- purge_data_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Purge data from {{TABLE_NAME}}

-- WARNING: This will permanently delete data
DELETE FROM {{TABLE_NAME}}
WHERE {{PURGE_CONDITION}};
