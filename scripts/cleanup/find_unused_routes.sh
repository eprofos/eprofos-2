#!/bin/bash

# Script to find unused Symfony routes
# Usage: ./find_unused_routes.sh (run from scripts/cleanup/ directory)

echo "=== Finding Unused Symfony Routes ==="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the project root directory (two levels up from scripts/cleanup/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "Project root: $PROJECT_ROOT"
echo ""

# Get all routes from Symfony (excluding those starting with _)
echo "Getting routes from Symfony..."
routes=$(cd "$PROJECT_ROOT" && docker compose exec -T php php bin/console debug:router --format=txt | \
    grep -E "^\s*[a-zA-Z][^_].*\s+" | \
    awk '{print $1}' | \
    grep -v "^Name$" | \
    sort | uniq)

if [ -z "$routes" ]; then
    echo "No routes found or error getting routes"
    exit 1
fi

total_routes=0
unused_routes=0
unused_routes_list=()

echo "Analyzing route usage in codebase..."
echo ""

# Check each route for usage in the codebase
for route in $routes; do
    if [ -z "$route" ]; then
        continue
    fi
    
    total_routes=$((total_routes + 1))
    
    # Search for the route name in PHP files (controllers, templates, etc.)
    # Look in src/, templates/, and config/ directories
    # Use -r for recursive, -l for files with matches, -w for whole word
    occurrences=$(find "$PROJECT_ROOT" -type f \( -name "*.php" -o -name "*.twig" -o -name "*.yaml" -o -name "*.yml" \) \
        -not -path "$PROJECT_ROOT/vendor/*" \
        -not -path "$PROJECT_ROOT/var/*" \
        -not -path "$PROJECT_ROOT/migrations/*" \
        -exec grep -l "\b$route\b" {} \; 2>/dev/null | wc -l)
    
    # Count actual string occurrences (not just files)
    total_occurrences=$(find "$PROJECT_ROOT" -type f \( -name "*.php" -o -name "*.twig" -o -name "*.yaml" -o -name "*.yml" \) \
        -not -path "$PROJECT_ROOT/vendor/*" \
        -not -path "$PROJECT_ROOT/var/*" \
        -not -path "$PROJECT_ROOT/migrations/*" \
        -exec grep -o "\b$route\b" {} \; 2>/dev/null | wc -l)
    
    # If route appears only once or zero times, it's likely unused
    # (once = only in route declaration, zero = completely unused)
    if [ "$total_occurrences" -le 1 ]; then
        unused_routes=$((unused_routes + 1))
        unused_routes_list+=("$route")
        echo -e "${RED}UNUSED:${NC} $route (found $total_occurrences time(s))"
    else
        echo -e "${GREEN}USED:${NC} $route (found $total_occurrences time(s))"
    fi
done

echo ""
echo "=== SUMMARY ==="
echo -e "Total routes analyzed: ${YELLOW}$total_routes${NC}"
echo -e "Unused routes found: ${RED}$unused_routes${NC}"
echo -e "Used routes: ${GREEN}$((total_routes - unused_routes))${NC}"

if [ ${#unused_routes_list[@]} -gt 0 ]; then
    echo ""
    echo "=== UNUSED ROUTES LIST ==="
    for unused_route in "${unused_routes_list[@]}"; do
        echo -e "${RED}- $unused_route${NC}"
    done
    
    echo ""
    echo "=== DETAILED ANALYSIS ==="
    echo "To get more details about where these routes are defined:"
    echo "cd $PROJECT_ROOT && docker compose exec -T php php bin/console debug:router | grep -E '^($(IFS='|'; echo "${unused_routes_list[*]}"))\b'"
fi

echo ""
echo "=== NOTES ==="
echo "- Routes with 0-1 occurrences are considered unused"
echo "- This script searches in .php, .twig, .yaml, and .yml files"
echo "- Excludes vendor/, var/, and migrations/ directories"
echo "- Manual verification recommended before removing routes"
echo "- Some routes might be used in JavaScript or external references"
echo "- Script must be run from the scripts/cleanup/ directory"
