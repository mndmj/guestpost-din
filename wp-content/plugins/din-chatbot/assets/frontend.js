(function (global) {
    'use strict';

    function safeClientId(value, fallback) {
        return typeof value === 'string' && /^[A-Za-z0-9_-]{8,64}$/.test(value) ? value : fallback;
    }

    function resetServerState(current, settings, welcome, fallbackClientId) {
        var initialSuggestions = settings && Array.isArray(settings.initialSuggestions) ? settings.initialSuggestions.map(function (item) { return Object.assign({}, item); }) : [];
        return {
            sessionId: 0,
            token: '',
            clientId: safeClientId(current && current.clientId, safeClientId(fallbackClientId, 'client_safe_identity')),
            transcript: [{ role: 'bot', html: welcome }],
            suggestions: initialSuggestions,
            activeTopicId: 0,
            lastRuleId: 0,
            history: []
        };
    }

    function focusTarget(isPending) {
        return isPending ? 'dialog' : 'input';
    }

    if (typeof module === 'object' && module.exports && global.__DIN_CHATBOT_TEST__) {
        module.exports = { resetServerState: resetServerState, focusTarget: focusTarget };
    }

    if (!global.document) {
        return;
    }

    var root = global.document.querySelector('[data-din-chatbot]');
    var config = global.DinChatbot;
    if (!root || !config || typeof config !== 'object') {
        return;
    }

    var fab = root.querySelector('#din-chatbot-fab');
    var dialog = root.querySelector('#din-chatbot-dialog');
    var transcript = root.querySelector('[data-din-chatbot-transcript]');
    var suggestions = root.querySelector('[data-din-chatbot-suggestions]');
    var status = root.querySelector('[data-din-chatbot-status]');
    var form = root.querySelector('[data-din-chatbot-form]');
    var input = root.querySelector('[data-din-chatbot-input]');
    var send = root.querySelector('[data-din-chatbot-send]');
    var close = root.querySelector('[data-din-chatbot-close]');
    var restart = root.querySelector('[data-din-chatbot-restart]');
    var back = root.querySelector('[data-din-chatbot-back]');
    var storageKey = 'dinChatbotStateV1';
    var audioUnlocked = false;
    var requestNumber = 0;
    var pending = false;
    var welcomeHtml = transcript.firstElementChild ? transcript.firstElementChild.innerHTML : '';
    var state = loadState() || newState();

    function newState() {
        var fresh = resetServerState(null, config, welcomeHtml, createClientId());
        fresh.suggestions = safeSuggestions(fresh.suggestions);
        return fresh;
    }

    function createClientId() {
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
            return 'client_' + Array.prototype.map.call(bytes, function (item) { return item.toString(16).padStart(2, '0'); }).join('');
        }
        return 'client_' + String(Date.now()) + '_' + String(Math.random()).slice(2, 18);
    }

    function loadState() {
        try {
            var saved = window.sessionStorage.getItem(storageKey);
            if (!saved) {
                return null;
            }
            var parsed = JSON.parse(saved);
            if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.transcript) || !Array.isArray(parsed.suggestions) || !Array.isArray(parsed.history)) {
                window.sessionStorage.removeItem(storageKey);
                return null;
            }
            parsed.sessionId = Number.isInteger(parsed.sessionId) && parsed.sessionId > 0 ? parsed.sessionId : 0;
            parsed.token = typeof parsed.token === 'string' && /^[a-f0-9]{64}$/.test(parsed.token) ? parsed.token : '';
            parsed.clientId = typeof parsed.clientId === 'string' && /^[A-Za-z0-9_-]{8,64}$/.test(parsed.clientId) ? parsed.clientId : createClientId();
            if (!parsed.sessionId || !parsed.token) {
                return resetServerState(parsed, config, welcomeHtml, createClientId());
            }
            parsed.suggestions = safeSuggestions(parsed.suggestions);
            parsed.activeTopicId = positiveId(parsed.activeTopicId);
            parsed.lastRuleId = positiveId(parsed.lastRuleId);
            return parsed;
        } catch (error) {
            try { window.sessionStorage.removeItem(storageKey); } catch (ignored) {}
            return null;
        }
    }

    function saveState() {
        try { window.sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch (ignored) {}
    }

    function safeSuggestions(items) {
        if (!Array.isArray(items)) {
            return [];
        }
        return items.filter(function (item) {
            return item && typeof item.label === 'string' && item.label.trim() && ['topic', 'rule', 'restart', 'contact'].indexOf(item.type) !== -1 && (item.type === 'topic' || item.type === 'rule' ? positiveId(item.target_id) > 0 : true) && (item.type !== 'contact' || safeUrl(config.contactUrl));
        }).map(function (item) {
            return { label: item.label.trim(), type: item.type, target_id: positiveId(item.target_id) };
        });
    }

    function positiveId(value) {
        var id = Number(value);
        return Number.isSafeInteger(id) && id > 0 ? id : 0;
    }

    function safeUrl(value) {
        if (typeof value !== 'string' || !value) {
            return '';
        }
        try {
            var url = new URL(value, window.location.href);
            return ['http:', 'https:', 'mailto:'].indexOf(url.protocol) !== -1 ? url.href : '';
        } catch (error) {
            return '';
        }
    }

    function render() {
        transcript.replaceChildren();
        state.transcript.forEach(function (message) {
            if (message.role === 'cards' && Array.isArray(message.cards)) {
                message.cards.slice(0, 3).forEach(function (card) { transcript.appendChild(renderCard(card)); });
                return;
            }
            var item = document.createElement('div');
            item.className = 'din-chatbot__message din-chatbot__message--' + (message.role === 'buyer' ? 'buyer' : 'bot');
            if (message.role === 'bot' && typeof message.html === 'string') {
                item.innerHTML = controlledHtml(message.html);
            } else {
                item.textContent = typeof message.text === 'string' ? message.text : '';
            }
            transcript.appendChild(item);
        });
        renderSuggestions();
        back.hidden = !(state.history.length > 0 && state.activeTopicId > 0);
        saveState();
    }

    function renderSuggestions() {
        suggestions.replaceChildren();
        state.suggestions.forEach(function (suggestion) {
            if (suggestion.type === 'contact') {
                var contact = safeUrl(config.contactUrl);
                if (!contact) { return; }
                var link = document.createElement('a');
                link.className = 'din-chatbot__suggestion';
                link.href = contact;
                link.textContent = suggestion.label;
                suggestions.appendChild(link);
                return;
            }
            var button = document.createElement('button');
            button.className = 'din-chatbot__suggestion';
            button.type = 'button';
            button.dataset.dinChatbotAction = suggestion.type;
            button.dataset.dinChatbotTarget = String(suggestion.target_id);
            button.textContent = suggestion.label;
            suggestions.appendChild(button);
        });
    }

    function setPending(next) {
        pending = next;
        send.disabled = next;
        input.disabled = next;
        restart.disabled = next;
        back.disabled = next;
        suggestions.querySelectorAll('button').forEach(function (button) { button.disabled = next; });
        root.classList.toggle('din-chatbot--pending', next);
        if (!next && !dialog.hidden && !dialog.contains(document.activeElement) && !input.disabled) {
            input.focus();
        }
    }

    function setStatus(message) {
        status.textContent = message;
    }

    async function request(path, body, needsToken) {
        var headers = { 'Content-Type': 'application/json' };
        if (needsToken) { headers['X-Din-Chatbot-Token'] = state.token; }
        var response = await fetch(String(config.restRoot).replace(/\/$/, '') + path, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify(body) });
        var payload = null;
        try { payload = await response.json(); } catch (ignored) {}
        if (!response.ok) {
            var error = new Error('request');
            error.status = response.status;
            error.payload = payload;
            throw error;
        }
        return payload;
    }

    async function ensureSession() {
        if (state.sessionId > 0 && state.token) { return; }
        var created = await request('/session', { client_id: state.clientId }, false);
        state.sessionId = positiveId(created && created.session_id);
        state.token = created && typeof created.token === 'string' ? created.token : '';
        if (!state.sessionId || !/^[a-f0-9]{64}$/.test(state.token)) { throw new Error('session'); }
        saveState();
    }

    function appendBuyer(text) {
        state.transcript.push({ role: 'buyer', text: text });
    }

    function appendBotHtml(html) {
        if (typeof html === 'string' && html) { state.transcript.push({ role: 'bot', html: html }); }
    }

    function controlledHtml(html) {
        var template = document.createElement('template');
        template.innerHTML = html;
        Array.prototype.slice.call(template.content.querySelectorAll('*')).forEach(function (element) {
            if (['P', 'BR', 'STRONG', 'EM', 'B', 'I', 'UL', 'OL', 'LI', 'A', 'SPAN', 'DEL', 'INS', 'SMALL'].indexOf(element.tagName) === -1) {
                element.replaceWith(document.createTextNode(element.textContent || ''));
                return;
            }
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                if (element.tagName === 'A' && attribute.name.toLowerCase() === 'href' && safeUrl(attribute.value)) { return; }
                element.removeAttribute(attribute.name);
            });
        });
        return template.innerHTML;
    }

    function renderCard(card) {
        var holder = document.createElement('div');
        holder.className = 'din-chatbot__cards';
        var url = safeUrl(card && card.url);
        if (!card || !url || typeof card.name !== 'string' || typeof card.attributes !== 'string' || typeof card.price_html !== 'string') { return holder; }
        var link = document.createElement('a');
        link.href = url;
        link.textContent = card.name;
        var attributes = document.createElement('p');
        attributes.textContent = card.attributes;
        var price = document.createElement('p');
        price.innerHTML = controlledHtml(card.price_html);
        holder.append(link, attributes, price);
        return holder;
    }

    function appendCards(cards) {
        if (!Array.isArray(cards)) { return; }
        var safeCards = cards.slice(0, 3).filter(function (card) {
            return card && safeUrl(card.url) && typeof card.name === 'string' && typeof card.attributes === 'string' && typeof card.price_html === 'string';
        }).map(function (card) {
            return { name: card.name, attributes: card.attributes, price_html: card.price_html, url: safeUrl(card.url) };
        });
        if (safeCards.length) { state.transcript.push({ role: 'cards', cards: safeCards }); }
    }

    function applyResponse(response, actionType, targetId) {
        var answer = response && typeof response.answer === 'string' ? response.answer : '';
        var responseStatus = response && typeof response.status === 'string' ? response.status : '';
        if (!answer) {
            if (responseStatus === 'no_match') { answer = 'I could not find a matching answer. Please try a topic or reword your question.'; }
            if (responseStatus === 'handoff_eligible') { answer = safeUrl(config.contactUrl) ? 'I could not resolve this automatically. You can contact us for help.' : 'I could not resolve this automatically. Please restart the chat to try again.'; }
        }
        appendBotHtml(answer);
        state.suggestions = safeSuggestions(response && response.suggestions);
        if (actionType === 'topic') {
            state.activeTopicId = targetId;
            state.lastRuleId = 0;
        } else if (actionType === 'rule') {
            state.lastRuleId = targetId;
        }
        setStatus(responseStatus === 'handoff_eligible' && safeUrl(config.contactUrl) ? 'More help is available.' : '');
        appendCards(response && response.cards);
        render();
        playTone();
    }

    async function sendMessage(text) {
        var requestId = ++requestNumber;
        setPending(true);
        try {
            await ensureSession();
            var response = await request('/message', { session_id: state.sessionId, text: text }, true);
            if (requestId !== requestNumber) { return; }
            appendBuyer(text);
            state.history = [];
            applyResponse(response, '', 0);
            input.value = '';
        } catch (error) {
            if (requestId === requestNumber) { handleError(error); }
        } finally {
            if (requestId === requestNumber) { setPending(false); }
        }
    }

    async function selectAction(type, targetId) {
        if (type === 'contact') { return; }
        if (type === 'restart') { await restartChat(); return; }
        var requestId = ++requestNumber;
        var history = state.history.slice();
        if (type === 'topic') { history.push({ topicId: state.activeTopicId }); } else { history = []; }
        setPending(true);
        try {
            await ensureSession();
            var response = await request('/action', { session_id: state.sessionId, type: type, target_id: targetId }, true);
            if (requestId !== requestNumber) { return; }
            state.history = history;
            applyResponse(response, type, targetId);
        } catch (error) {
            if (requestId === requestNumber) { handleError(error); }
        } finally {
            if (requestId === requestNumber) { setPending(false); }
        }
    }

    async function restartChat() {
        var requestId = ++requestNumber;
        setPending(true);
        try {
            await ensureSession();
            await request('/restart', { session_id: state.sessionId }, true);
            if (requestId !== requestNumber) { return; }
            var keep = { sessionId: state.sessionId, token: state.token, clientId: state.clientId };
            state = newState();
            state.sessionId = keep.sessionId;
            state.token = keep.token;
            state.clientId = keep.clientId;
            setStatus('Chat restarted.');
            render();
        } catch (error) {
            if (requestId === requestNumber) { handleError(error); }
        } finally {
            if (requestId === requestNumber) { setPending(false); }
        }
    }

    async function goBack() {
        var previous = state.history[state.history.length - 1];
        if (!previous || !state.activeTopicId || pending) { return; }
        var requestId = ++requestNumber;
        setPending(true);
        try {
            await ensureSession();
            await request('/restart', { session_id: state.sessionId }, true);
            var response = null;
            if (previous.topicId > 0) {
                response = await request('/action', { session_id: state.sessionId, type: 'topic', target_id: previous.topicId }, true);
            }
            if (requestId !== requestNumber) { return; }
            state.history.pop();
            state.transcript = [{ role: 'bot', html: welcomeHtml }];
            state.suggestions = safeSuggestions(config.initialSuggestions);
            state.activeTopicId = 0;
            state.lastRuleId = 0;
            if (response) { applyResponse(response, 'topic', previous.topicId); } else { render(); }
        } catch (error) {
            if (requestId === requestNumber) { handleError(error); }
        } finally {
            if (requestId === requestNumber) { setPending(false); }
        }
    }

    function handleError(error) {
        if (error && error.status === 401) {
            state = resetServerState(state, config, welcomeHtml, createClientId());
            saveState();
            render();
            setStatus('Your session expired. Please try again.');
        } else if (error && error.status === 400) {
            setStatus('Please check your message and try again.');
        } else if (error && error.status === 429) {
            setStatus('Please wait a moment before trying again.');
        } else if (error && error.status === 503) {
            setStatus('Chatbot is temporarily unavailable. Please try again later.');
        } else {
            setStatus('Network error. Please check your connection and try again.');
        }
    }

    function openDialog() {
        dialog.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        render();
        focusOpenTarget(!state.sessionId);
        if (!state.sessionId && !pending) {
            setPending(true);
            ensureSession().catch(handleError).finally(function () {
                setPending(false);
                if (!dialog.hidden) { focusOpenTarget(false); }
            });
        }
    }

    function focusOpenTarget(isPending) {
        var target = focusTarget(isPending) === 'dialog' ? dialog : input;
        if (target && !target.disabled) { target.focus(); }
    }

    function closeDialog() {
        dialog.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        saveState();
        fab.focus();
    }

    function focusTrap(event) {
        if (event.key === 'Escape') { event.preventDefault(); closeDialog(); return; }
        if (event.key !== 'Tab' || dialog.hidden) { return; }
        var focusable = Array.prototype.filter.call(dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled])'), function (element) { return !element.hidden; });
        if (!focusable.length) { return; }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }

    function playTone() {
        if (!audioUnlocked || !config.settings || !config.settings.notificationSound || !window.AudioContext) { return; }
        try {
            var context = new window.AudioContext();
            var oscillator = context.createOscillator();
            var gain = context.createGain();
            gain.gain.value = 0.03;
            oscillator.frequency.value = 660;
            oscillator.connect(gain).connect(context.destination);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.08);
            oscillator.addEventListener('ended', function () { context.close(); }, { once: true });
        } catch (ignored) {}
    }

    document.addEventListener('pointerdown', function () { audioUnlocked = true; }, { once: true });
    document.addEventListener('keydown', function () { audioUnlocked = true; }, { once: true });
    fab.addEventListener('click', openDialog);
    close.addEventListener('click', closeDialog);
    restart.addEventListener('click', restartChat);
    back.addEventListener('click', goBack);
    dialog.addEventListener('keydown', focusTrap);
    suggestions.addEventListener('click', function (event) {
        var button = event.target.closest('[data-din-chatbot-action]');
        if (!button || pending) { return; }
        selectAction(button.dataset.dinChatbotAction, positiveId(button.dataset.dinChatbotTarget));
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = input.value.trim();
        if (text && !pending) { sendMessage(text); }
    });
    render();
    if (typeof module === 'object' && module.exports && global.__DIN_CHATBOT_TEST__) {
        module.exports.runtime = {
            loadState: function () { state = loadState() || newState(); render(); return state; },
            handle401: function () { handleError({ status: 401 }); return state; },
            openDialog: function () { openDialog(); },
            setPending: function (next) { setPending(next); },
            sendMessage: function (text) { return sendMessage(text); },
            applyResponse: function (response) { applyResponse(response, '', 0); return state; },
            state: function () { return state; }
        };
    }
}(typeof window !== 'undefined' ? window : globalThis));
