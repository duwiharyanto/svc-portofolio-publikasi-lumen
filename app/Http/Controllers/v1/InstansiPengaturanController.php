<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\InstansiPublikasiModel as instansi;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;

class InstansiPengaturanController extends Controller 
{
    public function __construct()
    {
        
    }

    public function getAll(Request $request)
    {
        try {
            $results = instansi::where('flag_aktif', true)->get();
            if ($request->input('cari')) {
                $results = instansi::where('flag_aktif', true)->where('nama_instansi', '!=', 'Lainnya')->where('nama_instansi', 'like', "%{$request->input("cari")}%")->get();
            }
            if ($request->input('kd_negara')) {
                $results = instansi::where('flag_aktif', true)->where('kd_negara', '!=', 'Lainnya')->where('kd_negara', $request->input("kd_negara"))->get();
            }
            $results->makeHidden(['flag_ajuan', 'flag_ditolak']);
            $response = response()->json([
                'count' => $results->count(),
                'status' => 200,
                'message' => 'success',
                'data' => $results
            ], 200);
            return $response;
        } catch (Exception $e) {
            $response = response()->json([
                'instansi' => 'error',
                'message' => 'Error on get Posts Data! There is an error on the server, please try again later.',
            ], 500);
            return $response;
        }
    }

    public function addData(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = [
                'nama_instansi' => $request->input('instansi'),
                'kd_negara' => $request->input('kd_negara') ?? null,
                'user_input' => 'system',
                'flag_aktif' => true,
            ];
            $req = instansi::create($data);
            if (!$req) {
                throw new Exception("Simpan data instansi gagal");
            }
            DB::commit();
            $response = response()->json([
                'status' => '200',
                'message' => 'Simpan data berhasil',
                'data' => [
                    'instansi' => $data['nama_instansi'],
                ],
            ], 200);
            return $response;
        } catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateData(Request $request, $uuid)
    {
        try {
            DB::beginTransaction();
            $data = [
               'nama_instansi' => $request->input('instansi')
            ];
            $updataData = instansi::where('uuid', $uuid)->update($data);
            if (!$updataData){
                throw new Exception("Update data instansi gagal");
            }
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil update data',
            ]);
        }  catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function updateFlag(Request $request, $uuid)
    {
        try {
            DB::beginTransaction();
            $data = [
                'flag_perguruan_tinggi' => $request->input('flag_perguruan_tinggi') ?? 0
            ];
            $updataData = instansi::where('uuid', $uuid)->update($data);
            if (!$updataData){
                throw new Exception("Update data instansi gagal");
            }
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil update data',
            ]);
        }  catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deleteData(Request $request, $uuid)
    {
        try {
            DB::beginTransaction();
            $data = [
                'flag_aktif' => false,
                // 'alasan_dihapus' => $request->input('alasan_dihapus')
            ];
            instansi::where('uuid', $uuid)->update($data);
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil hapus data',
            ]);
        } catch (Exception $e){
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}