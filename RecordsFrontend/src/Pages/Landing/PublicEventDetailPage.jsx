import { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import "date-utils";
import { ArrowLeft, CalendarDays, CheckCircle, Clock, Download, Loader2, LogOut, MapPin, Phone, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/Context/AuthContext";
import { canAccessDashboard } from "@/lib/roles";
import { apiFetch } from "@/APIClients/APIClient";
import { extractErrorMessage } from "@/lib/errors";
import { notify } from "@/lib/toast";
import { QRCodeSVG } from "qrcode.react";

const OPERATORS = [
  { id: "27494cb5-ba9e-437f-a114-4e7a7686bcca", name: "TNM Mpamba", short: "tnm" },
  { id: "20be6c20-adeb-4b5b-a7ba-0769820df4fb", name: "Airtel Money", short: "airtel" },
  { id: "550c35a2-86aa-4590-9931-8f23e664cee9", name: "Changu Wallet", short: "changu" },
];

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
  const { isAuthenticated, user, logout } = useAuth();
  const qrRef = useRef(null);
  const pollingRef = useRef(null);

  const [event, setEvent] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isRegistered, setIsRegistered] = useState(false);
  const [isRegistering, setIsRegistering] = useState(false);
  const [registrationError, setRegistrationError] = useState(null);
  const [isCancelling, setIsCancelling] = useState(false);
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);

  const [showPaymentDialog, setShowPaymentDialog] = useState(false);
  const [phoneNumber, setPhoneNumber] = useState("");
  const [selectedOperator, setSelectedOperator] = useState(OPERATORS[0].id);
  const [isInitiatingPayment, setIsInitiatingPayment] = useState(false);
  const [paymentState, setPaymentState] = useState("idle");
  const [paymentError, setPaymentError] = useState(null);

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

  useEffect(() => {
    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
      }
    };
  }, []);

  const resetPaymentForm = useCallback(() => {
    setPhoneNumber("");
    setSelectedOperator(OPERATORS[0].id);
    setPaymentState("idle");
    setPaymentError(null);
  }, []);

  const startPolling = useCallback(
    (payId) => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
      }

      pollingRef.current = setInterval(async () => {
        try {
          const response = await apiFetch(`/v1/payments/${payId}/verify`);
          const payment = response?.data ?? response;

          if (payment?.status === "completed") {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
            setPaymentState("success");
            setIsRegistered(true);
            setShowPaymentDialog(false);
            resetPaymentForm();
            notify.success("Payment successful", "You are now registered for this event.");
          } else if (payment?.status === "failed") {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
            setPaymentState("failed");
            setPaymentError("Payment was not completed. Please try again.");
          }
        } catch {
          // continue polling
        }
      }, 3000);
    },
    [resetPaymentForm]
  );

  const handleRegister = async () => {
    setIsRegistering(true);
    setRegistrationError(null);
    try {
      await apiFetch(`/v1/events/${id}/register`, { method: "POST" });
      setIsRegistered(true);
      notify.success("You're registered", "Show your QR code at the event to check in.");
    } catch (err) {
      const message = extractErrorMessage(err);
      setRegistrationError(message);
      notify.error("Registration failed", message);
    } finally {
      setIsRegistering(false);
    }
  };

  const handleCancel = async () => {
    setIsCancelling(true);
    try {
      await apiFetch(`/v1/events/${id}/register`, { method: "DELETE" });
      setIsRegistered(false);
      setShowCancelConfirm(false);
      notify.success("Registration cancelled", "You can register again whenever you'd like.");
    } catch (err) {
      notify.error("Could not cancel registration", extractErrorMessage(err));
    } finally {
      setIsCancelling(false);
    }
  };

  const handleDownloadQR = () => {
    downloadQRCode(qrRef.current, `${event.title}-qr-code.png`);
  };

  const handleLogout = () => {
    logout(import.meta.env.VITE_LOGTO_POST_LOGOUT_REDIRECT_URI);
  };

  const handleOpenPayment = () => {
    resetPaymentForm();
    setShowPaymentDialog(true);
  };

  const handleClosePayment = () => {
    if (pollingRef.current) {
      clearInterval(pollingRef.current);
      pollingRef.current = null;
    }
    setShowPaymentDialog(false);
    resetPaymentForm();
  };

  const handleInitiatePayment = async () => {
    if (!phoneNumber || phoneNumber.length < 9) {
      setPaymentError("Please enter a valid phone number");
      return;
    }

    setIsInitiatingPayment(true);
    setPaymentError(null);

    try {
      const response = await apiFetch("/v1/payments/mobile-money", {
        method: "POST",
        body: JSON.stringify({
          payable_type: "App\\Models\\Event",
          payable_id: Number(id),
          amount: Number(event.entry_fee),
          phone_number: phoneNumber,
          operator_ref_id: selectedOperator,
        }),
      });

      const payId = response?.data?.payment?.id ?? response?.payment?.id;
      setPaymentState("waiting");
      startPolling(payId);
    } catch (err) {
      setPaymentError(err?.message || "Failed to initiate payment. Please try again.");
      setPaymentState("idle");
    } finally {
      setIsInitiatingPayment(false);
    }
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
        <p role="alert" className="text-sm text-rose-600">Failed to load this event.</p>
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
              canAccessDashboard(user) ? (
                <a href="/app">
                  <Button size="sm" className="h-8 px-4 text-xs font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                    Dashboard
                  </Button>
                </a>
              ) : (
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={handleLogout}
                  className="h-8 px-4 text-xs font-medium text-csit-text-muted hover:text-csit-text gap-1.5"
                >
                  <LogOut className="size-3.5" />
                  Sign out
                </Button>
              )
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
            {paymentState === "success" && (
              <div className="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200">
                <p className="text-sm font-medium text-emerald-700">
                  Payment successful! You are now registered for this event.
                </p>
              </div>
            )}
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
                  {registrationError && (
                    <p role="alert" className="mb-3 text-sm text-rose-600">
                      {registrationError}
                    </p>
                  )}
                  {Number(event.entry_fee) > 0 ? (
                    <>
                      <p className="text-sm text-csit-text-muted mb-2">
                        Pay the entry fee to register for this event.
                      </p>
                      <p className="text-lg font-semibold text-csit-text mb-4">
                        K {Number(event.entry_fee).toFixed(2)}
                      </p>
                      <Button
                        onClick={handleOpenPayment}
                        className="h-10 px-6 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full gap-2"
                      >
                        <Phone className="size-4" />
                        Pay with Mobile Money
                      </Button>
                    </>
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
                  )}
                </>
              )
            ) : (
              <>
                <p className="text-sm text-csit-text-muted mb-4">
                  {Number(event.entry_fee) > 0
                    ? "Sign in to pay the entry fee and register for this event."
                    : "Sign in to register for this event and check in."}
                </p>
                <a href="/login">
                  <Button className="h-10 px-6 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                    {Number(event.entry_fee) > 0 ? "Sign in to pay & register" : "Sign in to register"}
                  </Button>
                </a>
              </>
            )}
          </div>
        </div>
      </main>

      {/* ─── Payment Dialog ─── */}
      {showPaymentDialog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
          <div className="bg-white rounded-2xl shadow-lg p-6 max-w-sm w-full">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-base font-semibold text-csit-text">Pay Entry Fee</h3>
              <button onClick={handleClosePayment} className="text-csit-text-muted hover:text-csit-text">
                <XCircle className="size-5" />
              </button>
            </div>

            {paymentState === "idle" && (
              <>
                <p className="text-sm text-csit-text-muted mb-4">
                  Enter your mobile money number to pay K {Number(event.entry_fee).toFixed(2)}
                </p>

                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-csit-text mb-1.5">
                      Phone Number
                    </label>
                    <input
                      type="tel"
                      value={phoneNumber}
                      onChange={(e) => setPhoneNumber(e.target.value)}
                      placeholder="e.g. 991234567"
                      className="w-full px-3 py-2 text-sm border border-csit-border rounded-lg focus:outline-none focus:ring-2 focus:ring-csit-primary/20 focus:border-csit-primary"
                    />
                    <p className="mt-1 text-xs text-csit-text-muted">
                      Enter 9-digit number without country code or leading zero
                    </p>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-csit-text mb-1.5">
                      Mobile Money Provider
                    </label>
                    <div className="space-y-2">
                      {OPERATORS.map((op) => (
                        <label
                          key={op.id}
                          className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                            selectedOperator === op.id
                              ? "border-csit-primary bg-csit-primary/5"
                              : "border-csit-border hover:border-csit-primary/30"
                          }`}
                        >
                          <input
                            type="radio"
                            name="operator"
                            value={op.id}
                            checked={selectedOperator === op.id}
                            onChange={(e) => setSelectedOperator(e.target.value)}
                            className="size-4 text-csit-primary focus:ring-csit-primary/20"
                          />
                          <span className="text-sm font-medium text-csit-text">{op.name}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                </div>

                {paymentError && (
                  <p role="alert" className="mt-3 text-sm text-rose-600">{paymentError}</p>
                )}

                <div className="flex gap-3 mt-6">
                  <Button
                    variant="outline"
                    onClick={handleClosePayment}
                    className="flex-1"
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleInitiatePayment}
                    disabled={isInitiatingPayment || !phoneNumber}
                    className="flex-1 bg-csit-primary hover:bg-csit-primary-dark text-white"
                  >
                    {isInitiatingPayment ? (
                      <>
                        <Loader2 className="size-4 animate-spin mr-2" />
                        Initiating…
                      </>
                    ) : (
                      "Pay Now"
                    )}
                  </Button>
                </div>
              </>
            )}

            {paymentState === "waiting" && (
              <div className="flex flex-col items-center py-6">
                <Loader2 size={40} className="animate-spin text-csit-primary mb-4" />
                <p className="text-sm font-medium text-csit-text mb-1">
                  Waiting for payment confirmation
                </p>
                <p className="text-xs text-csit-text-muted text-center">
                  Please check your phone and approve the payment prompt.
                  <br />
                  This may take a few moments.
                </p>
                <Button
                  variant="ghost"
                  onClick={handleClosePayment}
                  className="mt-4 text-sm text-csit-text-muted"
                >
                  Cancel
                </Button>
              </div>
            )}

            {paymentState === "success" && (
              <div className="flex flex-col items-center py-6">
                <div className="flex size-12 items-center justify-center rounded-full bg-emerald-100 mb-4">
                  <CheckCircle className="size-6 text-emerald-600" />
                </div>
                <p className="text-sm font-medium text-csit-text mb-1">
                  Payment successful!
                </p>
                <p className="text-xs text-csit-text-muted text-center mb-4">
                  You are now registered for this event.
                </p>
                <Button
                  onClick={handleClosePayment}
                  className="bg-csit-primary hover:bg-csit-primary-dark text-white"
                >
                  Done
                </Button>
              </div>
            )}

            {paymentState === "failed" && (
              <div className="flex flex-col items-center py-6">
                <div className="flex size-12 items-center justify-center rounded-full bg-rose-100 mb-4">
                  <XCircle className="size-6 text-rose-600" />
                </div>
                <p className="text-sm font-medium text-csit-text mb-1">
                  Payment failed
                </p>
                <p className="text-xs text-csit-text-muted text-center mb-4">
                  {paymentError || "The payment was not completed. Please try again."}
                </p>
                <div className="flex gap-3">
                  <Button
                    variant="outline"
                    onClick={handleClosePayment}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={() => {
                      setPaymentState("idle");
                      setPaymentError(null);
                    }}
                    className="bg-csit-primary hover:bg-csit-primary-dark text-white"
                  >
                    Try Again
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

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
                disabled={isCancelling}
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
