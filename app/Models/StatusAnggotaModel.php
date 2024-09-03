<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusAnggotaModel extends Model {

    protected $table = 'publikasi_status_anggota';
	protected $guarded = [];
    protected $hidden = ['row_id', 'id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];
    protected $maps = [
        'uuid' => 'uuid_status_anggota'
    ];
    protected $appends = ['uuid_status_anggota'];
    //protected $attributes = [
    //     'status' => 'draft'
    // ];
    public function getUuidStatusAnggotaAttribute() {
        return $this->attributes['uuid'];
    }

}