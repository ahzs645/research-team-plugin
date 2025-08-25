# Research Team Manager - Utilities

This folder contains utility scripts for importing, managing, and maintaining the Research Team Manager plugin data.

## ⚠️ Important Warning

**These scripts directly modify your WordPress database. Always backup your database before running any of these utilities.**

## 📁 Files Overview

### Import Scripts

#### `import-team-members.php`
Primary import script for team member data from JSON exports.

**Usage:**
1. Place your JSON export file in this directory
2. Access via: `http://yoursite.com/wp-content/plugins/research-team-manager/utilities/import-team-members.php`
3. Follow the web interface to upload and import data

**Features:**
- JSON file upload interface
- Field mapping from old site structures
- Progress tracking and error reporting
- Dry-run capability for testing
- Duplicate detection and handling

#### `direct-import.php`
Direct database-to-database import utility for migrating from another WordPress site.

**Usage:**
1. Configure database connection settings in the script
2. Access via: `http://yoursite.com/wp-content/plugins/research-team-manager/utilities/direct-import.php`
3. Run the import process

**Features:**
- Direct database connection
- Real-time progress tracking
- Field mapping and transformation
- Image migration support
- Taxonomy term creation

#### `import-team-members-zip.php`
Advanced import script that handles ZIP files containing both data and media.

**Features:**
- ZIP file upload and extraction
- Media file import (profile pictures)
- Bulk data processing
- Error logging and recovery

### Data Management Scripts

#### `clear-team-members.php`
**⚠️ DESTRUCTIVE OPERATION** - Removes all team member data from the database.

**Usage:**
1. Access via: `http://yoursite.com/wp-content/plugins/research-team-manager/utilities/clear-team-members.php`
2. Type confirmation phrase exactly as prompted
3. Confirm deletion

**What it removes:**
- All team member posts
- Associated meta data
- Featured images
- Taxonomy terms (optional)
- Custom field data

### Data Files

#### `articles-import.json`
Sample publications data for testing import functionality.

**Structure:**
```json
[
  {
    "title": "Publication Title",
    "authors": "Author Names",
    "journal": "Journal Name",
    "publication_year": 2023,
    "citation_count": 0,
    "pub_url": "https://example.com",
    "extraction_order": 1
  }
]
```

#### `research-team-manager-original.php.bak`
Backup of the original plugin file before modifications.

## 🔧 Setup Instructions

### Prerequisites
1. WordPress installation with Research Team Manager plugin active
2. PHP with appropriate permissions to read/write files
3. Database backup (REQUIRED before running any script)

### Configuration Steps

1. **Database Backup**
   ```bash
   # Create a backup before proceeding
   mysqldump -u username -p database_name > backup.sql
   ```

2. **File Permissions**
   Ensure the utilities directory has appropriate permissions:
   ```bash
   chmod 755 utilities/
   chmod 644 utilities/*.php
   ```

3. **Security Configuration**
   For production sites, restrict access to these utilities:
   ```apache
   # Add to .htaccess in utilities folder
   <Files "*.php">
   Require ip 127.0.0.1
   Require ip YOUR_IP_ADDRESS
   </Files>
   ```

## 📋 Usage Guidelines

### Before Running Any Script

1. **Backup Everything**
   - Database backup
   - Files backup
   - Test on staging environment first

2. **Verify Prerequisites**
   - Plugin is active
   - WordPress is accessible
   - PHP error logging is enabled

3. **Prepare Data**
   - Validate JSON format
   - Check file permissions
   - Review field mappings

### Import Process Workflow

1. **Clear Existing Data** (if needed)
   - Run `clear-team-members.php` if starting fresh
   - Confirm all data removal

2. **Run Import**
   - Use appropriate import script based on your data source
   - Monitor progress and error logs
   - Verify results in WordPress admin

3. **Post-Import Tasks**
   - Check team member pages
   - Verify images and media
   - Test frontend display
   - Update any custom templates

## 🐛 Troubleshooting

### Common Issues

**Import Fails with Memory Error:**
- Increase PHP memory limit: `ini_set('memory_limit', '512M');`
- Process data in smaller batches
- Use direct import for large datasets

**Images Not Importing:**
- Check file permissions on uploads directory
- Verify image URLs are accessible
- Ensure sufficient disk space

**Database Connection Errors:**
- Verify database credentials
- Check MySQL connection limits
- Confirm database user permissions

**Field Mapping Issues:**
- Review `FIELD-MAPPING.md` in root directory
- Check custom field registration
- Verify taxonomy terms exist

### Debug Mode

Enable debug mode by adding to the script:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Files

Check these locations for error logs:
- `/wp-content/debug.log`
- Server error logs
- PHP error logs

## 🔒 Security Considerations

### Production Environment

1. **Restrict Access**
   - Use IP whitelisting
   - Require authentication
   - Remove utilities after use

2. **Secure Data**
   - Encrypt sensitive information
   - Use secure transfer methods
   - Validate all inputs

3. **Monitor Activity**
   - Log all script executions
   - Monitor database changes
   - Track file modifications

### Best Practices

- Always test on staging first
- Run scripts during low-traffic periods
- Monitor server resources during import
- Keep backups of successful imports
- Document any custom modifications

## 📝 Customization

### Modifying Import Scripts

When customizing import scripts:

1. **Field Mapping**
   ```php
   // Add custom field mappings
   $field_mappings = array(
       'old_field' => '_rtm_new_field',
       'custom_field' => '_rtm_custom_field'
   );
   ```

2. **Data Transformation**
   ```php
   // Add data transformation logic
   function transform_member_data($data) {
       // Custom transformation logic
       return $data;
   }
   ```

3. **Error Handling**
   ```php
   // Enhanced error logging
   function log_import_error($message, $data = null) {
       error_log("RTM Import Error: " . $message);
       if ($data) {
           error_log("Data: " . print_r($data, true));
       }
   }
   ```

## 📞 Support

For issues with these utilities:

1. Check the main plugin documentation
2. Review troubleshooting steps above
3. Enable debug mode for detailed error information
4. Ensure you have recent database backups before seeking help

---

**Remember: These are powerful tools that can significantly modify your WordPress database. Always backup before use!**
