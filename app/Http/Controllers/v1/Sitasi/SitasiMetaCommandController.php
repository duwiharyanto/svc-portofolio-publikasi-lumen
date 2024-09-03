<?php

namespace App\Http\Controllers\v1\Sitasi;

use App\Http\Controllers\Controller;
use App\Models\PublikasiModel as Publikasi;
use App\Models\SitasiMetaModel as SitasiMeta;
use App\Models\SitasiModel as Sitasi;
use App\Models\StatusModel as StatusPublikasi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SitasiMetaCommandController extends Controller
{
    public function __construct()
    {

    }
    public function generateId()
    {
        $data = [
            'id'   => DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
            'uuid' => Str::uuid()->toString(),
        ];
        return (object) $data;
    }
    public function sinkronSitasi($idSitasi)
    {
        $data       = SitasiMeta::orderBy('sitasi_tahun', 'DESC')->first();
        $dataSitasi = [
            'sitasi_tahun'        => date('Y', \strtotime($data->sitasi_tahun)),
            'id_publikasi_status' => $data->id_publikasi_status,
        ];
        $updateSitasi = Sitasi::where('id', $idSitasi)->update($dataSitasi);
        return $updateSitasi;
    }
    public function insert(Request $request)
    {
        try {
            DB::beginTransaction();
            //! Deklarasi variabel untuk kebutuhan sitasi
            $status          = 200;
            $uuidPublikasi   = Publikasi::where('uuid', $request->input('uuidKarya'))->first();
            $statusPublikasi = StatusPublikasi::where('kd_status', 'DVR')->first();
            $sitasi          = Sitasi::where('uuid', $request->input('uuidSitasi'))->first();
            $genID           = $this->generateId();
            //! Membuat payload data sitasi untuk disimpan
            $data = [
                'id'                  => $genID->id,
                'id_publikasi_status' => $statusPublikasi->id,
                'sitasi_tahun'        => $request->input('sitasimeta_tahun'),
                'sitasi_jumlah'       => $request->input('sitasimeta_jumlah'),
                'id_sitasi'           => $sitasi->id,
                'tgl_ajuan'           => date("Y-m-d H:i:s"),
            ];
            $insert = SitasiMeta::insert($data);
            if (!$insert) {
                throw new Exception("Simpan Sitasi Meta Gagal", 400);
            }
            //! Sinkron/Update data sitasi utama
            $sinkronSitasi = $this->sinkronSitasi($data['id_sitasi']);
            if (!$sinkronSitasi) {
                throw new Exception("Sinkron sitasi Gagal", 400);
            }
            DB::Commit();
            $response = [
                'info' => 'Data berhasil ditambahkan',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage() . ' line :' . $e->getLine());
            $status   = 400;
            $response = [
                'message' => $e->getMessage() . ' line :' . $e->getLine(),
                'info'    => 'Menampilkan data gagal',
            ];
        }
        return response()->json($response, $status);
    }
}
