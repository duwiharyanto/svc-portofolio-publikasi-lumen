<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeranModel extends Model {

    protected $table = 'publikasi_peran';
	protected $guarded = ['id','uuid'];
	protected $maps = [
		'uuid' => 'uuid_peran'
	]; 
	protected $appends = ['uuid_peran'];
	protected $hidden = ['id_publikasi_bentuk','kd_peran','row_id','id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];
	public function getUuidPeranAttribute() {
		return $this->attributes['uuid'];
	}
}