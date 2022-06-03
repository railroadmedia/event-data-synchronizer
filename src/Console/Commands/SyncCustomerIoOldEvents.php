<?php

namespace Railroad\EventDataSynchronizer\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Railroad\Ecommerce\Entities\Payment;
use Railroad\Ecommerce\Managers\EcommerceEntityManager;
use Railroad\Ecommerce\Repositories\OrderRepository;
use Railroad\Ecommerce\Repositories\PaymentRepository;
use Railroad\Ecommerce\Repositories\SubscriptionRepository;
use Railroad\EventDataSynchronizer\Listeners\CustomerIo\CustomerIoSyncEventListener;
use Railroad\Usora\Repositories\UserRepository;
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
    protected $signature = 'SyncCustomerIoOldEvents {--brand=} {--endDate=}';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        DatabaseManager $databaseManager,
        CustomerIoSyncEventListener $customerIoSyncEventListener,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        UserRepository $userRepository,
        SubscriptionRepository $subscriptionRepository,
        EcommerceEntityManager $ecommerceEntityManager
    ) {
        $tStart = $tStartCommand = time();

        $brand = $this->option('brand');

        $endDate = $this->option('endDate') ? Carbon::parse($this->option('endDate')) : Carbon::now();

        $ecommerceConnection = $databaseManager->connection(config('ecommerce.database_connection_name'));
        $ecommerceConnection->disableQueryLog();

        $ecommerceEntityManager->getConnection()
            ->getConfiguration()
            ->setSQLLogger(null);

        $queryOrders =
            $ecommerceConnection->table('ecommerce_orders')
                ->join('ecommerce_order_payments', 'ecommerce_orders.id', '=', 'ecommerce_order_payments.order_id')
                ->join('ecommerce_payments', 'ecommerce_order_payments.payment_id', '=', 'ecommerce_payments.id')
                ->where('ecommerce_orders.user_id', '!=', null)
                ->where('ecommerce_payments.type', '=', 'initial_order')
                ->where('ecommerce_orders.created_at', '<=', $endDate)
                ->orderBy('ecommerce_orders.id', 'desc');

        if (!empty($brand)) {
            $queryOrders->where('brand', $brand);
        } else {
            $queryOrders->where('brand', '!=', 'recordeo');
        }

        $orderIdsToProcess = [];

        $queryOrders->chunk(10000, function (Collection $orderRows) use (&$orderIdsToProcess) {
            foreach ($orderRows as $orderRow) {
                $orderIdsToProcess[$orderRow->order_id] = $orderRow->payment_id;
            }

            $this->info('10000 orders rows.');
        });

        unset($queryOrders);

        $this->info('Syncing ' . count($orderIdsToProcess) . ' total orders.');

        $thisSecond = time();
        $apiCallsThisSecond = 0;

        $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");
        foreach (array_chunk($orderIdsToProcess, 100, true) as $chunk) {
            foreach ($chunk as $orderId => $paymentId) {
                $order =
                    $orderRepository->createQueryBuilder('o')
                        ->where('o.id = :id')
                        ->setParameter('id', $orderId)
                        ->getQuery()
                        ->execute();

                if (!empty($order)) {
                    $order = $order[0];
                }
                $payment =
                    $paymentRepository->createQueryBuilder('p')
                        ->where('p.id = :id')
                        ->setParameter('id', $paymentId)
                        ->getQuery()
                        ->execute();
                if (!empty($payment)) {
                    $payment = $payment[0];
                }

                try {
                    $customerIoSyncEventListener->syncOrder($order, $payment);

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

                    $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");
                } catch (Throwable $throwable) {
                    error_log($throwable);
                }

                //$ecommerceEntityManager->flush();
                $ecommerceEntityManager->clear();
                $ecommerceEntityManager->getConnection()
                    ->ping();

                unset($order);
                unset($payment);
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
                ->select('ecommerce_payments.id', 'ecommerce_user_payment_methods.user_id')
                ->join(
                    'ecommerce_user_payment_methods',
                    'ecommerce_payments.payment_method_id',
                    '=',
                    'ecommerce_user_payment_methods.payment_method_id'
                )
                ->whereRaw('ecommerce_payments.total_paid = ecommerce_payments.total_due')
                ->where('ecommerce_payments.total_refunded', '=', 0)
                ->where('ecommerce_payments.total_paid', '!=', 0)
                ->where('ecommerce_payments.status', '=', 'paid')
                ->where('ecommerce_payments.created_at', '<=', $endDate)
                ->orderBy('ecommerce_payments.id', 'desc');

        if (!empty($brand)) {
            $queryPayments->where('ecommerce_payments.gateway_name', $brand);
        } else {
            $queryPayments->where('ecommerce_payments.gateway_name', '!=', 'recordeo');
        }

        $paymentsIdsToProcess = [];

        $queryPayments->chunk(10000, function (Collection $paymentRows) use (&$paymentsIdsToProcess) {
            foreach ($paymentRows as $paymentRow) {
                $paymentsIdsToProcess[$paymentRow->id] = $paymentRow->user_id;
            }

            $this->info('10000 payments rows.');
        });

        $this->info('Syncing ' . count($paymentsIdsToProcess) . ' total payments.');

        $thisSecond = time();
        $apiCallsThisSecond = 0;
        foreach (array_chunk($paymentsIdsToProcess, 100, true) as $chunk) {
            foreach ($chunk as $paymentId => $userId) {
                $payment =
                    $paymentRepository->createQueryBuilder('p')
                        ->where('p.id = :id')
                        ->setParameter('id', $paymentId)
                        ->getQuery()
                        ->execute();
                if (!empty($payment)) {
                    $payment = $payment[0];
                } else {
                    unset($payment);
                    continue;
                }

                try {
                    $this->info('payment id::' . $paymentId);

                    $customerIoSyncEventListener->syncPayment($payment, $userId);

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

                $ecommerceEntityManager->flush();
                $ecommerceEntityManager->clear();
                $ecommerceEntityManager->getConnection()
                    ->ping();

                unset($paymentMethod);
                unset($user);

                $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");

                if ($payment->getType() == Payment::TYPE_SUBSCRIPTION_RENEWAL) {
                    try {
                        $subscription =
                            $subscriptionRepository->createQueryBuilder('s')
                                ->where('s.id = :id')
                                ->setParameter(
                                    'id',
                                    $payment->getSubscriptionPayment()
                                        ->getSubscription()
                                        ->getId()
                                )
                                ->getQuery()
                                ->execute();
                        if (!empty($subscription)) {
                            $subscription = $subscription[0];

                            $this->info('subscription renewal id::' . $subscription->getId());

                            $customerIoSyncEventListener->syncSubscriptionRenew($subscription);

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
                        }
                    } catch (Throwable $throwable) {
                        error_log($throwable);
                    }
                }

                unset($payment);

                $ecommerceEntityManager->flush();
                $ecommerceEntityManager->clear();
                $ecommerceEntityManager->getConnection()
                    ->ping();

                $this->info("Memory usage: " . (memory_get_usage(false) / 1024 / 1024) . " MB");
            }
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
