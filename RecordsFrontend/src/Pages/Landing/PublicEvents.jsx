import { useEffect, useState } from "react";
import "date-utils";
import { CalendarDays, Loader2, MapPin, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { apiFetch } from "@/APIClients/APIClient";

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toFormat("DD MMM YYYY");
}

export default function PublicEvents() {
  const [events, setEvents] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setIsLoading(true);

    apiFetch("/v1/events?is_published=true&per_page=6")
      .then((body) => {
        if (cancelled) return;
        const list = Array.isArray(body) ? body : Array.isArray(body?.data) ? body.data : [];
        setEvents(list);
      })
      .catch((err) => {
        if (!cancelled) setError(err);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Loader2 size={28} className="animate-spin text-csit-primary" />
      </div>
    );
  }

  if (error || events.length === 0) return null;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {events.map((event) => (
        <div
          key={event.id}
          className="group rounded-2xl border border-csit-border/60 bg-white overflow-hidden hover:shadow-lg transition-shadow duration-300"
        >
          {event.cover_url ? (
            <div className="h-44 overflow-hidden">
              <img
                src={event.cover_url}
                alt={event.title}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            </div>
          ) : (
            <div className="h-44 flex items-center justify-center bg-csit-surface">
              <CalendarDays className="size-10 text-csit-text-muted/40" />
            </div>
          )}
          <div className="p-5">
            <h3 className="text-lg font-semibold text-csit-text mb-2 group-hover:text-csit-primary transition-colors">
              {event.title}
            </h3>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-csit-text-muted mb-4">
              <span className="inline-flex items-center gap-1.5">
                <CalendarDays className="size-3.5" />
                {formatDate(event.event_date)}
              </span>
              {event.location && (
                <span className="inline-flex items-center gap-1.5">
                  <MapPin className="size-3.5" />
                  {event.location}
                </span>
              )}
            </div>
            {event.description && (
              <p className="text-sm text-csit-text-muted leading-relaxed line-clamp-2 mb-4">
                {event.description}
              </p>
            )}
            <a href={`/events/${event.id}`}>
              <Button
                variant="ghost"
                size="sm"
                className="h-8 px-3 text-xs font-medium text-csit-primary hover:bg-csit-primary/5 rounded-full gap-1 group/btn"
              >
                View details
                <ArrowRight className="size-3 group-hover/btn:translate-x-0.5 transition-transform" />
              </Button>
            </a>
          </div>
        </div>
      ))}
    </div>
  );
}
