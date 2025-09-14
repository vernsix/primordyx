-- =============================================================================
-- add_user_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Remove MySQL user {{USERNAME}}

-- Revoke all privileges
REVOKE ALL PRIVILEGES ON {{DATABASE}}.* FROM '{{USERNAME}}'@'{{HOST}}';

-- Drop user
DROP USER IF EXISTS '{{USERNAME}}'@'{{HOST}}';

-- Apply changes
FLUSH PRIVILEGES;
