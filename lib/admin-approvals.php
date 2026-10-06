<?php
declare(strict_types=1);

/**
 * Generalized approval workflow (Phase 2, additive).
 *
 * The platform already stores approvable actions in admin_action_requests with a
 * request_type column. This registry lets new request types (refund, payout
 * release, role grant, rule change, ...) register a handler, while the existing
 * 'delete' path keeps using the legacy reviewer as a fallback.
 */

function admin_register_approval_handler(string $type, callable $handler): void
{
    $GLOBALS['__admin_approval_handlers'][$type] = $handler;
}

/** @return array<string,callable> */
function admin_approval_handlers(): array
{
    return $GLOBALS['__admin_approval_handlers'] ?? [];
}

/**
 * Apply an approval decision for a queued request row.
 *
 * @param array<string,mixed> $request A row from admin_action_requests.
 */
function admin_apply_approval(PDO $pdo, array $request, string $decision, string $note = ''): void
{
    if (!in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Unknown approval decision: ' . $decision);
    }

    $type = (string) ($request['request_type'] ?? '');
    $handlers = admin_approval_handlers();
    if (isset($handlers[$type])) {
        ($handlers[$type])($pdo, $request, $decision, $note);
        return;
    }

    // Legacy fallback preserves exact current behaviour for delete requests.
    if ($type === 'delete' && function_exists('admin_review_action_request')) {
        admin_review_action_request($pdo, (int) ($request['id'] ?? 0), $decision, $note);
        return;
    }

    throw new RuntimeException('No approval handler registered for request type "' . $type . '".');
}
