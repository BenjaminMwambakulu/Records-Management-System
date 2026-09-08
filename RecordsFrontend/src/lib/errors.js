export function extractErrorMessage(err) {
  const bodyErrors = err?.body?.errors;
  if (bodyErrors && typeof bodyErrors === "object") {
    const messages = Object.values(bodyErrors).flat().slice(0, 3);
    if (messages.length) return messages.join(". ");
  }
  return err?.message || "Something went wrong. Please try again.";
}