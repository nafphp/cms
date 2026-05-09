import { initChat } from './ai/chat-controller.js';

document.querySelectorAll('[data-local-ai-chat]').forEach(initChat);
