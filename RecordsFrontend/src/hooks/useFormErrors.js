import { useState } from "react"
import { extractErrorMessage, getFieldErrors } from "@/lib/errors"

export function useFormErrors() {
  const [fieldErrors, setFieldErrors] = useState({})
  const [formError, setFormError] = useState(null)

  function applyApiError(err) {
    setFormError(extractErrorMessage(err))
    setFieldErrors(getFieldErrors(err))
  }

  function clear() {
    setFormError(null)
    setFieldErrors({})
  }

  function clearField(name) {
    setFieldErrors((prev) => {
      if (!prev[name]) return prev
      const next = { ...prev }
      delete next[name]
      return next
    })
  }

  function fieldProps(name) {
    return {
      "aria-invalid": Boolean(fieldErrors[name]) || undefined,
      "aria-describedby": fieldErrors[name] ? `${name}-error` : undefined,
    }
  }

  return { fieldErrors, formError, applyApiError, clear, clearField, fieldProps }
}