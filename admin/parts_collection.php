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

$pageTitle = 'Parts Pricing Hub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Auto Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50" x-data="partsPricingApp()">
    <!-- Loading Overlay -->
    <div x-show="loading" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-4">
            <i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i>
            <span class="text-gray-700">Loading...</span>
        </div>
    </div>

    <!-- Success/Error Notifications -->
    <div x-show="notification.show" x-transition class="fixed top-4 right-4 z-50">
        <div :class="notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'"
             class="text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2">
            <i :class="notification.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
            <span x-text="notification.message"></span>
            <button @click="notification.show = false" class="ml-4 hover:opacity-75">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
            <div class="p-6">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cogs text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">Parts Hub</h2>
                        <p class="text-sm text-blue-200">Pricing Management</p>
                    </div>
                </div>
            </div>

            <nav class="mt-6 px-4">
                <a href="#" @click="currentView = 'dashboard'"
                   :class="currentView === 'dashboard' ? 'bg-blue-700 text-white' : 'text-blue-200 hover:text-white hover:bg-blue-700'"
                   class="nav-link flex items-center px-4 py-3 rounded-lg mb-2 transition-colors">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <a href="#" @click="currentView = 'requests'"
                   :class="currentView === 'requests' ? 'bg-blue-700 text-white' : 'text-blue-200 hover:text-white hover:bg-blue-700'"
                   class="nav-link flex items-center px-4 py-3 rounded-lg mb-2 transition-colors">
                    <i class="fas fa-list-check mr-3"></i>
                    <span>Active Requests</span>
                    <span x-show="stats.pending > 0" class="ml-auto bg-yellow-500 text-yellow-900 px-2 py-1 rounded-full text-xs font-bold"
                          x-text="stats.pending"></span>
                </a>

                <a href="#" @click="currentView = 'completed'"
                   :class="currentView === 'completed' ? 'bg-blue-700 text-white' : 'text-blue-200 hover:text-white hover:bg-blue-700'"
                   class="nav-link flex items-center px-4 py-3 rounded-lg mb-2 transition-colors">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span>Completed</span>
                </a>

                <div class="border-t border-blue-700 mt-6 pt-6">
                    <a href="../logout.php" class="flex items-center px-4 py-3 text-blue-200 hover:text-white hover:bg-blue-700 rounded-lg transition-colors">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white shadow-sm px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900" x-text="getPageTitle()"></h1>
                        <p class="text-sm text-gray-600 mt-1">Manage part pricing requests efficiently</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions -->
                        <div class="relative" x-show="currentView === 'requests'">
                            <button @click="showBulkActions = !showBulkActions"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-ellipsis-h mr-2"></i>
                                Actions
                            </button>

                            <div x-show="showBulkActions" @click.away="showBulkActions = false"
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-10">
                                <div class="py-2">
                                    <button @click="bulkAssign()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user-plus mr-2"></i>Assign Selected
                                    </button>
                                    <button @click="bulkComplete()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-check-double mr-2"></i>Complete Selected
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Refresh Button -->
                        <button @click="refreshData()" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-sync-alt" :class="{'fa-spin': loading}"></i>
                        </button>

                        <!-- User Info -->
                        <div class="flex items-center space-x-3 text-sm">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                            <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard View -->
            <div x-show="currentView === 'dashboard'" x-transition class="flex-1 p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Requests</p>
                                <p class="text-3xl font-bold text-yellow-600" x-text="stats.pending">0</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500">Awaiting assignment</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">In Progress</p>
                                <p class="text-3xl font-bold text-blue-600" x-text="stats.in_progress">0</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-spinner text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500">Being priced</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Completed Today</p>
                                <p class="text-3xl font-bold text-green-600" x-text="stats.completed_today">0</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500">Finished pricing</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Avg. Processing Time</p>
                                <p class="text-3xl font-bold text-purple-600" x-text="stats.avg_time">2.3h</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500">From assignment to completion</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                    </div>
                    <div class="p-6">
                        <div x-show="recentActivity.length === 0" class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p>No recent activity</p>
                        </div>

                        <div x-show="recentActivity.length > 0" class="space-y-4">
                            <template x-for="activity in recentActivity" :key="activity.id">
                                <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         :class="getActivityIconColor(activity.type)">
                                        <i :class="getActivityIcon(activity.type)"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900" x-text="activity.message"></p>
                                        <p class="text-xs text-gray-500" x-text="formatTime(activity.created_at)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests View -->
            <div x-show="currentView === 'requests'" x-transition class="flex-1 p-6">
                <!-- Filters and Search -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex flex-wrap gap-2">
                            <button @click="activeFilter = 'all'"
                                    :class="activeFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                All Requests
                            </button>
                            <button @click="activeFilter = 'pending'"
                                    :class="activeFilter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-clock mr-2"></i>Pending
                            </button>
                            <button @click="activeFilter = 'in_progress'"
                                    :class="activeFilter === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-blue-200'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-spinner mr-2"></i>In Progress
                            </button>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input x-model="searchQuery" type="text" placeholder="Search parts, invoices, customers..."
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            <select x-model="sortBy" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="created_at">Newest First</option>
                                <option value="part_name">Part Name</option>
                                <option value="customer_name">Customer</option>
                                <option value="priority">Priority</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Requests List -->
                <div class="space-y-4">
                    <!-- Bulk Selection Header -->
                    <div x-show="selectedRequests.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-blue-700">
                                <span x-text="selectedRequests.length"></span> request(s) selected
                            </span>
                            <div class="flex space-x-2">
                                <button @click="bulkAssign()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Assign Selected
                                </button>
                                <button @click="clearSelection()" class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loadingRequests" class="text-center py-12">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl mb-4"></i>
                        <p class="text-gray-600">Loading pricing requests...</p>
                    </div>

                    <!-- Empty State -->
                    <div x-show="!loadingRequests && filteredRequests.length === 0" class="text-center py-12">
                        <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-tools text-yellow-600 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Parts Collection System Not Configured</h3>
                        <p class="text-gray-600 mb-6">The parts pricing system needs to be set up first. Please run the migration from the admin dashboard.</p>
                        <div class="flex space-x-3 justify-center">
                            <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-cog mr-2"></i>Go to Admin Dashboard
                            </a>
                            <button @click="refreshData()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Request Cards -->
                    <template x-for="request in filteredRequests" :key="request.id">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-start space-x-4">
                                        <!-- Checkbox for bulk actions -->
                                        <input :checked="selectedRequests.includes(request.id)"
                                               @change="toggleSelection(request.id)"
                                               type="checkbox" class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500">

                                        <!-- Part Info -->
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900" x-text="request.part_name"></h3>
                                            <p class="text-sm text-gray-600 mb-2" x-text="request.part_description || 'No description'"></p>

                                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                                <span><i class="fas fa-hashtag mr-1"></i>Qty: <span x-text="request.requested_quantity"></span></span>
                                                <span><i class="fas fa-car mr-1"></i><span x-text="request.vehicle_make || 'N/A'"></span> <span x-text="request.vehicle_model || ''"></span></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Badge -->
                                    <div class="flex flex-col items-end space-y-2">
                                        <span :class="getStatusBadgeClass(request.status)" class="px-3 py-1 rounded-full text-xs font-medium">
                                            <i :class="getStatusIcon(request.status)" class="mr-1"></i>
                                            <span x-text="getStatusText(request.status)"></span>
                                        </span>

                                        <span class="text-xs text-gray-500" x-text="formatTime(request.created_at)"></span>
                                    </div>
                                </div>

                                <!-- Invoice Info -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                Invoice #<span x-text="request.invoice_id"></span>
                                            </p>
                                            <p class="text-sm text-gray-600" x-text="request.customer_name"></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-600" x-text="request.plate_number"></p>
                                            <p class="text-xs text-gray-500">License Plate</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assignment Info -->
                                <div x-show="request.assigned_to_name" class="flex items-center justify-between text-sm text-gray-600 mb-4">
                                    <span>Assigned to: <span class="font-medium" x-text="request.assigned_to_name"></span></span>
                                    <span x-show="request.final_price" class="text-green-600 font-medium">
                                        $<span x-text="request.final_price"></span>
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center justify-between">
                                    <div class="flex space-x-2">
                                        <button @click="viewRequest(request)" class="px-3 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <i class="fas fa-eye mr-1"></i>View Details
                                        </button>

                                        <button x-show="request.status === 'pending'" @click="quickAssign(request)"
                                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-user-plus mr-1"></i>Assign to Me
                                        </button>
                                    </div>

                                    <!-- Priority Indicator -->
                                    <div class="flex items-center space-x-1">
                                        <button @click="togglePriority(request)" :class="request.priority === 'high' ? 'text-red-500' : 'text-gray-400'"
                                                class="p-1 hover:text-red-500 transition-colors">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Completed View -->
            <div x-show="currentView === 'completed'" x-transition class="flex-1 p-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Completed Pricing Requests</h2>
                        <p class="text-sm text-gray-600 mt-1">Recently completed part pricing requests</p>
                    </div>
                    <div class="p-6">
                        <div x-show="completedRequests.length === 0" class="text-center py-8 text-gray-500">
                            <i class="fas fa-check-circle text-4xl mb-4 text-green-400"></i>
                            <p>No completed requests yet</p>
                        </div>

                        <div x-show="completedRequests.length > 0" class="space-y-4">
                            <template x-for="request in completedRequests" :key="request.id">
                                <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-check text-green-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900" x-text="request.part_name"></h4>
                                            <p class="text-sm text-gray-600">Invoice #<span x-text="request.invoice_id"></span> - $<span x-text="request.final_price"></span></p>
                                        </div>
                                    </div>
                                    <div class="text-right text-sm text-gray-500">
                                        <p x-text="formatTime(request.completed_at)"></p>
                                        <p>by <span x-text="request.completed_by_name"></span></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Detail Modal -->
    <div x-show="showModal" x-transition @keydown.escape.window="showModal = false"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Part Pricing Details</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div x-show="selectedRequest">
                    <!-- Part Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Part Details</label>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900" x-text="selectedRequest ? selectedRequest.part_name : ''"></h4>
                                <p class="text-sm text-gray-600 mt-1" x-text="selectedRequest ? (selectedRequest.part_description || 'No description') : ''"></p>
                                <p class="text-sm text-gray-600 mt-2">Quantity: <span x-text="selectedRequest ? selectedRequest.requested_quantity : ''"></span></p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Information</label>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-gray-900" x-text="selectedRequest ? (selectedRequest.vehicle_make || 'N/A') : ''"></p>
                                <p class="text-sm text-gray-600" x-text="selectedRequest ? (selectedRequest.vehicle_model || '') : ''"></p>
                                <p class="text-sm text-gray-600 mt-2">Invoice #<span x-text="selectedRequest ? selectedRequest.invoice_id : ''"></span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900" x-text="selectedRequest ? selectedRequest.customer_name : ''"></h4>
                                <p class="text-sm text-gray-600">License Plate: <span x-text="selectedRequest ? selectedRequest.plate_number : ''"></span></p>
                            </div>
                            <div class="text-right">
                                <span :class="selectedRequest ? getStatusBadgeClass(selectedRequest.status) : ''" class="px-3 py-1 rounded-full text-xs font-medium">
                                    <span x-text="selectedRequest ? getStatusText(selectedRequest.status) : ''"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Form (only for pending or in_progress requests) -->
                    <div x-show="selectedRequest && (selectedRequest.status === 'pending' || selectedRequest.status === 'in_progress')" class="bg-yellow-50 rounded-lg p-6 mb-6">
                        <h4 class="font-medium text-gray-900 mb-4">Set Part Price</h4>
                        <form @submit.prevent="submitPrice()">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Price ($)</label>
                                    <input x-model="priceForm.price" type="number" step="0.01" min="0"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Supplier/Source</label>
                                    <input x-model="priceForm.supplier" type="text"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           placeholder="Optional">
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                                <textarea x-model="priceForm.notes" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                          placeholder="Additional notes about pricing..."></textarea>
                            </div>
                            <div class="flex space-x-3 mt-6">
                                <button type="submit" :disabled="submitting"
                                        class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <i class="fas fa-save mr-2"></i>
                                    <span x-text="submitting ? 'Saving...' : 'Save Price'"></span>
                                </button>
                                <button type="button" @click="completeRequest(selectedRequest.id)" :disabled="submitting"
                                        class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <i class="fas fa-check mr-2"></i>Complete & Notify
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-3">
                        <button x-show="selectedRequest && selectedRequest.status === 'pending'" @click="assignRequest(selectedRequest.id)"
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Assign to Me
                        </button>

                        <button @click="showModal = false"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function partsPricingApp() {
            return {
                // State
                currentView: 'dashboard',
                loading: false,
                loadingRequests: false,
                showModal: false,
                showBulkActions: false,
                selectedRequest: null,
                submitting: false,

                // Data
                requests: [],
                completedRequests: [],
                stats: { pending: 0, in_progress: 0, completed: 0, completed_today: 0, avg_time: '2.3h' },
                recentActivity: [],

                // Filters & Search
                activeFilter: 'all',
                searchQuery: '',
                sortBy: 'created_at',
                selectedRequests: [],

                // Forms
                priceForm: {
                    price: '',
                    supplier: '',
                    notes: ''
                },

                // Notifications
                notification: {
                    show: false,
                    type: 'success',
                    message: ''
                },

                // Computed
                get filteredRequests() {
                    let filtered = this.requests;

                    // Filter by status
                    if (this.activeFilter !== 'all') {
                        filtered = filtered.filter(r => r.status === this.activeFilter);
                    }

                    // Search
                    if (this.searchQuery) {
                        const query = this.searchQuery.toLowerCase();
                        filtered = filtered.filter(r =>
                            r.part_name.toLowerCase().includes(query) ||
                            r.customer_name.toLowerCase().includes(query) ||
                            r.plate_number.toLowerCase().includes(query) ||
                            r.invoice_id.toString().includes(query)
                        );
                    }

                    // Sort
                    filtered.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'part_name':
                                return a.part_name.localeCompare(b.part_name);
                            case 'customer_name':
                                return a.customer_name.localeCompare(b.customer_name);
                            case 'priority':
                                // Add priority logic here
                                return 0;
                            default:
                                return new Date(b.created_at) - new Date(a.created_at);
                        }
                    });

                    return filtered;
                },

                // Methods
                async init() {
                    await this.loadData();
                },

                getPageTitle() {
                    switch (this.currentView) {
                        case 'dashboard': return 'Parts Pricing Dashboard';
                        case 'requests': return 'Active Pricing Requests';
                        case 'completed': return 'Completed Requests';
                        default: return 'Parts Pricing Hub';
                    }
                },

                async loadData() {
                    this.loading = true;
                    try {
                        await Promise.all([
                            this.loadStats(),
                            this.loadRequests(),
                            this.loadCompletedRequests(),
                            this.loadRecentActivity()
                        ]);
                    } catch (error) {
                        this.showNotification('Error loading data', 'error');
                        console.error('Error loading data:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async loadStats() {
                    try {
                        const response = await fetch('api_part_pricing.php?action=stats');
                        const data = await response.json();
                        if (data.success) {
                            this.stats = { ...this.stats, ...data.stats };
                        }
                    } catch (error) {
                        console.error('Error loading stats:', error);
                    }
                },

                async loadRequests() {
                    this.loadingRequests = true;
                    try {
                        const response = await fetch('api_part_pricing.php?action=list&status=all&limit=100');
                        const data = await response.json();
                        if (data.success) {
                            this.requests = data.requests;
                        }
                    } catch (error) {
                        console.error('Error loading requests:', error);
                    } finally {
                        this.loadingRequests = false;
                    }
                },

                async loadCompletedRequests() {
                    try {
                        const response = await fetch('api_part_pricing.php?action=list&status=completed&limit=20');
                        const data = await response.json();
                        if (data.success) {
                            this.completedRequests = data.requests;
                        }
                    } catch (error) {
                        console.error('Error loading completed requests:', error);
                    }
                },

                async loadRecentActivity() {
                    try {
                        const response = await fetch('api_part_pricing.php?action=activity&limit=10');
                        const data = await response.json();
                        if (data.success) {
                            this.recentActivity = data.activities;
                        }
                    } catch (error) {
                        console.error('Error loading recent activity:', error);
                        this.recentActivity = [];
                    }
                },

                async refreshData() {
                    await this.loadData();
                    this.showNotification('Data refreshed successfully', 'success');
                },

                viewRequest(request) {
                    this.selectedRequest = request;
                    this.priceForm = {
                        price: request.final_price || '',
                        supplier: request.supplier || '',
                        notes: request.notes || ''
                    };
                    this.showModal = true;
                },

                async quickAssign(request) {
                    await this.assignRequest(request.id);
                },

                async assignRequest(requestId) {
                    this.loading = true;
                    try {
                        const response = await fetch('api_part_pricing.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=assign&request_id=${requestId}`
                        });
                        const data = await response.json();

                        if (data.success) {
                            await Promise.all([this.loadRequests(), this.loadRecentActivity()]);
                            this.showNotification('Request assigned successfully', 'success');
                            this.showModal = false;
                        } else {
                            this.showNotification(data.message || 'Failed to assign request', 'error');
                        }
                    } catch (error) {
                        this.showNotification('Error assigning request', 'error');
                        console.error('Error assigning request:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async submitPrice() {
                    if (!this.selectedRequest) {
                        this.showNotification('No request selected', 'error');
                        return;
                    }

                    // Validate price
                    const price = parseFloat(this.priceForm.price);
                    if (isNaN(price) || price < 0) {
                        this.showNotification('Please enter a valid price', 'error');
                        return;
                    }

                    this.submitting = true;
                    try {
                        const response = await fetch('api_part_pricing.php?action=update_price', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: this.selectedRequest.id,
                                final_price: price,
                                notes: this.priceForm.notes || ''
                            })
                        });
                        const data = await response.json();

                        if (data.success) {
                            await Promise.all([this.loadRequests(), this.loadRecentActivity()]);
                            this.showNotification('Price updated successfully', 'success');
                            this.priceForm = { price: '', supplier: '', notes: '' };
                            this.showModal = false; // Close modal after successful update
                        } else {
                            this.showNotification(data.message || 'Failed to update price', 'error');
                        }
                    } catch (error) {
                        this.showNotification('Error updating price', 'error');
                        console.error('Error updating price:', error);
                    } finally {
                        this.submitting = false;
                    }
                },

                async completeRequest(requestId) {
                    this.submitting = true;
                    try {
                        const response = await fetch('api_part_pricing.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=complete&request_id=${requestId}`
                        });
                        const data = await response.json();

                        if (data.success) {
                            await Promise.all([this.loadRequests(), this.loadStats(), this.loadRecentActivity()]);
                            this.showNotification('Request completed successfully', 'success');
                            this.showModal = false;
                        } else {
                            this.showNotification(data.message || 'Failed to complete request', 'error');
                        }
                    } catch (error) {
                        this.showNotification('Error completing request', 'error');
                        console.error('Error completing request:', error);
                    } finally {
                        this.submitting = false;
                    }
                },

                // Bulk operations
                toggleSelection(requestId) {
                    if (this.selectedRequests.includes(requestId)) {
                        this.selectedRequests = this.selectedRequests.filter(id => id !== requestId);
                    } else {
                        this.selectedRequests.push(requestId);
                    }
                },

                clearSelection() {
                    this.selectedRequests = [];
                },

                async bulkAssign() {
                    if (this.selectedRequests.length === 0) return;

                    this.loading = true;
                    try {
                        const promises = this.selectedRequests.map(id =>
                            fetch('api_part_pricing.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=assign&request_id=${id}`
                            })
                        );

                        const assignedCount = this.selectedRequests.length;
                        await Promise.all(promises);
                        await Promise.all([this.loadRequests(), this.loadRecentActivity()]);
                        this.selectedRequests = [];
                        this.showNotification(`${assignedCount} requests assigned successfully`, 'success');
                    } catch (error) {
                        this.showNotification('Error in bulk assignment', 'error');
                        console.error('Bulk assign error:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async bulkComplete() {
                    // Implementation for bulk complete
                    this.showNotification('Bulk complete not yet implemented', 'error');
                },

                togglePriority(request) {
                    // Toggle priority logic
                    request.priority = request.priority === 'high' ? 'normal' : 'high';
                    this.showNotification('Priority updated', 'success');
                },

                // Utility methods
                getStatusBadgeClass(status) {
                    switch (status) {
                        case 'pending': return 'bg-yellow-100 text-yellow-800';
                        case 'in_progress': return 'bg-blue-100 text-blue-800';
                        case 'completed': return 'bg-green-100 text-green-800';
                        case 'cancelled': return 'bg-red-100 text-red-800';
                        default: return 'bg-gray-100 text-gray-800';
                    }
                },

                getStatusIcon(status) {
                    switch (status) {
                        case 'pending': return 'fas fa-clock';
                        case 'in_progress': return 'fas fa-spinner';
                        case 'completed': return 'fas fa-check-circle';
                        case 'cancelled': return 'fas fa-times-circle';
                        default: return 'fas fa-question-circle';
                    }
                },

                getStatusText(status) {
                    switch (status) {
                        case 'pending': return 'Pending';
                        case 'in_progress': return 'In Progress';
                        case 'completed': return 'Completed';
                        case 'cancelled': return 'Cancelled';
                        default: return 'Unknown';
                    }
                },

                getActivityIcon(type) {
                    switch (type) {
                        case 'assigned': return 'fas fa-user-plus';
                        case 'completed': return 'fas fa-check';
                        case 'created': return 'fas fa-plus';
                        default: return 'fas fa-info-circle';
                    }
                },

                getActivityIconColor(type) {
                    switch (type) {
                        case 'assigned': return 'bg-blue-100 text-blue-600';
                        case 'completed': return 'bg-green-100 text-green-600';
                        case 'created': return 'bg-purple-100 text-purple-600';
                        default: return 'bg-gray-100 text-gray-600';
                    }
                },

                formatTime(dateString) {
                    const date = new Date(dateString);
                    const now = new Date();
                    const diffMs = now - date;
                    const diffMins = Math.floor(diffMs / (1000 * 60));
                    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

                    if (diffMins < 1) return 'Just now';
                    if (diffMins < 60) return `${diffMins}m ago`;
                    if (diffHours < 24) return `${diffHours}h ago`;
                    if (diffDays < 7) return `${diffDays}d ago`;

                    return date.toLocaleDateString();
                },

                showNotification(message, type = 'success') {
                    this.notification = { show: true, type, message };
                    setTimeout(() => {
                        this.notification.show = false;
                    }, 3000);
                }
            }
        }

        // Initialize the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            Alpine.data('partsPricingApp', partsPricingApp);
        });
    </script>
</body>
</html>