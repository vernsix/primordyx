-- =============================================================================
-- create_role_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Create role {{ROLE_NAME}} (MySQL 8.0+)

CREATE ROLE {{ROLE_NAME}};
GRANT {{ROLE_PRIVILEGES}} TO {{ROLE_NAME}};
