<?php
$file = 'c:/Users/gesto/Desktop/projects/integra/app/Http/Controllers/ContratosController.php';
$content = file_get_contents($file);

// Replacement for NIT cleaning block
$oldBlock = "/\/\/ Limpiar NIT de formatos Excel \(decimales\) o guiones de verificación\s+\$nit_str = trim\(\(string\)\$nit_raw\);\s+if \(strpos\(\$nit_str, '-'|\"\-\"\) !== false\) \{\s+\$nit_parts = explode\('-'|\"\-\"|\'-\', \$nit_str\);\s+\$nit = trim\(\$nit_parts\[0\]\);\s+\} else \{\s+\$nit = preg_replace\('\/\.\\\.0\+\\\$\/'|\"\/\.\\\.0\+\\\$\/\", '', \$nit_str\);\s+\}/";

// Actually, let's just use string replace for a very specific part if regex is too complex
$newContent = $content;

// 1. First loop NIT cleaning
$target1 = '$nit_str = trim((string)$nit_raw);
            if (strpos($nit_str, \'-\') !== false) {
                $nit_parts = explode(\'-\', $nit_str);
                $nit = trim($nit_parts[0]);
            } else {
                $nit = preg_replace(\'/\.0+$/\', \'\', $nit_str);
            }';

// Since the spaces/tabs might be the issue, I'll use a regex for the white spaces
$pattern = '/\$nit_str = trim\(\(string\)\$nit_raw\);\s+if \(strpos\(\$nit_str, \'-\'\) !== false\) \{\s+\$nit_parts = explode\(\'-\', \$nit_str\);\s+\$nit = trim\(\$nit_parts\[0\]\);\s+\} else \{\s+\$nit = preg_replace\(\'\/\.0\+\\\$\/\', \'\', \$nit_str\);\s+\}/';
$replacement = '$nit = $this->cleanIdentification($nit_raw);';

$newContent = preg_replace($pattern, $replacement, $newContent);

if ($newContent !== null && $newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Successfully updated ContratosController.php\n";
} else {
    echo "Failed to update ContratosController.php or no changes needed\n";
}
