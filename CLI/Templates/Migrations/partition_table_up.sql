-- =============================================================================
-- partition_table_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add partitioning to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    PARTITION BY {{PARTITION_TYPE}} ({{PARTITION_EXPRESSION}})
    ({{PARTITION_DEFINITIONS}});
