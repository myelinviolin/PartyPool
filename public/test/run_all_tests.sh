#!/bin/bash

# Party Carpool Automated Test Suite
# Runs all critical tests to ensure data integrity

echo "=================================="
echo "Party Carpool Automated Test Suite"
echo "=================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track overall test status
ALL_PASSED=true

# Test 1: Lake Location Verification
echo -e "${YELLOW}[1/2] Running Lake Location Test...${NC}"
php test_map_locations.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Lake location test passed${NC}"
else
    echo -e "${RED}✗ Lake location test failed${NC}"
    ALL_PASSED=false
fi
echo ""

# Test 2: Route Alignment Check
echo -e "${YELLOW}[2/2] Running Route Alignment Check...${NC}"
php check_route_alignment.php | tail -n 1
if [ $? -eq 0 ] && php check_route_alignment.php | grep -q "All route lines perfectly align"; then
    echo -e "${GREEN}✓ Route alignment test passed${NC}"
else
    echo -e "${RED}✗ Route alignment test failed${NC}"
    ALL_PASSED=false
fi
echo ""

# Summary
echo "=================================="
if [ "$ALL_PASSED" = true ]; then
    echo -e "${GREEN}All tests passed successfully!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review the output above.${NC}"
    exit 1
fi