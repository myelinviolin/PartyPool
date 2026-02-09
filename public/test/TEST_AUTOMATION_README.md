# Party Carpool Test Automation

## Overview
Automatic testing has been set up to ensure data integrity for map locations and route generation. The lake location test will now run automatically whenever optimization is performed.

## Test Suite Components

### 1. Lake Location Test (`test_map_locations.php`)
- Verifies all participants are placed at valid land coordinates
- Checks that no one appears in Lake Mendota, Lake Monona, or Lake Waubesa
- Validates coordinates are within Madison area bounds
- Tests coordinate precision

### 2. Route Alignment Check (`check_route_alignment.php`)
- Verifies route polylines align with participant icon placements
- Detects mismatches between database coordinates and route data
- Ensures visual consistency on the map

### 3. Pre-Optimization Checks (`pre_optimize_checks.php`)
- Automatically runs before any optimization
- Includes lake location test
- Validates database integrity
- Checks coordinate formats

## Usage

### Manual Test Running
Run all tests at once:
```bash
cd /var/www/partycarpool.clodhost.com/public/test
./run_all_tests.sh
```

Run individual tests:
```bash
php test_map_locations.php
php check_route_alignment.php
```

### Automatic Testing
Tests run automatically when:
- Optimization is triggered through the admin dashboard
- The optimize_enhanced.php endpoint is called
- Pre-optimization checks detect any data issues

## Test Results

✅ **Current Status: All Tests Passing**
- All participants correctly placed on land
- No coordinates in water bodies
- Route lines perfectly aligned with icons
- Database integrity verified

## Recent Fixes Applied
1. Corrected Emily Williams coordinates (moved west from Lake Monona)
2. Corrected Kevin Martinez coordinates (moved west from Lake Monona)
3. Regenerated optimization with corrected coordinates
4. Verified all route lines now align with icon placements

## Integration Points
- `optimize_enhanced.php` - Includes pre-optimization checks
- `dashboard.php` - Displays optimization results with verified data
- `app.js` - Renders verified routes with correct polylines

## Error Handling
If pre-optimization checks fail:
- Optimization will be blocked
- Error messages will indicate specific issues
- Admin will need to fix data before proceeding

## Maintenance
- Tests should be run after any database updates
- Add new water body boundaries as needed
- Update coordinate bounds if service area expands