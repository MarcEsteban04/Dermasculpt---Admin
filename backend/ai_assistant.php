<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$apiKey = 'AIzaSyDNjE4Ws4MGvvFqnxrH6q0sxOuGZpDCS98'; 

$requestData = json_decode(file_get_contents('php://input'), true);
$conversationText = $requestData['conversation_text'] ?? '';
$prompt = $requestData['prompt'] ?? '';

if (empty($conversationText) || empty($prompt)) {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation context and prompt cannot be empty.']);
    exit;
}

// --- UPDATED PROMPT ---
// This new prompt structure clearly defines the AI's role and the context of the request.
$fullPrompt = "You are an AI assistant for a dermatologist. Your task is to analyze the provided conversation between the 'Dermatologist' and their 'Patient'. Based on this conversation, follow the instruction given by the dermatologist.\n\n--- CONVERSATION START ---\n" . $conversationText . "\n--- CONVERSATION END ---\n\nDermatologist's Instruction: " . $prompt;

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
        'temperature' => 0.4,
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
    // Include cURL error information for better debugging
    echo json_encode(['error' => 'Failed to connect to AI service.', 'http_code' => $httpcode, 'details' => $response, 'curl_error' => $curl_error]);
    exit;
}

$responseData = json_decode($response, true);

// Check for errors in the API response itself
if (isset($responseData['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'AI service returned an error.', 'details' => $responseData['error']['message']]);
    exit;
}

$aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response at this time.';

echo json_encode(['reply' => $aiText]);
?>