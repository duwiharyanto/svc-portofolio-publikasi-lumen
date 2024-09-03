<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusModel extends Model {

    protected $table = 'publikasi_status';
	protected $guarded = ['id','uuid'];
	protected $maps = [
		'uuid' => 'uuid_status'
	]; 
	protected $appends = ['uuid_status'];
	protected $hidden = ['row_id','id', 'id_publikasi_bentuk', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];
	public function getUuidStatusAttribute() {
		return $this->attributes['uuid'];
	}
}