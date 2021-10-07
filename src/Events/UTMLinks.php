<?php

namespace Railroad\EventDataSynchronizer\Events;

class UTMLinks
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
     * @var mixed|null
     */
    private $utmId;

    /**
     * @var mixed|null
     */
    private $utmSource;

    /**
     * @var mixed|null
     */
    private $utmCampaign;

    /**
     * @var mixed|null
     */
    private $utmMedium;

    /**
     * @param $userId
     * @param $brand
     * @param $dateTimeString
     * @param null $utmId
     * @param null $utmSource
     * @param null $utmCampaign
     * @param null $utmMedium
     */
    public function __construct(
        $userId,
        $brand,
        $dateTimeString,
        $utmId = null,
        $utmSource = null,
        $utmCampaign = null,
        $utmMedium = null
    ) {
        $this->userId = $userId;
        $this->brand = $brand;
        $this->dateTimeString = $dateTimeString;

        $this->utmId = $utmId;
        $this->utmSource = $utmSource;
        $this->utmCampaign = $utmCampaign;
        $this->utmMedium = $utmMedium;
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

    /**
     * @return mixed|null
     */
    public function getUtmId()
    {
        return $this->utmId;
    }

    /**
     * @return mixed|null
     */
    public function getUtmSource()
    {
        return $this->utmSource;
    }

    /**
     * @return mixed|null
     */
    public function getUtmCampaign()
    {
        return $this->utmCampaign;
    }

    /**
     * @return mixed|null
     */
    public function getUtmMedium()
    {
        return $this->utmMedium;
    }
}