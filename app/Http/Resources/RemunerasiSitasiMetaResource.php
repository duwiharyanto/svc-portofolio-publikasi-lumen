<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RemunerasiSitasiMetaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id_sitasi_meta'            => $this->id,
            'sitasi_jumlah'             => $this->sitasi_jumlah,
            'sitasi_tahun'              => $this->sitasi_tahun,
            'catatan'                   => $this->catatan_remunerasi,
            'flag_aktif'                => $this->flag_aktif,
            'flag_perbaikan_remunerasi' => $this->flag_perbaikan_remunerasi,
            'flag_remunerasi'           => $this->flag_remunerasi,
            'kd_status'                 => $this->status ? $this->status->kd_status : null,
            'status'                    => $this->status ? $this->status->status : null,
            'gambar_halaman'            => $this->sitasiFile ? $this->sitasiFile->nama_file : null,
            'path_file'                 => $this->sitasiFile ? $this->sitasiFile->path_file : null,
            'url_file'                  => $this->sitasiFile ? $this->sitasiFile->url_file : null,
            'uuid'                      => $this->uuid,
        ];
    }
}
