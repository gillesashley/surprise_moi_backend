Tool Usage
Always use auggie context engine for codebase search (mandatory).

<laravel-boost-guidelines>

Conventions
You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.

Use descriptive names for variables and methods. For example, isRegisteredForDiscounts, not discount().

Check for existing components to reuse before writing a new one.

Verification Scripts
Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

Application Structure & Architecture
Stick to existing directory structure; don't create new base folders without approval.

Do not change the application's dependencies without approval.

Frontend Bundling
If the user doesn't see a frontend change reflected in the UI, it could mean they need to run pnpm run build, pnpm run dev, or composer run dev. Ask them.

Replies
Be concise in your explanations - focus on what's important rather than explaining obvious details.

Documentation Files
You must only create documentation files if explicitly requested by the user.

=== boost rules ===

Laravel Boost
Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

Artisan
Use the list-artisan-commands tool when you need to call an Artisan command to double-check the available parameters.

URLs
Whenever you share a project URL with the user, you should use the get-absolute-url tool to ensure you're using the correct scheme, domain/IP, and port.

Tinker / Debugging
You should use the tinker tool when you need to execute PHP to debug code or query Eloquent models directly.

Use the database-query tool when you only need to read from the database.

Reading Browser Logs With the browser-logs Tool
You can read browser logs, errors, and exceptions using the browser-logs tool from Boost.

Only recent browser logs will be useful - ignore old logs.

Searching Documentation (Critically Important)
Boost comes with a powerful search-docs tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API.

The search-docs tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Pest, Nova, Nightwatch, etc.

You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.

Search the documentation before making code changes to ensure we are taking the correct approach.

Available Search Syntax
You can and should pass multiple queries at once. The most relevant results will be returned first.

Simple Word Searches with auto-stemming.

Multiple Words (AND Logic).

Quoted Phrases (Exact Position).

Mixed Queries.

Multiple Queries - queries=["authentication", "middleware"].

=== php rules ===

PHP
Always use curly braces for control structures, even if it has one line.

Constructors
Use PHP 8 constructor property promotion in __construct().

Do not allow empty __construct() methods with zero parameters unless the constructor is private.

Type Declarations
Always use explicit return type declarations for methods and functions.

Use appropriate PHP type hints for method parameters.

Comments
Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

PHPDoc Blocks
Add useful array shape type definitions for arrays when appropriate.

Enums
Typically, keys in an Enum should be TitleCase. For example: FavoritePerson, BestLake, Monthly.

=== tests rules ===

Test Enforcement
Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.

Run the minimum number of tests needed to ensure code quality and speed. Use php artisan test --compact with a specific filename or filter.

=== inertia-laravel/core rules ===

Inertia
Inertia.js components should be placed in the resources/js/Pages directory unless specified differently in the JS bundler.

Use Inertia::render() for server-side routing instead of traditional Blade views.

=== inertia-laravel/v2 rules ===

Inertia v2
Make use of all Inertia features from v1 and v2.

New Features: Deferred props, Infinite scrolling using merging props, Lazy loading data on scroll, Polling, and Prefetching.

Deferred Props: Add a nice empty state with pulsing/animated skeleton when using deferred props.

=== laravel/core rules ===

Do Things the Laravel Way
Use php artisan make: commands to create new files. Pass --no-interaction to ensure they work without user input.

Database
Always use proper Eloquent relationship methods with return type hints.

Avoid DB::; prefer Model::query(). Leverage Laravel's ORM capabilities.

Prevent N+1 query problems by using eager loading.

APIs & Eloquent Resources
For APIs, default to using Eloquent API Resources and API versioning.

Controllers & Validation
Always create Form Request classes for validation rather than inline validation in controllers.

Queues
Use queued jobs for time-consuming operations with the ShouldQueue interface.

Testing
When creating models for tests, use the factories. Follow existing conventions whether to use $this->faker or fake().

=== laravel/v12 rules ===

Laravel 12
Middleware are configured declaratively in bootstrap/app.php.

bootstrap/providers.php contains application-specific service providers.

Console commands in app/Console/Commands/ are automatically available.

When modifying a column in a migration, include all previously defined attributes to prevent loss.

=== wayfinder/core rules ===

Laravel Wayfinder
Wayfinder generates TypeScript functions and types for Laravel controllers and routes.

Always use named imports for tree-shaking.

Run php artisan wayfinder:generate after route changes.

Use .form() with --with-form flag for HTML form attributes.

=== pint/core rules ===

Laravel Pint Code Formatter
You must run vendor/bin/pint --dirty --format agent before finalizing changes to ensure your code matches the project's expected style.

=== phpunit/core rules ===

PHPUnit
All tests must be written as PHPUnit classes. Use php artisan make:test --phpunit {name}.

If you see a test using "Pest", convert it to PHPUnit.

Tests should cover happy paths, failure paths, and edge cases.

=== inertia-react/core rules ===

Inertia + React
Use router.visit() or <Link> for navigation instead of traditional links.

=== inertia-react/v2/forms rules ===

Inertia v2 + React Forms
The recommended way to build forms is with the <Form> component.

</laravel-boost-guidelines>
