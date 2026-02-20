document.addEventListener('DOMContentLoaded', () => {
    const voiceBtn = document.getElementById('voiceSearchBtn');
    // Handle both regular search input and search page input
    const searchInput = document.getElementById('searchInput') || document.getElementById('searchPageInput');
    const searchFormBtn = document.getElementById('searchBtn') || document.getElementById('searchPageBtn');

    if (!voiceBtn || !searchInput) return;

    // Check browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        voiceBtn.style.display = 'none';
        console.log('Speech recognition not supported');
        return;
    }

    const recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US';

    voiceBtn.addEventListener('click', () => {
        if (voiceBtn.classList.contains('listening')) {
            recognition.stop();
        } else {
            try {
                recognition.start();
            } catch (e) {
                console.error('Recognition start error:', e);
            }
        }
    });

    recognition.onstart = () => {
        voiceBtn.classList.add('listening');
        voiceBtn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
    };

    recognition.onend = () => {
        voiceBtn.classList.remove('listening');
        voiceBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
    };

    recognition.onresult = (event) => {
        let transcript = event.results[0][0].transcript;
        // Remove trailing period if present
        if (transcript.endsWith('.')) {
            transcript = transcript.slice(0, -1);
        }
        searchInput.value = transcript;

        // Trigger search
        if (typeof performSearch === 'function') {
            performSearch(transcript);
        } else if (searchFormBtn) {
            searchFormBtn.click();
        } else {
            // Fallback for home page if no performSearch function
            const query = encodeURIComponent(transcript);
            window.location.href = `search.php?q=${query}`;
        }
    };

    recognition.onerror = (event) => {
        console.error('Speech recognition error', event.error);
        voiceBtn.classList.remove('listening');
        voiceBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
    };
});
