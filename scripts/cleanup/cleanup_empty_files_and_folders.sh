#!/bin/bash

# Script to find and remove empty files and empty directories
# Usage: ./cleanup_empty_files_and_folders.sh [--delete]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Check for delete flag
DELETE_MODE=false
if [[ "$1" == "--delete" ]]; then
    DELETE_MODE=true
fi

# Directories to exclude from cleanup
EXCLUDE_DIRS=(
    "vendor"
    "var"
    "node_modules"
    ".git"
    "public/uploads"
    ".vscode"
    ".idea"
    "migrations"
    "translations"
)

# Directories to always keep even if empty (important for Symfony)
KEEP_DIRS=(
    "var"
    "var/cache"
    "var/log"
    "var/sessions"
    "public/uploads"
    "migrations"
    "translations"
    "src/Command"
    "scripts"
)

# File patterns to exclude from empty file cleanup
EXCLUDE_FILES=(
    ".gitkeep"
    ".gitignore"
    ".env*"
    "*.md"
    "README*"
    "LICENSE*"
    "composer.json"
    "composer.lock"
    "symfony.lock"
    "package.json"
    "package-lock.json"
)

echo -e "${GREEN}🧹 Cleaning up empty files and directories...${NC}"
echo "Project root: $PROJECT_ROOT"
if [ "$DELETE_MODE" = true ]; then
    echo -e "${RED}⚠️  DELETE MODE ENABLED - Empty files and directories will be removed!${NC}"
else
    echo -e "${BLUE}ℹ️  DRY RUN MODE - Use --delete flag to actually remove files/directories${NC}"
fi
echo ""

# Build exclude pattern for find command
FIND_EXCLUDE=""
for dir in "${EXCLUDE_DIRS[@]}"; do
    FIND_EXCLUDE="$FIND_EXCLUDE -path '*/$dir' -prune -o"
done

# Function to check if file should be excluded
should_exclude_file() {
    local file="$1"
    local basename=$(basename "$file")
    
    for pattern in "${EXCLUDE_FILES[@]}"; do
        if [[ "$basename" == $pattern ]]; then
            return 0  # Should exclude
        fi
    done
    return 1  # Should not exclude
}

# Function to check if directory should be kept
should_keep_directory() {
    local dir="$1"
    local relative_path="${dir#$PROJECT_ROOT/}"
    
    for keep_pattern in "${KEEP_DIRS[@]}"; do
        if [[ "$relative_path" == "$keep_pattern"* ]] || [[ "$relative_path" == "$keep_pattern" ]]; then
            return 0  # Should keep
        fi
    done
    return 1  # Can delete
}

# Function to get relative path
get_relative_path() {
    local file="$1"
    echo "${file#$PROJECT_ROOT/}"
}

# Counters
empty_files_found=0
empty_dirs_found=0
empty_files_deleted=0
empty_dirs_deleted=0

declare -a empty_files_list
declare -a empty_dirs_list

echo -e "${CYAN}🔍 Step 1: Finding empty files...${NC}"
echo ""

# Find empty files (0 bytes)
while IFS= read -r -d '' empty_file; do
    # Skip if file should be excluded
    if should_exclude_file "$empty_file"; then
        continue
    fi
    
    empty_files_found=$((empty_files_found + 1))
    relative_path=$(get_relative_path "$empty_file")
    empty_files_list+=("$empty_file")
    
    echo -e "${YELLOW}📄 Found empty file: $relative_path${NC}"
    
    if [ "$DELETE_MODE" = true ]; then
        echo -e "  ${RED}🗑️  Deleting: $relative_path${NC}"
        if rm -f "$empty_file" 2>/dev/null; then
            empty_files_deleted=$((empty_files_deleted + 1))
            echo -e "  ${GREEN}✅ Successfully deleted${NC}"
        else
            echo -e "  ${RED}❌ Failed to delete${NC}"
        fi
    else
        echo -e "  ${BLUE}ℹ️  Would delete: $relative_path${NC}"
    fi
    echo ""
done < <(find "$PROJECT_ROOT" $FIND_EXCLUDE -type f -empty -print0 2>/dev/null)

echo -e "${CYAN}🔍 Step 2: Finding empty directories...${NC}"
echo ""

# Find empty directories (multiple passes to handle nested empty dirs)
max_passes=5
current_pass=1

while [ $current_pass -le $max_passes ]; do
    echo -e "${YELLOW}Pass $current_pass of $max_passes...${NC}"
    found_in_this_pass=0
    
    # Find empty directories
    while IFS= read -r -d '' empty_dir; do
        # Skip if directory should be excluded or kept
        skip_dir=false
        for exclude_pattern in "${EXCLUDE_DIRS[@]}"; do
            if [[ "$empty_dir" == *"$exclude_pattern"* ]]; then
                skip_dir=true
                break
            fi
        done
        
        if [ "$skip_dir" = true ]; then
            continue
        fi
        
        # Check if directory should be kept
        if should_keep_directory "$empty_dir"; then
            continue
        fi
        
        # Check if directory is really empty (no files, no subdirectories)
        if [ -d "$empty_dir" ] && [ -z "$(ls -A "$empty_dir" 2>/dev/null)" ]; then
            # Skip if already processed
            if [[ " ${empty_dirs_list[@]} " =~ " ${empty_dir} " ]]; then
                continue
            fi
            
            empty_dirs_found=$((empty_dirs_found + 1))
            found_in_this_pass=$((found_in_this_pass + 1))
            relative_path=$(get_relative_path "$empty_dir")
            empty_dirs_list+=("$empty_dir")
            
            echo -e "${YELLOW}📁 Found empty directory: $relative_path${NC}"
            
            if [ "$DELETE_MODE" = true ]; then
                echo -e "  ${RED}🗑️  Deleting: $relative_path${NC}"
                if rmdir "$empty_dir" 2>/dev/null; then
                    empty_dirs_deleted=$((empty_dirs_deleted + 1))
                    echo -e "  ${GREEN}✅ Successfully deleted${NC}"
                else
                    echo -e "  ${RED}❌ Failed to delete (may not be empty)${NC}"
                fi
            else
                echo -e "  ${BLUE}ℹ️  Would delete: $relative_path${NC}"
            fi
            echo ""
        fi
    done < <(find "$PROJECT_ROOT" $FIND_EXCLUDE -type d -empty -print0 2>/dev/null)
    
    # If no empty directories found in this pass, we're done
    if [ $found_in_this_pass -eq 0 ]; then
        break
    fi
    
    current_pass=$((current_pass + 1))
done

echo -e "${GREEN}📊 Summary:${NC}"
echo "Empty files found: $empty_files_found"
echo "Empty directories found: $empty_dirs_found"

if [ "$DELETE_MODE" = true ]; then
    echo -e "Empty files deleted: ${GREEN}$empty_files_deleted${NC}"
    echo -e "Empty directories deleted: ${GREEN}$empty_dirs_deleted${NC}"
else
    echo ""
    if [ ${#empty_files_list[@]} -gt 0 ]; then
        echo -e "${YELLOW}📄 Empty files that would be deleted:${NC}"
        for file in "${empty_files_list[@]}"; do
            echo -e "  - $(get_relative_path "$file")"
        done
        echo ""
    fi
    
    if [ ${#empty_dirs_list[@]} -gt 0 ]; then
        echo -e "${YELLOW}📁 Empty directories that would be deleted:${NC}"
        for dir in "${empty_dirs_list[@]}"; do
            echo -e "  - $(get_relative_path "$dir")"
        done
        echo ""
    fi
    
    if [ $empty_files_found -gt 0 ] || [ $empty_dirs_found -gt 0 ]; then
        echo -e "${BLUE}ℹ️  Run with --delete flag to actually remove these files/directories${NC}"
    else
        echo -e "${GREEN}🎉 No empty files or directories found!${NC}"
    fi
fi

echo ""
if [ "$DELETE_MODE" = true ]; then
    echo -e "${GREEN}✅ Cleanup complete!${NC}"
else
    echo -e "${GREEN}✅ Analysis complete!${NC}"
fi
