<?php

namespace App\Utils;

use Illuminate\Http\JsonResponse;

trait ResponseTrait
{
    public function responseData($data)
    {
        return new JsonResponse([
            'result' => true,
            'data' => $data,
        ], 200);
    }

    public function responseDataCount($data, $count = null)
    {
        if ($count == null) {
            return new JsonResponse([
                'result' => true,
                'count' => count($data),
                'data' => $data,
            ], 200);
        }

        return new JsonResponse([
            'result' => true,
            'count' => $count,
            'data' => $data,
        ], 200);
    }

    public function responseDataLimitOffset($data, $total, $limit, $offset)
    {
        return new JsonResponse([
            'result' => 'true',
            'count' => $total,
            'count_data' => count($data),
            'limit' => (int) $limit,
            'offset' => (int) $offset,
            'data' => $data,
        ], 200);
    }

    public function responseInfo($info, $data = [])
    {
        if ($data == []) {
            return new JsonResponse([
                'result' => true,
                'info' => $info,
            ], 200);
        } else {
            return new JsonResponse([
                'result' => true,
                'data' => $data,
                'info' => $info,
            ], 200);
        }
    }

    public function responseValidation($info, $detail = "")
    {
        $message = 'Isian yang diberikan tidak valid';
        if ($detail == "") {
            return new JsonResponse([
                'message' => $message,
                'info' => $info,
            ], 400);
        } else {
            return new JsonResponse([
                'message' => $message,
                'info' => $info,
                'detail' => $detail,
            ], 400);
        }
    }

    public function responseBadRequest($info, $message = "")
    {
        if ($message == "") {
            return new JsonResponse([
                'info' => $info,
            ], 400);
        } else {
            return new JsonResponse([
                'message' => $message,
                'info' => $info,
            ], 400);
        }
    }

    public function responseBadRequestNotFound($msg, $info = "")
    {
        $message = 'Data ' . $msg . ' tidak ditemukan';
        if ($info == "") {
            return new JsonResponse([
                'message' => $message,
                'info' => $message,
            ], 400);
        } else {
            return new JsonResponse([
                'message' => $message,
                'info' => $info,
            ], 400);
        }
    }

    public function responseInternalServerError($info, $detail = "")
    {
        if ($detail == "") {
            return new JsonResponse([
                'result' => 'false',
                'info' => $info,
            ], 500);
        } else {
            return new JsonResponse([
                'result' => 'false',
                'info' => $info,
                'detail' => $detail,
            ], 500);
        }
    }

    public function responseDataNotFound($customMessage = "", $detail = "", $lang = "")
    {
        $statusCode = 400;
        if ($customMessage == "") {
            switch ($lang) {
                case "en":
                    $info = "Data not found";
                    break;
                default:
                    $info = "Data tidak ditemukan";
            }
        } else {
            $info = $customMessage;
        }
        if ($detail == "") {
            return new JsonResponse([
                'info' => $info,
            ], $statusCode);
        } else {
            return new JsonResponse([
                'info' => $info,
                'detail' => $detail,
            ], $statusCode);
        }
    }

    public function limitOffset($count)
    {
        $limit = $count;
        $offset = 0;
        if ($count < $limit) {
            $limit = $count;
        }
        if (isset($_GET['limit'])) {
            $limit = (int) $_GET['limit'];
        }
        if (isset($_GET['offset'])) {
            $offset = (int) $_GET['offset'];
        }

        return array($limit, $offset);
    }

    public function responseDataCrossApi($data, $status)
    {
        return new JsonResponse($data, $status);
    }
}
