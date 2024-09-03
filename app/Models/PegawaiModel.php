<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiModel extends Model {

	protected $table = 'pegawai';

	const CREATED_AT = 'tgl_input';
    const UPDATED_AT = 'tgl_update';

	protected $guarded = [];
	protected $appends = ['uuid_pegawai'];
	protected $hidden = ['row_id', 'id', 'id_personal_data_pribadi', 'id_jenis_pegawai', 'id_status_pegawai', 'id_kelompok_pegawai', 'id_golongan', 'id_ruang', 'id_unit_kerja1', 'id_unit_kerja2', 'id_unit_kerja3', 'id_unit_kerja_lokasi', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];

	public function getUuidPegawaiAttribute() {
		return $this->attributes['uuid'];
	}

}
