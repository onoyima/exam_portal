<?php
$content = file_get_contents(__DIR__ . '/openapi.json');
$data = json_decode($content);
if ($data === null) {
    echo "Invalid JSON: " . json_last_error_msg() . "\n";
    echo "First 300 bytes:\n" . substr($content, 0, 300) . "\n";
} else {
    $pathCount = count((array)$data->paths);
    $schemaCount = count((array)$data->components->schemas);
    echo "Valid JSON\n";
    echo "Paths: $pathCount\n";
    echo "Schemas: $schemaCount\n";
    echo "File size: " . strlen($content) . " bytes\n";
}
