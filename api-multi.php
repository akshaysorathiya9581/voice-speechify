<?php
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
if (!isset($data['input']) || empty(trim($data['input']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Text input is required']);
    exit;
}

// Get parameters
$text = trim($data['input']);
$filename = isset($data['filename']) ? trim($data['filename']) : 'libre';
// Using 'oliver' as working voice ID - TODO: Replace with correct Luna and Julien voice IDs
$voice1 = isset($data['voice_1']) ? trim($data['voice_1']) : 'oliver'; // Speaker 1 (prefix "1") - Should be Luna
$voice2 = isset($data['voice_2']) ? trim($data['voice_2']) : 'oliver'; // Speaker 2 (prefix "2") - Should be Julien
$language = isset($data['language']) ? trim($data['language']) : 'en-US';
$aiInstruction = isset($data['ai_instruction']) ? trim($data['ai_instruction']) : null;

// API Configuration
$apiKey = 'VbH870ZEHj2AT31wx4elnp3ImLh1DI94C5AyY6TPEEg=';
$apiUrl = 'https://api.sws.speechify.com/v1/audio/speech';
$verifySSL = false;

// Parse text by speaker prefixes (1 and 2)
function parseMultiSpeakerText($text) {
    $segments = [];
    $lines = explode("\n", $text);
    $currentSpeaker = null;
    $currentText = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if (!empty($currentText)) {
                $currentText .= "\n";
            }
            continue;
        }
        
        // Check if line starts with speaker prefix
        if (preg_match('/^([12])\s+(.+)$/', $line, $matches)) {
            // Save previous segment if exists
            if ($currentSpeaker !== null && !empty(trim($currentText))) {
                $segments[] = [
                    'speaker' => $currentSpeaker,
                    'text' => trim($currentText)
                ];
            }
            
            // Start new segment
            $currentSpeaker = $matches[1];
            $currentText = $matches[2];
        } else {
            // Continue current segment
            if ($currentSpeaker !== null) {
                $currentText .= "\n" . $line;
            }
        }
    }
    
    // Add last segment
    if ($currentSpeaker !== null && !empty(trim($currentText))) {
        $segments[] = [
            'speaker' => $currentSpeaker,
            'text' => trim($currentText)
        ];
    }
    
    return $segments;
}

// Generate audio for a single text segment
function generateAudioSegment($text, $voiceId, $apiKey, $apiUrl, $verifySSL, $language = 'en-US', $aiInstruction = null) {
    $payload = [
        'input' => $text,
        'voice_id' => $voiceId,
        'language' => $language
    ];
    
    // Add AI instruction if provided (for American English)
    if ($aiInstruction && !empty($aiInstruction) && $language === 'en-US') {
        $payload['ai_instruction'] = $aiInstruction;
    }
    
    $payload = json_encode($payload);
    
    $ch = curl_init($apiUrl);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json",
            "Accept: audio/mpeg"
        ],
    ];
    
    if (!$verifySSL) {
        $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
    }
    
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'cURL error: ' . $error];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = isset($errorData['error']) ? $errorData['error'] : 'API request failed';
        return ['error' => $errorMsg, 'http_code' => $httpCode];
    }
    
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['audio_data'])) {
        return ['audio_data' => $responseData['audio_data']];
    } else {
        // Assume binary MP3
        return ['audio_binary' => $response];
    }
}

// Parse the text
$segments = parseMultiSpeakerText($text);

if (empty($segments)) {
    http_response_code(400);
    echo json_encode(['error' => 'No speaker segments found. Text should start with "1" or "2" prefix.']);
    exit;
}

// Generate audio for each segment
$outputDir = __DIR__ . '/output/';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$audioFiles = [];
$errors = [];

foreach ($segments as $index => $segment) {
    $speaker = $segment['speaker'];
    $segmentText = $segment['text'];
    $voiceId = ($speaker == '1') ? $voice1 : $voice2;
    
    // Generate audio for this segment
    $result = generateAudioSegment($segmentText, $voiceId, $apiKey, $apiUrl, $verifySSL, $language, $aiInstruction);
    
    if (isset($result['error'])) {
        $errors[] = "Segment " . ($index + 1) . " (Speaker $speaker): " . $result['error'];
        continue;
    }
    
    // Save audio file
    $segmentFilename = $filename . '_segment_' . ($index + 1) . '_speaker' . $speaker . '.mp3';
    $segmentFilepath = $outputDir . $segmentFilename;
    
    $audioData = isset($result['audio_data']) ? base64_decode($result['audio_data']) : $result['audio_binary'];
    
    if (file_put_contents($segmentFilepath, $audioData) !== false) {
        $audioFiles[] = [
            'file' => 'output/' . $segmentFilename,
            'speaker' => $speaker,
            'text' => substr($segmentText, 0, 100) . '...'
        ];
    } else {
        $errors[] = "Failed to save segment " . ($index + 1);
    }
}

// Combine audio files using FFmpeg if available, or return list of files
$combinedFile = null;
if (!empty($audioFiles) && empty($errors)) {
    // Try to combine using FFmpeg
    $combinedFilename = $filename . '.mp3';
    $combinedFilepath = $outputDir . $combinedFilename;
    
    $fileList = $outputDir . 'filelist_' . time() . '.txt';
    $fileListContent = '';
    foreach ($audioFiles as $audioFile) {
        $fullPath = __DIR__ . '/' . $audioFile['file'];
        $fileListContent .= "file '" . str_replace('\\', '/', $fullPath) . "'\n";
    }
    file_put_contents($fileList, $fileListContent);
    
    // Try FFmpeg combination
    $ffmpegCmd = "ffmpeg -f concat -safe 0 -i \"$fileList\" -c copy \"$combinedFilepath\" 2>&1";
    exec($ffmpegCmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($combinedFilepath)) {
        $combinedFile = 'output/' . $combinedFilename;
        // Clean up file list
        @unlink($fileList);
    } else {
        // FFmpeg not available or failed, return individual files
        @unlink($fileList);
    }
}

// Return response
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
           '://' . $_SERVER['HTTP_HOST'] . 
           dirname($_SERVER['PHP_SELF']);

echo json_encode([
    'success' => true,
    'segments_count' => count($segments),
    'audio_files' => $audioFiles,
    'combined_file' => $combinedFile ? rtrim($baseUrl, '/') . '/' . $combinedFile : null,
    'combined_file_relative' => $combinedFile,
    'errors' => $errors,
    'message' => empty($errors) ? 'All segments generated successfully' : 'Some segments had errors'
]);
?>

