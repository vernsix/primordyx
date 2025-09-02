-- =============================================================================
-- add_fulltext_index_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add fulltext index {{INDEX_NAME}} to {{TABLE_NAME}}

CREATE FULLTEXT INDEX {{INDEX_NAME}} ON {{TABLE_NAME}} ({{COLUMNS}});
