(() => {
  const assistant = document.querySelector('[data-ai-assistant]');
  if (!assistant) return;

  const endpoint = assistant.getAttribute('data-ai-endpoint');
  const userType = assistant.getAttribute('data-user-type') || 'Guest';
  const fab = assistant.querySelector('.ai-fab');
  const panel = assistant.querySelector('.ai-chat-panel');
  const closeBtn = assistant.querySelector('.ai-chat-close');
  const input = assistant.querySelector('.ai-input');
  const sendBtn = assistant.querySelector('.ai-send');
  const messages = assistant.querySelector('.ai-messages');

  const togglePanel = (open) => {
    assistant.classList.toggle('is-open', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      setTimeout(() => input.focus(), 50);
    }
  };

  fab.addEventListener('click', () => {
    const isOpen = assistant.classList.contains('is-open');
    togglePanel(!isOpen);
  });

  closeBtn.addEventListener('click', () => togglePanel(false));

  const escapeHtml = (value) =>
    value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const formatMessage = (text) => {
    const lines = text.split(/\r?\n/);
    let html = '';
    let listType = null;

    const closeList = () => {
      if (listType) {
        html += `</${listType}>`;
        listType = null;
      }
    };

    lines.forEach((rawLine) => {
      const line = rawLine.trim();
      const orderedMatch = line.match(/^\d+\.\s+(.*)/);
      const unorderedMatch = line.match(/^[-*]\s+(.*)/);

      if (orderedMatch) {
        if (listType !== 'ol') {
          closeList();
          listType = 'ol';
          html += '<ol>';
        }
        html += `<li>${orderedMatch[1]}</li>`;
        return;
      }

      if (unorderedMatch) {
        if (listType !== 'ul') {
          closeList();
          listType = 'ul';
          html += '<ul>';
        }
        html += `<li>${unorderedMatch[1]}</li>`;
        return;
      }

      closeList();
      if (line === '') {
        html += '<br>';
        return;
      }
      html += `<p>${line}</p>`;
    });

    closeList();

    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  };

  const addMessage = (text, type, pending = false) => {
    const div = document.createElement('div');
    div.className = `ai-message ${type}${pending ? ' pending' : ''}`;
    const safeText = escapeHtml(text);
    div.innerHTML = formatMessage(safeText);
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  };

  const sendMessage = async () => {
    const text = input.value.trim();
    if (!text || sendBtn.disabled) return;

    addMessage(text, 'user');
    input.value = '';
    sendBtn.disabled = true;

    const placeholder = addMessage('Thinking...', 'ai', true);

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message: text,
          userType
        }),
        credentials: 'same-origin'
      });

      const data = await res.json();
      placeholder.remove();
      addMessage(data.reply || 'Sorry, I could not answer that.', 'ai');
    } catch (err) {
      placeholder.remove();
      addMessage('The assistant is currently unavailable. Please try again later.', 'ai');
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  };

  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      sendMessage();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      togglePanel(false);
    }
  });
})();
