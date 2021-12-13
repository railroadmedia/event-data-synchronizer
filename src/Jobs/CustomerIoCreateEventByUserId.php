<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Repositories\UserRepository;

class CustomerIoCreateEventByUserId extends CustomerIoBaseJob
{
    /**
     * @var string
     */
    private $receiverEmail;

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

//    /**
//     * @var string|null
//     */
//    private $eventType;

    /**
     * @var int|null
     */
    private $eventTimestamp;

    public $tries = 3;

    /**
     * CustomerIoCreateEventByUserId constructor.
     * @param  string  $receiverEmail
     * @param  string  $accountName  // usually the brand name
     * @param  string  $eventName
     * @param  array  $eventData  // key value pairs
     * @param  string|null  $eventType
     * @param  integer|null  $eventTimestamp
     */
    public function __construct(
        $receiverEmail,
        $accountName,
        $eventName,
        $eventData = [],
//        $eventType = null,
        $eventTimestamp = null
    ) {
        $this->receiverEmail = $receiverEmail;
        $this->accountName = $accountName;
        $this->eventName = $eventName;
        $this->eventData = $eventData;
//        $this->eventType = $eventType;
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
            $this->reconnectToMySQLDatabases();

//            $user = $userRepository->find($this->userId);

//            dd($user);
//            dd($this);

//            die("mircea-debug-customer-io-102");
            $accountNameToSyncAllBrand = config('event-data-synchronizer.customer_io_account_to_sync_all_brands');

            // events always sync to the brand specific workspace and the primary all synced workspace

//            var_dump($this);
//            die("customer-io-create-job");

            // useful info Roxana:
            // vechea metoda crea pe sender, noi trebuie sa creem pe receiver
            // nu avem user id pt receiver!!!!
            // doar user email;
            $customerIoService->createOrUpdateCustomerByEmailAndTriggerEvent(
                $this->receiverEmail,
                $this->accountName,  // drumeo
                $this->eventName,    // drumeo_saasquatch_referral-link_30-day
                $this->eventData,   // array(0) { }
//                $this->eventType,   // NULL
                $this->eventTimestamp   // 1639399051
            );


//                        $customerIoService->createEventForUserId(
//            $user->getId(),
//                $this->accountName,  // drumeo
//                $this->eventName,    // drumeo_saasquatch_referral-link_30-day
//                $this->eventData,   // array(0) { }
//                $this->eventType,   // NULL
//                $this->eventTimestamp   // 1639399051
//            );


        } catch (Exception $exception) {
            $this->failed($exception);
        }
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @param $user
     */
    public function failed(Exception $exception, $email = null)
    {
        error_log(
            'Error on CustomerIoCreateEventByUserId job trying to sync user to customer.io. Customer.io email: ' . $email
        );

        error_log($exception);


//        if ($this->job->attempts() >= $this->tries) {
//            $this->fail($exception);
//        } else {
//            $this->release(60);
//        }
    }
}