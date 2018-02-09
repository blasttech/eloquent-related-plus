<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait RelatedPlusTrait
 *
 * @property array nullable
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait RelatedPlusTrait
{
    use CustomOrderTrait, JoinsTrait, SearchTrait;

    /**
     * Boot method for trait
     *
     */
    public static function bootRelatedPlusTrait()
    {
        static::saving(function ($model) {
            if (!empty($model->nullable)) {
                /* @var \Illuminate\Database\Eloquent\Model|RelatedPlusTrait|static $model */
                $model->setAttributesNull();
            }
        });
    }

    /**
     * Set empty fields to null
     */
    protected function setAttributesNull()
    {
        /** @var Model $this */
        foreach ($this->attributes as $key => $value) {
            if (isset($this->nullable[$key])) {
                $this->{$key} = empty(trim($value)) ? null : $value;
            }
        }
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    abstract public function getTable();

    /**
     * Add joins for one or more relations
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     * Usages:
     * $query->modelJoin('customers')
     * $query->modelJoin('customer.client')
     *
     * @param Builder $query
     * @param string $relationName
     * @param string $operator
     * @param string $type
     * @param bool $where
     * @param bool $relatedSelect
     * @param string|null $direction
     *
     * @return Builder
     */
    public function scopeModelJoin(
        Builder $query,
        $relationName,
        $operator = '=',
        $type = 'left',
        $where = false,
        $relatedSelect = true,
        $direction = null
    ) {
        foreach (RelatedPlusHelpers::parseRelationNames($this->getModel(), $relationName) as $relation) {
            $table = RelatedPlusHelpers::getRelationTables($relation);

            // Add selects
            $query = $this->modelJoinSelects($query, $table, $relatedSelect);

            $query->relationJoin($table, $relation, $operator, $type, $where, $direction);
        }

        return $query;
    }

    /**
     * Add selects for model join
     *
     * @param Builder $query
     * @param \stdClass $table
     * @param bool $relatedSelect
     * @return mixed
     */
    protected function modelJoinSelects($query, $table, $relatedSelect)
    {
        if (empty($query->getQuery()->columns)) {
            $query->select($this->getTable() . ".*");
        }
        if ($relatedSelect) {
            $query = $this->selectRelated($query, $table);
        }

        return $query;
    }

    /**
     * Add select for related table fields
     *
     * @param Builder $query
     * @param \stdClass $table
     * @return Builder
     */
    protected function selectRelated(Builder $query, $table)
    {
        $connection = $this->connection;

        foreach (Schema::connection($connection)->getColumnListing($table->name) as $relatedColumn) {
            $query->addSelect(
                new Expression("`$table->alias`.`$relatedColumn` AS `$table->alias.$relatedColumn`")
            );
        }

        return $query;
    }

    /**
     * Set the order of a model
     *
     * @param Builder $query
     * @param string $orderField
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCustom(Builder $query, $orderField, $direction)
    {
        if ($this->hasOrderFieldsAndDefaults($orderField, $direction)) {
            $query = RelatedPlusHelpers::removeGlobalScope($this->getModel(), $query, 'order');
        }

        return $query->setCustomOrder($orderField, $direction);
    }

    /**
     * Use a model method to add columns or joins if in the order options
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    public function scopeOrderByWith(Builder $query, $order)
    {
        if (isset($this->order_with[$order])) {
            $query = $this->addOrderWith($query, $order);
        }

        if (isset($this->order_fields[$order])) {
            $query = $this->addOrderJoin($query, $order);
        }

        return $query;
    }

    /**
     * Execute a scope in the order_width settings
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    protected function addOrderWith(Builder $query, $order)
    {
        $with = 'with' . $this->order_with[$order];

        return $query->$with();
    }

    /**
     * Add join from order_fields
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    protected function addOrderJoin(Builder $query, $order)
    {
        $orderOption = (explode('.', $this->order_fields[$order]))[0];

        if (isset($this->order_relations[$orderOption])) {
            $query->modelJoin(
                $this->order_relations[$orderOption],
                '=',
                'left',
                false,
                false
            );
        }

        return $query;
    }

    /**
     * Join a model
     *
     * @param Builder $query
     * @param \stdClass $table
     * @param Relation $relation
     * @param string $operator
     * @param string $type
     * @param boolean $where
     * @param string $direction
     * @return Builder
     */
    public function scopeRelationJoin(
        Builder $query,
        $table,
        $relation,
        $operator,
        $type,
        $where,
        $direction = null
    ) {
        $fullTableName = RelatedPlusHelpers::getTableWithAlias($table);

        return $query->join($fullTableName, function (JoinClause $join) use (
            $table,
            $relation,
            $operator,
            $direction
        ) {
            return $this->getRelationJoin($relation, $join, $table, $operator, $direction);
        }, null, null, $type, $where);
    }

    /**
     * Add where statements for the model search fields
     *
     * @param Builder $query
     * @param string $searchText
     * @return Builder
     */
    public function scopeSearch(Builder $query, $searchText = '')
    {
        $searchText = trim($searchText);

        // If search is set
        if ($searchText != "" && $this->hasSearchFields()) {
            $query = $this->checkSearchFields($query, $searchText);
        }

        return $query;
    }

    /**
     * Switch a query to be a subquery of a model
     *
     * @param Builder $query
     * @param Builder $model
     * @return Builder
     */
    public function scopeSetSubquery(Builder $query, $model)
    {
        $sql = RelatedPlusHelpers::toSqlWithBindings($model);
        $table = $model->getQuery()->from;

        return $query
            ->from(DB::raw("({$sql}) as " . $table))
            ->select($table . '.*');
    }

    /**
     * Set the model order
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeSetCustomOrder(Builder $query, $column, $direction)
    {
        if (isset($this->order_defaults)) {
            $column = $this->setOrderColumn($column);
            $direction = $this->setOrderDirection($direction);
        }

        return $this->setOrder($query, $column, $direction);
    }

    /**
     * Check if column being sorted by is from a related model
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCheckModel(Builder $query, $column, $direction)
    {
        $query->orderBy(DB::raw($column), $direction);

        if (isset($this->order_relations) && (strpos($column, '.') !== false ||
                isset($this->order_relations[$column]))) {
            $query = $this->joinRelatedTable($query, RelatedPlusHelpers::getTableFromColumn($column));
        }

        return $query;
    }
}
