import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
export const categories = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: categories.url(options),
    method: 'get',
})

categories.definition = {
    methods: ["get","head"],
    url: '/api/v1/report-categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
categories.url = (options?: RouteQueryOptions) => {
    return categories.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
categories.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: categories.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
categories.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: categories.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
    const categoriesForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: categories.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
        categoriesForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: categories.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\ReportController::categories
 * @see app/Http/Controllers/Api/V1/ReportController.php:86
 * @route '/api/v1/report-categories'
 */
        categoriesForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: categories.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    categories.form = categoriesForm
/**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/reports',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\ReportController::index
 * @see app/Http/Controllers/Api/V1/ReportController.php:20
 * @route '/api/v1/reports'
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
* @see \App\Http\Controllers\Api\V1\ReportController::store
 * @see app/Http/Controllers/Api/V1/ReportController.php:38
 * @route '/api/v1/reports'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/v1/reports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\ReportController::store
 * @see app/Http/Controllers/Api/V1/ReportController.php:38
 * @route '/api/v1/reports'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ReportController::store
 * @see app/Http/Controllers/Api/V1/ReportController.php:38
 * @route '/api/v1/reports'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\V1\ReportController::store
 * @see app/Http/Controllers/Api/V1/ReportController.php:38
 * @route '/api/v1/reports'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\ReportController::store
 * @see app/Http/Controllers/Api/V1/ReportController.php:38
 * @route '/api/v1/reports'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
export const show = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/v1/reports/{report}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
show.url = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return show.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
show.get = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
show.head = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
    const showForm = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
        showForm.get = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\ReportController::show
 * @see app/Http/Controllers/Api/V1/ReportController.php:72
 * @route '/api/v1/reports/{report}'
 */
        showForm.head = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Api\V1\ReportController::cancel
 * @see app/Http/Controllers/Api/V1/ReportController.php:94
 * @route '/api/v1/reports/{report}/cancel'
 */
export const cancel = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/api/v1/reports/{report}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\V1\ReportController::cancel
 * @see app/Http/Controllers/Api/V1/ReportController.php:94
 * @route '/api/v1/reports/{report}/cancel'
 */
cancel.url = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return cancel.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\ReportController::cancel
 * @see app/Http/Controllers/Api/V1/ReportController.php:94
 * @route '/api/v1/reports/{report}/cancel'
 */
cancel.post = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Api\V1\ReportController::cancel
 * @see app/Http/Controllers/Api/V1/ReportController.php:94
 * @route '/api/v1/reports/{report}/cancel'
 */
    const cancelForm = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\ReportController::cancel
 * @see app/Http/Controllers/Api/V1/ReportController.php:94
 * @route '/api/v1/reports/{report}/cancel'
 */
        cancelForm.post = (args: { report: string | number | { id: string | number } } | [report: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, options),
            method: 'post',
        })
    
    cancel.form = cancelForm
const ReportController = { categories, index, store, show, cancel }

export default ReportController