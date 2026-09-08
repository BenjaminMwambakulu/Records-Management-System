import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

export default function useRolesDirectory() {
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState({});
  const [currentRole, setCurrentRole] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const cancelledRef = useRef(false);

  const fetchRoles = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);
    api.get("/v1/roles")
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        setRoles(Array.isArray(body) ? body : body?.data ?? []);
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, []);

  const fetchPermissions = useCallback(() => {
    api.get("/v1/permissions")
      .then((response) => {
        const body = response.data;
        setPermissions(body ?? {});
      })
      .catch(() => {});
  }, []);

  const fetchRole = useCallback((id) => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);
    api.get(`/v1/roles/${id}`)
      .then((response) => {
        if (cancelledRef.current) return;
        const body = response.data;
        setCurrentRole(body);
      })
      .catch((err) => {
        if (!cancelledRef.current) setError(err);
      })
      .finally(() => {
        if (!cancelledRef.current) setIsLoading(false);
      });
  }, []);

  useEffect(() => {
    fetchRoles();
    fetchPermissions();
    return () => { cancelledRef.current = true; };
  }, [fetchRoles, fetchPermissions]);

  const createRole = useCallback(async (data) => {
    setIsSubmitting(true);
    try {
      const response = await api.post("/v1/roles", data);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles]);

  const updateRole = useCallback(async (id, data) => {
    setIsSubmitting(true);
    try {
      const response = await api.put(`/v1/roles/${id}`, data);
      await fetchRoles();
      if (currentRole?.id === id) await fetchRole(id);
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles, fetchRole, currentRole]);

  const deleteRole = useCallback(async (id) => {
    setIsSubmitting(true);
    try {
      const response = await api.delete(`/v1/roles/${id}`);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRoles]);

  const syncPermissions = useCallback(async (roleId, permissionIds) => {
    setIsSubmitting(true);
    try {
      const response = await api.put(`/v1/roles/${roleId}/permissions`, { permissions: permissionIds });
      if (currentRole?.id === roleId) await fetchRole(roleId);
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, currentRole]);

  const assignRoleToUser = useCallback(async (roleId, userId) => {
    setIsSubmitting(true);
    try {
      const response = await api.post(`/v1/roles/${roleId}/users`, { user_id: userId });
      if (currentRole?.id === roleId) await fetchRole(roleId);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, fetchRoles, currentRole]);

  const removeRoleFromUser = useCallback(async (roleId, userId) => {
    setIsSubmitting(true);
    try {
      const response = await api.delete(`/v1/roles/${roleId}/users/${userId}`);
      if (currentRole?.id === roleId) await fetchRole(roleId);
      await fetchRoles();
      return response;
    } finally {
      setIsSubmitting(false);
    }
  }, [fetchRole, fetchRoles, currentRole]);

  return {
    roles,
    permissions,
    currentRole,
    isLoading,
    isSubmitting,
    error,
    fetchRoles,
    fetchRole,
    fetchPermissions,
    createRole,
    updateRole,
    deleteRole,
    syncPermissions,
    assignRoleToUser,
    removeRoleFromUser,
  };
}
