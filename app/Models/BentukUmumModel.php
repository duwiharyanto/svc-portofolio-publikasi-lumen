<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BentukUmumModel extends Model {

    protected $table = 'publikasi_bentuk_umum';
	protected $guarded = ['id','uuid'];
	protected $maps = [
		'uuid' => 'uuid_bentuk_umum'
	];
	protected $appends = ['uuid_bentuk_umum'];
	protected $hidden = ['row_id', 'id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
	public function getUuidBentukUmumAttribute() {
		return $this->attributes['uuid'];
	}
	public function bentukPublikasi() {
		return $this->hasMany('App\Models\BentukModel', 'id_publikasi_bentuk_umum');
	}

}