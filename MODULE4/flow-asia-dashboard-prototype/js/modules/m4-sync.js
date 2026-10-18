// Signals request a fresh authorized read; they never supply calendar records.
export function installCalendarSync({
  refresh, isBlocked, onError,
  eventTarget = window, visibilityTarget = document,
  isVisible = () => visibilityTarget.visibilityState === 'visible',
  delay = 250,
}) {
  let timer;
  let pending = false;
  let running = false;
  let disposed = false;

  function schedule() {
    if (disposed || timer !== undefined || !pending || !isVisible()) return;
    timer = setTimeout(run, delay);
  }

  async function run() {
    timer = undefined;
    if (disposed || !pending || !isVisible()) return;
    if (running || isBlocked()) { schedule(); return; }
    pending = false; running = true;
    try { await refresh(); }
    catch (error) { onError(error); }
    finally { running = false; schedule(); }
  }

  function notify() { pending = true; schedule(); }
  eventTarget.addEventListener('flowasia:calendar-updated', notify);
  eventTarget.addEventListener('focus', notify);
  visibilityTarget.addEventListener('visibilitychange', notify);

  return function dispose() {
    disposed = true; pending = false; clearTimeout(timer);
    eventTarget.removeEventListener('flowasia:calendar-updated', notify);
    eventTarget.removeEventListener('focus', notify);
    visibilityTarget.removeEventListener('visibilitychange', notify);
  };
}
