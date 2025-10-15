<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin'] || !isset($_SESSION['dermatologist_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/connection.php';

$apiKey = 'AIzaSyDNjE4Ws4MGvvFqnxrH6q0sxOuGZpDCS98';
$dermatologistId = $_SESSION['dermatologist_id'];

// Fetch appointments for next 30 days
$stmt = $conn->prepare("
    SELECT appointment_date, COUNT(*) as appointment_count 
    FROM appointments 
    WHERE dermatologist_id = ? 
    AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    GROUP BY appointment_date
");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch existing days off
$stmt = $conn->prepare("
    SELECT off_date, reason 
    FROM dermatologist_day_off 
    WHERE dermatologist_id = ? 
    AND off_date >= CURDATE()
    ORDER BY off_date
");
$stmt->bind_param("i", $dermatologistId);
$stmt->execute();
$daysOff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Format data for AI analysis
$scheduleData = "Current Schedule Analysis:\n\n";
$scheduleData .= "Upcoming Appointments:\n";
foreach ($appointments as $appt) {
    $scheduleData .= "- Date: {$appt['appointment_date']}, Number of appointments: {$appt['appointment_count']}\n";
}

$scheduleData .= "\nScheduled Days Off:\n";
foreach ($daysOff as $dayOff) {
    $scheduleData .= "- Date: {$dayOff['off_date']}" . ($dayOff['reason'] ? ", Reason: {$dayOff['reason']}" : "") . "\n";
}

$prompt = "As a scheduling AI assistant, analyze the following schedule data and provide:
1. A brief analysis of the workload distribution
2. Identify high-workload dates that might need breaks
3. Suggest 2-3 specific dates for taking days off based on workload patterns and existing schedule
4. Format your response in JSON with the following structure:
{
    \"analysis\": \"brief workload analysis text\",
    \"suggestions\": [
        {
            \"date\": \"YYYY-MM-DD\",
            \"reason\": \"brief explanation why this date is suggested\"
        }
    ]
}";

$fullPrompt = $prompt . "\n\nSchedule Data:\n" . $scheduleData;

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
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024,
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
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to get AI analysis',
        'http_code' => $httpcode,
        'curl_error' => $curl_error,
        'response' => $response
    ]);
    exit;
}

$responseData = json_decode($response, true);
$aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Try to extract JSON from AI response
preg_match('/\{.*\}/s', $aiResponse, $matches);
$analysisData = [];
if (isset($matches[0])) {
    $analysisData = json_decode($matches[0], true);
}

// Fallback if AI did not return valid JSON
if (
    !is_array($analysisData) ||
    !isset($analysisData['analysis']) ||
    !isset($analysisData['suggestions']) ||
    !is_array($analysisData['suggestions'])
) {
    $analysisData = [
        'analysis' => 'Sorry, the AI could not generate suggestions at this time. Please try again later.',
        'suggestions' => [],
        'debug' => [
            'ai_response' => $aiResponse,
            'matches' => $matches[0] ?? null,
            'raw_response' => $response
        ]
    ];
}

echo json_encode($analysisData);
?>
