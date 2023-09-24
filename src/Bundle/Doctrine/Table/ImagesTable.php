<?php

declare(strict_types=1);

namespace App\Bundle\Doctrine\Table;

use App\Bundle\Doctrine\Type\JsonTextType;
use App\Bundle\Doctrine\Type\ObjectIdType;
use Doctrine\DBAL\Types\Types;
use Mango\Doctrine\Schema\TableBuilder;
use Mango\Doctrine\Table;

class ImagesTable implements TableBuilder
{
    public const NAME = 'images';

    public function getName(): string
    {
        return self::NAME;
    }

    public function build(Table $table): void
    {
        $table->addColumn('id', ObjectIdType::NAME, ObjectIdType::DEFAULT_OPTIONS);
        $table->addColumn('name', Types::STRING, ['length' => 500]);
        $table->addColumn('debut_on', Types::DATE_IMMUTABLE);
        $table->addColumn('urlbase', Types::STRING, ['length' => 500]);
        $table->addColumn('copyright', Types::STRING, ['length' => 500]);
        $table->addColumn('downloadable', Types::BOOLEAN);
        $table->addColumn('video', JsonTextType::NAME, ['length' => 2000, 'notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name']);
        $table->addIndex(['debut_on', 'id']);
    }
}
