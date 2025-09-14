-- =============================================================================
-- create_procedure_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop stored procedure {{PROCEDURE_NAME}}

DROP PROCEDURE IF EXISTS {{PROCEDURE_NAME}};
