<?php

namespace Railroad\EventDataSynchronizer\Events;

class FirstActivityPerDay
{
    /**
     * @var integer
     */
    private $userId;

    /**
     * @var string
     */
    private $brand;

    /**
     * @var string
     */
    private $dateTimeString;

    /**
     * @param $userId
     * @param $brand
     * @param $dateTimeString
     */
    public function __construct($userId, $brand, $dateTimeString)
    {
        $this->userId = $userId;
        $this->brand = $brand;
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
     * @return string
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * @return string
     */
    public function getDateTimeString()
    {
        return $this->dateTimeString;
    }
}