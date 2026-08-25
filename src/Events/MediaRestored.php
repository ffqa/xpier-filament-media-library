<?php

namespace Xpier\FilamentMediaLibrary\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

class MediaRestored
{
    use Dispatchable;

    public function __construct(public MediaLibrary $media) {}
}
