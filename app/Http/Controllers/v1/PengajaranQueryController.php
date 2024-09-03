<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\PengajaranModel;
use App\Traits\MasterData;
use Exception;
use Illuminate\Http\Request;

class PengajaranQueryController extends Controller
{

    use MasterData;
    public function __construct()
    {
        // $this->middleware('auth');
    }
    protected function rawsql($pegawai_id, $status)
    {
        $raw_sql = PengajaranModel::select(
            'pengajaran.*',
            'ps.status',

        )->where('pengajaran.id_pegawai', $pegawai_id)->
            leftJoin('publikasi_status AS ps', 'ps.id', '=', 'id_pengajaran_status')->
            where('pengajaran.flag_aktif', true)->
            orderBy('pengajaran.tgl_update', 'desc');
        if ($status == 'usulan') {
            $raw_sql->where('ps.kd_status', 'USL');
        } else {
            $raw_sql->where('ps.kd_status', '!=', 'USL');
        }
        return $raw_sql;
    }
    public function getPengajaran(Request $request, $nik)
    {
        try {
            $pegawai = $this->pegawaibynik($nik);
            if (!$pegawai) {
                throw new Exception('NIK tidak ditemukan');
            }
            $pengajaran_nonusulan = $this->rawsql($pegawai->id, 'nonusulan');
            $pengajaran_usulan = $this->rawsql($pegawai->id, 'usulan');
            if ($request->input('matakuliah')) {
                $matakuliah = $request->input('matakuliah');
                $pengajaran_usulan = $pengajaran_usulan->where('pengajaran.matakuliah', 'like', "%{$matakuliah}%");
                $pengajaran_nonusulan = $pengajaran_nonusulan->where('pengajaran.matakuliah', 'like', "%{$matakuliah}%");

            }
            if ($request->input('semester')) {
                $semester = $request->input('semester');
                $pengajaran_usulan = $pengajaran_usulan->where('pengajaran.semester', 'like', "%{$semester}%");
                $pengajaran_nonusulan = $pengajaran_nonusulan->where('pengajaran.semester', 'like', "%{$semester}%");

            }
            $pengajaran = $pengajaran_usulan->get()->merge($pengajaran_nonusulan->get());
            $count = $pengajaran->count();
            $limit = $count;
            $limit = ($count < $limit) ? $count : $limit;
            $limit = ($request->input('limit')) ? (int) $request->input('limit') : $limit;
            $offset = ($request->input('offset')) ? (int) $request->input('offset') : 0;
            $pengajaran=$pengajaran->skip($offset)->take($limit)->values();
            $response = ($pengajaran->count() !== 0) ? response()->json([
                'count' => $count,
                'limit' => $limit,
                'offset' => $offset,
                'data' => $pengajaran,
            ], 200) : response()->json([
                'data' => [],
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $response = response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
        return $response;
    }
}
