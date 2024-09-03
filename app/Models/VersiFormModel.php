<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersiFormModel extends Model {
    protected $table = 'publikasi_form_versi';
    protected $guarded = [];
    protected $maps = [
        'uuid' => 'uuid_form_versi'
    ];
    protected $appends = ['uuid_form_versi'];
    protected $hidden = ['row_id', 'id', 'id_publikasi_bentuk', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update','user_update', 'uuid'];
    public function getUuidFormVersiAttribute() {
        return $this->attributes['uuid'];
    }
    protected $casts = [
        'grid_config' => 'json'
    ];

    public function forms() {
        return $this->hasMany('App\Models\FormModel', 'id_publikasi_form_versi');
    }

    public function form() {
        return $this->hasOne('App\Models\FormModel', 'id_publikasi_form_versi');
    }
}