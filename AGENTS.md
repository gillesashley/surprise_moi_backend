# AGENTS.md - Project Context for AI Assistants

## Project Overview

- **Name**: Surprise Moi Backend
- **Framework**: Laravel 12.x with Inertia.js (React 19.x)
- **Frontend**: React 19.x, Tailwind CSS 4.x, TypeScript
- **Build Tool**: Vite with @laravel/vite-plugin-wayfinder
- **Package Manager**: pnpm
- **PHP**: 8.2+

## Build/Lint/Test Commands

### Frontend (Node/pnpm)

```bash
# Development
pnpm run dev                    # Start Vite dev server
pnpm run build                  # Build for production
pnpm run build:ssr              # Build with SSR support

# Code Quality
pnpm run lint                   # Run ESLint with auto-fix
pnpm run format                 # Format code with Prettier
pnpm run format:check           # Check formatting without writing
pnpm run types                  # Type-check TypeScript (tsc --noEmit)
```

### Backend (PHP/Composer)

```bash
# Testing
php artisan test                # Run all tests
php artisan test --filter=TestName        # Run single test by name
php artisan test tests/Feature/Auth/AuthenticationTest.php  # Run single file
php vendor/bin/phpunit --filter test_login_screen_can_be_rendered  # Run single test method
./vendor/bin/phpunit tests/Unit/ExampleTest.php             # Run single test file

# Code Style
./vendor/bin/pint               # Run Laravel Pint (PHP CS Fixer)
./vendor/bin/pint --test        # Check without fixing

# Development
composer run dev                # Start all dev services (concurrently)
php artisan serve               # Start Laravel dev server
php artisan wayfinder:generate --with-form    # Generate Wayfinder types
```

### Full Quality Check (run before committing)

```bash
pnpm run lint && pnpm run format:check && pnpm run types && ./vendor/bin/pint --test && php artisan test
```

## Code Style Guidelines

### TypeScript/React

**Imports**: Organized automatically by prettier-plugin-organize-imports. Order:

1. External libraries (React, Inertia, etc.)
2. Absolute imports (`@/components`, `@/lib`)
3. Relative imports
4. Type-only imports use `import { type X }` syntax

**Formatting**:

- Single quotes, semicolons required
- Tab width: 4 spaces (2 for YAML)
- Print width: 80 characters
- Tailwind classes auto-sorted by prettier-plugin-tailwindcss

**Naming**:

- Components: PascalCase (e.g., `UserProfile.tsx`)
- Hooks: camelCase with `use` prefix (e.g., `useAuth.ts`)
- Utilities: camelCase (e.g., `formatDate.ts`)
- Types/Interfaces: PascalCase with descriptive names

**Types**:

- Strict TypeScript enabled (`strict: true`)
- Use `type` for object shapes, `interface` for extendable contracts
- Avoid `any`; use `unknown` when type is uncertain
- Path alias `@/*` maps to `./resources/js/*`

**Components**:

- Use function declarations for components
- Props interface named `{ComponentName}Props`
- Prefer early returns over nested conditionals
- Use React 19+ patterns (no need to import React)

### PHP

**Code Style**: Laravel Pint with default preset (PSR-12 aligned)

- 4 spaces indentation
- Unix line endings
- Opening braces on same line
- Strict types declaration encouraged

**Naming**:

- Classes: PascalCase (e.g., `UserController`)
- Methods: camelCase (e.g., `getUserProfile`)
- Properties: camelCase (e.g., `$userName`)
- Constants: UPPER_SNAKE_CASE

**Imports**:

- Use fully qualified class names in docblocks
- Group imports by namespace
- Alphabetize imports within groups

**Controllers**:

- Single responsibility methods
- Type-hint request objects
- Return Inertia responses for views, JsonResponse for APIs

## Error Handling

**Frontend**:

- Use Inertia's error handling for form submissions
- Display errors with `<InputError>` component
- Log errors to console in development only

**Backend**:

- Use Laravel's exception handler
- Return appropriate HTTP status codes
  \*\*\* End Patch

# AGENTS.md - Project Context for AI Assistants

## Project Overview

- **Name**: Surprise Moi Backend
- **Framework**: Laravel 12.x with Inertia.js (React 19.x)
- **Frontend**: React 19.x, Tailwind CSS 4.x, TypeScript
- **Build Tool**: Vite with @laravel/vite-plugin-wayfinder
- **Package Manager**: pnpm
- **PHP**: 8.2+

## Build/Lint/Test Commands

### Frontend (Node/pnpm)

```bash
# Development
pnpm run dev                    # Start Vite dev server
pnpm run build                  # Build for production
pnpm run build:ssr              # Build with SSR support

# Code Quality
pnpm run lint                   # Run ESLint with auto-fix
pnpm run format                 # Format code with Prettier
pnpm run format:check           # Check formatting without writing
pnpm run types                  # Type-check TypeScript (tsc --noEmit)
```

### Backend (PHP/Composer)

```bash
# Testing
php artisan test                # Run all tests
php artisan test --filter=TestName        # Run single test by name
php artisan test tests/Feature/Auth/AuthenticationTest.php  # Run single file
php vendor/bin/phpunit --filter test_login_screen_can_be_rendered  # Run single test method
./vendor/bin/phpunit tests/Unit/ExampleTest.php             # Run single test file

# Code Style
./vendor/bin/pint               # Run Laravel Pint (PHP CS Fixer)
./vendor/bin/pint --test        # Check without fixing

# Development
composer run dev                # Start all dev services (concurrently)
php artisan serve               # Start Laravel dev server
php artisan wayfinder:generate --with-form    # Generate Wayfinder types
```

### Full Quality Check (run before committing)

```bash
pnpm run lint && pnpm run format:check && pnpm run types && ./vendor/bin/pint --test && php artisan test
```

## Code Style Guidelines

### TypeScript/React

**Imports**: Organized automatically by prettier-plugin-organize-imports. Order:

1. External libraries (React, Inertia, etc.)
2. Absolute imports (`@/components`, `@/lib`)
3. Relative imports
4. Type-only imports use `import { type X }` syntax

**Formatting**:

- Single quotes, semicolons required
- Tab width: 4 spaces (2 for YAML)
- Print width: 80 characters
- Tailwind classes auto-sorted by prettier-plugin-tailwindcss

**Naming**:

- Components: PascalCase (e.g., `UserProfile.tsx`)
- Hooks: camelCase with `use` prefix (e.g., `useAuth.ts`)
- Utilities: camelCase (e.g., `formatDate.ts`)
- Types/Interfaces: PascalCase with descriptive names

**Types**:

- Strict TypeScript enabled (`strict: true`)
- Use `type` for object shapes, `interface` for extendable contracts
- Avoid `any`; use `unknown` when type is uncertain
- Path alias `@/*` maps to `./resources/js/*`

**Components**:

- Use function declarations for components
- Props interface named `{ComponentName}Props`
- Prefer early returns over nested conditionals
- Use React 19+ patterns (no need to import React)

### PHP

**Code Style**: Laravel Pint with default preset (PSR-12 aligned)

- 4 spaces indentation
- Unix line endings
- Opening braces on same line
- Strict types declaration encouraged

**Naming**:

- Classes: PascalCase (e.g., `UserController`)
- Methods: camelCase (e.g., `getUserProfile`)
- Properties: camelCase (e.g., `$userName`)
- Constants: UPPER_SNAKE_CASE

**Imports**:

- Use fully qualified class names in docblocks
- Group imports by namespace
- Alphabetize imports within groups

**Controllers**:

- Single responsibility methods
- Type-hint request objects
- Return Inertia responses for views, JsonResponse for APIs

## Error Handling

**Frontend**:

- Use Inertia's error handling for form submissions
- Display errors with `<InputError>` component
- Log errors to console in development only

**Backend**:

- Use Laravel's exception handler
- Return appropriate HTTP status codes
- Validate with FormRequest classes
- Use try-catch for external API calls

## Wayfinder Critical Configuration

The project uses `@laravel/vite-plugin-wayfinder` with `formVariants: true`:

```typescript
wayfinder({
    formVariants: true,
}),
```

**CRITICAL**: Dockerfile MUST use `--with-form` flag:

```dockerfile
RUN php artisan wayfinder:generate --with-form
```

Mismatch causes: `Error generating types: Command failed: php artisan wayfinder:generate --with-form`

## File Locations

```
routes/                   # PHP route definitions
resources/js/            # Frontend source
  ├── components/        # React components
  ├── pages/            # Inertia page components
  ├── layouts/          # Layout components
  ├── hooks/            # Custom React hooks
  ├── lib/              # Utilities
  ├── routes/           # Generated Wayfinder routes
  └── actions/          # Generated Wayfinder actions
app/                     # PHP application code
  ├── Http/
  │   ├── Controllers/  # Request handlers
  │   └── Requests/     # Form request validators
  ├── Models/           # Eloquent models
  └── Services/         # Business logic
```

## Docker Build

Multi-stage build with PHP in Node stage for Wayfinder:

1. Generate Wayfinder types: `php artisan wayfinder:generate --with-form`
2. Skip Wayfinder plugin: `SKIP_WAYFINDER=true pnpm run build`

```bash
docker build -t surprise-moi-backend .
```

## Environment Variables

- `SKIP_WAYFINDER`: Set to `true` during Docker builds
- `DOCKER_USE_VITE_CONFIG`: Enables Docker-specific Vite settings
    > > > > > > > ba3dd35 (docs: add AGENTS.md project context for AI assistants)
