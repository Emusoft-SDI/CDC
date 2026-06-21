# Security Audit: User-Facing Areas

## Inventory & Status

| Area | Auth Pattern | Status | Notes |
| :--- | :--- | :--- | :--- |
| `dashboard/` | Session (`$_SESSION['user_id']`) | Needs Audit | Consistent check required. |
| `api/` | JWT/Token-based | Needs Audit | Validate token logic. |
| Public Forms (`apply.php`, etc.) | None/Form-based | Needs Audit | CSRF and Rate Limiting. |

## Findings
1.  `dashboard/index.php` manually checks `$_SESSION['user_id']`.
2.  `api/auth.php` implements a class-based token system.
3.  Need to ensure all `dashboard/` files have uniform protection.
