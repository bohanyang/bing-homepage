<?php

declare(strict_types=1);

namespace App\Bundle\Doctrine\TableProvider;

use App\Bundle\Doctrine\Type\ObjectIdType;
use Doctrine\DBAL\Schema\Schema;
use Manyou\Mango\Doctrine\Contract\TableProvider;
use Manyou\Mango\Doctrine\Table;
use Manyou\Mango\Operation\Doctrine\TableProvider\OperationsTable;

class ImageOperationsTable implements TableProvider
{
    public const NAME = 'image_operations';

    public function __invoke(Schema $schema): Table
    {
        $table = new Table($schema, self::NAME);
        $table->addColumn('id', 'ulid');
        $table->addColumn('image_id', ObjectIdType::NAME, ObjectIdType::DEFAULT_OPTIONS);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['image_id']);
        $table->addForeignKeyConstraint(OperationsTable::NAME, ['id'], ['id']);
        $table->addForeignKeyConstraint(ImagesTable::NAME, ['image_id'], ['id']);

        return $table;
    }
}
