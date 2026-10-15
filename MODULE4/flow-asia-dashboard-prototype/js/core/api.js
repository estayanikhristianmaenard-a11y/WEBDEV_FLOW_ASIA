export async function request(path, { method = 'GET', body, signal, responseType = 'json' } = {}) {
  const baseUrl = globalThis.window?.flowAsiaBackend?.baseUrl;
  const url = baseUrl ? `${baseUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}` : path;
  let response;
  try {
    response = await fetch(url, {
      method, signal, credentials: 'same-origin', cache: 'no-store',
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      ...(body ? { body: JSON.stringify(body) } : {})
    });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new Error('Unable to reach the server. Check your connection and retry.');
  }
  if (response.status === 401 || response.status === 419) {
    throw new Error('Your session has expired. Reload the module or return to the main system.');
  }
  if (response.status >= 500) {
    throw new Error('The server could not complete the request. Please retry.');
  }
  if (response.status === 204) return null;
  if (response.ok && responseType === 'blob') {
    if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
      throw new Error('The server did not return a PDF. Reload the module or retry the export.');
    }
    return response.blob();
  }
  let data;
  try { data = await response.json(); }
  catch { throw new Error('The server returned an unexpected response. Reload the module.'); }
  if (!response.ok) {
    const details = Object.values(data.errors || {}).flat().join(' ');
    throw new Error(details || data.message || 'The request could not be completed.');
  }
  return data;
}
