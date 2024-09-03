<?php

namespace App\Traits;

use App\Models\FormModel as form;
use App\Models\RemunerasiPublikasiModel as publikasi;
use App\Traits\FormByKodeTrait;
use App\Traits\MasterData;
use App\Traits\TaxonomyTrait;
use App\Traits\UmumTrait;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait RemunerasiTrait
{
    use MasterData;
    use TaxonomyTrait;
    use UmumTrait;
    use FormByKodeTrait;
    protected function get_publikasi($uuid, $param = null)
    {
        $raw_sql = Publikasi::select(
            'f.kd_versi AS kd_form_versi',
            'b.id as id_pegawai',
            'b.nama',
            //'b.gelar_depan',
            DB::RAW("ifnull(b.gelar_depan,'') as gelar_depan"),
            DB::RAW("ifnull(b.gelar_belakang,'') as gelar_belakang"),
            //'b.gelar_belakang',
            'b.kd_unit2 AS kd_organisasi',
            'b.kd_pendidikan_terakhir as jenjang',
            'b.nik',
            'b.sinta_id',
            'b.scopus_id',
            'b.googlescholar_id',
            'b.nidn',
            'b.no_hp',
            'b.email',
            'pu1.unit1 AS fakultas',
            'pu2.unit2 AS prodi',
            'h.bentuk_umum AS bentuk_umum_publikasi',
            'h.kd_bentuk_umum AS kd_bentuk_umum_publikasi',
            'h.id AS id_bentuk_umum_publikasi',
            'c.bentuk_publikasi',
            'c.kd_bentuk_publikasi',
            'c.tingkatan_publikasi',
            'c.kelompok AS publikasi_kelompok',
            'd.status',
            'd.kd_status',
            'd.id AS id_status',
            'e.peran AS peran_penulis',
            'e.id AS id_peran',
            'e.kd_peran_umum',
            'publikasi.*',
            'publikasi.value as judul_umum',
            'g.id AS id_jenis_publikasi',
            'g.kd_jenis AS kd_jenis_publikasi',
            'g.nama_jenis AS jenis_publikasi',
            'pi.nama_instansi AS instansi_afiliasi',
        )->leftJoin('pegawai AS b', 'b.id', '=', 'publikasi.id_pegawai')->
            leftJoin('publikasi_bentuk AS c', 'c.id', '=', 'publikasi.id_publikasi_bentuk')->
            leftJoin('publikasi_status AS d', 'd.id', '=', 'publikasi.id_publikasi_status')->
            leftJoin('publikasi_peran AS e', 'e.id', '=', 'publikasi.id_publikasi_peran')->
            leftJoin('publikasi_form_versi AS f', 'f.id', '=', 'publikasi.id_publikasi_form_versi')->
            leftJoin('publikasi_jenis AS g', 'g.id', '=', 'publikasi.id_publikasi_jenis')->
            Join('publikasi_bentuk_umum AS h', 'h.id', '=', 'c.id_bentuk_umum')->
            Join('pegawai_unit1 AS pu1', 'pu1.kd_unit1', '=', 'b.kd_unit1')->
            Join('pegawai_unit2 AS pu2', 'pu2.kd_unit2', '=', 'b.kd_unit2')->
            Join('publikasi_instansi AS pi', 'pi.id', '=', 'publikasi.id_instansi')->
            where('publikasi.uuid', $uuid)->
            where('publikasi.flag_aktif', true)->
            where('c.flag_remunerasi', true)->
            orderBy('publikasi.tgl_update', 'desc');
        if ($param) {
            $raw_sql->where('d.kd_status', $param);
        }
        return $raw_sql;
    }
    public function sinkronRemunerasi($uuid)
    {
        try {
            $q_publikasi = $this->get_publikasi($uuid)->get();
            if (!$uuid) {
                throw new Exception('UUID tidak ditemukan', 400);
            }
            if ($q_publikasi->count() <= 0) {
                throw new Exception('Publikasi belum terverifikasi', 400);
            }
            //------------------------MENANGKAP QUERY STRING--------------------------
            // LIST BENTUK BUKU YANG FORM INPUTANYA KHUSUS
            $list_bentuk_buku_exlusif = [
                'BUK-17', 'BUK-18',
            ];
            $get_publikasi = $q_publikasi->map(function ($value) use ($list_bentuk_buku_exlusif) {
                $res_value          = $value;
                $res_value['email'] = $value->nik . '@uii.ac.id'; //HARDOCE EMAIL DEFAULT
                // $res_value->prodi = Str::of($value->jurusan)->explode(' ')[1];
                $res_value->jurusan = null;

                if (in_array($value->kd_bentuk_publikasi, $list_bentuk_buku_exlusif, true)) {
                    $res_value['kota_penerbit']      = null;
                    $res_value['negara_penerbit']    = null;
                    $res_value['ukuran_buku']        = null;
                    $res_value['id_negara_penerbit'] = null;
                    $res_value['penggunaan_buku']    = array();
                }
                return $res_value;
            })->first();
            // SET PARAMETER ke-3 getFormNameByKode TRUE UNTUK SETTING PUBLIKASI SESUAI VERSI FORM DISIMPAN BUKAN YG TERBARU
            $bentuk_form            = $this->getFormNameByKode($get_publikasi->kd_bentuk_publikasi, 'all', true); //AMBIL FORM BERDASAR BENTUK
            $dokumen_wajib          = [];
            $collect_meta_publikasi = [];

            foreach ($bentuk_form as $index => $row_form) {
                $publikasi_meta = DB::table('publikasi_meta')->where('id_publikasi', $get_publikasi->id)->where('flag_aktif', true)->get();
                $exclude_meta   = ['peran'];
                //HANDLING UNTUK MENGAMBIL KECUALI INFORMATION AND EXCLUDE META
                if (($row_form->name_field != null) && (!in_array($row_form->name_field, $exclude_meta))) {
                    $collect_meta_publikasi[$row_form->name_field] = null;
                }
                $collect_meta_publikasi['flag_internasional'] = 0;
                foreach ($publikasi_meta as $index_meta => $row_meta) {
                    if ($row_form->name_field == $row_meta->key) {
                        // dump($row_form->name_field);
                        switch ($row_form->tipe_field) {
                            case 'multiple':
                                if (strrpos($row_meta->key, 'dokumen') !== false) {
                                    $unserialize_data = unserialize($row_meta->value);
                                    if (count($unserialize_data) != 0) {
                                        foreach ($unserialize_data as $index => $value) {
                                            $get_berkas[$index] = $value;
                                            $dokumen            = ($value['path_file']) ? $this->getFile($value['path_file']) : '';
                                            if (empty($dokumen)) {
                                                $get_berkas[$index]['flag_aktif'] = false;
                                            }
                                            $value['keterangan'] = '';
                                            $removeValue         = ['id', 'uuid'];
                                            foreach ($removeValue as $keyRemove) {
                                                unset($get_berkas[$index][$keyRemove]);
                                            }
                                            if ($dokumen) {
                                                $get_berkas[$index]['url_file'] = $dokumen['plainUrl'];
                                                // $get_berkas[$index]['url_file'] = $dokumen['presignedUrl'];
                                            } else {
                                                $get_berkas[$index]['url_file'] = '';
                                            }
                                            if (isset($value['uuid'])) {
                                                $get_berkas[$index]['uuid_dokumen'] = $value['uuid'];
                                                $get_berkas[$index]['id_publikasi'] = $get_publikasi->id_publikasi;
                                                $get_berkas[$index]['id_dokumen']   = $value['id'];
                                            }
                                        }
                                        $_get_berkas = collect($get_berkas)->map(function ($value) {
                                            if ((isset($value['uuid_keterangan'])) && (!empty($value['uuid_keterangan']))) {
                                                $keterangan = DB::table('publikasi_terms')->
                                                    where('uuid', $value['uuid_keterangan'])->first();
                                                $value['id_keterangan'] = $keterangan->id;
                                                $value['keterangan']    = $keterangan->nama_term;
                                            }
                                            return $value;
                                        });
                                    }
                                    $collect_meta_publikasi[$row_meta->key] = $_get_berkas;
                                } else if (strrpos($row_meta->key, 'keanggotaan') !== false) {
                                    $unserialize_data = unserialize($row_meta->value);
                                    if (count($unserialize_data) != 0) {
                                        foreach ($unserialize_data as $key => $value) {
                                            $gelar                                  = false;
                                            $_keanggotaan[$key]                     = $value;
                                            $_keanggotaan[$key]['uuid_keanggotaan'] = $value['uuid'];
                                            $_keanggotaan[$key]['id_keanggotaan']   = $value['id'];
                                            $_keanggotaan[$key]['gelar_depan']      = null;
                                            $_keanggotaan[$key]['gelar_belakang']   = null;
                                            $_keanggotaan[$key]['flag_aktif']       = true;
                                            $empty_nama                             = false;
                                            foreach ($value as $row => $_value) {
                                                // dd($collect_meta_publikasi);
                                                // $collect_meta_publikasi['flag_internasional']=0;
                                                if (strrpos($row, 'instansi_anggota') !== false) {
                                                    $instansi = $this->intansiPublikasiByKolom('id', $value['id_instansi_anggota'] ?? $_value); //cek dafault value
                                                    // $_keanggotaan[$key][$_row] = $institution->nama_instansi ?? null;
                                                    // $_keanggotaan[$key]['uuid_instansi_anggota'] = $institution->uuid ?? null;
                                                    $_keanggotaan[$key][$row]                  = $instansi->nama_instansi ?? null;
                                                    $_keanggotaan[$key]['id_instansi_anggota'] = $instansi->id ?? null;
                                                } else if (strrpos($row, 'status') !== false) {
                                                    $_keanggotaan[$key][$row]                = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->status_anggota : null;
                                                    $_keanggotaan[$key]['id_status_anggota'] = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->id : null;
                                                    $status_anggota                          = $_keanggotaan[$key][$row];
                                                } else if (strrpos($row, 'peran') !== false) {
                                                    $_keanggotaan[$key][$row]               = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->peran : null;
                                                    $_keanggotaan[$key]['id_peran_anggota'] = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->id : null;
                                                    $_keanggotaan[$key]['kd_peran_umum']    = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->kd_peran_umum : null;
                                                } else if (strrpos($row, 'negara_anggota') !== false) {
                                                    $negara = $this->negaraAnggotaByKolom('id', $row['uuid_negara_anggota'] ?? $_value);
                                                    if ($negara && $negara->kd_negara != 'ID') {
                                                        $collect_meta_publikasi['flag_internasional'] = 1;
                                                    }
                                                    $_keanggotaan[$key][$row]                = !empty($negara) ? $negara->nama_negara : null;
                                                    $_keanggotaan[$key]['id_negara_anggota'] = !empty($negara) ? $negara->id : null;
                                                } else if ((strrpos($row, 'nik') !== false) && !empty($_value)) {
                                                    $pegawai = $this->pegawaibykolom('nik', $_value);
                                                    $gelar   = true;
                                                } else if ((strrpos($row, 'nama') !== false)) {
                                                    if ($_value == null or strtolower($_value == 'null')) {
                                                        $empty_nama = true;
                                                    }
                                                }
                                            }
                                            if ($empty_nama) {
                                                $_keanggotaan[$key]['flag_aktif'] = false;
                                            }
                                            //SET GELAR UNTUK PEGAWAI STATUS BUKAN MAHASISWA
                                            if (($status_anggota) && (strtolower($status_anggota) != 'mahasiswa') && ($gelar)) {
                                                $_keanggotaan[$key]['gelar_depan']    = $pegawai->gelar_depan;
                                                $_keanggotaan[$key]['gelar_belakang'] = $pegawai->gelar_belakang;
                                            }
                                            $removeValue = ['id', 'uuid', 'peran_anggota_lain', 'duplicate'];
                                            // foreach ($removeValue as $keyRemove) {
                                            //     unset($_keanggotaan[$key][$keyRemove]);
                                            // }
                                            $__keanggotaan[] = collect($_keanggotaan[$key])->except($removeValue)->all();
                                        }
                                    }
                                    $collect_meta_publikasi[$row_meta->key] = $__keanggotaan;
                                } else {
                                    $handleMultiple = unserialize($row_meta->value);
                                    foreach ($handleMultiple as $index => $value) {
                                        $get_multiple[$index] = $value;
                                        $removeValue          = [];
                                        foreach ($removeValue as $keyRemove) {
                                            unset($get_multiple[$index][$keyRemove]);
                                        }
                                        if (isset($value['uuid'])) {
                                            $get_multiple[$index]['uuid_topik_video'] = $value['uuid'];
                                        }
                                    }
                                    $collect_meta_publikasi[$row_meta->key] = $get_multiple ?? '';

                                }
                                break;
                            case 'multiple_select':
                                $tabel_spesial    = ['matakuliah']; //TABEL YANG NAMANYA GAK STANDAR
                                $unserialize_data = unserialize($row_meta->value);
                                $result           = form::where('id', $row_form->id)->first();
                                $get_tabel        = Str::of($result->options)->explode('-');
                                $get_data         = collect($unserialize_data)->map(function ($unserialize_value) use ($get_tabel, $tabel_spesial) {
                                    $get_all = $unserialize_value;
                                    if ($get_tabel[0] == 'master') {
                                        $nama_tabel = $get_tabel[1];
                                        if (!in_array($get_tabel[1], $tabel_spesial)) {
                                            $nama_tabel = 'publikasi_' . $get_tabel[1];
                                        }

                                        $id = DB::table($nama_tabel)->where('uuid', $unserialize_value['uuid'])->first();
                                        if (!$id) {
                                            throw new Exception("Multiple Select data is invalid", 400);
                                        }

                                        $get_all['id'] = $id ? $id->id : '';
                                    } else {
                                        $id = DB::table('publikasi_terms')->where('uuid', $unserialize_value['uuid'])->first();
                                        if (!$id) {
                                            throw new Exception("Multiple Select data is invalid", 400);
                                        }

                                        $get_all['id'] = $id ? $id->id : '';
                                    }
                                    return collect($get_all)->except(['option_text_field']);
                                });
                                $collect_meta_publikasi[$row_meta->key] = $get_data;
                                break;
                            case 'multiple_autoselect':
                                $tabel_spesial    = ['matakuliah']; //TABEL YANG NAMANYA GAK STANDAR
                                $unserialize_data = unserialize($row_meta->value);
                                $result           = form::where('id', $row_form->id)->first();
                                $get_tabel        = Str::of($result->options)->explode('-');
                                $get_data         = collect($unserialize_data)->map(function ($unserialize_value) use ($get_tabel, $tabel_spesial) {
                                    $get_all = $unserialize_value;
                                    if ($get_tabel[0] == 'master') {
                                        $nama_tabel = $get_tabel[1];
                                        if (!in_array($get_tabel[1], $tabel_spesial)) {
                                            $nama_tabel = 'publikasi_' . $get_tabel[1];
                                        }

                                        $id = DB::table($nama_tabel)->where('uuid', $unserialize_value['uuid'])->first();
                                        if (!$id) {
                                            throw new Exception("Multiple Select data is invalid", 400);
                                        }

                                        $get_all['id'] = $id ? $id->id : '';
                                    } else {
                                        $id = DB::table('publikasi_terms')->where('uuid', $unserialize_value['uuid'])->first();
                                        if (!$id) {
                                            throw new Exception("Multiple Select data is invalid", 400);
                                        }

                                        $get_all['id'] = $id ? $id->id : '';
                                    }
                                    return collect($get_all)->except(['option_text_field']);
                                });
                                $collect_meta_publikasi[$row_meta->key] = $get_data;
                                break;
                            case 'select':
                                $result = form::where('id', $row_form->id)->first();
                                $tabel  = Str::of($result->options)->explode('-');
                                if ($tabel[0] == 'master') {
                                    $nama_tabel      = 'publikasi_' . $tabel[1];
                                    $term_data_kolom = 'nama_' . $tabel[1];
                                    if (strtolower($tabel[1]) == 'peran') {
                                        $term_data_kolom = 'peran';
                                    }
                                    //HANDLING PERAN META
                                    $term_data = DB::table($nama_tabel)->where('id', $row_meta->value)->first();
                                    if (!$term_data) {
                                        throw new Exception("Select data is invalid", 400);
                                    }

                                } else {
                                    $term_data = DB::table('publikasi_terms')->where('id', $row_meta->value)->first();
                                    if (!$term_data) {
                                        throw new Exception("Select data is invalid", 400);
                                    }

                                    $term_data_kolom = 'nama_term';
                                }
                                $collect_meta_publikasi[$row_meta->key]         = $term_data ? $term_data->$term_data_kolom : '';
                                $collect_meta_publikasi['id_' . $row_meta->key] = $term_data ? $term_data->id : '';
                                // $collect_meta_publikasi[$row_meta->key]=$row_meta->value;
                                break;
                            case 'autoselect':
                                //CARI DATA FORM BERDASARKAN ID
                                $result = form::where('id', $row_form->id)->first();
                                // AMBIL KOLOM OPTION/NAMA TABEL MASTER
                                $tabel = Str::of($result->options)->explode('-');
                                // LIST TABEL KUSUS/PATERN BERBEDA DARI YG LAIN
                                $list_spesial_tabel = ['matakuliah'];
                                if ($tabel[0] == 'master') {
                                    //JIKA TABEL DEPANYA ADA STRING MASTER
                                    if (in_array($tabel[1], $list_spesial_tabel)) {
                                        // JIKA NAMA TABEL MASUK LIST KUSUS
                                        $nama_tabel = $tabel[1];
                                    } else {
                                        // JIKA TIDAK NAMA TABEL KETAMBAHAN STRING "publikasi_"
                                        $nama_tabel = 'publikasi_' . $tabel[1];
                                    }
                                    // AMBIL DATA MASTER SESUAI UUID
                                    $term_data = DB::table($nama_tabel)->where('uuid', $row_meta->value)->first();
                                    // NAMA KOLOM DATA MASTER PATTERNYA "nama_$nama_tabel"
                                    $term_data_kolom = 'nama_' . $tabel[1];
                                    // HANDLING JIKA NAMA TABEL ADALAH PERAN
                                    if (strtolower($tabel[1]) == 'peran') {
                                        $term_data_kolom = 'peran';
                                    }
                                    $term_data = DB::table($nama_tabel)->where('id', $row_meta->value)->first();
                                    if (!$term_data) {
                                        throw new Exception("Select data is invalid", 400);
                                    }
                                } else {
                                    // HANDLING JIKA PAKAI TAXONOMI
                                    $term_data = DB::table('publikasi_terms')->where('id', $row_meta->value)->first();
                                    if (!$term_data) {
                                        throw new Exception("Select data is invalid", 400);
                                    }
                                    $term_data_kolom = 'nama_term';
                                }
                                $collect_meta_publikasi[$row_meta->key]         = $term_data->$term_data_kolom ?? '';
                                $collect_meta_publikasi['id_' . $row_meta->key] = $term_data ? $term_data->id : '';
                                break;
                            case 'file':
                                $pathFile                                                     = DB::table('publikasi_meta')->where('id_publikasi', $get_publikasi->id)->where('key', $row_form->name_field . '_path_file')->where('flag_aktif', true)->first();
                                $file                                                         = ($pathFile->value) ? $this->getFile($pathFile->value) : null;
                                $collect_meta_publikasi[$row_form->name_field]                = $row_meta->value;
                                $collect_meta_publikasi[$row_form->name_field . '_path_file'] = ($file) ? $pathFile->value : '';
                                $collect_meta_publikasi[$row_form->name_field . '_url_file']  = ($file) ? $file['plainUrl'] : '';
                                if ($row_form->flag_required) {
                                    $collect_berkas_wajib['id_keterangan'] = $row_meta->id;
                                    $collect_berkas_wajib['keterangan']    = $row_form->name_field;
                                    $collect_berkas_wajib['id_file']       = $pathFile->id;
                                    $collect_berkas_wajib['path_file']     = !empty($pathFile->value) ? $pathFile->value : '';
                                    $collect_berkas_wajib['url_file']      = !empty($file) ? $file['plainUrl'] : '';
                                    $collect_berkas_wajib['uuid_file']     = $pathFile->uuid;

                                    array_push($dokumen_wajib, $collect_berkas_wajib);
                                }
                                break;
                            default:
                                if (strrpos($row_meta->key, 'judul') !== false) {
                                    $collect_meta_publikasi['judul_umum'] = $row_meta->value;
                                }
                                // $collect_meta_publikasi['email'] = $row_meta->value . '@uii.ac.id';
                                $collect_meta_publikasi[$row_meta->key] = $row_meta->value;
                                break;
                        }
                    }
                }
                $collect_meta_publikasi['berkas_wajib'] = $dokumen_wajib;
            }
            // BENTUK KHUSUS
            $_get_publikasi = collect($get_publikasi)->merge($collect_meta_publikasi)->map(function ($value, $index) {
                if (($index == 'bentuk_umum_publikasi') or ($index == 'publikasi_kelompok')) {
                    $value = Str::ucfirst(Str::lower($value));
                }
                $values = $value;
                return $values;
            });
            $data = [
                'message' => 'Berhasil',
                'status'  => 200,
                'data'    => $_get_publikasi,
            ];
        } catch (Exception $e) {
            Log::error('Trait sinkron :' . $e->getMessage() . ' line :' . $e->getLine() . ', file' . $e->getFile());
            $data = [
                'message' => 'Ambil data publikasi gagal',
                'status'  => 400,
                //'trace' => $e->getMessage(),
            ];
        }
        return $data;
    }
    public function RemunUpdate($uri, $data = [])
    {
        $base_url = "http://" . env('REMUNERASI_DATA_API_URL') . "/private/api/v1/";
        $response = Http::withOptions(
            [
                'base_uri' => $base_url,
            ]
        )->PUT($uri, $data);
        if ($response->successful()) {
            //RESPONSE BERHASIL
            $message = 'Update data remunerasi berhasil';
        } else if ($response->failed()) {
            //RESPONSE 400
            //$message = $response->json();
            $message = 'Terjadi error proses update data remunerasi';
        } else {
            //RESPONSE 500
            $message = 'Server sibuk';
        }
        $responses = [
            'message'  => $message,
            'response' => $response->json(),
            'url'      => $base_url . $uri,
            'status'   => $response->status(),
        ];
        return $responses;
        //dd($response->status());
    }
    public function getDataPublikasiRemunerasi(string $uuid)
    {
        try {
            $uri   = 'publikasi/detail';
            $param = [
                'uuid'   => $uuid,
                'getall' => true,
            ];
            $base_url = "http://" . env('PORTOFOLIO_REMUNERASI_DATA_API_URL') . "/private/api/v1/";
            $response = Http::withOptions(
                [
                    'base_uri' => $base_url,
                ]
            )->GET($uri, $param);
            $data = [
                'message' => 'Berhasil',
                'status'  => $response->status(),
                'data'    => $response->json(),
            ];
        } catch (\Throwable $th) {
            Log::error('Trait sinkron :' . $th->getMessage() . ' line :' . $th->getLine() . ', file' . $th->getFile());
            $data = [
                'message' => 'Ambil data publikasi gagal',
                'status'  => 400,
            ];
        }
        return $data;
    }

}
