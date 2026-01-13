<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>webIT</title>

  <!-- Bootstrap 5.3.8 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- AI Chat CSS -->
  <link rel="stylesheet" href="chat.css">
</head>

<body>

  <!-- Your page content -->
  <div class="container mt-5">
    <h1>Welcome to webIT</h1>
    <p>Webinar and virtual event platform</p>
  </div>

  <!-- Floating AI Button -->
  <div id="ai-fab" class="ai-fab">💬</div>

  <!-- AI Chat Panel -->
  <div id="ai-chat" class="ai-chat shadow-lg">

    <div class="ai-header">
      <span>webIT Assistant</span>
      <button id="ai-close" class="btn-close"></button>
    </div>

    <div id="ai-messages" class="ai-messages">
      <div class="ai-message ai">
        Hi! I’m the webIT assistant.
        I can help you understand how the platform works.
      </div>
    </div>

    <div class="ai-input-area">
      <input
        type="text"
        id="ai-input"
        class="form-control"
        placeholder="Ask about webIT..." />
      <button id="ai-send" class="btn btn-primary">Send</button>
    </div>

  </div>

  <!-- AI Script -->
  <script>
    const fab = document.getElementById('ai-fab');
    const chat = document.getElementById('ai-chat');
    const closeBtn = document.getElementById('ai-close');
    const sendBtn = document.getElementById('ai-send');
    const input = document.getElementById('ai-input');
    const messages = document.getElementById('ai-messages');

    fab.onclick = () => chat.style.display = 'flex';
    closeBtn.onclick = () => chat.style.display = 'none';

    sendBtn.onclick = sendMessage;
    input.addEventListener('keypress', e => {
      if (e.key === 'Enter') sendMessage();
    });

    function sendMessage() {
      const text = input.value.trim();
      if (!text) return;

      addMessage(text, 'user');
      input.value = '';

      fetch('chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            message: text,
            userType: 'User'
          })
        })
        .then(res => res.json())
        .then(data => {
          addMessage(data.reply, 'ai');
        })
        .catch(() => {
          addMessage(
            'The assistant is currently unavailable. Please try again later.',
            'ai'
          );
        });
    }

    function addMessage(text, type) {
      const div = document.createElement('div');
      div.className = `ai-message ${type}`;
      div.innerText = text;
      messages.appendChild(div);
      messages.scrollTop = messages.scrollHeight;
    }
  </script>

</body>

</html>