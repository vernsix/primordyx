-- =============================================================================
-- add_foreign_key_up.sql
-- =============================================================================
-- Migration: {{MIGRATION_NAME}}
-- Type: {{MIGRATION_TYPE}}
-- Generated: {{TIMESTAMP}}
-- Description: Add foreign key {{FK_NAME}} to {{TABLE_NAME}}

ALTER TABLE {{TABLE_NAME}}
    ADD CONSTRAINT {{FK_NAME}}
    FOREIGN KEY ({{COLUMN}})
    REFERENCES {{FOREIGN_TABLE}} ({{FOREIGN_COLUMN}})
    ON DELETE {{ON_DELETE}}
ON UPDATE {{ON_UPDATE}};
