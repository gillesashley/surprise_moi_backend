# AGENTS.md - Project Context for AI Assistants

---

## CRITICAL: Mandatory Tool Usage

# MCP Tool Calls.

Always invoke auggie MCP on every single request (mandatory).
Invoke sequential-thinking MCP when you need to think sequentially.
Invoke context7 MCP when you need live documentation and other libraries.

---

## Project Overview

| Component       | Version | Notes                                                 |
| --------------- | ------- | ----------------------------------------------------- |
| PHP             | 8.2.29  | Constructor property promotion required               |
| Laravel         | 12.x    | New streamlined structure (no Kernel.php)             |
| Inertia.js      | v2      | Deferred props, infinite scroll, polling, prefetching |
| React           | 19.x    | No need to import React                               |
| Tailwind CSS    | 4.x     | CSS-first config, no tailwind.config.js               |
| Wayfinder       | v0.x    | TypeScript route generation with `--with-form`        |
| PHPUnit         | 11.x    | All tests must be PHPUnit (not Pest)                  |
| Package Manager | pnpm    | All frontend commands use pnpm                        |

---

## Build/Lint/Test Commands

### Frontend

```bash
pnpm run dev              # Start Vite dev server
pnpm run build            # Production build
pnpm run build:ssr        # SSR build
pnpm run lint             # ESLint (auto-fix)
pnpm run format           # Prettier
pnpm run format:check     # Check formatting
pnpm run types            # TypeScript check
```

### Backend

```bash
php artisan test                     # All tests
php artisan test --compact           # Compact output
php artisan test --filter=TestName   # Single test
./vendor/bin/phpunit tests/Feature/ExampleTest.php  # Single file
./vendor/bin/pint                    # Fix PHP style
./vendor/bin/pint --test             # Check only
php artisan wayfinder:generate --with-form  # Generate routes
```

### Pre-Commit Quality Check

```bash
pnpm run lint && pnpm run format:check && pnpm run types && ./vendor/bin/pint --test && php artisan test
```

---

## Code Style Guidelines

### TypeScript/React

**Imports** (auto-organized by prettier-plugin-organize-imports):

1. External libraries (React, Inertia, etc.)
2. Absolute imports (`@/components`, `@/lib`)
3. Relative imports
4. Type-only: `import { type X }` syntax

**Formatting**: Single quotes, semicolons required, 4-space tabs, 80-char width

**Naming**:

- Components: `PascalCase.tsx` (e.g., `UserProfile.tsx`)
- Hooks: `useCamelCase.ts` (e.g., `useAuth.ts`)
- Props interface: `{ComponentName}Props`

**Types**: Strict mode. Use `type` for shapes, `interface` for extendable contracts. Never `any`.

**Components**: Function declarations, early returns, React 19+ patterns (no React import needed)

### PHP

**Style**: Laravel Pint (PSR-12), 4-space indentation, Unix line endings

**Naming**:

- Classes: `PascalCase`
- Methods/Properties: `camelCase`
- Constants: `UPPER_SNAKE_CASE`

**Requirements**:

- Always use curly braces for control structures
- Constructor property promotion: `public function __construct(public GitHub $github) {}`
- Explicit return types on all methods
- PHPDoc blocks over inline comments
- `env()` only in config files; use `config('app.name')` elsewhere

**Controllers**: Form Request classes for validation (not inline). Check sibling Form Requests for array vs string validation rules.

---

## Laravel 12 Specifics

**Structure Changes**:

- No `app/Http/Kernel.php` - middleware in `bootstrap/app.php`
- No `app/Console/Kernel.php` - console config in `routes/console.php`
- Commands in `app/Console/Commands/` auto-registered
- Providers in `bootstrap/providers.php`

**Database**: When modifying columns, migration must include ALL previous attributes.

**Models**: Prefer `casts()` method over `$casts` property. Follow existing conventions.

**Eager Loading**: Native limit support: `$query->latest()->limit(10);`

---

## Inertia v2 + React

**Navigation**: Use `router.visit()` or `<Link>`, never traditional links.

**Form Component** (preferred approach):

```tsx
import { Form } from '@inertiajs/react';

<Form {...store.form()}>
    {({ errors, processing, wasSuccessful }) => (
        <>
            <input name="title" />
            {errors.title && <div>{errors.title}</div>}
            <button disabled={processing}>Submit</button>
        </>
    )}
</Form>;
```

**Available Form Props**: `errors`, `hasErrors`, `processing`, `wasSuccessful`, `recentlySuccessful`, `clearErrors`, `resetAndClearErrors`, `defaults`, `resetOnError`, `resetOnSuccess`, `setDefaultsOnSuccess`

**v2 Features**: Deferred props, infinite scrolling (`WhenVisible`), lazy loading on scroll, polling, prefetching

**Deferred Props**: Add skeleton/empty states with pulsing animation

---

## Wayfinder

**Critical**: Use `--with-form` flag in Dockerfile. Mismatch causes build errors.

**Imports** (tree-shakable):

```ts
import {
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/PostController';
import { show as postShow } from '@/routes/post'; // Named routes
```

**Usage**:

```ts
show(1); // { url: "/posts/1", method: "get" }
show.url(1); // "/posts/1"
show.get(1); // Explicit GET
store.form(); // { action: "/posts", method: "post" }
```

**Query Params**: `show(1, { query: { page: 1 } })` → `/posts/1?page=1`

**Merge Query**: `show(1, { mergeQuery: { page: 2, sort: null } })` - `null` removes param

---

## Tailwind CSS 4

**Configuration**: CSS-first with `@theme` directive. No `tailwind.config.js`.

```css
@import 'tailwindcss';

@theme {
    --color-brand: oklch(0.72 0.11 178);
}
```

**Replaced Utilities**:

| Deprecated          | Replacement     |
| ------------------- | --------------- |
| `bg-opacity-*`      | `bg-black/*`    |
| `flex-shrink-*`     | `shrink-*`      |
| `flex-grow-*`       | `grow-*`        |
| `overflow-ellipsis` | `text-ellipsis` |

**Dark Mode**: Use `dark:` prefix. Match existing component patterns.

**Spacing**: Use `gap-*` utilities, not margins for flex/grid children.

---

## Testing Standards

**Framework**: PHPUnit only. Convert any Pest tests to PHPUnit.

**Creating Tests**:

```bash
php artisan make:test FeatureName           # Feature test
php artisan make:test UnitName --unit       # Unit test
```

**Requirements**:

- Every change must have tests
- Test happy paths, failure paths, edge cases
- Use factories: `User::factory()->create()`
- Never remove tests without approval
- Run minimal tests with `--filter`

**Faker**: Use `$this->faker->word()` or `fake()->randomDigit()` (follow existing convention)

---

## File Locations

```
routes/                    # PHP routes
  └── console.php         # Console commands (Laravel 12)
resources/js/
  ├── components/         # Reusable React components
  ├── pages/              # Inertia page components
  ├── layouts/            # Layout components
  ├── hooks/              # Custom hooks (use*.ts)
  ├── lib/                # Utilities
  ├── routes/             # Generated Wayfinder routes
  └── actions/            # Generated Wayfinder actions
app/
  ├── Http/Controllers/   # Request handlers
  ├── Http/Requests/      # Form validation classes
  ├── Models/             # Eloquent models
  ├── Services/           # Business logic
  └── Console/Commands/   # Auto-registered commands
bootstrap/
  ├── app.php             # Middleware, exceptions, routing
  └── providers.php       # Service providers
```

---

## Docker Build

Multi-stage build requires PHP in Node stage for Wayfinder:

```dockerfile
RUN php artisan wayfinder:generate --with-form
RUN SKIP_WAYFINDER=true pnpm run build
```

```bash
docker build -t surprise-moi-backend .
```

**Environment Variables**:

- `SKIP_WAYFINDER=true` - During Docker builds
- `DOCKER_USE_VITE_CONFIG` - Docker-specific Vite settings

---

## Laravel Boost MCP Tools

| Tool                    | Purpose                                   |
| ----------------------- | ----------------------------------------- |
| `search-docs`           | Version-specific Laravel docs (USE FIRST) |
| `tinker`                | Execute PHP, debug, query models          |
| `database-query`        | Read-only SQL queries                     |
| `database-schema`       | Inspect tables, columns, indexes          |
| `browser-logs`          | Frontend JS errors/logs                   |
| `last-error`            | Backend exceptions                        |
| `list-artisan-commands` | Available Artisan commands                |
| `get-absolute-url`      | Generate proper URLs                      |
| `get-config`            | Read config values                        |

---

## Quick Reference

- **Frontend changes not showing?** → Run `pnpm run build` or `pnpm run dev`
- **Vite manifest error?** → Run `pnpm run build`
- **Wayfinder types error?** → Check `--with-form` flag consistency
- **Creating PHP class?** → Use `php artisan make:class --no-interaction`
- **Creating model?** → `php artisan make:model Name --no-interaction` + factory + seeder
- **API response?** → Use Eloquent API Resources with versioning
- **Complex query?** → Eloquent first, then Query Builder. Avoid `DB::`
- **Time-consuming operation?** → Use queued jobs with `ShouldQueue`
- **Authorization?** → Gates, Policies, Sanctum

---

## Code Principles

1. **Reuse First**: Check for existing components before creating new ones
2. **Follow Siblings**: Match patterns from neighboring files
3. **Descriptive Names**: `isRegisteredForDiscounts`, not `discount()`
4. **No New Folders**: Stick to existing directory structure
5. **No New Dependencies**: Without approval
6. **Be Concise**: Focus on what matters
7. **No Docs Files**: Unless explicitly requested
