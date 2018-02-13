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
 * Static helper functions
 */
trait HelpersTrait
{
    /**
     * Return the sql for a query with the bindings replaced with the binding values
     *
     * @param Builder $builder
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
}
