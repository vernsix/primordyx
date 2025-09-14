-- =============================================================================
-- partition_table_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Remove partitioning from {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}} REMOVE PARTITIONING;
