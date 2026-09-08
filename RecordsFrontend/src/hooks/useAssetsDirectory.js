import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useAssetsDirectory() {
  const [assets, setAssets] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [status, setStatus] = useState("");
  const [category, setCategory] = useState("");
  const [categories, setCategories] = useState([]);
  const [page, setPage] = useState(1);
  const [summary, setSummary] = useState(null);
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
  }, [status, category]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (status) params.set("status", status);
    if (category) params.set("category", category);

    api
      .get(`/v1/assets?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const assetList = unwrapList(body);
        setAssets(assetList);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: assetList.length,
                per_page: assetList.length,
                from: assetList.length ? 1 : 0,
                to: assetList.length,
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
  }, [debouncedSearch, status, category, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchCategories = useCallback(async () => {
    try {
      const response = await api.get("/v1/assets/categories");
      setCategories(unwrapList(response?.data));
    } catch {
      // Silently fail — categories are non-critical
    }
  }, []);

  const fetchSummary = useCallback(async () => {
    try {
      const response = await api.get("/v1/assets/summary");
      setSummary(response?.data ?? null);
    } catch {
      // Silently fail — summary is non-critical
    }
  }, []);

  useEffect(() => {
    fetchCategories();
    fetchSummary();
  }, [fetchCategories, fetchSummary]);

  const fetchAsset = useCallback(async (id) => {
    const response = await api.get(`/v1/assets/${id}`);
    return response?.data ?? null;
  }, []);

  const createAsset = useCallback(
    async (data) => {
      const response = await api.post("/v1/assets", data);
      const asset = response?.data ?? null;
      await refetch();
      await fetchSummary();
      return asset;
    },
    [refetch, fetchSummary]
  );

  const updateAsset = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/assets/${id}`, data);
      const asset = response?.data ?? null;
      await refetch();
      await fetchSummary();
      return asset;
    },
    [refetch, fetchSummary]
  );

  const deleteAsset = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/assets/${id}`);
      await refetch();
      await fetchSummary();
      return response;
    },
    [refetch, fetchSummary]
  );

  const checkoutAsset = useCallback(
    async (id, data) => {
      const response = await api.post(`/v1/assets/${id}/checkout`, data);
      const loan = response?.data ?? null;
      await refetch();
      await fetchSummary();
      return loan;
    },
    [refetch, fetchSummary]
  );

  const returnAsset = useCallback(
    async (id) => {
      const response = await api.post(`/v1/assets/${id}/return`);
      const loan = response?.data ?? null;
      await refetch();
      await fetchSummary();
      return loan;
    },
    [refetch, fetchSummary]
  );

  return {
    assets,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    status,
    setStatus,
    category,
    setCategory,
    categories,
    page,
    setPage,
    summary,
    refetch,
    fetchAsset,
    createAsset,
    updateAsset,
    deleteAsset,
    checkoutAsset,
    returnAsset,
  };
}
