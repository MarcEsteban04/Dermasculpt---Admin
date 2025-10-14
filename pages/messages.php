<?php
session_start();

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/connection.php';

$dermatologistId = $_SESSION['dermatologist_id'];
$activeUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if ($activeUserId && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $updateStmt->bind_param("ii", $dermatologistId, $activeUserId);
    $updateStmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_new') {
    header('Content-Type: application/json');
    $userId = $_GET['user_id'];
    $stmt = $conn->prepare("SELECT * FROM messages WHERE receiver_id = ? AND sender_id = ? AND is_read = 0 AND sender_role = 'user' ORDER BY timestamp ASC");
    $stmt->bind_param("ii", $dermatologistId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $newMessages = $result->fetch_all(MYSQLI_ASSOC);
    if (!empty($newMessages)) {
        $messageIds = array_column($newMessages, 'message_id');
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $types = str_repeat('i', count($messageIds));
        $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE message_id IN ($placeholders)");
        $updateStmt->bind_param($types, ...$messageIds);
        $updateStmt->execute();
    }
    echo json_encode($newMessages);
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture_url FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$derma = $stmt->get_result()->fetch_assoc();

$sidebar_firstName = htmlspecialchars($derma['first_name'] ?? 'Dermatologist');
$profilePicturePath = (!empty($derma['profile_picture_url'])) ? '../' . htmlspecialchars($derma['profile_picture_url']) : 'https://placehold.co/100x100/E2E8F0/4A5568?text=Dr';

$sql = "
    SELECT
        u.user_id, u.first_name, u.last_name, u.profile_picture_url,
        last_msg.message_text AS last_message,
        last_msg.attachment_url AS last_attachment_url,
        last_msg.timestamp AS last_message_timestamp,
        last_msg.is_read AS last_message_is_read,
        last_msg.sender_role AS last_message_sender_role,
        COALESCE(unread.unread_count, 0) AS unread_count
    FROM (
        SELECT IF(m.sender_id = ? AND m.sender_role = 'dermatologist', m.receiver_id, m.sender_id) AS user_id,
               MAX(m.message_id) AS last_message_id
        FROM messages m WHERE (m.sender_id = ? AND m.sender_role = 'dermatologist') OR (m.receiver_id = ? AND m.receiver_role = 'dermatologist')
        GROUP BY user_id
    ) AS convos
    JOIN messages AS last_msg ON convos.last_message_id = last_msg.message_id
    JOIN users AS u ON convos.user_id = u.user_id
    LEFT JOIN (
        SELECT sender_id, COUNT(*) AS unread_count FROM messages
        WHERE receiver_id = ? AND is_read = 0 AND sender_role = 'user'
        GROUP BY sender_id
    ) AS unread ON unread.sender_id = convos.user_id
    ORDER BY last_msg.timestamp DESC;
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $dermatologistId, $dermatologistId, $dermatologistId, $dermatologistId);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$activeUser = null;
$messages = [];
if ($activeUserId) {
    if ($activeUserId) {
        $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
        $updateStmt->bind_param("ii", $dermatologistId, $activeUserId);
        $updateStmt->execute();
    }

    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, profile_picture_url FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $activeUserId);
    $stmt->execute();
    $activeUser = $stmt->get_result()->fetch_assoc();

    if ($activeUser) {
        $msgStmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY timestamp ASC");
        $msgStmt->bind_param("iiii", $dermatologistId, $activeUserId, $activeUserId, $dermatologistId);
        $msgStmt->execute();
        $messages = $msgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - DermaSculpt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
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

        .chat-grid {
            display: grid;
            grid-template-columns: 320px 1fr 360px;
            grid-template-rows: minmax(0, 1fr);
            min-height: 0;
        }

        .ai-panel-content p {
            margin-bottom: 0.75rem;
        }

        .ai-panel-content ul {
            list-style-type: disc;
            margin-left: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .ai-panel-content ol {
            list-style-type: decimal;
            margin-left: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .ai-panel-content strong,
        .ai-panel-content b {
            font-weight: 600;
        }

        .ai-panel-content code {
            background-color: #e5e7eb;
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
            font-family: monospace;
        }

        @media (max-width: 1280px) {
            .chat-grid {
                grid-template-columns: 320px 1fr;
            }

            .ai-panel {
                position: fixed;
                right: 0;
                top: 0;
                bottom: 0;
                transform: translateX(100%);
                transition: transform 0.3s ease-in-out;
                z-index: 45;
                width: 360px;
            }

            .ai-panel.open {
                transform: translateX(0);
            }
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .chat-grid {
                grid-template-columns: 1fr;
            }

            .conversation-list {
                display: <?php echo $activeUserId ? 'none' : 'flex'; ?>;
            }

            .chat-area,
            .ai-panel {
                display: <?php echo $activeUserId ? 'flex' : 'none'; ?>;
            }

            .ai-panel {
                position: static;
                transform: none;
                width: 100%;
                border-left: none;
                border-top: 1px solid #e5e7eb;
            }

            .chat-area {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .ai-panel {
                width: 100%;
            }
        }

        .sidebar-link.active {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-cyan-100 text-gray-800">
    <?php include '../components/sidebar.php'; ?>
    <div class="main-content flex flex-col h-screen">
        <header class="bg-white shadow-sm flex items-center justify-between p-4 h-16 flex-shrink-0 z-30">
            <div>
                <button id="sidebar-toggle" class="text-gray-600 hover:text-gray-800 lg:hidden"><i class="fas fa-bars fa-xl"></i></button>
                <?php if ($activeUserId): ?>
                    <button id="back-to-list" class="text-gray-600 hover:text-gray-800 hidden lg:hidden xl:hidden md:inline-block"><i class="fas fa-arrow-left fa-xl"></i></button>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($activeUserId): ?>
                    <button id="ai-toggle" class="text-gray-600 hover:text-blue-500 xl:hidden"><i class="fas fa-brain fa-xl"></i></button>
                <?php endif; ?>
                <div class="relative">
                    <button onclick="toggleDropdown()" class="flex items-center space-x-3">
                        <span class="hidden sm:inline text-sm font-medium text-gray-700">Dr. <?php echo $sidebar_firstName; ?></span>
                        <img class="h-10 w-10 rounded-full object-cover border-2 border-transparent hover:border-blue-500" src="<?php echo $profilePicturePath; ?>" alt="User avatar">
                    </button>
                    <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Your Profile</a>
                        <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="chat-grid flex-1 flex overflow-hidden">
            <div id="conversation-list-panel" class="conversation-list bg-white border-r border-gray-200 flex flex-col w-full lg:w-80 flex-shrink-0">
                <div class="p-4 border-b">
                    <h2 class="text-xl font-bold">Chats</h2>
                    <div class="relative mt-4"><input type="text" id="search-chat" placeholder="Search Patients..." class="w-full pl-10 pr-4 py-2 border rounded-lg"><i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i></div>
                </div>
                <div class="flex-1 overflow-y-auto" id="conversation-list-body">
                    <?php if (empty($conversations)): ?>
                        <p class="text-center text-gray-500 p-6" id="no-conversations-message">No conversations found.</p>
                    <?php else: ?>
                        <?php foreach ($conversations as $convo): ?>
                            <?php
                            $isUnread = ($activeUserId != $convo['user_id']) && ($convo['last_message_is_read'] == 0 && $convo['last_message_sender_role'] == 'user');
                            ?>
                            <a href="?user_id=<?php echo $convo['user_id']; ?>" class="flex items-center p-4 border-b hover:bg-gray-50 conversation-item <?php echo $activeUserId == $convo['user_id'] ? 'bg-blue-50' : ''; ?>" data-name="<?php echo htmlspecialchars(strtolower($convo['first_name'] . ' ' . $convo['last_name'])); ?>" data-user-id="<?php echo $convo['user_id']; ?>">
                                <img src="../../DermaSculpt_user/<?php echo $convo['profile_picture_url']; ?>" alt="avatar" class="w-12 h-12 rounded-full object-cover mr-4">
                                <div class="flex-1 overflow-hidden">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <h3 class="font-semibold truncate"><?php echo htmlspecialchars($convo['first_name'] . ' ' . $convo['last_name']); ?></h3>
                                            <span data-role="new-badge" class="ml-2">
                                                <?php if ($isUnread): ?>
                                                    <span class="bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">New</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 flex-shrink-0 ml-2" data-role="timestamp"><?php echo date("g:i A", strtotime($convo['last_message_timestamp'])); ?></p>
                                    </div>
                                    <div class="flex justify-between items-center mt-1">
                                        <p class="text-sm text-gray-600 truncate <?php if ($isUnread) echo 'font-bold'; ?>" data-role="last-message">
                                            <?php echo !empty($convo['last_attachment_url']) ? '<i class="fas fa-paperclip mr-1"></i> Attachment' : htmlspecialchars($convo['last_message']); ?>
                                        </p>
                                        <span data-role="unread-bubble" class="flex-shrink-0 ml-2">
                                            <?php if ($convo['unread_count'] > 0  && $activeUserId != $convo['user_id']): ?>
                                                <span class="bg-blue-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $convo['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-area flex flex-col bg-gray-100 flex-1 min-w-0">
                <?php if ($activeUser): ?>
                    <div class="flex items-center p-4 border-b bg-white shadow-sm flex-shrink-0">
                        <img src="../../DermaSculpt_user/<?php echo $convo['profile_picture_url']; ?>" alt="avatar" class="w-10 h-10 rounded-full object-cover mr-4">
                        <div>
                            <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($activeUser['first_name'] . ' ' . $activeUser['last_name']); ?></h3>
                        </div>
                    </div>
                    <div id="message-body" class="flex-1 p-6 overflow-y-auto">
                        <div class="space-y-4">
                            <?php foreach ($messages as $message): ?>
                                <?php
                                $isDermoMessage = ($message['sender_id'] == $dermatologistId && $message['sender_role'] == 'dermatologist');
                                $isDeleted = !empty($message['deleted_at']);
                                $isEdited = !empty($message['edit_at']);
                                ?>
                                <div class="message-bubble-wrapper <?php echo $isDermoMessage ? 'flex items-start justify-end group relative' : 'flex items-start'; ?>" data-message-id="<?php echo $message['message_id']; ?>" data-sender="<?php echo ($message['sender_id'] != $dermatologistId) ? 'Patient' : 'Dermatologist'; ?>" data-text="<?php echo htmlspecialchars($message['message_text']); ?>">

                                    <?php if ($isDermoMessage && !$isDeleted): ?>
                                        <div class="message-actions hidden group-hover:flex items-center mx-2 order-first">
                                            <button class="text-gray-400 hover:text-gray-600 p-1" onclick="toggleMessageDropdown(event)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="absolute right-6 top-0 mt-6 w-28 bg-white rounded-md shadow-lg py-1 z-20 hidden">
                                                <a href="#" class="edit-btn block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                                <a href="#" class="unsend-btn block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Unsend</a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="<?php echo $isDermoMessage ? 'bg-blue-500 text-white rounded-br-none' : 'bg-white text-gray-800 rounded-tl-none'; ?> p-3 rounded-lg max-w-lg shadow-sm">
                                        <?php if (!$isDeleted && !empty($message['attachment_url'])): ?>
                                            <?php if (strpos($message['attachment_type'], 'image') === 0): ?>
                                                <a href="../<?php echo htmlspecialchars($message['attachment_url']); ?>" target="_blank" class="cursor-pointer"><img src="../<?php echo htmlspecialchars($message['attachment_url']); ?>" class="max-w-xs w-full rounded-lg mb-2" alt="attachment"></a>
                                            <?php else: ?>
                                                <a href="../<?php echo htmlspecialchars($message['attachment_url']); ?>" target="_blank" class="flex items-center bg-gray-200 text-gray-700 p-3 rounded-lg hover:bg-gray-300 mb-2"><i class="fas fa-file-alt fa-2x mr-3"></i><span class="truncate"><?php echo htmlspecialchars(substr($message['attachment_url'], strrpos($message['attachment_url'], '-') + 1)); ?></span></a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($message['message_text'])): ?>
                                            <div class="message-content">
                                                <p class="text-sm message-text-content <?php if ($isDeleted) echo 'italic text-gray-400'; ?>">
                                                    <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <p class="text-xs <?php echo $isDermoMessage ? 'text-blue-100' : 'text-gray-400'; ?> text-right mt-1">
                                            <?php if ($isEdited): ?>
                                                <span class="edited-indicator">(edited) </span>
                                            <?php endif; ?>
                                            <?php echo date("g:i A", strtotime($message['timestamp'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="p-4 bg-white border-t flex-shrink-0">
                        <div id="attachment-preview-container" class="pb-2 hidden"></div>
                        <form id="send-message-form" class="flex items-center" autocomplete="off">
                            <input type="hidden" name="receiver_id" value="<?php echo $activeUser['user_id']; ?>">
                            <label for="file-input" class="mr-3 text-gray-500 hover:text-blue-500 cursor-pointer p-2 rounded-full hover:bg-gray-100"><i class="fas fa-paperclip fa-lg"></i></label>
                            <input type="file" id="file-input" name="attachment" class="hidden" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx">
                            <input type="text" name="message_text" id="message-input" placeholder="Type your message..." class="w-full px-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="ml-4 bg-blue-500 text-white rounded-full h-10 w-10 flex-shrink-0 flex items-center justify-center hover:bg-blue-600"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-center p-4"><i class="fas fa-comments fa-4x text-gray-300"></i>
                        <p class="mt-4 text-lg text-gray-500">Select a conversation to begin</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside id="ai-panel" class="ai-panel bg-slate-50 border-l border-gray-200 flex flex-col w-full lg:w-96 flex-shrink-0">
                <?php if ($activeUser): ?>
                    <div class="p-4 border-b flex-shrink-0">
                        <h2 class="text-xl font-bold flex items-center"><i class="fas fa-brain text-blue-500 mr-3"></i>AI Assistant</h2>
                    </div>
                    <div id="ai-output" class="flex-1 p-4 overflow-y-auto ai-panel-content">
                        <p class="text-gray-500">Hello, Dr. <?php echo $sidebar_firstName; ?>. I can help you analyze this conversation. Choose an action below or ask me a question.</p>
                    </div>
                    <div class="p-4 border-t bg-slate-100 flex-shrink-0">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="queryAI('Summarize this conversation briefly.')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold py-2 px-3 rounded-lg text-left"><i class="fas fa-align-left w-6"></i> Summarize</button>
                                <button onclick="queryAI('What are the patient`s key concerns or questions? List them as bullet points.')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-sm font-semibold py-2 px-3 rounded-lg text-left"><i class="fas fa-list-ul w-6"></i> Key Concerns</button>
                            </div>
                            <form id="ai-form" class="flex items-center">
                                <input type="text" id="ai-input" placeholder="Ask about this chat..." class="w-full px-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                <button type="submit" class="ml-2 bg-blue-500 text-white rounded-full h-9 w-9 flex-shrink-0 flex items-center justify-center hover:bg-blue-600"><i class="fas fa-arrow-up"></i></button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-full text-center p-4"><i class="fas fa-brain fa-4x text-gray-300"></i>
                        <p class="mt-4 text-lg text-gray-500">Select a conversation to activate the AI Assistant.</p>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <div id="ai-panel-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden xl:hidden" onclick="document.getElementById('ai-panel').classList.remove('open'); this.classList.add('hidden');"></div>

    <script>
        const activeUserId = <?php echo $activeUserId ?? 'null'; ?>;
        const fileInput = document.getElementById('file-input');
        const attachmentPreviewContainer = document.getElementById('attachment-preview-container');
        const messageInput = document.getElementById('message-input');
        const sendMessageForm = document.getElementById('send-message-form');
        const messageBody = document.getElementById('message-body');
        const aiToggleBtn = document.getElementById('ai-toggle');
        const aiPanel = document.getElementById('ai-panel');
        const aiPanelOverlay = document.getElementById('ai-panel-overlay');
        const aiForm = document.getElementById('ai-form');
        const aiOutput = document.getElementById('ai-output');
        const backToListBtn = document.getElementById('back-to-list');

        // Sidebar toggle and user dropdown handlers (fixes toggleDropdown reference)
        const sidebarEl = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const sidebarToggleBtn = document.getElementById('sidebar-toggle');
        const userDropdown = document.getElementById('user-dropdown');

        function toggleSidebar() {
            if (!sidebarEl) return;
            if (window.innerWidth < 1024) {
                sidebarEl.classList.toggle('open');
                sidebarOverlay?.classList.toggle('hidden');
            } else {
                sidebarEl.classList.toggle('collapsed');
            }
        }

        function toggleDropdown() {
            userDropdown?.classList.toggle('hidden');
        }

        sidebarToggleBtn?.addEventListener('click', toggleSidebar);
        window.addEventListener('click', function(e) {
            if (!userDropdown) return;
            const toggleBtn = document.querySelector('button[onclick="toggleDropdown()"]');
            if (!userDropdown.contains(e.target) && !(toggleBtn && toggleBtn.contains(e.target))) {
                userDropdown.classList.add('hidden');
            }
        });

        function scrollToBottom() {
            if (messageBody) messageBody.scrollTop = messageBody.scrollHeight;
        }
        scrollToBottom();

        function removeAttachment() {
            fileInput.value = '';
            attachmentPreviewContainer.innerHTML = '';
            attachmentPreviewContainer.classList.add('hidden');
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        attachmentPreviewContainer.innerHTML = `<div class="relative inline-block bg-gray-100 p-2 rounded-lg"><img src="${event.target.result}" class="h-20 w-20 object-cover rounded"><button type="button" onclick="removeAttachment()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs">&times;</button></div>`;
                        attachmentPreviewContainer.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    attachmentPreviewContainer.innerHTML = `<div class="relative inline-flex items-center bg-gray-100 p-2 rounded-lg"><i class="fas fa-file-alt fa-2x mr-2"></i><span class="text-sm truncate max-w-xs">${file.name}</span><button type="button" onclick="removeAttachment()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs">&times;</button></div>`;
                    attachmentPreviewContainer.classList.remove('hidden');
                }
            });
        }

        function createBubbleFromData(data, isSender) {
            const wrapper = document.createElement('div');
            wrapper.className = 'message-bubble-wrapper flex items-start justify-end group relative';
            wrapper.dataset.messageId = data.message_id;
            wrapper.dataset.sender = 'Dermatologist';
            wrapper.dataset.text = data.message_text || '';

            let attachmentHtml = '';
            if (data.attachment_url) {
                const attachmentUrl = data.attachment_url.startsWith('blob:') ? data.attachment_url : `../${data.attachment_url}`;
                if (data.attachment_type && data.attachment_type.startsWith('image/')) {
                    attachmentHtml = `<a href="${attachmentUrl}" target="_blank"><img src="${attachmentUrl}" class="max-w-xs w-full rounded-lg mb-2" alt="attachment"></a>`;
                } else {
                    const filename = data.attachment_url.substring(data.attachment_url.lastIndexOf('-') + 1);
                    attachmentHtml = `<a href="${attachmentUrl}" target="_blank" class="flex items-center bg-gray-200 text-gray-700 p-3 rounded-lg hover:bg-gray-300 mb-2"><i class="fas fa-file-alt fa-2x mr-3"></i><span class="truncate">${filename}</span></a>`;
                }
            }

            let textHtml = '';
            if (data.message_text) {
                textHtml = `<div class="message-content"><p class="text-sm message-text-content">${data.message_text.replace(/\n/g, '<br>')}</p></div>`;
            }

            const time = new Date(data.timestamp).toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });

            wrapper.innerHTML = `
                <div class="message-actions hidden group-hover:flex items-center mx-2 order-first">
                    <button class="text-gray-400 hover:text-gray-600 p-1" onclick="toggleMessageDropdown(event)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="absolute right-6 top-0 mt-6 w-28 bg-white rounded-md shadow-lg py-1 z-20 hidden">
                        <a href="#" class="edit-btn block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                        <a href="#" class="unsend-btn block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Unsend</a>
                    </div>
                </div>
                <div class="bg-blue-500 text-white rounded-br-none p-3 rounded-lg max-w-lg shadow-sm">
                    ${attachmentHtml}
                    ${textHtml}
                    <p class="text-xs text-blue-100 text-right mt-1">${time}</p>
                </div>`;
            return wrapper;
        }

        if (sendMessageForm) {
            sendMessageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageText = messageInput.value.trim();
                const file = fileInput.files[0];
                if (messageText === '' && !file) return;

                const formData = new FormData(this);
                const tempId = `temp_${Date.now()}`;
                const bubbleData = {
                    message_id: tempId,
                    message_text: messageText,
                    attachment_url: file ? URL.createObjectURL(file) : null,
                    attachment_type: file ? file.type : null,
                    timestamp: new Date().toISOString(),
                };
                const tempBubble = createBubbleFromData(bubbleData, true);
                tempBubble.id = tempId;
                tempBubble.classList.add('opacity-50');
                messageBody.querySelector('.space-y-4').appendChild(tempBubble);
                scrollToBottom();
                messageInput.value = '';
                removeAttachment();

                fetch('../backend/send_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        const sentBubble = document.getElementById(tempId);
                        if (data.success && sentBubble) {
                            sentBubble.dataset.messageId = data.messageData.message_id;
                            sentBubble.id = '';
                            sentBubble.classList.remove('opacity-50');
                        } else if (sentBubble) {
                            sentBubble.remove();
                            alert('Error: ' + (data.message || 'Failed to send message.'));
                        }
                    })
                    .catch(() => {
                        document.getElementById(tempId)?.remove();
                        alert('An unexpected error occurred.');
                    });
            });
        }

        if (aiToggleBtn) {
            aiToggleBtn.addEventListener('click', () => {
                aiPanel.classList.toggle('open');
                aiPanelOverlay.classList.toggle('hidden');
            });
        }

        if (backToListBtn) {
            backToListBtn.addEventListener('click', () => {
                window.location.href = 'messages.php';
            });
        }

        function queryAI(prompt) {
            const conversationBubbles = document.querySelectorAll('.message-bubble-wrapper');
            let conversationText = '';
            conversationBubbles.forEach(bubble => {
                const sender = bubble.dataset.sender;
                const text = bubble.dataset.text;
                if (text) conversationText += `${sender}: ${text}\n`;
            });

            if (conversationText.trim() === '') {
                aiOutput.innerHTML = '<p class="text-gray-500">There is no conversation to analyze yet.</p>';
                return;
            }

            aiOutput.innerHTML = '<div class="flex items-center justify-center h-full"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div></div>';

            fetch('../backend/ai_assistant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_text: conversationText,
                        prompt: prompt
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.details || data.error);
                    aiOutput.innerHTML = marked.parse(data.reply);
                })
                .catch(error => {
                    console.error('AI Error:', error);
                    aiOutput.innerHTML = '<p class="text-red-500">Sorry, there was an error connecting to the AI assistant.</p>';
                });
        }

        if (aiForm) {
            aiForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const aiInput = document.getElementById('ai-input');
                const customPrompt = aiInput.value.trim();
                if (customPrompt) {
                    queryAI(customPrompt);
                    aiInput.value = '';
                }
            });
        }

        function toggleMessageDropdown(event) {
            event.stopPropagation();
            const button = event.currentTarget;
            const dropdown = button.nextElementSibling;
            document.querySelectorAll('.message-actions .absolute').forEach(d => {
                if (d !== dropdown) d.classList.add('hidden');
            });
            dropdown.classList.toggle('hidden');
        }

        window.addEventListener('click', () => {
            document.querySelectorAll('.message-actions .absolute').forEach(d => d.classList.add('hidden'));
        });

        if (messageBody) {
            messageBody.addEventListener('click', function(e) {
                const unsendButton = e.target.closest('.unsend-btn');
                if (unsendButton) {
                    e.preventDefault();
                    const messageBubbleWrapper = unsendButton.closest('.message-bubble-wrapper');
                    const messageId = messageBubbleWrapper.dataset.messageId;

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You won't be able to revert this!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, unsend it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('../backend/unsend_message.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        message_id: messageId
                                    })
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        const textElement = messageBubbleWrapper.querySelector('.message-text-content');
                                        if (textElement) {
                                            textElement.innerHTML = "This message was unsent.";
                                            textElement.className = "text-sm message-text-content italic text-gray-400";
                                        }
                                        messageBubbleWrapper.querySelector('.message-actions')?.remove();
                                        messageBubbleWrapper.querySelector('a[href*="uploads"]')?.remove();
                                        Swal.fire('Unsents!', 'Your message has been removed.', 'success');
                                    } else {
                                        Swal.fire('Failed!', data.message || 'Could not unsend the message.', 'error');
                                    }
                                }).catch(err => {
                                    console.error("Unsend Error:", err);
                                    Swal.fire('Error!', 'A critical error occurred while unsending.', 'error');
                                });
                        }
                    });
                }

                const editButton = e.target.closest('.edit-btn');
                if (editButton) {
                    e.preventDefault();
                    const messageBubbleWrapper = editButton.closest('.message-bubble-wrapper');
                    const messageContentDiv = messageBubbleWrapper.querySelector('.message-content');
                    if (!messageContentDiv || messageBubbleWrapper.querySelector('.edit-form')) return;

                    editButton.closest('.absolute').classList.add('hidden');
                    const currentText = messageContentDiv.querySelector('.message-text-content').innerText;
                    messageContentDiv.style.display = 'none';

                    const editFormHTML = `
                        <div class="edit-form">
                            <textarea class="w-full p-2 border rounded-md text-gray-800 bg-white text-sm" rows="3">${currentText}</textarea>
                            <div class="flex justify-end gap-2 mt-2">
                                <button class="cancel-edit-btn text-xs px-2 py-1 rounded bg-gray-200 text-gray-700">Cancel</button>
                                <button class="save-edit-btn text-xs px-2 py-1 rounded bg-blue-600 text-white">Save</button>
                            </div>
                        </div>`;
                    messageContentDiv.insertAdjacentHTML('afterend', editFormHTML);
                }

                const saveButton = e.target.closest('.save-edit-btn');
                if (saveButton) {
                    e.preventDefault();
                    const messageBubbleWrapper = saveButton.closest('.message-bubble-wrapper');
                    const editForm = saveButton.closest('.edit-form');

                    if (!messageBubbleWrapper || !editForm) {
                        console.error("Save button's parent elements not found.");
                        return;
                    }

                    const messageId = messageBubbleWrapper.dataset.messageId;
                    const newText = editForm.querySelector('textarea').value;

                    fetch('../backend/edit_message.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                message_id: messageId,
                                message_text: newText
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const messageContentDiv = messageBubbleWrapper.querySelector('.message-content');
                                messageBubbleWrapper.dataset.text = newText;
                                messageContentDiv.querySelector('.message-text-content').innerHTML = newText.replace(/\n/g, '<br>');
                                messageContentDiv.style.display = 'block';
                                editForm.remove();

                                if (!messageBubbleWrapper.querySelector('.edited-indicator')) {
                                    const timeP = messageBubbleWrapper.querySelector('.text-xs');
                                    timeP.insertAdjacentHTML('afterbegin', '<span class="edited-indicator">(edited) </span>');
                                }
                            } else {
                                Swal.fire('Error!', data.message || 'Could not save the message.', 'error');
                            }
                        }).catch(err => {
                            console.error('Save Fetch Error:', err);
                            Swal.fire('Error!', 'A critical error occurred while saving.', 'error');
                        });
                }

                const cancelButton = e.target.closest('.cancel-edit-btn');
                if (cancelButton) {
                    e.preventDefault();
                    const messageBubbleWrapper = cancelButton.closest('.message-bubble-wrapper');
                    const editForm = messageBubbleWrapper.querySelector('.edit-form');
                    if (editForm) {
                        messageBubbleWrapper.querySelector('.message-content').style.display = 'block';
                        editForm.remove();
                    }
                }
            });
        }

        function updateConversationList(data) {
            const conversationListBody = document.getElementById('conversation-list-body');
            data.forEach(convo => {
                const convoItem = conversationListBody.querySelector(`.conversation-item[data-user-id='${convo.user_id}']`);
                if (!convoItem) return;

                const lastMessageEl = convoItem.querySelector('[data-role="last-message"]');
                const timestampEl = convoItem.querySelector('[data-role="timestamp"]');
                const newBadgeEl = convoItem.querySelector('[data-role="new-badge"]');
                const unreadBubbleEl = convoItem.querySelector('[data-role="unread-bubble"]');

                const lastMessageText = convo.attachment_url ? '<i class="fas fa-paperclip mr-1"></i> Attachment' : convo.last_message;
                lastMessageEl.innerHTML = lastMessageText;

                const messageDate = new Date(convo.last_message_timestamp);
                timestampEl.textContent = messageDate.toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                const isUnread = (convo.last_message_is_read == 0 && convo.last_message_sender_role === 'user');

                if (isUnread && String(activeUserId) !== String(convo.user_id)) {
                    newBadgeEl.innerHTML = '<span class="bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">New</span>';
                    lastMessageEl.classList.add('font-bold');
                } else {
                    newBadgeEl.innerHTML = '';
                    lastMessageEl.classList.remove('font-bold');
                }

                if (convo.unread_count > 0 && String(activeUserId) !== String(convo.user_id)) {
                    unreadBubbleEl.innerHTML = `<span class="bg-blue-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">${convo.unread_count}</span>`;
                } else {
                    unreadBubbleEl.innerHTML = '';
                }
            });
        }

        setInterval(() => {
            if (!document.hidden) {
                fetch('../backend/conversation_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data && !data.error) {
                            updateConversationList(data);
                        }
                    })
                    .catch(error => console.error('Error fetching conversation status:', error));
            }
        }, 5000);
    </script>
</body>

</html>