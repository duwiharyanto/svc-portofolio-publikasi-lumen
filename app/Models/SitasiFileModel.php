<?php

namespace App\Models;

use App\Http\Controllers\FileController;
use Illuminate\Database\Eloquent\Model;

class SitasiFileModel extends Model
{
    use FileController;
    protected $table   = 'publikasi_sitasi_file';
    public $timestamps = false;
    protected $guarded = ['id', 'uuid'];
    protected $hidden  = ['row_id', 'id', 'id_sitasi', 'id_sitasi_meta', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];
    protected $appends = ['url_file'];

    //! Prop Minio untuk file
    public function getUrlFileAttribute()
    {
        return $this->getFile($this->path_file)['plainUrl'];
    }
    // public function getUrlFileAttribute()
    // {
    //     return $this->getFile($this->path_file)['presignedUrl'];
    // }

    // public function statusSitasi()
    // {
    //     return $this->hasOne('App\Models\StatusModel', 'id', 'id_publikasi_status');

    // }

}
