<?php

/** @noinspection PhpUnhandledExceptionInspection */


class Login extends DataCollectionTypeSafe {

    protected $id = 0;
    protected $name = "";
    protected $token = "";
    protected $mode = "";
    protected $groupName = "";
    protected $groupLabel = "";
    protected $booklets = [];
    protected $workspaceId = 0;

    protected $validTo = 0;

    protected $customTexts;


    function __construct(
        int $id,
        string $name,
        string $token,
        string $mode,
        string $groupName,
        string $groupLabel,
        array $booklets,
        int $workspaceId,
        int $validTo,
        $customTexts = null
    ) {

        $this->id = $id;
        $this->name = $name;
        $this->token  = $token;
        $this->mode = $mode;
        $this->groupName = $groupName;
        $this->groupLabel = $groupLabel ?? ucfirst(str_replace('_', " ", $groupName));
        $this->booklets = $booklets;
        $this->workspaceId = $workspaceId;
        $this->validTo = $validTo;
        $this->customTexts = $customTexts ?? new stdClass();
    }
    
    
    public function getId(): int {

        return $this->id;
    }

    
    public function getName(): string {
        
        return $this->name;
    }


    public function getToken(): string {
        
        return $this->token;
    }


    public function getMode(): string {
        
        return $this->mode;
    }


    public function getGroupName(): string {
        
        return $this->groupName;
    }


    public function getGroupLabel(): string {

        return $this->groupLabel;
    }


    public function getBooklets(): array {
        
        return $this->booklets;
    }
    
    
    public function getWorkspaceId(): int {

        return $this->workspaceId;
    }


    public function getValidTo(): int {

        return $this->validTo;
    }


    public function getCustomTexts(): ?stdClass {

        return $this->customTexts;
    }


    public function isCodeRequired(): bool {

        return (count($this->booklets) and (array_keys($this->booklets) != ['']));
    }
}
