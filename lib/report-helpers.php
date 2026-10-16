<?php
/**
 * Report helper functions that are safe to require without side effects.
 * These are also defined in admin/reports.php (with if (!function_exists) guards)
 * so they can be loaded independently for testing or other contexts.
 */

if (!function_exists('report_subpages')) {
    function report_subpages(): array
    {
        return [
            'overview' => ['Executive Overview', 'National performance, growth, adoption, marketplace, finance, and risk signals.', 'fa-chart-pie'],
            'growers' => ['Grower & Farm Intelligence', 'Registration, documents, farms, accreditation, geography, and farm health.', 'fa-seedling'],
            'marketplace' => ['Marketplace Intelligence', 'Sellers, listings, orders, revenue, buyer demand, and seller performance.', 'fa-store'],
            'finance' => ['Finance & Wallet', 'Wallet volume, budget burn, transaction mix, and daily cash movement.', 'fa-wallet'],
            'field' => ['Field Operations', 'Visits, tasks, verification coverage, purpose mix, and agent workload.', 'fa-map-marked-alt'],
            'providers' => ['Provider Ecosystem', 'Provider approvals, types, offerings, geography, and onboarding backlog.', 'fa-truck'],
            'support' => ['Support & Communications', 'Ticket queues, priority risk, category load, and resolution status.', 'fa-headset'],
            'compliance' => ['Compliance & Accreditation', 'Document verification queue, accreditation coverage, and audit posture.', 'fa-shield-halved'],
            'coordinator_coverage' => ['Coordinator Coverage', 'Tiers, enrollment pipeline, and scope coverage gaps.', 'fa-users-crown'],
        ];
    }
}

if (!function_exists('report_subpages_for_role')) {
    function report_subpages_for_role(string $role): array
    {
        return match ($role) {
            'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator' => ['growers', 'field', 'providers', 'support', 'compliance'],
            'national_coordinator', 'regional_coordinator' => array_keys(report_subpages()),
            'field_agent', 'agronomist', 'agric_extensionist' => ['field', 'growers', 'support'],
            'investor' => ['overview', 'marketplace', 'finance'],
            default => array_keys(report_subpages()),
        };
    }
}
