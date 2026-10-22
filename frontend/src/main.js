import './styles/main.css';
import { api } from './api/client.js';
import { render, startRouter } from './router.js';
import { session } from './session.js';
import { syncThemeWithUser } from './lib/theme.js';
import { toast } from './lib/ui.js';

session.onChange((reason) => {
  syncThemeWithUser(session.user);
  if (reason === 'expired') toast('Tu sesión terminó. Ingresa de nuevo.');
  location.hash = session.user ? '/' : '/login';
  render();
});

async function boot() {
  if (session.token) {
    try {
      session.setUser(await api.get('/auth/yo'));
      syncThemeWithUser(session.user);
    } catch {
      session.clear();
      syncThemeWithUser(null);
    }
  }
  startRouter();
}

boot();
