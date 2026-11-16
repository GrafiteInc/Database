<?php

namespace Grafite\Database\Tree;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Walk an adjacency-list tree (a self-referencing parent column) in a single
 * query using a recursive CTE.
 *
 * Requires recursive CTE support: MySQL 8+, MariaDB 10.2+, Postgres, SQLite 3.8.3+.
 *
 * The recursion reads the raw table, so global scopes (e.g. soft deletes) do
 * not stop the walk; they only filter the outer results.
 */
class TreeQuery
{
    public const DESCENDANTS = 'descendants';

    public const ANCESTORS = 'ancestors';

    /**
     * Hard cap on recursion when no depth is given. Guards against cyclic
     * data and stays under MySQL's default cte_max_recursion_depth (1000).
     */
    public const MAX_DEPTH = 500;

    public static function apply(
        Builder $query,
        $nodes,
        string $direction,
        string $parentColumn = 'parent_id',
        ?int $depth = null,
        bool $includeSelf = false,
        bool $ordered = false
    ): Builder {
        if (! in_array($direction, [self::DESCENDANTS, self::ANCESTORS], true)) {
            throw new InvalidArgumentException("Unknown tree direction [{$direction}].");
        }

        $ids = self::normalizeNodes($nodes);

        if (empty($ids)) {
            return $query->whereRaw('0 = 1');
        }

        $model = $query->getModel();
        $grammar = $query->getQuery()->getGrammar();

        [$cte, $bindings] = self::buildCte($model, $grammar, $ids, $direction, $parentColumn, $depth ?? self::MAX_DEPTH);

        $selfFilter = $includeSelf ? '' : ' WHERE node_depth > 0';
        $qualifiedKey = $model->getQualifiedKeyName();

        if (! $ordered) {
            return $query->whereRaw(
                $grammar->wrap($qualifiedKey) . " IN ({$cte} SELECT node_id FROM grafite_tree{$selfFilter})",
                $bindings
            );
        }

        // Ordered: join the CTE as a derived table so we can expose and sort
        // by depth. MIN/GROUP BY dedupes nodes reachable from several seeds.
        $alias = 'grafite_tree_' . Str::lower(Str::random(8));
        $derived = "({$cte} SELECT node_id, MIN(node_depth) AS tree_depth FROM grafite_tree{$selfFilter} GROUP BY node_id) as {$alias}";

        if (empty($query->getQuery()->columns)) {
            $query->select($model->getTable() . '.*');
        }

        $query->join(new Expression($derived), $qualifiedKey, '=', new Expression("{$alias}.node_id"))
            ->addBinding($bindings, 'join')
            ->addSelect(new Expression("{$alias}.tree_depth"))
            // Top-down either way: descendants nearest-first, ancestors root-first.
            ->orderBy(new Expression("{$alias}.tree_depth"), $direction === self::ANCESTORS ? 'desc' : 'asc');

        return $query;
    }

    /**
     * Normalize a model, key, or iterable of either into unique, non-null keys.
     */
    protected static function normalizeNodes($nodes): array
    {
        if ($nodes instanceof Model || ! is_iterable($nodes)) {
            $nodes = [$nodes];
        }

        return collect($nodes)
            ->map(fn ($node) => $node instanceof Model ? $node->getKey() : $node)
            ->reject(fn ($id) => is_null($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build the "WITH RECURSIVE grafite_tree ..." prefix and its bindings.
     * Depth 0 is the seed nodes themselves.
     */
    protected static function buildCte(Model $model, $grammar, array $ids, string $direction, string $parentColumn, int $maxDepth): array
    {
        $table = $model->getTable();
        $wrappedTable = $grammar->wrapTable($table);
        $key = $grammar->wrap("{$table}.{$model->getKeyName()}");
        $parent = $grammar->wrap("{$table}.{$parentColumn}");

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $join = $direction === self::DESCENDANTS
            ? "{$parent} = grafite_tree.node_id"
            : "{$key} = grafite_tree.node_parent";

        $sql = 'WITH RECURSIVE grafite_tree (node_id, node_parent, node_depth) AS ('
            . "SELECT {$key}, {$parent}, 0 FROM {$wrappedTable} WHERE {$key} IN ({$placeholders})"
            . ' UNION ALL '
            . "SELECT {$key}, {$parent}, grafite_tree.node_depth + 1 FROM {$wrappedTable}"
            . " INNER JOIN grafite_tree ON {$join}"
            . ' WHERE grafite_tree.node_depth < ?'
            . ')';

        return [$sql, array_merge($ids, [max(0, $maxDepth)])];
    }
}
