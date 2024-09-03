<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use Illuminate\Http\Request;
use App\Models\JenisModel AS JenisPublikasi;

class MatakuliahQueryController extends Controller {

    public function __construct() {
        // $this->middleware('auth');
    }
    public function getAll(Request $request){
        $data=[];
        $status=500;
        try {
            $jenispublikasi = DB::table('matakuliah')->
            select('matakuliah.nama_matakuliah',
            'matakuliah.uuid')->
            join('kurikulum','kurikulum.id','=','matakuliah.id_kurikulum')->
            join('organisasi','organisasi.id','=','kurikulum.id_organisasi')->
            where('matakuliah.flag_aktif',true);
            if($request->input('cari')){
                $key=$request->input('cari');
                $jenispublikasi->where('nama_matakuliah','like',"%$key%");
            }
            $data=$jenispublikasi->get()->unique('nama_matakuliah')->values();
            $status=200;
            $info='Data berhasil diambil';
        }catch (QueryException $e) {
            $info='Gagal mengambil data';
            $status=400;
        }
        $respon=[
            'status' => $status,
            'data'=>$data,
        ];
        return response()->json($respon, $status);
    }
}
