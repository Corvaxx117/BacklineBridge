<?php

declare(strict_types=1);

namespace App\Service\Patch;

/** Résultat du formulaire et statut HTTP associé, sans construire la réponse. */
enum FormSubmissionResult: int
{
    case NotSubmitted = 200;
    case Invalid = 422;
    case Saved = 303;
    case Conflict = 409;
}
