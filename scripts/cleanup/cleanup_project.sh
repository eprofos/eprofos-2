#!/bin/bash

# Combined cleanup script - removes unused services and empty files/directories
# Usage: ./cleanup_project.sh [--delete]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check for delete flag
DELETE_MODE=false
if [[ "$1" == "--delete" ]]; then
    DELETE_MODE=true
fi

echo -e "${MAGENTA}🧽 EPROFOS Project Cleanup Tool${NC}"
echo -e "${MAGENTA}================================${NC}"
echo ""

if [ "$DELETE_MODE" = true ]; then
    echo -e "${RED}⚠️  DELETE MODE ENABLED - Files will be permanently removed!${NC}"
    echo -e "${YELLOW}This will:${NC}"
    echo "  1. Remove unused service classes"
    echo "  2. Remove unused Twig templates"
    echo "  3. Remove empty files (except important ones)"
    echo "  4. Remove empty directories (except important ones)"
    echo ""
    read -p "Are you sure you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}Operation cancelled.${NC}"
        exit 0
    fi
else
    echo -e "${BLUE}🔍 DRY RUN MODE - No files will be deleted${NC}"
    echo "This will show you what would be cleaned up."
    echo "Use --delete flag to actually perform the cleanup."
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}🔧 Step 1: Checking for unused services...${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Run unused services check
if [ "$DELETE_MODE" = true ]; then
    "$SCRIPT_DIR/cleanup_unused_services.sh" --delete
else
    "$SCRIPT_DIR/cleanup_unused_services.sh"
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}🎨 Step 2: Checking for unused Twig templates...${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Run unused templates check
if [ "$DELETE_MODE" = true ]; then
    "$SCRIPT_DIR/cleanup_unused_templates.sh" --delete
else
    "$SCRIPT_DIR/cleanup_unused_templates.sh"
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}🧹 Step 3: Cleaning up empty files and directories...${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Run empty files cleanup
if [ "$DELETE_MODE" = true ]; then
    "$SCRIPT_DIR/cleanup_empty_files_and_folders.sh" --delete
else
    "$SCRIPT_DIR/cleanup_empty_files_and_folders.sh"
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}🎉 Project cleanup complete!${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [ "$DELETE_MODE" = true ]; then
    echo ""
    echo -e "${GREEN}✅ Cleanup operations completed successfully!${NC}"
    echo ""
    echo -e "${YELLOW}📋 What was done:${NC}"
    echo "  • Removed unused service classes"
    echo "  • Removed unused Twig templates"
    echo "  • Removed empty files (preserving important files)"
    echo "  • Removed empty directories (preserving important directories)"
    echo ""
    echo -e "${YELLOW}💡 Recommended next steps:${NC}"
    echo "  1. Run tests to verify the application still works correctly"
    echo "  2. Commit the changes if everything looks good"
else
    echo ""
    echo -e "${BLUE}ℹ️  This was a dry run - no files were actually removed.${NC}"
    echo -e "${BLUE}Run with --delete flag to perform actual cleanup.${NC}"
fi

echo ""
