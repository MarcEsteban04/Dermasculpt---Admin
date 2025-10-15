<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/connection.php';

// Use the same API key from the existing AI assistant
$apiKey = 'AIzaSyDNjE4Ws4MGvvFqnxrH6q0sxOuGZpDCS98'; 

$requestData = json_decode(file_get_contents('php://input'), true);
$action = $requestData['action'] ?? '';
$messageId = $requestData['message_id'] ?? 0;
$customPrompt = $requestData['custom_prompt'] ?? '';
$textToImprove = $requestData['text_to_improve'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Action is required']);
    exit;
}

// Get dermatologist information for context
$dermatologistId = $_SESSION['dermatologist_id'];
$stmt = $conn->prepare("SELECT first_name, last_name, specialization, license_number, bio FROM dermatologists WHERE dermatologist_id = ?");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$result = $stmt->get_result();
$dermatologist = $result->fetch_assoc();
$stmt->close();

// Get message details if message_id is provided, or bulk data for analysis
$messageContext = '';
if ($messageId) {
    $stmt = $conn->prepare("SELECT name, email, message, phone_number, created_at FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $stmt->close();
    
    if ($message) {
        $messageContext = "DERMATOLOGIST INFORMATION:\n";
        $messageContext .= "Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . "\n";
        $messageContext .= "Specialization: " . $dermatologist['specialization'] . "\n";
        $messageContext .= "License Number: " . $dermatologist['license_number'] . "\n";
        if ($dermatologist['bio']) {
            $messageContext .= "Bio: " . $dermatologist['bio'] . "\n";
        }
        $messageContext .= "Practice: DermaSculpt - Dermatology, Aesthetics & Lasers\n\n";
        
        $messageContext .= "PATIENT INQUIRY:\n";
        $messageContext .= "Patient Name: " . $message['name'] . "\n";
        $messageContext .= "Patient Email: " . $message['email'] . "\n";
        if ($message['phone_number']) {
            $messageContext .= "Patient Phone: " . $message['phone_number'] . "\n";
        }
        $messageContext .= "Inquiry Date: " . $message['created_at'] . "\n";
        $messageContext .= "Patient Message: " . $message['message'] . "\n\n";
    }
} else {
    // For bulk analysis, get recent inquiries data with additional context from database
    $stmt = $conn->prepare("SELECT cm.name, cm.email, cm.message, cm.status, cm.created_at, cm.phone_number FROM contact_messages cm ORDER BY cm.created_at DESC LIMIT 20");
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Dermatologist context already loaded above
    
    if ($messages) {
        $messageContext = "Recent Patient Inquiries Data:\n\n";
        $messageContext .= "DERMATOLOGIST INFORMATION:\n";
        $messageContext .= "Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . "\n";
        $messageContext .= "Specialization: " . $dermatologist['specialization'] . "\n";
        $messageContext .= "License Number: " . $dermatologist['license_number'] . "\n";
        if ($dermatologist['bio']) {
            $messageContext .= "Bio: " . substr($dermatologist['bio'], 0, 200) . "\n";
        }
        $messageContext .= "Practice: DermaSculpt - Dermatology, Aesthetics & Lasers\n\n";
        
        foreach ($messages as $index => $msg) {
            $messageContext .= "Inquiry " . ($index + 1) . ":\n";
            $messageContext .= "Patient: " . $msg['name'] . "\n";
            $messageContext .= "Email: " . $msg['email'] . "\n";
            if ($msg['phone_number']) {
                $messageContext .= "Phone: " . $msg['phone_number'] . "\n";
            }
            $messageContext .= "Status: " . $msg['status'] . "\n";
            $messageContext .= "Date: " . $msg['created_at'] . "\n";
            $messageContext .= "Message: " . substr($msg['message'], 0, 200) . (strlen($msg['message']) > 200 ? "..." : "") . "\n\n";
        }
    }
}

// Define different AI prompts based on action
$prompts = [
    'suggest_reply' => "You are Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . ", a board-certified dermatologist specializing in " . $dermatologist['specialization'] . " at DermaSculpt. Based on the patient inquiry below, generate a professional, empathetic, and informative reply. The response should:\n\n1. Address the patient's concerns directly\n2. Provide helpful dermatological guidance (while noting it's not a substitute for in-person consultation)\n3. Suggest next steps if appropriate (scheduling appointment, etc.)\n4. Maintain a warm, professional tone\n5. Keep the response concise but comprehensive\n6. Sign the response with your name and credentials\n\n" . $messageContext . "Generate a professional reply:",
    
    'suggest_professional' => "You are Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . ", a board-certified dermatologist specializing in " . $dermatologist['specialization'] . " at DermaSculpt. Create a formal, clinical response to the patient inquiry below. The response should:\n\n1. Use professional medical terminology appropriately\n2. Provide evidence-based information\n3. Include appropriate disclaimers about remote consultation limitations\n4. Suggest proper medical evaluation if needed\n5. Maintain clinical objectivity\n6. Sign with your name and credentials\n\n" . $messageContext . "Generate a professional clinical response:",
    
    'suggest_empathetic' => "You are Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . ", a caring dermatologist specializing in " . $dermatologist['specialization'] . " at DermaSculpt. Create a warm, empathetic response to the patient inquiry below. The response should:\n\n1. Acknowledge the patient's concerns with empathy\n2. Provide reassuring but accurate information\n3. Use accessible, non-technical language\n4. Show understanding of patient anxiety or concerns\n5. Encourage the patient while being realistic\n6. Sign with your name and practice information\n\n" . $messageContext . "Generate an empathetic, caring response:",
    
    'suggest_appointment' => "You are representing Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . ", a dermatologist specializing in " . $dermatologist['specialization'] . " at DermaSculpt. Create a response focused on scheduling and next steps for the patient inquiry below. The response should:\n\n1. Acknowledge their inquiry briefly\n2. Explain the benefits of an in-person consultation with Dr. " . $dermatologist['last_name'] . "\n3. Provide clear instructions for scheduling at DermaSculpt\n4. Mention what to expect during the appointment\n5. Include any preparation instructions if relevant\n6. Sign on behalf of the practice\n\n" . $messageContext . "Generate a response focused on appointment scheduling:",
    
    'analyze_inquiry' => "You are Dr. " . $dermatologist['first_name'] . " " . $dermatologist['last_name'] . ", a dermatologist specializing in " . $dermatologist['specialization'] . ". Analyze the patient inquiry below and provide:\n\n1. Key concerns identified\n2. Possible conditions or issues mentioned\n3. Urgency level (routine, moderate, urgent)\n4. Recommended response approach\n5. Suggested follow-up actions\n\n" . $messageContext . "Provide a professional analysis:",
    
    'grammar_fixer' => "You are a professional writing assistant specializing in medical communication. Your task is to improve the grammar, clarity, and professionalism of the text provided while maintaining the original meaning and medical accuracy. The text should be suitable for doctor-patient communication. Please:\n\n1. Fix any grammatical errors\n2. Improve sentence structure and flow\n3. Ensure professional medical tone\n4. Maintain the original meaning\n5. Keep it concise and clear\n6. Return only the improved text without additional commentary\n\nText to improve: [TEXT_TO_IMPROVE]\n\nProvide the improved version:",
    
    // Bulk analysis prompts that use actual inquiry data
    'priority_analysis' => "You are a dermatologist analyzing patient inquiries for priority triage. Based on the inquiry data below, identify which patients need urgent attention and provide a prioritized action plan:\n\n" . $messageContext . "\nAnalyze and prioritize these inquiries based on medical urgency, patient anxiety, and time sensitivity. Provide specific recommendations for each high-priority case.",
    
    'common_concerns' => "You are a dermatologist analyzing patient inquiry patterns. Based on the inquiry data below, identify common concerns and trending issues:\n\n" . $messageContext . "\nAnalyze these inquiries to identify: 1) Most frequent concerns, 2) Seasonal patterns, 3) Common misconceptions, 4) Areas where patient education could help.",
    
    'response_suggestions' => "You are a dermatologist creating response templates. Based on the inquiry data below, create reusable template responses for common scenarios:\n\n" . $messageContext . "\nCreate 3-4 professional template responses that can be customized for similar inquiries. Include templates for: general skin concerns, appointment requests, follow-up questions, and urgent cases.",
    
    'sentiment_analysis' => "You are analyzing patient communication sentiment. Based on the inquiry data below, assess patient emotions and satisfaction:\n\n" . $messageContext . "\nAnalyze the emotional tone of these inquiries. Identify: 1) Anxious or worried patients, 2) Frustrated patients, 3) Satisfied patients, 4) Overall sentiment trends and recommendations for improving patient communication.",
    
    'follow_up_recommendations' => "You are a dermatologist reviewing patient inquiries for follow-up needs. Based on the inquiry data below, recommend follow-up actions:\n\n" . $messageContext . "\nReview these inquiries and recommend: 1) Which patients need immediate appointments, 2) Who would benefit from follow-up calls, 3) Cases requiring additional information, 4) Patients who might need specialist referrals.",
    
    'workflow_optimization' => "You are analyzing dermatology practice workflow efficiency. Based on the inquiry data below, suggest improvements:\n\n" . $messageContext . "\nAnalyze the inquiry patterns and suggest: 1) Ways to reduce response time, 2) Common questions that could be addressed with FAQs, 3) Process improvements for better patient satisfaction, 4) Staff training recommendations.",
    
    'custom' => $messageContext . $customPrompt
];

// Handle grammar_fixer action specially
if ($action === 'grammar_fixer' && !empty($textToImprove)) {
    $fullPrompt = str_replace('[TEXT_TO_IMPROVE]', $textToImprove, $prompts['grammar_fixer']);
} else {
    $fullPrompt = $prompts[$action] ?? $prompts['suggest_reply'];
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $fullPrompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 32,
        'topP' => 1,
        'maxOutputTokens' => 4096,
        'stopSequences' => []
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($httpcode !== 200) {
    http_response_code($httpcode);
    echo json_encode(['error' => 'Failed to connect to AI service.', 'http_code' => $httpcode, 'details' => $response, 'curl_error' => $curl_error]);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'AI service returned an error.', 'details' => $responseData['error']['message']]);
    exit;
}

$aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response at this time.';

// Remove asterisks and clean up formatting
$aiText = preg_replace('/\*\*([^*]+)\*\*/', '$1', $aiText); // Remove **bold** formatting
$aiText = preg_replace('/\*([^*]+)\*/', '$1', $aiText); // Remove *italic* formatting
$aiText = str_replace('*', '', $aiText); // Remove remaining asterisks
$aiText = trim($aiText);

echo json_encode([
    'success' => true,
    'suggestion' => $aiText,
    'action' => $action
]);

$conn->close();
?>
