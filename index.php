<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speechify Text-to-Speech</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="tts-card">
            <!-- Control Bar -->
            <div class="control-bar">
                <button class="play-btn" id="playBtn" title="Play / Pause">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" class="play-icon">
                        <path d="M6 4L16 10L6 16V4Z" fill="currentColor"/>
                    </svg>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" class="pause-icon" style="display: none;">
                        <path d="M6 4H10V16H6V4Z" fill="currentColor"/>
                        <path d="M10 4H14V16H10V4Z" fill="currentColor"/>
                    </svg>
                </button>
                
                <div class="voice-avatar" id="voiceAvatar">
                    <img src="https://ui-avatars.com/api/?name=Luna&background=6366f1&color=fff&size=40" alt="Voice Avatar" id="avatarImg">
                </div>
                
                <div class="control-buttons">
                    <select class="control-pill language-select" id="languageSelect">
                        <option value="en-US" selected>American English</option>
                        <option value="en-GB">British English</option>
                        <option value="fr-FR">French</option>
                        <option value="es-ES">Spanish</option>
                        <option value="de-DE">German</option>
                        <option value="it-IT">Italian</option>
                        <option value="pt-BR">Portuguese (Brazil)</option>
                        <option value="ja-JP">Japanese</option>
                        <option value="zh-CN">Chinese (Simplified)</option>
                    </select>
                    
                    <select class="control-pill voice-select" id="voiceSelect">
                        <option value="luna" selected>Luna (F)</option>
                        <option value="julien">Julien (M)</option>
                    </select>
                    
                    <select class="control-pill emotion-select" id="emotionSelect">
                        <option value="neutral">Neutral</option>
                        <option value="happy">Happy</option>
                        <option value="sad">Sad</option>
                        <option value="excited">Excited</option>
                        <option value="calm">Calm</option>
                    </select>
                    
                    <div class="control-pill slider-control">
                        <label>Speed</label>
                        <input type="range" id="speedSlider" min="-50" max="50" value="0" class="slider">
                        <span id="speedValue">0%</span>
                    </div>
                    
                    <div class="control-pill slider-control">
                        <label>Pitch</label>
                        <input type="range" id="pitchSlider" min="-50" max="50" value="0" class="slider">
                        <span id="pitchValue">0%</span>
                    </div>
                    
                    <div class="control-pill slider-control">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M8 2V14M3 7H13" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <input type="range" id="volumeSlider" min="0" max="100" value="100" class="slider">
                        <span id="volumeValue">100%</span>
                    </div>
                    
                    <button class="control-pill pause-btn" id="addPauseBtn">Add Pause</button>
                </div>
                
                <div class="control-actions">
                    <button class="icon-btn" id="moreOptionsBtn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="4" r="1.5" fill="currentColor"/>
                            <circle cx="10" cy="10" r="1.5" fill="currentColor"/>
                            <circle cx="10" cy="16" r="1.5" fill="currentColor"/>
                        </svg>
                    </button>
                    <div class="separator"></div>
                    <input type="checkbox" id="settingsCheck" class="checkbox">
                </div>
            </div>
            
            <!-- AI Instruction Textbox (shown for American English) -->
            <div class="ai-instruction-container" id="aiInstructionContainer" style="display: none;">
                <label for="aiInstruction" class="ai-instruction-label">AI Instruction (for American English):</label>
                <input 
                    type="text" 
                    id="aiInstruction" 
                    class="ai-instruction-input" 
                    placeholder="Read this in a warm, friendly tone with American accent."
                    maxlength="500"
                >
            </div>
            
            <!-- Text Input Area -->
            <div class="text-input-container">
                <textarea 
                    id="textInput" 
                    class="text-input" 
                    placeholder="Enter your text here..."
                    rows="6"
                    maxlength="2000"
                ></textarea>
                <div class="text-input-footer">
                    <div class="character-count" id="characterCount">
                        <span id="charCount">0</span> / 2000 characters
                    </div>
                    <div class="text-input-actions">
                        <button class="hint-btn" id="hintBtn" title="Get suggestions">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M10 2C5.58 2 2 5.58 2 10C2 14.42 5.58 18 10 18C14.42 18 18 14.42 18 10C18 5.58 14.42 2 10 2ZM10 15C9.45 15 9 14.55 9 14C9 13.45 9.45 13 10 13C10.55 13 11 13.45 11 14C11 14.55 10.55 15 10 15ZM11 11H9V6H11V11Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Audio Player (hidden initially) -->
            <audio id="audioPlayer" style="display: none;"></audio>
            
            <!-- Loading Indicator -->
            <div class="loading-indicator" id="loadingIndicator" style="display: none;">
                <div class="spinner"></div>
                <span>Generating audio...</span>
            </div>
            
            <!-- Error Message -->
            <div class="error-message" id="errorMessage" style="display: none;"></div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>

