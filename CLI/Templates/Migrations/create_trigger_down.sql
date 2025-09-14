-- =============================================================================
-- create_trigger_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop trigger {{TRIGGER_NAME}}

DROP TRIGGER IF EXISTS {{TRIGGER_NAME}};
