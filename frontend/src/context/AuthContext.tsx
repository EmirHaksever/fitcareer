import { createContext, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { authApi, type LoginPayload, type RegisterPayload } from '@/api/auth';
import { clearStoredToken, getStoredToken, setStoredToken } from '@/api/client';
import type { User } from '@/types/api';

interface AuthContextValue {
  user: User | null;
  isAuthenticated: boolean;
  isBootstrapping: boolean;
  login: (payload: LoginPayload) => Promise<User>;
  register: (payload: RegisterPayload) => Promise<User>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<User | null>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isBootstrapping, setIsBootstrapping] = useState(true);

  const refreshUser = useCallback(async (): Promise<User | null> => {
    const token = getStoredToken();

    if (!token) {
      setUser(null);
      return null;
    }

    try {
      const currentUser = await authApi.me();
      setUser(currentUser);
      return currentUser;
    } catch {
      clearStoredToken();
      setUser(null);
      return null;
    }
  }, []);

  useEffect(() => {
    void refreshUser().finally(() => setIsBootstrapping(false));
  }, [refreshUser]);

  const login = useCallback(async (payload: LoginPayload): Promise<User> => {
    const result = await authApi.login(payload);
    setStoredToken(result.token);
    setUser(result.user);
    return result.user;
  }, []);

  const register = useCallback(async (payload: RegisterPayload): Promise<User> => {
    const result = await authApi.register(payload);
    setStoredToken(result.token);
    setUser(result.user);
    return result.user;
  }, []);

  const logout = useCallback(async (): Promise<void> => {
    try {
      await authApi.logout();
    } finally {
      clearStoredToken();
      setUser(null);
    }
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isAuthenticated: user !== null,
      isBootstrapping,
      login,
      register,
      logout,
      refreshUser,
    }),
    [user, isBootstrapping, login, register, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
