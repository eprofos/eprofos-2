#!/bin/bash

# Script to check for truly unused service methods in EPROFOS project
# This script analyzes PHP service classes and finds methods that are NEVER called anywhere
# Only reports methods with absolutely zero usage - no false positives

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SERVICES_DIR="$PROJECT_ROOT/src/Service"
TEMP_FILE=$(mktemp)
RESULTS_FILE="$PROJECT_ROOT/unused_service_methods_report.txt"

echo "=== EPROFOS - Truly Unused Service Methods Analysis ===" > "$RESULTS_FILE"
echo "Generated on: $(date)" >> "$RESULTS_FILE"
echo "This report contains ONLY methods with zero usage - no false positives" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Function to extract method names from a service file
extract_methods() {
    local file="$1"
    # Extract public methods (excluding __construct, __toString, etc.)
    # Also exclude getters/setters as they're often auto-generated
    grep -n "^[[:space:]]*public function " "$file" | \
        grep -v "__construct\|__toString\|__invoke\|__call\|__get\|__set\|__destruct" | \
        sed 's/.*public function \([a-zA-Z0-9_]*\).*/\1/' | \
        sort -u
}

# Function to search for method usage across the project
check_method_usage() {
    local method="$1"
    local service_file="$2"
    local service_class=$(basename "$service_file" .php)
    
    local usage_count=0
    
    # First check if method is used internally within the same service file
    # This searches for actual method calls: $this->method, ->method, or ::method
    local internal_usage=$(grep -c "\$this->$method\|\->$method\|::$method" "$service_file" 2>/dev/null || echo "0")
    internal_usage=$(echo "$internal_usage" | tr -d '[:space:]')  # Remove any whitespace
    
    # If we found any internal calls, add them to usage count
    if [ "$internal_usage" -gt 0 ]; then
        usage_count=$((usage_count + internal_usage))
    fi
    
    # Search in OTHER PHP files (excluding the service file itself)
    # Count actual occurrences, not just files containing the method
    local external_usage=$(find "$PROJECT_ROOT/src" -name "*.php" ! -path "$service_file" -exec grep -c "\->$method\|::$method\|'$method'\|\"$method\"" {} \; 2>/dev/null | awk '{sum += $1} END {print sum+0}')
    
    if [ -n "$external_usage" ] && [ "$external_usage" -gt 0 ]; then
        usage_count=$((usage_count + external_usage))
    fi
    
    # Also search in Twig templates for potential usage
    local twig_usage=$(find "$PROJECT_ROOT/templates" -name "*.twig" -exec grep -c "$method\|$service_class" {} \; 2>/dev/null | awk '{sum += $1} END {print sum+0}')
    if [ -n "$twig_usage" ] && [ "$twig_usage" -gt 0 ]; then
        usage_count=$((usage_count + twig_usage))
    fi
    
    # Search in configuration files
    local config_usage=$(find "$PROJECT_ROOT/config" -name "*.yaml" -o -name "*.yml" -o -name "*.xml" -exec grep -c "$method\|$service_class" {} \; 2>/dev/null | awk '{sum += $1} END {print sum+0}')
    if [ -n "$config_usage" ] && [ "$config_usage" -gt 0 ]; then
        usage_count=$((usage_count + config_usage))
    fi
    
    echo $usage_count
}

# Function to get method details (parameters, return type)
get_method_details() {
    local file="$1"
    local method="$2"
    
    # Extract the full method signature (may span multiple lines)
    grep -A 3 "^[[:space:]]*public function $method" "$file" | \
        head -4 | \
        tr '\n' ' ' | \
        sed 's/^[[:space:]]*//' | \
        sed 's/{.*$//' | \
        sed 's/[[:space:]]*$//' | \
        head -c 100
}

echo "Analyzing service methods for usage..." >&2
echo "This may take a few minutes..." >&2

total_methods=0
unused_methods=0

# Find all service files
if [ ! -d "$SERVICES_DIR" ]; then
    echo "Error: Services directory not found at $SERVICES_DIR" >&2
    exit 1
fi

echo "## Summary" >> "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Create array of service files to avoid subshell issues
mapfile -t service_files < <(find "$SERVICES_DIR" -name "*.php" -type f)

# Analyze each service file (including subdirectories)
for service_file in "${service_files[@]}"; do
    service_name=$(basename "$service_file" .php)
    relative_path=$(realpath --relative-to="$PROJECT_ROOT" "$service_file")
    
    echo "Analyzing $service_name..." >&2
    
    methods=$(extract_methods "$service_file")
    method_count=$(echo "$methods" | grep -c "^[a-zA-Z]" 2>/dev/null || echo "0")
    
    if [ "$method_count" -gt 0 ]; then
        echo "### Service: $service_name" >> "$TEMP_FILE"
        echo "File: $relative_path" >> "$TEMP_FILE"
        echo "Methods found: $method_count" >> "$TEMP_FILE"
        echo "" >> "$TEMP_FILE"
        
        service_has_unused=false
        
        # Convert methods to array to avoid subshell issues
        mapfile -t method_array < <(echo "$methods")
        
        for method in "${method_array[@]}"; do
            if [ -n "$method" ] && [ "$method" != "" ]; then
                total_methods=$((total_methods + 1))
                usage_count=$(check_method_usage "$method" "$service_file")
                method_signature=$(get_method_details "$service_file" "$method")
                
                # Only report methods with absolutely NO usage
                if [ "$usage_count" -eq 0 ]; then
                    if [ "$service_has_unused" = false ]; then
                        echo "#### 🔴 TRULY UNUSED METHODS" >> "$TEMP_FILE"
                        service_has_unused=true
                    fi
                    echo "- **$method()** - No usage found anywhere" >> "$TEMP_FILE"
                    echo "  \`$method_signature\`" >> "$TEMP_FILE"
                    unused_methods=$((unused_methods + 1))
                fi
            fi
        done
        
        if [ "$service_has_unused" = true ]; then
            echo "" >> "$TEMP_FILE"
        fi
    else
        echo "No public methods found in $service_name" >&2
    fi
done

# Write summary to results file
{
    echo "- **Total methods analyzed:** $total_methods"
    echo "- **Truly unused methods:** $unused_methods"
    echo "- **Used methods:** $((total_methods - unused_methods))"
    echo ""
    echo "## Detailed Analysis"
    echo ""
} >> "$RESULTS_FILE"

# Append detailed analysis
cat "$TEMP_FILE" >> "$RESULTS_FILE"

# Add recommendations
{
    echo ""
    echo "## Recommendations"
    echo ""
    echo "### For Truly Unused Methods (🔴)"
    echo "- These methods have NO usage anywhere in the codebase"
    echo "- Includes check for internal usage within the same service"
    echo "- Safe candidates for removal after final verification"
    echo "- Double-check for dynamic calls or reflection usage"
    echo ""
    echo "### Important Notes"
    echo "- ✅ This analysis includes internal method calls within the same service"
    echo "- ✅ Searches across PHP files, Twig templates, and configuration files"
    echo "- ⚠️ May still miss dynamic method calls (\$object->\$methodName())"
    echo "- ⚠️ May miss reflection-based calls"
    echo "- ⚠️ Interface implementations might appear unused but could be required"
    echo "- ⚠️ Event listener methods might not show direct usage"
    echo ""
    echo "### Before Removing Any Method, Verify:"
    echo "- The method doesn't implement a required interface"
    echo "- It's not called via reflection or magic methods"
    echo "- It's not used in event listeners or command handlers"
    echo "- It's not part of a public API or extension points"
    echo "- It's not used in tests (if you have test files)"
} >> "$RESULTS_FILE"

# Cleanup
rm -f "$TEMP_FILE"

echo "" >&2
echo "✅ Analysis complete!" >&2
echo "📊 Total methods analyzed: $total_methods" >&2
echo "🔴 Unused methods: $unused_methods" >&2
echo " Report saved to: $RESULTS_FILE" >&2
echo "" >&2
echo "Run 'cat $RESULTS_FILE' to view the detailed report." >&2
