-- =============================================================================
-- backup_table_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create backup of {{TABLE_NAME}} as {{BACKUP_TABLE}}

-- Create backup table with current data
CREATE TABLE {{BACKUP_TABLE}} AS SELECT * FROM {{TABLE_NAME}};
