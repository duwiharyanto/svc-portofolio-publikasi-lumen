<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstansiPublikasiModel extends Model {

    protected $table = 'publikasi_instansi';
	protected $guarded = [];
    public $timestamps = false;
    protected $hidden = ['id', 'id_publikasi_form','id_publikasi_ajuan','row_id','flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];

    //public function negara() {
    //    return $this->hasOne('App\Models\NegaraPublikasiModel', 'kd_negara');
    //}
}
