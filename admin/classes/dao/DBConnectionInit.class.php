<?php

// TODO unit test

class DBConnectionInit extends DBConnectionSuperAdmin {


    /**
     *
     * adds a new super user to db, if user table is empty (!)
     *
     * @param $username - name for the super user to create
     * @param $userpassword  - password for the super user to create
     * @return bool - true if user was created, false if not (but no error occurred)
     * @throws Exception - if error occurs during connection
     */
    public function addSuperuser(string $username, string $userpassword): bool {

        $sql = $this->pdoDBhandle->prepare('SELECT users.name FROM users');

        if (!$sql->execute()) {
            throw new Exception('Could not select from table `users` - database not initialized correctly?');
        }

        $data = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (count($data)) {
            return false;
        }

        $sql = $this->pdoDBhandle->prepare('INSERT INTO users (name, password, is_superadmin) VALUES (:user_name, :user_password, true)');
        $params = array(
            ':user_name' => $username,
            ':user_password' => $this->encryptPassword($userpassword)
        );

        if (!$sql->execute($params)) {
            throw new Exception('Could not insert into table `users`');
        }

        return true;
    }


    /**
     * creates a new workspace with $name, if it does not exist
     *
     * @param $name - name for the new workspace
     * @return int - workspace id
     * @throws Exception - if error occurs
     */
    public function getWorkspace(string $name): int {

        if (!$this->pdoDBhandle) {
            throw new Exception('no database connection');
        }

        $sql = $this->pdoDBhandle->prepare("SELECT workspaces.id FROM workspaces WHERE `name` = :ws_name");
        $sql->execute(array(':ws_name' => $name));

        $workspaces_names = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (count($workspaces_names)) {
            return $workspaces_names[0]['id'];
        }

        $this->addWorkspace($name);

        return $this->getWorkspace($name);
    }

    /**
     *
     * grants RW rights to a given workspace( by id) to a user
     * @param $userName
     * @param $workspaceId
     */
    public function grantRights(string $userName, int $workspaceId) {

        $user = $this->getUserByName($userName);

        $this->setWorkspaceRightsByUser($user['id'], array((object) array(
            "id" => $workspaceId,
            "role" => "RW"
        )));
    }


}