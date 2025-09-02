-- =============================================================================
-- drop_function_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create function {{FUNCTION_NAME}}

-- WARNING: Customize this with the actual function definition
DELIMITER $$

CREATE FUNCTION {{FUNCTION_NAME}}({{PARAMETERS}})
RETURNS {{RETURN_TYPE}}
{{FUNCTION_CHARACTERISTICS}}
BEGIN
{{FUNCTION_BODY}}
END$$

DELIMITER ;
