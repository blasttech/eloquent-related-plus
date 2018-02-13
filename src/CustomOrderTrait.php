<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Trait CustomOrderTrait
 *
 * @property array attributes
 * @property array order_fields
 * @property array order_defaults
 * @property array order_relations
 * @property array order_with
 * @property array search_fields
 * @property string connection
 */
trait CustomOrderTrait
{
    /**
     * Check $order_fields and $order_defaults are set
     *
     * @param string $orderField
     * @param string $direction
     * @return bool
     */
    protected function hasOrderFieldsAndDefaults($orderField, $direction)
    {
        return $this->hasOrderFields() && $this->hasOrderDefaults($orderField, $direction);
    }

    /**
     * Check $this->order_fields set correctly
     *
     * @return bool
     */
    protected function hasOrderFields()
    {
        return $this->hasProperty('order_fields');
    }

    /**
     * @param string $attributeName
     * @param bool $canBeEmpty
     * @return bool
     */
    protected function hasProperty($attributeName, $canBeEmpty = true)
    {
        if (!isset($this->$attributeName) || !is_array($this->$attributeName) ||
            (!$canBeEmpty && empty($this->$attributeName))) {
            throw new InvalidArgumentException(get_class($this) . ' ' . $attributeName . ' property not set correctly.');
        }

        return true;
    }

    /**
     * Check order defaults set correctly
     *
     * @param string $orderField
     * @param string $direction
     * @return bool
     */
    protected function hasOrderDefaults($orderField, $direction)
    {
        if ($orderField === '' || $direction === '') {
            return $this->hasProperty('order_defaults');
        }

        return true;
    }

    /**
     * Check $this->search_fields set correctly
     *
     * @return bool
     */
    protected function hasSearchFields()
    {
        return $this->hasProperty('search_fields', false);
    }

    /**
     * Override column if provided column not valid. If $column not in order_fields list, use default.
     *
     * @param string $column
     * @return string
     */
    protected function setOrderColumn($column)
    {
        if ($column == '' || !isset($this->order_fields[$column])) {
            $column = $this->order_defaults['field'];
        }

        return $column;
    }

    /**
     * Override direction if provided direction not valid. If $direction not asc or desc, use default.
     *
     * @param string $direction
     * @return string
     */
    protected function setOrderDirection($direction)
    {
        if ($direction == '' || !in_array(strtoupper($direction), ['ASC', 'DESC'])) {
            $direction = $this->order_defaults['dir'];
        }

        return $direction;
    }

    /**
     * Set order based on order_fields
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function setOrder($query, $column, $direction)
    {
        if (is_array($this->order_fields[$column])) {
            return $this->setOrders($query, $column, $direction);
        }

        return $query->orderByCheckModel($this->order_fields[$column], $direction);
    }

    /**
     * Set order based on multiple order_fields
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function setOrders($query, $column, $direction)
    {
        foreach ($this->order_fields[$column] as $dbField) {
            $query->orderByCheckModel($dbField, $direction);
        }

        return $query;
    }

    /**
     * Join a related table if not already joined
     *
     * @param Builder $query
     * @param string $table
     * @return Builder
     */
    protected function joinRelatedTable($query, $table)
    {
        if (isset($this->order_relations[$table]) &&
            !$this->hasJoin($query, $table, $this->order_relations[$table])) {
            $columnRelations = $this->order_relations[$table];

            $query->modelJoin($columnRelations, '=', 'left', false, false);
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
        if (!$this->isJoinedToTable($builder, $table)) {
            return $this->isEagerLoaded($builder, $relation);
        }

        return true;
    }

    /**
     * Check if model is currently joined to $table
     *
     * @param Builder $builder
     * @param string $table
     * @return bool
     */
    protected function isJoinedToTable(Builder $builder, $table)
    {
        $joins = $builder->getQuery()->joins;
        if (!empty($joins)) {
            foreach ($joins as $joinClause) {
                if ($joinClause->table == $table) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if relation exists in eager loads
     *
     * @param Builder $builder
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @return bool
     */
    protected function isEagerLoaded(Builder $builder, $relation)
    {
        $eagerLoads = $builder->getEagerLoads();

        return !is_null($eagerLoads) && in_array($relation, $eagerLoads);
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
}
