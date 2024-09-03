<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use \App\Http\Controllers\FileController;
use Exception;
use Illuminate\Http\Request;
use App\Models\PegawaiModel AS Pegawai;

use App\Models\BentukModel as bentuk;
use App\Models\VersiFormModel as versiForm;
use App\Models\FormModel as form;
use App\Models\StatusModel as status;
use App\Models\PeranModel as peran;
use App\Models\PublikasiModel as publikasi;
use App\Models\PublikasiMetaModel as publikasimeta;
use App\Traits\MasterData;
use App\Traits\TaxonomyTrait;
use App\Traits\UmumTrait;
use App\Traits\FormByKodeTrait;

class PublikasiRemunerasiController extends Controller {
    use MasterData;
    use TaxonomyTrait;
    use FileController;
    use UmumTrait;
    use FormByKodeTrait;
    protected $enviroment;
    public function __construct() {
        // $this->middleware('auth');
        $this->enviroment=env('APP_ENV', 'UNDEFINED');
    }

    protected function get_publikasi($pegawaiId,$param){
        $raw_sql=Publikasi::select(
            'f.kd_versi AS kd_form_versi',
            'b.nama',
            'b.gelar_depan',
            'b.gelar_belakang',
            'c.bentuk_publikasi',
            'c.kd_bentuk_publikasi',
            'c.uuid AS uuid_bentuk_publikasi',
            'd.status',
            'd.kd_status',
            'd.uuid AS uuid_status',
            'e.peran',
            'e.kd_peran',
            'e.uuid AS uuid_peran',
            'publikasi.*',
            'g.nama_jenis AS jenis_publikasi',
            'g.kd_jenis AS kd_jenis_publikasi',
            'g.uuid AS uuid_jenis_publikasi',
        )->leftJoin('pegawai AS b','b.id','=','publikasi.id_pegawai')->
        leftJoin('publikasi_bentuk AS c','c.id','=','publikasi.id_publikasi_bentuk')->
        leftJoin('publikasi_status AS d','d.id','=','publikasi.id_publikasi_status')->
        leftJoin('publikasi_peran AS e','e.id','=','publikasi.id_publikasi_peran')->
        leftJoin('publikasi_form_versi AS f','f.id','=','publikasi.id_publikasi_form_versi')->
        leftJoin('publikasi_jenis AS g','g.id','=','publikasi.id_publikasi_jenis')->
        where('publikasi.id_pegawai',$pegawaiId)->
        where('publikasi.flag_aktif', true)->
        orderBy('publikasi.tgl_update', 'desc');
        if($param=='usulan'){
            $raw_sql->where('d.kd_status', 'USL');
        }else{
            $raw_sql->where('d.kd_status','!=', 'USL');
        }
        return $raw_sql;
    }
    public function getByNIK(Request $request, $nik){
        $status=200;
        try {
        
            $pegawai=$this->pegawaibynik($nik);
            if(!$pegawai) throw new Exception('NIK tidak ditemukan',400);
            $bentukForm=form::select('name_field')->where('name_field','like','%judul%')->get();
            foreach($bentukForm AS $index => $row){
                $_bentukForm[$index]=$row->name_field;
            }
            $listJudul=collect($_bentukForm)->unique();           
            $getUsulan=publikasi::select(
            'publikasi.id',    
            'publikasi_peran.peran AS peran',
            'publikasi.tgl_publikasi',
            'publikasi.uuid',
            'publikasi.flag_aktif',
            )->
            join('publikasi_peran','publikasi_peran.id','=','publikasi.id_publikasi_peran')->
            where('publikasi.id_pegawai',$pegawai->id)->
            where('publikasi.flag_aktif',true);
            $count=$getUsulan->count();
            $limit=($request->input('limit')) ? (int) $request->input('limit'): $count;
            $offset=($request->input('offset')) ? (int) $request->input('offset'): 0;
            $data=$getUsulan->get()->map(function($value) use($listJudul){
                $metadata=DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('flag_aktif',true)->get();
                foreach($metadata AS $meta_value){
                    if(in_array($meta_value->key,$listJudul->toArray())){
                        $_value['judul_karya']=$meta_value->value;
                    }
                }
                $_value['peran']=$value->peran;
                $_value['tgl_publikasi']=$value->tgl_publikasi;
                $_value['uuid']=$value->uuid;                
                return $_value;

            })->skip($offset)->take($limit);
            $respon=[
                'count'=>count($data),
                'data'=>$data,
            ];
        } catch (Exception $e) {
            $info=$e->getMessage().', baris: '.$e->getLine();
            $status=400;
            $respon=[
                'message'=>$info,
            ];
        }
        return response()->json($respon, $status);
    }
    public function getByNIKOLD(Request $request, $nik){
        $count=0;
        $limit=0;
        $offset=0;
        $status=200;
        try {
            $pegawai=$this->pegawaibynik($nik);
            if(!$pegawai) throw new Exception('NIK tidak ditemukan',400);
            $getUsulan=$this->get_publikasi($pegawai->id,'usulan');
            $getNonUsulan=$this->get_publikasi($pegawai->id,'nonusulan');
            //------------------------MENANGKAP QUERY STRING--------------------------
            if($request->input('uuid_bentuk')){
                $jenispublikasi=$this->bentukpublikasibykolom('uuid',$request->input('uuid_bentuk'));
                if(!$jenispublikasi) throw new Exception("Bentuk publikasi tidak ditemukan", 400);
                $getUsulan=$getUsulan->where('publikasi.id_publikasi_bentuk',$jenispublikasi->id);
                $getNonUsulan=$getNonUsulan->where('publikasi.id_publikasi_bentuk',$jenispublikasi->id);
            }
            if($request->input('uuid_status')){
                $statuspublikasi=$this->statuspublikasibykolom('uuid',$request->input('uuid_status'));
                if(!$statuspublikasi) throw new Exception("Status publikasi tidak ditemukan", 400);
                $getUsulan=$getUsulan->where('publikasi.id_publikasi_status',$statuspublikasi->id);
                $getNonUsulan=$getNonUsulan->where('publikasi.id_publikasi_status',$statuspublikasi->id);
            }
            if($request->input('uuid_jenis')){
                $jenispublikasi=$this->jenispublikasibykolom('uuid',$request->input('uuid_jenis'));
                if(!$jenispublikasi) throw new Exception("Jenis publikasi tidak ditemukan", 400);
                $getUsulan=$getUsulan->where('publikasi.id_publikasi_jenis', $jenispublikasi->id);
                $getNonUsulan=$getNonUsulan->where('publikasi.id_publikasi_jenis', $jenispublikasi->id);
            }
            if($request->input('tahun')){
                $tahunpencarian=$request->input('tahun');
                if(!$tahunpencarian)throw new Exception("Tahun tidak ditemukan", 400);
                $getUsulan=$getUsulan->whereYear('publikasi.tahun', $tahunpencarian);
                $getNonUsulan=$getNonUsulan->whereYear('publikasi.tahun', $tahunpencarian);
            }
            $getPublikasi=collect($getUsulan->get())->merge($getNonUsulan->get());
            $count=$getPublikasi->count();
            $limit=$count;
            $limit=($count < $limit) ? $count: $limit;
            $limit=($request->input('limit')) ? (int) $request->input('limit'): $limit;
            $offset=($request->input('offset')) ? (int) $request->input('offset'): 0;
            $dataPublikasi=[];
            $mergePublikasi=collect($getPublikasi)->skip($offset)->take($limit)->values();
            //GET JUDUL
            $bentukForm=form::select('name_field')->where('name_field','like','%judul%')->get();
            foreach($bentukForm AS $index => $row){
                $_bentukForm[$index]=$row->name_field;
            }
            $listJudul=$_bentukForm;
            foreach ($mergePublikasi as $keyPublikasi => $value) {
                // dd($value);
                $dataPublikasi[$keyPublikasi] = $value;
                $metadata=DB::table('publikasi_meta')->where('id_publikasi', $value->id)->where('flag_aktif',true)->get();
                $statuskeanggotaan = false;
                $statusfile = false;
                foreach ($metadata AS $row) {
                    if(strrpos($row->key,'keanggotaan')!==false){
                        $statuskeanggotaan=true;
                        $keanggotaan=unserialize($row->value);
                        // dd($keanggotaan);
                        $_keanggotaan=[];
                        if (count($keanggotaan) != 0) {
                            foreach ($keanggotaan as $key => $value) {
                                $_keanggotaan[$key]=$value;
                                foreach($value AS $_row => $_value){
                                    if(strrpos($_row,'instansi')!==false){
                                        $_keanggotaan[$key][$_row]=!empty($_value) ? $this->intansiPublikasiByKolom('id', $_value)->nama_instansi:NULL;
                                        $_keanggotaan[$key]['uuid_instansi_anggota']=!empty($_value) ? $this->intansiPublikasiByKolom('id', $_value)->uuid:NULL;    
                                    }else if(strrpos($_row,'status')!==false){
                                        $_keanggotaan[$key][$_row]=!empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->status_anggota:NULL;
                                        $_keanggotaan[$key]['uuid_status_anggota']=!empty($_value) ? $this->statusKeanggotaanPublikasiByKolom('id', $_value)->uuid:NULL;; 
                                    }else if(strrpos($_row,'peran')!==false){
                                        $_keanggotaan[$key][$_row]=!empty($_value) ? $this->peranpublikasibykolom('id', $_value)->peran:NULL;
                                        $_keanggotaan[$key]['uuid_peran_anggota']=!empty($_value) ? $this->peranpublikasibykolom('id', $_value)->uuid:NULL;                                                                             
                                    }else if(strrpos($_row,'penulis')!==false){
                                        $arraySerializeKeanggotaan[$_row]=$_value;
                                    }
                                }
                                // dd($_keanggotaan);
                                if(isset($value['uuid'])){
                                    $_keanggotaan[$key]['uuid_keanggotaan']=$value['uuid'];
                                }                                
                                //$removeValue=['uuid_instansi_anggota','uuid_status_anggota','uuid_peran_anggota','uuid'];
                                $removeValue=['id_instansi_anggota','id_status_anggota','id_peran_anggota','id','peran_anggota_lain'];
                                foreach ($removeValue as $keyRemove) {
                                    unset($_keanggotaan[$key][$keyRemove]);
                                }
                            }
                        }
                        $column_keanggotaan=$row->key;
                    }else if(strrpos($row->key,'dokumen')!==false){
                        $statusfile=true;
                        $rawberkas=unserialize($row->value);
                        $get_berkas=[];
                        if(count($rawberkas)!=0){
                            foreach ($rawberkas as $index => $value) {
                                $dokumen = ($value['path_file']) ? $this->getFile($value['path_file']) : '' ;
                                $get_berkas[$index]=$value;
                                $removeValue=['id'];
                                foreach ($removeValue as $keyRemove) {
                                    unset($get_berkas[$index][$keyRemove]);
                                }
                                if(isset($value['uuid'])) {
                                    $get_berkas[$index]['uuid_dokumen']=$value['uuid'];
                                }
                                if($dokumen){
                                    $get_berkas[$index]['url_file']=$dokumen['plainUrl'];
                                }else{
                                    $get_berkas[$index]['url_file']='';
                                }
                            }
                            $_get_berkas=collect($get_berkas)->map(function($value){
                                if(isset($value['uuid_keterangan'])){
                                    $keterangan=DB::table('publikasi_terms')->
                                    where('uuid',$value['uuid_keterangan'])->first();
                                    $value['keterangan']=$keterangan->nama_term??NULL;
                                }
                                return $value;
                            });
                        }
                        
                        //$berkas=$get_berkas;
                        $column_file=$row->key;
                    }else if(strrpos($row->key,'indeksi')!==false){
                        $dataPublikasi[$keyPublikasi][$row->key]=unserialize($row->value);
                    }else{
                        $dataPublikasi[$keyPublikasi][$row->key]=$row->value;
                        if(in_array($row->key,$listJudul)){
                            //$dataPublikasi[$keyPublikasi]['judul_publikasi']=$row->value;
                            $dataPublikasi[$keyPublikasi]['judul_artikel']=$row->value;
                        }
                    }
                }
                if($statusfile){
                    $dataPublikasi[$keyPublikasi][$column_file] = $_get_berkas;
                }
                if($statuskeanggotaan){
                    $dataPublikasi[$keyPublikasi][$column_keanggotaan]=$_keanggotaan;
                }
            }
            if($request->input('cari')){
                $pencarian=$request->input('cari');
                $publikasi=$dataPublikasi;
                // $pencarianPublikasi = array_filter($publikasi, function ($item) use ($pencarian) {
                //     if (stripos($item['judul_artikel'], $pencarian) !== false) {
                //         return true;
                //     }
                //     return false;
                // });   
                $pencarianPublikasi = collect($publikasi)->map(function($value) use($pencarian){
                    if (stripos($value['judul_artikel'], $pencarian) !== false) {
                        return $value;
                    }
                })->reject(function($value){
                    return empty($value);
                });                  
            }
            $result_publikasi=$request->input('cari') ? collect($pencarianPublikasi)->values():$dataPublikasi;              
            $respon=[
                'count'=>$count,
                'limit'=>$limit,
                'offset'=>$offset,
                'data'=>$result_publikasi,
            ];
        }catch(QueryException $e) {
            $info=$e->getMessage();
            $status=400;
            $respon=[
                'message'=>$info,
            ];
        }
        catch(Exception $e){
            $info=$e->getMessage().', baris: '.$e->getLine();
            //$status=$e->getCode();
            $status=400;
            $respon=[
                'message'=>$info,
            ];
        }
        return response()->json($respon, $status);
    }

}
