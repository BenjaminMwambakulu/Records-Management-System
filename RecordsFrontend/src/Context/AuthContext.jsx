import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { useLogto } from "@logto/react";
import { api, setTokenGetter } from "../APIClients/APIClient";

const AuthContext = createContext(null);

function decodeAccessTokenClaims(token) {
  try {
    const [, payload] = token.split(".");
    if (!payload) return null;
    const normalized = payload.replace(/-/g, "+").replace(/_/g, "/");
    return JSON.parse(atob(normalized));
  } catch (error) {
    return null;
  }
}

export function AuthProvider({ children }) {
  const {
    isAuthenticated,
    isLoading,
    signIn,
    signOut,
    getAccessToken,
    fetchUserInfo,
    error: logtoError,
  } = useLogto();

  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [sessionRestored, setSessionRestored] = useState(false);
  const fetchedUserRef = useRef(false);
  const isAuthenticatedRef = useRef(isAuthenticated);

  useLayoutEffect(() => {
    isAuthenticatedRef.current = isAuthenticated;

    setTokenGetter(async () => {
      if (!isAuthenticatedRef.current) return null;
      try {
        const token = await getAccessToken(
          import.meta.env.VITE_LOGTO_APP_RESOURCE || undefined
        );
        return token;
      } catch (err) {
        return null;
      }
    });
  }, [isAuthenticated, getAccessToken]);

  useEffect(() => {
    if (isLoading) return;

    if (!isAuthenticated) {
      setUser(null);
      setPermissions([]);
      fetchedUserRef.current = false;
      setSessionRestored(true);
      return;
    }

    if (fetchedUserRef.current) return;
    fetchedUserRef.current = true;

    fetchUserInfo()
      .then(async (info) => {
        let userData;
        try {
          const token = await getAccessToken(
            import.meta.env.VITE_LOGTO_APP_RESOURCE || undefined
          );
          const claims = token ? decodeAccessTokenClaims(token) : null;
          console.log("JWT claims:", claims);
          userData = { ...info, ...(claims || {}) };
        } catch (err) {
          userData = info;
        }

        try {
          const [permsRes, meRes] = await Promise.all([
            api.get('/v1/me/permissions'),
            api.get('/v1/me'),
          ]);
          const perms = permsRes?.data?.permissions ?? [];
          const profile = meRes?.data ?? {};
          setPermissions(perms);
          setUser({ ...userData, ...profile, permissions: perms });
        } catch (err) {
          setPermissions([]);
          setUser(userData);
        }
      })
      .catch((err) => {
        setUser(null);
      })
      .finally(() => setSessionRestored(true));
  }, [isLoading, isAuthenticated, fetchUserInfo, getAccessToken]);

  const getToken = useCallback(async () => {
    if (!isAuthenticated) return null;
    try {
      const token = await getAccessToken(
        import.meta.env.VITE_LOGTO_APP_RESOURCE || undefined
      );
      return token;
    } catch (err) {
      return null;
    }
  }, [isAuthenticated, getAccessToken]);

  const login = useCallback(
    async (redirectUri) => {
      if (!redirectUri) {
        throw new Error("Sign-in redirect URI is not configured (VITE_REDIRECT_URI).");
      }
      await signIn(redirectUri);
    },
    [signIn]
  );

  const logout = useCallback(
    async (postLogoutRedirectUri) => {
      await signOut(postLogoutRedirectUri);
      setUser(null);
    },
    [signOut]
  );

  const refreshUser = useCallback(async () => {
    if (!isAuthenticated) return;
    try {
      const info = await fetchUserInfo();
      let userData;
      try {
        const token = await getAccessToken(
          import.meta.env.VITE_LOGTO_APP_RESOURCE || undefined
        );
        const claims = token ? decodeAccessTokenClaims(token) : null;
        userData = { ...info, ...(claims || {}) };
      } catch (err) {
        userData = info;
      }

      try {
        const [permsRes, meRes] = await Promise.all([
          api.get('/v1/me/permissions'),
          api.get('/v1/me'),
        ]);
        const perms = permsRes?.data?.permissions ?? [];
        const profile = meRes?.data ?? {};
        setPermissions(perms);
        setUser({ ...userData, ...profile, permissions: perms });
      } catch (err) {
        setPermissions([]);
        setUser(userData);
      }
    } catch (err) {
      // silent
    }
  }, [isAuthenticated, fetchUserInfo, getAccessToken]);

  const value = useMemo(
    () => ({
      isAuthenticated,
      sessionRestored,
      user,
      permissions,
      login,
      logout,
      getToken,
      refreshUser,
      error: logtoError,
    }),
    [
      isAuthenticated,
      sessionRestored,
      user,
      permissions,
      login,
      logout,
      getToken,
      refreshUser,
      logtoError,
    ]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}