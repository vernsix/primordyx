-- =============================================================================
-- add_user_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add MySQL user {{USERNAME}}

-- Drop user if exists to ensure clean state
DROP USER IF EXISTS '{{USERNAME}}'@'{{HOST}}';

-- Create user with mysql_native_password authentication
CREATE USER '{{USERNAME}}'@'{{HOST}}' IDENTIFIED WITH mysql_native_password BY '{{PASSWORD}}';

-- Grant privileges
GRANT {{PRIVILEGES}} ON {{DATABASE}}.* TO '{{USERNAME}}'@'{{HOST}}';

-- Apply changes
FLUSH PRIVILEGES;