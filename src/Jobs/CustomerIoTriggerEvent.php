<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;

class CustomerIoTriggerEvent extends CustomerIoBaseJob
{
    /**
     * @var string
     */
    private $accountName;

    /**
     * @var string
     */
    private $customerEmail;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var string
     */
    private $eventName;

    /**
     * @var null|string
     */
    private $eventType;

    /**
     * If the customer id is passed, the system will use that to look for existing customers.
     * Otherwise, it will use the passed customer email and create a new customer if none are found with that email.
     * Then it will trigger the event for the customer.
     *
     * CustomerIoTriggerEvent constructor.
     * @param $accountName
     * @param $customerEmail
     * @param $customerId
     * @param $eventName
     * @param $eventType
     */
    public function __construct(
        $accountName,
        $customerEmail,
        $customerId,
        $eventName,
        $eventType = null
    ) {
        $this->accountName = $accountName;
        $this->customerEmail = $customerEmail;
        $this->customerId = $customerId;
        $this->eventName = $eventName;
        $this->eventType = $eventType;
    }

    /**
     * @param  CustomerIoService  $customerIoService
     * @throws \Throwable
     */
    public function handle(
        CustomerIoService $customerIoService
    ) {
        try {
            $customerIoService->createEventForEmailOrId(
                $this->customerEmail,
                $this->customerId,
                $this->accountName,
                $this->eventName,
                $this->eventType
            );
        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     */
    public function failed(Exception $exception)
    {
        error_log(
            'Error on CustomerIoTriggerEvent job trying send an email from customer.io. '.
            '$customerId='.$this->customerId.' - '.
            '$customerEmail='.$this->customerEmail.' - '.
            '$accountName='.$this->accountName.' - '.
            '$eventName='.$this->eventName.' - '.
            '$eventType='.$this->eventType.' - '
        );

        error_log($exception);

        parent::failed($exception);
    }
}