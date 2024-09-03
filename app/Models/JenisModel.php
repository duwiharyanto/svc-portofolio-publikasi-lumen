<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisModel extends Model {

    protected $table = 'publikasi_jenis';
	protected $guarded = ['id','uuid'];
	//protected $fillable = ['id_penelitian', 'nama_surat_dokumen', 'keterangan_surat_dokumen', 'path_file'];
    protected $hidden = ['id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update','row_id','id_publikasi'];

}