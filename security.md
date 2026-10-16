Security Architecture Report

This report outlines the **Security-First Development Approach** implemented across(file:///C:/Users/user/Downloads/UniServerZ/www/win)).

rewrite the securirty md for WIN 

## 1. Core Security Features & Implementations

### 🔒 Security-First Development Approach
*   **Preventing SQL Injection:** 100% of database queries use PDO prepared statements with parameterized binds. No raw user inputs are concatenated into queries.
*   **CSRF Protection:** Strict CSRF validation is applied to all state-changing HTTP `POST` requests. Output buffers dynamically inject CSRF tokens into forms.
*   **XSS Mitigation:** Output strings rendered to the browser are escaped using the `e()` helper (`htmlspecialchars` with `ENT_QUOTES | UTF-8`).
*   **Rate Limiting:** Enforced via IP-based and action-specific request counters (`checkRateLimit()`) to block brute-force attacks on login, registration, and password recovery pages.

### 🔑 End-to-End Encryption & Key Management
*   **Hashed Storage:** User passwords and one-time MFA codes are hashed prior to database insertion using PHP's native `password_hash()` with `PASSWORD_DEFAULT` (Bcrypt).
*   **Secure Keys:** Sensitive API integration keys (Monnify sandbox credentials and Paystack keys) are isolated from source code, loaded from a secure `.env` file (`app_load_env()`).
*   **Data Masking:** Verification endpoints hide target emails and mobile numbers using privacy-aware masking functions (`auth_mask_destination()`).

### 💳 PCI-Ready Architecture
*   **Tokenized Gateway Integrations:** Cardholder data, full primary account numbers (PAN), and card PINs are never stored or processed locally.
*   **Third-Party Processing:** Monnify and Paystack redirect and modal integrations process payment credentials on their secure PCI-DSS compliant infrastructure, returning secure transaction tokens and webhook payloads to `api/` endpoints.
*   **Immutable Double-Entry Ledger:** Money movements are tracked through insert-only ledger tables (`ledger_entries`, `ledger_lines`, `ledger_transactions`). Ledger rows are never updated or deleted; corrections require posting counteracting reversal entries.

### 👥 Role-Based Access Control (RBAC)
*   **Strict Route Guards:** Enforced at controller level using `requireRole($allowedRoles)`.
*   **Granular Privileges:** Separation of administrative panels based on roles:
    *   `super_admin`: Root owner for platform configuration,  settings, and RBAC.
    *   `admin`and all other stakeholders    *   `support_agent`: Support desk directors role for ticket/contact handling and escalation.

### 🛡️ Multi-Factor Authentication (MFA)
*   **Dispatched OTP Verification:** The login, registration, and reset flows force verification of a cryptographically secure, random 6-digit code (`random_int`) dispatched via WhatsApp Webhooks or SMTP Email.
*   **Biometric Extension Ready:** The database contains WebAuthn configurations under the `Includes/webauthn` structure for hardware token verification.