<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\BentukUmumModel as bentukUmum;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BentukUmumQueryController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function getAll(Request $request)
    {
        $data = [];
        $status = 200;
        try {
            $data = bentukUmum::where('flag_aktif', true)->where('kd_bentuk_umum', '!=', 'SIT')->get();
            $sitasi = bentukUmum::where('flag_aktif', true)->where('kd_bentuk_umum', 'SIT')->get();
            $info = 'data berhasil diambil';
            $respon = [
                'status' => $status,
                'data' => $data,
                'sitasi' => $sitasi,
                'info' => $info,
            ];

        } catch (QueryException $e) {
            // dd($e);
            $status = 400;
            $info = 'Gagal mengambil data';
            $respon = [
                'info' => 'Gagal mengambil data',
            ];
        }
        return response()->json($respon, $status);
    }

}
