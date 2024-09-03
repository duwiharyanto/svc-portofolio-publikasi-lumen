<?php

namespace App\Http\Controllers\v1\Sitasi;

use App\Http\Controllers\Controller;
use App\Models\PegawaiModel as Pegawai;
use App\Models\SitasiModel as Sitasi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KaryaQueryController extends Controller
{
    public function __construct()
    {

    }
    public function index(Request $request)
    {
        try {
            $status = 200;
            $stric=true;
            $nik = $request->header('X-member');
            $pegawai = Pegawai::where('nik', $nik)->first();
            if (!$pegawai) {
                throw new Exception("Karya sitasi tidak ditemukan", 400);
            }
            $dataKarya = DB::table('publikasi as p')->select('p.id', 'p.value','ps.status', 'pb.kd_bentuk_publikasi as kd_bentuk','p.uuid')->
            join('publikasi_bentuk as pb','pb.id','=','p.id_publikasi_bentuk')->
            join('publikasi_status as ps','ps.id','=','p.id_publikasi_status')->
            where('p.id_pegawai', $pegawai->id)->where('p.flag_aktif', true)
            ;
            if($stric){
                $dataKarya->where('ps.kd_status','DVR');
            }
            if ($request->input('search_key')) {
                $dataKarya->where('value', 'like', '%' . $request->input('search_key') . '%');
            }
            $dataKarya = $dataKarya->orderBY('p.value', 'ASC')->get();
            $dataSitasi = Sitasi::select('id_karya')->get();
            $listBentukSitasi=['SIT-1','SIT-2'];
            // HANDLING JIKA KARYA YG SUDAH DIAJUKAN TIDAK MUNCUL LAGI
            $data = $dataKarya->map(function ($value) use ($dataSitasi,$listBentukSitasi) {
                $idKarya = $value->id;
                $status = false;
                $cekKarya = $dataSitasi->flatMap(function ($valSitasi) use ($idKarya, &$status) {
                    if ($idKarya === $valSitasi->id_karya) {
                        $status = true;
                    }
                });
                if (!$status) {
                    $values['karya'] = $value->value;
                    $values['status'] = $value->status;
                    $values['uuid'] = $value->uuid;
                    if(!\in_array($value->kd_bentuk,$listBentukSitasi))
                    return $values;
                }
            })->reject(function ($value) {
                return empty($value);
            })->values();
            $response = [
                'message' => 'sukses',
                'data' => $data,
            ];

        } catch (Exception $e) {
            Log::error($e->getMessage().', line '.$e->getLine());
            $status = 400;
            $response = [
                'message' => $e->getMessage(),
            ];
        }
        return response()->json($response, $status);
    }
}
