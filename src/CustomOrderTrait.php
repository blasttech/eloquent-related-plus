<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Trait CustomOrderTrait
 *
 * @property array attributes
 * @property array orderFields
 * @property array orderDefaults
 * @property array orderRelations
 * @property array orderWith
 * @property array searchFields
 * @property string connection
 */
trait CustomOrderTrait
{
    /**
     * Check $orderFields and $orderDefaults are set
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
     * Check $this->orderFields set correctly
     *
     * @return bool
     */
    protected function hasOrderFields()
    {
        return $this->hasProperty('orderFields');
    }

    /**
     * Check if array property exists
     *
     * @param string $attributeName
     * @param bool $canBeEmpty
     * @return bool
     */
    protected function hasProperty($attributeName, $canBeEmpty = true)
    {
        if (!$this->isValidProperty($attributeName, $canBeEmpty)) {
            throw new InvalidArgumentException(
                get_class($this) . ' ' . $attributeName . ' property not set correctly.'
            );
        }

        return true;
    }

    /**
     * Check if property exists and is array
     *
     * @param $attributeName
     * @param bool $canBeEmpty
     * @return bool
     */
    protected function isValidProperty($attributeName, $canBeEmpty = true)
    {
        return isset($this->$attributeName) && is_array($this->$attributeName)
            && ($canBeEmpty || !empty($this->$attributeName));
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
            return $this->hasProperty('orderDefaults');
        }

        return true;
    }

    /**
     * Check $this->searchFields set correctly
     *
     * @return bool
     */
    protected function hasSearchFields()
    {
        return $this->hasProperty('searchFields', false);
    }

    /**
     * Override column if provided column not valid. If $column not in orderFields list, use default.
     *
     * @param string $column
     * @return string
     */
    protected function setOrderColumn($column)
    {
        if ($column == '' || !isset($this->orderFields[$column])) {
            $column = $this->orderDefaults['field'];
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
            $direction = $this->orderDefaults['dir'];
        }

        return $direction;
    }

    /**
     * Set order based on orderFields
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function setOrder($query, $column, $direction)
    {
        if (is_array($this->orderFields[$column])) {
            return $this->setOrders($query, $column, $direction);
        }

        return $query->orderByCheckModel($this->orderFields[$column], $direction);
    }

    /**
     * Set order based on multiple orderFields
     *
     * @param Builder $query
     * @param string $column
     * @param string $direction
     * @return Builder
     */
    protected function setOrders($query, $column, $direction)
    {
        foreach ($this->orderFields[$column] as $dbField) {
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
        if (isset($this->orderRelations[$table]) &&
            !$this->hasJoin($query, $table, $this->orderRelations[$table])) {
            $columnRelations = $this->orderRelations[$table];

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
        $with = 'with' . $this->orderWith[$order];

        return $query->$with();
    }

    /**
     * Add join from orderFields
     *
     * @param Builder $query
     * @param string $order
     * @return Builder
     */
    protected function addOrderJoin(Builder $query, $order)
    {
        $orderOption = (explode('.', $this->orderFields[$order]))[0];

        if (isset($this->orderRelations[$orderOption])) {
            $query->modelJoin(
                $this->orderRelations[$orderOption],
                '=',
                'left',
                false,
                false
            );
        }

        return $query;
    }
}
