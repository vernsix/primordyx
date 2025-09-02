-- =============================================================================
-- grant_privileges_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Grant privileges to {{USERNAME}}

GRANT {{PRIVILEGES}} ON {{DATABASE}}.{{OBJECT}} TO '{{USERNAME}}'@'{{HOST}}';
FLUSH PRIVILEGES;
