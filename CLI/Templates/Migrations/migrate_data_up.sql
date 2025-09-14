-- =============================================================================
-- migrate_data_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Migrate data from {{SOURCE_TABLE}} to {{TARGET_TABLE}}

-- Copy data to target table
INSERT INTO {{TARGET_TABLE}} ({{TARGET_COLUMNS}})
SELECT {{SOURCE_COLUMNS}}
FROM {{SOURCE_TABLE}}
WHERE {{MIGRATION_CONDITION}};
