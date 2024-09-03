<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SitasiMetaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            // 'id_sitasi_meta'            => $this->id,
            'sitasi_jumlah'             => $this->sitasi_jumlah,
            'sitasi_tahun'              => $this->sitasi_tahun,
            'catatan'                   => $this->catatan_remunerasi,
            'flag_aktif'                => $this->flag_aktif,
            'flag_tolak_remunerasi'     => $this->flag_tolak_remunerasi,
            'flag_perbaikan_remunerasi' => $this->flag_perbaikan_remunerasi,
            // 'tgl_input'           => $this->created_at,
            'kd_status'                 => $this->status ? $this->status->kd_status : null,
            'status'                    => $this->status ? $this->status->status : null,
            'gambar_halaman'            => $this->sitasiFile ? $this->sitasiFile->nama_file : null,
            'gambar_halaman_path'       => $this->sitasiFile ? $this->sitasiFile->path_file : null,
            'gambar_halaman_url'        => $this->sitasiFile ? $this->sitasiFile->url_file : null,
            // 'gambar_halaman_file'       => $this->sitasiFile ? $this->sitasiFile->url_file : null,
            'gambar_halaman_uuid'       => $this->sitasiFile ? $this->sitasiFile->uuid : null,
            'uuid'                      => $this->uuid,
        ];
    }
}
