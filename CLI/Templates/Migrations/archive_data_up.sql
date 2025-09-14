-- =============================================================================
-- archive_data_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Archive data from {{TABLE_NAME}} to {{ARCHIVE_TABLE}}

-- Create archive table if it doesn't exist
CREATE TABLE IF NOT EXISTS {{ARCHIVE_TABLE}} LIKE {{TABLE_NAME}};

-- Archive old data
INSERT INTO {{ARCHIVE_TABLE}}
SELECT * FROM {{TABLE_NAME}}
WHERE {{ARCHIVE_CONDITION}};

-- Remove archived data from original table
DELETE FROM {{TABLE_NAME}}
WHERE {{ARCHIVE_CONDITION}};
