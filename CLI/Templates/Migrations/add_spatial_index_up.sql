-- =============================================================================
-- add_spatial_index_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add spatial index {{INDEX_NAME}} to {{TABLE_NAME}}

CREATE SPATIAL INDEX {{INDEX_NAME}} ON {{TABLE_NAME}} ({{COLUMN}});
