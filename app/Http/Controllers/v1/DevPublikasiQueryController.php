<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use \App\Traits\UmumTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

use App\Models\PegawaiModel AS pegawai;
use App\Models\BentukModel as bentuk;
use App\Models\StatusModel as status;
use App\Models\PeranModel as peran;
use App\Models\PublikasiModel AS publikasi;
use App\Models\VersiFormModel as versiForm;
use App\Models\FormModel as form;

class DevPublikasiQueryController extends Controller {

    use UmumTrait;

    public function __construct() {
        // $this->middleware('auth');
    }

    public function getPublikasiByNIK(Request $request, $nik){
        $response = NULL;
        $publikasiAll = [];
        $pegawai = pegawai::where('nik', $nik)->first();
        try {
            $publikasiAll = publikasi::
            select([
                'pegawai.nama',
                'pegawai.gelar_depan',
                'pegawai.gelar_belakang',
                'dev_publikasi.*',
                'dev_publikasi_bentuk.*',
                'dev_publikasi_bentuk.uuid as uuid_bentuk_publikasi',
                'dev_publikasi_status.*',
                'dev_publikasi_status.uuid as uuid_status',
                'dev_publikasi_peran.*',
                'dev_publikasi_peran.uuid as uuid_peran'
            ])->
            join('pegawai', 'pegawai.id', '=', 'dev_publikasi.id_pegawai')->
            join('dev_publikasi_bentuk', 'dev_publikasi_bentuk.id', '=', 'dev_publikasi.id_publikasi_bentuk')->
            join('dev_publikasi_status', 'dev_publikasi_status.id', '=', 'dev_publikasi.id_publikasi_status')->
            join('dev_publikasi_peran', 'dev_publikasi_peran.id', '=', 'dev_publikasi.id_publikasi_peran')->
            where('dev_publikasi.id_pegawai', $pegawai['id'])->
            where('dev_publikasi.flag_aktif', true);
            //dd($pegawai);
            $count = $publikasiAll->count();
            $limit = $count;
            $limit = ($count < $limit) ? $count : $limit;
            $limit = ($request->input('limit')) ? (int) $request->input('limit') : $limit; 
            $offset = ($request->input('offset')) ? (int) $request->input('offset') : 0;

            if ($request->input('cari')) {
                $pencarian = $request->input('cari');
                $publikasiAll = $publikasiAll->where('dev_publikasi.value', 'like', "%{$pencarian}%") ;
            }

            if ($request->input('kd_bentuk_publikasi')) {
                $kdBentuk = ($request->input('kd_bentuk_publikasi')) ? $request->input('kd_bentuk_publikasi') : NULL ;
                $idBentuk = ($kdBentuk) ? bentuk::where('kd_publikasi_bentuk', $kdBentuk)->first()['id'] : NULL ;
                $publikasiAll = $publikasiAll->where('id_publikasi_bentuk', $idBentuk);
            }

            if ($request->input('kd_status')) {
                $kdStatus = ($request->input('kd_status')) ? $request->input('kd_status') : NULL;
                $idStatus = ($kdStatus) ? status::where('kd_status', $kdStatus)->first()['id'] : NULL;
                $publikasiAll = $publikasiAll->where('id_publikasi_status', $idStatus);
            }

            if ($request->input('tahun')) {
                $tahun = ($request->input('tahun')) ? $request->input('tahun') : NULL;
                $publikasiAll = $publikasiAll->whereYear('tahun', $tahun);
            }

            if ($request->input('kd_peran')) {
                $kdPeran = ($request->input('kd_peran')) ? $request->input('kd_peran') : NULL;
                $idPeran = ($kdPeran) ? peran::where('kd_peran', $kdPeran)->first()['id'] : NULL;
                $publikasiAll = $publikasiAll->where('id_publikasi_peran', $idPeran);
            }

            $response = ($count !== 0) ? response()->json([
                'count' => $count,
                'data' => $publikasiAll->take($limit)->skip($offset)->get(),
                'limit' => $limit,
                'offset' => $offset
            ], 200) : response()->json([
                'data' => [],
            ], 200);
        } catch (QueryException $e) {
            $response = response()->json([
                'status' => 'error',
                'message' => 'Error on get Publikasi Data! There is an error on the server, please try again later. '
            ], 500);
        }
        return $response;
    }

    public function getFormAll() {
        try {
            $dateNow = date('Y-m-d');
            $bentuk = bentuk::with(['formVersion' => function ($query) use ($dateNow) {
                $query->where(function ($query) use ($dateNow) {
                    $query->whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true);
                });
            }])->get();
            $data = [];
            foreach ($bentuk->toArray() as $key => $value) {
                $data[$key] = $value;
                $versiForm = versiForm::where('kd_versi', $value['form_version']['kd_versi'])->first();
                $dataSets = array_merge(form::whereNull('id_publikasi_form_versi')->get()->toArray(), form::whereNotNull('id_publikasi_form_versi')->where('id_publikasi_form_versi', $versiForm['id'])->get()->toArray());
                $children = $this->fields('id', 'id_publikasi_form_induk', $dataSets);
                $data[$key]['form_version']['fields'] = $children;
            }
            $response = ($bentuk->count() !== 0) ? response()->json([
                'data' => $data
            ], 200) : response()->json([
                'data' => [],
            ], 200);
        } catch (Exception $e) {
            $response = response()->json([
                'status' => 'error',
                'message' => 'Error on get Penelitian Data! There is an error on the server, please try again later. ' . $e
            ], 500);
        }
        return $response;
    }

    public function getFormByKDBentuk(Request $request) {
        try {
            $dateNow = date('Y-m-d');
            $kdBentuk = $request->input('kd_bentuk');
            $bentuk = bentuk::with(['formVersion' => function ($query) use ($dateNow) {
                $query->whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', true);
            }])->where('kd_bentuk_publikasi', $kdBentuk)->get();
            $data = [];
            foreach ($bentuk->toArray() as $key => $value) {
                $data[$key] = $value;
                $versiForm = versiForm::where('kd_versi', $value['form_version']['kd_versi'])->first();
                $dataSets = collect(
                    form::whereNull('id_publikasi_form_versi')->where('flag_aktif', true)->get()
                )->merge(
                    form::whereNotNull('id_publikasi_form_versi')->where('id_publikasi_form_versi', $versiForm['id'])->where('flag_aktif', true)->get()
                )->toArray();
                $children = $this->fields('id', 'id_publikasi_form_induk', $dataSets);
                $data[$key]['form_version']['fields'] = $children;
            }
            $response = ($bentuk->count() !== 0) ? response()->json([
                'data' => $data
            ], 200) : response()->json([
                'data' => [],
            ], 200); 
        } catch (Exception $e) {
            $response = response()->json([
                'status' => 'error',
                'message' => 'Error on get Penelitian Data! There is an error on the server, please try again later.'
            ], 500);
        }
        return $response;
    }

}