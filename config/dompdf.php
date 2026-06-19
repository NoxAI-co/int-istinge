<?php

/*
|--------------------------------------------------------------------------
| Configuración de dompdf (barryvdh/laravel-dompdf)
|--------------------------------------------------------------------------
|
| Esta versión del paquete arma las opciones de dompdf a partir del array
| 'defines' (ver ServiceProvider::register → "if ($defines)"). Reutilizamos
| TODOS los defaults del paquete y solo sobreescribimos lo necesario.
|
| FIX: por defecto dompdf escribe un log de depuración en {temp_dir}/log.htm
| (= /tmp/log.htm). En los contenedores ese archivo suele quedar creado por
| otro usuario/proceso y el webserver no puede sobreescribirlo, rompiendo la
| generación del PDF con:
|     file_put_contents(/tmp/log.htm): failed to open stream: Permission denied
| Esto reventaba, por ejemplo, el envío de la factura por correo (BTW) durante
| la emisión masiva a la DIAN. Con 'log_output_file' => null, dompdf omite por
| completo ese bloque de log (Dompdf::render(): "if ($logOutputFile)").
|
*/

$config = require base_path('vendor/barryvdh/laravel-dompdf/config/dompdf.php');

// Desactivar el log de depuración a /tmp/log.htm (no se usa en producción).
$config['defines']['log_output_file'] = null;

return $config;
