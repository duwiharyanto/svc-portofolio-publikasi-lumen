<?php

namespace App\Http\Controllers\v1\Sitasi;

use App\Http\Controllers\Controller;
use App\Http\Resources\RemunerasiSitasiResource;
use App\Http\Resources\SitasiMetaResource;
use App\Http\Resources\SitasiResource;
use App\Models\BentukModel as BentukPublikasi;
use App\Models\PegawaiModel as Pegawai;
use App\Models\PublikasiModel as Publikasi;
use App\Models\SitasiFileModel as SitasiFile;
use App\Models\SitasiMetaModel as SitasiMeta;
use App\Models\SitasiModel as Sitasi;
use App\Models\StatusModel as StatusPublikasi;
use App\Traits\ValidasiTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;
use \App\Http\Controllers\FileController;

class SitasiCommandController extends Controller
{
    use ValidasiTrait, FileController;
    private $sinkronRemunerasi = true;
    private $debug             = true;
    public function __construct()
    {

    }
    public function rules()
    {
        $rules = [
            'uuid_judul_karya'             => 'required',
            'sitasi_total'                 => 'required',
            'sitasi_link'                  => 'required',
            'gambar_halaman'               => 'required|max:50000|mimes:jpg,png,jpeg,pdf',
            'sitasi_meta'                  => 'required',
            'sitasi_meta.*.sitasi_jumlah'  => 'required',
            'sitasi_meta.*.sitasi_tahun'   => 'required',
            'sitasi_meta.*.kd_status'      => 'nullable',
            //'sitasi_meta.*.gambar_halaman' => 'nullable|max:50000|mimes:jpg,png,jpeg,pdf',
            'sitasi_meta.*.gambar_halaman' => 'nullable',
        ];
        return $rules;
    }
    public function responseValidation($info, $detail = "")
    {
        $message = 'Isian yang diberikan tidak valid';
        if ($detail == "") {
            return new JsonResponse([
                'message' => $message,
                'info'    => $info,
            ], 400);
        } else {
            return new JsonResponse([
                'message' => $message,
                'info'    => $info,
                'detail'  => $detail,
            ], 400);
        }
    }
    public function updateRemunerasi(array $request)
    {
        $sitasi = $request; //AMBIL DATA SITASI
        if ($sitasi) {
            //JIKA DATA STATUS DIVERIVIKASI
            $uri      = 'submission/fixing-portofolio-sitasi';
            $base_url = "http://" . env('REMUNERASI_DATA_API_URL') . "/private/api/v1/";
            $response = Http::withOptions(
                [
                    'base_uri' => $base_url,
                ]
            )->PUT($uri, $sitasi);
            $statusSinkron   = $response->status();
            $responseSinkron = $response->json();
            Log::info('sinkron sitasi remunerasi ' . $statusSinkron);
            //JIKA 200 UPDATE FLAG PERBAIKAN REMUN KE 0
            if ($statusSinkron == 200) {
                // Log::info('Data ', $sitasi);
                foreach ($sitasi['sitasi_meta'] as $index => $row) {
                    $dtSitasiMeta = [
                        'flag_tolak_remunerasi' => false, // BELUM DIPAKAI
                        'flag_perbaikan_remunerasi' => false,
                        'catatan_remunerasi'    => null,
                    ];
                    $updateSitasiMeta = DB::table('publikasi_sitasi_meta')->where('uuid', $row['uuid'])->update($dtSitasiMeta);
                    Log::info('Update sitasi sinkron remunerasi ' . $row['uuid'], $dtSitasiMeta);
                }
            }
            Log::info($responseSinkron);
        }
        $data = [
            'status'   => $statusSinkron,
            'response' => $responseSinkron,
        ];
        return $data;
    }
    public function generateId()
    {
        $data = [
            'id'   => DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
            'uuid' => Str::uuid()->toString(),
        ];
        return (object) $data;
    }
    public function insert(Request $request, $nik)
    {
        // ! Simpan dulu data sitasi meta baru simpan sitasi utama
        try {
            DB::beginTransaction();
            // SET PROPERTY DEFAULT
            // $nik               = ($nik == 'yudhistira') ? '075230424' : $nik;
            $status            = 200;
            $statusSitasiUtama = StatusPublikasi::where('kd_status', $request->input('kd_status'), 'DRF')->first();
            $statusDraf        = StatusPublikasi::where('kd_status', 'DRF')->first();
            $msgSuccess        = 'Data berhasil ditambahkan';
            $pegawai           = Pegawai::where('nik', $nik)->first();
            $bentukSitasi      = BentukPublikasi::where('kd_bentuk_publikasi', $request->input('kd_bentuk_publikasi'))->first();
            $idSitasi          = $this->generateId()->id;
            //! Handel update data(ada UUID)
            if ($request->input('sitasi_uuid')) {
                $masterSitasi = Sitasi::where('uuid', $request->input('sitasi_uuid'))->first();
                //! handel sitasi jika tidak ditemukan
                if (!$masterSitasi) {
                    return response()->json(['meesage' => 'Data sitasi tidak ditemukan'], 400);
                }
                $idSitasi = $masterSitasi->id;
            }
            //! Sudah tidak digunakan
            $publikasi = Publikasi::where('uuid', $request->input('uuid_judul_karya'))->first();
            $nik       = $nik;

            // SET VALIDASI UNTUK UPDATE
            $rules = $this->rules();
            if ($request->isMethod('put')) {
                $rules = collect($rules)->except(['gambar_halaman', 'uuid_judul_karya', 'sitasi_meta.*.gambar_halaman'])->toArray();
            }
            // MENJALANKAN VALIDASI
            $validasi = $this->validation($request->all(), $rules);
            if ($validasi != null) {
                return $this->responseValidation($validasi);
            }
            $sitasiMeta           = [];
            $updateSitasiMeta     = [];
            $sitasiMetaFile       = [];
            $updateSitasiMetaFile = [];
            //! Nama prop untuk file disitasi meta
            // $fileNameProp='gambar_halaman';
            $fileNameProp = 'gambar_halaman_file';
            //! Properti untuk menampung path file untuk sinkron remunerasi
            $collectBerkas = [];

            // HANDLING ARRAY SITASI META/LOOP
            // ! Sitasi Meta
            foreach ($request->input('sitasi_meta') as $index => $row) {
                //! CEK FLAG AKTIF, BERNILAI 0 JIKA DIHAPUS
                if ($row['flag_aktif']) {
                    $idSitasiMeta     = $this->generateId();
                    $statusSitasiMeta = StatusPublikasi::where('kd_status', $row['kd_status'])->first();
                    // ! Update Sitasi Meta
                    if ($row['uuid']) {
                        $updateSitasiMeta           = $row;
                        $sitasiMeta['sitasi_tahun'] = $row['sitasi_tahun']; //
                        $sitasiMeta['tgl_ajuan']    = date("Y-m-d H:i:s");
                        $getSitasiMeta              = SitasiMeta::where('uuid', $row['uuid'])->first();
                        $updateSitasiMeta           = collect($updateSitasiMeta)->only([
                            'sitasi_jumlah',
                            'sitasi_tahun',
                            'tgl_ajuan',
                        ])->toArray();
                        SitasiMeta::where('id', $getSitasiMeta->id)->update($updateSitasiMeta);
                        //! handel upload file sitasi meta

                        if ($request->hasFile('sitasi_meta.' . $index . ".$fileNameProp")) {
                            $sitasiLama = SitasiMeta::select('publikasi_sitasi_meta.*', 'psf.path_file')->leftJoin('publikasi_sitasi_file As psf', 'psf.id_sitasi_meta', '=', 'publikasi_sitasi_meta.id')->where('publikasi_sitasi_meta.uuid', $row['uuid'])->first();
                            $namaFile   = date("YmdHis") . '-' . $sitasiLama->uuid;
                            //UPLOAD FILE
                            $file             = $request->file('sitasi_meta.' . $index . ".$fileNameProp");
                            $fileTanpaExtensi = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            $namaFile         = $namaFile . '-' . str_replace([' ', '-'], '_', $fileTanpaExtensi);
                            $uploadFileSitasi = $this->uploadFile('sitasi', $nik, $namaFile, $file)['path'];
                            array_push($collectBerkas, $uploadFileSitasi);
                            $dataSitasiFile = [
                                'nama_file' => $file->getClientOriginalName(),
                                'path_file' => $uploadFileSitasi,
                                'id_sitasi' => $idSitasi,
                            ];
                            //UPDATE DATA JIKA TIDAK ADA FILE SEBELUMNYA
                            if ($sitasiLama->path_file) {
                                $this->deleteFile($sitasiLama->path_file);
                                SitasiFile::where('id_sitasi_meta', $sitasiLama->id)->update($dataSitasiFile);
                            } else {
                                $dataSitasiFile['id_sitasi_meta'] = $sitasiLama->id;
                                SitasiFile::insert($dataSitasiFile);
                            }
                            array_push($collectBerkas, $row['gambar_halaman_path']);
                        } else {
                            array_push($collectBerkas, $row['gambar_halaman_path']);
                        }
                    } else {
                        //! TAMBAH SITASI META
                        $sitasiMeta                        = $row;
                        $sitasiMeta['id']                  = $idSitasiMeta->id;
                        $sitasiMeta['uuid']                = $idSitasiMeta->uuid;
                        $sitasiMeta['id_publikasi_status'] = $statusSitasiMeta->id;
                        $sitasiMeta['id_sitasi']           = $idSitasi;
                        $sitasiMeta['tgl_ajuan']           = date("Y-m-d H:i:s");
                        $sitasiMeta['sitasi_tahun']        = $row['sitasi_tahun']; //
                        $sitasiMeta                        = collect($sitasiMeta)->only([
                            'sitasi_jumlah',
                            'sitasi_tahun',
                            'id_publikasi_status',
                            'id_sitasi',
                            'tgl_ajuan',
                            'uuid',
                            'id',
                        ])->toArray();
                        SitasiMeta::insert($sitasiMeta);
                        // ! Upload File
                        if ($request->hasFile('sitasi_meta.' . $index . ".$fileNameProp")) {
                            $namaFile         = date("YmdHis") . '-' . $sitasiMeta['uuid'];
                            $file             = $request->file('sitasi_meta.' . $index . ".$fileNameProp");
                            $fileTanpaExtensi = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            $namaFile         = $namaFile . '-' . str_replace([' ', '-'], '_', $fileTanpaExtensi);
                            $uploadFileSitasi = $this->uploadFile('sitasi', $nik, $namaFile, $file)['path'];
                            $sitasiMetaFile   = [
                                'nama_file'      => $file->getClientOriginalName(),
                                'path_file'      => $uploadFileSitasi,
                                'id_sitasi_meta' => $sitasiMeta['id'],
                                'id_sitasi'      => $idSitasi,
                            ];
                            SitasiFile::insert($sitasiMetaFile);
                            array_push($collectBerkas, $uploadFileSitasi);
                        } else {
                            array_push($collectBerkas, $row['gambar_halaman_path']);
                        }
                    }
                } else {
                    $dataHapusSitasiMeta = [
                        'flag_aktif' => false,
                    ];
                    SitasiMeta::where('uuid', $row['uuid'])->update($dataHapusSitasiMeta);
                }

            }
            // ! Sitasi Utama
            // * Menganmbil data sitasi terbaru berdasarkan tahun
            $getSitasiMetaTahun = SitasiMeta::where('id_sitasi', $idSitasi)->where('flag_aktif', true)->orderBy('sitasi_tahun', 'DESC')->first(); //ambil meta terakhir berdaskran tanggal
            // ! Depreceated
            // $getSitasiMeta      = SitasiMeta::select()->
            //     join('publikasi_status as ps', 'ps.id', '=', 'publikasi_sitasi_meta.id_publikasi_status')->
            //     where('publikasi_sitasi_meta.id_sitasi', $idSitasi)->
            //     where('publikasi_sitasi_meta.flag_aktif', true)->
            //     whereNotIn('ps.kd_status', ['DVR', 'VAL'])->get()->count();
            // $statusDefaultSitasiUtama = 'DVR';
            // if ($getSitasiMeta != 0) {
            //     $statusDefaultSitasiUtama = 'PRO';
            // }
            // $statusSitasiUtama=StatusPublikasi::where('kd_status',$statusDefaultSitasiUtama)->first();
            $sitasi = [
                'id'                  => $idSitasi,
                'id_pegawai'          => $pegawai->id,
                'id_publikasi_status' => isset($getSitasiMetaTahun->sitasi_tahun) ? $statusSitasiUtama->id : $statusDraf->id,
                'id_publikasi_bentuk' => $bentukSitasi->id,
                'sitasi_jenis'        => 'individual',
                'sitasi_total'        => $request->input('sitasi_total'),
                'sitasi_link'         => $request->input('sitasi_link'),
                'sitasi_tahun'        => $getSitasiMetaTahun->sitasi_tahun ?? null,
                'uuid'                => $this->generateId()->uuid,
                //! Publikasi dari sitasi, deprecated
                'id_karya'            => $publikasi->id ?? null,
            ];
            //! Handel tanggal pengajuan, di isi ketika status diverifikasi(bukan draf)
            if ($statusSitasiUtama->kd_status != 'DRF') {
                $sitasi['tgl_pengajuan'] = date('Y-m-d H:i:s');
            }

            //! handel sitasi utama
            if ($request->input('sitasi_uuid')) {
                $dataSitasi = collect($sitasi)->only(['sitasi_total', 'sitasi_link', 'sitasi_tahun', 'id_publikasi_status', 'id_karya', 'tgl_pengajuan'])->toArray();
                Sitasi::where('uuid', $request->input('sitasi_uuid'))->update($dataSitasi);
                // ! Handel file disitasi utama sudah tidak dipakai diversi terbaru
                // if ($request->hasFile('gambar_halaman')) {
                //     $sitasiLama = Sitasi::select('publikasi_sitasi.*', 'psf.path_file')->leftJoin('publikasi_sitasi_file As psf', 'psf.id_sitasi', '=', 'publikasi_sitasi.id')->where('publikasi_sitasi.uuid', $request->input('sitasi_uuid'))->first();
                //     $namaFile   = date("YmdHis") . '-' . $sitasiLama->uuid;
                //     //! UPLOAD FILE
                //     $file             = $request->file('gambar_halaman');
                //     $fileTanpaExtensi = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                //     $namaFile         = $namaFile . '-' . str_replace([' ', '-'], '_', $fileTanpaExtensi);
                //     $uploadFileSitasi = $this->uploadFile('sitasi', $nik, $namaFile, $file)['path'];
                //     $dataSitasiFile   = [
                //         'nama_file' => $file->getClientOriginalName(),
                //         'path_file' => $uploadFileSitasi,
                //     ];
                //     if ($sitasiLama->path_file) {
                //         $this->deleteFile($sitasiLama->path_file);
                //         SitasiFile::where('id_sitasi', $sitasiLama->id)->update($dataSitasiFile);
                //     } else {
                //         $dataSitasiFile['id_sitasi'] = $sitasiLama->id;
                //         SitasiFile::insert($dataSitasiFile);
                //     }
                //     array_push($collectBerkas, $uploadFileSitasi);
                // }
            } else {
                // ! Handel file disitasi utama sudah tidak dipakai diversi terbaru
                // if ($request->hasFile('gambar_halaman')) {
                //     $namaFile         = date("YmdHis") . '-' . $sitasi['uuid'];
                //     $file             = $request->file('gambar_halaman');
                //     $fileTanpaExtensi = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                //     $namaFile         = $namaFile . '-' . str_replace([' ', '-'], '_', $fileTanpaExtensi);
                //     $uploadFileSitasi = $this->uploadFile('sitasi', $nik, $namaFile, $file)['path'];
                //     $dataSitasiFile   = [
                //         'nama_file' => $file->getClientOriginalName(),
                //         'path_file' => $uploadFileSitasi,
                //         'id_sitasi' => $sitasi['id'],
                //     ];
                //     SitasiFile::insert($dataSitasiFile);
                // }
                Sitasi::insert($sitasi);
            }
            // ! SINKRON REMUNERASI
            if ($this->sinkronRemunerasi && $request->input('sitasi_uuid')) {
                $dtSitasi = Sitasi::select('publikasi_sitasi.*', 'ps.kd_status')->
                    join('publikasi_status as ps', 'ps.id', '=', 'publikasi_sitasi.id_publikasi_status')->
                    where('publikasi_sitasi.uuid', $request->input('sitasi_uuid'))->first();

                if ($dtSitasi->kd_status == 'DVR') {
                    $dataSinkron              = $request->all();
                    $dataSinkron['id_sitasi'] = $dtSitasi->id;
                    //! Sinkron Berkas sitasi utama
                    $dataSinkron['path_file'] = $collectBerkas[0] ?? null;
                    $dataSinkron['url_file']  = count($collectBerkas) != 0 ? $this->getFile($collectBerkas[0])['plainUrl'] : null;
                    $dataSinkron              = Arr::except($dataSinkron, ['_method', 'gambar_halaman']);
                    foreach ($dataSinkron['sitasi_meta'] as $index => $row) {
                        $sitasiMeta                                                      = DB::table('publikasi_sitasi_meta')->where('uuid', $row['uuid'])->first();
                        $dataSinkron['sitasi_meta'][$index]['id_sitasi_meta']            = $sitasiMeta ? $sitasiMeta->id : null;
                        $dataSinkron['sitasi_meta'][$index]['flag_tolak_remunerasi']     = $sitasiMeta ? $sitasiMeta->flag_tolak_remunerasi : null;
                        $dataSinkron['sitasi_meta'][$index]['flag_perbaikan_remunerasi'] = $sitasiMeta ? $sitasiMeta->flag_perbaikan_remunerasi : null;
                        // $dataSinkron['sitasi_meta'][$index]['flag_perbaikan_remunerasi'] = $sitasiMeta ? $sitasiMeta->flag_perbaikan_remunerasi : null;
                        $file = $request->file("sitasi_meta.$index.gambar_halaman_file");
                        if ($file) {
                            $dataSinkron['sitasi_meta'][$index]['gambar_halaman'] = $file->getClientOriginalName();
                        }
                        $dataSinkron['sitasi_meta'][$index]['gambar_halaman_file'] = $collectBerkas[$index] ? $this->getFile($collectBerkas[$index])['plainUrl'] : null;
                    }
                    Log::info('data sinkron remunerasi ', $dataSinkron);
                    $sinkron = $this->updateRemunerasi($dataSinkron);
                    // dd($cek);
                    if ($sinkron['status'] != 200) {
                        $msgError = $sinkron['response'] ? $sinkron['response']['info'] : 'Sinkron remunerasi ' . $sinkron['status'];
                        throw new Exception($msgError, 400);
                    } else {
                        //MESSAGE DARI RESPONSE REMUNERASI
                        $msgSuccess = $sinkron['response']['info'];
                    }
                }
            }
            Log::info("Update sitasi $nik");
            DB::Commit();
            $response = [
                'message' => $msgSuccess,
            ];
            Log::info($msgSuccess);
            return response()->json($response, $status);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage() . ' line :' . $e->getLine());
            $status   = 400;
            $response = [
                'message' => getenv('APP_DEBUG') ? $e->getMessage() . ' line :' . $e->getLine() : 'Menambahkan data gagal',
            ];
            return response()->json($response, $status);
        }
    }

    public function delete(Request $request, $uuid)
    {
        try {
            $status = 200;
            $data   = [
                'flag_aktif'          => false,
                'catatan_penghapusan' => $request->input('catatan_penghapusan'),
            ];
            $sitasi           = Sitasi::where('uuid', $uuid)->first();
            $deleteSitasiMeta = SitasiMeta::where('id_sitasi', $sitasi->id)->update($data); // MENDISABLE SITASI META BERDASARKAN ID SITASI
            $deleteSitasi     = Sitasi::where('uuid', $uuid)->update($data); // MENDISABLE SITASI BERDSARKAN UIID
            if (!$deleteSitasi || !$deleteSitasi) {
                throw new Exception("Hapus gagal", 400);
            }
            $response = [
                'message' => 'Hapus berhasil',
            ];
        } catch (\Exception $e) {
            $status  = 400;
            $message = $e->getMessage() . ', ' . $e->getLine();
            Log::error($message);
            $response = [
                'message' => 'Hapus gagal',
                //'stack' => $message,
            ];
        }
        return response()->json($response, $status);

    }
    public function insertSitasiUtama(Request $request, $nik)
    {
        try {
            //! Pengaturan validasi
            $rules = [
                'kd_status'           => 'required|string',
                // 'sitasi_uuid'         => 'required|string',
                'sitasi_total'        => 'required|string',
                'sitasi_link'         => 'required|string',
                'kd_bentuk_publikasi' => 'required|string',
                'sitasi_uuid'         => 'string',
            ];
            $validasi = Validator::make($request->all(), $rules, [
                'required' => 'Input :attribute wajib diisi',
            ]);
            if ($validasi->fails()) {
                return response()->json([
                    'info' => 'Isian yang diberikan kurang lengkap',
                    // 'message' => $validasi->errors()->toArray(),
                ], 400);
            }
            DB::beginTransaction();
            $validated    = $validasi->validated();
            $pegawai      = Pegawai::where('nik', $nik)->first();
            $status       = StatusPublikasi::where('kd_status', $validated['kd_status'])->first();
            $bentukSitasi = BentukPublikasi::where('kd_bentuk_publikasi', $validated['kd_bentuk_publikasi'])->first();
            $sitasi       = Sitasi::where('uuid', $validated['sitasi_uuid'])->first();

            //! Sitasi id jika tidak ditemukan, generate manual
            $sitasiId           = $sitasi->id ?? $this->generateId()->id;
            $getSitasiMetaTahun = SitasiMeta::where('id_sitasi', $sitasi->id)->where('flag_aktif', true)->orderBy('sitasi_tahun', 'DESC')->first();

            $sitasiUtama = [
                'id'                  => $sitasiId,
                'id_pegawai'          => $pegawai->id,
                'id_publikasi_status' => $status->id,
                'id_publikasi_bentuk' => $bentukSitasi->id,
                'sitasi_jenis'        => 'individual',
                'sitasi_total'        => $validated['sitasi_total'],
                'sitasi_link'         => $validated['sitasi_link'],
                'uuid'                => $this->generateId()->uuid,
                'tgl_pengajuan'       => date('Y-m-d H:i:s'),
                'sitasi_tahun'        => $getSitasiMetaTahun->sitasi_tahun ?? null,
            ];
            if (!$validated['sitasi_uuid']) {
                //! Tambah sitasi
                Sitasi::query()->insert($sitasiUtama);
                $uuid = $sitasiUtama['uuid'];
            } else {
                //! Update sitasi
                unset($sitasiUtama['uuid']);
                Sitasi::where('uuid', $validated['sitasi_uuid'])->update($sitasiUtama);
                $uuid = $validated['sitasi_uuid'];
            }
            // ! sinkron remunerasi
            $sitasi = Sitasi::query()->where('uuid', $uuid)->first();
            // return response()->json([
            //     $this->transformSitasi($uuid),
            // ], 200);
            DB::commit();
            Log::info("Simpan sitasi utama $nik berhasil");
            $jadwal            = true;
            $sinkronRemunerasi = $this->sinkronRemunerasiSitasi($uuid, $jadwal);
            Log::info('Sinkron remunerasi', $sinkronRemunerasi);
            if ($sinkronRemunerasi['status'] != 200) {
                Log::warning("Remunerasi : {$sinkronRemunerasi['message']}");
            }
            return response()->json([
                'message' => ($validated['sitasi_uuid'] ? 'Sitasi berhasil diperbarui' : 'Sitasi utama berhasil ditambahkan') . ', ' . $sinkronRemunerasi['message'],
                // 'detail'  => [
                //     $sinkronRemunerasi['message'] ?? 'Sinkron remunerasi berhasil',
                // ],
                'data'    => [
                    'uuid' => $uuid,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            $status  = $th instanceof HttpException ? $th->getStatusCode() : 400;
            $message = $th instanceof HttpException ? $th->getMessage() : (env('APP_DEBUG') === 'true' ? $th->getMessage() : 'Sitasi utama gagal disimpan');
            Log::error($th->getMessage() . ' line :' . $th->getLine());
            $response = [
                'message' => $message,
            ];
            return response()->json($response, $status);
        }
    }

    public function getDetailSitasi(Request $request)
    {
        try {
            DB::beginTransaction();
            $uuid = $request->input('uuid_sitasi');
            if (!$uuid) {
                return response()->json(["message" => "Data Sitasi berhasil diambil", "data" => []], 200);
            }
            $status             = StatusPublikasi::query()->where('flag_aktif', true)->get();
            $statusDiverifikasi = $status->filter(function ($item) {
                return $item->kd_status == 'DVR';
            })->first();
            $sitasiDetail = Sitasi::query()->with(['status',
                'pegawai',
                'sitasiMeta' => function ($query) use ($statusDiverifikasi) {
                    $query->where('flag_aktif', true)
                        ->where('id_publikasi_status', $statusDiverifikasi->id)
                        ->orderBy('sitasi_tahun', 'desc');
                },
                'sitasiMeta.status',
                'sitasiMeta.sitasiFile',
            ])->when($uuid, function ($query) use ($uuid) {
                return $query->where('uuid', $uuid);
            })->first();
            DB::commit();
            return (new SitasiResource($sitasiDetail))->response()->setStatusCode(200);
        } catch (\Throwable $th) {
            DB::rollBack();
            $status  = $th instanceof HttpException ? $th->getStatusCode() : 400;
            $message = $th instanceof HttpException ? $th->getMessage() : (env('APP_DEBUG') === 'true' ? $th->getMessage() : 'Sitasi detail gagal ditampilkan');
            Log::error($th->getMessage() . ' line :' . $th->getLine());
            $response = [
                'message' => $message,
            ];
            return response()->json($response, $status);
        }
    }
    public function getYearDetailSitasi($uuid)
    {
        try {
            DB::beginTransaction();
            // * Handel jika id tidak ditemukan
            if (!$uuid) {
                return response()->json(['message' => 'sitasi utama tidak ditemukan'], 400);
            }
            // * Mengambil data sitasi detail dan file dengan relasi antar tabel
            $sitasiDetail = SitasiMeta::query()->where('uuid', $uuid)->first();
            if (!$sitasiDetail) {
                throw new HttpException(400, 'Data sitasi detail tidak ditemukan');
            }
            DB::commit();
            return (new SitasiMetaResource($sitasiDetail))
                ->additional(['message' => 'Sitasi detail per tahun ditampilkan'])
                ->response()
                ->setStatusCode(200);

        } catch (\Throwable $th) {
            DB::rollBack();
            $status  = $th instanceof HttpException ? $th->getStatusCode() : 400;
            $message = $th instanceof HttpException ? $th->getMessage() : (env('APP_DEBUG') === 'true' ? $th->getMessage() : 'Tahun sitasi detail gagal ditampilkan');
            Log::error($th->getMessage() . ' line :' . $th->getLine());
            $response = [
                'message' => $message,
            ];
            return response()->json($response, $status);

        }
    }
    public function insertDetailSitasi(Request $request, $nik)
    {
        try {

            // ! Validasi inputan
            $rules = [
                'uuid_sitasi'         => 'string',
                'sitasi_tahun'        => 'required|string',
                'sitasi_jumlah'       => 'required|string',
                'gambar_halaman_file' => 'required|max:50000|mimes:jpg,png,jpeg',
            ];
            $validasi = Validator::make($request->all(), $rules, [
                'required' => 'Input :attribute wajib diisi.',
            ]);
            if ($validasi->fails()) {
                return response()->json([
                    'message' => 'Isian yang diberikan tidak valid',
                    'data'    => $validasi->errors(),
                ], 400);
            }
            $validated    = $validasi->validated();
            $statusSitasi = StatusPublikasi::where('kd_status', 'DVR')->first();
            DB::beginTransaction();
            // ! Simpan data payload string dan binary (jpg)
            $idSitasi = Sitasi::query()->where('uuid', $validated['uuid_sitasi'])->first()->id ?? false;
            //DWADD
            if (!$idSitasi) {
                throw new Exception('Sitasi utama tidak ditemukan', 400);
            }

            $year          = $validated['sitasi_tahun'];
            $date          = Carbon::createFromFormat('Y', $year);
            $formattedDate = $date->format('Y') . "-01-01";
            $uuid          = Uuid::uuid4()->toString();

            $payloadSitasi = [
                'id_sitasi'           => $idSitasi,
                'sitasi_jumlah'       => $validated['sitasi_jumlah'],
                'sitasi_tahun'        => $formattedDate,
                'id_publikasi_status' => $statusSitasi->id,
                'uuid'                => $uuid,
            ];
            $rowIdSitasiMeta = SitasiMeta::query()->insertGetId($payloadSitasi);
            $sitasiMeta      = SitasiMeta::query()->where('row_id', $rowIdSitasiMeta)->first();

            $uuid              = Uuid::uuid4()->toString();
            $payloadSitasiFile = [
                'id_sitasi'      => $idSitasi,
                'id_sitasi_meta' => $sitasiMeta->id,
                'uuid'           => $uuid,
            ];

            // ! Handel file
            if ($request->hasFile('gambar_halaman_file')) {
                $file                           = $validated['gambar_halaman_file'];
                $namaFile                       = date("YmdHis") . '-' . $request->input('uuid_sitasi');
                $fileTanpaExtensi               = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $namaFile                       = $namaFile . '-' . str_replace([' ', '-'], '_', $fileTanpaExtensi);
                $uploadFileSitasi               = $this->uploadFile('sitasi', $nik, $namaFile, $file)['path'];
                $payloadSitasiFile['nama_file'] = $file->getClientOriginalName();
                $payloadSitasiFile['path_file'] = $uploadFileSitasi;
            }
            SitasiFile::query()->insertGetId($payloadSitasiFile);
            DB::commit();
            return response()->json([
                'message' => 'Sitasi detail ditambahkan',
                'data'    => [
                    'uuid_sitasi_meta' => $payloadSitasi['uuid'],
                    'uuid_sitasi_file' => $payloadSitasiFile['uuid'],
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            $status  = $th instanceof HttpException ? $th->getStatusCode() : 400;
            $message = $th instanceof HttpException ? $th->getMessage() : (env('APP_DEBUG') === 'true' ? $th->getMessage() : 'Sitasi detail gagal disimpan');
            Log::error($th->getMessage() . ' line :' . $th->getLine());
            $response = [
                'message' => $message,
            ];
            return response()->json($response, $status);
        }
    }
    public function deleteDetailSitasi(Request $request, $uuid)
    {
        try {
            // ! Validasi inputan
            $rules = [
                'catatan_penghapusan' => 'required|string',
            ];
            $validasi = Validator::make($request->all(), $rules, [
                'required' => 'Input :attribute wajib diisi.',
            ]);
            if ($validasi->fails()) {
                return response()->json([
                    'message' => 'Isian yang diberikan tidak valid',
                    'data'    => $validasi->errors(),
                ], 400);
            }
            $validated = $validasi->validated();
            DB::beginTransaction();
            SitasiMeta::query()->where('uuid', $uuid)->update([
                'flag_aktif'          => false,
                'catatan_penghapusan' => $validated['catatan_penghapusan'],
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Sitasi detail barhasil dihapus',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            $status  = $th instanceof HttpException ? $th->getStatusCode() : 400;
            $message = $th instanceof HttpException ? $th->getMessage() : (env('APP_DEBUG') === 'true' ? $th->getMessage() : 'Sitasi detail gagal dihapus');
            Log::error($th->getMessage() . ' line :' . $th->getLine());
            $response = [
                'message' => $message,
            ];
            return response()->json($response, $status);
        }
    }
    //! v2 sinkron sitasi
    public function transformSitasi($sitasiUuid)
    {
        //! Filter status
        $status             = StatusPublikasi::query()->where('flag_aktif', true)->get();
        $statusDiverifikasi = $status->filter(function ($item) {
            return $item->kd_status == 'DVR';
        })->first();
        $sitasi = Sitasi::query()->with(['status',
            'pegawai',
            'bentukPublikasi',
            'sitasiMeta' => function ($query) use ($statusDiverifikasi) {
                $query->where('flag_aktif', true)
                    ->where('id_publikasi_status', $statusDiverifikasi->id)
                    ->orderBy('sitasi_tahun', 'desc');
            },
            'sitasiMeta.status',
            'sitasiMeta.sitasiFile',
        ])->where('uuid', $sitasiUuid)->first();
        if (!$sitasi) {
            throw new HttpException(400, 'Data sinkron sitasi tidak ditemukan');
        }
        if ($sitasi->status->kd_status != 'DVR') {
            throw new HttpException(400, 'Sinkron remunerasi hanya bisa dilakukan pada status diverifikasi');
        }
        return (new RemunerasiSitasiResource($sitasi))->resolve();
    }
    public function sinkronRemunerasiSitasi($uuid, $flag)
    {
        $sitasi   = $this->transformSitasi($uuid);
        $idSitasi = $sitasi['id_sitasi'] ?? null;
        if (!$idSitasi) {
            throw new HttpException(400, 'Sinkronisasi sitasi tidak ditemukan');
        }
        if (!$flag) {
            return [
                'status'  => false,
                'message' => 'Jadwal remunerasi belum buka',
            ];
        }
        $uri = "/private/api/v1/submission/fixing-portofolio-sitasi";
        $url = "http://" . env('REMUNERASI_DATA_API_URL') . $uri;
        Log::info("Kirim data sitasi ke $url", $sitasi);
        $sinkron  = Http::put($url, $sitasi);
        $response = $sinkron->json();
        Log::info("Respon sinkron remunerasi", $response);
        if ($sinkron->status() == 200) {
            //! Handel status sitasi jika sinkorn berhasil
            $updateData = [
                'flag_perbaikan_remunerasi' => false,
                'catatan_remunerasi'        => null,
            ];
            SitasiMeta::query()->where('id_sitasi', $idSitasi)->where('flag_perbaikan_remunerasi', true)->update($updateData);
        }
        return [
            'status'  => $sinkron->status(),
            'message' => $response['info'],
        ];

    }
}
