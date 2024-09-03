<?php

namespace App\Http\Controllers\v1;

use App\Models\BentukModel as bentuk;
use App\Models\BentukUmumModel as bentukUmum;
use App\Models\FormModel as form;
use App\Models\InstansiPublikasiModel as Instansi;
use App\Models\JenisPublikasiModel as jenisPublikasi;
use App\Models\PegawaiModel as pegawai;
use App\Models\PengaturanModel as pengaturan;
use App\Models\PeranModel as peran;
use App\Models\PublikasiMetaModel as publikasiMeta;
use App\Models\PublikasiModel as publikasi;
use App\Models\StatusModel as status;
use App\Models\TermsModel as terms;
use App\Models\VersiFormModel as versiForm;
use App\Traits\FormByKodeTrait;
use App\Traits\RemunerasiTrait;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use \App\Http\Controllers\Controller;
use \App\Http\Controllers\FileController;
use \App\Traits\MasterData;
use \App\Traits\PublikasiTrait;
use \App\Traits\TaxonomyTrait;
use \App\Traits\UmumTrait;
use \App\Traits\ValidationTrait;

class PublikasiCommandController extends Controller
{

    use FileController, RemunerasiTrait;
    use MasterData;
    use UmumTrait;
    use PublikasiTrait;
    use TaxonomyTrait;
    use FormByKodeTrait;
    use ValidationTrait;

    protected $debug            = true;
    protected $jenis_verifiaksi = 'sistem';
    protected $msg_input        = 'Berhasil menambahakan data';
    protected $msg_update       = 'Berhasil mengubah data';
    protected $msg_delete       = 'Berhasil menghapus data';

    public function __construct()
    {
        // $this->middleware('auth');
        $dependencyChild  = ['keanggotaan']; // keterangan, peran
        $dependencyParent = [ // keanggotaan
            [
                'action'    => 'disable',
                'condition' => 'AND',
                'options'   => [
                    [
                        'name_field' => 'peran',
                        'option'     => [
                            [
                                'value'       => 'fb82e956-cf1d-11eb-8820-000c2977b907',
                                'option_code' => 'KD10',
                                'compare'     => '===',
                            ],
                        ],
                    ],
                ],
            ], [
                'action'    => 'hide',
                'condition' => 'OR',
                'options'   => [
                    [
                        'name_field' => 'peran',
                        'option'     => [
                            [
                                'value'       => 'fb82e956-cf1d-11eb-8820-000c2977b907',
                                'option_code' => 'KD10',
                                'compare'     => '===',
                            ],
                        ],
                    ], [
                        'name_field' => 'dokumen.keterangan',
                        'option'     => [
                            [
                                'value'       => '38fe89b5-72b2-11eb-a56f-000c29d8230c',
                                'option_code' => '',
                                'compare'     => '!==',
                            ],
                        ],
                    ],
                ],
            ], [ // instansi_anggota
                'action'  => 'multilevel', // nest
                'condition' => 'AND',
                'options' => [
                    [
                        'name_field' => 'keanggotaan.negara_anggota',
                        'option'     => [
                            [
                                'value' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ], [ // nama_penulis
                'action'  => 'multilevel', // nest
                'condition' => 'AND',
                'options' => [
                    [
                        'name_field' => 'keanggotaan.instansi_anggota',
                        'option'     => [
                            [
                                'value' => 'uuid',
                            ],
                        ],
                    ], [
                        'name_field' => 'keanggotaan.status_anggota',
                        'option'     => [
                            [
                                'value' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ], [ // Time summary
                'action'    => 'time_sum',
                'condition' => 'AND',
                'options'   => [
                    [
                        'name_field' => 'topik_video.durasi_video',
                        'option'     => [
                            [
                                'value' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ], [ // Unique value
                'action'    => 'unique',
                'condition' => 'AND',
                'options'   => [
                    [
                        'name_field' => 'topik_video.tautan_url_video',
                        'option'     => [
                            [
                                'value' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    public function genID()
    {
        $generate = $this->generateId();
        return response()->json($generate, 500);

    }
    public function testPerbaikanRemunerasi(Request $request)
    {
        $uuidPublikasi = $request->input('uuid');
        // $sinkronremun = $this->getDataPublikasiRemunerasi($uuidPublikasi);
        $sinkronremun = $this->updateRemunerasi($uuidPublikasi);
        dd($sinkronremun);
    }

    public function populate_array_keanggotaan($data_anggota, $value, $nik, $pegawai_attr, $peran_publikasi_master)
    {
        foreach ($data_anggota as $___row => $___value) {
            //MENYUSUN KEANGGOTAAAN ATRIBUT
            foreach ($value as $__value => $__data) {
                if (strrpos($__value, 'nama') !== false) {
                    $__kolom_nama = $__value;
                }
                if (strrpos($__value, 'peran') !== false) {
                    $__kolom_status = $__value;
                }
                if (strrpos($__value, 'nik') !== false) {
                    $__kolom_nik = $__value;
                }
            }
            $publikasi_meta = $data_anggota;
            $pegawai        = $this->pegawaibykolom('nik', $nik);
            $id_uuid        = $this->generateId();
            $filter_meta    = collect($publikasi_meta)->map(function ($value) use ($pegawai_attr, $__kolom_nik, $__kolom_nama, $__kolom_status, $id_uuid, $pegawai, $peran_publikasi_master) {
                //$value['perans'] = DB::table('publikasi_peran')->where('id',$value['peran_anggota'])->first()->peran;
                //MERUBAH SUSUNAN PEGAWAI
                if ($value[$__kolom_nik] == $pegawai_attr->nik && $value[$__kolom_nama] == $pegawai_attr->nama) {
                    $value[$__kolom_nama]   = $pegawai->nama;
                    $value[$__kolom_status] = $peran_publikasi_master;
                    $value[$__kolom_nik]    = $pegawai->nik;
                    //$value['perans']=DB::table('publikasi_peran')->where('id', $peran_publikasi_master)->first()->peran;

                }
                return $value;
            })->all();
        }
        return $filter_meta;
    }
    public function populate_array_berkas($getRequestPublikasi, $key_dokumen, $pegawai_attr, $_dataPublikasiMaster, $nik)
    {
        if (count($getRequestPublikasi[$key_dokumen]) != 0) {
            $publikasi_meta_dokumen = [
                'id_publikasi' => $_dataPublikasiMaster['id'],
                'user_input'   => $nik,
                'key'          => $key_dokumen,
                'value'        => null,
                'flag_aktif'   => true,
                //'repeat' => true,
            ];
            $tampung_array_serialize = [];
            $increment               = 0;
            foreach ($getRequestPublikasi[$key_dokumen] as $req_row => $req_value) {
                $generateIdBerkas        = $this->generateId();
                $_nik                    = $pegawai_attr->nik;
                $array_serialize_dokumen = [
                    'id'         => $generateIdBerkas->id,
                    'uuid'       => $generateIdBerkas->uuid,
                    'berkas'     => null,
                    'flag_aktif' => true,
                    'path_file'  => null,
                ];
                foreach ($req_value as $_req_row => $_req_value) {
                    $generate_id = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                    // $increment = 0; //NAIKIN KE ATAS
                    if (strrpos($_req_row, 'berkas') !== false) {
                        if (isset($_req_value)) {
                            $_dokumen = $_req_value;
                            if (!empty($_req_value)) {
                                //MENGANTI NAMA BERKAS ADA ID PUBLIKASI
                                $id_publikasi = $_dataPublikasiMaster['id'];
                                $pathDokumen  = $this->uploadFile('publikasi', $_nik, "$id_publikasi-$increment", $_dokumen)['path'];
                                //$pathDokumen = $this->uploadFile('publikasi', $_nik, "$generate_id-$increment", $_dokumen)['path'];
                                $namaDokumen                          = $_dokumen->getClientOriginalName();
                                $array_serialize_dokumen[$_req_row]   = $namaDokumen;
                                $array_serialize_dokumen['path_file'] = $pathDokumen;
                                $increment++;
                            }
                        }
                    } else {
                        $array_serialize_dokumen[$_req_row] = $_req_value;
                    }
                }
                $tampung_array_serialize[] = $array_serialize_dokumen;
            }
            $publikasi_meta_dokumen['value'] = serialize($tampung_array_serialize);
        } else {
            $publikasi_meta_dokumen['value'] = serialize([]);
        }
        return $publikasi_meta_dokumen;
    }

    protected $listExtensi = ['pdf'];
    // ! DEPRECEATED
    // public function validasiIsian($form_structure, $getRequestPublikasi)
    // {
    //     $validasi = [
    //         'status' => true,
    //         'empty_form' => [],
    //     ];

    //     foreach ($getRequestPublikasi as $key_request_publikasi => $row_request_publikasi) {
    //         foreach ($form_structure as $row_validasi) {

    //             if ($row_validasi->tipe_field == 'multiple' || $row_validasi->flag_required == true) {
    //                 ////LOOP REQUEST
    //                 $requestFilter = str_replace(['uuid_', 'id_'], '', $key_request_publikasi);
    //                 if ($row_validasi->name_field == $requestFilter) {
    //                     switch ($row_validasi->tipe_field) {
    //                         case 'multiple':
    //                             foreach ($row_validasi->sub_form as $key_sub_form => $row_sub_form) {
    //                                 if ((count($row_request_publikasi) > 1) || $row_sub_form->flag_required == true) {
    //                                     foreach ($row_request_publikasi as $row_req_multiple) {
    //                                         foreach ($row_req_multiple as $_index_req_multiple => $_req_multiple) {
    //                                             if ($row_sub_form->name_field == str_replace('uuid_', '', $_index_req_multiple)) {
    //                                                 if (empty($_req_multiple) or ($_req_multiple == '')) {
    //                                                     array_push($validasi['empty_form'], str_replace('uuid_', '', $_index_req_multiple));
    //                                                 }
    //                                                 if ($row_sub_form->tipe_field == 'mask_full_time' and $_req_multiple == '00:00:00') {
    //                                                     array_push($validasi['empty_form'], str_replace('uuid_', '', $_index_req_multiple));
    //                                                 }
    //                                             }
    //                                         }
    //                                     }
    //                                 }
    //                             }
    //                             break;
    //                         case 'file':
    //                             if (!$row_request_publikasi && $row_request_publikasi == '' && empty($row_request_publikasi)) {
    //                                 array_push($validasi['empty_form'], $row_validasi->name_field);
    //                             } else if (is_file($row_request_publikasi)) {
    //                                 $list = $this->listExtensi;
    //                                 if (!in_array($row_request_publikasi->getClientOriginalExtension(), $list)) {
    //                                     array_push($validasi['empty_form'], $row_validasi->name_field);
    //                                 }
    //                             }
    //                             break;
    //                         case 'multiple_autoselect':
    //                             $list = collect($row_request_publikasi)->map(function ($item) {
    //                                 $item = str_replace(' ', '', $item);
    //                                 return (!empty($item)) ? true : false;
    //                             })->toArray();
    //                             if (in_array(false, $list)) {
    //                                 array_push($validasi['empty_form'], $requestFilter);
    //                             }
    //                             break;
    //                         case 'select':
    //                             $list = collect($row_request_publikasi)->map(function ($item) {
    //                                 $item = str_replace(' ', '', $item);
    //                                 return (!empty($item)) ? true : false;
    //                             })->toArray();
    //                             if (in_array(false, $list)) {
    //                                 array_push($validasi['empty_form'], $requestFilter);
    //                             }
    //                             break;
    //                         case 'currency':
    //                             if (empty($row_request_publikasi) || (array_key_exists("uuid_total_dana_currency", $getRequestPublikasi) && empty($getRequestPublikasi['uuid_total_dana_currency']))) {
    //                                 array_push($validasi['empty_form'], $requestFilter);
    //                             }
    //                             break;
    //                         default:
    //                             if (empty($row_request_publikasi)) {
    //                                 array_push($validasi['empty_form'], $requestFilter);
    //                             }
    //                             break;
    //                     }
    //                 }
    //             }
    //         }
    //     }
    //     $validasi['status'] = count($validasi['empty_form']) == 0;
    //     return $validasi;
    // }
    public function validasiIsian($form_structure, $getRequestPublikasi)
    {
        $validasi = [
            'status'     => true,
            'empty_form' => [],
        ];

        foreach ($getRequestPublikasi as $key_request_publikasi => $row_request_publikasi) {
            foreach ($form_structure as $row_validasi) {
                if ($row_validasi->flag_required == true) {
                    ////LOOP REQUEST
                    $requestFilter = str_replace(['uuid_', 'id_'], '', $key_request_publikasi);
                    if ($row_validasi->name_field === $requestFilter) {
                        switch ($row_validasi->tipe_field) {
                            case 'multiple':
                                foreach ($row_validasi->sub_form as $key_sub_form => $row_sub_form) {
                                    if ($row_sub_form->flag_required == true) {
                                        foreach ($row_request_publikasi as $row_req_multiple) {
                                            foreach ($row_req_multiple as $_index_req_multiple => $_req_multiple) {
                                                if ($row_sub_form->name_field == str_replace('uuid_', '', $_index_req_multiple)) {
                                                    if (empty($_req_multiple) or ($_req_multiple == '')) {
                                                        array_push($validasi['empty_form'], str_replace('uuid_', '', $_index_req_multiple));
                                                    }
                                                    if ($row_sub_form->tipe_field == 'mask_full_time' and $_req_multiple == '00:00:00') {
                                                        array_push($validasi['empty_form'], str_replace('uuid_', '', $_index_req_multiple));
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                break;
                            case 'file':
                                if (!$row_request_publikasi && $row_request_publikasi == '' && empty($row_request_publikasi)) {
                                    array_push($validasi['empty_form'], $row_validasi->name_field);
                                } else if (is_file($row_request_publikasi)) {
                                    $list = $this->listExtensi;
                                    if (!in_array($row_request_publikasi->getClientOriginalExtension(), $list)) {
                                        array_push($validasi['empty_form'], $row_validasi->name_field);
                                    }
                                }
                                break;
                            case 'multiple_autoselect':
                                $list = collect($row_request_publikasi)->map(function ($item) {
                                    $item = str_replace(' ', '', $item);
                                    return (!empty($item)) ? true : false;
                                })->toArray();
                                if (in_array(false, $list)) {
                                    array_push($validasi['empty_form'], $requestFilter);
                                }
                                break;
                            case 'select':
                                $list = collect($row_request_publikasi)->map(function ($item) {
                                    $item = str_replace(' ', '', $item);
                                    return (!empty($item)) ? true : false;
                                })->toArray();
                                if (in_array(false, $list)) {
                                    array_push($validasi['empty_form'], $requestFilter);
                                }
                                break;
                            case 'currency':
                                if (empty($row_request_publikasi) || (array_key_exists("uuid_total_dana_currency", $getRequestPublikasi) && empty($getRequestPublikasi['uuid_total_dana_currency']))) {
                                    array_push($validasi['empty_form'], $requestFilter);
                                }
                                break;
                            default:
                                if (empty($row_request_publikasi)) {
                                    array_push($validasi['empty_form'], $requestFilter);
                                }
                                break;
                        }
                    }
                }
            }
        }
        $validasi['status'] = count($validasi['empty_form']) == 0;
        return $validasi;
    }
    public function validasiAnggota($form_structure, $getRequestPublikasi)
    {
        $val_anggota      = $getRequestPublikasi['keanggotaan'];
        $bentukPublikasi  = bentuk::where('kd_bentuk_publikasi', $getRequestPublikasi['kd_bentuk_publikasi'])->first();
        $bentukUmum       = bentukUmum::where('id', $bentukPublikasi->id_bentuk_umum)->first();
        $validasi_anggota = true;

        $p1 = [];
        $p2 = [];
        $pK = [];
        $pT = [];

        $anggota_master = peran::where('uuid', $getRequestPublikasi['uuid_peran'])->first();
        if (empty($anggota_master)) {
            return $validasi_anggota;
        }

        if (Str::is('*1*', $anggota_master->kd_peran)) {
            $p1[0] = $anggota_master->kd_peran;
        }
        if (Str::is('*2*', $anggota_master->kd_peran)) {
            $p2[0] = $anggota_master->kd_peran;
        }
        if (Str::is('*K*', $anggota_master->kd_peran)) {
            $pK[0] = $anggota_master->kd_peran;
        }
        if (Str::is('*T*', $anggota_master->kd_peran)) {
            $pT[0] = $anggota_master->kd_peran;
        }

        $i = 1;
        foreach ($val_anggota as $index_anggota => $row_anggota) {
            if (($row_anggota['uuid_peran_anggota'] == null) || ($row_anggota['uuid_peran_anggota'] == 'null')) {
                break;
            }
            if (
                ($row_anggota['uuid_peran_anggota'] == null || $row_anggota['uuid_peran_anggota'] == '') ||
                ($row_anggota['uuid_negara_anggota'] == null || $row_anggota['uuid_negara_anggota'] == '') ||
                ($row_anggota['instansi_anggota'] == null || $row_anggota['instansi_anggota'] == '') ||
                ($row_anggota['uuid_status_anggota'] == null || $row_anggota['uuid_status_anggota'] == '') ||
                ($row_anggota['nama_penulis'] == null || $row_anggota['nama_penulis'] == '')
            ) {
                return true;
                break;
            }
            $anggota = peran::where('uuid', $row_anggota['uuid_peran_anggota'])->first();
            if (Str::is('*1*', $anggota->kd_peran)) {
                $p1[$i] = $anggota->kd_peran;
            }
            if (Str::is('*2*', $anggota->kd_peran)) {
                $p2[$i] = $anggota->kd_peran;
            }
            if (Str::is('*K*', $anggota->kd_peran)) {
                $pK[$i] = $anggota->kd_peran;
            }
            if (Str::is('*T*', $anggota->kd_peran)) {
                $pT[$i] = $anggota->kd_peran;
            }

            $i++;
        }

        $count_p1 = count($p1);
        $count_p2 = count($p2);
        $count_pK = count($pK);
        $count_pT = count($pT); // PENCIPTA TUNGGAL
        if ($bentukUmum->kd_bentuk_umum == 'BUK' || $bentukUmum->kd_bentuk_umum == 'BHN') {
            // BUKU
            if (($count_pT == 0) && ($count_p1 >= 1) && ($count_p2 >= 1) && ($count_pK == 0)) {
                $validasi_anggota = false; //HARUS TIDAK ADA KORESPONDENSI
            } else if (($count_pT == 1) && ($count_p1 == 0) && ($count_p2 == 0) && ($count_pK == 0)) {
                $validasi_anggota = false; // HARUS TIDAK ADA ANGGOTANYA
            }
        } else if ($bentukUmum->kd_bentuk_umum == 'KSR' || $bentukUmum->kd_bentuk_umum == 'PUN' || $bentukUmum->kd_bentuk_umum == 'DST') {
            // Reviewer, Pembicara Undangan, Dosen Tamu
            if (($count_pT == 1) && ($count_p1 == 0) && ($count_p2 == 0) && ($count_pK == 0)) {
                $validasi_anggota = false; // HARUS TIDAK ADA ANGGOTANYA
            }
        } else {
            // Sisanya
            if (($count_pT == 0) && ($count_p1 == 1) && ($count_p2 >= 0) && ($count_pK == 1)) {
                $validasi_anggota = false;
            } else if (($count_pT == 1) && ($count_p1 == 0) && ($count_p2 == 0) && ($count_pK == 0)) {
                $validasi_anggota = false; // HARUS TIDAK ADA ANGGOTANYA
            }
        }
        // dd($validasi_anggota);
        return $validasi_anggota;
    }
    public function bersihkanJudul($judul)
    {
        $value     = ['-', '_', ' ', "'"];
        $judulBaru = strtolower(str_replace($value, '', $judul));
        return $judulBaru;
    }
    public function cekJudul(string $nik, string $judulAwal = null, string $idBentukPublikasi, array $idPublikasi = null)
    {
        try {
            // ? Memebuat inisiasi nilai default
            $statusJudul = false;
            // ! Variabel bernilai true untuk validasi yang lebih rigit
            $strict = false;
            // ? Menghilangkan karakter khusus dari judul publikasi
            $judul = $this->bersihkanJudul($judulAwal);
            // ? Ambil data pegawai
            $id_pegawai = DB::table('pegawai')->where('nik', $nik)->where('flag_aktif', true)->first()->id;
            /*
            ? Query data publikasi dari pegawai dengan kriteria
            ? Status publikasi yang diverifikasi dan proses
            ? Bentuk publikasi yang sama
            ? Status publikasi aktif
            ? Memiliki judul publikasi yang mirip
            ? Pengecualaian id publikasi tertentu (opsional)
             */
            // $publikasi = DB::table('publikasi')->select('ps.status', 'pb.bentuk_publikasi', 'publikasi.*')->
            //     join('publikasi_status as ps', 'ps.id', '=', 'publikasi.id_publikasi_status')->
            //     join('publikasi_bentuk as pb', 'pb.id', '=', 'publikasi.id_publikasi_bentuk')->
            //     where('publikasi.flag_aktif', true)->where('publikasi.id_publikasi_bentuk', $idBentukPublikasi)->whereIn('ps.kd_status', ['DVR', 'PRO']);
            $publikasi = DB::table('publikasi')->select('ps.status', 'pb.bentuk_publikasi', 'publikasi.*')->
                join('publikasi_status as ps', 'ps.id', '=', 'publikasi.id_publikasi_status')->
                join('publikasi_bentuk as pb', 'pb.id', '=', 'publikasi.id_publikasi_bentuk')
                ->where('publikasi.flag_aktif', true)
                ->where('publikasi.value', 'like', "%" . $judulAwal . "%")
                ->where('publikasi.id_publikasi_bentuk', $idBentukPublikasi)
                ->whereIn('ps.kd_status', ['DVR', 'PRO']);

            if (!$strict) {
                $publikasi = $publikasi->where('publikasi.id_pegawai', $id_pegawai);
            }
            if ($idPublikasi) {
                $publikasi = $publikasi->whereNotIn('publikasi.id', $idPublikasi);
            }
            $publikasi = $publikasi->get();
            // ! Melakukan pengecekaan judul publikasi yang sudah dibersihakan dari karekter khusus
            $publikasi = collect($publikasi)->map(function ($value) use ($judul, &$statusJudul) {
                $value->judulBaru = $this->bersihkanJudul($value->value);
                if ($value->judulBaru == $judul) {
                    $statusJudul                = true;
                    $values['judul']            = $value->value;
                    $values['status']           = $value->status;
                    $values['bentuk_publikasi'] = $value->bentuk_publikasi;
                    return $values;
                }
            })->reject(function ($value) {
                return empty($value);
            })->values();

            // ? cek publikasi yang judulnya ditemukan kesamaan
            $result = count($publikasi) > 0 ? $publikasi : false;
            $status = 200;
        } catch (QueryException $e) {
            Log::error($e->getMessage() . ', Baris ' . $e->getLine());
            $status = 400;
            $result = 'validasi judul gagal';
        }
        $data = [
            'status' => $status,
            'datas'  => $result,
            'result' => $statusJudul,
        ];
        return $data;
    }
    //TESTING KIRIMAN DATA REMUNERASI
    public function SinkronRemun(Request $request)
    {
        $uuid  = $request->input('uuid');
        $param = "DVR";
        $data  = $this->sinkronRemunerasi($uuid, $param);
        return response()->json($data, 200);
    }
    public function GetPublikasiRemunerasi(Request $request)
    {
        $uuid = $request->input('uuid');
        $data = $this->getDataPublikasiRemunerasi($uuid);
        return response()->json($data, 200);
    }
    public function updateRemunerasi($uuid_publikasi = null)
    {
        $uuid = $uuid_publikasi;
        //LOAD PENGATURAN PORTOFOLIO
        $pengaturanPublikasi = pengaturan::Aktif()->VerifikasiAdmin('PBVA')->first(['kd_pengaturan', 'isi', 'keterangan']);
        $statusPublikasi     = status::where('flag_aktif', true)->get();
        // $param = 'DVR'; // PENCARIAN STATUS DIVERIVIKASI
        // $datas = $this->sinkronRemunerasi($uuid); //AMBIL DATA PORTOFOLIO
        $datas = $this->getDataPublikasiRemunerasi($uuid);
        // LOG KIRIM DATA KE REMUNERASI SVC
        // Log::info('Kirim data remunerasi', $datas['data']);
        Log::info('Kirim data remunerasi', $datas['data']['data']);
        $sinkron  = [];
        $response = [];
        if ($datas['status'] == 200) {
            //JIKA DATA STATUS DIVERIVIKASI
            // $publikasi = collect($datas['data']);
            $publikasi = collect($datas['data']['data']);
            if ($publikasi['flag_ajuan_remunerasi'] == 1 && $publikasi['flag_perbaikan_remunerasi'] == 1) {
                $uri  = 'submission/fixing-portofolio';
                $data = $publikasi->toArray();
                //AKSES PRIVATE API REMUNERASI
                $sinkron = $this->RemunUpdate($uri, $data);
                $kode    = $sinkron['status']; //AMBIL STATUS DARI KEMBALIAN REMUN
                $status  = true;
                Log::info('sinkron remunerasi ' . $kode);
                $response = $sinkron['response'];
                //JIKA 200 UPDATE FLAG PERBAIKAN REMUN KE 0
                if ($kode == 200) {
                    Log::info('Data tersinkron', $data);
                    $data = [
                        'flag_perbaikan_remunerasi' => false,
                        'keterangan_ditolak'        => null,
                    ];
                    //HANDLING PENGATURAN JIKA VALIDASI ADMIN 0 RUBAH STATUS KE DIVERIVIKASI
                    if ($pengaturanPublikasi->isi == '0') {
                        $statusPublikasiBaru = $statusPublikasi->where('kd_status', 'DVR')->first();
                    } else {
                        $statusPublikasiBaru = $statusPublikasi->where('kd_status', 'PRO')->first();
                    }
                    $data['id_publikasi_status'] = $statusPublikasiBaru->id;
                    publikasi::where('uuid', $uuid)->update($data);
                    Log::info('Update flag perbaikan remun uuid ' . $uuid, $data);
                }
            }
        } else {
            $status = false;
            $kode   = 400;
            Log::info('Sinkron remun publikasi belum terverivikasi');
        }
        $data = [
            'status'   => $status,
            'kode'     => $kode,
            'response' => $response,
        ];
        return $data;
    }
    //! SUDAH TIDAK DIGUNAKAN
    public function updateStatusPublikasi(Request $request)
    {
        try {
            $message_update   = $this->msg_update;
            $message_error    = 'update data gagal';
            $uuid             = $request->input('uuid');
            $kd_status        = $request->input('kd_status');
            $status_publikasi = status::where('kd_status', $kd_status)->first();
            $data             = [
                'id_publikasi_status' => $status_publikasi->id,
            ];
            $update_publikasi = publikasi::where('uuid', $uuid)->update($data);
            $param            = 'DVR'; // PENCARIAN STATUS DIVERIVIKASI
            $datas            = $this->updateRemunerasi($uuid);
            Log::info('update status publikasi ' . $uuid);
            $sinkronremun   = $this->updateRemunerasi($uuid);
            $message_update = $this->msg_update;
            if ($sinkronremun['kode'] != 200 && $sinkronremun['status'] == true) {
                $message_error = "Update & Sinkron data gagal";
                Log::error($message_error, [$sinkronremun['response']]);
                return response()->json([
                    'status'  => 400,
                    'message' => 'Update & Sinkron data gagal',
                    'trace'   => $sinkronremun['response'],
                ], 400);
                //throw new Exception($message_error, 500); //JIKA SINKRON GAGAL MAKA UPDATE GAGAL DILAKUKAN
            } else if ($sinkronremun['kode'] == 200) {
                $message_update = 'Berhasil mengubah data dan sinkron remunerasi';
                Log::info('Sinkron remunerasi berhasil');
            }
            $data = [
                'message' => $message_update,
                'data'    => $datas,
            ];
            return response()->json($data, 200);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $data = [
                'info'    => 'error',
                'status'  => 500,
                'trace'   => $e->getLine() . ' Pesan ' . $e->getMessage() . ' file ' . $e->getFile(),
                'message' => 'update data gagal',
            ];
            return response()->json($data, 500);
        }

    }
    //
    public function create(Request $request, $nik)
    {
        set_time_limit(80);
        //DEFINE UUID
        $id             = DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
        $uuid           = Str::uuid()->toString();
        $list_hapus     = [];
        $key_dokumen    = null;
        $tablePublikasi = [
            'uuid_peran',
            'tahun',
            'tgl_publikasi',
            'uuid_status_publik',
        ];
        $listPublikasiMasterKonversiToId = array_diff($tablePublikasi, ['tahun', 'tgl_publikasi']);

        // Load tabel pengaturan
        $pengaturanPublikasi       = pengaturan::Aktif()->VerifikasiAdmin('PBVA')->first(['kd_pengaturan', 'isi', 'keterangan']);
        $generateIdPublikasiMaster = $this->generateId();
        try {
            DB::beginTransaction();
            $nik        = ($nik == 'yudhistira') ? '075230424' : $nik;
            $getPegawai = $this->pegawaibynik($nik);
            if (!$getPegawai) {
                throw new Exception("NIK Pegawai Tidak Ditemukan");
            }
            $step_wizard      = 5;
            $dateNow          = date('Y-m-d');
            $bentuk_publikasi = $this->bentukpublikasibykolom('kd_bentuk_publikasi', $request->input('kd_bentuk_publikasi', null));
            if (!$bentuk_publikasi) {
                throw new Exception("Bentuk publikasi tidak ditemukan", 400);
            }
            $versi_form_aktif = versiForm::whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true)->where('id_publikasi_bentuk', $bentuk_publikasi->id)->first();
            //HARDCODE STATUS PUBLIKASI, DEFAULT DVR
            $statusPublikasi = $request->input('kd_status', 'DRF');
            if ($statusPublikasi == 'DVR') {
                $statusPublikasi = 'DVR';
            }
            //SUSUN ARAY DATA UTAMA PUBLIKASI
            $dataPublikasiMaster = [
                'id'                      => $generateIdPublikasiMaster->id,
                //'uuid'=>$generateIdPublikasiMaster->uuid,
                'id_publikasi_form_versi' => $versi_form_aktif['id'],
                'id_publikasi_bentuk'     => $bentuk_publikasi->id,
                'id_publikasi_status'     => $this->statuspublikasibykolom('kd_status', $statusPublikasi)->id, //STATUS PUBLIKASI DRAF/DIVERIFIKASI
                'id_pegawai'         => $getPegawai->id,
                'flag_aktif'              => true,
                'user_input'              => $nik,
                'user_update'             => $nik,
                'step_wizard'             => $request->input('step_wizard', 5), //STEP WIZARD DEFAULT 5
                'id_publikasi_jenis' => $this->jenispublikasibykolom('kd_jenis', 'INDV')->id, //KOLABORATIF ATAU INDIVIDUAL
                // 'tgl_pengajuan' => date('Y-m-d H:i:s'), //DATETIME
            ];
            $dataPublikasiMeta   = [];
            $all_publikasi_meta  = [];
            $getRequestPublikasi = $request->all();

            //FILTER REQUEST YANG HANYA DI META DAN ADA ISI NYA
            $_get_request_publikasi = collect($getRequestPublikasi)->map(function ($value, $index) use ($tablePublikasi) {
                //PERAN
                if (!in_array($index, $tablePublikasi)) {
                    return $value;
                }
            })->reject(function ($value) {
                return empty($value);
            });
            //JIKA ADA VARIABEL BARU TULIS DISINI, YANG AKAN DI MASUKKAN KE MASTER PUBLIKASI DARI SETINGAN FORM
            foreach ($getRequestPublikasi as $key => $value) {
                if (in_array($key, $tablePublikasi)) {
                    if (in_array($key, $listPublikasiMasterKonversiToId)) {
                        if (empty($value)) {
                            $id = null;
                        } else {

                            $id = $this->konversiToId($key, $value)->id;
                            if (!$id) {
                                throw new Exception("Konversi ID error");
                            }
                        }
                        ///MASUKAN ID PERAN KE TABEL PUBLIKASI MASTER
                        if (strrpos($key, 'peran') !== false) {
                            $dataPublikasiMaster['id_publikasi_peran'] = $id;
                        } else if (strrpos($key, 'uuid_status_publik') !== false) {
                            $flagPublik                           = terms::where('id', $id)->first();
                            $dataPublikasiMaster['flag_publik']   = !is_null($flagPublik) ? (Str::lower($flagPublik->nama_term) === 'ya' ? true : false) : null;
                            $dataPublikasiMaster['status_publik'] = !is_null($flagPublik) ? $flagPublik->nama_term : null;
                        } else {
                            $dataPublikasiMaster[$key] = $id;
                        }
                    }
                }

            }
            //PUBLIKASI META
            $form_structure               = $this->getFormNameByKode($request->input('kd_bentuk_publikasi'), 'all');
            $list_kolom_autocomplete      = []; //FLAG AUTOCOMPLATE NON MULTIPLE
            $insert_multiple_autocomplete = false; //STATUS UNTUK DI KEANGGOTAAN
            $insert_autocomplete          = false; //STATUS UNTUK INFORMASI UMUM
            $instansi_baru                = []; //PROPERTY UNTUK MENYIMPAN ARRAY INSTANSI BARU YANG AKAN DIPAKAI UNTUK TAGGING;
            //$insert_multiple_autocomplete = false;

            foreach ($form_structure as $value_form) {
                foreach ($_get_request_publikasi as $key => $value) {

                    $nameField  = $this->convertKeyUUIDToID($key);
                    $filter_key = Str::replaceFirst('uuid_', '', $key);
                    if ($value_form->name_field == $filter_key) {
                        $PublikasiMeta = [
                            'id_publikasi' => $dataPublikasiMaster['id'],
                            'user_input'   => $nik,
                            'key'          => $filter_key,
                            'value'        => '',
                            'flag_aktif'   => true,
                            //'repeat' => false,
                        ];
                        //HANDLING SET TAHUN & TANGGAL PUBLIKASI
                        $judul_tahun = form::where('id', $value_form->id)->first();
                        if ($judul_tahun->flag_tgl_publikasi == 1) {
                            $_value = $value;
                            if (($judul_tahun->tipe_field == 'year') || (Str::length($value) === 4)) {
                                $_value = $value . '-01-01';
                            }
                            $dataPublikasiMaster['tgl_publikasi'] = $_value;
                            $dataPublikasiMaster['tahun']         = $_value;
                        } else if ($judul_tahun->flag_judul_publikasi == 1) {
                            $dataPublikasiMaster['key'] = $judul_tahun->name_field;
                            // handling untuk flag judul matakuliah
                            if ($value_form->name_field == 'matakuliah') {
                                $matakuliahValue              = DB::table('matakuliah')->where('uuid', $value)->first();
                                $dataPublikasiMaster['value'] = $matakuliahValue->nama_matakuliah;
                            } else {
                                $dataPublikasiMaster['value'] = $value;
                            }
                        }
                        // ! HANDLE META PUBLIKASI
                        switch ($value_form->tipe_field) {
                            case 'multiple':
                                if (strrpos($key, 'keanggotaan') !== false) {
                                    $data_anggota              = [];
                                    $arraySerializeKeanggotaan = [];
                                    if (count($value) != 0) {
                                        //BACA NAMA ANGGOTA ADA ISINYA APA TIDAK
                                        if (!empty($value[0]['nama_penulis'])) {
                                            $dataPublikasiMaster['id_publikasi_jenis'] = $this->jenispublikasibykolom('kd_jenis', 'KOL')->id;
                                        }
                                        //$PublikasiMeta['repeat'] = true;
                                        $generateIdKeanggotaan = $this->generateId();
                                        foreach ($value as $key => $row) {
                                            $instansi                  = [];
                                            $arraySerializeKeanggotaan = [
                                                'id'         => $this->generateId()->id,
                                                'uuid'       => $this->generateId()->uuid,
                                                'flag_aktif' => true,
                                                'duplicate'  => false,
                                            ];
                                            // $arraySerializeKeanggotaan['id'] = $this->generateId()->id;
                                            // $arraySerializeKeanggotaan['uuid'] = $this->generateId()->uuid;
                                            foreach ($row as $_row => $_value) {
                                                // $duplicate = false;
                                                $filter_name_field = str_replace('uuid_', '', $_row);
                                                $sub_form          = $value_form->sub_form;
                                                //HANLING UUID DAN VALUE, AUTOCOMPLATE INSTANSI
                                                if (strrpos($_row, 'instansi') !== false) {
                                                    $filter_key = Str::replaceFirst('uuid_', '', $_row);
                                                    if (Str::is($filter_key, $_row)) {
                                                        $field = collect($sub_form)->map(function ($value) {
                                                            $values = [];
                                                            if (Str::is('*instansi*', $value->name_field)) {
                                                                $values = $value;
                                                                return $values;
                                                            }
                                                        })->reject(function ($value) {
                                                            return empty($value);
                                                        })->values()->toArray();
                                                        $field          = $field[0];
                                                        $uuid_kolom     = "keanggotaan.$key.uuid_$_row"; //UUID
                                                        $value_instansi = $_value;
                                                        $uuid           = $request->$uuid_kolom;
                                                        // SET DEFAULT PROPERTY
                                                        $arraySerializeKeanggotaan[$filter_name_field]         = null;
                                                        $arraySerializeKeanggotaan['id_' . $filter_name_field] = null;
                                                        if (($value_instansi != '') or ($uuid != '')) {
                                                            if ($value_instansi && !$uuid) {
                                                                $value_negara = "keanggotaan.$key.uuid_negara_anggota";
                                                                $negara       = $request->$value_negara;
                                                                $get_negara   = $negara ? $this->negaraAnggotaByKolom('uuid', $negara)->kd_negara : null;
                                                                $generate_id  = $this->generateId();
                                                                $data         = [
                                                                    'id'                 => $generate_id->id,
                                                                    'uuid'               => $generate_id->uuid,
                                                                    'nama_instansi'      => $_value,
                                                                    'id_publikasi_form'  => $field->id, //GET ID FORM YANG MENGAJUKAN
                                                                    'kd_negara' => $get_negara,
                                                                    'flag_ajuan'         => true,
                                                                    'flag_aktif'         => false,
                                                                    'user_update'        => $nik,
                                                                    'user_input'         => $nik,
                                                                    'id_publikasi_ajuan' => $generateIdPublikasiMaster->id,
                                                                ];
                                                                array_push($instansi, $data);
                                                                array_push($instansi_baru, $data); // UNTUK TAGGING
                                                                $data_instansi_baru = Arr::collapse($instansi);
                                                                $insert             = Instansi::create($data_instansi_baru);
                                                                if (!$insert) {
                                                                    throw new Exception("Menambahakan instansi gagal", 400);
                                                                }
                                                                array_push($list_kolom_autocomplete, 'instansi anggota');
                                                                $insert_multiple_autocomplete                          = true;
                                                                $arraySerializeKeanggotaan[$filter_name_field]         = $data['nama_instansi'];
                                                                $arraySerializeKeanggotaan['id_' . $filter_name_field] = $data['id'];
                                                                $getInstansi                                           = !empty($uuid) ? $this->intansiPublikasiByKolom('uuid', $uuid)->kd_instansi : null;
                                                            } else {
                                                                $instansi                                              = Instansi::where('uuid', $uuid)->first();
                                                                $getInstansi                                           = !empty($uuid) ? $this->intansiPublikasiByKolom('uuid', $uuid)->kd_instansi : null;
                                                                $arraySerializeKeanggotaan[$filter_name_field]         = $instansi->nama_instansi;
                                                                $arraySerializeKeanggotaan['id_' . $filter_name_field] = $instansi->id;
                                                            }
                                                        }
                                                    }
                                                } else if (strrpos($_row, 'status') !== false) {
                                                    $arraySerializeKeanggotaan[$filter_name_field] = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('uuid', $_value)->id : null;
                                                    $getStatusAnggota                              = !empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('uuid', $_value)->status_anggota : null;
                                                } else if (strrpos($_row, 'peran') !== false) {
                                                    $arraySerializeKeanggotaan[$filter_name_field] = !empty($_value) ? $this->peranpublikasibykolom('uuid', $_value)->id : null;
                                                } else if (strrpos($_row, 'negara') !== false) {
                                                    $arraySerializeKeanggotaan[$filter_name_field] = !empty($_value) ? $this->negaraAnggotaByKolom('uuid', $_value)->id : null;
                                                } else {
                                                    $arraySerializeKeanggotaan[$_row] = $_value;
                                                    if (strrpos($_row, 'nama') !== false) {
                                                        $nama = $_value; //CEK TRIGER
                                                    }
                                                }
                                            }
                                            ////////////// TRIGER DUPLIKASI \\\\\\\\\\\\\\\\\
                                            if (isset($getInstansi)) {
                                                //CEK APAKAH ADA INSTANSI YANG DI ISIKAN
                                                if ((strtolower($getInstansi) == 'uii') && (strtolower($getStatusAnggota) != 'mahasiswa') && (!empty($nama))) {
                                                    $duplicate                              = true; //STATUS DUPLICATE
                                                    $arraySerializeKeanggotaan['duplicate'] = $duplicate;
                                                }
                                            }
                                            //dd($arraySerializeKeanggotaan);
                                            array_push($data_anggota, $arraySerializeKeanggotaan);
                                            $tampungArraySerializeKeanggotaan[] = $arraySerializeKeanggotaan;
                                        }
                                        $PublikasiMeta['value'] = serialize($tampungArraySerializeKeanggotaan);
                                    } else {
                                        $PublikasiMeta['value'] = serialize([]);
                                    }
                                    array_push($all_publikasi_meta, $PublikasiMeta);
                                } else if (strrpos($key, 'dokumen') !== false) {
                                    $key_dokumen = $key;
                                    array_push($list_hapus, $key);
                                    $cek_file = true;
                                    //$PublikasiMeta['repeat'] = true; //SET REPEAT TRUE
                                    $i                     = 0; //VAR UNTUK INCREMENT JIKA FILE SAMA
                                    $arraySerializeDokumen = [];
                                    if (count($value) != 0) {
                                        foreach ($value as $key => $row) {
                                            $generateIdBerkas      = $this->generateId();
                                            $arraySerializeDokumen = [
                                                'id'         => $generateIdBerkas->id,
                                                'uuid'       => $generateIdBerkas->uuid,
                                                'berkas'     => null,
                                                'flag_aktif' => true,
                                                'path_file'  => null,
                                            ];
                                            foreach ($row as $_row => $_value) {
                                                if (strrpos($_row, 'berkas') !== false) {
                                                    if ($request->hasFile("dokumen.$key.berkas")) {
                                                        $_dokumen = $request->File("dokumen.$key.berkas");
                                                        if (!empty($row['berkas'])) {
                                                            $pathDokumen                        = $this->uploadFile('publikasi', $nik, "$generateIdPublikasiMaster->id-$i", $_dokumen)['path'];
                                                            $namaDokumen                        = $_dokumen->getClientOriginalName();
                                                            $arraySerializeDokumen[$_row]       = $namaDokumen;
                                                            $arraySerializeDokumen['path_file'] = $pathDokumen;
                                                            $cek_file                           = true;
                                                        }
                                                    }
                                                } else {
                                                    $arraySerializeDokumen[$_row] = $_value;
                                                }
                                            }
                                            $tampungArraySerialize[] = $arraySerializeDokumen;
                                            $i++;
                                        }
                                        $PublikasiMeta['value'] = serialize($tampungArraySerialize);
                                    } else {
                                        $PublikasiMeta['value'] = serialize([]);
                                    }
                                    array_push($all_publikasi_meta, $PublikasiMeta);
                                } else {
                                    $dataPublikasi = [
                                        'method'       => $request->method(),
                                        'id_publikasi' => $generateIdPublikasiMaster->id,
                                        'pegawai'      => $getPegawai,
                                        'flag_aktif'   => true,
                                        'user_input'   => $nik,
                                        'user_update'  => $nik,
                                    ];
                                    $children               = json_decode(json_encode($value_form->sub_form), true);
                                    $multipleDatas          = ($value && is_array($value)) ? $this->handleMultiple($dataPublikasi, $value, $value_form->name_field, $children) : null;
                                    $PublikasiMeta['value'] = serialize($multipleDatas);
                                    array_push($all_publikasi_meta, $PublikasiMeta);
                                }
                                break;
                            case 'select':
                                //LIST negara_penerbit
                                $list_spesial_select = [];
                                $list_spesial_tabel  = ['matakuliah']; //SET NAMA TABEL YANG TIDAK IDENTIK
                                if (!in_array($filter_key, $list_spesial_select)) {
                                    $form  = form::where('id', $value_form->id)->first();
                                    $tabel = Str::of($form->options)->explode('-');
                                    if ($tabel[0] == 'master') {
                                        if (in_array($tabel[1], $list_spesial_tabel)) {
                                            $nama_tabel = $tabel[1];
                                        } else {
                                            $nama_tabel = 'publikasi_' . $tabel[1];
                                        }
                                        $term_data = DB::table($nama_tabel)->where('uuid', $value)->first();
                                    } else {
                                        $term_data = DB::table('publikasi_terms')->where('uuid', $value)->first();
                                    }
                                    $PublikasiMeta['value'] = $term_data->id ?? null;
                                } else {
                                    $PublikasiMeta['value'] = $value ?? null;
                                }
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                            case 'radio':
                                $optionMetaData = ($value_form->options) ? explode('-', $value_form->options) : null;
                                $master         = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $value) : $this->getTaxonomyId($optionMetaData[1], $value);
                                $PublikasiMeta  = [
                                    'id_publikasi' => $dataPublikasiMaster['id'],
                                    'key'          => $filter_key,
                                    'value'        => $master ?? null,
                                    'flag_aktif'   => $dataPublikasiMaster['flag_aktif'],
                                    'user_input'   => $dataPublikasiMaster['user_input'],
                                ];
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                            case 'autoselect':
                                //HANDLING AUTOSELCET
                                $list_spesial_select = [];
                                $list_spesial_tabel  = ['matakuliah']; //SET NAMA TABEL YANG TIDAK IDENTIK
                                if (!in_array($filter_key, $list_spesial_select)) {
                                    $form  = form::where('id', $value_form->id)->first();
                                    $tabel = Str::of($form->options)->explode('-');
                                    if ($tabel[0] == 'master') {
                                        if (in_array($tabel[1], $list_spesial_tabel)) {
                                            $nama_tabel = $tabel[1];
                                        } else {
                                            $nama_tabel = 'publikasi_' . $tabel[1];
                                        }
                                        $term_data = DB::table($nama_tabel)->where('uuid', $value)->first();
                                    } else {
                                        $term_data = DB::table('publikasi_terms')->where('uuid', $value)->first();
                                    }
                                    $PublikasiMeta['value'] = $term_data->id;
                                } else {
                                    $PublikasiMeta['value'] = $value;
                                }
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                            case 'multiple_select':
                                $filter_name_field = $filter_key;
                                $spesial_tabel     = ['matakuliah']; //TABEL TANPA EMNBEL2 PUBLIKASI
                                $data              = [];
                                foreach ($value as $index => $row) {
                                    $trimrow = collect($row)->map(function ($value) {
                                        return trim($value);
                                    })->toArray();
                                    $form  = form::where('id', $value_form->id)->first();
                                    $tabel = Str::of($form->options)->explode('-');
                                    if ($tabel[0] == 'master') {
                                        $_tabel = 'publikasi_' . $tabel[1];
                                        if (in_array($tabel[1], $spesial_tabel)) {
                                            $_tabel = $tabel[1];
                                        }
                                        $term_data = (!empty($row)) ? DB::table($_tabel)->where('uuid', $row)->first() : null;
                                        $kolom     = 'nama_' . $tabel[1];
                                    } else {
                                        $term_data = DB::table('publikasi_terms')->where('uuid', $row)->first();
                                        $kolom     = 'nama_term';
                                    }
                                    $data[$index] = [
                                        'uuid'              => implode($trimrow),
                                        'value'             => $term_data->$kolom ?? '',
                                        'option_text_field' => $term_data->$kolom ?? '',
                                    ];
                                }
                                $PublikasiMeta['value'] = serialize($data);
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                            case 'multiple_autoselect':
                                $filter_name_field = $filter_key;
                                $spesial_tabel     = ['matakuliah']; //TABEL TANPA EMNBEL2 PUBLIKASI
                                $data              = [];
                                foreach ($value as $index => $row) {
                                    $trimrow = collect($row)->map(function ($value) {
                                        return trim($value);
                                    })->toArray();
                                    $form  = form::where('id', $value_form->id)->first();
                                    $tabel = Str::of($form->options)->explode('-');
                                    if ($tabel[0] == 'master') {
                                        $_tabel = 'publikasi_' . $tabel[1];
                                        if (in_array($tabel[1], $spesial_tabel)) {
                                            $_tabel = $tabel[1];
                                        }
                                        $term_data = (!empty($row)) ? DB::table($_tabel)->where('uuid', $row)->first() : null;
                                        $kolom     = 'nama_' . $tabel[1];
                                    } else {
                                        $term_data = DB::table('publikasi_terms')->where('uuid', $row)->first();
                                        $kolom     = 'nama_term';
                                    }
                                    $data[$index] = [
                                        'uuid'              => implode($trimrow),
                                        'value'             => $term_data->$kolom ?? '',
                                        'option_text_field' => $term_data->$kolom ?? '',
                                    ];
                                }
                                $PublikasiMeta['value'] = serialize($data);
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                            case 'autocomplete':
                                $filter_key = Str::replaceFirst('uuid_', '', $key);
                                if (Str::is($filter_key, $key)) {
                                    $param_uuid                      = "uuid_$key";
                                    $uuid                            = $request->$param_uuid;
                                    $value_autocomplete              = $value;
                                    $form                            = form::where('id', $value_form->id)->first();
                                    $option                          = Str::of($form->options)->explode('-');
                                    $tabel                           = "publikasi_$option[1]";
                                    $PublikasiMeta_Additional        = $PublikasiMeta;
                                    $PublikasiMeta_Additional['key'] = "id_$key";
                                    $conf_form                       = DB::table('publikasi_form')->where('id', $value_form->id)->first();
                                    if (($uuid !== '') && ($value_autocomplete !== '')) {
                                        if ($value_autocomplete && !$uuid) {
                                            //ARRAY MASTER DATA
                                            $generate_id = $this->generateId();
                                            $datas       = [
                                                'id'                 => $generate_id->id,
                                                'uuid'               => $generate_id->uuid,
                                                'flag_ajuan'         => true,
                                                'flag_aktif'         => false,
                                                'user_update'        => $nik,
                                                'user_input'         => $nik,
                                                'id_publikasi_ajuan' => $generateIdPublikasiMaster->id,
                                                'nama_instansi'      => $value,
                                                'id_publikasi_form'  => $form->id,
                                            ];
                                            $insert = DB::table($tabel)->insert($datas);
                                            if (!$insert) {
                                                throw new Exception("Menambah $conf_form->label gagal", 400);
                                            }
                                            $insert_autocomplete = true;
                                            array_push($list_kolom_autocomplete, $conf_form->label); //ADD NAMA LABEL FORM
                                            $PublikasiMeta_Additional['value'] = $datas['id'];
                                            $PublikasiMeta['value']            = $datas['nama_instansi'];
                                        } else if ($uuid) {
                                            $data                              = DB::table($tabel)->where('uuid', $uuid)->first();
                                            $PublikasiMeta_Additional['value'] = $data->id;
                                            $PublikasiMeta['value']            = $value_autocomplete;
                                        }
                                    }
                                    array_push($all_publikasi_meta, $PublikasiMeta);
                                    array_push($all_publikasi_meta, $PublikasiMeta_Additional);
                                }
                                break;
                            case 'currency':
                                $param_uuid = "uuid_$key" . "_" . $value_form->tipe_field;
                                $uuid_value = $_get_request_publikasi[$param_uuid] ?? null;

                                $PublikasiMeta_Additional        = $PublikasiMeta;
                                $PublikasiMeta_Additional['key'] = "id_$key" . "_" . $value_form->tipe_field;

                                $optionMetaData                    = ($value_form->options) ? explode('-', $value_form->options) : null;
                                $idMaster                          = $this->getMasterDataID($optionMetaData[1], $uuid_value) ?? null;
                                $PublikasiMeta_Additional['value'] = $idMaster ?? null;
                                $PublikasiMeta['value']            = $value;

                                array_push($all_publikasi_meta, $PublikasiMeta);
                                array_push($all_publikasi_meta, $PublikasiMeta_Additional);
                                break;
                            case 'image':
                                $image             = $request->hasFile($nameField) ? $request->file($nameField) : null;
                                $imageName         = ($image) ? $image->getClientOriginalName() : null;
                                $imagePath         = ($image) ? $this->uploadFile('publikasi', $nik, $generateIdPublikasiMaster->id . '_' . $nameField, $image)['path'] : null;
                                $publikasiMetaFile = [
                                    'id_publikasi' => $dataPublikasiMaster['id'],
                                    'key'          => $nameField,
                                    'value'        => $imageName ?: null,
                                    'flag_aktif'   => $dataPublikasiMaster['flag_aktif'],
                                    'user_input'   => $dataPublikasiMaster['user_input'],
                                ];
                                $publikasiPathFile = [
                                    'id_publikasi' => $dataPublikasiMaster['id'],
                                    'key'          => $nameField . '_path_file',
                                    'value'        => $imagePath ?: null,
                                    'flag_aktif'   => $dataPublikasiMaster['flag_aktif'],
                                    'user_input'   => $dataPublikasiMaster['user_input'],
                                ];
                                array_push($all_publikasi_meta, $publikasiMetaFile);
                                array_push($all_publikasi_meta, $publikasiPathFile);
                                break;
                            case 'file':
                                $file              = $request->hasFile($nameField) ? $request->file($nameField) : null;
                                $fileName          = ($file) ? $file->getClientOriginalName() : null;
                                $filePath          = ($file) ? $this->uploadFile('publikasi', $nik, $generateIdPublikasiMaster->id . '_' . $nameField, $file)['path'] : null;
                                $publikasiMetaFile = [
                                    'id_publikasi' => $dataPublikasiMaster['id'],
                                    'key'          => $nameField,
                                    'value'        => $fileName ?: null,
                                    'flag_aktif'   => $dataPublikasiMaster['flag_aktif'],
                                    'user_input'   => $dataPublikasiMaster['user_input'],
                                ];
                                $publikasiPathFile = [
                                    'id_publikasi' => $dataPublikasiMaster['id'],
                                    'key'          => $nameField . '_path_file',
                                    'value'        => $filePath ?: null,
                                    'flag_aktif'   => $dataPublikasiMaster['flag_aktif'],
                                    'user_input'   => $dataPublikasiMaster['user_input'],
                                ];
                                array_push($all_publikasi_meta, $publikasiMetaFile);
                                array_push($all_publikasi_meta, $publikasiPathFile);
                                break;
                            default:
                                $PublikasiMeta['value'] = $value;
                                array_push($all_publikasi_meta, $PublikasiMeta);
                                break;
                        }
                    }
                }
            }

            $text       = isset($dataPublikasiMaster['value']) ? $dataPublikasiMaster['value'] : '';
            $id_pegawai = $getPegawai->id;
            $publikasi  = DB::table('publikasi')->select('publikasi.id', 'pegawai.nama', 'publikasi.uuid', 'publikasi.value', 'publikasi_status.status', 'publikasi_bentuk.bentuk_publikasi', )
                ->join('publikasi_status', 'publikasi_status.id', '=', 'publikasi.id_publikasi_status')
                ->join('pegawai', 'pegawai.id', '=', 'publikasi.id_pegawai')
                ->join('publikasi_bentuk', 'publikasi_bentuk.id', '=', 'publikasi.id_publikasi_bentuk')
                ->where('publikasi.id_pegawai', $id_pegawai)
                ->where('publikasi.id_publikasi_bentuk', $bentuk_publikasi->id)
                ->where('publikasi.flag_aktif', true)->get();
            $similarity   = $this->checkSimilarity($text, $publikasi);
            $countSimilar = collect($similarity)->count();
            /// VALIDASI
            // Akhirnya ketemuu validasine neng kene
            if ($request->input('kd_status') == 'TRB') {
                // Validasi(isian, peran)
                // konfirmasi()
                $errorValidationMessages = [
                    'messages'   => [],
                    'error_type' => 'validation',
                ];
                $errorConfirmationMessages = [
                    'messages'   => [],
                    'error_type' => 'confirmation',
                ];
                $judul = isset($dataPublikasiMaster['value']) ? $dataPublikasiMaster['value'] : ' ';
                // ! Validasi kesamaan judul yang disimpan
                $cekJudul = $this->cekJudul($getPegawai->nik, $judul, $dataPublikasiMaster['id_publikasi_bentuk']);
                if ($cekJudul['result'] != false) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'judul',
                        'message' => ucfirst(str_replace('_', ' ', $dataPublikasiMaster['key'])) . " sudah tersimpan sebelumnya",
                        'data'    => $cekJudul['datas'],
                    ]);
                }
                $_validasi       = $this->validasiIsian($form_structure, $getRequestPublikasi);
                $cekStatusPublik = $dataPublikasiMaster['status_publik'] ?? false; //HARDCODE VALIDASI STATUS PUBLIK
                if (!$_validasi['status'] or !$cekStatusPublik) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'isian',
                        'message' => 'Pengisian data tidak lengkap, pastikan semua yang wajib sudah terisi',
                        'data'    => $_validasi['empty_form'],
                    ]);
                }
                $_validasi_anggota = $this->validasiAnggota($form_structure, $getRequestPublikasi);
                if ($_validasi_anggota) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'peran',
                        'message' => 'Pemilihan peran keanggotaan tidak tepat atau pengisian data anggota tidak lengkap',
                        'data'    => [],
                    ]);
                }

                if ($insert_multiple_autocomplete || $insert_autocomplete) {
                    //JIKA SALAHSATU ADA USULAN AUTOCOMPLETE
                    array_push($errorConfirmationMessages['messages'], [
                        'info'    => 'instansi',
                        'message' => 'Anda menambahkan instansi baru sehingga perlu pengecekan oleh admin',
                        'data'    => [],
                    ]);
                }

                if ($countSimilar > 0) {
                    //JIKA SALAHSATU ADA USULAN AUTOCOMPLETE
                    array_push($errorConfirmationMessages['messages'], [
                        'info'    => 'similarity',
                        'message' => 'Judul karya anda memiliki kemiripan dengan karya lain, yaitu:',
                        'data'    => $similarity,
                    ]);
                }

                // ==========================
                $kdStatuss = 'PRO';
                // pengaturan diberi isi 0 kd status DVR
                if ($pengaturanPublikasi->isi == '0') {
                    $kdStatuss = 'DVR';
                }
                if (count($errorValidationMessages['messages']) > 0) {
                    return response()->json([
                        'status'     => 400,
                        'error_type' => $errorValidationMessages['error_type'],
                        'messages'   => $errorValidationMessages['messages'],
                    ], 400);
                }

                if (count($errorConfirmationMessages['messages']) > 0) {
                    $cekInstansi = collect($errorConfirmationMessages['messages'])->filter(function ($value, $key) {
                        return $value['info'] == 'instansi';
                    })->count();
                    if ($cekInstansi > 0 || !$request->has('user_allowed')) {
                        //REDIRECT STATUS
                        $kdStatuss = 'PRO';
                        return response()->json([
                            'status'     => 400,
                            'error_type' => $errorConfirmationMessages['error_type'],
                            'messages'   => $errorConfirmationMessages['messages'],
                        ], 400);
                    }
                }
                //HARDCODE STATUS PUBLIKASI KE DIVERIKASI YANG SEBELUMNYA DITERBITKAN
                //JIKA ADA INSTANSI BARU PAKAI STATUS UPDATE
                $dataPublikasiMaster['id_publikasi_status'] = $this->statuspublikasibykolom('kd_status', $kdStatuss)->id;
            }
            //////////////////// CLONE////////////////////////
            // TAGGING HANYA UNTUK STATUS YANG DIVERIVIKASI ATAU TERBIT
            $status_kd = ['TRB', 'DVR', 'PRO'];
            if (in_array($request->input('kd_status'), $status_kd)) {
                foreach ($data_anggota as $row => $value) {
                    if ($value['duplicate']) {
                        //MERUBAH PERAN DAN NAMA PEGAWAI UNTUK DATA TAGGING(UPDATE DATA MASTER PUBLIKASI)
                        $_dataPublikasiMaster = $dataPublikasiMaster;
                        foreach ($value as $_value => $_data) {
                            if (strrpos($_value, 'nik') !== false) {
                                $pegawai_attr = $this->pegawaibykolom('nik', $_data) ?: null;
                            }
                            if (strrpos($_value, 'peran') !== false) {
                                $peran_attr = $this->peranpublikasibykolom('id', $_data) ?: null;
                            }
                        }
                        if ($pegawai_attr) {
                            $peran_publikasi_master                      = $_dataPublikasiMaster['id_publikasi_peran'];
                            $_dataPublikasiMaster['id']                  = $this->generateId()->id;
                            $_dataPublikasiMaster['id_pegawai']          = $pegawai_attr ? $pegawai_attr->id : null;
                            $_dataPublikasiMaster['id_publikasi_status'] = $this->statuspublikasibykolom('kd_status', 'USL')->id;
                            $_dataPublikasiMaster['id_publikasi_peran']  = $peran_attr ? $peran_attr->id : null;
                            $id_publikasi                                = $_dataPublikasiMaster['id'];
                            ////////////////////////// META //////////////////////////////////////
                            $_all_publikasi_meta = collect($all_publikasi_meta)->map(function ($value, $key) use ($list_hapus, $id_publikasi) {
                                //*REMOVE KEANGGOTAAN DAN BERKAS
                                if (!in_array($value['key'], $list_hapus)) {
                                    $value['id_publikasi'] = $id_publikasi;
                                    return $value;
                                }
                            })->reject(function ($value) {
                                return empty($value);
                            })->values()->all();
                            $_filter_meta = $this->populate_array_keanggotaan($data_anggota, $value, $nik, $pegawai_attr, $peran_publikasi_master);
                            // RESTRUKTUR INSTANSI
                            //dump($instansi_baru);
                            $dt_anggota = $_filter_meta;
                            if (count($instansi_baru) != 0) {
                                $id_publikasi_meta = $_dataPublikasiMaster['id'];
                                $dt_instansi       = [];
                                //RESTRUKTUR DATA INSTANSI TAGGING
                                foreach ($instansi_baru as $index => $row_instansi) {
                                    $id                                        = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                                    $dt_instansi[$index]                       = $row_instansi;
                                    $dt_instansi[$index]['id']                 = $id;
                                    $dt_instansi[$index]['uuid']               = $this->generateId()->uuid;
                                    $dt_instansi[$index]['id_publikasi_ajuan'] = $id_publikasi_meta;
                                    $dt_instansi[$index]['id_lama']            = $row_instansi['id'];
                                }
                                //UPDATE ID INSTANSI BERDASARKAN HASIL RESTRUKTUR DIATAS
                                $dt_anggota = collect($dt_anggota)->map(function ($val) use ($dt_instansi) {
                                    $values = $val;
                                    foreach ($dt_instansi as $row) {
                                        if ($values['id_instansi_anggota'] == $row['id_lama']) {
                                            $values['id_instansi_anggota'] = $row['id'];
                                        }
                                    }
                                    return $values;
                                })->toArray();
                                // dump($dt_instansi,$dt_anggota);
                                $cleanDataInstansi = collect($dt_instansi)->map(function ($value) {
                                    return collect($value)->except(['id_lama']);
                                })->toArray();
                                $insert = Instansi::insert($cleanDataInstansi);
                                if (!$insert) {
                                    throw new Exception("Menambah tagging instansi gagal", 400);
                                }
                            }
                            //////////////// BERKAS
                            // LIST BENTUK YANG TIDAK ADA MULTIPLE SEMENTARA HARDCODE BSK AMBIL DARI FORM
                            $excludeMultipleFile = ['BHN-2', 'BHN-1'];
                            if (!in_array($getRequestPublikasi['kd_bentuk_publikasi'], $excludeMultipleFile)) {
                                Log::info("handling multiple berkas");
                                $_publikasi_meta_dokumen = $this->populate_array_berkas($getRequestPublikasi, $key_dokumen, $pegawai_attr, $_dataPublikasiMaster, $nik);
                                /////////////////////// REPOPULATING ARRAY META
                                $anggota             = $dt_anggota;
                                $_all_publikasi_meta = collect($_all_publikasi_meta)->map(function ($value) use ($anggota) {
                                    if (strrpos($value['key'], 'keanggotaan') !== false) {
                                        $value['value'] = serialize($anggota);
                                    }
                                    return $value;
                                })->push($_publikasi_meta_dokumen)->all();
                            }
                            $nik_log = $pegawai_attr ? $pegawai_attr->nik : null;
                            Log::info("Tagging pegawai $nik_log");
                            publikasi::create($_dataPublikasiMaster);
                            publikasiMeta::insert($_all_publikasi_meta);
                        }
                    }
                }
            }
            //HANDLING TGL PENGAJUAN
            $pengajuanStatus = $this->statuspublikasibykolom('id', $dataPublikasiMaster['id_publikasi_status']);
            $statusRiwayat   = ['DRF', 'TRB', 'DVR', 'PRO'];
            if (in_array($pengajuanStatus->kd_status, $statusRiwayat)) {
                // fungsi untuk menyimpan riwayat perbaikan
                $dataRiwayat = $this->simpanRiwayatPerbaikan($pengajuanStatus->kd_status, $dataPublikasiMaster, $request->header('X-Member'));
                // simpan tgl pengajuan atau verifikasi ke variabel publikasi
                $dataPublikasiMaster = $dataRiwayat['publikasi'];
                // simpan riwayat perbaikan
                DB::table('publikasi_riwayat_perbaikan')->insert($dataRiwayat['riwayat_perbaikan']);
            }
            publikasi::create($dataPublikasiMaster);
            publikasiMeta::insert($all_publikasi_meta);
            DB::commit();
            return response()->json([
                'status'  => 200,
                'message' => $this->msg_input,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            $id     = $generateIdPublikasiMaster->id;
            $msgLog = "Id publikasi $id" . $e->getLine() . ' Pesan ' . $e->getMessage() . ' file ' . $e->getFile();
            Log::error($msgLog);
            $dataResponse = [
                'status'  => 400,
                'message' => 'Simpan data gagal',
            ];
            if (env('APP_ENV', 'local') == 'local') {
                $dataResponse['trace'] = $msgLog;
            }
            return response()->json($dataResponse, 400);

        }
    }

    public function updateByNIK(Request $request, $nik)
    {
        /*
         * Endpoint update publikasi
         * Alur update :
         * 1. Mengambil data form (key|name) dari model::form
         * 2. Mengambil data request input dari client
         * 3. Mencocokan data form dengan input menggunakan collection intersect
         * 4. Update data model::publikasi dan model::publikasi_meta
         */

        $message_error = "Update data gagal";
        set_time_limit(80);
        $action   = [];
        $response = response()->json([
            'status'  => 'info',
            'message' => 'The Publication update endpoint`s respond well, but no action is processed.',
        ], 200);
        $pengaturanPublikasi = pengaturan::Aktif()->VerifikasiAdmin('PBVA')->first(['kd_pengaturan', 'isi', 'keterangan']);
        $uuidPublikasi       = $request->input('uuid_publikasi');
        //? Mengambil data publikasi yang akan diupdate
        $previousPublication = publikasi::where('uuid', $uuidPublikasi)->first();
        // ! Test publikasi
        // return $this->tagPublikasiSvc($previousPublication->uuid);

        try {

            $message_update = "Update data berhasil";
            DB::beginTransaction();
            // Inisiasi variable utama
            // $nik = ($nik == 'yudhistira') ? '075230424' : $nik;
            $pegawai = pegawai::where('nik', $nik)->first();
            $Xmember = $request->header('X-Member');

            $kdBentukPublikasi = $request->input('kd_bentuk_publikasi');

            if (!isset($previousPublication['id'])) {
                throw new Exception('Data publikasi tidak ditemukan', 404);
            }

            //$previousPublicationMeta = publikasiMeta::where('id_pendidikan', $previousPublication['id'])->where('flag_aktif', true);
            $id = $previousPublication['id'];

            $individualID    = jenisPublikasi::where('kd_jenis', 'INDV')->first()->id;
            $kolaborativeID  = jenisPublikasi::where('kd_jenis', 'KOL')->first()->id;
            $statusPublikasi = $request->input('kd_status', 'DRF');
            // HANDLING STATUS PROSES
            if ($pengaturanPublikasi->isi == '1') {
                if ($statusPublikasi == 'DVR') {
                    $statusPublikasi = 'PRO';
                }

            }
            $status       = status::where('kd_status', $statusPublikasi)->first();
            $bentuk       = bentuk::where('kd_bentuk_publikasi', $kdBentukPublikasi)->first();
            $peran        = peran::where('uuid', $request->input('uuid_peran'))->first();
            $statusPublik = terms::where('uuid', $request->input('uuid_status_publik'))->first();
            $flagPublik   = ($statusPublik) ? (Str::lower($statusPublik->nama_term) === 'ya' ? true : false) : null;
            $statusPublik = ($statusPublik) ? $statusPublik->nama_term : null;

            $stepWizard   = $request->input('step_wizard');
            $flagAktif    = true;
            $userInput    = $pegawai['nik'];
            $tglInput     = date('Y-m-d h:i:s');
            $userUpdate   = $pegawai['nik'];
            $tglUpdate    = date('Y-m-d h:i:s');
            $tglPengajuan = date('Y-m-d H:i:s');

            // Mengambil data form (name, default_value, validasi)
            // Mengambil versi terbaru
            $versiForm = versiForm::where('id_publikasi_bentuk', $bentuk->id)->where('flag_aktif', true)->first();

            // settingan lama ngambil versi berdasarkan publikasinya (jangan dihapus)
            // $versiForm = versiForm::whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true)->where('id_publikasi_bentuk', $bentuk['id'])->where('id', $previousPublication['id_publikasi_form_versi'])->first();

            $formCommon    = form::whereNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->get();
            $formSpecifict = form::whereNotNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->where('id_publikasi_form_versi', $versiForm['id'])->get();
            $formConfig    = collect($formCommon->toArray())->merge($formSpecifict->toArray())->map(function ($column, $value) {
                return collect($column)->only(['id', 'id_publikasi_form_induk', 'tipe_field', 'label', 'name_field', 'default_value', 'options', 'tipe_validasi', 'flag_judul_publikasi', 'flag_tgl_publikasi', 'flag_peran'])->all();
            })->values();
            $unwantedColoumn     = ["peran", "status_publik"];
            $allowedStatusForTag = ["DVR", "TRB", "PRO"];

            // Data Input dari User
            $publikasiInput = $request->all();

            // $publikasi = ['id' => $id, 'id_pegawai' => $pegawai['id'], 'id_publikasi_bentuk' => $bentuk['id'] ?? null, 'id_publikasi_status' => $status['id'] ?? null, 'id_publikasi_form_versi' => $versiForm['id'] ?? null, 'id_publikasi_peran' => $peran['id'] ?? null, 'id_publikasi_jenis' => $previousPublication['id_publikasi_jenis'] ?? null, 'tgl_publikasi' => $request->input('tgl_publikasi', date('Y-m-d')) ?? null, 'tahun' => null, 'step_wizard' => $stepWizard, 'flag_aktif' => $flagAktif, 'user_update' => $userUpdate, 'tgl_update' => $tglUpdate];
            $publikasi     = ['id' => $id, 'id_pegawai' => $pegawai['id'], 'key' => $previousPublication['key'], 'value' => $previousPublication['value'], 'id_publikasi_bentuk' => $bentuk['id'] ?? null, 'id_publikasi_status' => $status['id'] ?? null, 'id_publikasi_form_versi' => $versiForm['id'] ?? null, 'id_publikasi_peran' => $peran['id'] ?? null, 'id_publikasi_jenis' => $previousPublication['id_publikasi_jenis'] ?? null, 'tgl_publikasi' => $request->input('tgl_publikasi', date('Y-m-d')) ?? null, 'tahun' => null, 'step_wizard' => $stepWizard, 'flag_aktif' => $flagAktif, 'user_update' => $userUpdate, 'tgl_update' => $tglUpdate, 'user_input' => $request->header('X-member'), 'flag_publik' => $flagPublik, 'status_publik' => $statusPublik, 'tgl_pengajuan' => $tglPengajuan];
            $publikasiMeta = [];
            $documents     = [];
            $members       = [];

            foreach ($publikasiInput as $keyInput => $valueInput) {
                $nameField = $this->convertKeyUUIDToID($keyInput);
                $keyInput  = Str::replaceFirst('uuid_', '', $keyInput);
                $config    = collect($formConfig)->where('name_field', $keyInput)->first();

                if ($config && $keyInput === $config['name_field']) {
                    if ($config['flag_judul_publikasi'] == true) {
                        $publikasi['key'] = $keyInput;
                        if ($keyInput == 'matakuliah') {
                            $matakuliahValue    = DB::table('matakuliah')->where('uuid', $valueInput)->first();
                            $publikasi['value'] = $matakuliahValue->nama_matakuliah;
                        } else {
                            $publikasi['value'] = $valueInput;
                        }
                    }

                    if ($config['flag_tgl_publikasi'] == true) {
                        if (($config['tipe_field'] === 'year') || (Str::length($valueInput) === 4)) {
                            $publikasi['tgl_publikasi'] = ($valueInput != '') ? $valueInput . '-01-01' : null;
                            $publikasi['tahun']         = ($valueInput != '') ? $valueInput . '-01-01' : null;
                        } else {
                            $publikasi['tgl_publikasi'] = ($valueInput != '') ? $valueInput : null;
                            $publikasi['tahun']         = ($valueInput != '') ? $valueInput : null;
                        }
                    }

                    if ($config['flag_peran'] == true) {
                        $optionMetaData                  = ($config['options']) ? explode('-', $config['options']) : null;
                        $publikasi['id_publikasi_peran'] = $this->getMasterDataID($optionMetaData[1], $valueInput);
                    }

                    if (!in_array($config['name_field'], $unwantedColoumn)) {
                        switch ($config['tipe_field']) {
                            case 'year':
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => ($valueInput != null || $valueInput != "") ? date('Y', strtotime($valueInput)) : null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'date':
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => ($valueInput != null || $valueInput != "") ? date('Y-m-d', strtotime($valueInput)) : null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'autocomplete':
                                if ($nameField != "id_publikasi_$keyInput") {
                                    $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                    $master         = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $request->input('uuid_' . $keyInput)) : $this->getTaxonomyID($optionMetaData[1], $request->input('uuid_' . $keyInput));

                                    $publikasiMeta = collect($publikasiMeta)->push([
                                        'id_publikasi' => $id,
                                        'key'          => $keyInput, 'value'        => $valueInput,
                                        'flag_aktif'   => $flagAktif,
                                        'user_update'  => $userUpdate, 'tgl_update' => $tglUpdate,
                                    ], [
                                        'id_publikasi' => $id,
                                        'key'          => 'id_' . $keyInput, 'value' => $master ?? null,
                                        'flag_aktif'   => $flagAktif,
                                        'user_update'  => $userUpdate, 'tgl_update'  => $tglUpdate,
                                    ]);
                                }
                                break;
                            case 'autoselect':
                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $master         = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $valueInput) : $this->getTaxonomyId($optionMetaData[1], $valueInput);
                                $publikasiMeta  = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => $master ?? null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'currency':
                                $param_uuid = "uuid_$keyInput" . "_" . $config['tipe_field'];
                                $uuid_value = $publikasiInput[$param_uuid] ?? null;

                                $idKey = "$keyInput" . "_" . $config['tipe_field'];

                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $idMaster       = $this->getMasterDataID($optionMetaData[1], $uuid_value) ?? null;
                                $publikasiMeta  = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput, 'value'        => $valueInput,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate, 'tgl_update' => $tglUpdate,
                                ], [
                                    'id_publikasi' => $id,
                                    'key'          => 'id_' . $idKey, 'value'   => $idMaster ?? null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate, 'tgl_update' => $tglUpdate,
                                ]);
                                break;
                            case 'select':
                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $master         = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $valueInput) : $this->getTaxonomyId($optionMetaData[1], $valueInput);
                                $publikasiMeta  = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => $master ?? null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'radio':
                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $master         = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $valueInput) : $this->getTaxonomyId($optionMetaData[1], $valueInput);
                                $publikasiMeta  = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => $master ?? null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'multiple_select':
                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $valueInput     = collect($valueInput)->map(function ($value) {
                                    return implode(collect($value)->map(function ($value) {
                                        return trim($value);
                                    })->all());
                                })->all();
                                $master = collect($valueInput)->map(function ($value) use ($optionMetaData) {
                                    return ($optionMetaData[0] == 'master') ?
                                    DB::table(($optionMetaData[1] == 'matakuliah') ? "$optionMetaData[1]" : "publikasi_$optionMetaData[1]")->where('id', $this->getMasterDataID($optionMetaData[1], $value))->first() : terms::where('id', $this->getTaxonomyId($optionMetaData[1], $value))->first();
                                })->toArray();
                                $values = collect($master)->map(function ($value) use ($optionMetaData) {
                                    $value = json_decode(json_encode($value), true);
                                    return ['uuid' => $value['uuid'], 'value' => ($optionMetaData[0] === 'master') ? $value["nama_$optionMetaData[1]"] : $value['nama_term'], 'id' => $value['id']];
                                })->all();
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => serialize($values),
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'multiple_autoselect':
                                $optionMetaData = ($config['options']) ? explode('-', $config['options']) : null;
                                $valueInput     = collect($valueInput)->map(function ($value) {
                                    return implode(collect($value)->map(function ($value) {
                                        return trim($value);
                                    })->all());
                                })->all();
                                $master = collect($valueInput)->map(function ($value) use ($optionMetaData) {
                                    return ($optionMetaData[0] == 'master') ?
                                    DB::table(($optionMetaData[1] == 'matakuliah') ? "$optionMetaData[1]" : "publikasi_$optionMetaData[1]")->where('id', $this->getMasterDataID($optionMetaData[1], $value))->first() : terms::where('id', $this->getTaxonomyId($optionMetaData[1], $value))->first();
                                })->toArray();
                                $values = collect($master)->map(function ($value) use ($optionMetaData) {
                                    $value = json_decode(json_encode($value), true);
                                    return ['uuid' => $value['uuid'], 'value' => ($optionMetaData[0] === 'master') ? $value["nama_$optionMetaData[1]"] : $value['nama_term'], 'id' => $value['id']];
                                })->all();
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $keyInput,
                                    'value'        => serialize($values),
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'multiple':
                                $dataPublikasi = [
                                    'method'         => $request->method(),
                                    'id_publikasi'   => $id,
                                    'publikasi'      => $publikasi,
                                    'publikasi_meta' => $publikasiMeta,
                                    'pegawai'        => $pegawai,
                                    'flag_aktif'     => $flagAktif,
                                    'user_input'     => $userInput,
                                    'tgl_input'      => $tglInput,
                                    'user_update'    => $userUpdate,
                                    'tgl_update'     => $tglUpdate,
                                ];
                                switch ($keyInput) {
                                    // OR $config['tipe_field']
                                    case 'dokumen':
                                        $documents     = ($publikasiInput['dokumen'] && is_array($publikasiInput['dokumen'])) ? $this->handleDocuments($dataPublikasi, $request) : null;
                                        $publikasiMeta = collect($publikasiMeta)->push([
                                            'id_publikasi' => $id,
                                            'key'          => 'dokumen',
                                            'value'        => serialize($documents ?: [['id' => '', 'berkas' => '', 'path_file' => '', 'keterangan' => '', 'uuid' => '', 'flag_publik' => '']]),
                                            'flag_aktif'   => $flagAktif,
                                            'user_update'  => $userUpdate,
                                            'tgl_update'   => $tglUpdate,
                                        ]);
                                        break;
                                    case 'keanggotaan':
                                        $jumlahAnggota = 0;
                                        foreach ($request->input('keanggotaan') as $anggota) {
                                            if (isset($anggota['nama_penulis']) && (!empty($anggota['nama_penulis']) || $anggota['nama_penulis'] != null || $anggota['nama_penulis'] != '')) {
                                                $jumlahAnggota++;
                                            }
                                        }
                                        $publikasi['id_publikasi_jenis'] = ($jumlahAnggota > 0) ? $kolaborativeID : $individualID;
                                        $members                         = ($publikasiInput['keanggotaan']) ? $this->handleMembers($dataPublikasi, $request) : null;
                                        $publikasi                       = collect([$publikasi]);
                                        $publikasiMeta                   = collect($publikasiMeta);

                                        // Penanganan untuk keanggotaan untuk publikasi utama
                                        if (isset($members['keanggotaan_utama']) && count($members['keanggotaan_utama']) > 0) {
                                            $publikasiMeta->push([
                                                'id_publikasi' => $id,
                                                'key'          => 'keanggotaan',
                                                "value"        => serialize($members['keanggotaan_utama']),
                                                'flag_aktif'   => $flagAktif,
                                                'user_update'  => $userUpdate,
                                                'tgl_update'   => $tglUpdate,
                                            ]);
                                        } else {
                                            $publikasiMeta->push([
                                                'id_publikasi' => $id,
                                                'key'          => 'keanggotaan',
                                                "value"        => serialize([
                                                    'id'                => "", 'nama_penulis'        => "", 'id_jenis_anggota' => "",
                                                    'jenis_anggota'     => "", 'id_instansi_anggota' => "", 'instansi_anggota' => "",
                                                    'id_status_anggota' => "", 'status_anggota'      => "", 'uuid'             => "",
                                                ]),
                                                'flag_aktif'   => $flagAktif,
                                                'user_update'  => $userUpdate,
                                                'tgl_update'   => $tglUpdate,
                                            ]);
                                        }
                                        // Penanganan duplikasi untuk publikasi lain (usulan)
                                        if ((in_array($status['kd_status'], $allowedStatusForTag)) && (isset($members['publikasi_lain']) && count($members['publikasi_lain']) > 0)) {
                                            $copiedPublicationMeta = collect($publikasiMeta);
                                            collect($members['publikasi_lain'])->each(function ($item) use ($publikasi, $publikasiMeta, $copiedPublicationMeta, $members) {

                                                // Publikasi lain (Usulan)
                                                $publikasi->push($item);

                                                // Meta yang lain (usulan)
                                                $copiedPublicationMeta->each(function ($value) use ($item, $publikasiMeta) {
                                                    $data                 = $value;
                                                    $data['id_publikasi'] = $item['id'];
                                                    if (($value['key'] != "dokumen" && $value['key'] != "keanggotaan")) {
                                                        $publikasiMeta->push($data);
                                                    }
                                                });

                                                // Dokumen lain (usulan)
                                                if ($members['dokumen_lain'] && count($members['dokumen_lain']) > 0) {
                                                    $publikasiMeta->push([
                                                        'id_publikasi' => $item['id'],
                                                        'key'          => 'dokumen',
                                                        'value'        => serialize(collect($members['dokumen_lain'])->where('id_pegawai', $item['id_pegawai'])->first()['dokumen']),
                                                        'flag_aktif'   => $item['flag_aktif'],
                                                        'user_update'  => $item['user_update'],
                                                        'tgl_update'   => $item['tgl_update'],
                                                    ]);
                                                }

                                                // Keanggotan lain (usulan)
                                                if (isset($members['keanggotaan_lain']) && count($members['keanggotaan_lain']) > 0) {
                                                    $publikasiMeta->push([
                                                        'id_publikasi' => $item['id'],
                                                        'key'          => 'keanggotaan',
                                                        'value'        => serialize(collect($members['keanggotaan_lain'])->where('id_pegawai', $item['id_pegawai'])->first()['keanggotaan']),
                                                        'flag_aktif'   => $item['flag_aktif'],
                                                        'user_update'  => $item['user_update'],
                                                        'tgl_update'   => $item['tgl_update'],
                                                    ]);
                                                } else {
                                                    $publikasiMeta->push([
                                                        'id_publikasi' => $item['id'],
                                                        'key'          => 'keanggotaan',
                                                        'value'        => serialize([[
                                                            'nama_penulis'          => '',
                                                            'id_jenis_anggota'      => '',
                                                            'jenis_anggota'         => '',
                                                            'uuid_jenis_anggota'    => '',
                                                            'id_status_anggota'     => '',
                                                            'status_anggota'        => '',
                                                            'uuid_status_anggota'   => '',
                                                            'id_instansi_anggota'   => '',
                                                            'instansi_anggota'      => '',
                                                            'uuid_instansi_anggota' => '',
                                                            'id_peran_anggota'      => '',
                                                            'peran_anggota'         => '',
                                                            'uuid_peran_anggota'    => '',
                                                            'nik_keanggotaan'       => '',
                                                            'uuid_keanggotaan'      => '',
                                                            'uuid'                  => '',
                                                        ]]),
                                                        'flag_aktif'   => $item['flag_aktif'],
                                                        'user_update'  => $item['user_update'],
                                                        'tgl_update'   => $item['tgl_update'],
                                                    ]);
                                                }

                                            });
                                        }

                                        // Penanganan jika ada master baru
                                        if (isset($members['master_baru']) && count($members['master_baru']) > 0) {
                                            $newMasters = $members['master_baru'];
                                            $publikasi  = ($request->input('kd_status') == 'TRB') ? collect($publikasi)->map(function ($publicationItem) use ($newMasters) {
                                                $newMaster                              = collect($newMasters)->where('id_publikasi', $publicationItem['id']);
                                                $publicationItem['id_publikasi_status'] = ($newMaster) ? status::where('kd_status', 'PRO')->first()->id : $publicationItem['id_publikasi_status'];
                                                return $publicationItem;
                                            })->toArray() : $publikasi;
                                        }

                                        break;

                                    default:
                                        $config['children'] = collect($formConfig)->where('id_publikasi_form_induk', $config['id'])->toArray() ?: [];
                                        $multipleDatas      = ($valueInput && is_array($valueInput)) ? $this->handleMultiple($dataPublikasi, $valueInput, $config['name_field'], $config['children']) : null;

                                        $publikasiMeta = collect($publikasiMeta)->push([
                                            'id_publikasi' => $id,
                                            'key'          => $nameField,
                                            'value'        => serialize($multipleDatas),
                                            'flag_aktif'   => $flagAktif,
                                            'user_update'  => $userUpdate,
                                            'tgl_update'   => $tglUpdate,
                                        ]);
                                        break;
                                }
                                break;
                            case 'file':
                                $file          = $request->file($nameField);
                                $fileName      = ($file) ? $file->getClientOriginalName() : publikasiMeta::where('id_publikasi', $previousPublication['id'])->where('flag_aktif', true)->where('key', $nameField)->first()['value'];
                                $filePath      = ($file) ? $this->uploadFile('publikasi', $nik, $id . '_' . $nameField, $file)['path'] : publikasiMeta::where('id_publikasi', $previousPublication['id'])->where('flag_aktif', true)->where('key', $nameField . '_path_file')->first()['value'];
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $nameField,
                                    'value'        => $fileName ?: null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $nameField . '_path_file',
                                    'value'        => $filePath ?: null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            case 'image':
                                //HANDLE BENTUK INPUTAN GAMBAR/IMAGE
                                $image         = $request->file($nameField);
                                $imageName     = ($image) ? $image->getClientOriginalName() : publikasiMeta::where('id_publikasi', $previousPublication['id'])->where('flag_aktif', true)->where('key', $nameField)->first()['value'];
                                $imagePath     = ($image) ? $this->uploadFile('publikasi', $nik, $id . '_' . $nameField, $image)['path'] : publikasiMeta::where('id_publikasi', $previousPublication['id'])->where('flag_aktif', true)->where('key', $nameField . '_path_file')->first()['value'];
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $nameField,
                                    'value'        => $imageName ?: null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $nameField . '_path_file',
                                    'value'        => $imagePath ?: null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                            default:
                                $publikasiMeta = collect($publikasiMeta)->push([
                                    'id_publikasi' => $id,
                                    'key'          => $nameField,
                                    'value'        => $valueInput ?: null,
                                    'flag_aktif'   => $flagAktif,
                                    'user_update'  => $userUpdate,
                                    'tgl_update'   => $tglUpdate,
                                ]);
                                break;
                        }
                    }
                }
            }

            $text          = $publikasi[0]['value'] ?? null;
            $id_pegawai    = $pegawai['id'];
            $cek_publikasi = DB::table('publikasi')->select('publikasi.id', 'pegawai.nama', 'publikasi.uuid', 'publikasi.value', 'publikasi_status.status', 'publikasi_bentuk.bentuk_publikasi', )
                ->join('publikasi_status', 'publikasi_status.id', '=', 'publikasi.id_publikasi_status')
                ->join('pegawai', 'pegawai.id', '=', 'publikasi.id_pegawai')
                ->join('publikasi_bentuk', 'publikasi_bentuk.id', '=', 'publikasi.id_publikasi_bentuk')
                ->where('publikasi.id_pegawai', $id_pegawai)
                ->where('publikasi.id_publikasi_bentuk', $bentuk->id)
                ->whereNotIn('publikasi.id', [$publikasi[0]['id']])
                ->where('publikasi.flag_aktif', true)->get();
            $similarity   = $this->checkSimilarity($text, $cek_publikasi);
            $countSimilar = collect($similarity)->count();
            // VALIDASI DATA DITERBITKAN
            if ($request->input('kd_status') == 'TRB') {
                $errorValidationMessages = [
                    'messages'   => [],
                    'error_type' => 'validation',
                ];
                $errorConfirmationMessages = [
                    'messages'   => [],
                    'error_type' => 'confirmation',
                ];

                $cekJudul = $this->cekJudul($pegawai->nik, $publikasi[0]['value'], $publikasi[0]['id_publikasi_bentuk'], [$publikasi[0]['id']]);
                if ($cekJudul['result'] != false) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'judul',
                        //'message' => ucfirst(str_replace('_', ' ', $publikasi[0]['key'])) . " sudah tersimpan sebelumnya",
                        'message' => ucfirst(str_replace('_', ' ', $publikasi[0]['key'])) . " '" . $publikasi[0]['value'] . "' sudah tersimpan sebelumnya",
                        'data'    => $cekJudul['datas'],
                    ]);
                }

                $validationResult = $this->validasiIsian($this->getFormNameByKode($bentuk['kd_bentuk_publikasi'], 'all'), $publikasiInput);
                if (!$validationResult['status']) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'isian',
                        'message' => 'Pengisian data tidak lengkap, pastikan semua yang wajib sudah terisi',
                        'data'    => $validationResult['empty_form'],
                    ]);
                }
                $_validasi_anggota = $this->validasiAnggota($this->getFormNameByKode($bentuk['kd_bentuk_publikasi'], 'all'), $publikasiInput);
                if ($_validasi_anggota) {
                    array_push($errorValidationMessages['messages'], [
                        'info'    => 'peran',
                        'message' => 'Pemilihan peran keanggotaan tidak tepat atau pengisian data anggota tidak lengkap',
                        'data'    => [],
                    ]);
                }

                $autocompleteFields = form::where('id_publikasi_form_versi', $versiForm->id)->where('tipe_field', 'autocomplete')->where('flag_aktif', true)->get();
                if ($autocompleteFields->count() > 0) {
                    $validationNewMaster = $this->validationNewMaster($request, $formConfig);
                    if ($validationNewMaster['status_code'] == 400) {
                        array_push($errorConfirmationMessages['messages'], [
                            'info'    => 'instansi',
                            'message' => 'Anda menambahkan instansi baru sehingga perlu pengecekan oleh admin',
                            'data'    => [],
                        ]);
                        // return response()->json($validationNewMaster['result'], $validationNewMaster['status_code']);
                    } else if ($validationNewMaster['status_code'] == 200) {
                        $keanggotaan        = $request->input('keanggotaan');
                        $newMasterNotVerify = ($keanggotaan) ? collect($autocompleteFields->toArray())->map(function ($item, $key) use ($id, $keanggotaan) {
                            $uuidInstansiAnggota = collect($keanggotaan)->map(function ($anggota) use ($item) {
                                return $anggota['uuid_' . $item['name_field']];
                            })->all();
                            $options   = explode('-', $item['options']);
                            $tableName = $this->getTableActualName($options[1]);
                            $master    = ($options[0] == 'master') ? DB::table($tableName)->select('*')->where('id_publikasi_ajuan', $id)->where('id_publikasi_form', $item['id'])->where('flag_ajuan', true)->whereIn('uuid', $uuidInstansiAnggota)->get() : $this->getTermOfTaxonomyDB($options[1]);
                            return ($master->count() > 0) ? ['field' => $item['label'], 'new_master' => $master->all()] : null;
                        })->filter() : collect([]);

                        $fields = ($newMasterNotVerify->count() > 0) ? $newMasterNotVerify->flatMap(function ($item, $key) {
                            return $item['new_master'];
                        })->map(function ($item) {
                            return form::where('id', $item->id_publikasi_form)->first()->label;
                        })->unique()->values()->implode(', ') : null;

                        if ($newMasterNotVerify->count() > 0) {
                            array_push($errorConfirmationMessages['messages'], [
                                'info'    => 'instansi',
                                'message' => 'Anda menambahkan instansi baru sehingga perlu pengecekan oleh admin',
                                'data'    => [],
                            ]);
                        }
                    }
                }

                if ($countSimilar > 0) {
                    array_push($errorConfirmationMessages['messages'], [
                        'info'    => 'similarity',
                        'message' => 'Judul karya anda memiliki kemiripan dengan karya lain, yaitu:',
                        'data'    => $similarity,
                    ]);
                }

                // ==========================
                //DEFAULT STATUS PUBLIKASI
                $kdStatuss = 'PRO';
                if ($pengaturanPublikasi->isi == '0') {
                    $kdStatuss = 'DVR';
                }
                if (count($errorValidationMessages['messages']) > 0) {
                    return response()->json([
                        'status'     => 400,
                        'error_type' => $errorValidationMessages['error_type'],
                        'messages'   => $errorValidationMessages['messages'],
                    ], 400);
                }

                if (count($errorConfirmationMessages['messages']) > 0) {
                    $cekInstansi = collect($errorConfirmationMessages['messages'])->filter(function ($value, $key) {
                        return $value['info'] == 'instansi';
                    })->count();
                    //$kdStatuss = 'TRB';
                    if ($cekInstansi > 0 || !$request->has('user_allowed')) {
                        $kdStatuss = 'PRO';
                        return response()->json([
                            'status'     => 400,
                            'error_type' => $errorConfirmationMessages['error_type'],
                            'messages'   => $errorConfirmationMessages['messages'],
                        ], 400);
                    }
                }

                // JIKA ADA INSTANSI BARU PAKAI STATUS PROSES YG ADA DI PENGECEKKAN INSTANSI
                // DATA PUBLIKASI MENGGUNAKAN UPSERT BENTUKNYA ARRAY, HARUS DICARI INDEX 0 DATA PUBLIKASI

                $publikasi = collect($publikasi)->map(function ($value, $index) use ($kdStatuss) {
                    if ($index === 0) {
                        //PUBLIKASI MASTER UDPTES STATUS
                        $value['id_publikasi_status'] = status::where('kd_status', $kdStatuss)->first()->id;
                    }
                    return $value;
                });
            }
            // END VALIDASI TERBIT
            $dtLog = [
                "publikasi"     => $publikasi,
                "publikasiMeta" => $publikasiMeta,
            ];
            Log::info("[INFO] Update publikasi", $dtLog);
            publikasiMeta::where('id_publikasi', $id)->where('flag_aktif', true)->update(['flag_aktif' => false]);
            $statusRiwayat   = ['DRF', 'TRB', 'DVR', 'PRO'];
            $pengajuanStatus = $this->statuspublikasibykolom('id', $publikasi[0]['id_publikasi_status']);
            if (in_array($pengajuanStatus->kd_status, $statusRiwayat)) {
                $msg = 'Data disimpan di draft';
                if ($pengajuanStatus->kd_status == 'DVR') {
                    $msg            = 'Data terverifikasi';
                    $tglVerifikasi  = date('Y-m-d H:i:s');
                    $userVerifikasi = $request->header('X-Member');
                    $publikasi[0]   = Arr::add($publikasi[0], 'tgl_verifikasi', $tglVerifikasi);
                    $publikasi[0]   = Arr::add($publikasi[0], 'user_verifikasi', $userVerifikasi);
                } elseif ($pengajuanStatus->kd_status == 'PRO') {
                    $tglPengajuan = date('Y-m-d H:i:s'); //DATETIME
                    $msg          = 'Data diajukan';
                    $publikasi[0] = Arr::add($publikasi[0], 'tgl_pengajuan', $tglPengajuan);
                } elseif ($pengajuanStatus->kd_status == 'TRB') {
                    $msg = 'Data diterbitkan';
                }
                $riwayatPublikasi = [
                    'id_publikasi'        => $publikasi[0]['id'],
                    'id_publikasi_status' => $publikasi[0]['id_publikasi_status'],
                    'kd_status'           => $pengajuanStatus->kd_status,
                    'catatan_perbaikan'   => $msg,
                    'user_input'          => $request->header('X-Member'),
                    'status'              => 1,
                ];
                DB::table('publikasi_riwayat_perbaikan')->insert($riwayatPublikasi);
            }

            collect($publikasi)->each(function ($item) use ($action) {
                array_push($action, publikasi::updateOrCreate([
                    'id'         => $item['id'],
                    'id_pegawai' => $item['id_pegawai'],
                ],
                    [
                        'id_publikasi_bentuk'     => $item['id_publikasi_bentuk'],
                        'id_publikasi_form_versi' => $item['id_publikasi_form_versi'],
                        'key'                     => $item['key'] ?? null,
                        'value'                   => $item['value'] ?? null,
                        'id_publikasi_status'     => $item['id_publikasi_status'],
                        'id_publikasi_peran'      => $item['id_publikasi_peran'],
                        'id_publikasi_jenis'      => $item['id_publikasi_jenis'],
                        'tgl_publikasi'           => $item['tgl_publikasi'],
                        'tahun'                   => $item['tahun'],
                        'step_wizard'             => $item['step_wizard'],
                        'flag_aktif'              => $item['flag_aktif'],
                        'user_update'             => $item['user_update'],
                        'tgl_update'              => $item['tgl_update'],
                        'flag_publik'             => $item['flag_publik'],
                        'status_publik'           => $item['status_publik'],
                        'tgl_pengajuan'           => $item['tgl_pengajuan'] ?? null,
                        'tgl_verifikasi'          => $item['tgl_verifikasi'] ?? null,
                        'user_verifikasi'         => $item['user_verifikasi'] ?? null,
                    ]));
            });
            array_push($action, publikasiMeta::insert($publikasiMeta->toArray()));
            //! Tagging SVC
            $this->tagPublikasiSvc($previousPublication->uuid);

            DB::commit();
            // UPDATE REMUNERASI/SINKRON
            // SKENARIO => JIKA DATA PUBLIKASI 'flag_perbaikan_remunerasi' AKTIF TERLEPAS
            // DARI STATUS PUBLIKASI APAPUN AKAN SINKRON DENGAN REMUNERASI

            //! ----------------------------------------------------------------
            //! SINKRON REMUNERASI
            $listStatusSinkron = ['DVR', 'TRB'];
            if ($previousPublication->flag_perbaikan_remunerasi && in_array($request->input('kd_status'), $listStatusSinkron)) {
                $sinkronremun   = $this->updateRemunerasi($uuidPublikasi);
                $message_update = $this->msg_update;
                if ($sinkronremun['kode'] != 200 && $sinkronremun['status'] == true) {
                    $message_error = "Updata & Sinkron data gagal";
                    // RETURN ERROR MESSAGE REMUN JIKA GAGAL SINKRON
                    Log::error($message_error, [$sinkronremun['response']]);
                    return response()->json([
                        'status'  => 400,
                        'message' => !blank($sinkronremun['response']['info']) ? $sinkronremun['response']['info'] : 'Penyelarasan remunerasi gagal', //PESAN ERROR SESUAI RESPONSE DARI REMUNERASI
                    ], 400);
                    // HANDLING JIKA GAGAL UPDATE ROLL BACK DATA PORTOFOLIO
                    //throw new Exception($message_error, 500); //JIKA SINKRON GAGAL MAKA UPDATE GAGAL DILAKUKAN
                } else if ($sinkronremun['kode'] == 200 && $sinkronremun['status'] == true) {
                    $message_update = 'Berhasil mengubah data dan sinkron remunerasi';
                    Log::info('Sinkron remunerasi berhasil');
                }
            }
            //! ----------------------------------------------------------------
            $response = ($action) ? response()->json([
                'info'    => 'success',
                'status'  => 200,
                'message' => $message_update,
            ], 200) : response()->json([
                'info'    => 'error',
                'status'  => 417,
                'message' => 'Gagal menyimpan data!',
            ], 417);
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            $baris  = $e->getLine();
            $file   = $e->getFile();
            $pesan  = $e->getMessage();
            $msgLog = "Error $pesan, pada $file dengan baris $baris";
            Log::error($msgLog);
            $dataResponse = [
                'status'  => 400,
                'message' => env('APP_ENV', 'local') === 'local' ? $msgLog : 'Update data gagal',
            ];
            return response()->json($dataResponse, 400);
        }
    }

    public function deleteByUuid($uuid)
    {
        try {
            DB::beginTransaction();
            $deletePublikasi = Publikasi::where('uuid', $uuid)->update([
                'flag_aktif' => false,
            ]);
            if (!$deletePublikasi) {
                throw new Exception("UUID Publikasi tidak ditemukan");
            }
            // HANDLING META
            // $getPublikasi = Publikasi::where('uuid', $uuid)->first();
            // if (!$getPublikasi) {
            //     throw new Exception("Publikasi tidak ditemukan");
            // }
            // $delelePublikasiMeta = publikasiMeta::where('id_publikasi', $getPublikasi->id)->update([
            //     'flag_aktif' => false,
            // ]);
            // if(!$delelePublikasiMeta) throw new Exception("Hapus Meta Data Gagal");
            DB::commit();
            return response()->json([
                'status'  => 200,
                'message' => $this->msg_delete,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            $msgLog = $e->getLine() . ' Pesan ' . $e->getMessage() . ' file ' . $e->getFile();
            Log::error($msgLog);
            $dataResponse = [
                'status'  => 400,
                'message' => 'Gagal menghapus data',
            ];
            if (env('APP_ENV', 'local') == 'local') {
                $dataResponse['trace'] = $msgLog;
            }
            return response()->json($dataResponse, 400);
        }
    }

    public function verifikasiByUUID(Request $request, $nik, $uuid)
    {
        $pegawai = $this->pegawaibynik($nik);
        if (!$pegawai) {
            throw new Exception("NIK tidak ditemukan");
        }

        try {
            $kdStatus   = $request->input('kd_status', 'DRF');
            $tglUpdate  = date('Y-m-d H:i:s');
            $userUpdate = $pegawai->nik;
            if ($kdStatus === 'DRF') {
                $statusPublikasi = $this->statuspublikasibykolom('kd_status', $kdStatus);
                $publikasi       = [
                    'id_publikasi_status' => $statusPublikasi->id,
                    'tgl_update'          => $tglUpdate,
                    'user_update'         => $userUpdate,
                ];
            } else {
                $publikasi = [
                    'flag_aktif'  => false,
                    'tgl_update'  => $tglUpdate,
                    'user_update' => $userUpdate,
                ];
            }
            $verifikasiPublikasi = Publikasi::where('uuid', $uuid)->where('id_pegawai', $pegawai->id)->update($publikasi);
            if (!$verifikasiPublikasi) {
                throw new Exception("Data sudah terverifikasi", 200);
            }
            return response()->json([
                'status'  => 200,
                'message' => 'Verifikasi berhasil',
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status'  => $e->getCode() ? $e->getCode() : 400,
                'message' => 'Update status gagal',
            ]);
        }
    }

    public function checkSimilarity($text, $nText)
    {
        $similar = [];
        foreach ($nText as $index => $values) {
            $row[$index] = $values;
            similar_text($text, $values->value, $percent);
            $row[$index]->persen = round($percent, 2);
            if ($row[$index]->persen >= 70) {
                $similar[$index]['uuid']              = $values->uuid;
                $similar[$index]['judul_karya']       = $values->value;
                $similar[$index]['bentuk_publikasi']  = $values->bentuk_publikasi;
                $similar[$index]['nama_penulis']      = $values->nama;
                $similar[$index]['status_publikasi']  = $values->status;
                $similar[$index]['tingkat_kemiripan'] = $row[$index]->persen;
            }
        }
        $similarity = collect($similar)->sortBy('tingkat_kemiripan', SORT_REGULAR, true)->values();
        return $similarity;
    }

    public function getSimilarity(Request $request, $uuid)
    {
        $pegawai = Publikasi::where('uuid', $uuid)->first();
        if (!$pegawai) {
            throw new Exception("pegawai tidak ditemukan");
        }
        try {
            $text       = $request->input('text');
            $id_pegawai = $pegawai->id_pegawai;
            $publikasi  = DB::table('publikasi')->select('publikasi.id', 'pegawai.nama', 'publikasi.uuid', 'publikasi.value', 'publikasi_status.status', 'publikasi_bentuk.bentuk_publikasi', )
                ->join('publikasi_status', 'publikasi_status.id', '=', 'publikasi.id_publikasi_status')
                ->join('pegawai', 'pegawai.id', '=', 'publikasi.id_pegawai')
                ->join('publikasi_bentuk', 'publikasi_bentuk.id', '=', 'publikasi.id_publikasi_bentuk')
                ->where('publikasi.id_pegawai', $id_pegawai)
                ->where('publikasi.id_publikasi_bentuk', $pegawai->id_publikasi_bentuk)
                ->where('publikasi.flag_aktif', true)->get();
            $similarity = $this->checkSimilarity($text, $publikasi);
            $count      = collect($similarity)->count();

            return response()->json([
                'count' => $count,
                'data'  => $similarity,
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status'  => $e->getCode() ? $e->getCode() : 400,
                'message' => 'Update status gagal',
            ]);
        }
    }

    public function simpanRiwayatPerbaikan($status, $publikasi, $xMember)
    {
        $msg = 'Data disimpan di draft';
        if ($status == 'DVR') {
            $msg                          = 'Data terverifikasi';
            $publikasi['tgl_pengajuan']   = date('Y-m-d H:i:s');
            $publikasi['tgl_verifikasi']  = date('Y-m-d H:i:s');
            $publikasi['user_verifikasi'] = $xMember;
        } elseif ($status == 'PRO') {
            $publikasi['tgl_pengajuan'] = date('Y-m-d H:i:s'); //DATETIME
            $msg                        = 'Data diajukan';
        } elseif ($status == 'TRB') {
            $msg = 'Data diterbitkan';
        }
        $riwayatData = [
            'id_publikasi'        => $publikasi['id'],
            'id_publikasi_status' => $publikasi['id_publikasi_status'],
            'kd_status'           => $status,
            'catatan_perbaikan'   => $msg,
            'user_input'          => $xMember,
            'status'              => 1,
        ];
        $data = [
            'publikasi'         => $publikasi,
            'riwayat_perbaikan' => $riwayatData,
        ];
        return $data;
    }

    //GENERATE FLAG PUBLIK KOLOM
    // public function generateFlagPublik(Request $request)
    // {
    //     $formVersi = DB::table('publikasi_form_versi')->select('id')->where('flag_aktif', true)->get();
    //     $arrData = [
    //         // 'id_publikasi_form_versi' => 819676527611802808,
    //         'id_publikasi_form_induk' => 819676527611809115,
    //         'label' => 'Ingin bisa diakses publik?',
    //         'id_field' => 'status_publik',
    //         'tipe_field' => 'select',
    //         // 'name_field' => 'status_publik',
    //         'options' => 'taxonomy-flag_publik',
    //         'order' => 7,
    //         'flag_multiple_form' => 0,
    //         'flag_judul_publikasi' => 0,
    //         'flag_tgl_publikasi' => 0,
    //         'flag_required' => 1,
    //         'flag_peran' => 0,
    //         'flag_aktif' => 1,
    //     ];
    //     foreach ($formVersi as $index => $row) {
    //         $form = DB::table('publikasi_form')->where('id_publikasi_form_induk', 819676527611809115)->where('id_publikasi_form_versi', $row->id)->get();
    //         $jumlah = $form->Count();
    //         $arrData['order'] = $jumlah + 1;
    //         //$arrData['id_publikasi_form_versi']=$row->id; //819676527611802758
    //         $generate = form::updateOrInsert(['name_field' => 'status_publik', 'id_publikasi_form_versi' => $row->id], $arrData);
    //         if ($request->input('debug') == true) {
    //             dd($generate->count(), $arrData, ['form versi' => $row->id]);
    //         }
    //     }
    //     return response()->json('generate berhasil', 200);
    // }

    public function changePublicationTypeByNIK(Request $request, $nik)
    {
        /**
         * Alur ubah bentuk :
         * 1. Mengambil data form (key|name) dari model::form
         * 2. Mengambil data request input dari client (key & value)
         * 3. Key dari input berisi nama field asal
         * 4. Value dari input berisi nama field tujuan
         * 5. Mencocokan data form dengan input
         * 6. Update data model::publikasi dan model::publikasi_meta
         */
        $message_error = "Ubah bentuk publikasi gagal";
        set_time_limit(80);
        $dateNow  = date('Y-m-d');
        $action   = [];
        $response = response()->json([
            'status'  => 'info',
            'message' => 'The Publication update endpoint`s respond well, but no action is processed.',
        ], 200);
        try {

            DB::beginTransaction();
            // Inisiasi variable utama
            $nik                  = ($nik == 'yudhistira') ? '075230424' : $nik;
            $pegawai              = pegawai::where('nik', $nik)->first();
            $uuidPublikasi        = $request->input('uuid_publikasi');
            $oldKdBentukPublikasi = $request->input('old_kd_bentuk_publikasi');
            $newKdBentukPublikasi = $request->input('new_kd_bentuk_publikasi');
            $statusPublikasi      = $request->input('kd_status', 'DRF');

            $previousPublication = publikasi::where('uuid', $uuidPublikasi)->first();
            if (!isset($previousPublication['id'])) {
                throw new Exception('Data publikasi tidak ditemukan', 404);
            }

            $previousPublicationMeta = publikasiMeta::where('id_publikasi', $previousPublication['id'])->where('flag_aktif', true)->get()->flatMap(function ($item) use ($action) {
                return [$item->key => $item->value];
            })->toArray();

            $id = $previousPublication['id'];

            $status    = status::where('kd_status', $statusPublikasi)->first();
            $newBentuk = bentuk::where('kd_bentuk_publikasi', $newKdBentukPublikasi)->first();
            $oldBentuk = bentuk::where('kd_bentuk_publikasi', $oldKdBentukPublikasi)->first();
            $peran     = peran::where('uuid', $previousPublication['uuid_peran'])->first();

            $stepWizard   = $request->input('step_wizard');
            $flagAktif    = true;
            $userInput    = $pegawai['nik'];
            $tglInput     = date('Y-m-d h:i:s');
            $userUpdate   = $pegawai['nik'];
            $tglUpdate    = date('Y-m-d h:i:s');
            $tglPengajuan = date('Y-m-d H:i:s');

            // Mengambil data form (name, default_value, validasi)
            $oldVersiForm = versiForm::whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true)->where('id_publikasi_bentuk', $oldBentuk['id'])->first();

            $formCommon    = form::whereNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->get();
            $formSpecifict = form::whereNotNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->where('id_publikasi_form_versi', $oldVersiForm['id'])->get();
            $formConfig    = collect($formCommon->toArray())->merge($formSpecifict->toArray())->map(function ($column, $value) {
                return collect($column)->only(['id', 'id_publikasi_form_induk', 'tipe_field', 'label', 'name_field', 'default_value', 'options', 'tipe_validasi', 'flag_judul_publikasi', 'flag_tgl_publikasi', 'flag_peran'])->all();
            })->values();

            $newVersiForm = versiForm::whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true)->where('id_publikasi_bentuk', $newBentuk['id'])->first();

            $newFormCommon    = form::whereNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->get();
            $newFormSpecifict = form::whereNotNull('id_publikasi_form_versi')->whereNotNull('name_field')->where('flag_aktif', true)->where('id_publikasi_form_versi', $newVersiForm['id'])->get();
            $newFormConfig    = collect($newFormCommon->toArray())->merge($newFormSpecifict->toArray())->map(function ($column, $value) {
                return collect($column)->only(['id', 'id_publikasi_form_induk', 'tipe_field', 'label', 'name_field', 'default_value', 'options', 'tipe_validasi', 'flag_judul_publikasi', 'flag_tgl_publikasi', 'flag_peran'])->all();
            })->values();

            $unwantedColoumn     = ["peran", "status_publik"];
            $allowedStatusForTag = ["DVR", "TRB", "PRO"];

            // Data Input dari User
            $publikasiInput = $request->all();

            $publikasi     = ['id' => $id, 'id_pegawai' => $pegawai['id'], 'key' => $previousPublication['key'], 'value' => $previousPublication['value'], 'id_publikasi_bentuk' => $newBentuk['id'] ?? null, 'id_publikasi_status' => $status['id'] ?? null, 'id_publikasi_form_versi' => $newVersiForm['id'] ?? null, 'id_publikasi_peran' => $previousPublication['id_publikasi_peran'], 'id_publikasi_jenis' => $previousPublication['id_publikasi_jenis'], 'tgl_publikasi' => $previousPublication['tgl_publikasi'], 'tahun' => $previousPublication['tahun'], 'step_wizard' => $previousPublication['step_wizard'], 'flag_aktif' => $previousPublication['flag_aktif'], 'user_update' => $userUpdate, 'tgl_update' => $tglUpdate, 'user_input' => $request->header('X-member'), 'flag_publik' => $previousPublication['flag_publik'], 'status_publik' => $previousPublication['status_publik'], 'tgl_pengajuan' => $previousPublication['tgl_pengajuan']];
            $publikasiMeta = collect([]);

            foreach ($publikasiInput as $keyInput => $valueInput) {
                $config = (is_array($valueInput)) ? collect($newFormConfig)->where('name_field', $keyInput)->first() : collect($newFormConfig)->where('name_field', $valueInput)->first();

                // Jika $config valid, $valueInput valid, $valueInput bertipe array atau $valueInput sama dengan $config['name_field'], $config['name_field'] tidak ada dalam $unwantedColoumn
                if ($config && $valueInput && (is_array($valueInput) || ($valueInput == $config['name_field'])) && !in_array($config['name_field'], $unwantedColoumn)) {

                    switch ($config['tipe_field']) {
                        case 'multiple':
                            $childTempData = (isset($previousPublicationMeta[$keyInput])) ? unserialize($previousPublicationMeta[$keyInput]) : null;
                            $childConfig   = collect($newFormConfig)->where('id_publikasi_form_induk', $config['id']);
                            $multipelData  = [];

                            if ($childTempData) {
                                foreach ($childTempData as $dataIndex => $dataValue) {

                                    $childData = [];

                                    if ($childConfig) {
                                        foreach ($childConfig->toArray() as $itemConfig) {
                                            $childValueInput = $valueInput[$dataIndex][$itemConfig['name_field']];

                                            if ($itemConfig && !in_array($itemConfig['name_field'], $unwantedColoumn)) {
                                                switch ($itemConfig['tipe_field']) {
                                                    case 'autocomplete':
                                                        if ($childValueInput) {
                                                            $childData[$childValueInput]         = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                            $childData['id_' . $childValueInput] = $dataValue['id_' . $valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                        } else {
                                                            $childData[$itemConfig['name_field']]         = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                            $childData['id_' . $itemConfig['name_field']] = $dataValue['id_' . $valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                        }
                                                        break;
                                                    case 'file':
                                                        switch ($config['name_field']) {
                                                            case 'dokumen':
                                                                if ($childValueInput) {
                                                                    $childData[$childValueInput] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                                    $childData['path_file']      = $dataValue['path_file'] ?? '';
                                                                } else {
                                                                    $childData[$itemConfig['name_field']] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                                    $childData['path_file']               = $dataValue['path_file'] ?? '';
                                                                }
                                                                break;

                                                            default:
                                                                if ($childValueInput) {
                                                                    $childData[$childValueInput]                = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                                    $childData[$childValueInput . '_path_file'] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']] . '_path_file'] ?? '';
                                                                } else {
                                                                    $childData[$itemConfig['name_field']]                = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                                    $childData[$itemConfig['name_field'] . '_path_file'] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']] . '_path_file'] ?? '';
                                                                }
                                                                break;
                                                        }
                                                        break;
                                                    default:
                                                        if ($childValueInput) {
                                                            $childData[$childValueInput] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                        } else {
                                                            $childData[$itemConfig['name_field']] = $dataValue[$valueInput[$dataIndex][$itemConfig['name_field']]] ?? '';
                                                        }
                                                        break;
                                                }
                                            }

                                        }
                                    }

                                    if ($config['name_field'] == 'keanggotaan') {
                                        $childData['nik_keanggotaan'] = $dataValue['nik_keanggotaan'] ?? '';
                                    }

                                    $childData['uuid'] = $dataValue['uuid'] ?? '';
                                    $childData['id']   = $dataValue['id'] ?? '';

                                    array_push($multipelData, $childData);
                                }
                            }

                            $publikasiMeta->push([
                                'id_publikasi' => $id,
                                'key'          => $config['name_field'],
                                'value'        => serialize($multipelData),
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ]);
                            break;
                        case 'autocomplete':
                            $publikasiMeta->push([
                                'id_publikasi' => $id,
                                'key'          => $valueInput,
                                'value'        => $previousPublicationMeta[$keyInput] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate, 'tgl_update' => $tglUpdate,
                            ], [
                                'id_publikasi' => $id,
                                'key'          => 'id_' . $valueInput,
                                'value'        => $previousPublicationMeta['id_' . $keyInput],
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate, 'tgl_update' => $tglUpdate,
                            ]);
                            break;
                        case 'file':
                            $publikasiMeta->push([
                                'id_publikasi' => $id,
                                'key'          => $valueInput,
                                'value'        => $previousPublicationMeta[$keyInput] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ], [
                                'id_publikasi' => $id,
                                'key'          => $valueInput . '_path_file',
                                'value'        => $previousPublicationMeta[$keyInput . '_path_file'] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ]);
                            break;
                        case 'image':
                            $publikasiMeta->push([
                                'id_publikasi' => $id,
                                'key'          => $valueInput,
                                'value'        => $previousPublicationMeta[$keyInput] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ], [
                                'id_publikasi' => $id,
                                'key'          => $valueInput . '_path_file',
                                'value'        => $previousPublicationMeta[$keyInput . '_path_file'] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ]);
                            break;
                        default:
                            $publikasiMeta->push([
                                'id_publikasi' => $id,
                                'key'          => $valueInput,
                                'value'        => $previousPublicationMeta[$keyInput] ?? '',
                                'flag_aktif'   => $flagAktif,
                                'user_update'  => $userUpdate,
                                'tgl_update'   => $tglUpdate,
                            ]);
                            break;
                    }
                }
            }

            $publikasi = [$publikasi];

            // Soft delete publikasiMeta
            publikasiMeta::where('id_publikasi', $id)->where('flag_aktif', true)->update(['flag_aktif' => false]);

            collect($publikasi)->each(function ($item) use ($action) {
                array_push($action, publikasi::updateOrCreate(
                    [
                        'id'         => $item['id'],
                        'id_pegawai' => $item['id_pegawai'],
                    ],
                    [
                        'id_publikasi_bentuk'     => $item['id_publikasi_bentuk'],
                        'id_publikasi_form_versi' => $item['id_publikasi_form_versi'],
                        'key'                     => $item['key'] ?? null,
                        'value'                   => $item['value'] ?? null,
                        'id_publikasi_status'     => $item['id_publikasi_status'],
                        'id_publikasi_peran'      => $item['id_publikasi_peran'],
                        'id_publikasi_jenis'      => $item['id_publikasi_jenis'],
                        'tgl_publikasi'           => $item['tgl_publikasi'],
                        'tahun'                   => $item['tahun'],
                        'step_wizard'             => $item['step_wizard'],
                        'flag_aktif'              => $item['flag_aktif'],
                        'user_update'             => $item['user_update'],
                        'tgl_update'              => $item['tgl_update'],
                        'flag_publik'             => $item['flag_publik'],
                        'status_publik'           => $item['status_publik'],
                        'tgl_pengajuan'           => $item['tgl_pengajuan'] ?? null,
                        'tgl_verifikasi'          => $item['tgl_verifikasi'] ?? null,
                        'user_verifikasi'         => $item['user_verifikasi'] ?? null,
                    ]
                ));
            });
            array_push($action, publikasiMeta::insert($publikasiMeta->toArray()));

            // RIWAYAT
            $dtLog = [
                "publikasi"     => $publikasi,
                "publikasiMeta" => $publikasiMeta->toArray(),
            ];
            Log::info("[INFO] Ubah bentuk publikasi", $dtLog);

            $statusRiwayat = ['DRF', 'TRB', 'DVR', 'PRO'];
            if (in_array($status['kd_status'], $statusRiwayat)) {
                $tglPengajuan = date('Y-m-d H:i:s'); //DATETIME
                $msg          = 'Ubah bentuk publikasi';
                $publikasi[0] = Arr::add($publikasi[0], 'tgl_pengajuan', $tglPengajuan);

                if ($status['kd_status'] == 'DVR') {
                    $publikasi[0] = Arr::add($publikasi[0], 'tgl_verifikasi', date('Y-m-d H:i:s'));
                    $publikasi[0] = Arr::add($publikasi[0], 'user_verifikasi', $request->header('X-Member'));
                }

                $riwayatPublikasi = [
                    'id_publikasi'        => $publikasi[0]['id'],
                    'id_publikasi_status' => $publikasi[0]['id_publikasi_status'],
                    'kd_status'           => $status['kd_status'],
                    'catatan_perbaikan'   => $msg,
                    'user_input'          => $request->header('X-Member'),
                    'status'              => 1,
                ];
                DB::table('publikasi_riwayat_perbaikan')->insert($riwayatPublikasi);
            }

            DB::commit();
            $response = ($action) ? response()->json([
                'info'    => 'success',
                'status'  => 201,
                'message' => 'Sukses menyimpan data!',
            ], 201) : response()->json([
                'info'    => 'error',
                'status'  => 417,
                'message' => 'Gagal menyimpan data!',
            ], 417);

            //} catch (\Throwable $e) {
            //    return response()->json([
            //        'error' => [
            //            'description' => $e->getMessage(),
            //            'line' => $e->getLine()
            //        ]
            //    ], 500);
            //}

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Exception updating publication data: ' . $e . ', line ' . $e->getLine());
            $responseMessage = [
                'status'  => 400,
                'message' => "Sistem sedang sibuk",
            ];
            $response = response()->json($responseMessage, 400);
        }
        return $response;
    }

    public function tagPublikasiSvc($uuid)
    {
        $uri = env('PORTOFOLIO_TAGGING_API_URL');
        $url = "http://" . $uri . "/private/api/v1/publikasi/tagging/$uuid/update";
        Log::info("Proses tagging $uuid", [$url]);
        $response = Http::POST($url);
        if ($response->status() !== 200) {
            Log::error("Gagal melakukan tagging untuk $uuid. Status: " . $response->status());
            throw new HttpException(400, "Gagal melakukan tagging. Status: " . $response->status());
        }
        $responseJson = $response->json();
        Log::info("Response tagging $uuid", [$responseJson]);
    }
}
