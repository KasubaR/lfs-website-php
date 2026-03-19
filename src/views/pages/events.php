<?php
/* ============================================================
   LFS — Lusaka Fitness Squad
   views/pages/events.php

   Variables provided by the events controller:
     $events  — array of event rows from the DB
   ============================================================ */

/* ── Helpers ─────────────────────────────────────────────── */
$now = new DateTime();

/**
 * Derive a display status from the event fields.
 * Returns ['key' => string, 'label' => string]
 */
function getEventStatus(array $ev, DateTime $now): array {
  $eDate    = !empty($ev['eventDate'])          ? new DateTime($ev['eventDate'])          : null;
  $regOpen  = !empty($ev['registrationOpen'])   ? new DateTime($ev['registrationOpen'])   : null;
  $regClose = !empty($ev['registrationClose'])  ? new DateTime($ev['registrationClose'])  : null;

  if (!$eDate)                                             return ['key' => 'upcoming',  'label' => 'Upcoming'];
  if ($eDate < $now)                                       return ['key' => 'completed', 'label' => 'Completed'];
  if (!empty($ev['registrationFull']))                     return ['key' => 'full',      'label' => 'Registration Full'];
  if ($regClose && $regClose < $now)                       return ['key' => 'closed',    'label' => 'Registration Closed'];
  if ($regOpen  && $regOpen <= $now && (!$regClose || $regClose >= $now))
                                                           return ['key' => 'open',      'label' => 'Registration Open'];
  return ['key' => 'upcoming', 'label' => 'Upcoming'];
}

/* ── Partition events ────────────────────────────────────── */
$allEvents = $events ?? [];

$upcomingList = array_filter($allEvents, fn($ev) =>
  !empty($ev['eventDate']) && new DateTime($ev['eventDate']) >= $now
);
$pastList = array_filter($allEvents, fn($ev) =>
  !empty($ev['eventDate']) && new DateTime($ev['eventDate']) < $now
);

/* Sort upcoming ASC, past DESC */
usort($upcomingList, fn($a, $b) => new DateTime($a['eventDate']) <=> new DateTime($b['eventDate']));
usort($pastList,     fn($a, $b) => new DateTime($b['eventDate']) <=> new DateTime($a['eventDate']));

$upcomingList = array_values($upcomingList);
$pastList     = array_values($pastList);

/* ── Derive filter options from data ─────────────────────── */
$yearSet = [];
foreach ($allEvents as $ev) {
  if (!empty($ev['eventDate'])) {
    $yearSet[(new DateTime($ev['eventDate']))->format('Y')] = 1;
  }
}
$years = array_keys($yearSet);
rsort($years);

$locationSet = [];
foreach ($allEvents as $ev) {
  if (!empty($ev['location'])) {
    $locKey = trim(explode(',', $ev['location'])[0]);
    $locationSet[$locKey] = 1;
  }
}
$locations = array_keys($locationSet);
sort($locations);
?>


<!-- ══════════════════════════════════════════════
     1. PAGE HEADER
     ══════════════════════════════════════════════ -->
<header class="events-header">
  <div class="events-header__inner">

    <nav class="events-breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="/">Home</a></li>
        <li><i class="fas fa-chevron-right" aria-hidden="true"></i></li>
        <li>Events &amp; Races</li>
      </ol>
    </nav>

    <span class="section-label light" data-reveal>
      LFS Calendar
    </span>
    <h1 class="font-['Bebas_Neue'] text-5xl md:text-6xl leading-tight text-white mt-2" data-reveal>
      Events &amp; Races
    </h1>
    <p class="events-header__desc" data-reveal>
      Join Lusaka Fitness Squad races, training runs, and community events.
      From Saturday LSDs to flagship half marathons — there's always a reason to run.
    </p>

    <!-- Quick stats -->
    <div class="stat-row mt-8" data-reveal>
      <div class="stat-item">
        <div class="stat-item__num"><?= count($upcomingList) ?></div>
        <div class="stat-item__label">Upcoming</div>
      </div>
      <div class="stat-item">
        <div class="stat-item__num"><?= count($pastList) ?></div>
        <div class="stat-item__label">Past Events</div>
      </div>
      <div class="stat-item">
        <div class="stat-item__num">52</div>
        <div class="stat-item__label">LSDs / Year</div>
      </div>
    </div>

  </div>
</header>


<!-- ══════════════════════════════════════════════
     2. FILTER BAR (collapsible on mobile, not sticky)
     ══════════════════════════════════════════════ -->
<div class="events-filter-bar" id="events-filter-bar" role="search" aria-label="Filter events">
  <button type="button" class="events-filter-bar__toggle" id="events-filter-toggle" aria-expanded="false" aria-controls="events-filter-panel">
    <i class="fas fa-filter" aria-hidden="true"></i>
    <span>Filters</span>
    <span id="events-filter-count-toggle" class="events-filter-bar__count-inline" aria-hidden="true"><?= count($allEvents) ?></span>
    <i class="fas fa-chevron-down events-filter-bar__toggle-chevron" aria-hidden="true"></i>
  </button>
  <div class="events-filter-bar__panel" id="events-filter-panel">
    <div class="events-filter-bar__inner">

    <span class="events-filter-bar__label">
      <i class="fas fa-filter" aria-hidden="true"></i> Filter
    </span>

    <!-- Event Type / Category -->
    <div class="events-filter-bar__select-wrap">
      <select class="events-filter-bar__select" data-filter="category" aria-label="Filter by event type">
        <option value="">All Types</option>
        <option value="Road Race">Road Race</option>
        <option value="LSD">LSD Run</option>
        <option value="Training">Weekly Run</option>
        <option value="Training Camp">Training Camp</option>
        <option value="Social">Social Run</option>
        <option value="Other">Other</option>
      </select>
      <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </div>

    <!-- Distance -->
    <div class="events-filter-bar__select-wrap">
      <select class="events-filter-bar__select" data-filter="distance" aria-label="Filter by distance">
        <option value="">All Distances</option>
        <option value="5K">5K</option>
        <option value="10K">10K</option>
        <option value="21">Half Marathon</option>
        <option value="42">Marathon</option>
        <option value="Other">Other</option>
      </select>
      <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </div>

    <!-- Year -->
    <div class="events-filter-bar__select-wrap">
      <select class="events-filter-bar__select" data-filter="year" aria-label="Filter by year">
        <option value="">All Years</option>
        <?php foreach ($years as $yr): ?>
        <option value="<?= htmlspecialchars($yr) ?>"><?= htmlspecialchars($yr) ?></option>
        <?php endforeach; ?>
      </select>
      <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </div>

    <!-- Location -->
    <div class="events-filter-bar__select-wrap">
      <select class="events-filter-bar__select" data-filter="location" aria-label="Filter by location">
        <option value="">All Locations</option>
        <?php foreach ($locations as $loc): ?>
        <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
        <?php endforeach; ?>
      </select>
      <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </div>

    <button id="events-filter-clear" class="events-filter-bar__clear" type="button" aria-label="Clear all filters">
      <i class="fas fa-times" aria-hidden="true"></i> Clear
    </button>

    <span id="events-filter-count" class="events-filter-bar__count" aria-live="polite">
      <?= count($allEvents) ?> event<?= count($allEvents) !== 1 ? 's' : '' ?>
    </span>

  </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     3. EVENTS BODY
     ══════════════════════════════════════════════ -->
<div class="events-body">
  <div class="events-body__inner">


    <!-- ── UPCOMING EVENTS ─────────────────────────────────── -->
    <section class="events-section" id="upcoming" aria-labelledby="upcoming-heading">

      <div class="events-section__heading">
        <div>
          <span class="section-label">Coming Up</span>
          <h2 class="font-['Bebas_Neue'] text-4xl md:text-5xl" id="upcoming-heading">Upcoming Events</h2>
        </div>
        <a href="/contact" class="btn btn-primary btn-sm hidden md:inline-flex">
          <i class="fas fa-envelope" aria-hidden="true"></i> Register Interest
        </a>
      </div>

      <div class="events-grid" role="list" aria-label="Upcoming events">

        <?php if (count($upcomingList) === 0): ?>
        <div id="upcoming-empty" class="events-empty" style="grid-column:1/-1">
          <div class="events-empty__icon"><i class="fas fa-calendar-xmark" aria-hidden="true"></i></div>
          <div class="events-empty__heading">No Upcoming Events</div>
          <p class="events-empty__desc">Check back soon — new events are added regularly.</p>
        </div>

        <?php else: ?>

        <!-- Hidden empty state (shown by JS when filters match nothing) -->
        <div id="upcoming-empty" class="events-empty" style="grid-column:1/-1" hidden>
          <div class="events-empty__icon"><i class="fas fa-filter" aria-hidden="true"></i></div>
          <div class="events-empty__heading">No Matching Events</div>
          <p class="events-empty__desc">Try adjusting your filters to see more events.</p>
        </div>

        <?php foreach ($upcomingList as $i => $ev):
          $status   = getEventStatus($ev, $now);
          $evYear   = !empty($ev['eventDate']) ? (new DateTime($ev['eventDate']))->format('Y') : '';
          $evLocKey = !empty($ev['location'])  ? trim(explode(',', $ev['location'])[0]) : '';
          $evDist   = $ev['distance'] ?? '';

          $dateObj  = !empty($ev['eventDate']) ? new DateTime($ev['eventDate']) : null;
          $dateStr  = $dateObj
            ? $dateObj->format('D, j F Y')   // e.g. "Sat, 14 June 2025"
            : 'TBA';
        ?>
        <article class="event-card"
          data-type="upcoming"
          data-category="<?= htmlspecialchars($ev['category'] ?? '') ?>"
          data-distance="<?= htmlspecialchars($evDist) ?>"
          data-year="<?= htmlspecialchars($evYear) ?>"
          data-location="<?= htmlspecialchars($evLocKey) ?>"
          role="listitem"
          <?= $i >= 6 ? 'hidden' : '' ?>>

          <!-- Banner image -->
          <div class="event-card__image">
            <?php if (!empty($ev['bannerImage'])): ?>
            <img src="<?= htmlspecialchars($ev['bannerImage']) ?>" alt="<?= htmlspecialchars($ev['title']) ?>" loading="lazy">
            <?php else: ?>
            <div class="event-card__image-placeholder" aria-hidden="true">
              <i class="fas fa-person-running"></i>
              <span>LFS</span>
            </div>
            <?php endif; ?>
            <span class="event-card__status event-card__status--<?= htmlspecialchars($status['key']) ?>">
              <?= htmlspecialchars($status['label']) ?>
            </span>
            <?php if (!empty($ev['category'])): ?>
            <span class="event-card__category"><?= htmlspecialchars($ev['category']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Body -->
          <div class="event-card__body">
            <h3 class="event-card__title"><?= htmlspecialchars($ev['title']) ?></h3>

            <div class="event-card__meta">
              <div class="event-card__meta-row">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                <span><?= htmlspecialchars($dateStr) ?></span>
              </div>
              <?php if (!empty($ev['location'])): ?>
              <div class="event-card__meta-row">
                <i class="fas fa-map-pin" aria-hidden="true"></i>
                <span><?= htmlspecialchars($ev['location']) ?></span>
              </div>
              <?php endif; ?>
              <?php if (!empty($ev['distance'])): ?>
              <div class="event-card__meta-row">
                <i class="fas fa-route" aria-hidden="true"></i>
                <span><?= htmlspecialchars($ev['distance']) ?></span>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($ev['description'])): ?>
            <p class="event-card__desc"><?= htmlspecialchars($ev['description']) ?></p>
            <?php endif; ?>

            <div class="event-card__footer">
              <?php
              $detailUrl = !empty($ev['slug'])
                ? '/events/' . htmlspecialchars($ev['slug'])
                : htmlspecialchars($ev['link'] ?? '/contact');
              ?>
              <a href="<?= $detailUrl ?>" class="btn btn-primary w-full justify-center"
                aria-label="View details for <?= htmlspecialchars($ev['title']) ?>">
                View Details <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </a>
            </div>
          </div>

        </article>
        <?php endforeach; ?>
        <?php endif; ?>

      </div><!-- /.events-grid -->

      <div class="events-load-more">
        <button id="upcoming-load-more" class="btn btn-outline" type="button" style="color:var(--black);border-color:rgba(0,0,0,0.2)" hidden>
          <i class="fas fa-chevron-down" aria-hidden="true"></i> Load More Events
        </button>
      </div>

    </section>


    <!-- ── PAST EVENTS ─────────────────────────────────────── -->
    <section class="events-section" id="past" aria-labelledby="past-heading">

      <div class="events-section__heading">
        <div>
          <span class="section-label">Archive</span>
          <h2 class="font-['Bebas_Neue'] text-4xl md:text-5xl" id="past-heading">Past Events</h2>
        </div>
      </div>

      <div class="past-events-list" role="list" aria-label="Past events">

        <?php if (count($pastList) === 0): ?>
        <div id="past-empty" class="events-empty" style="grid-column:1/-1">
          <div class="events-empty__icon"><i class="fas fa-calendar" aria-hidden="true"></i></div>
          <div class="events-empty__heading">No Past Events Yet</div>
          <p class="events-empty__desc">Past events will appear here after they take place.</p>
        </div>

        <?php else: ?>

        <div id="past-empty" class="events-empty" style="grid-column:1/-1" hidden>
          <div class="events-empty__icon"><i class="fas fa-filter" aria-hidden="true"></i></div>
          <div class="events-empty__heading">No Matching Events</div>
          <p class="events-empty__desc">Try adjusting your filters.</p>
        </div>

        <?php foreach ($pastList as $i => $ev):
          $evYear   = !empty($ev['eventDate']) ? (new DateTime($ev['eventDate']))->format('Y') : '';
          $evLocKey = !empty($ev['location'])  ? trim(explode(',', $ev['location'])[0]) : '';
          $dateObj  = !empty($ev['eventDate']) ? new DateTime($ev['eventDate']) : null;
          $dateStr  = $dateObj ? $dateObj->format('j F Y') : 'TBA';
        ?>
        <article class="past-event-card"
          data-category="<?= htmlspecialchars($ev['category'] ?? '') ?>"
          data-distance="<?= htmlspecialchars($ev['distance'] ?? '') ?>"
          data-year="<?= htmlspecialchars($evYear) ?>"
          data-location="<?= htmlspecialchars($evLocKey) ?>"
          role="listitem"
          <?= $i >= 6 ? 'hidden' : '' ?>>

          <div class="past-event-card__date">
            <i class="fas fa-calendar-check" aria-hidden="true"></i>
            <?= htmlspecialchars($dateStr) ?>
          </div>

          <h3 class="past-event-card__title"><?= htmlspecialchars($ev['title']) ?></h3>

          <div class="past-event-card__meta">
            <?php if (!empty($ev['location'])): ?>
            <i class="fas fa-map-pin" aria-hidden="true"></i>
            <span><?= htmlspecialchars($ev['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ev['distance'])): ?>
            &nbsp;·&nbsp;
            <i class="fas fa-route" aria-hidden="true"></i>
            <span><?= htmlspecialchars($ev['distance']) ?></span>
            <?php endif; ?>
          </div>

          <div class="past-event-card__actions">
            <a href="<?= htmlspecialchars($ev['resultsLink'] ?? '/contact') ?>"
              class="past-event-card__link past-event-card__link--results"
              aria-label="View results for <?= htmlspecialchars($ev['title']) ?>">
              <i class="fas fa-trophy" aria-hidden="true"></i> Results
            </a>
            <a href="<?= htmlspecialchars($ev['photosLink'] ?? '/gallery') ?>"
              class="past-event-card__link past-event-card__link--photos"
              aria-label="View photos for <?= htmlspecialchars($ev['title']) ?>">
              <i class="fas fa-camera" aria-hidden="true"></i> Photos
            </a>
          </div>

        </article>
        <?php endforeach; ?>
        <?php endif; ?>

      </div><!-- /.past-events-list -->

      <div class="events-load-more">
        <button id="past-load-more" class="btn btn-outline" type="button" style="color:var(--black);border-color:rgba(0,0,0,0.2)" hidden>
          <i class="fas fa-chevron-down" aria-hidden="true"></i> Load More
        </button>
      </div>

    </section>


  </div><!-- /.events-body__inner -->
</div><!-- /.events-body -->


<!-- ══════════════════════════════════════════════
     4. CTA
     ══════════════════════════════════════════════ -->
<section class="py-16 px-6 md:px-16 text-white text-center relative overflow-hidden"
  style="background:var(--dark-green)">
  <div class="absolute font-['Bebas_Neue'] text-[25vw] inset-0 flex items-center justify-center pointer-events-none select-none"
    style="color:rgba(255,255,255,0.04)" aria-hidden="true">RUN</div>
  <div class="relative z-10 max-w-2xl mx-auto" data-reveal>
    <span class="section-label light justify-center">
      Join The Squad
    </span>
    <h2 class="font-['Bebas_Neue'] text-4xl md:text-6xl text-white mt-3">
      Ready to Race?
    </h2>
    <p class="mt-4 text-white/60 text-base leading-relaxed">
      Every Saturday we run — rain or shine. Join LFS and never run alone.
    </p>
    <div class="flex flex-wrap gap-4 justify-center mt-7">
      <a href="/contact" class="btn btn-primary">
        <i class="fas fa-id-card" aria-hidden="true"></i> Register Interest
      </a>
      <a href="/about" class="btn btn-outline">
        About LFS <i class="fas fa-arrow-right" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</section>
