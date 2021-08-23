<?php

namespace Railroad\EventDataSynchronizer\Events;

class LiveStreamEventAttended
{
    /**
     * @var integer
     */
    private $userId;

    /**
     * @var integer
     */
    private $contentId;

    /**
     * @var string
     */
    private $dateTimeString;

    /**
     * @param  integer  $userId
     * @param  integer  $contentId
     * @param  string  $dateTimeString
     */
    public function __construct($userId, $contentId, $dateTimeString)
    {
        $this->userId = $userId;
        $this->contentId = $contentId;
        $this->dateTimeString = $dateTimeString;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return int
     */
    public function getContentId()
    {
        return $this->contentId;
    }

    /**
     * @return string
     */
    public function getDateTimeString()
    {
        return $this->dateTimeString;
    }
}