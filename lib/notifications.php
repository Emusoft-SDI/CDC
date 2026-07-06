<?php
declare(strict_types=1);

// Placeholder for notification logic using PHPMailer or similar
function send_field_agent_email(string $to, string $subject, string $body): bool
{
    // Integration point: Utilize system SMTP settings from config/env
    // This is a stub for the actual mail sending implementation
    error_log("Sending email to $to: $subject");
    return true;
}

function notify_task_assigned(array $agent, array $task): void
{
    $subject = "New Task Assigned: Visit to " . ($task['grower_name'] ?? 'Grower');
    $body = "Hello {$agent['name']},\n\nYou have been assigned a new task: {$task['notes']}.\n\nPlease check your dashboard.";
    send_field_agent_email($agent['email'], $subject, $body);
}

function notify_imagery_status(array $agent, array $task, string $status): void
{
    $subject = "Farm Imagery " . ucfirst($status);
    $body = "Hello {$agent['name']},\n\nYour farm imagery for task {$task['id']} has been {$status}.";
    send_field_agent_email($agent['email'], $subject, $body);
}
