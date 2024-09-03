<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\InstansiPublikasiModel as instansi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeanggotaanExternalController extends Controller
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

            $lainnya = instansi::where('flag_aktif', true)->where('nama_instansi', 'Lainnya')->get();
            $instansiAll = $instansiAll->merge($lainnya);
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

}
