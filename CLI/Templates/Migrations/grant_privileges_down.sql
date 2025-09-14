-- =============================================================================
-- grant_privileges_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Revoke privileges from {{USERNAME}}

REVOKE {{PRIVILEGES}} ON {{DATABASE}}.{{OBJECT}} FROM '{{USERNAME}}'@'{{HOST}}';
FLUSH PRIVILEGES;
