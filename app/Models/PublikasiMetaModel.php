<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublikasiMetaModel extends Model {

    const CREATED_AT = 'tgl_input';
    const UPDATED_AT = 'tgl_update';

    protected $table = 'publikasi_meta';
	protected $guarded = ['id','uuid'];
	//protected $fillable = ['id_penelitian', 'nama_surat_dokumen', 'keterangan_surat_dokumen', 'path_file'];
    protected $hidden = ['id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'row_id', 'id_publikasi'];
    // public function getValueAttribute() {
    // 	return $this->attributes['value'];
    // }

}