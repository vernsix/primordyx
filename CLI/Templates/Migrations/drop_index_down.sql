-- =============================================================================
-- drop_index_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Add index {{INDEX_NAME}} back to {{TABLE_NAME}}

-- WARNING: Customize this with the actual index definition
CREATE {{INDEX_TYPE}} INDEX {{INDEX_NAME}} ON {{TABLE_NAME}} ({{COLUMNS}});
