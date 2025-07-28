#!/bin/bash

# Script to find and delete unused Twig templates in templates directory
# Usage: ./cleanup_unused_templates.sh [--delete]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMPLATES_DIR="$PROJECT_ROOT/templates"

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
)

# Templates to exclude from deletion (important system templates)
EXCLUDE_TEMPLATES=(
    "base.html.twig"
    "public/base.html.twig"
    "admin/base.html.twig"
    "student/base.html.twig"
    "teacher/base.html.twig"
    "mentor/base.html.twig"
    "emails/base.html.twig"
    "form/themes/*.html.twig"
    "bundles/*"
    "components/*"
    "_*"  # Partial templates
)

echo -e "${GREEN}🔍 Checking for unused Twig templates...${NC}"
echo "Project root: $PROJECT_ROOT"
echo "Templates directory: $TEMPLATES_DIR"
if [ "$DELETE_MODE" = true ]; then
    echo -e "${RED}⚠️  DELETE MODE ENABLED - Unused templates will be removed!${NC}"
else
    echo -e "${BLUE}ℹ️  DRY RUN MODE - Use --delete flag to actually remove files${NC}"
fi
echo ""

# Check if Templates directory exists
if [ ! -d "$TEMPLATES_DIR" ]; then
    echo -e "${RED}❌ Templates directory not found: $TEMPLATES_DIR${NC}"
    exit 1
fi

# Build exclude pattern for find command
FIND_EXCLUDE=""
for dir in "${EXCLUDE_DIRS[@]}"; do
    FIND_EXCLUDE="$FIND_EXCLUDE -path '*/$dir' -prune -o"
done

# Function to check if template should be excluded
should_exclude_template() {
    local template_file="$1"
    local relative_path="${template_file#$TEMPLATES_DIR/}"
    
    for pattern in "${EXCLUDE_TEMPLATES[@]}"; do
        case "$relative_path" in
            $pattern)
                return 0  # Should exclude
                ;;
        esac
    done
    return 1  # Should not exclude
}

# Function to get template path relative to templates directory
get_template_path() {
    local template_file="$1"
    echo "${template_file#$TEMPLATES_DIR/}"
}

# Function to check if a template is used
check_template_usage() {
    local template_file="$1"
    local template_path="$2"
    
    echo -e "${YELLOW}Checking: $template_path${NC}"
    
    # Extract template name without .html.twig extension
    local template_name_full=$(basename "$template_path")
    local template_name="${template_name_full%.html.twig}"
    local template_dir=$(dirname "$template_path")
    
    # Remove leading './' if present
    template_dir="${template_dir#./}"
    
    # Build search patterns for template usage
    local search_patterns=(
        # Full template path patterns
        "'$template_path'"
        "\"$template_path\""
        "'$template_dir/$template_name_full'"
        "\"$template_dir/$template_name_full\""
        
        # Render calls with template path
        "render.*['\"]$template_path['\"]"
        "render.*['\"]$template_dir/$template_name_full['\"]"
        
        # Include/extends patterns
        "include.*['\"]$template_path['\"]"
        "extends.*['\"]$template_path['\"]"
        "include.*['\"]$template_dir/$template_name_full['\"]"
        "extends.*['\"]$template_dir/$template_name_full['\"]"
        
        # Template name without extension in quotes
        "'$template_dir/$template_name'"
        "\"$template_dir/$template_name\""
        
        # Just the template name for partial matches
        "$template_name_full"
        
        # Twig template inheritance patterns
        "{% extends ['\"]$template_path['\"] %}"
        "{% include ['\"]$template_path['\"] %}"
        "{% embed ['\"]$template_path['\"] %}"
    )
    
    local found_usage=false
    local usage_files=()
    
    # Search in all project files except excluded directories
    for pattern in "${search_patterns[@]}"; do
        # Search in PHP files (controllers, services, etc.)
        local php_results=$(find "$PROJECT_ROOT/src" \
            -type f -name "*.php" -print0 2>/dev/null | \
            xargs -0 grep -l "$pattern" 2>/dev/null | \
            head -3)
        
        # Search in Twig files (template inheritance, includes)
        local twig_results=$(find "$TEMPLATES_DIR" \
            -type f -name "*.twig" -print0 2>/dev/null | \
            xargs -0 grep -l "$pattern" 2>/dev/null | \
            grep -v "$template_file" 2>/dev/null | \
            head -3)
        
        # Search in config files (YAML, XML)
        local config_results=$(find "$PROJECT_ROOT/config" \
            -type f \( -name "*.yaml" -o -name "*.yml" -o -name "*.xml" \) -print0 2>/dev/null | \
            xargs -0 grep -l "$pattern" 2>/dev/null | \
            head -3)
        
        # Combine all results
        local all_results=""
        if [ ! -z "$php_results" ]; then
            all_results="$all_results$php_results"
        fi
        if [ ! -z "$twig_results" ]; then
            all_results="$all_results${all_results:+$'\n'}$twig_results"
        fi
        if [ ! -z "$config_results" ]; then
            all_results="$all_results${all_results:+$'\n'}$config_results"
        fi
        
        if [ ! -z "$all_results" ]; then
            echo -e "  ${GREEN}✓ Found usage with pattern '$pattern'${NC}"
            echo "$all_results" | head -3 | while read -r file; do
                if [ ! -z "$file" ]; then
                    echo -e "    📁 ${file#$PROJECT_ROOT/}"
                fi
            done
            found_usage=true
            break
        fi
    done
    
    # Special check for base templates and components that might be used indirectly
    if [ "$found_usage" = false ]; then
        # Check if it's a component or partial template
        if [[ "$template_path" == *"components/"* ]] || [[ "$template_name_full" == "_"* ]]; then
            # Look for the template name being referenced anywhere
            local component_usage=$(find "$TEMPLATES_DIR" \
                -type f -name "*.twig" -print0 2>/dev/null | \
                xargs -0 grep -l "$template_name" 2>/dev/null | \
                grep -v "$template_file" 2>/dev/null | \
                head -1)
            
            if [ ! -z "$component_usage" ]; then
                echo -e "  ${GREEN}✓ Found component/partial usage${NC}"
                echo -e "    📁 ${component_usage#$PROJECT_ROOT/}"
                found_usage=true
            fi
        fi
        
        # Check for form themes
        if [[ "$template_path" == *"form/"* ]]; then
            local form_usage=$(find "$PROJECT_ROOT" \
                -type f \( -name "*.php" -o -name "*.yaml" -o -name "*.yml" \) -print0 2>/dev/null | \
                xargs -0 grep -l "form.*theme" 2>/dev/null | \
                head -1)
            
            if [ ! -z "$form_usage" ]; then
                echo -e "  ${GREEN}✓ Found form theme usage${NC}"
                echo -e "    📁 ${form_usage#$PROJECT_ROOT/}"
                found_usage=true
            fi
        fi
    fi
    
    if [ "$found_usage" = false ]; then
        echo -e "  ${RED}❌ No usage found${NC}"
        
        if [ "$DELETE_MODE" = true ]; then
            echo -e "  ${RED}🗑️  Deleting: $template_file${NC}"
            # Remove the file
            rm -f "$template_file"
            if [ $? -eq 0 ]; then
                echo -e "  ${GREEN}✅ Successfully deleted${NC}"
            else
                echo -e "  ${RED}❌ Failed to delete${NC}"
            fi
        else
            echo -e "  ${BLUE}ℹ️  Would delete: $template_file${NC}"
        fi
        
        return 1  # Indicate unused template
    else
        echo -e "  ${GREEN}✅ Template is used${NC}"
        return 0  # Indicate used template
    fi
    
    echo ""
}

# Counter for statistics
total_templates=0
unused_templates=0
deleted_templates=0

# Array to store unused template info for summary
declare -a unused_templates_list

# Find all Twig template files
echo -e "${GREEN}📂 Scanning template files...${NC}"
echo ""

while IFS= read -r -d '' template_file; do
    # Skip if template should be excluded
    if should_exclude_template "$template_file"; then
        continue
    fi
    
    total_templates=$((total_templates + 1))
    
    # Get template path relative to templates directory
    template_path=$(get_template_path "$template_file")
    
    # Check if template is used
    if ! check_template_usage "$template_file" "$template_path"; then
        unused_templates=$((unused_templates + 1))
        unused_templates_list+=("$template_path")
        if [ "$DELETE_MODE" = true ]; then
            deleted_templates=$((deleted_templates + 1))
        fi
    fi
    
done < <(find "$TEMPLATES_DIR" -name "*.twig" -type f -print0)

# Summary
echo -e "${GREEN}📊 Summary:${NC}"
echo "Total templates found: $total_templates"

if [ ${#unused_templates_list[@]} -gt 0 ]; then
    if [ "$DELETE_MODE" = true ]; then
        echo -e "Deleted templates: ${RED}$deleted_templates${NC}"
        echo ""
        echo -e "${RED}🗑️  Deleted templates:${NC}"
        for template in "${unused_templates_list[@]}"; do
            echo -e "  - ${RED}$template${NC}"
        done
    else
        echo -e "Unused templates: ${RED}$unused_templates${NC}"
        echo ""
        echo -e "${YELLOW}📋 Templates that would be deleted:${NC}"
        for template in "${unused_templates_list[@]}"; do
            echo -e "  - ${YELLOW}$template${NC}"
        done
        echo ""
        echo -e "${BLUE}ℹ️  Run with --delete flag to actually remove these files${NC}"
    fi
else
    echo -e "Unused templates: ${GREEN}0${NC}"
    echo "All templates are being used! 🎉"
fi

echo ""
if [ "$DELETE_MODE" = true ]; then
    echo -e "${GREEN}✅ Template cleanup complete!${NC}"
else
    echo -e "${GREEN}✅ Template analysis complete!${NC}"
fi
