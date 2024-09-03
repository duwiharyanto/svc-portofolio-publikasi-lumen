<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\InstansiPublikasiModel as instansi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstansiQueryController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function index()
    {
        return 'instansi';
    }

    public function getInstansiAll(Request $request)
    {
        try {

            $instansiAll = instansi::where('flag_aktif', true)->where('nama_instansi', '!=', 'Lainnya')->get();
            if ($request->input('cari')) {
                $instansiAll = instansi::where('flag_aktif', true)->where('nama_instansi', '!=', 'Lainnya')->where('nama_instansi', 'like', "%{$request->input("cari")}%")->get();
            }
            if ($request->input('kd_negara')) {
                $instansiAll = instansi::where('flag_aktif', true)->where('kd_negara', '!=', 'Lainnya')->where('kd_negara', $request->input("kd_negara"))->get();
            }
            $instansiAll = $instansiAll;
            $response = ($instansiAll->count() !== 0) ? response()->json([
                'data' => $instansiAll,
            ], 200) : response()->json([
                'data' => [],
            ], 200);
        } catch (Exception $e) {
            $response = response()->json([
                'instansi' => 'error',
                'message' => 'Error on get Posts Data! There is an error on the server, please try again later.',
            ], 500);
        }
        return $response;
    }
    public function addInstansi(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = [
                'nama_instansi' => $request->input('instansi'),
                'kd_negara' => $request->input('kd_negara'),
                'user_input' => 'system',
                'flag_aktif' => true,
            ];
            $req = instansi::create($data);
            if (!$req) {
                throw new Exception("Simpan data instansi gagal");
            }
            $response = response()->json([
                'status' => '201',
                'message' => 'Simpan data berhasil',
                'data' => [
                    'instansi' => $data['nama_instansi'],
                ],
            ], 201);
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            DB::rollBack();
            $response = response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
        return $response;

    }
    public function getVerifikasiInstansi(Request $request)
    {
        try {
            DB::beginTransaction();
            $datas = instansi::where('flag_ajuan', true)->get();
            // dd($req);
            if (!$datas) {
                throw new Exception("Instansi tidak ditemukan");
            }
            DB::commit();
            $response = response()->json([
                'status' => '200',
                'data' => $datas,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            $response = response()->json([
                'status' => '400',
                'message' => 'Menampilkan data bermasalah',
                'stack_trace' => $e->getMessage() . ',line ' . $e->getLine(),
            ], 400);
        }
        return $response;
    }
    public function verifikasiInstansi(Request $request)
    {
        try {
            DB::beginTransaction();
            $uuid = $request->input('uuid');
            $diterima = $request->input('diterima');
            // dd($diterima);
            if (!$uuid) {
                throw new Exception("Data tidak ditemukan", 400);
            }
            // if (!$diterima) {
            //     throw new Exception("Data ditolak atau diterima ?", 400);
            // }
            if ($diterima) {
                $data = [
                    'flag_aktif' => true,
                    'flag_ajuan' => false,
                ];
            } else {
                $data = [
                    'flag_aktif' => false,
                    'flag_ajuan' => false,
                    'flag_ditolak' => true,
                ];
            }
            $datas = instansi::where('flag_ajuan', true)->update($data);
            if (!$datas) {
                throw new Exception("Instansi tidak ditemukan");
            }
            DB::commit();
            $response = response()->json([
                'status' => '200',
                'data' => $datas,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            $response = response()->json([
                'status' => '400',
                'message' => 'Update data bermasalah',
                'stack_trace' => $e->getMessage() . ',line ' . $e->getLine(),
            ], 400);
        }
        return $response;
    }
}
