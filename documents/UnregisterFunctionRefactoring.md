# Treatment Planner Module - Unregister Function Refactoring

**Date:** June 26, 2025  
**File:** `ModuleManagerListener.php`  
**Function:** `unregister($modId, $currentActionStatus)`  
**Line:** 151

## Overview

The `unregister` function in the Treatment Planner module has been completely refactored to provide comprehensive cleanup when the module is unregistered from OpenEMR. The original function contained only skeleton/placeholder code and did not perform any actual cleanup operations.

## Problem Statement

The original `unregister` function:
- Contained only commented-out skeleton code
- Did not remove any module-specific database tables
- Did not clean up layout forms and options
- Did not remove list options for mental health assessments
- Left orphaned data in the database after module removal

## Solution Implementation

### 1. Database Table Cleanup

The refactored function now drops all module-specific tables:

```php
$tables = [
    'module_treatment_planner_config',
    'module_treatment_planner_plans',
    'module_treatment_planner_templates',
    'module_treatment_planner_usage'
];

foreach ($tables as $table) {
    $sql = "DROP TABLE IF EXISTS `$table`";
    $result = sqlQuery($sql);
    // Error handling and logging...
}
```

### 2. Layout Form Cleanup

Removes all layout components for the LBF_MHINTAKE form:

- **Layout Options**: All field definitions for the mental health intake form
- **Layout Group Properties**: Form structure and grouping information

```php
// Clean up layout options
$sql = "DELETE FROM `layout_options` WHERE `form_id` = 'LBF_MHINTAKE'";

// Clean up layout group properties  
$sql = "DELETE FROM `layout_group_properties` WHERE `grp_form_id` = 'LBF_MHINTAKE'";
```

### 3. Mental Health Assessment Lists Cleanup

Removes 27 different mental health assessment list options:

- Affect-related lists (amplitude, appropriateness, intensity, mobility, range)
- Appearance and attitude assessments
- Cognitive assessments (intelligence, memory, orientation)
- Speech and communication assessments
- Thought process and content assessments
- Risk assessment lists

```php
$mentalHealthLists = [
    'mental_status_affect_amplitude',
    'mental_status_affect_appropriateness',
    'mental_status_affect_intensity',
    // ... (24 more lists)
    'RiskProtection',
    'risklevel'
];
```

### 4. Error Handling and Logging

Comprehensive error handling with detailed logging:

```php
try {
    // Cleanup operations...
    
    if (!empty($errors)) {
        $currentActionStatus = 'Partial cleanup completed with errors: ' . implode('; ', $errors);
    } else {
        $logMessage .= "Treatment Planner module cleanup completed successfully\n";
    }
    
} catch (Exception $e) {
    $currentActionStatus = 'Error during cleanup: ' . $e->getMessage();
}

error_log(text($logMessage));
```

## Key Features

### Safety Measures
- Uses `DROP TABLE IF EXISTS` to prevent errors if tables don't exist
- Parameterized queries to prevent SQL injection
- Exception handling to prevent function crashes
- Continues cleanup even if individual operations fail

### Comprehensive Coverage
- **4 Database Tables**: All module-specific tables are removed
- **Layout Components**: Complete form structure cleanup
- **27 List Options**: All mental health assessment lists
- **Registry Entries**: Removes list registrations from the main lists table

### Detailed Logging
- Each operation is logged with success/failure status
- Error messages are captured and reported
- Complete audit trail of cleanup operations
- Logs are written to OpenEMR's error log

### Status Reporting
- Returns success status when all operations complete
- Reports partial completion with specific error details
- Provides meaningful error messages for troubleshooting

## Database Components Removed

### Tables
1. `module_treatment_planner_config` - Module configuration settings
2. `module_treatment_planner_plans` - Patient treatment plans
3. `module_treatment_planner_templates` - Treatment plan templates
4. `module_treatment_planner_usage` - API usage tracking

### Layout Forms
- `LBF_MHINTAKE` - Mental Health Intake Assessment Form
  - All field definitions and properties
  - Form structure and grouping
  - Field validation and display rules

### List Options
Mental health assessment dropdown lists including:
- Affect assessments (amplitude, appropriateness, intensity, mobility, range)
- Appearance and hygiene assessments
- Cognitive assessments (intelligence, memory, judgment, orientation)
- Speech assessments (rate, rhythm, volume)
- Thought assessments (process, content)
- Risk and protection assessments

## Benefits

1. **Complete Cleanup**: No orphaned data remains after module removal
2. **Database Integrity**: Prevents accumulation of unused database objects
3. **Storage Efficiency**: Reclaims database space used by module data
4. **Clean Reinstallation**: Ensures fresh installation if module is reinstalled
5. **Audit Trail**: Detailed logging for troubleshooting and compliance
6. **Error Recovery**: Graceful handling of cleanup failures

## Usage

The function is automatically called by OpenEMR's Module Manager when:
- The module is unregistered through the admin interface
- The module is being completely removed from the system
- A clean reinstallation is being performed

## Considerations for Future Development

### Data Preservation Options
Consider adding options to:
- Backup patient treatment plans before deletion
- Preserve historical usage data for reporting
- Export configuration settings for migration

### Confirmation Dialogs
Consider adding:
- Admin confirmation before destructive operations
- Option to preview what will be deleted
- Selective cleanup options

### Background Services
If background services are added in the future, ensure they are also cleaned up in this function.

## Testing Recommendations

1. **Test with existing data**: Verify cleanup works with populated tables
2. **Test with missing tables**: Ensure no errors when tables don't exist
3. **Test partial failures**: Verify function continues when some operations fail
4. **Test logging**: Confirm all operations are properly logged
5. **Test reinstallation**: Verify clean reinstallation after unregistration

## Conclusion

The refactored `unregister` function provides comprehensive, safe, and well-logged cleanup of all Treatment Planner module components. This ensures proper database hygiene and prevents orphaned data accumulation in OpenEMR installations.
