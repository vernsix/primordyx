-- =============================================================================
-- drop_role_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create role {{ROLE_NAME}}

-- WARNING: Customize this with the actual role definition
CREATE ROLE {{ROLE_NAME}};
GRANT {{ROLE_PRIVILEGES}} TO {{ROLE_NAME}};
