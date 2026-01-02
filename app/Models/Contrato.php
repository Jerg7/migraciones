<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contrato
 * 
 * @property int $id_contrato
 * @property int $cod_estatus_poliza
 * @property int $num_contrato
 * @property int $max_casos_999
 * @property Carbon $fec_registro
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property EstatusPoliza $estatus_poliza
 * @property Collection|Certificado[] $certificados
 * @property Collection|FactorToleranciaPagoRecibo[] $factor_tolerancia_pago_recibos
 * @property Collection|HistorialContratosAnulado[] $historial_contratos_anulados
 * @property RecibosCambio|null $recibos_cambio
 * @property Collection|RecibosRegistroPago[] $recibos_registro_pagos
 * @property Collection|Siniestro[] $siniestros
 * @property Collection|VersionesContrato[] $versiones_contratos
 * @property Collection|AperturasAnticipo[] $aperturas_anticipos
 * @property Collection|SolicitudesServicio[] $solicitudes_servicios
 *
 * @package App\Models
 */
class Contrato extends Model
{
	protected $table = 'contrato';
	protected $primaryKey = 'id_contrato';
	protected $connection = 'mysql_personas';


	protected $casts = [
		'cod_estatus_poliza' => 'int',
		'num_contrato' => 'int',
		'max_casos_999' => 'int',
		'fec_registro' => 'datetime'
	];

	protected $fillable = [
		'cod_estatus_poliza',
		'num_contrato',
		'max_casos_999',
		'fec_registro'
	];

	public function estatus_poliza()
	{
		return $this->belongsTo(EstatusPoliza::class, 'cod_estatus_poliza');
	}

	public function certificados()
	{
		return $this->hasMany(Certificado::class);
	}

	public function factor_tolerancia_pago_recibos()
	{
		return $this->hasMany(FactorToleranciaPagoRecibo::class);
	}

	public function historial_contratos_anulados()
	{
		return $this->hasMany(HistorialContratosAnulado::class);
	}

	public function recibos_cambio()
	{
		return $this->hasOne(RecibosCambio::class, 'id_contrato');
	}

	public function recibos_registro_pagos()
	{
		return $this->hasMany(RecibosRegistroPago::class, 'id_contrato');
	}

	public function siniestros()
	{
		return $this->hasMany(Siniestro::class, 'id_contrato');
	}

	public function versiones_contratos()
	{
		return $this->hasMany(VersionesContrato::class, 'id_contrato');
	}

	public function aperturas_anticipos()
	{
		return $this->hasMany(AperturasAnticipo::class);
	}

	public function solicitudes_servicios()
	{
		return $this->hasMany(SolicitudesServicio::class);
	}
}
