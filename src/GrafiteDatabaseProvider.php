<?php

namespace Grafite\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Grafite\Database\Commands\TableEmpty;
use Grafite\Database\Commands\TableStart;
use Illuminate\Database\Eloquent\Builder;
use Grafite\Database\Commands\DatabaseDrop;
use Grafite\Database\Commands\DatabaseSize;
use Grafite\Database\Commands\TableOptimize;
use Grafite\Database\Commands\DatabaseBackup;
use Grafite\Database\Commands\DatabaseCreate;
use Grafite\Database\Commands\DatabaseUpload;
use Grafite\Database\Commands\DatabaseRestore;
use Grafite\Database\Commands\DatabaseDownload;
use Illuminate\Pagination\LengthAwarePaginator;
use Grafite\Database\Commands\DatabaseBackupPurge;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class GrafiteDatabaseProvider extends ServiceProvider
{
    /**
     * Boot method.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/backup.php' => base_path('config/backup.php'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Builder Macros
        |--------------------------------------------------------------------------
        */
        Builder::macro('whereLike', function ($attributes, string $searchTerm) {
            $this->where(function (Builder $query) use ($attributes, $searchTerm) {
                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        Str::contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $searchTerm) {
                            [$relationName, $relationAttribute] = explode('.', $attribute);

                            $query->orWhereHas($relationName, function (Builder $query) use ($relationAttribute, $searchTerm) {
                                $query->where($relationAttribute, 'LIKE', "%{$searchTerm}%");
                            });
                        },
                        function (Builder $query) use ($attribute, $searchTerm) {
                            $query->orWhere($attribute, 'LIKE', "%{$searchTerm}%");
                        }
                    );
                }
            });

            return $this;
        });

        Builder::macro('whereJsonSearch', function (string $attribute, string $searchTerm) {
            return $this->whereRaw("JSON_SEARCH('$attribute', 'all', '$searchTerm')");
        });

        /*
        | Swap a huge "WHERE ... IN (...)" for a join against an in-memory
        | temporary table. Past a few thousand ids MySQL tends to abandon the
        | index on the IN clause and fall back to a scan; joining an indexed
        | Memory table keeps the lookup linear. Small sets keep using whereIn,
        | which is faster for them and avoids the temp-table overhead.
        |
        | Note: MySQL only (ENGINE=Memory) and intended for integer keys.
        */
        Builder::macro('whereInLarge', function ($column, $ids, int $threshold = 1000) {
            /** @var Builder $this */
            $ids = collect($ids)
                ->reject(fn ($id) => is_null($id))
                ->unique()
                ->values();

            if ($ids->count() <= $threshold) {
                return $this->whereIn($column, $ids->all());
            }

            $connection = $this->getConnection();
            $tempTable = 'grafite_temp_ids_' . Str::random(16);

            $connection->statement(
                "CREATE TEMPORARY TABLE `{$tempTable}` (id BIGINT PRIMARY KEY) ENGINE=Memory"
            );

            $ids->chunk(1000)->each(function ($chunk) use ($connection, $tempTable) {
                $connection->table($tempTable)->insert(
                    $chunk->map(fn ($id) => ['id' => $id])->all()
                );
            });

            // Qualify the base table's columns so the join doesn't leak the
            // temp table's id into the results when no select was set.
            if (empty($this->getQuery()->columns)) {
                $this->select($this->getModel()->getTable() . '.*');
            }

            return $this->join($tempTable, $column, '=', "{$tempTable}.id");
        });

        Builder::macro('deferredPaginate', function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
            $model = $this->newModelInstance();
            $key = $model->getKeyName();
            $table = $model->getTable();

            $paginator = $this->clone()
                // We don't need them for this query, they'll remain
                // on the query that actually gets the records.
                ->setEagerLoads([])
                // Only select the primary key, we'll get the full
                // records in a second query below.
                ->paginate($perPage, ["$table.$key"], $pageName, $page);

            // Add our values in directly using "raw," instead of adding new bindings.
            // This is basically the `whereIntegerInRaw` that Laravel uses in some
            // places, but we're not guaranteed the primary keys are integers, so
            // we can't use that. We're sure that these values are safe because
            // they came directly from the database in the first place.
            $this->query->wheres[] = [
                'type'   => 'InRaw',
                'column' => "$table.$key",
                // Get the key values from the records on the *current* page, without mutating them.
                'values'  => $paginator->getCollection()->map->getRawOriginal($key)->toArray(),
                'boolean' => 'and'
            ];

            // simplePaginate increments by one to see if there's another page. We'll
            // decrement to counteract that since it's unnecessary in our situation.
            $page = $this->simplePaginate($paginator->perPage() - 1, $columns, null, 1);

            // Create a new paginator so that we can put our full records in,
            // not the ones that we modified to select only the primary key.
            return new LengthAwarePaginator(
                $page->items(),
                $paginator->total(),
                $paginator->perPage(),
                $paginator->currentPage(),
                $paginator->getOptions()
            );
        });

        Relation::macro('deferredPaginate', function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
            if ($this instanceof HasManyThrough || $this instanceof BelongsToMany) {
                $this->query->addSelect($this->shouldSelect($columns));
            }

            return tap($this->query->deferredPaginate($perPage, $columns, $pageName, $page), function ($paginator) {
                if ($this instanceof BelongsToMany) {
                    $this->hydratePivotRelation($paginator->items());
                }
            });
        });
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        /*
        |--------------------------------------------------------------------------
        | Register the Commands
        |--------------------------------------------------------------------------
        */
        $this->commands([
            DatabaseBackup::class,
            DatabaseBackupPurge::class,
            DatabaseRestore::class,
            DatabaseCreate::class,
            DatabaseDrop::class,
            DatabaseDownload::class,
            DatabaseUpload::class,
            DatabaseSize::class,
            TableStart::class,
            TableEmpty::class,
            TableOptimize::class,
        ]);
    }
}
