<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NATCODEV Super Admin Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.16/index.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .sidebar-link.active { background-color: #064e3b; color: #ffffff; border-right: 3px solid #10b981; }
        .sidebar-link:hover:not(.active) { background-color: #065f46; }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .toggle-checkbox:checked { right: 0; border-color: #10b981; }
        .toggle-checkbox:checked + .toggle-label { background-color: #10b981; }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-72 bg-emerald-950 text-emerald-100 flex flex-col flex-shrink-0 transition-all duration-300" id="sidebarNav">
            <div class="p-6 flex items-center gap-3 border-b border-emerald-800">
                <div class="w-10 h-10 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-emerald-900/50">N</div>
                <div>
                    <h1 class="text-white font-bold text-lg leading-tight">NATCODEV</h1>
                    <p class="text-xs text-emerald-400">Super Admin Workspace</p>
                </div>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-4 scrollbar-hide">
                <div class="px-4 mb-2 text-xs font-semibold text-emerald-500 uppercase tracking-wider">Core</div>
                <a href="#" class="sidebar-link active flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="dashboard">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="modules">
                    <i data-lucide="toggle-right" class="w-5 h-5"></i> Module Management
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="rbac">
                    <i data-lucide="shield-check" class="w-5 h-5"></i> RBAC & Users
                </a>
                
                <div class="px-4 mt-6 mb-2 text-xs font-semibold text-emerald-500 uppercase tracking-wider">Operations</div>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="applications">
                    <i data-lucide="file-text" class="w-5 h-5"></i> Grower Applications
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="agronomy">
                    <i data-lucide="sprout" class="w-5 h-5"></i> Agronomy & Farms
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="academy">
                    <i data-lucide="graduation-cap" class="w-5 h-5"></i> Academy & Webinars
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="marketplace">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i> Marketplace
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="support">
                    <i data-lucide="life-buoy" class="w-5 h-5"></i> Support & Tickets
                </a>
                
                <div class="px-4 mt-6 mb-2 text-xs font-semibold text-emerald-500 uppercase tracking-wider">System</div>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="settings">
                    <i data-lucide="settings" class="w-5 h-5"></i> System Settings
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm font-medium transition-colors" data-view="audit">
                    <i data-lucide="history" class="w-5 h-5"></i> Audit Logs
                </a>
            </nav>
            
            <div class="p-4 border-t border-emerald-800">
                <div class="flex items-center gap-3">
                    <img src="https://image.qwenlm.ai/public_source/611d6cd6-7fc9-40dc-906d-7e2a14b11d71/10f8b63eb-b2a8-4fe2-9fe7-c8a9fa7f35d4.png" alt="Admin" class="w-10 h-10 rounded-full object-cover border-2 border-emerald-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Super Admin</p>
                        <p class="text-xs text-emerald-400 truncate">admin@natcodev.com</p>
                    </div>
                    <button class="text-emerald-400 hover:text-white transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-4">
                    <button id="toggleSidebarBtn" class="text-slate-500 hover:text-slate-700 lg:hidden">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div class="relative">
                        <i data-lucide="search" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" placeholder="Global search: users, farms, tickets..." class="pl-10 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent w-64 lg:w-96">
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button class="relative p-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>
                    <div class="h-8 w-px bg-slate-200"></div>
                    <span class="text-sm font-medium text-slate-600">Friday, June 12, 2026</span>
                </div>
            </header>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-6" id="mainContentArea">
                
                <!-- Dashboard View -->
                <div id="view-dashboard" class="view-section fade-in">
                    <div class="mb-6 flex justify-between items-end">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Platform Overview</h2>
                            <p class="text-slate-500 text-sm mt-1">Real-time insights into NATCODEV agricultural operations.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2 shadow-sm" onclick="alert('Comprehensive system report generated.')">
                            <i data-lucide="download" class="w-4 h-4"></i> Export Report
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-2 bg-emerald-50 rounded-lg"><i data-lucide="users" class="w-6 h-6 text-emerald-600"></i></div>
                                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">+12.5%</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900">2,847</h3>
                            <p class="text-sm text-slate-500 mt-1">Total Registered Growers</p>
                        </div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-2 bg-blue-50 rounded-lg"><i data-lucide="map-pin" class="w-6 h-6 text-blue-600"></i></div>
                                <span class="text-xs font-medium text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">24 pending</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900">1,204</h3>
                            <p class="text-sm text-slate-500 mt-1">Verified Farms</p>
                        </div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-2 bg-purple-50 rounded-lg"><i data-lucide="shopping-bag" class="w-6 h-6 text-purple-600"></i></div>
                                <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">+8.2%</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900">₦4.2M</h3>
                            <p class="text-sm text-slate-500 mt-1">Marketplace Revenue</p>
                        </div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-2 bg-red-50 rounded-lg"><i data-lucide="alert-circle" class="w-6 h-6 text-red-600"></i></div>
                                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded-full">3 urgent</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900">42</h3>
                            <p class="text-sm text-slate-500 mt-1">Open Agronomy Cases</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4">Grower Enrollment & Farm Verification Trends</h3>
                            <canvas id="dashboardChartOne" height="250"></canvas>
                        </div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4">Stakeholder Distribution</h3>
                            <canvas id="dashboardChartTwo" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Modules View -->
                <div id="view-modules" class="view-section hidden fade-in">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900">Module Management</h2>
                        <p class="text-slate-500 text-sm mt-1">Activate or deactivate platform modules. Changes apply globally and are logged in the audit trail.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="modulesGridContainer">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- RBAC & Users View -->
                <div id="view-rbac" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">RBAC & User Management</h2>
                            <p class="text-slate-500 text-sm mt-1">Manage roles, permissions, and system stakeholders.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" onclick="openCrudModal('user')">
                            <i data-lucide="user-plus" class="w-4 h-4"></i> Add User
                        </button>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Name</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Email</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Role</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="divide-y divide-slate-100">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Applications View -->
                <div id="view-applications" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Grower Applications</h2>
                            <p class="text-slate-500 text-sm mt-1">Review, verify, and manage new grower onboarding requests.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" onclick="openCrudModal('application')">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Application
                        </button>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Ref</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Name</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Location</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Farm Size</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="applicationsTableBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Agronomy View -->
                <div id="view-agronomy" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Agronomy & Farms</h2>
                            <p class="text-slate-500 text-sm mt-1">Manage agronomy cases, farm verifications, and field tasks.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" onclick="openCrudModal('case')">
                            <i data-lucide="plus" class="w-4 h-4"></i> New Case
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm"><h4 class="text-sm font-medium text-slate-500 mb-2">Active Cases</h4><p class="text-3xl font-bold text-slate-900">87</p></div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm"><h4 class="text-sm font-medium text-slate-500 mb-2">Pending Verifications</h4><p class="text-3xl font-bold text-yellow-600">24</p></div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm"><h4 class="text-sm font-medium text-slate-500 mb-2">Field Tasks</h4><p class="text-3xl font-bold text-slate-900">156</p></div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm"><h4 class="text-sm font-medium text-slate-500 mb-2">Verified Farms</h4><p class="text-3xl font-bold text-emerald-600">1,204</p></div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Case Ref</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Grower</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Category</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Priority</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="agronomyTableBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Academy View -->
                <div id="view-academy" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Academy & Webinars</h2>
                            <p class="text-slate-500 text-sm mt-1">Manage training programs, webinars, lessons, and certificates.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" onclick="openCrudModal('webinar')">
                            <i data-lucide="plus" class="w-4 h-4"></i> New Webinar
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="academyGridContainer"></div>
                </div>

                <!-- Marketplace View -->
                <div id="view-marketplace" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Marketplace Management</h2>
                            <p class="text-slate-500 text-sm mt-1">Manage listings, sellers, categories, orders, and promotions.</p>
                        </div>
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" onclick="openCrudModal('listing')">
                            <i data-lucide="plus" class="w-4 h-4"></i> Add Listing
                        </button>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Title</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Seller</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Category</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Price</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="marketplaceTableBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Support View -->
                <div id="view-support" class="view-section hidden fade-in">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900">Support & Tickets</h2>
                            <p class="text-slate-500 text-sm mt-1">Handle user inquiries, support requests, and escalations.</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Ticket Ref</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Subject</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Requester</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Priority</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Status</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="supportTableBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Settings View -->
                <div id="view-settings" class="view-section hidden fade-in">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900">System Settings</h2>
                        <p class="text-slate-500 text-sm mt-1">Configure platform-wide settings, API integrations, and security.</p>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i data-lucide="settings" class="w-5 h-5 text-emerald-600"></i> General Settings</h3>
                            <div class="space-y-4">
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">Platform Name</label><input type="text" value="NATCODEV" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">Maintenance Mode</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Disabled</option><option>Enabled</option></select></div>
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">Max File Upload (MB)</label><input type="number" value="10" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i data-lucide="key" class="w-5 h-5 text-emerald-600"></i> API & Integrations</h3>
                            <div class="space-y-4">
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">Paystack Secret Key</label><input type="password" value="sk_live_xxxxxxxxxxxx" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">BVN Validation API Key</label><input type="password" value="" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                                <div><label class="block text-sm font-medium text-slate-700 mb-1">NIN Validation API Key</label><input type="password" value="" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button class="px-6 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 shadow-sm" onclick="alert('Settings saved successfully!')">Save Changes</button>
                    </div>
                </div>

                <!-- Audit View -->
                <div id="view-audit" class="view-section hidden fade-in">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-900">Audit Logs</h2>
                        <p class="text-slate-500 text-sm mt-1">Track all system actions, admin activities, and security events.</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Timestamp</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Action</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">Description</th>
                                    <th class="text-left px-6 py-4 font-semibold text-slate-600">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr class="hover:bg-slate-50"><td class="px-6 py-4 text-slate-500">2026-06-12 14:32:01</td><td class="px-6 py-4 font-medium text-slate-900">user_login</td><td class="px-6 py-4 text-slate-600">Super Admin logged in successfully</td><td class="px-6 py-4 text-slate-500">192.168.1.1</td></tr>
                                <tr class="hover:bg-slate-50"><td class="px-6 py-4 text-slate-500">2026-06-12 14:28:45</td><td class="px-6 py-4 font-medium text-slate-900">application_approved</td><td class="px-6 py-4 text-slate-600">Application NAT-260108-EE0083 approved</td><td class="px-6 py-4 text-slate-500">192.168.1.1</td></tr>
                                <tr class="hover:bg-slate-50"><td class="px-6 py-4 text-slate-500">2026-06-12 14:15:22</td><td class="px-6 py-4 font-medium text-slate-900">module_toggled</td><td class="px-6 py-4 text-slate-600">Marketplace module deactivated</td><td class="px-6 py-4 text-slate-500">192.168.1.1</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Universal CRUD Modal -->
    <div id="crudModalOverlay" class="fixed inset-0 z-50 hidden modal-overlay flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900" id="crudModalTitle">Add New Item</h3>
                <button id="closeCrudModalBtn" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="p-6" id="crudModalContent"></div>
            <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                <button id="cancelCrudBtn" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Cancel</button>
                <button id="saveCrudBtn" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Save</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const appDataList = [
            { id: 10, app_ref: 'NAT-260108-EE0083', name: 'ERNEST OKUGBE AKHIGBE', location: 'Delta', farm_size: 1.00, status: 'confirmed' },
            { id: 16, app_ref: 'NAT-260108-48B6CC', name: 'Gwenneth George', location: 'Delta. Ndukwa West LGA', farm_size: 1.00, status: 'pending' },
            { id: 17, app_ref: 'NAT-260108-5B0AE0', name: 'Eugene Hyacinth Ossai', location: 'Ashaka, Ndokwa East LGA', farm_size: 8.00, status: 'confirmed' },
            { id: 18, app_ref: 'NAT-260108-EB1D9F', name: 'Michael Martins', location: 'Delta & Aniocha South', farm_size: 50.00, status: 'pending' },
            { id: 27, app_ref: 'NAT-260418-64A106', name: 'Engr. isaac Adeboje', location: 'Oyo/Ibarapa east', farm_size: 2.00, status: 'pending' }
        ];

        const supportTicketsData = [
            { id: 1, ticket_ref: 'TKT-2026-001', subject: 'Unable to upload farm photo', requester: 'ERNEST OKUGBE', priority: 'high', status: 'open' },
            { id: 2, ticket_ref: 'TKT-2026-002', subject: 'Webinar certificate not received', requester: 'Gwenneth George', priority: 'medium', status: 'in_progress' },
            { id: 3, ticket_ref: 'TKT-2026-003', subject: 'Marketplace listing approval delay', requester: 'Michael Martins', priority: 'low', status: 'open' },
            { id: 4, ticket_ref: 'TKT-2026-004', subject: 'BVN validation failing', requester: 'Eugene Hyacinth', priority: 'high', status: 'open' }
        ];

        const modulesDataList = [
            { id: 'academy', name: 'Academy & Training', description: 'Webinars, lessons, assessments, and certificate management.', active: true, icon: 'graduation-cap' },
            { id: 'agronomy', name: 'Agronomy & Farms', description: 'Farm verifications, soil records, field tasks, and agronomy cases.', active: true, icon: 'sprout' },
            { id: 'marketplace', name: 'Marketplace', description: 'Product listings, seller management, orders, and promotions.', active: true, icon: 'shopping-bag' },
            { id: 'support', name: 'Support & Helpdesk', description: 'Ticketing system, user inquiries, and escalation management.', active: true, icon: 'life-buoy' },
            { id: 'field_management', name: 'Field Management', description: 'Agent locations, farm visits, geofencing, and field analytics.', active: true, icon: 'map-pin' },
            { id: 'disaster_recovery', name: 'Disaster Recovery', description: 'Site nodes, backup management, and data synchronization.', active: false, icon: 'shield-alert' },
            { id: 'iot_sensors', name: 'IoT & Satellite', description: 'Sensor readings, satellite imagery, and farm analytics.', active: false, icon: 'satellite' },
            { id: 'wallet', name: 'Wallet & Payments', description: 'User wallets, transactions, and payment gateway integrations.', active: true, icon: 'wallet' }
        ];

        const academyDataList = [
            { id: 1, title: 'Sustainable Cocoa Farming', category: 'Training', enrolled: 245, duration: '60 min', status: 'active' },
            { id: 2, title: 'Agronomy Fundamentals', category: 'Certification', enrolled: 189, duration: '90 min', status: 'active' },
            { id: 3, title: 'Market Access Strategies', category: 'Workshop', enrolled: 312, duration: '45 min', status: 'draft' }
        ];

        const usersDataList = [
            { id: 1, name: 'John Doe', email: 'john@natcodev.com', role: 'Super Admin', status: 'Active' },
            { id: 2, name: 'Jane Smith', email: 'jane@natcodev.com', role: 'Field Agent', status: 'Active' },
            { id: 3, name: 'Dr. A. Okafor', email: 'okafor@natcodev.com', role: 'Agronomist', status: 'Active' },
            { id: 4, name: 'ERNEST OKUGBE', email: 'emusoftinc@gmail.com', role: 'Grower', status: 'Active' }
        ];

        const agronomyCasesData = [
            { id: 1, case_ref: 'CASE-2026-001', grower: 'ERNEST OKUGBE AKHIGBE', category: 'Pest Infestation', priority: 'high', status: 'open' },
            { id: 2, case_ref: 'CASE-2026-002', grower: 'Eugene Hyacinth Ossai', category: 'Soil pH Imbalance', priority: 'medium', status: 'resolved' }
        ];

        const marketplaceDataList = [
            { id: 1, title: 'Organic Fertilizer (50kg)', seller: 'AgroSupplies Ltd', category: 'Input', price: '₦15,000', status: 'active' },
            { id: 2, title: 'Knapsack Sprayer', seller: 'FarmTools NG', category: 'Equipment', price: '₦25,000', status: 'active' }
        ];

        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        const viewSections = document.querySelectorAll('.view-section');

        sidebarLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetView = link.getAttribute('data-view');
                sidebarLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                viewSections.forEach(section => section.classList.add('hidden'));
                const targetSection = document.getElementById(`view-${targetView}`);
                if (targetSection) targetSection.classList.remove('hidden');
            });
        });

        function renderModules() {
            const grid = document.getElementById('modulesGridContainer');
            grid.innerHTML = modulesDataList.map(mod => `
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 bg-emerald-50 rounded-lg"><i data-lucide="${mod.icon}" class="w-6 h-6 text-emerald-600"></i></div>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none">
                            <input type="checkbox" id="toggle-${mod.id}" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-300 ${mod.active ? 'right-0 border-emerald-500' : 'left-0 border-slate-300'}" ${mod.active ? 'checked' : ''} onchange="toggleModuleState('${mod.id}')"/>
                            <label for="toggle-${mod.id}" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer transition-colors duration-300 ${mod.active ? 'bg-emerald-500' : 'bg-slate-300'}"></label>
                        </div>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">${mod.name}</h3>
                    <p class="text-sm text-slate-500 mb-4">${mod.description}</p>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium ${mod.active ? 'text-emerald-600 bg-emerald-50' : 'text-slate-500 bg-slate-100'} px-2 py-1 rounded-full">${mod.active ? 'Active' : 'Inactive'}</span>
                        <button class="text-xs text-blue-600 hover:text-blue-800 font-medium ml-auto">Configure</button>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        function toggleModuleState(moduleId) {
            const mod = modulesDataList.find(m => m.id === moduleId);
            if (mod) {
                mod.active = !mod.active;
                renderModules();
            }
        }

        function renderApplicationsTable() {
            const tbody = document.getElementById('applicationsTableBody');
            tbody.innerHTML = appDataList.map(app => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">${app.app_ref}</td>
                    <td class="px-6 py-4 font-medium text-slate-900">${app.name}</td>
                    <td class="px-6 py-4 text-slate-600">${app.location}</td>
                    <td class="px-6 py-4 text-slate-600">${app.farm_size} ha</td>
                    <td class="px-6 py-4"><span class="text-xs font-medium ${app.status === 'confirmed' ? 'text-emerald-600 bg-emerald-100' : 'text-yellow-600 bg-yellow-100'} px-2 py-1 rounded-full">${app.status === 'confirmed' ? 'Confirmed' : 'Pending'}</span></td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2">
                            <button class="text-blue-600 hover:text-blue-800 text-xs font-medium" onclick="openCrudModal('application', 'edit', ${app.id})">Edit</button>
                            <button class="text-red-600 hover:text-red-800 text-xs font-medium" onclick="deleteEntity('application', ${app.id})">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function renderSupportTable() {
            const tbody = document.getElementById('supportTableBody');
            tbody.innerHTML = supportTicketsData.map(ticket => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">${ticket.ticket_ref}</td>
                    <td class="px-6 py-4 font-medium text-slate-900">${ticket.subject}</td>
                    <td class="px-6 py-4 text-slate-600">${ticket.requester}</td>
                    <td class="px-6 py-4"><span class="text-xs font-medium ${ticket.priority === 'high' ? 'text-red-600 bg-red-100' : ticket.priority === 'medium' ? 'text-yellow-600 bg-yellow-100' : 'text-blue-600 bg-blue-100'} px-2 py-1 rounded-full">${ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}</span></td>
                    <td class="px-6 py-4"><span class="text-xs font-medium ${ticket.status === 'open' ? 'text-red-600 bg-red-100' : ticket.status === 'in_progress' ? 'text-yellow-600 bg-yellow-100' : 'text-emerald-600 bg-emerald-100'} px-2 py-1 rounded-full">${ticket.status.replace('_', ' ').charAt(0).toUpperCase() + ticket.status.replace('_', ' ').slice(1)}</span></td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2">
                            <button class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</button>
                            <button class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function renderAcademyGrid() {
            const grid = document.getElementById('academyGridContainer');
            grid.innerHTML = academyDataList.map(item => `
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-all">
                    <div class="h-32 bg-gradient-to-br from-emerald-500 to-teal-600 p-6 flex items-end">
                        <span class="text-white text-xs font-medium bg-white/20 px-2 py-1 rounded backdrop-blur-sm">${item.category}</span>
                    </div>
                    <div class="p-6">
                        <h3 class="font-bold text-slate-900 mb-2">${item.title}</h3>
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-4">
                            <span class="flex items-center gap-1"><i data-lucide="users" class="w-3 h-3"></i> ${item.enrolled} enrolled</span>
                            <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> ${item.duration}</span>
                        </div>
                        <div class="flex gap-2">
                            <button class="flex-1 px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-xs font-medium hover:bg-slate-200" onclick="openCrudModal('webinar', 'edit', ${item.id})">Edit</button>
                            <button class="flex-1 px-3 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100" onclick="deleteEntity('webinar', ${item.id})">Delete</button>
                        </div>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        }

        function renderUsersTable() {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = usersDataList.map(user => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-medium text-slate-900">${user.name}</td>
                    <td class="px-6 py-4 text-slate-600">${user.email}</td>
                    <td class="px-6 py-4"><span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">${user.role}</span></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">${user.status}</span></td>
                    <td class="px-6 py-4 flex gap-2">
                        <button class="text-blue-600 hover:text-blue-800 text-xs font-medium" onclick="openCrudModal('user', 'edit', ${user.id})">Edit</button>
                        <button class="text-red-600 hover:text-red-800 text-xs font-medium" onclick="deleteEntity('user', ${user.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        function renderAgronomyTable() {
            const tbody = document.getElementById('agronomyTableBody');
            tbody.innerHTML = agronomyCasesData.map(c => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-slate-600">${c.case_ref}</td>
                    <td class="px-6 py-4 font-medium text-slate-900">${c.grower}</td>
                    <td class="px-6 py-4 text-slate-600">${c.category}</td>
                    <td class="px-6 py-4"><span class="px-2 py-1 ${c.priority === 'high' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'} rounded-full text-xs font-medium">${c.priority.charAt(0).toUpperCase() + c.priority.slice(1)}</span></td>
                    <td class="px-6 py-4"><span class="px-2 py-1 ${c.status === 'open' ? 'bg-yellow-100 text-yellow-700' : 'bg-emerald-100 text-emerald-700'} rounded-full text-xs font-medium">${c.status.charAt(0).toUpperCase() + c.status.slice(1)}</span></td>
                    <td class="px-6 py-4 flex gap-2">
                        <button class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</button>
                        <button class="text-red-600 hover:text-red-800 text-xs font-medium" onclick="deleteEntity('case', ${c.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        function renderMarketplaceTable() {
            const tbody = document.getElementById('marketplaceTableBody');
            tbody.innerHTML = marketplaceDataList.map(item => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-medium text-slate-900">${item.title}</td>
                    <td class="px-6 py-4 text-slate-600">${item.seller}</td>
                    <td class="px-6 py-4 text-slate-600">${item.category}</td>
                    <td class="px-6 py-4 font-medium text-slate-900">${item.price}</td>
                    <td class="px-6 py-4"><span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span></td>
                    <td class="px-6 py-4 flex gap-2">
                        <button class="text-blue-600 hover:text-blue-800 text-xs font-medium" onclick="openCrudModal('listing', 'edit', ${item.id})">Edit</button>
                        <button class="text-red-600 hover:text-red-800 text-xs font-medium" onclick="deleteEntity('listing', ${item.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        const crudModalOverlay = document.getElementById('crudModalOverlay');
        const crudModalTitle = document.getElementById('crudModalTitle');
        const crudModalContent = document.getElementById('crudModalContent');
        const closeCrudModalBtn = document.getElementById('closeCrudModalBtn');
        const cancelCrudBtn = document.getElementById('cancelCrudBtn');
        const saveCrudBtn = document.getElementById('saveCrudBtn');

        function openCrudModal(type, action = 'add', id = null) {
            const isEdit = action === 'edit';
            crudModalTitle.textContent = `${isEdit ? 'Edit' : 'Add New'} ${type.charAt(0).toUpperCase() + type.slice(1)}`;
            
            let formHtml = '';
            if (type === 'application') {
                formHtml = `<div class="space-y-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label><input type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Enter full name"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">State</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Delta</option><option>Oyo</option><option>Cross River</option></select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Farm Size (ha)</label><input type="number" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="0.00"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label><input type="email" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="email@domain.com"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Status</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="rejected">Rejected</option></select></div>
                </div>`;
            } else if (type === 'user') {
                formHtml = `<div class="space-y-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label><input type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Enter full name"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label><input type="email" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="email@domain.com"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Role</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Grower</option><option>Field Agent</option><option>Agronomist</option><option>Admin</option><option>Super Admin</option></select></div>
                </div>`;
            } else if (type === 'webinar') {
                formHtml = `<div class="space-y-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Title</label><input type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Webinar title"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Training</option><option>Certification</option><option>Workshop</option></select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Duration (min)</label><input type="number" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" value="60"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Description</label><textarea rows="3" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Webinar description"></textarea></div>
                </div>`;
            } else if (type === 'case') {
                formHtml = `<div class="space-y-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Grower Name</label><input type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Enter grower name"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Pest Infestation</option><option>Soil pH Imbalance</option><option>Irrigation Issue</option></select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Priority</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Low</option><option>Medium</option><option>High</option></select></div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Description</label><textarea rows="3" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Case description"></textarea></div>
                </div>`;
            } else if (type === 'listing') {
                formHtml = `<div class="space-y-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Product Title</label><input type="text" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Product title"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><option>Input</option><option>Equipment</option><option>Service</option></select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Price (₦)</label><input type="number" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="0.00"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Description</label><textarea rows="3" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Product description"></textarea></div>
                </div>`;
            } else {
                formHtml = `<p class="text-slate-500 text-center py-4">Form for ${type} would be rendered here.</p>`;
            }
            
            crudModalContent.innerHTML = formHtml;
            crudModalOverlay.classList.remove('hidden');
        }

        function deleteEntity(type, id) {
            if (confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
                alert(`${type} deleted successfully.`);
            }
        }

        closeCrudModalBtn.addEventListener('click', () => crudModalOverlay.classList.add('hidden'));
        cancelCrudBtn.addEventListener('click', () => crudModalOverlay.classList.add('hidden'));
        saveCrudBtn.addEventListener('click', () => {
            alert('Item saved successfully!');
            crudModalOverlay.classList.add('hidden');
        });
        crudModalOverlay.addEventListener('click', (e) => {
            if (e.target === crudModalOverlay) crudModalOverlay.classList.add('hidden');
        });

        function initCharts() {
            const ctxOne = document.getElementById('dashboardChartOne').getContext('2d');
            new Chart(ctxOne, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'New Applications',
                        data: [45, 62, 78, 95, 110, 156],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Verified Farms',
                        data: [30, 45, 60, 75, 90, 120],
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
                }
            });

            const ctxTwo = document.getElementById('dashboardChartTwo').getContext('2d');
            new Chart(ctxTwo, {
                type: 'doughnut',
                data: {
                    labels: ['Growers', 'Field Agents', 'Agronomists', 'Admins'],
                    datasets: [{
                        data: [2500, 142, 87, 18],
                        backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '70%'
                }
            });
        }

        renderModules();
        renderApplicationsTable();
        renderSupportTable();
        renderAcademyGrid();
        renderUsersTable();
        renderAgronomyTable();
        renderMarketplaceTable();
        initCharts();
    </script>
</body>
</html>