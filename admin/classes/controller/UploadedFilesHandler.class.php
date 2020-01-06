<?php

/**
 * since forwarding from deprecated endpoint does not work with uploaded files, we let both endpoints use this class
 * this code could stay in workspace.php directly if deprecated endpoint is removed
 */

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Request;
use Slim\Http\UploadedFile;

class UploadedFilesHandler {

    const errorMessages = array(
        'UPLOAD_ERR_INI_SIZE' => 'The uploaded file exceeds the maximum.', // php.ini max_file_size
        'UPLOAD_ERR_FORM_SIZE' => 'The uploaded file exceeds the form maximum.', // html form max file size
        'UPLOAD_ERR_PARTIAL' => 'The uploaded file was only partially uploaded.',
        'UPLOAD_ERR_NO_FILE' => 'No file was uploaded.',
        'UPLOAD_ERR_NO_TMP_DIR' => 'Missing a temporary folder.',
        'UPLOAD_ERR_CANT_WRITE' => 'Failed to write file to disk.'
    );

    /**
     * @param Request $request
     * @param $fieldName
     * @param $workspaceId
     * @return array
     * @throws HttpBadRequestException
     * @throws HttpInternalServerErrorException
     */
    static function handleUploadedFiles(Request $request, $fieldName, $workspaceId) {

        $allUploadedFiles = $request->getUploadedFiles();

        if (!count($allUploadedFiles)) {

            if(intval($_SERVER['CONTENT_LENGTH']) > 0) {
                throw new HttpBadRequestException($request,'Request size exceeds the maximum for post data!');  // max_post_size
            }

            throw new HttpBadRequestException($request,"No Upload File.");
        }

        if (!isset($allUploadedFiles[$fieldName])) {
            throw new HttpBadRequestException($request,"No Upload File in $fieldName");
        }

        $uploadedFiles = $allUploadedFiles[$fieldName];

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = array($uploadedFiles);
        }

        $importedFiles = array();

        $workspaceController = new WorkspaceController($workspaceId);

        foreach ($uploadedFiles as $uploadedFile) { /** @var UploadedFile $uploadedFile */

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                throw new HttpInternalServerErrorException($request, UploadedFilesHandler::errorMessages[$uploadedFile->getError()] ?? 'unknown error');
            }

            $originalFileName = $uploadedFile->getClientFilename();
            $uploadedFile->moveTo($workspaceController->getWorkspacePath() . '/' . $originalFileName);
            $importedFiles = array_merge($importedFiles, $workspaceController->importUnfiledResource($originalFileName));
        }

        return $importedFiles;
    }


}