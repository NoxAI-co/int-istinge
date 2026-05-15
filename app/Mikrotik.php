<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Auth;
use App\User;
use App\Contrato;
use App\PlanesVelocidad;
use DB;

class Mikrotik extends Model
{
    protected $table = "mikrotik";
    protected $primaryKey = 'id';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nombre', 'ip', 'puerto_web', 'puerto_api', 'puerto_winbox', 'usuario', 'clave', 'status', 'segmento_ip', 'created_by', 'updated_by', 'created_at', 'updated_at', 'board', 'uptime', 'cpu', 'version', 'buildtime', 'freememory', 'totalmemory', 'cpucount', 'cpufrequency', 'cpuload', 'freehddspace', 'totalhddspace', 'writesectsincereboot', 'writesecttotal', 'architecturename', 'platform', 'interfaz', 'reglas', 'interfaz_lan', 'regla_ips_autorizadas'
    ];

    protected $appends = ['uso', 'session'];

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'server_configuration_id', 'id');
    }

    public function planes()
    {
        return $this->hasMany(PlanesVelocidad::class, 'mikrotik', 'id');
    }

    public function getUsoAttribute()
    {
        $hasContratosCount = array_key_exists('contratos_uso_count', $this->attributes);
        $hasPlanesCount    = array_key_exists('planes_uso_count', $this->attributes);
        if ($hasContratosCount || $hasPlanesCount) {
            return (int) ($this->attributes['contratos_uso_count'] ?? 0)
                 + (int) ($this->attributes['planes_uso_count'] ?? 0);
        }
        return $this->uso();
    }

    public function getSessionAttribute()
    {
        if(isset(Auth::user()->rol)){
            return $this->getAllPermissions(Auth::user()->id);
        }
    }

    public function getAllPermissions($id)
    {
        if(Auth::user()->rol>=2){
            if (isset($_SESSION['permisos']) && is_array($_SESSION['permisos'])) {
                return $_SESSION['permisos'];
            }
            if (DB::table('permisos_usuarios')->select('id_permiso')->where('id_usuario', $id)->count() > 0 ) {
                $permisos = DB::table('permisos_usuarios')->select('id_permiso')->where('id_usuario', $id)->get();
                foreach ($permisos as $key => $value) {
                    $_SESSION['permisos'][$permisos[$key]->id_permiso] = '1';
                }
                return $_SESSION['permisos'];
            }
            else return null;
        }
    }

    public function updated_by(){
        return User::where('id', $this->updated_by)->first();
    }
    
    public function created_by(){
        return User::where('id', $this->created_by)->first();
    }

    public function status($class = false){
        if($class){
            if($this->status == 0){
                $status = 'danger';
            }elseif($this->status == 1){
                $status = 'success';
            }
            return $status;
        }
        
        if($this->status == 0){
            $status = 'Desconectada';
        }elseif($this->status == 1){
            $status = 'Conectada';
        }
        return $status;
    }

    public function uso(){
        $tmp        = 0;
        $tmp        += Contrato::where('server_configuration_id', $this->id)->where('state','enabled')->where('status',1)->count();
        $tmp        += PlanesVelocidad::where('mikrotik', $this->id)->where('status',1)->count();
        return $tmp;
    }

    public function amarre_mac($class = false){
        if($class){
            return ($this->amarre_mac == 0) ? 'danger' : 'success';
        }

        return ($this->amarre_mac == 0) ? 'Deshabilitado' : 'Habilitado';
    }

    public function clientes($tipo){
        if ($tipo == 'enabled' && array_key_exists('clientes_enabled_count', $this->attributes)) {
            return (int) $this->attributes['clientes_enabled_count'];
        }
        if ($tipo == 'disabled' && array_key_exists('clientes_disabled_count', $this->attributes)) {
            return (int) $this->attributes['clientes_disabled_count'];
        }

        $base = Contrato::where('server_configuration_id', $this->id)->where('status', 1);
        if ($tipo == 'enabled') {
            return (clone $base)->whereNotIn('id', function ($q) {
                $q->select('contrato')->from('pings');
            })->count();
        }
        return (clone $base)->whereIn('id', function ($q) {
            $q->select('contrato')->from('pings');
        })->count();
    }
}