<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use DB;

/**
 * Recibe los eventos del puente externo de WhatsApp (whatsive).
 *
 * Las rutas van detrás del middleware `whatsapp.bridge`: antes estaban abiertas
 * a internet y, como todas las consultas se armaban concatenando el JSON
 * entrante, cualquiera podía inyectar SQL. Ahora se usan bindings en todo.
 */
class WhatsappController extends Controller
{
    /** Etiqueta que se muestra en la lista de chats según el tipo de mensaje. */
    private const ETIQUETAS_TIPO = [
        "video"                 => "  <span class = 'fas fa-video fa-lg' ></span> Video",
        "ptt"                   => "  <span class = 'fas fa-microphone fa-lg' ></span> Audio",
        "audio"                 => "  <span class = 'fas fa-microphone fa-lg' ></span> Audio",
        "image"                 => "  <span class = 'fas fa-image fa-lg' ></span> Imagen",
        "sticker"               => "  <span class = 'fas fa-file fa-lg' ></span> Sticker",
        "document"              => "  <span class = 'fas fa-file-archive fa-lg' ></span> Archivo",
        "location"              => "  <span class = 'fas fa-map fa-lg' ></span> Ubicacion",
        "call_log"              => "  <span style = 'color:red' class = 'fa fa-phone fa-lg' ></span> Llamada perdida ",
        "e2e_notification"      => "Respuesta automatica",
        "ciphertext"            => "  <span class = 'fas fa-microphone fa-lg' ></span> Audio",
        "revoked"               => "<span class = 'fa fa-ban fa-lg' ></span> Elimino el mensaje",
        "vcard"                 => "<span class = 'fa fa-user fa-lg' ></span> Contacto",
        "notification_template" => "<span class = 'fa fa-clock-o fa-lg' ></span> Aviso whatsapp",
        "gp2"                   => "<span class = 'fa fa-clock-o fa-lg' ></span> Aviso whatsapp",
    ];

    private const FOTO_POR_DEFECTO = 'https://ramenparados.com/wp-content/uploads/2019/03/no-avatar-png-8.png';

    public function whatsappApi(Request $request, $action)
    {
        switch ($action) {
            case "newmessagewatme":
                return $this->mensajePropio($request);

            case "newmessagewat":
                return $this->mensajeEntrante($request);

            case 'changeStatus':
                // Sin WHERE, igual que antes: la tabla `instancia` tiene una fila.
                DB::table('instancia')->update(['status' => $request->input('status')]);

                return "true";

            case "verify":
                $instancia = DB::table('instancia')->first();

                if (is_null($instancia) || empty($instancia)) {
                    return "true";
                }

                return hash_equals((string) $instancia->unique, (string) $request->input('unique')) ? "true" : "false";

            default:
                return $request->input("action");
        }
    }

    /** Mensaje enviado desde el teléfono del negocio (fromMe = 1). */
    private function mensajePropio(Request $request)
    {
        $data = json_decode($request->input("msg"));

        if (!$this->payloadUtil($data) || strpos($data->to, "@g.us") > 0) {
            return null;
        }

        $numero = explode("@", $data->to)[0];

        // Ojo: este flujo SÍ etiqueta las ubicaciones, a diferencia del entrante.
        $this->guardarChat($numero, $data, [
            'notRead' => '0',
            'fromMe'  => '1',
        ], null, false);

        return "true";
    }

    /** Mensaje recibido de un cliente (fromMe = 0), con contador de no leídos. */
    private function mensajeEntrante(Request $request)
    {
        $data = json_decode($request->input("msg"));

        // Los mensajes con `author` vienen de grupos y se ignoran.
        if (!$this->payloadUtil($data) || isset($data->author)) {
            return null;
        }

        $numero = explode("@", $data->from)[0];

        $chat = DB::table("chats_whatsapp")->where("number", "=", $numero)->first();
        $noLeidos = $chat ? ((int) $chat->notRead) + 1 : 1;

        $this->guardarChat($numero, $data, [
            'notRead' => (string) $noLeidos,
            'fromMe'  => '0',
        ], $chat, true);

        return "true";
    }

    /**
     * Inserta o actualiza la fila del chat.
     *
     * Antes esto eran DB::statement() con el JSON concatenado dentro del SQL, y
     * el único intento de escape era cambiar comillas simples por dobles en el
     * cuerpo del mensaje: number, name, type y photo entraban crudos. Con
     * bindings el texto se guarda tal cual lo escribió el cliente.
     */
    private function guardarChat(string $numero, $data, array $extra, $chat = null, bool $ubicacionComoTexto = true)
    {
        $hora = date("Y-m-d H:i:s", (int) $data->timestamp);
        $tipo = isset($data->type) ? (string) $data->type : 'chat';

        $esTexto = $tipo === "chat" || ($ubicacionComoTexto && $tipo === "location");

        $cuerpo = (!$esTexto && isset(self::ETIQUETAS_TIPO[$tipo]))
            ? self::ETIQUETAS_TIPO[$tipo]
            : (isset($data->body) ? (string) $data->body : '');

        if ($chat === null) {
            $chat = DB::table("chats_whatsapp")->where("number", "=", $numero)->first();
        }

        if (is_null($chat) || empty($chat)) {
            DB::table("chats_whatsapp")->insert([
                'number'       => $numero,
                'name'         => $this->nombreDe($data, $numero),
                'last_update'  => $hora,
                'asigned_to'   => '0',
                'last_message' => $cuerpo,
                'type'         => $tipo,
                'photo'        => $this->fotoDe($data),
            ] + $extra);

            return;
        }

        DB::table("chats_whatsapp")->where("number", "=", $numero)->update([
            'last_update'  => $hora,
            'last_message' => $cuerpo,
            'type'         => $tipo,
            'estado'       => 'abierto',
        ] + $extra);
    }

    private function nombreDe($data, string $numero): string
    {
        if (isset($data->contact->name)) {
            return (string) $data->contact->name;
        }

        if (isset($data->_data->notifyName)) {
            return (string) $data->_data->notifyName;
        }

        return $numero;
    }

    private function fotoDe($data): string
    {
        return (!isset($data->picurl) || is_null($data->picurl))
            ? self::FOTO_POR_DEFECTO
            : (string) $data->picurl;
    }

    /** El puente puede mandar JSON inválido o sin los campos mínimos. */
    private function payloadUtil($data): bool
    {
        return is_object($data) && isset($data->timestamp);
    }

    public function whatsappUpload(Request $request)
    {
        // Antes se aceptaba cualquier archivo de cualquier tamaño sin comprobar
        // siquiera que viniera uno: $request->file("file") podía ser null.
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file("file");
        $md5Hash = md5_file($file->path());

        Storage::disk('local')->put("files/" . $md5Hash, file_get_contents($file->getRealPath()));

        return ["name" => "files/" . $md5Hash, "mime" => $file->getClientMimeType(), "estado" => "success"];
    }
}
