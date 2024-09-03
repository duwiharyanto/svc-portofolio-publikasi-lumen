<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
| update comment
 */
// if (($_SERVER['SERVER_NAME'] == "localhost") || ($_SERVER['SERVER_NAME'] == "127.0.0.1")) {
//     putenv('AWS_ACCESS_KEY_ID=lmZPXbUgOtkgHa7yiTO6');
//     putenv('AWS_SECRET_ACCESS_KEY=uwW22P4SkVTdTsIS429eI58xe0GoarShtLH0Xrqm');
//     putenv('AWS_ENDPOINT=https://s3-dev.uii.ac.id');
//     putenv('AWS_ENDPOINT_UPLOAD=https://s3-dev.uii.ac.id');
//     putenv('AWS_REGION=us-east-1');
//     putenv('AWS_VERSION=latest');
// }

$router->group(['prefix' => 'public/api/v1'], function () use ($router) {
    $router->get('/', function () {
        $welcome = 'SVC Portofolio Publikasi Version 1.0.2';
        $date    = date('Y-m-d H:i:s');
        return $welcome . '</br>' . $date . '.';
    });
    $router->get('/generateId', 'v1\PublikasiCommandController@genID');
    $router->get('/master-matakuliah', 'v1\MatakuliahQueryController@getAll');
    $router->get('/master-mahasiswa', 'v1\MahasiswaQueryController@getBySearchKey');
    $router->get('/master-jenis', 'v1\JenisPublikasiQueryController@getAll');
    $router->get('/master-bentuk', 'v1\BentukPublikasiQueryController@getAll');
    $router->get('/master-bentuk-umum', 'v1\BentukUmumQueryController@getAll');
    $router->get('/master-peran', 'v1\PeranPublikasiQueryController@getAll');
    $router->get('/master-status', 'v1\StatusPublikasiQueryController@getAll');
    $router->get('/master-tahun', 'v1\TahunPublikasiQueryController@getAll');
    $router->get('/master-pegawai/{nik}', 'v1\PegawaiQueryController@getByNIK');
    $router->get('/master-pegawai', 'v1\PegawaiQueryController@getPegawaiBySearchKey');
    $router->put('/pegawai/{nik}', 'v1\PegawaiCommandController@updateByNIK');
    $router->get('/master-instansi', 'v1\InstansiQueryController@getInstansiAll');
    $router->post('/master-instansi', 'v1\InstansiQueryController@addInstansi');
    $router->get('/master/{uuidForm}', 'v1\PublikasiQueryController@findMasterData');
    $router->get('/master-instansi/verifikasi', 'v1\InstansiQueryController@getVerifikasiInstansi');
    $router->put('/master-instansi/verifikasi', 'v1\InstansiQueryController@verifikasiInstansi');
    $router->get('/cek-update-remun', 'v1\PublikasiCommandController@updateRemunerasi');

    $router->group(['prefix' => 'dashboard'], function () use ($router) {
        $router->get('/infobox/{nik}', 'v1\PublikasiQueryController@infoboxPublikasi');
    });

    // ENDPOINT UNTUK PENGATURAN KARYASISWA
    $router->group(['prefix' => 'pengaturan'], function () use ($router) {
        $router->get('/master-instansi', 'v1\InstansiPengaturanController@getAll');
        $router->post('/master-instansi', 'v1\InstansiPengaturanController@addData');
        $router->put('/master-instansi/{uuid}', 'v1\InstansiPengaturanController@updateData');
        $router->put('/master-instansi-flag/{uuid}', 'v1\InstansiPengaturanController@updateFlag');
        $router->delete('/master-instansi/{uuid}', 'v1\InstansiPengaturanController@deleteData');
    });

    // ! ENPOINT ADMIN PUBLIKASI
    $router->get('/publikasi/form', 'v1\PublikasiQueryController@getFormByKDBentuk');
    $router->get('/publikasi/{nik}', 'v1\PublikasiQueryController@getByNIK');
    $router->get('/publikasi/export/{nik}', 'v1\PublikasiQueryController@getExportByNIK');
    $router->delete('/publikasi/{uuid}', 'v1\PublikasiCommandController@deleteByUuid');
    $router->put('/publikasi/{nik}/verifikasi/{uuid}', 'v1\PublikasiCommandController@verifikasiByUUID');
    $router->put('/update-status-publikasi', 'v1\PublikasiCommandController@updateStatusPublikasi');
    $router->get('/check-similarity/{uuid}', 'v1\PublikasiCommandController@getSimilarity');

    // ! PORTIFOLIO DOSEN
    $router->group(['middleware' => 'requestFilter'], function () use ($router) {
        // CHANGE PUBLICATION TYPE
        $router->put('/publikasi/change-publication-type[/{nik}]', 'v1\PublikasiCommandController@changePublicationTypeByNIK');
        //CREATE
        $router->post('/publikasi/{nik}', 'v1\PublikasiCommandController@create');
        // UPDATE
        $router->put('/publikasi[/{nik}]', 'v1\PublikasiCommandController@updateByNIK');

    });
    $router->group(['prefix' => 'sitasi'], function () use ($router) {
        $router->get('/master-karya', 'v1\Sitasi\KaryaQueryController@index');
        $router->get('/publikasi/{nik}', 'v1\Sitasi\SitasiQueryController@sitasi');
        // $router->post('/publikasi/{nik}', 'v1\Sitasi\SitasiCommandController@insert');
        $router->post('/publikasi/{nik}', 'v1\Sitasi\SitasiCommandController@insertSitasiUtama');
        $router->put('/publikasi/{nik}', 'v1\Sitasi\SitasiCommandController@insert');
        $router->delete('/publikasi/{uuid}', 'v1\Sitasi\SitasiCommandController@delete');
        $router->post('/meta', 'v1\Sitasi\SitasiMetaCommandController@insert');
        $router->get('/sinkronremunerasi/{uuid}', 'v1\Sitasi\SitasiCommandController@updateRemunerasi');
        //! Routting sitasi detail
        $router->get('/publikasi/{uuid}/detail', 'v1\Sitasi\SitasiCommandController@getDetailSitasi');
        // $router->get('/publikasi/sitasi-utama/detail', 'v1\Sitasi\SitasiCommandController@getDetailSitasi');
        $router->post('/publikasi/{nik}/detail', 'v1\Sitasi\SitasiCommandController@insertDetailSitasi');
        $router->put('/publikasi/{uuid}/delete', 'v1\Sitasi\SitasiCommandController@deleteDetailSitasi');
        $router->get('/publikasi/year/{uuid}/detail', 'v1\Sitasi\SitasiCommandController@getYearDetailSitasi');
        //! Test Routing
        $router->get('/publikasi/{uuid}/sinkron-remunerasi', 'v1\Sitasi\SitasiCommandController@sinkronRemunerasi');
    });
});
