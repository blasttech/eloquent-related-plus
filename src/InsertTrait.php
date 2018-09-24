<?php

namespace App\Http\Traits;

use Blasttech\EloquentRelatedPlus\HelpersTrait;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait InsertSelect
 * @package App\Http\Traits
 *
 * @method bool insertSelect(Builder $builder, array $insertColumns = [])
 */
trait InsertTrait
{
    use HelpersTrait;

    /**
     * InsertSelect - builds SQL for 'INSERT INTO tablename (field1, field2) FROM SELECT a1, a2 FROM table2'
     *
     * @param Builder $query - the Builder to insert into
     * @param Builder $builder - the Query Builder to select from
     * @param array $insertColumns - the columns to insert into
     * @return bool
     */
    public function scopeInsertSelect(Builder $query, Builder $builder, array $insertColumns = [])
    {
        $insert = 'INSERT INTO ' . $query->getModel()->getTable();

        if (!empty($insertColumns)) {
            $insert .= ' (' . implode(', ', $insertColumns) . ')';
        }

        $insert .= ' ' . $this->toSqlWithBindings($builder);

        return $query->getConnection()->statement($insert);
    }
}
