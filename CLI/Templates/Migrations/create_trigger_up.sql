-- =============================================================================
-- create_trigger_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create trigger {{TRIGGER_NAME}} on {{TABLE_NAME}}

DELIMITER $$

CREATE TRIGGER {{TRIGGER_NAME}}
{{TRIGGER_TIME}} {{TRIGGER_EVENT}} ON {{TABLE_NAME}}
FOR EACH ROW
BEGIN
{{TRIGGER_BODY}}
END$$

DELIMITER ;
