import { useEffect, useState } from 'react';

export function useAuthenticatedBlob(
  fetchBlob: () => Promise<Blob>,
  enabled: boolean,
  refreshKey?: string | number | null,
): string | null {
  const [url, setUrl] = useState<string | null>(null);

  useEffect(() => {
    if (!enabled) {
      setUrl(null);
      return;
    }

    let active = true;
    let objectUrl: string | null = null;

    void fetchBlob()
      .then((blob) => {
        if (!active) return;
        objectUrl = URL.createObjectURL(blob);
        setUrl(objectUrl);
      })
      .catch(() => {
        if (active) setUrl(null);
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [enabled, fetchBlob, refreshKey]);

  return url;
}
