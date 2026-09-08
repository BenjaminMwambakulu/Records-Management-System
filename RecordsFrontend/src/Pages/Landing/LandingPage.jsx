import { ArrowRight, CheckCircle, FileText, Package, Shield, Users, Clock, BarChart3, Zap, ChevronRight, CalendarDays } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/Context/AuthContext";
import { canAccessDashboard } from "@/lib/roles";
import recordsHero from "@/assets/images/records.webp";
import mustLogo from "@/assets/images/mustlogo.png";
import PublicEvents from "./PublicEvents";

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

const benefits = [
  "Eliminate scattered spreadsheets and lost files",
  "Audit-ready records for university compliance",
  "Real-time collaboration across executive roles",
  "Mobile-responsive — manage records anywhere",
  "Built for MUST CSIT Society's workflow",
  "Open-source and self-hostable",
];

const techStack = [
  { name: "Laravel 11", desc: "Backend" },
  { name: "React 18", desc: "Frontend" },
  { name: "Tailwind CSS", desc: "Styling" },
  { name: "Spatie", desc: "Permissions" },
  { name: "Logto", desc: "Auth" },
  { name: "Vite", desc: "Build" },
];

export default function LandingPage() {
  const { isAuthenticated, user } = useAuth();
  const showDashboard = canAccessDashboard(user);
  console.log("[LandingPage] isAuthenticated:", isAuthenticated, "user:", user, "showDashboard:", showDashboard);

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
            <a href="#benefits" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
              Benefits
            </a>
            <a href="#tech" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors">
              Tech
            </a>
          </div>
          <div className="flex items-center gap-4">
            {isAuthenticated ? (
              <>
                <a href="/login" className="text-xs font-medium text-csit-text-muted hover:text-csit-text transition-colors hidden sm:block">
                  Sign out
                </a>
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

      {/* ─── Benefits — Dark section ─── */}
      <section id="benefits" className="bg-csit-dark py-24 md:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
              <p className="text-sm font-medium text-csit-primary-light mb-3 tracking-wide uppercase">
                Why CSIT Records
              </p>
              <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-white mb-6 leading-tight">
                Purpose-built for
                <br />
                student organizations.
              </h2>
              <p className="text-base text-white/50 leading-relaxed max-w-md">
                Not adapted from corporate tools. Built from the ground up for the way student societies actually work at MUST.
              </p>
            </div>
            <div className="space-y-5">
              {benefits.map((benefit, i) => (
                <div key={i} className="flex items-start gap-4">
                  <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 mt-0.5">
                    <CheckCircle className="size-4 text-emerald-400" />
                  </div>
                  <p className="text-white/80 text-base leading-relaxed">{benefit}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ─── Tech Stack ─── */}
      <section id="tech" className="py-24 md:py-32">
        <div className="mx-auto max-w-7xl px-6">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-csit-text mb-4">
              Built on modern,
              <br />
              reliable technology.
            </h2>
            <p className="text-lg text-csit-text-muted max-w-lg mx-auto">
              A stack chosen for maintainability, security, and developer experience.
            </p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 max-w-4xl mx-auto">
            {techStack.map((tech, i) => (
              <div key={i} className="text-center group">
                <div className="flex size-16 mx-auto items-center justify-center rounded-2xl bg-csit-surface border border-csit-border/50 mb-3 group-hover:border-csit-primary/30 transition-colors">
                  <span className="font-bold text-lg text-csit-primary">{tech.name.charAt(0)}</span>
                </div>
                <h3 className="font-semibold text-sm text-csit-text">{tech.name}</h3>
                <p className="text-xs text-csit-text-muted">{tech.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── CTA — Dark gradient ─── */}
      <section className="py-24 md:py-32 bg-gradient-to-b from-csit-dark to-csit-primary-dark">
        <div className="mx-auto max-w-7xl px-6 text-center">
          <h2 className="text-4xl md:text-5xl font-bold tracking-tight text-white mb-5">
            Ready to organize
            <br />
            your society?
          </h2>
          <p className="text-lg text-white/60 mb-10 max-w-lg mx-auto">
            Join the MUST CSIT Society in modernizing your records management.
            Free to start, no credit card required.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href={showDashboard ? "/app" : "/login"}>
              <Button className="h-12 px-8 text-sm font-medium bg-white text-csit-primary hover:bg-white/90 rounded-full gap-2">
                {showDashboard ? "Go to Dashboard" : "Get started — free"}
                <ArrowRight className="size-4" />
              </Button>
            </a>
            <Button variant="outline" className="h-12 px-8 text-sm font-medium border-white/20 text-white hover:bg-white/10 rounded-full">
              Contact us
            </Button>
          </div>
        </div>
      </section>

      {/* ─── Footer ─── */}
      <footer className="bg-csit-dark border-t border-white/10">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
            <div className="col-span-2 md:col-span-1">
              <div className="flex items-center gap-2 mb-4">
                <img src={mustLogo} alt="MUST Logo" className="h-5 w-auto brightness-0 invert" />
                <span className="font-semibold text-sm text-white tracking-tight">CSIT Records</span>
              </div>
              <p className="text-sm text-white/40 max-w-xs leading-relaxed">
                A records management system built for MUST CSIT Society.
              </p>
            </div>
            <div>
              <h4 className="font-semibold text-sm text-white mb-4">Product</h4>
              <ul className="space-y-2.5 text-sm text-white/40">
                <li><a href="#features" className="hover:text-white transition-colors">Features</a></li>
                <li><a href="#events" className="hover:text-white transition-colors">Events</a></li>
                <li><a href="#benefits" className="hover:text-white transition-colors">Benefits</a></li>
                <li><a href="#tech" className="hover:text-white transition-colors">Tech Stack</a></li>
                <li><a href={showDashboard ? "/app" : "/login"} className="hover:text-white transition-colors">{showDashboard ? "Dashboard" : "Sign In"}</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-sm text-white mb-4">Resources</h4>
              <ul className="space-y-2.5 text-sm text-white/40">
                <li><a href="#" className="hover:text-white transition-colors">Documentation</a></li>
                <li><a href="#" className="hover:text-white transition-colors">Changelog</a></li>
                <li><a href="#" className="hover:text-white transition-colors">Support</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-sm text-white mb-4">Connect</h4>
              <ul className="space-y-2.5 text-sm text-white/40">
                <li><a href="https://github.com" target="_blank" rel="noopener noreferrer" className="hover:text-white transition-colors">GitHub</a></li>
                <li><a href="#" className="hover:text-white transition-colors">Privacy Policy</a></li>
                <li><a href="#" className="hover:text-white transition-colors">Terms of Service</a></li>
              </ul>
            </div>
          </div>
          <div className="pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4">
            <p className="text-xs text-white/30">&copy; 2025 MUST CSIT Society. All rights reserved.</p>
            <div className="flex items-center gap-1 text-xs text-white/30">
              Built with <Zap className="size-3 mx-1 text-csit-primary-light" /> by CSIT Society
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
