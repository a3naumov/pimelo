import axios from 'axios';

export const apiClient = axios.create({
  baseURL: '/web',
  timeout: 15000,
  headers: { Accept: 'application/json' },
});
