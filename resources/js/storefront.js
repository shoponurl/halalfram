/*
 * Storefront behaviour carried over from the original single-file design.
 * Sprint 00: runs on the built-in demo catalog. Sprint 01–02 replace it with the Laravel API.
 */
(() => {
  'use strict';

  /* =====================================================
     CONFIG
     ===================================================== */
  const CONFIG = {
    currency: 'USD',
    locale: 'en-US',
    freeShipAt: 99,      // free cold-chain delivery threshold
    shipFee: 9.99,
    cutoffHour: 14,      // same-day cutoff (2 PM)
    pageSize: 8,
    // Demo promo codes — edit, add or remove to match your real offers
    coupons: {
      WELCOME10:    { type: 'pct', value: 10, label: '10% off your order' },
      FREEDELIVERY: { type: 'ship', label: 'Free cold-chain delivery' },
    },
    // ZIP prefixes you deliver to (190xx = Philadelphia suburbs incl. Upper Darby, 191xx = Philadelphia) — adjust to your real area
    deliveryZipPrefixes: ['190', '191'],
    // WhatsApp number, digits only with country code (e.g. '12675550123'). Leave '' to hide the chat button.
    whatsapp: '12673073777',
  };
  const LB_PER_KG = 2.20462;
  const WEIGHTS = { lb: [1, 2, 5], kg: [0.5, 1, 2] };   // weight pill presets per unit
  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Unsplash photo ID → sized URL; full URLs and uploaded file paths are used as-is
  const img = (id, w = 640) => /^(https?:)?\/|\.(jpe?g|png|webp|gif|avif)$/i.test(id) ? id : `https://images.unsplash.com/photo-${id}?w=${w}&q=72&auto=format&fit=crop`;

  /* =====================================================
     CATALOG (demo data — replace with your inventory/API)
     price = per lb for weight-sold items, per animal/share for `sold: 'unit'`.
     stock = units left from today's batch (drives the urgency meter).
     ===================================================== */
  const CATEGORIES = [
    { id: 'beef',      name: 'Beef',                icon: 'beef',      img: '1551028150-64b9f398f678',  blurb: 'Steaks, curry cuts, mince' },
    { id: 'lamb',      name: 'Lamb & Mutton',       icon: 'sprout',    img: '1574672280600-4accfa5b6f98', blurb: 'Chops, legs, goat khasi' },
    { id: 'poultry',   name: 'Poultry',             icon: 'drumstick', img: '1587593810167-a84920ea0781', blurb: 'Live birds, slaughtered fresh' },
    { id: 'specialty', name: 'Specialty & Qurbani', icon: 'gem',       img: '1524024973431-2ad916746881', blurb: 'Whole animals, premium cuts' },
    { id: 'marinades', name: 'Marinades & BBQ',     icon: 'flame',     img: '1603360946369-dc9bb6258143', blurb: 'Tikka, tandoori, kebabs' },
  ];
  const CAT_INFO = {
    beef:      { cook: 'Grill, sear or slow-cook',  keeps: '3 days chilled · 6 mo frozen' },
    lamb:      { cook: 'Roast, braise or chargrill', keeps: '3 days chilled · 6 mo frozen' },
    poultry:   { cook: 'Roast, curry or air-fry',    keeps: '2 days chilled · 4 mo frozen' },
    specialty: { cook: 'Per your processing choice', keeps: 'Delivered chilled within 48h' },
    marinades: { cook: 'Grill, oven or tandoor',     keeps: '2 days chilled · freezes well' },
  };

  const PRODUCTS = [
    { id: 'b-ribeye',  cat: 'beef',      name: 'Ribeye Steak, Bone-in',      img: '1603048297172-c92544798d5a', price: 18.99, was: 21.99, tag: 'Best seller', stock: 6, desc: 'Grass-fed, dry-chilled 48h. Rich marbling for the grill.', cuts: ['1" steaks', '1.5" steaks', 'Whole piece'], kw: 'steak rib' },
    { id: 'b-curry',   cat: 'beef',      name: 'Beef Curry Cut, Bone-in',    img: '1602470520998-f4a52199a3d6', price: 7.49,  desc: 'Shoulder & shin, cut for slow-cooked curries and nihari.', cuts: ['Small pieces', 'Large pieces', 'Boneless'], kw: 'curry stew nihari' },
    { id: 'm-tikka',   cat: 'marinades', name: 'Chicken Tikka, Marinated',   img: '1628294895950-9805252327bc', price: 7.99,  tag: 'New', desc: 'Boneless thigh in yoghurt, kashmiri chilli & garam masala.', kw: 'tikka bbq spicy' },
    { id: 'l-chops',   cat: 'lamb',      name: 'Lamb Rib Chops',             img: '1615937657715-bc7b4b7962c1', price: 17.99, tag: "Butcher's pick", stock: 4, desc: 'Frenched, pasture-raised lamb. Sear hot, rest, enjoy.', cuts: ['Single-bone', 'Double-bone', 'Whole rack'], kw: 'chop rack' },
    { id: 'p-whole',   cat: 'poultry',   name: 'Whole Chicken, Air-chilled', img: '1587593810167-a84920ea0781', price: 3.49,  was: 3.99, desc: 'Hand-slaughtered, air-chilled — no added water.', cuts: ['Whole', '8-piece', 'Curry cut, skinless'], kw: 'chicken whole roast' },
    { id: 'b-mince',   cat: 'beef',      name: 'Lean Beef Mince 85/15',      img: '1568901346375-23c9450c58cd', price: 6.99,  was: 7.99, desc: 'Ground fresh every morning. Kebabs, keema, smash burgers.', cuts: ['Fine mince', 'Coarse mince', '4 oz patties'], kw: 'mince ground keema burger' },
    { id: 's-goat',    cat: 'specialty', name: 'Qurbani Goat — Whole',       img: '1524024973431-2ad916746881', price: 349,   sold: 'unit', unitLabel: 'animal', preorder: true, stock: 9, desc: 'Healthy, age-verified goat. Slaughtered on Eid, cut & delivered in 48h.', kw: 'qurbani udhiyah eid goat' },
    { id: 'l-goat',    cat: 'lamb',      name: 'Goat Curry Cut (Khasi)',     img: '1545247181-516773cae754',    price: 10.49, desc: 'Young castrated goat, bone-in pieces — tender and mild.', cuts: ['Curry cut', 'Biryani cut', 'Boneless'], kw: 'goat mutton curry biryani' },
    { id: 'b-tender',  cat: 'beef',      name: 'Beef Tenderloin (Filet)',    img: '1613454320437-0c228c8b1723', price: 29.99, tag: 'Premium', stock: 3, desc: 'The most tender cut, trimmed of silverskin by hand.', cuts: ['Medallions', 'Whole, trimmed'], kw: 'filet steak' },
    { id: 'p-breast',  cat: 'poultry',   name: 'Chicken Breast, Boneless',   img: '1532550907401-a500c9a57435', price: 5.99,  desc: 'Skinless fillets, trimmed. Lean, versatile, weeknight hero.', cuts: ['Whole fillets', 'Butterflied', 'Diced'], kw: 'chicken breast' },
    { id: 'm-seekh',   cat: 'marinades', name: 'Beef Kebab Skewers',         img: '1603360946369-dc9bb6258143', price: 11.99, desc: 'Hand-threaded sirloin with onion & peppers, bihari spice.', kw: 'kebab skewer bbq' },
    { id: 'l-leg',     cat: 'lamb',      name: 'Lamb Leg, Bone-in',          img: '1574672280600-4accfa5b6f98', price: 11.99, desc: 'Roast-ready for raan or Sunday lunch. Serves 6–8.', cuts: ['Whole leg', 'Half leg', 'Curry cut'], kw: 'leg roast raan' },
    { id: 's-cow',     cat: 'specialty', name: 'Qurbani Cow — 1/7 Share',   img: '1570042225831-d98fa7577f1e', price: 289,   sold: 'unit', unitLabel: 'share', preorder: true, stock: 12, desc: 'One of seven shares in a healthy cow. Approx. 45–55 lb of meat.', kw: 'qurbani share cow eid' },
    { id: 'p-desi',    cat: 'poultry',   name: 'Desi Free-range Chicken',    img: '1598103442097-8b74394b95c6', price: 6.49,  tag: 'Limited', stock: 5, desc: 'Slow-grown village breed. Firmer texture, deeper flavour.', cuts: ['Whole', 'Curry cut'], kw: 'desi chicken free range' },
    { id: 'b-tbone',   cat: 'beef',      name: 'T-Bone Steak',               img: '1551028150-64b9f398f678',    price: 16.49, desc: 'Strip and filet in one, cut thick for the pan.', cuts: ['1" steaks', '1.5" steaks'], kw: 'steak tbone' },
    { id: 'm-tandoori',cat: 'marinades', name: 'Tandoori Chicken Legs',      img: '1610057099443-fde8c4d50f91', price: 6.49,  desc: 'Scored bone-in legs, 24h in tandoori masala. Oven or grill.', kw: 'tandoori chicken bbq' },
    { id: 's-wagyu',   cat: 'specialty', name: 'Wagyu-Cross Striploin',      img: '1546964124-0cce460f38ef',    price: 34.99, tag: 'Premium', stock: 2, desc: 'Marble score 6+. Zabiha from a single partner herd.', cuts: ['1" steaks', 'Whole piece'], kw: 'wagyu steak striploin' },
    { id: 'l-mutton',  cat: 'lamb',      name: 'Mutton Shoulder, Diced',     img: '1596797038530-2c107229654b', price: 9.49,  desc: 'Mature mutton for rich, slow-braised karahi and korma.', kw: 'mutton karahi korma' },
    { id: 'p-wings',   cat: 'poultry',   name: 'Chicken Party Wings',        img: '1623653387945-2fd25214f8fc', price: 4.49,  desc: 'Split flats & drums, ready for the air-fryer.', kw: 'wings chicken' },
    { id: 's-sheep',   cat: 'specialty', name: 'Qurbani Sheep — Whole',      img: '1484557985045-edf25e08da73', price: 329,   sold: 'unit', unitLabel: 'animal', preorder: true, stock: 7, desc: 'Well-fed sheep, meets all Udhiyah conditions. Donation option.', kw: 'qurbani sheep eid' },
    { id: 'b-brisket', cat: 'beef',      name: 'Brisket Flat, Smoker-ready', img: '1529692236671-f1f6cf9683ba', price: 9.99,  desc: 'Point-trimmed flat with a clean fat cap.', kw: 'brisket bbq smoke' },
    { id: 'm-shish',   cat: 'marinades', name: 'Lamb Shish, BBQ-ready',      img: '1555939594-58d7cb561ad1',    price: 13.49, desc: 'Leg cubes in garlic, cumin & lemon — skewer and grill.', kw: 'shish lamb bbq kebab' },
  ];
  const QUICK_CUTS = ['Ribeye', 'Curry cut', 'Mince', 'Lamb chops', 'Whole chicken', 'Kebab', 'Qurbani'];

  /* =====================================================
     PRODUCT DETAILS CONTENT (product page)
     about      → full description lead
     highlights → "Why you'll love it"
     specs      → product-specific specification rows
     cook       → method / target temperature / butcher's tip
     ===================================================== */
  const DETAILS = {
    'b-ribeye': {
      about: 'Cut from the rib section, our bone-in ribeye carries the richest marbling on the animal. The bone insulates the meat as it cooks, keeping it juicy, while the fat cap renders into a crisp, beefy crust. Every steak is hand-cut from grass-fed beef that has been dry-chilled for 48 hours to firm the texture and deepen the flavour.',
      highlights: ['Heavy, even marbling for a buttery bite', 'Bone-in for extra flavour and juiciness', 'Dry-chilled 48h and hand-cut to your thickness'],
      specs: { Animal: 'Beef (grass-fed)', Primal: 'Rib, bone-in', 'Standard thickness': '1" (1.5" or whole piece on request)', 'Fat trim': '¼" cap left on', Ageing: '48h dry-chill' },
      cook: { method: 'Sear in a very hot cast-iron pan or over direct grill heat, 3–4 min per side.', temp: '54–57 °C / 130–135 °F for medium-rare', tip: 'Salt 40 minutes ahead and rest 5 minutes before slicing against the grain.' },
    },
    'b-curry': {
      about: 'A mix of shoulder (chuck) and shin, cut into bone-in pieces sized for South Asian curries, nihari and haleem. The connective tissue melts during slow cooking into a rich, glossy gravy, and the marrow bones add a depth you simply can’t get from boneless meat.',
      highlights: ['Bone-in pieces for a full-bodied gravy', 'Ideal for nihari, bhuna and pressure-cooking', 'Choose small, large or boneless pieces'],
      specs: { Animal: 'Beef', Primal: 'Chuck & shin', Bone: 'Bone-in (boneless option)', 'Piece size': 'Approx. 1.5–2" small · 2.5–3" large', 'Fat trim': 'Lightly trimmed' },
      cook: { method: 'Brown in batches, then simmer covered for 2–2½ h (or 35–40 min in a pressure cooker).', temp: 'Cook until fork-tender', tip: 'Rinse briefly and pat dry before browning for a cleaner, clearer gravy.' },
    },
    'm-tikka': {
      about: 'Boneless chicken thigh, hand-trimmed and marinated for at least 12 hours in thick yoghurt, Kashmiri chilli, ginger, garlic, lemon and our house garam masala. Thigh stays juicy under high heat, so it chars beautifully on the grill, in a tandoor or under a hot oven broiler.',
      highlights: ['Marinated 12h+ in our house spice blend', 'Boneless thigh — juicy, forgiving and quick', 'Skewer-ready, bite-size pieces'],
      specs: { Animal: 'Chicken', Cut: 'Boneless thigh', Marinade: 'Yoghurt, Kashmiri chilli, ginger, garlic, lemon, garam masala', Heat: 'Medium', Allergens: 'Milk' },
      cook: { method: 'Thread onto skewers and grill or broil for 10–12 min, turning once.', temp: '74 °C / 165 °F internal', tip: 'Baste with melted butter in the last minute for extra char.' },
    },
    'l-chops': {
      about: 'Frenched rib chops from pasture-raised lamb, cut from the rack and cleaned down to the bone by hand. Mild, sweet and tender, they cook in minutes and look spectacular on the plate — perfect for Eid dinners and special occasions.',
      highlights: ['Frenched by hand for a clean presentation', 'Pasture-raised lamb — mild and sweet', 'Ready in under 8 minutes'],
      specs: { Animal: 'Lamb (pasture-raised)', Primal: 'Rack (rib)', Style: 'Frenched', 'Chop thickness': 'Approx. ¾–1"', 'Fat trim': 'Trimmed, thin cap' },
      cook: { method: 'Sear for 2–3 min per side in a hot pan, then rest.', temp: '57–60 °C / 135–140 °F for medium-rare', tip: 'Rub with garlic, rosemary and a pinch of cumin before cooking.' },
    },
    'p-whole': {
      about: 'A whole bird, hand-slaughtered and air-chilled rather than water-chilled — so there’s no absorbed water, the skin crisps properly and the flavour stays concentrated. Roast it whole, or ask us to cut it into 8 pieces or a skinless curry cut.',
      highlights: ['Air-chilled — no added water', 'Crispier skin and purer flavour', 'Whole, 8-piece or skinless curry cut'],
      specs: { Animal: 'Chicken', 'Average bird': 'Approx. 3–3.5 lb', Chilling: 'Air-chilled', Giblets: 'Removed (available on request)', Skin: 'Skin-on (skinless for curry cut)' },
      cook: { method: 'Roast at 220 °C / 425 °F for about 60–75 minutes.', temp: '74 °C / 165 °F in the thickest part of the thigh', tip: 'Pat dry and salt the day before for the crispiest skin.' },
    },
    'b-mince': {
      about: 'Beef chuck and trimmings ground fresh every morning to an 85/15 lean-to-fat ratio — lean enough for keema and kebabs, with enough fat to keep smash burgers juicy. Choose fine for seekh kebabs and koftas, coarse for chilli and bolognese, or pre-formed 4 oz patties.',
      highlights: ['Ground fresh every morning', '85/15 — the sweet spot for flavour', 'Fine, coarse or 4 oz patties'],
      specs: { Animal: 'Beef', Source: 'Chuck & trim', 'Lean ratio': '85% lean / 15% fat', Grind: 'Fine or coarse', 'Patty size': '4 oz (113 g)' },
      cook: { method: 'Brown in a hot pan, breaking it up as it cooks; for burgers, smash onto a very hot griddle.', temp: '71 °C / 160 °F', tip: 'Use within 24 hours of delivery, or freeze flat in portions.' },
    },
    's-goat': {
      about: 'Reserve a healthy, age-verified goat that meets every Udhiyah condition — at least one year old, sound and free from defects. Your animal is slaughtered by hand on Eid day after Salah with your name recited, then processed exactly the way you choose and delivered chilled within 48 hours.',
      highlights: ['Meets all Udhiyah conditions (age & health checked)', 'Slaughtered on Eid day after Salah, your name recited', 'Processed your way — or donated on your behalf'],
      specs: { Animal: 'Goat', Age: '1 year +', 'Typical live weight': '55–75 lb', 'Expected meat yield': 'Approx. 45–50% of live weight', Processing: 'Curry cut, mixed cuts, halves or donation' },
      cook: { method: 'Slow-cook the curry cuts; roast or grill the leg and chops.', temp: 'Cook curries until fork-tender', tip: 'Portion into meal-size bags before freezing.' },
    },
    'l-goat': {
      about: 'Tender meat from young castrated male goats (khasi), prized for its mild flavour and lack of gaminess. Cut bone-in to the classic curry size, or choose a larger biryani cut or boneless pieces.',
      highlights: ['Young khasi goat — mild, tender, never gamey', 'Bone-in for the richest curries', 'Curry, biryani or boneless cut'],
      specs: { Animal: 'Goat (khasi)', Primal: 'Shoulder, leg & ribs', Bone: 'Bone-in (boneless option)', 'Piece size': 'Approx. 1.5" curry · 2.5" biryani', 'Fat trim': 'Lightly trimmed' },
      cook: { method: 'Brown with onions and spices, then simmer covered for 1–1½ h.', temp: 'Cook until the meat pulls from the bone', tip: 'Marinate overnight in yoghurt with a little raw papaya for extra tenderness.' },
    },
    'b-tender': {
      about: 'The tenderloin runs beneath the spine and does almost no work, which makes it the most tender cut on the animal. We remove the chain and silverskin by hand, leaving a clean, even log you can roast whole or slice into filet medallions.',
      highlights: ['The most tender cut on the animal', 'Silverskin and chain removed by hand', 'Whole trimmed roast or 1½" medallions'],
      specs: { Animal: 'Beef', Primal: 'Short loin / sirloin', Trim: 'Fully peeled, chain removed', 'Medallion thickness': 'Approx. 1½"', Bone: 'Boneless' },
      cook: { method: 'Sear medallions 3 min per side, or roast whole at 220 °C / 425 °F for 20–25 min.', temp: '52–54 °C / 125–130 °F for rare to medium-rare', tip: 'Tie a whole tenderloin with string so it cooks evenly.' },
    },
    'p-breast': {
      about: 'Skinless, boneless breast fillets trimmed of fat and tendons. Lean and endlessly versatile — grill, pan-fry, bake or dice for karahi and stir-fries. Choose whole fillets, butterflied for quicker cooking, or diced.',
      highlights: ['Hand-trimmed skinless fillets', 'High protein, low fat', 'Whole, butterflied or diced'],
      specs: { Animal: 'Chicken', Cut: 'Breast fillet', Skin: 'Skinless', Bone: 'Boneless', 'Average fillet': 'Approx. 7–9 oz' },
      cook: { method: 'Pan-sear 5–6 min per side, or bake at 200 °C / 400 °F for about 20 min.', temp: '74 °C / 165 °F internal', tip: 'Brine in salted water for 30 minutes to keep it juicy.' },
    },
    'm-seekh': {
      about: 'Cubes of beef sirloin threaded by hand with red onion and bell pepper, coated in a smoky Bihari-style marinade of mustard oil, raw papaya, chilli and roasted spices. Ready for the grill straight from the box.',
      highlights: ['Hand-threaded sirloin, onion & peppers', 'Smoky Bihari-style marinade', 'Grill-ready — no prep needed'],
      specs: { Animal: 'Beef', Cut: 'Sirloin cubes', Marinade: 'Mustard oil, raw papaya, chilli, roasted spices', Heat: 'Medium-hot', 'Skewers per lb': 'Approx. 4' },
      cook: { method: 'Grill over high heat for 8–10 min, turning every 2 minutes.', temp: '63 °C / 145 °F for medium', tip: 'Soak wooden skewers first, and rest the kebabs 2 minutes before serving.' },
    },
    'l-leg': {
      about: 'A whole bone-in leg of lamb — the classic centrepiece for raan, Sunday roasts and family gatherings. The bone keeps the meat moist through long cooking, and a thin layer of fat bastes the joint as it roasts.',
      highlights: ['Showpiece roast — serves 6–8', 'Bone-in for flavour and moisture', 'Whole, half or curry cut'],
      specs: { Animal: 'Lamb', Primal: 'Leg', Bone: 'Bone-in', 'Average whole leg': 'Approx. 5–7 lb', 'Fat trim': 'Thin cap left on' },
      cook: { method: 'Marinate overnight, then roast at 160 °C / 325 °F for 20–25 min per lb.', temp: '60 °C / 140 °F for medium — or cook low and slow for pull-apart raan', tip: 'Score deeply so the marinade reaches the bone.' },
    },
    's-cow': {
      about: 'One of seven equal shares in a healthy, well-fed cow that meets every Udhiyah condition. The animal is slaughtered by hand on Eid day, then the meat is weighed and divided equally between the seven shareholders — so every share is fair and transparent.',
      highlights: ['1/7 share — the Sunnah way to share a cow', 'Weighed and divided equally, with records', 'Approx. 45–55 lb of meat per share'],
      specs: { Animal: 'Cow', Age: '2 years +', Share: '1/7 of the animal', 'Expected meat': 'Approx. 45–55 lb per share', Processing: 'Curry cut, mixed cuts or donation' },
      cook: { method: 'Your share arrives cut and labelled — slow-cook curry cuts, grill the steaks.', temp: 'Cook curries until fork-tender', tip: 'Plan freezer space: a share fills roughly 3–4 grocery bags.' },
    },
    'p-desi': {
      about: 'A slow-grown, free-range village breed raised outdoors for longer than commercial birds. The meat is leaner and firmer, with a deep, old-fashioned chicken flavour that shines in desi-style curries and clear yakhni broths.',
      highlights: ['Free-range, slow-grown village breed', 'Firmer texture, deeper flavour', 'Perfect for curry and yakhni'],
      specs: { Animal: 'Chicken (desi breed)', 'Average bird': 'Approx. 2–2.5 lb', Rearing: 'Free-range, slow-grown', Skin: 'Skin-on (skinless for curry cut)', Chilling: 'Air-chilled' },
      cook: { method: 'Simmer a little longer than regular chicken — 45–60 min for curries.', temp: '74 °C / 165 °F', tip: 'Pressure-cook for 15 minutes for tender meat in less time.' },
    },
    'b-tbone': {
      about: 'Two steaks in one: tender filet on one side of the T-shaped bone and flavourful strip on the other. Cut thick from the short loin so both sides cook evenly in a hot pan or on the grill.',
      highlights: ['Filet and strip on one bone', 'Cut thick for an even cook', 'A big, shareable steak'],
      specs: { Animal: 'Beef', Primal: 'Short loin', Bone: 'Bone-in (T-bone)', 'Standard thickness': '1" (1.5" option)', 'Fat trim': '¼" cap' },
      cook: { method: 'Grill with the filet side away from the hottest heat, 4–5 min per side.', temp: '54–57 °C / 130–135 °F for medium-rare', tip: 'Rest 5 minutes, then carve each side off the bone before slicing.' },
    },
    'm-tandoori': {
      about: 'Whole chicken legs, deeply scored and marinated for 24 hours in yoghurt, tandoori masala, Kashmiri chilli, garlic and lemon. The bone-in legs stay succulent in a hot oven, on the grill or in an air-fryer.',
      highlights: ['24-hour marinade — flavour to the bone', 'Bone-in legs stay juicy', 'Oven, grill or air-fryer ready'],
      specs: { Animal: 'Chicken', Cut: 'Whole leg (thigh + drumstick)', Marinade: 'Yoghurt, tandoori masala, Kashmiri chilli, garlic, lemon', Heat: 'Medium', Allergens: 'Milk' },
      cook: { method: 'Roast at 230 °C / 450 °F for 30–35 min, or air-fry for 22–25 min.', temp: '74 °C / 165 °F at the bone', tip: 'Finish with a squeeze of lemon and a pinch of chaat masala.' },
    },
    's-wagyu': {
      about: 'Striploin from wagyu-cross cattle raised by a single partner herd, with fine, abundant marbling (score 6+). Rich and buttery, it needs little more than salt and a very hot pan. Cut into 1" steaks or kept whole for roasting.',
      highlights: ['Marble score 6+ — rich and buttery', 'Single partner herd, fully traceable', 'Hand-cut 1" steaks or whole roast'],
      specs: { Animal: 'Beef (wagyu-cross)', Primal: 'Short loin (striploin)', 'Marble score': '6+', 'Standard thickness': '1"', Ageing: 'Dry-chilled' },
      cook: { method: 'Sear in a very hot pan for 2–3 min per side — no oil needed.', temp: '52–55 °C / 125–130 °F', tip: 'Slice thinly and serve in small portions — a little goes a long way.' },
    },
    'l-mutton': {
      about: 'Diced shoulder from mature sheep (mutton), which has a fuller, more robust flavour than lamb. Mutton rewards slow cooking — it becomes meltingly tender in karahi, korma and rogan josh.',
      highlights: ['Mature mutton — bold, traditional flavour', 'Diced shoulder for slow braising', 'Made for karahi, korma and rogan josh'],
      specs: { Animal: 'Sheep (mutton)', Primal: 'Shoulder', Bone: 'Boneless, diced', 'Piece size': 'Approx. 1.5"', 'Fat trim': 'Lightly trimmed' },
      cook: { method: 'Braise covered on low heat for 1½–2 hours.', temp: 'Cook until fork-tender', tip: 'Stir in a spoon of yoghurt towards the end for a silky sauce.' },
    },
    'p-wings': {
      about: 'Chicken wings split into flats and drumettes, with the tips removed. Air-fryer and oven friendly, they crisp up beautifully with nothing but salt — or toss them in your favourite marinade or sauce.',
      highlights: ['Split flats & drums, tips removed', 'Air-fryer ready', 'Party-size portions'],
      specs: { Animal: 'Chicken', Cut: 'Wing (flat + drumette)', Skin: 'Skin-on', 'Pieces per lb': 'Approx. 10–12', Chilling: 'Air-chilled' },
      cook: { method: 'Air-fry at 200 °C / 400 °F for 20–24 min, shaking halfway.', temp: '74 °C / 165 °F', tip: 'Toss with a little baking powder and salt for extra-crisp skin.' },
    },
    's-sheep': {
      about: 'A well-fed sheep that meets all Udhiyah conditions, reserved in your name for Eid. After hand slaughter on Eid day, we process it to your instructions — or distribute the meat to families in need and send you photo confirmation.',
      highlights: ['Meets all Udhiyah conditions', 'Donation option with photo confirmation', 'Processed and delivered chilled within 48h'],
      specs: { Animal: 'Sheep', Age: '1 year +', 'Typical live weight': '70–100 lb', 'Expected meat yield': 'Approx. 45–50% of live weight', Processing: 'Curry cut, mixed cuts, halves or donation' },
      cook: { method: 'Slow-cook the curry cuts; roast the leg and shoulder.', temp: 'Cook curries until fork-tender', tip: 'Ask us to pack the liver and kidneys separately for Eid breakfast.' },
    },
    'b-brisket': {
      about: 'The flat of the brisket, separated from the point and left with an even fat cap for smoking or slow braising. Brisket needs time — but after a long, low cook it slices into tender, beefy ribbons.',
      highlights: ['Point removed for an even thickness', 'Clean ¼" fat cap for smoking', 'Great for BBQ or pot roast'],
      specs: { Animal: 'Beef', Primal: 'Brisket (flat)', Bone: 'Boneless', 'Average piece': 'Approx. 4–6 lb', 'Fat trim': '¼" cap' },
      cook: { method: 'Smoke at 110–120 °C / 225–250 °F for 1–1¼ h per lb, or braise covered for 3–4 h.', temp: '93–95 °C / 200–203 °F, probe-tender', tip: 'Wrap in butcher paper at 74 °C / 165 °F to push through the stall.' },
    },
    'm-shish': {
      about: 'Cubes of lamb leg marinated in garlic, cumin, lemon, olive oil and fresh herbs — a Middle Eastern classic. Thread onto skewers with vegetables, or cook straight in a hot pan for quick wraps.',
      highlights: ['Trimmed lamb leg cubes', 'Garlic, cumin & lemon marinade', 'Skewer, grill or pan-fry'],
      specs: { Animal: 'Lamb', Cut: 'Leg cubes', Marinade: 'Garlic, cumin, lemon, olive oil, herbs', Heat: 'Mild', 'Piece size': 'Approx. 1.25"' },
      cook: { method: 'Grill over high heat for 8–10 min, turning often.', temp: '60 °C / 140 °F for medium', tip: 'Serve with garlic sauce, pickles and warm flatbread.' },
    },
  };

  /* Category-level copy used on every product page in that category */
  const CAT_STORY = {
    beef:      'Our cattle are raised on partner family farms on pasture and forage, without growth hormones. Each animal is rested before slaughter, hand-slaughtered by a trained Muslim slaughterman with the Tasmiyah, fully bled, then inspected and dry-chilled at 0–4 °C before our butchers break it down.',
    lamb:      'Our lambs, sheep and goats come from small flocks on partner farms, grazing outdoors for most of the year. They are handled calmly, hand-slaughtered one at a time with the Tasmiyah, fully bled and chilled at 0–4 °C — never stunned and never frozen.',
    poultry:   'Every bird is hand-slaughtered individually by a practising Muslim — never on a mechanical line — with the Tasmiyah recited for each one. Birds are then air-chilled rather than dunked in water baths, which keeps the meat firmer and the flavour cleaner.',
    specialty: 'Specialty and Qurbani animals are sourced in small numbers from farms we visit personally. Each animal is checked for age and health so it meets Udhiyah conditions, and is hand-slaughtered according to the Sunnah.',
    marinades: 'Every marinade starts with the same zabiha meat we sell fresh at the counter. Our spice blends are mixed in-house in small daily batches, with no artificial colours or preservatives — just yoghurt, fresh aromatics and whole spices.',
  };
  const BUTCHERY = 'Your order is cut to order on the morning it ships, vacuum-sealed and labelled with the cut, weight, slaughter date and batch code. It then travels in an insulated box with ice packs, so it reaches your door at 0–4 °C.';
  const CAT_STORE = {
    beef:      { fridge: '3 days at 0–4 °C', freezer: 'Up to 6 months', thaw: 'Overnight in the fridge' },
    lamb:      { fridge: '3 days at 0–4 °C', freezer: 'Up to 6 months', thaw: 'Overnight in the fridge' },
    poultry:   { fridge: '2 days at 0–4 °C', freezer: 'Up to 4 months', thaw: 'In the fridge — never at room temperature' },
    specialty: { fridge: '3 days at 0–4 °C', freezer: 'Up to 6 months', thaw: 'Overnight in the fridge' },
    marinades: { fridge: '2 days at 0–4 °C', freezer: 'Up to 3 months', thaw: 'Overnight in the fridge' },
  };
  /* Approximate typical values per 100 g raw — varies by cut; shown with a disclaimer */
  const NUTRITION = {
    beef:      { kcal: 230, protein: 20, fat: 16, sat: 6.5, iron: 2.2, b12: 2.5 },
    lamb:      { kcal: 280, protein: 17, fat: 23, sat: 10, iron: 1.6, b12: 2.3 },
    poultry:   { kcal: 150, protein: 21, fat: 7, sat: 2, iron: 0.9, b12: 0.4 },
    specialty: { kcal: 250, protein: 19, fat: 19, sat: 8, iron: 2.0, b12: 2.4 },
    marinades: { kcal: 170, protein: 18, fat: 9, sat: 3, iron: 1.2, b12: 0.8 },
  };

  /* =====================================================
     LIVE DATA — index.php injects window.HB_SERVER with the catalog, prices,
     stock and settings from the database. Without it (index.html opened
     directly) the demo catalog above is used and checkout is disabled.
     ===================================================== */
  const SERVER = window.HB_SERVER || null;
  if (SERVER) {
    CATEGORIES.splice(0, CATEGORIES.length, ...SERVER.categories);
    PRODUCTS.splice(0, PRODUCTS.length, ...SERVER.products);
    SERVER.products.forEach(p => { if (p.details) DETAILS[p.id] = p.details; });
    Object.assign(CONFIG, SERVER.config);
    CONFIG.coupons = {};   // codes are validated by the server, never listed in the page
    CATEGORIES.forEach(c => { CAT_INFO[c.id] ??= CAT_INFO.beef; CAT_STORY[c.id] ??= ''; CAT_STORE[c.id] ??= CAT_STORE.beef; NUTRITION[c.id] ??= NUTRITION.beef; });
  }
  // Demand-test switches (app/config.php → shop.delivery / shop.custom_weights)
  const PICKUP_ONLY = CONFIG.deliveryEnabled === false;
  const FIXED_WEIGHTS = CONFIG.customWeights === false;
  const api = async (path, body) => {
    const res = await fetch(`api/${path}`, body === undefined
      ? { credentials: 'same-origin' }
      : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    let data = {};
    try { data = await res.json(); } catch { /* non-JSON error page */ }
    if (!res.ok || data.ok === false) throw Object.assign(new Error(data.error || 'Something went wrong. Please try again.'), { status: res.status, data });
    return data;
  };

  /* =====================================================
     HELPERS
     ===================================================== */
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const wait = ms => new Promise(r => setTimeout(r, ms));
  const money = n => new Intl.NumberFormat(CONFIG.locale, { style: 'currency', currency: CONFIG.currency }).format(n);
  // Round to cents, half-up, immune to float error (18.99 × 3.5 = 66.465 → 66.47, not 66.46)
  const round2 = n => Math.round(Math.round(n * 1e4) / 100) / 100;
  const fmtW  = n => (Math.round(n * 100) / 100).toString();
  const esc   = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const byId  = id => PRODUCTS.find(p => p.id === id);
  const catName = id => (CATEGORIES.find(c => c.id === id) || { name: 'All' }).name;
  const icons = () => window.lucide && lucide.createIcons({ attrs: { 'stroke-width': 2 } });
  const animate = (el, frames, opts) => (!REDUCED && el && el.animate) ? el.animate(frames, opts) : null;
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* storage unavailable */ } },
  };
  const onImg = `onload="this.classList.add('loaded');(this.closest('.skeleton')||this).classList.remove('skeleton')" onerror="var s=this.closest('.skeleton')||this.parentElement;s.classList.remove('skeleton');s.classList.add('bg-gradient-to-br','from-brand-700','to-brand-900');this.remove()"`;
  const pdpHref = id => `#/product/${id}`;

  /* =====================================================
     STATE
     ===================================================== */
  const state = {
    cat: 'all',
    query: '',
    sort: 'featured',
    visible: CONFIG.pageSize,
    unit: store.get('hms-unit', 'lb'),
    cart: store.get('hms-cart', []),   // [{ key, id, weightLb, cut, qty }]
    sel: {},                           // per-product selection { w: index | 'custom', customW, cut }
    wish: new Set(store.get('hms-wish', [])),
    lastAdded: null,                   // cart key to animate in the drawer
    coupon: store.get('hms-coupon', null),
  };
  const selOf = id => (state.sel[id] ??= { w: 0, customW: state.unit === 'lb' ? 3 : 1.5, cut: (byId(id).cuts || ['Standard cut'])[0] });

  /* Variant helpers → { weightLb, cut, price, label, key } */
  function presetVariant(p, idx) {
    const w = WEIGHTS[state.unit][idx];
    const weightLb = state.unit === 'lb' ? w : w * LB_PER_KG;
    return { weightLb, cut: '', price: round2(p.price * weightLb), label: `${fmtW(w)} ${state.unit}`, key: `${p.id}|${weightLb.toFixed(3)}|` };
  }
  function variantOf(p) {
    if (p.sold === 'unit') return { weightLb: null, cut: '', price: p.price, label: `per ${p.unitLabel}`, key: p.id };
    const s = selOf(p.id);
    if (s.w !== 'custom') return presetVariant(p, s.w);
    const w = Math.max(0.25, +s.customW || 0.25);
    const weightLb = state.unit === 'lb' ? w : w * LB_PER_KG;
    return { weightLb, cut: s.cut, price: round2(p.price * weightLb), label: `${fmtW(w)} ${state.unit} · ${s.cut}`, key: `${p.id}|${weightLb.toFixed(3)}|${s.cut}` };
  }
  const unitPrice = p => state.unit === 'lb' ? p.price : p.price * LB_PER_KG;
  const lineLabel = item => {
    if (item.weightLb == null) return `1 ${byId(item.id).unitLabel} · pre-order`;
    const w = state.unit === 'lb' ? item.weightLb : item.weightLb / LB_PER_KG;
    return `${fmtW(w)} ${state.unit}${item.cut ? ' · ' + item.cut : ''}`;
  };
  const itemPrice = item => { const p = byId(item.id); return item.weightLb == null ? p.price : round2(p.price * item.weightLb); };

  /* =====================================================
     SCROLL REVEAL (IntersectionObserver)
     ===================================================== */
  const io = ('IntersectionObserver' in window && !REDUCED)
    ? new IntersectionObserver(entries => entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' })
    : null;
  function reveal(el, delay = 0) {
    el.classList.add('reveal');
    el.style.setProperty('--d', `${delay}ms`);
    io ? io.observe(el) : el.classList.add('in');
  }
  function initReveals() {
    $$('[data-reveal]').forEach(el => reveal(el));
    $$('[data-stagger]').forEach(box => [...box.children].forEach((el, i) => reveal(el, i * 90)));
  }

  /* Count-up numbers + filling bars in the Zabiha section */
  function initCounters() {
    const box = $('[aria-label="Quality commitments"]');
    if (!box || !io) return;                     // no-JS / reduced motion: final values stay
    const bars = $$('[data-bar]', box), counts = $$('[data-count]', box);
    bars.forEach(b => b.style.width = '0%');
    counts.forEach(c => c.textContent = '0' + (c.dataset.suffix || ''));
    const cio = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return;
      cio.disconnect();
      requestAnimationFrame(() => bars.forEach(b => b.style.width = b.dataset.bar + '%'));
      const t0 = performance.now();
      const step = now => {
        const k = Math.min(1, (now - t0) / 1400), ease = 1 - Math.pow(1 - k, 3);
        counts.forEach(c => c.textContent = Math.round(+c.dataset.count * ease) + (c.dataset.suffix || ''));
        if (k < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }, { threshold: 0.6 });
    cio.observe(box);
  }

  /* =====================================================
     HERO: 3D tilt + parallax cards, live temperature
     ===================================================== */
  (function heroTilt() {
    const vis = $('#heroVisual'), card = $('#heroTilt');
    if (!vis || REDUCED || !matchMedia('(pointer: fine)').matches) return;
    const floats = $$('#heroVisual > .bob');
    floats.forEach(f => f.style.transition = 'transform .6s cubic-bezier(.2,.8,.2,1)');
    vis.addEventListener('pointermove', e => {
      const r = vis.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      card.style.transform = `rotateY(${x * 8}deg) rotateX(${-y * 6}deg)`;
      floats.forEach((f, i) => f.style.transform = `translate3d(${x * (i ? -22 : 22)}px, ${y * 16}px, 0)`);
    });
    vis.addEventListener('pointerleave', () => { card.style.transform = ''; floats.forEach(f => f.style.transform = ''); });
  })();

  // Demo only: simulated in-transit reading. Wire to your cold-chain logger or remove.
  setInterval(() => {
    const el = $('#liveTemp');
    if (!el || document.hidden) return;
    el.textContent = (2.1 + Math.random() * 0.8).toFixed(1);
    animate(el, [{ opacity: 0.3 }, { opacity: 1 }], { duration: 400 });
  }, 4000);

  /* Same-day cutoff countdown (announcement bar + quick view) */
  function cutoffInfo() {
    const now = new Date(), cut = new Date(now);
    cut.setHours(CONFIG.cutoffHour, 0, 0, 0);
    const ms = cut - now;
    if (ms <= 0) return { open: false, text: 'tomorrow 9 AM' };
    const h = Math.floor(ms / 36e5), m = Math.floor((ms % 36e5) / 6e4);
    return { open: true, text: `${h ? h + 'h ' : ''}${m}m` };
  }
  function tickCountdown() {
    const c = cutoffInfo();
    $('#countdown').innerHTML = c.open
      ? `Order within <strong class="tabular-nums text-white">${c.text}</strong> for same-day delivery`
      : `Order now for <strong class="text-white">tomorrow 9 AM</strong> delivery`;
  }
  setInterval(tickCountdown, 30000);

  /* =====================================================
     CATEGORY CAROUSEL (spotlight hover, progress track)
     ===================================================== */
  function renderCarousel() {
    const count = id => PRODUCTS.filter(p => p.cat === id).length;
    const track = $('#catTrack');
    track.innerHTML = CATEGORIES.map(c => `
      <li class="w-[72%] shrink-0 snap-start min-[480px]:w-[45%] md:w-[31%] lg:w-[calc((100%-4rem)/5)]">
        <button data-cat-card="${c.id}" class="spotlight group relative block aspect-[3/4] w-full overflow-hidden rounded-3xl bg-brand-900 text-left shadow-card transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-lift">
          <img src="${img(c.img, 520)}" alt="" loading="lazy" class="zoom-img img-fade absolute inset-0 h-full w-full object-cover opacity-90" onload="this.classList.add('loaded')" />
          <span class="absolute inset-0 bg-gradient-to-t from-ink-900/85 via-ink-900/10 to-transparent"></span>
          <span class="absolute left-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-brand-700 backdrop-blur transition-transform duration-500 group-hover:rotate-12 group-hover:scale-110"><i data-lucide="${c.icon}" class="h-5 w-5" aria-hidden="true"></i></span>
          <span class="absolute inset-x-4 bottom-4 text-white">
            <span class="block font-display text-xl font-semibold leading-tight">${c.name}</span>
            <span class="mt-1 block text-[13px] text-white/75">${c.blurb}</span>
            <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-bold backdrop-blur transition group-hover:bg-white group-hover:text-ink-900">${count(c.id)} cuts <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i></span>
          </span>
        </button>
      </li>`).join('');
    [...track.children].forEach((li, i) => reveal(li, i * 90));

    const step = () => track.firstElementChild.getBoundingClientRect().width + 16;
    const sync = () => {
      $('#catPrev').disabled = track.scrollLeft < 4;
      $('#catNext').disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
      const w = Math.min(100, (track.clientWidth / track.scrollWidth) * 100);
      const ratio = track.scrollLeft / Math.max(1, track.scrollWidth - track.clientWidth);
      const bar = $('#catProgress');
      bar.style.width = w + '%';
      bar.style.transform = `translateX(${ratio * ((100 - w) / w) * 100}%)`;
    };
    $('#catPrev').onclick = () => track.scrollBy({ left: -step(), behavior: 'smooth' });
    $('#catNext').onclick = () => track.scrollBy({ left: step(), behavior: 'smooth' });
    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();
    track.addEventListener('click', e => { const b = e.target.closest('[data-cat-card]'); if (b) jumpToCategory(b.dataset.catCard); });
    track.addEventListener('pointermove', e => {
      const card = e.target.closest('.spotlight');
      if (!card) return;
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', `${e.clientX - r.left}px`);
      card.style.setProperty('--my', `${e.clientY - r.top}px`);
    });
  }

  /* =====================================================
     CATEGORY TABS — built once; a sliding indicator follows the selection
     ===================================================== */
  function renderTabs() {
    const box = $('#catTabs');
    if (!box.dataset.built) {
      const tabs = [{ id: 'all', name: 'All cuts' }, ...CATEGORIES];
      box.innerHTML = `<span id="tabInd" class="tab-ind" aria-hidden="true"></span>` + tabs.map(t => {
        const n = t.id === 'all' ? PRODUCTS.length : PRODUCTS.filter(p => p.cat === t.id).length;
        return `<button role="tab" id="tab-${t.id}" data-tab="${t.id}" aria-controls="productGrid" class="cat-tab">${t.name}<span class="count">${n}</span></button>`;
      }).join('');
      box.dataset.built = '1';
      if ('ResizeObserver' in window) { const ro = new ResizeObserver(() => moveTabInd(true)); $$('.cat-tab', box).forEach(t => ro.observe(t)); }
    }
    $$('.cat-tab', box).forEach(t => {
      const on = t.dataset.tab === state.cat;
      t.setAttribute('aria-selected', on);
      t.tabIndex = on ? 0 : -1;
    });
    $('#productGrid').setAttribute('aria-labelledby', `tab-${state.cat}`);
    moveTabInd();
  }
  function moveTabInd(instant) {
    const t = $(`#tab-${state.cat}`), ind = $('#tabInd'), box = $('#catTabs');
    if (!t || !ind) return;
    if (instant) ind.style.transition = 'none';
    ind.style.width = `${t.offsetWidth}px`;
    ind.style.height = `${t.offsetHeight}px`;
    ind.style.transform = `translate(${t.offsetLeft}px, ${t.offsetTop}px)`;
    if (instant) { void ind.offsetWidth; ind.style.transition = ''; }
    else box.scrollTo({ left: t.offsetLeft - box.clientWidth / 2 + t.offsetWidth / 2, behavior: REDUCED ? 'auto' : 'smooth' });
  }
  $('#catTabs').addEventListener('click', e => { const t = e.target.closest('[data-tab]'); if (t) setCategory(t.dataset.tab); });
  $('#catTabs').addEventListener('keydown', e => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
    const tabs = $$('[role="tab"]', e.currentTarget);
    let i = tabs.indexOf(document.activeElement);
    i = e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    setCategory(tabs[i].dataset.tab);
    tabs[i].focus();
    e.preventDefault();
  });

  function setCategory(id) {
    state.cat = id;
    state.visible = CONFIG.pageSize;
    renderTabs();
    renderGrid();
    // If the user is deep in the grid, bring the fresh results into view under the sticky tabs
    const top = $('#productGrid').getBoundingClientRect().top;
    if (!$('#homeView').hidden && top < 120) scrollTo({ top: top + scrollY - 150, behavior: REDUCED ? 'auto' : 'smooth' });
  }
  function jumpToCategory(id) {
    setCategory(id);
    $('#shop').scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth' });
  }
  // Delegated so links rendered later (product-page breadcrumbs, "view all") work too
  document.addEventListener('click', e => { const a = e.target.closest('[data-jump-cat]'); if (a) setCategory(a.dataset.jumpCat); });

  /* =====================================================
     PRODUCT GRID
     ===================================================== */
  /* Every word of the query must appear (in any order); simple plural handling ("chops" → "chop") */
  const matches = (p, q) => {
    if (!q) return true;
    const hay = `${p.name} ${p.desc} ${p.kw || ''} ${catName(p.cat)}`.toLowerCase();
    return q.toLowerCase().split(/\s+/).filter(Boolean).every(t => hay.includes(t) || (t.length > 3 && t.endsWith('s') && hay.includes(t.slice(0, -1))));
  };

  function filtered() {
    let list = PRODUCTS.filter(p => (state.cat === 'all' || p.cat === state.cat) && matches(p, state.query));
    if (state.sort === 'price-asc')  list = [...list].sort((a, b) => a.price - b.price);
    if (state.sort === 'price-desc') list = [...list].sort((a, b) => b.price - a.price);
    if (state.sort === 'name')       list = [...list].sort((a, b) => a.name.localeCompare(b.name));
    return list;
  }

  /* animate:false re-renders silently (e.g. unit switch); `from` animates only newly loaded cards */
  function renderGrid({ animate: anim = true, from = 0 } = {}) {
    const list = filtered();
    $('#productGrid').innerHTML = list.slice(0, state.visible).map(p => cardHTML(p)).join('');
    $$('#productGrid > article').forEach((card, i) => {
      if (!anim || i < from) card.classList.add('reveal', 'in');
      else reveal(card, ((i - from) % 4) * 80);
    });
    $('#emptyState').hidden = list.length > 0;
    $('#loadMore').hidden = list.length <= state.visible;
    $('#resultMeta').textContent = `${list.length} ${list.length === 1 ? 'cut' : 'cuts'} · prices shown per ${state.unit}`;
    $('#activeQuery').hidden = !state.query;
    $('[data-query-label]').textContent = `“${state.query}”`;
    icons();
  }

  const skeletonCard = () => `
    <div class="overflow-hidden rounded-3xl bg-white ring-1 ring-bone-200" aria-hidden="true" data-skeleton>
      <div class="skeleton aspect-[4/3]"></div>
      <div class="space-y-3 p-5">
        <div class="skeleton h-3 w-1/3 rounded"></div><div class="skeleton h-5 w-3/4 rounded"></div>
        <div class="skeleton h-3 w-full rounded"></div><div class="skeleton h-9 w-full rounded-lg"></div>
        <div class="skeleton h-11 w-full rounded-full"></div>
      </div>
    </div>`;

  /* Action area: Add ↔ qty stepper (re-rendered alone so focus stays stable) */
  function actionHTML(p) {
    const v = variantOf(p);
    const inCart = state.cart.find(i => i.key === v.key);
    if (p.stock === 0 && !inCart) {
      return `<button type="button" disabled class="flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-full bg-bone-200 text-sm font-bold text-ink-500"><i data-lucide="circle-slash" class="h-4 w-4" aria-hidden="true"></i> ${p.preorder ? 'Fully booked' : 'Sold out today'}</button>`;
    }
    if (p.preorder) {
      return inCart
        ? `<button data-act="open-cart" class="flex h-11 w-full items-center justify-center gap-2 rounded-full bg-halal-50 text-sm font-bold text-halal-700 ring-1 ring-halal-100 hover:bg-halal-100"><i data-lucide="check" class="h-4 w-4" aria-hidden="true"></i> Reserved · view cart</button>`
        : `<button data-act="add" class="group/btn flex h-11 w-full items-center justify-center gap-2 rounded-full bg-saffron-400 text-sm font-extrabold text-ink-900 transition-colors hover:bg-saffron-300"><i data-lucide="calendar-check" class="h-4 w-4 transition-transform group-hover/btn:-rotate-12" aria-hidden="true"></i> Pre-order · ${money(p.price)}</button>`;
    }
    if (inCart) {
      return `<div class="flex h-11 items-center justify-between rounded-full bg-brand-800 p-1 text-white" role="group" aria-label="Quantity in cart for ${esc(p.name)}, ${esc(v.label)}">
          <button data-act="dec" class="grid h-9 w-9 place-items-center rounded-full transition-colors hover:bg-white/15" aria-label="${inCart.qty === 1 ? 'Remove from cart' : 'Decrease quantity'}"><i data-lucide="${inCart.qty === 1 ? 'trash-2' : 'minus'}" class="h-4 w-4" aria-hidden="true"></i></button>
          <span class="text-sm font-bold tabular-nums" aria-live="polite">${inCart.qty} in cart · ${money(itemPrice(inCart) * inCart.qty)}</span>
          <button data-act="inc" class="grid h-9 w-9 place-items-center rounded-full transition-colors hover:bg-white/15" aria-label="Increase quantity"><i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i></button>
        </div>`;
    }
    return `<button data-act="add" class="group/btn flex h-11 w-full items-center justify-center gap-2 rounded-full border-2 border-brand-800 text-sm font-bold text-brand-800 transition-colors hover:bg-brand-800 hover:text-white">
        <i data-lucide="shopping-bag" class="h-4 w-4 transition-transform group-hover/btn:-translate-y-0.5" aria-hidden="true"></i> Add · ${money(v.price)}</button>`;
  }

  function weightUI(p) {
    if (p.sold === 'unit') return `
      <p class="mt-4 flex items-center gap-2 rounded-xl bg-bone-100 px-3 py-2.5 text-[13px] text-ink-600">
        <i data-lucide="calendar-clock" class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i> Slaughter on Eid · delivered within 48h
      </p>`;
    const s = selOf(p.id);
    return `
      <div class="mt-4 grid ${FIXED_WEIGHTS ? 'grid-cols-3' : 'grid-cols-4'} gap-1.5" role="radiogroup" aria-label="Choose weight for ${esc(p.name)}">
        ${WEIGHTS[state.unit].map((w, i) => `<button role="radio" data-act="pill" data-w="${i}" aria-checked="${s.w === i}" class="pill h-9 rounded-lg text-[13px] font-bold">${w} ${state.unit}</button>`).join('')}
        ${FIXED_WEIGHTS ? '' : `<button role="radio" data-act="pill" data-w="custom" aria-checked="${s.w === 'custom'}" class="pill inline-flex h-9 items-center justify-center gap-1 rounded-lg text-[12px] font-bold leading-none"><i data-lucide="scissors" class="h-3 w-3" aria-hidden="true"></i>Custom</button>`}
      </div>
      <div class="mt-2 grid grid-cols-[88px_1fr] gap-1.5" data-custom ${s.w === 'custom' ? '' : 'hidden'}>
        <label class="relative">
          <span class="sr-only">Custom weight in ${state.unit}</span>
          <input type="number" inputmode="decimal" min="0.25" step="0.25" value="${s.customW}" data-act="customW" class="h-9 w-full rounded-lg border border-bone-300 bg-bone-50 pl-2.5 pr-7 text-[13px] font-semibold focus:border-brand-500 focus:outline-none" />
          <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[11px] font-bold text-ink-500">${state.unit}</span>
        </label>
        <label>
          <span class="sr-only">Cut style</span>
          <select data-act="cut" class="h-9 w-full rounded-lg border border-bone-300 bg-bone-50 px-2 text-[13px] font-semibold focus:border-brand-500 focus:outline-none">
            ${(p.cuts || ['Standard cut']).map(c => `<option ${c === s.cut ? 'selected' : ''}>${esc(c)}</option>`).join('')}
          </select>
        </label>
      </div>`;
  }

  function stockUI(p) {
    if (!p.stock) return '';
    const label = p.preorder ? `${p.stock} ${p.unitLabel}s left for Eid` : `Only ${p.stock} left from today's batch`;
    return `
      <div class="mt-3">
        <p class="flex items-center gap-1.5 text-[12px] font-bold text-brand-600"><i data-lucide="flame" class="h-3.5 w-3.5" aria-hidden="true"></i>${label}</p>
        <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-bone-200"><div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-saffron-400" style="width:${Math.max(8, 100 - p.stock * 7)}%"></div></div>
      </div>`;
  }

  function badgesHTML(p) {
    const off = p.was ? Math.round((1 - p.price / p.was) * 100) : 0;
    return `
      <span class="inline-flex items-center gap-1 rounded-full bg-halal-600/95 px-2 py-1 text-[10.5px] font-extrabold uppercase tracking-wide text-white backdrop-blur" title="100% hand-slaughtered zabiha halal">
        <i data-lucide="badge-check" class="h-3 w-3" aria-hidden="true"></i> Zabiha
      </span>
      ${off ? `<span class="rounded-full bg-brand-600 px-2 py-1 text-[10.5px] font-extrabold text-white">−${off}%</span>` : ''}
      ${p.tag ? `<span class="rounded-full bg-white/95 px-2 py-1 text-[10.5px] font-extrabold text-ink-900">${esc(p.tag)}</span>` : ''}
      ${p.preorder ? `<span class="rounded-full bg-saffron-400 px-2 py-1 text-[10.5px] font-extrabold text-ink-900">Qurbani 2027</span>` : ''}`;
  }

  function priceHTML(p, big) {
    return `
      <div class="mt-3 flex items-baseline gap-2">
        <span class="${big ? 'text-3xl' : 'text-xl'} font-extrabold tabular-nums text-ink-900">${money(p.sold === 'unit' ? p.price : unitPrice(p))}</span>
        <span class="text-sm font-semibold text-ink-500">/ ${p.sold === 'unit' ? p.unitLabel : state.unit}</span>
        ${p.was ? `<s class="text-sm text-ink-500/70">${money(state.unit === 'lb' ? p.was : p.was * LB_PER_KG)}</s>` : ''}
      </div>`;
  }

  /* ctx prefixes the element id so the same product can appear in several grids
     (shop, related, wishlist) without duplicate ids */
  function cardHTML(p, ctx = 'p') {
    const wish = state.wish.has(p.id);
    return `
    <article id="${ctx}-${p.id}" data-id="${p.id}" class="group flex flex-col overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-bone-200 hover:-translate-y-1 hover:shadow-lift">
      <div class="skeleton relative aspect-[4/3] overflow-hidden">
        <a href="${pdpHref(p.id)}" tabindex="-1" aria-hidden="true" class="block h-full w-full"><img src="${img(p.img)}" alt="${esc(p.name)}" loading="lazy" class="zoom-img img-fade h-full w-full object-cover" ${onImg} /></a>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink-900/30 via-transparent to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
        <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">${badgesHTML(p)}</div>
        <button data-act="wish" aria-pressed="${wish}" aria-label="${wish ? 'Remove from' : 'Save to'} wishlist: ${esc(p.name)}"
                class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white/90 backdrop-blur transition hover:scale-110 hover:bg-white ${wish ? 'text-brand-600' : 'text-ink-700'}">
          <i data-lucide="heart" class="h-4 w-4 ${wish ? 'fill-current' : ''}" aria-hidden="true"></i>
        </button>
        <button data-act="quick" class="qv-btn absolute inset-x-3 bottom-3 flex h-10 items-center justify-center gap-2 rounded-full bg-white/95 text-sm font-bold text-ink-900 shadow-lg backdrop-blur hover:bg-white" aria-label="Quick view: ${esc(p.name)}">
          <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i> Quick view
        </button>
      </div>

      <div class="flex flex-1 flex-col p-4 sm:p-5">
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-ink-500">${catName(p.cat)}</p>
        <h3 class="mt-1 font-display text-[19px] font-semibold leading-snug text-ink-900">
          <a href="${pdpHref(p.id)}" class="inline-block py-0.5 decoration-brand-200 decoration-2 underline-offset-4 hover:text-brand-700 hover:underline">${esc(p.name)}</a>
        </h3>
        <p class="mt-1 line-clamp-2 text-[13px] leading-relaxed text-ink-500">${esc(p.desc)}</p>
        ${priceHTML(p)}
        ${stockUI(p)}
        ${weightUI(p)}
        <div class="mt-auto pt-4" data-action-area>${actionHTML(p)}</div>
      </div>
    </article>`;
  }

  /* Keep every rendering of a product (grid card + quick view) in sync */
  function syncSelection(id) {
    const s = selOf(id);
    $$(`[data-id="${id}"]`).forEach(root => {
      $$('[data-act="pill"]', root).forEach(b => b.setAttribute('aria-checked', String(b.dataset.w === String(s.w))));
      const c = $('[data-custom]', root);
      if (c) {
        const opening = c.hidden && s.w === 'custom';
        c.hidden = s.w !== 'custom';
        if (opening) animate(c, [{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 240, easing: 'cubic-bezier(.2,.8,.2,1)' });
      }
      const cw = $('[data-act="customW"]', root); if (cw && document.activeElement !== cw) cw.value = s.customW;
      const cs = $('[data-act="cut"]', root);     if (cs && document.activeElement !== cs) cs.value = s.cut;
    });
    refreshAction(id);
  }
  function refreshAction(id, focusRoot, focusSel) {
    const p = byId(id);
    $$(`[data-id="${id}"] [data-action-area]`).forEach(a => a.innerHTML = actionHTML(p));
    $$(`[data-id="${id}"] [data-pack]`).forEach(el => el.innerHTML = packHTML(p));
    icons();
    if (focusRoot && focusSel) $(focusSel, focusRoot)?.focus();
  }

  /* Shared handlers for grid cards and the quick-view panel */
  function onProductClick(e) {
    const btn = e.target.closest('[data-act]'), root = e.target.closest('[data-id]');
    if (!btn || !root || btn.tagName === 'INPUT' || btn.tagName === 'SELECT') return;
    const p = byId(root.dataset.id);
    switch (btn.dataset.act) {
      case 'pill': {
        const s = selOf(p.id);
        s.w = btn.dataset.w === 'custom' ? 'custom' : +btn.dataset.w;
        syncSelection(p.id);
        break;
      }
      case 'add': addFlow(p, btn, root); break;
      case 'inc': case 'dec': qtyFlow(p, btn.dataset.act, root); break;
      case 'quick': openQuickView(p.id, btn); break;
      case 'open-cart': {
        const back = qv.hidden ? btn : qvLast;
        if (!qv.hidden) closeQuickView(false);
        openCart(back);
        break;
      }
      case 'wish': toggleWish(p, btn); break;
      case 'share': shareProduct(p); break;
    }
  }
  async function shareProduct(p) {
    const url = location.href.split('#')[0] + pdpHref(p.id);
    try {
      if (navigator.share) await navigator.share({ title: `${p.name} — Halal Brothers`, text: p.desc, url });
      else { await navigator.clipboard.writeText(url); toast('Product link copied', 'link', true); }
    } catch { /* user cancelled or clipboard blocked */ }
  }
  function onProductInput(e) {
    const root = e.target.closest('[data-id]');
    if (!root) return;
    const s = selOf(root.dataset.id);
    if (e.target.dataset.act === 'customW') s.customW = e.target.value;
    else if (e.target.dataset.act === 'cut') s.cut = e.target.value;
    else return;
    syncSelection(root.dataset.id);
  }
  ['#productGrid', '#qvPanel', '#pdp', '#pdpBar', '#pageView'].forEach(sel => {
    $(sel).addEventListener('click', onProductClick);
    $(sel).addEventListener('input', onProductInput);
  });

  function addFlow(p, btn, root) {
    const v = variantOf(p);
    const image = root.dataset.fly ? $(root.dataset.fly) : root.closest('#qvPanel') ? $('#qvPanel img') : $('img', root);
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-circle" class="h-4 w-4 animate-spin" aria-hidden="true"></i> Adding…`;
    icons();
    setTimeout(() => {
      addToCart(p, v);
      flyToCart(image);
      btn.innerHTML = `<i data-lucide="check" class="h-4 w-4" aria-hidden="true"></i> ${p.preorder ? 'Reserved' : 'Added'}`;
      btn.classList.add('!border-halal-600', '!bg-halal-600', '!text-white');
      icons();
      setTimeout(() => refreshAction(p.id, root, p.preorder ? '[data-act="open-cart"]' : '[data-act="inc"]'), 650);
    }, REDUCED ? 0 : 320);
  }
  function qtyFlow(p, act, root) {
    const key = variantOf(p).key;
    const item = state.cart.find(i => i.key === key);
    if (!item) return;
    if (act === 'dec' && item.qty === 1) {
      removeWithUndo(key);
      refreshAction(p.id, root, '[data-act="add"]');
      return;
    }
    changeQty(key, act === 'inc' ? 1 : -1);
    if (act === 'inc') wiggleCart();
    refreshAction(p.id, root, `[data-act="${act}"]`);
  }
  function toggleWish(p, btn) {
    state.wish.has(p.id) ? state.wish.delete(p.id) : state.wish.add(p.id);
    store.set('hms-wish', [...state.wish]);
    const on = state.wish.has(p.id);
    $$(`[data-id="${p.id}"] [data-act="wish"]`).forEach(b => {
      b.setAttribute('aria-pressed', on);
      b.setAttribute('aria-label', `${on ? 'Remove from' : 'Save to'} wishlist: ${p.name}`);
      b.classList.toggle('text-brand-600', on);
      b.classList.toggle('text-ink-700', !on);
      const svg = b.querySelector('svg');
      svg?.classList.toggle('fill-current', on);
      svg?.classList.remove('pop'); void svg?.getBoundingClientRect(); svg?.classList.add('pop');
    });
    if (on) burst(btn, 10);
    toast(on ? `Saved ${p.name} to your wishlist` : 'Removed from wishlist', on ? 'heart' : 'heart-off');
    updateWishCount();
    if (view === 'page' && page === 'wishlist') setTimeout(() => renderPage('wishlist', null, { keepScroll: true }), 380);
  }

  /* Load more with skeleton placeholders */
  $('#loadMore').addEventListener('click', async e => {
    const btn = e.currentTarget, html = btn.innerHTML, from = state.visible;
    const n = Math.min(CONFIG.pageSize, filtered().length - from);
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-circle" class="h-4 w-4 animate-spin" aria-hidden="true"></i> Loading cuts…`;
    icons();
    $('#productGrid').insertAdjacentHTML('beforeend', Array.from({ length: n }, skeletonCard).join(''));
    await wait(REDUCED ? 0 : 550);
    state.visible += CONFIG.pageSize;
    renderGrid({ from });
    btn.disabled = false;
    btn.innerHTML = html;
    icons();
    $$('#productGrid article')[from]?.querySelector('button')?.focus({ preventScroll: true });
  });
  $('#sortSelect').addEventListener('change', e => { state.sort = e.target.value; renderGrid(); });

  /* =====================================================
     QUICK VIEW MODAL
     ===================================================== */
  const qv = $('#quickView');
  let qvLast = null;
  function openQuickView(id, from) {
    const p = byId(id), info = CAT_INFO[p.cat], c = cutoffInfo(), wish = state.wish.has(p.id);
    qvLast = from;
    $('#qvPanel').innerHTML = `
      <button id="qvClose" data-qv-close class="absolute right-4 top-4 z-10 grid h-10 w-10 place-items-center rounded-full bg-white/90 shadow-lg backdrop-blur transition hover:rotate-90 hover:bg-white" aria-label="Close quick view"><i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i></button>
      <div class="skeleton relative aspect-[4/3] overflow-hidden md:aspect-auto md:min-h-[540px]">
        <img src="${img(p.img, 1000)}" alt="${esc(p.name)}" class="img-fade kenburns absolute inset-0 h-full w-full object-cover" ${onImg} />
        <div class="absolute left-4 top-4 flex flex-wrap gap-1.5">${badgesHTML(p)}</div>
      </div>
      <div class="flex flex-col p-6 sm:p-8" data-id="${p.id}">
        <p class="text-[11px] font-bold uppercase tracking-[.16em] text-brand-600">${catName(p.cat)}</p>
        <h2 id="qvTitle" class="mt-1 pr-10 font-display text-3xl font-semibold leading-tight">${esc(p.name)}</h2>
        ${priceHTML(p, true)}
        <p class="mt-3 text-[15px] leading-relaxed text-ink-600">${esc(p.desc)}</p>
        ${stockUI(p)}
        <dl class="mt-5 grid grid-cols-2 gap-2 text-[13px]">
          <div class="rounded-xl bg-bone-100 p-3"><dt class="flex items-center gap-1.5 font-bold"><i data-lucide="shield-check" class="h-3.5 w-3.5 text-halal-600" aria-hidden="true"></i>Slaughter</dt><dd class="mt-0.5 text-ink-500">By hand, Tasmiyah recited</dd></div>
          <div class="rounded-xl bg-bone-100 p-3"><dt class="flex items-center gap-1.5 font-bold"><i data-lucide="snowflake" class="h-3.5 w-3.5 text-sky-600" aria-hidden="true"></i>Kept at</dt><dd class="mt-0.5 text-ink-500">0–4 °C, never frozen</dd></div>
          <div class="rounded-xl bg-bone-100 p-3"><dt class="flex items-center gap-1.5 font-bold"><i data-lucide="chef-hat" class="h-3.5 w-3.5 text-brand-600" aria-hidden="true"></i>Best for</dt><dd class="mt-0.5 text-ink-500">${info.cook}</dd></div>
          <div class="rounded-xl bg-bone-100 p-3"><dt class="flex items-center gap-1.5 font-bold"><i data-lucide="calendar-days" class="h-3.5 w-3.5 text-saffron-500" aria-hidden="true"></i>Keeps</dt><dd class="mt-0.5 text-ink-500">${info.keeps}</dd></div>
        </dl>
        ${weightUI(p)}
        <div class="mt-5 flex items-center gap-2">
          <div class="flex-1" data-action-area>${actionHTML(p)}</div>
          <button data-act="wish" aria-pressed="${wish}" aria-label="${wish ? 'Remove from' : 'Save to'} wishlist: ${esc(p.name)}" class="grid h-11 w-11 shrink-0 place-items-center rounded-full ring-1 ring-bone-300 transition hover:ring-brand-500 ${wish ? 'text-brand-600' : 'text-ink-700'}"><i data-lucide="heart" class="h-4 w-4 ${wish ? 'fill-current' : ''}" aria-hidden="true"></i></button>
        </div>
        <p class="mt-4 flex items-center gap-2 text-xs text-ink-500"><i data-lucide="truck" class="h-4 w-4 shrink-0 text-halal-600" aria-hidden="true"></i><span>${c.open ? `Order within <strong class="text-ink-900">${c.text}</strong> for same-day cold delivery` : `Order now for <strong class="text-ink-900">tomorrow 9 AM</strong> cold delivery`}</span></p>
        <a href="${pdpHref(p.id)}" class="group/more mt-5 inline-flex items-center gap-1.5 self-start text-sm font-bold text-brand-700 hover:underline">View full details <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover/more:translate-x-1" aria-hidden="true"></i></a>
      </div>`;
    icons();
    qv.hidden = false;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => qv.classList.remove('modal-closed')));
    $('#qvClose').focus({ preventScroll: true });
  }
  function closeQuickView(restore = true) {
    qv.classList.add('modal-closed');
    document.body.style.overflow = '';
    setTimeout(() => { qv.hidden = true; }, REDUCED ? 0 : 350);
    if (restore) qvLast?.focus?.({ preventScroll: true });
  }
  qv.addEventListener('click', e => { if (e.target.closest('[data-qv-close]')) closeQuickView(); });
  qv.addEventListener('keydown', e => { if (e.key === 'Escape') closeQuickView(); else trapFocus(qv, e); });

  function trapFocus(container, e) {
    if (e.key !== 'Tab') return;
    const f = $$('button:not([disabled]), a[href], select, input', container).filter(el => el.getClientRects().length);
    if (!f.length) return;
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
    else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
  }

  /* =====================================================
     MICRO-INTERACTIONS: fly-to-cart, wiggle, confetti
     ===================================================== */
  function wiggleCart() {
    $$('#cartBtn svg, #bnavCart svg, #cartTab svg').forEach(s => { s.classList.remove('wiggle'); void s.getBoundingClientRect(); s.classList.add('wiggle'); });
  }
  function flyToCart(fromImg) {
    const target = $('#cartBtn');
    if (REDUCED || !fromImg || !fromImg.getClientRects().length) return wiggleCart();
    const r = fromImg.getBoundingClientRect(), t = target.getBoundingClientRect();
    const size = Math.min(r.width, r.height, 160);
    const x0 = r.left + r.width / 2 - size / 2, y0 = r.top + r.height / 2 - size / 2;
    const ghost = new Image();
    ghost.src = fromImg.currentSrc || fromImg.src;
    ghost.alt = '';
    ghost.className = 'fly-ghost';
    Object.assign(ghost.style, { left: `${x0}px`, top: `${y0}px`, width: `${size}px`, height: `${size}px` });
    document.body.appendChild(ghost);
    const dx = t.left + t.width / 2 - (x0 + size / 2), dy = t.top + t.height / 2 - (y0 + size / 2);
    ghost.animate([
      { transform: 'translate(0,0) scale(1) rotate(0)', opacity: 1 },
      { transform: `translate(${dx * 0.35}px, ${dy * 0.35 - 90}px) scale(.55) rotate(-8deg)`, opacity: 1, offset: 0.45 },
      { transform: `translate(${dx}px, ${dy}px) scale(.1) rotate(10deg)`, opacity: 0.35, borderRadius: '50%' },
    ], { duration: 850, easing: 'cubic-bezier(.55,0,.35,1)' }).onfinish = () => { ghost.remove(); wiggleCart(); burst(target, 8); };
  }
  /* Small particle burst around an element (wishlist, cart landing) */
  function burst(el, n = 10) {
    if (REDUCED || !el) return;
    const r = el.getBoundingClientRect(), colors = ['#EC4A24', '#E8891A', '#2F7A45', '#FFCB7A'];
    for (let i = 0; i < n; i++) {
      const d = document.createElement('span');
      d.className = 'confetti';
      Object.assign(d.style, { width: '6px', height: '6px', borderRadius: '9999px', background: colors[i % colors.length], left: `${r.left + r.width / 2 - 3}px`, top: `${r.top + r.height / 2 - 3}px` });
      document.body.appendChild(d);
      const a = (i / n) * Math.PI * 2, v = 22 + Math.random() * 16;
      d.animate([{ transform: 'translate(0,0) scale(1)', opacity: 1 }, { transform: `translate(${Math.cos(a) * v}px, ${Math.sin(a) * v}px) scale(.3)`, opacity: 0 }],
        { duration: 520, easing: 'cubic-bezier(.2,.8,.2,1)' }).onfinish = () => d.remove();
    }
  }
  /* Celebration confetti (free-delivery unlock, form success) */
  function confetti(el) {
    if (REDUCED) return;
    const src = el && el.getClientRects().length ? el : $('#cartBtn');
    const r = src.getBoundingClientRect(), colors = ['#B02914', '#E8891A', '#2F7A45', '#FFCB7A', '#EC4A24'];
    for (let i = 0; i < 42; i++) {
      const c = document.createElement('span');
      c.className = 'confetti';
      Object.assign(c.style, { background: colors[i % colors.length], left: `${r.left + r.width / 2}px`, top: `${r.top + r.height / 2}px` });
      document.body.appendChild(c);
      const a = Math.random() * Math.PI * 2, v = 80 + Math.random() * 150;
      c.animate([
        { transform: 'translate(0,0) rotate(0deg)', opacity: 1 },
        { transform: `translate(${Math.cos(a) * v}px, ${Math.sin(a) * v - 70}px) rotate(${Math.random() * 540}deg)`, opacity: 1, offset: 0.55 },
        { transform: `translate(${Math.cos(a) * v * 1.2}px, ${Math.sin(a) * v + 140}px) rotate(${Math.random() * 900}deg)`, opacity: 0 },
      ], { duration: 1300 + Math.random() * 700, easing: 'cubic-bezier(.2,.6,.4,1)' }).onfinish = () => c.remove();
    }
  }

  /* =====================================================
     CART STATE
     ===================================================== */
  function addToCart(p, v, { quiet = false } = {}) {
    const hit = state.cart.find(i => i.key === v.key);
    if (hit) hit.qty += 1;
    else state.cart.push({ key: v.key, id: p.id, weightLb: v.weightLb, cut: v.cut, qty: 1 });
    state.lastAdded = v.key;
    saveCart();
    if (!quiet) toast(`${p.preorder ? 'Reserved' : 'Added'}: ${p.name} (${v.label})`, 'check', true, { label: 'View cart', onClick: () => openCart() });
  }
  function changeQty(key, delta) {
    const item = state.cart.find(i => i.key === key);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) state.cart = state.cart.filter(i => i.key !== key);
    saveCart();
  }
  function removeWithUndo(key) {
    const idx = state.cart.findIndex(i => i.key === key);
    if (idx < 0) return;
    const [removed] = state.cart.splice(idx, 1);
    saveCart();
    refreshAction(removed.id);
    toast(`Removed ${byId(removed.id).name}`, 'trash-2', false, {
      label: 'Undo',
      onClick: () => {
        const hit = state.cart.find(i => i.key === removed.key);
        if (hit) hit.qty += removed.qty; else state.cart.splice(Math.min(idx, state.cart.length), 0, removed);
        state.lastAdded = removed.key;
        saveCart();
        refreshAction(removed.id);
      },
    });
  }
  function saveCart() {
    store.set('hms-cart', state.cart);
    renderCart();
  }
  const subtotal = () => round2(state.cart.reduce((sum, i) => sum + round2(itemPrice(i) * i.qty), 0));
  /* Order totals incl. promo code and delivery (pickup is always free) */
  function totals({ pickup = false } = {}) {
    pickup ||= PICKUP_ONLY;
    const sub = subtotal();
    const c = state.coupon ? CONFIG.coupons[state.coupon] : null;
    const discount = !c || (c.min && sub < c.min) ? 0 : c.type === 'pct' ? round2(sub * c.value / 100) : c.type === 'fixed' ? Math.min(sub, c.value) : 0;
    const freeShip = pickup || sub >= CONFIG.freeShipAt || (c && c.type === 'ship');
    const ship = sub === 0 || freeShip ? 0 : CONFIG.shipFee;
    return { sub, discount, ship, total: Math.max(0, round2(sub - discount + ship)) };
  }

  let prevSub = null;
  function renderCart({ stagger = false } = {}) {
    const count = state.cart.reduce((n, i) => n + i.qty, 0);
    const { sub, discount, ship, total } = totals();

    // Counters everywhere (with a bump when they change)
    $$('[data-cart-count]').forEach(el => {
      if (el.textContent !== String(count)) { el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); }
      el.textContent = count;
    });
    $$('[data-cart-count-plain]').forEach(el => el.textContent = count);
    $$('[data-cart-subtotal]').forEach(el => el.textContent = money(sub));
    $('#cartBtn').setAttribute('aria-label', `Open cart, ${count} ${count === 1 ? 'item' : 'items'}, ${money(sub)}`);
    $('#bnavCart').setAttribute('aria-label', `Open cart, ${count} ${count === 1 ? 'item' : 'items'}`);

    // Free-shipping meter (+ confetti when the threshold is first crossed)
    const pct = Math.min(100, (sub / CONFIG.freeShipAt) * 100);
    $('#shipBar').style.width = pct + '%';
    $('#shipMeter').setAttribute('aria-valuenow', Math.round(pct));
    $('#shipMeter').hidden = PICKUP_ONLY;
    $('#shipMsg').innerHTML = PICKUP_ONLY
      ? `<span class="inline-flex items-center gap-1.5 text-halal-700"><i data-lucide="store" class="h-4 w-4" aria-hidden="true"></i> Free pickup at 3 Kelly Street, Lansdowne</span>`
      : sub >= CONFIG.freeShipAt
      ? `<span class="inline-flex items-center gap-1.5 text-halal-700"><i data-lucide="party-popper" class="h-4 w-4" aria-hidden="true"></i> You've unlocked free cold-chain delivery!</span>`
      : `You're <strong class="text-brand-700">${money(CONFIG.freeShipAt - sub)}</strong> away from <strong>free delivery</strong>`;
    if (!PICKUP_ONLY && prevSub !== null && prevSub < CONFIG.freeShipAt && sub >= CONFIG.freeShipAt) {
      setTimeout(() => confetti(drawer.hidden ? $('#cartBtn') : $('#shipMeter')), 350);
    }
    prevSub = sub;

    // Totals
    $('#shipCost').textContent = sub === 0 ? '—' : ship === 0 ? 'Free' : money(ship);
    $('#cartTotal').textContent = money(total);
    $('#discountRow').hidden = !discount;
    $('#discountAmt').textContent = `−${money(discount)}`;
    $$('[data-coupon-slot]').forEach(el => el.innerHTML = couponHTML());
    $$('[data-ship-mini]').forEach(el => el.innerHTML = shipMiniHTML(sub));

    // Items
    const empty = state.cart.length === 0;
    $('#cartEmpty').hidden = !empty;
    $('#cartBody').hidden = empty;
    $('#cartSummary').hidden = empty;
    $('#cartItems').innerHTML = state.cart.map((i, n) => {
      const p = byId(i.id);
      const anim = stagger ? `item-in" style="--d:${n * 60}ms` : (i.key === state.lastAdded ? 'item-in' : '');
      return `
      <li class="flex gap-3 rounded-2xl bg-white p-3 ring-1 ring-bone-200 ${anim}" data-key="${esc(i.key)}">
        <img src="${img(p.img, 200)}" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover" />
        <div class="flex min-w-0 flex-1 flex-col">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <a href="${pdpHref(p.id)}" class="block truncate text-sm font-bold hover:text-brand-700 hover:underline">${esc(p.name)}</a>
              <p class="text-xs text-ink-500">${esc(lineLabel(i))}</p>
            </div>
            <button data-cart-act="remove" class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-ink-500 transition-colors hover:bg-brand-50 hover:text-brand-700" aria-label="Remove ${esc(p.name)}"><i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i></button>
          </div>
          <div class="mt-auto flex items-center justify-between pt-2">
            <div class="flex items-center rounded-full ring-1 ring-bone-300" role="group" aria-label="Quantity">
              <button data-cart-act="dec" class="grid h-8 w-8 place-items-center rounded-full hover:bg-bone-200" aria-label="${i.qty === 1 ? 'Remove' : 'Decrease quantity'}"><i data-lucide="minus" class="h-3.5 w-3.5" aria-hidden="true"></i></button>
              <span class="w-7 text-center text-sm font-bold tabular-nums">${i.qty}</span>
              <button data-cart-act="inc" class="grid h-8 w-8 place-items-center rounded-full hover:bg-bone-200" aria-label="Increase quantity"><i data-lucide="plus" class="h-3.5 w-3.5" aria-hidden="true"></i></button>
            </div>
            <span class="text-sm font-extrabold tabular-nums">${money(itemPrice(i) * i.qty)}</span>
          </div>
        </div>
      </li>`;
    }).join('');
    state.lastAdded = null;
    renderUpsell();
    // Keep the checkout page in sync with cart edits
    if (view === 'page' && page === 'checkout') {
      if (!state.cart.length) renderPage('checkout', null, { keepScroll: true });
      else if ($('#coSummary')) $('#coSummary').innerHTML = coSummaryHTML($('#coForm')?.elements.method.value, $('#coForm')?.elements.payment?.value);
    }
    icons();
  }

  /* "Pairs well with" — favour categories not yet in the cart, marinades and tagged items */
  function renderUpsell() {
    const inCart = new Set(state.cart.map(i => i.id));
    const cats = new Set(state.cart.map(i => byId(i.id).cat));
    const picks = PRODUCTS.filter(p => !inCart.has(p.id) && !p.preorder)
      .map(p => ({ p, s: (cats.has(p.cat) ? 0 : 2) + (p.cat === 'marinades' ? 1 : 0) + (p.tag ? 1 : 0) }))
      .sort((a, b) => b.s - a.s).slice(0, 4).map(x => x.p);
    $('#upsell').hidden = !picks.length;
    $('#upsellList').innerHTML = picks.map(p => {
      const v = presetVariant(p, 0);
      return `
      <li class="w-[148px] shrink-0 snap-start overflow-hidden rounded-2xl bg-white ring-1 ring-bone-200">
        <img src="${img(p.img, 300)}" alt="" loading="lazy" class="h-20 w-full object-cover" />
        <div class="p-2.5">
          <p class="truncate text-[13px] font-bold">${esc(p.name)}</p>
          <div class="mt-1 flex items-center justify-between gap-1">
            <span class="text-[12px] font-extrabold">${money(v.price)}<span class="font-semibold text-ink-500"> · ${esc(v.label)}</span></span>
            <button data-upsell="${p.id}" class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-800 text-white transition hover:rotate-90 hover:bg-brand-700" aria-label="Add ${esc(p.name)}, ${esc(v.label)}"><i data-lucide="plus" class="h-3.5 w-3.5" aria-hidden="true"></i></button>
          </div>
        </div>
      </li>`;
    }).join('');
  }
  $('#upsellList').addEventListener('click', e => {
    const b = e.target.closest('[data-upsell]');
    if (!b) return;
    const p = byId(b.dataset.upsell), v = presetVariant(p, 0);
    addToCart(p, v, { quiet: true });
    refreshAction(p.id);
    wiggleCart();
    $('#cartBody').scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' });
    $(`#cartItems [data-key="${CSS.escape(v.key)}"] [data-cart-act="inc"]`)?.focus({ preventScroll: true });
  });

  $('#cartItems').addEventListener('click', async e => {
    const btn = e.target.closest('[data-cart-act]');
    if (!btn) return;
    const li = btn.closest('[data-key]'), key = li.dataset.key, id = key.split('|')[0], act = btn.dataset.cartAct;
    const item = state.cart.find(i => i.key === key);
    if (!item) return;
    if (act === 'remove' || (act === 'dec' && item.qty === 1)) {
      const nextKey = (li.nextElementSibling || li.previousElementSibling)?.dataset.key;
      await animate(li, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(56px)' }], { duration: 220, easing: 'ease-in' })?.finished;
      removeWithUndo(key);
      ((nextKey && $(`#cartItems [data-key="${CSS.escape(nextKey)}"] [data-cart-act="remove"]`)) || $('#cartClose')).focus();
      return;
    }
    changeQty(key, act === 'inc' ? 1 : -1);
    refreshAction(id);
    $(`#cartItems [data-key="${CSS.escape(key)}"] [data-cart-act="${act}"]`)?.focus();
  });

  /* =====================================================
     CART DRAWER (open/close, focus trap, Esc)
     ===================================================== */
  let lastFocus = null;
  const drawer = $('#cartDrawer'), overlay = $('#cartOverlay');
  function openCart(from) {
    lastFocus = from || document.activeElement;
    renderCart({ stagger: true });
    drawer.hidden = overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => {
      drawer.classList.remove('translate-x-full');
      overlay.classList.replace('opacity-0', 'opacity-100');
      $('#cartClose').focus({ preventScroll: true });
    }));
  }
  function closeCart() {
    drawer.classList.add('translate-x-full');
    overlay.classList.replace('opacity-100', 'opacity-0');
    document.body.style.overflow = '';
    setTimeout(() => { drawer.hidden = overlay.hidden = true; }, REDUCED ? 0 : 330);
    lastFocus?.focus?.({ preventScroll: true });
  }
  ['#cartBtn', '#cartTab', '#bnavCart'].forEach(s => $(s).addEventListener('click', e => openCart(e.currentTarget)));
  $('#cartClose').addEventListener('click', closeCart);
  overlay.addEventListener('click', closeCart);
  $$('[data-close-cart]').forEach(a => a.addEventListener('click', () => { lastFocus = null; closeCart(); }));
  drawer.addEventListener('keydown', e => { if (e.key === 'Escape') closeCart(); else trapFocus(drawer, e); });
  $('#checkoutBtn').addEventListener('click', () => navigate('#/checkout'));

  // Delivery / pickup slots: same-day only before the cutoff
  function slotList(pickup = false) {
    const today = new Date().getHours() < CONFIG.cutoffHour;
    return pickup
      ? [...(today ? ['Today · 3 PM – 8 PM'] : []), 'Tomorrow · 9 AM – 12 PM', 'Tomorrow · 12 PM – 4 PM', 'Tomorrow · 4 PM – 8 PM']
      : [...(today ? ['Today · 5 PM – 8 PM'] : []), 'Tomorrow · 9 AM – 12 PM', 'Tomorrow · 1 PM – 4 PM', 'Tomorrow · 5 PM – 8 PM'];
  }
  $('#slotSelect').innerHTML = slotList().map(s => `<option>${s}</option>`).join('');

  /* =====================================================
     SEARCH (live results with highlight + quick-cut filters)
     ===================================================== */
  const searchInput = $('#searchInput'), panel = $('#searchPanel');
  const chipHTML = q => `<button type="button" data-quick="${esc(q)}" class="shrink-0 rounded-full border border-bone-300 bg-bone-50 px-3 py-1.5 text-[13px] font-semibold transition hover:-translate-y-0.5 hover:border-brand-500 hover:text-brand-700">${esc(q)}</button>`;
  $('#quickCuts').innerHTML = QUICK_CUTS.map(chipHTML).join('');
  $('#quickCutsMobile').innerHTML = QUICK_CUTS.map(chipHTML).join('');

  const highlight = (text, query) => {
    // Highlight the whole query, or else the first word of it that appears in the text
    const lower = text.toLowerCase();
    const q = [query, ...query.split(/\s+/)].find(t => t && lower.includes(t.toLowerCase())) || query;
    const i = lower.indexOf(q.toLowerCase());
    return i < 0 ? esc(text) : `${esc(text.slice(0, i))}<mark class="rounded bg-saffron-300/60 px-0.5 text-inherit">${esc(text.slice(i, i + q.length))}</mark>${esc(text.slice(i + q.length))}`;
  };
  function renderSearchResults(q) {
    const res = q.length >= 2 ? PRODUCTS.filter(p => matches(p, q)).slice(0, 5) : [];
    $('#searchResults').hidden = q.length < 2;
    $('#searchResults').innerHTML = res.length
      ? `<p class="mb-1 text-[11px] font-bold uppercase tracking-[.14em] text-ink-500">Products</p>` + res.map((p, n) => `
        <button type="button" role="option" data-goto="${p.id}" class="item-in flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-bone-100 focus:bg-bone-100" style="--d:${n * 35}ms">
          <img src="${img(p.img, 120)}" alt="" class="h-10 w-10 rounded-lg object-cover" />
          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">${highlight(p.name, q)}</span><span class="text-xs text-ink-500">${catName(p.cat)}</span></span>
          <span class="text-sm font-extrabold">${money(p.sold === 'unit' ? p.price : unitPrice(p))}<span class="font-semibold text-ink-500">/${p.sold === 'unit' ? p.unitLabel : state.unit}</span></span>
        </button>`).join('')
      : `<p class="py-2 text-sm text-ink-500">No matches for “${esc(q)}”. <a href="#services" data-service-link="ondemand" class="font-semibold text-brand-700 underline">Request it on demand</a></p>`;
  }
  function togglePanel(open) {
    const wasHidden = panel.hidden;
    panel.hidden = !open;
    searchInput.setAttribute('aria-expanded', open);
    if (open && wasHidden) animate(panel, [{ opacity: 0, transform: 'translateY(-8px) scale(.98)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(.2,.8,.2,1)' });
  }

  function applyQuery(q) {
    if (view === 'pdp') navigate('#shop');   // searching from a product page returns to the shop
    state.query = q.trim();
    state.cat = 'all';
    state.visible = PRODUCTS.length;
    searchInput.value = $('#mobileSearchInput').value = state.query;
    renderTabs(); renderGrid();
    togglePanel(false);
    $('#shop').scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth' });
  }
  function gotoProduct(id) {
    togglePanel(false);
    searchInput.blur();
    navigate(pdpHref(id));
  }

  searchInput.addEventListener('focus', () => { renderSearchResults(searchInput.value.trim()); togglePanel(true); });
  searchInput.addEventListener('input', () => { renderSearchResults(searchInput.value.trim()); togglePanel(true); });
  searchInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); applyQuery(searchInput.value); }
    if (e.key === 'Escape') { togglePanel(false); searchInput.blur(); }
    if (e.key === 'ArrowDown') { $('#searchResults [data-goto]')?.focus(); e.preventDefault(); }
  });
  panel.addEventListener('keydown', e => {
    const opts = $$('[data-goto]', panel), i = opts.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' && i > -1) { (opts[i + 1] || opts[0]).focus(); e.preventDefault(); }
    if (e.key === 'ArrowUp' && i > -1) { (i === 0 ? searchInput : opts[i - 1]).focus(); e.preventDefault(); }
    if (e.key === 'Escape') { togglePanel(false); searchInput.focus(); }
  });
  document.addEventListener('click', e => {
    const quick = e.target.closest('[data-quick]');
    if (quick) return applyQuery(quick.dataset.quick);
    const go = e.target.closest('[data-goto]');
    if (go) return gotoProduct(go.dataset.goto);
    if (!e.target.closest('#searchWrap')) togglePanel(false);
  });
  $('#mobileSearchInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyQuery(e.target.value); } });
  $('#clearQuery').addEventListener('click', () => {
    state.query = '';
    state.visible = CONFIG.pageSize;
    searchInput.value = $('#mobileSearchInput').value = '';
    renderGrid();
    $('#catTabs [aria-selected="true"]').focus();
  });

  // "/" focuses search (desktop)
  document.addEventListener('keydown', e => {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && drawer.hidden && qv.hidden) {
      e.preventDefault();
      // The inline search box is only shown on some widths; otherwise open the search row
      if (searchInput.getClientRects().length) searchInput.focus();
      else setMobileSearch(true);
    }
  });

  /* =====================================================
     HEADER: mobile menu, mobile search, unit toggle
     ===================================================== */
  function toggler(btnSel, panelSel, { onOpen, iconSwap } = {}) {
    const btn = $(btnSel), pnl = $(panelSel);
    const set = open => {
      pnl.hidden = !open;
      btn.setAttribute('aria-expanded', open);
      if (iconSwap) { btn.innerHTML = `<i data-lucide="${open ? 'x' : iconSwap}" class="h-5 w-5" aria-hidden="true"></i>`; icons(); }
      if (open) { animate(pnl, [{ opacity: 0, transform: 'translateY(-8px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' }); onOpen?.(); }
    };
    btn.addEventListener('click', () => set(pnl.hidden));
    pnl.addEventListener('click', e => { if (e.target.closest('a')) set(false); });
    return set;
  }
  toggler('#menuBtn', '#mobileMenu', { iconSwap: 'menu' });
  const setMobileSearch = toggler('#mobileSearchBtn', '#mobileSearch', { onOpen: () => $('#mobileSearchInput').focus() });
  $('#bnavSearch').addEventListener('click', () => { scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }); setMobileSearch(true); });

  function setUnit(u) {
    state.unit = u;
    store.set('hms-unit', u);
    Object.values(state.sel).forEach(s => { s.customW = +(u === 'kg' ? s.customW / LB_PER_KG : s.customW * LB_PER_KG).toFixed(2); });
    $$('.unit-btn').forEach(b => b.setAttribute('aria-checked', b.dataset.unit === u));
    renderGrid({ animate: false });
    renderCart();
    if (view === 'pdp') renderPDP(pdpId, { keepScroll: true });
    else if (view === 'page' && page !== 'checkout') renderPage(page, pageArg, { keepScroll: true });
    $$('#productGrid .text-xl').forEach(el => animate(el, [{ opacity: 0.2, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], { duration: 350 }));
    toast(`Prices now shown per ${u}`, 'scale');
  }
  $$('.unit-btn').forEach(b => b.addEventListener('click', () => { if (b.dataset.unit !== state.unit) setUnit(b.dataset.unit); }));
  $$('.unit-btn').forEach(b => b.setAttribute('aria-checked', b.dataset.unit === state.unit));

  /* =====================================================
     SCROLL: progress bar, header shadow, back-to-top ring
     ===================================================== */
  const toTop = $('#toTop');
  let ticking = false;
  function onScroll() {
    const h = document.documentElement;
    const p = h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight);
    $('#scrollProgress').style.transform = `scaleX(${p})`;
    $('#siteHeader').classList.toggle('shadow-card', scrollY > 8);
    const show = scrollY > 900;
    toTop.classList.toggle('opacity-0', !show);
    toTop.classList.toggle('translate-y-4', !show);
    toTop.classList.toggle('pointer-events-none', !show);
    toTop.tabIndex = show ? 0 : -1;
    $('#toTopRing').style.strokeDashoffset = 138.2 * (1 - p);
    // Product page: show the sticky buy bar once the main Add button has scrolled above the viewport
    if (view === 'pdp') {
      const a = $('#pdpBuy [data-action-area]');
      const show = !!a && a.getBoundingClientRect().bottom < 0;
      if (show !== barShown) setBar(show);
    }
  }
  addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => { onScroll(); ticking = false; });
  }, { passive: true });
  toTop.addEventListener('click', () => { scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }); $('a[href="#top"]').focus({ preventScroll: true }); });

  /* Scroll-spy for desktop nav + mobile bottom nav */
  let currentSection = 'top';
  function setSpy(id) {
    currentSection = id;
    $$('.navlink').forEach(a => {
      const on = a.dataset.spy === id && (id !== 'services' || a.dataset.serviceLink === svc);
      on ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current');
    });
    $$('.bnav[data-bnav]').forEach(a => a.dataset.bnav === id ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current'));
  }
  if ('IntersectionObserver' in window) {
    const spy = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) setSpy(e.target.id); }), { rootMargin: '-45% 0px -50% 0px' });
    ['top', 'shop', 'services', 'standard'].forEach(id => spy.observe($('#' + id)));
  }

  /* =====================================================
     SERVICES: sliding tab indicator, animated switch, success state
     ===================================================== */
  const SERVICES = {
    qurbani:  { h: 'Qurbani pre-booking', sub: 'Reserve a whole animal or a 1/7 share. Slaughtered on Eid day after Salah, per Sunnah.', hint: 'A live cow typically yields 52–55% of its live weight as meat. Final price is confirmed after the animal is weighed.', cta: 'Pre-book Qurbani' },
    aqiqah:   { h: 'Aqiqah service',      sub: 'Two animals for a boy, one for a girl — slaughtered on your chosen day with the child’s name recited.', hint: 'Choose raw or cooked meat, or ask us to distribute it to a madrasa or families in need. Leave details in the note.', cta: 'Request Aqiqah' },
    ondemand: { h: 'On-demand request',   sub: 'Can’t find a cut? Tell us what you need and we’ll source, cut and deliver it.', hint: 'Offal, bones for broth, whole lambs for events or wholesale boxes for restaurants — just ask.', cta: 'Send request' },
  };
  let svc = 'qurbani';
  function moveSvcInd(instant) {
    const t = $(`#svc-tab-${svc}`), ind = $('#svcInd');
    if (instant) ind.style.transition = 'none';
    ind.style.width = `${t.offsetWidth}px`;
    ind.style.transform = `translateX(${t.offsetLeft}px)`;
    if (instant) { void ind.offsetWidth; ind.style.transition = ''; }
  }
  if ('ResizeObserver' in window) { const ro = new ResizeObserver(() => moveSvcInd(true)); $$('.svc-tab').forEach(t => ro.observe(t)); }

  function setService(key, focus) {
    const changed = key !== svc;
    svc = key;
    $$('.svc-tab').forEach(t => { const on = t.dataset.svc === key; t.setAttribute('aria-selected', on); t.tabIndex = on ? 0 : -1; if (on && focus) t.focus(); });
    $$('[data-svc-fields]').forEach(f => f.hidden = f.dataset.svcFields !== key);
    const s = SERVICES[key];
    $('#svcHeading').textContent = s.h;
    $('#svcSub').textContent = s.sub;
    $('[data-hint]').textContent = s.hint;
    $('[data-submit-label]').textContent = s.cta;
    $('#svcForm').setAttribute('aria-labelledby', `svc-tab-${key}`);
    moveSvcInd();
    if (!$('#svcSuccess').hidden) showSvcForm(false);
    if (changed) [$('#svcHeading'), $('#svcSub'), $(`[data-svc-fields="${key}"]`), $('#svcHint')].forEach((el, i) =>
      animate(el, [{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }], { duration: 380, delay: i * 55, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' }));
    setSpy(currentSection);
  }
  $('#svcTabs').addEventListener('click', e => { const t = e.target.closest('.svc-tab'); if (t) setService(t.dataset.svc); });
  $('#svcTabs').addEventListener('keydown', e => {
    if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    const keys = Object.keys(SERVICES), i = keys.indexOf(svc);
    setService(keys[(i + (e.key === 'ArrowRight' ? 1 : -1) + keys.length) % keys.length], true);
    e.preventDefault();
  });
  document.addEventListener('click', e => { const l = e.target.closest('[data-service-link]'); if (l) setService(l.dataset.serviceLink); });

  function showSvcForm(focus = true) {
    $('#svcSuccess').hidden = true;
    $('#svcForm').hidden = false;
    if (focus) $('#fName').focus();
  }
  $('#svcAgain').addEventListener('click', () => showSvcForm());

  $('#svcForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.currentTarget;
    if (form.dataset.busy) return;
    const required = [$('#fName'), $('#fPhone')];
    let firstBad = null;
    required.forEach(f => {
      const bad = !f.value.trim();
      f.setAttribute('aria-invalid', bad);
      if (bad) {
        firstBad ??= f;
        animate(f, [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }], { duration: 360 });
      }
    });
    if (firstBad) { firstBad.focus(); return toast('Please add your name and phone number.', 'circle-alert'); }
    if (SERVER) {
      // Only the fields for the selected service (the other tabs' fields are hidden)
      const fields = {};
      $$('[name]', form).filter(el => !el.closest('[data-svc-fields][hidden]') && el.value && (el.type !== 'checkbox' || el.checked)).forEach(el => {
        const label = (el.id && $(`label[for="${el.id}"]`)?.textContent.trim()) || el.name;
        const key = ['name', 'phone', 'email'].includes(el.name) ? el.name : label;
        if (el.type === 'checkbox') (fields[key] ??= []).push(el.value);
        else fields[key] = el.value.trim();
      });
      const btn = form.querySelector('button[type="submit"], button:not([type])');
      form.dataset.busy = '1';
      if (btn) btn.disabled = true;
      try {
        await api('request.php', { type: 'service', service: svc, fields });
      } catch (err) {
        return toast(err.message, 'circle-alert');
      } finally {
        delete form.dataset.busy;
        if (btn) btn.disabled = false;
      }
    }
    $('[data-success-name]').textContent = $('#fName').value.trim().split(/\s+/)[0];
    $('[data-success-what]').textContent = `Your ${SERVICES[svc].h.toLowerCase()} request`;
    form.reset();
    required.forEach(f => f.removeAttribute('aria-invalid'));
    form.hidden = true;
    $('#svcSuccess').hidden = false;
    $('#svcSuccess').focus();
    setTimeout(() => confetti($('#svcSuccess svg')), 300);
  });
  $$('#fName, #fPhone').forEach(f => f.addEventListener('input', () => f.value.trim() && f.removeAttribute('aria-invalid')));

  /* Newsletter: button morphs into a success state */
  $('#newsletterForm').addEventListener('submit', async e => {
    e.preventDefault();
    const input = $('#nlEmail'), btn = $('#nlBtn');
    if (!/^\S+@\S+\.\S+$/.test(input.value)) {
      input.focus();
      animate(input, [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(0)' }], { duration: 320 });
      return toast('Please enter a valid email address.', 'circle-alert');
    }
    if (SERVER) {
      btn.disabled = true;
      try { await api('request.php', { type: 'newsletter', email: input.value.trim() }); }
      catch (err) { return toast(err.message, 'circle-alert'); }
      finally { btn.disabled = false; }
    }
    input.value = '';
    btn.innerHTML = `<i data-lucide="check" class="h-4 w-4" aria-hidden="true"></i> Subscribed`;
    btn.classList.replace('bg-saffron-400', 'bg-halal-500');
    btn.classList.add('text-white');
    icons();
    confetti(btn);
    toast('You’re on the list — watch for Friday’s batch.', 'mail-check', true);
    setTimeout(() => { btn.textContent = 'Subscribe'; btn.classList.replace('bg-halal-500', 'bg-saffron-400'); btn.classList.remove('text-white'); }, 3500);
  });

  /* =====================================================
     TOASTS (with countdown bar, pause on hover, optional action)
     ===================================================== */
  function toast(msg, icon = 'info', good = false, action = null) {
    const box = $('#toasts');
    while (box.children.length >= 3) box.firstElementChild.remove();
    const dur = action ? 5000 : 3400;
    const t = document.createElement('div');
    t.className = 'pointer-events-auto relative flex w-full max-w-md items-center gap-3 overflow-hidden rounded-2xl bg-ink-900 px-4 py-3 text-sm font-semibold text-white shadow-lift';
    t.innerHTML = `
      <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full ${good ? 'bg-halal-500' : 'bg-white/15'}"><i data-lucide="${icon}" class="h-4 w-4" aria-hidden="true"></i></span>
      <span class="flex-1">${esc(msg)}</span>
      ${action ? `<button type="button" class="shrink-0 rounded-full bg-white/10 px-3 py-1.5 text-xs font-extrabold text-saffron-300 hover:bg-white/20">${esc(action.label)}</button>` : ''}
      <span class="toast-bar absolute inset-x-0 bottom-0 h-0.5 bg-saffron-400/80" style="--t:${dur}ms" aria-hidden="true"></span>`;
    box.appendChild(t);
    icons();
    animate(t, [{ opacity: 0, transform: 'translateY(16px) scale(.96)' }, { opacity: 1, transform: 'none' }], { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' });
    let timer, gone = false;
    const dismiss = () => {
      if (gone) return;
      gone = true;
      clearTimeout(timer);
      const a = animate(t, [{ opacity: 1 }, { opacity: 0, transform: 'translateY(8px)' }], { duration: 200 });
      a ? (a.onfinish = () => t.remove()) : t.remove();
    };
    timer = setTimeout(dismiss, dur);
    if (action) t.querySelector('button').addEventListener('click', () => { action.onClick(); dismiss(); });
    const bar = t.querySelector('.toast-bar');
    t.addEventListener('mouseenter', () => { clearTimeout(timer); bar.style.animationPlayState = 'paused'; });
    t.addEventListener('mouseleave', () => { timer = setTimeout(dismiss, 1500); bar.style.animationPlayState = 'running'; });
  }

  /* =====================================================
     FOOTER: open-now badge + year
     ===================================================== */
  (function openNow() {
    const d = new Date(), day = d.getDay(), h = d.getHours() + d.getMinutes() / 60;
    const ranges = day === 5 ? [[8, 12.5], [14.5, 21]] : (day === 0 || day === 6) ? [[7, 21]] : [[8, 20]];
    const open = ranges.some(([a, b]) => h >= a && h < b);
    $$('#openNow, #openNow2').forEach(el => {
    el.innerHTML = `<span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full ${open ? 'bg-halal-500' : 'bg-brand-500'} opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full ${open ? 'bg-halal-500' : 'bg-brand-500'}"></span></span>${open ? 'Open now' : 'Closed now'} · local time`;
    if (!open) el.className = el.className.replace('bg-halal-50', 'bg-brand-50').replace('text-halal-700', 'text-brand-700');
    });
  })();
  $('#year').textContent = new Date().getFullYear();

  /* =====================================================
     PRODUCT DETAILS PAGE  (#/product/<id>)
     ===================================================== */
  let view = 'home', pdpId = null, pdpTab = 'desc';
  let prevHash = '', lastHomeHash = '', homeScroll = 0, linkNav = false;
  const HOME_TITLE = document.title, HOME_DESC = $('meta[name="description"]').content;
  const crop = (id, x, y, z, w = 1200) => `https://images.unsplash.com/photo-${id}?w=${w}&h=${w}&q=75&auto=format&fit=crop&crop=focalpoint&fp-x=${x}&fp-y=${y}&fp-z=${z}`;
  const galleryOf = p => [
    { src: img(p.img, 1200), thumb: img(p.img, 200), label: p.sold === 'unit' ? 'The animal' : 'Full cut' },
    { src: crop(p.img, 0.4, 0.5, 1.8), thumb: crop(p.img, 0.4, 0.5, 1.8, 200), label: 'Close-up' },
    { src: crop(p.img, 0.62, 0.42, 2.6), thumb: crop(p.img, 0.62, 0.42, 2.6, 200), label: 'Texture detail' },
  ];
  const cutoffLabel = () => `${CONFIG.cutoffHour % 12 || 12} ${CONFIG.cutoffHour < 12 ? 'AM' : 'PM'}`;

  /* Link clicks and programmatic navigation set linkNav; browser Back/Forward doesn't —
     so returning "back" to the shop can restore the exact scroll position. */
  document.addEventListener('click', e => { if (e.target.closest('a[href^="#"]')) linkNav = true; }, true);
  function navigate(hash) { linkNav = true; location.hash = hash; }

  function packHTML(p) {
    if (p.sold === 'unit') {
      const s = (DETAILS[p.id] || {}).specs || {};
      return `<i data-lucide="package-check" class="h-4 w-4 shrink-0 text-halal-600" aria-hidden="true"></i><span>${esc(s['Expected meat'] || s['Expected meat yield'] || 'Processed your way')} · cut &amp; packed your way</span>`;
    }
    const v = variantOf(p), serves = Math.max(1, Math.round(v.weightLb / 0.5));   // ~8 oz raw per person
    return `<i data-lucide="package" class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i><span>Your pack: <strong class="text-ink-900">${esc(v.label)}</strong> · serves ~${serves} · <strong class="text-ink-900">${money(v.price)}</strong></span>`;
  }
  function shipMiniHTML(sub) {
    if (PICKUP_ONLY) return `<p class="flex items-center gap-1.5 text-[13px] font-semibold text-halal-700"><i data-lucide="store" class="h-3.5 w-3.5" aria-hidden="true"></i>Order online · free pickup at 3 Kelly Street</p>`;
    const pct = Math.min(100, (sub / CONFIG.freeShipAt) * 100);
    const msg = sub >= CONFIG.freeShipAt
      ? `<span class="inline-flex items-center gap-1.5 text-halal-700"><i data-lucide="party-popper" class="h-3.5 w-3.5" aria-hidden="true"></i>Free cold-chain delivery unlocked</span>`
      : `Add <strong class="text-brand-700">${money(CONFIG.freeShipAt - sub)}</strong> more for free delivery`;
    return `<p class="text-[13px] font-semibold text-ink-700">${msg}</p><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-bone-200"><div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-saffron-400 transition-[width] duration-500" style="width:${pct}%"></div></div>`;
  }
  const infoCard = (icon, title, body, tone = 'text-brand-600') => `
    <li class="rounded-2xl bg-white p-5 ring-1 ring-bone-200">
      <i data-lucide="${icon}" class="h-5 w-5 ${tone}" aria-hidden="true"></i>
      <p class="mt-3 font-bold text-ink-900">${title}</p>
      <p class="mt-1 text-sm leading-relaxed text-ink-600">${body}</p>
    </li>`;

  function renderPDP(id, { keepScroll = false } = {}) {
    const p = byId(id), el = $('#pdp');
    pdpId = id;
    if (!p) {
      el.innerHTML = `
        <div class="view-in mx-auto max-w-lg py-24 text-center">
          <i data-lucide="search-x" class="mx-auto h-12 w-12 text-ink-500" aria-hidden="true"></i>
          <h1 id="pdpTitle" tabindex="-1" class="mt-4 font-display text-3xl font-semibold focus:outline-none">We couldn’t find that cut</h1>
          <p class="mt-2 text-ink-500">It may have sold out or moved. Today’s fresh cuts are waiting for you.</p>
          <a href="#shop" class="mt-6 inline-flex h-12 items-center rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700">Browse today’s cuts</a>
        </div>`;
      document.title = 'Not found — Halal Brothers';
      icons(); setBar(false);
      $('#pdpTitle').focus({ preventScroll: true });
      return;
    }
    const d = DETAILS[p.id] || { about: p.desc, highlights: [], specs: {}, cook: {} };
    const g = galleryOf(p), c = cutoffInfo(), wish = state.wish.has(p.id);
    const n = NUTRITION[p.cat], st = CAT_STORE[p.cat], unitSold = p.sold === 'unit';
    const related = [...PRODUCTS.filter(x => x.cat === p.cat && x.id !== p.id), ...PRODUCTS.filter(x => x.cat !== p.cat && !x.preorder)].slice(0, 4);
    const recent = store.get('hms-recent', []).filter(r => r !== p.id && byId(r)).slice(0, 6);
    const specRows = {
      ...d.specs,
      'Slaughter method': 'Hand-slaughtered (zabiha), Tasmiyah recited',
      Stunning: 'None — never stunned or machine-slaughtered',
      Origin: 'Partner family farms',
      Packaging: unitSold ? 'Labelled cut packs in an insulated box' : 'Vacuum-sealed, labelled with batch code',
      'Shelf life': `${st.fridge} · freezer ${st.freezer.toLowerCase()}`,
      'Sold by': unitSold ? `Per ${p.unitLabel} (pre-order)` : `Weight — priced per ${state.unit}`,
    };
    const batch = `HB-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${p.id.replace('-', '').toUpperCase()}`;
    const TABS = [['desc', 'Description'], ['specs', 'Specifications'], ['cook', 'Cooking & storage'], ['halal', 'Halal & sourcing'], ['delivery', 'Delivery & returns']];
    const savings = p.was ? (p.was - p.price) * (state.unit === 'lb' ? 1 : LB_PER_KG) : 0;

    el.innerHTML = `
    <div class="view-in">
      <!-- Breadcrumb -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Breadcrumb">
          <ol class="flex flex-wrap items-center gap-1.5 text-sm text-ink-500">
            <li><a href="#/" class="hover:text-brand-700">Home</a></li>
            <li aria-hidden="true"><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></li>
            <li><a href="#shop" data-jump-cat="${p.cat}" class="hover:text-brand-700">${catName(p.cat)}</a></li>
            <li aria-hidden="true"><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></li>
            <li aria-current="page" class="max-w-[14rem] truncate font-semibold text-ink-900 sm:max-w-none">${esc(p.name)}</li>
          </ol>
        </nav>
        <a href="#shop" class="group/back hidden items-center gap-1.5 py-1 text-sm font-semibold text-ink-600 hover:text-brand-700 sm:inline-flex"><i data-lucide="arrow-left" class="h-4 w-4 transition-transform group-hover/back:-translate-x-1" aria-hidden="true"></i> Back to shop</a>
      </div>

      <div class="mt-6 grid gap-10 lg:grid-cols-[1.05fr_.95fr] lg:gap-14">
        <!-- Gallery -->
        <div class="lg:sticky lg:top-24 lg:self-start">
          <div class="grid gap-3 sm:grid-cols-[84px_1fr]">
            <div id="pdpThumbs" class="order-2 flex gap-3 sm:order-1 sm:flex-col" role="group" aria-label="Product images">
              ${g.map((x, i) => `<button type="button" data-thumb="${i}" aria-current="${i === 0}" aria-label="Show image ${i + 1} of ${g.length}: ${x.label}" class="thumb h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-bone-200 sm:h-[84px] sm:w-[84px]"><img src="${x.thumb}" alt="" loading="lazy" class="h-full w-full object-cover" /></button>`).join('')}
            </div>
            <div class="order-1 sm:order-2">
              <div id="pdpZoom" class="skeleton group/zoom relative aspect-square overflow-hidden rounded-[2rem] bg-bone-200 shadow-card [@media(pointer:fine)]:cursor-zoom-in">
                <img id="pdpMainImg" src="${g[0].src}" alt="${esc(p.name)} — ${g[0].label}" class="img-fade h-full w-full object-cover" fetchpriority="high" ${onImg} />
                <div class="pointer-events-none absolute left-4 top-4 flex flex-wrap gap-1.5">${badgesHTML(p)}</div>
                <button type="button" data-gal="-1" class="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 shadow-lg backdrop-blur transition hover:bg-white focus:opacity-100 [@media(pointer:fine)]:opacity-0 [@media(pointer:fine)]:group-hover/zoom:opacity-100" aria-label="Previous image"><i data-lucide="chevron-left" class="h-5 w-5" aria-hidden="true"></i></button>
                <button type="button" data-gal="1" class="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 shadow-lg backdrop-blur transition hover:bg-white focus:opacity-100 [@media(pointer:fine)]:opacity-0 [@media(pointer:fine)]:group-hover/zoom:opacity-100" aria-label="Next image"><i data-lucide="chevron-right" class="h-5 w-5" aria-hidden="true"></i></button>
                <span class="pointer-events-none absolute bottom-4 left-4 hidden items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-bold backdrop-blur transition-opacity group-hover/zoom:opacity-0 [@media(pointer:fine)]:inline-flex"><i data-lucide="zoom-in" class="h-3.5 w-3.5" aria-hidden="true"></i> Hover to zoom</span>
                <span id="pdpCounter" class="pointer-events-none absolute bottom-4 right-4 rounded-full bg-ink-900/70 px-2.5 py-1 text-xs font-bold tabular-nums text-white backdrop-blur">1 / ${g.length}</span>
              </div>
            </div>
          </div>
          <ul class="mt-5 grid grid-cols-3 gap-3 text-center text-[12px] font-semibold leading-snug text-ink-600">
            <li class="rounded-2xl bg-white p-3 ring-1 ring-bone-200"><i data-lucide="shield-check" class="mx-auto h-5 w-5 text-halal-600" aria-hidden="true"></i><span class="mt-1.5 block">Hand-slaughtered zabiha</span></li>
            <li class="rounded-2xl bg-white p-3 ring-1 ring-bone-200"><i data-lucide="snowflake" class="mx-auto h-5 w-5 text-sky-600" aria-hidden="true"></i><span class="mt-1.5 block">Delivered at 0–4 °C</span></li>
            <li class="rounded-2xl bg-white p-3 ring-1 ring-bone-200"><i data-lucide="scan-line" class="mx-auto h-5 w-5 text-brand-600" aria-hidden="true"></i><span class="mt-1.5 block">Farm-traceable batch</span></li>
          </ul>
        </div>

        <!-- Buy box -->
        <div id="pdpBuy" data-id="${p.id}" data-fly="#pdpMainImg">
          <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-600">${catName(p.cat)}</p>
          <h1 id="pdpTitle" tabindex="-1" class="mt-2 font-display text-4xl font-semibold leading-[1.05] tracking-tight text-ink-900 focus:outline-none sm:text-5xl">${esc(p.name)}</h1>
          <p class="mt-4 text-lg leading-relaxed text-ink-600">${esc(p.desc)}</p>
          ${priceHTML(p, true)}
          ${savings ? `<p class="mt-2"><span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-extrabold text-brand-700 ring-1 ring-brand-100"><i data-lucide="tag" class="h-3 w-3" aria-hidden="true"></i>You save ${money(savings)} / ${state.unit}</span></p>` : ''}
          ${stockUI(p)}

          <div class="mt-6 rounded-3xl bg-white p-5 shadow-card ring-1 ring-bone-200 sm:p-6">
            <p class="text-sm font-bold text-ink-900">${unitSold ? 'Reserve for Eid' : 'Choose your pack'}</p>
            ${weightUI(p)}
            <p data-pack class="mt-4 flex items-center gap-2 rounded-xl bg-bone-100 px-3 py-2.5 text-[13px] text-ink-600">${packHTML(p)}</p>
            <div class="mt-4 flex items-center gap-2">
              <div class="flex-1" data-action-area>${actionHTML(p)}</div>
              <button data-act="wish" aria-pressed="${wish}" aria-label="${wish ? 'Remove from' : 'Save to'} wishlist: ${esc(p.name)}" class="grid h-11 w-11 shrink-0 place-items-center rounded-full ring-1 ring-bone-300 transition hover:ring-brand-500 ${wish ? 'text-brand-600' : 'text-ink-700'}"><i data-lucide="heart" class="h-4 w-4 ${wish ? 'fill-current' : ''}" aria-hidden="true"></i></button>
              <button data-act="share" aria-label="Share ${esc(p.name)}" class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-ink-700 ring-1 ring-bone-300 transition hover:text-brand-700 hover:ring-brand-500"><i data-lucide="share-2" class="h-4 w-4" aria-hidden="true"></i></button>
            </div>
            <div class="mt-5 border-t border-dashed border-bone-300 pt-4">
              <p class="flex items-center gap-2 text-[13px] text-ink-600"><i data-lucide="truck" class="h-4 w-4 shrink-0 text-halal-600" aria-hidden="true"></i><span>${unitSold ? 'Delivered chilled within <strong class="text-ink-900">48 hours of Eid slaughter</strong>' : c.open ? `Order within <strong class="text-ink-900">${c.text}</strong> for <strong class="text-ink-900">same-day</strong> delivery` : 'Order now for delivery <strong class="text-ink-900">tomorrow from 9 AM</strong>'}</span></p>
              <div class="mt-3" data-ship-mini>${shipMiniHTML(subtotal())}</div>
            </div>
          </div>

          ${d.highlights.length ? `<ul class="mt-6 space-y-2.5">${d.highlights.map(h => `<li class="flex items-start gap-2.5 text-[15px] text-ink-700"><span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-halal-50 text-halal-600"><i data-lucide="check" class="h-3 w-3" aria-hidden="true"></i></span>${esc(h)}</li>`).join('')}</ul>` : ''}
        </div>
      </div>

      <!-- Detail tabs -->
      <section id="pdpInfo" class="mt-16 scroll-mt-20 sm:mt-20" aria-label="Product information">
        <div class="sticky top-[72px] z-20 -mx-4 border-b border-bone-300 bg-bone-100/90 px-4 backdrop-blur-lg sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
          <div id="pdpTabs" role="tablist" aria-label="Product information" class="no-scrollbar relative flex gap-2 overflow-x-auto py-3">
            <span id="pdpTabInd" class="tab-ind" aria-hidden="true"></span>
            ${TABS.map(([k, label], i) => `<button role="tab" id="pdp-tab-${k}" data-pdp-tab="${k}" aria-controls="pdp-panel-${k}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}" class="cat-tab">${label}</button>`).join('')}
          </div>
        </div>

        <div class="pt-8">
          <!-- Description -->
          <div role="tabpanel" id="pdp-panel-desc" aria-labelledby="pdp-tab-desc" data-pdp-panel="desc" tabindex="0" class="grid gap-10 focus:outline-none lg:grid-cols-[1fr_320px]">
            <article class="max-w-3xl">
              <h2 class="font-display text-3xl font-semibold tracking-tight">About this ${unitSold ? 'animal' : 'cut'}</h2>
              <div class="mt-5 space-y-5 text-[16px] leading-[1.85] text-ink-700">
                <p class="drop-cap">${esc(d.about)}</p>
                <p>${esc(CAT_STORY[p.cat])}</p>
                <p>${esc(unitSold ? 'After slaughter, your animal is processed to your instructions, packed by cut type and labelled with your name and order number. It travels in an insulated box with ice packs, so it reaches your door at 0–4 °C.' : BUTCHERY)}</p>
              </div>
              <h3 class="mt-10 font-display text-xl font-semibold">Why you’ll love it</h3>
              <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                ${d.highlights.map((h, i) => `<li class="rounded-2xl bg-white p-4 ring-1 ring-bone-200"><i data-lucide="${['sparkles', 'award', 'heart-handshake'][i % 3]}" class="h-5 w-5 text-brand-600" aria-hidden="true"></i><p class="mt-2 text-sm font-semibold leading-snug">${esc(h)}</p></li>`).join('')}
              </ul>
              <h3 class="mt-10 font-display text-xl font-semibold">What’s in the box</h3>
              <ul class="mt-4 grid gap-3 sm:grid-cols-3">
                ${infoCard('package', unitSold ? 'Labelled cut packs' : 'Vacuum-sealed pack', unitSold ? 'Sorted by cut, labelled with your name and order number.' : 'Labelled with the cut, weight, slaughter date and batch code.')}
                ${infoCard('snowflake', 'Insulated cold box', 'Ice packs keep everything at 0–4 °C in transit.', 'text-sky-600')}
                ${infoCard('file-text', 'Care card', 'Storage times and the cooking guide from this page.', 'text-saffron-500')}
              </ul>
            </article>
            <aside>
              ${unitSold ? `
              <div class="rounded-3xl bg-brand-900 p-6 text-bone-100 lg:sticky lg:top-40">
                <p class="font-display text-xl font-semibold text-white">Your Qurbani, step by step</p>
                <ol class="mt-5 space-y-5 border-l border-white/15 pl-5 text-sm">
                  ${[['Reserve now', 'Pre-order online — we confirm by phone within 2 hours.'], ['Before Eid', 'We select and health-check your animal.'], ['Eid day', 'Slaughtered by hand after Salah, your name recited.'], ['Within 48 hours', 'Processed your way and delivered chilled — or donated.']]
                    .map(([t, b]) => `<li class="relative"><span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full bg-saffron-400 ring-4 ring-brand-900"></span><p class="font-bold text-white">${t}</p><p class="mt-0.5 text-bone-300">${b}</p></li>`).join('')}
                </ol>
              </div>` : `
              <div class="nutri rounded-xl bg-white p-4 text-ink-900 lg:sticky lg:top-40">
                <p class="font-display text-2xl font-bold leading-none">Nutrition facts</p>
                <p class="mt-1 text-xs text-ink-500">Typical values per 100 g, raw</p>
                <div class="rule-thick mt-2"></div>
                <div class="flex items-end justify-between py-1"><span class="text-sm font-extrabold">Calories</span><span class="text-3xl font-extrabold leading-none">${n.kcal}</span></div>
                <div class="rule-thick"></div>
                <div class="rule flex justify-between py-1.5 text-sm"><span class="font-bold">Protein</span><span>${n.protein} g</span></div>
                <div class="rule flex justify-between py-1.5 text-sm"><span class="font-bold">Total fat</span><span>${n.fat} g</span></div>
                <div class="rule flex justify-between py-1.5 pl-4 text-sm"><span>Saturated fat</span><span>${n.sat} g</span></div>
                <div class="rule flex justify-between py-1.5 text-sm"><span class="font-bold">Carbohydrate</span><span>0 g</span></div>
                <div class="rule flex justify-between py-1.5 text-sm"><span class="font-bold">Iron</span><span>${n.iron} mg</span></div>
                <div class="rule flex justify-between py-1.5 text-sm"><span class="font-bold">Vitamin B12</span><span>${n.b12} µg</span></div>
                <p class="mt-3 text-[11px] leading-snug text-ink-500">Approximate values for this category${p.cat === 'marinades' ? ' (before marinade)' : ''}; actual values vary by cut and trim.</p>
              </div>`}
            </aside>
          </div>

          <!-- Specifications -->
          <div role="tabpanel" id="pdp-panel-specs" aria-labelledby="pdp-tab-specs" data-pdp-panel="specs" tabindex="0" class="focus:outline-none" hidden>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Specifications</h2>
            <dl class="mt-6 grid overflow-hidden rounded-3xl bg-white ring-1 ring-bone-200 md:grid-cols-2">
              ${Object.entries(specRows).map(([k, v]) => `<div class="flex gap-4 border-b border-bone-200 px-5 py-4 md:odd:border-r"><dt class="w-36 shrink-0 text-sm font-bold text-ink-900 sm:w-44">${esc(k)}</dt><dd class="text-sm text-ink-600">${esc(v)}</dd></div>`).join('')}
            </dl>
          </div>

          <!-- Cooking & storage -->
          <div role="tabpanel" id="pdp-panel-cook" aria-labelledby="pdp-tab-cook" data-pdp-panel="cook" tabindex="0" class="focus:outline-none" hidden>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Cooking guide</h2>
            <ul class="mt-6 grid gap-4 md:grid-cols-3">
              ${infoCard('flame', 'How to cook', esc(d.cook.method || 'Cook as you prefer.'))}
              ${infoCard('thermometer', 'Target temperature', esc(d.cook.temp || '—'), 'text-sky-600')}
              ${infoCard('chef-hat', 'Butcher’s tip', esc(d.cook.tip || '—'), 'text-saffron-500')}
            </ul>
            <h3 class="mt-10 font-display text-xl font-semibold">Storage</h3>
            <ul class="mt-4 grid gap-4 sm:grid-cols-3">
              ${infoCard('refrigerator', 'Fridge', st.fridge, 'text-sky-600')}
              ${infoCard('snowflake', 'Freezer', st.freezer, 'text-sky-600')}
              ${infoCard('clock-3', 'Thawing', st.thaw, 'text-brand-600')}
            </ul>
            <p class="mt-6 flex gap-2 rounded-xl bg-saffron-300/20 px-4 py-3 text-[13px] leading-relaxed text-ink-700"><i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-500" aria-hidden="true"></i>Temperatures are internal readings taken at the thickest point — a probe thermometer gives the best results.</p>
          </div>

          <!-- Halal & sourcing -->
          <div role="tabpanel" id="pdp-panel-halal" aria-labelledby="pdp-tab-halal" data-pdp-panel="halal" tabindex="0" class="grid gap-10 focus:outline-none lg:grid-cols-[1fr_380px]" hidden>
            <div>
              <h2 class="font-display text-3xl font-semibold tracking-tight">From farm to table — the zabiha way</h2>
              <ol class="mt-8 space-y-6">
                ${[['sprout', 'Raised on partner farms', 'Pasture-raised without growth hormones, on small farms we visit personally.'],
                   ['hand', 'Hand-slaughtered', 'A trained Muslim slaughterman makes a single swift cut with a sharp blade, reciting the Tasmiyah, with the animal facing the Qiblah. No stunning, no machines.'],
                   ['droplets', 'Fully bled & inspected', 'The animal is fully drained of blood, then inspected before chilling.'],
                   ['snowflake', 'Chilled & cut to order', 'Dry-chilled at 0–4 °C, then butchered to your order on the morning it ships.']]
                  .map(([ic, t, b], i) => `<li class="flex gap-4"><span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-halal-50 text-halal-600"><i data-lucide="${ic}" class="h-5 w-5" aria-hidden="true"></i><span class="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-ink-900 text-[10px] font-extrabold text-white">${i + 1}</span></span><div><p class="font-bold text-ink-900">${t}</p><p class="mt-1 text-sm leading-relaxed text-ink-600">${b}</p></div></li>`).join('')}
              </ol>
            </div>
            <aside class="self-start rounded-3xl bg-ink-900 p-6 text-bone-100">
              <i data-lucide="scan-line" class="h-6 w-6 text-saffron-400" aria-hidden="true"></i>
              <p class="mt-3 font-display text-xl font-semibold text-white">Traceable to the farm</p>
              <p class="mt-2 text-sm leading-relaxed text-bone-300">Every label carries a batch code linking your pack to the farm, slaughter date and slaughterman.</p>
              <p class="mt-4 rounded-xl bg-white/5 px-4 py-3 font-mono text-sm tracking-wider text-saffron-300 ring-1 ring-white/10">${batch}</p>
              <p class="mt-1.5 text-[11px] text-bone-400">Example batch code</p>
              <a href="#/page/halal" class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-ink-900 hover:bg-bone-200"><i data-lucide="file-badge" class="h-4 w-4 text-halal-600" aria-hidden="true"></i> Our halal certification</a>
            </aside>
          </div>

          <!-- Delivery & returns -->
          <div role="tabpanel" id="pdp-panel-delivery" aria-labelledby="pdp-tab-delivery" data-pdp-panel="delivery" tabindex="0" class="focus:outline-none" hidden>
            <h2 class="font-display text-3xl font-semibold tracking-tight">Delivery &amp; returns</h2>
            <ul class="mt-6 grid gap-4 md:grid-cols-2">
              ${infoCard('truck', 'Same-day delivery', `Order before ${cutoffLabel()} for delivery the same evening (5–8 PM). Later orders arrive the next day from 9 AM.`, 'text-halal-600')}
              ${infoCard('wallet', `Free over ${money(CONFIG.freeShipAt)}`, `Cold-chain delivery is free on orders over ${money(CONFIG.freeShipAt)}; otherwise it’s ${money(CONFIG.shipFee)}.`)}
              ${infoCard('snowflake', 'Cold-chain packaging', 'Insulated box with ice packs, kept at 0–4 °C from our chiller to your door.', 'text-sky-600')}
              ${infoCard('shield-check', 'Freshness promise', 'If anything isn’t right, contact us within 24 hours of delivery and we’ll replace it or refund you.', 'text-halal-600')}
              ${p.preorder ? infoCard('calendar-check', 'Eid pre-orders', 'Qurbani orders are slaughtered on Eid day and delivered within 48 hours — or donated on your behalf.', 'text-saffron-500') : ''}
            </ul>
          </div>
        </div>
      </section>

      <!-- Related -->
      <section class="mt-24" aria-labelledby="relTitle">
        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-brand-600">You may also like</p>
            <h2 id="relTitle" class="mt-2 font-display text-3xl font-semibold tracking-tight">More from ${catName(p.cat)}</h2>
          </div>
          <a href="#shop" data-jump-cat="${p.cat}" class="group/all hidden items-center gap-1.5 py-1 text-sm font-bold text-brand-700 hover:underline sm:inline-flex">View all <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover/all:translate-x-1" aria-hidden="true"></i></a>
        </div>
        <div id="pdpRelated" class="mt-8 grid grid-cols-1 gap-5 min-[480px]:grid-cols-2 lg:grid-cols-4">${related.map(x => cardHTML(x, 'rel')).join('')}</div>
      </section>

      ${recent.length ? `
      <section class="mt-16" aria-labelledby="recentTitle">
        <h2 id="recentTitle" class="font-display text-2xl font-semibold tracking-tight">Recently viewed</h2>
        <ul class="no-scrollbar mt-5 flex gap-4 overflow-x-auto pb-2">
          ${recent.map(r => { const q = byId(r); return `<li class="w-40 shrink-0"><a href="${pdpHref(q.id)}" class="group block"><div class="aspect-square overflow-hidden rounded-2xl bg-bone-200"><img src="${img(q.img, 320)}" alt="" loading="lazy" class="zoom-img h-full w-full object-cover" /></div><p class="mt-2 truncate text-sm font-bold group-hover:text-brand-700">${esc(q.name)}</p><p class="text-xs text-ink-500">${money(q.sold === 'unit' ? q.price : unitPrice(q))} / ${q.sold === 'unit' ? q.unitLabel : state.unit}</p></a></li>`; }).join('')}
        </ul>
      </section>` : ''}
    </div>`;

    icons();
    $$('#pdpRelated > article').forEach((card, i) => reveal(card, i * 80));
    initGallery(g, p);
    initPdpTabs();
    renderPdpBar(p);
    updateMeta(p);
    if (!keepScroll) {
      scrollTo({ top: 0, behavior: 'instant' });
      $('#pdpTitle').focus({ preventScroll: true });
    }
  }

  /* Gallery: thumbnails, arrows, swipe, hover-zoom */
  function initGallery(g, p) {
    let idx = 0;
    const main = $('#pdpMainImg'), zoom = $('#pdpZoom');
    const show = i => {
      idx = (i + g.length) % g.length;
      $$('#pdpThumbs [data-thumb]').forEach(t => t.setAttribute('aria-current', String(+t.dataset.thumb === idx)));
      $('#pdpCounter').textContent = `${idx + 1} / ${g.length}`;
      main.classList.remove('loaded');
      setTimeout(() => { main.src = g[idx].src; main.alt = `${p.name} — ${g[idx].label}`; }, REDUCED ? 0 : 160);
    };
    $('#pdpThumbs').addEventListener('click', e => { const t = e.target.closest('[data-thumb]'); if (t) show(+t.dataset.thumb); });
    $('#pdpThumbs').addEventListener('keydown', e => {
      const dir = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
      if (!dir) return;
      e.preventDefault();
      show(idx + dir);
      $(`#pdpThumbs [data-thumb="${idx}"]`).focus();
    });
    zoom.addEventListener('click', e => { const b = e.target.closest('[data-gal]'); if (b) show(idx + +b.dataset.gal); });
    if (matchMedia('(pointer: fine)').matches && !REDUCED) {
      zoom.addEventListener('pointermove', e => {
        if (e.target.closest('[data-gal]')) { main.style.transform = ''; return; }
        const r = zoom.getBoundingClientRect();
        main.style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
        main.style.transform = 'scale(2)';
      });
      zoom.addEventListener('pointerleave', () => { main.style.transform = ''; });
    }
    let x0 = null;
    zoom.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
    zoom.addEventListener('touchend', e => {
      if (x0 == null) return;
      const dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) show(idx + (dx < 0 ? 1 : -1));
      x0 = null;
    });
  }

  /* Detail tabs with sliding indicator */
  function placeInd(ind, t, box, instant) {
    if (!ind || !t || !box) return;
    if (instant) ind.style.transition = 'none';
    ind.style.width = `${t.offsetWidth}px`;
    ind.style.height = `${t.offsetHeight}px`;
    ind.style.transform = `translate(${t.offsetLeft}px, ${t.offsetTop}px)`;
    if (instant) { void ind.offsetWidth; ind.style.transition = ''; }
    else box.scrollTo({ left: t.offsetLeft - box.clientWidth / 2 + t.offsetWidth / 2, behavior: REDUCED ? 'auto' : 'smooth' });
  }
  const pdpRO = 'ResizeObserver' in window ? new ResizeObserver(() => placeInd($('#pdpTabInd'), $(`#pdp-tab-${pdpTab}`), $('#pdpTabs'), true)) : null;
  function initPdpTabs() {
    pdpTab = 'desc';
    const box = $('#pdpTabs');
    pdpRO?.disconnect();
    $$('[role="tab"]', box).forEach(t => pdpRO?.observe(t));
    placeInd($('#pdpTabInd'), $('#pdp-tab-desc'), box, true);
    box.addEventListener('click', e => { const t = e.target.closest('[data-pdp-tab]'); if (t) setPdpTab(t.dataset.pdpTab); });
    box.addEventListener('keydown', e => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
      const tabs = $$('[role="tab"]', box);
      let i = tabs.findIndex(t => t.dataset.pdpTab === pdpTab);
      i = e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
      setPdpTab(tabs[i].dataset.pdpTab, true);
      e.preventDefault();
    });
  }
  function setPdpTab(key, focus) {
    pdpTab = key;
    $$('#pdpTabs [role="tab"]').forEach(t => {
      const on = t.dataset.pdpTab === key;
      t.setAttribute('aria-selected', on);
      t.tabIndex = on ? 0 : -1;
      if (on && focus) t.focus();
    });
    $$('[data-pdp-panel]').forEach(panel => {
      const on = panel.dataset.pdpPanel === key;
      panel.hidden = !on;
      if (on) animate(panel, [{ opacity: 0, transform: 'translateY(12px)' }, { opacity: 1, transform: 'none' }], { duration: 380, easing: 'cubic-bezier(.2,.8,.2,1)' });
    });
    placeInd($('#pdpTabInd'), $(`#pdp-tab-${key}`), $('#pdpTabs'));
    // If the tab bar is stuck to the top, bring the start of the new panel into view
    const y = $('#pdpInfo').getBoundingClientRect().top + scrollY - 72;
    if (scrollY > y) scrollTo({ top: y, behavior: REDUCED ? 'auto' : 'smooth' });
  }

  /* Sticky buy bar — shown once the main Add button scrolls above the viewport */
  let barIO = null, barShown = false;
  function setBar(show) {
    const bar = $('#pdpBar');
    barShown = show;
    bar.classList.toggle('translate-y-[160%]', !show);
    bar.inert = !show;
    document.body.classList.toggle('bar-on', show);
  }
  function renderPdpBar(p) {
    $('#pdpBar').innerHTML = `
      <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8" data-id="${p.id}" data-fly="#pdpMainImg">
        <img src="${img(p.img, 120)}" alt="" class="hidden h-12 w-12 shrink-0 rounded-xl object-cover sm:block" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-bold">${esc(p.name)}</p>
          <p data-pack class="truncate text-xs text-ink-500 [&>svg]:hidden">${packHTML(p)}</p>
        </div>
        <div class="w-[210px] shrink-0 sm:w-72" data-action-area>${actionHTML(p)}</div>
      </div>`;
    icons();
    setBar(false);
    // Visibility is driven by scroll position in onScroll(), so even very fast jumps past the button show the bar
    requestAnimationFrame(onScroll);
  }

  function updateMeta(p) {
    document.title = `${p.name} — Halal Brothers`;
    $('meta[name="description"]').content = p.desc;
    let ld = $('#ldProduct');
    if (!ld) { ld = document.createElement('script'); ld.type = 'application/ld+json'; ld.id = 'ldProduct'; document.head.appendChild(ld); }
    ld.textContent = JSON.stringify({
      '@context': 'https://schema.org', '@type': 'Product',
      name: p.name, description: (DETAILS[p.id] || {}).about || p.desc, image: img(p.img, 1200), category: catName(p.cat),
      brand: { '@type': 'Brand', name: 'Halal Brothers' },
      offers: { '@type': 'Offer', priceCurrency: CONFIG.currency, price: p.price.toFixed(2), availability: p.preorder ? 'https://schema.org/PreOrder' : 'https://schema.org/InStock', url: location.href },
    });
  }
  function pushRecent(id) {
    if (!byId(id)) return;
    const r = store.get('hms-recent', []).filter(x => x !== id);
    r.unshift(id);
    store.set('hms-recent', r.slice(0, 8));
  }

  /* Hash router:
       #/product/<id>  → product page        #/checkout   → checkout
       #/order/<id>    → order confirmation  #/wishlist   → wishlist
       #/account       → account & orders    #/page/<slug> → delivery, refund, halal, privacy, terms
       anything else   → the shop (home), scrolling to #section anchors */
  function route() {
    const isBack = !linkNav;
    linkNav = false;
    const m = location.hash.match(/^#\/([\w-]+)(?:\/([\w-]+))?(?:\/([\w-]+))?/);
    pageArg2 = m ? m[3] || '' : '';
    if (!qv.hidden) closeQuickView(false);
    if (!drawer.hidden) { lastFocus = null; closeCart(); }
    if (m) {
      if (view === 'home') { homeScroll = scrollY; lastHomeHash = prevHash; }
      const isPdp = m[1] === 'product';
      view = isPdp ? 'pdp' : 'page';
      $('#homeView').hidden = true;
      $('#pdp').hidden = !isPdp;
      $('#pageView').hidden = isPdp;
      if (isPdp) {
        page = null;
        renderPDP(m[2]);
        pushRecent(m[2]);
      } else {
        setBar(false);
        barIO?.disconnect();
        $('#ldProduct')?.remove();
        $('meta[name="description"]').content = HOME_DESC;
        renderPage(m[1], m[2]);
      }
      setSpy('');
    } else {
      const wasPdp = view !== 'home';   // returning from any page
      view = 'home';
      pdpId = null;
      page = null;
      $('#pageView').hidden = true;
      $('#pdp').hidden = true;
      $('#homeView').hidden = false;
      setBar(false);
      barIO?.disconnect();
      document.title = HOME_TITLE;
      $('meta[name="description"]').content = HOME_DESC;
      $('#ldProduct')?.remove();
      if (wasPdp) {
        remeasure();
        const target = location.hash.slice(1);
        if (isBack && location.hash === lastHomeHash) scrollTo({ top: homeScroll, behavior: 'instant' });
        else if (target && target !== '/' && document.getElementById(target)) document.getElementById(target).scrollIntoView({ behavior: 'instant' });
        else scrollTo({ top: 0, behavior: 'instant' });
        animate($('#homeView'), [{ opacity: 0 }, { opacity: 1 }], { duration: 300 });
      }
    }
    prevHash = location.hash;
  }
  addEventListener('hashchange', route);

  const SHAKE = [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }];
  const FADE_IN = [{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }];
  const fmtDate = iso => new Date(iso).toLocaleString(CONFIG.locale, { dateStyle: 'medium', timeStyle: 'short' });
  const STORE_ADDR = '3 Kelly Street, Lansdowne, PA 19050';
  const DIRECTIONS = 'https://www.google.com/maps/dir/?api=1&amp;destination=3+Kelly+Street%2C+Lansdowne%2C+PA+19050';

  /* =====================================================
     FAQ (+ FAQPage structured data)
     ===================================================== */
  const FAQS = [
    ['Is all of your meat zabiha halal?', 'Yes — 100%. Every animal and bird is hand-slaughtered by a practising Muslim with the Tasmiyah recited, facing the Qiblah. We never use stunning or mechanical slaughter, and we don’t sell any non-halal products.'],
    ['Can I pick a live bird?', 'Yes — that’s what we’re known for. Visit our live poultry market at 3 Kelly Street, Lansdowne, choose your chicken and we’ll hand-slaughter it zabiha and clean it fresh for you. Prefer delivery? Order online and it’s cut and packed cold the same day.'],
    ['Where does your meat come from?', 'From a small circle of partner family farms we visit personally. Animals are pasture-raised without growth hormones, and every pack carries a batch code that traces it back to the farm and slaughter date.'],
    ['How do you keep the meat cold during delivery?', 'Orders are vacuum-sealed, packed in insulated boxes with ice packs and delivered at 0–4 °C on the day they’re cut. Please refrigerate your order as soon as it arrives.'],
    ['Which areas do you deliver to?', 'Lansdowne, Upper Darby and nearby ZIP codes across the Philadelphia suburbs and Philadelphia. Enter your ZIP at the top of the page to check — and free store pickup is always available at 3 Kelly Street.'],
    ['Can I ask for a custom cut?', 'Absolutely. Choose “Custom” on any product to pick the weight and cut style, add a note at checkout, or send us an on-demand request for anything you don’t see listed.'],
    ['How does Qurbani booking work?', 'Pre-order a goat, sheep or 1/7 cow share. We health-check your animal, slaughter it by hand on Eid day after Salah with your name recited, then deliver it cut your way within 48 hours — or donate it on your behalf.'],
    ['How long does fresh meat keep?', 'Beef, lamb and goat keep 3 days in the fridge at 0–4 °C; poultry and marinated items 2 days. Frozen, most cuts keep 4–6 months. Every product page lists exact storage times.'],
    ['How do I pay?', CONFIG.payOnline
      ? 'Pay securely online at checkout by card, Apple Pay or Google Pay (processed by Stripe — we never see your card number)' + (CONFIG.payOnArrival !== false ? ', or choose to pay by cash or card when your order arrives or when you pick it up.' : '.')
      : 'You pay when your order arrives or when you pick it up — cash or card. No payment is taken online.'],
    ['Do I need an account?', 'No — you can check out as a guest and track your order from the link we send you. Create a free account if you’d like your details filled in for you and all your orders in one place.'],
    ['What if something isn’t right?', 'Contact us within 24 hours of delivery with a photo and we’ll replace the item or refund you. See our refund & freshness policy for details.'],
  ];
  function renderFaq() {
    $('#faqList').innerHTML = FAQS.map(([q, a], i) => `
      <details class="faq group rounded-2xl bg-white ring-1 ring-bone-200 transition-shadow open:shadow-card" ${i === 0 ? 'open' : ''}>
        <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl p-5 text-[15px] font-bold text-ink-900 hover:text-brand-700">
          ${esc(q)}
          <span class="faq-icon grid h-8 w-8 shrink-0 place-items-center rounded-full bg-bone-100 text-ink-700" aria-hidden="true"><i data-lucide="plus" class="h-4 w-4"></i></span>
        </summary>
        <div class="px-5 pb-5 text-[15px] leading-relaxed text-ink-600">${esc(a)}</div>
      </details>`).join('');
    // 'toggle' doesn't bubble, so listen in the capture phase
    $('#faqList').addEventListener('toggle', e => {
      if (e.target.open) animate(e.target.querySelector('div'), FADE_IN, { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' });
    }, true);
    const ld = document.createElement('script');
    ld.type = 'application/ld+json';
    ld.textContent = JSON.stringify({ '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: FAQS.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });
    document.head.appendChild(ld);
  }

  /* =====================================================
     DELIVERY ZIP CHECK (hero + checkout)
     ===================================================== */
  const zipStatus = zip => (/^\d{5}$/.test(zip) ? CONFIG.deliveryZipPrefixes.some(p => zip.startsWith(p)) : null);
  function zipMessage(zip, withSwitch = false) {
    if (PICKUP_ONLY) return `<span class="inline-flex items-center gap-1.5 text-ink-700"><i data-lucide="store" class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i>Home delivery is coming soon — for now, order online and pick up free at 3 Kelly Street.</span>`;
    const s = zipStatus(zip);
    if (s === null) return `<span class="text-ink-500">Enter a 5-digit ZIP code.</span>`;
    return s
      ? `<span class="inline-flex items-center gap-1.5 text-halal-700"><i data-lucide="circle-check" class="h-4 w-4 shrink-0" aria-hidden="true"></i>Great news — we deliver to ${zip}. Order by ${cutoffLabel()} for same-day.</span>`
      : `<span class="inline-flex flex-wrap items-center gap-1.5 text-brand-700"><i data-lucide="circle-alert" class="h-4 w-4 shrink-0" aria-hidden="true"></i>We don’t deliver to ${zip} yet — free pickup at 3 Kelly Street.${withSwitch ? ' <button type="button" data-pick-pickup class="underline underline-offset-2">Switch to pickup</button>' : ''}</span>`;
  }
  $('#zipForm').addEventListener('submit', e => {
    e.preventDefault();
    const zip = $('#zipInput').value.trim();
    $('#zipResult').innerHTML = zipMessage(zip);
    icons();
    animate($('#zipResult'), FADE_IN, { duration: 250 });
    if (zipStatus(zip) !== null) store.set('hms-zip', zip);
    if (zipStatus(zip)) burst($('#zipForm button'), 10);
    else if (zipStatus(zip) === null) animate($('#zipForm'), SHAKE, { duration: 320 });
  });
  $('#zipInput').addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 5); });

  /* =====================================================
     PROMO CODES (cart drawer + checkout)
     ===================================================== */
  let couponUid = 0;
  function couponHTML() {
    const c = state.coupon ? CONFIG.coupons[state.coupon] : null;
    if (c) return `
      <div class="flex items-center justify-between gap-2 rounded-xl bg-halal-50 px-3 py-2 text-sm">
        <span class="flex items-center gap-2 font-semibold text-halal-700"><i data-lucide="ticket" class="h-4 w-4" aria-hidden="true"></i>${esc(state.coupon)} · ${esc(c.label)}</span>
        <button type="button" data-coupon-remove class="grid h-7 w-7 place-items-center rounded-full text-halal-700 hover:bg-halal-100" aria-label="Remove promo code ${esc(state.coupon)}"><i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i></button>
      </div>`;
    const id = `promo-${++couponUid}`;
    return `
      <form data-coupon-form class="flex gap-2" novalidate>
        <label for="${id}" class="sr-only">Promo code</label>
        <input id="${id}" autocomplete="off" placeholder="Promo code" class="field h-10 min-w-0 flex-1 text-sm uppercase placeholder:normal-case" />
        <button class="h-10 shrink-0 rounded-xl bg-ink-900 px-4 text-sm font-bold text-white hover:bg-ink-700">Apply</button>
      </form>`;
  }
  document.addEventListener('submit', async e => {
    const f = e.target.closest('[data-coupon-form]');
    if (!f) return;
    e.preventDefault();
    const input = f.querySelector('input'), btn = f.querySelector('button'), code = input.value.trim().toUpperCase();
    if (!code) return input.focus();
    const reject = msg => {
      input.setAttribute('aria-invalid', 'true');
      animate(input, SHAKE, { duration: 320 });
      return toast(msg, 'circle-alert');
    };
    if (SERVER) {
      btn.disabled = true;
      try {
        const { coupon } = await api('coupon.php', { code });
        CONFIG.coupons[code] = coupon;
        store.set('hms-coupon-info', coupon);
      } catch (err) {
        return reject(err.message);
      } finally {
        btn.disabled = false;
      }
    } else if (!CONFIG.coupons[code]) {
      return reject('That promo code isn’t valid.');
    }
    const c = CONFIG.coupons[code];
    if (c.min && subtotal() < c.min) return reject(`${code} needs an order of at least ${money(c.min)}.`);
    state.coupon = code;
    store.set('hms-coupon', code);
    renderCart();
    toast(`${code} applied — ${CONFIG.coupons[code].label}`, 'ticket', true);
    burst($$('[data-coupon-slot]').find(el => el.getClientRects().length), 12);
  });
  document.addEventListener('click', e => {
    if (e.target.closest('[data-coupon-remove]')) {
      state.coupon = null;
      store.set('hms-coupon', null);
      renderCart();
      toast('Promo code removed', 'ticket');
    }
    const oc = e.target.closest('[data-open-cart]');
    if (oc) openCart(oc);
    if (e.target.closest('[data-print]')) window.print();
  });

  /* =====================================================
     WISHLIST COUNT
     ===================================================== */
  function updateWishCount() {
    const n = state.wish.size;
    $$('[data-wish-count]').forEach(el => { el.textContent = n; el.hidden = !n; });
    $$('[data-wish-link]').forEach(a => a.setAttribute('aria-label', `Wishlist, ${n} saved`));
  }

  /* =====================================================
     PAGES — shared building blocks
     ===================================================== */
  let page = null, pageArg = null, pageArg2 = '', justPlaced = false;
  let customer = SERVER ? SERVER.customer : null;   // signed-in account, if any
  const crumb = title => `<nav aria-label="Breadcrumb" data-noprint><ol class="flex items-center gap-1.5 text-sm text-ink-500"><li><a href="#/" class="hover:text-brand-700">Home</a></li><li aria-hidden="true"><i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></li><li aria-current="page" class="font-semibold text-ink-900">${title}</li></ol></nav>`;
  const pageHead = (title, sub = '', eyebrow = '') => `${crumb(title)}
    <div class="mt-6">
      ${eyebrow ? `<p class="text-xs font-bold uppercase tracking-[.18em] text-brand-600">${eyebrow}</p>` : ''}
      <h1 id="pageTitle" tabindex="-1" class="mt-2 font-display text-4xl font-semibold tracking-tight focus:outline-none sm:text-5xl">${title}</h1>
      ${sub ? `<p class="mt-3 max-w-2xl text-lg text-ink-600">${sub}</p>` : ''}
    </div>`;
  const emptyBlock = (icon, title, body, href, cta) => `
    <div class="mt-10 rounded-3xl border border-dashed border-bone-400 bg-white px-6 py-16 text-center">
      <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-bone-100 text-ink-500"><i data-lucide="${icon}" class="h-7 w-7" aria-hidden="true"></i></span>
      <p class="mt-4 font-display text-2xl font-semibold">${title}</p>
      <p class="mx-auto mt-1 max-w-sm text-sm text-ink-500">${body}</p>
      <a href="${href}" class="mt-6 inline-flex h-11 items-center rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700">${cta}</a>
    </div>`;
  const setPage = (html, title) => { $('#pageView').innerHTML = `<div class="view-in">${html}</div>`; document.title = `${title} — Halal Brothers`; };
  const fieldHTML = (id, label, { type = 'text', value = '', req = false, auto = '', ph = '', mode = '', span = '' } = {}) => `
    <div class="${span}">
      <label for="${id}" class="text-sm font-semibold">${label}${req ? ' <span class="text-brand-600" aria-hidden="true">*</span>' : ''}</label>
      <input id="${id}" name="${id}" type="${type}" value="${esc(value)}" ${req ? 'required' : ''} ${auto ? `autocomplete="${auto}"` : ''} ${mode ? `inputmode="${mode}"` : ''} placeholder="${ph}" aria-describedby="${id}-err" class="field mt-1.5" />
      <p id="${id}-err" class="mt-1 text-xs font-semibold text-brand-600" hidden></p>
    </div>`;

  function renderPage(name, arg, { keepScroll = false } = {}) {
    page = name;
    pageArg = arg;
    const R = { checkout: renderCheckout, order: renderOrder, wishlist: renderWishlist, account: renderAccount, reset: renderReset, page: renderInfo };
    (R[name] || renderMissing)(arg);
    icons();
    if (!keepScroll) {
      scrollTo({ top: 0, behavior: 'instant' });
      $('#pageTitle')?.focus({ preventScroll: true });
    }
  }
  function renderMissing() {
    setPage(`${pageHead('Page not found')}${emptyBlock('search-x', 'Nothing here', 'This page may have moved. Let’s get you back to today’s fresh cuts.', '#shop', 'Browse fresh cuts')}`, 'Not found');
  }

  /* =====================================================
     CHECKOUT
     Orders are saved on this device. TODO: send `order` to your backend /
     order system in placeOrder() (e.g. fetch('/api/orders', { method: 'POST', ... })).
     ===================================================== */
  // Online payment when available (remembering the customer's last choice), otherwise pay on arrival
  const defaultPayment = () => {
    if (!CONFIG.payOnline) return 'on-arrival';
    if (CONFIG.payOnArrival === false) return 'card';
    return store.get('hms-pay', 'card') === 'on-arrival' ? 'on-arrival' : 'card';
  };
  function coSummaryHTML(method = 'delivery', payment = defaultPayment()) {
    const pickup = method === 'pickup', t = totals({ pickup });
    const hasPre = state.cart.some(i => byId(i.id).preorder);
    return `
      <div class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-bone-200">
        <div class="flex items-center justify-between">
          <h2 class="font-display text-xl font-semibold">Order summary</h2>
          <button type="button" data-open-cart class="py-1 text-sm font-bold text-brand-700 hover:underline">Edit cart</button>
        </div>
        <ul class="mt-4 max-h-72 space-y-3 overflow-y-auto pr-1">
          ${state.cart.map(i => { const p = byId(i.id); return `
            <li class="flex items-center gap-3">
              <span class="relative shrink-0"><img src="${img(p.img, 120)}" alt="" class="h-14 w-14 rounded-xl object-cover" /><span class="absolute -right-1.5 -top-1.5 grid h-5 min-w-[20px] place-items-center rounded-full bg-ink-900 px-1 text-[11px] font-extrabold text-white">${i.qty}</span></span>
              <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">${esc(p.name)}</span><span class="text-xs text-ink-500">${esc(lineLabel(i))}</span></span>
              <span class="text-sm font-extrabold tabular-nums">${money(itemPrice(i) * i.qty)}</span>
            </li>`; }).join('')}
        </ul>
        <div class="mt-4 border-t border-bone-200 pt-4" data-coupon-slot>${couponHTML()}</div>
        <dl class="mt-4 space-y-1.5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-600">Subtotal</dt><dd class="font-semibold tabular-nums">${money(t.sub)}</dd></div>
          ${t.discount ? `<div class="flex justify-between text-halal-700"><dt>Promo ${esc(state.coupon)}</dt><dd class="font-semibold tabular-nums">−${money(t.discount)}</dd></div>` : ''}
          <div class="flex justify-between"><dt class="text-ink-600">${pickup ? 'Store pickup' : 'Cold-chain delivery'}</dt><dd class="font-semibold">${t.ship ? money(t.ship) : 'Free'}</dd></div>
          <div class="flex justify-between border-t border-bone-200 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold tabular-nums">${money(t.total)}</dd></div>
        </dl>
        <button type="submit" form="coForm" data-place class="group/place mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70">
          <i data-lucide="lock" class="h-4 w-4" aria-hidden="true"></i> <span data-place-label>${payment === 'card' ? 'Pay securely' : 'Place order'} · ${money(t.total)}</span>
        </button>
        <p class="mt-3 text-center text-xs text-ink-500" data-pay-note>${payment === 'card' ? 'You’ll pay on Stripe’s secure page — card, Apple Pay or Google Pay.' : `No payment is taken online — you pay ${pickup ? 'at pickup' : 'on delivery'}.`}</p>
        ${hasPre ? `<p class="mt-3 rounded-xl bg-saffron-300/25 px-3 py-2 text-center text-xs text-ink-700">Includes a Qurbani pre-order — delivered within 48 hours of Eid.</p>` : ''}
      </div>`;
  }

  function renderCheckout() {
    if (!state.cart.length) {
      return setPage(`${pageHead('Checkout')}${emptyBlock('shopping-bag', 'Your cart is empty', 'Add some fresh cuts before checking out.', '#shop', 'Browse fresh cuts')}`, 'Checkout');
    }
    const saved = store.get('hms-profile', null) || {};
    const pr = customer ? { ...saved, ...Object.fromEntries(Object.entries(customer).filter(([, v]) => v)) } : saved;
    const zip = pr.zip || store.get('hms-zip', '');
    const method = PICKUP_ONLY || saved.method === 'pickup' ? 'pickup' : 'delivery';
    const payment = defaultPayment();
    if (pageArg === 'payment-cancelled') setTimeout(() => toast('Payment cancelled — your cart is still here. Try again or choose another way to pay.', 'circle-alert'), 300);
    const hasPre = state.cart.some(i => byId(i.id).preorder);
    const radio = (name, value, checked, icon, title, sub) => `
      <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-bone-300 bg-bone-50 p-4 transition hover:border-brand-500 has-[:checked]:border-brand-700 has-[:checked]:bg-brand-50/60 has-[:checked]:ring-1 has-[:checked]:ring-brand-700">
        <input type="radio" name="${name}" value="${value}" ${checked ? 'checked' : ''} class="mt-1 h-4 w-4 accent-brand-700" />
        <span class="flex-1"><span class="flex items-center gap-2 font-bold"><i data-lucide="${icon}" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>${title}</span><span class="mt-0.5 block text-sm text-ink-500">${sub}</span></span>
      </label>`;
    const card = (n, title, body) => `
      <fieldset class="rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-7">
        <legend class="sr-only">${title}</legend>
        <h2 class="flex items-center gap-3 font-display text-xl font-semibold"><span class="grid h-8 w-8 place-items-center rounded-full bg-ink-900 font-sans text-sm font-extrabold text-white">${n}</span>${title}</h2>
        ${body}
      </fieldset>`;

    setPage(`
      ${crumb('Checkout')}
      <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <h1 id="pageTitle" tabindex="-1" class="font-display text-4xl font-semibold tracking-tight focus:outline-none sm:text-5xl">Checkout</h1>
        <ol class="flex items-center gap-2 text-xs font-bold text-ink-500" aria-label="Checkout progress">
          <li class="flex items-center gap-1.5 text-halal-700"><i data-lucide="circle-check" class="h-4 w-4" aria-hidden="true"></i>Cart</li>
          <li aria-hidden="true" class="h-px w-5 bg-bone-400"></li>
          <li class="flex items-center gap-1.5 text-ink-900" aria-current="step"><span class="grid h-5 w-5 place-items-center rounded-full bg-ink-900 text-[10px] text-white">2</span>Details</li>
          <li aria-hidden="true" class="h-px w-5 bg-bone-400"></li>
          <li class="flex items-center gap-1.5"><span class="grid h-5 w-5 place-items-center rounded-full bg-bone-300 text-[10px] text-ink-700">3</span>Confirmation</li>
        </ol>
      </div>
      <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_400px]">
        <form id="coForm" class="space-y-5" novalidate>
          ${card(1, 'Contact', `
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              ${fieldHTML('coName', 'Full name', { value: pr.name, req: true, auto: 'name', span: 'sm:col-span-2' })}
              ${fieldHTML('coPhone', 'Phone', { type: 'tel', value: pr.phone, req: true, auto: 'tel', ph: '(215) 555-0123' })}
              ${fieldHTML('coEmail', 'Email (for your receipt)', { type: 'email', value: pr.email, auto: 'email', ph: 'you@example.com' })}
            </div>`)}
          ${card(2, 'Delivery', `
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              ${PICKUP_ONLY ? '' : radio('method', 'delivery', method === 'delivery', 'truck', 'Home delivery', `Cold-chain · free over ${money(CONFIG.freeShipAt)}`)}
              ${radio('method', 'pickup', method === 'pickup', 'store', 'Store pickup', 'Free · 3 Kelly Street, Lansdowne')}
            </div>
            <div data-when="delivery" class="mt-5 grid gap-4 sm:grid-cols-6" ${method === 'pickup' ? 'hidden' : ''}>
              ${fieldHTML('coStreet', 'Street address', { value: pr.street, req: true, auto: 'address-line1', span: 'sm:col-span-4' })}
              ${fieldHTML('coApt', 'Apt / unit', { value: pr.apt, auto: 'address-line2', span: 'sm:col-span-2' })}
              ${fieldHTML('coCity', 'City', { value: pr.city || 'Lansdowne', req: true, auto: 'address-level2', span: 'sm:col-span-3' })}
              ${fieldHTML('coSt', 'State', { value: pr.st || 'PA', auto: 'address-level1', span: 'sm:col-span-1' })}
              ${fieldHTML('coZip', 'ZIP', { value: zip, req: true, auto: 'postal-code', mode: 'numeric', span: 'sm:col-span-2' })}
              <p id="coZipStatus" class="text-[13px] font-semibold sm:col-span-6" aria-live="polite">${zip ? zipMessage(zip, true) : ''}</p>
            </div>
            <div data-when="pickup" class="mt-5 flex gap-3 rounded-2xl bg-bone-100 p-4 text-sm" ${method === 'pickup' ? '' : 'hidden'}>
              <i data-lucide="store" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i>
              <p><strong>Halal Brothers</strong>, ${STORE_ADDR} · <a href="${DIRECTIONS}" target="_blank" rel="noopener" class="font-semibold text-brand-700 underline">Directions</a><br /><span class="text-ink-500">We’ll call you when your order is ready.</span></p>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
              <div>
                <label for="coSlot" class="text-sm font-semibold" data-slot-label>${method === 'pickup' ? 'Pickup time' : 'Delivery slot'}</label>
                <select id="coSlot" class="field mt-1.5">${slotList(method === 'pickup').map(s => `<option>${s}</option>`).join('')}</select>
              </div>
              <div>
                <label for="coNotes" class="text-sm font-semibold">Notes for the butcher or driver</label>
                <input id="coNotes" class="field mt-1.5" placeholder="e.g. gate code, thin slices" />
              </div>
            </div>
            ${hasPre ? `<p class="mt-4 flex gap-2 rounded-xl bg-saffron-300/25 px-4 py-3 text-[13px] text-ink-700"><i data-lucide="moon-star" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-500" aria-hidden="true"></i>Your Qurbani pre-order is delivered within 48 hours of Eid slaughter; everything else arrives in the slot above.</p>` : ''}`)}
          ${card(3, 'Payment', `
            <div class="mt-5 grid gap-3 ${CONFIG.payOnline && CONFIG.payOnArrival !== false ? 'sm:grid-cols-2' : ''}">
              ${CONFIG.payOnline ? radio('payment', 'card', payment === 'card', 'credit-card', 'Pay online now', 'Card, Apple Pay or Google Pay — secured by Stripe') : ''}
              ${CONFIG.payOnArrival !== false ? radio('payment', 'on-arrival', payment === 'on-arrival', 'wallet', `<span data-pay-title>${method === 'pickup' ? 'Pay at pickup' : 'Pay on delivery'}</span>`, 'Cash or card when you receive your order') : ''}
            </div>
            <p class="mt-3 flex items-center gap-2 text-xs text-ink-500"><i data-lucide="shield-check" class="h-3.5 w-3.5 text-halal-600" aria-hidden="true"></i>Fresh cuts are weighed by hand — we confirm the final weight and price before delivery.</p>`)}
          <label class="flex items-center gap-2.5 px-1 text-sm font-semibold"><input type="checkbox" id="coSave" checked class="h-4 w-4 accent-brand-700" /> ${customer ? 'Save these details to my account' : 'Save my details on this device for next time'}</label>
          ${SERVER && !customer ? `<p class="px-1 text-sm text-ink-500">Have an account? <a href="#/account" class="font-semibold text-brand-700 underline">Sign in</a> to track all your orders — or check out as a guest.</p>` : ''}
        </form>
        <aside class="lg:sticky lg:top-24" aria-label="Order summary"><div id="coSummary">${coSummaryHTML(method, payment)}</div></aside>
      </div>`, 'Checkout');
    if (method === 'delivery' && $('#coSlot') && $('#slotSelect').value) $('#coSlot').value = $('#slotSelect').value;
  }

  function onMethodChange() {
    const f = $('#coForm');
    if (!f) return;
    const m = f.elements.method.value, pay = f.elements.payment?.value || 'on-arrival';
    $$('[data-when]', f).forEach(el => {
      const on = el.dataset.when === m;
      if (on && el.hidden) animate(el, FADE_IN, { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' });
      el.hidden = !on;
    });
    $('[data-slot-label]').textContent = m === 'pickup' ? 'Pickup time' : 'Delivery slot';
    $('#coSlot').innerHTML = slotList(m === 'pickup').map(s => `<option>${s}</option>`).join('');
    const payTitle = $('[data-pay-title]');
    if (payTitle) payTitle.textContent = m === 'pickup' ? 'Pay at pickup' : 'Pay on delivery';
    $('#coSummary').innerHTML = coSummaryHTML(m, pay);
    icons();
  }

  function placeOrder(e) {
    e.preventDefault();
    const f = e.target, v = id => ($(`#${id}`)?.value || '').trim();
    const method = f.elements.method.value, bad = [];
    const check = (id, ok, msg) => {
      const input = $(`#${id}`), err = $(`#${id}-err`);
      if (!input) return;
      input.setAttribute('aria-invalid', String(!ok));
      err.hidden = ok;
      err.textContent = ok ? '' : msg;
      if (!ok) bad.push(input);
    };
    check('coName', v('coName').length > 1, 'Please enter your name.');
    check('coPhone', v('coPhone').replace(/\D/g, '').length >= 10, 'Please enter a valid phone number.');
    check('coEmail', !v('coEmail') || /^\S+@\S+\.\S+$/.test(v('coEmail')), 'Please enter a valid email address.');
    if (method === 'delivery') {
      check('coStreet', !!v('coStreet'), 'Please enter your street address.');
      check('coCity', !!v('coCity'), 'Please enter your city.');
      const z = zipStatus(v('coZip'));
      check('coZip', z === true, z === false ? 'We don’t deliver here yet — choose store pickup.' : 'Enter a 5-digit ZIP code.');
    }
    if (bad.length) {
      bad.forEach(el => animate(el, SHAKE, { duration: 340 }));
      bad[0].focus();
      return toast(`Please check ${bad.length} field${bad.length > 1 ? 's' : ''}.`, 'circle-alert');
    }

    if (SERVER) return submitOrder(f, method, v);

    // Preview mode (index.html opened without the server): save on this device only
    const t = totals({ pickup: method === 'pickup' });
    const order = {
      id: `HB-${String(Date.now()).slice(-6)}`,
      date: new Date().toISOString(),
      items: state.cart.map(i => ({ id: i.id, name: byId(i.id).name, label: lineLabel(i), qty: i.qty, price: round2(itemPrice(i) * i.qty) })),
      sub: t.sub, discount: t.discount, ship: t.ship, total: t.total, coupon: state.coupon,
      contact: { name: v('coName'), phone: v('coPhone'), email: v('coEmail') },
      method,
      address: method === 'delivery' ? { street: v('coStreet'), apt: v('coApt'), city: v('coCity'), st: v('coSt'), zip: v('coZip') } : null,
      slot: v('coSlot'), notes: v('coNotes'),
      payment: method === 'pickup' ? 'Pay at pickup' : 'Pay on delivery',
    };
    // TODO: send `order` to your backend / order system here.
    store.set('hms-orders', [order, ...store.get('hms-orders', [])].slice(0, 20));
    if ($('#coSave').checked) {
      store.set('hms-profile', { ...order.contact, ...(order.address || {}), method });
    }

    const ids = [...new Set(state.cart.map(i => i.id))];
    page = 'placing';                 // stop the checkout re-rendering while the cart clears
    state.cart = [];
    state.coupon = null;
    store.set('hms-coupon', null);
    saveCart();
    ids.forEach(id => refreshAction(id));
    justPlaced = true;
    navigate(`#/order/${order.id}`);
  }

  function clearCartAfterOrder() {
    const ids = [...new Set(state.cart.map(i => i.id))];
    page = 'placing';                 // stop the checkout re-rendering while the cart clears
    state.cart = [];
    state.coupon = null;
    store.set('hms-coupon', null);
    saveCart();
    ids.forEach(id => byId(id) && refreshAction(id));
  }
  // Orders placed on this device (number + private token), so guests can find them again
  const rememberOrder = o => store.set('hms-order-refs', [
    { number: o.number, token: o.token, date: o.date, total: o.total, method: o.method, items: o.items.reduce((n, i) => n + i.qty, 0) },
    ...store.get('hms-order-refs', []).filter(r => r.number !== o.number),
  ].slice(0, 30));

  async function submitOrder(f, method, v) {
    const payment = f.elements.payment?.value === 'card' ? 'card' : 'on_arrival';
    const t = totals({ pickup: method === 'pickup' });
    const btn = $('[data-place]'), label = $('[data-place-label]');
    if (btn.disabled) return;
    btn.disabled = true;
    const oldLabel = label.textContent;
    label.textContent = payment === 'card' ? 'Opening secure payment…' : 'Placing your order…';
    const body = {
      cart: state.cart.map(({ id, weightLb, cut, qty }) => ({ id, weightLb, cut, qty })),
      unit: state.unit, method, payment, coupon: state.coupon || '', expectedTotal: t.total,
      contact: { name: v('coName'), phone: v('coPhone'), email: v('coEmail') },
      address: method === 'delivery' ? { street: v('coStreet'), apt: v('coApt'), city: v('coCity'), st: v('coSt'), zip: v('coZip') } : null,
      slot: v('coSlot'), notes: v('coNotes'), saveProfile: $('#coSave').checked,
    };
    try {
      const res = await api('order.php', body);
      if ($('#coSave').checked) store.set('hms-profile', { ...body.contact, ...(body.address || {}), method });
      rememberOrder(res.order);
      if (res.redirect) {
        store.set('hms-pending-order', res.order.number);   // cart is cleared once payment succeeds
        location.href = res.redirect;
        return;
      }
      clearCartAfterOrder();
      justPlaced = true;
      navigate(`#/order/${res.order.number}/${res.order.token}`);
    } catch (err) {
      btn.disabled = false;
      label.textContent = oldLabel;
      const fields = err.data?.fields || {};
      Object.entries(fields).forEach(([id, msg]) => {
        const input = $(`#${id}`), er = $(`#${id}-err`);
        if (!input || !er) return;
        input.setAttribute('aria-invalid', 'true');
        er.hidden = false;
        er.textContent = msg;
        animate(input, SHAKE, { duration: 340 });
      });
      Object.keys(fields).length && $(`#${Object.keys(fields)[0]}`)?.focus();
      toast(err.status === 0 || !err.status ? 'Couldn’t reach the store. Check your connection and try again.' : err.message, 'circle-alert');
    }
  }

  $('#pageView').addEventListener('change', e => {
    if (e.target.name === 'method' || e.target.name === 'payment') onMethodChange();
    if (e.target.name === 'payment') store.set('hms-pay', e.target.value);
  });
  $('#pageView').addEventListener('submit', e => { if (e.target.id === 'coForm') placeOrder(e); });
  $('#pageView').addEventListener('input', e => {
    const t = e.target;
    if (t.id === 'coZip') {
      t.value = t.value.replace(/\D/g, '').slice(0, 5);
      $('#coZipStatus').innerHTML = t.value.length === 5 ? zipMessage(t.value, true) : '';
      icons();
    }
    if (t.getAttribute('aria-invalid') === 'true') {
      t.removeAttribute('aria-invalid');
      const er = $(`#${t.id}-err`);
      if (er) er.hidden = true;
    }
  });
  $('#pageView').addEventListener('click', e => {
    if (e.target.closest('[data-pick-pickup]')) {
      $('#coForm input[name="method"][value="pickup"]').checked = true;
      onMethodChange();
    }
    if (e.target.closest('[data-forget]')) {
      const saved = store.get('hms-profile', null);
      store.set('hms-profile', null);
      renderPage('account', null, { keepScroll: true });
      toast('Saved details removed from this device', 'trash-2', false, { label: 'Undo', onClick: () => { store.set('hms-profile', saved); if (page === 'account') renderPage('account', null, { keepScroll: true }); } });
    }
  });

  /* =====================================================
     ORDER CONFIRMATION
     ===================================================== */
  const orderNotFound = () => setPage(`${pageHead('Order not found')}${emptyBlock('receipt', 'We can’t find that order', 'Check the link in your confirmation email or text, or sign in to see your orders.', '#/account', 'View my orders')}`, 'Order not found');
  let orderPoll = null;
  function renderOrder(id) {
    clearTimeout(orderPoll);
    if (!SERVER) {
      const o = store.get('hms-orders', []).find(x => x.id === id);
      return o ? showOrder(o) : orderNotFound();
    }
    const token = pageArg2, ref = store.get('hms-order-refs', []).find(r => r.number === id);
    if (!$(`#pageView [data-order="${CSS.escape(id)}"]`)) {   // refreshes keep the current view instead of flashing a loader
      setPage(`<div class="grid min-h-[40vh] place-items-center text-ink-500" role="status"><span class="flex items-center gap-2"><i data-lucide="loader-circle" class="h-5 w-5 animate-spin" aria-hidden="true"></i>Loading your order…</span></div>`, `Order ${id}`);
      icons();
    }
    api(`order.php?n=${encodeURIComponent(id)}&t=${encodeURIComponent(token || ref?.token || '')}`).then(({ order: o }) => {
      if (page !== 'order' || pageArg !== id) return;
      // Returning from Stripe: empty the cart once the payment has gone through
      if (store.get('hms-pending-order', '') === o.number && o.paymentStatus === 'paid') {
        store.set('hms-pending-order', '');
        clearCartAfterOrder();
        page = 'order';
        justPlaced = true;
      }
      rememberOrder(o);
      showOrder({ ...o, id: o.number });
      icons();
      // Still waiting for Stripe to confirm? Check again shortly.
      if (o.status === 'pending_payment') orderPoll = setTimeout(() => page === 'order' && pageArg === id && renderPage('order', id, { keepScroll: true }), 4000);
    }).catch(err => {
      if (page !== 'order') return;
      if (err.status === 404) return orderNotFound(), icons();
      setPage(`${pageHead('Couldn’t load your order')}${emptyBlock('wifi-off', 'Please try again', esc(err.message), '#/account', 'My orders')}`, 'Order');
      icons();
    });
  }
  function showOrder(o) {
    const pickup = o.method === 'pickup', first = o.contact.name.split(/\s+/)[0];
    const flow = ['new', 'preparing', pickup ? 'ready' : 'out_for_delivery', 'completed'];
    const reached = !o.status ? 0 : o.status === 'confirmed' ? 0 : Math.max(0, flow.indexOf(o.status));
    const steps = [
      ['clipboard-check', 'Order placed', fmtDate(o.date)],
      ['scissors', 'Being cut', 'Morning of delivery'],
      ['truck', pickup ? 'Ready for pickup' : 'Out for delivery', o.slot],
      ['package-check', pickup ? 'Collected' : 'Delivered', 'Enjoy!'],
    ];
    const cancelled = o.status === 'cancelled', awaiting = o.status === 'pending_payment';
    setPage(`
      <div class="mx-auto max-w-4xl" data-order="${esc(o.id)}">
        <div class="text-center">
          ${cancelled || awaiting
            ? `<span class="mx-auto grid h-20 w-20 place-items-center rounded-full ${cancelled ? 'bg-bone-200 text-ink-500' : 'bg-saffron-300/40 text-saffron-600'}"><i data-lucide="${cancelled ? 'circle-x' : 'loader-circle'}" class="h-9 w-9 ${awaiting ? 'animate-spin' : ''}" aria-hidden="true"></i></span>
               <p class="mt-5 text-xs font-bold uppercase tracking-[.18em] text-ink-500">Order ${esc(o.id)}</p>
               <h1 id="pageTitle" tabindex="-1" class="mt-2 font-display text-4xl font-semibold tracking-tight focus:outline-none sm:text-5xl">${cancelled ? 'This order was cancelled' : 'Confirming your payment…'}</h1>
               <p class="mx-auto mt-3 max-w-xl text-lg text-ink-600">${cancelled ? `Questions? Call <a href="tel:+12673073777" class="font-semibold text-brand-700 underline">(267) 307-3777</a>.` : 'This usually takes a few seconds. You can keep this page open.'}</p>`
            : `<svg class="check-draw mx-auto h-20 w-20" viewBox="0 0 56 56" aria-hidden="true"><circle cx="28" cy="28" r="26" fill="none" stroke="#2F7A45" stroke-width="3"/><path d="M16 29l8 8 16-17" fill="none" stroke="#2F7A45" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
               <p class="mt-5 text-xs font-bold uppercase tracking-[.18em] text-halal-600">Order ${esc(o.id)} ${o.statusLabel && reached > 0 ? '· ' + esc(o.statusLabel) : 'received'}</p>
               <h1 id="pageTitle" tabindex="-1" class="mt-2 font-display text-4xl font-semibold tracking-tight focus:outline-none sm:text-5xl">JazakAllah khair, ${esc(first)}!</h1>
               <p class="mx-auto mt-3 max-w-xl text-lg text-ink-600">We’ll call ${esc(o.contact.phone)} to confirm your order. ${pickup ? 'It will be waiting for you at 3 Kelly Street.' : 'It will arrive cold-packed at 0–4 °C.'}</p>`}
        </div>

        ${cancelled || awaiting ? '' : `<ol class="mt-10 grid grid-cols-4 gap-2" aria-label="Order progress">
          ${steps.map(([ic, t, s], i) => `
            <li class="relative text-center" ${i === reached ? 'aria-current="step"' : ''}>
              ${i ? `<span class="absolute right-1/2 top-5 h-0.5 w-full -translate-y-1/2 ${i <= reached ? 'bg-halal-600' : 'bg-bone-300'}" aria-hidden="true"></span>` : ''}
              <span class="step-dot relative mx-auto grid h-10 w-10 place-items-center rounded-full ${i <= reached ? 'bg-halal-600 text-white' : 'bg-white text-ink-500 ring-1 ring-bone-300'} ${i === reached ? 'shadow-lift' : ''}"><i data-lucide="${ic}" class="h-4 w-4" aria-hidden="true"></i></span>
              <p class="mt-2 text-xs font-bold sm:text-sm ${i <= reached ? 'text-ink-900' : 'text-ink-500'}">${t}</p>
              <p class="hidden text-xs text-ink-500 sm:block">${esc(s)}</p>
            </li>`).join('')}
        </ol>`}

        <div class="mt-10 grid gap-5 md:grid-cols-[1.4fr_1fr]">
          <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
            <h2 class="font-display text-xl font-semibold">Items</h2>
            <ul class="mt-2 divide-y divide-bone-200">
              ${o.items.map(i => { const p = byId(i.id); return `<li class="flex items-center gap-3 py-3">${p ? `<img src="${img(p.img, 120)}" alt="" class="h-12 w-12 rounded-xl object-cover" />` : ''}<div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">${esc(i.name)}</p><p class="text-xs text-ink-500">${esc(i.label)} · × ${i.qty}</p></div><span class="text-sm font-extrabold tabular-nums">${money(i.price)}</span></li>`; }).join('')}
            </ul>
            <dl class="mt-2 space-y-1.5 border-t border-bone-200 pt-4 text-sm">
              <div class="flex justify-between"><dt class="text-ink-600">Subtotal</dt><dd class="font-semibold">${money(o.sub)}</dd></div>
              ${o.discount ? `<div class="flex justify-between text-halal-700"><dt>Promo ${esc(o.coupon)}</dt><dd class="font-semibold">−${money(o.discount)}</dd></div>` : ''}
              <div class="flex justify-between"><dt class="text-ink-600">${pickup ? 'Store pickup' : 'Cold-chain delivery'}</dt><dd class="font-semibold">${o.ship ? money(o.ship) : 'Free'}</dd></div>
              <div class="flex justify-between border-t border-bone-200 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold">${money(o.total)}</dd></div>
            </dl>
          </div>
          <div class="space-y-5">
            <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
              <h2 class="flex items-center gap-2 font-bold"><i data-lucide="${pickup ? 'store' : 'map-pin'}" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>${pickup ? 'Store pickup' : 'Delivery address'}</h2>
              <p class="mt-2 text-sm leading-relaxed text-ink-600">${pickup ? STORE_ADDR : `${esc(o.address.street)}${o.address.apt ? ', ' + esc(o.address.apt) : ''}<br />${esc(o.address.city)}, ${esc(o.address.st)} ${esc(o.address.zip)}`}</p>
              <p class="mt-3 flex items-center gap-2 text-sm font-semibold"><i data-lucide="calendar-clock" class="h-4 w-4 text-ink-500" aria-hidden="true"></i>${esc(o.slot)}</p>
              ${o.notes ? `<p class="mt-2 text-sm italic text-ink-500">“${esc(o.notes)}”</p>` : ''}
            </div>
            <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
              <h2 class="flex items-center gap-2 font-bold"><i data-lucide="wallet" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Payment</h2>
              <p class="mt-2 text-sm text-ink-600">${esc(o.payment)}${o.paymentMethod === 'card' ? '' : ' — cash or card.'}</p>
              ${o.refunded ? `<p class="mt-1 text-sm font-semibold text-halal-700">Refunded ${money(o.refunded)}</p>` : ''}
            </div>
          </div>
        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-3" data-noprint>
          <a href="#shop" class="inline-flex h-11 items-center gap-2 rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700"><i data-lucide="shopping-bag" class="h-4 w-4" aria-hidden="true"></i> Continue shopping</a>
          <button type="button" data-print class="inline-flex h-11 items-center gap-2 rounded-full border border-ink-900/15 bg-white px-6 text-sm font-bold hover:border-brand-500 hover:text-brand-700"><i data-lucide="printer" class="h-4 w-4" aria-hidden="true"></i> Print receipt</button>
          <a href="#/account" class="inline-flex h-11 items-center gap-2 rounded-full border border-ink-900/15 bg-white px-6 text-sm font-bold hover:border-brand-500 hover:text-brand-700"><i data-lucide="receipt" class="h-4 w-4" aria-hidden="true"></i> My orders</a>
        </div>
      </div>`, `Order ${o.id}`);
    if (justPlaced) {
      justPlaced = false;
      setTimeout(() => confetti($('#pageView .check-draw')), 450);
    }
  }

  /* =====================================================
     WISHLIST
     ===================================================== */
  function renderWishlist() {
    const items = [...state.wish].map(byId).filter(Boolean);
    setPage(`${pageHead('Your wishlist', `${items.length} saved ${items.length === 1 ? 'item' : 'items'} — ready when you are.`, 'Saved for later')}
      ${items.length
        ? `<div id="wishGrid" class="mt-10 grid grid-cols-1 gap-5 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">${items.map(x => cardHTML(x, 'wish')).join('')}</div>`
        : emptyBlock('heart', 'Nothing saved yet', 'Tap the heart on any cut to save it here for later.', '#shop', 'Browse fresh cuts')}`, 'Wishlist');
    $$('#wishGrid > article').forEach((c, i) => reveal(c, (i % 4) * 80));
  }

  /* =====================================================
     ACCOUNT — sign in / register, profile and order history (server),
     or details saved on this device (preview mode)
     ===================================================== */
  const helpCard = `
        <div class="rounded-3xl bg-ink-900 p-6 text-bone-100">
          <p class="flex items-center gap-2 font-bold text-white"><i data-lucide="life-buoy" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Need help?</p>
          <ul class="mt-3 space-y-2 text-sm">
            <li><a href="tel:+12673073777" class="hover:text-white hover:underline">Call (267) 307-3777</a></li>
            <li><a href="#faq" class="hover:text-white hover:underline">Read the FAQ</a></li>
            <li><a href="#/page/delivery" class="hover:text-white hover:underline">Delivery information</a></li>
            <li><a href="#/page/refund" class="hover:text-white hover:underline">Refunds &amp; freshness</a></li>
          </ul>
        </div>`;
  const STATUS_TONE = { pending_payment: 'bg-bone-200 text-ink-600', new: 'bg-halal-50 text-halal-700', completed: 'bg-halal-50 text-halal-700', cancelled: 'bg-bone-200 text-ink-500' };
  const orderRowHTML = o => `
    <li><a href="#/order/${esc(o.number)}/${esc(o.token)}" class="group flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-bone-200 transition hover:shadow-card sm:p-5">
      <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="receipt" class="h-5 w-5" aria-hidden="true"></i></span>
      <span class="min-w-0 flex-1"><span class="block font-bold">Order ${esc(o.number)}</span><span class="text-sm text-ink-500">${fmtDate(o.date)} · ${o.items} items · ${o.method === 'pickup' ? 'Store pickup' : 'Delivery'}</span></span>
      ${o.statusLabel ? `<span class="rounded-full px-2.5 py-1 text-xs font-bold ${STATUS_TONE[o.status] || 'bg-saffron-300/40 text-ink-900'}">${esc(o.statusLabel)}</span>` : ''}
      <span class="font-extrabold tabular-nums">${money(o.total)}</span>
      <i data-lucide="chevron-right" class="h-4 w-4 text-ink-500 transition-transform group-hover:translate-x-1" aria-hidden="true"></i>
    </a></li>`;
  const authField = (form, name, label, type = 'text', auto = '', value = '') => `
    <div>
      <label for="${form}-${name}" class="text-sm font-semibold">${label}</label>
      <input id="${form}-${name}" name="${name}" type="${type}" value="${esc(value)}" ${auto ? `autocomplete="${auto}"` : ''} class="field mt-1.5" />
      <p data-err="${name}" class="mt-1 text-xs font-semibold text-brand-600" hidden></p>
    </div>`;

  function renderAccount() {
    if (!SERVER) return renderLocalAccount();
    if (!customer) return renderSignIn();
    setPage(`${pageHead('My account', `Signed in as ${esc(customer.email)}`, `Assalamu alaikum, ${esc(customer.name.split(/\s+/)[0])}`)}
      <div class="mt-10 grid gap-5 lg:grid-cols-[1.3fr_1fr]">
        <form data-account-form="update" class="rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-7" novalidate>
          <p class="flex items-center gap-2 font-bold"><i data-lucide="user-round" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Your details</p>
          <p class="mt-1 text-sm text-ink-500">Used to fill in checkout for you.</p>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            ${authField('up', 'name', 'Full name', 'text', 'name', customer.name)}
            ${authField('up', 'phone', 'Phone', 'tel', 'tel', customer.phone)}
            <div class="sm:col-span-2">${authField('up', 'street', 'Street address', 'text', 'address-line1', customer.street)}</div>
            ${authField('up', 'apt', 'Apt / unit', 'text', 'address-line2', customer.apt)}
            ${authField('up', 'city', 'City', 'text', 'address-level2', customer.city)}
            ${authField('up', 'st', 'State', 'text', 'address-level1', customer.st)}
            ${authField('up', 'zip', 'ZIP', 'text', 'postal-code', customer.zip)}
          </div>
          <details class="mt-5 rounded-2xl bg-bone-50 p-4 ring-1 ring-bone-200">
            <summary class="cursor-pointer text-sm font-bold">Change password</summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              ${authField('up', 'currentPassword', 'Current password', 'password', 'current-password')}
              ${authField('up', 'newPassword', 'New password (8+ characters)', 'password', 'new-password')}
            </div>
          </details>
          <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <button class="h-11 rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">Save details</button>
            <button type="button" data-signout class="inline-flex items-center gap-1.5 py-1 text-sm font-semibold text-brand-700 hover:underline"><i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>Sign out</button>
          </div>
        </form>
        <div class="space-y-5">
          <a href="#/wishlist" class="group block rounded-3xl bg-white p-6 ring-1 ring-bone-200 transition hover:-translate-y-0.5 hover:shadow-card">
            <p class="flex items-center gap-2 font-bold"><i data-lucide="heart" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Wishlist</p>
            <p class="mt-3 font-display text-4xl font-semibold">${state.wish.size}</p>
            <p class="text-sm text-ink-500">saved ${state.wish.size === 1 ? 'item' : 'items'} <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span></p>
          </a>
          ${helpCard}
        </div>
      </div>
      <h2 class="mt-14 font-display text-2xl font-semibold">Order history</h2>
      <div id="accountOrders" class="mt-5 text-sm text-ink-500" role="status">Loading your orders…</div>`, 'My account');
    api('account.php').then(r => {
      if (page !== 'account') return;
      customer = r.customer;
      if (!customer) return renderPage('account', null, { keepScroll: true });
      $('#accountOrders').outerHTML = r.orders.length
        ? `<ul class="mt-5 space-y-3">${r.orders.map(orderRowHTML).join('')}</ul>`
        : emptyBlock('receipt', 'No orders yet', 'Orders you place while signed in will appear here.', '#shop', 'Start shopping');
      icons();
    }).catch(err => { if ($('#accountOrders')) $('#accountOrders').textContent = err.message; });
  }

  function renderSignIn(mode = 'signin') {
    const refs = store.get('hms-order-refs', []);
    const forms = {
      signin: `
        <form data-account-form="login" class="space-y-4" novalidate>
          ${authField('in', 'email', 'Email', 'email', 'email')}
          ${authField('in', 'password', 'Password', 'password', 'current-password')}
          <button class="h-11 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">Sign in</button>
          <div class="flex flex-wrap justify-between gap-2 text-sm"><button type="button" data-auth-mode="forgot" class="font-semibold text-brand-700 hover:underline">Forgot password?</button><button type="button" data-auth-mode="register" class="font-semibold text-brand-700 hover:underline">Create an account</button></div>
        </form>`,
      register: `
        <form data-account-form="register" class="space-y-4" novalidate>
          ${authField('reg', 'name', 'Full name', 'text', 'name')}
          ${authField('reg', 'email', 'Email', 'email', 'email')}
          ${authField('reg', 'phone', 'Phone (optional)', 'tel', 'tel')}
          ${authField('reg', 'password', 'Password (8+ characters)', 'password', 'new-password')}
          <button class="h-11 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">Create account</button>
          <p class="text-sm">Already have one? <button type="button" data-auth-mode="signin" class="font-semibold text-brand-700 hover:underline">Sign in</button></p>
        </form>`,
      forgot: `
        <form data-account-form="forgot" class="space-y-4" novalidate>
          <p class="text-sm text-ink-600">Enter your email and we’ll send you a link to choose a new password.</p>
          ${authField('fg', 'email', 'Email', 'email', 'email')}
          <button class="h-11 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">Send reset link</button>
          <p class="text-sm"><button type="button" data-auth-mode="signin" class="font-semibold text-brand-700 hover:underline">Back to sign in</button></p>
        </form>`,
    };
    const titles = { signin: 'Sign in', register: 'Create your account', forgot: 'Reset your password' };
    setPage(`${pageHead('My account', 'Sign in to track your orders and check out faster. An account is optional — you can always check out as a guest.', 'Assalamu alaikum')}
      <div class="mt-10 grid gap-5 lg:grid-cols-[1.1fr_1fr]">
        <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
          <h2 class="font-display text-2xl font-semibold">${titles[mode]}</h2>
          <div class="mt-5">${forms[mode]}</div>
        </div>
        <div class="space-y-5">
          <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
            <p class="flex items-center gap-2 font-bold"><i data-lucide="receipt" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Orders on this device</p>
            ${refs.length ? `<ul class="mt-4 space-y-3">${refs.slice(0, 5).map(orderRowHTML).join('')}</ul>` : '<p class="mt-3 text-sm text-ink-500">Guest orders you place on this device will show here, with a link to track each one.</p>'}
          </div>
          ${helpCard}
        </div>
      </div>`, titles[mode]);
  }

  function renderReset(token) {
    setPage(`${pageHead('Choose a new password')}
      <div class="mt-10 max-w-md rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
        <form data-account-form="reset" data-token="${esc(token || '')}" class="space-y-4" novalidate>
          ${authField('rs', 'password', 'New password (8+ characters)', 'password', 'new-password')}
          <button class="h-11 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">Save password &amp; sign in</button>
        </form>
      </div>`, 'Reset password');
  }

  $('#pageView').addEventListener('click', async e => {
    const m = e.target.closest('[data-auth-mode]');
    if (m) { renderSignIn(m.dataset.authMode); icons(); $('#pageView form input')?.focus(); }
    if (e.target.closest('[data-signout]')) {
      try { await api('account.php', { action: 'logout' }); } catch { /* signed out locally anyway */ }
      customer = null;
      toast('You’re signed out', 'log-out');
      renderPage('account', null, { keepScroll: true });
    }
  });
  $('#pageView').addEventListener('submit', async e => {
    const f = e.target.closest('[data-account-form]');
    if (!f) return;
    e.preventDefault();
    const action = f.dataset.accountForm, btn = f.querySelector('button:not([type="button"])');
    const body = { action, ...Object.fromEntries(new FormData(f)) };
    if (action === 'reset') body.token = f.dataset.token;
    $$('[data-err]', f).forEach(p => { p.hidden = true; p.previousElementSibling?.removeAttribute('aria-invalid'); });
    btn.disabled = true;
    try {
      const r = await api('account.php', body);
      if (action === 'forgot') {
        f.innerHTML = `<p class="flex gap-2 rounded-2xl bg-halal-50 p-4 text-sm text-halal-700"><i data-lucide="mail-check" class="h-4 w-4 shrink-0" aria-hidden="true"></i>If an account exists for that email, a reset link is on its way. Check your inbox (and spam folder).</p>`;
        return icons();
      }
      customer = r.customer;
      toast({ login: 'Welcome back!', register: 'Your account is ready', update: 'Details saved', reset: 'Password changed — you’re signed in' }[action], 'circle-check', true);
      if (action === 'reset') return navigate('#/account');
      renderPage('account', null, { keepScroll: action === 'update' });
    } catch (err) {
      const fields = err.data?.fields || {};
      Object.entries(fields).forEach(([name, msg]) => {
        const p = f.querySelector(`[data-err="${name}"]`);
        if (!p) return;
        p.hidden = false;
        p.textContent = msg;
        p.previousElementSibling?.setAttribute('aria-invalid', 'true');
      });
      animate(f.querySelector('[aria-invalid="true"]') || btn, SHAKE, { duration: 320 });
      toast(err.message, 'circle-alert');
    } finally {
      if (btn.isConnected) btn.disabled = false;
    }
  });

  function renderLocalAccount() {
    const pr = store.get('hms-profile', null), orders = store.get('hms-orders', []);
    setPage(`${pageHead('My account', 'Your saved details and order history on this device.', 'Assalamu alaikum')}
      <div class="mt-10 grid gap-5 md:grid-cols-3">
        <div class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
          <p class="flex items-center gap-2 font-bold"><i data-lucide="user-round" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Saved details</p>
          ${pr ? `
            <address class="mt-3 text-sm not-italic leading-relaxed text-ink-600"><strong class="text-ink-900">${esc(pr.name)}</strong><br />${esc(pr.phone)}${pr.email ? `<br />${esc(pr.email)}` : ''}${pr.street ? `<br />${esc(pr.street)}${pr.apt ? ', ' + esc(pr.apt) : ''}<br />${esc(pr.city)}, ${esc(pr.st)} ${esc(pr.zip)}` : '<br />Prefers store pickup'}</address>
            <button type="button" data-forget class="mt-3 inline-flex items-center gap-1.5 py-1 text-sm font-semibold text-brand-700 hover:underline"><i data-lucide="trash-2" class="h-3.5 w-3.5" aria-hidden="true"></i>Forget my details</button>`
            : `<p class="mt-3 text-sm text-ink-500">Nothing saved yet — keep “Save my details” ticked at checkout for faster ordering next time.</p>`}
        </div>
        <a href="#/wishlist" class="group rounded-3xl bg-white p-6 ring-1 ring-bone-200 transition hover:-translate-y-0.5 hover:shadow-card">
          <p class="flex items-center gap-2 font-bold"><i data-lucide="heart" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Wishlist</p>
          <p class="mt-3 font-display text-4xl font-semibold">${state.wish.size}</p>
          <p class="text-sm text-ink-500">saved ${state.wish.size === 1 ? 'item' : 'items'} <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span></p>
        </a>
        <div class="rounded-3xl bg-ink-900 p-6 text-bone-100">
          <p class="flex items-center gap-2 font-bold text-white"><i data-lucide="life-buoy" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Need help?</p>
          <ul class="mt-3 space-y-2 text-sm">
            <li><a href="tel:+12673073777" class="hover:text-white hover:underline">Call (267) 307-3777</a></li>
            <li><a href="#faq" class="hover:text-white hover:underline">Read the FAQ</a></li>
            <li><a href="#/page/delivery" class="hover:text-white hover:underline">Delivery information</a></li>
            <li><a href="#/page/refund" class="hover:text-white hover:underline">Refunds &amp; freshness</a></li>
          </ul>
        </div>
      </div>
      <h2 class="mt-14 font-display text-2xl font-semibold">Order history</h2>
      ${orders.length ? `
        <ul class="mt-5 space-y-3">
          ${orders.map(o => `
            <li><a href="#/order/${esc(o.id)}" class="group flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-bone-200 transition hover:shadow-card sm:p-5">
              <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-50 text-brand-700"><i data-lucide="receipt" class="h-5 w-5" aria-hidden="true"></i></span>
              <span class="min-w-0 flex-1"><span class="block font-bold">Order ${esc(o.id)}</span><span class="text-sm text-ink-500">${fmtDate(o.date)} · ${o.items.reduce((n, i) => n + i.qty, 0)} items · ${o.method === 'pickup' ? 'Store pickup' : 'Delivery'}</span></span>
              <span class="rounded-full bg-halal-50 px-2.5 py-1 text-xs font-bold text-halal-700">Placed</span>
              <span class="font-extrabold tabular-nums">${money(o.total)}</span>
              <i data-lucide="chevron-right" class="h-4 w-4 text-ink-500 transition-transform group-hover:translate-x-1" aria-hidden="true"></i>
            </a></li>`).join('')}
        </ul>`
        : emptyBlock('receipt', 'No orders yet', 'Orders you place on this device will appear here.', '#shop', 'Start shopping')}`, 'My account');
  }

  /* =====================================================
     INFO PAGES — template policy copy; review with your team before launch
     ===================================================== */
  const INFO_PAGES = {
    delivery: { title: 'Delivery & cold chain', icon: 'truck', body: `
      <h2>Where we deliver</h2>
      <p>We deliver across Lansdowne, Upper Darby and nearby ZIP codes in the Philadelphia suburbs and Philadelphia. <a href="#top">Check your ZIP code</a> on our home page. Outside our area? Free store pickup is always available at ${STORE_ADDR}.</p>
      <h2>Delivery times</h2>
      <ul>
        <li><strong>Same-day:</strong> order before ${cutoffLabel()} for delivery between 5 PM and 8 PM.</li>
        <li><strong>Next-day:</strong> later orders arrive the next day — choose a morning, afternoon or evening slot at checkout.</li>
        <li><strong>Qurbani &amp; Aqiqah:</strong> delivered within 48 hours of slaughter.</li>
      </ul>
      <h2>Delivery cost</h2>
      <p>Delivery is <strong>free on orders over ${money(CONFIG.freeShipAt)}</strong>. Below that, a flat ${money(CONFIG.shipFee)} cold-chain fee applies. Store pickup is always free.</p>
      <h2>How we keep it cold</h2>
      <p>Every order is cut to order, vacuum-sealed and labelled, then packed in an insulated box with ice packs so it stays at 0–4 °C from our chiller to your door.</p>
      <h2>When your order arrives</h2>
      <ul>
        <li>Our driver will call if you’re not home.</li>
        <li>Please refrigerate or freeze your order as soon as possible — within 2 hours of delivery.</li>
        <li>Check your order on arrival and tell us within 24 hours if anything isn’t right.</li>
      </ul>` },
    refund: { title: 'Refund & freshness policy', icon: 'shield-check', body: `
      <h2>Our freshness promise</h2>
      <p>We want every order to be perfect. If a product arrives damaged, isn’t fresh, or isn’t what you ordered, contact us <strong>within 24 hours of delivery</strong> with your order number and a photo, and we’ll replace it or refund you in full.</p>
      <h2>How refunds work</h2>
      <ul>
        <li>Refunds are made to your original payment method, or as store credit if you prefer.</li>
        <li>For food-safety reasons we can’t take back perishable items once delivered — a photo is all we need.</li>
      </ul>
      <h2>Cancellations</h2>
      <ul>
        <li><strong>Fresh orders</strong> can be cancelled free of charge until we start cutting them — call us as early as possible.</li>
        <li><strong>Custom cuts</strong> can’t be cancelled once prepared.</li>
        <li><strong>Qurbani pre-orders</strong> can be cancelled for a full refund up to 7 days before Eid.</li>
      </ul>` },
    halal: { title: 'Halal certification', icon: 'badge-check', body: `
      <h2>Our zabiha standard</h2>
      <p>Every animal and bird we sell is <strong>hand-slaughtered</strong> by a trained, practising Muslim, with a single swift cut of a sharp blade while reciting the Tasmiyah, facing the Qiblah.</p>
      <ul>
        <li>No stunning — before or after slaughter.</li>
        <li>No mechanical or machine slaughter.</li>
        <li>Animals are rested, handled calmly and fully bled.</li>
        <li>We sell no pork or non-halal products, and our equipment is never shared with non-halal meat.</li>
      </ul>
      <h2>Sourcing &amp; traceability</h2>
      <p>We buy from a small circle of partner family farms that we visit personally. Every pack is labelled with a batch code linking it to the farm, slaughter date and slaughterman.</p>
      <h2>Our certificate</h2>
      <p>Our halal certificate is displayed at our store at ${STORE_ADDR} and is available on request — call or WhatsApp us and we’ll send you a copy.</p>` },
    privacy: { title: 'Privacy policy', icon: 'lock', body: `
      <h2>What we collect</h2>
      <p>When you order or send a request, we collect your name, phone number, email, delivery address and order details. If you subscribe to our newsletter, we store your email address.</p>
      <h2>How we use it</h2>
      <ul>
        <li>To prepare, deliver and support your order.</li>
        <li>To contact you about your order, Qurbani or Aqiqah booking.</li>
        <li>To send our newsletter — only if you subscribed. You can unsubscribe anytime.</li>
      </ul>
      <h2>What we never do</h2>
      <p>We never sell or rent your personal information.</p>
      <h2>Stored on your device</h2>
      <p>This website remembers your cart, wishlist, weight unit, recently viewed items and saved checkout details in your browser’s local storage so they’re there next time. You can remove saved details from <a href="#/account">My account</a> or clear them in your browser settings.</p>
      <h2>Payments &amp; messages</h2>
      <p>Online card payments are handled by Stripe; we never receive or store your full card number. Order confirmations and updates are sent by email and text message.</p>
      <h2>Your choices</h2>
      <p>To see, correct or delete the information we hold about you, call or WhatsApp us and we’ll respond promptly.</p>` },
    terms: { title: 'Terms of service', icon: 'file-text', body: `
      <h2>Using this website</h2>
      <p>By placing an order you agree to these terms. Product photos are for illustration; natural variation in colour, marbling and shape is normal.</p>
      <h2>Prices &amp; weights</h2>
      <p>Fresh meat is priced by weight. Because every cut is prepared by hand, your pack may vary slightly from the weight you chose — we confirm the final weight and price before delivery.</p>
      <h2>Orders &amp; availability</h2>
      <p>Some cuts are limited to each day’s batch. If an item is unavailable, we’ll call you to offer a substitute or remove it from your order.</p>
      <h2>Payment</h2>
      <p>${CONFIG.payOnline ? `You can pay online at checkout by card, Apple Pay or Google Pay through our payment processor, Stripe${CONFIG.payOnArrival !== false ? ', or on delivery or at pickup by cash or card' : ''}. If the final weight of a fresh cut changes the price, we contact you before delivery and refund any difference to your original payment method.` : 'Payment is taken on delivery or at pickup, by cash or card.'}</p>
      <h2>Qurbani &amp; Aqiqah</h2>
      <p>Qurbani animals meet Udhiyah conditions and are slaughtered on Eid day after Salah. Final pricing for whole animals is confirmed once the animal is weighed. See our <a href="#/page/refund">refund &amp; cancellation policy</a>.</p>` },
  };
  function renderInfo(slug) {
    const pg = INFO_PAGES[slug];
    if (!pg) return renderMissing();
    setPage(`${pageHead(pg.title, '', 'Customer care')}
      <div class="mt-10 grid gap-8 lg:grid-cols-[260px_1fr] lg:gap-10">
        <nav aria-label="Customer care pages" class="min-w-0 lg:sticky lg:top-24 lg:self-start" data-noprint>
          <ul class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-col lg:px-0">
            ${Object.entries(INFO_PAGES).map(([k, v]) => `<li class="shrink-0"><a href="#/page/${k}" ${k === slug ? 'aria-current="page"' : ''} class="flex items-center gap-2.5 whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition ${k === slug ? 'bg-ink-900 text-white' : 'bg-white text-ink-700 ring-1 ring-bone-200 hover:text-brand-700'}"><i data-lucide="${v.icon}" class="h-4 w-4" aria-hidden="true"></i>${v.title}</a></li>`).join('')}
          </ul>
        </nav>
        <article class="doc min-w-0 max-w-3xl rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-10">
          ${pg.body}
          <p class="!mt-10 border-t border-bone-200 pt-5 text-sm">Questions? Call <a href="tel:+12673073777">(267) 307-3777</a>, message us on <a href="https://wa.me/12673073777" target="_blank" rel="noopener">WhatsApp</a>, or visit us at ${STORE_ADDR}.</p>
        </article>
      </div>`, pg.title);
  }

  /* =====================================================
     INIT
     ===================================================== */
  state.cart = state.cart.filter(i => byId(i.id));   // drop lines whose product no longer exists
  if (SERVER && state.coupon) {
    // Restore the applied code, then re-check it in the background (it may have expired)
    const info = store.get('hms-coupon-info', null);
    if (info && info.code === state.coupon) CONFIG.coupons[state.coupon] = info;
    api('coupon.php', { code: state.coupon }).then(r => { CONFIG.coupons[state.coupon] = r.coupon; store.set('hms-coupon-info', r.coupon); renderCart(); })
      .catch(err => { if (err.status === 404) { state.coupon = null; store.set('hms-coupon', null); renderCart(); } });
  }
  if (state.coupon && !CONFIG.coupons[state.coupon]) state.coupon = null;
  renderFaq();
  initReveals();
  updateWishCount();
  if (CONFIG.whatsapp) {
    const wa = $('#waBtn');
    wa.href = `https://wa.me/${CONFIG.whatsapp}?text=${encodeURIComponent('Assalamu alaikum! I have a question about an order.')}`;
    wa.hidden = false;
  }
  const savedZip = store.get('hms-zip', '');
  if (savedZip) $('#zipInput').value = savedZip;
  renderCarousel();
  renderTabs();
  renderGrid();
  renderCart();
  initCounters();
  tickCountdown();
  onScroll();
  icons();
  moveSvcInd(true);
  // Re-measure indicators once fonts/Tailwind styles settle
  function remeasure() {
    moveTabInd(true);
    moveSvcInd(true);
    if (view === 'pdp') placeInd($('#pdpTabInd'), $(`#pdp-tab-${pdpTab}`), $('#pdpTabs'), true);
  }
  document.fonts?.ready.then(remeasure);
  addEventListener('load', remeasure);
  addEventListener('resize', remeasure);

  // Routing: we manage scroll ourselves so Back returns to the exact spot in the shop
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
  route();
})();
