import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

export default function useYearRepStudents() {
  const [students, setStudents] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [page, setPage] = useState(1);
  const [stats, setStats] = useState(null);
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
  }, []);

  const fetchStats = useCallback(async () => {
    try {
      const response = await api.get("/v1/year-rep/students/stats");
      setStats(response.data?.data ?? response.data);
    } catch {
      // Stats are non-critical; ignore errors
    }
  }, []);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());

    api
      .get(`/v1/year-rep/students?${params.toString()}`)
      .then((response) => {
        if (!cancelledRef.current) {
          const body = response.data;
          const studentList = Array.isArray(body)
            ? body
            : Array.isArray(body?.data)
              ? body.data
              : [];
          setStudents(studentList);
          setPagination(
            Array.isArray(body)
              ? {
                  current_page: 1,
                  last_page: 1,
                  total: studentList.length,
                  per_page: studentList.length,
                  from: studentList.length ? 1 : 0,
                  to: studentList.length,
                }
              : body
          );
        }
      })
      .catch((err) => {
        if (!cancelledRef.current) {
          setError(err);
        }
      })
      .finally(() => {
        if (!cancelledRef.current) {
          setIsLoading(false);
        }
      });
  }, [debouncedSearch, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const createStudent = useCallback(
    async (data) => {
      const response = await api.post("/v1/year-rep/students", data);
      await refetch();
      await fetchStats();
      return response;
    },
    [refetch, fetchStats]
  );

  const updateStudent = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/year-rep/students/${id}`, data);
      await refetch();
      return response;
    },
    [refetch]
  );

  const deleteStudent = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/year-rep/students/${id}`);
      await refetch();
      await fetchStats();
      return response;
    },
    [refetch, fetchStats]
  );

  return {
    students,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    page,
    setPage,
    refetch,
    createStudent,
    updateStudent,
    deleteStudent,
    stats,
  };
}
