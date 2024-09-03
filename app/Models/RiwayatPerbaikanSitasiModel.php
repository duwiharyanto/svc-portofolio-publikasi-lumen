<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatPerbaikanSitasiModel extends Model
{

    protected $table = 'publikasi_sitasi_riwayat_perbaikan';
    protected $guarded = ['id', 'uuid', 'row_id'];
    protected $hidden = ['id','id_publikasi_sitasi','id_publikasi_status', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'row_id', 'id_publikasi'];

}
