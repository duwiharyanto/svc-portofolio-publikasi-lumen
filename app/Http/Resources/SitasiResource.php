<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SitasiResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'message' => 'Data Sitasi berhasil diambil',
            'data'    => [
                'nama'                => $this->pegawai->nama,
                'nik'                 => $this->pegawai->nik,
                'status'              => $this->status->status,
                'kd_status'           => $this->status->kd_status,
                'bentuk_publikasi'    => $this->bentukPublikasi->bentuk_publikasi,
                'kd_bentuk_publikasi' => $this->bentukPublikasi->kd_bentuk_publikasi,
                'sitasi_jenis'        => $this->sitasi_jenis,
                'sitasi_total'        => $this->sitasi_total,
                'sitasi_link'         => $this->sitasi_link,
                'tgl_input'           => $this->tgl_input,
                'uuid'                => $this->uuid,
                'sitasi_meta'         => SitasiMetaResource::collection($this->sitasiMeta),
            ],
        ];
    }
}
