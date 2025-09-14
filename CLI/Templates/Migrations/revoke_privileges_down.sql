-- =============================================================================
-- revoke_privileges_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Grant privileges back to {{USERNAME}}

GRANT {{PRIVILEGES}} ON {{DATABASE}}.{{OBJECT}} TO '{{USERNAME}}'@'{{HOST}}';
FLUSH PRIVILEGES;
