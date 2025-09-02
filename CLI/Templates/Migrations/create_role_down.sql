-- =============================================================================
-- create_role_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop role {{ROLE_NAME}}

DROP ROLE IF EXISTS {{ROLE_NAME}};
