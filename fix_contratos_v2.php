<?php
$file = 'c:/Users/gesto/Desktop/projects/integra/app/Http/Controllers/ContratosController.php';
$content = file_get_contents($file);

// Use a more flexible regex that ignores specific white space amounts
$pattern = '/\/\/ Limpiar NIT de formatos Excel \(decimales\) o guiones de verificación\s+\$nit_str = trim\(\(string\)\$nit_raw\);\s+if \(strpos\(\$nit_str, [\'"]-[\'"]\) !== false\) \{\s+\$nit_parts = explode\([\'"]-[\'"], \$nit_str\);\s+\$nit = trim\(\$nit_parts\[0\]\);\s+\} else \{\s+\$nit = preg_replace\([\'"]\/\.0\+\\\$\/[\'"], [\'"][\'"], \$nit_str\);\s+\}/';

$replacement = '// Limpiar NIT de formatos Excel (decimales) o guiones de verificación
            $nit = $this->cleanIdentification($nit_raw);';

$newContent = preg_replace($pattern, $replacement, $content);

if ($newContent !== null && $newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Successfully updated ContratosController.php\n";
} else {
    echo "Failed to update ContratosController.php\n";
    // Try even simpler match
    $simplePattern = '/\$nit_str = trim\(\(string\)\$nit_raw\);\s+if \(strpos\(\$nit_str, [\'"]-[\'"]\) !== false\) \{.*?\$nit = preg_replace\([\'"]\/\.0\+\\\$\/[\'"], [\'"][\'"], \$nit_str\);\s+\}/s';
     $newContent = preg_replace($simplePattern, '$nit = $this->cleanIdentification($nit_raw);', $content);
     if ($newContent !== null && $newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Successfully updated ContratosController.php (simple pattern)\n";
     } else {
        echo "Failed even with simple pattern\n";
     }
}
