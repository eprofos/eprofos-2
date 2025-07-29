<?php

declare(strict_types=1);

/**
 * Script to fix route name prefixes in controllers.
 * 
 * This script:
 * 1. Finds controllers with class-level route name prefixes
 * 2. Removes the prefix from the class-level route attribute
 * 3. Automatically prepends the prefix to each method's route name
 * 
 * Example transformation:
 * - Class: #[Route('/admin/services', name: 'admin_service_')]
 * - Method: #[Route('/', name: 'index')]
 * 
 * Becomes:
 * - Class: #[Route('/admin/services')]
 * - Method: #[Route('/', name: 'admin_service_index')]
 */

function findControllersWithRoutePrefixes(string $srcDir): array
{
    $controllers = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir . '/Controller'),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);
            
            // Look for class-level Route attributes with name prefixes ending in '_'
            // Use a simpler pattern that's more reliable
            if (preg_match('/name:\s*[\'"]([a-zA-Z0-9_]+_)[\'"]/', $content, $matches)) {
                // Make sure this is in a Route attribute by checking context
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (strpos($line, '#[Route') !== false && strpos($line, 'name:') !== false && strpos($line, $matches[1]) !== false) {
                        $controllers[] = [
                            'file' => $filePath,
                            'content' => $content,
                            'prefix' => $matches[1]
                        ];
                        break;
                    }
                }
            }
        }
    }

    return $controllers;
}

function processController(array $controllerInfo, bool $dryRun = false): bool
{
    $filePath = $controllerInfo['file'];
    $content = $controllerInfo['content'];
    $prefix = $controllerInfo['prefix'];
    
    echo "Processing: " . basename($filePath) . " (prefix: {$prefix})\n";
    
    $modified = false;
    $changes = [];
    
    // Parse the file line by line to find route attributes
    $lines = explode("\n", $content);
    $routeChanges = [];
    $hasClassLevelPrefix = false;
    
    foreach ($lines as $lineNum => $line) {
        // Check for class-level Route attribute with name prefix
        if (strpos($line, '#[Route') !== false && strpos($line, 'name:') !== false && strpos($line, $prefix) !== false) {
            $changes[] = "Remove class-level name prefix";
            $modified = true;
            $hasClassLevelPrefix = true;
        }
        
        // Check for method-level Route attributes with names
        if (preg_match('/^\s*#\[Route\([^)]*name:\s*[\'"]([^\'"]+)[\'"]/', $line, $matches)) {
            $currentName = $matches[1];
            
            // Skip if the name already starts with the prefix
            if (strpos($currentName, $prefix) !== 0) {
                $newName = $prefix . $currentName;
                $changes[] = "Update route name: {$currentName} -> {$newName}";
                $routeChanges[$lineNum] = ['old' => $currentName, 'new' => $newName];
                $modified = true;
            }
        }
    }
    
    // Special case: if class has prefix but all method names already have prefix,
    // we still need to remove the class-level prefix
    if ($hasClassLevelPrefix && empty($routeChanges)) {
        $changes[] = "All method routes already correctly prefixed";
    }
    
    if ($dryRun) {
        if (!empty($changes)) {
            foreach ($changes as $change) {
                echo "  - {$change}\n";
            }
        } else {
            echo "  - No changes needed\n";
        }
        echo "\n";
        return $modified;
    }
    
    if (!$modified) {
        echo "  - No changes needed\n\n";
        return false;
    }
    
    // Apply changes if not dry run
    $newLines = [];
    
    foreach ($lines as $lineNum => $line) {
        // Remove class-level name prefix
        if (strpos($line, '#[Route') !== false && strpos($line, 'name:') !== false && strpos($line, $prefix) !== false) {
            // Use regex to remove the name parameter
            $newLine = preg_replace('/,\s*name:\s*[\'"][^\'"]+'.$prefix.'[\'"]/', '', $line);
            echo "  - Removing class-level name prefix\n";
            $newLines[] = $newLine;
        } 
        // Update method route names
        elseif (isset($routeChanges[$lineNum])) {
            $change = $routeChanges[$lineNum];
            $newLine = str_replace(
                "name: '{$change['old']}'",
                "name: '{$change['new']}'",
                $line
            );
            $newLine = str_replace(
                'name: "' . $change['old'] . '"',
                'name: "' . $change['new'] . '"',
                $newLine
            );
            echo "  - Updating route name: {$change['old']} -> {$change['new']}\n";
            $newLines[] = $newLine;
        } else {
            $newLines[] = $line;
        }
    }
    
    $newContent = implode("\n", $newLines);
    
    // Write the modified content back to the file
    file_put_contents($filePath, $newContent);
    echo "  ✓ File updated successfully\n\n";
    return true;
}

function main(): void
{
    $srcDir = __DIR__ . '/../../src';
    
    if (!is_dir($srcDir)) {
        echo "Error: Source directory not found: {$srcDir}\n";
        exit(1);
    }
    
    echo "Looking for controllers with route name prefixes...\n\n";
    
    $controllers = findControllersWithRoutePrefixes($srcDir);
    
    if (empty($controllers)) {
        echo "No controllers found with route name prefixes.\n";
        return;
    }
    
    echo "Found " . count($controllers) . " controller(s) with route name prefixes:\n\n";
    
    // First, run in dry-run mode to show what would be changed
    echo "=== DRY RUN - Preview of changes ===\n\n";
    foreach ($controllers as $controller) {
        processController($controller, true);
    }
    
    // Ask for confirmation
    echo "=== CONFIRMATION ===\n";
    echo "Do you want to apply these changes? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'y' && trim($line) !== 'Y') {
        echo "Aborted.\n";
        return;
    }
    
    echo "\n=== APPLYING CHANGES ===\n\n";
    
    $totalProcessed = 0;
    $totalModified = 0;
    
    foreach ($controllers as $controller) {
        $totalProcessed++;
        if (processController($controller, false)) {
            $totalModified++;
        }
    }
    
    echo "Summary:\n";
    echo "- Controllers processed: {$totalProcessed}\n";
    echo "- Controllers modified: {$totalModified}\n";
    echo "- Controllers unchanged: " . ($totalProcessed - $totalModified) . "\n\n";
    
    if ($totalModified > 0) {
        echo "✓ Route name prefixes have been fixed!\n";
        echo "Remember to check your templates and routes to ensure they use the new route names.\n";
    } else {
        echo "ℹ No changes were needed.\n";
    }
}

// Run the script
main();


$srcDir = __DIR__ . '/../../src';

function findControllersWithClassPrefixes(string $srcDir): array
{
    $controllers = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir . '/Controller'),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);
            
            // Look for class-level Route attributes with name prefixes ending in '_'
            if (preg_match('/#\[Route\([^)]*name:\s*[\'"]([a-zA-Z0-9_]+_)[\'"][^)]*\)/', $content, $matches)) {
                $controllers[] = [
                    'file' => $filePath,
                    'content' => $content,
                    'prefix' => $matches[1]
                ];
            }
        }
    }
    
    return $controllers;
}

function removeClassPrefix(array $controller): string
{
    $content = $controller['content'];
    $prefix = $controller['prefix'];
    
    // Remove the name attribute from the class-level Route
    $pattern = '/(#\[Route\([^)]*), name:\s*[\'"]' . preg_quote($prefix, '/') . '[\'"]([^)]*\))/';
    $replacement = '$1$2';
    
    return preg_replace($pattern, $replacement, $content);
}

// Main execution
echo "Looking for controllers with class-level route name prefixes...\n\n";

$controllers = findControllersWithClassPrefixes($srcDir);

if (empty($controllers)) {
    echo "No controllers found with class-level route name prefixes.\n";
    exit(0);
}

echo "Found " . count($controllers) . " controller(s) with class-level route name prefixes:\n\n";

// Show preview
echo "=== DRY RUN - Preview of changes ===\n\n";
foreach ($controllers as $controller) {
    $fileName = basename($controller['file']);
    echo "Processing: {$fileName} (prefix: {$controller['prefix']})\n";
    echo "  - Remove class-level name prefix '{$controller['prefix']}'\n\n";
}

// Ask for confirmation
echo "=== CONFIRMATION ===\n";
echo "Do you want to apply these changes? (y/N): ";
$confirmation = trim(fgets(STDIN));

if (strtolower($confirmation) !== 'y') {
    echo "Operation cancelled.\n";
    exit(0);
}

echo "\n=== APPLYING CHANGES ===\n\n";

$successful = 0;
$failed = 0;

foreach ($controllers as $controller) {
    $fileName = basename($controller['file']);
    echo "Processing: {$fileName} (prefix: {$controller['prefix']})\n";
    
    try {
        $newContent = removeClassPrefix($controller);
        
        if (file_put_contents($controller['file'], $newContent) !== false) {
            echo "  ✓ File updated successfully\n\n";
            $successful++;
        } else {
            echo "  ✗ Failed to write file\n\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $failed++;
    }
}

echo "Summary:\n";
echo "- Controllers processed: " . count($controllers) . "\n";
echo "- Controllers modified: {$successful}\n";
echo "- Controllers failed: {$failed}\n\n";

if ($successful > 0) {
    echo "✓ Class-level route name prefixes have been removed!\n";
}
