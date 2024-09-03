<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BentukModel extends Model {

    protected $table = 'publikasi_bentuk';
	protected $guarded = ['id','uuid'];
	protected $maps = [
		'uuid' => 'uuid_bentuk'
	];
	protected $appends = ['uuid_bentuk'];
	protected $hidden = ['slug','row_id', 'id', 'id_bentuk_umum','id_publikasi_form_versi', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
	public function getUuidBentukAttribute() {
		return $this->attributes['uuid'];
	}
	public function formVersions() {
		return $this->hasMany('App\Models\VersiFormModel', 'id_publikasi_bentuk');
	}

	public function formVersion() {
		return $this->hasOne('App\Models\VersiFormModel', 'id_publikasi_bentuk');
	}

}