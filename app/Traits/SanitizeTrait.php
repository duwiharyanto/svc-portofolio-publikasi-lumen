<?php

namespace App\Traits;

trait SanitizeTrait
{
    public static function entityEncode($data)
    {
        $typeData = gettype($data);
        if($typeData == 'array') {
            
            array_walk_recursive($data, function (&$value) {
                $type = gettype($value);
                switch($type) {
                    case 'boolean': $value = (boolean) htmlentities($value); break;
                    case 'integer': $value = (int) htmlentities($value); break;
                    case 'string': $value = (string) htmlentities($value); break;
                    case 'double': $value = (double) htmlentities($value); break;
                    default: $value = htmlentities($value); break;
                }
            });

            $data = (array) $data;
        } else {
            $data = htmlentities($data);
        }
        
        return $data;
    }

    public static function entityDecode($data)
    {
        $typeData = gettype($data);
        if($typeData == 'array') {
            array_walk_recursive($data, function (&$value) {
                $type = gettype($value);
                switch($type) {
                    case 'boolean': $value = (boolean) html_entity_decode($value);  break;
                    case 'integer': $value = (int) html_entity_decode($value);  break;
                    case 'string': $value = (string) html_entity_decode($value);  break;
                    case 'double': $value = (double) html_entity_decode($value);  break;
                    default: $value = html_entity_decode($value);  break;
                }
            });

            $data = (array) $data;
        } elseif($typeData == 'object') {
            $data = (array) $data->data;
            array_walk_recursive($data, function (&$value) {
                $data = (array) $value;
                array_walk_recursive($data, function (&$value) {
                    $type = gettype($value);
                    switch($type) {
                        case 'boolean': $value = (boolean) html_entity_decode($value);  break;
                        case 'integer': $value = (int) html_entity_decode($value);  break;
                        case 'string': $value = (string) html_entity_decode($value);  break;
                        case 'double': $value = (double) html_entity_decode($value);  break;
                        default: $value = html_entity_decode($value);  break;
                        
                    }
                });
            });
           
            $data = (array) $data;
        } else{
            $data = html_entity_decode($data);
        }

        return $data;
    }
}
