<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use Illuminate\Http\Request;

class TahunPublikasiQueryController extends Controller {

    public function __construct() {
        // $this->middleware('auth');
    }
    public function getAll(){
        $data=[];
        $status=500;
        $firstYear=2010;
        try {
            $years = [];
            $thisYear = date('Y');
            for ($i = $firstYear; $i <= $thisYear; $i++) { 
                array_push($years, ['uuid' => $i, 'tahun' => $i]);
            }
            $data=array_reverse($years);
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