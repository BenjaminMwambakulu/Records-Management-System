import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useActivityLogs() {
  const [logs, setLogs] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [event, setEvent] = useState("");
  const [subjectType, setSubjectType] = useState("");
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
  }, [event, subjectType]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (event && event !== "all") params.set("event", event);
    if (subjectType && subjectType !== "all") params.set("subject_type", subjectType);

    api
      .get(`/v1/activity-logs?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const logList = unwrapList(body);
        setLogs(logList);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: logList.length,
                per_page: logList.length,
                from: logList.length ? 1 : 0,
                to: logList.length,
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
  }, [debouncedSearch, event, subjectType, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  return {
    logs,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    event,
    setEvent,
    subjectType,
    setSubjectType,
    page,
    setPage,
    refetch,
  };
}
