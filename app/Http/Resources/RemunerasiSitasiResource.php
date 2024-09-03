<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RemunerasiSitasiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_sitasi'           => $this->id,
            'id'                  => $this->id,
            'nama'                => $this->pegawai->nama,
            'nik'                 => $this->pegawai->nik,
            'kd_bentuk_publikasi' => $this->bentukpublikasi->kd_bentuk_publikasi,
            'bentuk_publikasi'    => $this->bentukpublikasi->bentuk_publikasi,
            'status'              => $this->status->status,
            'kd_status'           => $this->status->kd_status,
            'sitasi_jenis'        => $this->sitasi_jenis,
            'sitasi_total'        => $this->sitasi_total,
            'sitasi_link'         => $this->sitasi_link,
            'tgl_input'           => $this->tgl_input,
            'path_file'           => $this->path_file ?? null,
            'url_file'            => $this->url_file ?? null,
            'uuid'                => $this->uuid,
            'sitasi_meta'         => RemunerasiSitasiMetaResource::collection($this->sitasiMeta),
        ];
    }
}
