<?php
// Start output buffering to prevent any accidental output
ob_start();

// Disable error display to prevent HTML errors in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// Clear any previous output and set JSON header
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../config/connection.php';

// Gemini API configuration
define('GEMINI_API_KEY', 'AIzaSyDNjE4Ws4MGvvFqnxrH6q0sxOuGZpDCS98');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY);

$dermatologistId = $_SESSION['dermatologist_id'];

try {
    // Get analysis mode
    $analysisMode = $_POST['analysis_mode'] ?? 'upload';
    $patientName = $_POST['patient_name'] ?? null;
    $patientAge = $_POST['patient_age'] ?? null;
    $patientGender = $_POST['patient_gender'] ?? null;
    $analysisPrompt = $_POST['analysis_prompt'] ?? '';
    
    $filePath = '';
    $fileName = '';
    $imageData = '';
    $mimeType = '';
    $appointmentId = null;

    if ($analysisMode === 'appointment') {
        // Handle appointment image analysis
        $appointmentId = $_POST['appointment_id'] ?? null;
        $appointmentImagePath = $_POST['appointment_image_path'] ?? null;
        
        if (!$appointmentId || !$appointmentImagePath) {
            throw new Exception('Missing appointment information');
        }
        
        // Construct full path to appointment image
        $fullImagePath = '../../DermaSculpt_user/' . $appointmentImagePath;
        
        if (!file_exists($fullImagePath)) {
            throw new Exception('Appointment image not found: ' . $appointmentImagePath);
        }
        
        // Validate appointment belongs to this dermatologist
        $appointmentStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND dermatologist_id = ?");
        $appointmentStmt->bind_param("ii", $appointmentId, $dermatologistId);
        $appointmentStmt->execute();
        $appointmentResult = $appointmentStmt->get_result();
        
        if ($appointmentResult->num_rows === 0) {
            throw new Exception('Unauthorized access to appointment');
        }
        $appointmentStmt->close();
        
        // Copy appointment image to analysis directory for record keeping
        $uploadDir = '../uploads/skin_analysis/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($appointmentImagePath, PATHINFO_EXTENSION);
        $fileName = 'appointment_analysis_' . $appointmentId . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (!copy($fullImagePath, $filePath)) {
            throw new Exception('Failed to copy appointment image for analysis');
        }
        
        // Get image data for API
        $imageData = base64_encode(file_get_contents($fullImagePath));
        $mimeType = mime_content_type($fullImagePath);
        
    } else {
        // Handle uploaded image analysis
        if (!isset($_FILES['skin_image']) || $_FILES['skin_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No image uploaded or upload error occurred');
        }

        $image = $_FILES['skin_image'];

        // Validate image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($image['type'], $allowedTypes)) {
            throw new Exception('Invalid image type. Please upload JPG, PNG, GIF, or WebP images.');
        }

        if ($image['size'] > 10 * 1024 * 1024) { // 10MB limit
            throw new Exception('Image size too large. Please upload images smaller than 10MB.');
        }

        // Create upload directory
        $uploadDir = '../uploads/skin_analysis/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileExtension = pathinfo($image['name'], PATHINFO_EXTENSION);
        $fileName = 'analysis_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($image['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded image');
        }

        // Convert image to base64 for Gemini API
        $imageData = base64_encode(file_get_contents($filePath));
        $mimeType = $image['type'];
    }

    // Create comprehensive dermatology knowledge base prompt
    $knowledgeBase = getDermatologyKnowledgeBase();
    
    // Build AI analysis prompt
    $aiPrompt = buildAnalysisPrompt($patientAge, $patientGender, $analysisPrompt, $knowledgeBase);

    // Call Gemini API
    $analysisResult = callGeminiAPI($imageData, $mimeType, $aiPrompt);

    // Parse AI response
    $parsedResult = parseAIResponse($analysisResult);

    // Save to database
    if ($appointmentId) {
        // For appointment-based analysis, we need to add appointment_id column
        // First check if the column exists, if not we'll add it
        $checkColumnStmt = $conn->prepare("SHOW COLUMNS FROM skin_analysis LIKE 'appointment_id'");
        $checkColumnStmt->execute();
        $columnExists = $checkColumnStmt->get_result()->num_rows > 0;
        $checkColumnStmt->close();
        
        if (!$columnExists) {
            // Add appointment_id column
            $conn->query("ALTER TABLE skin_analysis ADD COLUMN appointment_id INT(11) NULL AFTER dermatologist_id");
            $conn->query("ALTER TABLE skin_analysis ADD CONSTRAINT fk_skin_analysis_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO skin_analysis 
            (dermatologist_id, appointment_id, patient_name, patient_age, patient_gender, image_path, image_filename, 
             analysis_prompt, ai_diagnosis, confidence_score, detected_conditions, recommendations) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iisisssssiss",
            $dermatologistId,
            $appointmentId,
            $patientName,
            $patientAge,
            $patientGender,
            $filePath,
            $fileName,
            $analysisPrompt,
            $parsedResult['diagnosis'],
            $parsedResult['confidence'],
            json_encode($parsedResult['conditions']),
            $parsedResult['recommendations']
        );
    } else {
        // Regular analysis without appointment
        $stmt = $conn->prepare("
            INSERT INTO skin_analysis 
            (dermatologist_id, patient_name, patient_age, patient_gender, image_path, image_filename, 
             analysis_prompt, ai_diagnosis, confidence_score, detected_conditions, recommendations) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isisssssiss",
            $dermatologistId,
            $patientName,
            $patientAge,
            $patientGender,
            $filePath,
            $fileName,
            $analysisPrompt,
            $parsedResult['diagnosis'],
            $parsedResult['confidence'],
            json_encode($parsedResult['conditions']),
            $parsedResult['recommendations']
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to save analysis to database');
    }

    $analysisId = $conn->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Analysis completed successfully',
        'analysis_id' => $analysisId,
        'result' => $parsedResult
    ]);

} catch (Exception $e) {
    // Clean up uploaded file if it exists
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Log the error for debugging (optional)
    error_log("Skin Analysis Error: " . $e->getMessage());
    
    // Clear any output buffer to ensure clean JSON
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'analysis_error'
    ]);
} catch (Throwable $e) {
    // Catch any other errors (PHP 7+)
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    error_log("Skin Analysis Fatal Error: " . $e->getMessage());
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred during analysis',
        'error_type' => 'fatal_error'
    ]);
}

function getDermatologyKnowledgeBase() {
    return "
DERMATOLOGY KNOWLEDGE BASE - Evidence-Based Sources:

1. COMMON SKIN CONDITIONS (Mayo Clinic, AAD, DermNet):
- Acne Vulgaris: Comedones, papules, pustules, nodules. Common in adolescents/young adults
- Atopic Dermatitis (Eczema): Chronic inflammatory condition, often with family history
- Psoriasis: Silvery scales on erythematous plaques, commonly on elbows, knees, scalp
- Seborrheic Dermatitis: Greasy scales in sebaceous areas (face, scalp)
- Rosacea: Central facial erythema, papules, pustules, telangiectasias
- Melanoma: ABCDE criteria (Asymmetry, Border, Color, Diameter, Evolution)
- Basal Cell Carcinoma: Pearly papules with telangiectasias, most common skin cancer
- Squamous Cell Carcinoma: Scaly, hyperkeratotic lesions on sun-exposed areas

2. DIAGNOSTIC CRITERIA (American Academy of Dermatology):
- Morphology: Primary lesions (macule, papule, plaque, nodule, vesicle, bulla, pustule)
- Distribution: Localized, generalized, symmetric, asymmetric
- Configuration: Linear, annular, grouped, scattered
- Color: Erythematous, hyperpigmented, hypopigmented, violaceous

3. RED FLAGS (Dermatology journals, NEJM):
- Rapid growth or change in existing lesion
- Irregular borders or asymmetry
- Multiple colors within single lesion
- Bleeding, ulceration, or crusting
- Diameter >6mm for pigmented lesions
- New lesions in adults >40 years

4. TREATMENT PRINCIPLES (UpToDate, Cochrane Reviews):
- Topical corticosteroids for inflammatory conditions
- Retinoids for acne and photoaging
- Antifungals for fungal infections
- Immunomodulators for chronic inflammatory conditions
- Sun protection for prevention and treatment

IMPORTANT: This AI analysis is for educational purposes only and should not replace clinical examination and professional medical judgment.
";
}

function buildAnalysisPrompt($age, $gender, $userPrompt, $knowledgeBase) {
    $prompt = $knowledgeBase . "\n\n";
    
    $prompt .= "PATIENT INFORMATION:\n";
    if ($age) $prompt .= "- Age: $age years\n";
    if ($gender) $prompt .= "- Gender: $gender\n";
    if ($userPrompt) $prompt .= "- Clinical Notes: $userPrompt\n";
    
    $prompt .= "\nANALYSIS REQUEST:
Please analyze this dermatological image using evidence-based dermatology knowledge. Provide:

1. VISUAL ASSESSMENT:
   - Describe the primary and secondary lesions observed
   - Note distribution, morphology, and configuration
   - Identify any concerning features

2. DIFFERENTIAL DIAGNOSIS:
   - List 3-5 most likely conditions based on clinical appearance
   - Rank by probability with confidence percentages
   - Include brief rationale for each diagnosis

3. CLINICAL RECOMMENDATIONS:
   - Suggest additional diagnostic tests if needed
   - Recommend treatment approaches based on most likely diagnosis
   - Identify any red flags requiring urgent referral

4. PATIENT EDUCATION:
   - Provide evidence-based information about the condition
   - Suggest preventive measures
   - Recommend follow-up timeline

Please format your response as structured JSON with the following fields:
{
  \"primary_diagnosis\": \"Most likely condition\",
  \"confidence_score\": 85,
  \"differential_diagnoses\": [
    {\"condition\": \"Diagnosis 1\", \"probability\": 85, \"rationale\": \"Reasoning\"},
    {\"condition\": \"Diagnosis 2\", \"probability\": 10, \"rationale\": \"Reasoning\"}
  ],
  \"visual_findings\": \"Detailed description of lesions\",
  \"recommendations\": \"Clinical recommendations\",
  \"red_flags\": \"Any concerning features\",
  \"patient_education\": \"Educational information\",
  \"follow_up\": \"Recommended timeline\"
}

CRITICAL: Base analysis only on evidence-based dermatology sources. Acknowledge limitations and emphasize need for clinical correlation.";

    return $prompt;
}

function callGeminiAPI($imageData, $mimeType, $prompt) {
    $requestData = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageData
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'topK' => 32,
            'topP' => 1,
            'maxOutputTokens' => 4096
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GEMINI_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        throw new Exception('API request failed: ' . curl_error($ch));
    }
    
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('API request failed with status: ' . $httpCode);
    }

    $responseData = json_decode($response, true);
    
    if (!$responseData || !isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Invalid API response format');
    }

    return $responseData['candidates'][0]['content']['parts'][0]['text'];
}

function parseAIResponse($aiResponse) {
    // Try to extract JSON from the response
    $jsonStart = strpos($aiResponse, '{');
    $jsonEnd = strrpos($aiResponse, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonString = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
        $parsed = json_decode($jsonString, true);
        
        if ($parsed) {
            return [
                'diagnosis' => $parsed['primary_diagnosis'] ?? 'Analysis completed',
                'confidence' => $parsed['confidence_score'] ?? null,
                'conditions' => $parsed['differential_diagnoses'] ?? [],
                'recommendations' => formatRecommendations($parsed),
                'raw_response' => $aiResponse
            ];
        }
    }
    
    // Fallback if JSON parsing fails
    return [
        'diagnosis' => 'AI Analysis Completed',
        'confidence' => null,
        'conditions' => [],
        'recommendations' => $aiResponse,
        'raw_response' => $aiResponse
    ];
}

function formatRecommendations($parsed) {
    $recommendations = '';
    
    if (isset($parsed['visual_findings'])) {
        $recommendations .= "**Visual Findings:**\n" . $parsed['visual_findings'] . "\n\n";
    }
    
    if (isset($parsed['recommendations'])) {
        $recommendations .= "**Clinical Recommendations:**\n" . $parsed['recommendations'] . "\n\n";
    }
    
    if (isset($parsed['red_flags'])) {
        $recommendations .= "**Red Flags:**\n" . $parsed['red_flags'] . "\n\n";
    }
    
    if (isset($parsed['patient_education'])) {
        $recommendations .= "**Patient Education:**\n" . $parsed['patient_education'] . "\n\n";
    }
    
    if (isset($parsed['follow_up'])) {
        $recommendations .= "**Follow-up:**\n" . $parsed['follow_up'] . "\n\n";
    }
    
    return $recommendations ?: 'Analysis completed successfully.';
}
?>
