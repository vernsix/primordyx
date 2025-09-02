# Migration Template Usage Guide

## Template Overview

Each template requires specific variables to be passed via command line options. Here are usage examples for each template type:

## 1. **create_table**
Create a new database table with standard required fields.

**Minimal Command (uses defaults):**
```bash
primordyx migrate create create_users_table --type=create_table
```

**Full Command with custom columns:**
```bash
primordyx migrate create create_users_table --type=create_table \
  --column_definitions="name VARCHAR(100) NOT NULL, email VARCHAR(255) UNIQUE NOT NULL," \
  --engine=InnoDB \
  --charset=utf8mb4 \
  --collation=utf8mb4_unicode_ci
```

**Standard Fields Included:**
- `id` - INT AUTO_INCREMENT PRIMARY KEY
- `created_at` - TIMESTAMP DEFAULT CURRENT_TIMESTAMP  
- `updated_at` - TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- `deleted_at` - TIMESTAMP NULL DEFAULT NULL (for soft deletes)
- `restored_at` - TIMESTAMP NULL DEFAULT NULL (for tracking restoration)

**Default Values:**
- `COLUMN_DEFINITIONS` → `-- Add your column definitions here` (comment placeholder)
- `ENGINE` → `InnoDB`
- `CHARSET` → `utf8mb4` 
- `COLLATION` → `utf8mb4_unicode_ci`

**Generated SQL (minimal command):**
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Add your column definitions here
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    restored_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. **add_column**
Add a new column to existing table.

**Command:**
```bash
primordyx migrate create add_phone_to_users --type=add_column \
  --column=phone \
  --column_type="VARCHAR(20)" \
  --column_options="NULL" \
  --after="AFTER email"
```

**Variables:**
- `COLUMN` → Column name
- `COLUMN_TYPE` → Data type
- `COLUMN_OPTIONS` → NULL/NOT NULL, DEFAULT, etc.
- `AFTER` → Position (AFTER column_name, FIRST, or empty)

## 3. **drop_column**
Remove a column from table.

**Command:**
```bash
primordyx migrate create remove_phone_from_users --type=drop_column \
  --column=phone \
  --column_type="VARCHAR(20)" \
  --column_options="NULL" \
  --after="AFTER email"
```

**Note:** Down migration needs original column definition for restoration.

## 4. **modify_column**
Change column definition.

**Command:**
```bash
primordyx migrate create modify_user_email --type=modify_column \
  --column=email \
  --new_column_type="VARCHAR(320)" \
  --new_column_options="NOT NULL" \
  --old_column_type="VARCHAR(255)" \
  --old_column_options="NOT NULL"
```

## 5. **add_index**
Add database index.

**Command:**
```bash
primordyx migrate create add_email_index --type=add_index \
  --index_name=idx_users_email \
  --index_type="" \
  --columns="email"

# For unique index:
primordyx migrate create add_unique_email_index --type=add_index \
  --index_name=idx_users_email_unique \
  --index_type="UNIQUE" \
  --columns="email"
```

## 6. **drop_index**
Remove database index.

**Command:**
```bash
primordyx migrate create remove_email_index --type=drop_index \
  --index_name=idx_users_email \
  --index_type="" \
  --columns="email"
```

## 7. **add_foreign_key**
Add foreign key constraint.

**Command:**
```bash
primordyx migrate create add_user_profile_fk --type=add_foreign_key \
  --table_name=profiles \
  --fk_name=fk_profiles_user_id \
  --column=user_id \
  --foreign_table=users \
  --foreign_column=id \
  --on_delete="CASCADE" \
  --on_update="CASCADE"
```

## 8. **drop_table**
Drop an entire table.

**Command:**
```bash
primordyx migrate create drop_old_logs_table --type=drop_table
```

**Warning:** Down migration creates empty table structure - data is lost!

## 9. **rename_column**
Rename a column.

**Command:**
```bash
primordyx migrate create rename_user_name --type=rename_column \
  --old_column=name \
  --new_column=full_name \
  --column_type="VARCHAR(100)" \
  --column_options="NOT NULL"
```

## 10. **rename_table**
Rename entire table.

**Command:**
```bash
primordyx migrate create rename_users_to_customers --type=rename_table \
  --old_table=users \
  --new_table=customers
```

## 11. **seed_table**
Insert seed data.

**Command:**
```bash
primordyx migrate create seed_admin_user --type=seed_table \
  --columns="name, email, role" \
  --seed_data="('Admin User', 'admin@example.com', 'admin'), ('Test User', 'test@example.com', 'user')" \
  --delete_condition="email IN ('admin@example.com', 'test@example.com')"
```

## 12. **copy_table**
Copy table structure and data.

**Command:**
```bash
primordyx migrate create backup_users_table --type=copy_table \
  --source_table=users \
  --destination_table=users_backup \
  --where_condition="WHERE created_at < '2024-01-01'"
```

## 13. **backup_table**
Create table backup.

**Command:**
```bash
primordyx migrate create backup_users --type=backup_table \
  --backup_table=users_backup_20250101
```

## 14. **create_view**
Create database view.

**Command:**
```bash
primordyx migrate create create_active_users_view --type=create_view \
  --view_name=active_users \
  --view_definition="SELECT id, name, email FROM users WHERE status = 'active'"
```

## 15. **add_unique**
Add unique constraint.

**Command:**
```bash
primordyx migrate create add_unique_email --type=add_unique \
  --constraint_name=uk_users_email \
  --columns="email"
```

## 16. **drop_constraint**
Drop any constraint.

**Command:**
```bash
primordyx migrate create drop_email_unique --type=drop_constraint \
  --constraint_name=uk_users_email \
  --constraint_type="INDEX" \
  --constraint_definition="UNIQUE (email)"
```

## 17. **move_column**
Reposition column (MySQL specific).

**Command:**
```bash
primordyx migrate create move_email_after_name --type=move_column \
  --column=email \
  --column_type="VARCHAR(255)" \
  --column_options="NOT NULL" \
  --position="AFTER name" \
  --original_position="AFTER id"
```

## 18. **transform_data**
Transform existing data.

**Command:**
```bash
primordyx migrate create normalize_emails --type=transform_data \
  --column=email \
  --transformation="LOWER(email)" \
  --condition="email != LOWER(email)" \
  --reverse_transformation="email" \
  --reverse_condition="1=1"
```

## 19. **change_engine**
Change table storage engine.

**Command:**
```bash
primordyx migrate create change_users_to_myisam --type=change_engine \
  --new_engine=MyISAM \
  --old_engine=InnoDB
```

## 20. **blank**
Custom migration template.

**Command:**
```bash
primordyx migrate create custom_migration --type=blank
```

**Use case:** For complex migrations that don't fit standard patterns.

## 21. **add_user**
Add MySQL user account.

**Command:**
```bash
primordyx migrate create add_app_user --type=add_user \
  --username=app_user \
  --host="%" \
  --password="secure_password_123" \
  --privileges="SELECT, INSERT, UPDATE, DELETE" \
  --database=myapp_production
```

## Additional Templates (22-45)

## 22. **create_database**
Create a new database/schema.

**Command:**
```bash
primordyx migrate create create_app_database --type=create_database \
  --database_name=myapp_production \
  --charset=utf8mb4 \
  --collation=utf8mb4_unicode_ci
```

## 23. **drop_database**
Drop an entire database.

**Command:**
```bash
primordyx migrate create drop_old_database --type=drop_database \
  --database_name=old_app_db
```

**Warning:** Permanently deletes entire database and all data!

## 24. **create_trigger**
Create database triggers.

**Command:**
```bash
primordyx migrate create create_audit_trigger --type=create_trigger \
  --trigger_name=users_audit_trigger \
  --trigger_time=AFTER \
  --trigger_event=UPDATE \
  --trigger_body="INSERT INTO audit_log (table_name, action, user_id) VALUES ('users', 'UPDATE', NEW.id);"
```

**Variables:**
- `TRIGGER_TIME` → BEFORE/AFTER
- `TRIGGER_EVENT` → INSERT/UPDATE/DELETE

## 25. **drop_trigger**
Remove database triggers.

**Command:**
```bash
primordyx migrate create remove_audit_trigger --type=drop_trigger \
  --trigger_name=users_audit_trigger
```

## 26. **create_procedure**
Create stored procedures.

**Command:**
```bash
primordyx migrate create create_user_cleanup --type=create_procedure \
  --procedure_name=CleanupInactiveUsers \
  --parameters="IN days_inactive INT" \
  --procedure_body="DELETE FROM users WHERE last_login < DATE_SUB(NOW(), INTERVAL days_inactive DAY);"
```

## 27. **drop_procedure**
Remove stored procedures.

**Command:**
```bash
primordyx migrate create remove_user_cleanup --type=drop_procedure \
  --procedure_name=CleanupInactiveUsers
```

## 28. **create_function**
Create database functions.

**Command:**
```bash
primordyx migrate create create_full_name_function --type=create_function \
  --function_name=GetFullName \
  --parameters="first_name VARCHAR(50), last_name VARCHAR(50)" \
  --return_type="VARCHAR(100)" \
  --function_characteristics="DETERMINISTIC READS SQL DATA" \
  --function_body="RETURN CONCAT(first_name, ' ', last_name);"
```

## 29. **drop_function**
Remove database functions.

**Command:**
```bash
primordyx migrate create remove_full_name_function --type=drop_function \
  --function_name=GetFullName
```

## 30. **add_fulltext_index**
Add fulltext search index.

**Command:**
```bash
primordyx migrate create add_search_index --type=add_fulltext_index \
  --index_name=idx_posts_search \
  --columns="title, content"
```

## 31. **add_spatial_index**
Add spatial index for geographic data.

**Command:**
```bash
primordyx migrate create add_location_index --type=add_spatial_index \
  --index_name=idx_stores_location \
  --column=coordinates
```

## 32. **analyze_table**
Analyze and optimize table performance.

**Command:**
```bash
primordyx migrate create optimize_users_table --type=analyze_table
```

## 33. **partition_table**
Add table partitioning.

**Command:**
```bash
primordyx migrate create partition_logs_table --type=partition_table \
  --partition_type=RANGE \
  --partition_expression="YEAR(created_at)" \
  --partition_definitions="PARTITION p2023 VALUES LESS THAN (2024), PARTITION p2024 VALUES LESS THAN (2025)"
```

## 34. **truncate_table**
Empty table data (faster than DELETE).

**Command:**
```bash
primordyx migrate create clear_temp_data --type=truncate_table
```

**Warning:** Data cannot be recovered after truncate!

## 35. **archive_data**
Archive old data to separate table.

**Command:**
```bash
primordyx migrate create archive_old_logs --type=archive_data \
  --archive_table=logs_archive \
  --archive_condition="created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)" \
  --restore_condition="created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"
```

## 36. **purge_data**
Delete data based on conditions.

**Command:**
```bash
primordyx migrate create purge_old_sessions --type=purge_data \
  --purge_condition="expires_at < NOW()"
```

## 37. **migrate_data**
Move data between tables/columns.

**Command:**
```bash
primordyx migrate create migrate_user_profiles --type=migrate_data \
  --source_table=old_profiles \
  --target_table=user_profiles \
  --source_columns="user_id, bio, avatar" \
  --target_columns="user_id, biography, profile_image" \
  --migration_condition="migrated = 0" \
  --rollback_condition="source = 'migration'"
```

## 38. **grant_privileges**
Grant specific database privileges.

**Command:**
```bash
primordyx migrate create grant_read_access --type=grant_privileges \
  --username=readonly_user \
  --privileges="SELECT" \
  --database=myapp \
  --object="*" \
  --host="%"
```

## 39. **revoke_privileges**
Revoke database privileges.

**Command:**
```bash
primordyx migrate create revoke_write_access --type=revoke_privileges \
  --username=temp_user \
  --privileges="INSERT, UPDATE, DELETE" \
  --database=myapp \
  --object="*"
```

## 40. **create_role**
Create user roles (MySQL 8.0+).

**Command:**
```bash
primordyx migrate create create_admin_role --type=create_role \
  --role_name=app_admin \
  --role_privileges="SELECT, INSERT, UPDATE, DELETE ON myapp.*"
```

## 41. **drop_role**
Remove user roles.

**Command:**
```bash
primordyx migrate create remove_admin_role --type=drop_role \
  --role_name=app_admin
```

## 42. **add_check_constraint**
Add CHECK constraints.

**Command:**
```bash
primordyx migrate create add_age_check --type=add_check_constraint \
  --constraint_name=chk_users_age \
  --check_condition="age >= 0 AND age <= 150"
```

## 43. **modify_table_options**
Change table options.

**Command:**
```bash
primordyx migrate create reset_auto_increment --type=modify_table_options \
  --table_options="AUTO_INCREMENT = 1000" \
  --original_table_options="AUTO_INCREMENT = 1"
```

## 44. **convert_charset**
Convert table character set.

**Command:**
```bash
primordyx migrate create convert_to_utf8mb4 --type=convert_charset \
  --new_charset=utf8mb4 \
  --new_collation=utf8mb4_unicode_ci \
  --old_charset=utf8 \
  --old_collation=utf8_general_ci
```

## 45. **add_generated_column**
Add computed/generated columns.

**Command:**
```bash
primordyx migrate create add_full_name_column --type=add_generated_column \
  --column=full_name \
  --column_type="VARCHAR(200)" \
  --generation_expression="CONCAT(first_name, ' ', last_name)" \
  --generation_type="STORED"
```

**Variables:**
- `GENERATION_TYPE` → STORED (physically stored) or VIRTUAL (computed on access)

## Default Values System

**If you don't specify template variables, the generator provides sensible defaults:**

### **Always Available:**
- `MIGRATION_NAME` → Your migration name
- `MIGRATION_TYPE` → Template type used  
- `TABLE_NAME` → Auto-extracted from migration name
- `TIMESTAMP` → Current date/time

### **Template-Specific Defaults:**
- `COLUMN_DEFINITIONS` → `-- Add your column definitions here`
- `ENGINE` → `InnoDB`
- `CHARSET` → `utf8mb4`
- `COLLATION` → `utf8mb4_unicode_ci`
- `COLUMN_OPTIONS` → `` (empty)
- `AFTER` → `` (empty - places at end)
- `INDEX_TYPE` → `` (empty - regular index)
- `ON_DELETE` → `RESTRICT`
- `ON_UPDATE` → `RESTRICT`
- `HOST` → `%` (any host)
- `PRIVILEGES` → `SELECT, INSERT, UPDATE, DELETE`
- `DATABASE` → `*` (all databases)
- `DATABASE_NAME` → Extracted from migration name
- `TRIGGER_TIME` → `AFTER`
- `TRIGGER_EVENT` → `UPDATE`
- `TRIGGER_BODY` → `-- Add trigger logic here`
- `PROCEDURE_BODY` → `-- Add procedure logic here`
- `FUNCTION_BODY` → `-- Add function logic here`
- `RETURN_TYPE` → `VARCHAR(255)`
- `FUNCTION_CHARACTERISTICS` → `DETERMINISTIC`
- `PARTITION_TYPE` → `RANGE`
- `ARCHIVE_CONDITION` → `created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)`
- `GENERATION_TYPE` → `VIRTUAL`
- `NEW_CHARSET` → `utf8mb4`
- `NEW_COLLATION` → `utf8mb4_unicode_ci`

### **What This Means:**
- **Minimal commands work**: You can use `--type=create_table` without any other options
- **Generated files are always valid**: No syntax errors from missing variables
- **Comment placeholders**: Missing required variables become SQL comments for manual editing
- **Sensible defaults**: Common values are pre-filled (InnoDB, utf8mb4, etc.)

### **Example - Minimal vs Full:**

**Minimal:**
```bash
primordyx migrate create add_phone_to_users --type=add_column --column=phone
```

**Generated SQL:**
```sql
ALTER TABLE users 
ADD COLUMN phone   ;  -- Uses empty defaults for COLUMN_TYPE and COLUMN_OPTIONS
```

**Better:**
```bash
primordyx migrate create add_phone_to_users --type=add_column \
  --column=phone --column_type="VARCHAR(20)" --column_options="NULL"
```

**Generated SQL:**
```sql
ALTER TABLE users 
ADD COLUMN phone VARCHAR(20) NULL ;
```

### **Auto-extracted Variables:**
- `TABLE_NAME` - Extracted from migration name
- `MIGRATION_NAME` - The full migration name
- `MIGRATION_TYPE` - Template type used
- `TIMESTAMP` - Generation timestamp

### **Position Options:**
- `AFTER column_name` - Place after specific column
- `FIRST` - Place as first column
- Empty - Place at end

### **Privilege Examples:**
- `ALL PRIVILEGES` - Full access
- `SELECT, INSERT, UPDATE, DELETE` - CRUD operations
- `SELECT` - Read-only access

### **Engine Options:**
- `InnoDB` - Default, supports transactions
- `MyISAM` - Faster for read-heavy workloads
- `MEMORY` - In-memory storage

## Best Practices

1. **Always test with --dry-run first**
2. **Backup data before destructive operations**
3. **Use descriptive migration names**
4. **Document complex transformations**
5. **Test rollback migrations**
6. **Use appropriate data types and constraints**
7. **Consider performance impact of large data changes**

## Example Workflow

```bash
# 1. Create migration with dry run
primordyx migrate create add_phone_to_users --type=add_column \
  --column=phone --column_type="VARCHAR(20)" --dry-run

# 2. Run actual migration
primordyx migrate create add_phone_to_users --type=add_column \
  --column=phone --column_type="VARCHAR(20)"

# 3. Apply migration
primordyx migrate up

# 4. If needed, rollback
primordyx migrate down
```