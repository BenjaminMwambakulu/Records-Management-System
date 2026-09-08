import { useState } from "react";
import recordsWebp from "../../assets/images/records.webp";
import { ArrowRight, Loader2 } from "lucide-react";
import { useAuth } from "../../Context/AuthContext";
import { getImagePath } from "@/lib/utils";

export default function LoginPage() {
  const { login } = useAuth();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  const handleSignIn = async () => {
    setError("");
    setIsSubmitting(true);
    try {
      await login(import.meta.env.VITE_REDIRECT_URI);
    } catch {
      setError("Couldn't start sign-in. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-csit-surface flex items-center justify-center p-4 md:p-8">
      <div className="w-full max-w-5xl bg-csit-background rounded-3xl shadow-xl overflow-hidden flex flex-col md:flex-row min-h-[640px]">
        {/* Brand panel */}
        <div className="relative md:w-[45%] h-56 md:h-auto rounded-3xl m-2 overflow-hidden flex flex-col justify-between p-8">
          {/* background image */}
          <img
            src={recordsWebp}
            alt=""
            aria-hidden
            className="absolute inset-0 h-full w-full object-cover"
          />
          {/* dark brand overlay */}
          <div className="absolute inset-0 bg-gradient-to-b from-csit-dark/75 via-csit-dark/45 to-csit-dark/85" />
          {/* soft accent blob for depth */}
          <div className="absolute inset-0 opacity-40 mix-blend-screen bg-[radial-gradient(60%_45%_at_75%_60%,rgba(180,210,255,0.55)_0%,transparent_70%)]" />

          <div className="relative flex items-center gap-2">
            <img
              src={getImagePath("mustlogo.png")}
              alt="MUST Logo"
              className="h-10 w-10 object-contain rounded-lg"
            />
            <img
              src={getImagePath("csit-logo.png")}
              alt="CSIT Logo"
              className="h-10 w-10 object-contain rounded-lg bg-white p-1"
            />
          </div>

          <div className="relative text-white max-w-xs">
            <p className="text-sm text-white/70 mb-2">You can easily</p>
            <h2 className="text-2xl font-semibold leading-snug">
              Manage society records, members and events with clarity and ease
            </h2>
          </div>
        </div>

        {/* Action panel */}
        <div className="flex-1 flex items-center justify-center p-8 md:p-14">
          <div className="w-full max-w-sm space-y-6">
            <div>
              <div className="flex items-center gap-2 mb-4">
                <img
                  src={getImagePath("mustlogo.png")}
                  alt="MUST Logo"
                  className="h-8 w-8 object-contain"
                />
                <img
                  src={getImagePath("csit-logo.png")}
                  alt="CSIT Logo"
                  className="h-8 w-8 object-contain rounded-md border border-csit-muted"
                />
              </div>
              <h1 className="text-2xl font-semibold text-csit-text">
                Welcome to the CSIT Society
              </h1>
              <p className="text-sm text-csit-text-muted mt-2">
                Sign in with your MUST account to access student records,
                events, documents, and more.
              </p>
            </div>

            {error && (
              <div className="text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-md px-3 py-2">
                {error}
              </div>
            )}

            <button
              type="button"
              onClick={handleSignIn}
              disabled={isSubmitting}
              className="w-full h-11 rounded-lg text-white text-sm font-medium transition-opacity disabled:opacity-60 bg-gradient-to-r from-csit-primary to-csit-primary-light flex items-center justify-center gap-2"
            >
              {isSubmitting ? (
                <Loader2 size={16} className="animate-spin" />
              ) : (
                <ArrowRight size={16} />
              )}
              {isSubmitting ? "Redirecting…" : "Continue with MUST"}
            </button>

            <p className="text-center text-sm text-csit-text-muted">
              Trouble signing in?{" "}
              <a href="#" className="text-csit-primary font-medium hover:underline">
                Contact support
              </a>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}