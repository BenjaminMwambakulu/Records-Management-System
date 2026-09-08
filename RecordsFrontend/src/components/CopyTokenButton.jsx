import { useCallback, useEffect, useRef, useState } from "react";
import { Check, Copy, Loader2, X } from "lucide-react";
import { useAuth } from "../Context/AuthContext";
import { Button } from "./ui/button";

const IS_LOCAL = import.meta.env.VITE_ENV === "local";

const RESET_DELAY_MS = 2000;

export default function CopyTokenButton() {
  const { getToken } = useAuth();
  const [status, setStatus] = useState("idle");
  const timeoutRef = useRef(null);

  const resetSoon = useCallback(() => {
    clearTimeout(timeoutRef.current);
    timeoutRef.current = setTimeout(() => setStatus("idle"), RESET_DELAY_MS);
  }, []);

  const handleClick = useCallback(async () => {
    console.log("[CopyToken] click fired");
    if (!navigator.clipboard) {
      console.warn("[CopyToken] navigator.clipboard unavailable");
      setStatus("failed");
      resetSoon();
      return;
    }

    setStatus("busy");

    try {
      const token = await getToken();
      console.log("[CopyToken] token resolved, length:", token?.length);
      if (!token) {
        throw new Error("No access token available");
      }
      await navigator.clipboard.writeText(token);
      console.log("[CopyToken] writeText succeeded");
      setStatus("copied");
    } catch (error) {
      console.error("[CopyToken] failed:", error);
      setStatus("failed");
    }

    resetSoon();
  }, [getToken, resetSoon]);

  useEffect(() => () => clearTimeout(timeoutRef.current), []);

  if (!IS_LOCAL) {
    return null;
  }

  const icon =
    status === "busy" ? (
      <Loader2 className="size-3.5 animate-spin" />
    ) : status === "copied" ? (
      <Check className="size-3.5 text-emerald-500" />
    ) : status === "failed" ? (
      <X className="size-3.5 text-red-500" />
    ) : (
      <Copy className="size-3.5" />
    );

  const label =
    status === "copied" ? "Copied" : status === "failed" ? "Failed" : "Copy token";

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={handleClick}
      disabled={status === "busy"}
      title="Copy the backend access token for manual testing"
    >
      {icon}
      {label}
    </Button>
  );
}