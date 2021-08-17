<?php

namespace Railroad\EventDataSynchronizer\Listeners\CustomerIo;

use Carbon\Carbon;
use Railroad\Ecommerce\Entities\User as EcommerceUser;
use Railroad\Ecommerce\Events\AppSignupFinishedEvent;
use Railroad\Ecommerce\Events\AppSignupStartedEvent;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodCreated;
use Railroad\Ecommerce\Events\PaymentMethods\PaymentMethodUpdated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionCreated;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionRenewFailed;
use Railroad\Ecommerce\Events\Subscriptions\SubscriptionUpdated;
use Railroad\Ecommerce\Events\UserProducts\UserProductCreated;
use Railroad\Ecommerce\Events\UserProducts\UserProductDeleted;
use Railroad\Ecommerce\Events\UserProducts\UserProductUpdated;
use Railroad\EventDataSynchronizer\Jobs\CustomerIoCreateEventByUserId;
use Railroad\EventDataSynchronizer\Jobs\CustomerIoSyncNewUserByEmail;
use Railroad\EventDataSynchronizer\Jobs\CustomerIoSyncUserByUserId;
use Railroad\Railcontent\Events\CommentCreated;
use Railroad\Railcontent\Events\CommentLiked;
use Railroad\Railcontent\Events\UserContentProgressSaved;
use Railroad\Railcontent\Repositories\CommentRepository;
use Railroad\Railcontent\Repositories\ContentRepository;
use Railroad\Railcontent\Services\ContentService;
use Railroad\Railforums\Events\PostCreated;
use Railroad\Railforums\Events\ThreadCreated;
use Railroad\Railforums\Repositories\CategoryRepository;
use Railroad\Railforums\Repositories\PostRepository;
use Railroad\Railforums\Repositories\ThreadRepository;
use Railroad\Railforums\Services\ConfigService;
use Railroad\Usora\Entities\User;
use Railroad\Usora\Events\User\UserCreated;
use Railroad\Usora\Events\User\UserUpdated;
use Railroad\Usora\Repositories\UserRepository;
use Throwable;

class CustomerIoSyncEventListener
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var CommentRepository
     */
    private $commentRepository;

    /**
     * @var ThreadRepository
     */
    private $threadRepository;

    /**
     * @var PostRepository
     */
    private $postRepository;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var ContentService
     */
    private $contentService;

    private $queueConnectionName = 'database';

    private $queueName = 'customer_io';

    /**
     * @var bool
     */
    public static $disable = false;
    /**
     * @var array
     */
    public static $alreadyQueuedUserIds = [];

    /**
     * CustomerIoSyncEventListener constructor.
     *
     * @param  UserRepository  $userRepository
     * @param  CommentRepository  $commentRepository
     * @param  CategoryRepository  $categoryRepository
     * @param  ThreadRepository  $threadRepository
     * @param  PostRepository  $postRepository
     */
    public function __construct(
        UserRepository $userRepository,
        CommentRepository $commentRepository,
        CategoryRepository $categoryRepository,
        ThreadRepository $threadRepository,
        PostRepository $postRepository,
        ContentService $contentService
    ) {
        $this->userRepository = $userRepository;

        $this->queueConnectionName = config('event-data-synchronizer.customer_io_queue_connection_name', 'database');
        $this->queueName = config('event-data-synchronizer.customer_io_queue_name', 'customer_io');
        $this->commentRepository = $commentRepository;
        $this->categoryRepository = $categoryRepository;
        $this->threadRepository = $threadRepository;
        $this->postRepository = $postRepository;
        $this->contentService = $contentService;
    }

    /**
     * @param  UserCreated  $userCreated
     */
    public function handleUserCreated(UserCreated $userCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find($userCreated->getUser()->getId());

            if (!empty($user) && !in_array($userCreated->getUser()->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new CustomerIoSyncNewUserByEmail($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $userCreated->getUser()->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserUpdated  $userUpdated
     */
    public function handleUserUpdated(UserUpdated $userUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find($userUpdated->getNewUser()->getId());

            if (!empty($user) && !in_array($userUpdated->getNewUser()->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new CustomerIoSyncUserByUserId($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  PaymentMethodCreated  $paymentMethodCreated
     */
    public function handleUserPaymentMethodCreated(PaymentMethodCreated $paymentMethodCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if ($paymentMethodCreated->getUser() instanceof User) {
                $this->handleUserPaymentMethodUpdated(
                    new PaymentMethodUpdated(
                        $paymentMethodCreated->getPaymentMethod(),
                        $paymentMethodCreated->getPaymentMethod(),
                        $paymentMethodCreated->getUser()
                    )
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  PaymentMethodUpdated  $paymentMethodUpdated
     */
    public function handleUserPaymentMethodUpdated(PaymentMethodUpdated $paymentMethodUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty(
                $paymentMethodUpdated->getUser()
                    ->getId()
                ) && $paymentMethodUpdated->getUser() instanceof User &&
                !in_array($paymentMethodUpdated->getUser()->getId(), self::$alreadyQueuedUserIds)) {
                $user = $this->userRepository->find(
                    $paymentMethodUpdated->getUser()
                        ->getId()
                );

                dispatch(
                    (new CustomerIoSyncUserByUserId($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductCreated  $userProductCreated
     */
    public function handleUserProductCreated(UserProductCreated $userProductCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductCreated->getUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new CustomerIoSyncUserByUserId($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductUpdated  $userProductUpdated
     */
    public function handleUserProductUpdated(UserProductUpdated $userProductUpdated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductUpdated->getNewUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new CustomerIoSyncUserByUserId($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  UserProductDeleted  $userProductDeleted
     */
    public function handleUserProductDeleted(UserProductDeleted $userProductDeleted)
    {
        if (self::$disable) {
            return;
        }

        try {
            $user = $this->userRepository->find(
                $userProductDeleted->getUserProduct()
                    ->getUser()
                    ->getId()
            );

            if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                dispatch(
                    (new CustomerIoSyncUserByUserId($user))
                        ->onConnection($this->queueConnectionName)
                        ->onQueue($this->queueName)
                        ->delay(Carbon::now()->addSeconds(3))
                );

                self::$alreadyQueuedUserIds[] = $user->getId();
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionCreated  $subscriptionCreated
     */
    public function handleSubscriptionCreated(SubscriptionCreated $subscriptionCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty(
                $subscriptionCreated->getSubscription()
                    ->getUser() &&
                $subscriptionCreated->getSubscription()
                    ->getUser() instanceof User
            )) {
                $user = $this->userRepository->find(
                    $subscriptionCreated->getSubscription()
                        ->getUser()
                        ->getId()
                );

                if (!empty($user) && !in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                    dispatch(
                        (new CustomerIoSyncUserByUserId($user))
                            ->onConnection($this->queueConnectionName)
                            ->onQueue($this->queueName)
                            ->delay(Carbon::now()->addSeconds(3))
                    );

                    self::$alreadyQueuedUserIds[] = $user->getId();
                }
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionUpdated  $subscriptionUpdated
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $subscriptionUpdated)
    {
        if (self::$disable) {
            return;
        }

        $newSubscription = $subscriptionUpdated->getNewSubscription();

        try {
            $user = $newSubscription->getUser();

            if ($user instanceof EcommerceUser) {
                $user = $this->userRepository->find($newSubscription->getUser()->getId());
            }

            if ($user instanceof User) {
                if (!in_array($user->getId(), self::$alreadyQueuedUserIds)) {
                    dispatch(
                        (new CustomerIoSyncUserByUserId($user))
                            ->onConnection($this->queueConnectionName)
                            ->onQueue($this->queueName)
                            ->delay(Carbon::now()->addSeconds(3))
                    );

                    self::$alreadyQueuedUserIds[] = $user->getId();
                }
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  AppSignupStartedEvent  $appSignupStarted
     */
    public function handleAppSignupStarted(AppSignupStartedEvent $appSignupStarted)
    {
        if (self::$disable) {
            return;
        }

        try {
            // todo: sync event for this: brand_onboarding_app_sign-up-flow-started
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  AppSignupFinishedEvent  $appSignupFinished
     */
    public function handleAppSignupFinished(AppSignupFinishedEvent $appSignupFinished)
    {
        if (self::$disable) {
            return;
        }

        try {
            // todo: sync event for this: brand_onboarding_app_sign-up-flow-finished
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionRenewed  $subscriptionRenewed
     */
    public function handleSubscriptionRenewed(SubscriptionRenewed $subscriptionRenewed)
    {
        if (self::$disable) {
            return;
        }

        try {
            if (!empty($subscriptionRenewed->getSubscription()) &&
                !empty($subscriptionRenewed->getSubscription()->getUser())) {
                dispatch(
                    (new CustomerIoCreateEventByUserId(
                        $subscriptionRenewed->getSubscription()->getUser()->getId(),
                        $subscriptionRenewed->getSubscription()->getBrand(),
                        $subscriptionRenewed->getSubscription()->getBrand().'_membership_renewed',
                        [
                            'membership_rate' => $subscriptionRenewed->getSubscription()->getTotalPrice(),
                        ],
                        null,
                        Carbon::now()->timestamp
                    ))
                        ->onConnection('sync')
                        ->onQueue('customer_io')
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  SubscriptionRenewFailed  $subscriptionRenewFailed
     */
    public function handleSubscriptionRenewalAttemptFailed(SubscriptionRenewFailed $subscriptionRenewFailed)
    {
        if (self::$disable) {
            return;
        }

        if ($subscriptionRenewFailed->getSubscription()->getIsActive() == false &&
            $subscriptionRenewFailed->getSubscription()->getCanceledOn() == null &&
            $subscriptionRenewFailed->getSubscription()->getStopped() == false &&
            $subscriptionRenewFailed->getSubscription()->getPaidUntil() < Carbon::now()) {
            // todo
        }
    }

    /**
     * @param  CommentLiked  $commentLiked
     */
    public function handleCommentLiked(CommentLiked $commentLiked)
    {
        if (self::$disable) {
            return;
        }

        try {
            $comment = $this->commentRepository->getById($commentLiked->commentId);
            $content = $this->contentService->getById($comment['content_id']);
            $user = $this->userRepository->find($commentLiked->userId);

            if (!empty($comment) &&
                !empty($content) &&
                !empty($user)) {
                dispatch(
                    (new CustomerIoCreateEventByUserId(
                        $user->getId(),
                        $content['brand'],
                        $content['brand'].'_action_lesson_comment-like',
                        [
                            'content_id' => $content['id'],
                            'content_name' => $content->fetch('fields.title'),
                            'content_type' => $content['type'],
                        ],
                        null,
                        Carbon::now()->timestamp
                    ))
                        ->onConnection('sync')
                        ->onQueue('customer_io')
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  CommentCreated  $commentCreated
     */
    public function handleCommentCreated(CommentCreated $commentCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $comment = $this->commentRepository->getById($commentCreated->commentId);
            $content = $this->contentService->getById($comment['content_id']);
            $user = $this->userRepository->find($commentCreated->userId);

            if (!empty($comment) &&
                !empty($content) &&
                !empty($user)) {
                dispatch(
                    (new CustomerIoCreateEventByUserId(
                        $user->getId(),
                        $content['brand'],
                        $content['brand'].'_action_lesson_comment',
                        [
                            'content_id' => $content['id'],
                            'content_name' => $content->fetch('fields.title'),
                            'content_type' => $content['type'],
                        ],
                        null,
                        Carbon::now()->timestamp
                    ))
                        ->onConnection('sync')
                        ->onQueue('customer_io')
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  ThreadCreated  $threadCreated
     */
    public function handleForumsThreadCreated(ThreadCreated $threadCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $thread = $this->threadRepository->getDecoratedQuery()
                ->where(ConfigService::$tableThreads.'.id', $threadCreated->getThreadId())
                ->first();
            $category = $this->categoryRepository->getDecoratedQuery()
                ->where(ConfigService::$tableCategories.'.id', $thread['category_id'])
                ->first();
            $user = $this->userRepository->find($threadCreated->getUserId());

            if (!empty($thread) &&
                !empty($user)) {
                dispatch(
                    (new CustomerIoCreateEventByUserId(
                        $user->getId(),
                        $category['brand'],
                        $category['brand'].'_action_forum_create-thread',
                        [],
                        null,
                        Carbon::now()->timestamp
                    ))
                        ->onConnection('sync')
                        ->onQueue('customer_io')
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }

    /**
     * @param  PostCreated  $postCreated
     */
    public function handleForumsPostCreated(PostCreated $postCreated)
    {
        if (self::$disable) {
            return;
        }

        try {
            $post = $this->postRepository->getDecoratedQuery()
                ->where(ConfigService::$tablePosts.'.id', $postCreated->getPostId())
                ->first();
            $thread = $this->threadRepository->getDecoratedQuery()
                ->where(ConfigService::$tableThreads.'.id', $post['thread_id'])
                ->first();
            $category = $this->categoryRepository->getDecoratedQuery()
                ->where(ConfigService::$tableCategories.'.id', $thread['category_id'])
                ->first();
            $user = $this->userRepository->find($post['author_id']);

            if (!empty($thread) &&
                !empty($user)) {
                dispatch(
                    (new CustomerIoCreateEventByUserId(
                        $user->getId(),
                        $category['brand'],
                        $category['brand'].'_action_forum_comment',
                        [],
                        null,
                        Carbon::now()->timestamp
                    ))
                        ->onConnection('sync')
                        ->onQueue('customer_io')
                );
            }
        } catch (Throwable $throwable) {
            error_log($throwable);
        }
    }


    /**
     * @param  UserContentProgressSaved  $userContentProgressSaved
     */
    public function handleUserContentProgressSaved(UserContentProgressSaved $userContentProgressSaved)
    {
        if (self::$disable) return;

        $content = $this->contentService->getById($userContentProgressSaved->contentId);

    }
}