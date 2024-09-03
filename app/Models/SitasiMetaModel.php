<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitasiMetaModel extends Model
{
    public $timestamps = false;
    protected $table   = 'publikasi_sitasi_meta';
    protected $guarded = ['id', 'uuid'];
    protected $hidden  = [
        'row_id',
        'id',
        'id_sitasi',
        'id_publikasi_status',
        'tgl_input',
        'user_input',
        'tgl_update',
        'user_update',
        'user_verifikasi',
        'tgl_verifikasi',
        'user_validasi',
        'tgl_validasi',
        'catatan_penghapusan',
        'flag_perbaikan_remunerasi',
        'flag_tolak_remunerasi',
        'flag_remunerasi'];

    public function sitasiUtama()
    {
        return $this->belongsTo('App\Models\SitasiModel', 'id_sitasi');
    }
    public function sitasiFile()
    {
        return $this->hasOne('App\Models\SitasiFileModel', 'id_sitasi_meta');
    }
    public function status()
    {
        return $this->belongsTo('App\Models\StatusModel', 'id_publikasi_status');
    }
    //! Tidak digunakan
    // public function formVersion()
    // {
    //     return $this->hasOne('App\Models\VersiFormModel', 'id_publikasi_bentuk');
    // }
    // public function statusSitasi()
    // {
    //     return $this->hasOne('App\Models\StatusModel', 'id', 'id_publikasi_status');
    // }

}
