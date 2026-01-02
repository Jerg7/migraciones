<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CertificadosTercero
 * 
 * @property int $id
 * @property int $certificado_id
 * @property int $tercero_id
 * @property int $parentesco_id
 * @property Carbon|null $fecha_ingreso
 * @property Carbon|null $fecha_egreso
 * @property string|null $observacion_egreso
 * @property Carbon|null $fecha_reactivacion
 * @property string|null $observacion_reactivacion
 * @property int|null $plazo_espera
 * @property string|null $motivo_anulacion_plazo_espera
 * @property string|null $estatus_ingreso
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Certificado $certificado
 * @property Parentesco $parentesco
 * @property Tercero $tercero
 * @property Collection|Siniestro[] $siniestros
 * @property Collection|SolicitudesServicio[] $solicitudes_servicios
 *
 * @package App\Models
 */
class CertificadosTercero extends Model
{
	protected $table = 'certificados_terceros';
	
	protected $connection = 'mysql_personas';

	protected $casts = [
		'certificado_id' => 'int',
		'tercero_id' => 'int',
		'parentesco_id' => 'int',
		'fecha_ingreso' => 'datetime',
		'fecha_egreso' => 'datetime',
		'fecha_reactivacion' => 'datetime',
		'plazo_espera' => 'int'
	];

	protected $fillable = [
		'certificado_id',
		'tercero_id',
		'parentesco_id',
		'fecha_ingreso',
		'fecha_egreso',
		'observacion_egreso',
		'fecha_reactivacion',
		'observacion_reactivacion',
		'plazo_espera',
		'motivo_anulacion_plazo_espera',
		'estatus_ingreso',
		'status'
	];

	public function certificado()
	{
		return $this->belongsTo(Certificado::class);
	}

	public function parentesco()
	{
		return $this->belongsTo(Parentesco::class);
	}

	public function tercero()
	{
		return $this->belongsTo(Tercero::class);
	}

	public function siniestros()
	{
		return $this->hasMany(Siniestro::class, 'certificados_terceros_id');
	}

	public function solicitudes_servicios()
	{
		return $this->hasMany(SolicitudesServicio::class, 'certificado_tercero_id');
	}
}
