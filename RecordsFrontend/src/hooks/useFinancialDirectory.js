import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiFetch } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useFinancialDirectory() {
  const [records, setRecords] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [summary, setSummary] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [type, setType] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [page, setPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [categoriesError, setCategoriesError] = useState(null);
  const [isExporting, setIsExporting] = useState(false);
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
  }, [type, categoryId]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (type) params.set("type", type);
    if (categoryId) params.set("category_id", categoryId);

    api
      .get(`/v1/financial-records?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const list = unwrapList(body);
        setRecords(list);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: list.length,
                per_page: list.length,
                from: list.length ? 1 : 0,
                to: list.length,
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
  }, [debouncedSearch, type, categoryId, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchSummary = useCallback(() => {
    api
      .get("/v1/financial-records/summary")
      .then((response) => {
        if (!cancelledRef.current) setSummary(response?.data ?? null);
      })
      .catch(() => {
        if (!cancelledRef.current) setSummary(null);
      });
  }, []);

  const fetchCategories = useCallback(() => {
    setCategoriesError(null);
    api
      .get("/v1/financial-categories")
      .then((response) => {
        setCategories(unwrapList(response.data));
      })
      .catch((err) => setCategoriesError(err));
  }, []);

  useEffect(() => {
    fetchSummary();
    fetchCategories();
  }, [fetchSummary, fetchCategories]);

  const createRecord = useCallback(
    async (data) => {
      const response = await api.post("/v1/financial-records", data);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const updateRecord = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/financial-records/${id}`, data);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const deleteRecord = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/financial-records/${id}`);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const exportCsv = useCallback(async () => {
    setIsExporting(true);
    try {
      const params = new URLSearchParams();
      if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
      if (type) params.set("type", type);
      if (categoryId) params.set("category_id", categoryId);
      const qs = params.toString();
      const response = await apiFetch(
        `/v1/financial-records/export${qs ? `?${qs}` : ""}`,
        { raw: true }
      );
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "financial-records.csv";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } finally {
      setIsExporting(false);
    }
  }, [debouncedSearch, type, categoryId]);

  const createCategory = useCallback(async (data) => {
    const response = await api.post("/v1/financial-categories", data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const updateCategory = useCallback(async (id, data) => {
    const response = await api.put(`/v1/financial-categories/${id}`, data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const deleteCategory = useCallback(async (id) => {
    const response = await api.delete(`/v1/financial-categories/${id}`);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  return {
    records,
    pagination,
    summary,
    isLoading,
    error,
    search,
    setSearch,
    type,
    setType,
    categoryId,
    setCategoryId,
    page,
    setPage,
    categories,
    categoriesError,
    isExporting,
    refetch,
    fetchSummary,
    exportCsv,
    createRecord,
    updateRecord,
    deleteRecord,
    createCategory,
    updateCategory,
    deleteCategory,
  };
}
