<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use App\Maps\ContentTypes;
use Carbon\Carbon;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;
use Railroad\Points\Events\UserPointsUpdated;
use Railroad\Points\Services\UserPointsService;
use Railroad\Railcontent\Events\CommentCreated;
use Railroad\Railcontent\Events\CommentLiked;
use Railroad\Railcontent\Events\CommentUnLiked;
use Railroad\Railcontent\Events\UserContentProgressSaved;
use Railroad\Railcontent\Events\UserContentProgressStarted;
use Railroad\Railcontent\Events\UserContentsProgressReset;
use Railroad\Railcontent\Helpers\ContentHelper;
use Railroad\Railcontent\Repositories\ContentRepository;
use Railroad\Railcontent\Services\CommentLikeService;
use Railroad\Railcontent\Services\CommentService;
use Railroad\Railcontent\Services\ContentHierarchyService;
use Railroad\Railcontent\Services\ContentService;
use Railroad\Railcontent\Services\UserContentProgressService;
use Railroad\Railtracker\Events\MediaPlaybackTracked;
use Railroad\Railtracker\Repositories\MediaPlaybackRepository;
use Throwable;

class ContentProgressEventListener
{
    /**
     * @var UserContentProgressService
     */
    private $userContentProgressService;

    /**
     * @var ContentHierarchyService
     */
    private $contentHierarchyService;

    /**
     * @var ContentService
     */
    private $contentService;
    /**
     * @var CommentService
     */
    private $commentService;
    /**
     * @var CommentLikeService
     */
    private $commentLikeService;
    /**
     * @var ContentRepository
     */
    private $contentRepository;
    /**
     * @var UserPointsService
     */
    private $userPointsService;
    /**
     * @var MediaPlaybackRepository
     */
    private $mediaPlaybackRepository;

    private UserProviderInterface $userProvider;

    public function __construct(
        UserContentProgressService $userContentProgressService,
        ContentHierarchyService $contentHierarchyService,
        ContentService $contentService,
        CommentService $commentService,
        CommentLikeService $commentLikeService,
        ContentRepository $contentRepository,
        UserPointsService $userPointsService,
        UserProviderInterface $userProvider,
        MediaPlaybackRepository $mediaPlaybackRepository
    ) {
        $this->userContentProgressService = $userContentProgressService;
        $this->contentService = $contentService;
        $this->commentService = $commentService;
        $this->contentHierarchyService = $contentHierarchyService;
        $this->contentRepository = $contentRepository;
        $this->userPointsService = $userPointsService;
        $this->userProvider = $userProvider;
        $this->mediaPlaybackRepository = $mediaPlaybackRepository;
    }

    public function handleUserProgressSaved(UserContentProgressSaved $userContentProgressSaved)
    {
        $content = $this->contentService->getById($userContentProgressSaved->contentId);

        if (!empty($content)) {
            $state = ContentHelper::getUserContentProgressState($userContentProgressSaved->userId, $content);
            $percent = ContentHelper::getUserContentProgressPercent($userContentProgressSaved->userId, $content);

            // -----------------------------------
            // award xp
            // course
            if ($content['type'] == 'course') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'course_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.course_content_completed')),
                        'Awarded per complete course.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // semester pack
            if ($content['type'] == 'semester-pack') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'pack_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.pack_content_completed')),
                        'Awarded per complete pack.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // pack
            if ($content['type'] == 'pack') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'pack_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.pack_content_completed')),
                        'Awarded per complete pack.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // pack bundle
            if ($content['type'] == 'pack-bundle') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'pack_bundle_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.pack_bundle_content_completed')),
                        'Awarded per complete pack dvd/bundle.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // learning path
            if ($content['type'] == 'learning-path') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'learning_path_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.learning_path_content_completed')),
                        'Awarded per complete learning path.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // learning path level
            if ($content['type'] == 'learning-path-level') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'learning_path_content_level_completed',
                        $content->fetch('fields.xp', config('xp_ranks.learning_path_level_content_completed')),
                        'Awarded per complete learning path level.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // learning path course
            if ($content['type'] == 'learning-path-course') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'learning_path_content_course_completed',
                        $content->fetch('fields.xp', config('xp_ranks.learning_path_course_content_completed')),
                        'Awarded per complete learning path course.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // learning path lesson
            if ($content['type'] == 'learning-path-lesson') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'learning_path_content_lesson_completed',
                        $content->fetch('fields.xp', config('xp_ranks.learning_path_lesson_content_completed')),
                        'Awarded per complete learning path lesson.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // assignment
            if ($content['type'] == 'assignment') {
                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        'assignment_content_completed',
                        $content->fetch('fields.xp', config('xp_ranks.assignment_content_completed')),
                        'Awarded per complete assignment.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }

            // other singular lesson types
            if (in_array(
                $content['type'],
                ContentTypes::singularContentTypes()
            )) {
                $pointAmount =
                    config('xp_ranks.difficulty_xp_map')[$content->fetch('fields.difficulty')]
                    ??
                    config('xp_ranks.difficulty_xp_map.all');

                // if xp is set in the field use that instead of the preconfigured defaults
                $pointAmount = $content->fetch('fields.xp', $pointAmount);

                if (($percent == 100 || $state == 'completed')) {
                    $this->userPointsService->setPoints(
                        $userContentProgressSaved->userId,
                        [
                            'content_id' => $content['id'],
                            'progress_state' => 'completed',
                        ],
                        str_replace('-', '_', $content['type']).'_content_completed',
                        $pointAmount,
                        'Awarded per complete '.str_replace('-', ' ', $content['type']).'.'
                    );
                } else {
                    $this->userPointsService->deletePoints($userContentProgressSaved->userId, [
                        'content_id' => $content['id'],
                        'progress_state' => 'completed',
                    ]);
                }
            }
            $this->userProvider->saveExperiencePoints(
                $userContentProgressSaved->userId,
                $this->userPointsService->countUserPoints(
                    $userContentProgressSaved->userId
                )
            );
        }
    }

    public function handleMediaPlaybackTracked(MediaPlaybackTracked $mediaPlaybackTracked)
    {
        // sound slice assignment
        if ($mediaPlaybackTracked->typeId == 4) {
            $maxMinutesToTrack = 600;

            $totalTimeWatched = (integer)$this->mediaPlaybackRepository->sumTotalPlayed(
                $mediaPlaybackTracked->userId,
                $mediaPlaybackTracked->mediaId,
                $mediaPlaybackTracked->typeId
            );

            if ($totalTimeWatched <= $maxMinutesToTrack) {
                $minutes = floor($totalTimeWatched / 60);

                while ($minutes > 0) {
                    $this->userPointsService->setPoints(
                        $mediaPlaybackTracked->userId,
                        [
                            'content_id' => $mediaPlaybackTracked->mediaId,
                            'minutes_watched' => $minutes,
                        ],
                        'per_minute_of_assignment_practiced',
                        config('xp_ranks.per_minute_of_assignment_practiced'),
                        'Awarded for every minute of an assignment practiced watched.',
                        $mediaPlaybackTracked->brand
                    );

                    $minutes--;
                }
            }

            return;
        }

        // play along song
        if ($mediaPlaybackTracked->typeId == 5) {
            $maxMinutesToTrack = 600;

            $totalTimeWatched = (integer)$this->mediaPlaybackRepository->sumTotalPlayed(
                $mediaPlaybackTracked->userId,
                $mediaPlaybackTracked->mediaId,
                $mediaPlaybackTracked->typeId
            );

            if ($totalTimeWatched <= $maxMinutesToTrack) {
                $minutes = floor($totalTimeWatched / 60);

                while ($minutes > 0) {
                    $this->userPointsService->setPoints(
                        $mediaPlaybackTracked->userId,
                        [
                            'content_id' => $mediaPlaybackTracked->mediaId,
                            'minutes_watched' => $minutes,
                        ],
                        'per_minute_of_play_along_practiced',
                        config('xp_ranks.per_minute_of_play_along_practiced'),
                        'Awarded for every minute of a play-along practiced watched.',
                        $mediaPlaybackTracked->brand
                    );

                    $minutes--;
                }
            }

            return;
        }

        $vimeoIdFields = $this->contentService->getContentWithExternalVideoId($mediaPlaybackTracked->mediaId);

        $lengthInSeconds = $mediaPlaybackTracked->mediaLengthInSeconds;

        foreach ($vimeoIdFields as $content) {
            $totalTimeWatched = (integer)$this->mediaPlaybackRepository->sumTotalPlayed(
                $mediaPlaybackTracked->userId,
                $mediaPlaybackTracked->mediaId,
                $mediaPlaybackTracked->typeId
            );

            if ($lengthInSeconds > 0 && $totalTimeWatched < $lengthInSeconds) {
                $minutes = floor($totalTimeWatched / 60);

                while ($minutes > 0) {
                    $this->userPointsService->setPoints(
                        $mediaPlaybackTracked->userId,
                        [
                            'content_id' => $content['id'],
                            'minutes_watched' => $minutes,
                        ],
                        'minutes_of_content_watched',
                        config('xp_ranks.per_minute_content_watched'),
                        'Awarded for every minute of video watched.',
                        $mediaPlaybackTracked->brand
                    );

                    $minutes--;
                    $this->userProvider->saveExperiencePoints(
                        $mediaPlaybackTracked->userId,
                        $this->userPointsService->countUserPoints(
                            $mediaPlaybackTracked->userId
                        )
                    );
                }
            }

            if ($mediaPlaybackTracked->mediaLengthInSeconds > 0) {
                $this->userContentProgressService->saveContentProgress(
                    $content['id'],
                    min(
                        round(
                            $mediaPlaybackTracked->currentSecond / $mediaPlaybackTracked->mediaLengthInSeconds * 100
                        ),
                        99
                    ),
                    $mediaPlaybackTracked->userId
                );
            }
        }

        $this->userProvider->saveExperiencePoints(
            $mediaPlaybackTracked->userId,
            $this->userPointsService->countUserPoints(
                $mediaPlaybackTracked->userId
            )
        );
    }

    public function handleReset(UserContentsProgressReset $userContentsProgressReset)
    {
        foreach ($userContentsProgressReset->contentIds as $contentId) {
            $this->userPointsService->deletePoints($userContentsProgressReset->userId, [
                'content_id' => $contentId,
                'progress_state' => 'completed',
            ]);
        }

        $this->userProvider->saveExperiencePoints(
            $userContentsProgressReset->userId,
            $this->userPointsService->countUserPoints(
                $userContentsProgressReset->userId
            )
        );
    }

    public function handleCommentCreated(CommentCreated $commentCreated)
    {
        /** @var CommentEntity $comment */
        $comment = $this->commentService->get($commentCreated->commentId);

        if (empty($comment)) {
            return;
        }

        $content = $this->contentService->getById($comment['content_id']);
        if (empty($content)) {
            return;
        }

        // award xp
        $this->userPointsService->setPoints(
            $comment['user_id'],
            [
                'comment_id' => $comment['id'],
                'content_id' => $comment['content_id'],
            ],
            'comment_posted',
            config('xp_ranks.comment_posted'),
            'Awarded per comment posted.'
        );

        $this->userProvider->saveExperiencePoints(
            $comment['user_id'],
            $this->userPointsService->countUserPoints(
                $comment['user_id']
            )
        );
    }

    public function handleCommentDeleted(CommentDeleted $commentDeleted)
    {
        $comment = $this->commentService->get($commentDeleted->commentId);

        if (empty($comment)) {
            return;
        }

        // remove xp
        $this->userPointsService->deletePoints(
            $comment['user_id'],
            [
                'comment_id' => $comment['id'],
                'content_id' => $comment['content_id'],
            ]
        );

        // chunk delete points for all the comment likes
        $page = 1;

        do {
            $commentLikes = $this->commentLikeService->getCommentLikesPaginated($comment['id'], 250, $page)['results'];

            $hashes = [];

            foreach ($commentLikes as $commentLike) {
                $hashes[] = $this->userPointsService->hash(
                    [
                        'comment_id' => $comment['id'],
                        'comment_liker_user_id' => $commentLike['user_id'],
                    ]
                );
            }

            $this->userPointsService->repository()
                ->query()
                ->whereIn('trigger_hash', $hashes)
                ->delete();

            $page++;
        } while (count($commentLikes) > 0);

        $this->userProvider->saveExperiencePoints(
            $comment['user_id'],
            $this->userPointsService->countUserPoints(
                $comment['user_id']
            )
        );
    }

    public function handleCommentLiked(CommentLiked $commentLiked)
    {
        $comment = $this->commentService->get($commentLiked->commentId);

        // award xp
        $this->userPointsService->setPoints(
            $comment['user_id'],
            [
                'comment_id' => $comment['id'],
                'comment_liker_user_id' => $commentLiked->userId,
            ],
            'comment_liked',
            config('xp_ranks.comment_liked'),
            'Awarded per comment like.'
        );

        $this->userProvider->saveExperiencePoints(
            $commentLiked->userId,
            $this->userPointsService->countUserPoints(
                $commentLiked->userId
            )
        );
    }

    public function handleCommentUnLiked(CommentUnLiked $commentUnLiked)
    {
        $comment = $this->commentService->get($commentUnLiked->commentId);

        // remove xp
        $this->userPointsService->deletePoints(
            $comment['user_id'],
            [
                'comment_id' => $comment['id'],
                'comment_liker_user_id' => $commentUnLiked->userId,
            ]
        );

        $this->userProvider->saveExperiencePoints(
            $commentUnLiked->userId,
            $this->userPointsService->countUserPoints(
                $commentUnLiked->userId
            )
        );
    }
}
