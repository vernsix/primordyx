-- =============================================================================
-- add_fulltext_index_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Drop fulltext index {{INDEX_NAME}} from {{TABLE_NAME}}

DROP INDEX {{INDEX_NAME}} ON {{TABLE_NAME}};
