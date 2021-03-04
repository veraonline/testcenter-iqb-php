<?php

use Slim\App;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;

global $app;

$app->group('/monitor', function(App $app) {

    $adminDAO = new AdminDAO();

    $app->get('/group/{group_name}', function(Request $request, Response $response) use ($adminDAO) {

        /* @var $authToken AuthToken */
        $authToken = $request->getAttribute('AuthToken');
        $groupName = $request->getAttribute('group_name');

        $testtakersFolder = new TesttakersFolder($authToken->getWorkspaceId());

        $group = $testtakersFolder->findGroup($groupName);

        if (!$group) {

            throw new HttpNotFoundException($request, "Group `$groupName` not found.");
        }

        // currently a group-monitor can always only monitor it's own group
        if ($groupName !== $authToken->getGroup()) {

            throw new HttpForbiddenException($request,"Group `$groupName` not allowed.");
        }

        return $response->withJson($group);
    });


    $app->get('/test-sessions', function(Request $request, Response $response) use ($adminDAO) {

        /* @var $authToken AuthToken */
        $authToken = $request->getAttribute('AuthToken');

        // currently a group-monitor can always only monitor it's own group
        $groupNames = [$authToken->getGroup()];

        $sessionChangeMessages = $adminDAO->getTestSessions($authToken->getWorkspaceId(), $groupNames);

        $bsUrl = BroadcastService::registerChannel('monitor', ["groups" => [$authToken->getGroup()]]);

         if ($bsUrl !== null) {

            foreach ($sessionChangeMessages as $sessionChangeMessage) {

                BroadcastService::sessionChange($sessionChangeMessage);
            }

            $response = $response->withHeader('SubscribeURI', $bsUrl);
        }

        return $response->withJson($sessionChangeMessages->asArray());
    });


    $app->put('/command', function(Request $request, Response $response) use ($adminDAO) {

        /* @var $authToken AuthToken */
        $authToken = $request->getAttribute('AuthToken');
        $personId = $authToken->getId();

        $body = RequestBodyParser::getElements($request, [
            'keyword' => null,
            'arguments' => [],
            'timestamp' => null,
            'testIds' => []
        ]);

        $command = new Command(-1, $body['keyword'], (int) $body['timestamp'], ...$body['arguments']);

        foreach (array_unique($body['testIds']) as $testId) {

            if (!$adminDAO->getTest($testId)) {

                throw new HttpNotFoundException($request, "Test `{$testId}` not found. `{$command->getKeyword()}` not committed.");
            }
        }

        foreach ($body['testIds'] as $testId) {

            $commandId = $adminDAO->storeCommand($personId, (int) $testId, $command);
            $command->setId($commandId);
        }

        BroadcastService::send('command', json_encode([
            'command' => $command,
            'testIds' => $body['testIds']
        ]));

        return $response->withStatus(201);
    });
})
    ->add(new IsGroupMonitor())
    ->add(new RequireToken('person'));
