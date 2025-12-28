<?php
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Only allow managers and part collectors to access messaging
$allowed_roles = ['manager', 'user', 'parts_collection_manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../index.php');
    exit;
}

// Get user info for display
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Get list of users for messaging (managers and part collectors)
$users_stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE role IN ('manager', 'user', 'parts_collection_manager') AND id != ? ORDER BY role, username");
$users_stmt->execute([$_SESSION['user_id']]);
$available_users = $users_stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შეტყობინებები - ავტოსერვისი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <!-- Georgian Fonts -->
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial/" />
    <link rel="stylesheet" href="https://web-fonts.ge/bpg-arial-caps/" />

    <style>
        body { font-family: 'BPG Arial', 'BPG Arial Caps'; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-6 min-h-screen overflow-x-hidden font-sans antialiased" x-data="messagesApp()">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="container mx-auto ml-0 md:ml-64">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">შეტყობინებები</h1>
            <button @click="showComposeModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                ახალი შეტყობინება
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Messages List -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h2 class="text-lg font-semibold mb-4">შეტყობინებები</h2>

                    <!-- Filter Tabs -->
                    <div class="flex mb-4 border-b">
                        <button @click="activeTab = 'inbox'" :class="activeTab === 'inbox' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'" class="px-4 py-2 font-medium">
                            შემოსული ({{ inboxMessages.length }})
                        </button>
                        <button @click="activeTab = 'sent'" :class="activeTab === 'sent' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500'" class="px-4 py-2 font-medium">
                            გაგზავნილი ({{ sentMessages.length }})
                        </button>
                    </div>

                    <!-- Messages List -->
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <template x-for="message in (activeTab === 'inbox' ? inboxMessages : sentMessages)" :key="message.id">
                            <div @click="selectMessage(message)" :class="selectedMessage?.id === message.id ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50'" class="p-3 border rounded-lg cursor-pointer">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-medium text-sm" x-text="activeTab === 'inbox' ? message.sender_name : message.recipient_name"></span>
                                    <span class="text-xs text-gray-500" x-text="formatDate(message.created_at)"></span>
                                </div>
                                <div class="text-sm text-gray-600 truncate" x-text="message.subject || 'უსათაურო'"></div>
                                <div class="text-xs text-gray-500 truncate mt-1" x-text="message.body.substring(0, 50) + '...'"></div>
                                <div x-show="activeTab === 'inbox' && !message.is_read" class="inline-block w-2 h-2 bg-blue-500 rounded-full mt-1"></div>
                            </div>
                        </template>
                        <div x-show="(activeTab === 'inbox' ? inboxMessages : sentMessages).length === 0" class="text-center text-gray-500 py-8">
                            შეტყობინებები არ არის
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message View -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <template x-if="selectedMessage">
                        <div>
                            <div class="border-b pb-4 mb-4">
                                <h3 class="text-xl font-semibold" x-text="selectedMessage.subject || 'უსათაურო'"></h3>
                                <div class="flex justify-between items-center mt-2 text-sm text-gray-600">
                                    <span x-show="activeTab === 'inbox'">
                                        გამომგზავნი: <span x-text="selectedMessage.sender_name"></span>
                                    </span>
                                    <span x-show="activeTab === 'sent'">
                                        მიმღები: <span x-text="selectedMessage.recipient_name"></span>
                                    </span>
                                    <span x-text="formatDate(selectedMessage.created_at)"></span>
                                </div>
                            </div>
                            <div class="prose max-w-none">
                                <pre class="whitespace-pre-wrap font-sans" x-text="selectedMessage.body"></pre>
                            </div>
                            <div x-show="activeTab === 'inbox' && !selectedMessage.is_read" class="mt-4">
                                <button @click="markAsRead(selectedMessage.id)" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                    მონიშნე როგორც წაკითხული
                                </button>
                            </div>
                        </div>
                    </template>
                    <div x-show="!selectedMessage" class="text-center text-gray-500 py-16">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        შეარჩიეთ შეტყობინება
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compose Modal -->
    <div x-show="showComposeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" x-cloak>
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">ახალი შეტყობინება</h2>
                    <button @click="showComposeModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="sendMessage">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">მიმღები</label>
                        <select x-model="newMessage.recipient_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">აირჩიეთ მიმღები</option>
                            <?php foreach ($available_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['role'] === 'manager' ? 'მენეჯერი' : ($user['role'] === 'parts_collection_manager' ? 'ნაწილების მენეჯერი' : 'ნაწილების მკრებელი'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">სათაური (არასავალდებულო)</label>
                        <input x-model="newMessage.subject" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">შეტყობინება</label>
                        <textarea x-model="newMessage.body" required rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-vertical"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showComposeModal = false" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            გაუქმება
                        </button>
                        <button type="submit" :disabled="sending" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50">
                            <span x-show="sending">იგზავნება...</span>
                            <span x-show="!sending">გაგზავნა</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function messagesApp() {
            return {
                activeTab: 'inbox',
                inboxMessages: [],
                sentMessages: [],
                selectedMessage: null,
                showComposeModal: false,
                sending: false,
                newMessage: {
                    recipient_id: '',
                    subject: '',
                    body: ''
                },

                init() {
                    this.loadMessages();
                },

                async loadMessages() {
                    try {
                        const response = await fetch('api_messages.php?action=get_messages');
                        const data = await response.json();
                        if (data.success) {
                            this.inboxMessages = data.inbox || [];
                            this.sentMessages = data.sent || [];
                        }
                    } catch (error) {
                        console.error('Error loading messages:', error);
                    }
                },

                selectMessage(message) {
                    this.selectedMessage = message;
                    // Auto-mark as read if it's inbox and unread
                    if (this.activeTab === 'inbox' && !message.is_read) {
                        this.markAsRead(message.id);
                    }
                },

                async markAsRead(messageId) {
                    try {
                        const response = await fetch('api_messages.php?action=mark_read', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ message_id: messageId })
                        });
                        const data = await response.json();
                        if (data.success) {
                            // Update local state
                            const message = this.inboxMessages.find(m => m.id === messageId);
                            if (message) {
                                message.is_read = true;
                            }
                            this.selectedMessage.is_read = true;
                        }
                    } catch (error) {
                        console.error('Error marking as read:', error);
                    }
                },

                async sendMessage() {
                    if (this.sending) return;

                    this.sending = true;
                    try {
                        const response = await fetch('api_messages.php?action=send', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.newMessage)
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showComposeModal = false;
                            this.newMessage = { recipient_id: '', subject: '', body: '' };
                            this.loadMessages();
                            alert('შეტყობინება გაგზავნილია!');
                        } else {
                            alert('შეტყობინების გაგზავნა ვერ მოხერხდა: ' + (data.message || 'უცნობი შეცდომა'));
                        }
                    } catch (error) {
                        console.error('Error sending message:', error);
                        alert('შეტყობინების გაგზავნა ვერ მოხერხდა');
                    } finally {
                        this.sending = false;
                    }
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('ka-GE', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
        }
    </script>
</body>
</html>