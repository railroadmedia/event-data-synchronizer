<?php

namespace Railroad\EventDataSynchronizer\Jobs;

use Exception;
use Railroad\CustomerIo\Services\CustomerIoService;

class CustomerIoSendTransactionalEmail extends CustomerIoBaseJob
{
    /**
     * @var string
     */
    private $accountName;

    /**
     * @var integer
     */
    private $customerIoTransactionalMessageId;

    /**
     * @var string
     */
    private $customerEmail;

    /**
     * @var array
     */
    private $messageDataArray;

    public function __construct(
        $accountName,
        $customerIoTransactionalMessageId,
        $customerEmail,
        $messageDataArray = []
    ) {
        $this->accountName = $accountName;
        $this->customerIoTransactionalMessageId = $customerIoTransactionalMessageId;
        $this->customerEmail = $customerEmail;
        $this->messageDataArray = $messageDataArray;
    }

    /**
     * @param  CustomerIoService  $customerIoService
     * @throws \Throwable
     */
    public function handle(
        CustomerIoService $customerIoService
    ) {
        try {
            $this->reconnectToMySQLDatabases();

            $customerIoService->sendTransactionalEmail(
                $this->accountName,
                $this->customerIoTransactionalMessageId,
                $this->customerEmail,
                $this->messageDataArray
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
            'Error on CustomerIoSendTransactionalEmail job trying send an email from customer.io. '.
            '$accountName='.$this->accountName.' - '.
            '$customerIoTransactionalMessageId='.$this->customerIoTransactionalMessageId.' - '.
            '$customerEmail='.$this->customerEmail.' - '.
            '$messageDataArray='.var_export($this->messageDataArray).' - '
        );

        error_log($exception);

        parent::failed($exception);
    }
}