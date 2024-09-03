<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

use App\Models\PegawaiModel AS Pegawai;
use App\Models\PublikasiModel AS Publikasi;
use App\Models\JenisPublikasiModel AS JenisPublikasi;
use App\Models\StatusModel AS StatusPublikasi;
use App\Models\StatusAnggotaModel AS StatusAnggotaPublikasi;
use App\Models\PeranModel AS PeranPublikasi;
use App\Models\BentukUmumModel AS BentukUmumPublikasi;
use App\Models\BentukModel AS BentukPublikasi;
use App\Models\VersiFormModel AS VersiFormModel;
use App\Models\InstansiPublikasiModel AS InstansiPublikasi;
use App\Models\NegaraPublikasiModel AS negaraAnggota;
use App\Models\TermsModel as Terms;

trait MasterData {
    public function pegawaibynik($nik){
        return Pegawai::where('nik',$nik)->first();
    }
    public function pegawaibykolom($kolom,$key){
        return Pegawai::where($kolom,$key)->first();
    }
    public function publikasibyid($id){
        return Publikasi::where('id',$id)->first();
    }
    public function jenispublikasibyuuid($uuid){
        return JenisPublikasi::where('uuid',$uuid)->first();
    }
    public function jenispublikasibykolom($kolom,$key){
        return JenisPublikasi::where($kolom,$key)->first();
    }
    public function statuspublikasibyuuid($uuid){
        return StatusPublikasi::where('uuid',$uuid)->first();
    }
    public function statuspublikasibykolom($kolom,$key){
        return StatusPublikasi::where($kolom,$key)->first();
    }
    public function peranpublikasibyuuid($uuid){
        return PeranPublikasi::where('uuid',$uuid)->first();
    }
    public function peranpublikasibykolom($kolom,$key){
        return PeranPublikasi::where($kolom,$key)->first();
    }
    public function bentukumumpublikasibykolom($kolom,$key){
        return BentukUmumPublikasi::where($kolom,$key)->first();
    }
    public function bentukpublikasibykolom($kolom,$key){
        return BentukPublikasi::where($kolom,$key)->first();
    }
    public function negaraAnggotaByKolom($kolom,$key){
        return negaraAnggota::where($kolom,$key)->first();
    }
    public function generateId(){
        $data=[
            'id'=>DB::select('SELECT UUID_SHORT() AS uuidShort;')[0]->uuidShort,
            'uuid'=>Str::uuid()->toString(),
        ];
        return (object) $data;
    }
    public function intansiPublikasiByKolom($kolom,$key) {
        return InstansiPublikasi::where($kolom, $key)->first();
    }
    public function statusKeanggotaanPublikasiByKolom($kolom,$key) {
        return StatusAnggotaPublikasi::where($kolom, $key)->first();
    }
    protected function konversiToId($param,$uuid){
        switch ($param) {
            case 'id_publikasi_bentuk':
                return BentukPublikasi::where('uuid',$uuid)->first();
                break;
            case 'id_publikasi_status':
                return StatusPublikasi::where('uuid',$uuid)->first();
                break;
            case 'id_publikasi_jenis':
                return JenisPublikasi::where('uuid',$uuid)->first();
                break;
            case 'id_publikasi_peran':
                return PeranPublikasi::where('uuid',$uuid)->first();
                break;
            case 'uuid_peran':
                return PeranPublikasi::where('uuid',$uuid)->first();
                break;
            case 'uuid_status_publik':
                return Terms::where('uuid',$uuid)->first();
                break;
            case 'id_publikasi_form_versi':
                return VersiFormModel::where('uuid',$uuid)->first();
                break;
            default:
                return false;
                break;
        }
    }

    public function bentukPublikasibyKD($kdBentuk) {
        return BentukPublikasi::where('kd_bentuk', $kdBentuk)->first();
    }

    public function getTableActualName($name) {
        $actualName = "publikasi_$name" ;
        switch ($name) {
            case 'matakuliah':
                $actualName = "matakuliah" ;
                break;
            case 'mahasiswa_rekap':
                $actualName = "mahasiswa_rekap" ;
                break;
        }
        return $actualName ;
    }

    public function getMasterData(string $table, string $orderDirection = NULL, bool $isUnique = FALSE) {

        $result = NULL;

        // Initial query of table
        $db = DB::table("publikasi_$table");

        // Handler for every master table
        if ($table == "bentuk") {
            $db->select('kd_bentuk_publikasi', 'kd_bentuk_publikasi AS kd_opsi', 'bentuk_publikasi AS option_text_field', 'uuid');
        } else if ($table === "peran" || $table === "peran_alt_1" || $table === "peran_alt_2") {
            $db->select('kd_peran', 'kd_peran AS kd_opsi', 'peran AS option_text_field', 'uuid');
        } else if ($table === "negara") {
            $db->select('kd_negara', 'kd_negara AS kd_opsi', 'nama_negara AS option_text_field', 'uuid');
        } else if ($table === "instansi") {
            $db->select('kd_instansi', 'kd_instansi AS kd_opsi', 'nama_instansi AS option_text_field', 'uuid');
        } else if ($table === "status") {
            $db->select('kd_status', 'kd_status AS kd_opsi', 'status AS option_text_field', 'uuid');
        } else if ($table === "status_anggota") {
            $db->select('status_anggota AS option_text_field', 'jenis_anggota', 'uuid');
        } else if ($table === "jenis") {
            $db->select('kd_jenis', 'kd_jenis AS kd_opsi', 'nama_jenis AS option_text_field', 'uuid');
        } else if ($table === "jenis_anggota") {
            $db->select('jenis_anggota AS option_text_field', 'uuid');
        } else if ($table === "matakuliah") {
            $db = DB::table("$table")->select('matakuliah.nama_matakuliah AS option_text_field', 'c.organisasi AS nama_organisasi', 'matakuliah.uuid AS uuid')->join('kurikulum AS b', 'b.id', '=', 'matakuliah.id_kurikulum')->join('organisasi AS c', 'c.id', '=', 'b.id_organisasi')->where('matakuliah.flag_aktif', TRUE);
        } else if ($table === "mahasiswa_rekap") {
            $db = $db->select('nim', 'nim AS kd_opsi', 'nama_mahasiswa AS option_text_field', 'uuid');
        } else if ($table === "mata_uang") {
            $db = $db->select('kd_mata_uang', 'kd_mata_uang AS kd_opsi', 'mata_uang AS option_text_field', 'uuid');
        } else {
            $db->select('*');
        }

        // Query for get activated data only
        $db->where('flag_aktif', TRUE);

        // Query for get ordered data by order direction (ASC | DESC)
        if ($orderDirection != NULL) $db->orderBy('option_text_field', $orderDirection);

        // Get all data
        $result = $db->get();

        // Collection proccess fro get unique data and get value of array
        if ($isUnique) $result->unique('option_text_field')->values();

        return $result;
    }

    public function getMasterDataLimit(string $table, string $orderDirection = NULL, bool $isUnique = FALSE)
    {
        $result = NULL;

        // Initial query of table
        $db = DB::table("publikasi_$table");

        // Handler for every master table
        if ($table === "bentuk") {
            $db->select('kd_bentuk_publikasi', 'kd_bentuk_publikasi AS kd_opsi', 'bentuk_publikasi AS option_text_field', 'uuid');
        } else if ($table === "peran" || $table === "peran_alt_1" || $table === "peran_alt_2") {
            $db->select('kd_peran', 'kd_peran AS kd_opsi', 'peran AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "negara") {
            $db->select('kd_negara', 'kd_negara AS kd_opsi', 'nama_negara AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "instansi") {
            $db->select('kd_instansi', 'kd_instansi AS kd_opsi', 'nama_instansi AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "status") {
            $db->select('kd_status', 'kd_status AS kd_opsi', 'status AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "status_anggota") {
            $db->select('status_anggota AS option_text_field', 'jenis_anggota', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "jenis") {
            $db->select('kd_jenis', 'kd_jenis AS kd_opsi', 'nama_jenis AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "jenis_anggota") {
            $db->select('jenis_anggota AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else if ($table === "matakuliah") {
            $db = DB::table("$table")->select('matakuliah.nama_matakuliah AS option_text_field', 'c.organisasi AS nama_organisasi', 'matakuliah.uuid AS uuid')->join('kurikulum AS b', 'b.id', '=', 'matakuliah.id_kurikulum')->join('organisasi AS c', 'c.id', '=', 'b.id_organisasi')->where('matakuliah.flag_aktif', true);
        } else if ($table === "mahasiswa_rekap") {
            $db = $db->select('nim', 'nim AS kd_opsi', 'nama_mahasiswa AS option_text_field', 'uuid')->where('flag_aktif', TRUE);
        } else {
            $db->select('*')->where('flag_aktif', TRUE);
        }

        // Query for get ordered data by order direction (ASC | DESC)
        if ($orderDirection != NULL) $db->orderBy('option_text_field', $orderDirection);

        // Get all data
        $result = $db->take(20)->get();

        // Collection proccess fro get unique data and get value of array
        if ($isUnique) $result->unique('option_text_field')->values();

        return $result;
    }

    public function getMasterDataSearchKey($table, $searchKey = '', $parent = NULL) {
        $db = DB::table('publikasi_' . $table);
        if ($table === "unsur_kegiatan") {
            $db = $db->select('kd_unsur_kegiatan', 'kd_unsur_kegiatan AS kd_opsi', 'unsur_kegiatan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('kd_unsur_kegiatan', 'like', '%' . $searchKey . '%')->orWhere('unsur_kegiatan', 'like', '%' . $searchKey . '%')->get();
        } else if ($table === "jenis_pendidikan") {
            $db = $db->select('kd_jenis_pendidikan', 'kd_jenis_pendidikan AS kd_opsi', 'jenis_pendidikan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('kd_jenis_pendidikan', 'like', '%' . $searchKey . '%')->orWhere('jenis_pendidikan', 'like', '%' . $searchKey . '%')->get();
        } else if ($table === "negara") {
            $db = $db->select('kd_negara', 'kd_negara AS kd_opsi', 'nama_negara AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('kd_negara', 'like', '%' . $searchKey . '%')->orWhere('nama_negara', 'like', '%' . $searchKey . '%')->get();
        } else if ($table === "instansi") {
            $db = $db->select('kd_instansi', 'kd_instansi AS kd_opsi', 'kd_negara', 'nama_instansi AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->
            where(function ($query) use ($searchKey) {
                $query->orWhere('kd_instansi', 'like', '%' . $searchKey . '%')->orWhere('nama_instansi', 'like', '%' . $searchKey . '%');
            })->get();
            if ($parent) {
                $db = collect($db->toArray())->filter(function ($item) use ($parent) {
                    return $item->kd_negara == $parent->kd_negara;
                })->values();
            }
        } else if ($table === "status") {
            $db = $db->select('kd_status', 'kd_status AS kd_opsi', 'status AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('kd_status', 'like', '%' . $searchKey . '%')->orWhere('status', 'like', '%' . $searchKey . '%')->get();
        } else if ($table === "tahun_akademik") {
            $db = $db->select('nama_tahun_akademik AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('nama_tahun_akademik', 'like', '%' . $searchKey . '%')->orderBy('option_text_field', 'desc')->get()->unique('option_text_field')->values();
        } else if ($table === "semester") {
            $db = $db->select('kd_semester', 'kd_semester AS kd_opsi', 'semester AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->orWhere('kd_semester', 'like', '%' . $searchKey . '%')->orWhere('semester', 'like', '%' . $searchKey . '%')->get();
        } else if ($table === "matakuliah") {
            $db = DB::table("$table")->select('matakuliah.kd_matakuliah AS kd_opsi', 'matakuliah.nama_matakuliah AS option_text_field', 'c.organisasi AS nama_organisasi', 'matakuliah.uuid AS uuid')->join('kurikulum AS b', 'b.id', '=', 'matakuliah.id_kurikulum')->join('organisasi AS c', 'c.id', '=', 'b.id_organisasi')->Where('kd_matakuliah', 'like', '%' . $searchKey . '%')->orWhere('nama_matakuliah', 'like', '%' . $searchKey . '%')->where('matakuliah.flag_aktif', TRUE)->get()->unique('option_text_field')->values();
        } else if ($table === "mahasiswa_rekap") {
            $db = DB::table("$table")->select('nim', 'nim AS kd_opsi', 'nama_mahasiswa AS option_text_field', 'uuid')->where('nim', 'like', '%' . $searchKey . '%')->orWhere('nama_mahasiswa', 'like', '%' . $searchKey . '%')->get();
            //if ($parent) {
            //    $db = collect($db->toArray())->filter(function ($item) use ($parent) {
            //        return $item->kd_instansi == $parent->kd_negara;
            //    })->values();
            //}
        } else {
            $db = $db->select('*')->where('flag_aktif', TRUE)->get();
        }
        return $db;
    }

    public function getMasterDataByID($table, $id) {
        $db = DB::table($table);
        if ($table === "pendidikan_unsur_kegiatan") {
            $db = $db->select('kd_unsur_kegiatan', 'kd_unsur_kegiatan AS kd_opsi', 'unsur_kegiatan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "pendidikan_jenis_pendidikan") {
            $db = $db->select('kd_jenis_pendidikan', 'kd_jenis_pendidikan AS kd_opsi', 'jenis_pendidikan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "pendidikan_negara") {
            $db = $db->select('kd_negara', 'kd_negara AS kd_opsi', 'nama_negara AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "pendidikan_instansi") {
            $db = $db->select('kd_instansi', 'kd_instansi AS kd_opsi', 'nama_instansi AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "pendidikan_status") {
            $db = $db->select('kd_status', 'kd_status AS kd_opsi', 'status AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "pendidikan_tahun_akademik") {
            $db = $db->select('nama_tahun_akademik AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->orderBy('option_text_field', 'desc')->first();
        } else if ($table === "pendidikan_semester") {
            $db = $db->select('kd_semester', 'kd_semester AS kd_opsi', 'semester AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('id', $id)->first();
        } else if ($table === "matakuliah") {
            $db = DB::table("$table")->select('matakuliah.nama_matakuliah AS option_text_field', 'c.organisasi AS nama_organisasi', 'matakuliah.uuid AS uuid')->join('kurikulum AS b', 'b.id', '=', 'matakuliah.id_kurikulum')->join('organisasi AS c', 'c.id', '=', 'b.id_organisasi')->where('matakuliah.flag_aktif', TRUE)->where('matakuliah.id', $id)->first();
        } else if ($table === "mahasiswa_rekap") {
            $db = $db->select('nim', 'nim AS kd_opsi', 'nama_mahasiswa AS option_text_field', 'uuid')->where('id', $id)->first();
        } else if ($table === "mata_uang") {
            $db =  DB::table('publikasi_' . $table)->select('kd_mata_uang AS kd_opsi', 'mata_uang AS option_text_field', 'uuid')->where('id', $id)->first();
        } else {
            $db = $db->select('*')->where('flag_aktif', TRUE)->where('id', $id)->first();
        }
        return $db;
    }

    public function getMasterDataByUUID($table, $uuid) {
        $db = DB::table('publikasi_' . $table);
        if ($table === "unsur_kegiatan") {
            $db = $db->select('kd_unsur_kegiatan', 'kd_unsur_kegiatan AS kd_opsi', 'unsur_kegiatan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "jenis_pendidikan") {
            $db = $db->select('kd_jenis_pendidikan', 'kd_jenis_pendidikan AS kd_opsi', 'jenis_pendidikan AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "negara") {
            $db = $db->select('kd_negara', 'kd_negara AS kd_opsi', 'nama_negara AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "instansi") {
            $db = $db->select('kd_instansi', 'kd_instansi AS kd_opsi', 'nama_instansi AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "status") {
            $db = $db->select('kd_status', 'kd_status AS kd_opsi', 'status AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "tahun_akademik") {
            $db = $db->select('nama_tahun_akademik AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->orderBy('option_text_field', 'desc')->first();
        } else if ($table === "semester") {
            $db = $db->select('kd_semester', 'kd_semester AS kd_opsi', 'semester AS option_text_field', 'uuid')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        } else if ($table === "matakuliah") {
            $db = DB::table("$table")->select('matakuliah.nama_matakuliah AS option_text_field', 'c.organisasi AS nama_organisasi', 'matakuliah.uuid AS uuid')->join('kurikulum AS b', 'b.id', '=', 'matakuliah.id_kurikulum')->join('organisasi AS c', 'c.id', '=', 'b.id_organisasi')->where('matakuliah.flag_aktif', TRUE)->where('matakuliah.uuid', $uuid)->first();
        } else if ($table === "mahasiswa_rekap") {
            $db = DB::table("$table")->select('nim', 'nim AS kd_opsi', 'nama_mahasiswa AS option_text_field', 'uuid')->where('uuid', $uuid)->first();
        } else if ($table === "mata_uang") {
            $db =  $db->select('kd_mata_uang AS kd_opsi', 'mata_uang AS option_text_field', 'uuid')->where('uuid', $uuid)->first();
        } else {
            $db = $db->select('*')->where('flag_aktif', TRUE)->where('uuid', $uuid)->first();
        }
        return $db;
    }

    public function getMasterDataID($key, $uuid) {
        $table = ($key == 'matakuliah') ? "matakuliah" : 'publikasi_' . $key;
        $data = DB::table("$table")->select("*")->where('uuid', $uuid)->first();
        return ($data) ? $data->id : NULL;
    }

    public function addNewMasterData(String $table, String $uuid, $data) {
        $process = NULL;
        //$result = [
        //    'status' => 'success',
        //    'message' => 'Success running the function but no data is entered!'
        //];
        try {
            //DB::beginTransaction();
            $result['status'] = 'success';
            $result['message'] = 'Success creating master data..!';
            $validation = ['uuid' => $uuid ?: Str::uuid()->toString() ];
            $process = DB::table($table);
            if (!$process) {
                throw new Exception("Table not found..!");
                //$result['status'] = 'error';
                //$result['message'] = 'Error creating master data..!';
            }
            $process = $process->updateOrInsert($validation, $data);
            if (!$process) {
                throw new Exception("Error creating master data. Please report to the administrator..!");
                //$result['status'] = 'error';
                //$result['message'] = 'Error creating master data..!';
            }
            //DB::commit();
        } catch (Exception $e) {
            //DB::rollBack();
            Log::error('Exception on create master data: ' . $e);
            //$result['status'] = 'error' ;
            //$result['message'] = 'Error creating master data! There is an error on the server, please try again later.' ;
            //$result['stack_trace'] = $e ;
        }
        //return $result;
    }

}
