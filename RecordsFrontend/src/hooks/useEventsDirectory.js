import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiFetch } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useEventsDirectory() {
  const [events, setEvents] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [published, setPublished] = useState("");
  const [page, setPage] = useState(1);
  const cancelledRef = useRef(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
      setPage(1);
    }, SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [published]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (published === "true" || published === "false") {
      params.set("is_published", published);
    }

    api
      .get(`/v1/events?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const eventList = unwrapList(body);
        setEvents(eventList);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: eventList.length,
                per_page: eventList.length,
                from: eventList.length ? 1 : 0,
                to: eventList.length,
              }
            : body
        );
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, [debouncedSearch, published, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchEvent = useCallback(async (id) => {
    const response = await api.get(`/v1/events/${id}`);
    return response?.data ?? null;
  }, []);

  const fetchAttendances = useCallback(async (id) => {
    const response = await api.get(`/v1/events/${id}/attendances`);
    return unwrapList(response?.data);
  }, []);

  const uploadCover = useCallback(async (id, file) => {
    const formData = new FormData();
    formData.append("cover", file);
    const response = await apiFetch(`/v1/events/${id}/cover`, { method: "POST", body: formData });
    return response?.data ?? null;
  }, []);

  const removeCover = useCallback(async (id) => {
    const response = await api.delete(`/v1/events/${id}/cover`);
    return response?.data ?? null;
  }, []);

  const createEvent = useCallback(
    async (data) => {
      const response = await api.post("/v1/events", data);
      const event = response?.data ?? null;
      if (event && data.cover) {
        await uploadCover(event.id, data.cover);
      }
      await refetch();
      return event;
    },
    [refetch, uploadCover]
  );

  const updateEvent = useCallback(
    async (id, data) => {
      const payload = { ...data };
      delete payload.cover;
      const response = await api.put(`/v1/events/${id}`, payload);
      if (data.removeCover) {
        await removeCover(id);
      } else if (data.cover) {
        await uploadCover(id, data.cover);
      }
      const event = response?.data ?? null;
      await refetch();
      return event;
    },
    [refetch, removeCover, uploadCover]
  );

  const deleteEvent = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/events/${id}`);
      await refetch();
      return response;
    },
    [refetch]
  );

  const checkIn = useCallback(async (id, payload) => {
    const response = await api.post(`/v1/events/${id}/check-in`, payload);
    return response?.data ?? null;
  }, []);

  const cancelRegistrations = useCallback(
    async (id, userIds, reason) => {
      const response = await api.delete(`/v1/events/${id}/attendances`, {
        user_ids: userIds,
        reason: reason?.trim() || null,
      });
      await refetch();
      return response?.data ?? null;
    },
    [refetch]
  );

  return {
    events,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    published,
    setPublished,
    page,
    setPage,
    refetch,
    fetchEvent,
    fetchAttendances,
    createEvent,
    updateEvent,
    deleteEvent,
    checkIn,
    cancelRegistrations,
    uploadCover,
    removeCover,
  };
}