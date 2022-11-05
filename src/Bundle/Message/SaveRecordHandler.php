<?php

declare(strict_types=1);

namespace App\Bundle\Message;

use App\Bundle\Message\SaveRecord\ImageConfliction;
use App\Bundle\Repository\DoctrineRepository;
use App\Bundle\Repository\LeanCloudRepository;
use ArrayObject;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use GuzzleHttp\Promise\Utils;
use Manyou\BingHomepage\Image;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SaveRecordHandler
{
    public function __construct(
        private DoctrineRepository $doctrine,
        private LeanCloudRepository $leancloud,
    ) {
    }

    public function __invoke(SaveRecord $command): void
    {
        $this->doctrine->getSchemaProvider()->getConnection()->transactional(function () use ($command) {
            // buffer LeanCloud requests
            $requests = new ArrayObject();

            $record = $command->record->with(image: $this->saveImage($command, $requests));

            $this->doctrine->createRecord($record);
            $requests[] = $this->leancloud->createRecordRequest($record);

            // commit
            Utils::unwrap($this->leancloud->getClient()->batch(...$requests));
        });
    }

    private function imageEquals(Image $a, Image $b): bool
    {
        return $a->copyright === $b->copyright
            && $a->downloadable === $b->downloadable
            && $a->name === $b->name;
    }

    private function saveImage(SaveRecord $command, ArrayObject $requests): Image
    {
        $input = $command->record->image;

        try {
            $this->doctrine->createImage($input);
            $requests[] = $this->leancloud->createImageRequest($input);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->doctrine->getImage($input->name);

            if ($command->throwIfDiffer() && ! $this->imageEquals($input, $existing)) {
                throw ImageConfliction::create($command->record, $existing, $e);
            }

            if ($command->updateExisting()) {
                $updated = $input->with(id: $existing->id);
                $this->doctrine->updateImage($updated);
                $requests[] = $this->leancloud->updateImageRequest($updated);

                return $updated;
            }

            return $existing;
        }

        return $input;
    }
}
