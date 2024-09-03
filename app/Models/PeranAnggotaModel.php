<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeranAnggotaModel extends Model {

	protected $table = 'publikasi_peran_anggota';
	protected $guarded = ['id','uuid'];
	protected $maps = [
		'uuid' => 'uuid_peran_anggota'
	]; 
	protected $appends = ['uuid_peran_anggota'];
	protected $hidden = ['row_id', 'id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
	public function getUuidBentukAttribute() {
		return $this->attributes['uuid'];
	}

}