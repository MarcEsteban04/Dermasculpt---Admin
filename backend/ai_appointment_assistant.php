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
$appointments = $requestData['appointments'] ?? [];
$prompt = $requestData['prompt'] ?? '';

if (empty($prompt)) {
    http_response_code(400);
    echo json_encode(['error' => 'Prompt cannot be empty.']);
    exit;
}

$appointmentsText = "";
if (!empty($appointments)) {
    foreach ($appointments as $appt) {
        $appointmentsText .= "- Date: {$appt['date']}, Time: {$appt['time']}, Patient: {$appt['patient']}, Status: {$appt['status']}\n";
    }
} else {
    $appointmentsText = "No appointments in the current view.\n";
}


$doctorName = "Dr. " . ($_SESSION['first_name'] ?? 'Doctor') . " " . ($_SESSION['last_name'] ?? '');

$fullPrompt = "You are an AI assistant specifically designed to help dermatologists manage their appointments and clinical workflow. You are speaking directly to $doctorName.

CRITICAL ADDRESSING REQUIREMENTS:
- ALWAYS start your response by addressing the doctor by name: '$doctorName, [your response]'
- NEVER address patients or speak as if talking to patients
- You are speaking TO the dermatologist, not ABOUT the dermatologist
- Use 'you' and 'your' when referring to the dermatologist
- Refer to patients as 'your patients' or by their names

Your role is to:
- Help analyze appointment schedules and patient loads
- Provide insights about appointment patterns and scheduling
- Assist with clinical workflow optimization
- Answer questions about specific appointments or patients
- Help with appointment management decisions

FORMATTING REQUIREMENTS: 
- Your response must be plain text only - no markdown formatting
- Be professional, concise, and clinically relevant
- Focus on helping with appointment management, not patient care advice
- ALWAYS format dates and times in word format (e.g., 'October 1, 2025 at 8:00 AM' not '2025-10-01 08:00:00')
- Use natural language for all dates and times in your responses

Current Appointments in View:
" . $appointmentsText . "

$doctorName's Question: " . $prompt;

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
        'maxOutputTokens' => 2048,
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

echo json_encode(['reply' => $aiText]);
?>