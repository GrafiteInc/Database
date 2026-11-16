<?php

namespace Grafite\Database\Traits;

use Grafite\Database\Tree\TreeQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adjacency-list tree helpers for a model with a self-referencing parent column.
 *
 * Set `protected $parentColumn = 'category_id';` on the model to change the
 * column (defaults to parent_id). descendants()/ancestors() return builders,
 * not relations, so they can be chained but not eager loaded.
 */
trait HasTree
{
    public function getParentColumn(): string
    {
        return property_exists($this, 'parentColumn') ? $this->parentColumn : 'parent_id';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getParentColumn());
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getParentColumn());
    }

    public function descendants(?int $depth = null, bool $ordered = false): Builder
    {
        return $this->treeQuery(TreeQuery::DESCENDANTS, $depth, false, $ordered);
    }

    public function descendantsAndSelf(?int $depth = null, bool $ordered = false): Builder
    {
        return $this->treeQuery(TreeQuery::DESCENDANTS, $depth, true, $ordered);
    }

    /**
     * Pass ordered: true for breadcrumbs (root first).
     */
    public function ancestors(?int $depth = null, bool $ordered = false): Builder
    {
        return $this->treeQuery(TreeQuery::ANCESTORS, $depth, false, $ordered);
    }

    public function ancestorsAndSelf(?int $depth = null, bool $ordered = false): Builder
    {
        return $this->treeQuery(TreeQuery::ANCESTORS, $depth, true, $ordered);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn($this->getParentColumn()));
    }

    public function isRoot(): bool
    {
        return is_null($this->getAttribute($this->getParentColumn()));
    }

    protected function treeQuery(string $direction, ?int $depth, bool $includeSelf, bool $ordered): Builder
    {
        return TreeQuery::apply(
            static::query(),
            $this,
            $direction,
            $this->getParentColumn(),
            $depth,
            $includeSelf,
            $ordered
        );
    }
}
