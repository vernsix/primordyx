-- =============================================================================
-- transform_data_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Transform data in {{TABLE_NAME}}

-- Example: Update existing data
UPDATE {{TABLE_NAME}}
SET {{COLUMN}} = {{TRANSFORMATION}}
WHERE {{CONDITION}};

-- Example: Populate new column based on existing data
-- UPDATE {{TABLE_NAME}}
-- SET {{NEW_COLUMN}} = CONCAT({{EXISTING_COLUMN1}}, ' ', {{EXISTING_COLUMN2}})
-- WHERE {{NEW_COLUMN}} IS NULL;
