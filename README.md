
platform for registration, verification, IoT monitoring, and marketplace access.

## 🚀 Quick Start
1. Clone repo
2. Copy `.env.example` → `.env` and fill credentials
3. Run `composer install` (if using Composer)
4. Import `database.sql` (if provided)
5. Start local server: `php -S localhost:8000 -t public`

## 🔑 Environment Variables
See `.env.example` for required keys (DB, SMS, payment gateways).

## 📁 Project Structure
- `public/` - Web entry point
- `src/` - Application logic
- `api/` - REST endpoints
- `cron/` - Scheduled tasks

## 🔒 Security
- Never commit `.env` or secrets
- Report vulnerabilities to security@yourdomain.com

## 🤝 Contributing
1. Fork → Create feature branch → PR with tests
