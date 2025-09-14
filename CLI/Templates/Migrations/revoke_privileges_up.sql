-- =============================================================================
-- revoke_privileges_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Revoke privileges from {{USERNAME}}

REVOKE {{PRIVILEGES}} ON {{DATABASE}}.{{OBJECT}} FROM '{{USERNAME}}'@'{{HOST}}';
FLUSH PRIVILEGES;
