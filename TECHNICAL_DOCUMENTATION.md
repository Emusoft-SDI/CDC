# NATCODEV Platform - Technical Documentation

## System Architecture

### Technology Stack
- **Backend**: PHP 8.3
- **Database**: MySQL
- **Web Server**: Apache (via UniServerZ)
- **Frontend**: HTML5, CSS3, JavaScript, Font Awesome
- **Environment**: Windows-compatible development stack

### Project Structure
```
www/win/
├── academy/              # Learning Management System
├── admin/                # Administrative Panel
├── api/                  # API endpoints
├── assets/               # Static assets (CSS, JS, Images)
├── buyer/                # Buyer registration and management
├── certificates/         # Certificate management
├── config.php            # Core configuration
├── .env                  # Environment variables
├── field-agent/          # Field agent functionality
├── lib/                  # Core libraries and functions
├── market/               # Marketplace functionality
├── provider/             # Provider management
├── support/              # Support desk system
├── tcpdf/                # PDF generation library
└── ...                   # Additional modules
```

## Core Components

### 1. Configuration System
The platform uses a centralized configuration system:
- `config.php`: Main configuration file with database connection, environment handling, and utility functions
- `.env`: Environment variables for sensitive data and configuration settings

Key functions:
- `db()`: Database connection management
- `app_env()`: Environment variable retrieval
- `app_base_url()`: Base URL configuration

### 2. Authentication & Session Management
- Admin authentication: `lib/admin-layout.php`
- User authentication: `lib/auth-layout.php`
- Session security configuration in `config.php`

### 3. Database Schema Management
- Academy schema: `lib/academy/schema.php`
- Marketplace schema: `lib/marketplace.php`
- Support system schema: `lib/support.php`

### 4. Academy System (Learning Management)
Directory: `academy/`
Key files:
- `index.php`: Main academy landing page
- `catalog.php`: Course catalog
- `course.php`: Individual course view
- `lesson.php`: Lesson content delivery
- `quiz.php`: Assessment system
- `certificates.php`: Certificate management

### 5. Marketplace System (E-commerce)
Directory: `market/`
Key components:
- Seller central: `market/seller-central.php`
- Product listings and management
- Shopping cart functionality
- Payment integration

### 6. Administrative Panel
Directory: `admin/`
Features:
- User management
- Content management
- Analytics and reporting
- System configuration

## Platform Kernel

The admin platform is built on a set of additive, opt-in kernel primitives so new
capabilities can be introduced without rewriting existing pages: a generic action
registry dispatched through `POST admin/action.php`, a dual-control approval
workflow queued in `admin_action_requests` and applied via `admin_apply_approval()`,
structured audit v2 events, a DB-backed metrics cache, a durable job queue with
retry/idempotency, a search index, and a health report. Every primitive is
registered at bootstrap and falls back silently when its table or helper is
absent, so unregistered actions and legacy pages behave exactly as before. To plug
in a new feature: register an action (`admin_register_action`) or job handler
(`admin_register_job_handler`) or approval handler (`admin_register_approval_handler`)
from any loaded library, then call the matching dispatch/enqueue/apply function —
no existing page needs to change.

| Primitive | Register | Dispatch / use | Notes |
| --- | --- | --- | --- |
| Action registry | `admin_register_action(string $key, callable $handler, ?string $capability = null)` | `POST admin/action.php` → `admin_dispatch_action(PDO, string $key, array $input)` | JSON result; CSRF + capability enforced by the endpoint |
| Approval workflow | `admin_register_approval_handler(string $type, callable $handler)` | Queue row in `admin_action_requests` → `admin_apply_approval(PDO, array $request, string $decision, string $note)` | Handler signature: `fn(PDO $pdo, array $request, string $decision, string $note)`; `decision` is `approve`/`reject` |
| Audit v2 | — | `admin_audit_event(PDO, string $action, string $entityType, ?int $entityId, array $before, array $after, string $outcome, string $description)` | Adds entity/actor/request context columns to `audit_log`; falls back to `admin_audit()` |
| Metrics cache | — | `admin_metric(PDO, string $key, int $ttl, callable $compute)` / `admin_metric_invalidate(PDO, string $prefix = '')` | DB-backed TTL cache for expensive aggregates |
| Job queue | `admin_register_job_handler(string $jobType, callable $handler)` | `admin_enqueue(PDO, string $jobType, array $payload, int $delaySeconds = 0)`; worker: `php cron/admin-jobs.php` → `admin_run_jobs(PDO, int $limit)` | Retry with backoff; `admin_idempotent(PDO, string $key, callable $fn)` for exactly-once work |
| Search index | — | `admin_search_upsert(PDO, string $type, int $id, string $title, string $subtitle, string $url, string $keywords)` / `admin_search_query(PDO, string $query, int $limit)` | Global admin search backing store |
| Health report | — | `admin_health_report(PDO)` | Returns `checks` (database, queue, cron, ...) plus an overall `ok` flag |
| Page shell | — | `admin_page_start(PDO, string $title, array $opts)` / `admin_page_end()` | Renders the `nc-hub` workspace grid + topbar (`lib/layout/html_layout.php:196,418`); wrap legacy controllers with `admin_workspace_render()` (`lib/admin-workspace.php:35`) |

Require-list convention: there is no central kernel loader. Every portal page
lists its own requires at the top — the bootstrap (`config.php`), then its
workspace middleware (`admin/` → `admin_require()`/`admin_require_feature()`
from `lib/layout/auth.php`; `field-agent/` → `_field.php`;
`coordination/` → `_coordination.php`; `support/agent.php` → agent session
checks) — then feature-gates the request. New pages follow the same
top-of-file require list so the kernel primitives are available.

## Database Design

### Key Tables
1. **academy_programs**: Academic program definitions
2. **webinars**: Online training sessions
3. **academy_courses**: Course content and structure
4. **academy_lessons**: Individual lesson materials
5. **academy_quizzes**: Assessment questions
6. **marketplace_products**: Product listings
7. **support_tickets**: Customer support system

### Database Connection
```php
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', 'localhost');
    $port = app_env('DB_PORT', '3306');
    $name = app_env('DB_DATABASE', 'natcodevcom_data');
    $user = app_env('DB_USERNAME', 'natcodevcom_data');
    $pass = app_env('DB_PASSWORD', '');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
```

## Security Features

### Session Security
- HTTP-only cookies
- Secure cookies (when HTTPS is detected)
- SameSite cookie attribute
- Session regeneration mechanisms

### Input Validation
- Form validation in registration and application forms
- SQL injection prevention through prepared statements
- XSS prevention through output encoding

### Authentication
- Password hashing
- Session management
- Role-based access control

## API Endpoints

### Webhooks
Directory: `webhooks/`
- Payment processing callbacks
- SMS delivery notifications
- External service integrations

### REST-like API
Directory: `api/`
- Data exchange endpoints
- Mobile app integration points

## External Integrations

### Payment Processing
- Monnify integration: `lib/monnify.php`
- Paystack integration: `lib/paystack.php`

### SMS & Communication
- Sendchamp integration: `lib/sendchamp.php`
- Twilio integration: `lib/twilio.php`

### PDF Generation
- TCPDF library: `tcpdf/`

## Deployment Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite
- Write permissions for upload directories

### Environment Variables
Key variables in `.env`:
- `APP_ENV`: Environment (production/development)
- `APP_URL`: Base URL of the application
- `DB_*`: Database connection parameters
- `ADMIN_PASSWORD`: Admin access credentials
- `JWT_SECRET`: JWT token signing key

## Common Workflows

### User Registration
1. Role selection on `index.php`
2. Role-specific registration (academy, provider, buyer, etc.)
3. Email verification process
4. Profile completion

### Course Enrollment
1. Browse courses in `academy/catalog.php`
2. Enroll in courses
3. Access lessons in `academy/lesson.php`
4. Complete quizzes in `academy/quiz.php`
5. Receive certificates in `academy/certificates.php`

### Marketplace Purchase
1. Browse products in `market/index.php`
2. Add to cart in `market/cart.php`
3. Checkout process in `market/checkout.php`
4. Payment processing
5. Order confirmation

## Maintenance Procedures

### Database Updates
- Schema updates through `run_schema_update.php`
- Manual table modifications when needed

### Backup Procedures
- Database backup scripts in `private_backups/`
- File backup considerations for upload directories

### Log Management
- Error logs in `logs/` directory
- Application logs through built-in logging functions

## Troubleshooting Guide

### Common Issues
1. **Database Connection Failures**
   - Check MySQL service status
   - Verify credentials in `.env`
   - Confirm database existence

2. **Session Issues**
   - Check session directory permissions
   - Verify cookie settings
   - Clear browser cookies

3. **File Upload Problems**
   - Check directory permissions
   - Verify upload size limits in php.ini
   - Confirm available disk space

### Performance Optimization
- Database indexing strategies
- Query optimization
- Caching opportunities
- Asset compression

## Future Enhancement Opportunities

### Scalability Improvements
- Database read/write separation
- Caching layer implementation
- Load balancing considerations

### Feature Extensions
- Mobile app API expansion
- Advanced analytics dashboard
- Multi-language support
- Integration with agricultural IoT devices

This documentation provides a comprehensive overview of the NATCODEV platform's technical architecture and implementation details to assist reviewers in understanding the system's structure and functionality.