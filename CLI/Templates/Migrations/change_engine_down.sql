-- =============================================================================
-- change_engine_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Change table engine for {{TABLE_NAME}} back to {{OLD_ENGINE}}

ALTER TABLE {{TABLE_NAME}} ENGINE = {{OLD_ENGINE}};
