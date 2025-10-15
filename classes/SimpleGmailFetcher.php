<?php
/**
 * Simple Gmail Fetcher using IMAP
 * Reuses existing Gmail SMTP credentials to fetch email replies
 */

class SimpleGmailFetcher {
    private $username;
    private $password;
    private $imapServer;
    
    public function __construct() {
        // Use the same Gmail credentials from your existing setup
        $this->username = 'marcdelacruzesteban@gmail.com';
        $this->password = 'gnnrblabtfpseolu'; // Your existing app password
        $this->imapServer = '{imap.gmail.com:993/imap/ssl}INBOX';
    }
    
    /**
     * Get email replies from a specific patient email
     */
    public function getEmailReplies($patientEmail, $afterDate = null) {
        $replies = [];
        
        // Check if IMAP extension is available
        if (!function_exists('imap_open')) {
            throw new Exception('PHP IMAP extension is not installed. Please enable it in php.ini or visit enable_imap_instructions.html for help.');
        }
        
        try {
            // Connect to Gmail IMAP
            $inbox = imap_open($this->imapServer, $this->username, $this->password);
            
            if (!$inbox) {
                throw new Exception('Cannot connect to Gmail IMAP: ' . imap_last_error());
            }
            
            // Build search criteria
            $searchCriteria = "FROM \"$patientEmail\"";
            
            if ($afterDate) {
                $searchDate = date('d-M-Y', strtotime($afterDate));
                $searchCriteria .= " SINCE \"$searchDate\"";
            }
            
            // Search for emails from patient
            $emails = imap_search($inbox, $searchCriteria);
            
            if ($emails) {
                // Sort emails by date (newest first)
                rsort($emails);
                
                foreach ($emails as $emailNumber) {
                    $header = imap_headerinfo($inbox, $emailNumber);
                    $body = $this->getEmailBody($inbox, $emailNumber);
                    
                    // Skip if this is not a reply (no "Re:" in subject)
                    if (!$this->isReplyEmail($header->subject)) {
                        continue;
                    }
                    
                    $replies[] = [
                        'id' => $emailNumber,
                        'subject' => $header->subject,
                        'from_name' => isset($header->from[0]->personal) ? $header->from[0]->personal : $header->from[0]->mailbox,
                        'from_email' => $header->from[0]->mailbox . '@' . $header->from[0]->host,
                        'date' => date('Y-m-d H:i:s', $header->udate),
                        'timestamp' => $header->udate,
                        'body' => $body,
                        'snippet' => substr(strip_tags($body), 0, 150) . '...'
                    ];
                }
            }
            
            imap_close($inbox);
            
        } catch (Exception $e) {
            error_log("Gmail IMAP Error: " . $e->getMessage());
            return [];
        }
        
        return $replies;
    }
    
    /**
     * Check if email is a reply (has "Re:" in subject)
     */
    private function isReplyEmail($subject) {
        $replyPrefixes = ['Re:', 'RE:', 'Fwd:', 'FWD:'];
        foreach ($replyPrefixes as $prefix) {
            if (stripos($subject, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Extract email body content
     */
    private function getEmailBody($inbox, $emailNumber) {
        $body = '';
        
        // Get email structure
        $structure = imap_fetchstructure($inbox, $emailNumber);
        
        if (!isset($structure->parts)) {
            // Simple email (not multipart)
            $body = imap_fetchbody($inbox, $emailNumber, 1);
            
            if ($structure->encoding == 3) { // Base64
                $body = base64_decode($body);
            } elseif ($structure->encoding == 4) { // Quoted-printable
                $body = quoted_printable_decode($body);
            }
        } else {
            // Multipart email
            foreach ($structure->parts as $partNumber => $part) {
                $partBody = imap_fetchbody($inbox, $emailNumber, $partNumber + 1);
                
                if ($part->encoding == 3) { // Base64
                    $partBody = base64_decode($partBody);
                } elseif ($part->encoding == 4) { // Quoted-printable
                    $partBody = quoted_printable_decode($partBody);
                }
                
                // Prefer plain text, but take HTML if that's all we have
                if ($part->subtype == 'PLAIN') {
                    $body = $partBody;
                    break;
                } elseif ($part->subtype == 'HTML' && empty($body)) {
                    $body = strip_tags($partBody);
                }
            }
        }
        
        // Clean up the body
        $body = trim($body);
        
        // Extract only the new reply content (before any quoted text)
        
        // Enhanced quote indicators to catch more patterns
        $quoteIndicators = [
            '> DermaSculpt',
            '> Dermatology',
            '> Response to Your',
            '> Dear ',
            '> Thank you for contacting',
            '> Dr. ',
            'On ' . date('Y') . '-', // Current year date patterns like "On 2025-"
            'On ' . date('M'), // Month patterns like "On Oct"
            'On Mon,', 'On Tue,', 'On Wed,', 'On Thu,', 'On Fri,', 'On Sat,', 'On Sun,', // Day patterns
            'On Monday,', 'On Tuesday,', 'On Wednesday,', 'On Thursday,', 'On Friday,', 'On Saturday,', 'On Sunday,',
            '-----Original Message-----',
            'From:',
            'Sent:',
            'To:',
            'Subject:',
            'DermaSculpt - Dr.',
            'marcdelacruzesteban@gmail.com',
            '< marcdelacruzesteban@gmail.com>',
            'wrote:'
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
        
        // Limit length to reasonable reply size (avoid huge quoted content)
        if (strlen($cleanBody) > 500) {
            $cleanBody = substr($cleanBody, 0, 500) . '...';
        }
        
        return $cleanBody;
    }
    
    /**
     * Test IMAP connection
     */
    public function testConnection() {
        try {
            $inbox = imap_open($this->imapServer, $this->username, $this->password);
            if ($inbox) {
                $status = imap_status($inbox, $this->imapServer, SA_ALL);
                imap_close($inbox);
                return [
                    'success' => true,
                    'message' => 'IMAP connection successful',
                    'total_messages' => $status->messages
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'IMAP connection failed: ' . imap_last_error()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'IMAP error: ' . $e->getMessage()
            ];
        }
    }
}
?>
