#!/bin/bash

# Script to find and delete unused services in src/Service directory
# Usage: ./cleanup_unused_services.sh [--delete]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SERVICE_DIR="$PROJECT_ROOT/src/Service"

# Check for delete flag
DELETE_MODE=false
if [[ "$1" == "--delete" ]]; then
    DELETE_MODE=true
fi

# Directories to exclude from search
EXCLUDE_DIRS=(
    "vendor"
    "var"
    "node_modules"
    ".git"
    "public/uploads"
    "migrations"
    "assets"
)

# File patterns to exclude
EXCLUDE_FILES=(
    "*.log"
    "*.cache"
    "*.tmp"
    "composer.lock"
    "symfony.lock"
    "*.min.js"
    "*.min.css"
)

echo -e "${GREEN}🔍 Checking for unused services in src/Service...${NC}"
echo "Project root: $PROJECT_ROOT"
echo "Service directory: $SERVICE_DIR"
if [ "$DELETE_MODE" = true ]; then
    echo -e "${RED}⚠️  DELETE MODE ENABLED - Unused services will be removed!${NC}"
else
    echo -e "${BLUE}ℹ️  DRY RUN MODE - Use --delete flag to actually remove files${NC}"
fi
echo ""

# Check if Service directory exists
if [ ! -d "$SERVICE_DIR" ]; then
    echo -e "${RED}❌ Service directory not found: $SERVICE_DIR${NC}"
    exit 1
fi

# Build exclude pattern for find command
FIND_EXCLUDE=""
for dir in "${EXCLUDE_DIRS[@]}"; do
    FIND_EXCLUDE="$FIND_EXCLUDE -path '*/$dir' -prune -o"
done

# Build exclude pattern for grep command
GREP_EXCLUDE=""
for pattern in "${EXCLUDE_FILES[@]}"; do
    GREP_EXCLUDE="$GREP_EXCLUDE --exclude='$pattern'"
done

# Function to check if a service is used
check_service_usage() {
    local service_file="$1"
    local service_name="$2"
    local class_name="$3"
    
    echo -e "${YELLOW}Checking: $service_name${NC}"
    
    # Search patterns for service usage
    local search_patterns=(
        "$class_name"                    # Direct class name usage
        "use.*$class_name"              # Use statements
        "$service_name"                 # Service name in DI
        "autowire.*$class_name"         # Autowiring
        "arguments.*$class_name"        # Service arguments
        "calls.*$class_name"            # Service calls
    )
    
    local found_usage=false
    
    # Search in all project files except excluded directories
    for pattern in "${search_patterns[@]}"; do
        # Use find to get files, excluding certain directories and file types
        local search_results=$(find "$PROJECT_ROOT" \
            $FIND_EXCLUDE \
            -type f \( -name "*.php" -o -name "*.yaml" -o -name "*.yml" -o -name "*.xml" -o -name "*.twig" \) -print0 | \
            xargs -0 grep -l "$pattern" 2>/dev/null | \
            grep -v "$service_file" 2>/dev/null)
        
        if [ ! -z "$search_results" ]; then
            echo -e "  ${GREEN}✓ Found usage with pattern '$pattern'${NC}"
            echo "$search_results" | head -3 | while read -r file; do
                echo -e "    📁 $file"
            done
            found_usage=true
            break
        fi
    done
    
    if [ "$found_usage" = false ]; then
        echo -e "  ${RED}❌ No usage found${NC}"
        
        if [ "$DELETE_MODE" = true ]; then
            echo -e "  ${RED}🗑️  Deleting: $service_file${NC}"
            # Remove the file
            rm -f "$service_file"
            if [ $? -eq 0 ]; then
                echo -e "  ${GREEN}✅ Successfully deleted${NC}"
            else
                echo -e "  ${RED}❌ Failed to delete${NC}"
            fi
        else
            echo -e "  ${BLUE}ℹ️  Would delete: $service_file${NC}"
        fi
        
        return 1  # Indicate unused service
    else
        echo -e "  ${GREEN}✅ Service is used${NC}"
        return 0  # Indicate used service
    fi
    
    echo ""
}

# Counter for statistics
total_services=0
unused_services=0
deleted_services=0

# Array to store unused service info for summary
declare -a unused_services_list

# Find all PHP service files in src/Service
echo -e "${GREEN}📂 Scanning service files...${NC}"
echo ""

while IFS= read -r -d '' service_file; do
    total_services=$((total_services + 1))
    
    # Extract class name from file
    class_name=$(basename "$service_file" .php)
    
    # Extract service name (convert from PascalCase to snake_case for service names)
    service_name=$(echo "$class_name" | sed 's/\([A-Z]\)/_\1/g' | sed 's/^_//' | tr '[:upper:]' '[:lower:]')
    
    # Check if service is used
    if ! check_service_usage "$service_file" "$service_name" "$class_name"; then
        unused_services=$((unused_services + 1))
        unused_services_list+=("$class_name")
        if [ "$DELETE_MODE" = true ]; then
            deleted_services=$((deleted_services + 1))
        fi
    fi
    
done < <(find "$SERVICE_DIR" -name "*.php" -type f -print0)

# Summary
echo -e "${GREEN}📊 Summary:${NC}"
echo "Total services found: $total_services"

if [ ${#unused_services_list[@]} -gt 0 ]; then
    if [ "$DELETE_MODE" = true ]; then
        echo -e "Deleted services: ${RED}$deleted_services${NC}"
        echo ""
        echo -e "${RED}🗑️  Deleted services:${NC}"
        for service in "${unused_services_list[@]}"; do
            echo -e "  - ${RED}$service${NC}"
        done
    else
        echo -e "Unused services: ${RED}$unused_services${NC}"
        echo ""
        echo -e "${YELLOW}📋 Services that would be deleted:${NC}"
        for service in "${unused_services_list[@]}"; do
            echo -e "  - ${YELLOW}$service${NC}"
        done
        echo ""
        echo -e "${BLUE}ℹ️  Run with --delete flag to actually remove these files${NC}"
    fi
else
    echo -e "Unused services: ${GREEN}0${NC}"
    echo "All services are being used! 🎉"
fi

echo ""
if [ "$DELETE_MODE" = true ]; then
    echo -e "${GREEN}✅ Cleanup complete!${NC}"
else
    echo -e "${GREEN}✅ Analysis complete!${NC}"
fi
