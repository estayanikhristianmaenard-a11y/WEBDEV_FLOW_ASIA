export async function request(path, { method = 'GET', body, signal, responseType = 'json' } = {}) {
  const response = await fetch(path, {
    method, signal, credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
    },
    ...(body ? { body: JSON.stringify(body) } : {})
  });
  if (response.status === 204) return null;
  if (response.ok && responseType === 'blob') {
    if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
      throw new Error('The server did not return a PDF. Sign in again or retry the export.');
    }
    return response.blob();
  }
  let data;
  try { data = await response.json(); }
  catch { throw new Error('The server returned an unexpected response. Reload the dashboard or sign in at /login.'); }
  if (!response.ok) {
    if (response.status === 401 || response.status === 419) {
      throw new Error('Your session has expired. Sign in again at /login.');
    }
    const details = Object.values(data.errors || {}).flat().join(' ');
    throw new Error(details || data.message || 'The request could not be completed.');
  }
  return data;
}
