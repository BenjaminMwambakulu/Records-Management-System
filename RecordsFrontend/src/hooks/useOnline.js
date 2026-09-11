import { useEffect, useState } from "react"
import { notify } from "@/lib/toast"

export function useOnline() {
  const [online, setOnline] = useState(() => typeof navigator !== "undefined" ? navigator.onLine : true)

  useEffect(() => {
    function handleOnline() {
      setOnline(true)
      notify.info("You're back online", "Connection restored.")
    }
    function handleOffline() {
      setOnline(false)
      notify.error("You're offline", "Check your connection and try again.", { timeout: 0 })
    }

    window.addEventListener("online", handleOnline)
    window.addEventListener("offline", handleOffline)
    return () => {
      window.removeEventListener("online", handleOnline)
      window.removeEventListener("offline", handleOffline)
    }
  }, [])

  return online
}