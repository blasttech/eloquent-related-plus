<?php

namespace Blasttech\EloquentRelatedPlus;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait RelatedPlusHelpers
 *
 * Helper functions
 *
 * @property array attributes
 * @property array nullable
 * @property array orderFields
 * @property array orderDefaults
 * @property array orderRelations
 * @property array orderWith
 * @property array searchFields
 * @property string connection
 * @method array getGlobalScopes()
 *
 */
trait HelpersTrait
{
    /**
     * Return the sql for a query with the bindings replaced with the binding values
     *
     * @param Builder|\Illuminate\Database\Query\Builder $builder
     * @return string
     */
    public function toSqlWithBindings($builder)
    {
        $replacements = $builder->getBindings();
        $sql = $builder->toSql();

        foreach ($replacements as &$replacement) {
            $replacement = addslashes($replacement);
            if (!is_numeric($replacement)) {
                $replacement = '"' . $replacement . '"';
            }
        }

        return preg_replace_callback(
            '/(\?)(?=(?:[^\'"]|["\'][^\'"]*["\'])*$)/',
            function () use (&$replacements) {
                return array_shift($replacements);
            },
            $sql
        );
    }

    /**
     * Get table name from column name
     *
     * @param string $column
     * @return string
     */
    public function getTableFromColumn($column)
    {
        $periodPos = strpos($column, '.');

        return ($periodPos === false ? $column : substr($column, 0, $periodPos));
    }

    /**
     * Remove any global scopes which contain $scopeName in their name
     *
     * @param RelatedPlusTrait|Model $model
     * @param Relation|Builder $query
     * @param string $scopeName
     * @return Relation|BelongsTo|HasOneOrMany|Builder
     */
    public function removeGlobalScopes($model, $query, $scopeName)
    {
        $query->withoutGlobalScopes(collect($model->getGlobalScopes())->keys()->filter(function (
            $value
        ) use ($scopeName) {
            return stripos($value, $scopeName) !== false;
        })->toArray());

        return $query;
    }

    /**
     * Set empty fields to null
     */
    public function setAttributesNull()
    {
        /** @var Model $this */
        foreach ($this->attributes as $key => $value) {
            if (isset($this->nullable[$key])) {
                $this->{$key} = empty(trim($value)) ? null : $value;
            }
        }
    }
}
