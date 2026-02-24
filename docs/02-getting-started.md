# Getting Started

Complete guide for setting up the Surprise Moi development environment.

## Prerequisites

### Required Software

1. **PHP 8.2+**
    - Windows: [Download from windows.php.net](https://windows.php.net/download/)
    - macOS: `brew install php@8.2`
    - Linux: `apt-get install php8.2-cli`

2. **Composer 2.x**
    - [getcomposer.org](https://getcomposer.org/)
    - Verify: `composer --version`

3. **Node.js 18+ & pnpm**
    - [nodejs.org](https://nodejs.org/) (LTS version)
    - Install pnpm: `npm install -g pnpm`
    - Verify: `node --version`, `pnpm --version`

4. **PostgreSQL 14+**
    - Windows: [Download PostgreSQL](https://www.postgresql.org/download/windows/)
    - macOS: `brew install postgresql@14`
    - Linux: `apt-get install postgresql-14`
    - Verify: `psql --version`

### Recommended Tools

- **Git** - Version control
- **VS Code** - Recommended editor
- **Postman** - API testing (import from `postman-collections/`)
- **TablePlus/pgAdmin** - Database GUI

### PHP Extensions Required

Ensure these extensions are enabled in `php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
extension=mbstring
extension=openssl
extension=curl
extension=fileinfo
extension=gd
extension=zip
```

## Installation

### 1. Clone Repository

```bash
git clone <repository-url> surprise_moi_backend
cd surprise_moi_backend
```

### 2. Install PHP Dependencies

```bash
composer install
```

If you encounter memory issues:

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

### 3. Install JavaScript Dependencies

```bash
pnpm install
```

### 4. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

**Windows PowerShell**:

```powershell
Copy-Item .env.example .env
```

Edit `.env` and configure:

#### Application Settings

```env
APP_NAME="Surprise Moi"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
```

#### Database

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=surprise_moi
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

#### Paystack (Payment Gateway)

```env
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
```

Get test keys from [paystack.com](https://dashboard.paystack.com/#/settings/developers)

#### Laravel Reverb (WebSockets)

```env
REVERB_APP_ID=123456
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

#### Broadcasting

```env
BROADCAST_DRIVER=reverb
QUEUE_CONNECTION=database
```

#### Google Maps (for location features)

```env
GOOGLE_MAPS_API_KEY=your_google_maps_key
```

Get API key from [Google Cloud Console](https://console.cloud.google.com/)

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Create Database

Connect to PostgreSQL:

```bash
psql -U postgres
```

Create database:

```sql
CREATE DATABASE surprise_moi;
\q
```

### 7. Run Migrations

```bash
php artisan migrate
```

For fresh database with seeders:

```bash
php artisan migrate:fresh --seed
```

### 8. Create Storage Symlink

```bash
php artisan storage:link
```

### 9. Install Reverb Tables

```bash
php artisan reverb:install
```

## Running the Application

### Development Server

Open **3 terminal windows** and run:

**Terminal 1 - Laravel Backend**:

```bash
php artisan serve
```

Backend runs at: http://localhost:8000

**Terminal 2 - Vite (Frontend Assets)**:

```bash
pnpm run dev
```

**Terminal 3 - Laravel Reverb (WebSockets)**:

```bash
php artisan reverb:start
```

WebSocket server runs at: ws://localhost:8080

### Alternative: Using Composer Scripts

```bash
composer run dev
```

This runs both the Laravel server and Vite dev server.

### Queue Worker (Optional but Recommended)

For processing jobs (emails, notifications):

**Terminal 4**:

```bash
php artisan queue:work
```

## Testing the Installation

### 1. Check Backend Health

Visit http://localhost:8000 - you should see the application homepage.

### 2. API Test

```bash
curl http://localhost:8000/api/v1/filters/categories
```

Should return JSON with product categories.

### 3. Admin Dashboard

Visit http://localhost:8000/admin

- Default credentials are created by seeders (check console output)

### 4. Run Tests

```bash
php artisan test --compact
```

All tests should pass.

## Default Seeded Data

After running `php artisan db:seed`, you'll have:

### Users

- **Super Admin**: `admin@surprisemoi.com` / `password`
- **Test Vendor**: `vendor@test.com` / `password`
- **Test Customer**: `customer@test.com` / `password`

### Sample Data

- 10 product categories
- 20 products with variants
- 15 services
- 5 shops
- Interest categories
- Personality traits
- Bespoke services

## Common Development Workflows

### Creating a New Model

```bash
php artisan make:model Product -mfsc
```

Flags:

- `-m` = migration
- `-f` = factory
- `-s` = seeder
- `-c` = controller

### Creating a Migration

```bash
php artisan make:migration create_products_table
```

Run migrations:

```bash
php artisan migrate
```

Rollback:

```bash
php artisan migrate:rollback
```

### Creating a Controller

```bash
php artisan make:controller Api/V1/ProductController --api
```

### Creating a Form Request

```bash
php artisan make:request StoreProductRequest
```

### Creating a Resource

```bash
php artisan make:resource ProductResource
```

### Creating a Service Class

```bash
php artisan make:class Services/ProductService
```

### Running Tests

All tests:

```bash
php artisan test --compact
```

Specific file:

```bash
php artisan test --compact tests/Feature/AuthTest.php
```

Filter by name:

```bash
php artisan test --compact --filter=testUserCanLogin
```

With coverage:

```bash
php artisan test --coverage
```

### Code Formatting

Format all files:

```bash
vendor/bin/pint
```

Format changed files only:

```bash
vendor/bin/pint --dirty
```

### Clearing Caches

```bash
php artisan optimize:clear
```

This clears:

- Config cache
- Route cache
- View cache
- Application cache

Individual cache commands:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Database Operations

Fresh migration with seeding:

```bash
php artisan migrate:fresh --seed
```

Seed without migrating:

```bash
php artisan db:seed
```

Specific seeder:

```bash
php artisan db:seed --class=CategorySeeder
```

### Tinker (Interactive Console)

```bash
php artisan tinker
```

Example commands in tinker:

```php
User::count();
Product::with('category')->first();
$user = User::find(1);
$user->products;
```

## Docker Setup (Alternative)

If you prefer Docker:

### Build and Start

```bash
docker-compose up -d
```

### Run Artisan Commands

```bash
docker-compose exec app php artisan migrate
```

### Access Container Shell

```bash
docker-compose exec app bash
```

## Troubleshooting

### "Class not found" Errors

```bash
composer dump-autoload
```

### Permission Issues (Linux/macOS)

```bash
chmod -R 775 storage bootstrap/cache
```

### Migration Errors

Check database connection:

```bash
php artisan db:show
```

Reset database:

```bash
php artisan migrate:fresh
```

### Frontend Not Updating

Clear Vite cache:

```bash
rm -rf node_modules/.vite
pnpm run build
```

### Port Already in Use

**Backend**:

```bash
php artisan serve --port=8001
```

**Reverb**:

```bash
php artisan reverb:start --port=8081
```

### Paystack Webhook Testing

Use [ngrok](https://ngrok.com/) to expose local server:

```bash
ngrok http 8000
```

Update `.env`:

```env
APP_URL=https://your-ngrok-url.ngrok.io
```

Set webhook URL in Paystack dashboard:

```
https://your-ngrok-url.ngrok.io/api/v1/payments/webhook
```

### WebSocket Connection Issues

Check Reverb is running:

```bash
php artisan reverb:restart
```

Verify `.env` settings match frontend configuration.

### "Vite manifest not found" Error

Build assets:

```bash
pnpm run build
```

Or ensure Vite dev server is running:

```bash
pnpm run dev
```

## IDE Setup (VS Code)

### Recommended Extensions

- PHP Intelephense
- Laravel Extension Pack
- ESLint
- Prettier
- Tailwind CSS IntelliSense
- GitLens

### Workspace Settings

Create `.vscode/settings.json`:

```json
{
    "php.validate.executablePath": "/path/to/php",
    "editor.formatOnSave": true,
    "editor.defaultFormatter": "esbenp.prettier-vscode",
    "[php]": {
        "editor.defaultFormatter": "open-southeners.laravel-pint"
    }
}
```

## Next Steps

Now that your environment is set up:

1. **Review [Architecture Overview](01-architecture.md)** to understand the codebase structure
2. **Read [Authentication & Users](03-authentication-users.md)** to understand user management
3. **Explore [E-commerce System](04-ecommerce.md)** for product/order flows
4. **Check [API Reference](09-api-reference.md)** for endpoint documentation
5. **Import Postman collection** from `postman-collections/` for API testing

## Development Best Practices

### Before Committing

1. Run tests: `php artisan test --compact`
2. Format code: `vendor/bin/pint --dirty`
3. Check for errors: Review test output

### Creating New Features

1. Create feature branch: `git checkout -b feature/your-feature`
2. Write tests first (TDD approach)
3. Implement feature
4. Run tests to verify
5. Format code with Pint
6. Commit with clear message

### Database Changes

- Always create migrations for schema changes
- Never edit old migrations (create new ones)
- Test migrations with rollback: `php artisan migrate:rollback`
- Include factories for new models

### API Development

- Follow existing controller patterns in `app/Http/Controllers/Api/V1/`
- Use Form Requests for validation
- Use API Resources for responses
- Write feature tests for all endpoints
- Document in [API Reference](09-api-reference.md)

## Getting Help

- **Documentation**: Start in `docs/` folder
- **Postman Collection**: Import `postman-collections/Surprise_Moi_Complete_API_Collection.json`
- **Laravel Docs**: [laravel.com/docs/12.x](https://laravel.com/docs/12.x)
- **Inertia Docs**: [inertiajs.com](https://inertiajs.com/)

---

You're now ready to start developing! Check the [README](README.md) for links to all documentation.
