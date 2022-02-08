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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
