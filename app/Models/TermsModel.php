<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermsModel extends Model {

    protected $table = 'publikasi_terms';
	protected $guarded = ['row_id', 'id', 'uuid'];
	//protected $fillable = ['id_penelitian', 'nama_surat_dokumen', 'keterangan_surat_dokumen', 'path_file'];
	protected $hidden = ['row_id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];

     protected $maps = [
		'nama_term' => 'option_text_field'
	];
    protected $appends = ['option_text_field'];
    public function getOptionTextFieldAttribute()
    {
        return $this->attributes['nama_term'];
    }

	public function taxonomy() {
		return $this->hasOne('App\Models\TermTaxonomyModel', 'id');
	}
}
