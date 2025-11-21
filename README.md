# Speechify Text-to-Speech Application

A modern web application for converting text to speech using the Speechify API.

## Features

- **Voice Selection**: Choose from multiple voices (Luna, Julien, Oliver, Sarah)
- **Emotion Control**: Adjust the tone/emotion of the speech (Neutral, Happy, Sad, Excited, Calm)
- **Speed Control**: Adjust speech speed from -50% to +50%
- **Pitch Control**: Adjust voice pitch from -50% to +50%
- **Volume Control**: Adjust audio volume from 0% to 100%
- **Add Pause**: Insert pauses in the text for better pacing
- **Real-time Audio Generation**: Generate and play audio instantly

## Installation

1. Place the `voice-speechify` folder in your web server directory
2. Ensure PHP is enabled and cURL extension is installed
3. Update the API key in `api.php` if needed:
   ```php
   $apiKey = 'your-api-key-here';
   ```

## Usage

1. Open `index.php` in your web browser
2. Enter or paste the text you want to convert to speech
3. Select your preferred voice, emotion, speed, pitch, and volume
4. Click the play button to generate and play the audio
5. Use "Add Pause" to insert pauses in your text
6. Press Ctrl+Enter (or Cmd+Enter on Mac) to quickly generate audio

## File Structure

```
voice-speechify/
├── index.php      # Main UI page
├── api.php        # PHP API handler for Speechify integration
├── style.css      # Styling for the UI
├── script.js      # JavaScript for UI interactions
├── output/        # Generated audio files (created automatically)
└── README.md      # This file
```

## API Configuration

The application uses the Speechify API endpoint:
- **URL**: `https://api.sws.speechify.com/v1/audio/speech`
- **Method**: POST
- **Authentication**: Bearer token

## Output

Generated audio files are saved in the `output/` directory with filenames like:
- `speechify_1234567890_abc123.mp3`

## Requirements

- PHP 7.0 or higher
- cURL extension enabled
- Web server (Apache, Nginx, etc.)
- Modern web browser with JavaScript enabled

## Notes

- The API key is currently hardcoded in `api.php`. For production use, consider storing it in an environment variable or configuration file.
- Generated audio files are stored on the server. Consider implementing a cleanup mechanism for old files.
- The application supports the Speechify API format. Some parameters (speed, pitch, emotion) may need to be adjusted based on the actual API capabilities.

