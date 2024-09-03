<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormModel extends Model {

    protected $table = 'publikasi_form';
    protected $guarded = [];
    protected $maps = [
        'uuid' => 'uuid_form'
    ];
    protected $appends = ['uuid_form'];
    protected $hidden = ['row_id', 'flag_aktif', 'tgl_input', 'user_input', 'tgl_update', 'user_update', 'uuid'];
    protected $casts = [
        'field_configs' => 'json'
    ];

    public function getUuidFormAttribute() {
        return $this->attributes['uuid'];
    }

    public function childrenFields() {
        return $this->hasMany($this, 'id_publikasi_form_induk', 'id');
    }

    public function allChildrenFields() {
        return $this->childrenFields()->with('allChildrenFields');
    }

}