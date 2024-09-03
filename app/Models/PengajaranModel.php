<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajaranModel extends Model
{

    protected $table = 'pengajaran';
    public $timestamps=false;
    protected $guarded = ['id', 'uuid'];
    protected $hidden = ['row_id', 'id', 'id_pegawai','id_pengajaran_status','flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];

}
