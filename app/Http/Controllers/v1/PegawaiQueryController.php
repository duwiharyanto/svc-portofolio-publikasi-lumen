<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\PegawaiModel as Pegawai;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PegawaiQueryController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
    }
    public function getByNIK(Request $request, $nik = null)
    {
        $data   = [];
        $status = 500;
        try {
            // Mengambil kd otoritas
            if ($request->header('X-Organization')) {
                $organisasi = json_decode($request->header('X-Organization'));
                $otoritas   = collect($organisasi)->map(function ($values) {
                    $kd_organisasi = $values->kd_organisasi;
                    return $kd_organisasi;
                });
            } else {
                throw new Exception("Otoritas user tidak ditemukan", 400);
            }
            // Mengambil nama otoritas
            $all_name = DB::table('hcm_organisasi_unit')->whereIn('kd_unit', $otoritas)->get('nama_unit')->map(function ($item) {
                return $item->nama_unit;
            })->toArray();
            $get_data = Pegawai::select([
                'pegawai.nama',
                'pegawai.gelar_depan',
                'pegawai.gelar_belakang',
                'pegawai.sinta_id',
                'pegawai.googlescholar_id',
                'pegawai.scopus_id',
                'pegawai_fungsional.nidn',
                'jabatan_fungsional.fungsional as jabatan_fungsional',
                'pegawai.uuid',
            ])->leftJoin('pegawai_fungsional', 'pegawai_fungsional.id_pegawai', '=', 'pegawai.id')
                ->leftJoin('jabatan_fungsional', 'jabatan_fungsional.id', '=', 'pegawai_fungsional.id_jabatan_fungsional');
            if ($nik) {
                // $nik    = ($nik == 'yudhistira') ? '075230424' : $nik;
                $result = $get_data->where('nik', $nik)->first();
                //menambah property otoritas
                $result['otoritas'] = $all_name;
                if (!$result) {
                    throw new Exception('Data berdasarkan nik tidak ditemukan');
                }
            } else {
                $result = $get_data->get();
                if ($result->count() == 0) {
                    throw new Exception('Data berdasarkan nik tidak ditemukan');
                }
            }
            $data   = $result;
            $status = 200;
            $info   = 'data berhasil diambil';
        } catch (Exception $e) {
            Log::error('Exception on getting student data: ' . $e);
            $info   = $e->getMessage();
            $status = 400;
        }
        Log::info("Pegawai ", $data->toArray());
        $respon = [
            'status' => $status,
            'data'   => $data,
            'info'   => $info,
        ];
        return response()->json($respon, $status);
    }

    public function getPegawaiBySearchKey(Request $request)
    {
        $data   = [];
        $status = 500;
        try {
            $pegawai = Pegawai::select('*', 'nik as nomor_induk')->where('flag_aktif', true);

            if ($request->input('search_key')) {
                $key = $request->input('search_key');
                $pegawai->where(function ($query) use ($key) {
                    $query->where('nama', 'like', "%$key%")->orWhere('nik', 'like', "%$key%");
                });
            }

            if ($request->input('exclude_nik')) {
                $excludeNIK = $request->input('exclude_nik');
                $pegawai->whereNotIn('nik', [$excludeNIK]);
            }

            $data   = $pegawai->take(25)->get();
            $status = 200;
            $info   = 'data berhasil diambil';
        } catch (Exception $e) {
            Log::error('Exception on getting student data: ' . $e);
            $info   = $e->getMessage();
            $status = 400;
        }
        $respon = [
            'status' => $status,
            'data'   => $data,
            'info'   => $info,
        ];
        return response()->json($respon, $status);
    }

}
