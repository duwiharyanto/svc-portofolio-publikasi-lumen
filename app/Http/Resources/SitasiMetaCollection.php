<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SitasiMetaCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'message' => 'Sitasi detail ditampilkan',
            'count'   => $this->collection->count(),
            'data'    => $this->collection->map(function ($sitasi) use ($request) {
                return (new SitasiMetaResource($sitasi))->toArray($request);
            }),
        ];
    }
}
