const tokenStore = {
  getToken: async () => null,
};

const unauthorizedHandlerStore = {
  handler: null,
};

export function setTokenGetter(getter) {
  tokenStore.getToken = getter;
}

export function setOnUnauthorized(handler) {
  unauthorizedHandlerStore.handler = handler;
}

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";

const REQUEST_TIMEOUT_MS = 30000;

function requestSignal(userSignal) {
  const timeoutSignal = AbortSignal.timeout(REQUEST_TIMEOUT_MS);

  if (!userSignal) return timeoutSignal;

  return AbortSignal.any([userSignal, timeoutSignal]);
}

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

  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      credentials: "omit",
      ...rest,
      signal: requestSignal(rest.signal),
      headers: requestHeaders,
    });
  } catch (err) {
    if (err?.name === "AbortError" || err?.name === "TimeoutError") {
      const timeoutError = new Error(
        "The request timed out. Please try again."
      );
      timeoutError.status = 408;
      throw timeoutError;
    }
    throw err;
  }

  if (response.status === 401) {
    unauthorizedHandlerStore.handler?.();
  }

  if (!response.ok) {
    let message = `Request failed with status ${response.status}`;
    let errorBody = null;
    const contentType = response.headers?.get("content-type") || "";
    if (contentType.includes("application/json")) {
      errorBody = await response.json().catch(() => null);
      if (errorBody?.message) {
        message = errorBody.message;
      }
    } else {
      const text = await response.text().catch(() => "");
      const match =
        text.match(/<title>(.*?)<\/title>/i) ||
        text.match(/<center><h1>(.*?)<\/h1><\/center>/i);
      if (match?.[1]) {
        message = match[1].trim();
      } else if (text && text.length < 200) {
        message = text.trim();
      }
    }

    const error = new Error(message);
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

  const text = await response.text();

  return text ? JSON.parse(text) : null;
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
  delete: (path, body, options) =>
    apiFetch(path, {
      ...options,
      method: "DELETE",
      body:
        body === undefined || body === null || body instanceof FormData
          ? body
          : JSON.stringify(body),
    }),
};