<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tercero
 * 
 * @property int $id_terceros
 * @property int|null $id_import
 * @property int|null $cod_documento
 * @property int|null $cedula
 * @property string|null $rif
 * @property string|null $nombre_completo
 * @property string|null $nombre_razonsocial
 * @property string|null $apellido
 * @property int|null $id_estado
 * @property int|null $id_ciudad
 * @property int|null $id_municipio
 * @property string|null $direccion
 * @property string|null $correo
 * @property string|null $correo_reembolso
 * @property string|null $correo_admision
 * @property string|null $correo_control_citas
 * @property string|null $telef1
 * @property string|null $telef2
 * @property string|null $telef_admision
 * @property string|null $telef_control_citas
 * @property int|null $zona_postal
 * @property string|null $sexo
 * @property int|null $edad
 * @property int|null $edad_actuarial
 * @property Carbon|null $fecha_nac_consti
 * @property float|null $peso
 * @property float|null $estatura
 * @property int|null $id_ocupacion
 * @property int|null $id_act_economica
 * @property int|null $tipo_estado_civil_id
 * @property string|null $nacionalidad
 * @property string|null $lugar_nacimiento
 * @property string|null $grado_instruccion
 * @property int|null $nivel_estudio_id
 * @property float|null $ingreso_anual
 * @property Carbon|null $fecha_expedicion_pasaporte
 * @property Carbon|null $fecha_caducidad_pasaporte
 * @property Carbon|null $fecha_ingreso_pais
 * @property string|null $estado_migratorio
 * @property bool $protegido
 * @property Carbon|null $fecha_registro
 * @property Carbon|null $fecha_update
 * @property int|null $id_proveedor
 * @property int|null $id_contratante
 * @property int|null $id_intermediario
 * @property int|null $id_carga_ente_final
 * @property int|null $actualizado_perfiles
 * @property string|null $urbanizacion_sector
 * @property string|null $avenida_calle_transv
 * @property string|null $casa_qta_edificio
 * @property string|null $apto_piso
 * @property string|null $alquilado
 * @property string|null $ultima_declaracion_islr
 * @property int|null $grupo_sanguineo_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property GruposSanguineo|null $grupos_sanguineo
 * @property NivelesEstudio|null $niveles_estudio
 * @property Ciudade|null $ciudade
 * @property Estado|null $estado
 * @property Municipio|null $municipio
 * @property TipoOcupacion|null $tipo_ocupacion
 * @property TipoActividadEconomica|null $tipo_actividad_economica
 * @property TipoDocumento|null $tipo_documento
 * @property TipoEstadoCivil|null $tipo_estado_civil
 * @property Collection|Alergia[] $alergias
 * @property Collection|ArchivosTercero[] $archivos_terceros
 * @property Collection|Contratante[] $contratantes
 * @property Collection|ContratantesRepresentantesLegale[] $contratantes_representantes_legales
 * @property Collection|CuentasBancaria[] $cuentas_bancarias
 * @property Collection|FirmasAutorizadasProveedore[] $firmas_autorizadas_proveedores
 * @property Collection|Intermediario[] $intermediarios
 * @property Collection|NominaAccionariaProveedore[] $nomina_accionaria_proveedores
 * @property Collection|Patologia[] $patologias
 * @property Collection|Proveedor[] $proveedors
 * @property Collection|RepresentanteLegalProveedore[] $representante_legal_proveedores
 * @property Collection|RrhhEmpleado[] $rrhh_empleados
 * @property Collection|SolicitudesServicio[] $solicitudes_servicios
 *
 * @package App\Models
 */
class Tercero extends Model
{
	protected $table = 'terceros';
	protected $primaryKey = 'id_terceros';

	protected $casts = [
		'id_import' => 'int',
		'cod_documento' => 'int',
		'cedula' => 'int',
		'id_estado' => 'int',
		'id_ciudad' => 'int',
		'id_municipio' => 'int',
		'zona_postal' => 'int',
		'edad' => 'int',
		'edad_actuarial' => 'int',
		'fecha_nac_consti' => 'datetime',
		'peso' => 'float',
		'estatura' => 'float',
		'id_ocupacion' => 'int',
		'id_act_economica' => 'int',
		'tipo_estado_civil_id' => 'int',
		'nivel_estudio_id' => 'int',
		'ingreso_anual' => 'float',
		'fecha_expedicion_pasaporte' => 'datetime',
		'fecha_caducidad_pasaporte' => 'datetime',
		'fecha_ingreso_pais' => 'datetime',
		'protegido' => 'bool',
		'fecha_registro' => 'datetime',
		'fecha_update' => 'datetime',
		'id_proveedor' => 'int',
		'id_contratante' => 'int',
		'id_intermediario' => 'int',
		'id_carga_ente_final' => 'int',
		'actualizado_perfiles' => 'int',
		'grupo_sanguineo_id' => 'int'
	];

	protected $fillable = [
		'id_import',
		'cod_documento',
		'cedula',
		'rif',
		'nombre_completo',
		'nombre_razonsocial',
		'apellido',
		'id_estado',
		'id_ciudad',
		'id_municipio',
		'direccion',
		'correo',
		'correo_reembolso',
		'correo_admision',
		'correo_control_citas',
		'telef1',
		'telef2',
		'telef_admision',
		'telef_control_citas',
		'zona_postal',
		'sexo',
		'edad',
		'edad_actuarial',
		'fecha_nac_consti',
		'peso',
		'estatura',
		'id_ocupacion',
		'id_act_economica',
		'tipo_estado_civil_id',
		'nacionalidad',
		'lugar_nacimiento',
		'grado_instruccion',
		'nivel_estudio_id',
		'ingreso_anual',
		'fecha_expedicion_pasaporte',
		'fecha_caducidad_pasaporte',
		'fecha_ingreso_pais',
		'estado_migratorio',
		'protegido',
		'fecha_registro',
		'fecha_update',
		'id_proveedor',
		'id_contratante',
		'id_intermediario',
		'id_carga_ente_final',
		'actualizado_perfiles',
		'urbanizacion_sector',
		'avenida_calle_transv',
		'casa_qta_edificio',
		'apto_piso',
		'alquilado',
		'ultima_declaracion_islr',
		'grupo_sanguineo_id'
	];

	public function grupos_sanguineo()
	{
		return $this->belongsTo(GruposSanguineo::class, 'grupo_sanguineo_id');
	}

	public function niveles_estudio()
	{
		return $this->belongsTo(NivelesEstudio::class, 'nivel_estudio_id');
	}

	public function ciudade()
	{
		return $this->belongsTo(Ciudade::class, 'id_ciudad');
	}

	public function estado()
	{
		return $this->belongsTo(Estado::class, 'id_estado');
	}

	public function municipio()
	{
		return $this->belongsTo(Municipio::class, 'id_municipio');
	}

	public function tipo_ocupacion()
	{
		return $this->belongsTo(TipoOcupacion::class, 'id_ocupacion');
	}

	public function tipo_actividad_economica()
	{
		return $this->belongsTo(TipoActividadEconomica::class, 'id_act_economica');
	}

	public function tipo_documento()
	{
		return $this->belongsTo(TipoDocumento::class, 'cod_documento');
	}

	public function tipo_estado_civil()
	{
		return $this->belongsTo(TipoEstadoCivil::class);
	}

	public function alergias()
	{
		return $this->belongsToMany(Alergia::class, 'alergias_terceros')
					->withPivot('id')
					->withTimestamps();
	}

	public function archivos_terceros()
	{
		return $this->hasMany(ArchivosTercero::class);
	}

	public function contratantes()
	{
		return $this->hasMany(Contratante::class);
	}

	public function contratantes_representantes_legales()
	{
		return $this->hasMany(ContratantesRepresentantesLegale::class);
	}

	public function cuentas_bancarias()
	{
		return $this->hasMany(CuentasBancaria::class);
	}

	public function firmas_autorizadas_proveedores()
	{
		return $this->hasMany(FirmasAutorizadasProveedore::class);
	}

	public function intermediarios()
	{
		return $this->hasMany(Intermediario::class);
	}

	public function nomina_accionaria_proveedores()
	{
		return $this->hasMany(NominaAccionariaProveedore::class);
	}

	public function patologias()
	{
		return $this->belongsToMany(Patologia::class, 'patologias_terceros')
					->withPivot('id')
					->withTimestamps();
	}

	public function proveedors()
	{
		return $this->hasMany(Proveedor::class, 'tercero_conyuge_id');
	}

	public function representante_legal_proveedores()
	{
		return $this->hasMany(RepresentanteLegalProveedore::class);
	}

	public function rrhh_empleados()
	{
		return $this->belongsToMany(RrhhEmpleado::class, 'rrhh_empleados_terceros', 'tercero_id', 'empleado_id')
					->withPivot('id', 'ubicacion_trabajo', 'cargo_trabajo', 'parentesco_id')
					->withTimestamps();
	}

	public function solicitudes_servicios()
	{
		return $this->hasMany(SolicitudesServicio::class);
	}
}
