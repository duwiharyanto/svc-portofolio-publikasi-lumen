<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitasiModel extends Model
{

    public $timestamps = false;
    protected $table   = 'publikasi_sitasi';
    protected $guarded = ['id', 'uuid'];
    protected $hidden  = ['row_id', 'id', 'id_pegawai', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
    // public function formVersion()
    // {
    //     return $this->hasOne('App\Models\VersiFormModel', 'id_publikasi_bentuk');
    // }
    public function pegawai()
    {
        return $this->belongsTo(PegawaiModel::class, 'id_pegawai');
    }
    public function status()
    {
        return $this->belongsTo(StatusModel::class, 'id_publikasi_status');
    }
    public function sitasiMeta()
    {
        return $this->hasMany(SitasiMetaModel::class, 'id_sitasi')->where('flag_aktif', true)->orderBy('sitasi_tahun', 'desc');
    }
    public function bentukPublikasi()
    {
        return $this->belongsTo(BentukModel::class, 'id_publikasi_bentuk');
    }
}
