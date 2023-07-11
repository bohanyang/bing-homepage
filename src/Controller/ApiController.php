<?php

declare(strict_types=1);

namespace App\Controller;

use App\Bundle\Message\RetryCollectRecord;
use App\Bundle\Message\RetryDownloadImage;
use App\Bundle\Repository\DoctrineRepository;
use Manyou\Mango\HttpKernel\AsDtoInitializer;
use Manyou\Mango\HttpKernel\DtoInitializerValueResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[AsController]
#[Route('/api')]
#[IsGranted('ROLE_STAFF')]
class ApiController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private DoctrineRepository $repository,
    ) {
    }

    #[AsDtoInitializer]
    public function initRetryRecord(Request $request): RetryCollectRecord
    {
        if (! $id = $request->attributes->get('id')) {
            throw new NotFoundHttpException();
        }

        if (! $task = $this->repository->getRecordTask(Ulid::fromString($id))) {
            throw new NotFoundHttpException();
        }

        return new RetryCollectRecord($task);
    }

    #[Route('/record-tasks/{id}/retry', methods: ['POST'])]
    public function retryRecord(
        #[MapRequestPayload(resolver: DtoInitializerValueResolver::class)]
        RetryCollectRecord $data,
    ): Response {
        $this->messageBus->dispatch($data);

        return new JsonResponse($data, Response::HTTP_ACCEPTED);
    }

    #[AsDtoInitializer]
    public function initRetryImage(Request $request): RetryDownloadImage
    {
        if (! $id = $request->attributes->get('id')) {
            throw new NotFoundHttpException();
        }

        if (! $task = $this->repository->getImageTask(Ulid::fromString($id))) {
            throw new NotFoundHttpException();
        }

        return new RetryDownloadImage($task);
    }

    #[Route('/image-tasks/{id}/retry', methods: ['POST'])]
    public function retryImage(
        #[MapRequestPayload(resolver: DtoInitializerValueResolver::class)]
        RetryDownloadImage $data,
    ): Response {
        $this->messageBus->dispatch($data);

        return new JsonResponse($data, Response::HTTP_ACCEPTED);
    }
}