<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublikasiModel extends Model
{

    const CREATED_AT = 'tgl_input';
    const UPDATED_AT = 'tgl_update';

    protected $table = 'publikasi';
    protected $guarded = [];
    // protected $maps = [
    //     'uuid' => 'uuid_publikasi'
    // ];
    protected $appends = ['uuid_publikasi'];
    protected $hidden = ['row_id', 'id_publikasi_jenis', 'key', 'id', 'id_pegawai', 'id_publikasi_bentuk', 'id_publikasi_status', 'id_publikasi_peran', 'id_publikasi_form_versi', 'id_instansi', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
    public function getUuidPublikasiAttribute()
    {
        return $this->attributes['uuid'];
    }
    public function PublikasiMeta()
    {
        return $this->hasMany('App\Models\PublikasiMetaModel','id_publikasi');
    }
    public function RiwayatPerbaikan()
    {
        return $this->hasMany('App\Models\RiwayatPerbaikanModel','id_publikasi');
    }
}
