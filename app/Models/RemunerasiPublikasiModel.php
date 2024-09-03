<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemunerasiPublikasiModel extends Model
{

    const CREATED_AT = 'tgl_input';
    const UPDATED_AT = 'tgl_update';

    protected $table = 'publikasi';
    protected $guarded = [];
    // protected $maps = [
    //     'uuid' => 'uuid_publikasi'
    // ];
    // protected $appends = ['id_publikasi'];
    protected $appends = ['uuid_publikasi', 'id_publikasi', 'id_bentuk_publikasi', 'id_status_publikasi'];
    protected $hidden = ['row_id', 'flag_internasional', 'id', 'id_publikasi_jenis', 'key', 'value', 'flag_aktif', 'id_publikasi_bentuk', 'id_publikasi_status', 'tgl_input', 'tgl_update', 'user_update'];
    public function getUuidPublikasiAttribute()
    {
        return $this->attributes['uuid'];
    }
    public function getIdPublikasiAttribute()
    {
        return $this->attributes['id'];
    }
    public function getIdBentukPublikasiAttribute()
    {
        return $this->attributes['id_publikasi_bentuk'];
    }
    public function getIdStatusPublikasiAttribute()
    {
        return $this->attributes['id_publikasi_bentuk'];
    }
}
