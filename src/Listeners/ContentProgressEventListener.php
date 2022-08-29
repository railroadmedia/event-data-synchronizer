<?php

namespace Railroad\EventDataSynchronizer\Listeners;

use App\Maps\ContentTypes;
use Carbon\Carbon;
use Railroad\EventDataSynchronizer\Providers\UserProviderInterface;
use Railroad\Points\Events\UserPointsUpdated;
use Railroad\Points\Services\UserPointsService;
use Railroad\Railcontent\Events\UserContentProgressSaved;
use Railroad\Railcontent\Events\UserContentProgressStarted;
use Railroad\Railcontent\Events\UserContentsProgressReset;
use Railroad\Railcontent\Helpers\ContentHelper;
use Railroad\Railcontent\Repositories\ContentRepository;
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
     * @var ContentRepository
     */
    private $contentRepository;
    /**
     * @var UserPointsService
     */
    private $userPointsService;
    //    /**
    //     * @var MediaPlaybackRepository
    //     */
    private $mediaPlaybackRepository;

    private UserProviderInterface $userProvider;

    public function __construct(
        UserContentProgressService $userContentProgressService,
        ContentHierarchyService $contentHierarchyService,
        ContentService $contentService,
        ContentRepository $contentRepository,
        UserPointsService $userPointsService,
        UserProviderInterface $userProvider
        //        MediaPlaybackRepository $mediaPlaybackRepository
    )
    {
        $this->userContentProgressService = $userContentProgressService;
        $this->contentService = $contentService;
        $this->contentHierarchyService = $contentHierarchyService;
        $this->contentRepository = $contentRepository;
        $this->userPointsService = $userPointsService;
        $this->userProvider = $userProvider;
        //        $this->mediaPlaybackRepository = $mediaPlaybackRepository;
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
                        'Awarded for every minute of an assignment practiced watched.'
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
                        'Awarded for every minute of a play-along practiced watched.'
                    );

                    $minutes--;
                }
            }

            return;
        }

        // get all videos with this vimeo id
        //TODO
        //        $vimeoIdFields = $this->contentFieldService->getByKeyValueTypePosition(
        //            'vimeo_video_id',
        //            $mediaPlaybackTracked->mediaId,
        //            'string',
        //            1
        //        );
        $vimeoIdFields = [];
        //        $vimeoIdFields = array_merge(
        //            $vimeoIdFields,
        //            $this->contentFieldService->getByKeyValueTypePosition(
        //                'youtube_video_id',
        //                $mediaPlaybackTracked->mediaId,
        //                'string',
        //                1
        //            )
        //        );
        //
        //        $lengthInSeconds = $mediaPlaybackTracked->mediaLengthInSeconds;
        //
        //        foreach ($vimeoIdFields as $vimeoIdField) {
        //            // get all the contents with this video and update their progress
        //            $videoFields = $this->contentFieldService->getByKeyValueType(
        //                $vimeoIdField['key'] == 'youtube_video_id' ? 'youtube_video' : 'video',
        //                $vimeoIdField['content_id'],
        //                'content_id'
        //            );
        //
        //            $contentIds = array_column($videoFields, 'content_id');
        //            sort($contentIds);
        //
        //            $contentIds = array_slice(array_reverse($contentIds), 0, 1);
        //
        //            foreach ($contentIds as $contentId) {
        //                $totalTimeWatched = (integer)$this->mediaPlaybackRepository->sumTotalPlayed(
        //                    $mediaPlaybackTracked->userId,
        //                    $mediaPlaybackTracked->mediaId,
        //                    $mediaPlaybackTracked->typeId
        //                );
        //
        //                if ($lengthInSeconds > 0 && $totalTimeWatched < $lengthInSeconds) {
        //                    $minutes = floor($totalTimeWatched / 60);
        //
        //                    while ($minutes > 0) {
        //                        $this->userPointsService->setPoints(
        //                            $mediaPlaybackTracked->userId,
        //                            [
        //                                'content_id' => $contentId,
        //                                'minutes_watched' => $minutes,
        //                            ],
        //                            'minutes_of_content_watched',
        //                            config('xp_ranks.per_minute_content_watched'),
        //                            'Awarded for every minute of video watched.'
        //                        );
        //
        //                        $minutes--;$this->userProvider->saveExperiencePoints($userContentProgressSaved->userId, $this->userPointsService->countUserPoints($userContentProgressSaved->userId));
        //                    }
        //                }
        //
        //                if ($mediaPlaybackTracked->mediaLengthInSeconds > 0) {
        //                    $this->userContentProgressService->saveContentProgress(
        //                        $contentId,
        //                        min(
        //                            round(
        //                                $mediaPlaybackTracked->currentSecond / $mediaPlaybackTracked->mediaLengthInSeconds * 100
        //                            ),
        //                            99
        //                        ),
        //                        $mediaPlaybackTracked->userId
        //                    );
        //                }
        //            }
        //        }
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
}
