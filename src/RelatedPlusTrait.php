<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait RelatedPlusTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait RelatedPlusTrait
{
    use CustomOrderTrait, HelperMethodTrait, SearchTrait;

    /**
     * Boot method for trait
     *
     */
    public static function bootRelatedPlusTrait()
    {
        static::saving(function ($model) {
            if (!empty($model->nullable)) {
                foreach ($model->attributes as $key => $value) {
                    if (isset($model->nullable[$key])) {
                        $model->{$key} = empty(trim($value)) ? null : $value;
                    }
                }
            }
        });
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
        foreach ($this->parseRelationNames($relationName) as $relation) {
            $table = $this->getRelationTables($relation);

            /** @var Model $query */
            if (empty($query->getQuery()->columns)) {
                $query->select($this->getTable() . ".*");
            }
            if ($relatedSelect) {
                $query = $this->selectRelated($query, $table);
            }
            $query->relationJoin($table, $relation, $operator, $type, $where, $direction);
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
    public function selectRelated(Builder $query, $table)
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
            $query = $this->removeGlobalScope($query, 'order');
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
     * @param null $direction
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
        $fullTableName = $this->getTableWithAlias($table);

        return $query->join($fullTableName, function (JoinClause $join) use (
            $table,
            $relation,
            $operator,
            $direction
        ) {
            // If a HasOne relation and ordered - ie join to the latest/earliest
            if (class_basename($relation) === 'HasOne' && !empty($relation->toBase()->orders)) {
                return $this->hasOneJoin($relation, $join);
            } else {
                return $this->hasManyJoin($relation, $join, $table, $operator, $direction);
            }
        }, null, null, $type, $where);
    }

    /**
     * Join a HasOne relation which is ordered
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @return JoinClause
     */
    private function hasOneJoin($relation, $join)
    {
        // Get first relation order (should only be one)
        $order = $relation->toBase()->orders[0];

        return $join->on($order['column'], $this->hasOneJoinSql($relation, $order));
    }

    /**
     * Get join sql for a HasOne relation
     *
     * @param Relation $relation
     * @param array $order
     * @return Expression
     */
    public function hasOneJoinSql($relation, $order)
    {
        // Build subquery for getting first/last record in related table
        $subQuery = $this
            ->joinOne(
                $relation->getRelated()->newQuery(),
                $relation,
                $order['column'],
                $order['direction']
            )
            ->setBindings($relation->getBindings());

        return DB::raw('(' . $this->toSqlWithBindings($subQuery) . ')');
    }

    /**
     * Adds a where for a relation's join columns and and min/max for a given column
     *
     * @param Builder $query
     * @param Relation $relation
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function joinOne($query, $relation, $column, $direction)
    {
        // Get join fields
        $joinColumns = $this->getJoinColumns($relation);

        return $this->selectMinMax(
            $query->whereColumn($joinColumns->first, '=', $joinColumns->second),
            $column,
            $direction
        );
    }

    /**
     * Adds a select for a min or max on the given column, depending on direction given
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function selectMinMax($query, $column, $direction)
    {
        $column = $this->addBackticks($column);

        /** @var Model $query */
        if ($direction == 'asc') {
            return $query->select(DB::raw('MIN(' . $column . ')'));
        } else {
            return $query->select(DB::raw('MAX(' . $column . ')'));
        }
    }

    /**
     * Join a HasMany Relation
     *
     * @param Relation $relation
     * @param JoinClause $join
     * @param \stdClass $table
     * @param string $operator
     * @param string $direction
     * @return Builder|JoinClause
     */
    protected function hasManyJoin($relation, $join, $table, $operator, $direction)
    {
        // Get relation join columns
        $joinColumns = $this->getJoinColumns($relation);

        $first = $joinColumns->first;
        $second = $joinColumns->second;
        if ($table->name !== $table->alias) {
            $first = str_replace($table->name, $table->alias, $first);
            $second = str_replace($table->name, $table->alias, $second);
        }

        $join->on($first, $operator, $second);

        // Add any where clauses from the relationship
        $join = $this->addRelatedWhereConstraints($join, $relation, $table->alias);

        if (!is_null($direction) && get_class($relation) === HasMany::class) {
            $join = $this->hasManyJoinWhere($join, $first, $relation, $table->alias, $direction);
        }

        return $join;
    }

    /**
     * Add wheres if they exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder|JoinClause $builder
     */
    protected function addRelatedWhereConstraints($builder, $relation, $table)
    {
        // Get where clauses from the relationship
        $wheres = collect($relation->toBase()->wheres)
            ->where('type', 'Basic')
            ->map(function ($where) use ($table) {
                // Add table name to column if it is absent
                return [$this->columnWithTableName($table, $where['column']), $where['operator'], $where['value']];
            })->toArray();

        if (!empty($wheres)) {
            $builder->where($wheres);
        }

        return $builder;
    }

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param JoinClause $joinClause
     * @param string $column
     * @param HasMany|Relation $relation
     * @param string $table
     * @param string $direction
     *
     * @return JoinClause
     */
    public function hasManyJoinWhere(JoinClause $joinClause, $column, $relation, $table, $direction)
    {
        return $joinClause->where(
            $column,
            function ($subQuery) use ($table, $direction, $relation, $column) {
                $subQuery = $this->joinOne(
                    $subQuery->from($table),
                    $relation,
                    $column,
                    $direction
                );

                // Add any where statements with the relationship
                $subQuery = $this->addRelatedWhereConstraints($subQuery, $relation, $table);

                // Add any order statements with the relationship
                return $this->addOrder($subQuery, $relation, $table);
            }
        );
    }

    /**
     * Add orderBy if orders exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder|JoinClause $builder
     */
    protected function addOrder($builder, $relation, $table)
    {
        /** @var Model $builder */
        if (!empty($relation->toBase()->orders)) {
            // Get where clauses from the relationship
            foreach ($relation->toBase()->orders as $order) {
                $builder->orderBy($this->columnWithTableName($table, $order['column']), $order['direction']);
            }
        }

        return $builder;
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
        $sql = $this->toSqlWithBindings($model);
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
        /** @var Model $query */
        $query->orderBy(DB::raw($column), $direction);

        if (isset($this->order_relations) && (strpos($column,
                    '.') !== false || isset($this->order_relations[$column]))) {
            $query = $this->joinRelatedTable($query, $this->getTableFromColumn($column));
        }

        return $query;
    }
}
