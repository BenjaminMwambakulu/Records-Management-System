import { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import "date-utils";
import { ArrowLeft, CalendarDays, CheckCircle, Clock, Download, Loader2, MapPin, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/Context/AuthContext";
import { apiFetch } from "@/APIClients/APIClient";
import { QRCodeSVG } from "qrcode.react";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

function downloadQRCode(svgElement, filename) {
  if (!svgElement) return;
  const svgData = new XMLSerializer().serializeToString(svgElement);
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d");
  const img = new Image();

  img.onload = () => {
    canvas.width = img.width;
    canvas.height = img.height;
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0);
    const link = document.createElement("a");
    link.download = filename;
    link.href = canvas.toDataURL("image/png");
    link.click();
  };

  img.src = "data:image/svg+xml;base64," + btoa(svgData);
}

export default function PublicEventDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const qrRef = useRef(null);

  const [event, setEvent] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isRegistered, setIsRegistered] = useState(false);
  const [isRegistering, setIsRegistering] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);

    apiFetch(`/v1/events/${id}`)
      .then((body) => {
        setEvent(body?.data ?? body);
      })
      .catch((err) => {
        setError(err);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!isAuthenticated || !id) return;

    apiFetch(`/v1/events/${id}/registration`)
      .then((body) => {
        setIsRegistered(body?.data?.registered ?? false);
      })
      .catch(() => {});
  }, [isAuthenticated, id]);

  const handleRegister = async () => {
    setIsRegistering(true);
    try {
      await apiFetch(`/v1/events/${id}/register`, { method: "POST" });
      setIsRegistered(true);
    } catch (err) {
      // already registered or error
    } finally {
      setIsRegistering(false);
    }
  };

  const handleCancel = async () => {
    setShowCancelConfirm(false);
    setIsCancelling(true);
    try {
      await apiFetch(`/v1/events/${id}/register`, { method: "DELETE" });
      setIsRegistered(false);
    } catch (err) {
      // error
    } finally {
      setIsCancelling(false);
    }
  };

  const handleDownloadQR = () => {
    downloadQRCode(qrRef.current, `${event.title}-qr-code.png`);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white">
        <Loader2 size={32} className="animate-spin text-csit-primary" />
      </div>
    );
  }

  if (error || !event) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center gap-3 bg-white text-center px-4">
        <p className="text-sm text-rose-600">Failed to load this event.</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => navigate("/")}>
            Back to home
          </Button>
          <Button onClick={load}>Retry</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white text-csit-text">
      {/* ─── Navbar ─── */}
      <nav className="fixed top-0 left-0 right-0 z-50 bg-white/70 backdrop-blur-xl border-b border-csit-border/50">
        <div className="mx-auto max-w-7xl px-6 flex items-center justify-between h-12">
          <a href="/" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
            CSIT Records
          </a>
          <div className="flex items-center gap-4">
            {isAuthenticated ? (
              <a href="/app">
                <Button size="sm" className="h-8 px-4 text-xs font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                  Dashboard
                </Button>
              </a>
            ) : (
              <a href="/login">
                <Button size="sm" className="h-8 px-4 text-xs font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                  Sign in
                </Button>
              </a>
            )}
          </div>
        </div>
      </nav>

      {/* ─── Content ─── */}
      <main className="pt-20 pb-16">
        <div className="mx-auto max-w-3xl px-6">
          <Button
            type="button"
            variant="ghost"
            onClick={() => navigate("/")}
            className="mb-6 justify-start gap-1.5 text-sm font-medium text-csit-text-muted hover:text-csit-text"
          >
            <ArrowLeft className="size-4" />
            Back to home
          </Button>

          {event.cover_url ? (
            <img
              src={event.cover_url}
              alt={`Cover of ${event.title}`}
              className="w-full h-64 object-cover rounded-2xl border border-csit-border/30 mb-8"
            />
          ) : (
            <div className="w-full h-48 flex items-center justify-center rounded-2xl border border-dashed border-csit-border bg-csit-surface mb-8">
              <CalendarDays className="size-10 text-csit-text-muted/40" />
            </div>
          )}

          <h1 className="text-3xl md:text-4xl font-bold tracking-tight text-csit-text mb-4">
            {event.title}
          </h1>

          <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-csit-text-muted mb-6">
            <span className="inline-flex items-center gap-1.5">
              <CalendarDays className="size-4" />
              {formatDate(event.event_date)}
            </span>
            {event.start_time && (
              <span className="inline-flex items-center gap-1.5">
                <Clock className="size-4" />
                {event.start_time}
              </span>
            )}
            {event.duration && (
              <span className="text-csit-text-muted">
                {event.duration} min
              </span>
            )}
            {event.location && (
              <span className="inline-flex items-center gap-1.5">
                <MapPin className="size-4" />
                {event.location}
              </span>
            )}
            {event.entry_fee != null && Number(event.entry_fee) > 0 && (
              <span className="font-medium text-csit-text">
                Entry fee: K {Number(event.entry_fee).toFixed(2)}
              </span>
            )}
          </div>

          {event.description && (
            <div className="prose prose-sm max-w-none text-csit-text leading-relaxed whitespace-pre-line">
              {event.description}
            </div>
          )}

          {/* ─── Registration section ─── */}
          <div className="mt-10 p-6 rounded-2xl bg-csit-surface border border-csit-border/50 text-center">
            {new Date(event.event_date) <= new Date() ? (
              <div className="flex flex-col items-center gap-2">
                <div className="flex size-10 items-center justify-center rounded-full bg-slate-100">
                  <Clock className="size-5 text-slate-500" />
                </div>
                <p className="text-sm font-medium text-csit-text">Registration closed</p>
                <p className="text-xs text-csit-text-muted">This event has already passed.</p>
              </div>
            ) : isAuthenticated ? (
              isRegistered ? (
                <div className="flex flex-col items-center gap-4">
                  <div className="flex size-10 items-center justify-center rounded-full bg-emerald-100">
                    <CheckCircle className="size-5 text-emerald-600" />
                  </div>
                  <p className="text-sm font-medium text-csit-text">You&apos;re registered for this event</p>
                  <p className="text-xs text-csit-text-muted">Show this QR code at the event to check in.</p>
                  {event.qr_code_hash && (
                    <div className="p-3 bg-white rounded-xl border border-csit-border/30 shadow-sm">
                      <QRCodeSVG
                        ref={qrRef}
                        value={event.qr_code_hash}
                        size={160}
                        level="M"
                      />
                    </div>
                  )}
                  <div className="flex items-center gap-3 mt-1">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={handleDownloadQR}
                      className="h-8 px-3 text-xs font-medium gap-1.5"
                    >
                      <Download className="size-3.5" />
                      Download QR
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setShowCancelConfirm(true)}
                      disabled={isCancelling}
                      className="h-8 px-3 text-xs font-medium text-rose-600 hover:bg-rose-50 gap-1.5"
                    >
                      <XCircle className="size-3.5" />
                      Cancel registration
                    </Button>
                  </div>
                </div>
              ) : (
                <>
                  <p className="text-sm text-csit-text-muted mb-4">
                    Register to attend this event and check in via QR code.
                  </p>
                  <Button
                    onClick={handleRegister}
                    disabled={isRegistering}
                    className="h-10 px-6 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full"
                  >
                    {isRegistering ? "Registering…" : "Register for this event"}
                  </Button>
                </>
              )
            ) : (
              <>
                <p className="text-sm text-csit-text-muted mb-4">
                  Sign in to register for this event and check in.
                </p>
                <a href="/login">
                  <Button className="h-10 px-6 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                    Sign in to register
                  </Button>
                </a>
              </>
            )}
          </div>
        </div>
      </main>

      {/* ─── Cancel confirmation dialog ─── */}
      {showCancelConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
          <div className="bg-white rounded-2xl shadow-lg p-6 max-w-sm w-full text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-rose-100 mx-auto mb-4">
              <XCircle className="size-5 text-rose-600" />
            </div>
            <h3 className="text-base font-semibold text-csit-text mb-2">Cancel registration?</h3>
            <p className="text-sm text-csit-text-muted mb-6">
              Are you sure you want to cancel your registration for this event? You can register again later.
            </p>
            <div className="flex items-center justify-center gap-3">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowCancelConfirm(false)}
                className="h-9 px-4 text-sm"
              >
                Keep registration
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={handleCancel}
                disabled={isCancelling}
                className="h-9 px-4 text-sm"
              >
                {isCancelling ? "Cancelling…" : "Yes, cancel"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
