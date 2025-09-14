-- =============================================================================
-- create_function_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create function {{FUNCTION_NAME}}

DELIMITER $$

CREATE FUNCTION {{FUNCTION_NAME}}({{PARAMETERS}})
RETURNS {{RETURN_TYPE}}
{{FUNCTION_CHARACTERISTICS}}
BEGIN
{{FUNCTION_BODY}}
END$$

DELIMITER ;
