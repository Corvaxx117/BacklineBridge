<?php

declare(strict_types=1);

namespace App\Dto\Venue;

use App\Entity\VenueReply;
use App\Enum\Availability;
use Symfony\Component\Validator\Constraints as Assert;

final class VenueReplyInput
{
    #[Assert\NotBlank(message: 'Indiquez votre nom ou votre fonction.'), Assert\Length(max: 150)]
    public string $respondent = '';
    #[Assert\Length(max: 2000)]
    public ?string $notes = null;
    /** @var array<string, AvailabilityAnswerInput> */
    #[Assert\Valid]
    public array $answers = [];
    public string $revision = '0';

    /**
     * @param array<string, array{category: string, label: string, specification: string}> $requests
     */
    public static function forRequests(array $requests, ?VenueReply $reply): self
    {
        $input = new self();
        $input->respondent = $reply?->getRespondent() ?? '';
        $input->notes = $reply?->getNotes();
        $input->revision = (string) ($reply?->getRevision() ?? 0);
        foreach ($requests as $key => $request) {
            $answer = new AvailabilityAnswerInput();
            $saved = $reply?->getAnswers()[$key] ?? null;
            if (null !== $saved) {
                $answer->status = Availability::from($saved['status']);
                $answer->details = $saved['details'];
            }
            $input->answers[$key] = $answer;
        }

        return $input;
    }
}
