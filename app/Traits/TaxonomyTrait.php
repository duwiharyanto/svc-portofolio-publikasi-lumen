<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

use App\Models\PegawaiModel AS Pegawai;
use App\Models\DevPublikasiModel AS Publikasi;
use App\Models\JenisModel AS JenisPublikasi;
use App\Models\StatusModel AS StatusPublikasi;
use App\Models\PeranModel AS PeranPublikasi;
use App\Models\DevBentukModel AS BentukPublikasi;
use App\Models\TermTaxonomyModel as taxonomy;
use App\Models\TermsModel as terms;


trait TaxonomyTrait {

    public function getTaxonomyID($taxonomy, $uuid) {
        return taxonomy::with(['terms' => function ($query) use ($uuid) {
            $query->where('uuid', $uuid)->first();
        }])->where('taxonomy', $taxonomy)->first()['terms']->where('uuid', $uuid)->first()['id'];
    }

    public function getTaxonomyByUUID($taxonomy, $uuid) {
        return taxonomy::with(['terms' => function ($query) use ($uuid) {
            $query->where('uuid', $uuid)->first();
        }])->where('taxonomy', $taxonomy)->first()['terms']->where('uuid', $uuid)->first();
    }

    public function getTaxonomyByID($taxonomy, $id) {
        return taxonomy::with(['terms' => function ($query) use ($id) {
            $query->where('id', $id)->first();
        }])->where('taxonomy', $taxonomy)->first()['terms']->where('id', $id)->first();
    }

    public function getTermOfTaxonomy($taxonomy) {
        return taxonomy::with('terms')->where('taxonomy', $taxonomy)->first()['terms'];
    }

    public function getTermOfTaxonomyDB($taxonomy) {
        $taxonomy = DB::table('publikasi_term_taxonomy')->select('*')->where('taxonomy', $taxonomy)->first();
        return DB::table('publikasi_terms')->select('kd_term', 'kd_term AS kd_opsi', 'nama_term AS option_text_field' , 'uuid')->where('id_publikasi_term_taxonomy', $taxonomy->id)->where('flag_aktif', TRUE)->orderBy('option_text_field')->get();
    }

    public function getTermOfTaxonomyLimitDB($taxonomy)
    {
        $taxonomy = DB::table('publikasi_term_taxonomy')->select('*')->where('taxonomy', $taxonomy)->first();
        return DB::table('publikasi_terms')->select('kd_term', 'kd_term AS kd_opsi', 'nama_term AS option_text_field', 'uuid')->where('id_publikasi_term_taxonomy', $taxonomy->id)->where('flag_aktif', TRUE)->orderBy('option_text_field')->take(25)->get();
    }

    public function getTermOfTaxonomyDBSearchKey(String $taxonomy, $searchKey = '', $parent = NULL) {
        $taxonomyRaw = DB::table('publikasi_term_taxonomy')->select('*')->where('taxonomy', $taxonomy);
        $taxonomy = ($parent) ? $taxonomyRaw->where('id_publikasi_taxonomy', $parent->id)->first() : $taxonomyRaw->first() ;
        return DB::table('publikasi_terms')->select('kd_term', 'kd_term AS kd_opsi', 'nama_term AS option_text_field', 'uuid')->where('id_publikasi_term_taxonomy', $taxonomy->id)->where('flag_aktif', TRUE)->orWhere('kd_term', 'like', '%' . $searchKey . '%')->orWhere('nama_term', 'like', '%' . $searchKey . '%') ?: NULL;
    }

}
