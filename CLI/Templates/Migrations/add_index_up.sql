-- =============================================================================
-- add_index_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add index {{INDEX_NAME}} to {{TABLE_NAME}}

CREATE {{INDEX_TYPE}} INDEX {{INDEX_NAME}} ON {{TABLE_NAME}} ({{COLUMNS}});
