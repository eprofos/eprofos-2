#!/bin/bash

# Script to check if Twig templates referenced in PHP classes actually exist
# Usage: ./check_twig_templates_existence.sh [--verbose] [--missing-only]

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
SRC_DIR="$PROJECT_ROOT/src"

# Flags
VERBOSE=false
MISSING_ONLY=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --verbose)
            VERBOSE=true
            shift
            ;;
        --missing-only)
            MISSING_ONLY=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [--verbose] [--missing-only]"
            echo "  --verbose      Show detailed information"
            echo "  --missing-only Show only missing templates"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}🔍 Checking Twig template references in PHP classes...${NC}"
echo "Project root: $PROJECT_ROOT"
echo "Source directory: $SRC_DIR"
echo "Templates directory: $TEMPLATES_DIR"
echo ""

# Check if directories exist
if [ ! -d "$SRC_DIR" ]; then
    echo -e "${RED}❌ Source directory not found: $SRC_DIR${NC}"
    exit 1
fi

if [ ! -d "$TEMPLATES_DIR" ]; then
    echo -e "${RED}❌ Templates directory not found: $TEMPLATES_DIR${NC}"
    exit 1
fi

# Counter variables
total_references=0
missing_templates=0
existing_templates=0

# Arrays to store results
declare -a missing_templates_list
declare -a existing_templates_list

# Function to check if template exists
check_template_exists() {
    local template_path="$1"
    local full_path="$TEMPLATES_DIR/$template_path"
    
    if [ -f "$full_path" ]; then
        return 0  # Template exists
    else
        return 1  # Template doesn't exist
    fi
}

# Function to normalize template path
normalize_template_path() {
    local template="$1"
    
    # Remove quotes
    template=$(echo "$template" | sed 's/['\''"]//g')
    
    # Add .html.twig if not present
    if [[ ! "$template" == *.twig ]]; then
        template="$template.html.twig"
    fi
    
    echo "$template"
}

# Function to extract template references from PHP file
extract_template_references() {
    local php_file="$1"
    local file_relative="${php_file#$PROJECT_ROOT/}"
    
    if [ "$VERBOSE" = true ]; then
        echo -e "${CYAN}📄 Analyzing: $file_relative${NC}"
    fi
    
    # Regular expressions to match template references
    local patterns=(
        # render() method calls
        "render\s*\(\s*['\"]([^'\"]+)['\"]"
        
        # TemplatedEmail htmlTemplate() calls
        "htmlTemplate\s*\(\s*['\"]([^'\"]+)['\"]"
        
        # renderForm() method calls
        "renderForm\s*\(\s*['\"]([^'\"]+)['\"]"
        
        # renderView() method calls
        "renderView\s*\(\s*['\"]([^'\"]+)['\"]"
        
        # createForm() with template parameter (less common)
        "createForm\s*\([^,]+,\s*[^,]*,\s*\[[^]]*['\"]template['\"][^]]*=>\s*['\"]([^'\"]+)['\"]"
    )
    
    local found_templates=()
    
    # Search for each pattern
    for pattern in "${patterns[@]}"; do
        # Use perl for better regex support
        local matches=$(perl -ne "
            while (/$pattern/gi) {
                print \"\$1\n\";
            }
        " "$php_file" 2>/dev/null)
        
        if [ ! -z "$matches" ]; then
            while IFS= read -r template; do
                if [ ! -z "$template" ]; then
                    found_templates+=("$template")
                fi
            done <<< "$matches"
        fi
    done
    
    # Process found templates
    local file_missing=0
    local file_existing=0
    
    if [ ${#found_templates[@]} -gt 0 ]; then
        # Remove duplicates
        local unique_templates=($(printf '%s\n' "${found_templates[@]}" | sort -u))
        
        for template in "${unique_templates[@]}"; do
            local normalized_template=$(normalize_template_path "$template")
            total_references=$((total_references + 1))
            
            if check_template_exists "$normalized_template"; then
                existing_templates=$((existing_templates + 1))
                file_existing=$((file_existing + 1))
                existing_templates_list+=("$normalized_template|$file_relative")
                
                if [ "$VERBOSE" = true ] && [ "$MISSING_ONLY" = false ]; then
                    echo -e "  ${GREEN}✓ $normalized_template${NC}"
                fi
            else
                missing_templates=$((missing_templates + 1))
                file_missing=$((file_missing + 1))
                missing_templates_list+=("$normalized_template|$file_relative")
                
                if [ "$MISSING_ONLY" = false ] || [ "$VERBOSE" = true ]; then
                    echo -e "  ${RED}❌ $normalized_template${NC}"
                fi
            fi
        done
        
        if [ "$VERBOSE" = true ]; then
            echo -e "  ${BLUE}Found: $file_existing existing, $file_missing missing${NC}"
        fi
    else
        if [ "$VERBOSE" = true ]; then
            echo -e "  ${YELLOW}No template references found${NC}"
        fi
    fi
    
    if [ "$VERBOSE" = true ]; then
        echo ""
    fi
}

# Function to get class type from file path
get_class_type() {
    local file_path="$1"
    
    if [[ "$file_path" == */Controller/* ]]; then
        echo "Controller"
    elif [[ "$file_path" == */Service/* ]]; then
        echo "Service"
    elif [[ "$file_path" == */Command/* ]]; then
        echo "Command"
    elif [[ "$file_path" == */EventListener/* ]]; then
        echo "EventListener"
    elif [[ "$file_path" == */Form/* ]]; then
        echo "Form"
    elif [[ "$file_path" == */Twig/* ]]; then
        echo "Twig Extension"
    else
        echo "Other"
    fi
}

echo -e "${GREEN}📂 Scanning PHP files for template references...${NC}"
echo ""

# Find all PHP files in src directory
while IFS= read -r -d '' php_file; do
    extract_template_references "$php_file"
done < <(find "$SRC_DIR" -name "*.php" -type f -print0)

echo -e "${GREEN}📊 Summary Report${NC}"
echo "============================================"
echo "Total template references found: $total_references"
echo -e "Existing templates: ${GREEN}$existing_templates${NC}"
echo -e "Missing templates: ${RED}$missing_templates${NC}"

if [ $total_references -gt 0 ]; then
    success_rate=$((existing_templates * 100 / total_references))
    echo "Success rate: $success_rate%"
fi

echo ""

# Show missing templates grouped by location
if [ ${#missing_templates_list[@]} -gt 0 ]; then
    echo -e "${RED}🚨 Missing Templates Details:${NC}"
    echo "============================================"
    
    # Group by template for better readability
    declare -A template_to_files
    for entry in "${missing_templates_list[@]}"; do
        IFS='|' read -r template file <<< "$entry"
        if [ -z "${template_to_files[$template]}" ]; then
            template_to_files[$template]="$file"
        else
            template_to_files[$template]="${template_to_files[$template]}, $file"
        fi
    done
    
    # Sort templates alphabetically
    for template in $(printf '%s\n' "${!template_to_files[@]}" | sort); do
        echo -e "${RED}❌ $template${NC}"
        echo -e "   Referenced in: ${YELLOW}${template_to_files[$template]}${NC}"
        echo ""
    done
fi

# Show existing templates by category if verbose
if [ "$VERBOSE" = true ] && [ "$MISSING_ONLY" = false ] && [ ${#existing_templates_list[@]} -gt 0 ]; then
    echo -e "${GREEN}✅ Existing Templates by Category:${NC}"
    echo "============================================"
    
    declare -A category_templates
    for entry in "${existing_templates_list[@]}"; do
        IFS='|' read -r template file <<< "$entry"
        class_type=$(get_class_type "$file")
        
        if [ -z "${category_templates[$class_type]}" ]; then
            category_templates[$class_type]="$template ($file)"
        else
            category_templates[$class_type]="${category_templates[$class_type]}|$template ($file)"
        fi
    done
    
    for category in $(printf '%s\n' "${!category_templates[@]}" | sort); do
        echo -e "${GREEN}📁 $category:${NC}"
        IFS='|' read -ra templates <<< "${category_templates[$category]}"
        for template_info in "${templates[@]}"; do
            echo -e "   ✓ $template_info"
        done
        echo ""
    done
fi

# Provide recommendations
echo -e "${BLUE}💡 Recommendations:${NC}"
echo "============================================"

if [ $missing_templates -gt 0 ]; then
    echo "1. Create missing template files or fix template path references"
    echo "2. Check for typos in template names"
    echo "3. Verify template directory structure matches expectations"
    echo "4. Consider using IDE auto-completion for template paths"
else
    echo "✅ All template references are valid!"
fi

echo ""
echo -e "${GREEN}✅ Template existence check complete!${NC}"

# Exit with error code if missing templates found
if [ $missing_templates -gt 0 ]; then
    exit 1
else
    exit 0
fi
