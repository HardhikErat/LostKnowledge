<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="About Lost Knowledge — our mission, the team, and how the archive works.">
  <meta name="keywords" content="lost knowledge, archive, forgotten traditions, ancient wisdom">
  <meta name="author" content="Lost Knowledge Archive">
  <title>About — Lost Knowledge</title>
  <link rel="stylesheet" href="/lost-knowledge/assets/css/style.css">
  <link rel="stylesheet" href="/lost-knowledge/assets/css/features.css">
  <style>
    /* ── About-page specific styles ── */

    /* Section wrapper alternating dark/slightly-lighter */
    .about-section {
      padding: 80px 0;
      border-bottom: 1px solid var(--border-dark);
    }
    .about-section-alt {
      background: var(--bg-dark);
    }

    /* Two-column text + visual layout */
    .about-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 64px;
      align-items: start;
    }
    .about-grid-reverse { direction: rtl; }
    .about-grid-reverse > * { direction: ltr; }

    /* Paragraph text on dark */
    .about-body p {
      color: var(--text-on-dark-muted);
      font-size: 1rem;
      line-height: 1.85;
      margin-bottom: 1.1rem;
    }
    .about-body p:last-child { margin-bottom: 0; }

    /* Text formatting demo box */
    .format-demo {
      background: var(--bg-panel);
      border: 1px solid var(--border-gold);
      border-radius: 6px;
      padding: 24px 28px;
      font-size: .95rem;
      line-height: 2;
      color: var(--text-on-dark-muted);
    }

    /* Blockquote */
    .about-quote {
      border-left: 3px solid var(--amber);
      padding: 16px 24px;
      margin: 24px 0;
      background: var(--bg-panel);
      border-radius: 0 6px 6px 0;
    }
    .about-quote p {
      font-style: italic;
      font-size: 1.05rem;
      color: var(--text-on-dark-muted) !important;
      margin: 0 0 8px !important;
    }
    .about-quote cite {
      font-size: .85rem;
      color: var(--amber);
      font-style: normal;
    }

    /* Image map container */
    .map-wrap {
      position: relative;
      border: 1px solid var(--border-gold);
      border-radius: 6px;
      overflow: hidden;
      background: var(--bg-panel);
    }
    .map-wrap img {
      width: 100%; height: auto; display: block;
      filter: sepia(.3) brightness(.85) contrast(1.1);
    }
    .map-region-result {
      margin-top: 12px;
      padding: 10px 16px;
      background: var(--bg-panel);
      border: 1px solid var(--border-gold);
      border-radius: 6px;
      font-size: .88rem;
      color: var(--amber-light);
      display: none;
    }
    .map-hint {
      font-size: .8rem;
      color: var(--text-on-dark-faint);
      margin-top: 8px;
      font-style: italic;
    }

    /* Archive stats table */
    .about-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .88rem;
    }
    .about-table caption {
      font-family: var(--font-display);
      font-size: 1rem; font-weight: 600;
      color: var(--text-on-dark);
      margin-bottom: 12px;
      caption-side: top;
      text-align: left;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-gold);
    }
    .about-table th {
      background: var(--bg-void);
      color: var(--amber-light);
      padding: 10px 16px;
      text-align: left;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: .12em;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border-gold);
    }
    .about-table td {
      padding: 10px 16px;
      border-bottom: 1px solid var(--border-dark);
      color: var(--text-on-dark-muted);
      vertical-align: middle;
    }
    .about-table tr:hover td { background: var(--bg-panel-h); }
    .about-table tfoot td {
      background: var(--bg-void);
      color: var(--amber-light);
      font-weight: 600;
      border-top: 1px solid var(--border-gold);
    }

    /* How it works — numbered list */
    .steps-list { list-style: none; }
    .steps-list li {
      display: flex; gap: 16px; align-items: flex-start;
      padding: 16px 0;
      border-bottom: 1px solid var(--border-dark);
    }
    .steps-list li:last-child { border-bottom: none; }
    .step-num {
      width: 32px; height: 32px; border-radius: 50%;
      border: 1px solid var(--border-gold);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-display); font-weight: 700;
      font-size: .85rem; color: var(--amber);
      flex-shrink: 0; margin-top: 2px;
    }
    .step-text { color: var(--text-on-dark-muted); font-size: .95rem; line-height: 1.65; }
    .step-text strong { color: var(--text-on-dark); }

    /* Definition list */
    .def-list { }
    .def-list dt {
      font-family: var(--font-display); font-weight: 600;
      font-size: 1rem; color: var(--amber-light);
      margin-top: 20px; margin-bottom: 4px;
    }
    .def-list dt:first-child { margin-top: 0; }
    .def-list dd {
      color: var(--text-on-dark-muted); font-size: .9rem;
      line-height: 1.65; margin-left: 16px;
      padding-left: 12px; border-left: 2px solid var(--border-gold);
    }

    /* Contact form */
    .contact-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-gold);
      border-radius: 10px;
      padding: 32px;
      position: relative;
    }
    .contact-card::before {
      content: '';
      position: absolute; top: 0; left: 32px; right: 32px;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--amber), transparent);
    }

    /* Details/summary */
    .faq-item {
      background: var(--bg-panel);
      border: 1px solid var(--border-dark);
      border-radius: 6px;
      margin-bottom: 8px;
      overflow: hidden;
    }
    .faq-item summary {
      padding: 16px 20px;
      cursor: pointer;
      font-weight: 600;
      font-size: .95rem;
      color: var(--text-on-dark);
      list-style: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: background var(--transition);
    }
    .faq-item summary:hover { background: var(--bg-panel-h); }
    .faq-item summary::after {
      content: '✦';
      color: var(--gold); font-size: 10px;
    }
    .faq-item[open] summary::after { content: '◆'; }
    .faq-body {
      padding: 0 20px 16px;
      color: var(--text-on-dark-muted);
      font-size: .9rem;
      line-height: 1.7;
    }

    /* Team cards */
    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
    }
    .team-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-dark);
      border-radius: 10px;
      padding: 20px 16px;
      text-align: center;
      transition: all var(--transition);
    }
    .team-card:hover { border-color: var(--border-gold); }
    .team-av {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: var(--amber-dark);
      border: 2px solid var(--border-gold);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-display);
      font-size: 1.25rem; font-weight: 700;
      color: var(--text-on-dark);
      margin: 0 auto 12px;
    }
    .team-name { font-weight: 600; font-size: .9rem; color: var(--text-on-dark); margin-bottom: 3px; }
    .team-role { font-size: .75rem; color: var(--text-on-dark-faint); letter-spacing: .06em; text-transform: uppercase; }

    /* Section heading accent */
    .section-heading {
      margin-bottom: 32px;
    }
    .section-heading h2 { margin-bottom: 8px; }
    .section-heading p { color: var(--text-on-dark-muted); font-size: .95rem; margin-bottom: 0; }
    .gold-line {
      display: flex; align-items: center; gap: 10px; margin-top: 16px;
    }
    .gold-line::before, .gold-line::after {
      content: ''; flex: 1; height: 1px; background: var(--border-gold);
    }
    .gold-line span { color: var(--gold); font-size: 9px; }

    /* Inline code */
    pre.code-block {
      background: var(--bg-void);
      border: 1px solid var(--border-gold);
      border-radius: 6px;
      padding: 20px 24px;
      font-family: var(--font-mono);
      font-size: .82rem;
      color: var(--amber-light);
      overflow-x: auto;
      line-height: 1.8;
      margin: 16px 0;
    }

    @media (max-width: 768px) {
      .about-grid { grid-template-columns: 1fr; gap: 32px; }
      .about-grid-reverse { direction: ltr; }
      .team-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<!-- ═══ HEADER ════════════════════════════════════════════ -->
<header class="site-header">
  <div class="container nav-inner">
    <a href="/lost-knowledge/index.html" class="site-logo">
      <img src="/lost-knowledge/assets/logo.png" alt="Lost Knowledge" class="nav-logo-img">
      <div class="logo-text">
        <span class="logo-mark">Lost Knowledge</span>
        <span class="logo-sub">Archive of Vanishing Wisdom</span>
      </div>
    </a>
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav-links" aria-label="Main navigation">
      <a href="/lost-knowledge/index.html"    class="nav-link">Archive</a>
      <a href="/lost-knowledge/about.php"    class="nav-link active">About</a>
      <div class="nav-sep"></div>
      <a href="/lost-knowledge/register.html" class="nav-link">Register</a>
      <a href="/lost-knowledge/login.html"    class="nav-link">Sign In</a>
      <a href="/lost-knowledge/submit.php"    class="nav-link nav-cta">✦ Submit Entry</a>
    </nav>
  </div>
</header>

<!-- ═══ PAGE HEADER ════════════════════════════════════════ -->
<div class="page-header">
  <div class="container">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
      <span style="color:var(--gold);font-size:10px">✦</span>
      <span style="font-size:11px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--text-on-dark-faint)">About the archive</span>
    </div>
    <h1>About Lost Knowledge</h1>
    <p style="margin-top:10px">Our mission, how it works, and what we're building together.</p>
  </div>
</div>

<!-- ═══ SECTION 1 — WHAT WE DO ════════════════════════════ -->
<section class="about-section">
  <div class="container">
    <div class="about-grid">

      <div class="about-body">
        <div class="section-heading">
          <h2>What We Do</h2>
          <div class="gold-line"><span>✦</span></div>
        </div>

        <!-- Module 1.2: all text formatting tags -->
        <p>
          Lost Knowledge is a <strong>community-driven archive</strong> dedicated to preserving
          <em>dying traditions</em> and <u>forgotten crafts</u> before they vanish from
          human memory. Unlike <s>traditional museums</s>, we are
          <mark style="background:var(--border-gold);color:var(--text-on-dark);padding:1px 5px;border-radius:2px">open to everyone</mark>.
        </p>
        <p>
          Water<sub>2</sub>O and CO<sub>2</sub> formulas remind us that even chemistry
          has its H<sup>+</sup> ions — and knowledge too has its building blocks.
          Our archive holds <abbr title="Knowledge Preservation Units">KPU</abbr>s
          from over <strong>83</strong> registered Keepers worldwide.
        </p>

        <div class="about-quote">
          <p>"A people without knowledge of their past history, origin and culture is like a tree without roots."</p>
          <cite>— Marcus Garvey</cite>
        </div>

        <p>
          Every entry is <em>reviewed</em>, <strong>voted on</strong>, and
          <u>preserved permanently</u> in our database — ensuring that what you
          contribute today survives for generations.
        </p>
      </div>

      <div>
        <div class="format-demo">
          <p style="color:var(--text-on-dark-faint);font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px">Our API returns structured data like this:</p>
          <pre class="code-block">{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Polynesian Stick Chart Reading",
      "category_name": "Navigation",
      "votes_up": 87
    }
  ]
}</pre>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══ SECTION 2 — IMAGE MAP / EXPLORE BY REGION ═════════ -->
<section class="about-section about-section-alt">
  <div class="container">
    <div class="section-heading" style="text-align:center;margin-bottom:40px">
      <h2>Explore by Region</h2>
      <p>Click on a region of the world map below to filter entries from that area.</p>
      <div class="gold-line"><span>✦</span></div>
    </div>

    <!--
      Module 1.2: <img> + <map> + <area> — Image Map
      coords are calibrated for the rendered display width of the Wikipedia
      SVG map (1200px natural size). Each area maps to the visual continent
      position on that specific projection.

      Projection: Natural Earth / Robinson-style (Wikipedia low-res world map)
      Natural size: 1200 × 600 px
      Coordinate system: pixels from top-left of the image at natural size.

      Region bounding boxes (tested against the actual image):
        North America  : x1=30,  y1=30,  x2=290, y2=340
        South America  : x1=180, y1=330, x2=340, y2=560
        Europe         : x1=470, y1=30,  x2=620, y2=240
        Africa         : x1=460, y1=230, x2=650, y2=530
        Middle East    : x1=620, y1=180, x2=730, y2=320
        Central Asia   : x1=620, y1=30,  x2=820, y2=200
        East Asia      : x1=810, y1=40,  x2=1010,y2=300
        South Asia     : x1=720, y1=260, x2=870, y2=410
        Southeast Asia : x1=860, y1=290, x2=1010,y2=450
        Oceania        : x1=900, y1=410, x2=1180,y2=580
        Russia/N.Asia  : x1=610, y1=30,  x2=1010,y2=140 (overlaps, handled last)
    -->

    <!-- Tooltip label shown on hover -->
    <div id="mapTooltip" style="
      display:none;
      position:fixed;
      background:var(--bg-void);
      border:1px solid var(--border-gold);
      color:var(--amber-light);
      font-family:var(--font-display);
      font-size:.85rem;
      font-weight:600;
      padding:6px 14px;
      border-radius:4px;
      pointer-events:none;
      z-index:9999;
      letter-spacing:.04em;
      white-space:nowrap;
    "></div>

    <div class="map-wrap" style="position:relative;line-height:0">
      <img
        id="worldMapImg"
        src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/World_map_-_low_resolution.svg/1200px-World_map_-_low_resolution.svg.png"
        alt="World map — click a region to explore knowledge entries from that area"
        width="1200"
        height="600"
        usemap="#worldmap"
        style="cursor:crosshair;max-width:100%;height:auto;display:block"
        onerror="this.onerror=null;this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/World_map_blank_without_borders.svg/1200px-World_map_blank_without_borders.svg.png'">

      <!--
        All coords below are for the 1200×600 natural image size.
        The browser automatically scales them proportionally when the
        image is displayed at a different CSS width.
        Shapes use polygon where possible for better fit.
      -->
      <map name="worldmap" id="worldmap">

        <!-- North America: covers Canada, USA, Mexico, Caribbean -->
        <area shape="poly"
          coords="30,40, 290,40, 290,160, 260,180, 250,240, 230,260, 210,310, 185,335, 150,340, 100,300, 60,260, 30,200, 30,40"
          href="#" alt="North America" title="North America"
          onclick="filterRegion('North America');return false;">

        <!-- South America -->
        <area shape="poly"
          coords="185,335, 215,330, 260,340, 290,360, 310,400, 320,450, 310,510, 290,555, 255,560, 225,530, 195,490, 175,440, 170,390, 175,355, 185,335"
          href="#" alt="South America" title="South America"
          onclick="filterRegion('South America');return false;">

        <!-- Europe: UK, Scandinavia, France, Germany, Italy, Iberia etc. -->
        <area shape="poly"
          coords="460,40, 620,40, 625,80, 610,120, 590,160, 565,200, 540,230, 510,235, 480,220, 460,180, 455,130, 460,40"
          href="#" alt="Europe" title="Europe"
          onclick="filterRegion('Europe');return false;">

        <!-- Africa -->
        <area shape="poly"
          coords="460,235, 510,235, 545,230, 580,240, 615,235, 640,260, 650,320, 645,400, 620,460, 600,520, 570,545, 540,540, 510,510, 490,460, 470,400, 455,340, 455,280, 460,235"
          href="#" alt="Africa" title="Africa"
          onclick="filterRegion('Africa');return false;">

        <!-- Middle East: Arabian Peninsula, Iran, Iraq, Turkey -->
        <area shape="poly"
          coords="615,170, 730,170, 740,210, 740,280, 720,320, 690,330, 655,320, 630,280, 615,240, 610,200, 615,170"
          href="#" alt="Middle East" title="Middle East"
          onclick="filterRegion('Middle East');return false;">

        <!-- Russia / North Asia (broad band across top) -->
        <area shape="poly"
          coords="615,40, 1010,40, 1010,140, 820,145, 730,165, 620,160, 615,40"
          href="#" alt="Russia / North Asia" title="Russia / North Asia"
          onclick="filterRegion('Russia / North Asia');return false;">

        <!-- East Asia: China, Japan, Korea, Mongolia -->
        <area shape="poly"
          coords="820,145, 1010,145, 1010,310, 960,330, 900,300, 860,270, 840,230, 820,180, 820,145"
          href="#" alt="East Asia" title="East Asia"
          onclick="filterRegion('East Asia');return false;">

        <!-- South Asia: India, Pakistan, Bangladesh, Sri Lanka -->
        <area shape="poly"
          coords="730,165, 820,175, 820,280, 800,340, 775,380, 755,410, 725,400, 710,360, 700,310, 700,240, 715,200, 730,165"
          href="#" alt="South Asia" title="South Asia"
          onclick="filterRegion('South Asia');return false;">

        <!-- Southeast Asia: Thailand, Vietnam, Indonesia, Philippines -->
        <area shape="poly"
          coords="860,270, 960,280, 1005,310, 1010,400, 980,450, 940,460, 900,430, 870,390, 855,330, 850,290, 860,270"
          href="#" alt="South East Asia" title="South East Asia"
          onclick="filterRegion('South East Asia');return false;">

        <!-- Oceania: Australia, New Zealand, Pacific Islands -->
        <area shape="poly"
          coords="920,415, 1010,405, 1090,415, 1170,435, 1180,490, 1140,545, 1080,560, 1010,545, 960,510, 920,470, 910,440, 920,415"
          href="#" alt="Oceania / Pacific" title="Oceania / Pacific"
          onclick="filterRegion('Oceania / Pacific');return false;">

      </map>
    </div>

    <p class="map-hint">✦ Hover over a region to see its name. Click to filter archive entries.</p>

    <div class="map-region-result" id="regionResult" style="display:none">
      <strong>✦ Region selected:</strong> <span id="regionName"></span> —
      <a href="/lost-knowledge/index.html" style="color:var(--amber-light)" id="regionLink">Browse matching entries →</a>
    </div>

    <!-- Interactive Leaflet Map -->
    <div style="margin-top:40px">
      <h3 style="text-align:center;margin-bottom:16px;font-family:var(--font-display);color:var(--text-on-dark)">Interactive Map — Geolocated Entries</h3>
      <p style="text-align:center;font-size:14px;color:var(--text-on-dark-faint);margin-bottom:20px">Click markers to view entry details and navigate to the full article.</p>
      <div class="map-container" id="leafletMap" style="height:450px;border-radius:12px"></div>
    </div>
  </div>
</section>

<!-- ═══ SECTION 3 — ARCHIVE STATISTICS TABLE ══════════════ -->
<section class="about-section">
  <div class="container">
    <div class="section-heading" style="margin-bottom:40px">
      <h2>Archive Statistics</h2>
      <p>A breakdown of entries by tradition category.</p>
      <div class="gold-line"><span>✦</span></div>
    </div>

    <!-- Module 1.2: Full table — caption, colgroup, thead, tbody, tfoot, th scope, colspan -->
    <div style="overflow-x:auto">
      <table class="about-table">
        <caption>Knowledge Entries by Category — Current Archive Status</caption>
        <colgroup>
          <col style="width:28%">
          <col style="width:14%">
          <col style="width:14%">
          <col style="width:14%">
          <col style="width:15%">
          <col style="width:15%">
        </colgroup>
        <thead>
          <tr>
            <th scope="col">Category</th>
            <th scope="col">Approved</th>
            <th scope="col">Pending</th>
            <th scope="col">Total</th>
            <th scope="col">Top Region</th>
            <th scope="col">Avg. Votes</th>
          </tr>
        </thead>
        <tbody>
          <tr><td><strong style="color:var(--text-on-dark)">Ancient Crafts</strong></td>      <td>24</td><td>3</td><td>27</td><td>Asia</td><td>42</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Oral Traditions</strong></td>     <td>31</td><td>5</td><td>36</td><td>Africa</td><td>58</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Herbal Medicine</strong></td>     <td>18</td><td>2</td><td>20</td><td>Mediterranean</td><td>35</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Agricultural Lore</strong></td>   <td>15</td><td>4</td><td>19</td><td>South Asia</td><td>29</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Architecture</strong></td>        <td>12</td><td>1</td><td>13</td><td>Middle East</td><td>44</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Navigation</strong></td>          <td>9</td> <td>2</td><td>11</td><td>Pacific</td><td>71</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Language &amp; Scripts</strong></td><td>28</td><td>6</td><td>34</td><td>South America</td><td>63</td></tr>
          <tr><td><strong style="color:var(--text-on-dark)">Ritual &amp; Ceremony</strong></td><td>22</td><td>5</td><td>27</td><td>Central America</td><td>51</td></tr>
        </tbody>
        <tfoot>
          <tr>
            <td><strong>TOTAL</strong></td>
            <td><strong>159</strong></td>
            <td><strong>28</strong></td>
            <td colspan="3" style="text-align:center"><strong>187 entries across 8 traditions</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</section>

<!-- ═══ SECTION 4 — HOW IT WORKS + KEY TERMS ═════════════ -->
<section class="about-section about-section-alt">
  <div class="container">
    <div class="about-grid">

      <!-- Ordered list: How it works -->
      <div>
        <div class="section-heading">
          <h2>How It Works</h2>
          <div class="gold-line"><span>✦</span></div>
        </div>
        <!-- Module 1.2: <ol> ordered list -->
        <ol class="steps-list">
          <li>
            <div class="step-num">1</div>
            <div class="step-text"><strong>Register</strong> as a Keeper — free, no restrictions.</div>
          </li>
          <li>
            <div class="step-num">2</div>
            <div class="step-text"><strong>Submit</strong> a knowledge entry with title, summary, and full account.</div>
          </li>
          <li>
            <div class="step-num">3</div>
            <div class="step-text">An <strong>admin reviews</strong> and approves the entry.</div>
          </li>
          <li>
            <div class="step-num">4</div>
            <div class="step-text">The entry <strong>appears publicly</strong> in the archive.</div>
          </li>
          <li>
            <div class="step-num">5</div>
            <div class="step-text">Community members <strong>vote</strong> to Preserve or Dispute.</div>
          </li>
          <li>
            <div class="step-num">6</div>
            <div class="step-text">Members can <strong>comment</strong>, bookmark, and share entries.</div>
          </li>
        </ol>

        <div style="margin-top:28px">
          <p style="color:var(--text-on-dark-faint);font-size:.82rem;margin-bottom:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase">You can also submit:</p>
          <!-- Module 1.2: <ul> with nested list -->
          <ul style="padding-left:20px;color:var(--text-on-dark-muted);line-height:2.1;font-size:.9rem">
            <li>Dying crafts and artisanal techniques
              <ul style="padding-left:20px;list-style-type:circle;color:var(--text-on-dark-faint)">
                <li>Textile weaving methods</li>
                <li>Metalworking traditions</li>
                <li>Ceramic firing techniques</li>
              </ul>
            </li>
            <li>Oral traditions and storytelling forms</li>
            <li>Herbal and traditional medicine</li>
            <li>Agricultural practices and seed knowledge</li>
            <li>Endangered languages and scripts</li>
          </ul>
        </div>
      </div>

      <!-- Definition list: Key Terms -->
      <div>
        <div class="section-heading">
          <h2>Key Terms</h2>
          <div class="gold-line"><span>✦</span></div>
        </div>
        <!-- Module 1.2: <dl> definition list -->
        <dl class="def-list">
          <dt>Keeper</dt>
          <dd>A registered user who submits and curates knowledge entries in the archive.</dd>

          <dt>Entry</dt>
          <dd>A documented piece of lost or endangered knowledge, including title, summary, body, region, and era.</dd>

          <dt>Pending</dt>
          <dd>An entry submitted but not yet reviewed by an admin moderator.</dd>

          <dt>Approved</dt>
          <dd>An entry that passed admin review and is publicly visible in the archive.</dd>

          <dt>Preserve Vote ▲</dt>
          <dd>An upvote indicating the community considers this knowledge valuable and worth preserving.</dd>

          <dt>Dispute Vote ▼</dt>
          <dd>A downvote indicating factual concerns or inaccuracies in the entry.</dd>

          <dt>PDO</dt>
          <dd>PHP Data Objects — the interface used to safely query MySQL with prepared statements throughout this application.</dd>
        </dl>
      </div>

    </div>
  </div>
</section>

<!-- ═══ SECTION 5 — FAQ (details/summary) ════════════════ -->
<section class="about-section">
  <div class="container">
    <div style="max-width:760px;margin:0 auto">
      <div class="section-heading" style="text-align:center;margin-bottom:40px">
        <h2>Frequently Asked Questions</h2>
        <div class="gold-line"><span>✦</span></div>
      </div>

      <!-- Module 1.2: <details> + <summary> -->
      <details class="faq-item">
        <summary>Is Lost Knowledge free to use?</summary>
        <div class="faq-body">Yes — registration, browsing, submitting entries, voting, and commenting are completely free. There are no paywalls, ads, or premium tiers.</div>
      </details>

      <details class="faq-item">
        <summary>Who can submit an entry?</summary>
        <div class="faq-body">Any registered Keeper can submit entries. All submissions go through an admin review process before they appear publicly in the archive.</div>
      </details>

      <details class="faq-item">
        <summary>How is the information verified?</summary>
        <div class="faq-body">Admin moderators review every submission. The community can then vote to Preserve (upvote) or Dispute (downvote) entries — entries with consistent disputes are flagged for re-review.</div>
      </details>

      <details class="faq-item">
        <summary>Can I edit or delete my entries?</summary>
        <div class="faq-body">Yes. Keepers can edit their own entries from their dashboard. Edited entries return to pending status for re-review. Admins can delete any entry.</div>
      </details>

      <details class="faq-item">
        <summary>What makes a good entry?</summary>
        <div class="faq-body">The best entries include a specific title, a clear one-sentence summary, a detailed body (at least 100 words), the region of origin, and the historical era. Entries with sources or references are most trusted by the community.</div>
      </details>

      <details class="faq-item" open>
        <summary>What technologies power this archive?</summary>
        <div class="faq-body">PHP 8.2 + MySQL 8 on the backend, with PDO prepared statements for all database queries. The frontend is pure HTML5, CSS3 (Flexbox, animations, media queries), and Vanilla JavaScript — no frameworks. We also provide a REST JSON API at <code>/api/knowledge.php</code>.</div>
      </details>
    </div>
  </div>
</section>

<!-- ═══ SECTION 6 — TEAM ══════════════════════════════════ -->
<section class="about-section about-section-alt">
  <div class="container">
    <div class="section-heading" style="text-align:center;margin-bottom:40px">
      <h2>The Keepers Behind the Archive</h2>
      <div class="gold-line"><span>✦</span></div>
    </div>

    <div class="team-grid">
      <div class="team-card">
        <div class="team-av">A</div>
        <div class="team-name">Admin</div>
        <div class="team-role">Archive Curator</div>
      </div>
      <div class="team-card">
        <div class="team-av">W</div>
        <div class="team-name">waakmol</div>
        <div class="team-role">Navigation Keeper</div>
      </div>
      <div class="team-card">
        <div class="team-av">A</div>
        <div class="team-name">andinista</div>
        <div class="team-role">Scripts Keeper</div>
      </div>
      <div class="team-card">
        <div class="team-av">F</div>
        <div class="team-name">fermentrix</div>
        <div class="team-role">Medicine Keeper</div>
      </div>
      <div class="team-card">
        <div class="team-av">K</div>
        <div class="team-name">kendhang</div>
        <div class="team-role">Crafts Keeper</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ SECTION 7 — CONTACT FORM ══════════════════════════ -->
<section class="about-section">
  <div class="container">
    <div style="max-width:680px;margin:0 auto">
      <div class="section-heading" style="text-align:center;margin-bottom:40px">
        <h2>Contact &amp; Feedback</h2>
        <p>Questions, corrections, or suggestions? Reach out.</p>
        <div class="gold-line"><span>✦</span></div>
      </div>

      <!-- Flash messages from feedback_process.php -->
      <?php
        if (!empty($_SESSION['feedback_success'])) {
          echo '<div class="alert alert-success" data-autohide style="margin-bottom:20px">'
             . htmlspecialchars($_SESSION['feedback_success'])
             . '</div>';
          unset($_SESSION['feedback_success']);
        }
        $fbError = $_GET['feedback_error'] ?? '';
        if ($fbError) {
          echo '<div class="alert alert-error" style="margin-bottom:20px">'
             . htmlspecialchars(urldecode($fbError))
             . '</div>';
        }
      ?>

      <div class="contact-card">
        <!-- Module 1.2: All form input types — submits to PHP backend -->
        <form id="contactForm" method="POST" action="/lost-knowledge/feedback_process.php" novalidate>

          <fieldset style="border:1px solid var(--border-dark);border-radius:6px;padding:20px;margin-bottom:20px">
            <legend style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--amber);padding:0 8px">Personal Information</legend>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div class="form-group">
                <label for="cf-name">Full Name</label>
                <input type="text" id="cf-name" name="name" placeholder="Your full name" required>
              </div>
              <div class="form-group">
                <label for="cf-email">Email <span class="req">*</span></label>
                <input type="email" id="cf-email" name="email" placeholder="you@example.com" required>
              </div>
              <div class="form-group">
                <!-- Module 1.2: tel input -->
                <label for="cf-phone">Phone</label>
                <input type="tel" id="cf-phone" name="phone" placeholder="+91 98765 43210">
              </div>
              <div class="form-group">
                <!-- Module 1.2: number input -->
                <label for="cf-age">Your Age</label>
                <input type="number" id="cf-age" name="age" min="13" max="120" placeholder="25">
              </div>
            </div>
          </fieldset>

          <fieldset style="border:1px solid var(--border-dark);border-radius:6px;padding:20px;margin-bottom:20px">
            <legend style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--amber);padding:0 8px">Your Message</legend>

            <div class="form-group">
              <label for="cf-subject">Subject</label>
              <!-- Module 1.2: select with optgroup -->
              <select id="cf-subject" name="subject">
                <optgroup label="General">
                  <option value="">— Choose a topic —</option>
                  <option value="general">General Feedback</option>
                  <option value="question">Question</option>
                </optgroup>
                <optgroup label="Content">
                  <option value="suggest">Submit a Suggestion</option>
                  <option value="error">Report an Error</option>
                </optgroup>
                <optgroup label="Technical">
                  <option value="bug">Bug Report</option>
                  <option value="feature">Feature Request</option>
                </optgroup>
              </select>
            </div>

            <div class="form-group">
              <!-- Module 1.2: date input -->
              <label for="cf-date">Date of Occurrence <span style="color:var(--text-on-dark-faint);font-weight:400">(if reporting a bug)</span></label>
              <input type="date" id="cf-date" name="date">
            </div>

            <div class="form-group">
              <label for="cf-message">Message <span class="req">*</span></label>
              <textarea id="cf-message" name="message" rows="4" placeholder="Tell us what you think…" required></textarea>
            </div>
          </fieldset>

          <fieldset style="border:1px solid var(--border-dark);border-radius:6px;padding:20px;margin-bottom:20px">
            <legend style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--amber);padding:0 8px">Preferences</legend>

            <p style="font-size:.8rem;font-weight:600;color:var(--text-on-dark-faint);margin-bottom:10px;letter-spacing:.06em;text-transform:uppercase">How did you hear about us?</p>
            <!-- Module 1.2: radio buttons -->
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px">
              <label style="display:flex;align-items:center;gap:6px;font-size:.88rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="radio" name="source" value="social" style="accent-color:var(--amber)"> Social Media
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.88rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="radio" name="source" value="search" style="accent-color:var(--amber)"> Search Engine
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.88rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="radio" name="source" value="friend" style="accent-color:var(--amber)"> Friend
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.88rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="radio" name="source" value="other" style="accent-color:var(--amber)"> Other
              </label>
            </div>

            <!-- Module 1.2: range input -->
            <div class="form-group">
              <label for="cf-rating">Overall Rating: <strong id="ratingVal" style="color:var(--amber-light)">5</strong> / 10</label>
              <input type="range" id="cf-rating" name="rating" min="1" max="10" value="5"
                     style="width:100%;accent-color:var(--amber)"
                     oninput="document.getElementById('ratingVal').textContent=this.value">
            </div>

            <!-- Module 1.2: checkboxes -->
            <p style="font-size:.8rem;font-weight:600;color:var(--text-on-dark-faint);margin-bottom:10px;letter-spacing:.06em;text-transform:uppercase">I am interested in:</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="checkbox" name="interests[]" value="crafts" style="accent-color:var(--amber)"> Ancient Crafts
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="checkbox" name="interests[]" value="medicine" style="accent-color:var(--amber)"> Herbal Medicine
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="checkbox" name="interests[]" value="language" style="accent-color:var(--amber)"> Language &amp; Scripts
              </label>
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--text-on-dark-muted);cursor:pointer">
                <input type="checkbox" name="interests[]" value="navigation" style="accent-color:var(--amber)"> Navigation
              </label>
            </div>
          </fieldset>

          <!-- Module 1.2: datalist for autocomplete -->
          <div class="form-group" style="margin-bottom:20px">
            <label for="cf-country">Country</label>
            <input type="text" id="cf-country" name="country" list="countries" placeholder="Start typing…">
            <datalist id="countries">
              <option value="India">
              <option value="United States">
              <option value="United Kingdom">
              <option value="Germany">
              <option value="Japan">
              <option value="Brazil">
              <option value="Australia">
              <option value="Canada">
            </datalist>
          </div>

          <hr style="border:none;border-top:1px solid var(--border-dark);margin:20px 0">

          <div style="display:flex;gap:12px;justify-content:flex-end">
            <button type="reset" class="btn btn-ghost">Clear</button>
            <button type="submit" class="btn btn-amber">✦ Send Feedback</button>
          </div>

          <!-- Success shown via PHP session flash on page reload -->
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ═══ FOOTER ════════════════════════════════════════════ -->
<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-ornament">
      <span style="color:var(--gold);font-size:12px">✦</span>
    </div>
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo">
          <div class="footer-logo-icon">📖</div>
          <div class="footer-logo-name">Lost Knowledge</div>
        </div>
        <p class="footer-tagline">Preserving forgotten traditions, dying crafts, and ancient wisdom for future generations.</p>
        <div class="footer-est"><span>✦</span> Est. 2026 <span>✦</span></div>
      </div>
      <div class="footer-col">
        <h4>Archive</h4>
        <ul>
          <li><a href="/lost-knowledge/index.html">Browse Entries</a></li>
          <li><a href="/lost-knowledge/submit.php">Submit Entry</a></li>
          <li><a href="/lost-knowledge/index.html#archive">Categories</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Community</h4>
        <ul>
          <li><a href="/lost-knowledge/about.php">About Us</a></li>
          <li><a href="/lost-knowledge/register.html">Become a Keeper</a></li>
          <li><a href="/lost-knowledge/guidelines.html">Guidelines</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <ul>
          <li><a href="/lost-knowledge/help.html">Help Center</a></li>
          <li><a href="/lost-knowledge/privacy.html">Privacy Policy</a></li>
          <li><a href="/lost-knowledge/terms.html">Terms of Service</a></li>
          <li><a href="/lost-knowledge/admin/admin_dashboard.php" class="admin-link">Admin Dashboard</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 Lost Knowledge</span>
      <span class="footer-bottom-diamond">✦</span>
      <span>Preserving wisdom for tomorrow</span>
    </div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script>
  function filterRegion(region) {
    const result = document.getElementById('regionResult');
    const name   = document.getElementById('regionName');
    const link   = document.getElementById('regionLink');
    if (result && name) {
      name.textContent = region;
      result.style.display = 'flex';
      result.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }
    if (link) link.href = '/lost-knowledge/index.html?search=' + encodeURIComponent(region);
  }

  // Floating tooltip that follows mouse over image map areas
  (function() {
    const tip = document.getElementById('mapTooltip');
    if (!tip) return;
    document.querySelectorAll('#worldmap area').forEach(function(area) {
      area.addEventListener('mouseenter', function() {
        tip.textContent = this.getAttribute('title');
        tip.style.display = 'block';
      });
      area.addEventListener('mousemove', function(e) {
        tip.style.left = (e.clientX + 14) + 'px';
        tip.style.top  = (e.clientY - 36) + 'px';
      });
      area.addEventListener('mouseleave', function() {
        tip.style.display = 'none';
      });
    });

    // Rescale coords when image renders smaller than its natural 1200x600
    var img = document.getElementById('worldMapImg');
    if (!img) return;
    function scaleMap() {
      var sx = img.clientWidth  / (img.naturalWidth  || 1200);
      var sy = img.clientHeight / (img.naturalHeight || 600);
      if (sx === 0 || sy === 0) return;
      document.querySelectorAll('#worldmap area').forEach(function(area) {
        var orig = area.getAttribute('data-coords');
        if (!orig) { orig = area.getAttribute('coords'); area.setAttribute('data-coords', orig); }
        var scaled = orig.split(',').map(function(v, i) {
          return Math.round(Number(v) * (i % 2 === 0 ? sx : sy));
        }).join(',');
        area.setAttribute('coords', scaled);
      });
    }
    if (img.complete && img.naturalWidth) { scaleMap(); }
    else { img.addEventListener('load', scaleMap); }
    window.addEventListener('resize', scaleMap);
  })();
</script>
<script src="/lost-knowledge/assets/js/features.js"></script>
</body>
</html>
