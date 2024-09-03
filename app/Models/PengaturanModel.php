<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanModel extends Model {

    protected $table = 'pengaturan';
	protected $guarded = ['id','uuid'];

    public function scopeAktif($query){
        return $query->where('flag_aktif', true);
    }

    public function scopeVerifikasiAdmin($query, $kdPengaturan){
        return $query->where('kd_pengaturan', $kdPengaturan);
    }

}
