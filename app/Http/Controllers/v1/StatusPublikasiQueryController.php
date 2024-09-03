<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use Illuminate\Http\Request;
use App\Models\StatusModel AS StatusPublikasi;

class StatusPublikasiQueryController extends Controller {

    public function __construct() {
        // $this->middleware('auth');
    }
    public function getAll(){
        $data=[];
        $status=500;
        try {
            $get_data = StatusPublikasi::where('flag_aktif',true)->get();
            $data=$get_data;
            $status=200;
            $info='data berhasil diambil';
        }catch (QueryException $e) {
            $info='Gagal mengambil data';
            $status=400;
        }
        $respon=[
            'status' => $status,
            'data'=>$data,
            'info'=>$info,
        ];
        return response()->json($respon, $status);
    }
}