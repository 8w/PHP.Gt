<?php
namespace Gt\User;

use DateTime;

class AuthenticatedUser extends User
{
    /** @var string */
    private $oAuthUuid;
    /** @var string */
    private $oAuthProviderName;
    /** @var DateTime */
    private $timeIdentified;

    public function __construct(
        int $id,
        string $uuid,
        string $timeLastActive,
        string $oAuthUuid,
        string $oAuthProviderName,
        DateTime $timeIdentified
    ) {
        parent::__construct($id, $uuid, $timeLastActive);

        $this->oAuthUuid = $oAuthUuid;
        $this->oAuthProviderName = $oAuthProviderName;
        $this->timeIdentified = $timeIdentified;
    }

    public function getOAuthUuid(): string
    {
        return $this->oAuthUuid;
    }

    public function getOAuthProviderName(): string
    {
        return $this->oAuthProviderName;
    }

    public function getTimeIdentified(): DateTime
    {
        return $this->timeIdentified;
    }
}
