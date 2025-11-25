<?php
/**
 * Speechify Multi-Voice Audio Generator
 * 
 * This script reads text where paragraphs start with "1" (Luna - Female) or "2" (Julien - Male),
 * generates SSML for each paragraph, calls Speechify API, and concatenates all audio parts
 * into a single MP3 file.
 * 
 * Usage:
 *   php speechify_multi_voice.php [input_file] [output_file] [api_key]
 * 
 * Or call via HTTP POST with JSON:
 *   {
 *     "text": "1 Paragraph one...\n2 Paragraph two...",
 *     "language": "en-US",
 *     "output_file": "speechify_output.mp3"
 *   }
 */

class SpeechifyMultiVoice {
    private $apiKey;
    private $apiUrl = 'https://api.sws.speechify.com/v1/audio/speech';
    private $verifySSL = false;
    private $outputDir;
    private $lastApiError = '';
    private $maxRetries = 3;
    private $retryDelaySeconds = 2;
    
    // Voice mappings - using default working voices
    // You can customize these by calling setVoice() method
    private $voices = [
        '1' => 'oliver',  // Default female voice (change if needed)
        '2' => 'oliver'   // Default male voice (change if needed)
    ];
    
    // Common voice IDs that might work (adjust based on your Speechify account)
    // Female voices: 'oliver', 'sarah', 'luna', 'emma', 'sophia'
    // Male voices: 'oliver', 'julien', 'david', 'michael', 'james'
    
    public function __construct($apiKey, $outputDir = null, $femaleVoice = null, $maleVoice = null) {
        $this->apiKey = $apiKey;
        $this->outputDir = $outputDir ?: __DIR__ . '/output/';
        
        // Set custom voices if provided
        if ($femaleVoice !== null) {
            $this->voices['1'] = $femaleVoice;
        }
        if ($maleVoice !== null) {
            $this->voices['2'] = $maleVoice;
        }
        
        // Create output directory if it doesn't exist
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }
    
    /**
     * Set voice ID for a specific prefix
     * 
     * @param string $prefix Voice prefix ('1' for female, '2' for male)
     * @param string $voiceId Voice ID from Speechify
     * @return void
     */
    public function setVoice($prefix, $voiceId) {
        if (isset($this->voices[$prefix])) {
            $this->voices[$prefix] = $voiceId;
        }
    }
    
    /**
     * Set both voices at once
     * 
     * @param string $femaleVoice Voice ID for prefix '1' (female)
     * @param string $maleVoice Voice ID for prefix '2' (male)
     * @return void
     */
    public function setVoices($femaleVoice, $maleVoice) {
        $this->voices['1'] = $femaleVoice;
        $this->voices['2'] = $maleVoice;
    }
    
    /**
     * Get current voice IDs
     * 
     * @return array Array with '1' and '2' keys containing voice IDs
     */
    public function getVoices() {
        return $this->voices;
    }
    
    /**
     * Parse text into segments based on voice prefixes (1 or 2)
     * 
     * @param string $text Input text with paragraphs starting with 1 or 2
     * @return array Array of segments with 'voice' and 'text' keys
     */
    public function parseText($text) {
        $segments = [];
        $lines = explode("\n", $text);
        $currentVoice = null;
        $currentText = '';
        $foundPrefixedLine = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines but preserve paragraph breaks
            if (empty($line)) {
                if (!empty($currentText)) {
                    $currentText .= "\n\n";
                }
                continue;
            }
            
            // Check if line starts with voice prefix (1 or 2)
            if (preg_match('/^([12])\s+(.+)$/', $line, $matches)) {
                $foundPrefixedLine = true;
                // Save previous segment if exists
                if ($currentVoice !== null && !empty(trim($currentText))) {
                    $segments[] = [
                        'voice' => $currentVoice,
                        'text' => trim($currentText)
                    ];
                }
                
                // Start new segment
                $currentVoice = $matches[1];
                $currentText = $matches[2];
            } else {
                // Continue current segment
                if ($currentVoice !== null) {
                    $currentText .= "\n" . $line;
                }
            }
        }
        
        // Add last segment
        if ($currentVoice !== null && !empty(trim($currentText))) {
            $segments[] = [
                'voice' => $currentVoice,
                'text' => trim($currentText)
            ];
        }
        
        if (empty($segments) && !$foundPrefixedLine) {
            $fallbackVoice = isset($this->voices['1']) ? '1' : null;
            if ($fallbackVoice === null) {
                foreach ($this->voices as $voiceKey => $_) {
                    $fallbackVoice = $voiceKey;
                    break;
                }
            }
            if (!empty(trim($text)) && $fallbackVoice !== null) {
                $segments[] = [
                    'voice' => $fallbackVoice,
                    'text' => trim($text)
                ];
            }
        }
        
        return $segments;
    }
    
    /**
     * Build SSML for a text segment
     * 
     * @param string $text The text content
     * @param string $voiceId Resolved Speechify voice ID
     * @param string $language Language code (e.g., 'en-US')
     * @return string SSML formatted string
     */
    public function buildSSML($text, $voiceId, $language = 'en-US') {
        // Use minimal SSML - just wrap the text, no voice tags or prosody
        // Voice is specified in API call, prosody/instructions via ai_instruction parameter
        $ssml = '<speak>' . htmlspecialchars($text, ENT_XML1, 'UTF-8') . '</speak>';
        
        return $ssml;
    }
    
    /**
     * Call Speechify API to generate audio
     * 
     * @param string $ssml SSML formatted text
     * @param string $voiceId Voice ID (Luna or Julien)
     * @param string $language Language code
     * @return string|false Audio binary data or false on error
     */
    public function callSpeechifyAPI($ssml, $voiceId, $language = 'en-US') {
        // Extract plain text from SSML for cleaner input
        $plainText = strip_tags($ssml);
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $plainText = trim($plainText);
        
        $payload = [
            'input' => $plainText,  // Only the actual text to be spoken - NO refinement prompt here
            'voice_id' => $voiceId,
            'language' => $language
        ];
        
        // Add AI instruction for English (US) - this is sent as a separate parameter
        // It guides the voice tone/style but should NOT be spoken in the audio
        if ($language === 'en-US') {
            $payload['ai_instruction'] = 'Read this in a warm, friendly tone with American accent.';
        }
        
        $payloadJson = json_encode($payload);
        
        $ch = curl_init($this->apiUrl);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/json",
                "Accept: audio/mpeg"
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15
        ];
        
        // SSL certificate options
        if (!$this->verifySSL) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->lastApiError = "cURL error: $error";
            error_log("Speechify API cURL error: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = isset($errorData['error']) ? $errorData['error'] : "API request failed with HTTP $httpCode";
            
            // Check if it's a voice ID error
            $errorStr = is_string($errorData) ? $errorData : json_encode($errorData);
            if (stripos($errorStr, 'voice') !== false || stripos($errorStr, 'not found') !== false) {
                error_log("Speechify API error: Voice ID '$voiceId' not found. Error: $errorMsg");
            } else {
                error_log("Speechify API error: $errorMsg");
            }
            
            $this->lastApiError = $errorMsg;
            return false;
        }
        
        $this->lastApiError = '';
        
        // Try to decode as JSON first (some APIs return JSON with base64 audio)
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['audio_data'])) {
            return base64_decode($responseData['audio_data']);
        } elseif ($responseData && isset($responseData['audioContent'])) {
            return base64_decode($responseData['audioContent']);
        }
        
        // Assume binary MP3 response
        return $response;
    }
    
    /**
     * Concatenate multiple MP3 files into one
     * 
     * @param array $audioFiles Array of file paths
     * @param string $outputFile Output file path
     * @return bool True on success, false on failure
     */
    public function concatenateAudioFiles($audioFiles, $outputFile) {
        if (empty($audioFiles)) {
            return false;
        }
        
        // Try FFmpeg first (most reliable)
        if ($this->hasFFmpeg()) {
            if ($this->concatenateWithFFmpeg($audioFiles, $outputFile)) {
                return true;
            }
            
            error_log('FFmpeg concatenation failed, falling back to binary merge.');
        }
        
        // Fallback to binary concatenation (works for compatible MP3s)
        return $this->concatenateBinary($audioFiles, $outputFile);
    }
    
    /**
     * Check if FFmpeg is available
     * 
     * @return bool
     */
    private function hasFFmpeg() {
        $output = [];
        $returnCode = 0;
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Concatenate audio files using FFmpeg
     * 
     * @param array $audioFiles Array of file paths
     * @param string $outputFile Output file path
     * @return bool
     */
    private function concatenateWithFFmpeg($audioFiles, $outputFile) {
        $fileList = $this->outputDir . 'filelist_' . time() . '_' . uniqid() . '.txt';
        $fileListContent = '';
        
        foreach ($audioFiles as $audioFile) {
            $fullPath = realpath($audioFile);
            if ($fullPath) {
                $fileListContent .= "file '" . str_replace('\\', '/', $fullPath) . "'\n";
            }
        }
        
        if (empty($fileListContent)) {
            return false;
        }
        
        file_put_contents($fileList, $fileListContent);
        
        $fileListArg = $this->escapeShellArg($fileList);
        $outputArg = $this->escapeShellArg($outputFile);
        
        // Re-encode while concatenating to eliminate encoder delay pops
        $ffmpegCmd = sprintf(
            'ffmpeg -hide_banner -loglevel warning -y -f concat -safe 0 -i %s -c:a libmp3lame -b:a 192k -ar 44100 -ac 2 %s 2>&1',
            $fileListArg,
            $outputArg
        );
        
        exec($ffmpegCmd, $output, $returnCode);
        
        // Clean up file list
        @unlink($fileList);
        
        if ($returnCode !== 0) {
            error_log('FFmpeg error: ' . implode("\n", $output));
        }
        
        return $returnCode === 0 && file_exists($outputFile) && filesize($outputFile) > 0;
    }
    
    /**
     * Concatenate audio files using binary concatenation
     * Note: This works for MP3 files in the same format, but may not work for all cases
     * 
     * @param array $audioFiles Array of file paths
     * @param string $outputFile Output file path
     * @return bool
     */
    private function concatenateBinary($audioFiles, $outputFile) {
        $outputHandle = fopen($outputFile, 'wb');
        if (!$outputHandle) {
            return false;
        }
        
        $success = true;
        foreach ($audioFiles as $index => $audioFile) {
            if (!file_exists($audioFile)) {
                $success = false;
                continue;
            }
            
            $audioData = file_get_contents($audioFile);
            if ($audioData === false) {
                $success = false;
                continue;
            }
            
            // Strip metadata headers from every file except the first one to avoid audible pops
            if ($index > 0) {
                $audioData = $this->stripMp3Headers($audioData);
                if ($audioData === '') {
                    $success = false;
                    continue;
                }
            }
            
            // For MP3, we can try simple binary concatenation
            // This works if all files have the same bitrate and format
            if (fwrite($outputHandle, $audioData) === false) {
                $success = false;
            }
        }
        
        fclose($outputHandle);
        
        return $success && file_exists($outputFile) && filesize($outputFile) > 0;
    }
    
    /**
     * Remove MP3 headers (ID3v2/ID3v1) that cause audible artifacts when concatenating
     * Only used for non-leading chunks so the final file keeps a single header.
     *
     * @param string $audioData
     * @return string
     */
    private function stripMp3Headers($audioData) {
        $dataLength = strlen($audioData);
        
        // Remove ID3v2 header at the start (if present)
        if ($dataLength >= 10 && substr($audioData, 0, 3) === 'ID3') {
            $sizeBytes = substr($audioData, 6, 4);
            if (strlen($sizeBytes) === 4) {
                $size = 0;
                for ($i = 0; $i < 4; $i++) {
                    $size = ($size << 7) | (ord($sizeBytes[$i]) & 0x7F);
                }
                $headerSize = 10 + $size;
                if ($headerSize < $dataLength) {
                    $audioData = substr($audioData, $headerSize);
                    $dataLength = strlen($audioData);
                } else {
                    return '';
                }
            }
        }
        
        // Remove ID3v1 tag at the end (128 bytes) if present
        if ($dataLength >= 128 && substr($audioData, -128, 3) === 'TAG') {
            $audioData = substr($audioData, 0, -128);
        }
        
        return $audioData;
    }
    
    /**
     * Generate multi-voice audio from text
     * 
     * @param string $text Input text with voice prefixes
     * @param string $outputFile Output file name (without path)
     * @param string $language Language code (default: 'en-US')
     * @return array Result array with success status and file info
     */
    public function generateAudio($text, $outputFile = 'speechify_output.mp3', $language = 'en-US') {
        // Parse text into segments
        $segments = $this->parseText($text);
        
        if (empty($segments)) {
            return [
                'success' => false,
                'error' => 'No valid segments found. Text should start with "1" or "2" prefix.'
            ];
        }
        
        $tempFiles = [];
        $errors = [];
        
        // Generate audio for each segment
        foreach ($segments as $index => $segment) {
            $voice = $segment['voice'];
            $text = $segment['text'];
            $voiceId = $this->voices[$voice];
            
            // Build SSML
            $ssml = $this->buildSSML($text, $voiceId, $language);
            
            // Call API with retry logic
            $audioData = $this->requestAudioWithRetries($ssml, $voiceId, $language);
            
            if ($audioData === false) {
                $errorMsg = $this->lastApiError ?: 'Failed to generate audio';
                $errors[] = "Segment " . ($index + 1) . " (Voice $voiceId): $errorMsg";
                continue;
            }
            
            // Save temporary audio file
            $tempFile = $this->outputDir . 'temp_' . time() . '_' . $index . '_' . uniqid() . '.mp3';
            if (file_put_contents($tempFile, $audioData) === false) {
                $errors[] = "Segment " . ($index + 1) . ": Failed to save audio file";
                continue;
            }
            
            $tempFiles[] = $tempFile;
        }
        
        if (empty($tempFiles)) {
            return [
                'success' => false,
                'error' => 'No audio segments were generated. ' . implode('; ', $errors)
            ];
        }
        
        // Concatenate all audio files
        $finalOutputFile = $this->outputDir . $outputFile;
        $concatenated = $this->concatenateAudioFiles($tempFiles, $finalOutputFile);
        
        // Clean up temporary files
        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }
        
        if (!$concatenated) {
            return [
                'success' => false,
                'error' => 'Failed to concatenate audio files. ' . implode('; ', $errors)
            ];
        }
        
        return [
            'success' => true,
            'output_file' => $outputFile,
            'output_path' => $finalOutputFile,
            'file_size' => filesize($finalOutputFile),
            'segments_processed' => count($segments),
            'errors' => $errors
        ];
    }

    /**
     * Helper to request audio with retries
     * 
     * @param string $ssml
     * @param string $voiceId
     * @param string $language
     * @return string|false
     */
    private function requestAudioWithRetries($ssml, $voiceId, $language) {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $audioData = $this->callSpeechifyAPI($ssml, $voiceId, $language);
            
            if ($audioData !== false && strlen($audioData) > 0) {
                return $audioData;
            }
            
            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelaySeconds * $attempt);
            }
        }
        
        return false;
    }
    
    /**
     * Escape shell arguments cross-platform
     * 
     * @param string $argument
     * @return string
     */
    private function escapeShellArg($argument) {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '""', $argument) . '"';
        }
        
        return escapeshellarg($argument);
    }
    
    /**
     * Retrieve the last API error message
     * 
     * @return string
     */
    public function getLastApiError() {
        return $this->lastApiError;
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    $inputFile = $argv[1] ?? 'php://stdin';
    $outputFile = $argv[2] ?? 'speechify_output.mp3';
    $apiKey = $argv[3] ?? '';
    
    if (empty($apiKey)) {
        die("Usage: php speechify_multi_voice.php [input_file] [output_file] [api_key]\n");
    }
    
    $text = file_get_contents($inputFile);
    if ($text === false) {
        die("Error: Could not read input file: $inputFile\n");
    }
    
    $generator = new SpeechifyMultiVoice($apiKey);
    $result = $generator->generateAudio($text, $outputFile);
    
    if ($result['success']) {
        echo "Success! Audio file created: {$result['output_file']}\n";
        echo "File size: " . number_format($result['file_size']) . " bytes\n";
        echo "Segments processed: {$result['segments_processed']}\n";
    } else {
        echo "Error: {$result['error']}\n";
        exit(1);
    }
    
    exit(0);
}

// HTTP API Usage - Only run when this file is accessed directly (not when included)
// Check if this file is the main script being executed
$isDirectAccess = (
    php_sapi_name() !== 'cli' && 
    isset($_SERVER['SCRIPT_FILENAME']) && 
    realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
);

if ($isDirectAccess) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    // Validate required fields
    if (!isset($data['text']) || empty(trim($data['text']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Text input is required']);
        exit;
    }

    // Get parameters
    $text = trim($data['text']);
    $outputFile = isset($data['output_file']) ? trim($data['output_file']) : 'speechify_output.mp3';
    $language = isset($data['language']) ? trim($data['language']) : 'en-US';
    $apiKey = isset($data['api_key']) ? trim($data['api_key']) : '';
    $femaleVoice = isset($data['female_voice']) ? trim($data['female_voice']) : null;
    $maleVoice = isset($data['male_voice']) ? trim($data['male_voice']) : null;

    // Use default API key if not provided (you can set this)
    if (empty($apiKey)) {
        // Default API key - replace with your actual key or use environment variable
        $apiKey = 'okl5-3g24ABikhNtTqQUPTiaDqo3SVWDHr3RXSeObcc=';
    }

    // Generate audio
    // Create generator with optional custom voices
    $generator = new SpeechifyMultiVoice($apiKey, null, $femaleVoice, $maleVoice);
    $result = $generator->generateAudio($text, $outputFile, $language);

    // Return response
    if ($result['success']) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . 
                   dirname($_SERVER['PHP_SELF']);
        $fileUrl = rtrim($baseUrl, '/') . '/output/' . $outputFile;
        
        echo json_encode([
            'success' => true,
            'output_file' => $outputFile,
            'file_url' => $fileUrl,
            'file_url_relative' => 'output/' . $outputFile,
            'file_size' => $result['file_size'],
            'segments_processed' => $result['segments_processed'],
            'errors' => $result['errors']
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ], JSON_PRETTY_PRINT);
    }
}
?>

