<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use \App\Http\Controllers\FileController;

use App\Models\FormModel as form;
use App\Models\InstansiPublikasiModel as instansi;
use App\Models\NegaraPublikasiModel as negara;
use App\Models\PegawaiModel as pegawai;
use App\Models\PeranModel as peran;
use App\Models\PublikasiMetaModel as publikasiMeta;
use App\Models\StatusAnggotaModel as statusAnggota;
use App\Models\StatusModel as status;
use App\Models\VersiFormModel as versiForm;
use App\Models\TermsModel as terms;

use \App\Traits\MasterData;
use \App\Traits\TaxonomyTrait;

trait PublikasiTrait
{

    use FileController;
    use MasterData;
    use TaxonomyTrait;

    /**
     * - Multiple baca form (Dokumen & Keanggotaan)
     */

    public function handleDocuments(array $dataPublikasi, Request $request, array $formArray = null)
    {
        $method = $dataPublikasi['method'] ?? $request->method();
        $idPublikasi = $dataPublikasi['id_publikasi'] ?? null;
        $publikasi = $dataPublikasi['publikasi'] ?? null;
        $pegawai = $dataPublikasi['pegawai'] ?? null;
        $flagAktif = $dataPublikasi['flag_aktif'] ?? null;
        $tglInput = $dataPublikasi['tgl_input'] ?? date('Y-m-d');
        $userInput = $dataPublikasi['user_input'] ?? null;
        $tglUpdate = $dataPublikasi['tgl_update'] ?? date('Y-m-d');
        $userUpdate = $dataPublikasi['user_update'] ?? null;
        $data = [];

        if ($request['dokumen']) {
            $i = 0;
            $previousDocument = publikasiMeta::where('id_publikasi', $idPublikasi)->where('key', 'dokumen')->where('flag_aktif', true)->first() ?: null;
            $documentBase = ($previousDocument) ? unserialize($previousDocument['value']) : null;
            foreach ($request['dokumen'] as $key => $value) {
                $newID = DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                $newUUID = Str::uuid()->toString();
                $documents = [];

                $document = ($request->hasFile("dokumen.$key.berkas")) ? $request->file("dokumen.$key.berkas") : null;
                $savedDocument = ($request->input("dokumen.$key.uuid_dokumen")) ? collect($documentBase)->where('uuid', $request->input("dokumen.$key.uuid_dokumen"))->first() : null;

                // Getting path file
                $uploadFile = ($document) ? $this->uploadFile('publikasi', $pegawai['nik'], "$idPublikasi-$key", $document)['path'] : null;
                $copyFile = ((isset($savedDocument['path_file']) && $document != null) && ($savedDocument['path_file'] != null || $savedDocument['path_file'] != '') && ($uploadFile == null)) ? $this->copyFile($savedDocument['path_file'], null, $pegawai['nik'], "$idPublikasi-$key")['path'] : null;
                $pathFile = ($document != null) ? $uploadFile : $copyFile;

                if ($method === 'PUT' && ($request->has("dokumen.$key.uuid_dokumen") && $request->input("dokumen.$key.uuid_dokumen") != null)) {
                    $documents = array_merge($documents, [
                        'uuid' => ($savedDocument && isset($savedDocument['uuid'])) ? $savedDocument['uuid'] : $newUUID,
                        'id' => ($savedDocument && isset($savedDocument['id'])) ? $savedDocument['id'] : $newID,
                        'flag_aktif' => $savedDocument['flag_aktif'] ?? $flagAktif,
                        // perlu dirubah di data form
                        'nomor_berkas' => $value['no_surat'] ?? '',
                        'berkas' => ($document != null) ? $document->getClientOriginalName() : (!blank(data_get($savedDocument,'berkas')) ? $savedDocument['berkas']:''),
                        'path_file' => $pathFile ?: $savedDocument['path_file'],
                        'keterangan' => (isset($value['keterangan']) && $value['keterangan']) ? $value['keterangan'] : ($savedDocument['keterangan'] ?? null),
                        'uuid_keterangan' => (isset($value['uuid_keterangan']) && $value['uuid_keterangan']) ? ($value['uuid_keterangan'] ?? null) : ($savedDocument['uuid_keterangan'] ?? null),
                        // flag publik
                        //'uuid_flag_publik' => (isset($value['uuid_flag_publik']) && $value['uuid_flag_publik']) ? $value['uuid_flag_publik'] : $savedDocument['uuid_flag_publik'],
                    ]);
                    array_push($data, $documents);
                } else {
                    $newID = DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                    $newUUID = Str::uuid()->toString();
                    if ($request->hasFile("dokumen.$key.berkas")) {
                        array_push($data, [
                            'uuid' => $newUUID,
                            'id' => $newID,
                            // perlu dirubah di data form
                            'nomor_berkas' => $value['no_surat'] ?? '',
                            'flag_aktif' => $flagAktif,
                            'berkas' => ($document) ? $document->getClientOriginalName() : '',
                            'path_file' => ($document) ? $this->uploadFile('publikasi', $pegawai['nik'], "$idPublikasi-$key", $document)['path'] : '',
                            'keterangan' => (isset($value['keterangan']) && $value['keterangan']) ? $value['keterangan'] : '',
                            'uuid_keterangan' => (isset($value['uuid_keterangan']) && $value['uuid_keterangan']) ? $value['uuid_keterangan'] : '',
                            // flag publik
                            //'uuid_flag_publik' => (isset($value['uuid_flag_publik']) && $value['uuid_flag_publik']) ? $value['uuid_flag_publik'] : '',
                        ]);
                    } else {
                        array_push($data, ($request->has("dokumen.$key.uuid_dokumen") && $request->input("dokumen.$key.uuid_dokumen") != null) ? [
                            'uuid' => $savedDocument['uuid'] ?? '',
                            'id' => $savedDocument['id'] ?? '',
                            'flag_aktif' => $savedDocument['flag_aktif'] ?? $flagAktif,
                            // perlu dirubah di data form
                            'nomor_berkas' => $value['no_surat'] ?? '',
                            'berkas' => ($document) ? $document->getClientOriginalName() : $savedDocument['berkas'] ?? '',
                            'path_file' => ($document) ? $this->uploadFile('publikasi', $pegawai['nik'], "$idPublikasi-$key", $document)['path'] : $savedDocument['path_file'] ?? '',
                            'keterangan' => (isset($value['keterangan']) && $value['keterangan']) ? $value['keterangan'] : $savedDocument['uuid_keterangan'],
                            'uuid_keterangan' => (isset($value['uuid_keterangan']) && $value['uuid_keterangan']) ? $value['uuid_keterangan'] : $savedDocument['uuid_keterangan'],
                            // flag publik
                            //'uuid_flag_publik' => (isset($value['uuid_flag_publik']) && $value['uuid_flag_publik']) ? $value['uuid_flag_publik'] : $savedDocument['uuid_flag_publik'],
                        ] : [
                            'uuid' => $newUUID,
                            'id' => $newID,
                            // perlu dirubah di data form
                            'nomor_berkas' => $value['no_surat'] ?? '',
                            'flag_aktif' => $flagAktif,
                            'berkas' => ($document != null) ? $document->getClientOriginalName() : $savedDocument['berkas'] ?? '',
                            'path_file' => $pathFile ?: $savedDocument['path_file'] ?? '',
                            'keterangan' => (isset($value['keterangan']) && $value['keterangan']) ? $value['keterangan'] : '',
                            'uuid_keterangan' => (isset($value['uuid_keterangan']) && $value['uuid_keterangan']) ? $value['uuid_keterangan'] : '',
                            // flag publik
                            //'uuid_flag_publik' => (isset($value['uuid_flag_publik']) && $value['uuid_flag_publik']) ? $value['uuid_flag_publik'] : '',
                        ]);
                    }
                }
                $i++;
            }
        }

        return (is_array($data) && count($data) > 0) ? $data : [[
            'uuid' => '',
            'id' => '',
            'nomor_berkas' => '',
            'flag_aktif' => $flagAktif,
            'berkas' => '',
            'path_file' => '',
            'keterangan' => '',
            'uuid_keterangan' => '',
            // flag publik
            'uuid_flag_publik' => '',
        ]];
    }

    public function handleMembers(array $dataPublikasi, Request $request, array $formArray = null)
    {
        $method = $dataPublikasi['method'] ?? $request->method();
        $idPublikasi = $dataPublikasi['id_publikasi'] ?? null;
        $publikasi = $dataPublikasi['publikasi'] ?? null;
        $pegawai = $dataPublikasi['pegawai'] ?? null;
        $flagAktif = $dataPublikasi['flag_aktif'] ?? null;
        $tglInput = $dataPublikasi['tgl_input'] ?? date('Y-m-d h:i:s');
        $userInput = $dataPublikasi['user_input'] ?? null;
        $tglUpdate = $dataPublikasi['tgl_update'] ?? date('Y-m-d h:i:s');
        $userUpdate = $dataPublikasi['user_update'] ?? null;
        $data = [];
        $publikasiUtama = [];
        $publikasiLain = [];
        $dokumenUtama = [];
        $dokumenLain = [];
        $keanggotaanUtama = [];
        $keanggotaanLain = [];
        $masterBaru = [];
        $masterBaruTagging = [];

        if ($request->input('keanggotaan')) {
            $previousMember = publikasiMeta::where('id_publikasi', $idPublikasi)->where('key', 'keanggotaan')->where('flag_aktif', true)->first() ?: null;
            $memberBase = ($method === 'PUT' && $previousMember) ? collect(unserialize($previousMember['value']))->toArray() : null;
            $previousDocument = publikasiMeta::where('id_publikasi', $idPublikasi)->where('key', 'dokumen')->where('flag_aktif', true)->first() ?: null;
            $documentBase = ($method === 'PUT' && $previousMember) ? collect(unserialize($previousDocument['value']))->toArray() : null;

            foreach ($request->input('keanggotaan') as $key => $value) {
                $otherPublication = [];
                $instansi = instansi::where('uuid', $value['uuid_instansi_anggota'])->first();

                $newMasterID = ($value['uuid_instansi_anggota'] == null || $value['uuid_instansi_anggota'] == '') ? \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort : null;

                // Kondisi jika ada data instansi baru
                if ((!$value['uuid_instansi_anggota'] || $value['uuid_instansi_anggota'] == '') && ($value['instansi_anggota'] || $value['instansi_anggota'] != '')) {
                    $namefield = 'instansi_anggota';
                    $versiForm = versiForm::where('id_publikasi_bentuk', $publikasi['id_publikasi_bentuk'])->where('flag_aktif', true)->first();
                    $form = form::where('id_publikasi_form_versi', $versiForm['id'])->where('name_field', $namefield)->first();
                    $negara = negara::where('uuid', $value['uuid_negara_anggota'] ?? null)->first();
                    $dataInstansi = ['id' => $newMasterID, 'nama_instansi' => $value['instansi_anggota'], 'kd_negara' => $negara->kd_negara ?? null, 'id_publikasi_ajuan' => $idPublikasi, 'id_publikasi_form' => $form->id ?? null, 'user_input' => $userInput, 'tgl_input' => $tglInput, 'tgl_update' => $tglUpdate, 'user_update' => $userUpdate, 'flag_aktif' => false, 'flag_ajuan' => true, 'flag_ditolak' => false];
                    $this->addNewMasterData('publikasi_instansi', $value['uuid_instansi_anggota'], $dataInstansi);
                    array_push($masterBaru, [
                        'id_publikasi' => $idPublikasi,
                        'name_field' => $namefield,
                        'master_data' => $dataInstansi['nama_instansi'],
                    ]);
                }

                if ($method == 'PUT') { // && ($value["uuid_keanggotaan"] && ($value["uuid_keanggotaan"] != NULL || $value["uuid_keanggotaan"] != '')) UUID Ada
                    // PUT

                    $savedMember = ($request->input("keanggotaan.$key.uuid_keanggotaan")) ? collect($memberBase)->where('uuid', $request->input("keanggotaan.$key.uuid_keanggotaan"))->first() : null;
                    //dump($savedMember);
                    $statusAnggota = statusAnggota::where('uuid', $value['uuid_status_anggota'] ?? null)->first();
                    $institutionID = (isset($newMasterID) && $newMasterID) ? $newMasterID : null;
                    $inputInstitutionID = instansi::where('uuid', $value['uuid_instansi_anggota'] ?? null)->first()->id ?? null;
                    //dump('PUT');
                    $keanggotaan = [
                        'id' => $savedMember['id'] ?? \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
                        'nama_penulis' => $request->input("keanggotaan.$key.nama_penulis") ?: '',
                        'nik_keanggotaan' => $value['nik_keanggotaan'] ?: '',
                        'pegawai_eksternal' => ($value['nik_keanggotaan']) ? '' : $value['nama_penulis'],
                        'peran_anggota' => peran::where('uuid', $value['uuid_peran_anggota'] ?? null)->first()->id ?? null,
                        'negara_anggota' => negara::where('uuid', $value['uuid_negara_anggota'] ?? null)->first()->id ?? '',
                        'instansi_anggota' => $value['instansi_anggota'] ?? null,
                        'id_instansi_anggota' => (isset($newMasterID) && $institutionID) ? $institutionID : $inputInstitutionID,
                        'status_anggota' => $statusAnggota->id ?? '',
                        'flag_aktif' => $flagAktif,
                        'tgl_input' => $tglInput,
                        'user_input' => $userInput,
                        'tgl_update' => $tglUpdate,
                        'user_update' => $userUpdate,
                        //'uuid' => $savedMember['uuid'] ?? null,
                        'uuid' => $savedMember['uuid'] ?? Str::uuid()->toString(),
                    ];

                    array_push($keanggotaanUtama, $keanggotaan);

                    if ($value['uuid_instansi_anggota'] && $value['instansi_anggota']) {
                        $namefield = 'instansi_anggota';
                        $versiForm = versiForm::where('id_publikasi_bentuk', $publikasi['id_publikasi_bentuk'])->where('flag_aktif', true)->first();
                        $form = form::where('id_publikasi_form_versi', $versiForm['id'])->where('name_field', $namefield)->first();
                        $negara = negara::where('uuid', $value['uuid_negara_anggota'] ?? null)->first();
                        $instansi = instansi::where('uuid', $value['uuid_instansi_anggota'] ?? null)->first();
                        // if ($instansi->flag_ajuan == true and $instansi->flag_aktif == false) {
                        //     $dataInstansi = ['id' => $newMasterID, 'nama_instansi' => $value['instansi_anggota'], 'kd_negara' => $negara->kd_negara ?? null, 'id_publikasi_ajuan' => $idPublikasi, 'id_publikasi_form' => $form->id ?? null, 'user_input' => $userInput, 'tgl_input' => $tglInput, 'tgl_update' => $tglUpdate, 'user_update' => $userUpdate, 'flag_aktif' => false, 'flag_ajuan' => true, 'flag_ditolak' => false];
                        //     // $this->addNewMasterData('publikasi_instansi', $value['uuid_instansi_anggota'], $dataInstansi);
                        //     array_push($masterBaruTagging, $dataInstansi);
                        // }
                    }
                    // SET DUPLIKASI
                    //if (is_array($savedMember) && count($savedMember) > 0) {
                    //if (!$savedMember) {
                        // Data publikasi baru (Usulan)
                        if (($instansi && $instansi['kd_instansi'] == 'UII') && ($value['nik_keanggotaan'] != $pegawai['nik']) && ((is_array($savedMember) && count($savedMember) > 0) && ($value['nik_keanggotaan'] != $savedMember['nik_keanggotaan']))) {
                            $pegawaiInternal = pegawai::where('nik', $value['nik_keanggotaan'])->first();
                            //$mahasiswaInternal = (($instansi && $instansi['kd_instansi'] == 'UII') && ($value['nik_keanggotaan'])) ? mahasiswa::where('nim', $value['nik_keanggotaan'])->first() : '' ;
                            //$nikKeanggotaan = ($statusAnggota->kd_jenis == 'MS') ? $mahasiswaInternal->nim : $pegawaiInternal->nik ;

                            // Publikasi Baru (Usulan)
                            $tempPublication = $publikasi;
                            $tempPublication['id'] = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                            $tempPublication['id_publikasi_status'] = status::where('kd_status', 'USL')->first()['id'];
                            $tempPublication['id_publikasi_peran'] = $keanggotaan['peran_anggota'] ?? null;
                            $tempPublication['id_pegawai'] = ($pegawaiInternal) ? $pegawaiInternal->id : null;

                            // Surat Dokumen Baru (Usulan)
                            $dataPublikasiLain = ['method' => 'POST', 'id_publikasi' => $tempPublication['id'], 'publikasi' => $tempPublication, 'pegawai' => $pegawaiInternal, 'flag_aktif' => $flagAktif, 'user_update' => $userUpdate, 'tgl_update' => $tglUpdate];
                            if ($request->has('dokumen') && $pegawaiInternal) {
                                $tempDocuments = collect([]);
                                foreach ($request->input('dokumen') as $keyDocument => $valueDocument) {
                                    $document = [
                                        'id' => \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
                                        'uuid' => Str::uuid()->toString(),
                                        'berkas' => '',
                                        'path_file' => '',
                                        'nomor_berkas' => '',
                                        'uuid_keterangan' => '',
                                        'flag_aktif' => $flagAktif,
                                    ];
                                    $base = collect($documentBase)->where('uuid', $valueDocument['uuid_dokumen'] ?? null)->first();
                                    if ($request->hasFile("dokumen.$keyDocument.berkas") && $base) {
                                        $document['berkas'] = $request->file("dokumen.$keyDocument.berkas")->getClientOriginalName() ?? $document['berkas'];
                                        $document['path_file'] = $this->uploadFile('publikasi', $pegawaiInternal['nik'], $tempPublication['id'] . '-' . $key, $request->file("dokumen.$keyDocument.berkas"))['path'] ?? $document['path_file'];
                                        $document['uuid_keterangan'] = $valueDocument['uuid_keterangan'] ?? $document['uuid_keterangan'];
                                    } else if (!$request->hasFile("dokumen.$keyDocument.berkas") && $base) {
                                        $document['berkas'] = $valueDocument['berkas'] ?? $document['berkas'];
                                        $document['path_file'] = $base['path_file'] ?? ''; // $this->copyFile($base['path_file'], NULL, $pegawaiInternal['nik'], $tempPublication['id'] . '-' . $key)['path']
                                        $document['uuid_keterangan'] = $valueDocument['uuid_keterangan'] ?? ($base['uuid_keterangan'] ?? null);
                                    }
                                    $tempDocuments->push($document);
                                }
                                if (isset($pegawaiInternal->id)) {
                                    array_push($dokumenLain, ['id_pegawai' => $pegawaiInternal->id, 'dokumen' => $tempDocuments->all()]);
                                }

                            }
                            // Keanggaotaan Baru (Usulan)
                            $members = [];
                            foreach ($request->input('keanggotaan') as $key => $valueOther) {
                                $instansiBaru = instansi::where('uuid', $valueOther['uuid_instansi_anggota'] ?? null)->first();
                                $negaraBaru = (isset($valueOther['uuid_negara_anggota'])) ? negara::where('uuid', $valueOther['uuid_negara_anggota'] ?? null)->first() : null;
                                $newMasterUsulanID = ($valueOther['uuid_instansi_anggota'] == null || $value['uuid_instansi_anggota'] == '') ? \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort : null;
                                if ((!$valueOther['uuid_instansi_anggota'] || $valueOther['uuid_instansi_anggota'] == '') && ($valueOther['instansi_anggota'] || $valueOther['instansi_anggota'] != '')) {
                                    $namefield = 'instansi_anggota';
                                    $versiForm = versiForm::where('id_publikasi_bentuk', $publikasi['id_publikasi_bentuk'])->where('flag_aktif', true)->first();
                                    $form = form::where('id_publikasi_form_versi', $versiForm['id'])->where('name_field', $namefield)->first();
                                    $negara = negara::where('uuid', $valueOther['uuid_negara_anggota'] ?? null)->first();
                                    // $dataInstansiUsulan = ['id' => $newMasterUsulanID, 'nama_instansi' => $valueOther['instansi_anggota'], 'kd_negara' => $negara->kd_negara ?? null, 'id_publikasi_ajuan' => $idPublikasi, 'id_publikasi_form' => $form->id ?? null, 'user_input' => $userInput, 'tgl_input' => $tglInput, 'tgl_update' => $tglUpdate, 'user_update' => $userUpdate, 'flag_aktif' => false, 'flag_ajuan' => true, 'flag_ditolak' => false];
                                    // Merubah id instansi
                                    $dataInstansiUsulan = ['id' => $newMasterUsulanID, 'nama_instansi' => $valueOther['instansi_anggota'], 'kd_negara' => $negara->kd_negara ?? null, 'id_publikasi_ajuan' => $tempPublication['id'], 'id_publikasi_form' => $form->id ?? null, 'user_input' => $userInput, 'tgl_input' => $tglInput, 'tgl_update' => $tglUpdate, 'user_update' => $userUpdate, 'flag_aktif' => false, 'flag_ajuan' => true, 'flag_ditolak' => false];
                                    $this->addNewMasterData('publikasi_instansi', $valueOther['uuid_instansi_anggota'], $dataInstansiUsulan);
                                    // Menambahkan data tagging usulan
                                    array_push($masterBaru, [
                                        'id_publikasi' => $tempPublication['id'],
                                        'name_field' => $namefield,
                                        'master_data' => $dataInstansiUsulan['nama_instansi'],
                                    ]);
                                }
                                $institutionUsulanID = (isset($newMasterUsulanID) && $newMasterUsulanID) ? $newMasterUsulanID : null;
                                $inputInstitutionUsulanID = instansi::where('uuid', $valueOther['uuid_instansi_anggota'] ?? null)->first()->id ?? null;
                                $InstitutionUsulanOld =DB::table('publikasi_instansi')->select(
                                    'id',
                                    'kd_instansi',
                                    'nama_instansi',
                                    'kd_negara',
                                    'flag_aktif',
                                    'flag_ajuan',
                                    'id_publikasi_ajuan',
                                    'id_publikasi_form',
                                    'flag_ditolak',
                                    'user_input',
                                    'user_update',
                                    'uuid',
                                )->where('uuid', $valueOther['uuid_instansi_anggota'] ?? null)->first() ?? null;
                                //dump($inputInstitutionUsulanID,$InstitutionUsulanOld);
                                // JIKA ADA INSTANSI SEBELUMNYA YANG FLAG USULAN MASIH AKTIF
                                if($inputInstitutionUsulanID && $InstitutionUsulanOld->flag_ajuan==true){
                                    $InstitutionUsulanOld->id = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                                    $InstitutionUsulanOld->uuid= Str::uuid()->toString();
                                    $InstitutionUsulanOld->id_publikasi_ajuan = $tempPublication['id'];
                                    $InstitutionUsulanOld->user_input = $userInput;
                                    $InstitutionUsulanOld->user_update = $userUpdate;
                                    //dump($InstitutionUsulanOld);
                                    $dataInstansi=collect($InstitutionUsulanOld)->toarray();
                                    $tambahInstansiUsulan=instansi::insert($dataInstansi);
                                    $inputInstitutionUsulanID=$InstitutionUsulanOld->id;
                                    array_push($masterBaru, [
                                        'id_publikasi' => $tempPublication['id'],
                                        'name_field' => 'instansi_anggota',
                                        'master_data' => $InstitutionUsulanOld->nama_instansi,
                                    ]);

                                }

                                array_push($members, (($valueOther['nama_penulis'] == $pegawaiInternal['nama'])&&($valueOther['nik_keanggotaan'] == $pegawaiInternal['nik'])) ? [
                                    'nama_penulis' => $pegawai['nama'],
                                    'peran_anggota' => $publikasi['id_publikasi_peran'] ?: null,
                                    'negara_anggota' => $negaraBaru['id'] ?? null,
                                    'instansi_anggota' => $valueOther['instansi_anggota'] ?? null,
                                    'id_instansi_anggota' => (isset($newMasterUsulanID) && $institutionUsulanID) ? $institutionUsulanID : $inputInstitutionUsulanID,
                                    'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'] ?? null)->first()['id'],
                                    'nik_keanggotaan' => ($instansiBaru['kd_instansi'] == 'UII') ? $pegawai['nik'] : null,
                                    'pegawai_eksternal' => ($instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                                    'flag_aktif' => $flagAktif,
                                    'tgl_input' => $tglInput,
                                    'user_input' => $userInput,
                                    'tgl_update' => $tglUpdate,
                                    'user_update' => $userUpdate,
                                    'uuid' => Str::uuid()->toString(),
                                ] : [
                                    'nama_penulis' => $valueOther['nama_penulis'],
                                    'peran_anggota' => peran::where('uuid', $valueOther['uuid_peran_anggota'] ?? null)->first()['id'] ?: null,
                                    'negara_anggota' => $negaraBaru['id'] ?? null,
                                    'instansi_anggota' => $valueOther['instansi_anggota'] ?? null,
                                    'id_instansi_anggota' => (isset($newMasterUsulanID) && $institutionUsulanID) ? $institutionUsulanID : $inputInstitutionUsulanID,
                                    'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'] ?? null)->first()['id'],
                                    'nik_keanggotaan' => ($instansiBaru['kd_instansi'] == 'UII') ? $valueOther['nik_keanggotaan'] : null,
                                    'pegawai_eksternal' => ($instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                                    'flag_aktif' => $flagAktif,
                                    'tgl_input' => $tglInput,
                                    'user_input' => $userInput,
                                    'tgl_update' => $tglUpdate,
                                    'user_update' => $userUpdate,
                                    'uuid' => Str::uuid()->toString(),
                                ]);
                            }
                            if (isset($pegawaiInternal->id)) {
                                array_push($keanggotaanLain, ['id_pegawai' => $pegawaiInternal->id, 'keanggotaan' => $members]);
                            }

                            array_push($otherPublication, $tempPublication);
                        }
                    //}

                    $newMasterID = null;

                } else { // UUID tidak ada
                    // POST

                    $statusAnggota = statusAnggota::where('uuid', $value['uuid_status_anggota'] ?? null)->first();

                    $institutionID = (isset($newMasterID) && $newMasterID) ? $newMasterID : null;
                    $inputInstitutionID = instansi::where('uuid', $value['uuid_instansi_anggota'] ?? null)->first()->id ?? null;

                    //$pegawaiInternal = (($instansi && $instansi['kd_instansi'] == 'UII') && ($value['nik_keanggotaan'])) ? pegawai::where('nik', $value['nik_keanggotaan'])->first() : '' ;
                    //$mahasiswaInternal = (($instansi && $instansi['kd_instansi'] == 'UII') && ($value['nik_keanggotaan'])) ? mahasiswa::where('nim', $value['nik_keanggotaan'])->first() : '' ;
                    //$nikKeanggotaan = ($statusAnggota->kd_jenis == 'MS') ? $mahasiswaInternal->nim : $pegawaiInternal->nik ;

                    //dump('POST');

                    $keanggotaan = [
                        'id' => \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
                        'nama_penulis' => $request->input("keanggotaan.$key.nama_penulis"),
                        'peran_anggota' => peran::where('uuid', $value['uuid_peran_anggota'] ?? null)->first()->id ?? null,
                        'negara_anggota' => negara::where('uuid', $value['uuid_negara_anggota'] ?? null)->first()->id ?? '',
                        'instansi_anggota' => $value['instansi_anggota'] ?? null,
                        'id_instansi_anggota' => (isset($newMasterID) && $institutionID) ? $institutionID : $inputInstitutionID,
                        'status_anggota' => ($statusAnggota) ? $statusAnggota->id : '',
                        'nik_keanggotaan' => ($value['nik_keanggotaan']) ? $value['nik_keanggotaan'] : '',
                        'flag_aktif' => $flagAktif,
                        'tgl_input' => $tglInput,
                        'user_input' => $userInput,
                        'tgl_update' => $tglUpdate,
                        'user_update' => $userUpdate,
                        'uuid' => Str::uuid()->toString(),
                    ];

                    // Data publikasi baru (Usulan)
                    if (($instansi && $instansi['kd_instansi'] == 'UII') && ($value['nik_keanggotaan'] != $pegawai['nik'])) {
                        // Publikasi Baru (Usulan)
                        $pegawaiInternal = pegawai::where('nik', $value['nik_keanggotaan'])->first();
                        $tempPublication = $publikasi;
                        $tempPublication['id'] = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
                        $tempPublication['id_publikasi_status'] = status::where('kd_status', 'USL')->first()['id'];
                        $tempPublication['id_publikasi_peran'] = $keanggotaan['peran_anggota'] ?? null;
                        $tempPublication['id_pegawai'] = ($pegawaiInternal) ? $pegawaiInternal->id : null;

                        // Surat Dokumen Baru (Usulan)
                        $dataPublikasiLain = [
                            'method' => 'POST',
                            'id_publikasi' => $tempPublication['id'],
                            'publikasi' => $tempPublication,
                            'pegawai' => $pegawaiInternal,
                            'flag_aktif' => $flagAktif,
                            'user_update' => $userUpdate,
                            'tgl_update' => $tglUpdate,
                        ];
                        if ($request->has('dokumen') && $pegawaiInternal) {
                            $tempDocuments = collect([]);
                            foreach ($request->input('dokumen') as $keyDocument => $valueDocument) {
                                $document = [
                                    'id' => \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
                                    'uuid' => Str::uuid()->toString(),
                                    'berkas' => '',
                                    'path_file' => '',
                                    'nomor_berkas' => '',
                                    'uuid_keterangan' => '',
                                    'flag_aktif' => $flagAktif,
                                ];
                                $base = collect($documentBase)->where('uuid', $valueDocument['uuid_dokumen'] ?? null)->first();
                                if ($request->hasFile("dokumen.$keyDocument.berkas")) {
                                    $document['berkas'] = $request->file("dokumen.$keyDocument.berkas")->getClientOriginalName() ?? $document['berkas'];
                                    $document['path_file'] = $this->uploadFile('publikasi', $pegawaiInternal['nik'], $tempPublication['id'] . '-' . $key, $request->file("dokumen.$keyDocument.berkas"))['path'] ?? $document['path_file'];
                                    $document['uuid_keterangan'] = $valueDocument['uuid_keterangan'] ?? $document['uuid_keterangan'];
                                } else if (!$request->hasFile("dokumen.$keyDocument.berkas")) {
                                    $document['berkas'] = $valueDocument['berkas'] ?? $document['berkas'];
                                    $document['path_file'] = $base['path_file'] ?? ''; // $this->copyFile($base['path_file'], NULL, $pegawaiInternal['nik'], $tempPublication['id'] . '-' . $key)['path'] ??
                                    $document['uuid_keterangan'] = $valueDocument['uuid_keterangan'] ?? $base['uuid_keterangan'];
                                }
                                $tempDocuments->push($document);
                            }
                            if (isset($pegawaiInternal->id)) {
                                array_push($dokumenLain, ['id_pegawai' => $pegawaiInternal->id, 'dokumen' => $tempDocuments->all()]);
                            }

                        }

                        $members = [];

                        // Keanggotaan Baru (Usulan)
                        foreach ($request->input('keanggotaan') as $key => $valueOther) {
                            $instansiBaru = instansi::where('uuid', $valueOther['uuid_instansi_anggota'] ?? null)->first();
                            $negaraBaru = (isset($valueOther['uuid_negara_anggota'])) ? negara::where('uuid', $valueOther['uuid_negara_anggota'] ?? null)->first() : null;

                            $newMasterUsulanID = ($valueOther['uuid_instansi_anggota'] == null || $value['uuid_instansi_anggota'] == '') ? \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort : null;

                            if ((!$valueOther['uuid_instansi_anggota'] || $valueOther['uuid_instansi_anggota'] == '') && ($valueOther['instansi_anggota'] || $valueOther['instansi_anggota'] != '')) {
                                $namefield = 'instansi_anggota';
                                $versiForm = versiForm::where('id_publikasi_bentuk', $publikasi['id_publikasi_bentuk'])->where('flag_aktif', true)->first();
                                $form = form::where('id_publikasi_form_versi', $versiForm['id'])->where('name_field', $namefield)->first();
                                $negara = negara::where('uuid', $valueOther['uuid_negara_anggota'] ?? null)->first();
                                $dataInstansiUsulan = ['id' => $newMasterUsulanID, 'nama_instansi' => $valueOther['instansi_anggota'], 'kd_negara' => $negara->kd_negara ?? null, 'id_publikasi_ajuan' => $idPublikasi, 'id_publikasi_form' => $form->id ?? null, 'user_input' => $userInput, 'tgl_input' => $tglInput, 'tgl_update' => $tglUpdate, 'user_update' => $userUpdate, 'flag_aktif' => false, 'flag_ajuan' => true, 'flag_ditolak' => false];
                                $this->addNewMasterData('publikasi_instansi', $valueOther['uuid_instansi_anggota'], $dataInstansiUsulan);
                            }

                            $institutionUsulanID = (isset($newMasterUsulanID) && $newMasterUsulanID) ? $newMasterUsulanID : null;
                            $inputInstitutionUsulanID = instansi::where('uuid', $valueOther['uuid_instansi_anggota'] ?? null)->first()->id ?? null;

                            array_push($members, ($valueOther['nama_penulis'] == $pegawaiInternal['nama']) ? [
                                'nama_penulis' => $pegawai['nama'],
                                'peran_anggota' => $publikasi['id_publikasi_peran'] ?? null,
                                'negara_anggota' => $negaraBaru['id'] ?? null,
                                'instansi_anggota' => $valueOther['instansi_anggota'] ?? '',
                                'id_instansi_anggota' => (isset($newMasterUsulanID) && $institutionUsulanID) ? $institutionUsulanID : $inputInstitutionUsulanID,
                                'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'] ?? null)->first()['id'] ?? '',
                                'nik_keanggotaan' => (isset($instansiBaru['kd_instansi']) && $instansiBaru['kd_instansi'] == 'UII') ? $pegawai['nik'] : null,
                                'pegawai_eksternal' => (isset($instansiBaru['kd_instansi']) && $instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                                'flag_aktif' => $flagAktif,
                                'tgl_input' => $tglInput,
                                'user_input' => $userInput,
                                'tgl_update' => $tglUpdate,
                                'user_update' => $userUpdate,
                                'uuid' => Str::uuid()->toString(),
                            ] : [
                                'nama_penulis' => $valueOther['nama_penulis'],
                                'peran_anggota' => peran::where('uuid', $valueOther['uuid_peran_anggota'] ?? null)->first()['id'] ?? null,
                                'negara_anggota' => $negaraBaru['id'] ?? '',
                                'instansi_anggota' => $valueOther['instansi_anggota'] ?? '',
                                'id_instansi_anggota' => (isset($newMasterUsulanID) && $institutionUsulanID) ? $institutionUsulanID : $inputInstitutionUsulanID,
                                'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'] ?? null)->first()['id'] ?? '',
                                'nik_keanggotaan' => (isset($instansiBaru['kd_instansi']) && $instansiBaru['kd_instansi'] == 'UII') ? $pegawaiInternal['nik'] ?? '' : '',
                                'pegawai_eksternal' => (isset($instansiBaru['kd_instansi']) && $instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                                'flag_aktif' => $flagAktif,
                                'tgl_input' => $tglInput,
                                'user_input' => $userInput,
                                'tgl_update' => $tglUpdate,
                                'user_update' => $userUpdate,
                                'uuid' => Str::uuid()->toString(),
                            ]);
                        }
                        if (isset($pegawaiInternal->id)) {
                            array_push($keanggotaanLain, ['id_pegawai' => $pegawaiInternal->id, 'keanggotaan' => $members]);
                        }

                        array_push($otherPublication, $tempPublication);

                        // Keanggotaan Utama
                        $keanggotaan['id_pegawai_internal'] = $pegawaiInternal['id'];
                        $keanggotaan['pegawai_eksternal'] = null;
                    } else {
                        // Keanggotaan Utama
                        //$pegawaiEksternal = pegawai::where('nama_pegawai', $value['nama_penulis'])->first() ;
                        $keanggotaan['id_pegawai_internal'] = null;
                        $keanggotaan['pegawai_eksternal'] = $value['nama_penulis'];
                        //$keanggotaan['nama_penulis'] = $value['nama_penulis'];
                    }
                    array_push($keanggotaanUtama, $keanggotaan);
                    $newMasterID = null;
                }
                $publikasiLain = array_merge($publikasiLain, $otherPublication);
            }
            //$publikasiUtama['id_publikasi_jenis'] = jenisPublikasi::where('kd_jenis', 'KOL')->first()->id ;
        } else {
            //$publikasiUtama['id_publikasi_jenis'] = jenisPublikasi::where('kd_jenis', 'INDV')->first()->id ;
        }
        // Handling pengelompokan master baru berdasarkan id publikasi
        $masterBaru = collect($masterBaru)->groupBy('id_publikasi')->transform(function ($masterItem, $masterKey) {
            return ['id_publikasi' => $masterKey, 'master' => $masterItem->toArray()];
        })->values()->toArray();

        $data = [
            'publikasi_utama' => $publikasiUtama,
            'dokumen_utama' => $dokumenUtama,
            'keanggotaan_utama' => $keanggotaanUtama,
            'publikasi_lain' => $publikasiLain,
            'dokumen_lain' => $dokumenLain,
            'keanggotaan_lain' => $keanggotaanLain,
            'master_baru' => $masterBaru,
        ];

        return $data;
    }

    public function handleMultiple(Array $dataPublikasi, Array $tempData, String $parentNameField, Array $childFields)
    {
        $result = collect([]);
        collect($tempData)->each(function($item, $key) use (&$result, &$childFields, $dataPublikasi, $parentNameField) {

            collect($childFields)->each(function($field) use (&$item, &$key, $dataPublikasi) {

                if ($field['name_field'] == $key) switch ($field['tipe_field']) {
                    case 'autocomplete':
                        if ($field['name_field'] != "id_publikasi_$key") {
                            $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                            $master = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $item['uuid_' . $key]) : $this->getTaxonomyID($optionMetaData[1], $item['uuid_' . $key]);

                            $item[$field['name_field']] = $item[$field['name_field']] ?? NULL;
                            $item['id_' . $field['name_field']] = $master ?? NULL;
                        }
                        break;
                    case 'autoselect':
                        $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                        $master = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $item['uuid_' . $key]) : $this->getTaxonomyID($optionMetaData[1], $item['uuid_' . $key]);

                        $item['id_' . $field['name_field']] = $master ?? NULL;
                        break;
                    case 'select':
                        $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                        $master = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $item['uuid_' . $key]) : $this->getTaxonomyID($optionMetaData[1], $item['uuid_' . $key]);

                        $item[$field['name_field']] = $master ?? NULL;
                        break;
                    case 'radio':
                        $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                        $master = (is_array($optionMetaData) && $optionMetaData[0] == 'master') ? $this->getMasterDataID($optionMetaData[1], $item['uuid_' . $key]) : $this->getTaxonomyID($optionMetaData[1], $item['uuid_' . $key]);

                        $item[$field['name_field']] = $master ?? NULL;
                        break;
                    case 'multiple_select':
                        $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                        $master = collect($item[$field['name_field']])->map(function ($value) use ($optionMetaData) {
                            return ($optionMetaData[0] == 'master') ?
                                DB::table(($optionMetaData[1] == 'matakuliah') ? "$optionMetaData[1]" : "publikasi_$optionMetaData[1]")->where('id', $this->getMasterDataID($optionMetaData[1], $value))->first() : terms::where('id', $this->getTaxonomyId($optionMetaData[1], $value))->first();
                        })->toArray();
                        $values = collect($master)->map(function ($value) use ($optionMetaData) {
                            $value = json_decode(json_encode($value), true);
                            return ['uuid' => $value['uuid'], 'value' => ($optionMetaData[0] === 'master') ? $value["nama_$optionMetaData[1]"] : $value['nama_term'], 'id' => $value['id']];
                        })->all();
                        $item[$field['name_field']] = json_encode($values ?: []);
                        break;
                    case 'multiple_autoselect':
                        $optionMetaData = ($field['options']) ? explode('-', $field['options']) : null;
                        $master = collect($item[$field['name_field']])->map(function ($value) use ($optionMetaData) {
                            return ($optionMetaData[0] == 'master') ?
                                DB::table(($optionMetaData[1] == 'matakuliah') ? "$optionMetaData[1]" : "publikasi_$optionMetaData[1]")->where('id', $this->getMasterDataID($optionMetaData[1], $value))->first() : terms::where('id', $this->getTaxonomyId($optionMetaData[1], $value))->first();
                        })->toArray();
                        $values = collect($master)->map(function ($value) use ($optionMetaData) {
                            $value = json_decode(json_encode($value), true);
                            return ['uuid' => $value['uuid'], 'value' => ($optionMetaData[0] === 'master') ? $value["nama_$optionMetaData[1]"] : $value['nama_term'], 'id' => $value['id']];
                        })->all();
                        $item[$field['name_field']] = json_encode($values ?: []);
                        break;
                    default:
                        $item[$field['name_field']] = $item[$field['name_field']];
                        break;
                }

            });

            //$item['id_' . $parentNameField] = (!empty($item['id_' . $parentNameField])) ? $item['id_' . $parentNameField] : DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
            $item['id'] = (!empty($item['id'])) ? $item['id'] : DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
            $item['uuid_' . $parentNameField] = (!empty($item['uuid_' . $parentNameField])) ? $item['uuid_' . $parentNameField] : Str::uuid()->toString();

            $item['flag_remunerasi'] = (!empty($item['flag_remunerasi'])) ? $item['flag_remunerasi'] : 0;
            $item['flag_pak'] = (!empty($item['flag_pak'])) ? $item['flag_pak'] : 0;
            $item['flag_bkd'] = (!empty($item['flag_bkd'])) ? $item['flag_bkd'] : 0;

            $result->push($item);
        });

        return $result->toArray();
    }

    public function setDuplicatePublications(array $dataPublikasi, Request $request, array $formArray = null)
    {
        $method = $dataPublikasi['method'] ?? $request->method();
        $idPublikasi = $dataPublikasi['id_publikasi'] ?? null;
        $publikasi = $dataPublikasi['publikasi'] ?? null;
        $pegawai = $dataPublikasi['pegawai'] ?? null;
        $flagAktif = $dataPublikasi['flag_aktif'] ?? null;
        $tglInput = $dataPublikasi['tgl_input'] ?? date('Y-m-d');
        $userInput = $dataPublikasi['user_input'] ?? null;
        $tglUpdate = $dataPublikasi['tgl_update'] ?? date('Y-m-d');
        $userUpdate = $dataPublikasi['user_update'] ?? null;
        $data = [];

        $pegawaiInternal = $dataPublikasi['pegawai'] ?? null;

        // Publikasi Baru (Usulan)
        $tempPublication = $publikasi;
        $tempPublication['id'] = \Illuminate\Support\Facades\DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort;
        $tempPublication['id_publikasi_status'] = status::where('kd_status', 'USL')->first()['id'];
        $tempPublication['id_publikasi_peran'] = $keanggotaan['peran_anggota'] ?? null;
        $tempPublication['id_pegawai'] = ($pegawaiInternal) ? $pegawaiInternal->id :null;

        // Surat Dokumen Baru (Usulan)
        $dataPublikasiLain = ['method' => 'POST', 'id_publikasi' => $tempPublication['id'], 'publikasi' => $tempPublication, 'pegawai' => $pegawaiInternal, 'flag_aktif' => $flagAktif, 'user_update' => $userUpdate, 'tgl_update' => $tglUpdate];
        if (isset($pegawaiInternal->id)) {
            array_push($dokumenLain, ['id_pegawai' => $pegawaiInternal->id, 'dokumen' => $this->handleDocuments($dataPublikasiLain, $request)]);
        }

        // Keanggaotaan Baru (Usulan)
        $members = [];
        foreach ($request->input('keanggotaan') as $key => $valueOther) {
            $instansiBaru = instansi::where('uuid', $valueOther['uuid_instansi_anggota'])->first();
            $negaraBaru = (isset($valueOther['uuid_negara_anggota'])) ? negara::where('uuid', $valueOther['uuid_negara_anggota'])->first() : null;
            array_push($members, ($valueOther['nama_penulis'] === $pegawaiInternal['nama']) ? [
                'nama_penulis' => $pegawai['nama'],
                'peran_anggota' => $publikasi['id_publikasi_peran'] ?: null,
                'negara_anggota' => $negaraBaru['id'] ?? null,
                'instansi_anggota' => $instansiBaru['id'] ?? null,
                'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'])->first()['id'],
                'nik_keanggotaan' => ($instansiBaru['kd_instansi'] == 'UII') ? $pegawai['nik'] : null,
                'pegawai_eksternal' => ($instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                'flag_aktif' => $flagAktif,
                'tgl_input' => $tglInput,
                'user_input' => $userInput,
                'tgl_update' => $tglUpdate,
                'user_update' => $userUpdate,
                'uuid' => Str::uuid()->toString(),
            ] : [
                'nama_penulis' => $valueOther['nama_penulis'],
                'peran_anggota' => peran::where('uuid', $valueOther['uuid_peran_anggota'])->first()['id'] ?: null,
                'negara_anggota' => $negaraBaru['id'] ?? null,
                'instansi_anggota' => $instansiBaru['id'] ?? null,
                'status_anggota' => statusAnggota::where('uuid', $valueOther['uuid_status_anggota'])->first()['id'],
                'nik_keanggotaan' => ($instansiBaru['kd_instansi'] == 'UII') ? $pegawaiInternal['nik'] : null,
                'pegawai_eksternal' => ($instansiBaru['kd_instansi'] == 'UII') ? null : $valueOther['nama_penulis'],
                'flag_aktif' => $flagAktif,
                'tgl_input' => $tglInput,
                'user_input' => $userInput,
                'tgl_update' => $tglUpdate,
                'user_update' => $userUpdate,
                'uuid' => Str::uuid()->toString(),
            ]);
        }
        return null;
    }
}
