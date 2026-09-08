import { useCallback, useEffect, useRef, useState } from "react";
import { api, apiFetch } from "@/APIClients/APIClient";

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

function unwrapList(body) {
  if (Array.isArray(body)) return body;
  return Array.isArray(body?.data) ? body.data : [];
}

export default function useDocumentsDirectory() {
  const [documents, setDocuments] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [categoriesError, setCategoriesError] = useState(null);
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
  }, [categoryId, status]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (categoryId) params.set("category_id", categoryId);
    if (status) params.set("status", status);

    api
      .get(`/v1/documents?${params.toString()}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        const documentList = unwrapList(body);
        setDocuments(documentList);
        setPagination(
          Array.isArray(body)
            ? {
                current_page: 1,
                last_page: 1,
                total: documentList.length,
                per_page: documentList.length,
                from: documentList.length ? 1 : 0,
                to: documentList.length,
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
  }, [debouncedSearch, categoryId, status, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const fetchCategories = useCallback(() => {
    setCategoriesError(null);
    api
      .get("/v1/document-categories")
      .then((response) => {
        setCategories(unwrapList(response.data));
      })
      .catch((err) => setCategoriesError(err));
  }, []);

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  const createDocument = useCallback(
    async (data) => {
      const formData = new FormData();
      formData.append("title", data.title);
      formData.append("category_id", data.category_id ?? "");
      formData.append("status", data.status ?? "active");
      formData.append("is_public", data.is_public ? "1" : "0");
      formData.append("file", data.file);
      const response = await apiFetch("/v1/documents", { method: "POST", body: formData });
      await refetch();
      return response;
    },
    [refetch]
  );

  const updateDocument = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/documents/${id}`, data);
      await refetch();
      return response;
    },
    [refetch]
  );

  const setDocumentStatus = useCallback(
    async (id, nextStatus) => {
      const response = await api.put(`/v1/documents/${id}`, { status: nextStatus });
      await refetch();
      return response;
    },
    [refetch]
  );

  const deleteDocument = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/documents/${id}`);
      await refetch();
      return response;
    },
    [refetch]
  );

  const createCategory = useCallback(async (data) => {
    const response = await api.post("/v1/document-categories", data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const updateCategory = useCallback(async (id, data) => {
    const response = await api.put(`/v1/document-categories/${id}`, data);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  const deleteCategory = useCallback(async (id) => {
    const response = await api.delete(`/v1/document-categories/${id}`);
    await fetchCategories();
    return response;
  }, [fetchCategories]);

  return {
    documents,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    categoryId,
    setCategoryId,
    status,
    setStatus,
    page,
    setPage,
    categories,
    categoriesError,
    refetch,
    fetchCategories,
    createDocument,
    updateDocument,
    setDocumentStatus,
    deleteDocument,
    createCategory,
    updateCategory,
    deleteCategory,
  };
}