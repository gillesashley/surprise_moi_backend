import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
export const spa = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: spa.url(args, options),
    method: 'get',
})

spa.definition = {
    methods: ["get","head"],
    url: '/marketer/{any?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
spa.url = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { any: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    any: args[0],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "any",
        ])

    const parsedArgs = {
                        any: args?.any,
                }

    return spa.definition.url
            .replace('{any?}', parsedArgs.any?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
spa.get = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: spa.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
spa.head = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: spa.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
    const spaForm = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: spa.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
        spaForm.get = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: spa.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MarketerDashboardController::spa
 * @see app/Http/Controllers/MarketerDashboardController.php:21
 * @route '/marketer/{any?}'
 */
        spaForm.head = (args?: { any?: string | number } | [any: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: spa.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    spa.form = spaForm
const marketer = {
    spa: Object.assign(spa, spa),
}

export default marketer