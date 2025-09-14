-- =============================================================================
-- drop_database_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Drop database {{DATABASE_NAME}}

-- WARNING: This will permanently delete the entire database and all its data!
DROP DATABASE IF EXISTS {{DATABASE_NAME}};
