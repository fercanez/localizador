/**
 * Vigilancia de sesión:
 * - Idle: 5 min sin mouse/teclado/touch/scroll → logout (?idle=1)
 * - Heartbeat cada 25s; si falla 2 veces seguidas o red + gracia ~12s → logout (?disconnect=1)
 * - 401 idle/ip/replaced → logout inmediato con mensaje correspondiente
 *
 * Política de desconexión:
 *   1) Fallo de red/timeout → un reintento tras 12s; si también falla → cerrar.
 *   2) Dos heartbeats fallidos consecutivos (cualquier causa no-OK) → cerrar.
 *   3) Respuesta 401/403 con reason conocido → cerrar inmediato (sin reintento).
 */
(function (window, document) {
  "use strict";

  if (window.__MXLI_SESSION_GUARD__) return;
  window.__MXLI_SESSION_GUARD__ = true;

  var IDLE_MS = 5 * 60 * 1000;
  var HEARTBEAT_MS = 25 * 1000;
  var RETRY_GRACE_MS = 12 * 1000;
  var FETCH_TIMEOUT_MS = 10000;

  var root = (function () {
    var scripts = document.getElementsByTagName("script");
    for (var i = scripts.length - 1; i >= 0; i--) {
      var src = scripts[i].src || "";
      var m = src.match(/^(.*\/)?js\/sessionGuard\.js/i);
      if (m) {
        var base = src.replace(/js\/sessionGuard\.js.*$/i, "");
        return base || "./";
      }
    }
    var path = window.location.pathname || "";
    if (path.indexOf("/admin/") !== -1) return "../";
    return "./";
  })();

  var activityDirty = false;
  var idleTimer = null;
  var heartbeatTimer = null;
  var consecutiveFails = 0;
  var loggingOut = false;
  var retryTimer = null;

  function loginUrl(query) {
    return root + "login.php" + (query ? "?" + query : "");
  }

  function logoutUrl() {
    return root + "logout.php";
  }

  function heartbeatUrl() {
    return root + "api/auth/heartbeat.php";
  }

  function forceLogout(query) {
    if (loggingOut) return;
    loggingOut = true;
    try {
      if (idleTimer) clearTimeout(idleTimer);
      if (heartbeatTimer) clearInterval(heartbeatTimer);
      if (retryTimer) clearTimeout(retryTimer);
    } catch (e) {}
    // Best-effort: limpiar token en servidor; luego ir a login con mensaje.
    var done = function () {
      window.location.href = loginUrl(query || "disconnect=1");
    };
    try {
      var ctrl = typeof AbortController !== "undefined" ? new AbortController() : null;
      var t = setTimeout(function () {
        try {
          if (ctrl) ctrl.abort();
        } catch (e2) {}
      }, 4000);
      fetch(root + "api/auth/logout.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
        signal: ctrl ? ctrl.signal : undefined,
      })
        .catch(function () {})
        .then(function () {
          clearTimeout(t);
          done();
        });
    } catch (e3) {
      window.location.href = logoutUrl();
    }
  }

  function resetIdleTimer() {
    activityDirty = true;
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(function () {
      forceLogout("idle=1");
    }, IDLE_MS);
  }

  function onActivity() {
    if (loggingOut) return;
    resetIdleTimer();
  }

  function bindActivity() {
    var opts = { capture: true, passive: true };
    ["mousedown", "mousemove", "keydown", "touchstart", "touchmove", "scroll", "wheel", "click"].forEach(
      function (evt) {
        document.addEventListener(evt, onActivity, opts);
      }
    );
    window.addEventListener("focus", onActivity);
  }

  function fetchWithTimeout(url, options, ms) {
    var ctrl = typeof AbortController !== "undefined" ? new AbortController() : null;
    var timer = setTimeout(function () {
      try {
        if (ctrl) ctrl.abort();
      } catch (e) {}
    }, ms || FETCH_TIMEOUT_MS);
    var opts = options || {};
    if (ctrl) opts.signal = ctrl.signal;
    return fetch(url, opts).finally(function () {
      clearTimeout(timer);
    });
  }

  function reasonQuery(data) {
    if (!data) return "disconnect=1";
    if (data.session_idle || data.reason === "session_idle") return "idle=1";
    if (data.session_replaced || data.reason === "session_replaced") return "replaced=1";
    if (data.session_ip || data.reason === "session_ip") return "disconnect=1";
    return "disconnect=1";
  }

  function handleHeartbeatFailure(isAuthReject, data) {
    if (isAuthReject) {
      forceLogout(reasonQuery(data));
      return;
    }
    consecutiveFails += 1;
    if (consecutiveFails >= 2) {
      forceLogout("disconnect=1");
      return;
    }
    // Un fallo de red: gracia corta y un reintento.
    if (retryTimer) clearTimeout(retryTimer);
    retryTimer = setTimeout(function () {
      runHeartbeat(true);
    }, RETRY_GRACE_MS);
  }

  function runHeartbeat(isRetry) {
    if (loggingOut) return;
    var hadActivity = activityDirty;
    activityDirty = false;

    fetchWithTimeout(
      heartbeatUrl(),
      {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ activity: !!hadActivity }),
      },
      FETCH_TIMEOUT_MS
    )
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data || {} };
        }).catch(function () {
          return { res: res, data: {} };
        });
      })
      .then(function (result) {
        if (loggingOut) return;
        if (result.res.status === 401 || result.res.status === 403) {
          handleHeartbeatFailure(true, result.data);
          return;
        }
        if (!result.res.ok || !result.data.ok) {
          handleHeartbeatFailure(false, result.data);
          return;
        }
        consecutiveFails = 0;
        if (typeof result.data.remaining_seconds === "number" && result.data.remaining_seconds <= 0) {
          forceLogout("idle=1");
        }
      })
      .catch(function () {
        if (loggingOut) return;
        // Fallo de red / timeout
        if (isRetry) {
          forceLogout("disconnect=1");
          return;
        }
        handleHeartbeatFailure(false, null);
      });
  }

  function start() {
    bindActivity();
    resetIdleTimer();
    runHeartbeat(false);
    heartbeatTimer = setInterval(function () {
      runHeartbeat(false);
    }, HEARTBEAT_MS);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})(window, document);
