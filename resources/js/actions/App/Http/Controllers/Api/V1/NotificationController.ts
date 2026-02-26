import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/v1/notifications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::index
 * @see app/Http/Controllers/Api/V1/NotificationController.php:20
 * @route '/api/v1/notifications'
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
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
export const unread = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unread.url(options),
    method: 'get',
})

unread.definition = {
    methods: ["get","head"],
    url: '/api/v1/notifications/unread',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
unread.url = (options?: RouteQueryOptions) => {
    return unread.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
unread.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unread.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
unread.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: unread.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
    const unreadForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: unread.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
        unreadForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: unread.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:60
 * @route '/api/v1/notifications/unread'
 */
        unreadForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: unread.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    unread.form = unreadForm
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
export const unreadCount = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadCount.url(options),
    method: 'get',
})

unreadCount.definition = {
    methods: ["get","head"],
    url: '/api/v1/notifications/unread-count',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
unreadCount.url = (options?: RouteQueryOptions) => {
    return unreadCount.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
unreadCount.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadCount.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
unreadCount.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: unreadCount.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
    const unreadCountForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: unreadCount.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
        unreadCountForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: unreadCount.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::unreadCount
 * @see app/Http/Controllers/Api/V1/NotificationController.php:44
 * @route '/api/v1/notifications/unread-count'
 */
        unreadCountForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: unreadCount.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    unreadCount.form = unreadCountForm
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:76
 * @route '/api/v1/notifications/{notification}/read'
 */
export const markAsRead = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAsRead.url(args, options),
    method: 'patch',
})

markAsRead.definition = {
    methods: ["patch"],
    url: '/api/v1/notifications/{notification}/read',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:76
 * @route '/api/v1/notifications/{notification}/read'
 */
markAsRead.url = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { notification: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: typeof args.notification === 'object'
                ? args.notification.id
                : args.notification,
                }

    return markAsRead.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:76
 * @route '/api/v1/notifications/{notification}/read'
 */
markAsRead.patch = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAsRead.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:76
 * @route '/api/v1/notifications/{notification}/read'
 */
    const markAsReadForm = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAsRead.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:76
 * @route '/api/v1/notifications/{notification}/read'
 */
        markAsReadForm.patch = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAsRead.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAsRead.form = markAsReadForm
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsUnread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:98
 * @route '/api/v1/notifications/{notification}/unread'
 */
export const markAsUnread = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAsUnread.url(args, options),
    method: 'patch',
})

markAsUnread.definition = {
    methods: ["patch"],
    url: '/api/v1/notifications/{notification}/unread',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsUnread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:98
 * @route '/api/v1/notifications/{notification}/unread'
 */
markAsUnread.url = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { notification: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: typeof args.notification === 'object'
                ? args.notification.id
                : args.notification,
                }

    return markAsUnread.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsUnread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:98
 * @route '/api/v1/notifications/{notification}/unread'
 */
markAsUnread.patch = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAsUnread.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsUnread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:98
 * @route '/api/v1/notifications/{notification}/unread'
 */
    const markAsUnreadForm = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAsUnread.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAsUnread
 * @see app/Http/Controllers/Api/V1/NotificationController.php:98
 * @route '/api/v1/notifications/{notification}/unread'
 */
        markAsUnreadForm.patch = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAsUnread.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAsUnread.form = markAsUnreadForm
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAllAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:120
 * @route '/api/v1/notifications/read-all'
 */
export const markAllAsRead = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAllAsRead.url(options),
    method: 'patch',
})

markAllAsRead.definition = {
    methods: ["patch"],
    url: '/api/v1/notifications/read-all',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAllAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:120
 * @route '/api/v1/notifications/read-all'
 */
markAllAsRead.url = (options?: RouteQueryOptions) => {
    return markAllAsRead.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAllAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:120
 * @route '/api/v1/notifications/read-all'
 */
markAllAsRead.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: markAllAsRead.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAllAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:120
 * @route '/api/v1/notifications/read-all'
 */
    const markAllAsReadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: markAllAsRead.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::markAllAsRead
 * @see app/Http/Controllers/Api/V1/NotificationController.php:120
 * @route '/api/v1/notifications/read-all'
 */
        markAllAsReadForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: markAllAsRead.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    markAllAsRead.form = markAllAsReadForm
/**
* @see \App\Http\Controllers\Api\V1\NotificationController::destroy
 * @see app/Http/Controllers/Api/V1/NotificationController.php:137
 * @route '/api/v1/notifications/{notification}'
 */
export const destroy = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/v1/notifications/{notification}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::destroy
 * @see app/Http/Controllers/Api/V1/NotificationController.php:137
 * @route '/api/v1/notifications/{notification}'
 */
destroy.url = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { notification: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { notification: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    notification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        notification: typeof args.notification === 'object'
                ? args.notification.id
                : args.notification,
                }

    return destroy.definition.url
            .replace('{notification}', parsedArgs.notification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\V1\NotificationController::destroy
 * @see app/Http/Controllers/Api/V1/NotificationController.php:137
 * @route '/api/v1/notifications/{notification}'
 */
destroy.delete = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Api\V1\NotificationController::destroy
 * @see app/Http/Controllers/Api/V1/NotificationController.php:137
 * @route '/api/v1/notifications/{notification}'
 */
    const destroyForm = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Api\V1\NotificationController::destroy
 * @see app/Http/Controllers/Api/V1/NotificationController.php:137
 * @route '/api/v1/notifications/{notification}'
 */
        destroyForm.delete = (args: { notification: string | number | { id: string | number } } | [notification: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const NotificationController = { index, unread, unreadCount, markAsRead, markAsUnread, markAllAsRead, destroy }

export default NotificationController