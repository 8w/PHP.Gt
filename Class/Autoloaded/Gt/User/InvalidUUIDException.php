<?php
namespace Gt\User;

use Exception;

class InvalidUUIDException extends Exception
{
    public function __construct(string $UUID)
    {
        parent::__construct("User could not be retrieved by UUID $UUID");
    }
}
