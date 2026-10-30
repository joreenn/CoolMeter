<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#143e35"><meta name="description" content="Your personal LG air conditioner energy and cost journal.">
    <title>CoolMeter · Your cooling, accounted for</title>
    <link rel="icon" type="image/svg+xml" href="/icon.svg"><link rel="manifest" href="/manifest.webmanifest">
    <script src="/theme.js?v=7"></script>
    <link rel="stylesheet" href="/app.css?v=7">
    <script type="application/json" id="initial-state">{!! json_encode($initialState ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="/app.js?v=7" defer></script>
</head>
<body class="dashboard-page">
<svg class="svg-library" xmlns="http://www.w3.org/2000/svg"><defs>
    <symbol id="snow" viewBox="0 0 24 24"><path d="M12 2v20M3.34 7l17.32 10M3.34 17L20.66 7M8 4l4 3 4-3M8 20l4-3 4 3M3 11l4-2-1-5M21 13l-4 2 1 5M3 13l4 2-1 5M21 11l-4-2 1-5"/></symbol>
    <symbol id="home" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10H3ZM9 20v-7h6v7"/></symbol>
    <symbol id="clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="settings" viewBox="0 0 24 24"><path d="M4 7h16M4 17h16M8 4v6M16 14v6"/></symbol>
    <symbol id="plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
    <symbol id="leaf" viewBox="0 0 24 24"><path d="M20 3C9 2 2 8 5 15s15 5 15-12ZM4 21 16 9"/></symbol>
    <symbol id="bolt" viewBox="0 0 24 24"><path d="m13 2-9 12h7l-1 8 10-13h-7Z"/></symbol>
    <symbol id="play" viewBox="0 0 24 24"><path d="m8 4 12 8-12 8Z"/></symbol>
    <symbol id="stop" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/></symbol>
    <symbol id="info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/></symbol>
    <symbol id="download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></symbol>
    <symbol id="close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
    <symbol id="check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="edit" viewBox="0 0 24 24"><path d="m15 4 5 5M4 20l5-1L21 7l-5-5L4 14Z"/></symbol>
    <symbol id="trash" viewBox="0 0 24 24"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></symbol>
    <symbol id="chart" viewBox="0 0 24 24"><path d="M4 3v18h17M9 17v-5M14 17V7M19 17V4"/></symbol>
</defs></svg>
<aside class="sidebar">
    <a class="brand" href="/#overview"><span class="brand-icon"><svg><use href="#snow"/></svg></span>CoolMeter<span class="brand-dot">.</span></a>
    <div class="workspace"><span class="home-avatar"><svg class="icon"><use href="#home"/></svg></span><div>Our home<small>Davao City, Philippines</small></div><span class="status-dot"></span></div>
    <p class="nav-label">WORKSPACE</p>
    <nav aria-label="Main navigation">
        <button class="nav-item active" data-page="overview"><svg class="icon"><use href="#home"/></svg>Overview<span class="nav-active-dot"></span></button>
        <button class="nav-item" data-page="history"><svg class="icon"><use href="#clock"/></svg>Session history<span id="history-count" class="count">0</span></button>
        <button class="nav-item" data-page="settings"><svg class="icon"><use href="#settings"/></svg>Settings</button>
    </nav>
    <div class="sidebar-bottom"><div class="little-snow"><svg><use href="#snow"/></svg></div><h3>A little awareness.<br>A cooler tomorrow.</h3><p>Understand your cooling habits, one session at a time.</p><div class="local-badge"><span class="status-dot"></span>{{ config('journal.hosted') ? 'Saved in your private journal' : 'Stored on your home PC' }}</div></div>
    <div class="sidebar-foot"><span class="avatar">{{ auth()->check() ? strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'J' }}</span><div>{{ auth()->user()?->name ?? 'Home workspace' }}<small>One AC, a shared journal</small></div>@auth<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-button">Sign out</button></form>@endauth</div>
</aside>
<main>
    <header class="topbar">
        <span class="eyebrow">YOUR HOME ENERGY JOURNAL</span>
        <div class="topbar-controls">
            <div class="topbar-date"><span class="timezone">PHT · </span><time id="today-date"></time></div>
            <div class="topbar-actions"><button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch color theme">Change theme</button>@auth<form class="mobile-signout" method="POST" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Sign out</button></form>@endauth</div>
        </div>
    </header>
    <div class="page" id="page-overview">
        <section class="page-heading"><div><div class="greeting">A little cooler. A little wiser.</div><h1>Your cooling, accounted for<span>.</span></h1><p>Get a clearer picture of what your comfort costs.</p></div><button class="btn btn-white" data-action="focus-log"><svg class="icon"><use href="#plus"/></svg>Log a session</button></section>
        <section class="stats" aria-label="Completed session totals this month">
            <article class="stat"><div class="stat-top">Estimated cost this month<span class="stat-icon mint">₱</span></div><div class="stat-value" id="month-cost">₱0.00</div><div class="stat-foot"><span class="small-dot"></span><span id="month-name">This month</span> · completed sessions</div></article>
            <article class="stat"><div class="stat-top">Energy used this month<span class="stat-icon sand"><svg class="icon"><use href="#bolt"/></svg></span></div><div class="stat-value"><span id="month-kwh">0.00</span> <span class="unit">kWh</span></div><div class="stat-foot">Estimated electricity consumption</div></article>
            <article class="stat"><div class="stat-top">Cooling time this month<span class="stat-icon lavender"><svg class="icon"><use href="#clock"/></svg></span></div><div class="stat-value"><span id="month-hours">0</span> <span class="unit">hrs</span></div><div class="stat-foot"><span id="today-cost">₱0.00</span> estimated cost today</div></article>
        </section>
        <div id="active-session" class="active-session" hidden></div>
        <div class="dashboard-grid">
            <section class="card log-card" id="log-card">
                <div class="section-heading"><div><span class="eyebrow green">MAKE COMFORT COUNT</span><h2 id="log-heading">Log a cooling session</h2></div><span class="step-tag">01 — 03</span></div>
                <div class="segmented" aria-label="Session entry mode"><button id="mode-live" type="button" class="selected"><svg class="icon"><use href="#play"/></svg>Live timer</button><button id="mode-manual" type="button"><svg class="icon"><use href="#clock"/></svg>Enter times</button></div>
                <form id="session-form">
                    <div id="manual-times" class="field-grid" hidden><label>AC turned on<input type="datetime-local" id="started-at" name="started_at"></label><label>AC turned off<input type="datetime-local" id="ended-at" name="ended_at"></label><p class="field-help full">Use the next date for an overnight session. All times are Philippine time.</p></div>
                    <fieldset class="heat-field"><legend>How did the room feel when you started?</legend><div class="heat-options"><label><input type="radio" name="room_heat" value="comfortable"><span><span class="heat-glyph">◌</span>Comfortable<small>Already quite cool</small></span></label><label><input type="radio" name="room_heat" value="warm" checked><span><span class="heat-glyph">☀</span>A little warm<small>Needs cooling down</small></span></label><label><input type="radio" name="room_heat" value="hot"><span><span class="heat-glyph">♨</span>Very hot<small>Heat built up</small></span></label></div></fieldset>
                    <div class="field-grid settings-row"><label>Target temperature<div class="temperature-control"><button type="button" id="temp-minus" aria-label="Lower target temperature">−</button><output id="temp-output">26<span>°C</span></output><button type="button" id="temp-plus" aria-label="Raise target temperature">+</button></div><input type="hidden" name="temperature" id="temperature" value="26"></label><label>Fan speed<select name="fan" id="fan"><option value="low">Low fan</option><option value="medium">Medium fan</option><option value="high">High fan</option><option value="auto">Auto fan</option></select><span class="field-help">Mode: Cool · your usual setup</span></label></div>
                    <label class="notes-label">Session note <span class="optional">optional</span><input name="notes" id="notes" maxlength="1000" placeholder="e.g. Warm afternoon, curtains closed"></label>
                    <div class="form-error" id="session-error" role="alert" hidden></div>
                    <div class="form-bottom"><span id="mode-hint"><svg class="icon"><use href="#info"/></svg>Tap Start when you turn on your AC.</span><div class="button-group"><button type="button" class="btn btn-white" id="cancel-edit" hidden>Cancel edit</button><button class="btn btn-primary" id="session-submit" type="submit"><svg class="icon"><use href="#play"/></svg>Start session</button></div></div>
                </form>
            </section>
            <aside class="right-column">
                <section class="card device-card"><div class="section-heading"><h2>Your air conditioner</h2><span class="tag">1 HP INVERTER</span></div>
                    <div class="ac-scene" id="ac-preview-scene" aria-hidden="true"><div class="scene-circle"></div><div class="ac-unit"><div class="ac-grille"></div><div class="ac-panel"><span class="lg-mark">LG</span><span class="ac-display"><span id="ac-preview-temperature">26</span><span class="ac-degree">°</span></span><span class="ac-led"></span></div><div class="ac-louver"></div></div><div class="air-flow"><i></i><i></i><i></i></div></div>
                    <p class="ac-preview-caption" id="ac-preview-caption" role="status" aria-live="polite" aria-atomic="true">Selected target 26°C</p>
                    <div class="device-name"><div><h3>LG LA100ENT</h3><p>Window-type inverter</p></div><svg class="icon"><use href="#snow"/></svg></div><div class="device-specs"><div><span>Normal input</span><strong>1,030 <small>W</small></strong></div><div><span>Maximum input</span><strong>1,250 <small>W</small></strong></div></div><p class="label-verified"><svg class="icon"><use href="#check"/></svg>Specifications from your unit’s label</p>
                </section>
                <section class="rate-card"><div class="rate-top"><span class="rate-logo"><svg class="icon"><use href="#bolt"/></svg></span><div><h3>Davao Light</h3><p>Residential electricity rate</p></div><button class="icon-button" data-page="settings" aria-label="Edit electricity rate"><svg class="icon"><use href="#edit"/></svg></button></div><div class="rate-number" id="rate-number">₱13.33 <span>/ kWh</span></div><div class="rate-period" id="rate-period">Oct 11 – Nov 10, 2026</div><a class="rate-source" id="rate-source" href="#" target="_blank" rel="noopener">Reported rate · view source ↗</a><p id="rate-warning" class="rate-warning" hidden>Rate period has expired. Update it from your latest bill.</p></section>
            </aside>
        </div>
        <section class="card chart-card"><div class="section-heading"><div><h2>A week of keeping cool</h2><p>Estimated energy from completed sessions, split by day.</p></div><span class="chart-legend"><i></i>Energy used (kWh)</span></div><div id="weekly-chart" class="weekly-chart"></div></section>
        <section class="card history-card"><div class="section-heading"><div><h2>Recent sessions</h2><p>Your comfort, with a little more context.</p></div><button class="text-button" data-page="history">View all sessions <svg class="icon"><use href="#arrow"/></svg></button></div><div id="recent-sessions"></div></section>
        <footer><svg class="icon"><use href="#info"/></svg>Estimates help you spot habits. Actual consumption varies with your room and compressor load.<button class="text-button" data-action="method">How we estimate ↗</button></footer>
    </div>
    <div class="page" id="page-history" hidden><section class="page-heading"><div><div class="greeting">Every session tells a story.</div><h1>Your session history<span>.</span></h1><p>Review, edit, or export your cooling journal.</p></div><a class="btn btn-white" href="/api/export"><svg class="icon"><use href="#download"/></svg>Export CSV</a></section><section class="card"><div class="history-tools"><label>Filter by month<input type="month" id="history-month"></label><button class="text-button" id="clear-filter">Show all</button><span id="filtered-total"></span></div><div id="all-sessions"></div></section></div>
    <div class="page" id="page-settings" hidden><section class="page-heading"><div><div class="greeting">A better estimate starts here.</div><h1>Make it yours<span>.</span></h1><p>Your AC specifications, electricity rate, and estimation assumptions.</p></div></section><form id="settings-form" class="settings-layout"><section class="card"><div class="section-heading"><div><h2>Electricity rate</h2><p>Davao Light · residential</p></div><svg class="icon"><use href="#bolt"/></svg></div><div class="form-fields"><label>Rate per kWh (₱)<input type="number" step="0.0001" min="0.01" max="100" name="rate" required></label><div class="field-grid"><label>Billing period starts<input type="date" name="rate_from" required></label><label>Billing period ends<input type="date" name="rate_until" required></label></div><p class="field-help">Update this when Davao Light publishes a new billing rate. Rates do not update automatically. Previous sessions keep their original rate.</p><a class="text-button" href="https://www.davaolight.com/customer-services/faqs-rates" target="_blank" rel="noopener">Davao Light rates & billing ↗</a></div></section><section class="card"><div class="section-heading"><div><h2>Power assumptions</h2><p>Adjust with measured data when available.</p></div><svg class="icon"><use href="#settings"/></svg></div><div class="form-fields"><div class="info-box">Your label confirms 1,030 W normal and 1,250 W maximum. Settled power below is an assumption. Eco and normal start equal because this unit’s Eco savings have not been measured.</div><div class="field-grid"><label>Settled power, normal (W)<input type="number" name="normal_watts" min="50" max="1250" required></label><label>Settled power, Eco (W)<input type="number" name="eco_watts" min="50" max="1250" required></label><label>Warm-room cooldown (min)<input type="number" name="cooldown_minutes" min="0" max="120" required></label><label>Scenario spread (±%)<input type="number" name="uncertainty_percent" min="10" max="80" required></label></div><p class="field-help">Comfortable: half the cooldown time. Warm: full time. Very hot: 1.5×. Temperature and fan speed are recorded for comparisons; no unverified percentage is assigned to them.</p></div></section><div class="settings-actions"><span id="settings-message" role="status"></span><button class="btn btn-primary" type="submit">Save settings<svg class="icon"><use href="#check"/></svg></button></div></form>
<section class="card household-panel" id="household-panel">
    <div class="section-heading"><div><h2>One AC, together</h2><p>Invite your household to the same cooling journal.</p></div><svg class="icon"><use href="#home"/></svg></div>
    <p class="household-intro">You and your invited family can see the same sessions, start or stop the timer, and record Eco mode. Changes sync while the app is open.</p>
    @if(config('journal.require_login'))
    <div class="household-layout">
        <div><h3>People with access</h3><div id="household-members" class="household-list" aria-live="polite"><p class="field-help">Open this section to load your household.</p></div></div>
        <div>
            @if(auth()->user()?->isJournalOwner())
            <h3>Invite someone</h3>
            <p class="field-help">Enter their email, then share the private invitation link with them. They’ll choose their own password.</p>
            <form id="invite-form" class="invite-form"><label>Email address<input id="invite-email" type="email" name="email" maxlength="254" placeholder="Your brother’s email" required></label><button class="btn btn-primary" type="submit">Create invite link</button></form>
            <div id="invite-error" class="form-error" role="alert" hidden></div>
            <div id="invite-result" class="invite-result" role="status" hidden></div>
            <div class="household-invites"><h3>Pending invitations</h3><div id="household-invites" class="household-list"></div></div>
            @else
            <p class="household-member-note">You share this AC with your household. Only the journal owner can invite or remove people.</p>
            @endif
        </div>
    </div>
    @else
    <p class="household-member-note">Household invitations are available in the online app with sign-in enabled. On your home Wi-Fi, open this PC’s app address to share this local journal.</p>
    @endif
</section>
<section class="card home-guide"><h2>{{ config('journal.hosted') ? 'Your journal, wherever you are' : 'Made for your home' }}</h2>@if(config('journal.hosted'))<p>Your journal is saved in a hosted database. Sign in on your phone or PC to see the same sessions. Your PC does not need to stay on.</p><p>On Android, use your browser’s Install app or Add to Home screen option for easy access.</p>@else<p>Your journal lives in a SQLite database on this PC. Both devices see the same records when your Android opens the PC’s local network address on home Wi-Fi. Keep the PC and app server running.</p><p>A home-screen shortcut works on Android. Full PWA installation needs HTTPS (or localhost); the ordinary home Wi-Fi browser app works over HTTP.</p>@endif<button class="text-button" data-action="method">Read the estimation method <svg class="icon"><use href="#arrow"/></svg></button></section></div>
</main>
<dialog id="eco-dialog" class="modal"><button class="modal-close icon-button" data-close="eco-dialog" aria-label="Close Eco dialog"><svg class="icon"><use href="#close"/></svg></button><span class="modal-symbol"><svg><use href="#leaf"/></svg></span><span class="eyebrow green">A SMALL DETAIL THAT HELPS</span><h2>When did you switch to Eco?</h2><p>Record the time you enabled Eco mode during this session.</p><form id="eco-form"><label>Eco turned on<input id="eco-time" type="datetime-local" required></label><p class="field-help" id="eco-help"></p><div class="form-error" id="eco-error" role="alert" hidden></div><button type="submit" class="btn btn-primary full-width">Use this Eco time <svg class="icon"><use href="#arrow"/></svg></button><button type="button" id="no-eco" class="text-button full-width">I didn’t use Eco mode</button></form></dialog>
<dialog id="result-dialog" class="modal result-modal"><button class="modal-close icon-button" data-close="result-dialog" aria-label="Close estimate"><svg class="icon"><use href="#close"/></svg></button><span class="eyebrow green">YOUR SESSION AT A GLANCE</span><h2>A little comfort, accounted for.</h2><div id="result-content"></div><div class="form-error" id="result-error" role="alert" hidden></div><div class="modal-actions"><button class="btn btn-white" data-close="result-dialog">Back</button><button class="btn btn-primary" id="save-session">Save session <svg class="icon"><use href="#check"/></svg></button></div></dialog>
<dialog id="detail-dialog" class="modal result-modal"><button class="modal-close icon-button" data-close="detail-dialog" aria-label="Close session details"><svg class="icon"><use href="#close"/></svg></button><span class="eyebrow green">FROM YOUR COOLING JOURNAL</span><h2>Session details</h2><div id="detail-content"></div><div class="modal-actions"><button class="btn btn-white" id="delete-session"><svg class="icon"><use href="#trash"/></svg>Delete</button><button class="btn btn-primary" id="edit-session"><svg class="icon"><use href="#edit"/></svg>Edit session</button></div></dialog>
<dialog id="confirm-dialog" class="modal"><h2 id="confirm-title">Delete this session?</h2><p id="confirm-copy">This removes the session from your journal and totals.</p><div class="form-error" id="confirm-error" role="alert" hidden></div><div class="modal-actions"><button class="btn btn-white" data-close="confirm-dialog">Keep session</button><button class="btn btn-danger" id="confirm-delete">Delete session</button></div></dialog>
<dialog id="stop-dialog" class="modal"><button class="modal-close icon-button" data-close="stop-dialog" aria-label="Close stop dialog"><svg class="icon"><use href="#close"/></svg></button><h2>Finish your cooling session</h2><p>Stop now, or enter when you turned the AC off if you forgot to stop the timer.</p><form id="stop-form"><label>When did you turn it off?<select id="stop-choice"><option value="now">Just now</option><option value="earlier">Earlier — enter a time</option></select></label><label id="stop-time-label" hidden>AC turned off<input id="stop-time" type="datetime-local"></label><div class="form-error" id="stop-error" role="alert" hidden></div><button class="btn btn-primary full-width" type="submit">Stop & save session <svg class="icon"><use href="#check"/></svg></button></form></dialog>
<dialog id="method-dialog" class="modal method-modal"><button class="modal-close icon-button" data-close="method-dialog" aria-label="Close estimation method"><svg class="icon"><use href="#close"/></svg></button><span class="eyebrow green">A LITTLE TRANSPARENCY</span><h2>How your estimate works</h2><p>Energy (kWh) = electrical input (W) ÷ 1,000 × hours. Cost = estimated kWh × the saved Davao Light rate.</p><div class="method-step"><span>01</span><div><h3>Cooling the room</h3><p>We assume the label’s normal 1,030 W during initial cooling. The default period is 10, 20, or 30 minutes for comfortable, warm, or very hot rooms. These durations are editable modelling assumptions.</p></div></div><div class="method-step"><span>02</span><div><h3>Keeping it comfortable</h3><p>After cooldown, we use your assumed average input: initially 670 W for both normal and Eco. Eco timing is logged, but no savings are promised. Use measured average power to refine these settings.</p></div></div><div class="method-step"><span>03</span><div><h3>A range, with context</h3><p>The default range is ±35%, capped at 1,250 W × hours. This is an illustrative scenario range, not a tested accuracy interval or a guaranteed minimum/maximum. Your actual usage can fall outside it.</p></div></div><p>Temperature and fan speed are journal details. We do not infer fixed savings from them. Billing rates are dated, manually maintained, and saved per session. Monthly/day totals split overnight sessions at Philippine midnight. Running sessions are separate until stopped.</p><p class="info-box">This is a personal estimate of your AC’s energy cost, not a prediction of your full electricity bill. Meter readings are needed to measure actual consumption.</p></dialog>
<div id="toast" class="toast" role="status" hidden></div>
<div id="load-error" class="load-error" role="alert" hidden><span id="load-error-text"></span><button class="btn btn-white" id="retry-load">Retry</button></div>
</body></html>
