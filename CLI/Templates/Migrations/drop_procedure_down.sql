-- =============================================================================
-- drop_procedure_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create stored procedure {{PROCEDURE_NAME}}

-- WARNING: Customize this with the actual procedure definition
DELIMITER $$

CREATE PROCEDURE {{PROCEDURE_NAME}}({{PARAMETERS}})
BEGIN
{{PROCEDURE_BODY}}
END$$

DELIMITER ;
