import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import App from "./App.jsx";

function registerServiceWorker() {
  if (
    !("serviceWorker" in navigator) ||
    !import.meta.env.PROD
  ) {
    return;
  }

  window.addEventListener("load", () => {
    navigator.serviceWorker
      .register("/sw.js")
      .catch((error) => {
        console.error(
          "ElectoFlow service worker registration failed.",
          error,
        );
      });
  });
}

registerServiceWorker();

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <App />
  </StrictMode>,
);

//The service worker registers only in the production build. This prevents development caching from interfering while you edit the project