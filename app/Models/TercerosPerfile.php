<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TercerosPerfile
 * 
 * @property int $id
 * @property int $tercero_id
 * @property string|null $contratante_id
 * @property string|null $proveedor_id
 * @property string|null $intermediario_id
 * @property string|null $asegurado_id
 * @property string|null $administrativo_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class TercerosPerfile extends Model
{
	protected $table = 'terceros_perfiles';

	protected $casts = [
		'tercero_id' => 'int'
	];

	protected $fillable = [
		'tercero_id',
		'contratante_id',
		'proveedor_id',
		'intermediario_id',
		'asegurado_id',
		'administrativo_id'
	];
}
