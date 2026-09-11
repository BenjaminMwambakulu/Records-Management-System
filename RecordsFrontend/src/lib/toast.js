import { toast } from "@/components/ui/toast"
import { extractErrorMessage } from "@/lib/errors"

export const notify = {
  success(title, description) {
    toast.add({ type: "success", title, description, timeout: 8000, priority: "low" })
  },
  error(title, description, options = {}) {
    toast.add({ type: "error", title, description, timeout: 10000, priority: "high", ...options })
  },
  info(title, description, options = {}) {
    toast.add({ type: "info", title, description, timeout: 5000, priority: "low", ...options })
  },
  warning(title, description, options = {}) {
    toast.add({ type: "warning", title, description, timeout: 5000, priority: "low", ...options })
  },
}

export function runAction(handler, { successTitle, errorTitle = "Action failed" } = {}) {
  try {
    const result = handler()
    if (successTitle) notify.success(successTitle)
    return result
  } catch (err) {
    notify.error(errorTitle, extractErrorMessage(err))
    throw err
  }
}

export async function runAsyncAction(handler, { successTitle, errorTitle = "Action failed" } = {}) {
  try {
    const result = await handler()
    if (successTitle) notify.success(successTitle)
    return result
  } catch (err) {
    notify.error(errorTitle, extractErrorMessage(err))
    throw err
  }
}