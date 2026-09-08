const tokenStore = {
  getToken: async () => null,
};

export function setTokenGetter(getter) {
  tokenStore.getToken = getter;
}

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";

export async function apiFetch(path, options = {}) {
  const { headers, ...rest } = options;
  const token = await tokenStore.getToken();

  const requestHeaders = new Headers(headers);
  requestHeaders.set("Accept", "application/json");

  if (rest.body && !(rest.body instanceof FormData)) {
    requestHeaders.set("Content-Type", "application/json");
  }

  if (token) {
    requestHeaders.set("Authorization", `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...rest,
    headers: requestHeaders,
  });

  if (!response.ok) {
    const errorBody = await response.json().catch(() => null);
    const error = new Error(
      errorBody?.message || `Request failed with status ${response.status}`
    );
    error.status = response.status;
    error.body = errorBody;
    throw error;
  }

  if (options.raw) {
    return response;
  }

  if (response.status === 204) {
    return null;
  }

  return response.json();
}

export const api = {
  get: (path, options) => apiFetch(path, { ...options, method: "GET" }),
  post: (path, body, options) =>
    apiFetch(path, {
      ...options,
      method: "POST",
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  put: (path, body, options) =>
    apiFetch(path, {
      ...options,
      method: "PUT",
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  patch: (path, body, options) =>
    apiFetch(path, {
      ...options,
      method: "PATCH",
      body: JSON.stringify(body),
    }),
  delete: (path, options) => apiFetch(path, { ...options, method: "DELETE" }),
};