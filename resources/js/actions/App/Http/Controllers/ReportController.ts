import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/dashboard/reports',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ReportController::index
 * @see app/Http/Controllers/ReportController.php:17
 * @route '/dashboard/reports'
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
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
export const show = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/reports/{report}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
show.url = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
show.get = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
show.head = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
    const showForm = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
        showForm.get = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\ReportController::show
 * @see app/Http/Controllers/ReportController.php:71
 * @route '/dashboard/reports/{report}'
 */
        showForm.head = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\ReportController::updateStatus
 * @see app/Http/Controllers/ReportController.php:118
 * @route '/dashboard/reports/{report}/status'
 */
export const updateStatus = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateStatus.url(args, options),
    method: 'post',
})

updateStatus.definition = {
    methods: ["post"],
    url: '/dashboard/reports/{report}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ReportController::updateStatus
 * @see app/Http/Controllers/ReportController.php:118
 * @route '/dashboard/reports/{report}/status'
 */
updateStatus.url = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return updateStatus.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportController::updateStatus
 * @see app/Http/Controllers/ReportController.php:118
 * @route '/dashboard/reports/{report}/status'
 */
updateStatus.post = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateStatus.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ReportController::updateStatus
 * @see app/Http/Controllers/ReportController.php:118
 * @route '/dashboard/reports/{report}/status'
 */
    const updateStatusForm = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateStatus.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ReportController::updateStatus
 * @see app/Http/Controllers/ReportController.php:118
 * @route '/dashboard/reports/{report}/status'
 */
        updateStatusForm.post = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateStatus.url(args, options),
            method: 'post',
        })
    
    updateStatus.form = updateStatusForm
/**
* @see \App\Http\Controllers\ReportController::resolve
 * @see app/Http/Controllers/ReportController.php:132
 * @route '/dashboard/reports/{report}/resolve'
 */
export const resolve = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

resolve.definition = {
    methods: ["post"],
    url: '/dashboard/reports/{report}/resolve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ReportController::resolve
 * @see app/Http/Controllers/ReportController.php:132
 * @route '/dashboard/reports/{report}/resolve'
 */
resolve.url = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return resolve.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ReportController::resolve
 * @see app/Http/Controllers/ReportController.php:132
 * @route '/dashboard/reports/{report}/resolve'
 */
resolve.post = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resolve.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ReportController::resolve
 * @see app/Http/Controllers/ReportController.php:132
 * @route '/dashboard/reports/{report}/resolve'
 */
    const resolveForm = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ReportController::resolve
 * @see app/Http/Controllers/ReportController.php:132
 * @route '/dashboard/reports/{report}/resolve'
 */
        resolveForm.post = (args: { report: number | { id: number } } | [report: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, options),
            method: 'post',
        })
    
    resolve.form = resolveForm
const ReportController = { index, show, updateStatus, resolve }

export default ReportController