<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\PengajaranModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Exception;

class PengajaranCommandController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function delete($uuid)
    {
        try {
            DB::beginTransaction();
            $pengajaran = PengajaranModel::where('uuid', $uuid)->update([
                'flag_aktif'=>false,
            ]);
            DB::commit();
            if(!$pengajaran) throw new Exception("Hapus data gagal");
            $response = response()->json([
                'status' => 200,
                'message'=>'Data berhasil dihapus',
            ], 200);
        } catch (QueryException $e){
            DB::rollBack();
            return response()->json([
                'status'=>400,
                'message'=> $e->getMessage(),
            ],400);
        } catch (Exception $e) {
            DB::rollBack();
            $response = response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
        return $response;
    }
}
