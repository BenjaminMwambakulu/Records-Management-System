export function extractErrorMessage(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object") {
    const messages = Object.values(bodyErrors)
      .flat()
      .filter((msg) => typeof msg === "string" && (msg.includes(" ") || msg.endsWith(".")))
      .slice(0, 3);
    if (messages.length) return messages.join(". ");
  }
  return err?.body?.message || err?.message || "Something went wrong. Please try again.";
}

export function isValidationError(err) {
  return err?.status === 422;
}

export function getFieldErrors(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object" && !Array.isArray(bodyErrors)) {
    return bodyErrors;
  }
  return {};
}