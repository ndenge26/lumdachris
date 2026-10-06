(() => {
    // Keep the widget optional so pages without its markup do not run chat code.
    const chatbot = document.querySelector('#property-chatbot');
    if (!chatbot) {
        return;
    }

    const panel = chatbot.querySelector('#chatbot-panel');
    const launcher = chatbot.querySelector('[data-chat-open]');
    const closeButton = chatbot.querySelector('[data-chat-close]');
    const form = chatbot.querySelector('[data-chat-form]');
    const input = chatbot.querySelector('[data-chat-input]');
    const sendButton = chatbot.querySelector('[data-chat-send]');
    const microphoneButton = chatbot.querySelector('[data-chat-microphone]');
    const messages = chatbot.querySelector('[data-chat-messages]');
    const status = chatbot.querySelector('[data-chat-status]');
    const speakerToggle = chatbot.querySelector('[data-chat-speaker]');
    const languageSelect = chatbot.querySelector('[data-chat-language]');
    const endpoint = chatbot.dataset.endpoint;
    const csrfToken = chatbot.dataset.csrf;
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const speechAvailable = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    let isSending = false;
    let recognition = null;

    // Render all user and server text as text, never as HTML markup.
    function addMessage(role, text, pending = false) {
        const article = document.createElement('article');
        article.className = `chat-message ${role === 'user' ? 'chat-message-user' : 'chat-message-bot'}`;
        if (pending) {
            article.classList.add('chat-message-pending');
        }
        const paragraph = document.createElement('p');
        paragraph.textContent = text;
        article.appendChild(paragraph);
        messages.appendChild(article);
        messages.scrollTop = messages.scrollHeight;
        return article;
    }

    function setStatus(text) {
        status.textContent = text;
    }

    // Keep keyboard and screen-reader state in sync with the panel visibility.
    function setPanelOpen(open) {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        if (open) {
            input.focus();
        } else if (recognition) {
            recognition.stop();
        }
    }

    // Speak replies only when the user has explicitly enabled the speaker switch.
    function speakReply(text, language) {
        if (!speakerToggle.checked || !speechAvailable) {
            return;
        }

        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = language === 'fr' ? 'fr-FR' : 'en-GB';
        const voicePrefix = language === 'fr' ? 'fr' : 'en';
        const voice = window.speechSynthesis.getVoices().find((item) => item.lang.toLowerCase().startsWith(voicePrefix));
        if (voice) {
            utterance.voice = voice;
        }
        window.speechSynthesis.speak(utterance);
    }

    // Send one message to the same-origin PHP endpoint with the session CSRF token.
    async function sendMessage(message) {
        if (isSending || message.length === 0) {
            return;
        }

        isSending = true;
        sendButton.disabled = true;
        microphoneButton.disabled = true;
        input.disabled = true;
        addMessage('user', message);
        const pendingMessage = addMessage('bot', languageSelect.value === 'fr' ? 'Un instant…' : 'One moment…', true);
        setStatus('');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ message, language: languageSelect.value }),
            });
            const payload = await response.json();
            pendingMessage.remove();

            if (!response.ok || typeof payload.reply !== 'string') {
                throw new Error(typeof payload.error === 'string' ? payload.error : 'The service is temporarily unavailable.');
            }

            addMessage('bot', payload.reply);
            speakReply(payload.reply, payload.language || languageSelect.value);
        } catch (error) {
            pendingMessage.remove();
            addMessage('bot', error.message || 'I could not send that message. Please try again.');
        } finally {
            isSending = false;
            sendButton.disabled = false;
            microphoneButton.disabled = !SpeechRecognition;
            input.disabled = false;
            input.focus();
        }
    }

    // Support launcher, close button, and Escape-key dismissal.
    launcher.addEventListener('click', () => setPanelOpen(panel.hidden));
    closeButton.addEventListener('click', () => setPanelOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setPanelOpen(false);
            launcher.focus();
        }
    });

    // Submit typed messages without navigating away from the current page.
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) {
            return;
        }
        input.value = '';
        sendMessage(message);
    });

    speakerToggle.disabled = !speechAvailable;
    if (!speechAvailable) {
        speakerToggle.title = 'Speech synthesis is not available in this browser.';
    }
    speakerToggle.addEventListener('change', () => {
        if (!speakerToggle.checked && speechAvailable) {
            window.speechSynthesis.cancel();
        }
    });

    microphoneButton.disabled = !SpeechRecognition;
    if (!SpeechRecognition) {
        microphoneButton.title = 'Voice input is not available in this browser.';
    }

    // Convert the final speech transcript into an input value, then use the same submit path.
    microphoneButton.addEventListener('click', () => {
        if (!SpeechRecognition || isSending) {
            return;
        }

        recognition = new SpeechRecognition();
        recognition.lang = languageSelect.value === 'fr' ? 'fr-FR' : 'en-GB';
        recognition.continuous = false;
        recognition.interimResults = false;
        microphoneButton.classList.add('is-listening');
        microphoneButton.disabled = true;
        setStatus(languageSelect.value === 'fr' ? 'Je vous ecoute…' : 'Listening…');

        recognition.onresult = (event) => {
            const transcript = Array.from(event.results)
                .map((result) => result[0].transcript)
                .join(' ')
                .trim();
            if (transcript) {
                input.value = transcript;
                form.requestSubmit();
            }
        };
        recognition.onerror = (event) => {
            const errorMessage = languageSelect.value === 'fr'
                ? 'La reconnaissance vocale a echoue. Vous pouvez saisir votre message.'
                : `Voice input failed (${event.error}). You can type your message instead.`;
            setStatus(errorMessage);
        };
        recognition.onend = () => {
            microphoneButton.classList.remove('is-listening');
            microphoneButton.disabled = isSending;
            if (!isSending && status.textContent.includes('Listening')) {
                setStatus('');
            }
            recognition = null;
        };

        try {
            recognition.start();
        } catch (error) {
            microphoneButton.classList.remove('is-listening');
            microphoneButton.disabled = false;
            recognition = null;
            setStatus(languageSelect.value === 'fr' ? 'Impossible de demarrer le microphone.' : 'Could not start the microphone.');
        }
    });

    // Keep recognition locale and prompt text aligned with the selected response language.
    languageSelect.addEventListener('change', () => {
        input.placeholder = languageSelect.value === 'fr' ? 'Posez une question sur un bien au Cameroun' : 'Ask about property in Cameroon';
        if (recognition) {
            recognition.stop();
        }
    });
})();
