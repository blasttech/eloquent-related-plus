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
use InvalidArgumentException;

/**
 * Trait RelatedPlusTrait
 *
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 *
 * @package Blasttech\WherePlus
 */
trait RelatedPlusTrait
{
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
     * Add joins for one or more relations
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     * Usages:
     * $query->modelJoin('customers')
     * $query->modelJoin('customer.client')
     *
     * @param Builder|RelatedPlus $query
     * @param string $relation_name
     * @param string $operator
     * @param string $type
     * @param bool $where
     * @param bool $related_select
     *
     * @return Builder
     */
    public function scopeModelJoin(
        Builder $query,
        $relation_name,
        $operator = '=',
        $type = 'left',
        $where = false,
        $related_select = true
    ) {
        /** @var Builder $this */
        $connection = $this->connection;

        foreach ($this->parseRelationNames($relation_name) as $relation) {
            $table_name = $relation->getRelated()->getTable();
            // if using a 'table' AS 'table_alias' in a from statement, otherwise alias will be the table name
            $from = explode(' ', $relation->getQuery()->getQuery()->from);
            $table_alias = array_pop($from);

            if (empty($query->getQuery()->columns)) {
                /** @var Model $this */
                $query->select($this->getTable() . ".*");
            }
            if ($related_select) {
                foreach (Schema::connection($connection)->getColumnListing($table_name) as $related_column) {
                    $query->addSelect(
                        new Expression("`$table_alias`.`$related_column` AS `$table_alias.$related_column`")
                    );
                }
            }
            $query->relationJoin($table_name, $table_alias, $relation, $operator, $type, $where);
        }

        return $query;
    }

    /**
     * Get the relations from a relation name
     * $relation_name can be a single relation
     * Usage for User model:
     * parseRelationNames('customer') returns [$user->customer()]
     * parseRelationNames('customer.contact') returns [$user->customer(), $user->customer->contact()]
     *
     * @param $relation_name
     * @return Relation[]
     */
    protected function parseRelationNames($relation_name)
    {
        $relation_names = explode('.', $relation_name);
        $parent_relation_name = null;
        $relations = [];

        foreach ($relation_names as $relation_name) {
            if (is_null($parent_relation_name)) {
                $relations[] = $this->$relation_name();
                $parent_relation_name = $this->$relation_name()->getRelated();
            } else {
                $relations[] = $parent_relation_name->$relation_name();
            }
        }

        return $relations;
    }

    /**
     * Join a model
     *
     * @param Builder|RelatedPlus $query
     * @param string $table_name
     * @param string $table_alias
     * @param Relation $relation
     * @param string $operator
     * @param string $type
     * @param boolean $where
     * @return Builder
     */
    public function scopeRelationJoin(Builder $query, $table_name, $table_alias, $relation, $operator, $type, $where)
    {
        if ($table_alias !== '' && $table_name !== $table_alias) {
            $full_table_name = $table_name . ' AS ' . $table_alias;
        } else {
            $full_table_name = $table_name;
        }

        return $query->join($full_table_name, function (JoinClause $join) use (
            $table_name,
            $table_alias,
            $relation,
            $operator
        ) {
            // If a HasOne relation and ordered - ie join to the latest/earliest
            if (class_basename($relation) === 'HasOne' && !empty($relation->toBase()->orders)) {
                // Get first relation order (should only be one)
                $order = $relation->toBase()->orders[0];

                return $join->on($order['column'], $this->hasOneJoinSql($relation, $order));
            } else {
                // Get relation join columns
                $joinColumns = $this->getJoinColumns($relation);

                $first = $joinColumns->first;
                $second = $joinColumns->second;
                if ($table_name !== $table_alias) {
                    $first = str_replace($table_name, $table_alias, $first);
                    $second = str_replace($table_name, $table_alias, $second);
                }

                $join->on($first, $operator, $second);

                // Add any where clauses from the relationship
                return $this->addWhereConstraints($join, $relation, $table_alias);
            }
        }, null, null, $type, $where);
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
     * @param Builder|RelatedPlus $query
     * @param Relation $relation
     * @param string $column
     * @param string $direction
     * @return mixed
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
     * Get the join columns for a relation
     *
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @return \stdClass
     */
    protected function getJoinColumns($relation)
    {
        // Get keys with table names
        if ($relation instanceof BelongsTo) {
            $first = $relation->getOwnerKey();
            $second = $relation->getForeignKey();
        } else {
            $first = $relation->getQualifiedParentKeyName();
            $second = $relation->getQualifiedForeignKeyName();
        }

        return (object)['first' => $first, 'second' => $second];
    }

    /**
     * Adds a select for a min or max on the given column, depending on direction given
     *
     * @param Builder|RelatedPlus $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function selectMinMax($query, $column, $direction)
    {
        $column = $this->addBackticks($column);

        if ($direction == 'asc') {
            return $query->select(DB::raw('MIN(' . $column . ')'));
        } else {
            return $query->select(DB::raw('MAX(' . $column . ')'));
        }
    }

    /**
     * Add backticks to a table/column
     *
     * @param $column
     * @return string
     */
    private function addBackticks($column)
    {
        return preg_match('/^[0-9a-zA-Z\.]*$/', $column) ?
            '`' . str_replace(['`', '.'], ['', '`.`'], $column) . '`' : $column;
    }

    /**
     * Return the sql for a query with the bindings replaced with the binding values
     *
     * @param Builder $builder
     * @return string
     */
    private function toSqlWithBindings(Builder $builder)
    {
        return vsprintf($this->replacePlaceholders($builder), array_map('addslashes', $builder->getBindings()));
    }

    /**
     * Replace SQL placeholders with '%s'
     *
     * @param Builder $builder
     * @return mixed
     */
    private function replacePlaceholders(Builder $builder)
    {
        return str_replace(['?'], ['\'%s\''], $builder->toSql());
    }

    /**
     * Add wheres if they exist for a relation
     *
     * @param Builder|JoinClause $builder
     * @param Relation|BelongsTo|HasOneOrMany $relation
     * @param string $table
     * @return Builder
     */
    protected function addWhereConstraints($builder, $relation, $table)
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
     * Add table name to column name if table name not already included in column name
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    private function columnWithTableName($table, $column)
    {
        return (preg_match('/(' . $table . '\.|`' . $table . '`)/i', $column) > 0 ? '' : $table . '.') . $column;
    }

    /**
     * Set the order of a model
     *
     * @param Builder|RelatedPlus $query
     * @param string $order_field
     * @param string $dir
     * @return Builder
     */
    public function scopeOrderByCustom(Builder $query, $order_field, $dir)
    {
        if (!isset($this->order_fields) || !is_array($this->order_fields)) {
            throw new InvalidArgumentException(get_class($this) . ' order fields not set correctly.');
        }

        if (($order_field === '' || $dir === '')
            && (!isset($this->order_defaults) || !is_array($this->order_defaults))) {
            throw new InvalidArgumentException(get_class($this) . ' order defaults not set and not overriden.');
        }

        // Remove order global scope if it exists
        /** @var Model $this */
        $global_scopes = $this->getGlobalScopes();
        if (isset($global_scopes['order'])) {
            $query->withoutGlobalScope('order');
        }

        $query->setCustomOrder($order_field, $dir);

        return $query;
    }

    /**
     * Check if column being sorted by is from a related model
     *
     * @param Builder|RelatedPlus $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeOrderByCheckModel(Builder $query, $column, $direction)
    {
        $query->orderBy(DB::raw($column), $direction);

        $period_pos = strpos($column, '.');
        if (isset($this->order_relations) && ($period_pos !== false || isset($this->order_relations[$column]))) {
            $table = ($period_pos !== false ? substr($column, 0, $period_pos) : $column);

            if (isset($this->order_relations[$table]) &&
                !$this->hasJoin($query, $table, $this->order_relations[$table])) {
                $column_relations = $this->order_relations[$table];

                $query->modelJoin(
                    $column_relations,
                    '=',
                    'left',
                    false,
                    false
                );

                foreach ($this->parseRelationNames($column_relations) as $relation) {
                    if (get_class($relation) === HasMany::class) {
                        $query->hasManyJoin($column, $relation, $table, $direction);
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Check if this model has already been joined to a table or relation
     *
     * @param Builder $builder
     * @param string $table
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @return bool
     */
    protected function hasJoin(Builder $builder, $table, $relation)
    {
        $joins = $builder->getQuery()->joins;
        if (!is_null($joins)) {
            foreach ($joins as $JoinClause) {
                if ($JoinClause->table == $table) {
                    return true;
                }
            }
        }

        $eager_loads = $builder->getEagerLoads();

        return !is_null($eager_loads) && in_array($relation, $eager_loads);
    }

    /**
     * Set the model order
     *
     * @param Builder|RelatedPlus $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    public function scopeSetCustomOrder(Builder $query, $column, $direction)
    {
        if (isset($this->order_defaults)) {
            // If $column not in order_fields list, use default
            if ($column == '' || !isset($this->order_fields[$column])) {
                $column = $this->order_defaults['field'];
            }

            // If $direction not asc or desc, use default
            if ($direction == '' || !in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                $direction = $this->order_defaults['dir'];
            }
        }

        if (!is_array($this->order_fields[$column])) {
            $query->orderByCheckModel($this->order_fields[$column], $direction);
        } else {
            foreach ($this->order_fields[$column] as $db_field) {
                $query->orderByCheckModel($db_field, $direction);
            }
        }

        return $query;
    }

    /**
     * Switch a query to be a subquery of a model
     *
     * @param Builder|RelatedPlus $query
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
     * Use a model method to add columns or joins if in the order options
     *
     * @param Builder|RelatedPlus $query
     * @param string $order
     * @return Builder
     */
    public function scopeOrderByWith(Builder $query, $order)
    {
        if (isset($this->order_with[$order])) {
            $with = 'with' . $this->order_with[$order];

            $query->$with();
        }

        if (isset($this->order_fields[$order])) {
            $order_option = (explode('.', $this->order_fields[$order]))[0];

            if (isset($this->order_relations[$order_option])) {
                $query->modelJoin(
                    $this->order_relations[$order_option],
                    '=',
                    'left',
                    false,
                    false
                );
            }
        }

        return $query;
    }

    /**
     * If the relation is one-to-many, just get the first related record
     *
     * @param Builder|RelatedPlus $query
     * @param string $column
     * @param HasMany|Relation $relation
     * @param string $table
     * @param string $direction
     *
     * @return Builder
     */
    public function scopeHasManyJoin(Builder $query, $column, $relation, $table, $direction)
    {
        return $query->where(
            $column,
            function ($subQuery) use ($table, $direction, $relation, $column, $query) {
                $subQuery = $this->joinOne(
                    $subQuery->from($table),
                    $relation,
                    $column,
                    $direction
                );

                // Add any where statements with the relationship
                $subQuery = $this->addWhereConstraints($subQuery, $relation, $table);

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
     * @return Builder
     */
    protected function addOrder($builder, $relation, $table)
    {
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
     * @param Builder|RelatedPlus $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, $search = '')
    {
        $search = trim($search);

        // If search is set
        if ($search != "") {
            if (!isset($this->search_fields) || !is_array($this->search_fields) || empty($this->search_fields)) {
                throw new InvalidArgumentException(get_class($this) . ' search properties not set correctly.');
            } else {
                $search_fields = $this->search_fields;
                /** @var Model $this */
                $table = $this->getTable();
                $query->where(function (Builder $query) use ($search_fields, $table, $search) {
                    foreach ($search_fields as $search_field => $search_field_parameters) {
                        if (!isset($search_field_parameters['regex']) ||
                            preg_match($search_field_parameters['regex'], $search)) {
                            $search_column = is_array($search_field_parameters)
                                ? $search_field : $search_field_parameters;
                            $search_operator = isset($search_field_parameters['operator'])
                                ? $search_field_parameters['operator'] : 'like';
                            $search_value = isset($search_field_parameters['value'])
                                ? $search_field_parameters['value'] : '%{{search}}%';

                            if (isset($search_field_parameters['relation'])) {
                                $relation = $search_field_parameters['relation'];
                                $related_table = $this->$relation()->getRelated()->getTable();

                                $query->orWhere(function (Builder $query) use (
                                    $search,
                                    $search_column,
                                    $search_field_parameters,
                                    $relation,
                                    $related_table
                                ) {
                                    $query->orWhereHas($relation, function (Builder $query2) use (
                                        $search,
                                        $search_column,
                                        $search_field_parameters,
                                        $related_table
                                    ) {
                                        $query2->where($related_table . '.' . $search_column, 'like', $search . '%');
                                    });
                                });
                            } else {
                                $query->orWhere(
                                    $table . '.' . $search_column,
                                    $search_operator,
                                    str_replace('{{search}}', $search, $search_value)
                                );
                            }
                        }
                    }

                    return $query;
                });
            }
        }

        return $query;
    }
}
