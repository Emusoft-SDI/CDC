<?php
declare(strict_types=1);

require_once __DIR__ . '/support.php';

function support_escalate_to_coordinator(PDO $pdo, int $ticketId, int $coordinatorUserId, int $escalatedBy): bool
{
    $stmt = $pdo->prepare("INSERT INTO support_tickets_internal_notes (ticket_id, note_type, note, created_by) VALUES (?, 'escalation', ?, ?) ON DUPLICATE KEY UPDATE note=VALUES(note), created_at=NOW()");
    return $stmt->execute([$ticketId, 'Escalated to coordinator user #' . $coordinatorUserId, $escalatedBy]);
}
