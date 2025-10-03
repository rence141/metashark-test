let currentUserId = window.currentUserId || 0;
let currentGroup = null;
let isMaximized = false;
let sessionStartTimestampMs = null; // when user clicks New Chat
let lastSessionsCache = []; // cached sessions for optimistic UI
let lastSessionsCount = 0; // track count to detect growth after refresh

function openAiChat() {
  const modal = document.getElementById("aiChatModal");
  if (modal) {
    modal.classList.add('show');
    loadChatHistory();
  }
}

function closeAiChat() {
  const modal = document.getElementById("aiChatModal");
  if (modal) {
    modal.classList.remove('show');
  }
}

function formatDate(dateStr) {
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return new Date(dateStr).toLocaleDateString(undefined, options);
}

function showWelcome() {
  const messagesDiv = document.getElementById("aiChatMessages");
  if (messagesDiv) {
    messagesDiv.innerHTML = '<div class="message bot">Hello I\'m Verna your Meta Shark Attendee and Staff, how may I help you today?</div>';
  }
}

function showNotification(message) {
  const notification = document.createElement('div');
  notification.style.position = 'fixed';
  notification.style.top = '20px';
  notification.style.right = '20px';
  notification.style.backgroundColor = '#5bf431ff';
  notification.style.color = 'white';
  notification.style.padding = '12px 24px';
  notification.style.borderRadius = '8px';
  notification.style.zIndex = '1000';
  notification.style.animation = 'fadeInOut 2s ease forwards';
  notification.textContent = message;
  document.body.appendChild(notification);
  setTimeout(() => {
    if (notification.parentNode) {
      document.body.removeChild(notification);
    }
  }, 2000);
}

function displayMessages(msgs) {
  const messagesDiv = document.getElementById("aiChatMessages");
  if (messagesDiv) {
    messagesDiv.innerHTML = '';
    // Apply session filter for today's group if a new session was started
    let displayMsgs = msgs;
    if (sessionStartTimestampMs && currentGroup) {
      const todayKey = new Date().toISOString().split('T')[0];
      if (currentGroup === todayKey) {
        displayMsgs = msgs.filter(m => {
          const t = m.timestamp ? Date.parse(m.timestamp) : 0;
          return t >= sessionStartTimestampMs;
        });
      }
    }

    if (displayMsgs.length === 0) {
      showWelcome();
    } else {
      displayMsgs.forEach(msg => {
        const msgWrap = document.createElement("div");
        msgWrap.className = `message ${msg.role}`;

        const header = document.createElement('div');
        header.className = 'ai-message-header';
        const who = msg.role === 'ai' ? 'AI' : 'You';
        const time = msg.timestamp ? new Date(msg.timestamp).toLocaleTimeString() : '';
        const title = document.createElement('span');
        title.textContent = `${who}${time ? ' • ' + time : ''}`;
        const actions = document.createElement('div');
        actions.className = 'ai-message-actions';
        const copyBtn = document.createElement('button');
        copyBtn.className = 'ai-copy-btn';
        copyBtn.title = 'Copy';
        copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
        copyBtn.onclick = () => copyToClipboard(msg.message || '');
        actions.appendChild(copyBtn);
        header.appendChild(title);
        header.appendChild(actions);

        const body = document.createElement('div');
        body.className = 'ai-message-body';
        body.textContent = (msg.role === 'ai' ? ' ' : '') + (msg.message || '');

        msgWrap.appendChild(header);
        msgWrap.appendChild(body);
        messagesDiv.appendChild(msgWrap);
      });
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
  }
}

function populateSidebar(dates, groups) {
  const list = document.getElementById("chatHistoryList");
  if (!list) return;
  list.innerHTML = '';
  dates.forEach(date => {
    const li = document.createElement("li");
    li.textContent = formatDate(date) + ` (${groups[date].length} msgs)`;
    li.dataset.date = date;
    li.onclick = (e) => {
      e.stopPropagation();
      sessionStartTimestampMs = null;
      currentGroup = date;
      displayMessages(groups[date]);
      document.querySelectorAll("#chatHistoryList li").forEach(l => l.classList.remove("active"));
      li.classList.add("active");
    };
    list.appendChild(li);
  });
  const currentLi = document.querySelector(`#chatHistoryList li[data-date="${currentGroup}"]`);
  if (currentLi) {
    currentLi.classList.add("active");
  }
}

function populateSessions(sessions) {
  const list = document.getElementById("chatHistoryList");
  if (!list) return;
  list.innerHTML = '';
  lastSessionsCache = sessions.slice();
  lastSessionsCount = sessions.length;
  for (let i = sessions.length - 1; i >= 0; i--) {
    const sessionMsgs = sessions[i];
    const sessionIdx = (i + 1).toString();
    const firstTs = sessionMsgs[0]?.timestamp ? new Date(sessionMsgs[0].timestamp).toLocaleString() : '';
    const li = document.createElement('li');
    li.innerHTML = `<span>Session ${sessionIdx}${firstTs ? ' • ' + firstTs : ''} (${sessionMsgs.length} msgs)</span><button class="ai-session-delete" title="Delete session" data-session-index="${sessionIdx}">✕</button>`;
    li.dataset.key = sessionIdx;
    li.onclick = (e) => {
      // Ignore clicks on delete button
      const target = e.target;
      if (target && target.classList && target.classList.contains('ai-session-delete')) return;
      e.stopPropagation();
      sessionStartTimestampMs = null;
      currentGroup = sessionIdx;
      displayMessages(sessionMsgs);
      document.querySelectorAll('#chatHistoryList li').forEach(l => l.classList.remove('active'));
      li.classList.add('active');
    };
    list.appendChild(li);
  }
  const currentLi = document.querySelector(`#chatHistoryList li[data-key="${currentGroup}"]`);
  if (currentLi) {
    currentLi.classList.add('active');
  } else if (sessions.length > 0) {
    currentGroup = sessions.length.toString();
    const fallbackLi = document.querySelector(`#chatHistoryList li[data-key="${currentGroup}"]`);
    if (fallbackLi) fallbackLi.classList.add('active');
  }
  // Wire delete buttons
  list.querySelectorAll('.ai-session-delete').forEach(btn => {
    btn.addEventListener('click', async (ev) => {
      ev.stopPropagation();
      const el = ev.currentTarget;
      const idxStr = el && el.getAttribute ? el.getAttribute('data-session-index') : '0';
      const idxNum = parseInt(idxStr || '0', 10);
      if (!idxNum || idxNum < 1) return;
      try {
        const res = await fetch('ai_chat_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete_session', user_id: currentUserId, session_index: idxNum })
        });
        const data = await res.json();
        if (data && data.success) {
          const updated = lastSessionsCache.slice();
          updated.splice(idxNum - 1, 1);
          currentGroup = updated.length ? updated.length.toString() : null;
          populateSessions(updated);
          displayMessages(updated.length ? updated[updated.length - 1] : []);
          showNotification('Session deleted');
        } else {
          showNotification('Failed to delete session');
        }
      } catch (e) {
        showNotification('Failed to delete session');
      }
    });
  });
}

async function refreshChat() {
  if (!currentUserId) return;

  try {
    const response = await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "load_history", user_id: currentUserId, limit: 1000 })
    });
    if (!response.ok) {
      throw new Error("Failed to load chat history");
    }
    const data = await response.json();

    const allMessages = data.history || [];
    let sessions = [];
    let current = [];
    allMessages.forEach(m => {
      if ((m.role === 'marker' || m.role === 'system') && (m.message || '').trim() === '__session_break__') {
        if (current.length) sessions.push(current);
        current = [];
      } else {
        current.push(m);
      }
    });
    sessions.push(current);
    if (sessions.length === 0) sessions = [[]];

    const previousCount = lastSessionsCount;
    populateSessions(sessions);

    // If sessions grew, select the newest; otherwise keep current if valid
    if (sessions.length > previousCount) {
      currentGroup = sessions.length.toString();
    } else if (!currentGroup || isNaN(parseInt(currentGroup, 10)) || parseInt(currentGroup, 10) > sessions.length) {
      currentGroup = sessions.length.toString();
    }
    const idx = Math.max(0, Math.min(sessions.length - 1, parseInt(currentGroup, 10) - 1));
    displayMessages(sessions[idx]);
  } catch (error) {
    console.error("Refresh Chat Error:", error);
    showNotification("Failed to refresh chat history");
  }
}

async function loadChatHistory() {
  if (!currentUserId) {
    showWelcome();
    populateSessions([]);
    return;
  }

  try {
    const response = await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "load_history", user_id: currentUserId, limit: 1000 })
    });
    if (!response.ok) {
      throw new Error("Failed to load chat history");
    }
    const data = await response.json();

    const allMessages = data.history || [];
    if (allMessages.length === 0) {
      showWelcome();
      populateSessions([]);
      return;
    }

    let sessions = [];
    let current = [];
    allMessages.forEach(m => {
      if ((m.role === 'marker' || m.role === 'system') && (m.message || '').trim() === '__session_break__') {
        if (current.length) sessions.push(current);
        current = [];
      } else {
        current.push(m);
      }
    });
    sessions.push(current);
    if (sessions.length === 0) sessions = [[]];

    populateSessions(sessions);

    currentGroup = sessions.length.toString();
    displayMessages(sessions[sessions.length - 1]);
  } catch (error) {
    console.error("Load History Error:", error);
    showWelcome();
    populateSessions([]);
    showNotification("Failed to load chat history");
  }
}

async function newChat() {
  if (currentUserId) {
    try {
      const response = await fetch("ai_chat_handler.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "save_message",
          user_id: currentUserId,
          role: "marker",
          message: "__session_break__"
        })
      });
      if (!response.ok) {
        throw new Error("Failed to save session marker");
      }
    } catch (error) {
      console.error("Failed to create new session:", error);
      showNotification("Failed to start new chat. Please try again.");
      return;
    }
  }

  // Optimistic UI update
  const newSessionId = (lastSessionsCache.length + 1).toString();
  const optimistic = lastSessionsCache.slice();
  optimistic.push([]);
  currentGroup = newSessionId;
  sessionStartTimestampMs = Date.now();

  populateSessions(optimistic);
  displayMessages([]);
  showWelcome();

  document.querySelectorAll("#chatHistoryList li").forEach(l => l.classList.remove("active"));
  const currentLi = document.querySelector(`#chatHistoryList li[data-key="${currentGroup}"]`);
  if (currentLi) currentLi.classList.add("active");

  showNotification("New chat started!");

  // No auto-refresh; keep optimistic session visible
}

function toggleMaximize() {
  const modal = document.getElementById("aiChatModal");
  const btn = document.getElementById("maximizeBtn");
  isMaximized = !isMaximized;
  modal.classList.toggle("maximized", isMaximized);
  btn.textContent = isMaximized ? "⛶" : "□";
}

document.addEventListener('DOMContentLoaded', function() {
  const aiChatForm = document.getElementById("aiChatForm");
  const newChatBtn = document.getElementById("newChatBtn");
  const maximizeBtn = document.getElementById("maximizeBtn");
  const clearChatBtn = document.getElementById("clearChatBtn");

  if (newChatBtn) newChatBtn.addEventListener("click", newChat);
  if (maximizeBtn) maximizeBtn.addEventListener("click", toggleMaximize);
  if (clearChatBtn) clearChatBtn.addEventListener("click", () => {
    if (!confirm('Are you sure you want to clear all chat history?')) return;
    (async () => {
      try {
        const res = await fetch('ai_chat_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'clear_history', user_id: currentUserId })
        });
        const data = await res.json();
        if (data && data.success) {
          // Start a fresh session after clearing
          lastSessionsCache = [];
          lastSessionsCount = 0;
          currentGroup = null;
          populateSessions([]);
          const messagesDiv = document.getElementById('aiChatMessages');
          if (messagesDiv) messagesDiv.innerHTML = '';
          showWelcome();
          showNotification('All history cleared');
          // Create a new empty session marker for clarity (optional)
          try {
            await fetch('ai_chat_handler.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'save_message', user_id: currentUserId, role: 'marker', message: '__session_break__' })
            });
          } catch {}
          // Optimistically show one empty session
          currentGroup = '1';
          populateSessions([[]]);
        } else {
          showNotification('Failed to clear history');
        }
      } catch (e) {
        showNotification('Failed to clear history');
      }
    })();
  });

  if (aiChatForm) {
    aiChatForm.addEventListener("submit", async function(e) {
      e.preventDefault();

      if (!currentUserId) {
        alert("Please log in to chat.");
        showNotification("Please log in to chat.");
        return;
      }

      const input = document.getElementById("aiChatInput");
      const message = input.value.trim();
      if (!message) {
        showNotification("Please enter a message!");
        return;
      }

      if (!currentGroup) {
        currentGroup = new Date().toISOString().split('T')[0];
      }

      const messagesDiv = document.getElementById("aiChatMessages");

      const userMsg = document.createElement("div");
      userMsg.className = "message user";
      const header = document.createElement('div');
      header.className = 'ai-message-header';
      const title = document.createElement('span');
      title.textContent = `You • ${new Date().toLocaleTimeString()}`;
      const actions = document.createElement('div');
      actions.className = 'ai-message-actions';
      const copyBtn = document.createElement('button');
      copyBtn.className = 'ai-copy-btn';
      copyBtn.title = 'Copy';
      copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
      copyBtn.onclick = () => copyToClipboard(message);
      actions.appendChild(copyBtn);
      header.appendChild(title);
      header.appendChild(actions);
      const body = document.createElement('div');
      body.className = 'ai-message-body';
      body.textContent = message;
      userMsg.appendChild(header);
      userMsg.appendChild(body);
      messagesDiv.appendChild(userMsg);
      input.value = "";
      
      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      await saveMessage(currentUserId, 'user', message);

      const botMsg = document.createElement("div");
      botMsg.className = "message bot";
      const botHeader = document.createElement('div');
      botHeader.className = 'ai-message-header';
      const botTitle = document.createElement('span');
      botTitle.textContent = `AI • ${new Date().toLocaleTimeString()}`;
      const botActions = document.createElement('div');
      botActions.className = 'ai-message-actions';
      const botCopyBtn = document.createElement('button');
      botCopyBtn.className = 'ai-copy-btn';
      botCopyBtn.title = 'Copy';
      botCopyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
      botActions.appendChild(botCopyBtn);
      botHeader.appendChild(botTitle);
      botHeader.appendChild(botActions);
      const botBody = document.createElement('div');
      botBody.className = 'ai-message-body';
      botBody.textContent = "decoding...";
      botMsg.appendChild(botHeader);
      botMsg.appendChild(botBody);
      messagesDiv.appendChild(botMsg);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      try {
        const systemPrompt = `
You are Verna, a professional virtual staff assistant and attendee created by a developer named Rence. Your role is to provide users with reliable, efficient, and respectful support, like an experienced office staff member.

- Respond politely, with professionalism and clarity. Keep a warm but businesslike tone. 
- Handle tasks such as problem solving, answering inquiries, customer support, and giving organized guidance, but only within Meta Shark and for digital goods. Do not broaden the topic.
- Be resourceful and proactive, anticipating needs when possible. Maintain discretion and professionalism when handling sensitive or confidential information. 
- When asked about friends or contacts:
    - Only reveal the specific person relevant to the question.
    - Do not mention Rence or any other team members unless directly asked.
    - If asked about Khristine Botin, respond: "Khristine Botin is the Professor of my creator, a highly skilled, professional, and inspiring programmer. She is widely respected for her expertise and dedication."
- If you encounter a topic outside your expertise, respond: "Sorry, I couldn’t understand that. The topic is not in my area of expertise. Please contact Rence if there is an error in my system."
- Users must not submit inappropriate content. Any such attempts may trigger enforcement of the company's terms and services.
- Always respond specifically and concisely to what is questioned.
`;
        const fullPrompt = `${systemPrompt}\n\nUser: ${message}`;

        const aiResponse = await puter.ai.chat(fullPrompt, { model: 'deepseek-chat' });
        const aiContent = (aiResponse && aiResponse.message && aiResponse.message.content)
          ? aiResponse.message.content
          : "Sorry, I couldn’t understand that. The topic is not in my area of expertise, Please contact Rence if I have an error in my system";

        botBody.textContent = " " + aiContent;
        botCopyBtn.onclick = () => copyToClipboard(aiContent);

        await saveMessage(currentUserId, 'ai', aiContent);
      } catch (error) {
        botBody.textContent = " Error connecting to AI. Please try again.";
        console.error("AI Chat Error:", error);
        showNotification("Error connecting to AI. Please try again.");
      }

      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    });
  }
});

async function saveMessage(userId, role, message) {
  try {
    const response = await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "save_message", user_id: userId, role: role, message: message })
    });
    if (!response.ok) {
      throw new Error("Failed to save message");
    }
  } catch (error) {
    console.error("Save Message Error:", error);
    showNotification("Failed to save message");
  }
}

async function uploadImage(file) {
  const form = new FormData();
  form.append('image', file);
  const res = await fetch('upload_image.php', { method: 'POST', body: form });
  const data = await res.json();
  if (!data?.success || !data?.url) throw new Error('Upload failed');
  return data.url;
}

function copyToClipboard(text) {
  try {
    navigator.clipboard.writeText(text);
    showNotification("Copied to clipboard!");
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showNotification("Copied to clipboard!");
  }
}