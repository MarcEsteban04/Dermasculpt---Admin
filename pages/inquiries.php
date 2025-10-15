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

// Get all contact messages with dermatologist reply information
$sql = "SELECT cm.*, 
        COUNT(ir.reply_id) as dermatologist_reply_count,
        MAX(ir.created_at) as last_dermatologist_reply_date
        FROM contact_messages cm 
        LEFT JOIN inquiry_replies ir ON cm.id = ir.original_message_id 
        $where_clause
        GROUP BY cm.id 
        ORDER BY COALESCE(MAX(ir.created_at), cm.created_at) DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to check for Gmail replies using simple IMAP
function checkGmailReplies($email, $createdAt) {
    static $gmailFetcher = null;
    static $gmailDisabled = false;
    
    // Skip Gmail checking if it's been disabled due to errors or manually disabled
    if ($gmailDisabled || file_exists('../config/gmail_disabled.tmp')) {
        return ['count' => 0, 'hasNew' => false, 'lastDate' => null];
    }
    
    if ($gmailFetcher === null) {
        try {
            require_once '../classes/SimpleGmailFetcher.php';
            $gmailFetcher = new SimpleGmailFetcher();
        } catch (Exception $e) {
            error_log("Gmail IMAP error: " . $e->getMessage());
            $gmailDisabled = true; // Disable for this request to avoid repeated failures
            return ['count' => 0, 'hasNew' => false, 'lastDate' => null];
        }
    }
    
    try {
        // Set a short timeout for IMAP calls in list view
        set_time_limit(8);
        
        $replies = $gmailFetcher->getEmailReplies($email, $createdAt);
        $hasNewReplies = false;
        $lastReplyDate = null;
        
        foreach ($replies as $reply) {
            if ($lastReplyDate === null || $reply['timestamp'] > strtotime($lastReplyDate)) {
                $lastReplyDate = $reply['date'];
            }
            // Consider replies from the last 24 hours as "new"
            if ($reply['timestamp'] > (time() - 86400)) {
                $hasNewReplies = true;
            }
        }
        
        set_time_limit(30); // Reset timeout
        
        return [
            'count' => count($replies),
            'hasNew' => $hasNewReplies,
            'lastDate' => $lastReplyDate
        ];
    } catch (Exception $e) {
        error_log("Error checking Gmail replies for $email: " . $e->getMessage());
        set_time_limit(30); // Reset timeout
        return ['count' => 0, 'hasNew' => false, 'lastDate' => null];
    }
}

// Add Gmail reply information to each message
foreach ($messages as &$message) {
    $gmailInfo = checkGmailReplies($message['email'], $message['created_at']);
    $message['gmail_reply_count'] = $gmailInfo['count'];
    $message['has_new_gmail_replies'] = $gmailInfo['hasNew'];
    $message['last_gmail_reply_date'] = $gmailInfo['lastDate'];
    $message['total_reply_count'] = $message['dermatologist_reply_count'] + $message['gmail_reply_count'];
    
    // Determine the most recent activity date
    $dates = array_filter([
        $message['created_at'],
        $message['last_dermatologist_reply_date'],
        $message['last_gmail_reply_date']
    ]);
    $message['last_activity_date'] = !empty($dates) ? max($dates) : $message['created_at'];
}
unset($message); // Break the reference

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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border-radius: 12px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .message-item:hover {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 10px 30px rgba(8, 145, 178, 0.15);
        }
        
        .message-item:active {
            transform: translateY(0) scale(0.99);
        }

        .message-item.unread {
            border-left: 5px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1);
        }

        .message-item.read {
            border-left: 5px solid #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.1);
        }

        .message-item.replied {
            border-left: 5px solid #10b981;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1);
        }

        .message-item.new-patient-reply {
            border-left: 5px solid #dc2626;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.2);
            animation: pulse-glow 2s infinite;
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 6px 20px rgba(220, 38, 38, 0.2);
            }
            50% {
                box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
            }
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

        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e3f2fd;
            border-top: 4px solid #2196f3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-text {
            font-size: 18px;
            color: #333;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .loading-subtext {
            font-size: 14px;
            color: #666;
            text-align: center;
            max-width: 300px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
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
            min-height: 600px;
        }

        .message-list {
            height: calc(100vh - 12rem);
            min-height: 400px;
            overflow-y: auto;
        }
        
        .main-content {
            overflow-y: auto;
            max-height: 100vh;
        }
        
        #mainContent {
            overflow-y: auto;
            height: calc(100vh - 4rem);
        }

        .message-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Custom Alert Styles */
        .custom-alert {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .custom-alert.show {
            opacity: 1;
            visibility: visible;
        }
        
        .custom-alert-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            max-width: 400px;
            width: 90%;
            transform: scale(0.8) translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .custom-alert.show .custom-alert-content {
            transform: scale(1) translateY(0);
        }
        
        .alert-success {
            border-top: 4px solid #10b981;
        }
        
        .alert-error {
            border-top: 4px solid #ef4444;
        }
        
        .alert-warning {
            border-top: 4px solid #f59e0b;
        }
        
        .alert-question {
            border-top: 4px solid #3b82f6;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-cyan-100 text-gray-800">
    <?php include '../components/sidebar.php'; ?>
    
    <div class="main-content flex flex-col min-h-screen">
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

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay">
            <div class="loading-spinner"></div>
            <div class="loading-text" id="loadingText">Loading Patient Inquiries</div>
            <div class="loading-subtext" id="loadingSubtext">
                Fetching inquiries and checking for email replies...<br>
                <small>This may take a few moments</small>
            </div>
            
            <!-- Progress Steps -->
            <div class="mt-6 w-full max-w-md">
                <div class="flex justify-between text-xs text-gray-500 mb-2">
                    <span id="step1" class="flex items-center">
                        <i class="fas fa-circle-notch fa-spin mr-1"></i>
                        Loading inquiries
                    </span>
                    <span id="step2" class="flex items-center opacity-50">
                        <i class="fas fa-circle mr-1"></i>
                        Checking Gmail
                    </span>
                    <span id="step3" class="flex items-center opacity-50">
                        <i class="fas fa-circle mr-1"></i>
                        Ready
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: 33%"></div>
                </div>
            </div>
        </div>

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
            <div class="bg-white rounded-2xl shadow-2xl email-interface flex border border-gray-100 overflow-hidden backdrop-blur-sm">
                <!-- Left Sidebar - Filters and Counts -->
                <div class="w-64 border-r border-gray-200 p-6 flex flex-col bg-gradient-to-b from-gray-50 to-white">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Inbox</h3>
                        
                        <!-- Status Filters -->
                        <div class="space-y-3">
                            <a href="?status=all&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 <?php echo $status_filter === 'all' ? 'bg-gradient-to-r from-cyan-100 to-blue-100 text-cyan-800 shadow-md' : 'hover:bg-gray-100 hover:shadow-sm'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-inbox mr-3 text-gray-600"></i>
                                    <span class="font-medium">All Messages</span>
                                </div>
                                <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['all']; ?></span>
                            </a>
                            
                            <a href="?status=unread&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 <?php echo $status_filter === 'unread' ? 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 shadow-md' : 'hover:bg-gray-100 hover:shadow-sm'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope mr-3 text-red-600"></i>
                                    <span class="font-medium">Unread</span>
                                </div>
                                <span class="bg-red-200 text-red-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['unread']; ?></span>
                            </a>
                            
                            <a href="?status=read&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 <?php echo $status_filter === 'read' ? 'bg-gradient-to-r from-yellow-100 to-amber-100 text-yellow-800 shadow-md' : 'hover:bg-gray-100 hover:shadow-sm'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope-open mr-3 text-yellow-600"></i>
                                    <span class="font-medium">Read</span>
                                </div>
                                <span class="bg-yellow-200 text-yellow-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['read']; ?></span>
                            </a>
                            
                            <a href="?status=replied&search=<?php echo urlencode($search_query); ?>" 
                               class="flex items-center justify-between p-4 rounded-xl transition-all duration-300 <?php echo $status_filter === 'replied' ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 shadow-md' : 'hover:bg-gray-100 hover:shadow-sm'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-reply mr-3 text-green-600"></i>
                                    <span class="font-medium">Replied</span>
                                </div>
                                <span class="bg-green-200 text-green-700 text-xs px-2 py-1 rounded-full"><?php echo $counts['replied']; ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <form method="GET" class="relative">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Search messages..." 
                                   class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-300 bg-white shadow-sm hover:shadow-md">
                            <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
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
                                <?php 
                                $hasNewPatientReply = $message['has_new_gmail_replies'];
                                $messageClass = $message['status'];
                                if ($hasNewPatientReply) {
                                    $messageClass = 'new-patient-reply';
                                }
                                ?>
                                <div class="message-item <?php echo $messageClass; ?> p-4 border-b border-gray-100" 
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
                                                
                                                <!-- Conversation indicators -->
                                                <?php if ($message['total_reply_count'] > 0): ?>
                                                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">
                                                        <i class="fas fa-comments mr-1"></i>
                                                        <?php echo $message['total_reply_count']; ?> replies
                                                        <?php if ($message['gmail_reply_count'] > 0): ?>
                                                            <i class="fas fa-envelope ml-1" title="Includes Gmail replies"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($hasNewPatientReply): ?>
                                                    <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full animate-pulse">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                                        New Patient Reply
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-gray-600 mb-1">
                                                <?php echo htmlspecialchars($message['email']); ?>
                                            </p>
                                            <p class="text-sm text-gray-800 message-preview">
                                                <?php echo htmlspecialchars($message['message']); ?>
                                            </p>
                                            
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <?php if ($message['status'] === 'unread'): ?>
                                                    <span class="inline-flex items-center px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full">
                                                        <i class="fas fa-robot mr-1"></i>
                                                        AI Ready
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($message['total_reply_count'] > 0 && $message['last_activity_date']): ?>
                                                    <span class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Last activity: <?php echo timeAgo($message['last_activity_date']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ml-4 text-right flex-shrink-0">
                                            <p class="text-xs text-gray-500">
                                                <?php 
                                                $displayDate = $message['last_activity_date'] ? $message['last_activity_date'] : $message['created_at'];
                                                echo timeAgo($displayDate); 
                                                ?>
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

    <!-- Custom Alert Modal -->
    <div id="customAlert" class="custom-alert">
        <div class="custom-alert-content">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div id="alertIcon" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center mr-4">
                        <!-- Icon will be inserted here -->
                    </div>
                    <div class="flex-1">
                        <h3 id="alertTitle" class="text-lg font-semibold text-gray-900"></h3>
                        <p id="alertMessage" class="text-sm text-gray-600 mt-1"></p>
                    </div>
                </div>
                <div id="alertButtons" class="flex justify-end space-x-3">
                    <!-- Buttons will be inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Message Detail Modal -->
    <div id="messageModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
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
            // Show loading overlay before refresh
            showInquiriesLoading();
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
            
            // Find the clicked button and show loading only on that button
            const clickedButton = event.target.closest('.ai-bulk-btn');
            const originalContent = clickedButton.innerHTML;
            
            // Disable only the clicked button and show loading
            clickedButton.disabled = true;
            clickedButton.innerHTML = '<div class="flex items-center"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing...</div>';
            clickedButton.setAttribute('data-original', originalContent);
            
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
                // Re-enable only the clicked button and restore original content
                setTimeout(() => {
                    clickedButton.disabled = false;
                    clickedButton.innerHTML = clickedButton.getAttribute('data-original');
                    clickedButton.removeAttribute('data-original');
                }, 1000);
            });
        }

        function exportInsights() {
            const content = document.getElementById('aiInsightsContent').textContent;
            const title = document.getElementById('aiInsightsTitle').textContent;
            
            if (!content || content.includes('Generating AI insights')) {
                CustomAlert.fire('Error!', 'No insights to export. Please generate insights first.', 'error');
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
            
            CustomAlert.fire({
                title: 'Exported!',
                text: 'AI insights have been exported to a text file.',
                icon: 'success',
                timer: 2000
            });
        }

        function markAllAsRead() {
            CustomAlert.fire({
                title: 'Mark All as Read?',
                text: 'This will mark all unread messages as read.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark all as read!',
                onConfirm: function() {
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
                            CustomAlert.fire({
                                title: 'Success!',
                                text: 'All messages marked as read.',
                                icon: 'success',
                                onConfirm: () => window.location.reload()
                            });
                        } else {
                            CustomAlert.fire('Error!', data.message || 'Failed to mark messages as read.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        CustomAlert.fire('Error!', 'An error occurred while processing your request.', 'error');
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
                        
                        // Execute any scripts in the loaded content
                        const scripts = content.querySelectorAll('script');
                        scripts.forEach(script => {
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.head.appendChild(newScript);
                            document.head.removeChild(newScript);
                        });
                        
                        // Update UI in real-time if message was marked as read
                        if (data.message && data.message.status === 'read') {
                            updateMessageStatusInList(messageId, 'read');
                        }
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
        
        function updateMessageStatusInList(messageId, newStatus) {
            const messageItem = document.querySelector(`[onclick="openMessageModal(${messageId})"]`);
            if (messageItem) {
                // Remove old status classes
                messageItem.classList.remove('unread', 'read', 'replied');
                // Add new status class
                messageItem.classList.add(newStatus);
                
                // Update status badge
                const statusBadge = messageItem.querySelector('.px-2.py-1.text-xs.rounded-full');
                if (statusBadge) {
                    statusBadge.className = 'ml-2 px-2 py-1 text-xs rounded-full';
                    switch(newStatus) {
                        case 'unread':
                            statusBadge.className += ' bg-red-100 text-red-800';
                            break;
                        case 'read':
                            statusBadge.className += ' bg-yellow-100 text-yellow-800';
                            break;
                        case 'replied':
                            statusBadge.className += ' bg-green-100 text-green-800';
                            break;
                    }
                    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                }
                
                // Update icon
                const icon = messageItem.querySelector('i.fas');
                if (icon) {
                    icon.className = 'fas mr-2 text-sm';
                    switch(newStatus) {
                        case 'unread':
                            icon.className += ' fa-envelope';
                            break;
                        case 'read':
                            icon.className += ' fa-envelope-open';
                            break;
                        case 'replied':
                            icon.className += ' fa-reply';
                            break;
                    }
                }
            }
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
                CustomAlert.fire('Error!', 'Please enter a reply message.', 'error');
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
                    // Update message status to replied in real-time
                    updateMessageStatusInList(messageId, 'replied');
                    
                    CustomAlert.fire({
                        title: 'Success!',
                        text: 'Reply sent successfully!',
                        icon: 'success',
                        onConfirm: () => {
                            closeMessageModal();
                            // Don't reload the entire page, just refresh the counts
                            updateMessageCounts();
                        }
                    });
                } else {
                    CustomAlert.fire('Error!', data.message || 'Failed to send reply.', 'error');
                    replyBtn.disabled = false;
                    replyBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Reply';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                CustomAlert.fire('Error!', 'An error occurred while sending the reply.', 'error');
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

        // Function to update message counts without page reload
        function updateMessageCounts() {
            fetch('get_message_counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update count badges
                        const counts = data.counts;
                        document.querySelector('a[href*="status=all"] .bg-gray-200').textContent = counts.all || 0;
                        document.querySelector('a[href*="status=unread"] .bg-red-200').textContent = counts.unread || 0;
                        document.querySelector('a[href*="status=read"] .bg-yellow-200').textContent = counts.read || 0;
                        document.querySelector('a[href*="status=replied"] .bg-green-200').textContent = counts.replied || 0;
                    }
                })
                .catch(error => console.error('Error updating counts:', error));
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMessageModal();
            }
        });
        
        // AI Assistant functions for modal
        window.currentAISuggestion = '';
        
        window.toggleAIAssistant = function() {
            const panel = document.getElementById('aiAssistantPanel');
            const btn = document.getElementById('aiToggleBtn');
            
            if (panel && panel.classList.contains('hidden')) {
                panel.classList.remove('hidden');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-robot"></i> Hide AI';
                    btn.classList.add('bg-purple-700');
                }
            } else if (panel) {
                panel.classList.add('hidden');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-robot"></i> AI Assistant';
                    btn.classList.remove('bg-purple-700');
                }
                const responseArea = document.getElementById('aiResponseArea');
                if (responseArea) {
                    responseArea.classList.add('hidden');
                }
            }
        }
        
        // Global AI suggestion functions that will be overridden by modal content
        window.getAISuggestion = function(action) {
            console.log('AI Suggestion requested:', action);
            // This will be overridden when modal loads
        };
        
        window.getCustomAISuggestion = function() {
            console.log('Custom AI Suggestion requested');
            // This will be overridden when modal loads
        };
        
        window.useAISuggestion = function() {
            console.log('Use AI Suggestion requested');
            // This will be overridden when modal loads
        };
        
        window.appendAISuggestion = function() {
            console.log('Append AI Suggestion requested');
            // This will be overridden when modal loads
        };
        
        window.deleteMessage = function(messageId) {
            console.log('Delete message requested:', messageId);
            // This will be overridden when modal loads
        };
        
        window.getGrammarFixer = function() {
            console.log('Grammar fixer requested');
            // This will be overridden when modal loads
        };
        
        // Custom Alert System
        function showCustomAlert(type, title, message, options = {}) {
            const alert = document.getElementById('customAlert');
            const alertContent = alert.querySelector('.custom-alert-content');
            const alertIcon = document.getElementById('alertIcon');
            const alertTitle = document.getElementById('alertTitle');
            const alertMessage = document.getElementById('alertMessage');
            const alertButtons = document.getElementById('alertButtons');
            
            // Remove existing type classes
            alertContent.classList.remove('alert-success', 'alert-error', 'alert-warning', 'alert-question');
            
            // Set icon and colors based on type
            let iconHtml = '';
            let iconBg = '';
            
            switch(type) {
                case 'success':
                    iconHtml = '<i class="fas fa-check text-white"></i>';
                    iconBg = 'bg-green-500';
                    alertContent.classList.add('alert-success');
                    break;
                case 'error':
                    iconHtml = '<i class="fas fa-times text-white"></i>';
                    iconBg = 'bg-red-500';
                    alertContent.classList.add('alert-error');
                    break;
                case 'warning':
                    iconHtml = '<i class="fas fa-exclamation-triangle text-white"></i>';
                    iconBg = 'bg-yellow-500';
                    alertContent.classList.add('alert-warning');
                    break;
                case 'question':
                    iconHtml = '<i class="fas fa-question text-white"></i>';
                    iconBg = 'bg-blue-500';
                    alertContent.classList.add('alert-question');
                    break;
            }
            
            alertIcon.className = `flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center mr-4 ${iconBg}`;
            alertIcon.innerHTML = iconHtml;
            alertTitle.textContent = title;
            alertMessage.textContent = message;
            
            // Create buttons
            alertButtons.innerHTML = '';
            
            if (options.showCancelButton) {
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors';
                cancelBtn.textContent = options.cancelButtonText || 'Cancel';
                cancelBtn.onclick = () => {
                    hideCustomAlert();
                    if (options.onCancel) options.onCancel();
                };
                alertButtons.appendChild(cancelBtn);
            }
            
            const confirmBtn = document.createElement('button');
            let confirmClass = 'px-4 py-2 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 transition-colors ';
            
            switch(type) {
                case 'success':
                    confirmClass += 'bg-green-600 hover:bg-green-700 focus:ring-green-500';
                    break;
                case 'error':
                    confirmClass += 'bg-red-600 hover:bg-red-700 focus:ring-red-500';
                    break;
                case 'warning':
                    confirmClass += 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500';
                    break;
                case 'question':
                    confirmClass += 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500';
                    break;
            }
            
            confirmBtn.className = confirmClass;
            confirmBtn.textContent = options.confirmButtonText || 'OK';
            confirmBtn.onclick = () => {
                hideCustomAlert();
                if (options.onConfirm) options.onConfirm();
            };
            alertButtons.appendChild(confirmBtn);
            
            // Show alert
            alert.classList.add('show');
            
            // Auto-hide for simple alerts
            if (options.timer && !options.showCancelButton) {
                setTimeout(() => {
                    hideCustomAlert();
                    if (options.onConfirm) options.onConfirm();
                }, options.timer);
            }
        }
        
        function hideCustomAlert() {
            const alert = document.getElementById('customAlert');
            alert.classList.remove('show');
        }
        
        // Custom alert wrapper functions
        window.CustomAlert = {
            fire: function(options) {
                if (typeof options === 'string') {
                    // Simple usage: CustomAlert.fire('Success!', 'Message', 'success')
                    const title = arguments[0];
                    const message = arguments[1] || '';
                    const type = arguments[2] || 'success';
                    showCustomAlert(type, title, message);
                } else {
                    // Object usage: CustomAlert.fire({title: 'Title', text: 'Message', icon: 'success'})
                    showCustomAlert(
                        options.icon || 'success',
                        options.title || '',
                        options.text || '',
                        {
                            showCancelButton: options.showCancelButton,
                            confirmButtonText: options.confirmButtonText,
                            cancelButtonText: options.cancelButtonText,
                            timer: options.timer,
                            onConfirm: options.onConfirm,
                            onCancel: options.onCancel
                        }
                    );
                }
            }
        };
        
        // Click outside to close
        document.getElementById('customAlert').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCustomAlert();
            }
        });

        // Add smooth animations and enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to message items
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
            });
            
            // Add loading states to buttons
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.disabled && this.type !== 'button') {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                        this.disabled = true;
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.disabled = false;
                        }, 2000);
                    }
                });
            });
        });

        // Loading overlay control with progress steps
        document.addEventListener('DOMContentLoaded', function() {
            // Remove temporary loading overlay from sidebar navigation if it exists
            const tempOverlay = document.getElementById('tempLoadingOverlay');
            if (tempOverlay) {
                tempOverlay.remove();
            }
            
            const loadingOverlay = document.getElementById('loadingOverlay');
            const progressBar = document.getElementById('progressBar');
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            
            // Step 1: Loading inquiries (already active)
            
            // Step 2: Checking Gmail (simulate after DOM is loaded)
            setTimeout(function() {
                if (step1 && step2 && progressBar) {
                    // Complete step 1
                    step1.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Loading inquiries';
                    step1.classList.remove('opacity-50');
                    
                    // Start step 2
                    step2.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Checking Gmail';
                    step2.classList.remove('opacity-50');
                    progressBar.style.width = '66%';
                }
            }, 500);
            
            // Step 3: Ready (after page fully loads)
            window.addEventListener('load', function() {
                setTimeout(function() {
                    if (step2 && step3 && progressBar) {
                        // Complete step 2
                        step2.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Checking Gmail';
                        
                        // Start step 3
                        step3.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Ready';
                        step3.classList.remove('opacity-50');
                        progressBar.style.width = '100%';
                        
                        // Hide loading overlay after a brief moment
                        setTimeout(function() {
                            if (loadingOverlay) {
                                loadingOverlay.classList.add('fade-out');
                                
                                setTimeout(function() {
                                    loadingOverlay.style.display = 'none';
                                }, 500);
                            }
                        }, 800);
                    }
                }, 1200); // Wait for Gmail checking to complete
            });
        });

        // Show loading overlay when navigating to inquiries page
        window.showInquiriesLoading = function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                loadingOverlay.classList.remove('fade-out');
            }
        };

        // Hide loading overlay manually (can be called when Gmail fetching is complete)
        window.hideInquiriesLoading = function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('fade-out');
                setTimeout(function() {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }
        };
    </script>
</body>
</html>
