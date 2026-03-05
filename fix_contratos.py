import re

path = r'c:\Users\gesto\Desktop\projects\integra\app\Http\Controllers\ContratosController.php'
with open(path, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# Pattern for the NIT block
pattern = r'\$nit_str = trim\(\(string\)\$nit_raw\);\s+if \(strpos\(\$nit_str, [\'"]-[\'"]\) !== false\) \{\s+\$nit_parts = explode\([\'"]-[\'"], \$nit_str\);\s+\$nit = trim\(\$nit_parts\[0\]\);\s+\} else \{\s+\$nit = preg_replace\([\'"]\/\.0\+\\\$/[\'"], [\'"][\'"], \$nit_str\);\s+\}'
# Note: I'll make the regex even more flexible by using \s* and .*?
flexible_pattern = r'\$nit_str = trim\(\(string\)\$nit_raw\);\s*if \(strpos\(\$nit_str, [\'"]-[\'"]\) !== false\) \{.*?\$nit = preg_replace\([\'"]\/\.0\+\\?\$/[\'"], [\'"][\'"], \$nit_str\);\s*\}'

new_content = re.sub(flexible_pattern, '$nit = $this->cleanIdentification($nit_raw);', content, flags=re.DOTALL)

if new_content != content:
    with open(path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Successfully updated ContratosController.php")
else:
    print("Failed to update ContratosController.php")
