<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TipoDocumento
 * 
 * @property int $cod_documento
 * @property string|null $siglas
 * @property string $descripcion
 * @property int $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Collection|DetalleUsuario[] $detalle_usuarios
 * @property Collection|Tercero[] $terceros
 *
 * @package App\Models
 */
class TipoDocumento extends Model
{
	protected $table = 'tipo_documento';
	protected $primaryKey = 'cod_documento';

	protected $casts = [
		'status' => 'int'
	];

	protected $fillable = [
		'siglas',
		'descripcion',
		'status'
	];

	public function detalle_usuarios()
	{
		return $this->hasMany(DetalleUsuario::class, 'cod_documento');
	}

	public function terceros()
	{
		return $this->hasMany(Tercero::class, 'cod_documento');
	}
}
