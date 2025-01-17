<?php

namespace App\Providers;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AppServiceProvider extends ServiceProvider
{
    /**
     * All the container bindings that should be registered.
     */
    public array $bindings = [
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'location' => Location::class,
        ]);

        if (! App::environment('local')) {
            URL::forceScheme('https');
        }

        JsonResource::withoutWrapping();

        Collection::macro('paginate', function ($perPage = 25, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            $perPage = request()->input('per_page') ?? 10;

            if (request()->input('per_page') == '-1') {
                $perPage = $this->count() > 0 ? $this->count() : 10;
            }

            $paginator = new LengthAwarePaginator(
                $this->forPage($page, $perPage),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => Request::url(),
                    'query' => [
                        'page' => $page,
                    ],
                ]
            );

            if (is_array(reset($this->items))) {

                return [
                    'data' => $paginator->values(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                        'total' => $paginator->total(),
                    ],

                ];
            }

            return $paginator;

        });

        Builder::macro('_paginate', function () {
            if (request()->input('per_page') == '-1') {

                /** @var Builder $this */
                return $this->paginate($this->getQuery()->getCountForPagination());
            }

            $perPage = request()->input('per_page') ?? 10;

            $paginated = $this->paginate($perPage);

            return new LengthAwarePaginator(
                $paginated->items(),
                $paginated->total(),
                $paginated->perPage(),
                $paginated->currentPage(), [
                    'path' => Request::url(),
                    'query' => [
                        'page' => $paginated->currentPage(),
                    ],
                ]
            );
        });

        ResponseFactory::macro('errorMessage',
            function (string $body, string $title = 'Error', int $status = ResponseAlias::HTTP_BAD_REQUEST) {
                return Response::json([
                    'message' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ], $status);
            }
        );

        ResponseFactory::macro('accepted', function () {
            return response()->noContent(ResponseAlias::HTTP_ACCEPTED);
        });

        Http::macro('paystack', function () {
            return Http::withToken(config('paystack.secretKey'))->baseUrl(config('paystack.baseUrl'));
        });

        HeadingRowFormatter::extend('custom', function ($value, $key) {
            /**
             * We want to skip header rows and not use named headers so we just return the key here.
             */
            return $key;
        });
    }
}
