<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Railroad\Ecommerce\Entities\Payment;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\Ecommerce\Repositories\OrderRepository;
use Railroad\Ecommerce\Repositories\PaymentRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Throwable;

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
        $brand = $this->option('brand');

        $ecommerceConnection = $this->databaseManager->connection(config('ecommerce.database_connection_name'));

        $queryOrders =
            $ecommerceConnection->table('ecommerce_orders')
                ->join('ecommerce_order_payments', 'ecommerce_orders.id', '=', 'ecommerce_order_payments.order_id')
                ->where('ecommerce_orders.user_id', '=', 340756)
                ->orderBy('ecommerce_orders.id', 'desc');

        if (!empty($brand)) {
            $queryOrders->where('brand', $brand);
        }

        $doneOrders = 0;

        //        $queryOrders->chunk(1, function (Collection $orderRows) use (&$doneOrders) {
        //            foreach ($orderRows as $orderRow) {
        //                $order = $this->orderRepository->find($orderRow->order_id);
        //                $payment = $this->paymentRepository->find($orderRow->payment_id);
        //
        //                // $this->customerIoSyncEventListener->syncOrder($order, $payment);
        //
        //                $doneOrders++;
        //
        //            }
        //            //sleep 1 second to not hit the API limit
        //            $this->info('Created events for ' . $doneOrders . ' orders. Now sleep one second');
        //            sleep(1);
        //
        //        });
        unset($queryOrders);

        //drumeo - 360555 payments
        $queryPayments =
            $ecommerceConnection->table('ecommerce_payments')
                ->select('ecommerce_payments.id')
                ->whereRaw('ecommerce_payments.total_paid = ecommerce_payments.total_due')
                ->where('ecommerce_payments.total_refunded', '=', 0)
                ->where('ecommerce_payments.total_paid', '!=', 0)
                ->where('ecommerce_payments.type', '=', 'subscription_renewal')
                ->where('ecommerce_payments.status', '=', 'paid')
                ->orderBy('ecommerce_payments.id', 'desc')
                ->limit(1);

        if (!empty($brand)) {
            $queryPayments->where('ecommerce_payments.gateway_name', $brand);
        }

        $donePayments = 0;
        $payments = $queryPayments->get();

        $this->info('Found ' . count($payments) . ' payments.');

        foreach ($payments->chunk(1) as $paymentsRows) {
            $this->info('RAM usage: ' . round(memory_get_usage(true) / 1048576, 2));
            foreach ($paymentsRows as $paymentRow) {
                $payment = $this->paymentRepository->find($paymentRow->id);

                if (!$payment->getPaymentMethod() ||
                    !$payment->getPaymentMethod()
                        ->getUserPaymentMethod()) {
                    continue;
                }

                $user =
                    $payment->getPaymentMethod()
                        ->getUserPaymentMethod()
                        ->getUser();

                try {
                    // $this->customerIoSyncEventListener->syncPayment($payment, $user);
                } catch (Throwable $throwable) {
                    error_log($throwable);
                }

                if ($payment->getType() == Payment::TYPE_SUBSCRIPTION_RENEWAL) {
                    try {
                        $this->customerIoSyncEventListener->syncSubscriptionRenew($payment->getSubscription());
                    } catch (Throwable $throwable) {
                        error_log($throwable);
                    }

                }

                unset($payment);
                unset($user);

                $donePayments++;
            }

            $this->info('Created events for ' . $donePayments . ' payments. Now sleep one second');

            $this->ecommerceEntityManager->flush();
            $this->ecommerceEntityManager->clear();
            $this->ecommerceEntityManager->getConnection()
                ->ping();

            $this->info('RAM usage: ' . round(memory_get_usage(true) / 1048576, 2));
            sleep(1);
        }
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
