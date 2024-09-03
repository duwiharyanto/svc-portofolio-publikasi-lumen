<?php

namespace App\Http\Controllers\v1\Sitasi;

use App\Http\Controllers\Controller;
use App\Models\BentukModel as BentukPublikasi;
use App\Models\PegawaiModel as Pegawai;
use App\Models\RiwayatPerbaikanSitasiModel as RiwayatPerbaikan;
use App\Models\SitasiFileModel as SitasiFile;
use App\Models\SitasiMetaModel as SitasiMeta;
use App\Models\SitasiModel as Sitasi;
use App\Models\StatusModel as StatusPublikasi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use \App\Http\Controllers\FileController;

class SitasiQueryController extends Controller
{
    use FileController;
    public function __construct()
    {
    }
    public function rawQuery($pegawaiId)
    {
        $query = Sitasi::select('publikasi_sitasi.id',
            'p.value as judul_karya',
            'publikasi_sitasi.id_pegawai',
            'publikasi_sitasi.sitasi_link',
            'publikasi_sitasi.sitasi_total',
            'publikasi_sitasi.sitasi_tahun',
            'publikasi_sitasi.sitasi_jenis',
            'publikasi_sitasi.flag_aktif',
            'ps.kd_status',
            'ps.status',
            'pb.bentuk_publikasi',
            'pb.kd_bentuk_publikasi',
            'pb.uuid as uuid_bentuk_publikasi',
            'publikasi_sitasi.uuid as uuid_sitasi',
            'p.uuid as uuid_judul_karya'
        )->
            leftJoin('publikasi as p', 'p.id', '=', 'publikasi_sitasi.id_karya')->
            join('publikasi_status as ps', 'ps.id', '=', 'publikasi_sitasi.id_publikasi_status')->
            join('publikasi_bentuk as pb', 'pb.id', '=', 'publikasi_sitasi.id_publikasi_bentuk')->
            where('publikasi_sitasi.flag_aktif', true)->
            where('publikasi_sitasi.id_pegawai', $pegawaiId)->
            orderBy('pb.kd_bentuk_publikasi', 'ASC')->orderBy('publikasi_sitasi.row_id', 'DESC');
        return $query;
    }
    public function sitasi(Request $request, $nik)
    {
        try {
            DB::beginTransaction();
            $pegawai = Pegawai::where('nik', $nik)->first();
            // ! Cek Pegawai
            if (!$pegawai) {
                throw new Exception("Pegawai tidak ditemukan", 400);
            }
            //! Cek sitasi untuk membuat data awalan
            $this->cekSitasi($pegawai);
            $dataSitasi = $this->rawQuery($pegawai->id);
            // ! Handle limit offset
            $count  = collect($dataSitasi->get())->count();
            $limit  = ($request->input('limit')) ? (int) $request->input('limit') : $count;
            $offset = ($request->input('offset')) ? (int) $request->input('offset') : 0;
            // ! Data sitasi yang ditampilkan
            $dataSitasi = $dataSitasi->skip($offset)->take($limit)->get();

            foreach ($dataSitasi as $value) {
                $sitasiMeta = SitasiMeta::select('publikasi_sitasi_meta.*', 'ps.kd_status', 'ps.status')->join('publikasi_status as ps', 'ps.id', '=', 'publikasi_sitasi_meta.id_publikasi_status')->
                    where('publikasi_sitasi_meta.id_sitasi', $value->id)->
                    where('publikasi_sitasi_meta.flag_aktif', true)->
                    orderBy('sitasi_tahun', 'DESC')->get();
                $sitasiFile     = SitasiFile::select('path_file', 'nama_file')->where('id_sitasi', $value->id)->where('flag_aktif', true)->first();
                $riwayatCatatan = RiwayatPerbaikan::where('id_publikasi_sitasi', $value->id)->where('flag_aktif', true)->get();

                foreach ($sitasiMeta as $indexSitasiMeta => $valueSitasiMeta) {
                    $sitasiMetaFile                                      = SitasiFile::select('path_file', 'nama_file')->where('id_sitasi_meta', $valueSitasiMeta->id)->where('flag_aktif', true)->first();
                    $pathFileSitasiMeta                                  = $sitasiMetaFile->path_file ?? null;
                    $sitasiMeta[$indexSitasiMeta]['gambar_halaman_file'] = $pathFileSitasiMeta ? $this->getFile($pathFileSitasiMeta)['presignedUrl'] : null;
                    $sitasiMeta[$indexSitasiMeta]['gambar_halaman_path'] = $pathFileSitasiMeta;
                    $sitasiMeta[$indexSitasiMeta]['gambar_halaman']      = $sitasiMetaFile->nama_file ?? null;
                }
                $pathFile                     = $sitasiFile->path_file ?? null;
                $value['gambar_halaman']      = $pathFile ? $this->getFile($pathFile)['presignedUrl'] : null;
                $value['gambar_halaman_file'] = $sitasiFile->nama_file ?? null;
                $cekFlagRemunerasi            = SitasiMeta::where('flag_remunerasi', 1)->where('id_sitasi', $value->id)->get();
                $value['flag_remunerasi']     = $cekFlagRemunerasi->count() > 0 ? 1 : 0;
                $value['riwayat_perbaikan']   = $riwayatCatatan;
                $value['sitasi_meta']         = $sitasiMeta;
                // ! HANDLING UNTUK JUDUL SITASI SCOPUS KARENA SELALU KOSONG
                if (is_null($value['judul_karya'])) {
                    $value['judul_karya'] = $value['kd_bentuk_publikasi'] === 'SIT-1' ? 'Sitasi Scopus/Wos' : 'Sitasi Google Scholar';
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'success',
                'count'   => $count,
                'limit'   => $limit,
                'offset'  => $offset,
                'data'    => $dataSitasi,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage() . ' line :' . $e->getLine());
            $response = [
                'info' => 'Menampilkan data gagal',
            ];
            return response()->json($response, 400);
        }
    }
    public function cekSitasi($pegawai)
    {
        $kodeScopus  = 'SIT-1';
        $kodeGscolar = 'SIT-2';
        $statusDraf  = StatusPublikasi::where('kd_status', 'DRF')->first();
        //! Cek sitasi gScolar dan scopus
        $cekScopus   = $this->rawQuery($pegawai->id)->where('pb.kd_bentuk_publikasi', $kodeScopus)->get();
        $cekGScholar = $this->rawQuery($pegawai->id)->where('pb.kd_bentuk_publikasi', $kodeGscolar)->get();
        $sitasi      = [
            'id_pegawai'          => $pegawai->id,
            'id_publikasi_status' => $statusDraf->id,
            'sitasi_jenis'        => 'Individual',
            'sitasi_total'        => 0,
            'sitasi_link'         => null,
            'sitasi_tahun'        => null,
            'user_input'          => $pegawai->nik,
            'tgl_pengajuan'       => date('Y-m-d H:i:s'),
            'tgl_validasi'        => date('Y-m-d H:i:s'),
        ];
        if ($cekGScholar->count() == 0) {
            $bentukPublikasi               = BentukPublikasi::Where('kd_bentuk_publikasi', $kodeGscolar)->first();
            $sitasi['id_publikasi_bentuk'] = $bentukPublikasi->id;
            Sitasi::create($sitasi);
        }
        if ($cekScopus->count() == 0) {
            $bentukPublikasi               = BentukPublikasi::Where('kd_bentuk_publikasi', $kodeScopus)->first();
            $sitasi['id_publikasi_bentuk'] = $bentukPublikasi->id;
            Sitasi::create($sitasi);
        }
    }
    //! SUDAH TIDAK DIGUNAKAN
    // public function halamanUtama(Request $request, $nik)
    // {
    //     try {
    //         $pegawai = Pegawai::where('nik', $nik)->first();
    //         // ! Cek Pegawai
    //         if (!$pegawai) {
    //             throw new Exception("Pegawai tidak ditemukan", 400);
    //         }
    //         // ! Ambil data sitasi per pegawai
    //         $dataSitasi  = $this->rawQuery($pegawai->id);
    //         $cekScopus   = $this->rawQuery($pegawai->id)->where('pb.kd_bentuk_publikasi', 'SIT-1')->get();
    //         $cekGScholar = $this->rawQuery($pegawai->id)->where('pb.kd_bentuk_publikasi', 'SIT-2')->get();

    //         // ! Handle pencarian data
    //         if ($request->input('cari')) {
    //             $cari       = $request->input('cari');
    //             $dataSitasi = $dataSitasi->Where(function ($query) use ($cari) {
    //                 $query->where('p.value', 'like', '%' . $cari . '%')->orWhere('publikasi_sitasi.sitasi_jenis', 'like', '%' . $cari . '%');
    //             });
    //         }
    //         if ($request->input('uuid_bentuk')) {
    //             $bentukPublikasi = BentukPublikasi::where('uuid', $request->input('uuid_bentuk'))->first();
    //             $dataSitasi      = $dataSitasi->Where('publikasi_sitasi.id_publikasi_bentuk', $bentukPublikasi->id);
    //         }
    //         if ($request->input('status_sitasi')) {
    //             $statusPublikasi = StatusPublikasi::where('uuid', $request->input('status_sitasi'))->first();
    //             $dataSitasi      = $dataSitasi->Where('publikasi_sitasi.id_publikasi_status', $statusPublikasi->id);
    //         }
    //         if ($request->input('tahun')) {
    //             $dataSitasi = $dataSitasi->whereYear('publikasi_sitasi.sitasi_tahun', $request->input('tahun'));
    //         }
    //         // ! Handle limit offset
    //         $count  = collect($dataSitasi->get())->count();
    //         $limit  = ($request->input('limit')) ? (int) $request->input('limit') : $count;
    //         $offset = ($request->input('offset')) ? (int) $request->input('offset') : 0;
    //         // ! Data sitasi yang ditampilkan
    //         $dataSitasi = $dataSitasi->skip($offset)->take($limit)->get();

    //         foreach ($dataSitasi as $value) {
    //             $sitasiMeta = SitasiMeta::select('publikasi_sitasi_meta.*', 'ps.kd_status', 'ps.status')->join('publikasi_status as ps', 'ps.id', '=', 'publikasi_sitasi_meta.id_publikasi_status')->
    //                 where('publikasi_sitasi_meta.id_sitasi', $value->id)->
    //                 where('publikasi_sitasi_meta.flag_aktif', true)->
    //                 orderBy('sitasi_tahun', 'DESC')->get();
    //             $sitasiFile     = SitasiFile::select('path_file', 'nama_file')->where('id_sitasi', $value->id)->where('flag_aktif', true)->first();
    //             $riwayatCatatan = RiwayatPerbaikan::where('id_publikasi_sitasi', $value->id)->where('flag_aktif', true)->get();

    //             foreach ($sitasiMeta as $indexSitasiMeta => $valueSitasiMeta) {
    //                 $sitasiMetaFile                                      = SitasiFile::select('path_file', 'nama_file')->where('id_sitasi_meta', $valueSitasiMeta->id)->where('flag_aktif', true)->first();
    //                 $pathFileSitasiMeta                                  = $sitasiMetaFile->path_file ?? null;
    //                 $sitasiMeta[$indexSitasiMeta]['gambar_halaman_file'] = $pathFileSitasiMeta ? $this->getFile($pathFileSitasiMeta)['presignedUrl'] : null;
    //                 $sitasiMeta[$indexSitasiMeta]['gambar_halaman_path'] = $pathFileSitasiMeta;
    //                 $sitasiMeta[$indexSitasiMeta]['gambar_halaman']      = $sitasiMetaFile->nama_file ?? null;
    //             }
    //             $pathFile                     = $sitasiFile->path_file ?? null;
    //             $value['gambar_halaman']      = $pathFile ? $this->getFile($pathFile)['presignedUrl'] : null;
    //             $value['gambar_halaman_file'] = $sitasiFile->nama_file ?? null;
    //             $cekFlagRemunerasi            = SitasiMeta::where('flag_remunerasi', 1)->where('id_sitasi', $value->id)->get();
    //             $value['flag_remunerasi']     = $cekFlagRemunerasi->count() > 0 ? 1 : 0;
    //             $value['riwayat_perbaikan']   = $riwayatCatatan;
    //             $value['sitasi_meta']         = $sitasiMeta;
    //             // ! HANDLING UNTUK JUDUL SITASI SCOPUS KARENA SELALU KOSONG
    //             if (is_null($value['judul_karya'])) {
    //                 $value['judul_karya'] = $value['kd_bentuk_publikasi'] === 'SIT-1' ? 'Sitasi Scopus/Wos' : 'Sitasi Google Scholar';
    //             }
    //         }
    //         if ($cekGScholar->count() == 0) {
    //             $bentukPublikasi = BentukPublikasi::Where('kd_bentuk_publikasi', 'SIT-2')->first();
    //             $arrGScholar     = [
    //                 "judul_karya"           => 'Sitasi Google Scholar',
    //                 "link_sitasi"           => null,
    //                 "sitasi_total"          => null,
    //                 "sitasi_tahun"          => null,
    //                 "sitasi_jenis"          => 'Individual',
    //                 "kd_status"             => 'DRF',
    //                 "status"                => 'Draf',
    //                 "bentuk_publikasi"      => 'Sitasi Google Scholar',
    //                 "kd_bentuk_publikasi"   => 'SIT-2',
    //                 'uuid_bentuk_publikasi' => $bentukPublikasi->uuid,
    //                 "uuid_sitasi"           => null,
    //                 "uuid_judul_karya"      => null,
    //                 "gambar_halaman"        => null,
    //                 "gambar_halaman_file"   => null,
    //                 "flag_aktif"            => 1,
    //                 "flag_remunerasi"       => 0,
    //                 "riwayat_perbaikan"     => [],
    //                 "sitasi_meta"           => [],
    //             ];
    //             $dataSitasi = collect([$arrGScholar])->merge($dataSitasi);
    //         }
    //         if ($cekScopus->count() == 0) {
    //             $bentukPublikasi = BentukPublikasi::Where('kd_bentuk_publikasi', 'SIT-1')->first();
    //             $arrScopus       = [
    //                 "judul_karya"           => 'Sitasi Scopus/Wos',
    //                 "link_sitasi"           => null,
    //                 "sitasi_total"          => null,
    //                 "sitasi_tahun"          => null,
    //                 "sitasi_jenis"          => 'Individual',
    //                 "kd_status"             => 'DRF',
    //                 "status"                => 'Draf',
    //                 "bentuk_publikasi"      => 'Sitasi Scopus/Wos',
    //                 "kd_bentuk_publikasi"   => 'SIT-1',
    //                 'uuid_bentuk_publikasi' => $bentukPublikasi->uuid,
    //                 "uuid_sitasi"           => null,
    //                 "uuid_judul_karya"      => null,
    //                 "gambar_halaman"        => null,
    //                 "gambar_halaman_file"   => null,
    //                 "flag_aktif"            => 1,
    //                 "flag_remunerasi"       => 0,
    //                 "riwayat_perbaikan"     => [],
    //                 "sitasi_meta"           => [],
    //             ];
    //             $dataSitasi = collect([$arrScopus])->merge($dataSitasi);
    //         }
    //         $dataSitasi = $dataSitasi->toArray();
    //         Log::info("Menampilkan sitasi $nik", $dataSitasi);
    //         $response = [
    //             'info'   => 'sukses',
    //             'count'  => $count,
    //             'limit'  => $limit,
    //             'offset' => $offset,
    //             'data'   => $dataSitasi,
    //         ];
    //         return response()->json($response, 200);

    //     } catch (Exception $e) {
    //         Log::error($e->getMessage() . ' line :' . $e->getLine());
    //         $response = [
    //             //'message' => $e->getMessage() . ' line :' . $e->getLine(),
    //             'info' => 'Menampilkan data gagal',
    //         ];
    //         return response()->json($response, 400);
    //     }
    // }
}
