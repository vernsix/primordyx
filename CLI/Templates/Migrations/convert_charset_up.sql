-- =============================================================================
-- convert_charset_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Convert charset for {{TABLE_NAME}} to {{NEW_CHARSET}}

ALTER TABLE {{TABLE_NAME}}
    CONVERT TO CHARACTER SET {{NEW_CHARSET}} COLLATE {{NEW_COLLATION}};
