<?php

declare(strict_types=1);

namespace App\Bundle\Repository;

use App\Bundle\ApiResource\ImageTask;
use App\Bundle\ApiResource\RecordTask;
use App\Bundle\Doctrine\Table\ImagesTable;
use App\Bundle\Doctrine\Table\ImageTasksTable;
use App\Bundle\Doctrine\Table\RecordsTable;
use App\Bundle\Doctrine\Table\RecordTasksTable;
use DateTimeImmutable;
use Generator;
use Mango\Doctrine\SchemaProvider;
use Mango\TaskQueue\Doctrine\Table\TaskLogsTable;
use Mango\TaskQueue\Doctrine\Table\TasksTable;
use Manyou\BingHomepage\Image;
use Manyou\BingHomepage\Record;
use Symfony\Component\Uid\Ulid;

use function array_keys;
use function array_values;

class DoctrineRepository
{
    public function __construct(private SchemaProvider $schema)
    {
    }

    public function createImage(Image $image): void
    {
        $this->schema->createQuery()
            ->insert(ImagesTable::NAME, (array) $image)
            ->executeStatement();
    }

    public function importImages(array $images): void
    {
        $this->schema->createQuery()->bulkInsert(ImagesTable::NAME, ...$images);
    }

    public function importRecords(array $records): void
    {
        $this->schema->createQuery()->bulkInsert(RecordsTable::NAME, ...$records);
    }

    public function updateImage(Image $image, bool $baseUrl = false): void
    {
        $q = $this->schema->createQuery();
        $q->update(ImagesTable::NAME, [
            'copyright' => $image->copyright,
            'downloadable' => $image->downloadable,
        ] + ($baseUrl ? ['urlbase' => $image->urlbase] : []))
            ->where(name: $image->name)
            ->executeStatement();
    }

    public function getRecordsByImageId(string $id): array
    {
        $q = $this->schema->createQuery();
        $q->from(RecordsTable::NAME)
            ->select('title', 'market', 'date', 'keyword')
            ->where(image_id: $id)
            ->orderBy('date');

        return $q->fetchAllAssociativeFlat();
    }

    public function createRecord(Record $record): void
    {
        $this->schema->createQuery()
            ->insert(RecordsTable::NAME, (array) $record)
            ->executeStatement();
    }

    public function updateRecord(Record $record): void
    {
        $this->schema->createQuery()
            ->update(RecordsTable::NAME, (array) $record)
            ->where(id: $record->id, date: $record->date, market: $record->market)
            ->executeStatement(1);
    }

    public function createRecordTask(Ulid $id, Record $record): void
    {
        $this->schema->createQuery()->insert(RecordTasksTable::NAME, [
            'id' => $id,
            'date' => $record->date,
            'market' => $record->market,
        ])->executeStatement();
    }

    public function createImageTask(Ulid $id, Image $image): void
    {
        $this->schema->createQuery()->insert(ImageTasksTable::NAME, [
            'id' => $id,
            'image_id' => $image->id,
        ])->executeStatement();
    }

    public function getRecordTask(Ulid $id): ?RecordTask
    {
        $q = $this->schema->createQuery();
        $q->from(RecordTasksTable::NAME)
            ->select()
            ->where(id: $id)
            ->joinOn(TasksTable::NAME, 'id', 'id', 'status')
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociativeFlat()) {
            return null;
        }

        $q = $this->schema->createQuery();

        $logs = $q->from(TaskLogsTable::NAME)
            ->select()
            ->where(task_id: $id)
            ->setMaxResults(10)
            ->fetchAllAssociativeFlat();

        return new RecordTask(...$data, logs: $logs);
    }

    public function getImageTask(Ulid $id): ?ImageTask
    {
        $q = $this->schema->createQuery();
        $q->from(ImageTasksTable::NAME, 'id')
            ->select()
            ->where(id: $id)
            ->joinOn(TasksTable::NAME, 'id', 'id', 'status')
            ->joinOn(ImagesTable::NAME, 'id', 'image_id', 'name', 'urlbase', 'video')
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociativeFlat()) {
            return null;
        }

        $q = $this->schema->createQuery();

        $logs = $q->from(TaskLogsTable::NAME)
            ->select()
            ->where(task_id: $id)
            ->setMaxResults(10)
            ->fetchAllAssociativeFlat();

        return new ImageTask(...$data, logs: $logs);
    }

    public function getImagesByDate(DateTimeImmutable $date): array
    {
        $q = $this->schema->createQuery();

        $records = $q->from(RecordsTable::NAME, 'image_id', 'market')
            ->select()
            ->where(date: $date)
            ->fetchColumnGrouped();

        if ($records === []) {
            return [];
        }

        $imageIds = array_keys($records);

        $q = $this->schema->createQuery();

        $images = $q->from(ImagesTable::NAME, 'id', 'name', 'urlbase')
            ->select()
            ->where($q->in('id', $imageIds))
            ->fetchAllAssociativeIndexed();

        foreach ($records as $imageId => $markets) {
            $images[$imageId]['markets'] = $markets;
        }

        return array_values($images);
    }

    public function listRecordOperations(): Generator
    {
        $q = $this->schema->createQuery();
        $q->from(RecordTasksTable::NAME)
            ->select()
            ->orderBy('id', 'DESC')
            ->joinOn(TasksTable::NAME, 'id', 'id', 'status')
            ->setMaxResults(100);

        while ($data = $q->fetchAssociativeFlat()) {
            yield new RecordTask(...$data);
        }
    }

    public function listImageOperations(): Generator
    {
        $q = $this->schema->createQuery();
        $q->from(ImageTasksTable::NAME, 'id')
            ->select()
            ->orderBy('id', 'DESC')
            ->joinOn(TasksTable::NAME, 'id', 'id', 'status')
            ->joinOn(ImagesTable::NAME, 'id', 'image_id', 'name', 'urlbase', 'video')
            ->setMaxResults(100);

        while ($data = $q->fetchAssociativeFlat()) {
            yield new ImageTask(...$data);
        }
    }

    public function getImage(string $name): ?Image
    {
        $q = $this->schema->createQuery();

        $q->from(ImagesTable::NAME)
            ->select()
            ->where(name: $name)
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociativeFlat()) {
            return null;
        }

        return new Image(...$data);
    }

    /** @return Image[] */
    public function browse(DateTimeImmutable $cursor, DateTimeImmutable $prevCursor): array
    {
        $q = $this->schema->createQuery();

        $q->from(ImagesTable::NAME, 'name', 'urlbase')
            ->select()
            ->where($q->gt('debutOn', $cursor), $q->lte('debutOn', $prevCursor))
            ->addOrderBy('debutOn', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $q->fetchAllAssociativeFlat();
    }

    public function getImageByOperationId(Ulid $id): ?Image
    {
        $q = $this->schema->createQuery();

        $q->from(ImagesTable::NAME)
            ->select()
            ->joinOn(ImageTasksTable::NAME, 'image_id', 'id', null)
            ->where(id: $id)
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociativeFlat()) {
            return null;
        }

        return new Image(...$data);
    }

    public function getImageById(string $id): ?Image
    {
        $q = $this->schema->createQuery();

        $q->from(ImagesTable::NAME)
            ->select()
            ->where(id: $id)
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociativeFlat()) {
            return null;
        }

        return new Image(...$data);
    }

    public function getRecord(string $market, ?DateTimeImmutable $date = null): ?Record
    {
        $q = $this->schema->createQuery();

        $q->from(RecordsTable::NAME, 'r')
            ->select()
            ->where(market: $market)
            ->orderBy('date', 'DESC');

        if ($date !== null) {
            $q->andWhere(date: $date);
        }

        $q->joinOn([ImagesTable::NAME, 'i'], 'id', 'image_id')
            ->setMaxResults(1);

        if (false === $data = $q->fetchAssociative()) {
            return null;
        }

        $record          = $data['r'];
        $record['image'] = new Image(...$data['i']);
        $record          = new Record(...$record);

        return $record;
    }

    /** @return array[] */
    public function exportImages(): array
    {
        return $this->schema
            ->createQuery()
            ->from(ImagesTable::NAME)
            ->select()
            ->orderBy('id')
            ->fetchAllAssociativeFlat();
    }

    /** @return array[] */
    public function exportImagesWhere(): array
    {
        $q = $this->schema
            ->createQuery();

        return $q->from(ImagesTable::NAME)
            ->select()
            ->orderBy('id')
            ->where(debutOn: new DateTimeImmutable('2022-11-20T00:00:00.000000Z'))
            ->fetchAllAssociativeFlat();
    }

    /** @return array[] */
    public function exportRecordsWhere(): array
    {
        $q = $this->schema
            ->createQuery();

        return $q->from(RecordsTable::NAME)
            ->select()
            ->orderBy('id')
            ->where(date: new DateTimeImmutable('2022-11-19T00:00:00.000000Z'))
            ->fetchAllAssociativeFlat();
    }

    /** @return array[] */
    public function exportRecords(): array
    {
        return $this->schema
            ->createQuery()
            ->from(RecordsTable::NAME)
            ->select()
            ->orderBy('id')
            ->fetchAllAssociativeFlat();
    }

    public function getMarketsPendingOrExisting(DateTimeImmutable $date): array
    {
        $recordQuery = $q = $this->schema->createQuery();
        $q->from(RecordsTable::NAME)
            ->select('market')
            ->where(date: $date);

        $taskQuery = $q = $this->schema->createQuery();
        $q->from(RecordTasksTable::NAME)
            ->select('market')
            ->where(date: $date);

        return $this->schema
            ->executeMergedQuery($recordQuery, ' UNION ', $taskQuery)
            ->fetchFirstColumn();
    }
}
