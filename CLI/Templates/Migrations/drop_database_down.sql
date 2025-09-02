-- =============================================================================
-- drop_database_down.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Rollback: Create database {{DATABASE_NAME}}

CREATE DATABASE {{DATABASE_NAME}}
DEFAULT CHARACTER SET {{CHARSET}}
DEFAULT COLLATE {{COLLATION}};
