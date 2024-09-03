<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatPerbaikanModel extends Model
{

    protected $table = 'publikasi_riwayat_perbaikan';
    protected $guarded = ['id', 'uuid','row_id'];
    protected $hidden = ['id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'row_id', 'id_publikasi', 'id_publikasi_status'];

}
