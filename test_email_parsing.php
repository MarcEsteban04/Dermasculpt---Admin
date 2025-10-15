<?php
/**
 * Test Email Parsing - Verify that Gmail replies are parsed correctly
 */

require_once 'classes/SimpleGmailFetcher.php';

echo "<h1>Email Parsing Test</h1>";

// Test the email parsing with sample content
$testEmails = [
    [
        'description' => 'Simple reply',
        'content' => 'bulbol'
    ],
    [
        'description' => 'Reply with quoted content (from image)',
        'content' => 'bulbol On Wed, Oct 15, 2025 at 12:39 PM DermaSculpt - Dr. Maria Lourdes Santos < marcdelacruzesteban@gmail.com> wrote:

> DermaSculpt
> Dermatology • Aesthetics • Lasers
> Response to Your Inquiry
> Dear Marc Esteban,
> Thank you for contacting DermaSculpt. Dr. Maria Lourdes Santos has
> personally reviewed your inquiry and provided the following response:
> Doctor\'s Response:
> kupal
> Your Original Message:
> "testtestasdasdasdasddasdasdasdsadsasdasds"
> Sent on October 15, 2025 at 1:34 AM
> Need Further Assistance?'
    ],
    [
        'description' => 'Reply with quoted content',
        'content' => 'bulbol

> DermaSculpt
> Dermatology • Aesthetics • Lasers
> Response to Your Inquiry
> Dear Marc Esteban,
> Thank you for contacting DermaSculpt. Dr. Maria Lourdes Santos has
> personally reviewed your inquiry and provided the following response:
> Doctor\'s Response:
> kupal
> Your Original Message:
> "testtestasdasdasdasddasdasdasdsadsasdasds"
> Sent on October 15, 2025 at 1:34 AM
> Need Further Assistance?'
    ],
    [
        'description' => 'Real Gmail reply with quote (like in screenshot)',
        'content' => 'bulbol On Wed, Oct 15, 2025 at 12:39 PM DermaSculpt - Dr. Maria Lourdes Santos < marcdelacruzesteban@gmail.com> wrote:'
    ],
    [
        'description' => 'Reply with Gmail-style quote',
        'content' => 'test message

On Oct 15, 2025, at 9:40 PM, DermaSculpt wrote:
> Thank you for contacting DermaSculpt
> Dr. Santos has reviewed your inquiry'
    ],
    [
        'description' => 'Reply with Outlook-style quote',
        'content' => 'my reply here

-----Original Message-----
From: DermaSculpt
Sent: Tuesday, October 15, 2025
To: Patient
Subject: Response to Your Inquiry'
    ]
];

// Create a mock Gmail fetcher to test the parsing
$gmailFetcher = new SimpleGmailFetcher();

// Use reflection to access the private method
$reflection = new ReflectionClass($gmailFetcher);
$method = $reflection->getMethod('getEmailBody');
$method->setAccessible(true);

echo "<style>
    .test-case { 
        border: 1px solid #ddd; 
        margin: 20px 0; 
        padding: 15px; 
        border-radius: 5px;
        background: #f9f9f9;
    }
    .original { 
        background: #fff3cd; 
        padding: 10px; 
        border-radius: 3px; 
        margin: 10px 0;
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 12px;
    }
    .parsed { 
        background: #d4edda; 
        padding: 10px; 
        border-radius: 3px; 
        margin: 10px 0;
        white-space: pre-wrap;
        font-weight: bold;
    }
    .success { color: green; }
    .warning { color: orange; }
</style>";

foreach ($testEmails as $index => $testEmail) {
    echo "<div class='test-case'>";
    echo "<h3>Test Case " . ($index + 1) . ": " . $testEmail['description'] . "</h3>";
    
    echo "<h4>Original Email Content:</h4>";
    echo "<div class='original'>" . htmlspecialchars($testEmail['content']) . "</div>";
    
    // Simulate the email parsing logic
    $body = trim($testEmail['content']);
    
    // Extract only the new reply content (before any quoted text)
    $quoteIndicators = [
        '> DermaSculpt',
        '> Dermatology',
        '> Response to Your',
        '> Dear ',
        '> Thank you for contacting',
        '> Dr. ',
        'On ' . date('Y') . '-', // Current year date patterns
        'On ' . date('M'), // Month patterns like "On Oct"
        '-----Original Message-----',
        'From:',
        'Sent:',
        'To:',
        'Subject:'
    ];
    
    // Split into lines
    $lines = explode("\n", $body);
    $replyLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines at the beginning
        if (empty($line) && empty($replyLines)) {
            continue;
        }
        
        // Check if this line indicates start of quoted content
        $isQuotedContent = false;
        foreach ($quoteIndicators as $indicator) {
            if (stripos($line, $indicator) !== false || strpos($line, '>') === 0) {
                $isQuotedContent = true;
                break;
            }
        }
        
        // If we hit quoted content, stop collecting
        if ($isQuotedContent) {
            break;
        }
        
        // Add this line to the reply
        if (!empty($line)) {
            $replyLines[] = $line;
        }
    }
    
    // Join the reply lines
    $cleanBody = implode("\n", $replyLines);
    
    // If we didn't get anything, try a different approach - take first few sentences
    if (empty($cleanBody) || strlen($cleanBody) < 3) {
        // Fallback: take the first meaningful content before any ">" character
        $firstPart = explode('>', $body)[0];
        $cleanBody = trim($firstPart);
        
        // Remove common email artifacts from the beginning
        $cleanBody = preg_replace('/^(Re:|Fwd:|FW:)\s*/i', '', $cleanBody);
        $cleanBody = trim($cleanBody);
    }
    
    // Final cleanup
    $cleanBody = preg_replace('/\s+/', ' ', $cleanBody); // Multiple spaces to single
    $cleanBody = trim($cleanBody);
    
    echo "<h4>Parsed Reply Content:</h4>";
    echo "<div class='parsed'>" . htmlspecialchars($cleanBody) . "</div>";
    
    // Validation
    if (!empty($cleanBody) && strlen($cleanBody) < 200) {
        echo "<p class='success'>✅ Parsing looks good - extracted clean reply content</p>";
    } else if (empty($cleanBody)) {
        echo "<p class='warning'>⚠️ Warning - no content extracted</p>";
    } else {
        echo "<p class='warning'>⚠️ Warning - extracted content might still contain quoted text</p>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<h2>Live Gmail Test</h2>";
echo "<p>To test with real Gmail data, make sure IMAP is enabled and visit <a href='test_simple_gmail.php'>test_simple_gmail.php</a></p>";
?>
