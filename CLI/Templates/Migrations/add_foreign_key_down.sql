-- =============================================================================
-- add_foreign_key_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop foreign key {{FK_NAME}} from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
DROP FOREIGN KEY {{FK_NAME}};
