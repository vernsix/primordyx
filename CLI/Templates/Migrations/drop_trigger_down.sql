-- =============================================================================
-- drop_trigger_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create trigger {{TRIGGER_NAME}} on {{TABLE_NAME}}

-- WARNING: Customize this with the actual trigger definition
DELIMITER $$

CREATE TRIGGER {{TRIGGER_NAME}}
{{TRIGGER_TIME}} {{TRIGGER_EVENT}} ON {{TABLE_NAME}}
FOR EACH ROW
BEGIN
{{TRIGGER_BODY}}
END$$

DELIMITER ;
