-- =============================================================================
-- create_procedure_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create stored procedure {{PROCEDURE_NAME}}

DELIMITER $$

CREATE PROCEDURE {{PROCEDURE_NAME}}({{PARAMETERS}})
BEGIN
{{PROCEDURE_BODY}}
END$$

DELIMITER ;
