-- =============================================================================
-- create_database_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop database {{DATABASE_NAME}}

-- WARNING: This will permanently delete the entire database and all its data!
DROP DATABASE IF EXISTS {{DATABASE_NAME}};
