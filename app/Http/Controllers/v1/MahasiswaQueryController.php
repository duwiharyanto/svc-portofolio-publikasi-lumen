<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Exception;
use App\Models\MahasiswaModel as mahasiswa;

use Illuminate\Http\Request;

class MahasiswaQueryController extends Controller {

    public function __construct() {
        // $this->middleware('auth');
    }

    public function getAll(Request $request) {
        $data = [];
        $status = 200;
        try {
            $mahasiswas = DB::table('mahasiswa_rekap')->select('nim', 'nama_mahasiswa', 'uuid');
            $data = $mahasiswas->get();
            $status = 200;
            $info = 'Data berhasil diambil';
        } catch (QueryException $e) {
            $info = $e->getMessage();
            $status=404;
        }
        $respon = [
            'status' => $status,
        ];
        if($status == 200){
            $respon['data'] = $data;
        }else{
            $respon['message'] = $info;
        }
        return response()->json($respon, $status);
    }

    public function getBySearchKey(Request $request) {
        $data = [];
        $status = 200;
        try {
            $mahasiswa = mahasiswa::select('*', 'nim as nik', 'nim as nomor_induk');
            if ($request->input('search_key')) {
                $key = $request->input('search_key');
                $mahasiswa->where('nama_mahasiswa', 'like', "%$key%")->orWhere('nim', 'like', "%$key%");
            }
            $data = $mahasiswa->take(25)->get();
            $status = 200;
            $info = 'Data berhasil diambil';
        } catch (Exception $e) {
            Log::error('Exception on getting student data: ' . $e);
            $info = $e->getMessage();
            $status = 404;
        }
        $respon = [
            'status' => $status,
            'data' => $data,
            'info' => $info,
        ];
        return response()->json($respon, $status);
    }

}