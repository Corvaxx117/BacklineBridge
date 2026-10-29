<?php

declare(strict_types=1);

namespace App\Service\Merch;

use App\Dto\Merch\ProductInput;
use App\Entity\{Band, User, MerchProduct, MerchVariant};
use App\Service\Finance\MusicianAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ProductCreator
{
    public function __construct(
        private EntityManagerInterface $em,
        private MusicianAccess $access,
        private ValidatorInterface $validator,
    ) {}

    public function create(Band $band, User $actor, ProductInput $input): void
    {
        $this->access->assert($actor, $band);
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            throw new \DomainException((string) $errors[0]->getMessage());
        }
        $labels = array_values(
            array_unique(
                array_filter(
                    array_map('trim', explode(',', $input->variants)),
                    static fn(string $s): bool => $s !== '',
                ),
            ),
        );
        $labels = $labels === [] ? ['Unique'] : $labels;
        if (count($labels) > 12 || array_any($labels, static fn($s): bool => mb_strlen($s) > 30)) {
            throw new \DomainException(
                'Limite les variantes à 12, avec 30 caractères maximum par libellé.',
            );
        }
        $product = new MerchProduct($band, trim($input->name), $input->category);
        foreach ($labels as $label) {
            new MerchVariant($product, $label);
        }
        $this->em->persist($product);
        $this->em->flush();
    }
}
