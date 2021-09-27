<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Railroad\Ecommerce\Entities\Payment;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\Ecommerce\Repositories\OrderRepository;
use Railroad\Ecommerce\Repositories\PaymentRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;

class SyncCustomerIoOldEvents extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SyncCustomerIoOldEvents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all old orders, payments and membership renewed and create proper events in Customer io.';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SyncCustomerIoOldEvents {--brand=}';

    /**
     * @var DatabaseManager
     */
    private $databaseManager;
    /**
     * @var CustomerIoSyncEventListener
     */
    private $customerIoSyncEventListener;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var PaymentRepository
     */
    private $paymentRepository;

    /**
     * @var EcommerceEntityManager
     */
    private $ecommerceEntityManager;

    /**
     * @param DatabaseManager $databaseManager
     * @param CustomerIoSyncEventListener $customerIoSyncEventListener
     * @param OrderRepository $orderRepository
     * @param PaymentRepository $paymentRepository
     */
    public function __construct(
        DatabaseManager $databaseManager,
        CustomerIoSyncEventListener $customerIoSyncEventListener,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        EcommerceEntityManager $ecommerceEntityManager
    ) {
        parent::__construct();

        $this->databaseManager = $databaseManager;
        $this->customerIoSyncEventListener = $customerIoSyncEventListener;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->ecommerceEntityManager = $ecommerceEntityManager;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tStart = $tStartCommand = time();

        $brand = $this->option('brand');

        $ecommerceConnection = $this->databaseManager->connection(config('ecommerce.database_connection_name'));

        $queryOrders =
            $ecommerceConnection->table('ecommerce_orders')
                ->join('ecommerce_order_payments', 'ecommerce_orders.id', '=', 'ecommerce_order_payments.order_id')
                ->join('ecommerce_payments', 'ecommerce_order_payments.payment_id', '=', 'ecommerce_payments.id')
                ->where('ecommerce_orders.user_id', '!=', null)
                ->where('ecommerce_payments.type', '=', 'initial_order')
                ->orderBy('ecommerce_orders.id', 'desc');

        if (!empty($brand)) {
            $queryOrders->where('brand', $brand);
        }

        $orderIdsToProcess = [];

        $queryOrders->chunk(10000, function (Collection $orderRows) use (&$orderIdsToProcess) {
            foreach ($orderRows as $orderRow) {
                $orderIdsToProcess[$orderRow->order_id] = $orderRow->payment_id;
            }

            $this->info('10000 orders rows.');
        });

        $this->info('Syncing ' . count($orderIdsToProcess) . ' total orders.');

        $thisSecond = time();
        $apiCallsThisSecond = 0;

        $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");

        foreach ($orderIdsToProcess as $orderId => $paymentId) {
            $order = $this->orderRepository->find($orderId);
            $payment = $this->paymentRepository->find($paymentId);

            try {
                $this->customerIoSyncEventListener->syncOrder($order, $payment);

                $apiCallsThisSecond++;

                if ($thisSecond == time()) {
                    if ($apiCallsThisSecond > 30) {
                        $this->info('Sleeping due to api calls in sec: ' . $apiCallsThisSecond);
                        sleep(1);
                    }
                } else {
                    $thisSecond = time();
                    $apiCallsThisSecond = 0;
                }

                $this->info('order id::' . $orderId);

                $this->ecommerceEntityManager->flush();
                $this->ecommerceEntityManager->clear();
                $this->ecommerceEntityManager->getConnection()
                    ->ping();

                unset($order);
                unset($payment);

                $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");
            } catch (Throwable $throwable) {
                error_log($throwable);
            }
        }

        $tEnd = time();

        $this->info(
            'Synced ' . count($orderIdsToProcess) . ' orders in ' . round((($tEnd - $tStart) / 60)) . ' minutes'
        );

        unset($orderIdsToProcess);
        unset($queryOrders);

        $this->info("Memory usage before payments: " . (memory_get_usage(false) / 1024 / 1024) . " MB");

        $tStart = time();

        $queryPayments =
            $ecommerceConnection->table('ecommerce_payments')
                ->select('ecommerce_payments.id')
                ->whereRaw('ecommerce_payments.total_paid = ecommerce_payments.total_due')
                ->where('ecommerce_payments.total_refunded', '=', 0)
                ->where('ecommerce_payments.total_paid', '!=', 0)
                ->where('ecommerce_payments.status', '=', 'paid')
                ->orderBy('ecommerce_payments.id', 'desc');

        if (!empty($brand)) {
            $queryPayments->where('ecommerce_payments.gateway_name', $brand);
        }

        $paymentsIdsToProcess = [];

        $queryPayments->chunk(10000, function (Collection $paymentRows) use (&$paymentsIdsToProcess) {
            foreach ($paymentRows as $paymentRow) {
                $paymentsIdsToProcess[$paymentRow->id] = $paymentRow->id;
            }

            $this->info('10000 payments rows.');
        });

        $this->info('Syncing ' . count($paymentsIdsToProcess) . ' total payments.');

        // $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");

        $thisSecond = time();
        $apiCallsThisSecond = 0;

        foreach ($paymentsIdsToProcess as $paymentId) {
            $payment = $this->paymentRepository->find($paymentId);

            if (!$payment->getPaymentMethod() ||
                !$payment->getPaymentMethod()
                    ->getUserPaymentMethod()) {
                unset($payment);
                continue;
            }

            $user =
                $payment->getPaymentMethod()
                    ->getUserPaymentMethod()
                    ->getUser();

            try {
                $this->customerIoSyncEventListener->syncPayment($payment, $user);
                $apiCallsThisSecond++;

                if ($thisSecond == time()) {
                    if ($apiCallsThisSecond > 50) {
                        $this->info('Sleeping due to api calls in sec: ' . $apiCallsThisSecond);
                        sleep(1);
                    }
                } else {
                    $thisSecond = time();
                    $apiCallsThisSecond = 0;
                }
            } catch (Throwable $throwable) {
                error_log($throwable);
            }

            if ($payment->getType() == Payment::TYPE_SUBSCRIPTION_RENEWAL) {
                try {
                    $this->customerIoSyncEventListener->syncSubscriptionRenew($payment->getSubscription());

                    $apiCallsThisSecond++;

                    if ($thisSecond == time()) {
                        if ($apiCallsThisSecond > 50) {
                            $this->info('Sleeping due to api calls in sec: ' . $apiCallsThisSecond);
                            sleep(1);
                        }
                    } else {
                        $thisSecond = time();
                        $apiCallsThisSecond = 0;
                    }
                } catch (Throwable $throwable) {
                    error_log($throwable);
                }
            }

            unset($payment);
            unset($user);

            $this->ecommerceEntityManager->flush();
            $this->ecommerceEntityManager->clear();
            $this->ecommerceEntityManager->getConnection()
                ->ping();

            $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");
        }

        $tEnd = time();

        $this->info(
            'Synced ' . count($paymentsIdsToProcess) . ' payments in ' . round((($tEnd - $tStart) / 60)) . ' minutes'
        );

        $this->info(
            'Command finished in ' . round((($tEnd - $tStartCommand) / 60)) . ' minutes'
        );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
