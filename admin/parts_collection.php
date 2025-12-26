<?php
require_once '../config.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'parts_collection_manager'])) {
    header('Location: ../index.php');
    exit;
}

$pageTitle = 'Part Pricing Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white">
            <div class="p-4">
                <h2 class="text-xl font-bold">Auto Shop</h2>
                <p class="text-sm text-gray-300">Parts Collection</p>
            </div>
            <nav class="mt-8">
                <a href="#dashboard" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700" data-section="dashboard">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="#requests" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700 active" data-section="requests">
                    <i class="fas fa-list mr-2"></i>Pricing Requests
                </a>
                <a href="#completed" class="nav-link block px-4 py-2 text-gray-300 hover:bg-gray-700" data-section="completed">
                    <i class="fas fa-check-circle mr-2"></i>Completed
                </a>
                <a href="../logout.php" class="block px-4 py-2 text-gray-300 hover:bg-gray-700 mt-8">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white shadow-sm px-6 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900" id="page-title">Part Pricing Requests</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <div class="relative">
                            <button id="notifications-btn" class="relative p-2 text-gray-600 hover:text-gray-900">
                                <i class="fas fa-bell"></i>
                                <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 p-6">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="section">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Pending</p>
                                    <p class="text-2xl font-bold text-gray-900" id="pending-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i class="fas fa-spinner"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">In Progress</p>
                                    <p class="text-2xl font-bold text-gray-900" id="inprogress-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Completed</p>
                                    <p class="text-2xl font-bold text-gray-900" id="completed-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Cancelled</p>
                                    <p class="text-2xl font-bold text-gray-900" id="cancelled-count">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests Section -->
                <div id="requests-section" class="section">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-900">Pricing Requests</h2>
                                <div class="flex space-x-2">
                                    <button id="filter-pending" class="px-3 py-1 text-sm bg-yellow-100 text-yellow-800 rounded-full filter-btn active" data-filter="pending">Pending</button>
                                    <button id="filter-inprogress" class="px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full filter-btn" data-filter="in_progress">In Progress</button>
                                    <button id="filter-completed" class="px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full filter-btn" data-filter="completed">Completed</button>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div id="requests-list" class="space-y-4">
                                <!-- Requests will be loaded here -->
                            </div>
                            <div id="loading" class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                <p class="text-gray-500 mt-2">Loading requests...</p>
                            </div>
                            <div id="no-requests" class="text-center py-8 hidden">
                                <i class="fas fa-inbox text-gray-400 text-4xl"></i>
                                <p class="text-gray-500 mt-2">No pricing requests found</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Completed Section -->
                <div id="completed-section" class="section hidden">
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">Recently Completed</h2>
                        </div>
                        <div class="p-6">
                            <div id="completed-list" class="space-y-4">
                                <!-- Completed requests will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Request Detail Modal -->
    <div id="request-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modal-title">Part Pricing Request</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modal-content">
                    <!-- Modal content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentSection = 'requests';
        let currentFilter = 'pending';
        let currentRequests = [];

        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.dataset.section;
                showSection(section);
            });
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                loadRequests();
            });
        });

        // Modal
        document.getElementById('close-modal').addEventListener('click', () => {
            document.getElementById('request-modal').classList.add('hidden');
        });

        function showSection(section) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(section + '-section').classList.remove('hidden');
            currentSection = section;

            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[data-section="${section}"]`).classList.add('active');

            if (section === 'dashboard') {
                loadStats();
            } else if (section === 'requests') {
                loadRequests();
            } else if (section === 'completed') {
                loadCompleted();
            }
        }

        async function loadStats() {
            try {
                const response = await fetch('api_part_pricing.php?action=stats');
                const data = await response.json();
                if (data.success) {
                    document.getElementById('pending-count').textContent = data.stats.pending || 0;
                    document.getElementById('inprogress-count').textContent = data.stats.in_progress || 0;
                    document.getElementById('completed-count').textContent = data.stats.completed || 0;
                    document.getElementById('cancelled-count').textContent = data.stats.cancelled || 0;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        async function loadRequests() {
            const loading = document.getElementById('loading');
            const noRequests = document.getElementById('no-requests');
            const requestsList = document.getElementById('requests-list');

            loading.classList.remove('hidden');
            noRequests.classList.add('hidden');
            requestsList.innerHTML = '';

            try {
                const response = await fetch(`api_part_pricing.php?action=list&status=${currentFilter}`);
                const data = await response.json();

                loading.classList.add('hidden');

                if (data.success && data.requests.length > 0) {
                    currentRequests = data.requests;
                    renderRequests(data.requests);
                } else {
                    noRequests.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error loading requests:', error);
                loading.innerHTML = '<p class="text-red-500">Error loading requests</p>';
            }
        }

        async function loadCompleted() {
            const completedList = document.getElementById('completed-list');
            completedList.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-gray-400"></i></div>';

            try {
                const response = await fetch('api_part_pricing.php?action=list&status=completed&limit=20');
                const data = await response.json();

                if (data.success) {
                    renderRequests(data.requests, true);
                } else {
                    completedList.innerHTML = '<p class="text-center text-gray-500">No completed requests found</p>';
                }
            } catch (error) {
                console.error('Error loading completed requests:', error);
                completedList.innerHTML = '<p class="text-center text-red-500">Error loading completed requests</p>';
            }
        }

        function renderRequests(requests, isCompleted = false) {
            const container = isCompleted ? document.getElementById('completed-list') : document.getElementById('requests-list');
            container.innerHTML = '';

            requests.forEach(request => {
                const card = createRequestCard(request, isCompleted);
                container.appendChild(card);
            });
        }

        function createRequestCard(request, isCompleted = false) {
            const card = document.createElement('div');
            card.className = 'bg-gray-50 rounded-lg p-4 border';

            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'completed': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800'
            };

            const statusText = {
                'pending': 'Pending',
                'in_progress': 'In Progress',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };

            card.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h3 class="font-medium text-gray-900">${request.part_name}</h3>
                        <p class="text-sm text-gray-600">Invoice #${request.invoice_id} - ${request.customer_name} (${request.plate_number})</p>
                        <p class="text-sm text-gray-600">${request.vehicle_make || 'N/A'} ${request.vehicle_model || ''}</p>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full ${statusColors[request.status]}">
                        ${statusText[request.status]}
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        Requested: ${new Date(request.created_at).toLocaleDateString()}
                        ${request.assigned_to_name ? ` | Assigned: ${request.assigned_to_name}` : ''}
                    </div>
                    <div class="space-x-2">
                        ${!isCompleted ? `
                            <button onclick="viewRequest(${request.id})" class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">
                                View Details
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;

            return card;
        }

        async function viewRequest(requestId) {
            const modal = document.getElementById('request-modal');
            const content = document.getElementById('modal-content');

            modal.classList.remove('hidden');
            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-gray-400"></i></div>';

            try {
                const request = currentRequests.find(r => r.id == requestId);
                if (!request) return;

                content.innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Part Name</label>
                            <p class="mt-1 text-sm text-gray-900">${request.part_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity</label>
                            <p class="mt-1 text-sm text-gray-900">${request.requested_quantity}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                            <p class="mt-1 text-sm text-gray-900">${request.vehicle_make || 'N/A'} ${request.vehicle_model || ''}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Invoice</label>
                            <p class="mt-1 text-sm text-gray-900">#${request.invoice_id} - ${request.customer_name} (${request.plate_number})</p>
                        </div>
                        ${request.status === 'pending' ? `
                            <div class="pt-4">
                                <button onclick="assignRequest(${request.id})" class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                    Assign to Me
                                </button>
                            </div>
                        ` : request.status === 'in_progress' ? `
                            <form onsubmit="updatePrice(event, ${request.id})" class="space-y-4 pt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Price</label>
                                    <input type="number" step="0.01" name="price" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                                </div>
                                <div class="flex space-x-2">
                                    <button type="submit" class="flex-1 px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                        Update Price
                                    </button>
                                    <button type="button" onclick="completeRequest(${request.id})" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                        Complete
                                    </button>
                                </div>
                            </form>
                        ` : ''}
                    </div>
                `;
            } catch (error) {
                console.error('Error loading request details:', error);
                content.innerHTML = '<p class="text-red-500">Error loading request details</p>';
            }
        }

        async function assignRequest(requestId) {
            try {
                const response = await fetch('api_part_pricing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=assign&request_id=${requestId}`
                });
                const data = await response.json();

                if (data.success) {
                    alert('Request assigned successfully');
                    document.getElementById('request-modal').classList.add('hidden');
                    loadRequests();
                    loadStats();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error assigning request:', error);
                alert('Error assigning request');
            }
        }

        async function updatePrice(event, requestId) {
            event.preventDefault();
            const formData = new FormData(event.target);

            try {
                const response = await fetch('api_part_pricing.php', {
                    method: 'POST',
                    body: `action=update_price&request_id=${requestId}&price=${formData.get('price')}&notes=${encodeURIComponent(formData.get('notes'))}`
                });
                const data = await response.json();

                if (data.success) {
                    alert('Price updated successfully');
                    loadRequests();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error updating price:', error);
                alert('Error updating price');
            }
        }

        async function completeRequest(requestId) {
            if (!confirm('Are you sure you want to mark this request as completed?')) return;

            try {
                const response = await fetch('api_part_pricing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=complete&request_id=${requestId}`
                });
                const data = await response.json();

                if (data.success) {
                    alert('Request completed successfully');
                    document.getElementById('request-modal').classList.add('hidden');
                    loadRequests();
                    loadStats();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error completing request:', error);
                alert('Error completing request');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadRequests();

            // Auto refresh every 30 seconds
            setInterval(() => {
                if (currentSection === 'dashboard') loadStats();
                if (currentSection === 'requests') loadRequests();
            }, 30000);
        });
    </script>
</body>
</html>