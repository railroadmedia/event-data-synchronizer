<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\Usora\Repositories\UserRepository;

class CustomerIoSyncUserDevice extends CustomerIoBaseJob
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
     * @var array
     */
    private $deviceData;

    /**
     * @var int|null
     */
    private $timestamp;

    /**
     * @param $userId
     * @param $accountName
     * @param $deviceData
     * @param null $timestamp
     */
    public function __construct( $userId,
        $accountName,
        $deviceData,
        $timestamp = null)
    {
        $this->userId = $userId;
        $this->accountName = $accountName;
        $this->deviceData = $deviceData;
        $this->timestamp = $timestamp;
    }

    /**
     * @param CustomerIoService $customerIoService
     * @param UserRepository $userRepository
     * @throws \Throwable
     */
    public function handle(
        CustomerIoService $customerIoService,
        UserRepository $userRepository
    ) {
        try {
            $user = $userRepository->find($this->userId);

            $customerIoService->syncDeviceForUserId(
                $user->getId(),
                $this->accountName,
                $this->deviceData,
                $this->timestamp
            );

        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * The job failed to process.
     *
     * @param Exception $exception
     */
    public function failed(Exception $exception)
    {
        error_log(
            'Error on CustomerIoSyncUserDevice job trying to sync user device to customer.io. User ID: ' .
            $this->userId
        );

        error_log($exception);

        parent::failed($exception);
    }
}