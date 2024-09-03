<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\BentukModel as BentukPublikasi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BentukPublikasiQueryController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
    }
    public function rawQuery()
    {
        $result = BentukPublikasi::select('publikasi_bentuk.kd_bentuk_publikasi',
            'publikasi_bentuk.bentuk_publikasi',
            'publikasi_bentuk.uuid',
            'publikasi_bentuk_umum.bentuk_umum',
            'publikasi_bentuk_umum.kd_bentuk_umum')->
            join('publikasi_bentuk_umum', 'publikasi_bentuk_umum.id', '=', 'publikasi_bentuk.id_bentuk_umum')->
            where('publikasi_bentuk.flag_aktif', 1)->orderBy('publikasi_bentuk.bentuk_publikasi', 'ASC');
            
            return $result;
    }
    public function getAll()
    {
        $data = [];
        $status = 200;
        try {
            //SET ORDER BENTUK PUBLIKASI
            $data = $this->rawQuery()->where('publikasi_bentuk_umum.kd_bentuk_umum','!=','SIT')->get();
            // $sitasi = $this->rawQuery()->where('publikasi_bentuk.kd_bentuk_publikasi', 'SIT-2')->get();
            $sitasi = $this->rawQuery()->where('publikasi_bentuk_umum.kd_bentuk_umum', 'SIT')->get();

            // dd($sitasi);
            $info = 'data berhasil diambil';
            $respon = [
                'status' => $status,
                'data' => $data,
                'sitasi' => $sitasi,
                'info' => $info,
            ];
        } catch (Exception $e) {
            Log::error('Exception on getting form data: ' . $e);
            // $info = 'Gagal mengambil data';
            $info = $e->getMessage();
            $status = 400;
            $respon = [
                'info' => 'Gagal mengambil data',
            ];
        }
        return response()->json($respon, $status);
    }

    public function getAllAdmin(Request $request)
    {
        $data = [];
        $status = 500;
        try {
            $result = BentukPublikasi::select('publikasi_bentuk.*',
                'publikasi_bentuk_umum.bentuk_umum')->
                join('publikasi_bentuk_umum', 'publikasi_bentuk_umum.id', '=', 'publikasi_bentuk.id_bentuk_umum')->
                where('publikasi_bentuk.flag_aktif', 1);
            if ($request->input('jenis_pengajuan')) {
                if ($request->input('jenis_pengajuan') == 'remunerasi') {
                    $result->where('flag_remunerasi', 1);
                } else if ($request->input('jenis_pengajuan') == 'pak') {
                    $result->where('flag_pak', 1);
                } else if ($request->input('jenis_pengajuan') == 'bkd') {
                    $result->where('flag_bkd', 1);
                }
            }
            $data = $result->get();
            $status = 200;
            $info = 'data berhasil diambil';
        } catch (QueryException $e) {
            $info = 'Gagal mengambil data';
            $status = 400;
        }
        $respon = [
            'status' => $status,
            'data' => $data,
            'info' => $info,
        ];
        return response()->json($respon, $status);
    }
}
