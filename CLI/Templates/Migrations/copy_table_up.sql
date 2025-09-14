-- =============================================================================
-- copy_table_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Copy table {{SOURCE_TABLE}} to {{DESTINATION_TABLE}}

-- Create table structure
CREATE TABLE {{DESTINATION_TABLE}} LIKE {{SOURCE_TABLE}};

-- Copy data (if needed)
INSERT INTO {{DESTINATION_TABLE}} SELECT * FROM {{SOURCE_TABLE}} {{WHERE_CONDITION}};
