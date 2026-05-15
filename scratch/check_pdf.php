<?php
$url = 'https://cablenetjm.site/software/api/factura/2y10tHDHXgJ1PdczYm5sOpuMfOQEQ9o7lNkTcp4T3o3O9fHcRHR1DUCbK/pdf-onepay';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$data = curl_exec($ch);
if ($data === false) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo "First 20 bytes: " . bin2hex(substr($data, 0, 20)) . "\n";
    echo "As text: " . substr($data, 0, 20) . "\n";
}
curl_close($ch);
