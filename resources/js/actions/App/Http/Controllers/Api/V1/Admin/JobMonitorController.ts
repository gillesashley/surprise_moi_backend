import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
export const stats = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})

stats.definition = {
    methods: ["get","head"],
    url: '/api/v1/admin/jobs/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
stats.url = (options?: RouteQueryOptions) => {
    return stats.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
stats.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: stats.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
stats.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: stats.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
    const statsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: stats.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
        statsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stats.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::stats
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:184
 * @route '/api/v1/admin/jobs/stats'
 */
        statsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: stats.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    stats.form = statsForm
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/admin/jobs/failed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::index
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:16
 * @route '/api/v1/admin/jobs/failed'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/admin/jobs/failed/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
    const showForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
        showForm.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::show
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:61
 * @route '/api/v1/admin/jobs/failed/{id}'
 */
        showForm.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retry
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:92
 * @route '/api/v1/admin/jobs/failed/{id}/retry'
 */
export const retry = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

retry.definition = {
    methods: ["post"],
    url: '/api/v1/admin/jobs/failed/{id}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retry
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:92
 * @route '/api/v1/admin/jobs/failed/{id}/retry'
 */
retry.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    id: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        id: args.id,
                }

    return retry.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retry
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:92
 * @route '/api/v1/admin/jobs/failed/{id}/retry'
 */
retry.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retry
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:92
 * @route '/api/v1/admin/jobs/failed/{id}/retry'
 */
    const retryForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retry.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retry
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:92
 * @route '/api/v1/admin/jobs/failed/{id}/retry'
 */
        retryForm.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retry.url(args, options),
            method: 'post',
        })
    
    retry.form = retryForm
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retryAll
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:130
 * @route '/api/v1/admin/jobs/retry-all'
 */
export const retryAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryAll.url(options),
    method: 'post',
})

retryAll.definition = {
    methods: ["post"],
    url: '/api/v1/admin/jobs/retry-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retryAll
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:130
 * @route '/api/v1/admin/jobs/retry-all'
 */
retryAll.url = (options?: RouteQueryOptions) => {
    return retryAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retryAll
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:130
 * @route '/api/v1/admin/jobs/retry-all'
 */
retryAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryAll.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retryAll
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:130
 * @route '/api/v1/admin/jobs/retry-all'
 */
    const retryAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retryAll.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::retryAll
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:130
 * @route '/api/v1/admin/jobs/retry-all'
 */
        retryAllForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retryAll.url(options),
            method: 'post',
        })
    
    retryAll.form = retryAllForm
/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::clear
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:207
 * @route '/api/v1/admin/jobs/clear'
 */
export const clear = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(options),
    method: 'delete',
})

clear.definition = {
    methods: ["delete"],
    url: '/api/v1/admin/jobs/clear',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::clear
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:207
 * @route '/api/v1/admin/jobs/clear'
 */
clear.url = (options?: RouteQueryOptions) => {
    return clear.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::clear
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:207
 * @route '/api/v1/admin/jobs/clear'
 */
clear.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: clear.url(options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::clear
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:207
 * @route '/api/v1/admin/jobs/clear'
 */
    const clearForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: clear.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\Admin\JobMonitorController::clear
 * @see app/Http/Controllers/Api/V1/Admin/JobMonitorController.php:207
 * @route '/api/v1/admin/jobs/clear'
 */
        clearForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: clear.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    clear.form = clearForm
const JobMonitorController = { stats, index, show, retry, retryAll, clear }

export default JobMonitorController