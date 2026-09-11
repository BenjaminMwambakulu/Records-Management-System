import { Component } from "react";
import { TriangleAlertIcon } from "lucide-react";

class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error, errorInfo) {
    console.error("[ErrorBoundary]", error?.message ?? error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen items-center justify-center bg-csit-surface p-6">
          <div className="w-full max-w-md rounded-2xl border border-rose-200 bg-white p-8 text-center shadow-lg">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600">
              <TriangleAlertIcon className="h-6 w-6" aria-hidden="true" />
            </div>
            <h1 className="text-lg font-semibold text-csit-text">Something went wrong</h1>
            <p className="mt-2 text-sm text-csit-text-muted">
              An unexpected error interrupted the app. Reload to continue.
            </p>
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="mt-6 inline-flex h-9 items-center justify-center rounded-lg bg-csit-primary px-4 text-sm font-medium text-white transition-colors hover:bg-csit-primary/90"
            >
              Reload app
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;