<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

use Illuminate\Http\Request;
use App\Models\PegawaiModel AS Pegawai;

class PegawaiCommandController extends Controller {

    public function __construct() {
        // $this->middleware('auth');
    }

    public function updateByNIK(Request $request, $nik){
        $message=null;
        $status=400;
        try {
            //dd($request->all());
            $data_update=[
                'sinta_id' => $request->input('sinta_id'),
                'googlescholar_id'=>$request->input('googlescholar_id'),
                'scopus_id'=>$request->input('scopus_id'),
                'user_update' => $nik,
            ];
            $result=Pegawai::where('nik',$nik)->update($data_update);
            if($result){
                $status=200;
                $message='Update berhasil';
            }else{
                throw new Exception("Update gagal");
            }

        }catch(QueryException $e) {
            $message='Query error ='.$e->getMessage();
            //$message = 'Database sedang sibuk';
            $status=400;
        }catch(Exception $e){
            $message=$e->getMessage();
            $status=400;
        }
        $respon=[
            'status' => $status,
            'message'=>$message,
        ];
        return response()->json($respon, $status);
    }


}