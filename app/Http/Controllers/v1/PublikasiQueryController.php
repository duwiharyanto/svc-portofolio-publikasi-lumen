<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\BentukModel as bentuk;
use App\Models\FormModel as form;
use App\Models\PeranModel as peran;
use App\Models\PublikasiModel as publikasi;
use App\Models\VersiFormModel as versiForm;
use App\Traits\FormByKodeTrait;
use App\Traits\MasterData;
use App\Traits\TaxonomyTrait;
use App\Traits\UmumTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use \App\Http\Controllers\FileController;

class PublikasiQueryController extends Controller
{
    use MasterData;
    use TaxonomyTrait;
    use FileController;
    use UmumTrait;
    use FormByKodeTrait;
    protected $enviroment;
    public function __construct()
    {
        $this->enviroment = env('APP_ENV', 'local');
    }

    public function getStatusPublikasi($nik = null, $kd_status = null)
    {
        $nik           = ($nik == 'yudhistira') ? '075230424' : $nik;
        $jumlah_status = DB::table('publikasi as p')->select('p.*', 'ps.uuid as uuid_status')->
            join('publikasi_status as ps', 'ps.id', '=', 'p.id_publikasi_status')->
            join('pegawai as pe', 'pe.id', '=', 'p.id_pegawai')->
            where('p.flag_aktif', true);
        if ($nik) {
            $jumlah_status->where('pe.nik', $nik);
        }

        $status = DB::table('publikasi_status');
        if ($kd_status) {
            $jumlah_status->where('ps.kd_status', $kd_status);
            $status->where('kd_status', $kd_status);
        }
        $datas = [
            'name'             => $status->first()->status,
            'jumlah_publikasi' => $jumlah_status->get()->count(),
            'uuid_status'      => $status->first()->uuid,
        ];
        return $datas;
    }

    public function infoboxPublikasi($nik)
    {
        // $nik              = ($nik == 'yudhistira') ? '075230424' : $nik;
        $status_publikasi = [
            [
                'status' => 'Total',
                'jumlah' => $this->getStatusPublikasi($nik)['jumlah_publikasi'],
                'uuid'   => '',
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'TRB')['name'] ?? 'Diterbitkan',
                'jumlah' => $this->getStatusPublikasi($nik, 'TRB')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'TRB')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'VAL')['name'] ?? 'Divalidasi',
                'jumlah' => $this->getStatusPublikasi($nik, 'VAL')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'VAL')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'DVR')['name'] ?? 'Diverifikasi',
                'jumlah' => $this->getStatusPublikasi($nik, 'DVR')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'DVR')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'DRF')['name'] ?? 'Draf',
                'jumlah' => $this->getStatusPublikasi($nik, 'DRF')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'DRF')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'USL')['name'] ?? 'Usulan',
                'jumlah' => $this->getStatusPublikasi($nik, 'USL')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'USL')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'PRO')['name'] ?? 'Proses',
                'jumlah' => $this->getStatusPublikasi($nik, 'PRO')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'PRO')['uuid_status'],
            ],
            [
                'status' => $this->getStatusPublikasi($nik, 'DTK')['name'] ?? 'Perbaikan',
                'jumlah' => $this->getStatusPublikasi($nik, 'DTK')['jumlah_publikasi'],
                'uuid'   => $this->getStatusPublikasi($nik, 'DTK')['uuid_status'],
            ],
        ];
        $respon = [
            'message' => 'Infobox Publikasi',
            'data'    => $status_publikasi,
        ];
        return response()->json($respon, 200);
    }

    protected function getPublikasi($pegawaiId)
    {
        $raw_sql = Publikasi::select(
            'f.kd_versi AS kd_form_versi',
            'f.index_versi',
            'b.nama',
            'b.gelar_depan',
            'b.gelar_belakang',
            'h.bentuk_umum',
            'h.kd_bentuk_umum',
            'h.uuid AS uuid_bentuk_umum',
            'c.bentuk_publikasi',
            'c.kd_bentuk_publikasi',
            'c.uuid AS uuid_bentuk_publikasi',
            'd.status',
            'd.kd_status',
            'd.proses_selanjutnya',
            'd.uuid AS uuid_status',
            'e.peran',
            'e.uuid AS uuid_peran',
            'publikasi.*',
            'publikasi.value as judul_artikel',
            'g.nama_jenis AS jenis_publikasi',
            'g.kd_jenis AS kd_jenis_publikasi',
            'g.uuid AS uuid_jenis_publikasi',
            'flagPublik.uuid AS uuid_status_publik',
            'pi.nama_instansi AS afiliasi_instansi',
        )->leftJoin('pegawai AS b', 'b.id', '=', 'publikasi.id_pegawai')->
            leftJoin('publikasi_bentuk AS c', 'c.id', '=', 'publikasi.id_publikasi_bentuk')->
            leftJoin('publikasi_bentuk_umum AS h', 'h.id', '=', 'c.id_bentuk_umum')->
            leftJoin('publikasi_status AS d', 'd.id', '=', 'publikasi.id_publikasi_status')->
            leftJoin('publikasi_peran AS e', 'e.id', '=', 'publikasi.id_publikasi_peran')->
            leftJoin('publikasi_form_versi AS f', 'f.id', '=', 'publikasi.id_publikasi_form_versi')->
            leftJoin('publikasi_jenis AS g', 'g.id', '=', 'publikasi.id_publikasi_jenis')->
            leftJoin('publikasi_terms AS flagPublik', 'flagPublik.nama_term', '=', 'publikasi.status_publik')->
            Join('publikasi_instansi AS pi', 'pi.id', '=', 'publikasi.id_instansi')->
            where('publikasi.flag_aktif', true)->
            where('publikasi.id_pegawai', $pegawaiId);
        return $raw_sql->with('RiwayatPerbaikan');
    }

    public function getByNIK(Request $request, $nik)
    {
        //DB::connection()->enableQueryLog();
        $infoError   = "Terdapat kesalahan menampilkan data";
        $infoSuccess = "Berhasil menampilkan data";
        $count       = 0;
        $limit       = 0;
        $offset      = 0;
        $status      = 200;
        try {
            $nik     = ($nik == 'yudhistira') ? '075230424' : $nik;
            $pegawai = $this->pegawaibynik($nik);
            if (!$pegawai) {
                throw new Exception('NIK tidak ditemukan', 400);
            }
            $getPublikasi = $this->getPublikasi($pegawai->id, 'usulan');
            //------------------------MENANGKAP QUERY STRING--------------------------
            if ($request->input('uuid_bentuk_umum')) {
                $bentukumumpublikasi = $this->bentukumumpublikasibykolom('uuid', $request->input('uuid_bentuk_umum'));
                if (!$bentukumumpublikasi) {
                    throw new Exception("Bentuk umum tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('c.id_bentuk_umum', $bentukumumpublikasi->id);
            }
            if ($request->input('uuid_bentuk')) {
                $jenispublikasi = $this->bentukpublikasibykolom('uuid', $request->input('uuid_bentuk'));
                if (!$jenispublikasi) {
                    throw new Exception("Bentuk publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_bentuk', $jenispublikasi->id);
            }
            if ($request->input('uuid_status')) {
                $statuspublikasi = $this->statuspublikasibykolom('uuid', $request->input('uuid_status'));
                if (!$statuspublikasi) {
                    throw new Exception("Status publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_status', $statuspublikasi->id);
            }
            if ($request->input('uuid_jenis')) {
                $jenispublikasi = $this->jenispublikasibykolom('uuid', $request->input('uuid_jenis'));
                if (!$jenispublikasi) {
                    throw new Exception("Jenis publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_jenis', $jenispublikasi->id);
            }
            if ($request->input('tahun')) {
                $tahunpencarian = $request->input('tahun');
                if (!$tahunpencarian) {
                    throw new Exception("Tahun tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->whereYear('publikasi.tahun', $tahunpencarian);
            }
            if ($request->input('cari')) {
                $getPublikasi = $getPublikasi->where('publikasi.value', 'like', '%' . $request->input('cari') . '%');
            }
            $dataPublikasi    = [];
            $result_publikasi = $getPublikasi->orderBy('d.urutan_publikasi', 'asc')->
                orderBy('publikasi.tgl_update', 'desc')->get();
            $count            = collect($result_publikasi)->count();
            $limit            = $count;
            $limit            = ($count < $limit) ? $count : $limit;
            $limit            = ($request->input('limit')) ? (int) $request->input('limit') : $limit;
            $offset           = ($request->input('offset')) ? (int) $request->input('offset') : 0;
            $result_publikasi = collect($result_publikasi)->skip($offset)->take($limit)->values();
            $list_remove      = ['kd_status'];

            //GET JUDUL
            // $_bentukForm = [];
            // $bentukForm = form::select('name_field')->where('name_field', 'like', '%judul%')->get();
            // if ($bentukForm) {
            //     foreach ($bentukForm as $index => $row) {
            //         $_bentukForm[$index] = $row->name_field;
            //     }
            // }
            // $listJudul = $_bentukForm;

            //HANDLING UNTUK TAHUN YANG FORMATNYA 4 DIGIT
            // $_mergePublikasi = $mergePublikasi;
            // $_mergePublikasi = $mergePublikasi->map(function ($value) {
            //     if (strlen($value->tahun) == 4) {
            //         $tahun = $value->tahun;
            //         $value->tahun = $tahun . '-01-01';
            //     }
            //     return $value;
            // });
            foreach ($result_publikasi as $keyPublikasi => $value) {
                $dataPublikasi[$keyPublikasi] = $value;
                $metadata                     = DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('flag_aktif', true)->get();
                $statuskeanggotaan            = false;
                $statusfile                   = false;
                // PROPERTI KE 3, TRUE FALSE
                $bentuk_form = $this->getFormNameByKode($value->kd_bentuk_publikasi, 'all', true, $value->id_publikasi_form_versi);
                foreach ($bentuk_form as $row_form) {
                    foreach ($metadata as $row) {
                        if ($row_form->name_field == str_replace(['id_', 'uuid_', '_currency'], '', $row->key)) {
                            switch ($row_form->tipe_field) {
                                case 'year':
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value ?: '';
                                    }
                                    break;
                                case 'date':
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value ?: '';
                                    }
                                    break;
                                case 'multiple':
                                    if (strrpos($row->key, 'keanggotaan') !== false) {
                                        ///////////////////// PENANGANAN KEANGGOTAAN
                                        $statuskeanggotaan = true;
                                        $keanggotaan       = unserialize($row->value);
                                        $_keanggotaan      = [];
                                        if (count($keanggotaan) != 0) {
                                            foreach ($keanggotaan as $key => $value) {
                                                $_keanggotaan[$key] = $value;
                                                foreach ($value as $_row => $_value) {
                                                    if (strrpos($_row, 'instansi_anggota') !== false) {
                                                        $institution                                 = $this->intansiPublikasiByKolom('id', $value['id_instansi_anggota'] ?? $_value);
                                                        $_keanggotaan[$key][$_row]                   = $institution->nama_instansi ?? null;
                                                        $_keanggotaan[$key]['uuid_instansi_anggota'] = $institution->uuid ?? null;
                                                    } else if (strrpos($_row, 'status') !== false) {
                                                        $_keanggotaan[$key][$_row]                 = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->status_anggota : null;
                                                        $_keanggotaan[$key]['uuid_status_anggota'] = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->uuid : null;
                                                    } else if (strrpos($_row, 'peran') !== false) {
                                                        $_keanggotaan[$key][$_row]                = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->peran : null;
                                                        $_keanggotaan[$key]['uuid_peran_anggota'] = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->uuid : null;
                                                    } else if (strrpos($_row, 'negara') !== false) {
                                                        // dump($_row,$_value);
                                                        $negara                                    = $this->negaraAnggotaByKolom('id', $value['id_negara_anggota'] ?? $_value);
                                                        $_keanggotaan[$key][$_row]                 = !empty($negara) ? $negara->nama_negara : null;
                                                        $_keanggotaan[$key]['uuid_negara_anggota'] = !empty($negara) ? $negara->uuid : null;
                                                    }
                                                }
                                                // dd($_keanggotaan);
                                                if (isset($value['uuid'])) {
                                                    $_keanggotaan[$key]['uuid_keanggotaan'] = $value['uuid'];
                                                }
                                                //$removeValue=['uuid_instansi_anggota','uuid_status_anggota','uuid_peran_anggota','uuid'];
                                                $removeValue = ['id_instansi_anggota', 'id_status_anggota', 'id_peran_anggota', 'id', 'peran_anggota_lain'];
                                                foreach ($removeValue as $keyRemove) {
                                                    unset($_keanggotaan[$key][$keyRemove]);
                                                }
                                            }
                                        }
                                        $column_keanggotaan = $row->key;
                                    } else if (strrpos($row->key, 'dokumen') !== false) {
                                        /////////////////// PENANGANAN DOKUMEN
                                        $statusfile = true;
                                        $rawberkas  = unserialize($row->value);
                                        $get_berkas = [];
                                        if (count($rawberkas) != 0) {
                                            foreach ($rawberkas as $index => $value) {
                                                $dokumen             = ($value['path_file']) ? $this->getFile($value['path_file']) : '';
                                                $get_berkas[$index]  = $value;
                                                $value['keterangan'] = '';
                                                $removeValue         = ['id'];
                                                foreach ($removeValue as $keyRemove) {
                                                    unset($get_berkas[$index][$keyRemove]);
                                                }
                                                if (isset($value['uuid'])) {
                                                    $get_berkas[$index]['uuid_dokumen'] = $value['uuid'];
                                                }
                                                if ($dokumen) {
                                                    $get_berkas[$index]['url_file'] = $dokumen['plainUrl'];
                                                } else {
                                                    $get_berkas[$index]['url_file'] = '';
                                                }
                                            }
                                            $_get_berkas = collect($get_berkas)->map(function ($value) {
                                                if ((isset($value['uuid_keterangan'])) && (!empty($value['uuid_keterangan']))) {
                                                    $keterangan = DB::table('publikasi_terms')->
                                                        where('uuid', $value['uuid_keterangan'])->first();
                                                    $value['keterangan'] = $keterangan ? $keterangan->nama_term : null;
                                                }
                                                // flag publik
                                                if ((isset($value['uuid_flag_publik'])) && (!empty($value['uuid_flag_publik']))) {
                                                    $flag_publik = DB::table('publikasi_terms')->
                                                        where('uuid', $value['uuid_flag_publik'])->first();
                                                    $value['flag_publik'] = $flag_publik ? $flag_publik->nama_term : null;
                                                }
                                                return $value;
                                            });
                                        }
                                        $column_file = $row->key;
                                    } else {
                                        $handleMultiple = unserialize($row->value);
                                        $get_multiple   = [];
                                        foreach ($handleMultiple as $index => $value) {
                                            $get_multiple[$index] = $value;
                                            // $value['keterangan'] = '';
                                            $removeValue = ['id'];
                                            foreach ($removeValue as $keyRemove) {
                                                unset($get_multiple[$index][$keyRemove]);
                                            }
                                            if (isset($value['uuid'])) {
                                                $get_multiple[$index]['uuid_topik_video'] = $value['uuid'];
                                            }
                                        }
                                        $dataPublikasi[$keyPublikasi][$row->key] = $get_multiple ?? '';
                                    }
                                    break;
                                case 'select':
                                    //LIST negara_penerbit
                                    $list_spesial_select = [];
                                    $list_spesial_tabel  = ['matakuliah'];
                                    if (!in_array($row->key, $list_spesial_select)) {
                                        // dump($row->key);
                                        $form  = form::where('id', $row_form->id)->first();
                                        $tabel = Str::of($form->options)->explode('-');
                                        if ($tabel[0] == 'master') {
                                            if (in_array($tabel[1], $list_spesial_tabel)) {
                                                $nama_tabel = $tabel[1];
                                            } else {
                                                $nama_tabel = 'publikasi_' . $tabel[1];
                                            }
                                            $term_data       = DB::table($nama_tabel)->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_' . $tabel[1];
                                        } else {
                                            $term_data       = DB::table('publikasi_terms')->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_term';
                                        }
                                        $val_row_term_data                                 = $term_data->$term_data_kolom ?? '';
                                        $uuid_row_term_data                                = $term_data->uuid ?? '';
                                        $dataPublikasi[$keyPublikasi][$row->key]           = $val_row_term_data;
                                        $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $uuid_row_term_data;
                                    } else {
                                        // dump($row->key);
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                    }
                                    break;
                                case 'autoselect':
                                    //LIST negara_penerbit
                                    $list_spesial_select = [];
                                    $list_spesial_tabel  = ['matakuliah'];
                                    if (!in_array($row->key, $list_spesial_select)) {
                                        // dump($row->key);
                                        $form  = form::where('id', $row_form->id)->first();
                                        $tabel = Str::of($form->options)->explode('-');
                                        if ($tabel[0] == 'master') {
                                            if (in_array($tabel[1], $list_spesial_tabel)) {
                                                $nama_tabel = $tabel[1];
                                            } else {
                                                $nama_tabel = 'publikasi_' . $tabel[1];
                                            }
                                            $term_data       = DB::table($nama_tabel)->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_' . $tabel[1];
                                        } else {
                                            $term_data       = DB::table('publikasi_terms')->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_term';
                                        }
                                        $val_row_term_data                                 = $term_data->$term_data_kolom ?? '';
                                        $uuid_row_term_data                                = $term_data->uuid ?? '';
                                        $dataPublikasi[$keyPublikasi][$row->key]           = $val_row_term_data;
                                        $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $uuid_row_term_data;
                                    } else {
                                        // dump($row->key);
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                    }
                                    break;
                                case 'radio':
                                    $options                                           = explode('-', $row_form->options);
                                    $masterValue                                       = ($options[0] == 'master') ? $this->getMasterDataByID($options[1], $row->value) : $this->getTaxonomyByID($options[1], $row->value);
                                    $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $masterValue['uuid'] ?? '';
                                    $dataPublikasi[$keyPublikasi][$row->key]           = $masterValue['option_text_field'] ?? '';
                                    break;
                                case 'multiple_select':
                                    $value_multiple = [];
                                    if ((isset($row->value)) && (!empty($row->value) || $row->value != '')) {
                                        // CEK PROPERTY BISA SERIALIZE
                                        if (@unserialize($row->value)) {
                                            $value_multiple = unserialize($row->value);
                                        }
                                    }
                                    $dataPublikasi[$keyPublikasi][$row->key] = $value_multiple ?? '';
                                    break;
                                case 'multiple_autoselect':
                                    $value_multiple = [];
                                    if ((isset($row->value)) && (!empty($row->value) || $row->value != '')) {
                                        // CEK PROPERTY BISA SERIALIZE
                                        if (@unserialize($row->value)) {
                                            $value_multiple = @unserialize($row->value);
                                        }
                                    }
                                    $dataPublikasi[$keyPublikasi][$row->key] = $value_multiple ?? '';
                                    break;
                                case 'autocomplete':
                                    //ITERASI TIPE FIELD AUTOCOMPLETE
                                    $field        = "id_$row->key";
                                    $get_id       = DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('key', $field)->where('flag_aktif', true)->first();
                                    $id           = $get_id ? $get_id->value : null;
                                    $atribut_form = form::where('id', $row_form->id)->first(); //AMBIL SRTINGAN FORM
                                    $get_options  = explode('-', $atribut_form->options); //EXPLODE UNTUK AMBIL NAMA TABEL
                                    $tabel        = 'publikasi_' . $get_options[1]; //CONFIG TABEL DITAMBAHKAN STRING PUBLIKASI
                                    // $tabel = $get_options[1];
                                    $datas = DB::table($tabel)->where('id', $id)->first(); //AMBIL DATA BERDASARKAN TABEL DAN ID DARI VALUE AUTOCOMPLETE
                                    switch ($row->key) {
                                        //SET NAMA KOLOM MASING2
                                        case 'instansi':
                                            $kolom = 'nama_instansi';
                                            break;
                                        default:
                                            throw new Exception("Data $row->key tidak ditemukan", 400);
                                            break;
                                    }
                                    //TAMBAHLAN KE DATA GET PUBLIKASI
                                    $dataPublikasi[$keyPublikasi]["uuid_$row->key"] = $datas->uuid ?? '';
                                    $dataPublikasi[$keyPublikasi][$row->key]        = $datas->$kolom ?? '';
                                    break;
                                case 'currency':
                                    //ITERASI TIPE FIELD CURRENCY
                                    if (str_contains($row->key, "id_")) {
                                        $options     = explode('-', $row_form->options);
                                        $master      = $this->getMasterDataByID($options[1], $row->value);
                                        $masterUuid  = str_replace("id_", "uuid_", $row->key);
                                        $masterValue = str_replace("id_", "", $row->key);
                                        $masterKd    = str_replace("id_", "kd_", $row->key);
                                        //TAMBAHLAN KE DATA MASTER PUBLIKASI
                                        $dataPublikasi[$keyPublikasi][$masterUuid]  = $master->uuid ?? null;
                                        $dataPublikasi[$keyPublikasi][$masterValue] = $master->option_text_field ?? null;
                                        $dataPublikasi[$keyPublikasi][$masterKd]    = $master->kd_opsi ?? null;
                                    } else {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                    }
                                    break;
                                case 'file':
                                    $pathFile                                                           = DB::table('publikasi_meta')->where('id_publikasi', $value['id'])->where('key', $row_form->name_field . '_path_file')->where('flag_aktif', true)->first();
                                    $file                                                               = ($pathFile->value) ? $this->getFile($pathFile->value) : null;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field]                = $row->value;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_path_file'] = ($file) ? $pathFile->value : '';
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_url_file']  = ($file) ? $file['presignedUrl'] : '';
                                    break;
                                case 'image':
                                    $pathImage                                                          = DB::table('publikasi_meta')->where('id_publikasi', $value['id'])->where('key', $row_form->name_field . '_path_file')->where('flag_aktif', true)->first();
                                    $image                                                              = (($pathImage) && ($pathImage->value)) ? $this->getFile($pathImage->value) : null;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field]                = $row->value;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_path_file'] = ($image) ? $pathImage->value : '';
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_url_file']  = ($image) ? $image['presignedUrl'] : '';
                                    break;
                                default:
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                        if (strrpos($row->key, 'judul') !== false) {
                                            $dataPublikasi[$keyPublikasi]['judul_artikel'] = $row->value;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                    if ($statusfile) {
                        $dataPublikasi[$keyPublikasi][$column_file] = $_get_berkas;
                    }
                    if ($statuskeanggotaan) {
                        $dataPublikasi[$keyPublikasi][$column_keanggotaan] = $_keanggotaan;
                    }
                }
            }
            $ajuan_keterangan = [];
            //$result_publikasi = $request->input('cari') ? collect($pencarianPublikasi)->values() : $dataPublikasi;
            $result_publikasi = collect($result_publikasi)->map(function ($value) use ($ajuan_keterangan) {
                $values                   = $value;
                $values->ajuan_keterangan = '';
                if ($values->flag_ajuan_remunerasi == 1) {
                    $keterangan = [
                        'kd_label'       => 'REM',
                        'label'          => 'Remunerasi',
                        'flag_ajuan'     => $values->flag_ajuan_remunerasi,
                        'flag_perbaikan' => $values->flag_perbaikan_remunerasi,
                        'message'        => $values->keterangan_ditolak,
                    ];
                    array_push($ajuan_keterangan, $keterangan);
                }

                $values->ajuan_keterangan = $ajuan_keterangan;
                return $values;
            });
            $respon = [
                'count'  => $count,
                'limit'  => $limit,
                'offset' => $offset,
                'data'   => $result_publikasi,
                //'data' => count($queries),
            ];
            Log::info('Get publikasi');
            return response()->json($respon, 200);
        } catch (Exception $e) {
            $messageError = $e->getFile() . ' ' . $e->getMessage() . ', baris: ' . $e->getLine();
            Log::error($messageError);
            $respon = [
                'message' => $infoError,
            ];
            if (env('APP_ENV', 'local') == 'local') {
                $respon['trace'] = $messageError;
            }
            return response()->json($respon, 400);
        }
    }

    public function getFormByKDBentuk(Request $request)
    {
        try {
            $dateNow        = date('Y-m-d');
            $kdBentuk       = $request->input('kd_bentuk_publikasi');
            $uuid_publikasi = $request->input('uuid_publikasi');

            // Get version of form when uuid of publication is requested
            if ($uuid_publikasi) {
                $publikasi = publikasi::where('uuid', $uuid_publikasi)->first();
                $bentuk    = bentuk::with(['formVersion' => function ($query) use ($dateNow, $publikasi) {
                    $query->whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('publikasi_form_versi.id', $publikasi->id_publikasi_form_versi);
                }])->where('kd_bentuk_publikasi', $kdBentuk)->first();
                $data      = $bentuk->toArray();
                $versiForm = versiForm::where('id', $publikasi->id_publikasi_form_versi)->first();
            } else {
                $bentuk = bentuk::with(['formVersion' => function ($query) use ($dateNow) {
                    $query->whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true);
                }])->where('kd_bentuk_publikasi', $kdBentuk)->first();
                $data      = $bentuk->toArray();
                $versiForm = versiForm::where('kd_versi', $data['form_version']['kd_versi'])->first();
            }

            // Get global and spesifict fields of form and merge it
            $dataSets = collect(
                form::whereNull('id_publikasi_form_versi')->where('flag_aktif', true)->get()
            )->merge(
                form::whereNotNull('id_publikasi_form_versi')->where('id_publikasi_form_versi', $versiForm['id'])->where('flag_aktif', true)->get()
            )->values()->map(function ($item) {

                $result = null;
                $option = (!empty($item['options'])) ? explode('-', $item['options']) : [];

                // Handler for type of field select, multiple_select and radio when option type is master
                if ((($item['tipe_field'] == 'select') || ($item['tipe_field'] == 'multiple_select') || ($item['tipe_field'] == 'radio') || ($item['tipe_field'] == 'currency')) && (count($option) != 0) && ($option[0] == 'master')) {

                    $result = $item;

                    if ($option[1] == 'instansi') {
                        $result['children'] = $this->getMasterData($option[1], null, true)->toArray();
                    } else {
                        $result['children'] = $this->getMasterData($option[1], 'ASC')->toArray();
                    }

                    // Handler for type of field select, multiple_select and radio when option type is taxonomy
                } elseif ((($item['tipe_field'] == 'select') || ($item['tipe_field'] == 'multiple_select') || ($item['tipe_field'] == 'radio')) && (count($option) != 0) && ($option[0] == 'taxonomy')) {

                    $result             = $item;
                    $result['children'] = $this->getTermOfTaxonomyDB($option[1])->toArray();

                    // Handler for type of field autoselect and autocomplete when option type is master
                } elseif ((($item['tipe_field'] == 'autoselect') || ($item['tipe_field'] == 'autocomplete') || ($item['tipe_field'] == 'currency')) && (count($option) != 0) && ($option[0] == 'master')) {

                    $result = $item;

                    if ($option[1] == 'instansi') {
                        $result['children'] = $this->getMasterDataLimit($option[1], null, true)->toArray();
                    } else {
                        $result['children'] = $this->getMasterDataLimit($option[1], 'ASC')->toArray();
                    }

                    // Handler for type of field autoselect and autocomplete when option type is master
                } elseif ((($item['tipe_field'] == 'autoselect') || ($item['tipe_field'] == 'autocomplete')) && (count($option) != 0) && ($option[0] == 'taxonomy')) {

                    $result             = $item;
                    $result['children'] = $this->getTermOfTaxonomyLimitDB($option[1])->toArray();

                    // Default handler (text, hidden, mask, mask_full_time, date, year, number, well, panel, and multiple)
                } else {

                    $result = $item;

                }

                return collect($result)->except(['id_publikasi_form_versi', 'uuid'])->all();

            })->sortBy('order')->toArray();

            // Set proccess of cleaning of column and set recursive data of forms
            $children = $this->setFields($dataSets, $versiForm->grid_config);

            // Set recursive data of forms in 'fields' json property
            $data['form_version']['fields'] = $children;

            // Build json response
            $response = ($bentuk->count() !== 0) ? response()->json([
                'data' => $data,
            ], 200) : response()->json([
                'data' => [],
            ], 200);
        } catch (Exception $e) {
            Log::error('Exception on getting form data: ' . $e);
            $response = response()->json([
                'status'         => 'error',
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'server_message' => $e->getMessage(),
                'message'        => 'Error on get Publikasi form! There is an error on the server, please try again later.',
            ], 400);
        }
        return $response;
    }

    public function getFormName($kd_bentuk, $param = null)
    {
        try {
            // if(!$param) throw new Exception("Param tidak ditemukan", 1);
            $form_structure = $this->getFormNameByKode($kd_bentuk, $param);
            return response()->json($form_structure, 200);

        } catch (Exception $e) {
            return response()->json($e->getMessage(), 400);
        }
    }

    public function findMasterData(Request $request, $uuidForm)
    {
        $data     = [];
        $status   = 500;
        $response = [];
        try {
            $name   = $request->input('nama') ?: '';
            $field  = form::where('uuid', $uuidForm)->first();
            $option = explode('-', $field->options);

            $dependencyParents = json_decode($field->dependency_parent);
            if ($dependencyParents) {
                foreach ($dependencyParents as $action) {
                    if ($action->action == 'multilevel') {
                        foreach ($action->options as $optionItem) {
                            $nameFieldX = explode('.', $optionItem->name_field);
                            $uuidParent = $request->input('uuid_' . ($nameFieldX[1] ?? $nameFieldX[0])) ?? null;
                            if ($uuidParent) {
                                $parentField   = form::where('name_field', ($nameFieldX[1] ?? $nameFieldX[0]))->first();
                                $parentOptions = explode('-', $parentField->options);
                                $parent        = ($parentOptions[0] === 'master') ? $this->getMasterDataByUUID($parentOptions[1], $uuidParent) : $this->getTaxonomyByUUID($parentOptions[1], $uuidParent);
                            }
                        }
                    }

                }
            }
            switch ($option[0]) {
                case 'master':
                    $result = $this->getMasterDataSearchKey($option[1], $name, $parent ?? null)->toArray();
                    break;
                case 'taxonomy':
                    $result = $this->getTermOfTaxonomyDBSearchKey($option[1], $name, $parent ?? null)->toArray();
                    break;
                default:
                    $result = [];
                    break;
            }
            $data    = collect($result)->take(25)->all();
            $status  = 200;
            $message = 'Sukses mengambil data master...';
        } catch (Exception $e) {
            Log::error('Exception on getting master data: ' . $e);
            $status  = 400;
            $message = 'Gagal mengambil data master...';
        }
        $response['status']  = $status;
        $response['data']    = $data;
        $response['message'] = $message;
        return response()->json($response, $status);
    }

    public function validasiInstansi()
    {
        $data_demo   = DB::table('pegawai')->where("nama", "like", "%demo%")->get();
        $list_iddemo = $data_demo->map(function ($value) {
            return $value->id;
        })->toArray();
        $data_publikasi = Publikasi::select(
            'publikasi.uuid',
            'pi.id as instansi_ajuan',
            'pm.id as id_publikasi_meta',
            'pi.id_publikasi_ajuan as id_publikasi_ajuan/id_publikasi',
            'pi.nama_instansi',
            'publikasi.value as judul_publikasi',
            'ps.status',
            'publikasi.flag_ajuan_remunerasi',
            'publikasi.tgl_input AS input_publikasi',
            'pm.value as keanggotaan',
            'ps.status',
        )
            ->whereNotIn('publikasi.id_pegawai', $list_iddemo)
            ->join('publikasi_instansi as pi', 'pi.id_publikasi_ajuan', '=', 'publikasi.id')
            ->join('publikasi_status AS ps', 'ps.id', '=', 'publikasi.id_publikasi_status')
            ->join('publikasi_meta as pm', 'pm.id_publikasi', '=', 'publikasi.id')
            ->where('publikasi.flag_aktif', true)
            ->where('ps.kd_status', 'PRO')
            ->where('pi.flag_ajuan', true)
            ->where('pm.flag_aktif', true)
            ->where('pm.key', 'like', '%keanggotaan%')
            ->orderByDesc('publikasi.tgl_input')
            ->get();
        $data_publikasi = $data_publikasi->map(function ($value) {
            $values                            = $value;
            $values['keanggotaan_unserialize'] = unserialize($value->keanggotaan);
            // $values['keanggotaan_serial'] = trim($value->keanggotaan);

            return $values;
        });
        $datas = [
            'info'      => 'Data publikasi diurutkan berdasarkan tgl input',
            'publikasi' => $data_publikasi,
        ];
        return response()->json($datas, 200);
    }

    public function validasiAproveInstansi(Request $request)
    {
        $id_publikasi = $request->input('id_publikasi');
        $id_meta      = $request->input('id_meta');
        // $publikasi=Publikasi::
        // select('pm.value as meta',
        //     'pm.uuid',
        //     'publikasi.id as id_publikasi',
        // )->
        // join('publikasi_meta as pm','pm.id_publikasi','=','publikasi.id')->
        // where('publikasi.id',$id_publikasi)->
        // where('pm.key','like','%keanggotaan%')->
        // where('pm.flag_aktif',true)->
        // first();
        $update_data = [
            'value' => $request->input('keanggotaan'),
        ];
        $publikasi_meta = DB::table('publikasi_meta')->
            where('id_publikasi', $id_publikasi)->
            where('id', $id_meta)->
            where('key', 'keanggotaan')->
            where('flag_aktif', true)->
            update($update_data);
        if ($publikasi_meta) {
            $publikasi_meta = DB::table('publikasi_meta')->
                where('key', 'like', '%keanggotaan%')->
                where('id_publikasi', $id_publikasi)->get();
            $datas = [
                'message' => 'data berhasil update',
                'data'    => $publikasi_meta,
            ];
        } else {
            $datas = [
                'message' => 'data gagal di update',
                'data'    => [],
            ];
        }
        return response()->json($datas, 200);
    }

    // Experimental
    public function getFormAndGridByKDBentuk(Request $request)
    {
        try {
            $dateNow  = date('Y-m-d');
            $kdBentuk = $request->input('kd_bentuk_publikasi');
            $bentuk   = bentuk::with(['formVersion' => function ($query) use ($dateNow) {
                $query->whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true);
            }])->where('kd_bentuk_publikasi', $kdBentuk)->first();

            $data      = $bentuk->toArray();
            $versiForm = versiForm::where('kd_versi', $data['form_version']['kd_versi'])->first();
            $dataSets  = collect(
                form::whereNull('id_publikasi_form_versi')->where('flag_aktif', true)->get()
            )->merge(
                form::whereNotNull('id_publikasi_form_versi')->where('id_publikasi_form_versi', $versiForm['id'])->where('flag_aktif', true)->get()
            )->values()->map(function ($item) use ($data) {
                $result = null;
                $option = (!empty($item['options'])) ? explode('-', $item['options']) : [];
                if (count($option) !== 0 && $option[0] === 'master') {
                    if (($item['tipe_field'] === 'select' || $item['tipe_field'] === 'multiple_select')) {
                        $result             = $item;
                        $result['children'] = $this->getMasterData($option[1])->toArray();
                    } elseif ($item['tipe_field'] === 'autoselect' || $item['tipe_field'] === 'autocomplete') {
                        $result             = $item;
                        $result['children'] = $this->getMasterDataLimit($option[1])->toArray();
                    }
                } elseif (count($option) !== 0 && $option[0] === 'taxonomy') {
                    if (($item['tipe_field'] === 'select' || $item['tipe_field'] === 'multiple_select')) {
                        $result             = $item;
                        $result['children'] = $this->getTermOfTaxonomyDB($option[1])->toArray();
                    } elseif ($item['tipe_field'] === 'autoselect' || $item['tipe_field'] === 'autocomplete') {
                        $result             = $item;
                        $result['children'] = $this->getTermOfTaxonomyLimitDB($option[1])->toArray();
                    }
                } else {
                    $result = $item;
                }
                return collect($result)->except(['id_publikasi_form_versi', 'uuid'])->all();
            })->sortBy('order')->toArray();

            $gridConfig = json_decode($versiForm->grid_config);

            $children = $this->setFieldsAndGrid($dataSets, $gridConfig ?? []);

            $data['form_version']['fields'] = $children;
            $response                       = ($bentuk->count() !== 0) ? response()->json([
                'request_usage_type' => 'experimental',
                'data'               => $data,
            ], 200) : response()->json([
                'request_usage_type' => 'experimental',
                'data'               => [],
            ], 200);
        } catch (Exception $e) {
            Log::error('Exception on getting form data: ' . $e);
            $response = response()->json([
                'request_usage_type' => 'experimental',
                'status'             => 'error',
                'message'            => 'Gagal mengambil data formulir publikasi!',
            ], 400);
        }
        return $response;
    }

    public function getExportByNIK(Request $request, $nik)
    {
        $infoError   = "Terdapat kesalahan menampilkan data";
        $infoSuccess = "Berhasil menampilkan data";
        $count       = 0;
        $limit       = 0;
        $offset      = 0;
        $status      = 200;
        try {
            $nik     = ($nik == 'yudhistira') ? '075230424' : $nik;
            $pegawai = $this->pegawaibynik($nik);
            if (!$pegawai) {
                throw new Exception('NIK tidak ditemukan', 400);
            }
            $getPublikasi = $this->getPublikasi($pegawai->id, 'usulan');
            //------------------------MENANGKAP QUERY STRING--------------------------
            if ($request->input('uuid_bentuk_umum')) {
                $bentukumumpublikasi = $this->bentukumumpublikasibykolom('uuid', $request->input('uuid_bentuk_umum'));
                if (!$bentukumumpublikasi) {
                    throw new Exception("Bentuk umum tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('c.id_bentuk_umum', $bentukumumpublikasi->id);
            }
            if ($request->input('uuid_bentuk')) {
                $jenispublikasi = $this->bentukpublikasibykolom('uuid', $request->input('uuid_bentuk'));
                if (!$jenispublikasi) {
                    throw new Exception("Bentuk publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_bentuk', $jenispublikasi->id);
            }
            if ($request->input('uuid_status')) {
                $statuspublikasi = $this->statuspublikasibykolom('uuid', $request->input('uuid_status'));
                if (!$statuspublikasi) {
                    throw new Exception("Status publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_status', $statuspublikasi->id);
            }
            if ($request->input('uuid_jenis')) {
                $jenispublikasi = $this->jenispublikasibykolom('uuid', $request->input('uuid_jenis'));
                if (!$jenispublikasi) {
                    throw new Exception("Jenis publikasi tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->where('publikasi.id_publikasi_jenis', $jenispublikasi->id);
            }
            if ($request->input('tahun')) {
                $tahunpencarian = $request->input('tahun');
                if (!$tahunpencarian) {
                    throw new Exception("Tahun tidak ditemukan", 400);
                }
                $getPublikasi = $getPublikasi->whereYear('publikasi.tahun', $tahunpencarian);
            }
            if ($request->input('cari')) {
                $getPublikasi = $getPublikasi->where('publikasi.value', 'like', '%' . $request->input('cari') . '%');
            }
            $dataPublikasi    = [];
            $result_publikasi = $getPublikasi->get();
            $count            = collect($result_publikasi)->count();
            $limit            = $count;
            $limit            = ($count < $limit) ? $count : $limit;
            $limit            = ($request->input('limit')) ? (int) $request->input('limit') : $limit;
            $offset           = ($request->input('offset')) ? (int) $request->input('offset') : 0;
            $result_publikasi = collect($result_publikasi)->skip($offset)->take($limit)->values();
            $list_remove      = ['kd_status'];

            foreach ($result_publikasi as $keyPublikasi => $value) {
                $dataPublikasi[$keyPublikasi] = $value;
                $metadata                     = DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('flag_aktif', true)->get();
                $statuskeanggotaan            = false;
                $bentuk_form                  = $this->getFormNameByKodeExport($value->kd_bentuk_publikasi, 'all', $value->id_publikasi_form_versi);
                foreach ($bentuk_form as $row_form) {
                    foreach ($metadata as $row) {
                        if ($row_form->name_field == $row->key) {
                            switch ($row_form->tipe_field) {
                                case 'year':
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value ?: '';
                                    }
                                    break;
                                case 'date':
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value ?: '';
                                    }
                                    break;
                                case 'multiple':
                                    if (strrpos($row->key, 'keanggotaan') !== false) {
                                        ///////////////////// PENANGANAN KEANGGOTAAN
                                        $statuskeanggotaan = true;
                                        $keanggotaan       = unserialize($row->value);
                                        $_keanggotaan      = [];
                                        if (count($keanggotaan) != 0) {
                                            foreach ($keanggotaan as $key => $value) {
                                                $_keanggotaan[$key] = $value;
                                                foreach ($value as $_row => $_value) {
                                                    if (strrpos($_row, 'instansi_anggota') !== false) {
                                                        $institution                                 = $this->intansiPublikasiByKolom('id', $value['id_instansi_anggota'] ?? $_value);
                                                        $_keanggotaan[$key][$_row]                   = $institution->nama_instansi ?? null;
                                                        $_keanggotaan[$key]['uuid_instansi_anggota'] = $institution->uuid ?? null;
                                                    } else if (strrpos($_row, 'status') !== false) {
                                                        $_keanggotaan[$key][$_row]                 = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->status_anggota : null;
                                                        $_keanggotaan[$key]['uuid_status_anggota'] = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->uuid : null;
                                                    } else if (strrpos($_row, 'peran') !== false) {
                                                        $_keanggotaan[$key][$_row]                = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->peran : null;
                                                        $_keanggotaan[$key]['uuid_peran_anggota'] = !empty($_value) ? $this->peranpublikasibykolom('id', $_value)->uuid : null;
                                                    } else if (strrpos($_row, 'negara') !== false) {
                                                        // dump($_row,$_value);
                                                        $negara                                    = $this->negaraAnggotaByKolom('id', $value['id_negara_anggota'] ?? $_value);
                                                        $_keanggotaan[$key][$_row]                 = !empty($negara) ? $negara->nama_negara : null;
                                                        $_keanggotaan[$key]['uuid_negara_anggota'] = !empty($negara) ? $negara->uuid : null;
                                                    }
                                                }
                                                if (isset($value['uuid'])) {
                                                    $_keanggotaan[$key]['uuid_keanggotaan'] = $value['uuid'];
                                                }
                                                $removeValue = ['id_instansi_anggota', 'id_status_anggota', 'id_peran_anggota', 'id', 'peran_anggota_lain'];
                                                foreach ($removeValue as $keyRemove) {
                                                    unset($_keanggotaan[$key][$keyRemove]);
                                                }
                                            }
                                        }
                                        $column_keanggotaan = $row->key;
                                    } else {
                                        $handleMultiple = unserialize($row->value);
                                        foreach ($handleMultiple as $index => $value) {
                                            $get_multiple[$index] = $value;
                                            $removeValue          = ['id'];
                                            foreach ($removeValue as $keyRemove) {
                                                unset($get_multiple[$index][$keyRemove]);
                                            }
                                            if (isset($value['uuid'])) {
                                                $get_multiple[$index]['uuid_topik_video'] = $value['uuid'];
                                            }
                                        }
                                        $dataPublikasi[$keyPublikasi][$row->key] = $get_multiple ?? '';
                                    }
                                    break;
                                case 'select':
                                    //LIST negara_penerbit
                                    $list_spesial_select = [];
                                    $list_spesial_tabel  = ['matakuliah'];
                                    if (!in_array($row->key, $list_spesial_select)) {
                                        $form  = form::where('id', $row_form->id)->first();
                                        $tabel = Str::of($form->options)->explode('-');
                                        if ($tabel[0] == 'master') {
                                            if (in_array($tabel[1], $list_spesial_tabel)) {
                                                $nama_tabel = $tabel[1];
                                            } else {
                                                $nama_tabel = 'publikasi_' . $tabel[1];
                                            }
                                            $term_data       = DB::table($nama_tabel)->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_' . $tabel[1];
                                        } else {
                                            $term_data       = DB::table('publikasi_terms')->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_term';
                                        }
                                        $val_row_term_data                                 = $term_data->$term_data_kolom ?? '';
                                        $uuid_row_term_data                                = $term_data->uuid ?? '';
                                        $dataPublikasi[$keyPublikasi][$row->key]           = $val_row_term_data;
                                        $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $uuid_row_term_data;
                                    } else {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                    }
                                    break;
                                case 'autoselect':
                                    //LIST negara_penerbit
                                    $list_spesial_select = [];
                                    $list_spesial_tabel  = ['matakuliah'];
                                    if (!in_array($row->key, $list_spesial_select)) {
                                        // dump($row->key);
                                        $form  = form::where('id', $row_form->id)->first();
                                        $tabel = Str::of($form->options)->explode('-');
                                        if ($tabel[0] == 'master') {
                                            if (in_array($tabel[1], $list_spesial_tabel)) {
                                                $nama_tabel = $tabel[1];
                                            } else {
                                                $nama_tabel = 'publikasi_' . $tabel[1];
                                            }
                                            $term_data       = DB::table($nama_tabel)->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_' . $tabel[1];
                                        } else {
                                            $term_data       = DB::table('publikasi_terms')->where('id', $row->value)->first();
                                            $term_data_kolom = 'nama_term';
                                        }
                                        $val_row_term_data                                 = $term_data->$term_data_kolom ?? '';
                                        $uuid_row_term_data                                = $term_data->uuid ?? '';
                                        $dataPublikasi[$keyPublikasi][$row->key]           = $val_row_term_data;
                                        $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $uuid_row_term_data;
                                    } else {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                    }
                                    break;
                                case 'radio':
                                    $options                                           = explode('-', $row_form->options);
                                    $masterValue                                       = ($options[0] == 'master') ? $this->getMasterDataByID($options[1], $row->value) : $this->getTaxonomyByID($options[1], $row->value);
                                    $dataPublikasi[$keyPublikasi]['uuid_' . $row->key] = $masterValue['uuid'] ?? '';
                                    $dataPublikasi[$keyPublikasi][$row->key]           = $masterValue['option_text_field'] ?? '';
                                    break;
                                case 'multiple_select':
                                    $value_multiple = '';
                                    if ((isset($row->value)) && (!empty($row->value) || $row->value != '')) {
                                        $value_multiple = unserialize($row->value);
                                    }
                                    $dataPublikasi[$keyPublikasi][$row->key] = $value_multiple ?? '';
                                    break;
                                case 'multiple_autoselect':
                                    $value_multiple = '';
                                    if ((isset($row->value)) && (!empty($row->value) || $row->value != '')) {
                                        $value_multiple = unserialize($row->value);
                                    }
                                    $value_multiple = collect($value_multiple)->map(function ($values) {
                                        $value = Arr::get($values, 'value');
                                        return implode(collect($value)->map(function ($value) {
                                            return trim($value);
                                        })->all());
                                    })->all();
                                    $dataPublikasi[$keyPublikasi][$row->key] = $value_multiple ?? '';
                                    break;
                                case 'autocomplete':
                                    //ITERASI TIPE FIELD AUTOCOMPLETE
                                    $field        = "id_$row->key";
                                    $get_id       = DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('key', $field)->where('flag_aktif', true)->first();
                                    $id           = $get_id ? $get_id->value : null;
                                    $atribut_form = form::where('id', $row_form->id)->first(); //AMBIL SRTINGAN FORM
                                    $get_options  = explode('-', $atribut_form->options); //EXPLODE UNTUK AMBIL NAMA TABEL
                                    $tabel        = 'publikasi_' . $get_options[1]; //CONFIG TABEL DITAMBAHKAN STRING PUBLIKASI
                                    $datas        = DB::table($tabel)->where('id', $id)->first(); //AMBIL DATA BERDASARKAN TABEL DAN ID DARI VALUE AUTOCOMPLETE
                                    switch ($row->key) {
                                        //SET NAMA KOLOM MASING2
                                        case 'instansi':
                                            $kolom = 'nama_instansi';
                                            break;
                                        default:
                                            throw new Exception("Data $row->key tidak ditemukan", 400);
                                            break;
                                    }
                                    //TAMBAHLAN KE DATA GET PUBLIKASI
                                    $dataPublikasi[$keyPublikasi]["uuid_$row->key"] = $datas->uuid ?? '';
                                    $dataPublikasi[$keyPublikasi][$row->key]        = $datas->$kolom ?? '';
                                    break;
                                case 'file':
                                    $pathFile                                                           = DB::table('publikasi_meta')->where('id_publikasi', $value['id'])->where('key', $row_form->name_field . '_path_file')->where('flag_aktif', true)->first();
                                    $file                                                               = ($pathFile->value) ? $this->getFile($pathFile->value) : null;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field]                = $row->value;
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_path_file'] = ($file) ? $pathFile->value : '';
                                    $dataPublikasi[$keyPublikasi][$row_form->name_field . '_url_file']  = ($file) ? $file['presignedUrl'] : '';
                                    break;
                                default:
                                    if (!in_array($row->key, $list_remove)) {
                                        $dataPublikasi[$keyPublikasi][$row->key] = $row->value;
                                        if (strrpos($row->key, 'judul') !== false) {
                                            $dataPublikasi[$keyPublikasi]['judul_artikel'] = $row->value;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                    if ($statuskeanggotaan) {
                        $dataPublikasi[$keyPublikasi][$column_keanggotaan] = $_keanggotaan;
                    }
                }
            }
            $result_publikasi = collect($result_publikasi)->map(function ($value) {
                $item  = ['uuid_bentuk_umum', 'dokumen', 'uuid_bentuk_publikasi', 'uuid_status', 'uuid_peran', 'step_wizard', 'tahun', 'flag_ajuan_remunerasi', 'flag_internasional', 'flag_perbaikan_remunerasi', 'flag_publik', 'uuid_publikasi', 'riwayat_perbaikan', 'uuid_jenis_publikasi', 'uuid_negara_penerbit', 'uuid_bahasa_penulisan', 'uuid_indeksi', 'uuid_status_publik', 'uuid_negara_penyelenggara'];
                $value = collect($value)->except($item);
                return ($value);
            });
            $bentuk_publikasi = DB::table('publikasi_bentuk')->get();
            $versiForm        = versiForm::select('id', 'id_publikasi_bentuk', 'index_versi', 'kd_versi', 'flag_aktif')->get();
            $export_publikasi = [];
            foreach ($bentuk_publikasi as $index => $value) {
                $bentuk_publikasi             = $value->kd_bentuk_publikasi;
                $publikasi_berdasarkan_bentuk = $result_publikasi->where('kd_bentuk_publikasi', $bentuk_publikasi)->values();
                if ($publikasi_berdasarkan_bentuk) {
                    if (count($publikasi_berdasarkan_bentuk) > 0) {
                        $export_publikasi[$index]['nik']                 = $pegawai->nik;
                        $export_publikasi[$index]['sinta_id']            = $pegawai->sinta_id;
                        $export_publikasi[$index]['scopus_id']           = $pegawai->scopus_id;
                        $export_publikasi[$index]['googlescholar_id']    = $pegawai->googlescholar_id;
                        $export_publikasi[$index]['kd_bentuk_publikasi'] = $bentuk_publikasi;
                        $export_publikasi[$index]['bentuk_publikasi']    = $value->bentuk_publikasi;
                        $export_publikasi[$index]['flag_aktif']          = $value->flag_aktif;
                        $bentuk_versi_form                               = $versiForm->where('id_publikasi_bentuk', $value->id);
                        $bentuk_versi                                    = collect($bentuk_versi_form)->map(function ($item) use ($bentuk_publikasi, $publikasi_berdasarkan_bentuk) {
                            $jumlah_data           = count($publikasi_berdasarkan_bentuk->where('index_versi', $item->index_versi));
                            $values['versi_form']  = $item->index_versi;
                            $values['kd_versi']    = $item->kd_versi;
                            $values['flag_aktif']  = $item->flag_aktif;
                            $values['jumlah_data'] = $jumlah_data;
                            $values['field']       = $this->getVersionForm($item->id);
                            return $values;
                        });
                        $export_publikasi[$index]['form'] = $bentuk_versi->values();
                        $export_publikasi[$index]['data'] = collect($publikasi_berdasarkan_bentuk);
                    }
                }
            }
            Log::info('Export publikasi');
            $respon = [
                'message' => $infoSuccess,
                'data'    => collect($export_publikasi)->values(),
            ];
            return response()->json($respon, 200);
        } catch (Exception $e) {
            $messageError = $e->getFile() . ' ' . $e->getMessage() . ', baris: ' . $e->getLine();
            Log::error($messageError);
            $respon = [
                'message' => $infoError,
                'trace'   => $messageError,
            ];
            return response()->json($respon, 400);
        }
    }
}
