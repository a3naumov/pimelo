/// <reference types="vite/client" />
import './src/app/router-meta';

declare global {
  interface ViteTypeOptions {
    strictImportMetaEnv: true;
  }

  interface ImportMetaEnv {
    readonly VITE_BACKEND_URL: string;
  }
}
