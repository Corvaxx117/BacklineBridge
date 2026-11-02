<?php

declare(strict_types=1);

namespace App\Service\Patch;

use App\Entity\PatchLine;

final class PatchLineCopier
{
    public function copy(PatchLine $source, ?PatchLine $target = null): PatchLine
    {
        return ($target ?? new PatchLine())
            ->inheritSourceKey($source)
            ->setChannelNumber($source->getChannelNumber())
            ->setSourceName($source->getSourceName())
            ->setPreferredMicrophone($source->getPreferredMicrophone())
            ->setStand($source->getStand())
            ->setNotes($source->getNotes())
            ->setSection($source->getSection());
    }
}
