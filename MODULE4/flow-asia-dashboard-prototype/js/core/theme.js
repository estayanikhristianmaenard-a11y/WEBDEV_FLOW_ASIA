import { store } from './state.js';

export function initTheme() {
  const btn = document.getElementById('theme-toggle-btn');
  btn.addEventListener('click', () => {
    const current = store.getState().theme;
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    btn.textContent = next === 'dark' ? '🌙 Dark Mode' : '☀️ Light Mode';
    store.setTheme(next);
  });
}
