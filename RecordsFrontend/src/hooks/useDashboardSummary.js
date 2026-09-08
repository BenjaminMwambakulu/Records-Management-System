import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/APIClients/APIClient";

export default function useDashboardSummary() {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const cancelledRef = useRef(false);

  const refetch = useCallback(() => {
    cancelledRef.current = false;
    setError(null);
    setIsLoading(true);

    api
      .get("/v1/dashboard/summary")
      .then((response) => {
        if (!cancelledRef.current) {
          setData(response.data);
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
  }, []);

  useEffect(() => {
    refetch();
    return () => {
      cancelledRef.current = true;
    };
  }, [refetch]);

  return { data, isLoading, error, refetch };
}