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