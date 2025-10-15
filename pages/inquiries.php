<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$sidebar_firstName = $_SESSION['first_name'];

$stmt = $conn->prepare("SELECT first_name, last_name, email, specialization, license_number, bio, profile_picture_url FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$derma = $result->fetch_assoc();

$sidebar_firstName = htmlspecialchars($derma['first_name'] ?? 'Dermatologist');
$profilePicturePath = isset($derma['profile_picture_url']) && !empty($derma['profile_picture_url']) ? '../' . htmlspecialchars($derma['profile_picture_url']) : 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query based on filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search_query)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all contact messages
$sql = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count messages by status
$counts = [
    'all' => 0,
    'unread' => 0,
    'read' => 0,
    'replied' => 0
];

$count_sql = "SELECT status, COUNT(*) as count FROM contact_messages GROUP BY status";
$count_result = $conn->query($count_sql);
while ($row = $count_result->fetch_assoc()) {
    $counts[$row['status']] = $row['count'];
    $counts['all'] += $row['count'];
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'unread':
            return 'bg-red-100 text-red-800';
        case 'read':
            return 'bg-yellow-100 text-yellow-800';
        case 'replied':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'unread':
            return 'fas fa-envelope';
        case 'read':
            return 'fas fa-envelope-open';
        case 'replied':
            return 'fas fa-reply';
        default:
            return 'fas fa-envelope';
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Inquiries - DermaSculpt</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📧</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 256px;
            --sidebar-width-collapsed: 80px;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        @import url('https://rsms.me/inter/inter.css');

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #a8a8a8;
            border-radius: 10px;
        }

        #sidebar {
            width: var(--sidebar-width);
            transition: width 0.3s ease-in-out;
        }

        #sidebar.collapsed {
            width: var(--sidebar-width-collapsed);
        }

        #sidebar.collapsed .sidebar-text,
        #sidebar.collapsed .sidebar-logo-text,
        #sidebar.collapsed .profile-info {
            display: none;
        }

        .main-content {
            transition: margin-left 0.3s ease-in-out;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
        }

        #sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-width-collapsed);
            width: calc(100% - var(--sidebar-width-collapsed));
        }

        .sidebar-link.active {
            background-color: #3b82f6;
            color: white;
        }

        .message-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .message-item:hover {
            background-color: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .message-item.unread {
            border-left: 4px solid #ef4444;
            background-color: #fef2f2;
        }

        .message-item.read {
            border-left: 4px solid #f59e0b;
        }

        .message-item.replied {
            border-left: 4px solid #10b981;
        }

        .modal {
            transition: visibility 0s linear 0.3s, opacity 0.3s ease-in-out;
            visibility: hidden;
            opacity: 0;
        }

        .modal:not(.hidden) {
            transition: visibility 0s linear 0s;
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            transform: scale(0.95);
            opacity: 0;
        }

        .modal:not(.hidden) .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .email-interface {
            height: calc(100vh - 8rem);
        }

        .message-list {
            height: calc(100vh - 12rem);
            overflow-y: auto;
        }

        .message-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-cyan-100 text-gray-800">
    <?php include '../components/sidebar.php'; ?>
    
    <div class="main-content flex flex-col h-screen">
        <header class="bg-white shadow-sm flex items-center justify-between p-4 h-16 flex-shrink-0 z-30">
            <button id="sidebar-toggle" onclick="toggleSidebar()" class="text-cyan-600 hover:text-cyan-800">
                <i class="fas fa-bars fa-xl"></i>
            </button>
            <div class="relative">
                <button onclick="toggleDropdown()" class="flex items-center space-x-3">
                    <span class="hidden sm:inline text-sm font-medium text-cyan-700">Dr. <?php echo $sidebar_firstName; ?></span>
                    <img class="h-10 w-10 rounded-full object-cover border-2 border-transparent hover:border-cyan-500" src="<?php echo $profilePicturePath; ?>" alt="User avatar">
                </button>
                <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                    <a href="profile.php" class="block px-4 py-2 text-sm text-cyan-700 hover:bg-cyan-50">Your Profile</a>
                    <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-cyan-50">Logout</a>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 overflow-hidden" id="mainContent">
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-extrabold text-cyan-700">Patient Inquiries</h2>
                    <p class="text-cyan-800 mt-1">Manage and respond to patient contact messages.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="toggleAIInsights()" id="aiInsightsBtn" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-brain"></i> AI Insights
                    </button>
                    <button onclick="refreshInquiries()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button onclick="markAllAsRead()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 shadow-lg">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </div>

            <!-- AI Insights Panel -->
            <div id="aiInsightsPanel" class="hidden mb-6 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-purple-800 flex items-center">
                        <i class="fas fa-brain mr-2"></i>
                        AI Inquiry Insights
                    </h3>
                    <button onclick="toggleAIInsights()" class="text-purple-600 hover:text-purple-800">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <button 
                        onclick="generateBulkInsights('priority_analysis')" 
                        class="ai-bulk-btn bg-red-100 text-red-800 p-4 rounded-lg hover:bg-red-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="font-semibold">Priority Analysis</span>
                        </div>
                        <p class="text-sm">Identify urgent inquiries that need immediate attention</p>
                    </button>
                    
                    <button 
                        onclick="generateBulkInsights('common_concerns')" 
                        class="ai-bulk-btn bg-blue-100 text-blue-800 p-4 rounded-lg hover:bg-blue-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-chart-pie mr-2"></i>
                            <span class="font-semibold">Common Concerns</span>
                        </div>
                        <p class="text-sm">Analyze trending topics and frequent patient concerns</p>
                    </button>
                    
                    <button 
                        onclick="generateBulkInsights('response_suggestions')" 
                        class="ai-bulk-btn bg-green-100 text-green-800 p-4 rounded-lg hover:bg-green-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>
                            <span class="font-semibold">Response Templates</span>
                        </div>
                        <p class="text-sm">Generate template responses for common inquiries</p>
                    </button>
                    
                    <button 
                        onclick="generateBulkInsights('sentiment_analysis')" 
                        class="ai-bulk-btn bg-yellow-100 text-yellow-800 p-4 rounded-lg hover:bg-yellow-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-smile mr-2"></i>
                            <span class="font-semibold">Sentiment Analysis</span>
                        </div>
                        <p class="text-sm">Understand patient emotions and satisfaction levels</p>
                    </button>
                    
                    <button 
                        onclick="generateBulkInsights('follow_up_recommendations')" 
                        class="ai-bulk-btn bg-indigo-100 text-indigo-800 p-4 rounded-lg hover:bg-indigo-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-calendar-check mr-2"></i>
                            <span class="font-semibold">Follow-up Recommendations</span>
                        </div>
                        <p class="text-sm">Suggest which patients need follow-up appointments</p>
                    </button>
                    
                    <button 
                        onclick="generateBulkInsights('workflow_optimization')" 
                        class="ai-bulk-btn bg-purple-100 text-purple-800 p-4 rounded-lg hover:bg-purple-200 text-left transition-colors"
                    >
                        <div class="flex items-center mb-2">
                            <i class="fas fa-cogs mr-2"></i>
                            <span class="font-semibold">Workflow Tips</span>
                        </div>
                        <p class="text-sm">Get suggestions to improve inquiry management</p>
                    </button>
                </div>
                
                <!-- AI Results Area -->
                <div id="aiInsightsResults" class="hidden">
                    <div class="bg-white border border-purple-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold text-gray-800" id="aiInsightsTitle">AI Analysis Results</h4>
                            <button onclick="exportInsights()" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700">
                                <i class="fas fa-download mr-1"></i>Export
                            </button>
                        </div>
                        <div id="aiInsightsContent" class="text-sm text-gray-700 whitespace-pre-wrap"></div>
                    </div>
                </div>
            </div>

            <!-- Email Interface -->
            <div class="bg-white rounded-xl shadow-lg email-interface flex">
                <!-- Left Sidebar - Filters and Counts -->
                <div class="w-64 border-r border-gray-200 p-4 flex flex-col">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Inbox</h3>
                        
                        <!-- Status Filters -->
                        <div class="space-y-2">
                            <a href="?status=all&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-3 rounded-lg transition-colors <?php echo $status_filter === 'all' ? 'bg-cyan-100 text-cyan-800' : 'hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-inbox mr-3 text-gray-600"></i>
                                    <span class="font-medium">All Messages</span>
                                </div>
                                <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['all']; ?></span>
                            </a>
                            
                            <a href="?status=unread&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-3 rounded-lg transition-colors <?php echo $status_filter === 'unread' ? 'bg-red-100 text-red-800' : 'hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope mr-3 text-red-600"></i>
                                    <span class="font-medium">Unread</span>
                                </div>
                                <span class="bg-red-200 text-red-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['unread']; ?></span>
                            </a>
                            
                            <a href="?status=read&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-3 rounded-lg transition-colors <?php echo $status_filter === 'read' ? 'bg-yellow-100 text-yellow-800' : 'hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope-open mr-3 text-yellow-600"></i>
                                    <span class="font-medium">Read</span>
                                </div>
                                <span class="bg-yellow-200 text-yellow-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['read']; ?></span>
                            </a>
                            
                            <a href="?status=replied&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-3 rounded-lg transition-colors <?php echo $status_filter === 'replied' ? 'bg-green-100 text-green-800' : 'hover:bg-gray-100'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-reply mr-3 text-green-600"></i>
                                    <span class="font-medium">Replied</span>
                                </div>
                                <span class="bg-green-200 text-green-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['replied']; ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-4">
                        <form method="GET" class="relative">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search messages..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </form>
                    </div>
                </div>

                <!-- Message List -->
                <div class="flex-1 flex flex-col">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <?php 
                                $filter_titles = [
                                    'all' => 'All Messages',
                                    'unread' => 'Unread Messages',
                                    'read' => 'Read Messages',
                                    'replied' => 'Replied Messages'
                                ];
                                echo $filter_titles[$status_filter];
                                ?>
                                <span class="text-sm text-gray-500 ml-2">(<?php echo count($messages); ?>)</span>
                            </h3>
                        </div>
                    </div>

                    <div class="message-list flex-1">
                        <?php if (empty($messages)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-500">
                                <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                                <h3 class="text-xl font-semibold mb-2">No messages found</h3>
                                <p class="text-center">
                                    <?php if (!empty($search_query)): ?>
                                        No messages match your search criteria.
                                    <?php else: ?>
                                        No messages in this category yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?php echo $message['status']; ?> p-4 border-b border-gray-100" 
                                     onclick="openMessageModal(<?php echo $message['id']; ?>)">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center mb-2">
                                                <i class="<?php echo getStatusIcon($message['status']); ?> mr-2 text-sm"></i>
                                                <h4 class="font-semibold text-gray-900 truncate">
                                                    <?php echo htmlspecialchars($message['name']); ?>
                                                </h4>
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full <?php echo getStatusBadgeClass($message['status']); ?>">
                                                    <?php echo ucfirst($message['status']); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <?php echo htmlspecialchars($message['email']); ?>
                                            </p>
                                            <p class="text-sm text-gray-800 message-preview">
                                                <?php echo htmlspecialchars($message['message']); ?>
                                            </p>
                                            <?php if ($message['status'] === 'unread'): ?>
                                                <div class="mt-2">
                                                    <span class="inline-flex items-center px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full">
                                                        <i class="fas fa-robot mr-1"></i>
                                                        AI Ready
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4 text-right flex-shrink-0">
                                            <p class="text-xs text-gray-500">
                                                <?php echo timeAgo($message['created_at']); ?>
                                            </p>
                                            <?php if ($message['phone_number']): ?>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <i class="fas fa-phone mr-1"></i>
                                                    <?php echo htmlspecialchars($message['phone_number']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Message Detail Modal -->
    <div id="messageModal" class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeMessageModal()"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Message Details</h3>
                <button onclick="closeMessageModal()" class="text-gray-500 hover:text-gray-800 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="messageModalContent" class="flex-1 overflow-y-auto">
                <div class="flex items-center justify-center h-32">
                    <i class="fas fa-spinner fa-spin fa-2x text-cyan-500"></i>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/sidebar.js"></script>
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth >= 1024) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        }

        function refreshInquiries() {
            window.location.reload();
        }

        function toggleAIInsights() {
            const panel = document.getElementById('aiInsightsPanel');
            const btn = document.getElementById('aiInsightsBtn');
            
            if (panel.classList.contains('hidden')) {
                panel.classList.remove('hidden');
                btn.innerHTML = '<i class="fas fa-brain"></i> Hide AI Insights';
                btn.classList.add('bg-purple-700');
            } else {
                panel.classList.add('hidden');
                btn.innerHTML = '<i class="fas fa-brain"></i> AI Insights';
                btn.classList.remove('bg-purple-700');
                document.getElementById('aiInsightsResults').classList.add('hidden');
            }
        }

        function generateBulkInsights(analysisType) {
            const buttons = document.querySelectorAll('.ai-bulk-btn');
            const resultsArea = document.getElementById('aiInsightsResults');
            const titleElement = document.getElementById('aiInsightsTitle');
            const contentElement = document.getElementById('aiInsightsContent');
            
            // Disable all buttons and show loading
            buttons.forEach(btn => {
                btn.disabled = true;
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<div class="flex items-center"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing...</div>';
                btn.setAttribute('data-original', originalContent);
            });
            
            resultsArea.classList.remove('hidden');
            contentElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating AI insights...';
            
            // Set title based on analysis type
            const titles = {
                'priority_analysis': 'Priority Analysis Results',
                'common_concerns': 'Common Concerns Analysis',
                'response_suggestions': 'Response Template Suggestions',
                'sentiment_analysis': 'Sentiment Analysis Results',
                'follow_up_recommendations': 'Follow-up Recommendations',
                'workflow_optimization': 'Workflow Optimization Tips'
            };
            titleElement.textContent = titles[analysisType] || 'AI Analysis Results';
            
            // Create bulk analysis prompt
            const bulkPrompts = {
                'priority_analysis': 'Analyze all the patient inquiries and identify which ones require urgent attention. Consider factors like: medical urgency, patient anxiety level, time sensitivity, and potential complications. Provide a prioritized list with explanations.',
                'common_concerns': 'Analyze all patient inquiries to identify the most common concerns, trending topics, and frequently asked questions. Provide insights on patterns and suggest how to address these efficiently.',
                'response_suggestions': 'Based on the common patient inquiries, create template responses that can be customized for similar cases. Include professional, empathetic, and appointment-focused templates.',
                'sentiment_analysis': 'Analyze the emotional tone and sentiment of patient inquiries. Identify patients who seem anxious, frustrated, or satisfied. Provide insights on overall patient satisfaction and areas for improvement.',
                'follow_up_recommendations': 'Review patient inquiries and recommend which patients would benefit from follow-up appointments, additional consultations, or proactive outreach based on their concerns.',
                'workflow_optimization': 'Analyze the inquiry management workflow and suggest improvements for efficiency, response time, and patient satisfaction. Include recommendations for better organization and prioritization.'
            };
            
            fetch('../backend/ai_inquiry_assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'custom',
                    message_id: 0, // Bulk analysis doesn't need specific message
                    custom_prompt: bulkPrompts[analysisType]
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    contentElement.textContent = data.suggestion;
                } else {
                    contentElement.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' + (data.error || 'Failed to generate insights') + '</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contentElement.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while generating insights.</span>';
            })
            .finally(() => {
                // Re-enable buttons and restore original content
                setTimeout(() => {
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = btn.getAttribute('data-original');
                        btn.removeAttribute('data-original');
                    });
                }, 1000);
            });
        }

        function exportInsights() {
            const content = document.getElementById('aiInsightsContent').textContent;
            const title = document.getElementById('aiInsightsTitle').textContent;
            
            if (!content || content.includes('Generating AI insights')) {
                Swal.fire('Error!', 'No insights to export. Please generate insights first.', 'error');
                return;
            }
            
            const blob = new Blob([`${title}\n${'='.repeat(title.length)}\n\n${content}`], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `AI_Insights_${new Date().toISOString().split('T')[0]}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            Swal.fire({
                title: 'Exported!',
                text: 'AI insights have been exported to a text file.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }

        function markAllAsRead() {
            Swal.fire({
                title: 'Mark All as Read?',
                text: 'This will mark all unread messages as read.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0891b2',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, mark all read'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('inquiry_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_all_read'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success!', 'All messages marked as read.', 'success')
                                .then(() => window.location.reload());
                        } else {
                            Swal.fire('Error!', data.message || 'Failed to mark messages as read.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred while processing your request.', 'error');
                    });
                }
            });
        }

        function openMessageModal(messageId) {
            const modal = document.getElementById('messageModal');
            const content = document.getElementById('messageModalContent');
            
            modal.classList.remove('hidden');
            
            // Show loading
            content.innerHTML = `
                <div class="flex items-center justify-center h-32">
                    <i class="fas fa-spinner fa-spin fa-2x text-cyan-500"></i>
                </div>
            `;
            
            // Fetch message details
            fetch(`get_message_details.php?id=${messageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = data.html;
                    } else {
                        content.innerHTML = `
                            <div class="p-6 text-center text-red-600">
                                <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
                                <p>Error loading message details: ${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div class="p-6 text-center text-red-600">
                            <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
                            <p>An error occurred while loading the message.</p>
                        </div>
                    `;
                });
        }

        function closeMessageModal() {
            document.getElementById('messageModal').classList.add('hidden');
        }

        function markAsRead(messageId) {
            fetch('inquiry_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    message_id: messageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI without full reload
                    const messageItem = document.querySelector(`[onclick="openMessageModal(${messageId})"]`);
                    if (messageItem) {
                        messageItem.classList.remove('unread');
                        messageItem.classList.add('read');
                        const statusBadge = messageItem.querySelector('.bg-red-100');
                        if (statusBadge) {
                            statusBadge.className = 'ml-2 px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800';
                            statusBadge.textContent = 'Read';
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function sendReply(messageId) {
            const replyText = document.getElementById('replyMessage').value.trim();
            const replyBtn = document.getElementById('sendReplyBtn');
            
            if (!replyText) {
                Swal.fire('Error!', 'Please enter a reply message.', 'error');
                return;
            }
            
            replyBtn.disabled = true;
            replyBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
            
            fetch('send_reply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message_id: messageId,
                    reply_message: replyText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', 'Reply sent successfully!', 'success')
                        .then(() => {
                            closeMessageModal();
                            window.location.reload();
                        });
                } else {
                    Swal.fire('Error!', data.message || 'Failed to send reply.', 'error');
                    replyBtn.disabled = false;
                    replyBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Reply';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while sending the reply.', 'error');
                replyBtn.disabled = false;
                replyBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Reply';
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('user-dropdown');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick || button.onclick.toString().indexOf('toggleDropdown') === -1) {
                dropdown.classList.add('hidden');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMessageModal();
            }
        });
    </script>
</body>
</html>
