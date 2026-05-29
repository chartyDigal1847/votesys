<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VoteSys | Student Election System</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/votesys-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/votesys.css') }}">
    <link rel="stylesheet" href="{{ asset('css/deoris-module-theme.css') }}?v={{ filemtime(public_path('css/deoris-module-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/votesys-entryease.css') }}?v={{ file_exists(public_path('css/votesys-entryease.css')) ? filemtime(public_path('css/votesys-entryease.css')) : 1 }}">
    <script>
        // ── Module configuration injected from Laravel config ─────────────
        // Same-origin only — API routes live on this module host, not the portal.
        window.VOTESYS_API_BASE = "";
        // The portal that embeds this module — used for SSO postMessage origin check
        window.PORTAL_ORIGIN = "{{ config('app.portal_url') }}";
        // How long to wait for the portal's SSO_TOKEN before showing an error
        window.SSO_TIMEOUT_MS = 8000;
        window.DEORIS_SSO_MODE = "module";
    </script>
</head>
<body>
    <!-- Root container — the ONLY static HTML element -->
    <div id="votesys-root" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
        <!-- Loader — visible by default, hidden after SSO + data load -->
        <div id="votesys-loader" style="display: flex; flex-direction: column; align-items: center; gap: 16px;">
            <div style="width: 48px; height: 48px; border-radius: 50%; border: 4px solid rgba(0,0,0,.1); border-top-color: #722F37; animation: spin .8s linear infinite;"></div>
            <p style="color: #722F37; font-weight: 700; font-size: 15px;">Loading VoteSys…</p>
            <!-- Error message — hidden by default, shown if SSO or API fails -->
            <p id="votesys-loader-error" style="color: #dc2626; font-size: 13px; display: none; max-width: 360px; text-align: center;"></p>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <!--
        module-bridge.js is served by the DEORIS portal when embedded.
        When running standalone (direct URL, no portal), we inject a minimal
        shim that fires module:ready with a guest/dev identity so the app
        boots without hanging.
    -->
    <script>
    (function () {
        var portalOrigin = window.PORTAL_ORIGIN || "";
        var isSameOrigin = portalOrigin && (
            portalOrigin === window.location.origin ||
            portalOrigin.replace(/\/$/, "") === window.location.origin
        );

        // If the portal is on a different origin (or not set), load the bridge
        // script but set a short timeout — if it doesn't fire module:ready
        // within SSO_TIMEOUT_MS, boot in standalone dev mode automatically.
        var bridgeLoaded = false;
        var standaloneTimer = null;

        function bootStandalone() {
            if (window.__VOTESYS_STANDALONE_BOOTED__) return;
            window.__VOTESYS_STANDALONE_BOOTED__ = true;
            console.warn("[votesys] Portal bridge not available — booting in standalone mode.");
            // Fire module:ready with a local admin identity so the app loads.
            window.dispatchEvent(new CustomEvent("module:ready", {
                detail: {
                    user: {
                        id:    "local-admin",
                        name:  "Local Admin",
                        email: "admin@localhost",
                        role:  "admin"
                    },
                    embedded: false,
                    token: null
                }
            }));
        }

        // Listen for the real bridge to fire — if it does, cancel the fallback.
        window.addEventListener("module:ready", function () {
            bridgeLoaded = true;
            if (standaloneTimer) clearTimeout(standaloneTimer);
        }, { once: true });

        window.addEventListener("module:error", function () {
            bridgeLoaded = true;
            if (standaloneTimer) clearTimeout(standaloneTimer);
        }, { once: true });

        // Try to load the real bridge script.
        var bridgeUrl = portalOrigin.replace(/\/$/, "") + "/module-bridge.js";
        var script = document.createElement("script");
        script.src = bridgeUrl;
        script.onerror = function () {
            // Bridge script failed to load (portal not running) — boot standalone.
            if (standaloneTimer) clearTimeout(standaloneTimer);
            bootStandalone();
        };
        document.head.appendChild(script);

        // Safety net: if bridge loads but never fires module:ready within timeout.
        var timeout = (window.SSO_TIMEOUT_MS || 8000);
        standaloneTimer = setTimeout(function () {
            if (!bridgeLoaded) bootStandalone();
        }, timeout);
    })();
    </script>
    <script src="{{ asset('js/votesys.js') }}?v={{ filemtime(public_path('js/votesys.js')) }}"></script>
</body>
</html>
