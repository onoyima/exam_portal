import axios from 'axios';

const BASE = '/exam_portal/lms/public';

const client = axios.create({
  baseURL: BASE,
  headers: { 'Content-Type': 'application/json' },
});

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('lms_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

let refreshing: Promise<boolean> | null = null;

client.interceptors.response.use(
  (res) => res.data?.data ?? res.data,
  async (err) => {
    const original = err.config;
    console.log('[api] response error:', err.response?.status, original.url, err.message);
    if (err.response?.status === 401 && !original._retry) {
      original._retry = true;
      try {
        if (!refreshing) {
          refreshing = (async () => {
            const refreshToken = localStorage.getItem('lms_refresh');
            console.log('[api] refresh: token found?', !!refreshToken);
            if (!refreshToken) return false;
            try {
              const { data } = await axios.post(`${BASE}/auth/refresh`, {
                refresh_token: refreshToken,
              });
              const payload = data.data ?? data;
              console.log('[api] refresh response:', payload);
              if (payload.token) {
                localStorage.setItem('lms_token', payload.token);
                localStorage.setItem('lms_refresh', payload.refresh_token);
                console.log('[api] tokens refreshed');
                return true;
              }
            } catch (e: any) {
              console.log('[api] refresh failed:', e?.response?.status, e?.response?.data, e?.message);
            }
            return false;
          })();
        }
        const ok = await refreshing;
        refreshing = null;
        if (ok) {
          original.headers.Authorization = `Bearer ${localStorage.getItem('lms_token')}`;
          return client(original);
        }
        console.log('[api] refresh returned false, clearing session');
      } finally {
        refreshing = null;
      }
      localStorage.clear();
      window.location.hash = '#/login';
    }
    console.log('[api] non-retriable error:', err.response?.data?.error?.message || err.message);
    const msg = err.response?.data?.error?.message || err.message || 'Request failed';
    return Promise.reject(new Error(msg));
  },
);

export const api = {
  get: <T = any>(path: string) => client.get<T>(path).then((r) => r as unknown as T),
  post: <T = any>(path: string, data?: any) => client.post<T>(path, data).then((r) => r as unknown as T),
  put: <T = any>(path: string, data?: any) => client.put<T>(path, data).then((r) => r as unknown as T),
  delete: <T = any>(path: string) => client.delete<T>(path).then((r) => r as unknown as T),
};

export default client;
