<?php

namespace App\Traits;

use Illuminate\Support\Str;

use App\Models\PegawaiModel AS Pegawai;
use App\Models\PublikasiModel AS Publikasi;
use App\Models\JenisModel AS JenisPublikasi;
use App\Models\StatusModel AS StatusPublikasi;
use App\Models\PeranModel AS PeranPublikasi;
use App\Models\BentukModel AS BentukPublikasi;
use Hamcrest\Arrays\IsArray;

trait UmumTrait {

    public function convertKeyUUIDToID($variable) {
        $data = NULL;
        if (is_array($variable)) {
            $data = collect($variable)->map(function ($value, $key) {
                return (Str::is('uuid*', $value)) ? str_replace('uuid_', 'id_publikasi_', $value) : $value ;
            });
        } elseif (is_string($variable)) {
            $data = Str::replaceFirst('uuid_', 'id_publikasi_', $variable);
        } else {
            $data = $variable;
        }
        return $data;
    }

    public function convertBadWords($string) {

        $blacklistArray = array('ass', 'ball sack');

        $string = 'Cassandra is a clean word so it should pass the check';

        $matches = array();
        $matchFound = preg_match_all(
            "/\b(" . implode($blacklistArray, ["|"]) . ")\b/i",
            $string,
            $matches
        );

        // if it find matches bad words

        if ($matchFound) {
            $words = array_unique($matches[0]);
            foreach ($words as $word) {

                //show bad words found
                dd($word);
            }
        }
    }

    function fields($idField, $idParentField, array &$elements = [], Int $parentId = NULL, Int $order = 0) {
        $branch = [];
        foreach ($elements as $element) {
            if (($element[$idParentField] == $parentId)) {
                $children = $this->fields($idField, $idParentField, $elements, $element[$idField], $element['order']);
                if ($element['tipe_field'] !== 'select' && $element['tipe_field'] !== 'multiple_select') {
                    $element['children'] = $children ?: [];
                    if ($element['tipe_field'] === 'multiple') {
                        if ($element['name_field'] === 'keanggotaan') {
                            $element['children'][] = [
                                "id" => NULL, "id_publikasi_form_versi" => NULL,
                                "id_publikasi_form_induk" => NULL, "label" => NULL,
                                "id_field" => NULL, "tipe_field" => "hidden",
                                "class_field" => NULL, "name_field" => "nik_keanggotaan",
                                "placeholder" => NULL, "options" => NULL,
                                "default_value" => NULL, "tipe_validasi" => NULL,
                                "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                                "order" => 1, "uuid_form" => NULL,
                                "children" => []
                            ];
                        }
                        $element['children'][] = [
                            "id" => NULL, "id_publikasi_form_versi" => NULL,
                            "id_publikasi_form_induk" => NULL, "label" => NULL,
                            "id_field" => NULL, "tipe_field" => "hidden",
                            "class_field" => NULL, "name_field" => "uuid_" . $element['name_field'],
                            "placeholder" => NULL, "options" => NULL,
                            "default_value" => NULL, "tipe_validasi" => NULL,
                            "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                            "order" => 1, "uuid_form" => NULL,
                            "children" => []
                        ];
                    }
                }
                $branch[] = $element;
                unset($elements[$element[$idField]]);
            }
        }
        return $branch;
    }

    public function checkBySearchKey($haystack, $needle) {
        $result = FALSE;
        if (is_array($haystack)) {
            foreach($haystack as $item) {
                if ($needle == '' || stristr($item, $needle)) {
                    $result = TRUE;
                }
            }
        } else {
            $result = stristr($haystack, $needle);
        }
        return ($result) ? TRUE : FALSE ;
    }

    function setFields(array $flatArrays, array $gridConfig = NULL) {
        $recursive = $this->setRecursiveFields('id', 'id_publikasi_form_induk', $flatArrays, null, $gridConfig);
        $data = $this->cleanFields(["id", "id_publikasi_form_versi", "id_publikasi_form_induk", "order", "tabel", "flag_judul_publikasi", "flag_tgl_publikasi", "flag_peran"], $recursive);
        return $data;
    }

    function setRecursiveFields($idField, $idParentField, array &$elements = [], Int $parentId = NULL, $gridConfig = NULL) {
        $branch = [];
        foreach ($elements as $element) {

            // Set grid config
            $element['field_configs'] = $this->setGridConfig($element, $gridConfig);

            if ($element[$idParentField] == $parentId) {
                $children = $this->setRecursiveFields($idField, $idParentField, $elements, $element[$idField], $gridConfig);

                if (($element['tipe_field'] == 'mask') && ($element['tipe_validasi'])) {
                    $element['tipe_validasi'] = json_decode($element['tipe_validasi']);
                }

                if ($element['dependency_parent'] || $element['dependency_child']) {
                    $dependencyParent = json_decode($element['dependency_parent']) ;
                    $dependencyChild = json_decode($element['dependency_child']) ;
                    $element['dependency_parent'] = (is_array($dependencyParent)) ? $dependencyParent : $element['dependency_parent'] ;
                    $element['dependency_child'] = (is_array($dependencyChild)) ? $dependencyChild : $element['dependency_child'] ;
                }

                if ($element['tipe_field'] !== 'select' && $element['tipe_field'] !== 'radio' && $element['tipe_field'] !== 'autoselect' && $element['tipe_field'] !== 'autocomplete' && $element['tipe_field'] !== 'multiple_select' && $element['tipe_field'] !== 'currency') {
                    $element['children'] = ($children) ? $children : [];
                    if ($element['tipe_field'] === 'multiple') {
                        if ($element['name_field'] === 'keanggotaan') $element['children'][] = [
                            "id" => NULL, "id_publikasi_form_versi" => NULL,
                            "id_publikasi_form_induk" => NULL, "label" => NULL,
                            "id_field" => NULL, "tipe_field" => "hidden",
                            "class_field" => NULL, "name_field" => "nik_keanggotaan",
                            "placeholder" => NULL, "options" => NULL,
                            "default_value" => NULL, "tipe_validasi" => NULL,
                            "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                            "order" => 1, "uuid_form" => NULL,
                            "children" => []
                        ];

                        $element['children'][] = [
                            "label" => NULL, "id_field" => NULL,
                            "tipe_field" => "hidden", "class_field" => NULL,
                            "name_field" => "uuid_" . $element['name_field'],
                            "placeholder" => NULL, "options" => NULL,
                            "default_value" => NULL, "tipe_validasi" => NULL,
                            "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                            "order" => 1, "uuid_form" => NULL,
                            "children" => []
                        ];
                    }
                }

                $element['flag_multiple_form'] = ($element['tipe_field'] === 'wizard' && count($children) > 0 && collect($children)->where('tipe_field', 'multiple')->count() > 0) ? 1 : 0 ;

                // Handling or setting grid configuration

                $branch[] = $element ;
                unset($elements[$element[$idField]]);
            }
        }
        return $branch;
    }

    function cleanFields(Array $acceptableFields, Array &$elements = []) {
        $branch = [];
        foreach ($elements as $element) {
            $element = collect($element)->except($acceptableFields)->all();
            if (isset($element['children']) && (count($element['children']) > 0)) {
                $children = $this->cleanFields($acceptableFields, $element['children']);
                $element['children'] = ($children) ? $children : [] ;
            }
            $branch[] = $element;
        }
        return $branch;
    }

    /**
     * Set grid config (grid system)
     */
    function setGridConfig(array $element, $gridSystem) {

        // Initial result data
        $result = NULL;

        // Get element`s or field`s grid system config
        $elementGridConfig = $gridSystem['config'][$element['id_field']] ?? NULL;

        // Merge current element`s or field`s config with grid system config
        $result = ($element && $elementGridConfig) ? array_merge($element['field_configs'] ?: [], $elementGridConfig ?: []) : $element['field_configs'];

        return $result;
    }

    // Experimental

    function setFieldsAndGrid(Array $flatArrays, Array $gridConfig = []) {
        $recursive = $this->setRecursiveFieldsAndGrid('id', 'id_publikasi_form_induk', $flatArrays, $gridConfig);
        $data = $this->cleanFields(["id", "id_publikasi_form_versi", "id_publikasi_form_induk", "order", "tabel", "flag_judul_publikasi", "flag_tgl_publikasi", "flag_peran"], $recursive);
        return $data;
    }

    function setRecursiveFieldsAndGrid($idField, $idParentField, Array &$elements = [], Array $gridConfig = [], Int $parentId = NULL) {
        $branch = [];
        foreach ($elements as $element) {
            if ($element[$idParentField] == $parentId) {
                $children = $this->setRecursiveFields($idField, $idParentField, $elements, $element[$idField], $gridConfig);
                if ($element['dependency_parent'] || $element['dependency_child']) {
                    $dependencyParent = json_decode($element['dependency_parent']) ;
                    $dependencyChild = json_decode($element['dependency_child']) ;
                    $element['dependency_parent'] = (is_array($dependencyParent)) ? $dependencyParent : $element['dependency_parent'] ;
                    $element['dependency_child'] = (is_array($dependencyChild)) ? $dependencyChild : $element['dependency_child'] ;
                }
                if ($element['tipe_field'] !== 'select' && $element['tipe_field'] !== 'multiple_select') {
                    $element['children'] = ($children) ? $children : [];
                    if ($element['tipe_field'] === 'multiple') {
                        if ($element['name_field'] === 'keanggotaan') $element['children'][] = [
                            "id" => NULL, "id_publikasi_form_versi" => NULL,
                            "id_publikasi_form_induk" => NULL, "label" => NULL,
                            "id_field" => NULL, "tipe_field" => "hidden",
                            "class_field" => NULL, "name_field" => "nik_keanggotaan",
                            "placeholder" => NULL, "options" => NULL,
                            "default_value" => NULL, "tipe_validasi" => NULL,
                            "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                            "order" => 1, "uuid_form" => NULL,
                            "children" => []
                        ];

                        $element['children'][] = [
                            "label" => NULL, "id_field" => NULL,
                            "tipe_field" => "hidden", "class_field" => NULL,
                            "name_field" => "uuid_" . $element['name_field'],
                            "placeholder" => NULL, "options" => NULL,
                            "default_value" => NULL, "tipe_validasi" => NULL,
                            "tabel" => "publikasi_meta", "flag_multiple_form" => 0,
                            "order" => 1, "uuid_form" => NULL,
                            "children" => []
                        ];
                    }
                }
                $element['flag_multiple_form'] = ($element['tipe_field'] === 'wizard' && count($children) > 0 && collect($children)->where('tipe_field', 'multiple')->count() > 0) ? 1 : 0 ;

                // Handling or setting grid configuration

                $branch[] = $element ;
                unset($elements[$element[$idField]]);
            }
        }
        return $branch;
    }

}
