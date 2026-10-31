<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Dto\Document\DocumentInput;
use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\User;
use App\Enum\DocumentSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class DocumentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentStorage $storage,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(Band $band, User $user, DocumentInput $input, DocumentSource $source): BandDocument
    {
        if (0 !== count($this->validator->validate($input, groups: ['Default', $source->value]))) {
            throw new \DomainException('Vérifie le titre, le fichier ou le lien du document.');
        }
        if (null !== $input->concert && $input->concert->getBand() !== $band) {
            throw new \DomainException('Ce concert n’appartient pas à ce groupe.');
        }

        return $this->storage->exclusively(function () use ($band, $user, $input, $source): BandDocument {
            assert(null !== $input->category);
            $key = $filename = null;
            $size = 0;
            if (DocumentSource::Pdf === $source) {
                assert(null !== $input->file);
                $size = $input->file->getSize();
                $originalName = basename(str_replace('\\', '/', $input->file->getClientOriginalName()));
                $filename = mb_substr(
                    preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?? 'document.pdf',
                    0,
                    255,
                );
                $key = $this->storage->store($band, $input->file);
            }
            $description = trim($input->description ?? '');
            $document = new BandDocument(
                $band,
                $input->concert,
                $user,
                trim($input->title),
                $input->category,
                '' !== $description ? $description : null,
                DocumentSource::Link === $source ? trim($input->url ?? '') : null,
                $key,
                $filename,
                $size,
            );
            try {
                $this->entityManager->persist($document);
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                if ($document->isPdf()) {
                    $this->storage->discard($document);
                }
                throw $exception;
            }

            return $document;
        });
    }

    public function remove(BandDocument $document): void
    {
        $this->storage->exclusively(function () use ($document): void {
            $path = $document->isPdf() ? $this->storage->path($document) : null;
            $staged = null;
            if (null !== $path && is_file($path)) {
                $staged = $path.'.pending-delete';
                if (!rename($path, $staged)) {
                    throw new \RuntimeException('Impossible de retirer le PDF.');
                }
            }
            try {
                $this->entityManager->remove($document);
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                if (null !== $staged) {
                    rename($staged, $path);
                }
                throw $exception;
            }
            if (null !== $staged && !unlink($staged)) {
                throw new \RuntimeException('Le document a été retiré, mais son fichier doit être nettoyé.');
            }
        });
    }
}
