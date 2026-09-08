import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

export const MEMBER_ROLES = ["superadmin", "admin", "member"];

export const MEMBER_ROLE_OPTIONS = [
  { value: "superadmin", label: "Superadmin" },
  { value: "admin", label: "Admin" },
  { value: "member", label: "Member" },
];

export const ACADEMIC_TRACK_OPTIONS = [
  { value: "BIT", label: "Business Information Technology (BIT)" },
  { value: "CSS", label: "Computer Systems and Security (CSS)" },
];

const DEFAULT_PER_PAGE = 15;
const SEARCH_DEBOUNCE_MS = 400;

export default function useMembersDirectory() {
  const [members, setMembers] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [role, setRole] = useState("");
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
  }, [role]);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    const params = new URLSearchParams({
      page: String(page),
      per_page: String(DEFAULT_PER_PAGE),
    });
    if (debouncedSearch.trim()) params.set("search", debouncedSearch.trim());
    if (role) params.set("role", role);

    api
      .get(`/v1/members?${params.toString()}`)
      .then((response) => {
        if (!cancelledRef.current) {
          const body = response.data;
          const memberList = Array.isArray(body)
            ? body
            : Array.isArray(body?.data)
              ? body.data
              : [];
          setMembers(memberList);
          setPagination(
            Array.isArray(body)
              ? {
                  current_page: 1,
                  last_page: 1,
                  total: memberList.length,
                  per_page: memberList.length,
                  from: memberList.length ? 1 : 0,
                  to: memberList.length,
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
  }, [debouncedSearch, role, page]);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  const createMember = useCallback(
    async (data) => {
      const response = await api.post("/v1/members", data);
      await refetch();
      return response;
    },
    [refetch]
  );

  const updateMember = useCallback(
    async (id, data) => {
      const response = await api.put(`/v1/members/${id}`, data);
      await refetch();
      return response;
    },
    [refetch]
  );

  const deleteMember = useCallback(
    async (id) => {
      const response = await api.delete(`/v1/members/${id}`);
      await refetch();
      return response;
    },
    [refetch]
  );

  return {
    members,
    pagination,
    isLoading,
    error,
    search,
    setSearch,
    role,
    setRole,
    page,
    setPage,
    refetch,
    createMember,
    updateMember,
    deleteMember,
  };
}