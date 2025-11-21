# Speechify Multi-Voice Audio Generator

A server-side PHP script that generates multi-voice audio files using the Speechify API.

## Features

- ✅ Reads text where paragraphs start with `1` (Luna - Female) or `2` (Julien - Male)
- ✅ Builds SSML for each paragraph with prosody, pauses, and tone
- ✅ Includes refinement prompt for English (US): "Read this in a warm, friendly tone with American accent."
- ✅ Calls Speechify's `/v1/audio/speech` endpoint for each paragraph
- ✅ Concatenates all audio parts into a single MP3 file
- ✅ Clean, modular PHP code - easy to reuse
- ✅ Works via CLI or HTTP API

## Requirements

- PHP 7.0 or higher
- cURL extension enabled
- Speechify API key
- (Optional) FFmpeg for reliable audio concatenation (falls back to binary concatenation if not available)

## Installation

1. Place `speechify_multi_voice.php` in your project directory
2. Ensure the `output/` directory exists and is writable
3. Update the API key in the script or pass it as a parameter

## Usage

### Method 1: Command Line Interface (CLI)

```bash
php speechify_multi_voice.php input.txt output.mp3 YOUR_API_KEY
```

Or read from stdin:
```bash
echo "1 Hello world\n2 This is a test" | php speechify_multi_voice.php php://stdin output.mp3 YOUR_API_KEY
```

### Method 2: HTTP API (POST Request)

Send a POST request to `speechify_multi_voice.php` with JSON:

```json
{
  "text": "1 This is paragraph one.\n2 This is paragraph two.",
  "output_file": "speechify_output.mp3",
  "language": "en-US",
  "api_key": "YOUR_API_KEY",
  "female_voice": "sarah",
  "male_voice": "david"
}
```

**Note:** `female_voice` and `male_voice` are optional. If not provided, defaults to `oliver` for both.

**Response (Success):**
```json
{
  "success": true,
  "output_file": "speechify_output.mp3",
  "file_url": "http://yoursite.com/voice-speechify/output/speechify_output.mp3",
  "file_url_relative": "output/speechify_output.mp3",
  "file_size": 123456,
  "segments_processed": 2,
  "errors": []
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Error message here"
}
```

### Method 3: PHP Class Usage

```php
<?php
require_once 'speechify_multi_voice.php';

$apiKey = 'YOUR_API_KEY';
$generator = new SpeechifyMultiVoice($apiKey);

$text = "1 First paragraph with Luna voice.\n2 Second paragraph with Julien voice.";

$result = $generator->generateAudio($text, 'output.mp3', 'en-US');

if ($result['success']) {
    echo "Audio created: {$result['output_path']}\n";
} else {
    echo "Error: {$result['error']}\n";
}
?>
```

## Input Text Format

Each paragraph must start with `1` or `2` followed by a space:

```
1 This paragraph will be read by Luna (female voice).

2 This paragraph will be read by Julien (male voice).

1 You can have multiple paragraphs with the same voice.

2 And switch between voices as needed.
```

## Voice Mappings

- `1` → Female voice (default: `oliver`)
- `2` → Male voice (default: `oliver`)

**Note:** The default voice IDs are set to `oliver` which is known to work. You can customize them using the methods below.

### Setting Custom Voices

**Via Constructor:**
```php
$generator = new SpeechifyMultiVoice($apiKey, null, 'sarah', 'david');
// Parameters: (apiKey, outputDir, femaleVoice, maleVoice)
```

**Via Methods:**
```php
$generator = new SpeechifyMultiVoice($apiKey);
$generator->setVoices('sarah', 'david');  // Set both at once
// Or individually:
$generator->setVoice('1', 'sarah');  // Female voice (prefix 1)
$generator->setVoice('2', 'david');  // Male voice (prefix 2)
```

**Via HTTP API:**
```json
{
  "text": "1 Text here\n2 More text",
  "female_voice": "sarah",
  "male_voice": "david"
}
```

**Common Voice IDs to Try:**
- Female: `oliver`, `sarah`, `luna`, `emma`, `sophia`
- Male: `oliver`, `julien`, `david`, `michael`, `james`

**Important:** Available voice IDs depend on your Speechify account. If a voice ID is not found, check your Speechify dashboard or API documentation for available voices.

## SSML Features

The script automatically generates SSML with:

- **Prosody**: Medium rate, pitch, and volume
- **Pauses**: 300ms before content, 500ms after content
- **Refinement Prompt**: For English (US), includes "Read this in a warm, friendly tone with American accent."
- **Proper XML escaping**: Text is properly escaped for SSML

## Language Support

Currently optimized for English (US) with the refinement prompt. Other languages are supported but won't include the refinement prompt.

To use a different language:
```php
$result = $generator->generateAudio($text, 'output.mp3', 'es-ES');
```

## Audio Concatenation

The script uses two methods for concatenating audio:

1. **FFmpeg** (preferred): Most reliable, handles different MP3 formats
2. **Binary concatenation** (fallback): Works for compatible MP3 files

If FFmpeg is not available, the script will automatically fall back to binary concatenation.

## Configuration

### API Key

You can set the API key in three ways:

1. **Default in script**: Edit line with `$apiKey = '...'` in the HTTP API section
2. **Via HTTP request**: Include `api_key` in the JSON payload
3. **Via CLI**: Pass as third argument

### SSL Verification

For localhost development, SSL verification is disabled by default. For production, set:

```php
$generator = new SpeechifyMultiVoice($apiKey);
$generator->verifySSL = true; // Enable SSL verification
```

### Output Directory

Default: `./output/`

To change:
```php
$generator = new SpeechifyMultiVoice($apiKey, '/path/to/output/');
```

## Error Handling

The script handles various error scenarios:

- Invalid input text format
- API request failures
- Audio generation failures
- File system errors
- Concatenation failures

Errors are logged and returned in the response.

## Example

See `example_usage.php` for a complete working example.

## Notes

- Generated audio files are saved in the `output/` directory
- Temporary files are automatically cleaned up
- The script supports both JSON responses (with base64 audio) and binary MP3 responses from the Speechify API
- Maximum text length per segment depends on Speechify API limits (typically 2000 characters)

## Troubleshooting

### Audio files not concatenating

- Ensure FFmpeg is installed and available in PATH
- Check file permissions on output directory
- Verify all segments were generated successfully

### API errors

- Verify your API key is correct
- **Voice ID not found**: The default voices are set to `oliver`. If you get a "voice not found" error:
  1. Check your Speechify account dashboard for available voice IDs
  2. Use the Speechify API `/v1/voices` endpoint to list available voices
  3. Update the voice IDs using `setVoices()` method or via HTTP API parameters
  4. Common working voices: `oliver`, `sarah`, `david`, `julien`, `luna`, `emma`, `michael`
- Ensure your API quota hasn't been exceeded

### SSML not working

- Some Speechify API versions may not support SSML directly
- Try using plain text with `ai_instruction` parameter instead
- Check Speechify API documentation for SSML support

## License

This script is provided as-is for use with the Speechify API.

