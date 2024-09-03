<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;

trait ValidasiTrait
{
    public function validation($request, $rules, $lang = null)
    {
        $messageId = [
            'sitasi_meta.required'=>'Sitasi tahunan wajib diisi',
            'sitasi_meta.*.sitasi_jumlah.required' => 'Jumlah sitasi wajib diisi',
            'sitasi_meta.*.sitasi_tahun.required' => 'Tahun sitasi wajib diisi',
            'sitasi_meta.*.sitasi_status.required' => 'Status sitasi wajib diisi',
            'sitasi_meta.*.gambar_halaman.required' => 'File sitasi wajib diisi',
            'sitasi_meta.*.gambar_halaman.max' => 'File sitasi maksimal 5 mb',
            'sitasi_meta.*.gambar_halaman.mimes' => 'File sitasi harus berformat JPG,JPEG,PNG',
            'uuid_jenis_anggota.required' => 'Jenis anggota wajib diisi',
            'surat_dokumen.*.keterangan.required' => 'Keterangan dokumen wajib diisi',
            'surat_dokumen.*.dokumen.required' => 'Dokumen wajib diisi',
            'array' => ':attribute hanya boleh berupa array',
            'alpha' => ':attribute hanya boleh berupa huruf',
            'alpha_num' => ':attribute hanya boleh berupa huruf atau angka',
            'date_format' => ':attribute format tidak sesuai',
            'in' => ':attribute hanya boleh diisi dengan nilai-nilai berikut: :values',
            'integer' => ':attribute hanya boleh berupa angka',
            'max' => ':attribute tidak boleh lebih dari :max karakter',
            'min' => ':attribute tidak boleh kurang dari :min karakter',
            'numeric' => ':attribute hanya boleh berupa angka',
            'required' => ':attribute wajib diisi',
            'string' => ':attribute hanya boleh berupa huruf',
            'unique' => 'hanya boleh menggunakan :attribute yang belum ada',
        ];
        switch ($lang) {
            case "en":
                $messages = array();
                break;
            default:
                $messages = $messageId;
        }

        $validator = Validator::make($request, $rules, $messages);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $result = $errors;
        } else {
            $result = [];
        }
        return $result;
    }
}
