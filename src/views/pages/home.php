<?php
/**
 * LFS HOME PAGE — pages/home.php
 *
 * Variables expected from controller:
 *   $events         array   — upcoming events (may be empty)
 *   $galleryPreview array   — gallery photos for preview (may be empty)
 *   $products       array   — shop products (may be empty; shop preview shows empty state)
 *   $posts          array   — news/blog posts (may be empty, falls back to defaults)
 */
$events         = $events         ?? [];
$galleryPreview = $galleryPreview ?? [];
$products       = $products       ?? [];
$posts          = $posts          ?? [];
$heroSlides     = $heroSlides     ?? [];

// Build ordered URL list from slider media; fall back to static image
$_heroDefault = '/images/LSD07.02.2026-3.jpg';
$_slideUrls   = array_values(array_filter(array_map(function (array $s): string {
    return $s['urls']['large'] ?? $s['urls']['original'] ?? $s['urls']['medium'] ?? '';
}, $heroSlides)));
if (empty($_slideUrls)) {
    $_slideUrls = [$_heroDefault];
}
?>

<!-- ══════════════════════════════════════════════
     1. HERO
     ══════════════════════════════════════════════ -->
<section id="hero"
  class="home-hero home-hero--compact relative overflow-hidden flex flex-col justify-center">

  <div class="home-hero__bg" id="heroSliderBg" aria-hidden="true">
    <?php foreach ($_slideUrls as $_i => $_url): ?>
      <img
        src="<?= htmlspecialchars($_url) ?>"
        class="home-hero__slide<?= $_i === 0 ? ' home-hero__slide--active' : '' ?>"
        alt=""
        loading="<?= $_i === 0 ? 'eager' : 'lazy' ?>"
        aria-hidden="true"
      >
    <?php endforeach; ?>
  </div>

  <div class="absolute inset-0 bg-black/60 z-[1]" aria-hidden="true"></div>

  <div class="relative z-10 px-6 lg:px-16 py-16 lg:py-20 max-w-4xl">
    <div class="home-hero__content">
      <h1 class="font-['Bebas_Neue'] text-5xl sm:text-6xl lg:text-7xl leading-tight text-white mt-6 animate-fadeUp"
        style="animation-delay:0.15s">
        Zambia's Biggest<br>
        Running Community
      </h1>

      <p class="mt-5 text-white text-base font-light leading-relaxed max-w-xl animate-fadeUp"
        style="animation-delay:0.3s">
        Train. Run. Compete. Together.&nbsp; LFS is a vibrant squad of runners, dreamers and doers
        pushing each other forward, every single stride, across six satellites in Lusaka.
      </p>

      <div class="flex flex-wrap gap-4 mt-6 animate-fadeUp" style="animation-delay:0.45s">
        <a href="https://squidal.com/lfsmembership" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
          Join LFS
        </a>
        <a href="/shop" class="btn btn-outline">
          Shop
        </a>
      </div>

      <div class="stat-row animate-fadeUp mt-6" style="animation-delay:0.6s" aria-label="LFS at a glance">
        <div class="stat-item">
          <div class="stat-item__num" data-count="6" data-suffix="">6</div>
          <div class="stat-item__label">Satellites</div>
        </div>
        <div class="stat-item">
          <div class="stat-item__num" data-count="7" data-suffix="+">7+</div>
          <div class="stat-item__label">Years Running</div>
        </div>
        <div class="stat-item">
          <div class="stat-item__num" data-count="52" data-suffix="">52</div>
          <div class="stat-item__label">LSDs / Year</div>
        </div>
      </div>
    </div>
  </div>

  <?php if (count($_slideUrls) > 1): ?>
  <!-- Dot indicators -->
  <div class="hero-slider-dots" aria-hidden="true" id="heroSliderDots">
    <?php foreach ($_slideUrls as $_i => $_url): ?>
      <button class="hero-slider-dot<?= $_i === 0 ? ' hero-slider-dot--active' : '' ?>"
              type="button" data-index="<?= $_i ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</section>

<script>
(function () {
  const slides = document.querySelectorAll('.home-hero__slide');
  const dots   = document.querySelectorAll('.hero-slider-dot');
  if (slides.length <= 1) return;

  let current   = 0;
  let timer     = null;
  const INTERVAL = 6000; // ms between slides
  const FADE_MS  = 1000; // must match CSS transition duration

  function goTo(idx) {
    slides[current].classList.remove('home-hero__slide--active');
    if (dots[current]) dots[current].classList.remove('hero-slider-dot--active');
    current = (idx + slides.length) % slides.length;
    slides[current].classList.add('home-hero__slide--active');
    if (dots[current]) dots[current].classList.add('hero-slider-dot--active');
  }

  function startTimer() {
    clearInterval(timer);
    timer = setInterval(function () { goTo(current + 1); }, INTERVAL);
  }

  // Dot clicks
  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      goTo(parseInt(this.dataset.index, 10));
      startTimer(); // reset interval on manual nav
    });
  });

  startTimer();
}());
</script>


<!-- ══════════════════════════════════════════════
     2. ABOUT SNAPSHOT
     ══════════════════════════════════════════════ -->
<section id="about" class="py-20 px-6 md:px-16 grid md:grid-cols-2 gap-12 items-center bg-lfs-warm-white">

  <!-- Stacked images -->
  <div class="relative h-[500px]">
    <img src="/images/whoweare.jpg"
      alt="Who we are, LFS community" class="w-3/4 h-[420px] object-cover rounded absolute top-0 left-0 shadow-lg" loading="lazy">
    <img src="/images/LFSKafueRun2025-13.jpg"
      alt="LFS Kafue Run 2025"
      class="w-3/5 h-72 object-cover rounded absolute bottom-0 right-0 border-8 border-[#faf8f4] shadow-lg"
      loading="lazy">
    <!-- Years badge -->
    <div
      class="absolute top-1/2 left-3/4 -translate-x-1/2 -translate-y-1/2 w-24 h-24 rounded-full flex flex-col items-center justify-center border-4 border-[#faf8f4] z-10"
      style="background:var(--dark-green)" aria-label="7+ years of running">
      <span class="font-['Bebas_Neue'] text-3xl" style="color:var(--green-bright)">7+</span>
      <span class="text-[0.6rem] tracking-widest text-white/70 uppercase">Years</span>
    </div>
  </div>

  <!-- Copy -->
  <div>
    <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl leading-tight">
      More Than A<br>Running Club
    </h2>
    <p class="text-[#6b6b6b] text-base leading-relaxed mt-4">
      LFS is a vibrant community of fitness enthusiasts from different parts of the world, coming together
      to stay active, support one another, and grow stronger as a team. Whether you're just starting
      out or a seasoned runner, there's a place for you here.
    </p>

    <!-- Flag-coloured value pillars -->
    <div class="grid grid-cols-2 gap-4 mt-6">
      <div class="pillar">
        <div class="pillar__title"><i class="fas fa-medal" style="color:var(--flag-green)" aria-hidden="true"></i>
          Biggest Club</div>
        <p class="pillar__body">Zambia's #1 fitness community with runners across Lusaka</p>
      </div>
      <div class="pillar red">
        <div class="pillar__title"><i class="fas fa-calendar-star" style="color:var(--flag-red)" aria-hidden="true"></i>
          Pro Events</div>
        <p class="pillar__body">7+ years managing Zambia's premier running events</p>
      </div>
      <div class="pillar orange">
        <div class="pillar__title"><i class="fas fa-universal-access" style="color:var(--flag-orange)"
            aria-hidden="true"></i> Inclusive</div>
        <p class="pillar__body">All paces, all levels, everyone belongs at LFS</p>
      </div>
      <div class="pillar">
        <div class="pillar__title"><i class="fas fa-chart-line" style="color:var(--flag-green)" aria-hidden="true"></i>
          Structured</div>
        <p class="pillar__body">Satellite captains run weekly training programs</p>
      </div>
    </div>

    <a href="https://squidal.com/lfsmembership" class="btn btn-primary mt-8" target="_blank" rel="noopener noreferrer">
      <i class="fas fa-id-card" aria-hidden="true"></i> Become a Member
    </a>
  </div>

</section>


<!-- ══════════════════════════════════════════════
     3. ACTIVITIES HIGHLIGHTS
     ══════════════════════════════════════════════ -->
<section id="activities" class="py-20 px-6 md:px-16 bg-black text-white">
  <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl text-white" data-reveal>
    Squad<br>Activities
  </h2>

  <div class="grid md:grid-cols-3 gap-px mt-12"
    style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.08)">

    <!-- LSD Runs (wide) -->
    <div class="activity-card md:col-span-2" data-reveal data-reveal-delay="1">
      <div class="activity-card__num">01</div>
      <i class="activity-card__icon fas fa-person-running" aria-hidden="true"></i>
      <div class="activity-card__title">Saturday LSD Runs</div>
      <p class="activity-card__body">
        Every Saturday, all six satellites unite for a Long Slow Distance run at a rotating host location.
        Inclusive, low-pressure, and built for all fitness levels, the weekly highlight of LFS life.
      </p>
    </div>

    <!-- Weekly satellite runs -->
    <div class="activity-card" data-reveal data-reveal-delay="2">
      <div class="activity-card__num">02</div>
      <i class="activity-card__icon fas fa-calendar-alt" aria-hidden="true"></i>
      <div class="activity-card__title">Weekly Satellite Runs</div>
      <p class="activity-card__body">
        Training sessions within your local satellite every week, guided by your captain's structured program.
      </p>
    </div>

    <!-- Race events -->
    <div class="activity-card" data-reveal data-reveal-delay="1">
      <div class="activity-card__num">03</div>
      <i class="activity-card__icon fas fa-trophy" aria-hidden="true"></i>
      <div class="activity-card__title">Race Events</div>
      <p class="activity-card__body">
        LFS manages Zambia's biggest running events, 7+ years of delivering world-class race experiences
        across multiple distances.
      </p>
    </div>

    <!-- Volunteering -->
    <div class="activity-card" data-reveal data-reveal-delay="2">
      <div class="activity-card__num">04</div>
      <i class="activity-card__icon fas fa-hand-holding-heart" aria-hidden="true"></i>
      <div class="activity-card__title">Volunteering</div>
      <p class="activity-card__body">
        Every member serves at least once per year. LFS runs because members show up and give back.
      </p>
    </div>

    <!-- Community events -->
    <div class="activity-card" data-reveal data-reveal-delay="3">
      <div class="activity-card__num">05</div>
      <i class="activity-card__icon fas fa-champagne-glasses" aria-hidden="true"></i>
      <div class="activity-card__title">Community Events</div>
      <p class="activity-card__body">
        Annual AGM, social gatherings, celebration runs, and more. A community that thrives beyond the road.
      </p>
    </div>

  </div>

</section>


<!-- ══════════════════════════════════════════════
     4. UPCOMING EVENTS
     ══════════════════════════════════════════════ -->
<section id="events" class="py-20 px-6 md:px-16 bg-lfs-off-white">

  <div class="flex flex-wrap justify-between items-end gap-4 mb-12">
    <div data-reveal>
      <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl">Event<br>Calendar</h2>
    </div>
    <a href="/events" class="btn btn-primary" data-reveal="right">View All Events</a>
  </div>

  <div class="events-list <?= empty($events) ? 'events-list--empty' : '' ?>" role="list">
    <?php if (empty($events)): ?>
      <div class="events-empty">
        <div class="events-empty__icon"><i class="fas fa-calendar-xmark" aria-hidden="true"></i></div>
        <div class="events-empty__heading">No Upcoming Events</div>
        <p class="events-empty__desc">Check back soon, new events are added regularly. View the full calendar for past events.</p>
        <a href="/events" class="btn btn-primary events-empty__cta">View Events</a>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($events, 0, 5) as $idx => $ev): ?>
        <?php
          $dateParts = $ev['date'] ? explode(' ', $ev['date']) : [];
          $dayNum    = isset($dateParts[1]) ? rtrim($dateParts[1], ',') : '--';
          $month     = isset($dateParts[2]) ? strtoupper(substr($dateParts[2], 0, 3)) : 'TBA';
          $tagColor  = $ev['tagColor'] ?? '';
          $tagClass  = in_array($tagColor, ['orange', 'red', 'gold']) ? $tagColor : '';
          $delay     = ($idx % 3) + 1;
        ?>
        <article class="event-card" data-reveal data-reveal-delay="<?= $delay ?>" role="listitem">
          <div class="event-card__date-block">
            <span class="event-card__month"><?= htmlspecialchars($month) ?></span>
            <span class="event-card__day"><?= htmlspecialchars($dayNum) ?></span>
          </div>
          <div class="event-card__body">
            <div class="event-card__tags">
              <span class="badge <?= $tagClass ?>">
                <?= htmlspecialchars($ev['tag'] ?? 'Run') ?>
              </span>
              <span class="event-card__distance">
                <i class="fas fa-route mr-1" aria-hidden="true"></i>
                <?= htmlspecialchars($ev['distance'] ?? '') ?>
              </span>
            </div>
            <h3 class="event-card__title"><?= htmlspecialchars($ev['title']) ?></h3>
            <p class="event-card__meta">
              <i class="fas fa-map-pin mr-1" aria-hidden="true"></i><?= htmlspecialchars($ev['location'] ?? '') ?>
              &nbsp;·&nbsp;
              <i class="fas fa-calendar mr-1" aria-hidden="true"></i><?= htmlspecialchars($ev['date'] ?? '') ?>
            </p>
          </div>
          <div class="event-card__actions">
            <a href="<?= htmlspecialchars($ev['link'] ?? '/events') ?>" class="btn btn-primary btn-sm">
              View <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</section>


<!-- ══════════════════════════════════════════════
     5. MEMBERSHIP CTA
     ══════════════════════════════════════════════ -->
<section id="membership" class="py-20 px-6 md:px-16 text-white relative overflow-hidden"
  style="background:var(--dark-green)">
  <!-- Background display text -->
  <div class="absolute font-['Bebas_Neue'] text-[30vw] right-0 top-0 pointer-events-none select-none leading-none"
    style="color:rgba(255,255,255,0.04)" aria-hidden="true">LFS</div>

  <div class="grid md:grid-cols-2 gap-12 items-center relative z-10">

    <!-- Benefits copy -->
    <div data-reveal="left">
      <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl text-white">Become A<br>Full Member</h2>
      <p class="text-white/60 text-base leading-relaxed mt-4">
        Your annual membership keeps you connected to the full LFS experience,
        from official WhatsApp groups to voting rights at the AGM.
      </p>
      <ul class="membership-list" aria-label="Membership benefits">
        <li>
          <span class="membership-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
          Access to "LFS Full Members" WhatsApp group
        </li>
        <li>
          <span class="membership-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
          Access to "The HUB" announcements channel
        </li>
        <li>
          <span class="membership-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
          Voting rights at the Annual General Meeting
        </li>
        <li>
          <span class="membership-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
          Priority registration for LFS-managed events
        </li>
        <li>
          <span class="membership-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
          Official LFS community recognition &amp; jersey eligibility
        </li>
      </ul>
    </div>

    <!-- Pricing card -->
    <div class="price-card" data-reveal="right">
      <div class="text-center text-xs tracking-widest" style="color:var(--green-bright)">Annual Membership 2026</div>
      <div class="text-center mt-4">
        <span class="font-['Bebas_Neue'] text-5xl align-top mt-2 inline-block"
          style="color:var(--green-bright)">K</span>
        <span class="font-['Bebas_Neue'] text-7xl text-white">1,000</span>
      </div>
      <div class="text-sm text-center mt-1" style="color:rgba(255,255,255,0.4)">per year · renewed annually</div>
      <hr class="my-5" style="border-color:rgba(255,255,255,0.1)">
      <a href="https://squidal.com/lfsmembership" class="btn btn-primary w-full justify-center" target="_blank" rel="noopener noreferrer">
        Pay Membership Fee
      </a>
      <p class="text-center mt-3 text-xs" style="color:rgba(255,255,255,0.4)">
        Contact the LFS Treasurer to make payment
      </p>
      <hr class="my-4" style="border-color:rgba(255,255,255,0.1)">
      <div class="badge gold inline-flex">
        <i class="far fa-clock mr-1" aria-hidden="true"></i> Deadline: End of April each year
      </div>
    </div>

  </div>
</section>


<!-- ══════════════════════════════════════════════
     7. SHOP PREVIEW
     ══════════════════════════════════════════════ -->
<?php require __DIR__ . '/../partials/shop-preview.php'; ?>


<!-- ══════════════════════════════════════════════
     8. NEWS & UPDATES
     ══════════════════════════════════════════════ -->
<?php $newsBase = (defined('BASE_PATH') ? BASE_PATH : '') . '/news'; ?>
<section id="news" class="py-20 px-6 md:px-16 bg-black text-white">

  <div class="flex flex-wrap justify-between items-end gap-4 mb-12">
    <div data-reveal>
      <h2 class="font-['Bebas_Neue'] text-5xl md:text-6xl text-white">News &amp;<br>Updates</h2>
    </div>
    <?php if (!empty($posts)): ?>
    <a href="<?= htmlspecialchars($newsBase) ?>" class="btn btn-outline" data-reveal="right">View All Posts</a>
    <?php endif; ?>
  </div>

  <?php if (empty($posts)): ?>
  <div class="news-empty" data-reveal>
    <h3 class="news-empty__title">No stories yet</h3>
    <p class="news-empty__text">
      Club news, race reports, and updates will appear here. Check back soon or visit the news page.
    </p>
    <a href="<?= htmlspecialchars($newsBase) ?>" class="btn btn-outline news-empty__cta">
      Visit News <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
    </a>
  </div>
  <?php else: ?>
  <div class="grid md:grid-cols-3 gap-6">
    <?php foreach (array_slice($posts, 0, 3) as $idx => $post): ?>
      <article class="news-card" data-reveal data-reveal-delay="<?= $idx + 1 ?>">
        <a href="<?= htmlspecialchars($post['link'] ?? '#') ?>" class="news-card__image-link" aria-label="Read: <?= htmlspecialchars($post['title']) ?>">
          <div class="news-card__image">
            <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
            <div class="news-card__category"><?= htmlspecialchars($post['category']) ?></div>
          </div>
        </a>
        <div class="news-card__body">
          <div class="news-card__date">
            <i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i>
            <?= htmlspecialchars($post['date']) ?>
          </div>
          <h3 class="news-card__title">
            <a href="<?= htmlspecialchars($post['link'] ?? '#') ?>"><?= htmlspecialchars($post['title']) ?></a>
          </h3>
          <p class="news-card__excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
          <a href="<?= htmlspecialchars($post['link'] ?? '#') ?>" class="news-card__read-more">
            Read More <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
