<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

$messageId = $_GET['id'] ?? 0;

if (!$messageId) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

// Get message details
$stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
$stmt->bind_param("i", $messageId);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();
$stmt->close();

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

// Mark as read if it's unread
if ($message['status'] === 'unread') {
    $updateStmt = $conn->prepare("UPDATE contact_messages SET status = 'read', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->bind_param("i", $messageId);
    $updateStmt->execute();
    $updateStmt->close();
    $message['status'] = 'read';
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

function formatDateTime($datetime) {
    return date('F j, Y \a\t g:i A', strtotime($datetime));
}

// Generate HTML content
ob_start();
?>
<div class="p-6">
    <!-- Message Header -->
    <div class="bg-gradient-to-r from-cyan-50 to-blue-50 p-6 rounded-lg border border-cyan-200 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($message['name']); ?></h3>
                <div class="flex items-center gap-4 text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-envelope mr-2 text-cyan-600"></i>
                        <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>" class="text-cyan-600 hover:text-cyan-800">
                            <?php echo htmlspecialchars($message['email']); ?>
                        </a>
                    </div>
                    <?php if ($message['phone_number']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone mr-2 text-cyan-600"></i>
                            <a href="tel:<?php echo htmlspecialchars($message['phone_number']); ?>" class="text-cyan-600 hover:text-cyan-800">
                                <?php echo htmlspecialchars($message['phone_number']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 text-sm rounded-full <?php echo getStatusBadgeClass($message['status']); ?> mb-2">
                    <?php echo ucfirst($message['status']); ?>
                </span>
                <p class="text-sm text-gray-500">
                    <?php echo formatDateTime($message['created_at']); ?>
                </p>
                <?php if ($message['updated_at'] !== $message['created_at']): ?>
                    <p class="text-xs text-gray-400">
                        Updated: <?php echo formatDateTime($message['updated_at']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Technical Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-500 border-t border-cyan-200 pt-4">
            <?php if ($message['ip_address']): ?>
                <div class="flex items-center">
                    <i class="fas fa-globe mr-2"></i>
                    <span>IP: <?php echo htmlspecialchars($message['ip_address']); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($message['user_agent']): ?>
                <div class="flex items-center">
                    <i class="fas fa-desktop mr-2"></i>
                    <span class="truncate">Browser: <?php echo htmlspecialchars(substr($message['user_agent'], 0, 50)); ?>...</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message Content -->
    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-comment-alt mr-2 text-cyan-600"></i>
            Message Content
        </h4>
        <div class="prose max-w-none">
            <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($message['message']); ?></p>
        </div>
    </div>

    <!-- Reply Section -->
    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-reply mr-2 text-green-600"></i>
            Send Reply
            <span class="ml-auto">
                <button 
                    onclick="toggleAIAssistant()" 
                    id="aiToggleBtn"
                    class="bg-purple-600 text-white px-3 py-1 rounded-lg hover:bg-purple-700 flex items-center gap-2 text-sm"
                >
                    <i class="fas fa-robot"></i>
                    AI Assistant
                </button>
            </span>
        </h4>
        
        <!-- AI Assistant Panel -->
        <div id="aiAssistantPanel" class="hidden mb-6 bg-white border border-purple-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <h5 class="font-semibold text-purple-800 flex items-center">
                    <i class="fas fa-magic mr-2"></i>
                    AI Reply Suggestions
                </h5>
                <button onclick="toggleAIAssistant()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
                <button 
                    onclick="getAISuggestion('suggest_reply')" 
                    class="ai-suggestion-btn bg-blue-100 text-blue-800 px-3 py-2 rounded-lg hover:bg-blue-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-lightbulb"></i>
                    General Reply
                </button>
                <button 
                    onclick="getAISuggestion('suggest_professional')" 
                    class="ai-suggestion-btn bg-indigo-100 text-indigo-800 px-3 py-2 rounded-lg hover:bg-indigo-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-user-md"></i>
                    Professional
                </button>
                <button 
                    onclick="getAISuggestion('suggest_empathetic')" 
                    class="ai-suggestion-btn bg-pink-100 text-pink-800 px-3 py-2 rounded-lg hover:bg-pink-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-heart"></i>
                    Empathetic
                </button>
                <button 
                    onclick="getAISuggestion('suggest_appointment')" 
                    class="ai-suggestion-btn bg-green-100 text-green-800 px-3 py-2 rounded-lg hover:bg-green-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-calendar"></i>
                    Appointment
                </button>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-4">
                <button 
                    onclick="getGrammarFixer()" 
                    class="ai-suggestion-btn bg-purple-100 text-purple-800 px-3 py-2 rounded-lg hover:bg-purple-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-spell-check"></i>
                    Grammar Fixer
                </button>
            
                <button 
                    onclick="getAISuggestion('analyze_inquiry')" 
                    class="ai-suggestion-btn bg-orange-100 text-orange-800 px-3 py-2 rounded-lg hover:bg-orange-200 text-sm flex items-center justify-center gap-1"
                >
                    <i class="fas fa-search"></i>
                    Analyze Inquiry
                </button>
            </div>
            
            <!-- Custom Prompt -->
            <div class="border-t border-purple-200 pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Custom AI Prompt</label>
                <div class="flex gap-2">
                    <input 
                        type="text" 
                        id="customPrompt" 
                        placeholder="Enter custom instruction for AI..."
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                    >
                    <button 
                        onclick="getCustomAISuggestion()" 
                        class="ai-suggestion-btn bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm"
                    >
                        <i class="fas fa-wand-magic-sparkles"></i>
                        Generate
                    </button>
                </div>
            </div>
            
            <!-- AI Response Area -->
            <div id="aiResponseArea" class="hidden mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <h6 class="font-medium text-gray-800">AI Suggestion:</h6>
                    <div class="flex gap-2">
                        <button 
                            onclick="useAISuggestion()" 
                            class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                        >
                            <i class="fas fa-check mr-1"></i>Use This
                        </button>
                        <button 
                            onclick="appendAISuggestion()" 
                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700"
                        >
                            <i class="fas fa-plus mr-1"></i>Append
                        </button>
                    </div>
                </div>
                <div id="aiSuggestionText" class="text-sm text-gray-700 whitespace-pre-wrap bg-white p-3 rounded border"></div>
            </div>
        </div>
        
        <div class="space-y-4">
            <div>
                <label for="replyMessage" class="block text-sm font-medium text-gray-700 mb-2">
                    Your Reply Message
                </label>
                <textarea 
                    id="replyMessage" 
                    rows="6" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                    placeholder="Type your reply here or use AI suggestions above..."
                ></textarea>
            </div>
            
            <div class="bg-green-100 border border-green-300 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-green-600 mt-1 mr-3"></i>
                    <div class="text-sm text-green-800">
                        <p class="font-medium mb-1">Email Reply Information:</p>
                        <ul class="space-y-1">
                            <li>• Reply will be sent to: <strong><?php echo htmlspecialchars($message['email']); ?></strong></li>
                            <li>• Message status will be updated to "Replied"</li>
                            <li>• Patient will receive your response via email</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="flex gap-2">
                    <button 
                        onclick="markAsRead(<?php echo $message['id']; ?>)" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"
                        <?php echo $message['status'] === 'read' ? 'disabled' : ''; ?>
                    >
                        <i class="fas fa-check"></i>
                        <?php echo $message['status'] === 'read' ? 'Already Read' : 'Mark as Read'; ?>
                    </button>
                    
                    <button 
                        onclick="deleteMessage(<?php echo $message['id']; ?>)" 
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2"
                    >
                        <i class="fas fa-trash"></i>
                        Delete Message
                    </button>
                </div>
                
                <button 
                    id="sendReplyBtn"
                    onclick="sendReply(<?php echo $message['id']; ?>)" 
                    class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 font-semibold"
                >
                    <i class="fas fa-paper-plane"></i>
                    Send Reply
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.currentAISuggestion = '';

window.toggleAIAssistant = function() {
    const panel = document.getElementById('aiAssistantPanel');
    const btn = document.getElementById('aiToggleBtn');
    
    if (panel.classList.contains('hidden')) {
        panel.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-robot"></i> Hide AI';
        btn.classList.add('bg-purple-700');
    } else {
        panel.classList.add('hidden');
        btn.innerHTML = '<i class="fas fa-robot"></i> AI Assistant';
        btn.classList.remove('bg-purple-700');
        document.getElementById('aiResponseArea').classList.add('hidden');
    }
}

window.getAISuggestion = function(action) {
    const messageId = <?php echo $message['id']; ?>;
    const responseArea = document.getElementById('aiResponseArea');
    const suggestionText = document.getElementById('aiSuggestionText');
    
    // Find the clicked button and show loading only on that button
    const clickedButton = event.target.closest('.ai-suggestion-btn');
    if (!clickedButton) return;
    
    const originalContent = clickedButton.innerHTML;
    
    // Disable only the clicked button and show loading
    clickedButton.disabled = true;
    clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    clickedButton.setAttribute('data-original', originalContent);
    
    responseArea.classList.remove('hidden');
    suggestionText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating AI suggestion...';
    
    fetch('../backend/ai_inquiry_assistant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: action,
            message_id: messageId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.currentAISuggestion = data.suggestion;
            suggestionText.textContent = data.suggestion;
        } else {
            suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' + (data.error || 'Failed to generate suggestion') + '</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while generating the suggestion.</span>';
    })
    .finally(() => {
        // Re-enable only the clicked button and restore original content
        clickedButton.disabled = false;
        clickedButton.innerHTML = clickedButton.getAttribute('data-original');
        clickedButton.removeAttribute('data-original');
    });
}

window.getGrammarFixer = function() {
    const replyTextarea = document.getElementById('replyMessage');
    const textToImprove = replyTextarea.value.trim();
    const responseArea = document.getElementById('aiResponseArea');
    const suggestionText = document.getElementById('aiSuggestionText');
    
    if (!textToImprove) {
        parent.CustomAlert.fire('Error!', 'Please enter some text in the reply message area first.', 'error');
        return;
    }
    
    // Find the clicked button and show loading
    const clickedButton = event.target.closest('.ai-suggestion-btn');
    if (!clickedButton) return;
    
    const originalContent = clickedButton.innerHTML;
    
    clickedButton.disabled = true;
    clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fixing...';
    clickedButton.setAttribute('data-original', originalContent);
    
    responseArea.classList.remove('hidden');
    suggestionText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Improving grammar and clarity...';
    
    fetch('../backend/ai_inquiry_assistant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'grammar_fixer',
            message_id: 0,
            text_to_improve: textToImprove
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.currentAISuggestion = data.suggestion;
            suggestionText.textContent = data.suggestion;
        } else {
            suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' + (data.error || 'Failed to improve text') + '</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while improving the text.</span>';
    })
    .finally(() => {
        clickedButton.disabled = false;
        clickedButton.innerHTML = clickedButton.getAttribute('data-original');
        clickedButton.removeAttribute('data-original');
    });
}

window.getCustomAISuggestion = function() {
    const customPrompt = document.getElementById('customPrompt').value.trim();
    const messageId = <?php echo $message['id']; ?>;
    const responseArea = document.getElementById('aiResponseArea');
    const suggestionText = document.getElementById('aiSuggestionText');
    const generateBtn = document.querySelector('button[onclick="getCustomAISuggestion()"]');
    
    if (!customPrompt) {
        parent.CustomAlert.fire('Error!', 'Please enter a custom prompt.', 'error');
        return;
    }
    
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    responseArea.classList.remove('hidden');
    suggestionText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating custom AI suggestion...';
    
    fetch('../backend/ai_inquiry_assistant.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'custom',
            message_id: messageId,
            custom_prompt: customPrompt
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.currentAISuggestion = data.suggestion;
            suggestionText.textContent = data.suggestion;
        } else {
            suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' + (data.error || 'Failed to generate suggestion') + '</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        suggestionText.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while generating the suggestion.</span>';
    })
    .finally(() => {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Generate';
    });
}

window.useAISuggestion = function() {
    if (window.currentAISuggestion) {
        document.getElementById('replyMessage').value = window.currentAISuggestion;
    }
}

window.appendAISuggestion = function() {
    if (window.currentAISuggestion) {
        const replyTextarea = document.getElementById('replyMessage');
        const currentText = replyTextarea.value;
        const separator = currentText ? '\n\n' : '';
        replyTextarea.value = currentText + separator + window.currentAISuggestion;
    }
}

window.deleteMessage = function(messageId) {
    parent.CustomAlert.fire({
        title: 'Delete Message?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        onConfirm: function() {
            fetch('inquiry_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_message',
                    message_id: messageId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    parent.CustomAlert.fire({
                        title: 'Deleted!',
                        text: 'Message has been deleted.',
                        icon: 'success',
                        onConfirm: () => {
                            parent.closeMessageModal();
                            parent.location.reload();
                        }
                    });
                } else {
                    parent.CustomAlert.fire('Error!', data.message || 'Failed to delete message.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                parent.CustomAlert.fire('Error!', 'An error occurred while deleting the message.', 'error');
            });
        }
    });
}
</script>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'message' => $message
]);

$conn->close();
?>
