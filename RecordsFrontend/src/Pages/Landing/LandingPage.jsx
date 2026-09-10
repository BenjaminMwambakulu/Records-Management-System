import { ArrowRight, FileText, Package, Shield, Users, Clock, BarChart3, ChevronRight, CalendarDays } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { useAuth } from "@/Context/AuthContext";
import { canAccessDashboard } from "@/lib/roles";
import recordsHero from "@/assets/images/records.webp";
import mustLogo from "@/assets/images/mustlogo.png";
import PublicEvents from "./PublicEvents";
import PublicDocuments from "./PublicDocuments";

const features = [
  {
    icon: FileText,
    title: "Document Management",
    description: "Centralized storage for all society documents with version control, categories, and secure access permissions.",
  },
  {
    icon: Users,
    title: "Member Directory",
    description: "Maintain an organized member database with roles, attendance tracking, and import/export capabilities.",
  },
  {
    icon: Package,
    title: "Asset Tracking",
    description: "Track society equipment and assets with checkout/return workflows, status monitoring, and maintenance scheduling.",
  },
  {
    icon: BarChart3,
    title: "Financial Records",
    description: "Transparent financial tracking with categories, summaries, and exportable reports for accountability.",
  },
  {
    icon: Shield,
    title: "Role-Based Access",
    description: "Granular permission system with Spatie roles — admins, managers, and members see only what they need.",
  },
  {
    icon: Clock,
    title: "Event Management",
    description: "Plan and manage society events with RSVPs, attendance tracking, cover images, and published/draft states.",
  },
];

const stats = [
  { value: "500+", label: "Documents Managed" },
  { value: "200+", label: "Active Members" },
  { value: "50+", label: "Assets Tracked" },
  { value: "100%", label: "Audit Compliance" },
];
 
export default function LandingPage() {
  const { isAuthenticated, user, logout } = useAuth();
  const showDashboard = canAccessDashboard(user);

  const rawName = user?.name;
  const isPlaceholderName =
    !rawName ||
    /^(undefined|null|NaN|\[object Object\])(\s+(undefined|null|NaN))?$/i.test(
      rawName.trim()
    );
  const displayName = isPlaceholderName
    ? user?.username || "Guest"
    : rawName;
  const initials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toUpperCase();

  const handleLogout = () => {
    logout(import.meta.env.VITE_LOGTO_POST_LOGOUT_REDIRECT_URI);
  };

  return (
    <div className="min-h-screen bg-white text-csit-text">
      {/* ─── Navbar ─── */}
      <nav className="fixed top-0 left-0 right-0 z-50 bg-white/70 backdrop-blur-xl border-b border-csit-border/50">
        <div className="mx-auto max-w-7xl px-6 flex items-center justify-between h-12">
          <div className="flex items-center gap-2">
            <img src={mustLogo} alt="MUST Logo" className="h-6 w-auto" />
            <span className="font-semibold text-sm tracking-tight">CSIT Records</span>
          </div>
          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
              Features
            </a>
            <a href="#events" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
              Events
            </a>
            <a href="#documents" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
              Documents
            </a>
          </div>
          <div className="flex items-center gap-4">
            {isAuthenticated ? (
              <>
                {user ? (
                  <div className="hidden sm:flex items-center gap-2 pl-1 pr-2 py-1 rounded-full border border-csit-border/50">
                    <Avatar className="size-6">
                      {user?.avatar ? <AvatarImage src={user.avatar} alt={displayName} /> : null}
                      <AvatarFallback className="text-[10px]">{initials}</AvatarFallback>
                    </Avatar>
                    <span className="text-xs font-medium text-csit-text">{displayName}</span>
                  </div>
                ) : null}
                <button
                  onClick={handleLogout}
                  className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors hidden sm:block"
                >
                  Sign out
                </button>
                {showDashboard ? (
                  <a href="/app">
                    <Button size="sm" className="h-8 px-4 text-xs font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                      Dashboard
                    </Button>
                  </a>
                ) : null}
              </>
            ) : (
              <>
                <a href="/login" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors hidden sm:block">
                  Sign in
                </a>
                <a href="/login">
                  <Button size="sm" className="h-8 px-4 text-xs font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full">
                    Get Started
                  </Button>
                </a>
              </>
            )}
          </div>
        </div>
      </nav>

      {/* ─── Hero ─── */}
      <section className="pt-20 pb-8 md:pt-28 md:pb-12">
        <div className="mx-auto max-w-7xl px-6 text-center">
          <p className="text-sm font-medium text-csit-primary mb-3 tracking-wide uppercase">
            Built for MUST CSIT Society
          </p>
          <h1 className="text-5xl md:text-7xl font-bold tracking-tight text-csit-text leading-[1.05] mb-5">
            Organize your society&apos;s
            <br />
            records effortlessly.
          </h1>
          <p className="text-lg md:text-xl text-csit-text-muted max-w-xl mx-auto leading-relaxed mb-8">
            A modern records management system designed for student societies.
            Manage documents, members, assets, finances, and events — all in one secure platform.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-12">
            <a href={showDashboard ? "/app" : "/login"}>
              <Button className="h-11 px-7 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full gap-2">
                {showDashboard ? "Go to Dashboard" : "Get started — free"}
                <ArrowRight className="size-4" />
              </Button>
            </a>
            <Button variant="ghost" className="h-11 px-7 text-sm font-medium text-csit-primary hover:bg-csit-primary/5 rounded-full gap-1">
              Learn more
              <ChevronRight className="size-4" />
            </Button>
          </div>

          {/* Product showcase — Apple style */}
          <div className="relative mx-auto max-w-5xl">
            <div className="absolute inset-0 bg-gradient-to-b from-csit-primary/5 to-transparent rounded-3xl -z-10 scale-105 blur-2xl" />
            <img
              src={recordsHero}
              alt="CSIT Records Dashboard"
              className="w-full rounded-2xl shadow-2xl border border-csit-border/30"
            />
          </div>
        </div>
      </section>

      {/* ─── Stats — Dark band ─── */}
      <section className="bg-csit-dark py-16 md:py-20">
        <div className="mx-auto max-w-7xl px-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
            {stats.map((stat, i) => (
              <div key={i} className="text-center">
                <div className="text-4xl md:text-5xl font-bold text-white mb-1 tracking-tight">
                  {stat.value}
                </div>
                <div className="text-sm text-white/50">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Features — Apple product-page style ─── */}
      <section id="features" className="py-24 md:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center mb-20">
            <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-csit-text mb-4">
              Everything you need.
              <br />
              Nothing you don&apos;t.
            </h2>
            <p className="text-lg text-csit-text-muted max-w-lg mx-auto">
              Purpose-built modules that work together seamlessly.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-14">
            {features.map((feature, i) => (
              <div key={i} className="flex gap-5">
                <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-csit-primary/8 text-csit-primary">
                  <feature.icon className="size-5" strokeWidth={1.5} />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-csit-text mb-1">{feature.title}</h3>
                  <p className="text-sm text-csit-text-muted leading-relaxed">{feature.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Public Events ─── */}
      <section id="events" className="py-24 md:py-32 bg-csit-surface">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 rounded-full border border-csit-border bg-white px-3 py-1 text-xs font-medium text-csit-primary mb-4">
              <CalendarDays className="size-3.5" />
              Upcoming Events
            </div>
            <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-csit-text mb-4">
              See what&apos;s happening.
            </h2>
            <p className="text-lg text-csit-text-muted max-w-lg mx-auto">
              Browse upcoming society events. Sign in to register and check in.
            </p>
          </div>

          <PublicEvents />

          <div className="text-center mt-12">
            <a href={showDashboard ? "/app" : "/login"}>
              <Button className="h-11 px-7 text-sm font-medium bg-csit-primary hover:bg-csit-primary-dark text-white rounded-full gap-2">
                {showDashboard ? "Go to Dashboard" : "Sign in to register for events"}
                <ArrowRight className="size-4" />
              </Button>
            </a>
          </div>
        </div>
      </section>

      {/* ─── Public Documents ─── */}
      <section id="documents" className="py-24 md:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-2 rounded-full border border-csit-border bg-csit-surface px-3 py-1 text-xs font-medium text-csit-primary mb-4">
              <FileText className="size-3.5" />
              Public Documents
            </div>
            <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-csit-text mb-4">
              Read the latest
              <br />
              society records.
            </h2>
            <p className="text-lg text-csit-text-muted max-w-lg mx-auto">
              Public documents and shared resources from the society — no sign-in required.
            </p>
          </div>

          <PublicDocuments />
        </div>
      </section>
    </div>
  );
}
