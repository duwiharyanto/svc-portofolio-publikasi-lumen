<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait FormByKodeTrait
{
    public function rawQueryForm($kd_bentuk)
    {
        // DEPRECATED
        // $now = '2019-01-01';
        // $bentuk_publikasi = DB::table('publikasi_bentuk')->where('publikasi_bentuk.kd_bentuk_publikasi', '=', $kd_bentuk)->first();
        // $query = DB::table('publikasi_form')->
        //     select('publikasi_form.name_field', 'publikasi_form.flag_required', 'publikasi_form.id', 'publikasi_form.tipe_field', 'publikasi_form.id_publikasi_form_induk')->
        //     join('publikasi_form_versi', 'publikasi_form_versi.id', '=', 'publikasi_form.id_publikasi_form_versi')->
        //     where('publikasi_form_versi.flag_aktif', true)->
        //     where('publikasi_form_versi.id_publikasi_bentuk', '=', $bentuk_publikasi->id);

        $form_versi = DB::table('publikasi_form_versi')->
            join('publikasi_bentuk', 'publikasi_bentuk.id', '=', 'publikasi_form_versi.id_publikasi_bentuk')->
            where('publikasi_bentuk.kd_bentuk_publikasi', '=', $kd_bentuk)->
            where('publikasi_form_versi.flag_aktif', true)->
            first(['publikasi_form_versi.id', 'publikasi_form_versi.nama_versi', 'publikasi_form_versi.flag_aktif']);

        $query = DB::table('publikasi_form')->
            select('publikasi_form.name_field', 'publikasi_form.id_publikasi_form_versi', 'publikasi_form.flag_required', 'publikasi_form.id', 'publikasi_form.tipe_field', 'publikasi_form.id_publikasi_form_induk', 'publikasi_form.options')->
            join('publikasi_form_versi', 'publikasi_form_versi.id', '=', 'publikasi_form.id_publikasi_form_versi')->
            Where('publikasi_form_versi.flag_aktif', true)->Where('publikasi_form.flag_aktif', true)->
            where('publikasi_form.id_publikasi_form_versi', '=', $form_versi->id);
        return $query;
    }
    public function formByIdVersi($id_form_versi)
    {
        $query = DB::table('publikasi_form')->
            select('publikasi_form.name_field', 'publikasi_form.id_publikasi_form_versi', 'publikasi_form.flag_required', 'publikasi_form.id', 'publikasi_form.tipe_field', 'publikasi_form.id_publikasi_form_induk', 'publikasi_form.options')->
            join('publikasi_form_versi', 'publikasi_form_versi.id', '=', 'publikasi_form.id_publikasi_form_versi')->Where('publikasi_form.flag_aktif', true)->
            where('publikasi_form.id_publikasi_form_versi', '=', $id_form_versi);
        return $query;
    }
    public function getFormNameByKode(string $publikasi, string $param = null,bool $strict = false, string $id_form_versi=null)
    {
        $kd_bentuk = $publikasi;
        $id_form_versi = $id_form_versi;

        if (!$param) {
            //SET DEFAULT PARAM
            $param = 'all';
        }
        if ($strict) {
            $form_multiple = $this->formByIdVersi($id_form_versi)->where('publikasi_form.tipe_field', '=', 'multiple')->get();
            $form_all = $this->formByIdVersi($id_form_versi)->where('publikasi_form.tipe_field', '!=', 'multiple')->get();
        } else {
            $form_multiple = $this->rawQueryForm($kd_bentuk)->where('publikasi_form.tipe_field', '=', 'multiple')->get();
            $form_all = $this->rawQueryForm($kd_bentuk)->where('publikasi_form.tipe_field', '!=', 'multiple')->get();
        }
        // $form_multiple = $this->rawQueryForm($kd_bentuk)->where('publikasi_form.tipe_field', '=', 'multiple')->get();
        // $form_all = $this->rawQueryForm($kd_bentuk)->where('publikasi_form.tipe_field', '!=', 'multiple')->get();

        $list_remove = [];
        $list_main_form = [];
        $list_multiple = [];
        foreach ($form_multiple as $index => $value) {
            $list_multiple[$index] = $value;
            foreach ($form_all as $_index => $_value) {
                if ($_value->id_publikasi_form_induk == $value->id) {
                    $list_remove[$_index] = $_value;
                }
            }
            $sub_form = $this->rawQueryForm($kd_bentuk)->where('publikasi_form.id_publikasi_form_induk', '=', $value->id)->get();
            foreach ($sub_form as $__index => $__values) {
                $list_multiple[$index]->sub_form[$__index] = $__values;
            }
        }
        $colect_list_remove = collect($list_remove)->values();
        foreach ($form_all as $index => $value) {
            $status = false;
            foreach ($colect_list_remove as $_row => $_value) {
                if ($value->name_field == $_value->name_field) {
                    $status = true;
                    break;
                }
            }
            if (!$status) {
                $list_main_form[$index] = $value;
            }
        }
        $colect_list_main_form = collect($list_main_form)->values();
        $collect_list_multiple = collect($list_multiple)->values();
        $form_structure = $colect_list_main_form->merge($collect_list_multiple)->all();
        $filter_array = collect($form_structure)->map(function ($value) use ($param) {
            switch ($param) {
                case 'dokumen':
                    if ($value->name_field == 'dokumen') {
                        $var = $value;
                        return $var;
                    }
                    break;
                case 'keanggotaan':
                    if ($value->name_field == 'keanggotaan') {
                        $var = $value;
                        return $var;
                    }
                    break;
                case 'meta':
                    if ($value->name_field != 'dokumen') {
                        $var = $value;
                        if ($value->name_field != 'keanggotaan') {
                            $var = $value;
                            return $var;
                        }
                    }
                    break;
                default:
                    $var = $value;
                    return $var;
                    break;
            }
        })->reject(function ($value) {
            return empty($value);
        })->values();
        return $filter_array;
        //dd(collect($list_remove)->values());
        // $remove_form=$query->map(function($value, $key) use($query){
        //     $var=null;
        //     if($value->tipe_field=='multiple'){
        //         $form=$value;
        //         $var=$query->map(function($_value) use($form){
        //             if($form->id==$_value->id_publikasi_form_induk){
        //                 $var=$_value;
        //                 return $var;
        //             }
        //         })->reject(function($value){
        //             return empty($value);
        //         })->values();
        //     }
        //     return $var;
        // })->reject(function($value){
        //     return empty($value);
        // })->values();
        // $_remove_form=$remove_form->map(function($value,$index){
        //     $var=$value->map(function($_value,$_index){
        //         return $_value;
        //     });
        //     //dump($var);
        //     return $var;
        // });
        // $form_meta=$query->map(function($value, $key) use($query){
        //     if($value->tipe_field!='multiple'){
        //         $var=$value;
        //     }else{
        //         $var=$value;
        //         $var->sub_form=$query->map(function($_value) use($var){
        //             if($var->id==$_value->id_publikasi_form_induk){
        //                 return $_value;
        //             }
        //         })->reject(function($value){
        //             return empty($value);
        //         })->values();
        //         $remove_form=$var->sub_form;
        //     }
        //     return $var;
        // })->reject(function($value){
        //     return empty($value);
        // })->values();
    }

    // Penanganan Export
    public function rawQueryVersionForm($id_form_versi)
    {
        $query = DB::table('publikasi_form')->
            select('publikasi_form.id', 'publikasi_form.id_publikasi_form_versi', 'id_publikasi_form_induk', 'publikasi_form.name_field', 'publikasi_form.label', 'publikasi_form.tipe_field', 'publikasi_form.id_publikasi_form_induk', 'publikasi_form.order', 'publikasi_form.options')->
            join('publikasi_form_versi', 'publikasi_form_versi.id', '=', 'publikasi_form.id_publikasi_form_versi')->
            where('publikasi_form_versi.id', '=', $id_form_versi)->where('publikasi_form.flag_aktif', true);
        return $query;
    }
    public function getVersionForm($id_form_versi)
    {
        $exclude = [];
        $form_multiple = $this->rawQueryVersionForm($id_form_versi)->where('publikasi_form.tipe_field', '=', 'multiple')->where([['name_field', '!=', 'dokumen'], ['name_field', '!=', 'keanggotaan']])->get()->map(function ($value) use (&$exclude) {
            $multiple_child = DB::table('publikasi_form')->select('publikasi_form.name_field', 'publikasi_form.label', 'publikasi_form.tipe_field')->where('id_publikasi_form_induk', $value->id)->get();
            $value->child = $multiple_child;
            $a = DB::table('publikasi_form')->where('id', $value->id_publikasi_form_induk)->first();
            $wizard = ($a) ? $a->label : '';
            $exclude = array_merge($exclude, [$value->id]);
            $wizard = strtolower(str_replace(' ', '_', $wizard));
            $value->wizard = $wizard;
            return $value;
        });
        $form_all = $this->rawQueryVersionForm($id_form_versi)->where('publikasi_form.tipe_field', '!=', 'multiple')->whereNotIn('id_publikasi_form_induk', $exclude)->get()->map(function ($value) {
            $a = DB::table('publikasi_form')->where('id', $value->id_publikasi_form_induk)->first();
            $wizard = ($a) ? $a->label : '';
            $wizard = strtolower(str_replace(' ', '_', $wizard));
            $value->wizard = $wizard;
            return $value;
        });
        $form = $form_all->merge($form_multiple)->map(function ($value) {
            $value = collect($value)->except(['id_publikasi_form_induk', 'id_publikasi_form_versi', 'id'])->toArray();
            return $value;
        });
        return $form->sortBy('order')->values();
    }

    public function getFormNameByKodeExport($kd_bentuk, $param = null, $id_form_versi = null)
    {
        if (!$param) {
            //SET DEFAULT PARAM
            $param = 'all';
        }
        $form_multiple = $this->rawQueryVersionForm($id_form_versi)->where('publikasi_form.tipe_field', '=', 'multiple')->get();
        $form_all = $this->rawQueryVersionForm($id_form_versi)->where('publikasi_form.tipe_field', '!=', 'multiple')->get();
        $list_remove = [];
        $list_main_form = [];
        $list_multiple = [];
        foreach ($form_multiple as $index => $value) {
            $list_multiple[$index] = $value;
            foreach ($form_all as $_index => $_value) {
                if ($_value->id_publikasi_form_induk == $value->id) {
                    $list_remove[$_index] = $_value;
                }
            }
            $sub_form = $this->rawQueryVersionForm($kd_bentuk)->where('publikasi_form.id_publikasi_form_induk', '=', $value->id)->get();
            foreach ($sub_form as $__index => $__values) {
                $list_multiple[$index]->sub_form[$__index] = $__values;
            }
        }
        $colect_list_remove = collect($list_remove)->values();
        foreach ($form_all as $index => $value) {
            $status = false;
            foreach ($colect_list_remove as $_row => $_value) {
                if ($value->name_field == $_value) {
                    $status = true;
                    break;
                }
            }
            if (!$status) {
                $list_main_form[$index] = $value;
            }
        }
        $colect_list_main_form = collect($list_main_form)->values();
        $collect_list_multiple = collect($list_multiple)->values();
        $form_structure = $colect_list_main_form->merge($collect_list_multiple)->all();
        $filter_array = collect($form_structure)->map(function ($value) use ($param) {
            switch ($param) {
                case 'dokumen':
                    if ($value->name_field == 'dokumen') {
                        $var = $value;
                        return $var;
                    }
                    break;
                case 'keanggotaan':
                    if ($value->name_field == 'keanggotaan') {
                        $var = $value;
                        return $var;
                    }
                    break;
                case 'meta':
                    if ($value->name_field != 'dokumen') {
                        $var = $value;
                        if ($value->name_field != 'keanggotaan') {
                            $var = $value;
                            return $var;
                        }
                    }
                    break;
                default:
                    $var = $value;
                    return $var;
                    break;
            }
        })->reject(function ($value) {
            return empty($value);
        })->values();
        return $filter_array;
    }
}
