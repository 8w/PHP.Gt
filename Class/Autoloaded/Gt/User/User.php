<?php
namespace Gt\User;

use DateTime;

class User
{
    private $id;
    /** @var string */
    private $uuid;
    /** @var DateTime */
    private $timeLastActive;

    public function __construct($id, string $uuid, $timeLastActive)
    {
        $this->id = $id;
        $this->uuid = $uuid;
        if (!$timeLastActive instanceof DateTime) {
            $timeLastActive = new DateTime($timeLastActive);
        }
        $this->timeLastActive = $timeLastActive;
    }

    public function setActive()
    {
        $this->timeLastActive = new DateTime();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return DateTime
     */
    public function getTimeLastActive(): DateTime
    {
        return $this->timeLastActive;
    }
}
