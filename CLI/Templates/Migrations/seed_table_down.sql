-- =============================================================================
-- seed_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Remove seeded data from {{TABLE_NAME}}

-- WARNING: This will delete the seeded data
-- Customize this condition to match only the seeded records
DELETE FROM {{TABLE_NAME}} WHERE {{DELETE_CONDITION}};
