<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MahasiswaModel extends Model {

    protected $table = 'mahasiswa_rekap';

    const CREATED_AT = 'tgl_input';
    const UPDATED_AT = 'tgl_update';

    protected $guarded = [];
    protected $appends = ['uuid_mahasiswa', 'nama'];
    protected $hidden = ['row_id', 'id', 'id_organisasi', 'id_dosen', 'kd_dosen','flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update'];

    public function getUuidMahasiswaAttribute() {
        return $this->attributes['uuid'];
    }

    public function getNamaAttribute() {
        return $this->attributes['nama_mahasiswa'];
    }

}
