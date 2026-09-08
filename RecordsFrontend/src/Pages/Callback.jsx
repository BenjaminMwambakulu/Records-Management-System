import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { useHandleSignInCallback } from "@logto/react";

export default function Callback() {
  const navigate = useNavigate();

  const handleSignIn = useCallback(() => {
    navigate("/", { replace: true });
  }, [navigate]);

  const { error } = useHandleSignInCallback(handleSignIn);

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center space-y-2">
          <h1 className="text-lg font-semibold text-red-600">
            Sign-in failed
          </h1>
          <p className="text-sm text-gray-600 max-w-sm">
            {error.message || "Something went wrong while signing you in."}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-csit-surface">
      <div className="flex flex-col items-center gap-4">
        <div className="loader" aria-hidden="true" />
        <p className="text-lg text-csit-text animate-pulse">
          Loading your workspace, tools, and resources
        </p>
      </div>
    </div>
  );
}