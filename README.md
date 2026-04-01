# VoiceScribe Backend

Laravel REST API backend for VoiceScribe — cloud LLM summarization proxy and data sync service.

## Requirements

- PHP 8.3+
- MySQL 8.x
- Composer
- Redis (optional, for caching)

## Setup

```bash
# Clone the repository
git clone https://github.com/hemreduru/voicescribe-backend.git
cd voicescribe-backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

## API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/v1/health` | Health check | No |
| POST | `/api/v1/auth/register` | Register | No |
| POST | `/api/v1/auth/login` | Login | No |
| POST | `/api/v1/auth/logout` | Logout | Yes |

## Architecture

- **Service-Repository Pattern** with PSR-12 compliance
- **Dedicated Form Request** classes for validation
- **API Resources** for consistent JSON responses
- **Consistent Response Wrapper** via `ApiResponse` trait

## Project Structure

```
app/
├── Http/Controllers/Api/V1/   # API controllers
├── Http/Requests/Api/V1/      # Form request validation
├── Http/Resources/            # API resource transformers
├── Http/Middleware/            # Custom middleware
├── Models/                    # Eloquent models
├── Repositories/              # Repository pattern
├── Services/                  # Business logic services
├── Traits/                    # Shared traits
└── Providers/                 # Service providers
```

## License

MIT
