<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Certificado
 * 
 * @property int $id
 * @property int $contrato_id
 * @property int $codigo_certificado
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Contrato $contrato
 * @property Collection|Tercero[] $terceros
 * @property Collection|HerederosLegale[] $herederos_legales
 *
 * @package App\Models
 */
class Certificado extends Model
{
	protected $table = 'certificados';

	protected $connection = 'mysql_personas';

	protected $casts = [
		'contrato_id' => 'int',
		'codigo_certificado' => 'int'
	];

	protected $fillable = [
		'contrato_id',
		'codigo_certificado',
		'status'
	];

	public function contrato()
	{
		return $this->belongsTo(Contrato::class);
	}

	public function terceros()
	{
		return $this->belongsToMany(Tercero::class, 'certificados_terceros')
					->withPivot('id', 'parentesco_id', 'fecha_ingreso', 'fecha_egreso', 'observacion_egreso', 'fecha_reactivacion', 'observacion_reactivacion', 'plazo_espera', 'motivo_anulacion_plazo_espera', 'estatus_ingreso', 'status')
					->withTimestamps();
	}

	public function herederos_legales()
	{
		return $this->hasMany(HerederosLegale::class);
	}
}
