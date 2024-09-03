<?php

namespace App\Http\Controllers;

use Aws\S3\S3Client;

trait FileController {
    public function uploadFile($tipe, $nik, $name, $file){
        $fileName = "$tipe/$nik/$name." . $file->getClientOriginalExtension();
        $s3 = new S3Client([
            'version' => getenv('AWS_VERSION'),
            'region'  => getenv('AWS_REGION'),
            'endpoint' => getenv('AWS_ENDPOINT'),
            'use_path_style_endpoint' => true
        ]);
        $insert = $s3->putObject([
            'Bucket' => 'portofolio',
            'ContentType' => $file->getMimeType(),
            'Key'    => $fileName,
            'Body'   => fopen($file, 'r+')
        ]);

        return array(
            'result' => 'true',
            'path'   => "$fileName"
        );
    }

    public function getFile($fileName) {
        $s3 = new S3Client([
            'version' => getenv('AWS_VERSION'),
            'region' => getenv('AWS_REGION'),
            'endpoint' => getenv('AWS_ENDPOINT'),
            'use_path_style_endpoint' => true
        ]);

        $command = $s3->getCommand('GetObject', [
            'Bucket' => 'portofolio',
            'Key'    => $fileName
        ]);

        // Create a pre-signed URL for a request with duration of 10 miniutes
        $presignedRequest = $s3->createPresignedRequest($command, '+30 minutes');

        // Get the actual presigned-url
        $presignedUrl =  (string)$presignedRequest->getUri();
        $plainUrl = $s3->getObjectUrl('portofolio', $fileName);
        return array(
            'result'	=> 'true',
            'plainUrl' => $plainUrl,
            'presignedUrl' => $presignedUrl
        );
    }

    public function copyFile($sourceFilePath, $newFolder, $newNIK, $newFileName) {
        $source = explode('/', $sourceFilePath);
        $bucket = "portofolio";
        $sourceFolder = $source[0];
        $sourceFile = $source[2];
        $sourceFileExtension = explode('.', $sourceFile)[1];
        $newFilePath = $newFolder ?: $sourceFolder . "/$newNIK/$newFileName.$sourceFileExtension";

        $s3 = new S3Client([
            'version'   => getenv('AWS_VERSION'),
            'region'    => getenv('AWS_REGION'),
            'endpoint'  => getenv('AWS_ENDPOINT'),
            'use_path_style_endpoint' => true
        ]);

        $object = $s3->getObject([
            'Bucket' => $bucket,
            'Key'    => $sourceFilePath,
        ]);

        $copy = $s3->copyObject([
            'Bucket'        => $bucket,
            'Key'           => $newFilePath,
            'ContentType'   => $object['ContentType'],
            'CopySource'    => $s3::encodeKey($bucket . '/' . $sourceFilePath)
        ]);

        return [
            'result' => 'true',
            //'another_result' => $copy,
            'path'   => "$newFilePath"
        ];
    }

    public function deleteFile($pathFile)
    {
        $bucket = 'portofolio';
        $keyname = $pathFile;
        $s3 = new S3Client([
            'version' => getenv('AWS_VERSION'),
            'region' => getenv('AWS_REGION'),
            'endpoint' => getenv('AWS_ENDPOINT'),
            'use_path_style_endpoint' => true,
        ]);
        $hapus = $s3->deleteObject([
            'Bucket' => $bucket,
            'Key' => $keyname,
        ]);
        return array(
            'result' => true,
            'file' => $keyname,
        );

    }
}
