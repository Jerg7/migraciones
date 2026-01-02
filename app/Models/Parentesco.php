<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Parentesco
 * 
 * @property int $id
 * @property string|null $desc_parentesco
 * @property int $codigo_nueveonce
 * @property int $status
 * @property Carbon $fecha_registro
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class Parentesco extends Model
{
	protected $table = 'parentesco';

	protected $casts = [
		'codigo_nueveonce' => 'int',
		'status' => 'int',
		'fecha_registro' => 'datetime'
	];

	protected $fillable = [
		'desc_parentesco',
		'codigo_nueveonce',
		'status',
		'fecha_registro'
	];
}
