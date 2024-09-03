<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class RequestFilterMiddleware {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {

        if ($request->isJson()) {
            $this->clean($request->json(), $request->method());
        } else {
            $this->clean($request->request, $request->method());
        }

        return $next($request);
    }

    /**
     * Clean the request's data by removing mask from phonenumber.
     *
     * @param  \Symfony\Component\HttpFoundation\ParameterBag  $bag
     * @return void
     */
    private function clean(ParameterBag $bag, $method){
        $bag->replace($this->cleanData($bag->all(), $method));
    }

    /**
     * Check (method POST) the parameters and/or clean value
     *
     * @param  array $data
     * @return array
     */
    private function cleanData(Array $data, $method) {
        // Filter berdasarkan key (keanggotaan) apabila NULL maka akan dihapus
        $filteredByKey = collect($data)->map(function ($value, $key) use ($data, $method) {
            switch ($key) {
                case 'keanggotaan':
                    return collect($value)->filter(function ($v, $k) use ($value) {
                        // if ($v['nama_peneliti'] === '' || $v['nama_peneliti'] === "" || $v['nama_peneliti'] === NULL) {
                        //     return NULL;
                        // }else 
                        // if(isset($v['nama_penulis'])){
                        //     if($v['nama_penulis'] === '' || $v['nama_penulis'] === "" || $v['nama_penulis'] === NULL){
                        //         return NULL;
                        //     }else {
                        //         return $v;
                        //     }
                        // }else if(isset($v['nama_penulis_lain'])){
                        //     if($v['nama_penulis_lain'] === '' || $v['nama_penulis_lain'] === "" || $v['nama_penulis_lain'] === NULL){
                        //         return NULL;
                        //     }else {
                        //         return $v;
                        //     }
                        // }
                        return $v;
                    })->all();
                    break;
                case 'dokumen':
                    return collect($value)->filter(function ($v, $k) use ($value) {
                        // if ($v['keterangan'] === '' || $v['keterangan'] === "" || $v['keterangan'] === NULL) {
                        //     return NULL;
                        // } else {
                        //     return $v;
                        // }
                        return $v;
                    })->all();
                    
                    break;                    
                default:
                    return $value;
                    break;
            }
        })->all();

        // Filter berdasarkan value (keseluruhan) apabila string kosong ("") maka akan dirubah ke NULL
        $filteredByValue = collect($filteredByKey)->map(function ($value, $key) {
            if ($value === '' || $value === "") {
                return NULL;
            } else {
                return $value;
            }
        })->all();
        //dd($filteredByValue);
        return $filteredByValue;
    }
}
