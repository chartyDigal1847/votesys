/**
 * votesys.js — Main application
 *
 * Boot sequence:
 *   1. https://deoris.test/module-bridge.js runs first and dispatches
 *      "module:ready" on window after origin-checked SSO exchange
 *   2. This file listens for that event, injects the full HTML shell,
 *      wires event listeners, loads data, and renders.
 *   3. Loader is hidden only after data loads successfully.
 *   4. If SSO or API fails, error is shown in the loader.
 */

if (window.__VOTESYS_LOADED__) {
    console.warn("[votesys] Already loaded — skipping.");
} else {
    window.__VOTESYS_LOADED__ = true;
    console.log("[votesys] Script loaded.");

    function showInitError(message) {
        console.error("[votesys] Init error:", message);
        const el = document.getElementById("votesys-loader-error");
        if (el) { el.style.display = "block"; el.textContent = message; }
    }

    let bootStarted = false;

    async function startFromPortalDetail(detail = {}) {
        if (bootStarted) return;
        bootStarted = true;

        console.log("[votesys] module:ready received. Booting.");
        const { user, embedded, token } = detail;
        try {
            await bootApp(user, embedded, token || window.SSO_TOKEN || null);
        } catch (error) {
            bootStarted = false;
            showInitError(error?.message || "Failed to load. Check the console.");
        }
    }

    window.addEventListener("module:error", function (event) {
        showInitError("Authentication failed: " + ((event.detail && event.detail.error) || "unknown"));
    });

    window.addEventListener("module:ready", function (event) {
        startFromPortalDetail(event.detail || {});
    });

    if (window.__DEORIS_MODULE_READY_DETAIL__ || (window.PORTAL_USER && window.PORTAL_USER.id)) {
        startFromPortalDetail(window.__DEORIS_MODULE_READY_DETAIL__ || { user: window.PORTAL_USER, embedded: true });
    } else if (window.__DEORIS_MODULE_ERROR_DETAIL__) {
        showInitError("Authentication failed: " + (window.__DEORIS_MODULE_ERROR_DETAIL__.error || "Unknown error"));
    }

    async function bootApp(user, embedded, ssoToken) {
        const root = document.getElementById("votesys-root");

        // API must stay on this module origin (never the portal host).
        const configuredBase = (window.VOTESYS_API_BASE || "").replace(/\/$/, "");
        const API_BASE =
            !configuredBase || configuredBase === window.location.origin
                ? ""
                : (console.warn("[votesys] Ignoring cross-origin VOTESYS_API_BASE:", configuredBase), "");
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

        let ssoUser  = user || window.PORTAL_USER || null;
        if (!ssoUser?.id && ssoToken) {
            const ex = await fetch("/sso/exchange", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ token: ssoToken, embedded: !!embedded }),
            });
            const payload = await ex.json().catch(() => ({}));
            if (!ex.ok) {
                throw new Error(payload?.message || ("SSO exchange failed: " + ex.status));
            }
            ssoUser = payload?.user || ssoUser;
        }
        const ssoRole  = ssoUser?.role  || "student";
        const ssoEmail = (ssoUser?.email || "").toLowerCase();
        const ssoName  = ssoUser?.name  || "User";

        const state = {
            tab:         "vote",
            role:        ssoRole,
            userEmail:   ssoEmail,
            userName:    ssoName,
            userId:      ssoUser?.id || null,
            election:    null,
            positions:   [],
            results:     {},
            candidates:  [],
            activityLog: [],
            winners:     [],
            selections:  {},
            studentId:   ssoUser?.id || "",
            viewMode:    "grid",
            filterParty: "all",
            searchQuery: "",
            wsConnected: false,
        };

        // Build the shell now that state is initialized.
        root.style.display = "block";
        root.style.minHeight = "100vh";
        root.style.alignItems = "";
        root.style.justifyContent = "";
        root.innerHTML = buildAppShell();

        async function apiRequest(url, options = {}) {
            const res = await fetch(url, {
                credentials: "include",
                headers: {
                    "Content-Type":         "application/json",
                    "X-VoteSys-Role":       state.role,
                    "X-VoteSys-User-Id":    state.userId || "",
                    "X-VoteSys-User-Email": state.userEmail || "",
                    "X-VoteSys-User-Name":  state.userName || "",
                    "X-CSRF-TOKEN":         csrfToken,
                    "X-Requested-With":     "XMLHttpRequest",
                    ...(options.headers || {}),
                },
                ...options,
            });
            if (!res.ok) {
                const text = await res.text();
                let msg = text || "Request failed";
                try { msg = JSON.parse(text).message || msg; } catch (_) {}
                throw new Error(msg);
            }
            return res.status === 204 ? null : res.json();
        }

        async function loadData() {
            const payload = await apiRequest(
                `${API_BASE}/votesys/api/bootstrap?role=${encodeURIComponent(state.role)}`
            );
            state.election    = payload.election;
            state.positions   = payload.positions   || [];
            state.results     = payload.results     || {};
            state.candidates  = payload.candidates  || [];
            state.activityLog = payload.activityLog || [];
            state.permissions = payload.permissions || {};
            state.winners     = payload.winners     || [];
        }

        function canEdit()   { return ["admin", "election_officer"].includes(state.role); }
        function canDelete() { return state.role === "admin"; }
        function roleLabel() {
            return ({
                admin: "Administrator",
                election_officer: "Election Officer",
                student: "Student Voter",
                candidate: "Candidate",
            })[state.role] || state.role;
        }
        function userInitial() {
            return (state.userName || state.userEmail || "V").trim().charAt(0).toUpperCase();
        }
        function pageTitle() {
            return ({
                vote: "Cast Vote",
                results: "Results",
                candidates: "Candidates",
                approval: "Approvals",
                dashboard: "Dashboard",
                analytics: "Analytics",
                timeline: "Timeline",
                notifications: "Notifications",
                audit: "Audit Monitor",
            })[state.tab] || "Dashboard";
        }
        function pageSubtitle() {
            return ({
                vote: "Select one candidate per position and submit through the secured voting service.",
                results: "Live election totals grouped by position.",
                candidates: "Browse candidate profiles, parties, and election positions.",
                approval: "Review pending candidate applications and record approval decisions.",
                dashboard: "Operational overview for election management.",
                analytics: "Vote distribution and position-level performance.",
                timeline: "Election lifecycle from draft through archive.",
                notifications: "Election-related notices for your portal identity.",
                audit: "Tamper-resistant activity trail for election operations.",
            })[state.tab] || "VoteSys workspace";
        }
        function roleNotice() {
            return "";
        }
        function totalVotes() {
            return Object.values(state.results).reduce(
                (sum, group) => sum + (Array.isArray(group) ? group.reduce((a, r) => a + Number(r.votes || 0), 0) : 0),
                0
            );
        }
        function statusLabel(status) {
            return String(status || "draft").replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase());
        }

        function escHtml(str) {
            return String(str ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#39;");
        }

        function renderAll() {
            renderHeader();
            renderStats();
            renderTab();
        }

        function renderHeader() {
            document.querySelectorAll(".cc-tab[data-tab]").forEach(btn => {
                btn.classList.toggle("active", btn.dataset.tab === state.tab);
            });
            const current = document.getElementById("vs-current-section");
            if (current) current.textContent = pageTitle();
            const subtitle = document.getElementById("vs-current-subtitle");
            if (subtitle) subtitle.textContent = pageSubtitle();
            const notice = document.getElementById("vs-role-notice");
            if (notice) {
                notice.textContent = "";
                const banner = notice.closest(".privacy-notice");
                if (banner) banner.style.display = "none";
            }
            const role = document.getElementById("userRole");
            if (role) role.textContent = roleLabel();
        }

        function renderStats() {
            const wrap = document.getElementById("vs-stats-wrap");
            if (!wrap) return;
            const votes = totalVotes();
            const status = statusLabel(state.election?.status);
            const pendingCands = state.candidates.filter(c => c.status === "pending").length;
            const visible = state.role === "student"
                ? [
                    ["fa-check-to-slot", state.positions.length, "Open Positions"],
                    ["fa-users", state.candidates.length, "Candidates"],
                    ["fa-chart-line", votes, "Votes Counted"],
                    ["fa-calendar-check", status, "Election Status"],
                ]
                : [
                    ["fa-check-to-slot", votes, "Total Votes"],
                    ["fa-users", state.candidates.length, "Candidates"],
                    ["fa-hourglass-half", pendingCands, "Pending Review"],
                    ["fa-sitemap", state.positions.length, "Positions"],
                    ["fa-calendar-check", state.election ? 1 : 0, "Active Election"],
                ];
            wrap.innerHTML = `
            <div class="metrics-grid">
                ${visible.map(([icon, value, label]) => `
                    <div class="metric-card">
                        <div class="metric-icon"><i class="fa-solid ${icon}"></i></div>
                        <div class="metric-value">${escHtml(value)}</div>
                        <div class="metric-label">${escHtml(label)}</div>
                    </div>
                `).join("")}
            </div>`;
        }

        function renderTab() {
            const area = document.getElementById("vs-content");
            if (!area) return;
            if (state.tab === "vote")          area.innerHTML = buildVotePanel();
            else if (state.tab === "results")  area.innerHTML = buildResultsPanel();
            else if (state.tab === "candidates") area.innerHTML = buildCandidatesPanel();
            else if (state.tab === "approval") area.innerHTML = buildApprovalPanel();
            else if (state.tab === "dashboard") area.innerHTML = buildDashboardPanel();
            else if (state.tab === "analytics") area.innerHTML = buildAnalyticsPanel();
            else if (state.tab === "timeline") area.innerHTML = buildTimelinePanel();
            else if (state.tab === "notifications") area.innerHTML = buildNotificationsPanel();
            else if (state.tab === "audit")    area.innerHTML = buildAuditPanel();
            wireTabListeners();
        }

        /* ── VOTE PANEL ─────────────────────────────────────────────────── */
        function buildVotePanel() {
            if (!state.election) return `<div class="empty-state"><i class="fa-solid fa-box-open"></i><p>No active election found.</p></div>`;

            // Election is over — show winners instead of the ballot form.
            if (state.election.status === "completed") {
                const podiumCards = (state.winners || []).map(w => {
                    const pct = w.total_votes > 0 ? Math.round((w.votes / w.total_votes) * 100) : 0;
                    return `<div style="background:#fff;border:2px solid #b8960c;border-radius:16px;padding:24px 20px;text-align:center;min-width:160px;flex:1;box-shadow:0 4px 16px rgba(184,150,12,.15);">
                        <div style="font-size:.72rem;font-weight:700;color:#b8960c;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">${escHtml(w.position_name)}</div>
                        <img src="${escHtml(w.profile_photo_url)}" alt="${escHtml(w.candidate_name)}"
                            style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:center top;border:3px solid #b8960c;margin:0 auto 10px;display:block;">
                        <div style="font-size:1rem;font-weight:700;color:#1a1a1a;margin-bottom:4px;">${escHtml(w.candidate_name)}</div>
                        <div style="font-size:.8rem;color:#666;margin-bottom:8px;">${escHtml(w.party || "Independent")}</div>
                        <div style="background:#b8960c22;color:#b8960c;border-radius:999px;padding:3px 12px;font-size:.78rem;font-weight:700;display:inline-block;">
                            <i class="fa-solid fa-crown"></i> ${w.votes} votes (${pct}%)
                        </div>
                    </div>`;
                }).join("");
                return `<div class="main-card">
                    <div style="text-align:center;padding:16px 0 24px;">
                        <i class="fa-solid fa-flag-checkered" style="font-size:2.5rem;color:#1f7a45;margin-bottom:12px;display:block;"></i>
                        <h2 style="font-size:1.4rem;font-weight:700;color:#1a1a1a;margin-bottom:6px;">Election Has Ended</h2>
                        <p style="color:#666;font-size:.9rem;">Voting is now closed. The official results are below.</p>
                    </div>
                    ${podiumCards.length ? `<div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:24px;">${podiumCards}</div>` : ""}
                    <div style="text-align:center;">
                        <button onclick="document.querySelector('.cc-tab[data-tab=results]')?.click()"
                            style="background:var(--maroon,#722F37);color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;">
                            <i class="fa-solid fa-chart-line"></i> View Full Results
                        </button>
                    </div>
                </div>`;
            }
            const positionsHtml = state.positions.map(pos => {
                const candidatesHtml = pos.candidates.map(c => {
                    const selected = state.selections[pos.id] === c.id;
                    return `<div class="candidate-card ${selected ? "selected" : ""}" data-pos="${pos.id}" data-cand="${c.id}" role="radio" aria-checked="${selected}" tabindex="0">
                        <div class="candidate-avatar"><img src="${escHtml(c.profile_photo_url)}" alt="${escHtml(c.name)}" loading="lazy"></div>
                        <div class="candidate-name">${escHtml(c.name)}</div>
                        <div class="candidate-party">${escHtml(c.party || "Independent")}</div>
                        <div class="candidate-course"><i class="fa-solid fa-graduation-cap"></i> ${escHtml(c.course || "")}</div>
                    </div>`;
                }).join("");
                return `<div class="position-section">
                    <div class="position-header">
                        <span class="position-name">${escHtml(pos.name)}</span>
                        <span class="position-count">Select ${escHtml(pos.max_selections)}</span>
                    </div>
                    <div class="candidates-grid">${candidatesHtml}</div>
                </div>`;
            }).join("");
            return `<div class="privacy-notice">
                <i class="fa-solid fa-lock"></i>
                <span>Your ballot is submitted through the secured VoteSys service and checked against duplicate voting rules.</span>
            </div>
            <div class="main-card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-ballot-check"></i> ${escHtml(state.election.name)}</h3>
                    <span class="status-badge ongoing"><span class="dot"></span> ${escHtml(statusLabel(state.election.status))}</span>
                </div>
                <div class="form-group">
                    <label class="form-label" for="vs-student-id"><i class="fa-solid fa-id-card"></i> Student ID</label>
                    <input class="form-input" id="vs-student-id" type="text" placeholder="e.g. STU-2026-001" maxlength="32" value="${escHtml(state.studentId)}" readonly style="background: #f3f4f6; color: #4b5563; cursor: not-allowed; border-color: #d1d5db;">
                </div>
                ${positionsHtml}
                <div class="submit-section">
                    <button class="vote-btn" id="vs-submit-vote"><i class="fa-solid fa-check-to-slot"></i> CAST MY VOTE</button>
                    <p id="vs-vote-msg" style="margin-top:12px;font-size:.9rem;color:#1f7a45;display:none;"></p>
                    <p id="vs-vote-err" style="margin-top:12px;font-size:.9rem;color:#b91c1c;display:none;"></p>
                </div>
            </div>`;
        }

        /* ── RESULTS PANEL ──────────────────────────────────────────────── */
        function buildResultsPanel() {
            if (!state.election) return `<div class="empty-state"><i class="fa-solid fa-chart-bar"></i><p>No election data.</p></div>`;
            const isCompleted = state.election.status === "completed";
            const positionsHtml = state.positions.map(pos => {
                const posResults = state.results[pos.id] || [];
                const totalVotesPos = posResults.reduce((s, r) => s + r.votes, 0);
                const maxVotes   = Math.max(...posResults.map(r => r.votes), 1);
                const candidatesHtml = pos.candidates.map(c => {
                    const r = posResults.find(r => r.candidate_id === c.id);
                    const votes = r ? r.votes : 0;
                    const pct   = totalVotesPos > 0 ? Math.round((votes / totalVotesPos) * 100) : 0;
                    const isLeading = votes === maxVotes && votes > 0;
                    return `<div class="result-item">
                        <div class="result-candidate">
                            <div class="result-name">
                                <div class="cand-name-cell">
                                    <div class="result-avatar"><img src="${escHtml(c.profile_photo_url)}" alt="${escHtml(c.name)}" loading="lazy"></div>
                                    <div>
                                        <div class="result-name-text">${escHtml(c.name)} ${isLeading ? '<span class="leading-badge"><i class="fa-solid fa-crown"></i> ' + (isCompleted ? "Winner" : "Leading") + '</span>' : ""}</div>
                                        <div style="font-size:.8rem;color:#8e8e8e;margin-top:2px;">${escHtml(c.party || "")}</div>
                                    </div>
                                </div>
                            </div>
                            <div style="font-weight:700;color:#1a1a1a;">${votes} <span style="font-size:.8rem;color:#8e8e8e;font-weight:400;">votes (${pct}%)</span></div>
                        </div>
                        <div class="result-bar-wrap"><div class="result-bar" style="width:${pct}%;background:${isLeading ? "var(--gold)" : "var(--maroon)"};"></div></div>
                    </div>`;
                }).join("");
                return `<div class="result-position">
                    <div class="result-position-title"><i class="fa-solid fa-trophy"></i> ${escHtml(pos.name)} <span style="font-size:.8rem;color:#8e8e8e;font-weight:400;margin-left:auto;">${totalVotesPos} total votes</span></div>
                    ${candidatesHtml}
                </div>`;
            }).join("");

            // Winners podium — shown only when election is completed
            let winnersHtml = "";
            if (isCompleted && state.winners.length > 0) {
                const podiumCards = state.winners.map(w => {
                    const pct = w.total_votes > 0 ? Math.round((w.votes / w.total_votes) * 100) : 0;
                    return `<div style="background:#fff;border:2px solid #b8960c;border-radius:16px;padding:24px 20px;text-align:center;min-width:180px;flex:1;box-shadow:0 4px 16px rgba(184,150,12,.15);">
                        <div style="font-size:.72rem;font-weight:700;color:#b8960c;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">${escHtml(w.position_name)}</div>
                        <img src="${escHtml(w.profile_photo_url)}" alt="${escHtml(w.candidate_name)}"
                            style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:center top;border:3px solid #b8960c;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
                        <div style="font-size:1rem;font-weight:700;color:#1a1a1a;margin-bottom:4px;">${escHtml(w.candidate_name)}</div>
                        <div style="font-size:.8rem;color:#666;margin-bottom:8px;">${escHtml(w.party || "Independent")}</div>
                        <div style="background:#b8960c22;color:#b8960c;border-radius:999px;padding:3px 12px;font-size:.78rem;font-weight:700;display:inline-block;">
                            <i class="fa-solid fa-crown"></i> ${w.votes} votes (${pct}%)
                        </div>
                    </div>`;
                }).join("");
                winnersHtml = `<div style="margin-bottom:28px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                        <i class="fa-solid fa-trophy" style="color:#b8960c;font-size:1.3rem;"></i>
                        <h3 style="font-size:1.2rem;font-weight:700;color:#1a1a1a;margin:0;">Election Winners</h3>
                        <span style="background:#1f7a4522;color:#1f7a45;border-radius:999px;padding:2px 10px;font-size:.75rem;font-weight:700;">OFFICIAL RESULTS</span>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:16px;">${podiumCards}</div>
                </div>`;
            }

            return `<div class="main-card">
                <div class="results-header">
                    <div class="live-badge">${isCompleted
                        ? '<span style="background:#1f7a45;color:#fff;border-radius:999px;padding:3px 10px;font-size:.75rem;font-weight:700;"><i class="fa-solid fa-flag-checkered"></i> Final Results</span>'
                        : '<span class="live-dot"></span> Live Results'}</div>
                    <div class="results-title">${escHtml(state.election.name)}</div>
                    <div class="results-subtitle">${isCompleted ? "Official final results — election has ended." : "Results update in real-time as votes are cast."}</div>
                </div>
                ${winnersHtml}
                ${positionsHtml}
                <div style="text-align:center;margin-top:24px;">
                    <button class="btn btn-primary" id="vs-refresh-results">
                        <i class="fa-solid fa-rotate"></i> Refresh Results
                    </button>
                </div>
            </div>`;
        }

        /* ── CANDIDATES PANEL ───────────────────────────────────────────── */
        function buildCandidatesPanel() {
            const parties = [...new Set(state.candidates.map(c => c.party).filter(Boolean))];
            const pillsHtml = [`<button class="pill ${state.filterParty === "all" ? "active" : ""}" data-party="all">All</button>`,
                ...parties.map(p => `<button class="pill ${state.filterParty === p ? "active" : ""}" data-party="${escHtml(p)}">${escHtml(p)}</button>`)
            ].join("");
            let filtered = state.candidates;
            if (state.filterParty !== "all") filtered = filtered.filter(c => c.party === state.filterParty);
            if (state.searchQuery) {
                const q = state.searchQuery.toLowerCase();
                filtered = filtered.filter(c =>
                    c.name.toLowerCase().includes(q) ||
                    (c.party || "").toLowerCase().includes(q) ||
                    (c.course || "").toLowerCase().includes(q) ||
                    c.position.name.toLowerCase().includes(q)
                );
            }
            const addBtn = canEdit() ? `<a href="/votesys/candidates/create" class="btn-candidate-add"><i class="fa-solid fa-plus"></i> Add Candidate</a>` : "";
            let listHtml = "";
            if (state.viewMode === "table") {
                const rows = filtered.map(c => `<tr>
                    <td><div style="display:flex;align-items:center;gap:10px;"><img src="${escHtml(c.profile_photo_url)}" alt="${escHtml(c.name)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #e5e5e5;"><strong>${escHtml(c.name)}</strong></div></td>
                    <td class="td-muted">${escHtml(c.position.name)}</td>
                    <td class="td-muted">${escHtml(c.party || "—")}</td>
                    <td class="td-muted">${escHtml(c.course || "—")}</td>
                    <td><div class="table-actions">
                        ${canEdit() ? `<a href="/votesys/candidates/${c.id}/edit" class="btn-card-soft">Edit</a>` : ""}
                        ${canDelete() ? `<button class="btn-delete" data-delete-id="${c.id}" data-delete-name="${escHtml(c.name)}">Delete</button>` : ""}
                    </div></td>
                </tr>`).join("");
                listHtml = `<div class="candidates-table-card"><table class="candidates-data-table">
                    <thead><tr><th>Candidate</th><th>Position</th><th>Party</th><th>Course</th><th style="text-align:right;">Actions</th></tr></thead>
                    <tbody>${rows || '<tr><td colspan="5" style="text-align:center;color:#8e8e8e;padding:32px;">No candidates found.</td></tr>'}</tbody>
                </table></div>`;
            } else {
                const cards = filtered.map(c => `<div class="listing-card">
                    <div class="listing-candidate-avatar"><img src="${escHtml(c.profile_photo_url)}" alt="${escHtml(c.name)}" loading="lazy"></div>
                    <div class="listing-type-badge type-a">${escHtml(c.position.name)}</div>
                    <div class="listing-title">${escHtml(c.name)}</div>
                    <div class="listing-meta">${escHtml(c.party || "Independent")} · ${escHtml(c.course || "")}</div>
                    ${c.bio ? `<div class="listing-desc">${escHtml(c.bio.slice(0, 100))}${c.bio.length > 100 ? "…" : ""}</div>` : ""}
                    <div class="listing-actions">
                        ${canEdit() ? `<a href="/votesys/candidates/${c.id}/edit" class="btn-card-soft">Edit</a>` : ""}
                        ${canDelete() ? `<button class="btn-delete" data-delete-id="${c.id}" data-delete-name="${escHtml(c.name)}">Delete</button>` : ""}
                    </div>
                </div>`).join("");
                listHtml = `<div class="cc-listing-grid">${cards || '<p style="color:#8e8e8e;">No candidates found.</p>'}</div>`;
            }
            return `<div class="page-head">
                <div><h1>Candidates</h1>
                <p>${state.candidates.length} candidate${state.candidates.length !== 1 ? "s" : ""} registered across ${state.positions.length} position${state.positions.length !== 1 ? "s" : ""}.</p></div>
                <div class="page-tools">${addBtn}</div>
            </div>
            <div class="candidates-toolbar">
                <div class="filter-pills">${pillsHtml}</div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="vs-cand-search" placeholder="Search candidates…" value="${escHtml(state.searchQuery)}" style="width:220px;">
                    </div>
                    <div class="view-mode-toggle">
                        <button class="vm-btn ${state.viewMode === "grid" ? "active" : ""}" data-vm="grid"><i class="fa-solid fa-grid-2"></i></button>
                        <button class="vm-btn ${state.viewMode === "table" ? "active" : ""}" data-vm="table"><i class="fa-solid fa-list"></i></button>
                    </div>
                </div>
            </div>
            ${listHtml}`;
        }

        /* ── ELECTION DASHBOARD PANEL ───────────────────────────────────── */
        function buildDashboardPanel() {
            if (!state.election) return `<div class="empty-state"><i class="fa-solid fa-gauge-high"></i><p>No election found.</p></div>`;
            const e = state.election;
            const votes = totalVotes();
            const approvedCands = state.candidates.filter(c => c.status === "approved" || !c.status).length;
            const pendingCands  = state.candidates.filter(c => c.status === "pending").length;
            const statusColor   = { draft:"#8e8e8e", candidate_registration:"#3b82f6", candidate_review:"#f59e0b",
                                    approved:"#10b981", voting_open:"#722F37", voting_closed:"#6366f1",
                                    result_processing:"#f97316", completed:"#1f7a45", archived:"#9ca3af" };
            const sc = statusColor[e.status] || "#8e8e8e";
            const isCompleted = e.status === "completed";

            // End Election button — visible to admin/officer when voting is open/closed/processing
            const canEnd = canEdit() && ["voting_open", "voting_closed", "result_processing"].includes(e.status);
            const endBtnHtml = canEnd ? `
                <button id="vs-end-election-btn"
                    style="background:linear-gradient(135deg,#7f1d1d,#dc2626);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 2px 8px rgba(220,38,38,.3);">
                    <i class="fa-solid fa-flag-checkered"></i> End Election &amp; Release Results
                </button>
                <p id="vs-end-election-msg" style="margin-top:10px;font-size:.85rem;display:none;"></p>
            ` : "";

            // Winners summary card — shown when election is completed
            let winnersSummaryHtml = "";
            if (isCompleted && state.winners && state.winners.length > 0) {
                const rows = state.winners.map(w => `
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0f0;">
                        <img src="${escHtml(w.profile_photo_url)}" alt="${escHtml(w.candidate_name)}"
                            style="width:44px;height:44px;border-radius:50%;object-fit:cover;object-position:center top;border:2px solid #b8960c;flex-shrink:0;">
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:.9rem;">${escHtml(w.candidate_name)}</div>
                            <div style="font-size:.78rem;color:#666;">${escHtml(w.position_name)} &middot; ${escHtml(w.party || "Independent")}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:#b8960c;font-size:.9rem;"><i class="fa-solid fa-crown"></i> ${w.votes} votes</div>
                            <div style="font-size:.75rem;color:#999;">${w.total_votes > 0 ? Math.round((w.votes/w.total_votes)*100) : 0}%</div>
                        </div>
                    </div>`).join("");
                winnersSummaryHtml = `<div class="main-card" style="border:2px solid #b8960c44;">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-trophy" style="color:#b8960c;"></i> Winners</h3>
                        <button onclick="document.querySelector('.cc-tab[data-tab=results]')?.click()"
                            style="background:var(--maroon,#722F37);color:#fff;border:none;padding:5px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;">
                            View Full Results
                        </button>
                    </div>
                    ${rows}
                </div>`;
            }

            return `<div>
                <div class="dash-header">
                    <div>
                        <h2 class="dash-title">${escHtml(e.name)}</h2>
                        <p class="dash-sub">Election operations and monitoring overview.</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        ${endBtnHtml}
                    </div>
                </div>
                <div style="display:inline-flex;align-items:center;gap:6px;background:${sc}22;color:${sc};border:1px solid ${sc}44;border-radius:999px;padding:5px 12px;font-size:.78rem;font-weight:900;margin-bottom:18px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:${sc};display:inline-block;"></span>
                    ${escHtml(statusLabel(e.status))}
                </div>
                <div class="metrics-grid" style="margin-bottom:22px;">
                    <div class="metric-card"><div class="metric-icon"><i class="fa-solid fa-check-to-slot"></i></div><div class="metric-value">${votes}</div><div class="metric-label">Total Votes Cast</div></div>
                    <div class="metric-card"><div class="metric-icon"><i class="fa-solid fa-user-check"></i></div><div class="metric-value">${approvedCands}</div><div class="metric-label">Approved Candidates</div></div>
                    <div class="metric-card"><div class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="metric-value">${pendingCands}</div><div class="metric-label">Pending Approval</div></div>
                    <div class="metric-card"><div class="metric-icon"><i class="fa-solid fa-sitemap"></i></div><div class="metric-value">${state.positions.length}</div><div class="metric-label">Positions</div></div>
                </div>
                <div class="dashboard-grid">
                <div class="main-card">
                    <div class="card-header"><h3><i class="fa-solid fa-circle-info"></i> Election Details</h3></div>
                    <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                        <tr><td style="padding:8px 0;color:#666;width:180px;">Status</td><td style="font-weight:600;">${escHtml(statusLabel(e.status))}</td></tr>
                        <tr><td style="padding:8px 0;color:#666;">Voting Opens</td><td style="font-weight:600;">${e.voting_starts_at ? new Date(e.voting_starts_at).toLocaleString() : "—"}</td></tr>
                        <tr><td style="padding:8px 0;color:#666;">Voting Closes</td><td style="font-weight:600;">${e.voting_ends_at ? new Date(e.voting_ends_at).toLocaleString() : "—"}</td></tr>
                        <tr><td style="padding:8px 0;color:#666;">Results Released</td><td style="font-weight:600;">${e.results_released_at ? new Date(e.results_released_at).toLocaleString() : "Not yet released"}</td></tr>
                    </table>
                </div>
                ${winnersSummaryHtml || `<div class="main-card">
                    <div class="card-header"><h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h3></div>
                    <div>${state.activityLog.slice(0,10).map(l => `
                        <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0;">
                            <span style="width:8px;height:8px;border-radius:50%;background:${l.type === "green" ? "#1f7a45" : l.type === "red" ? "#dc2626" : l.type === "gold" ? "#b45309" : "#3b82f6"};margin-top:5px;flex-shrink:0;"></span>
                            <div><div style="font-size:.85rem;">${escHtml(l.message)}</div><div style="font-size:.75rem;color:#999;">${l.at ? new Date(l.at).toLocaleString() : ""}</div></div>
                        </div>`).join("") || '<p style="color:#999;font-size:.9rem;">No activity yet.</p>'}
                    </div>
                </div>`}
                </div>
            </div>`;
        }

        /* ── CANDIDATE APPROVAL PANEL ────────────────────────────────────── */
        function buildApprovalPanel() {
            const pending = state.candidates.filter(c => c.status === "pending");
            if (!pending.length) return `<div class="empty-state"><i class="fa-solid fa-user-check"></i><p>No candidates pending approval.</p></div>`;
            const rows = pending.map(c => `<tr>
                <td><div style="display:flex;align-items:center;gap:10px;">
                    <img src="${escHtml(c.profile_photo_url)}" alt="${escHtml(c.name)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                    <div><strong>${escHtml(c.name)}</strong><div style="font-size:.78rem;color:#666;">${escHtml(c.course || "")}</div></div>
                </div></td>
                <td class="td-muted">${escHtml(c.position?.name || "—")}</td>
                <td class="td-muted">${escHtml(c.party || "Independent")}</td>
                <td><div class="table-actions">
                    <button class="btn-approve" data-approve-id="${c.id}" data-approve-name="${escHtml(c.name)}" style="background:#1f7a45;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:.82rem;font-weight:600;">
                        <i class="fa-solid fa-check"></i> Approve
                    </button>
                    <button class="btn-reject" data-reject-id="${c.id}" data-reject-name="${escHtml(c.name)}" style="background:#dc2626;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:.82rem;font-weight:600;margin-left:6px;">
                        <i class="fa-solid fa-xmark"></i> Reject
                    </button>
                </div></td>
            </tr>`).join("");
            return `<div>
                <div class="privacy-notice">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Approval actions are restricted to admins and election officers and are recorded in the audit activity log.</span>
                </div>
                <div class="page-head"><div><h1>Candidate Approval</h1><p>${pending.length} candidate${pending.length !== 1 ? "s" : ""} awaiting review.</p></div></div>
                <div class="candidates-table-card">
                    <table class="candidates-data-table">
                        <thead><tr><th>Candidate</th><th>Position</th><th>Party</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div id="vs-approval-msg" style="margin-top:14px;font-size:.9rem;display:none;"></div>
            </div>`;
        }

        /* ── ANALYTICS PANEL ─────────────────────────────────────────────── */
        function buildAnalyticsPanel() {
            const totalVotes = Object.values(state.results).reduce(
                (s, g) => s + (Array.isArray(g) ? g.reduce((a, r) => a + r.votes, 0) : 0), 0
            );
            const positionBreakdown = state.positions.map(pos => {
                const posResults = state.results[pos.id] || [];
                const posTotal   = posResults.reduce((s, r) => s + r.votes, 0);
                const leader     = posResults.reduce((a, b) => (b.votes > (a?.votes || 0) ? b : a), null);
                const leaderName = leader ? (state.candidates.find(c => c.id === leader.candidate_id)?.name || "—") : "—";
                return `<div class="main-card" style="margin-bottom:16px;">
                    <div class="card-header"><h3><i class="fa-solid fa-trophy"></i> ${escHtml(pos.name)}</h3>
                        <span style="font-size:.85rem;color:#666;">${posTotal} votes cast</span>
                    </div>
                    ${posResults.sort((a,b) => b.votes - a.votes).map(r => {
                        const cand = state.candidates.find(c => c.id === r.candidate_id);
                        const pct  = posTotal > 0 ? Math.round((r.votes / posTotal) * 100) : 0;
                        return `<div style="margin-bottom:10px;">
                            <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:4px;">
                                <span style="font-weight:600;">${escHtml(cand?.name || "Unknown")}</span>
                                <span>${r.votes} votes (${pct}%)</span>
                            </div>
                            <div style="background:#f0f0f0;border-radius:4px;height:10px;">
                                <div style="background:var(--maroon,#722F37);height:10px;border-radius:4px;width:${pct}%;transition:width .4s;"></div>
                            </div>
                        </div>`;
                    }).join("")}
                    <div style="font-size:.8rem;color:#666;margin-top:8px;">Current leader: <strong>${escHtml(leaderName)}</strong></div>
                </div>`;
            }).join("");
            return `<div style="padding:24px 32px;">
                <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:4px;"><i class="fa-solid fa-chart-pie"></i> Election Analytics</h2>
                <p style="color:#666;margin-bottom:24px;">Live participation and vote distribution across all positions.</p>
                <div class="stats-grid" style="margin-bottom:28px;">
                    <div class="stat-card"><div class="stat-icon gold"><i class="fa-solid fa-check-to-slot"></i></div><div class="stat-value">${totalVotes}</div><div class="stat-label">Total Votes</div></div>
                    <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-sitemap"></i></div><div class="stat-value">${state.positions.length}</div><div class="stat-label">Positions</div></div>
                    <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div><div class="stat-value">${state.candidates.length}</div><div class="stat-label">Candidates</div></div>
                    <div class="stat-card"><div class="stat-icon burgundy"><i class="fa-solid fa-percent"></i></div>
                        <div class="stat-value">${state.positions.length > 0 ? Math.round(totalVotes / Math.max(state.positions.length, 1)) : 0}</div>
                        <div class="stat-label">Avg Votes / Position</div>
                    </div>
                </div>
                ${positionBreakdown || '<div class="empty-state"><i class="fa-solid fa-chart-pie"></i><p>No vote data yet.</p></div>'}
            </div>`;
        }

        /* ── ELECTION TIMELINE PANEL ─────────────────────────────────────── */
        function buildTimelinePanel() {
            const statuses = [
                { key: "draft",                  label: "Draft",                 icon: "fa-file-pen" },
                { key: "candidate_registration", label: "Candidate Registration",icon: "fa-user-plus" },
                { key: "candidate_review",       label: "Candidate Review",      icon: "fa-magnifying-glass" },
                { key: "approved",               label: "Approved",              icon: "fa-circle-check" },
                { key: "voting_open",            label: "Voting Open",           icon: "fa-check-to-slot" },
                { key: "voting_closed",          label: "Voting Closed",         icon: "fa-lock" },
                { key: "result_processing",      label: "Result Processing",     icon: "fa-gears" },
                { key: "completed",              label: "Completed",             icon: "fa-flag-checkered" },
                { key: "archived",               label: "Archived",              icon: "fa-box-archive" },
            ];
            const currentStatus = state.election?.status || "draft";
            const currentIdx    = statuses.findIndex(s => s.key === currentStatus);
            const stepsHtml = statuses.map((s, i) => {
                const done    = i < currentIdx;
                const current = i === currentIdx;
                const color   = done ? "#1f7a45" : current ? "#722F37" : "#d1d5db";
                const textCol = done || current ? "#1a1a1a" : "#9ca3af";
                return `<div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:0;">
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div style="width:40px;height:40px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;">
                            <i class="fa-solid ${s.icon}"></i>
                        </div>
                        ${i < statuses.length - 1 ? `<div style="width:2px;height:32px;background:${done ? "#1f7a45" : "#e5e7eb"};margin-top:2px;"></div>` : ""}
                    </div>
                    <div style="padding-top:8px;">
                        <div style="font-weight:${current ? "700" : "500"};color:${textCol};font-size:.9rem;">${s.label}
                            ${current ? '<span style="background:#722F37;color:#fff;font-size:.7rem;padding:2px 8px;border-radius:10px;margin-left:8px;font-weight:700;">CURRENT</span>' : ""}
                            ${done ? '<span style="color:#1f7a45;font-size:.8rem;margin-left:6px;"><i class="fa-solid fa-check"></i></span>' : ""}
                        </div>
                    </div>
                </div>`;
            }).join("");
            return `<div style="padding:24px 32px;">
                <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:4px;"><i class="fa-solid fa-timeline"></i> Election Timeline</h2>
                <p style="color:#666;margin-bottom:28px;">Track the full lifecycle of the election from draft to archive.</p>
                <div class="main-card" style="max-width:520px;">
                    ${state.election ? stepsHtml : '<p style="color:#999;">No election found.</p>'}
                </div>
            </div>`;
        }

        /* ── NOTIFICATIONS PANEL ─────────────────────────────────────────── */
        function buildNotificationsPanel() {
            return `<div style="padding:24px 32px;" id="vs-notif-wrap">
                <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:4px;"><i class="fa-solid fa-bell"></i> Notifications</h2>
                <p style="color:#666;margin-bottom:20px;">Your election-related notifications.</p>
                <div id="vs-notif-list"><div style="text-align:center;padding:40px;color:#999;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div></div>
            </div>`;
        }

        /* ── AUDIT MONITORING PANEL ──────────────────────────────────────── */
        function buildAuditPanel() {
            const rows = state.activityLog.map(l => `<tr>
                <td style="font-size:.82rem;">${l.at ? new Date(l.at).toLocaleString() : "—"}</td>
                <td><span style="display:inline-flex;align-items:center;gap:5px;font-size:.82rem;">
                    <span style="width:8px;height:8px;border-radius:50%;background:${l.type === "green" ? "#1f7a45" : l.type === "red" ? "#dc2626" : l.type === "gold" ? "#b45309" : "#3b82f6"};"></span>
                    ${escHtml(l.message)}
                </span></td>
            </tr>`).join("");
            return `<div style="padding:24px 32px;">
                <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:4px;"><i class="fa-solid fa-shield-halved"></i> Audit Monitor</h2>
                <p style="color:#666;margin-bottom:20px;">Complete tamper-resistant log of all election activities.</p>
                <div class="candidates-table-card">
                    <table class="candidates-data-table">
                        <thead><tr><th style="width:180px;">Timestamp</th><th>Activity</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="2" style="text-align:center;color:#999;padding:32px;">No activity logged yet.</td></tr>'}</tbody>
                    </table>
                </div>
            </div>`;
        }

        /* ── WIRE LISTENERS ─────────────────────────────────────────────── */
        function wireTabListeners() {
            document.querySelectorAll(".candidate-card").forEach(card => {
                card.addEventListener("click", () => {
                    const posId  = parseInt(card.dataset.pos);
                    const candId = parseInt(card.dataset.cand);
                    state.selections[posId] = candId;
                    document.querySelectorAll(`.candidate-card[data-pos="${posId}"]`).forEach(c => {
                        const isSelected = parseInt(c.dataset.cand) === candId;
                        c.classList.toggle("selected", isSelected);
                        c.setAttribute("aria-checked", isSelected);
                    });
                });
                card.addEventListener("keydown", e => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); card.click(); } });
            });

            const sidInput = document.getElementById("vs-student-id");
            if (sidInput) sidInput.addEventListener("input", () => { state.studentId = sidInput.value; });

            const submitBtn = document.getElementById("vs-submit-vote");
            if (submitBtn) {
                submitBtn.addEventListener("click", async () => {
                    const msg = document.getElementById("vs-vote-msg");
                    const err = document.getElementById("vs-vote-err");
                    msg.style.display = "none"; err.style.display = "none";
                    const sid = document.getElementById("vs-student-id")?.value?.trim();
                    if (!sid || sid.length < 1) { err.textContent = "Please enter a valid Student ID."; err.style.display = "block"; return; }
                    if (!state.positions.every(p => state.selections[p.id])) { err.textContent = "Please select a candidate for every position."; err.style.display = "block"; return; }
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                    try {
                        await apiRequest(`${API_BASE}/api/v1/vote`, {
                            method: "POST",
                            body: JSON.stringify({ student_id: sid, election_id: state.election.id, selections: state.selections }),
                        });
                        msg.textContent = "✓ Your vote has been recorded!"; msg.style.display = "block";
                        state.selections = {}; state.studentId = ssoUser?.id || "";
                        await loadData(); renderAll();
                    } catch (e) {
                        err.textContent = e.message; err.style.display = "block";
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-check-to-slot"></i> CAST MY VOTE';
                    }
                });
            }

            const refreshBtn = document.getElementById("vs-refresh-results");
            if (refreshBtn) {
                refreshBtn.addEventListener("click", async () => {
                    refreshBtn.disabled = true; refreshBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Refreshing…';
                    await loadData(); renderAll();
                });
            }

            // ── End Election button ───────────────────────────────────────
            const endBtn = document.getElementById("vs-end-election-btn");
            if (endBtn) {
                endBtn.addEventListener("click", async () => {
                    if (!confirm("End this election and release the official results? This cannot be undone.")) return;
                    const msgEl = document.getElementById("vs-end-election-msg");
                    endBtn.disabled = true;
                    endBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ending election…';
                    try {
                        const res = await apiRequest(`${API_BASE}/votesys/api/election/end`, { method: "POST" });
                        if (msgEl) { msgEl.style.color = "#1f7a45"; msgEl.textContent = res?.message || "Election ended. Results released."; msgEl.style.display = "block"; }
                        await loadData();
                        renderAll();
                        // Switch to results tab to show winners
                        state.tab = "results";
                        renderAll();
                    } catch (e) {
                        endBtn.disabled = false;
                        endBtn.innerHTML = '<i class="fa-solid fa-flag-checkered"></i> End Election &amp; Release Results';
                        if (msgEl) { msgEl.style.color = "#dc2626"; msgEl.textContent = e.message; msgEl.style.display = "block"; }
                    }
                });
            }

            document.querySelectorAll("[data-delete-id]").forEach(btn => {
                btn.addEventListener("click", async () => {
                    if (!confirm(`Delete candidate "${btn.dataset.deleteName}"? This cannot be undone.`)) return;
                    try { await apiRequest(`${API_BASE}/api/v1/candidates/${btn.dataset.deleteId}`, { method: "DELETE" }); await loadData(); renderAll(); }
                    catch (e) { alert("Delete failed: " + e.message); }
                });
            });

            document.querySelectorAll(".pill[data-party]").forEach(pill => {
                pill.addEventListener("click", () => { state.filterParty = pill.dataset.party; renderTab(); });
            });

            document.querySelectorAll(".vm-btn[data-vm]").forEach(btn => {
                btn.addEventListener("click", () => { state.viewMode = btn.dataset.vm; renderTab(); });
            });

            const searchInput = document.getElementById("vs-cand-search");
            if (searchInput) searchInput.addEventListener("input", () => { state.searchQuery = searchInput.value; renderTab(); });

            // ── Candidate approval / rejection ────────────────────────────
            document.querySelectorAll(".btn-approve[data-approve-id]").forEach(btn => {
                btn.addEventListener("click", async () => {
                    if (!confirm(`Approve candidate "${btn.dataset.approveName}"?`)) return;
                    btn.disabled = true;
                    try {
                        await apiRequest(`${API_BASE}/api/v1/candidates/${btn.dataset.approveId}/approve`, { method: "POST" });
                        await loadData(); renderAll();
                    } catch (e) {
                        const msg = document.getElementById("vs-approval-msg");
                        if (msg) { msg.style.display = "block"; msg.style.color = "#dc2626"; msg.textContent = e.message; }
                        btn.disabled = false;
                    }
                });
            });

            document.querySelectorAll(".btn-reject[data-reject-id]").forEach(btn => {
                btn.addEventListener("click", async () => {
                    const reason = prompt(`Rejection reason for "${btn.dataset.rejectName}":`);
                    if (reason === null) return;
                    if (!reason.trim()) { alert("A rejection reason is required."); return; }
                    btn.disabled = true;
                    try {
                        await apiRequest(`${API_BASE}/api/v1/candidates/${btn.dataset.rejectId}/reject`, {
                            method: "POST",
                            body: JSON.stringify({ reason }),
                        });
                        await loadData(); renderAll();
                    } catch (e) {
                        const msg = document.getElementById("vs-approval-msg");
                        if (msg) { msg.style.display = "block"; msg.style.color = "#dc2626"; msg.textContent = e.message; }
                        btn.disabled = false;
                    }
                });
            });

            // ── Notifications lazy-load ───────────────────────────────────
            if (state.tab === "notifications") {
                const listEl = document.getElementById("vs-notif-list");
                if (listEl) {
                    apiRequest(`${API_BASE}/api/v1/notifications`).then(data => {
                        const items = data?.data || [];
                        if (!items.length) {
                            listEl.innerHTML = '<p style="color:#999;text-align:center;padding:32px;">No notifications yet.</p>';
                            return;
                        }
                        listEl.innerHTML = items.map(n => `
                            <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid #f0f0f0;${n.read_at ? "opacity:.6;" : ""}">
                                <div style="width:10px;height:10px;border-radius:50%;background:${n.read_at ? "#d1d5db" : "#722F37"};margin-top:5px;flex-shrink:0;"></div>
                                <div style="flex:1;">
                                    <div style="font-weight:${n.read_at ? "400" : "700"};font-size:.9rem;">${escHtml(n.title)}</div>
                                    ${n.body ? `<div style="font-size:.82rem;color:#666;margin-top:2px;">${escHtml(n.body)}</div>` : ""}
                                    <div style="font-size:.75rem;color:#999;margin-top:4px;">${n.created_at ? new Date(n.created_at).toLocaleString() : ""}</div>
                                </div>
                                ${!n.read_at ? `<button data-notif-id="${n.id}" class="btn-notif-read" style="font-size:.75rem;color:#722F37;background:none;border:1px solid #722F37;border-radius:4px;padding:3px 8px;cursor:pointer;">Mark read</button>` : ""}
                            </div>`).join("");

                        listEl.querySelectorAll(".btn-notif-read").forEach(btn => {
                            btn.addEventListener("click", async () => {
                                await apiRequest(`${API_BASE}/api/v1/notifications/${btn.dataset.notifId}/read`, { method: "POST" });
                                btn.closest("div[style]").style.opacity = ".6";
                                btn.remove();
                            });
                        });
                    }).catch(() => {
                        listEl.innerHTML = '<p style="color:#dc2626;text-align:center;padding:32px;">Failed to load notifications.</p>';
                    });
                }
            }
        }

        function wireGlobalListeners() {
            document.querySelectorAll(".cc-tab[data-tab]").forEach(btn => {
                btn.addEventListener("click", () => { state.tab = btn.dataset.tab; renderAll(); });
            });
        }

        /* ── APP SHELL ──────────────────────────────────────────────────── */
        function buildAppShell() {
            const isAdmin    = state.role === "admin";
            const isOfficer  = ["admin", "election_officer"].includes(state.role);
            const isStudent  = state.role === "student";

            const tabs = [
                { key: "dashboard", icon: "fa-gauge-high",     label: "Dashboard",     show: isOfficer },
                { key: "vote",      icon: "fa-check-to-slot",  label: "Cast Vote",     show: isStudent },
                { key: "results",   icon: "fa-chart-line",     label: "Results",       show: true },
                { key: "candidates",icon: "fa-users",          label: "Candidates",    show: true },
                { key: "approval",  icon: "fa-user-check",     label: "Approvals",     show: isOfficer },
                { key: "analytics", icon: "fa-chart-pie",      label: "Analytics",     show: isOfficer },
                { key: "timeline",  icon: "fa-timeline",       label: "Timeline",      show: isOfficer },
                { key: "notifications", icon: "fa-bell",       label: "Notifications", show: true },
                { key: "audit",     icon: "fa-shield-halved",  label: "Audit",         show: isAdmin },
            ].filter(t => t.show);

            const tabsHtml = tabs.map((t, i) =>
                `<button class="cc-tab top-action${t.key === state.tab || (i === 0 && !state.tab) ? " active" : ""}" data-tab="${t.key}" type="button">
                     <i class="fa-solid ${t.icon}" aria-hidden="true"></i><span>${t.label}</span>
                 </button>`
            ).join("");

            return `<div class="vs-app-shell meditrack-shell">
                <main class="app-main app-main--embedded" id="main-content">
                    <section class="app-content">
                        <div class="workspace">
                            <div class="module-topbar">
                                <div>
                                    <div class="module-brandline">
                                        <span id="vs-ws-badge" class="vs-live-badge"><span></span> Live</span>
                                    </div>
                                    <h1 class="module-title" id="vs-current-section">${escHtml(pageTitle())}</h1>
                                    <p class="module-sub" id="vs-current-subtitle">${escHtml(pageSubtitle())}</p>
                                </div>
                                <div class="module-actions" aria-label="VoteSys navigation">${tabsHtml}</div>
                            </div>
                            <div class="privacy-notice">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span id="vs-role-notice">${escHtml(roleNotice())}</span>
                            </div>
                            <div id="vs-stats-wrap"></div>
                            <div class="content-area" id="vs-content"></div>
                        </div>
                    </section>
                </main>
            </div>`;
        }

        /* ── WEBSOCKET — live vote count updates ────────────────────────── */
        function subscribeToLiveResults() {
            if (!state.election || !window.Echo) return;

            window.Echo.channel(`election.${state.election.id}`)
                .listen(".vote.cast", (data) => {
                    const posId  = String(data.position_id);
                    const candId = data.candidate_id;
                    const count  = data.new_vote_count;

                    if (!state.results[posId]) state.results[posId] = [];
                    const existing = state.results[posId].find(r => r.candidate_id === candId);
                    if (existing) {
                        existing.votes = count;
                    } else {
                        state.results[posId].push({ candidate_id: candId, votes: count });
                    }

                    // Re-render only the results tab if it is active.
                    if (state.tab === "results") {
                        const area = document.getElementById("vs-content");
                        if (area) { area.innerHTML = buildResultsPanel(); wireTabListeners(); }
                    }

                    // Update the stats bar vote total.
                    renderStats();

                    state.wsConnected = true;
                    const badge = document.getElementById("vs-ws-badge");
                    if (badge) { badge.style.display = "inline-flex"; }
                });

            console.log("[votesys] WebSocket subscribed to election." + state.election.id);
        }

        /* ── BOOT ───────────────────────────────────────────────────────── */
        await loadData();

        // Set the default tab based on role and election state.
        if (!["student"].includes(state.role) && state.election) {
            state.tab = "dashboard";
        } else if (state.role === "candidate") {
            state.tab = "candidates";
        } else if (state.role === "student" && state.election?.status === "completed") {
            // Election is over — send students straight to results/winners.
            state.tab = "results";
        }

        renderAll();
        wireGlobalListeners();
        subscribeToLiveResults();

        const loader = document.getElementById("votesys-loader");
        if (loader) loader.style.display = "none";
        console.log("[votesys] Boot complete. Role:", state.role);
    }
}
