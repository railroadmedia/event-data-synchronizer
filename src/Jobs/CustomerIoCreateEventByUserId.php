<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\Usora\Repositories\UserRepository;

class CustomerIoCreateEventByUserId extends CustomerIoBaseJob
{
    /**
     * @var integer
     */
    private $userId;

    /**
     * @var string
     */
    private $accountName;

    /**
     * @var string
     */
    private $eventName;

    /**
     * @var array
     */
    private $eventData;

    /**
     * @var string|null
     */
    private $eventType;

    /**
     * @var int|null
     */
    private $eventTimestamp;

    /**
     * CustomerIoCreateEventByUserId constructor.
     * @param  integer  $userId
     * @param  string  $accountName  // usually the brand name
     * @param  string  $eventName
     * @param  array  $eventData  // key value pairs
     * @param  string|null  $eventType
     * @param  integer|null  $eventTimestamp
     */
    public function __construct(
        $userId,
        $accountName,
        $eventName,
        $eventData = [],
        $eventType = null,
        $eventTimestamp = null
    ) {
        $this->userId = $userId;
        $this->accountName = $accountName;
        $this->eventName = $eventName;
        $this->eventData = $eventData;
        $this->eventType = $eventType;
        $this->eventTimestamp = $eventTimestamp;
    }

    /**
     * @param  CustomerIoService  $customerIoService
     * @throws \Throwable
     */
    public function handle(
        CustomerIoService $customerIoService,
        UserRepository $userRepository
    ) {
        try {
            $user = $userRepository->find($this->userId);

            $accountNameToSyncAllBrand = config('event-data-synchronizer.customer_io_account_to_sync_all_brands');

            // events always sync to the brand specific workspace and the primary all synced workspace
            $customerIoService->createEventForUserId(
                $user->getId(),
                $this->accountName,
                $this->eventName,
                $this->eventData,
                $this->eventType,
                $this->eventTimestamp
            );

            $customerIoService->createEventForUserId(
                $user->getId(),
                $accountNameToSyncAllBrand,
                $this->eventName,
                $this->eventData,
                $this->eventType,
                $this->eventTimestamp
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
            'Error on CustomerIoCreateEventByUserId job trying to sync user to customer.io. User ID: '.
            $this->userId
        );

        error_log($exception);

        parent::failed($exception);
    }
}