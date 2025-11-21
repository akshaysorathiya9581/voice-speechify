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

// Get parameters from request
$text = trim($data['input']);
// Default to 'oliver' which is known to work
// TODO: Update with correct voice IDs once verified with Speechify API
$voiceId = isset($data['voice_id']) ? trim($data['voice_id']) : 'oliver';
$language = isset($data['language']) ? trim($data['language']) : 'en-US';
$aiInstruction = isset($data['ai_instruction']) ? trim($data['ai_instruction']) : null;

// Validate text length (Speechify API limit is 2000 characters for speech endpoint)
if (strlen($text) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Text input exceeds 2000 character limit']);
    exit;
}

// Build payload - include all available parameters
$payload = [
    'input' => $text,
    'voice_id' => $voiceId
];

// Add language if provided
if ($language) {
    $payload['language'] = $language;
}

// Add AI instruction if provided (for American English)
if ($aiInstruction && !empty($aiInstruction)) {
    $payload['ai_instruction'] = $aiInstruction;
}

// Add speed, pitch, emotion if provided (if API supports them)
if (isset($data['speed'])) {
    $payload['speed'] = intval($data['speed']);
}
if (isset($data['pitch'])) {
    $payload['pitch'] = intval($data['pitch']);
}
if (isset($data['emotion'])) {
    $payload['emotion'] = $data['emotion'];
}

$payload = json_encode($payload);

// API Configuration
$apiKey = 'okl5-3g24ABikhNtTqQUPTiaDqo3SVWDHr3RXSeObcc=';
$apiUrl = 'https://api.sws.speechify.com/v1/audio/speech';

// SSL Configuration
// Set to false for localhost development (fixes SSL certificate issues)
// Set to true for production (recommended for security)
$verifySSL = false;

// Initialize cURL
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

// SSL certificate options for localhost development
// NOTE: These should be set to true in production for security
if (!$verifySSL) {
    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
}

curl_setopt_array($ch, $curlOptions);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// Handle cURL errors
if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . $error]);
    exit;
}

// Handle HTTP errors
if ($httpCode !== 200) {
    http_response_code($httpCode);
    
    // Try to decode response
    $errorData = json_decode($response, true);
    $rawResponse = $response;
    
    // Try to extract detailed error message
    $errorMsg = 'API request failed';
    $errorDetails = [];
    
    if ($errorData && is_array($errorData)) {
        if (isset($errorData['error'])) {
            $errorMsg = is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error']);
        } elseif (isset($errorData['message'])) {
            $errorMsg = is_string($errorData['message']) ? $errorData['message'] : json_encode($errorData['message']);
        } elseif (isset($errorData['detail'])) {
            $errorMsg = is_string($errorData['detail']) ? $errorData['detail'] : json_encode($errorData['detail']);
        } elseif (isset($errorData['errors'])) {
            $errorMsg = is_array($errorData['errors']) ? json_encode($errorData['errors']) : (string)$errorData['errors'];
        }
        $errorDetails = $errorData;
    } else {
        // If not JSON or empty, use raw response
        if (!empty($response)) {
            $errorMsg = 'API request failed. Response: ' . substr($response, 0, 500);
        } else {
            $errorMsg = 'API request failed. Empty response from server.';
        }
    }
    
    // Log full details for debugging
    $debugInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'http_code' => $httpCode,
        'error_message' => $errorMsg,
        'api_response_raw' => substr($rawResponse, 0, 1000),
        'api_response_parsed' => $errorData,
        'payload_sent' => $payload,
        'voice_id' => $voiceId,
        'text_length' => strlen($text),
        'text_preview' => substr($text, 0, 100)
    ];
    
    echo json_encode([
        'error' => $errorMsg,
        'http_code' => $httpCode,
        'response' => substr($rawResponse, 0, 1000), // Include full response for debugging
        'response_parsed' => $errorData, // Parsed response if JSON
        'payload_sent' => $payload, // Include what we sent for debugging
        'voice_id_used' => $voiceId, // Show which voice ID was used
        'debug' => $debugInfo
    ], JSON_PRETTY_PRINT);
    exit;
}

// Decode JSON response
$responseData = json_decode($response, true);

// Check if response is JSON with audio_data (base64 encoded)
if ($responseData && isset($responseData['audio_data'])) {
    // Save audio file
    $filename = 'speechify_' . time() . '_' . uniqid() . '.mp3';
    $outputDir = __DIR__ . '/output/';
    
    // Create output directory if it doesn't exist
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filepath = $outputDir . $filename;
    $audioData = base64_decode($responseData['audio_data']);
    
    if ($audioData === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to decode audio data']);
        exit;
    }
    
    if (file_put_contents($filepath, $audioData) !== false) {
        // Verify file was created and is readable
        if (file_exists($filepath) && filesize($filepath) > 0) {
            // Return success with file URL (use absolute path for better compatibility)
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                       '://' . $_SERVER['HTTP_HOST'] . 
                       dirname($_SERVER['PHP_SELF']);
            $fileUrl = rtrim($baseUrl, '/') . '/output/' . $filename;
            
            // Also return relative path as fallback
            $relativeUrl = 'output/' . $filename;
            
            echo json_encode([
                'success' => true,
                'file_url' => $fileUrl,
                'file_url_relative' => $relativeUrl,
                'filename' => $filename,
                'file_size' => filesize($filepath)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Audio file was created but is empty or unreadable']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save audio file']);
    }
} else {
    // Check if it's an error response
    if ($responseData && isset($responseData['error'])) {
        http_response_code(400);
        echo json_encode(['error' => $responseData['error']]);
        exit;
    }
    
    // Try to handle as binary MP3 response (fallback)
    $filename = 'speechify_' . time() . '_' . uniqid() . '.mp3';
    $outputDir = __DIR__ . '/output/';
    
    // Create output directory if it doesn't exist
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filepath = $outputDir . $filename;
    
    if (file_put_contents($filepath, $response) !== false) {
        // Verify file was created and is readable
        if (file_exists($filepath) && filesize($filepath) > 0) {
            // Return success with file URL (use absolute path for better compatibility)
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                       '://' . $_SERVER['HTTP_HOST'] . 
                       dirname($_SERVER['PHP_SELF']);
            $fileUrl = rtrim($baseUrl, '/') . '/output/' . $filename;
            
            // Also return relative path as fallback
            $relativeUrl = 'output/' . $filename;
            
            echo json_encode([
                'success' => true,
                'file_url' => $fileUrl,
                'file_url_relative' => $relativeUrl,
                'filename' => $filename,
                'file_size' => filesize($filepath)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Audio file was created but is empty or unreadable']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save audio file or invalid response format']);
    }
}
?>

