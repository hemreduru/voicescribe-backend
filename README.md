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
| POST | `/api/v1/transcribe` | Transcribe one audio chunk (Groq Whisper relay) | Yes |

### Transcription (Groq relay)

`POST /api/v1/transcribe` takes a multipart `audio` file (wav, m4a/mp4, mp3, ogg/opus, webm, flac;
10 MB max by default) plus optional `language` (2-letter ISO, default `tr`) and `prompt` (max 500
chars), and returns `{"data": {"text": "..."}}`. The mobile app sends ~15 s chunks, one request each.

Configure it in `.env` (see `.env.example`):

- `GROQ_API_KEY` — server-side only, never sent to clients. Without it the endpoint answers `503`.
- `GROQ_STT_MODEL` — defaults to `whisper-large-v3-turbo`.
- `TRANSCRIBE_MAX_SIZE_KB` — max chunk size (default `10240`); keep it below the 16M PHP/nginx limit.
- `RATE_LIMIT_TRANSCRIBE` — requests per minute per user (default `60`).

Groq `429` is returned as `429` (with `Retry-After` when Groq sends it); other upstream failures
return a generic `502`.

## Scheduler

Laravel's scheduler must run every minute, otherwise expired Sanctum tokens are never pruned
(`sanctum:prune-expired`, scheduled daily in `routes/console.php`). On Dokploy add a cron job to
the app container:

```
* * * * * php artisan schedule:run
```

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
