<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Trait CustomOrderTrait
 *
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
        if (!isset($this->order_fields) || !is_array($this->order_fields)) {
            throw new InvalidArgumentException(get_class($this) . ' order fields not set correctly.');
        } else {
            return true;
        }
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
        if (($orderField === '' || $direction === '')
            && (!isset($this->order_defaults) || !is_array($this->order_defaults))) {
            throw new InvalidArgumentException(get_class($this) . ' order defaults not set and not overriden.');
        } else {
            return true;
        }
    }

    /**
     * Override column if provided column not valid
     *
     * @param string $column
     * @return string
     */
    protected function setOrderColumn($column)
    {
        // If $column not in order_fields list, use default
        if ($column == '' || !isset($this->order_fields[$column])) {
            $column = $this->order_defaults['field'];
        }

        return $column;
    }

    /**
     * Override direction if provided direction not valid
     *
     * @param string $direction
     * @return string
     */
    protected function setOrderDirection($direction)
    {
        // If $direction not asc or desc, use default
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
        if (!is_array($this->order_fields[$column])) {
            $query->orderByCheckModel($this->order_fields[$column], $direction);
        } else {
            foreach ($this->order_fields[$column] as $dbField) {
                $query->orderByCheckModel($dbField, $direction);
            }
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

            $query->modelJoin(
                $columnRelations,
                '=',
                'left',
                false,
                false
            );
        }

        return $query;
    }
}
