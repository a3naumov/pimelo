import axios from 'axios';

export function getGatewayOrigin(value: string | undefined): string {
  const message =
    'VITE_GATEWAY_URL must be an HTTP(S) origin without credentials, a path, query, or fragment.';

  if (!value || !URL.canParse(value)) {
    throw new Error(message);
  }

  const url = new URL(value);

  if (
    !['http:', 'https:'].includes(url.protocol) ||
    url.username ||
    url.password ||
    url.pathname !== '/' ||
    url.search ||
    url.hash
  ) {
    throw new Error(message);
  }

  return url.origin;
}

export const apiClient = axios.create({
  baseURL: `${getGatewayOrigin(import.meta.env.VITE_GATEWAY_URL)}/pim/web`,
  timeout: 15000,
  headers: { Accept: 'application/json' },
});
