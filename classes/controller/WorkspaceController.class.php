<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);
// TODO unit tests !

use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;


class WorkspaceController extends Controller {

    public static function  get(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');

        /* @var $authToken AuthToken */
        $authToken = $request->getAttribute('AuthToken');

        return $response->withJson([
            "id" => $workspaceId,
            "name" => self::adminDAO()->getWorkspaceName($workspaceId),
            "role" => self::adminDAO()->getWorkspaceRole($authToken->getToken(), $workspaceId)
        ]);
    }


    public static function put (Request $request, Response $response): Response {

        $requestBody = JSON::decode($request->getBody()->getContents());
        if (!isset($requestBody->name)) {
            throw new HttpBadRequestException($request, "New workspace name missing");
        }

        self::superAdminDAO()->createWorkspace($requestBody->name);
        
        return $response->withStatus(201);
    }


    public static function patch(Request $request, Response $response): Response {

        $requestBody = JSON::decode($request->getBody()->getContents());
        $workspaceId = (int) $request->getAttribute('ws_id');

        if (!isset($requestBody->name) or (!$requestBody->name)) {
            throw new HttpBadRequestException($request, "New name (name) is missing");
        }

        self::superAdminDAO()->setWorkspaceName($workspaceId, $requestBody->name);
        
        return $response;
    }
    
    
    public static function patchUsers(Request $request, Response $response): Response {

        $requestBody = JSON::decode($request->getBody()->getContents());
        $workspaceId = (int) $request->getAttribute('ws_id');

        if (!isset($requestBody->u) or (!count($requestBody->u))) {
            throw new HttpBadRequestException($request, "User-list (u) is missing");
        }

        self::superAdminDAO()->setUserRightsForWorkspace($workspaceId, $requestBody->u);
        
        return $response->withHeader('Content-type', 'text/plain;charset=UTF-8');
    }


    public static function getUsers(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');

        return $response->withJson(self::superAdminDAO()->getUsersByWorkspace($workspaceId));
    }
    

    public static function getReviews(Request $request, Response $response): Response {

        $groups = explode(",", $request->getParam('groups'));
        $workspaceId = (int) $request->getAttribute('ws_id');

        if (!$groups) {
            throw new HttpBadRequestException($request, "Parameter groups is missing");
        }

        $reviews = self::adminDAO()->getReviews($workspaceId, $groups);

        return $response->withJson($reviews);
    }


    public static function getResults(Request $request, Response $response): Response {

        $workspaceId = (int)$request->getAttribute('ws_id');
        $results = self::adminDAO()->getAssembledResults($workspaceId);
        return $response->withJson($results);
    }


    public static function getResponses(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $groups = explode(",", $request->getParam('groups'));

        $results = self::adminDAO()->getResponses($workspaceId, $groups);

        return $response->withJson($results);
    }


    public static function deleteResponses(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $groups = RequestBodyParser::getRequiredElement($request, 'groups');

        foreach ($groups as $group) {
            self::adminDAO()->deleteResultData($workspaceId, $group);
        }

        BroadcastService::send('system/clean');

        return $response;
    }


    public static function getLogs(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $groups = explode(",", $request->getParam('groups'));

        $results = self::adminDAO()->getLogs($workspaceId, $groups);

        return $response->withJson($results);
    }


    public static function validation(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');

        $workspaceValidator = new WorkspaceValidator($workspaceId);
        $report = $workspaceValidator->validate();

        // TODO remove temporal report reformation below and change FE
        $oldFormatReport = ['warnings'=>[],'errors'=>[],'infos'=>[]];
        foreach ($report as $file => $reportEntries) {
            foreach ($reportEntries as $reportEntry) {
                /* @var ValidationReportEntry $reportEntry */
                $oldFormatReport[$reportEntry->level . 's'][] = "`[$file]` {$reportEntry->message}";
            }
        }
        $report = $oldFormatReport;

        return $response->withJson($report);
    }

    public static function  getFile(Request $request, Response $response): Response {

        $workspaceId = $request->getAttribute('ws_id', 0);
        $fileType = $request->getAttribute('type', '[type missing]');
        $filename = $request->getAttribute('filename', '[filename missing]');

        $fullFilename = DATA_DIR . "/ws_$workspaceId/$fileType/$filename";
        if (!file_exists($fullFilename)) {
            throw new HttpNotFoundException($request, "File not found:" . $fullFilename);
        }

        $response->withHeader('Content-Description', 'File Transfer');
        $response->withHeader('Content-Type', ($fileType == 'Resource') ? 'application/octet-stream' : 'text/xml' );
        $response->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->withHeader('Expires', '0');
        $response->withHeader('Cache-Control', 'must-revalidate');
        $response->withHeader('Pragma', 'public');
        $response->withHeader('Content-Length', filesize($fullFilename));

        $fileHandle = fopen($fullFilename, 'rb');

        $fileStream = new Stream($fileHandle);

        return $response->withBody($fileStream);
    }


    public static function postFile(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $importedFiles = UploadedFilesHandler::handleUploadedFiles($request, 'fileforvo', $workspaceId);
        return $response->withJson($importedFiles)->withStatus(201);
    }


    public static function getFiles(Request $request, Response $response): Response {

        $workspaceId = (int)$request->getAttribute('ws_id');
        $workspace = new Workspace($workspaceId);
        $files = $workspace->getAllFiles();
        return $response->withJson($files);
    }


    public static function deleteFiles(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $filesToDelete = RequestBodyParser::getRequiredElement($request, 'f');

        $workspaceController = new Workspace($workspaceId);
        $deletionReport = $workspaceController->deleteFiles($filesToDelete);

        return $response->withJson($deletionReport)->withStatus(207);
    }


    public static function getSysCheckReports(Request $request, Response $response): Response {

        $checkIds = explode(',', $request->getParam('checkIds', ''));
        $delimiter = $request->getParam('delimiter', ';');
        $lineEnding = $request->getParam('lineEnding', '\n');
        $enclosure = $request->getParam('enclosure', '"');

        $workspaceId = (int) $request->getAttribute('ws_id');

        $sysChecks = new SysChecksFolder($workspaceId);
        $reports = $sysChecks->collectSysCheckReports($checkIds);

        # TODO remove $acceptWorkaround if https://github.com/apiaryio/api-elements.js/issues/413 is resolved
        $acceptWorkaround = $request->getParam('format', 'json') == 'csv';

        if (($request->getHeaderLine('Accept') == 'text/csv') or $acceptWorkaround) {

            $flatReports = array_map(function (SysCheckReportFile $report) {return $report->getFlat();}, $reports);
            $response->getBody()->write(CSV::build($flatReports, [], $delimiter, $enclosure, $lineEnding));
            return $response->withHeader('Content-type', 'text/csv');
        }

        $reportsArrays = array_map(function (SysCheckReportFile $report) {return $report->get();}, $reports);

        return $response->withJson($reportsArrays);
    }


    public static function getSysCheckReportsOverview(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');

        $sysChecksFolder = new SysChecksFolder($workspaceId);
        $reports = $sysChecksFolder->getSysCheckReportList();

        return $response->withJson($reports);
    }


    public static function deleteSysCheckReports(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $checkIds = RequestBodyParser::getElementWithDefault($request,'checkIds', []);

        $sysChecksFolder = new SysChecksFolder($workspaceId);
        $fileDeletionReport = $sysChecksFolder->deleteSysCheckReports($checkIds);

        return $response->withJson($fileDeletionReport)->withStatus(207);
    }


    public static function getSysCheck(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $sysCheckName = $request->getAttribute('sys-check_name');
    
        $workspaceController = new Workspace($workspaceId);
        /* @var XMLFileSysCheck $xmlFile */
        $xmlFile = $workspaceController->getXMLFileByName('SysCheck', $sysCheckName);
    
        return $response->withJson(new SysCheck([
            'name' => $xmlFile->getId(),
            'label' => $xmlFile->getLabel(),
            'canSave' => $xmlFile->hasSaveKey(),
            'hasUnit' => $xmlFile->hasUnit(),
            'questions' => $xmlFile->getQuestions(),
            'customTexts' => (object) $xmlFile->getCustomTexts(),
            'skipNetwork' => $xmlFile->getSkipNetwork(),
            'downloadSpeed' => $xmlFile->getSpeedtestDownloadParams(),
            'uploadSpeed' => $xmlFile->getSpeedtestUploadParams(),
            'workspaceId' => $workspaceId
        ]));
    }

    public static function  getSysCheckUnitAndPLayer(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $sysCheckName = $request->getAttribute('sys-check_name');
    
        $workspaceController = new Workspace($workspaceId);
        /* @var XMLFileSysCheck $xmlFile */
        $xmlFile = $workspaceController->getXMLFileByName('SysCheck', $sysCheckName);
    
        return $response->withJson($xmlFile->getUnitData());
    }
    
    public static function  putSysCheckReport(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $sysCheckName = $request->getAttribute('sys-check_name');
        $report = new SysCheckReport(JSON::decode($request->getBody()->getContents()));
    
        $sysChecksFolder = new SysChecksFolder($workspaceId);
    
        /* @var XMLFileSysCheck $xmlFile */
        $xmlFile = $sysChecksFolder->getXMLFileByName('SysCheck', $sysCheckName);
    
        if (strlen($report->keyPhrase) <= 0) {
    
            throw new HttpBadRequestException($request,"No key `$report->keyPhrase`");
        }
    
        if (strtoupper($report->keyPhrase) !== strtoupper($xmlFile->getSaveKey())) {
    
            throw new HttpError("Wrong key `$report->keyPhrase`", 400);
        }
    
        $report->checkId = $sysCheckName;
        $report->checkLabel = $xmlFile->getLabel();
    
        $sysChecksFolder->saveSysCheckReport($report);
    
        return $response->withStatus(201);
    }

    
    public static function  patchUnlock(Request $request, Response $response): Response { 

        $groups = RequestBodyParser::getRequiredElement($request, 'groups');
        $workspaceId = (int) $request->getAttribute('ws_id');

        foreach($groups as $groupName) {
            self::adminDAO()->changeBookletLockStatus($workspaceId, $groupName, false);
        }

        return $response;
    }
    
    
    public static function  patchLock(Request $request, Response $response): Response { // TODO name more RESTful

        $groups = RequestBodyParser::getRequiredElement($request, 'groups');
        $workspaceId = (int) $request->getAttribute('ws_id');
    
        foreach($groups as $groupName) {
            self::adminDAO()->changeBookletLockStatus($workspaceId, $groupName, true);
        }
    
        return $response;
    }


    public static function  getStatus(Request $request, Response $response): Response {
    
        $workspaceId = (int) $request->getAttribute('ws_id');
        $bookletsFolder = new BookletsFolder($workspaceId);
    
        return $response->withJson($bookletsFolder->getTestStatusOverview(self::adminDAO()->getBookletsStarted($workspaceId)));
    }
    
    
    public static function  getBookletsStarted(Request $request, Response $response): Response {

        $workspaceId = (int) $request->getAttribute('ws_id');
        $groups = explode(",", $request->getParam('groups', ''));
    
        $bookletsStarted = [];
        foreach(self::adminDAO()->getBookletsStarted($workspaceId) as $booklet) {
            if (in_array($booklet['groupname'], $groups)) {
                if ($booklet['locked'] == '1') {
                    $booklet['locked'] = true;
                } else {
                    $booklet['locked'] = false;
                }
                array_push($bookletsStarted, $booklet);
            }
        }
    
        return $response->withJson($bookletsStarted);
    }
}
