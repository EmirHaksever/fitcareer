export function sanitizePayload<T extends object>(payload: T): T {
  const result = { ...payload } as Record<string, unknown>;

  Object.keys(result).forEach((key) => {
    if (result[key] === '') {
      result[key] = null;
    }
  });

  return result as T;
}
