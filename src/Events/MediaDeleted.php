<?php

namespace Xpier\FilamentMediaLibrary\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

class MediaDeleted
{
    use Dispatchable;

    /**
     * @param  bool  $force  Whether the record was force-deleted (physical file removed).
     */
    public function __construct(
        public MediaLibrary $media,
        public bool $force = false,
    ) {
    }
}
