// Shared mock data + real pricing math for every lab version.
// Money is integer nanodollars (1e-9 USD), as in ADR-0001.
(function () {
  const PRICING = {
    rates: { input_text_tokens: "5.00", input_image_tokens: "10.00", output_tokens: "40.00", reasoning_tokens: "12.50" },
    per_reference_image: 765,
    output: {
      low: { "1024x1024": 280, "1536x1024": 420, "1024x1536": 420 },
      medium: { "1024x1024": 1100, "1536x1024": 1650, "1024x1536": 1650 },
      high: { "1024x1024": 4300, "1536x1024": 6450, "1024x1536": 6450 },
    },
    reasoning: { low: { min: 0, max: 500 }, medium: { min: 200, max: 2000 }, high: { min: 1000, max: 8000 } },
  };

  // $X.XX per 1M tokens -> nanodollars per token = X.XX * 1000
  const perToken = (s) => Math.round(parseFloat(s) * 1000);
  const R = Object.fromEntries(Object.entries(PRICING.rates).map(([k, v]) => [k, perToken(v)]));

  const providerCost = (u) =>
    u.text * R.input_text_tokens + u.image * R.input_image_tokens + u.output * R.output_tokens + u.reasoning * R.reasoning_tokens;
  const markupUp = (n) => Math.ceil((n * 125) / 100);
  const markupDown = (n) => Math.floor((n * 125) / 100);

  function estimate(prompt, size, quality, refCount) {
    const chars = [...(prompt || "")].length;
    const est = Math.ceil(chars / 4);
    const textLow = Math.floor(est * 0.85), textHigh = Math.ceil(est * 1.15);
    const image = refCount * PRICING.per_reference_image;
    const output = PRICING.output[quality][size];
    const rs = PRICING.reasoning[quality];
    const pLow = providerCost({ text: textLow, image, output, reasoning: rs.min });
    const pHigh = providerCost({ text: textHigh, image, output, reasoning: rs.max });
    return {
      lowNano: markupDown(pLow), highNano: markupUp(pHigh), providerLow: pLow, providerHigh: pHigh,
      breakdown: [
        { label: "Prompt", tokens: textLow === textHigh ? `${textLow}` : `${textLow}–${textHigh}`, note: `${chars} chars ÷ 4, ±15%`, low: textLow * R.input_text_tokens, high: textHigh * R.input_text_tokens },
        { label: "Reference images", tokens: `${image}`, note: `${refCount} × 765`, low: image * R.input_image_tokens, high: image * R.input_image_tokens },
        { label: "Image output", tokens: `${output}`, note: `${quality}, ${size}`, low: output * R.output_tokens, high: output * R.output_tokens },
        { label: "Thinking", tokens: `${rs.min}–${rs.max}`, note: `${quality} range`, low: rs.min * R.reasoning_tokens, high: rs.max * R.reasoning_tokens },
      ],
    };
  }

  // Formatting
  const cents = (n, dir = "near") => {
    const d = n / 1e9;
    const c = dir === "up" ? Math.ceil(d * 100) / 100 : dir === "down" ? Math.floor(d * 100) / 100 : Math.round(d * 100) / 100;
    return "$" + c.toFixed(2);
  };
  const exact = (n) => {
    const neg = n < 0; n = Math.abs(n);
    const s = String(n).padStart(10, "0");
    return (neg ? "-$" : "$") + s.slice(0, -9) + "." + s.slice(-9);
  };

  // Placeholder image like Pixelforge's: abstract shapes captioned with the prompt.
  function placeholder(prompt, size, hue) {
    const [w, h] = size.split("x").map(Number);
    const vw = 400, vh = Math.round((400 * h) / w);
    const esc = prompt.replace(/[<&>"]/g, "").slice(0, 60);
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${vw} ${vh}'>
      <defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='hsl(${hue},70%,55%)'/><stop offset='1' stop-color='hsl(${(hue + 70) % 360},65%,30%)'/></linearGradient></defs>
      <rect width='100%' height='100%' fill='url(#g)'/>
      <circle cx='${vw * 0.3}' cy='${vh * 0.4}' r='${vh * 0.28}' fill='hsla(${(hue + 180) % 360},80%,70%,.45)'/>
      <rect x='${vw * 0.5}' y='${vh * 0.2}' width='${vw * 0.35}' height='${vh * 0.45}' rx='18' fill='hsla(${(hue + 40) % 360},90%,80%,.35)'/>
      <rect x='0' y='${vh - 40}' width='100%' height='40' fill='rgba(0,0,0,.45)'/>
      <text x='14' y='${vh - 15}' font-family='Helvetica,Arial' font-size='14' fill='white'>${esc}</text></svg>`;
    return "data:image/svg+xml;utf8," + encodeURIComponent(svg);
  }

  const now = Date.now();
  const ago = (s) => new Date(now - s * 1000).toISOString();

  function gen(o) {
    const e = estimate(o.prompt, o.size, o.quality, o.refs.length);
    const g = { ...o, estLow: e.lowNano, estHigh: e.highNano, actual: null, provider: null, capped: false, absorbed: 0, image: null };
    if (o.status === "succeeded") {
      const p = providerCost(o.usage);
      g.provider = p;
      const raw = markupUp(o.rateBump ? Math.round(p * o.rateBump) : p);
      g.actual = Math.min(raw, e.highNano);
      g.capped = raw > e.highNano;
      g.absorbed = g.capped ? raw - e.highNano : 0;
      if (o.rateBump) g.provider = Math.round(p * o.rateBump);
      g.image = o.expired ? null : placeholder(o.prompt, o.size, o.hue || 200);
    }
    return g;
  }

  const generations = [
    gen({ id: "g11", prompt: "A paper lantern festival over a canal, watercolor", size: "1536x1024", quality: "medium", refs: [], status: "waiting", waitReason: "busy", attempts: 4, createdAt: ago(25) }),
    gen({ id: "g10", prompt: "Isometric tiny greenhouse full of succulents", size: "1024x1024", quality: "high", refs: ["https://example.com/style-a.png"], status: "queued", createdAt: ago(9) }),
    gen({ id: "g9", prompt: "Portrait of a fox astronaut, studio lighting [slow]", size: "1024x1536", quality: "high", refs: [], status: "processing", createdAt: ago(48), startedAt: ago(41) }),
    gen({ id: "g8", prompt: "A lighthouse on a cliff at dawn, oil painting", size: "1536x1024", quality: "high", refs: ["https://example.com/style.png", "https://example.com/palette.png"], status: "succeeded", usage: { text: 12, image: 1530, output: 6450, reasoning: 3184 }, hue: 25, createdAt: ago(600), completedAt: ago(580) }),
    gen({ id: "g7", prompt: "Brutalist concrete library interior, morning haze", size: "1024x1024", quality: "medium", refs: [], status: "succeeded", usage: { text: 13, image: 0, output: 1100, reasoning: 1412 }, hue: 210, createdAt: ago(3600), completedAt: ago(3585) }),
    gen({ id: "g6", prompt: "Neon koi pond seen from above", size: "1024x1024", quality: "low", refs: [], status: "succeeded", usage: { text: 7, image: 0, output: 280, reasoning: 480 }, hue: 300, rateBump: 1.3, createdAt: ago(7200), completedAt: ago(7190) }),
    gen({ id: "g5", prompt: "Retro travel poster of a mountain railway [fail]", size: "1024x1536", quality: "medium", refs: [], status: "failed", error: { code: "generation_failed" }, createdAt: ago(9000), completedAt: ago(8990) }),
    gen({ id: "g4", prompt: "Macro shot of frost on a leaf", size: "1024x1024", quality: "high", refs: [], status: "failed", error: { code: "internal_error" }, createdAt: ago(10000), completedAt: ago(9980) }),
    gen({ id: "g3", prompt: "Something not allowed [blocked]", size: "1024x1024", quality: "medium", refs: [], status: "failed", error: { code: "content_policy_violation" }, createdAt: ago(20000), completedAt: ago(20000) }),
    gen({ id: "g2", prompt: "Desert caravan at night under the milky way", size: "1536x1024", quality: "medium", refs: [], status: "failed", error: { code: "too_busy" }, createdAt: ago(30000), completedAt: ago(29400) }),
    gen({ id: "g1", prompt: "Cat wearing a tiny wizard hat, children's book style", size: "1024x1024", quality: "medium", refs: [], status: "succeeded", expired: true, usage: { text: 13, image: 0, output: 1100, reasoning: 640 }, hue: 120, createdAt: ago(200000), completedAt: ago(199980) }),
  ];

  const ERRORS = {
    generation_failed: { title: "Couldn't draw this prompt", body: "Pixelforge couldn't produce an image for this prompt. Try rewording it." },
    internal_error: { title: "Pixelforge had a problem", body: "Something went wrong on Pixelforge's side. Trying again usually works." },
    content_policy_violation: { title: "Prompt blocked", body: "Pixelforge rejected this prompt. Try rewording it." },
    daily_limit_exceeded: { title: "Daily limit reached", body: "Pixelforge's daily limit is used up. Try again after midnight UTC." },
    too_busy: { title: "Pixelforge was too busy", body: "We couldn't get a slot after 10 minutes of trying. Try again." },
  };

  const charged = generations.filter((g) => g.status === "succeeded").reduce((s, g) => s + g.actual, 0);
  const ledger = [
    { at: ago(250000), reason: "Sign-up bonus", amount: 5_000_000_000 },
    { at: ago(199980), reason: "Generation charge", gen: "g1", amount: -generations.find((g) => g.id === "g1").actual },
    { at: ago(150000), reason: "Bought $10 credit pack", amount: 10_000_000_000 },
    ...generations.filter((g) => g.status === "succeeded" && g.id !== "g1").map((g) => ({ at: g.completedAt, reason: "Generation charge", gen: g.id, amount: -g.actual })),
  ].sort((a, b) => (a.at < b.at ? 1 : -1));
  const balance = ledger.reduce((s, e) => s + e.amount, 0);
  const inProgress = generations.filter((g) => ["waiting", "queued", "processing"].includes(g.status));
  const held = inProgress.reduce((s, g) => s + g.estHigh, 0);

  // Admin report (UTC days by completion). Synthetic but internally consistent.
  const days = [];
  for (let i = 0; i < 10; i++) {
    const d = new Date(now - i * 86400000).toISOString().slice(0, 10);
    const gens = i === 3 ? 0 : 3 + ((i * 7) % 9);
    const provider = gens * (55_000_000 + ((i * 13_370_517) % 90_000_000));
    const cappedCount = i === 0 ? 1 : i === 6 ? 2 : 0;
    const absorbed = cappedCount * 1_840_250;
    const chargedDay = markupUp(provider) - absorbed;
    const estLow = Math.floor(chargedDay * 0.78), estHigh = Math.ceil(chargedDay * 1.31);
    const pixelforge = i === 1 ? provider + 4_200_000 : provider; // one mismatch to show the warning
    days.push({ date: d, gens, charged: chargedDay, provider, profit: chargedDay - provider, cappedCount, absorbed, estLow, estHigh, pixelforge });
  }
  const users = [
    { name: "Ada Lovelace", email: "ada@example.com", gens: 24, charged: 2_913_400_125, provider: 2_331_710_100, cappedCount: 1, absorbed: 1_840_250, estLow: 2_270_000_000, estHigh: 3_820_000_000 },
    { name: "Grace Hopper", email: "grace@example.com", gens: 17, charged: 1_402_118_750, provider: 1_121_695_000, cappedCount: 0, absorbed: 0, estLow: 1_090_000_000, estHigh: 1_870_000_000 },
    { name: "Grader", email: "grader@example.com", gens: 9, charged: 611_250_000, provider: 490_472_250, cappedCount: 2, absorbed: 3_680_500, estLow: 470_000_000, estHigh: 820_000_000 },
  ].map((u) => ({ ...u, profit: u.charged - u.provider }));

  window.LAB = {
    PRICING, estimate, cents, exact, placeholder, generations, ERRORS, ledger, balance, held,
    available: balance - held, inProgress, packs: [5, 10, 25], days, users,
    rangePos: (g) => (g.actual == null ? null : (g.actual - g.estLow) / Math.max(1, g.estHigh - g.estLow)),
    user: { name: "Ada Lovelace", email: "ada@example.com", isAdmin: true },
  };
})();
