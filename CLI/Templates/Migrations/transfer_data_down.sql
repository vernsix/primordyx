-- =============================================================================
-- transform_data_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Reverse data transformation in {{TABLE_NAME}}

-- WARNING: Customize this to reverse your specific transformation
-- This is highly dependent on what transformation was performed

UPDATE {{TABLE_NAME}}
SET {{COLUMN}} = {{REVERSE_TRANSFORMATION}}
WHERE {{REVERSE_CONDITION}};
