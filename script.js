// Voice configuration
// US Voices: Luna (F) and Julien (M)
// Currently using "oliver" as a working voice ID (known to work from original function)
// Update the voiceId values below with the correct IDs from Speechify API
const voiceConfig = {
    luna: {
        name: 'Luna (F)',
        avatar: 'https://ui-avatars.com/api/?name=Luna&background=6366f1&color=fff&size=40',
        voiceId: 'oliver' // US Female voice - TODO: Replace with correct Luna voice ID from Speechify API
    },
    julien: {
        name: 'Julien (M)',
        avatar: 'https://ui-avatars.com/api/?name=Julien&background=10b981&color=fff&size=40',
        voiceId: 'oliver' // US Male voice - TODO: Replace with correct Julien voice ID from Speechify API
    },
};

// DOM Elements
const playBtn = document.getElementById('playBtn');
const languageSelect = document.getElementById('languageSelect');
const voiceSelect = document.getElementById('voiceSelect');
const emotionSelect = document.getElementById('emotionSelect');
const aiInstructionContainer = document.getElementById('aiInstructionContainer');
const aiInstruction = document.getElementById('aiInstruction');
const speedSlider = document.getElementById('speedSlider');
const pitchSlider = document.getElementById('pitchSlider');
const volumeSlider = document.getElementById('volumeSlider');
const speedValue = document.getElementById('speedValue');
const pitchValue = document.getElementById('pitchValue');
const volumeValue = document.getElementById('volumeValue');
const textInput = document.getElementById('textInput');
const addPauseBtn = document.getElementById('addPauseBtn');
const hintBtn = document.getElementById('hintBtn');
const voiceAvatar = document.getElementById('voiceAvatar');
const avatarImg = document.getElementById('avatarImg');
const audioPlayer = document.getElementById('audioPlayer');
const loadingIndicator = document.getElementById('loadingIndicator');
const errorMessage = document.getElementById('errorMessage');
const characterCount = document.getElementById('characterCount');
const charCount = document.getElementById('charCount');

let currentAudioUrl = null;
let isPlaying = false;
let lastGeneratedText = ''; // Track the last text that was converted to audio
let lastGeneratedVoice = ''; // Track the last voice used
let lastGeneratedSettings = {}; // Track the last settings used (speed, pitch, emotion)

// Update voice avatar when voice changes
voiceSelect.addEventListener('change', (e) => {
    const selectedVoice = voiceConfig[e.target.value];
    if (selectedVoice) {
        avatarImg.src = selectedVoice.avatar;
    }
});

// Show/hide AI instruction based on language selection
languageSelect.addEventListener('change', (e) => {
    const selectedLanguage = e.target.value;
    // Show AI instruction for American English
    if (selectedLanguage === 'en-US') {
        aiInstructionContainer.style.display = 'block';
    } else {
        aiInstructionContainer.style.display = 'none';
        aiInstruction.value = ''; // Clear instruction when language changes
    }
});

// Initialize AI instruction visibility
if (languageSelect.value === 'en-US') {
    aiInstructionContainer.style.display = 'block';
}

// Update slider values display
speedSlider.addEventListener('input', (e) => {
    speedValue.textContent = e.target.value + '%';
});

pitchSlider.addEventListener('input', (e) => {
    pitchValue.textContent = e.target.value + '%';
});

volumeSlider.addEventListener('input', (e) => {
    volumeValue.textContent = e.target.value + '%';
    if (audioPlayer) {
        audioPlayer.volume = e.target.value / 100;
    }
});

// Update character count
function updateCharacterCount() {
    const count = textInput.value.length;
    const maxLength = 2000;
    charCount.textContent = count;
    
    // Update styling based on character count
    characterCount.classList.remove('warning', 'error');
    if (count > maxLength * 0.9) {
        characterCount.classList.add('error');
    } else if (count > maxLength * 0.75) {
        characterCount.classList.add('warning');
    }
    
    // Reset audio if text or settings change (so new audio will be generated)
    const currentText = textInput.value.trim();
    const selectedVoice = voiceConfig[voiceSelect.value];
    const currentSettings = {
        voice: selectedVoice ? selectedVoice.voiceId : '',
        speed: parseInt(speedSlider.value),
        pitch: parseInt(pitchSlider.value),
        emotion: emotionSelect.value
    };
    
    const textChanged = currentText !== lastGeneratedText;
    const voiceChanged = selectedVoice && selectedVoice.voiceId !== lastGeneratedVoice;
    const settingsChanged = JSON.stringify(currentSettings) !== JSON.stringify(lastGeneratedSettings);
    
    if (textChanged || voiceChanged || settingsChanged) {
        currentAudioUrl = null;
        audioPlayer.src = '';
        if (isPlaying) {
            audioPlayer.pause();
            isPlaying = false;
        }
        updateButtonStates();
    }
}

// Update character count on input
textInput.addEventListener('input', updateCharacterCount);
textInput.addEventListener('paste', () => {
    setTimeout(updateCharacterCount, 0);
});

// Initialize character count
updateCharacterCount();

// Add pause button
addPauseBtn.addEventListener('click', () => {
    const cursorPos = textInput.selectionStart;
    const textBefore = textInput.value.substring(0, cursorPos);
    const textAfter = textInput.value.substring(cursorPos);
    textInput.value = textBefore + ' [PAUSE] ' + textAfter;
    textInput.focus();
    textInput.setSelectionRange(cursorPos + 9, cursorPos + 9);
    updateCharacterCount();
});

// Hint button
hintBtn.addEventListener('click', () => {
    const hints = [
        "Try using different emotions to change the tone of the voice.",
        "Adjust speed to make the speech faster or slower.",
        "Use pitch to make the voice higher or lower.",
        "Add pauses using the 'Add Pause' button for better pacing.",
        "Experiment with different voices to find the perfect match."
    ];
    const randomHint = hints[Math.floor(Math.random() * hints.length)];
    alert(randomHint);
});

// Update button visibility based on audio state
function updateButtonStates() {
    const playIcon = playBtn.querySelector('.play-icon');
    const pauseIcon = playBtn.querySelector('.pause-icon');
    
    if (isPlaying) {
        // Audio is playing - show pause icon
        playBtn.classList.add('playing');
        if (playIcon) playIcon.style.display = 'none';
        if (pauseIcon) pauseIcon.style.display = 'block';
    } else {
        // Audio is paused or stopped - show play icon
        playBtn.classList.remove('playing');
        if (playIcon) playIcon.style.display = 'block';
        if (pauseIcon) pauseIcon.style.display = 'none';
    }
}

// Play button functionality
playBtn.addEventListener('click', async () => {
    console.log('Play button clicked');
    const text = textInput.value.trim();
    
    if (!text) {
        showError('Please enter some text to convert to speech.');
        return;
    }
    
    // Get current settings
    const selectedVoice = voiceConfig[voiceSelect.value];
    const currentSettings = {
        voice: selectedVoice ? selectedVoice.voiceId : '',
        speed: parseInt(speedSlider.value),
        pitch: parseInt(pitchSlider.value),
        emotion: emotionSelect.value
    };
    
    console.log('Text to convert:', text);
    console.log('Current settings:', currentSettings);
    
    // If audio is currently playing, pause it first
    if (isPlaying) {
        console.log('Pausing current audio');
        audioPlayer.pause();
        isPlaying = false;
        updateButtonStates();
    }
    
    // Always generate new audio with current text and settings
    console.log('Generating new audio with current text and settings');
    await generateAudio();
});


// Check if text has multi-speaker format (prefixes 1 and 2)
function hasMultiSpeakerFormat(text) {
    // Check if text starts with "1 " or "2 " or contains lines starting with "1 " or "2 "
    const trimmed = text.trim();
    return /^[12]\s+/.test(trimmed) || /\n[12]\s+/.test(text);
}

// Generate audio from Speechify API
async function generateAudio() {
    const text = textInput.value.trim();
    
    if (!text) {
        showError('Please enter some text to convert to speech.');
        return;
    }
    
    console.log('generateAudio called with text:', text);
    
    // Show loading
    loadingIndicator.style.display = 'flex';
    errorMessage.style.display = 'none';
    if (playBtn) {
        playBtn.disabled = true;
        playBtn.style.pointerEvents = 'none';
    }
    
    // Check if text has multi-speaker format (prefixes 1 and 2)
    const isMultiSpeaker = hasMultiSpeakerFormat(text);
    
    if (isMultiSpeaker) {
        console.log('Multi-speaker format detected, using api-multi.php');
        await generateMultiSpeakerAudio(text);
        return;
    }
    
    // Single speaker mode
    // Prepare request data
    const selectedVoice = voiceConfig[voiceSelect.value];
    
    if (!selectedVoice) {
        console.error('No voice selected or voice not found:', voiceSelect.value);
        showError('Please select a valid voice.');
        loadingIndicator.style.display = 'none';
        playBtn.disabled = false;
        return;
    }
    
    console.log('Selected voice:', selectedVoice);
    
    // Get AI instruction if American English is selected
    const aiInstructionText = (languageSelect.value === 'en-US' && aiInstruction.value.trim()) 
        ? aiInstruction.value.trim() 
        : null;
    
    const requestData = {
        input: text,
        voice_id: selectedVoice.voiceId,
        language: languageSelect.value,
        speed: parseInt(speedSlider.value),
        pitch: parseInt(pitchSlider.value),
        emotion: emotionSelect.value
    };
    
    // Add AI instruction if provided (for American English)
    if (aiInstructionText) {
        requestData.ai_instruction = aiInstructionText;
    }
    
    console.log('Request data:', requestData);
    console.log('Calling api.php...');
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });
        
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            console.error('Response was:', responseText);
            throw new Error('Invalid JSON response from server: ' + responseText.substring(0, 100));
        }
        
        console.log('Parsed response data:', data);
        
        if (!response.ok) {
            // Show detailed error message
            console.error('Response not OK. Status:', response.status);
            console.error('Full response data:', data);
            console.error('Response text:', responseText);
            
            let errorMsg = 'API request failed';
            
            if (data) {
                if (data.error) {
                    errorMsg = data.error;
                } else if (data.message) {
                    errorMsg = data.message;
                } else if (data.detail) {
                    errorMsg = data.detail;
                }
                
                if (data.response) {
                    console.error('API Response:', data.response);
                    errorMsg += '\n\nFull API response logged to console.';
                }
                
                if (data.payload_sent) {
                    console.error('Payload sent:', data.payload_sent);
                }
                
                if (data.http_code) {
                    errorMsg += `\nHTTP Code: ${data.http_code}`;
                }
            } else {
                errorMsg += `\nHTTP Status: ${response.status}\nResponse: ${responseText.substring(0, 200)}`;
            }
            
            throw new Error(errorMsg);
        }
        
        if (data.success && (data.file_url || data.file_url_relative)) {
            // Store the text and settings that were converted
            lastGeneratedText = text;
            lastGeneratedVoice = selectedVoice.voiceId;
            lastGeneratedSettings = {
                voice: selectedVoice.voiceId,
                speed: parseInt(speedSlider.value),
                pitch: parseInt(pitchSlider.value),
                emotion: emotionSelect.value
            };
            
            console.log('Stored generation data:', {
                text: lastGeneratedText,
                voice: lastGeneratedVoice,
                settings: lastGeneratedSettings
            });
            
            // Clear previous audio source
            audioPlayer.pause();
            audioPlayer.src = '';
            audioPlayer.load();
            
            // Get the audio URL - prefer absolute URL, fallback to relative
            const audioUrl = data.file_url || data.file_url_relative;
            currentAudioUrl = audioUrl;
            
            // Wait for audio to load before playing
            return new Promise((resolve, reject) => {
                let timeoutId;
                
                // Remove any existing event listeners to avoid duplicates
                const handleCanPlay = () => {
                    clearTimeout(timeoutId);
                    audioPlayer.volume = volumeSlider.value / 100;
                    audioPlayer.play()
                        .then(() => {
                            isPlaying = true;
                            updateButtonStates();
                            resolve();
                        })
                        .catch((playError) => {
                            console.error('Play error:', playError);
                            showError('Error playing audio: ' + playError.message);
                            isPlaying = false;
                            updateButtonStates();
                            reject(playError);
                        });
                    audioPlayer.removeEventListener('canplay', handleCanPlay);
                    audioPlayer.removeEventListener('error', handleError);
                };
                
                const handleError = (error) => {
                    clearTimeout(timeoutId);
                    console.error('Audio load error:', error);
                    console.error('Audio URL attempted:', audioUrl);
                    showError('Error loading audio file. Please check if the file exists: ' + audioUrl);
                    isPlaying = false;
                    updateButtonStates();
                    audioPlayer.removeEventListener('canplay', handleCanPlay);
                    audioPlayer.removeEventListener('error', handleError);
                    reject(error);
                };
                
                audioPlayer.addEventListener('canplay', handleCanPlay, { once: true });
                audioPlayer.addEventListener('error', handleError, { once: true });
                
                // Set source and load
                audioPlayer.src = audioUrl;
                audioPlayer.load();
                
                // Set timeout in case audio doesn't load
                timeoutId = setTimeout(() => {
                    if (audioPlayer.readyState < 2) { // HAVE_CURRENT_DATA
                        showError('Audio file is taking too long to load. Please try again.');
                        isPlaying = false;
                        updateButtonStates();
                        audioPlayer.removeEventListener('canplay', handleCanPlay);
                        audioPlayer.removeEventListener('error', handleError);
                        reject(new Error('Audio load timeout'));
                    }
                }, 10000); // 10 second timeout
            });
        } else {
            throw new Error('Invalid response from server - no file URL provided');
        }
    } catch (error) {
        console.error('Error in generateAudio:', error);
        console.error('Error stack:', error.stack);
        let errorMsg = error.message || 'Failed to generate audio. Please try again.';
        
        // Check if error contains additional details
        if (error.message && error.message.includes('http_code')) {
            errorMsg = error.message;
        }
        
        showError(errorMsg);
    } finally {
        loadingIndicator.style.display = 'none';
        if (playBtn) {
            playBtn.disabled = false;
            playBtn.style.pointerEvents = 'auto';
        }
        console.log('generateAudio completed');
    }
}

// Generate multi-speaker audio
async function generateMultiSpeakerAudio(text) {
    console.log('Generating multi-speaker audio');
    
    // Get AI instruction if American English is selected
    const aiInstructionText = (languageSelect.value === 'en-US' && aiInstruction.value.trim()) 
        ? aiInstruction.value.trim() 
        : null;
    
    // Get voice IDs from voiceConfig
    const lunaVoice = voiceConfig.luna ? voiceConfig.luna.voiceId : 'oliver';
    const julienVoice = voiceConfig.julien ? voiceConfig.julien.voiceId : 'oliver';
    
    const requestData = {
        input: text,
        filename: 'libre',
        voice_1: lunaVoice, // Speaker 1 - Luna (F) from voiceConfig
        voice_2: julienVoice, // Speaker 2 - Julien (M) from voiceConfig
        language: languageSelect.value
    };
    
    // Add AI instruction if provided (for American English)
    if (aiInstructionText) {
        requestData.ai_instruction = aiInstructionText;
    }
    
    console.log('Multi-speaker request data:', requestData);
    
    try {
        const response = await fetch('api-multi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });
        
        const responseText = await response.text();
        console.log('Multi-speaker response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON:', parseError);
            throw new Error('Invalid JSON response: ' + responseText.substring(0, 100));
        }
        
        if (!response.ok || !data.success) {
            const errorMsg = data.error || data.message || 'Failed to generate multi-speaker audio';
            throw new Error(errorMsg);
        }
        
        // Use combined file if available, otherwise use first segment
        const audioUrl = data.combined_file || data.combined_file_relative || 
                        (data.audio_files && data.audio_files[0] ? data.audio_files[0].file : null);
        
        if (!audioUrl) {
            throw new Error('No audio file generated');
        }
        
        // Store generation data
        lastGeneratedText = text;
        const selectedVoice = voiceConfig[voiceSelect.value];
        if (selectedVoice) {
            lastGeneratedVoice = selectedVoice.voiceId;
        }
        lastGeneratedSettings = {
            voice: 'multi-speaker',
            speed: parseInt(speedSlider.value),
            pitch: parseInt(pitchSlider.value),
            emotion: emotionSelect.value
        };
        
        // Clear previous audio
        audioPlayer.pause();
        audioPlayer.src = '';
        audioPlayer.load();
        
        currentAudioUrl = audioUrl;
        
        // Wait for audio to load
        return new Promise((resolve, reject) => {
            let timeoutId;
            
            const handleCanPlay = () => {
                clearTimeout(timeoutId);
                audioPlayer.volume = volumeSlider.value / 100;
                audioPlayer.play()
                    .then(() => {
                        isPlaying = true;
                        updateButtonStates();
                        resolve();
                    })
                    .catch((playError) => {
                        console.error('Play error:', playError);
                        showError('Error playing audio: ' + playError.message);
                        isPlaying = false;
                        updateButtonStates();
                        reject(playError);
                    });
                audioPlayer.removeEventListener('canplay', handleCanPlay);
                audioPlayer.removeEventListener('error', handleError);
            };
            
            const handleError = (error) => {
                clearTimeout(timeoutId);
                console.error('Audio load error:', error);
                showError('Error loading audio file.');
                isPlaying = false;
                updateButtonStates();
                audioPlayer.removeEventListener('canplay', handleCanPlay);
                audioPlayer.removeEventListener('error', handleError);
                reject(error);
            };
            
            audioPlayer.addEventListener('canplay', handleCanPlay, { once: true });
            audioPlayer.addEventListener('error', handleError, { once: true });
            
            audioPlayer.src = audioUrl;
            audioPlayer.load();
            
            timeoutId = setTimeout(() => {
                if (audioPlayer.readyState < 2) {
                    showError('Audio file is taking too long to load.');
                    isPlaying = false;
                    updateButtonStates();
                    audioPlayer.removeEventListener('canplay', handleCanPlay);
                    audioPlayer.removeEventListener('error', handleError);
                    reject(new Error('Audio load timeout'));
                }
            }, 30000); // 30 second timeout for multi-speaker
        });
        
    } catch (error) {
        console.error('Error in generateMultiSpeakerAudio:', error);
        showError('Error generating multi-speaker audio: ' + error.message);
    } finally {
        loadingIndicator.style.display = 'none';
        if (playBtn) {
            playBtn.disabled = false;
            playBtn.style.pointerEvents = 'auto';
        }
    }
}

// Show error message
function showError(message) {
    // Format error message for better display
    let displayMessage = message;
    if (message.length > 200) {
        displayMessage = message.substring(0, 200) + '... (Check browser console for full details)';
    }
    
    errorMessage.textContent = displayMessage;
    errorMessage.style.display = 'block';
    
    // Allow clicking to dismiss error
    errorMessage.onclick = () => {
        errorMessage.style.display = 'none';
    };
}

// Initialize volume and button states
audioPlayer.volume = volumeSlider.value / 100;
updateButtonStates();

// Verify all elements are loaded
console.log('Initializing Speechify TTS...');
console.log('Play button:', playBtn);
console.log('Voice select:', voiceSelect);
console.log('Text input:', textInput);
console.log('Voice config:', voiceConfig);

// Ensure play button is enabled
if (playBtn) {
    playBtn.disabled = false;
    console.log('Play button initialized and enabled');
} else {
    console.error('Play button not found!');
}

// Test if button click works
playBtn.addEventListener('click', () => {
    console.log('Play button click detected!');
}, { once: true });

// Allow Enter key to generate audio (Ctrl+Enter or Cmd+Enter)
textInput.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        generateAudio();
    }
});

