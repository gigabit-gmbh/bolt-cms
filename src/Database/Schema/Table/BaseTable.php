<?php
namespace Bolt\Database\Schema\Table;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Base database table class for Bolt.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class BaseTable
{
    /** @var \Doctrine\DBAL\Platforms\AbstractPlatform $platform */
    protected $platform;
    /** @var \Doctrine\DBAL\Schema\Table */
    protected $table;
    /** @var string */
    protected $tableName;

    /**
     * Constructor.
     *
     * @param AbstractPlatform $platform
     */
    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * Get the table's schema object.
     *
     * @param Schema $schema
     * @param string $tableName
     *
     * @return \Doctrine\DBAL\Schema\Table
     */
    public function buildTable(Schema $schema, $tableName)
    {
        $this->table = $schema->createTable($tableName);
        $this->addColumns();
        $this->addIndexes();
        $this->setPrimaryKey();

        return $this->table;
    }

    /**
     * Add columns to the table.
     */
    abstract protected function addColumns();

    /**
     * Define the columns that require indexing.
     */
    abstract protected function addIndexes();

    /**
     * Set the table's primary key.
     */
    abstract protected function setPrimaryKey();
}