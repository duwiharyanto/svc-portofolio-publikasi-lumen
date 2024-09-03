<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\VersiFormModel as versiForm;
use App\Models\FormModel as form;
use App\Models\PublikasiModel as Publikasi;
use App\Models\JenisModel as JenisPublikasi;
use App\Models\PeranModel as PeranPublikasi;
use App\Models\BentukModel as bentuk;

trait ValidationTrait {

	public function validationNewMaster(Request $request, $forms = NULL) {
		$errorStatusCode = 400;
		$successStatusCode = 200;
		$formConfiguration = ($forms) ? collect($forms->toArray()) : NULL ;
		if (!$forms) {
			$dateNow = date('Y-m-d');
			$uuidPublication = $request->input('uuid_publikasi');
			$previousPublication = publikasi::where('uuid', $uuidPublication)->first();
			$type = bentuk::where('id', $previousPublication['id_publikasi_bentuk'])->first();
			$versiForm = versiForm::whereRaw("'$dateNow' BETWEEN `tgl_mulai` AND `tgl_selesai`")->orWhere('flag_aktif', TRUE)->where('id_publikasi_bentuk', $type->id)->first();
			$formConfiguration = $formConfiguration ?: collect(form::where('id_publikasi_form_versi', $versiForm->id)->where('flag_aktif', TRUE)->get()->toArray());
		}

		$matchFields = $formConfiguration->map(function ($item) use ($request) {
			$data = collect([]) ;
			$condition = ($request->has($item['name_field'])) && (($request->input($item['name_field']) != NULL || $request->input($item['name_field']) != "") && ($request->input('uuid_' . $item['name_field']) == NULL || $request->input('uuid_' . $item['name_field']) == "")) ;
			switch ($item['tipe_field']) {
				case 'autocomplete':
					$data = $data->merge(($condition) ? [
						'label' => $item['label'],
						'name_field' => $item['name_field'],
						'value' => $request->input($item['name_field']),
					] : NULL);
					break;
				case 'multiple':
					$formsInMultiple = form::where('id_publikasi_form_induk', $item['id'])->get();
					foreach($formsInMultiple->toArray() as $child) {
						foreach ($request->input($item['name_field']) as $key => $value) {
							$condition = ($request->has("$item[name_field].$key.$child[name_field]")) && (($request->input("$item[name_field].$key.$child[name_field]") != NULL || $request->input("$item[name_field].$key.$child[name_field]") != "") && ($request->input("$item[name_field].$key.uuid_$child[name_field]") == NULL || $request->input("$item[name_field].$key.uuid_$child[name_field]") == ""));
							switch ($child['tipe_field']) {
								case 'autocomplete':
									$data = $data->merge(($condition) ? [
										'label' => $child['label'],
										'name_field' => $child['name_field'],
										'value' => $request->input("$item[name_field].$key.$child[name_field]")
									] : NULL );
									break;
							}
						}
					}
					break;
			}
			return $data->all() ;
		})->filter()->values();

		$fields = $matchFields->map(function ($item) {
			return $item['label'];
		})->implode(', ');

		$fieldsWithValues = $matchFields->map(function ($item) {
			return $item['label'] . ' = ' . $item['value'];
		})->implode(', ');

		$result = ($matchFields->count() > 0) ? [
			'status' => $errorStatusCode,
			'error_type' => 'validasi_master_baru',
			'message' => 'Membutuhkan verifikasi admin',
			'submessage' => 'Kolom ' . $fields ,
		] : [
			'status' => $successStatusCode,
			'message' => 'Tidak ada data yang perlu diverifikasi admin.'
		];

		return [
			'result' => $result,
			'status_code' => ($matchFields->count() > 0) ? $errorStatusCode : $successStatusCode
		] ;
	}
}
