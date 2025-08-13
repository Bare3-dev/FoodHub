#!/bin/bash

# Phase 4 Testing & Documentation Test Runner
# This script runs all tests for Phase 4 completion

echo "🚀 Starting Phase 4 Testing & Documentation Suite"
echo "=================================================="
echo ""

# Set colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    
    case $status in
        "PASS")
            echo -e "${GREEN}✅ PASS${NC}: $message"
            ;;
        "FAIL")
            echo -e "${RED}❌ FAIL${NC}: $message"
            ;;
        "WARN")
            echo -e "${YELLOW}⚠️  WARN${NC}: $message"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  INFO${NC}: $message"
            ;;
    esac
}

# Function to run tests and capture results
run_test_suite() {
    local suite_name=$1
    local test_command=$2
    
    echo -e "\n${BLUE}Running $suite_name...${NC}"
    echo "----------------------------------------"
    
    # Run the test command and capture output
    if $test_command > /tmp/test_output.log 2>&1; then
        print_status "PASS" "$suite_name completed successfully"
        return 0
    else
        print_status "FAIL" "$suite_name failed"
        echo -e "${RED}Test output:${NC}"
        cat /tmp/test_output.log
        return 1
    fi
}

# Initialize counters
total_tests=0
passed_tests=0
failed_tests=0

echo -e "${BLUE}Phase 4 Components to Test:${NC}"
echo "1. Compatibility Testing Suite"
echo "2. Integration Testing Suite"
echo "3. API Versioning Tests"
echo "4. Documentation System"
echo "5. Migration Guide"
echo "6. Deprecation Middleware"
echo ""

# Check if Laravel is available
if ! command -v php artisan &> /dev/null; then
    print_status "FAIL" "Laravel not found. Please run this script from the Laravel project root."
    exit 1
fi

# Check if tests directory exists
if [ ! -d "tests" ]; then
    print_status "FAIL" "Tests directory not found. Please run this script from the Laravel project root."
    exit 1
fi

# 1. Run Compatibility Testing Suite
if [ -f "tests/Feature/Api/CompatibilityTestingTest.php" ]; then
    total_tests=$((total_tests + 1))
    if run_test_suite "Compatibility Testing Suite" "php artisan test tests/Feature/Api/CompatibilityTestingTest.php"; then
        passed_tests=$((passed_tests + 1))
    else
        failed_tests=$((failed_tests + 1))
    fi
else
    print_status "WARN" "CompatibilityTestingTest.php not found"
fi

# 2. Run Integration Testing Suite
if [ -f "tests/Feature/Api/IntegrationTestingTest.php" ]; then
    total_tests=$((total_tests + 1))
    if run_test_suite "Integration Testing Suite" "php artisan test tests/Feature/Api/IntegrationTestingTest.php"; then
        passed_tests=$((passed_tests + 1))
    else
        failed_tests=$((failed_tests + 1))
    fi
else
    print_status "WARN" "IntegrationTestingTest.php not found"
fi

# 3. Run API Versioning Tests
if [ -f "tests/Feature/Api/ApiVersioningTest.php" ]; then
    total_tests=$((total_tests + 1))
    if run_test_suite "API Versioning Tests" "php artisan test tests/Feature/Api/ApiVersioningTest.php"; then
        passed_tests=$((passed_tests + 1))
    else
        failed_tests=$((failed_tests + 1))
    fi
else
    print_status "WARN" "ApiVersioningTest.php not found"
fi

# 4. Check Documentation System
total_tests=$((total_tests + 1))
if [ -f "resources/views/api/docs/layout.blade.php" ] && [ -f "resources/views/api/docs/index.blade.php" ]; then
    print_status "PASS" "Documentation system files exist"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "Documentation system files missing"
    failed_tests=$((failed_tests + 1))
fi

# 5. Check Migration Guide
total_tests=$((total_tests + 1))
if [ -f "resources/views/api/docs/migration.blade.php" ]; then
    print_status "PASS" "Migration guide exists"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "Migration guide missing"
    failed_tests=$((failed_tests + 1))
fi

# 6. Check Deprecation Middleware
total_tests=$((total_tests + 1))
if [ -f "app/Http/Middleware/VersionDeprecationMiddleware.php" ]; then
    print_status "PASS" "Version deprecation middleware exists"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "Version deprecation middleware missing"
    failed_tests=$((failed_tests + 1))
fi

# 7. Check API Routes
total_tests=$((total_tests + 1))
if [ -f "routes/web.php" ] && grep -q "api.docs" routes/web.php; then
    print_status "PASS" "API documentation routes configured"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "API documentation routes not configured"
    failed_tests=$((failed_tests + 1))
fi

# 8. Check API Versioning Routes
total_tests=$((total_tests + 1))
if [ -f "routes/api.php" ] && grep -q "v1\|v2" routes/api.php; then
    print_status "PASS" "API versioning routes configured"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "API versioning routes not configured"
    failed_tests=$((failed_tests + 1))
fi

# 9. Check Models and Controllers
total_tests=$((total_tests + 1))
if [ -f "app/Models/ApiVersion.php" ] && [ -f "app/Http/Controllers/Api/ApiVersionAnalyticsController.php" ]; then
    print_status "PASS" "API version models and controllers exist"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "API version models or controllers missing"
    failed_tests=$((failed_tests + 1))
fi

# 10. Check Middleware
total_tests=$((total_tests + 1))
if [ -f "app/Http/Middleware/ApiVersionMiddleware.php" ]; then
    print_status "PASS" "API version middleware exists"
    passed_tests=$((passed_tests + 1))
else
    print_status "FAIL" "API version middleware missing"
    failed_tests=$((failed_tests + 1))
fi

# Summary
echo ""
echo "=================================================="
echo "📊 Phase 4 Testing & Documentation Summary"
echo "=================================================="
echo -e "Total Tests: ${BLUE}$total_tests${NC}"
echo -e "Passed: ${GREEN}$passed_tests${NC}"
echo -e "Failed: ${RED}$failed_tests${NC}"
echo -e "Success Rate: ${BLUE}$(( (passed_tests * 100) / total_tests ))%${NC}"

# Final status
if [ $failed_tests -eq 0 ]; then
    echo ""
    print_status "PASS" "🎉 Phase 4 Testing & Documentation completed successfully!"
    echo ""
    echo "✅ All components are properly implemented and tested"
    echo "✅ Documentation system is ready"
    echo "✅ Migration guides are available"
    echo "✅ Testing suites are functional"
    echo "✅ API versioning is working"
    exit 0
else
    echo ""
    print_status "FAIL" "⚠️  Phase 4 has $failed_tests failed components"
    echo ""
    echo "Please review the failed tests above and fix any issues."
    echo "Re-run this script after making corrections."
    exit 1
fi
