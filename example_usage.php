<?php
/**
 * Example usage of speechify_multi_voice.php
 * 
 * This file demonstrates how to use the SpeechifyMultiVoice class
 * to generate multi-voice audio files.
 * 
 * Can be run via CLI or accessed via HTTP browser.
 */

require_once __DIR__ . '/speechify_multi_voice.php';

// Check if running via CLI or HTTP
$isCLI = php_sapi_name() === 'cli';

// Your Speechify API key
$apiKey = 'VbH870ZEHj2AT31wx4elnp3ImLh1DI94C5AyY6TPEEg=';

// Example text with voice prefixes
// 1 = Female voice (default: oliver)
// 2 = Male voice (default: oliver)
$exampleText = <<<TEXT
1 Welcome to our presentation. Today we'll be discussing the latest developments in technology and how they impact our daily lives.

2 Thank you for that introduction. I'm excited to share some insights about artificial intelligence and machine learning.

1 That's a great topic. Let's start by exploring the basics of AI and how it's being used in various industries.

2 Absolutely. AI has revolutionized many sectors, from healthcare to finance, and even creative industries.
TEXT;

// Create generator instance
// You can optionally specify custom voice IDs:
// $generator = new SpeechifyMultiVoice($apiKey, null, 'sarah', 'david');
// Or use the default 'oliver' voice for both

$generator = new SpeechifyMultiVoice($apiKey);

// Alternatively, set custom voices after creation:
// $generator->setVoices('sarah', 'david');  // Female: sarah, Male: david
// Or set individually:
$generator->setVoice('1', 'sarah');  // Set female voice (prefix 1)
$generator->setVoice('2', 'david');  // Set male voice (prefix 2)

// Common voice IDs to try:
// Female: 'oliver', 'sarah', 'luna', 'emma', 'sophia'
// Male: 'oliver', 'julien', 'david', 'michael', 'james'
// Note: Available voices depend on your Speechify account

// Generate audio
$result = $generator->generateAudio($exampleText, 'example_output.mp3', 'en-US');

// Display results
if ($isCLI) {
    // CLI output
    if ($result['success']) {
        echo "✓ Success!\n";
        echo "Output file: {$result['output_file']}\n";
        echo "File path: {$result['output_path']}\n";
        echo "File size: " . number_format($result['file_size']) . " bytes\n";
        echo "Segments processed: {$result['segments_processed']}\n";
        
        if (!empty($result['errors'])) {
            echo "\nWarnings:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    } else {
        echo "✗ Error: {$result['error']}\n";
        exit(1);
    }
} else {
    // HTTP output
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Speechify Multi-Voice Example</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                margin-top: 0;
            }
            .success {
                color: #28a745;
                font-weight: bold;
                font-size: 18px;
            }
            .error {
                color: #dc3545;
                font-weight: bold;
                font-size: 18px;
            }
            .info {
                background: #e9ecef;
                padding: 15px;
                border-radius: 5px;
                margin: 15px 0;
            }
            .info p {
                margin: 5px 0;
            }
            .audio-link {
                display: inline-block;
                margin-top: 15px;
                padding: 10px 20px;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
            .audio-link:hover {
                background: #0056b3;
            }
            .warnings {
                background: #fff3cd;
                padding: 15px;
                border-radius: 5px;
                margin-top: 15px;
                border-left: 4px solid #ffc107;
            }
            .warnings ul {
                margin: 5px 0;
                padding-left: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Speechify Multi-Voice Example</h1>
            
            <?php if ($result['success']): ?>
                <div class="success">✓ Success! Audio file generated successfully.</div>
                
                <div class="info">
                    <p><strong>Output file:</strong> <?php echo htmlspecialchars($result['output_file']); ?></p>
                    <p><strong>File size:</strong> <?php echo number_format($result['file_size']); ?> bytes</p>
                    <p><strong>Segments processed:</strong> <?php echo $result['segments_processed']; ?></p>
                </div>
                
                <?php
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                           '://' . $_SERVER['HTTP_HOST'] . 
                           dirname($_SERVER['PHP_SELF']);
                $fileUrl = rtrim($baseUrl, '/') . '/output/' . $result['output_file'];
                ?>
                
                <a href="<?php echo htmlspecialchars($fileUrl); ?>" class="audio-link" target="_blank">
                    ▶ Play Audio File
                </a>
                
                <?php if (!empty($result['errors'])): ?>
                    <div class="warnings">
                        <strong>Warnings:</strong>
                        <ul>
                            <?php foreach ($result['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="error">✗ Error: <?php echo htmlspecialchars($result['error']); ?></div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>

