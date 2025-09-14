-- =============================================================================
-- analyze_table_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Analyze and optimize {{TABLE_NAME}}

ANALYZE TABLE {{TABLE_NAME}};
OPTIMIZE TABLE {{TABLE_NAME}};
