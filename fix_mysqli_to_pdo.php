<?php
/**
 * Script to convert MySQLi syntax to PDO syntax across all PHP files
 * This fixes the bind_param, get_result, and close method issues
 */

function convertMySQLiToPDO($content) {
    // Pattern 1: $stmt->bind_param("types", $var1, $var2, ...) -> $stmt->execute([$var1, $var2, ...])
    $content = preg_replace_callback(
        '/\$(\w+)->bind_param\(["\']([^"\']*)["\'],\s*([^)]+)\);\s*\$\1->execute\(\);/',
        function($matches) {
            $stmtVar = $matches[1];
            $types = $matches[2];
            $params = $matches[3];
            
            // Split parameters and clean them up
            $paramArray = array_map('trim', explode(',', $params));
            $paramList = implode(', ', $paramArray);
            
            return "\${$stmtVar}->execute([{$paramList}]);";
        },
        $content
    );
    
    // Pattern 2: $stmt->bind_param("types", $var1, $var2, ...)  (without execute)
    $content = preg_replace_callback(
        '/\$(\w+)->bind_param\(["\']([^"\']*)["\'],\s*([^)]+)\);/',
        function($matches) {
            $stmtVar = $matches[1];
            $types = $matches[2];
            $params = $matches[3];
            
            // Split parameters and clean them up
            $paramArray = array_map('trim', explode(',', $params));
            $paramList = implode(', ', $paramArray);
            
            return "// Parameters will be passed to execute(): [{$paramList}]";
        },
        $content
    );
    
    // Pattern 3: $result = $stmt->get_result(); $data = $result->fetch_assoc();
    $content = preg_replace(
        '/\$(\w+)\s*=\s*\$(\w+)->get_result\(\);\s*\$(\w+)\s*=\s*\$\1->fetch_assoc\(\);/',
        '$\3 = $\2->fetch();',
        $content
    );
    
    // Pattern 4: $stmt->get_result()->fetch_assoc()
    $content = preg_replace(
        '/\$(\w+)->get_result\(\)->fetch_assoc\(\)/',
        '$\1->fetch()',
        $content
    );
    
    // Pattern 5: $stmt->get_result()->num_rows
    $content = preg_replace(
        '/\$(\w+)->get_result\(\)->num_rows/',
        '$\1->rowCount()',
        $content
    );
    
    // Pattern 6: $result = $stmt->get_result(); while loop
    $content = preg_replace(
        '/\$(\w+)\s*=\s*\$(\w+)->get_result\(\);\s*while\s*\(\$(\w+)\s*=\s*\$\1->fetch_assoc\(\)\)/',
        'while ($\3 = $\2->fetch())',
        $content
    );
    
    // Pattern 7: $stmt->close();
    $content = preg_replace('/\$\w+->close\(\);\s*/', '', $content);
    
    // Pattern 8: if ($stmt->execute()) with parameters in bind_param
    $content = preg_replace(
        '/if\s*\(\$(\w+)->execute\(\)\)/',
        'if ($\1->execute())',
        $content
    );
    
    return $content;
}

function processDirectory($dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $phpFiles = [];
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' && $file->getFilename() !== 'fix_mysqli_to_pdo.php') {
            $phpFiles[] = $file->getPathname();
        }
    }
    
    return $phpFiles;
}

// Get all PHP files in the project
$projectDir = __DIR__;
$phpFiles = processDirectory($projectDir);

$processedFiles = [];
$errorFiles = [];

foreach ($phpFiles as $file) {
    try {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Convert MySQLi to PDO
        $convertedContent = convertMySQLiToPDO($content);
        
        // Only write if content changed
        if ($convertedContent !== $originalContent) {
            if (file_put_contents($file, $convertedContent)) {
                $processedFiles[] = $file;
                echo "✓ Processed: " . basename($file) . "\n";
            } else {
                $errorFiles[] = $file;
                echo "✗ Error writing: " . basename($file) . "\n";
            }
        }
    } catch (Exception $e) {
        $errorFiles[] = $file;
        echo "✗ Error processing " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n=== CONVERSION SUMMARY ===\n";
echo "Files processed: " . count($processedFiles) . "\n";
echo "Files with errors: " . count($errorFiles) . "\n";

if (!empty($processedFiles)) {
    echo "\nProcessed files:\n";
    foreach ($processedFiles as $file) {
        echo "- " . str_replace($projectDir . DIRECTORY_SEPARATOR, '', $file) . "\n";
    }
}

if (!empty($errorFiles)) {
    echo "\nFiles with errors:\n";
    foreach ($errorFiles as $file) {
        echo "- " . str_replace($projectDir . DIRECTORY_SEPARATOR, '', $file) . "\n";
    }
}

echo "\nConversion complete!\n";
?>
