<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermTaxonomyModel extends Model {

    protected $table = 'publikasi_term_taxonomy';
	protected $guarded = ['row_id', 'id','uuid'];
	//protected $fillable = ['id_penelitian', 'nama_surat_dokumen', 'keterangan_surat_dokumen', 'path_file'];
	protected $hidden = ['row_id', 'id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];
	
	public function terms() {
		return $this->hasMany('App\Models\TermsModel', 'id_publikasi_term_taxonomy');
	}
}