<?php

namespace Railroad\EventDataSynchronizer\ValueObjects;

class IntercomAddRemoveTagsVO
{
    /**
     * @var array
     */
    public $tagsToAdd;

    /**
     * @var array
     */
    public $tagsToRemove;

    /**
     * IntercomAddRemoveTagsVO constructor.
     * @param  array  $tagsToAdd
     * @param  array  $tagsToRemove
     */
    public function __construct(array $tagsToAdd = [], array $tagsToRemove = [])
    {
        $this->tagsToAdd = $tagsToAdd;
        $this->tagsToRemove = $tagsToRemove;
    }
}