import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/account-deletion',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AccountDeletionController::show
 * @see app/Http/Controllers/AccountDeletionController.php:13
 * @route '/account-deletion'
 */
        showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\AccountDeletionController::submit
 * @see app/Http/Controllers/AccountDeletionController.php:18
 * @route '/account-deletion'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/account-deletion',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AccountDeletionController::submit
 * @see app/Http/Controllers/AccountDeletionController.php:18
 * @route '/account-deletion'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\AccountDeletionController::submit
 * @see app/Http/Controllers/AccountDeletionController.php:18
 * @route '/account-deletion'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AccountDeletionController::submit
 * @see app/Http/Controllers/AccountDeletionController.php:18
 * @route '/account-deletion'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AccountDeletionController::submit
 * @see app/Http/Controllers/AccountDeletionController.php:18
 * @route '/account-deletion'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
const accountDeletion = {
    show: Object.assign(show, show),
submit: Object.assign(submit, submit),
}

export default accountDeletion